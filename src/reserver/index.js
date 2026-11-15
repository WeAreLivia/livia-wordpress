/**
 * The "Book button" block: a button in the style of the theme's, rendered on the server, which opens the booking
 * experience.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RadioControl, TextControl } from '@wordpress/components';
import { createElement as el, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import icon from '../icon';
import restaurantControl from '../restaurant-control';

registerBlockType( metadata.name, {
	icon: { src: icon },
	edit( { attributes, setAttributes } ) {
		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Settings', 'livia' ) },
					el( TextControl, {
						label: __( 'Label', 'livia' ),
						value: attributes.label,
						placeholder: __( 'Book a table', 'livia' ),
						onChange: ( label ) => setAttributes( { label } ),
						help: __( 'Empty: “Book a table”.', 'livia' ),
						__nextHasNoMarginBottom: true,
					} ),
					el( RadioControl, {
						label: __( 'Opens the window on', 'livia' ),
						selected: attributes.target,
						options: [
							{ value: 'guests', label: __( 'The choice of the number of guests', 'livia' ) },
							{ value: 'event', label: __( 'The private hire, at once', 'livia' ) },
						],
						onChange: ( target ) => setAttributes( { target } ),
					} ),
					restaurantControl( attributes.restaurant, ( restaurant ) => setAttributes( { restaurant } ) ),
					el( 'p', { className: 'components-base-control__help' }, __( 'Appearance: that of your theme\'s buttons.', 'livia' ) )
				)
			),
			el(
				'div',
				useBlockProps(),
				el(
					'div',
					{ className: 'wp-block-button' },
					el( 'span', { className: 'wp-block-button__link wp-element-button' }, attributes.label || __( 'Book a table', 'livia' ) )
				)
			)
		);
	},
	save: () => null,
} );
