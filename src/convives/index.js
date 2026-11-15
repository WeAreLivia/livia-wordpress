/**
 * The "How many of you?" block: the guest selector, drawn on the published page by the booking experience.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RadioControl } from '@wordpress/components';
import { createElement as el, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import icon from '../icon';
import restaurantControl from '../restaurant-control';
import '../placeholder.css';

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
					el( RadioControl, {
						label: __( 'Layout', 'livia' ),
						selected: attributes.variant,
						options: [
							{ value: 'card', label: __( 'Card', 'livia' ) },
							{ value: 'band', label: __( 'Band, over a photo', 'livia' ) },
						],
						onChange: ( variant ) => setAttributes( { variant } ),
					} ),
					restaurantControl( attributes.restaurant, ( restaurant ) => setAttributes( { restaurant } ) )
				)
			),
			el(
				'div',
				useBlockProps( { className: 'livia-placeholder' } ),
				el( 'span', { className: 'livia-placeholder__icon' }, icon ),
				el(
					'div',
					null,
					el( 'div', { className: 'livia-placeholder__title' }, __( 'How many of you?', 'livia' ) ),
					el(
						'div',
						{ className: 'livia-placeholder__text' },
						'band' === attributes.variant
							? __( 'Livia guest selector, band layout.', 'livia' )
							: __( 'Livia guest selector, card layout.', 'livia' )
					)
				)
			)
		);
	},
	save: () => null,
} );
