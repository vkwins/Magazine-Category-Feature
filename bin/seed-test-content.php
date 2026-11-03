<?php
/**
 * Creates the content the brief asks for.
 *
 * Run from the WordPress root, inside the container, with this plugin active:
 *   php wp-content/plugins/magazine-category-feature/bin/seed-test-content.php
 *
 * Safe to run again. Categories, posts and pages are matched by slug
 * and updated in place. Existing featured images are kept.
 *
 * @package MagazineCategoryFeature
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "CLI only.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Cannot find wp-load.php at {$wp_load}\n" );
	exit( 1 );
}

require $wp_load;
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	fwrite( STDERR, "PHP GD is required to draw the test images.\n" );
	exit( 1 );
}

/**
 * @return int Term id.
 */
function magazine_category_feature_seed_category( string $name, string $slug ): int {
	$existing = get_term_by( 'slug', $slug, 'category' );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}

	$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	if ( is_wp_error( $created ) ) {
		fwrite( STDERR, $created->get_error_message() . "\n" );
		exit( 1 );
	}

	return (int) $created['term_id'];
}

/**
 * Draws a JPEG and attaches it. Returns the attachment id.
 */
function magazine_category_feature_seed_image( string $label, int $red, int $green, int $blue, string $alt ): int {
	$width  = 1630;
	$height = 918;
	$image  = imagecreatetruecolor( $width, $height );
	$fill   = imagecolorallocate( $image, $red, $green, $blue );
	$text   = imagecolorallocate( $image, 255, 255, 255 );
	imagefilledrectangle( $image, 0, 0, $width, $height, $fill );
	imagestring( $image, 5, 40, (int) ( $height / 2 ), $label, $text );

	$upload = wp_upload_bits( sanitize_file_name( $label ) . '.jpg', null, ' ' );
	if ( ! empty( $upload['error'] ) ) {
		fwrite( STDERR, $upload['error'] . "\n" );
		exit( 1 );
	}

	imagejpeg( $image, $upload['file'], 85 );
	imagedestroy( $image );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $label,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		fwrite( STDERR, "Could not insert attachment for {$label}\n" );
		exit( 1 );
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	wp_update_attachment_metadata( $attachment_id, $metadata );

	if ( '' !== $alt ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	}

	return (int) $attachment_id;
}

/**
 * @param array<string, mixed> $args Post fields plus category_id, image, alt, sponsored, days_ago.
 */
function magazine_category_feature_seed_post( array $args ): int {
	$existing = get_page_by_path( $args['slug'], OBJECT, 'post' );
	$date     = gmdate( 'Y-m-d H:i:s', time() - ( (int) $args['days_ago'] * DAY_IN_SECONDS ) );

	$postarr = array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_title'    => $args['title'],
		'post_name'     => $args['slug'],
		'post_excerpt'  => $args['excerpt'],
		'post_content'  => $args['content'],
		'post_date'     => get_date_from_gmt( $date ),
		'post_date_gmt' => $date,
		'post_author'   => 1,
		'post_category' => array( (int) $args['category_id'] ),
	);

	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = $existing->ID;
		$post_id       = wp_update_post( $postarr, true );
	} else {
		$post_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, $post_id->get_error_message() . "\n" );
		exit( 1 );
	}

	if ( ! empty( $args['image'] ) ) {
		$alt            = isset( $args['alt'] ) ? (string) $args['alt'] : '';
		$existing_thumb = (int) get_post_thumbnail_id( $post_id );
		if ( $existing_thumb && get_post( $existing_thumb ) ) {
			if ( '' !== $alt ) {
				update_post_meta( $existing_thumb, '_wp_attachment_image_alt', $alt );
			} else {
				delete_post_meta( $existing_thumb, '_wp_attachment_image_alt' );
			}
		} else {
			set_post_thumbnail(
				$post_id,
				magazine_category_feature_seed_image( $args['slug'], (int) $args['red'], (int) $args['green'], (int) $args['blue'], $alt )
			);
		}
	} else {
		delete_post_thumbnail( $post_id );
	}

	delete_post_meta( $post_id, 'category_feature_sponsored_by' );
	if ( ! empty( $args['sponsored'] ) ) {
		update_post_meta( $post_id, 'magazine_category_feature_sponsored_by', $args['sponsored'] );
	} else {
		delete_post_meta( $post_id, 'magazine_category_feature_sponsored_by' );
	}

	return (int) $post_id;
}

