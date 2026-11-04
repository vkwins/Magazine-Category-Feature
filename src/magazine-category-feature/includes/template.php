<?php
/**
 * Markup helpers shared by every render of the block.
 *
 * These live outside render.php because that file is included once per
 * block on the page. A function declared there would fatal on the second one.
 *
 * @package MagazineCategoryFeature
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True while Gutenberg is asking the block renderer for a preview.
 *
 * Server-side render calls /wp/v2/block-renderer with context=edit.
 * The public page, and a headless request without that flag, is not a preview.
 * An empty category then renders nothing, which is what a cached homepage should store.
 */
function magazine_category_feature_is_editor_preview(): bool {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return false;
	}

	$context = filter_input( INPUT_GET, 'context', FILTER_SANITIZE_SPECIAL_CHARS );
	return is_string( $context ) && 'edit' === $context;
}

/**
 * Editor-only message. The front end calls this and then returns no markup.
 */
function magazine_category_feature_render_notice( string $message ): void {
	if ( ! magazine_category_feature_is_editor_preview() ) {
		return;
	}

	printf(
		'<div %1$s><p class="magazine-category-feature__notice">%2$s</p></div>',
		get_block_wrapper_attributes(
			array(
				'class' => 'magazine-category-feature magazine-category-feature--notice',
			)
		),
		esc_html( $message )
	);
}

/**
 * Featured image, or a placeholder that occupies the same box.
 *
 * The first hero image on the page is treated as the LCP element:
 * eager, high fetch priority. Cards are lazy and low priority so they
 * do not compete with it. Width and height come from WordPress.
 * The CSS box (aspect-ratio) is what actually stops the layout shift.
 *
 * @param WP_Post              $post       Post being rendered.
 * @param string               $variant    Either "hero" or "card".
 * @param array<string, mixed> $attributes Block attributes. Used for the hero sizes.
 */
function magazine_category_feature_media_html( WP_Post $post, string $variant, array $attributes ): string {
	$is_hero   = 'hero' === $variant;
	$class     = 'magazine-category-feature__media ' . ( $is_hero ? 'magazine-category-feature__media--hero' : 'magazine-category-feature__media--card' );
	$thumb_id  = (int) get_post_thumbnail_id( $post );
	$image_alt = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';

	if ( ! $thumb_id ) {
		return sprintf(
			'<span class="%1$s magazine-category-feature__placeholder" aria-hidden="true"></span>',
			esc_attr( $class )
		);
	}

	if ( '' === $image_alt ) {
		$image_alt = get_the_title( $post );
	}

	$img_attr = array(
		'alt'      => $image_alt,
		'class'    => $is_hero ? 'magazine-category-feature__img magazine-category-feature__img--hero' : 'magazine-category-feature__img magazine-category-feature__img--card',
		'decoding' => 'async',
		'sizes'    => $is_hero ? magazine_category_feature_hero_sizes( $attributes ) : '(max-width: 781px) 88px, 160px',
	);

	if ( $is_hero && magazine_category_feature_claim_lcp_image() ) {
		$img_attr['loading']       = 'eager';
		$img_attr['fetchpriority'] = 'high';
	} else {
		$img_attr['loading']       = 'lazy';
		$img_attr['fetchpriority'] = 'low';
	}

	$image = wp_get_attachment_image( $thumb_id, $is_hero ? 'large' : 'medium', false, $img_attr );

	return '<span class="' . esc_attr( $class ) . '">' . $image . '</span>';
}

/**
 * sizes for the hero. Wide and full alignments need a larger slot than the default column.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function magazine_category_feature_hero_sizes( array $attributes ): string {
	$align = isset( $attributes['align'] ) ? (string) $attributes['align'] : '';

	if ( 'full' === $align ) {
		return '100vw';
	}

	if ( 'wide' === $align ) {
		return '(max-width: 781px) 100vw, 1200px';
	}

	return '(max-width: 781px) 100vw, 840px';
}

/**
 * Lets the first hero image on the page take fetchpriority="high".
 * A second Magazine Category Feature block on the same page does not.
 */
function magazine_category_feature_claim_lcp_image(): bool {
	static $claimed = false;

	if ( $claimed ) {
		return false;
	}

	$claimed = true;
	return true;
}

/**
 * "Sponsored by …" from post meta. Empty when the post is not sponsored.
 */
function magazine_category_feature_sponsored_text( WP_Post $post ): string {
	$value = get_post_meta( $post->ID, 'magazine_category_feature_sponsored_by', true );
	if ( ! is_string( $value ) ) {
		return '';
	}

	return trim( $value );
}
