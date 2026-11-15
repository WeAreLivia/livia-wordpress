/**
 * The block the group booking page carries: Livia renders it on the published page.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { createElement as el } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import icon from '../icon';
import '../placeholder.css';

registerBlockType( metadata.name, {
	icon: { src: icon },
	edit() {
		return el(
			'div',
			useBlockProps( { className: 'livia-placeholder' } ),
			el( 'span', { className: 'livia-placeholder__icon' }, icon ),
			el(
				'div',
				null,
				el( 'div', { className: 'livia-placeholder__title' }, __( 'Livia group booking page', 'livia' ) ),
				el(
					'div',
					{ className: 'livia-placeholder__text' },
					__( 'Livia shows the content of this page on the published page. Its address, title and widget are set in the Livia menu.', 'livia' )
				)
			)
		);
	},
	save: () => null,
} );
