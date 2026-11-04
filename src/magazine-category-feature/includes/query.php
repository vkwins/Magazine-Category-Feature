<?php
/**
 * The category query.
 *
 * Cached value: the five post IDs, newest first. Post titles and images are
 * loaded afterwards, so a title edit still comes from WordPress's own post cache.
 * The ID list is what changes when a new article is published.
 *
 * @package MagazineCategoryFeature
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newest published posts in a category. At most five: one feature and four cards.
 *
 * @param int $category_id Category term id.
 * @return WP_Post[]
 */
function magazine_category_feature_get_posts( int $category_id ): array {
	if ( $category_id <= 0 ) {
		return array();
	}

	$cache_key = magazine_category_feature_cache_key( $category_id );
	$cached    = wp_cache_get( $cache_key, MAGAZINE_CATEGORY_FEATURE_CACHE_GROUP );

	if ( is_array( $cached ) ) {
		return magazine_category_feature_posts_from_ids( $cached );
	}

	$query = new WP_Query( magazine_category_feature_query_args( $category_id ) );
	$posts = $query->posts;
	$ids   = array();

	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post ) {
			$ids[] = (int) $post->ID;
		}
	}

	wp_cache_set( $cache_key, $ids, MAGAZINE_CATEGORY_FEATURE_CACHE_GROUP, MAGAZINE_CATEGORY_FEATURE_CACHE_TTL );

	return $posts;
}

/**
 * Query arguments for the five newest posts.
 *
 * The filter lets a brand exclude a tag or pin a meta query without copying
 * this plugin. The flags after the filter keep the query cheap and the layout
 * contract intact: five published posts, no pagination count, sticky posts ignored.
 *
 * @param int $category_id Category term id.
 * @return array<string, mixed>
 */
function magazine_category_feature_query_args( int $category_id ): array {
	$args = array(
		'post_type'              => 'post',
		'post_status'            => 'publish',
		'cat'                    => $category_id,
		'posts_per_page'         => 5,
		'orderby'                => array(
			'date' => 'DESC',
			'ID'   => 'DESC',
		),
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'magazine_category_feature_query' => true,
	);

	/**
	 * Filters the query before it runs.
	 *
	 * @param array<string, mixed> $args        WP_Query arguments.
	 * @param int                  $category_id Category term id.
	 */
	$args = apply_filters( 'magazine_category_feature_query_args', $args, $category_id );

	$args['post_type']           = 'post';
	$args['post_status']         = 'publish';
	$args['posts_per_page']      = 5;
	$args['no_found_rows']       = true;
	$args['ignore_sticky_posts'] = true;

	return $args;
}

/**
 * Loads posts for a cached ID list, keeping that order.
 *
 * @param int[] $ids Post IDs, newest first.
 * @return WP_Post[]
 */
function magazine_category_feature_posts_from_ids( array $ids ): array {
	$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
	if ( ! $ids ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'post__in'               => $ids,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $ids ),
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return $query->posts;
}

/**
 * Object-cache key for one category's ID list.
 */
function magazine_category_feature_cache_key( int $category_id ): string {
	return 'cat_' . $category_id;
}
