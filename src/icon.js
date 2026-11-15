import { createElement as el } from '@wordpress/element';

/**
 * The Livia picto on its lime tile, as the block and the editor show it.
 */
const gradient = ( id, attributes, stops ) =>
	el(
		'radialGradient',
		{ id, ...attributes, gradientUnits: 'userSpaceOnUse' },
		stops.map( ( [ offset, stopColor ] ) => el( 'stop', { key: offset, offset, stopColor } ) )
	);

export default el(
	'svg',
	{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', 'aria-hidden': true, focusable: false },
	el( 'rect', { width: 24, height: 24, rx: 6, fill: '#e0f399' } ),
	el(
		'svg',
		{ x: 3, y: 5, width: 18, height: 14, viewBox: '0 0 272 181' },
		el(
			'defs',
			null,
			gradient( 'livia-icon-eye1', { cx: '22.61', cy: '89.96', fx: '40.19', fy: '54.77', r: '39.34', gradientTransform: 'translate(80.11 -1.05) rotate(3)' }, [ [ '.76', '#fff' ], [ '.91', '#fdfdfd' ], [ '.96', '#f6f6f6' ], [ '.99', '#eaeaea' ], [ '1', '#e6e6e6' ] ] ),
			gradient( 'livia-icon-eye2', { cx: '93.5', cy: '92.08', fx: '93.5', fy: '92.08', r: '39.34', gradientTransform: 'translate(80.11 -1.05) rotate(3)' }, [ [ '.76', '#fff' ], [ '.87', '#f7f7f7' ], [ '1', '#e6e6e6' ] ] )
		),
		el( 'path', { fill: '#0a0b02', d: 'M89.15,22.45l123.38,41c26.16,8.69,39.19,38.18,27.7,63.24-1.33,2.9-2.86,5.89-4.62,8.98-15.85,27.81-98.17,26.53-141.92,23.77-16.76-1.06-31.64-11.05-38.95-26.17l-22.43-46.41c-4.06-8.41-5.5-17.85-4.12-27.09h0c2.78-18.61,16.5-33.71,34.76-38.24l.34-.08c8.55-2.12,17.52-1.78,25.87,1Z' } ),
		el( 'path', { fill: 'url(#livia-icon-eye1)', d: 'M121.65,58.54c-6.04-4.57-13.47-7.44-21.62-7.87-21.7-1.14-40.21,15.54-41.34,37.23-1.14,21.7,15.52,40.22,37.23,41.36,7.74.41,15.09-1.45,21.36-5.02,11.34-6.37,19.25-18.26,19.98-32.22.71-13.55-5.54-25.88-15.61-33.48Z' } ),
		el( 'path', { fill: '#171904', d: 'M111.86,91.66c-.8,15.24-13.1,27.04-27.41,26.29s-25.31-13.77-24.51-29.01c.8-15.24,13.1-27.04,27.41-26.29,14.31.75,25.31,13.77,24.51,29.01Z' } ),
		el( 'path', { fill: 'url(#livia-icon-eye2)', d: 'M207.95,97.85c-1.14,21.7-19.65,38.37-41.35,37.23-21.7-1.14-38.37-19.65-37.23-41.35,1.14-21.7,19.65-38.37,41.35-37.23,21.7,1.14,38.37,19.65,37.23,41.35Z' } ),
		el( 'path', { fill: '#171904', d: 'M184.23,98.09c-.8,15.24-13.1,27.04-27.41,26.29-14.31-.75-25.31-13.77-24.51-29.01.8-15.24,13.1-27.04,27.41-26.29,14.31.75,25.31,13.77,24.51,29.01Z' } )
	)
);