/**
 * @param array<string, mixed> $attributes Block attributes.
 */
function magazine_category_feature_seed_page( string $title, string $slug, array $attributes ): int {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$content  = '<!-- wp:magazine-category-feature/articles ' . wp_json_encode( $attributes ) . ' /-->';

	$postarr = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_author'  => 1,
	);

	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = $existing->ID;
		$page_id       = wp_update_post( $postarr, true );
	} else {
		$page_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $page_id ) ) {
		fwrite( STDERR, $page_id->get_error_message() . "\n" );
		exit( 1 );
	}

	return (int) $page_id;
}

$gardening  = magazine_category_feature_seed_category( 'Gardening', 'gardening' );
$decorating = magazine_category_feature_seed_category( 'Decorating', 'decorating' );
$news       = magazine_category_feature_seed_category( 'News', 'news' );
$homes      = magazine_category_feature_seed_category( 'Homes', 'homes' );

$stories = array(
	array(
		'title'       => 'Spring pruning that keeps roses flowering',
		'slug'        => 'cf-roses-flowering',
		'excerpt'     => 'Sed do eiusmod tempor incididunt ut labore.',
		'content'     => 'A short gardening story used as the featured article.',
		'category_id' => $gardening,
		'days_ago'    => 1,
		'image'       => true,
		'alt'         => 'Rose canes after a spring prune',
		'sponsored'   => '',
		'red'         => 90,
		'green'       => 122,
		'blue'        => 90,
	),
	array(
		'title'       => 'Five tools worth the cupboard space',
		'slug'        => 'cf-garden-tools',
		'excerpt'     => 'The second newest gardening story. It should be the first card, with a sponsor.',
		'content'     => 'A sponsored gardening card.',
		'category_id' => $gardening,
		'days_ago'    => 2,
		'image'       => true,
		'alt'         => 'Hand tools on a potting bench',
		'sponsored'   => 'GARDEN CO',
		'red'         => 70,
		'green'       => 110,
		'blue'        => 80,
	),
	array(
		'title'       => 'What to plant before the first frost',
		'slug'        => 'cf-before-frost',
		'excerpt'     => 'Third newest. Its image has no alt text, so the block should use the headline.',
		'content'     => 'Tests the alt-text fallback.',
		'category_id' => $gardening,
		'days_ago'    => 3,
		'image'       => true,
		'alt'         => '',
		'sponsored'   => '',
		'red'         => 120,
		'green'       => 100,
		'blue'        => 70,
	),
	array(
		'title'       => 'A shaded corner that still earns its keep',
		'slug'        => 'cf-shaded-corner',
		'excerpt'     => 'Fourth newest, and it has no featured image.',
		'content'     => 'Tests the placeholder.',
		'category_id' => $gardening,
		'days_ago'    => 4,
		'image'       => false,
		'sponsored'   => '',
		'red'         => 0,
		'green'       => 0,
		'blue'        => 0,
	),
	array(
		'title'       => 'Compost, simplified for small gardens',
		'slug'        => 'cf-compost',
		'excerpt'     => 'Fifth newest. Last card on the grid.',
		'content'     => 'Closes the set of five.',
		'category_id' => $gardening,
		'days_ago'    => 5,
		'image'       => true,
		'alt'         => 'A small compost bay',
		'sponsored'   => '',
		'red'         => 96,
		'green'       => 78,
		'blue'        => 52,
	),
	array(
		'title'       => 'The oldest gardening note, kept off the grid',
		'slug'        => 'cf-oldest-gardening',
		'excerpt'     => 'Sixth newest. The block must not show this one.',
		'content'     => 'Proves the block stops at five posts.',
		'category_id' => $gardening,
		'days_ago'    => 6,
		'image'       => true,
		'alt'         => 'An old notebook',
		'sponsored'   => '',
		'red'         => 80,
		'green'       => 80,
		'blue'        => 80,
	),
	array(
		'title'       => 'Paint colours that warm a south-facing room',
		'slug'        => 'cf-paint-colours',
		'excerpt'     => 'Newest decorating story. Hero of a short list.',
		'content'     => 'Decorating has only two posts.',
		'category_id' => $decorating,
		'days_ago'    => 1,
		'image'       => true,
		'alt'         => 'A sunlit living room wall',
		'sponsored'   => '',
		'red'         => 176,
		'green'       => 132,
		'blue'        => 96,
	),
	array(
		'title'       => 'A hallway that does more than pass through',
		'slug'        => 'cf-hallway',
		'excerpt'     => 'The only decorating card.',
		'content'     => 'Second and last decorating story.',
		'category_id' => $decorating,
		'days_ago'    => 3,
		'image'       => true,
		'alt'         => 'A narrow hallway with a bench',
		'sponsored'   => '',
		'red'         => 150,
		'green'       => 120,
		'blue'        => 100,
	),
	array(
		'title'       => 'Council vote delays the station rebuild',
		'slug'        => 'cf-station-rebuild',
		'excerpt'     => 'The only news story, and it has no image.',
		'content'     => 'A hero with a placeholder.',
		'category_id' => $news,
		'days_ago'    => 2,
		'image'       => false,
		'sponsored'   => '',
		'red'         => 0,
		'green'       => 0,
		'blue'        => 0,
	),
);

