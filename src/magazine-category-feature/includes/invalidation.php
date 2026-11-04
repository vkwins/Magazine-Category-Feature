<?php
/**
 * Drops cached post IDs when a post's place in a category can change,
 * then asks the page cache to forget URLs that embed this block.
 *
 * The object cache is not the cache that serves two million homepage views.
 * CloudFront and WP Engine's page cache serve HTML without running PHP.
 * Clearing post IDs only helps the next uncached render. The purge at the
 * bottom is what makes a new article show up on a cached landing page.
 *
 * @package MagazineCategoryFeature
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_post_update', 'magazine_category_feature_remember_categories' );
add_action( 'save_post_post', 'magazine_category_feature_refresh_post_categories' );
add_action( 'wp_trash_post', 'magazine_category_feature_refresh_post_categories' );
add_action( 'untrash_post', 'magazine_category_feature_refresh_post_categories' );
add_action( 'before_delete_post', 'magazine_category_feature_refresh_post_categories' );
add_action( 'delete_category', 'magazine_category_feature_forget_category' );

/**
 * Stores the categories a post had before this save.
 *
 * save_post only sees the new terms. Without this, moving a post from
 * Gardening to News would leave Gardening's cached five IDs in place.
 */
function magazine_category_feature_remember_categories( int $post_id ): void {
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	magazine_category_feature_previous_categories( $post_id, wp_get_post_categories( $post_id ) );
}

/**
 * Reads or stores the category IDs captured in pre_post_update.
 *
 * @param int        $post_id Post ID.
 * @param int[]|null $set     Pass an array to store. Omit it to read.
 * @return int[]
 */
function magazine_category_feature_previous_categories( int $post_id, ?array $set = null ): array {
	static $stored = array();

	if ( is_array( $set ) ) {
		$stored[ $post_id ] = array_map( 'intval', $set );
	}

	return $stored[ $post_id ] ?? array();
}

/**
 * Clears the ID-list cache for every category this post is leaving or joining.
 *
 * @param int $post_id Post ID.
 */
function magazine_category_feature_refresh_post_categories( int $post_id ): void {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	$category_ids = array_unique(
		array_merge(
			magazine_category_feature_previous_categories( $post_id ),
			wp_get_post_categories( $post_id )
		)
	);

	magazine_category_feature_forget_categories( $category_ids );
	magazine_category_feature_purge_pages_for_categories( $category_ids );
}

/**
 * Clears one category when the term itself is deleted.
 */
function magazine_category_feature_forget_category( int $term_id ): void {
	magazine_category_feature_forget_categories( array( $term_id ) );
}

/**
 * Deletes cached ID lists.
 *
 * @param int[] $category_ids Category term IDs.
 */
function magazine_category_feature_forget_categories( array $category_ids ): void {
	foreach ( $category_ids as $category_id ) {
		$category_id = (int) $category_id;
		if ( $category_id <= 0 ) {
			continue;
		}
		wp_cache_delete( magazine_category_feature_cache_key( $category_id ), MAGAZINE_CATEGORY_FEATURE_CACHE_GROUP );
	}
}

/**
 * Purges published content that contains this block for the given categories.
 *
 * The block comment stored in post_content looks like:
 * <!-- wp:magazine-category-feature/articles {"categoryId":12,"showCategoryLabel":true} /-->
 *
 * This runs on save, not on page view. A LIKE query here is acceptable.
 * A busy multisite would replace it with a lookup table written when the
 * landing page is saved. That version is described in TECHNICAL-NOTE.md.
 *
 * @param int[] $category_ids Category term IDs.
 */
function magazine_category_feature_purge_pages_for_categories( array $category_ids ): void {
	global $wpdb;

	$post_ids = array();

	foreach ( $category_ids as $category_id ) {
		$category_id = (int) $category_id;
		if ( $category_id <= 0 ) {
			continue;
		}

		/*
		 * The comma form matches a categoryId followed by another attribute.
		 * The brace form matches a categoryId that is the last attribute.
		 * Both are anchored on the digits so category 5 does not match 50.
		 */
		$found = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_content LIKE %s
				AND ( post_content LIKE %s OR post_content LIKE %s )",
				'%wp:magazine-category-feature/articles%',
				'%"categoryId":' . $category_id . ',%',
				'%"categoryId":' . $category_id . '}%'
			)
		);

		foreach ( $found as $found_id ) {
			$post_ids[] = (int) $found_id;
		}
	}

	$post_ids = array_values( array_unique( $post_ids ) );
	$urls     = array();

	foreach ( $post_ids as $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			$urls[] = $permalink;
		}

		magazine_category_feature_purge_host_cache( $post_id );
	}

	/**
	 * Lets a CDN integration purge the exact URLs a new article affects.
	 *
	 * CloudFront does not watch WordPress post saves. Something in the stack
	 * has to invalidate these paths, or the edge keeps the previous HTML
	 * until its own TTL.
	 *
	 * @param string[] $urls         Public URLs whose HTML embeds the block.
	 * @param int[]    $category_ids Categories whose membership just changed.
	 */
	do_action( 'magazine_category_feature_purge_urls', $urls, array_values( array_unique( array_map( 'intval', $category_ids ) ) ) );
}

/**
 * Asks WP Engine's page cache to drop one post, when that plugin is present.
 *
 * Local Docker has neither WP Engine nor a persistent object cache, so this
 * is a no-op in development. The hooks are still the ones that run in production.
 */
function magazine_category_feature_purge_host_cache( int $post_id ): void {
	if ( function_exists( 'wpe_purge_post_cache' ) ) {
		wpe_purge_post_cache( $post_id );
		return;
	}

	if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
		WpeCommon::purge_varnish_cache( $post_id );
	}
}
