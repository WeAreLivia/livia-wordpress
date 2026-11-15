/**
 * The "Livia" panel of a page or a post: the site bubble, none, or another bubble.
 */
import { registerPlugin } from '@wordpress/plugins';
import * as editor from '@wordpress/editor';
import * as editPost from '@wordpress/edit-post';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { SelectControl } from '@wordpress/components';
import { createElement as el } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import widgetGroups from './widget-groups';
import './editor-panel.css';

// The panel moved from edit-post to editor in WordPress 6.6.
const DocumentPanel = editor.PluginDocumentSettingPanel || editPost.PluginDocumentSettingPanel;

const MODE = '_livia_widget_mode';
const WIDGET = '_livia_widget_id';

function LiviaPanel() {
	const { postType, postId } = useSelect(
		( select ) => ( {
			postType: select( 'core/editor' ).getCurrentPostType(),
			postId: select( 'core/editor' ).getCurrentPostId(),
		} ),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	const data = window.liviaEditor || { siteWidget: '', restaurants: [], groupPageIds: [] };
	if ( ! meta || data.groupPageIds.includes( postId ) ) {
		return null;
	}
	const bubbles = data.restaurants.flatMap( ( restaurant ) => restaurant.bubbles );
	const mode = meta[ MODE ] || 'site';
	const choose = ( value ) => {
		const changes = { [ MODE ]: value };
		if ( 'other' === value && ! meta[ WIDGET ] && bubbles.length ) {
			changes[ WIDGET ] = bubbles[ 0 ].id;
		}
		setMeta( { ...meta, ...changes } );
	};
	const radio = ( value, label, detail ) =>
		el(
			'label',
			{ className: 'livia-panel__radio', key: value },
			el( 'input', {
				type: 'radio',
				name: 'livia-page-widget',
				value,
				checked: mode === value,
				onChange: () => choose( value ),
			} ),
			el( 'span', null, label, detail ? el( 'span', { className: 'livia-panel__detail' }, detail ) : null )
		);

	let other = null;
	if ( 'other' === mode ) {
		other = bubbles.length
			? el(
					SelectControl,
					{
						label: __( 'Widget', 'livia' ),
						hideLabelFromVision: true,
						value: meta[ WIDGET ],
						onChange: ( widget ) => setMeta( { ...meta, [ WIDGET ]: widget } ),
						__nextHasNoMarginBottom: true,
					},
					widgetGroups( data.restaurants, 'bubbles' )
			  )
			: el( 'p', { className: 'livia-panel__help' }, __( 'Your restaurants have no other bubble widget.', 'livia' ) );
	}

	return el(
		DocumentPanel,
		{ name: 'livia', title: 'Livia', className: 'livia-panel' },
		el(
			'fieldset',
			{ className: 'livia-panel__set' },
			el( 'legend', { className: 'livia-panel__legend' }, __( 'Widget of this page', 'livia' ) ),
			radio( 'site', __( 'Site widget', 'livia' ), data.siteWidget || __( 'None chosen in the Livia menu', 'livia' ) ),
			radio( 'none', __( 'No widget', 'livia' ) ),
			radio( 'other', __( 'Another widget', 'livia' ) )
		),
		other,
		el(
			'p',
			{ className: 'livia-panel__help' },
			__( 'The site widget is set in the Livia menu. To place a conversation in the text, add the “Livia widget” block.', 'livia' )
		)
	);
}

registerPlugin( 'livia-page-widget', { render: LiviaPanel } );
