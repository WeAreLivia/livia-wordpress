/**
 * The "Livia widget" block: an embedded conversation or a button of the restaurant, rendered on the server.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { createElement as el, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import icon from '../icon';
import widgetGroups from '../widget-groups';
import '../placeholder.css';

const editorData = () => window.liviaEditor || { connected: false, restaurants: [] };

function summary( widgets, id ) {
	const widget = widgets.find( ( candidate ) => candidate.id === id );
	if ( ! id ) {
		return __( 'Choose a widget in the block settings.', 'livia' );
	}
	if ( ! widget ) {
		return __( 'This widget no longer exists in Livia: choose another one in the block settings.', 'livia' );
	}
	return 'inline' === widget.format
		? /* translators: %s: widget name. */
		  sprintf( __( '%s: embedded conversation. It appears here on the published page.', 'livia' ), widget.name )
		: /* translators: %s: widget name. */
		  sprintf( __( '%s: button in the page. It appears here on the published page.', 'livia' ), widget.name );
}

registerBlockType( metadata.name, {
	icon: { src: icon },
	edit( { attributes, setAttributes } ) {
		const data = editorData();
		const blockProps = useBlockProps( { className: 'livia-placeholder' } );
		const widgets = data.restaurants.flatMap( ( restaurant ) => restaurant.inContent );
		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Settings', 'livia' ) },
					el(
						SelectControl,
						{
							label: __( 'Widget', 'livia' ),
							value: attributes.widget,
							onChange: ( widget ) => setAttributes( { widget } ),
							help: data.connected
								? __( 'Embedded conversation or button, among the widgets of your restaurants. Bubbles are set for the whole site in the Livia menu.', 'livia' )
								: __( 'Connect your restaurant in the Livia menu to choose a widget.', 'livia' ),
							__nextHasNoMarginBottom: true,
						},
						el( 'option', { value: '' }, __( 'Choose a widget', 'livia' ) ),
						widgetGroups( data.restaurants, 'inContent' )
					)
				)
			),
			el(
				'div',
				blockProps,
				el( 'span', { className: 'livia-placeholder__icon' }, icon ),
				el(
					'div',
					null,
					el( 'div', { className: 'livia-placeholder__title' }, __( 'Livia widget', 'livia' ) ),
					el( 'div', { className: 'livia-placeholder__text' }, summary( widgets, attributes.widget ) )
				)
			)
		);
	},
	save: () => null,
} );