foreach ( $stories as $story ) {
	magazine_category_feature_seed_post( $story );
}

$pages = array(
	array( 'Magazine Category Feature: Gardening', 'cf-gardening', array( 'categoryId' => $gardening, 'showCategoryLabel' => true, 'showExcerpt' => true ) ),
	array( 'Magazine Category Feature: Gardening without labels', 'cf-gardening-plain', array( 'categoryId' => $gardening, 'showCategoryLabel' => false, 'showExcerpt' => false ) ),
	array( 'Magazine Category Feature: Decorating', 'cf-decorating', array( 'categoryId' => $decorating, 'showCategoryLabel' => true, 'showExcerpt' => true ) ),
	array( 'Magazine Category Feature: News', 'cf-news', array( 'categoryId' => $news, 'showCategoryLabel' => true, 'showExcerpt' => true ) ),
	array( 'Magazine Category Feature: Homes', 'cf-homes', array( 'categoryId' => $homes, 'showCategoryLabel' => true, 'showExcerpt' => true ) ),
	array( 'Plain page without the block', 'cf-plain', array() ),
);

echo "Categories\n";
echo "  gardening {$gardening}\n  decorating {$decorating}\n  news {$news}\n  homes (empty) {$homes}\n\n";
echo "Pages\n";

foreach ( $pages as $page ) {
	if ( empty( $page[2] ) ) {
		$existing = get_page_by_path( $page[1], OBJECT, 'page' );
		$postarr  = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $page[0],
			'post_name'    => $page[1],
			'post_content' => '<!-- wp:paragraph --><p>This page does not use the magazine category feature block.</p><!-- /wp:paragraph -->',
			'post_author'  => 1,
		);
		if ( $existing instanceof WP_Post ) {
			$postarr['ID'] = $existing->ID;
			$page_id       = wp_update_post( $postarr, true );
		} else {
			$page_id = wp_insert_post( $postarr, true );
		}
	} else {
		$page_id = magazine_category_feature_seed_page( $page[0], $page[1], $page[2] );
	}

	if ( is_wp_error( $page_id ) ) {
		fwrite( STDERR, $page_id->get_error_message() . "\n" );
		exit( 1 );
	}

	echo '  ' . get_permalink( $page_id ) . "\n";
}

echo "\nDone.\n";
