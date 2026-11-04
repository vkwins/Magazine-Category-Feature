<?php
// This file is generated. Do not modify it manually.
return array(
	'magazine-category-feature' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'magazine-category-feature/articles',
		'version' => '1.0.0',
		'title' => 'Magazine Category Feature',
		'category' => 'widgets',
		'icon' => 'grid-view',
		'description' => 'Shows the newest article in a category, then four cards.',
		'keywords' => array(
			'category',
			'articles',
			'magazine',
			'latest'
		),
		'textdomain' => 'magazine-category-feature',
		'attributes' => array(
			'categoryId' => array(
				'type' => 'number',
				'default' => 0
			),
			'showCategoryLabel' => array(
				'type' => 'boolean',
				'default' => true
			),
			'showExcerpt' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'anchor' => true
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	)
);
