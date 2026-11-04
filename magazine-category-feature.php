<?php
/**
 * Plugin Name:       Magazine Category Feature
 * Description:       A magazine-style block that shows the newest article in a category, then four cards. The list stays current without an editor updating the page.
 * Version:           1.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            Vivek Mishra
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/vkwins/magazine-category-feature
 * Text Domain:       magazine-category-feature
 *
 * @package MagazineCategoryFeature
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'MAGAZINE_CATEGORY_FEATURE_DIR', __DIR__ );
define( 'MAGAZINE_CATEGORY_FEATURE_CACHE_GROUP', 'magazine-category-feature' );
define( 'MAGAZINE_CATEGORY_FEATURE_CACHE_TTL', 5 * MINUTE_IN_SECONDS );


require_once MAGAZINE_CATEGORY_FEATURE_DIR . '/src/magazine-category-feature/includes/query.php';
require_once MAGAZINE_CATEGORY_FEATURE_DIR . '/src/magazine-category-feature/includes/template.php';
require_once MAGAZINE_CATEGORY_FEATURE_DIR . '/src/magazine-category-feature/includes/sponsored.php';
require_once MAGAZINE_CATEGORY_FEATURE_DIR . '/src/magazine-category-feature/includes/invalidation.php';


/**
 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function magazine_category_feature_magazine_category_feature_block_init() {
	magazine_category_feature_register_sponsored_meta();

	// WordPress 6.8+ fast path for metadata collections.
	if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
		return;
	}

	// WordPress 6.7 compatibility path.
	register_block_type( __DIR__ . '/build/magazine-category-feature' );
}
add_action( 'init', 'magazine_category_feature_magazine_category_feature_block_init' );

add_action( 'wp_enqueue_scripts', 'magazine_category_feature_enqueue_style_in_head' );
add_filter( 'render_block', 'magazine_category_feature_enqueue_style_on_render', 10, 2 );
/**
 * Prints the block CSS in the document head when this singular view contains the block.
 *
 * The hero reserves its space with CSS. That CSS has to be in the head, before the
 * image is requested, or the headline jumps down when the image arrives (CLS).
 * WordPress can also hoist late block styles, but a landing page should not depend on that.
 *
 * Blocks placed only in a template part are still styled when they render. On those
 * templates, confirm the stylesheet ended up in the head.
 */
function magazine_category_feature_enqueue_style_in_head(): void {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post || ! has_block( 'magazine-category-feature/articles', $post ) ) {
		return;
	}

	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'magazine-category-feature/articles' );
	if ( ! $block_type || empty( $block_type->style ) ) {
		return;
	}

	foreach ( (array) $block_type->style as $handle ) {
		wp_enqueue_style( $handle );
	}
}

/**
 * Fallback for non-singular or template-part usage.
 *
 * Some layouts render this block from template parts or non-singular contexts where
 * has_block() on the current post cannot detect it ahead of time. When that happens,
 * enqueue styles as soon as this block is encountered during rendering.
 *
 * @param string               $block_content Rendered block HTML.
 * @param array<string, mixed> $block         Parsed block.
 * @return string
 */
function magazine_category_feature_enqueue_style_on_render( string $block_content, array $block ): string {
	if ( is_admin() || ( $block['blockName'] ?? '' ) !== 'magazine-category-feature/articles' ) {
		return $block_content;
	}

	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'magazine-category-feature/articles' );
	if ( ! $block_type || empty( $block_type->style ) ) {
		return $block_content;
	}

	foreach ( (array) $block_type->style as $handle ) {
		wp_enqueue_style( $handle );
	}

	return $block_content;
}