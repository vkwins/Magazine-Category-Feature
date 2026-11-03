<?php
/**
 * Front-end and editor preview markup.
 *
 * WordPress includes this file with $attributes, $content and $block in scope.
 * One template serves both, so the editor preview matches the page.
 *
 * Headings start at h2. The page title is the h1, and Yoast owns that.
 * This file does not print schema, a canonical, or a meta description.
 *
 * @package MagazineCategoryFeature
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner content. Unused.
 * @var WP_Block             $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>