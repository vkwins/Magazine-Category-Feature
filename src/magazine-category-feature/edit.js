/**
 * Sidebar controls and a preview that uses the same PHP as the front end.
 *
 * ServerSideRender posts the attributes to the block-renderer endpoint.
 * render.php answers. The preview cannot drift from the public markup
 * because there is only one template.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { categoryId, showCategoryLabel, showExcerpt } = attributes;

	const categories = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', 'category', {
				per_page: 100,
				orderby: 'name',
				order: 'asc',
				hide_empty: false,
				_fields: 'id,name',
			} ),
		[]
	);

	const options = [
		{
			label: categories
				? __( 'Select a category', 'magazine-category-feature' )
				: __( 'Loading categories…', 'magazine-category-feature' ),
			value: '0',
		},
		...( categories || [] ).map( ( category ) => ( {
			label: category.name,
			value: String( category.id ),
		} ) ),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Magazine Category Feature', 'magazine-category-feature' ) }
				>
					<SelectControl
						label={ __( 'Category', 'magazine-category-feature' ) }
						value={ String( categoryId || 0 ) }
						options={ options }
						help={ __(
							'The newest post is the feature. The next four are the cards.',
							'magazine-category-feature'
						) }
						onChange={ ( value ) =>
							setAttributes( { categoryId: Number( value ) } )
						}
					/>
					<ToggleControl
						label={ __(
							'Display category label',
							'magazine-category-feature'
						) }
						checked={ !! showCategoryLabel }
						onChange={ ( value ) =>
							setAttributes( { showCategoryLabel: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show subtext (excerpt)',
							'magazine-category-feature'
						) }
						checked={ !! showExcerpt }
						help={ __(
							'Applies to the featured article only.',
							'magazine-category-feature'
						) }
						onChange={ ( value ) =>
							setAttributes( { showExcerpt: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<Disabled>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</Disabled>
			</div>
		</>
	);
}