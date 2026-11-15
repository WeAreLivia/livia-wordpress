/**
 * The decisions of the booking window, without the page: tiers, quick choices, addresses and the path each tier takes.
 * A restaurant is read as the plugin hands it over (Livia_Experience::restaurant_config()).
 */

/** The widget takes 1 to 999 guests. */
export const MAX = 999;

/**
 * A text with its `%d`, `%s` and numbered `%1$d` placeholders filled, as WordPress's translations write them.
 *
 * @param {string}    text   Text.
 * @param {...*}      values Values, in order.
 * @return {string} The text filled.
 */
export function format( text, ...values ) {
	let next = 0;
	return String( text ).replace( /%(?:(\d+)\$)?[ds]/g, ( match, index ) =>
		String( values[ index ? Number( index ) - 1 : next++ ] )
	);
}

/**
 * A number of guests the window accepts.
 *
 * @param {*}      value    Value typed or chosen.
 * @param {number} fallback Value kept when it is no number.
 * @return {number} A whole number from 1 to MAX.
 */
export function clamp( value, fallback ) {
	const number = Math.round( Number( value ) );
	if ( '' === value || null === value || Number.isNaN( number ) ) {
		return fallback;
	}
	return Math.min( MAX, Math.max( 1, number ) );
}

/**
 * The tier of a number of guests: below the group threshold, the group, or above the event threshold.
 *
 * @param {number} guests     Number of guests.
 * @param {Object} restaurant Restaurant.
 * @return {string} `table`, `group` or `event`.
 */
export function tier( guests, restaurant ) {
	if ( guests < restaurant.xp_from ) {
		return 'table';
	}
	return guests <= restaurant.xp_above ? 'group' : 'event';
}

/**
 * The quick choices of the window, from the restaurant's thresholds: four small tables, three groups, one event.
 *
 * @param {Object} restaurant Restaurant.
 * @param {Object} i18n       Texts.
 * @return {Array<{value: number, label: string, tier: string}>} Choices, in order.
 */
export function chips( restaurant, i18n ) {
	const from = restaurant.xp_from;
	const above = restaurant.xp_above;
	const values = [ 2, 4, 6, 8 ].filter( ( value ) => value < from );
	[ from, from + 5, from + 15 ].forEach( ( value ) => {
		if ( value <= above && ! values.includes( value ) ) {
			values.push( value );
		}
	} );
	return values
		.map( ( value ) => ( { value, label: String( value ), tier: tier( value, restaurant ) } ) )
		.concat( [ { value: above + 1, label: format( i18n.chipEvent, above + 1 ), tier: 'event' } ] );
}

/**
 * The three tiers as the window labels them.
 *
 * @param {Object} restaurant Restaurant.
 * @param {Object} i18n       Texts.
 * @return {Object} Label by tier.
 */
export function tierLabels( restaurant, i18n ) {
	const from = restaurant.xp_from;
	return {
		table: 2 === from ? '1' : format( i18n.range, 1, from - 1 ),
		group: format( i18n.rangeGroup, from, restaurant.xp_above ),
		event: format( i18n.rangeEvent, restaurant.xp_above + 1 ),
	};
}

/**
 * A booking address with the number of guests in the place of each `{n}`; only a web address is opened.
 *
 * @param {string} url    Address.
 * @param {number} guests Number of guests.
 * @return {string} Empty when it is no web address.
 */
export function fill( url, guests ) {
	const filled = String( url || '' ).split( '{n}' ).join( String( guests ) );
	return /^https?:\/\//i.test( filled ) ? filled : '';
}

/**
 * Where a tier leads.
 *
 * @param {Object} restaurant Restaurant.
 * @param {string} key        Tier.
 * @param {number} guests     Number of guests.
 * @return {{kind: string, url?: string, open?: string, widget?: string}} `tool` (with `url` and `open`: `window`,
 *         `tab` or `page`), `page` (with `url`), `chat` (with `widget`) or `contact`.
 */
export function destination( restaurant, key, guests ) {
	const groupPage = () => {
		const url = new URL( restaurant.page );
		url.searchParams.set( 'couverts', String( guests ) );
		return { kind: 'page', url: url.toString() };
	};
	if ( 'table' === key ) {
		const url = fill( restaurant.xp_url, guests );
		return url ? { kind: 'tool', url, open: restaurant.xp_open } : { kind: 'contact' };
	}
	if ( 'group' === key ) {
		if ( 'page' === restaurant.xp_group_dest && restaurant.page ) {
			return groupPage();
		}
		if ( restaurant.xp_group_widget ) {
			return { kind: 'chat', widget: restaurant.xp_group_widget };
		}
		return restaurant.page ? groupPage() : { kind: 'contact' };
	}
	const url = 'tool' === restaurant.xp_event_dest ? fill( restaurant.xp_event_url, guests ) : '';
	return url ? { kind: 'tool', url, open: restaurant.xp_open } : { kind: 'contact' };
}

/**
 * What the "go" button of a selector does now.
 *
 * @param {Object}  state            Shared state.
 * @param {boolean} state.chosen     Whether the visitor chose a number of guests.
 * @param {number}  state.guests     The number shown.
 * @param {?Object} state.restaurant The restaurant booked, null while the visitor has to choose it.
 * @param {boolean} inPage           Whether the selector sits in the page, where nothing goes without a number.
 * @return {string} `empty` (ask for the number), `pick` (ask for the restaurant), or the tier.
 */
export function goState( state, inPage ) {
	if ( inPage && ! state.chosen ) {
		return 'empty';
	}
	if ( ! state.restaurant ) {
		return 'pick';
	}
	return tier( state.guests, state.restaurant );
}

/**
 * The restaurant a trigger books, and whether the window asks for it.
 *
 * @param {Object[]} restaurants Restaurants, in the order of the choice.
 * @param {string}   target      Restaurant the trigger names, empty for none.
 * @param {string}   chosen      Restaurant the visitor chose earlier, empty for none.
 * @return {{picker: boolean, restaurant: ?Object}} The restaurant, null until the visitor chooses one.
 */
export function pick( restaurants, target, chosen ) {
	const find = ( id ) => restaurants.find( ( restaurant ) => restaurant.id === id ) || null;
	if ( 1 === restaurants.length ) {
		return { picker: false, restaurant: restaurants[ 0 ] };
	}
	if ( target && find( target ) ) {
		return { picker: false, restaurant: find( target ) };
	}
	return { picker: true, restaurant: find( chosen ) };
}
