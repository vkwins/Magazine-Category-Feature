/**
 * "Sponsored by" on the post, in the editor sidebar.
 *
 * This is not a block setting. It is post meta, so the label follows the
 * article onto every page that lists it.
 */
import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';

const META_KEY = 'magazine_category_feature_sponsored_by';

export default function SponsoredPanel() {
	const postType = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		if ( ! editor || ! editor.getCurrentPostType ) {
			return '';
		}
		return editor.getCurrentPostType() || '';
	}, [] );

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType || 'post',
		'meta'
	);

	if ( postType !== 'post' ) {
		return null;
	}

	const current =
		meta && typeof meta[ META_KEY ] === 'string' ? meta[ META_KEY ] : '';

	return (
		<PluginDocumentSettingPanel
			name="magazine-category-feature-sponsored"
			title={ __( 'Sponsored', 'magazine-category-feature' ) }
		>
			<TextControl
				label={ __( 'Sponsored by', 'magazine-category-feature' ) }
				value={ current }
				help={ __(
					'Shown on this article’s card. Leave empty for a normal story.',
					'magazine-category-feature'
				) }
				onChange={ ( value ) =>
					setMeta( { ...meta, [ META_KEY ]: value } )
				}
			/>
		</PluginDocumentSettingPanel>
	);
}
