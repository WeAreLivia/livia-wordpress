/**
 * The restaurant a booking block opens on, offered when the site has several restaurants.
 */
import { SelectControl } from '@wordpress/components';
import { createElement as el } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function restaurantControl( value, onChange ) {
	const restaurants = ( window.liviaEditor || { restaurants: [] } ).restaurants;
	if ( restaurants.length < 2 ) {
		return null;
	}
	return el( SelectControl, {
		label: __( 'Restaurant', 'livia' ),
		value,
		onChange,
		help: __( 'Chosen here, the window opens on this restaurant; otherwise it asks the visitor where and how many.', 'livia' ),
		options: [ { value: '', label: __( 'Asked to the visitor', 'livia' ) } ].concat(
			restaurants.map( ( restaurant ) => ( { value: restaurant.id, label: restaurant.name } ) )
		),
		__nextHasNoMarginBottom: true,
	} );
}
