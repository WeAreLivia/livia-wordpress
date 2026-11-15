/**
 * The decisions of the booking window: `npm run test:js` (Node's own test runner).
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { clamp, chips, destination, fill, format, goState, pick, tier, tierLabels } from '../logic.js';

const i18n = {
	chipEvent: '%d+',
	range: '%1$d à %2$d',
	rangeGroup: '%1$d à %2$d · groupe',
	rangeEvent: '%d et +',
};

const restaurant = ( settings = {} ) => ( {
	id: 'r1',
	name: 'Brasserie Lumière',
	page: '',
	xp_from: 15,
	xp_above: 40,
	xp_url: 'https://bookings.zenchef.com/results?rid=123456&pax={n}',
	xp_open: 'window',
	xp_group_dest: 'window',
	xp_group_widget: 'wdg_page',
	xp_event_dest: 'contact',
	xp_event_url: '',
	...settings,
} );

describe( 'tiers', () => {
	it( 'follow the restaurant thresholds', () => {
		const r = restaurant();
		assert.deepEqual([ 1, 14, 15, 40, 41, 999 ].map( ( n ) => tier( n, r ) ), [ 'table', 'table', 'group', 'group', 'event', 'event' ]);
		const small = restaurant( { xp_from: 8, xp_above: 20 } );
		assert.deepEqual([ 7, 8, 20, 21 ].map( ( n ) => tier( n, small ) ), [ 'table', 'group', 'group', 'event' ]);
	} );

	it( 'are labelled from the thresholds', () => {
		assert.deepEqual(tierLabels( restaurant(), i18n ), { table: '1 à 14', group: '15 à 40 · groupe', event: '41 et +' });
		assert.equal(tierLabels( restaurant( { xp_from: 2, xp_above: 10 } ), i18n ).table, '1');
	} );
} );

describe( 'quick choices', () => {
	it( 'are those of the reference site with the default thresholds', () => {
		assert.deepEqual(chips( restaurant(), i18n ).map( ( chip ) => chip.label ), [ '2', '4', '6', '8', '15', '20', '30', '41+' ]);
	} );

	it( 'follow other thresholds and never repeat a value', () => {
		const choices = chips( restaurant( { xp_from: 6, xp_above: 12 } ), i18n );
		assert.deepEqual(choices.map( ( chip ) => chip.value ), [ 2, 4, 6, 11, 13 ]);
		assert.deepEqual(choices.map( ( chip ) => chip.tier ), [ 'table', 'table', 'group', 'group', 'event' ]);
	} );
} );

describe( '{n}', () => {
	it( 'is replaced by the number of guests, everywhere', () => {
		assert.equal(fill( 'https://bookings.zenchef.com/results?rid=1&pax={n}', 4 ), 'https://bookings.zenchef.com/results?rid=1&pax=4');
		assert.equal(fill( 'https://x.test/{n}/?a={n}', 12 ), 'https://x.test/12/?a=12');
		assert.equal(fill( 'https://brasserie.test/nos-tables/#reserver', 4 ), 'https://brasserie.test/nos-tables/#reserver');
	} );

	it( 'never opens anything but a web address', () => {
		assert.equal(fill( 'javascript:alert({n})', 4 ), '');
		assert.equal(fill( '', 4 ), '');
		assert.equal(fill( undefined, 4 ), '');
	} );
} );

describe( 'the number of guests', () => {
	it( 'stays a whole number from 1 to 999', () => {
		assert.equal(clamp( '12', 15 ), 12);
		assert.equal(clamp( '0', 15 ), 1);
		assert.equal(clamp( '5000', 15 ), 999);
		assert.equal(clamp( '3.6', 15 ), 4);
		assert.equal(clamp( 'abc', 15 ), 15);
		assert.equal(clamp( '', 15 ), 15);
	} );

	it( 'fills translated texts', () => {
		assert.equal(format( 'Plus de %d convives', 40 ), 'Plus de 40 convives');
		assert.equal(format( '%2$s, %1$s', 'a', 'b' ), 'b, a');
	} );
} );

describe( 'the selector', () => {
	const r = restaurant();

	it( 'in the page stays empty until the visitor chooses', () => {
		assert.equal(goState( { chosen: false, guests: 15, restaurant: r }, true ), 'empty');
		assert.equal(goState( { chosen: true, guests: 4, restaurant: r }, true ), 'table');
	} );

	it( 'in the window opens on the group threshold', () => {
		assert.equal(goState( { chosen: false, guests: r.xp_from, restaurant: r }, false ), 'group');
	} );

	it( 'asks for the restaurant before going anywhere', () => {
		assert.equal(goState( { chosen: true, guests: 4, restaurant: null }, false ), 'pick');
		assert.equal(goState( { chosen: false, guests: 4, restaurant: null }, true ), 'empty');
	} );
} );

describe( 'the restaurant choice', () => {
	const brasserie = restaurant();
	const terrasse = restaurant( { id: 'r2', name: 'La Terrasse Lumière' } );

	it( 'is never asked with one restaurant', () => {
		assert.deepEqual(pick( [ brasserie ], '', '' ), { picker: false, restaurant: brasserie });
	} );

	it( 'is asked with several, with no restaurant until the visitor chooses', () => {
		assert.deepEqual(pick( [ brasserie, terrasse ], '', '' ), { picker: true, restaurant: null });
		assert.deepEqual(pick( [ brasserie, terrasse ], '', 'r2' ), { picker: true, restaurant: terrasse });
	} );

	it( 'is skipped by a button that names a connected restaurant', () => {
		assert.deepEqual(pick( [ brasserie, terrasse ], 'r2', '' ), { picker: false, restaurant: terrasse });
		assert.deepEqual(pick( [ brasserie, terrasse ], 'r9', '' ), { picker: true, restaurant: null });
	} );
} );

describe( 'paths', () => {
	it( 'open the tool at the number of guests, as set', () => {
		assert.deepEqual(destination( restaurant(), 'table', 4 ), {
			kind: 'tool',
			url: 'https://bookings.zenchef.com/results?rid=123456&pax=4',
			open: 'window',
		});
		assert.equal(destination( restaurant( { xp_open: 'tab' } ), 'table', 4 ).open, 'tab');
		assert.deepEqual(destination( restaurant( { xp_url: '' } ), 'table', 4 ), { kind: 'contact' });
	} );

	it( 'open the conversation in the window, or the group page with the number of guests', () => {
		assert.deepEqual(destination( restaurant(), 'group', 20 ), { kind: 'chat', widget: 'wdg_page' });
		const page = restaurant( { xp_group_dest: 'page', page: 'https://brasserie.test/reservation-groupe/' } );
		assert.deepEqual(destination( page, 'group', 20 ), { kind: 'page', url: 'https://brasserie.test/reservation-groupe/?couverts=20' });
	} );

	it( 'open the window when the group page is not published', () => {
		assert.deepEqual(destination( restaurant( { xp_group_dest: 'page' } ), 'group', 20 ), { kind: 'chat', widget: 'wdg_page' });
	} );

	it( 'contact the restaurant, or open the private hire tool like the table tool', () => {
		assert.deepEqual(destination( restaurant(), 'event', 60 ), { kind: 'contact' });
		const tool = restaurant( { xp_event_dest: 'tool', xp_event_url: 'https://events.test/?pax={n}', xp_open: 'tab' } );
		assert.deepEqual(destination( tool, 'event', 60 ), { kind: 'tool', url: 'https://events.test/?pax=60', open: 'tab' });
	} );
} );
