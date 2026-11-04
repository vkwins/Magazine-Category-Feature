<?php
/**
 * Sponsored label, stored on the post rather than the block.
 *
 * The same article can appear on more than one landing page. The sponsor
 * belongs to the article, so every card that renders it stays in agreement.
 *
 * @package MagazineCategoryFeature
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the sponsor name to the block editor and to the front-end render.
 */
function magazine_category_feature_register_sponsored_meta(): void {
	register_post_meta(
		'post',
		'magazine_category_feature_sponsored_by',
		array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
				unset( $allowed, $meta_key );
				if ( ! $post_id ) {
					return current_user_can( 'edit_posts' );
				}
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);
}
