/**
 * The booking window and the "How many of you?" selectors of the page.
 *
 * Every link to #reserver, or element of class `livia-reserver`, opens the window (`data-livia-restaurant` books one
 * restaurant, `data-livia-event` goes to the event path at once). The window asks for the number of guests, and the
 * restaurant when the site has several, then opens the restaurant's tool, its Livia conversation, its group page or
 * its contact. A number chosen anywhere shows everywhere; a selector of the page stays empty until the visitor
 * chooses, and its button never goes without a number. Settings and texts come from `window.liviaExperience`.
 */
import { MAX, format, clamp, tier, chips, tierLabels, destination, goState, pick } from './logic';
import './style.css';

const config = window.liviaExperience || {};
const restaurants = config.restaurants || [];
const i18n = config.i18n || {};

const svg = ( paths ) =>
	'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
	paths +
	'</svg>';
const ICONS = {
	arrow: svg( '<path d="M5 12h14M13 6l6 6-6 6"/>' ),
	back: svg( '<path d="M19 12H5M11 18l-6-6 6-6"/>' ),
	close: svg( '<path d="M18 6 6 18M6 6l12 12"/>' ),
	external: svg( '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>' ),
	check: svg( '<path d="M20 6 9 17l-5-5"/>' ),
	mail: svg( '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>' ),
	phone: svg(
		'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>'
	),
	pin: svg( '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>' ),
	table: svg( '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>' ),
	group: svg( '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4.5"/>' ),
	event: svg( '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8-4.3-4.1 5.9-.9z"/>' ),
};

const escape = ( text ) =>
	String( text ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' } )[ c ] );

/** Shared by every selector: the number shown, whether the visitor chose it, the restaurant chosen. */
const state = {
	guests: restaurants.length ? restaurants[ 0 ].xp_from : 15,
	chosen: false,
	chosenId: '',
	target: '',
};
const instances = [];

const guestsText = ( guests ) => guests + ' ' + ( guests > 1 ? i18n.guests : i18n.guest );

/* Selectors */

function template( variant ) {
	const counter =
		'<div class="livia-sel-count">' +
		'<button type="button" class="livia-sel-pm" data-delta="-1" aria-label="' + escape( i18n.fewer ) + '">−</button>' +
		'<label class="livia-sel-nb"><input type="number" min="1" max="' + MAX + '" inputmode="numeric" data-nb aria-label="' + escape( i18n.count ) + '"><span data-unit></span></label>' +
		'<button type="button" class="livia-sel-pm" data-delta="1" aria-label="' + escape( i18n.more ) + '">+</button>' +
		'</div>';
	const go = '<button type="button" class="wp-element-button livia-sel-go" data-go><span data-go-text></span>' + ICONS.arrow + '</button>';
	if ( 'modal' === variant ) {
		return (
			'<div class="livia-sel is-modal">' +
			'<div class="livia-sel-a">' + counter + '<div class="livia-sel-chips" data-chips></div></div>' +
			'<div class="livia-sel-b"><ol class="livia-sel-tiers" data-tiers></ol>' +
			'<div class="livia-sel-hint"><i data-hint-icon></i><b data-hint-title></b><p data-hint-text></p></div>' +
			go +
			'</div></div>'
		);
	}
	return (
		'<div class="livia-sel is-' + variant + '"><p class="livia-sel-title">' + escape( i18n.howMany ) + '</p>' +
		counter + go + '<p class="livia-sel-tip">' + escape( i18n.emptyHint ) + '</p></div>'
	);
}

/** The restaurant a selector books: the window's, or the one its block names. */
function restaurantOf( instance ) {
	return pick( restaurants, instance.modal ? state.target : instance.target, state.chosenId ).restaurant;
}

function mount( container, variant ) {
	container.innerHTML = template( variant );
	container.dataset.mounted = '';
	const root = container.firstChild;
	const instance = {
		root,
		modal: 'modal' === variant,
		target: container.dataset.liviaRestaurant || '',
		input: root.querySelector( '[data-nb]' ),
		shown: '',
	};
	if ( ! instance.modal ) {
		instance.input.placeholder = '?';
	}
	instances.push( instance );

	root.addEventListener( 'click', ( event ) => {
		const chip = event.target.closest( '[data-chip]' );
		if ( chip ) {
			choose( Number( chip.dataset.chip ) );
		} else if ( event.target.closest( '[data-go]' ) ) {
			go( instance );
		}
	} );
	root.querySelectorAll( '[data-delta]' ).forEach( ( button ) => repeat( button, Number( button.dataset.delta ), instance ) );
	instance.input.addEventListener( 'input', () => {
		if ( '' !== instance.input.value ) {
			choose( instance.input.value, instance );
		}
	} );
	instance.input.addEventListener( 'blur', () => render( instance ) );
	instance.input.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			go( instance );
		}
	} );
	render( instance );
	return instance;
}

/** Holding + or − down runs the number faster and faster. */
function repeat( button, step, instance ) {
	let timer = null;
	let delay;
	const waiting = () => ! instance.modal && ! state.chosen;
	const advance = () => choose( waiting() ? 1 : state.guests + step );
	const stop = () => {
		clearTimeout( timer );
		timer = null;
	};
	const tick = () => {
		advance();
		delay = Math.max( 45, delay * 0.82 );
		timer = setTimeout( tick, delay );
	};
	button.addEventListener( 'pointerdown', ( event ) => {
		event.preventDefault();
		advance();
		delay = 380;
		timer = setTimeout( tick, delay );
	} );
	[ 'pointerup', 'pointerleave', 'pointercancel' ].forEach( ( type ) => button.addEventListener( type, stop ) );
	button.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			advance();
		}
	} );
}

function choose( value, source ) {
	state.chosen = true;
	state.guests = clamp( value, state.guests );
	instances.forEach( ( instance ) => render( instance, instance === source ) );
	renderWindow();
}

function render( instance, typing ) {
	const booked = restaurantOf( instance );
	const restaurant = booked || restaurants[ 0 ];
	const waiting = ! instance.modal && ! state.chosen;
	const action = goState( { chosen: state.chosen, guests: state.guests, restaurant: booked }, ! instance.modal );
	const key = tier( state.guests, restaurant );
	const root = instance.root;
	if ( ! typing ) {
		instance.input.value = waiting ? '' : state.guests;
	}
	root.querySelector( '[data-unit]' ).textContent = waiting || state.guests > 1 ? i18n.guests : i18n.guest;
	root.querySelector( '[data-delta="-1"]' ).disabled = waiting || state.guests <= 1;
	root.querySelector( '[data-delta="1"]' ).disabled = state.guests >= MAX;
	root.dataset.tier = waiting ? 'empty' : key;

	const event = 'event' === action ? destination( restaurant, 'event', state.guests ) : null;
	const labels = {
		empty: i18n.empty,
		pick: i18n.pick,
		table: i18n.goTable,
		group: i18n.goGroup,
		event: event && 'contact' === event.kind ? i18n.goContact : i18n.goEvent,
	};
	root.querySelector( '[data-go]' ).dataset.state = action;
	root.querySelector( '[data-go-text]' ).textContent = labels[ action ];

	if ( ! instance.modal ) {
		return;
	}
	if ( instance.shown !== restaurant.id ) {
		instance.shown = restaurant.id;
		const names = tierLabels( restaurant, i18n );
		root.querySelector( '[data-chips]' ).innerHTML = chips( restaurant, i18n )
			.map( ( chip ) => '<button type="button" data-chip="' + chip.value + '" data-tier="' + chip.tier + '">' + escape( chip.label ) + '</button>' )
			.join( '' );
		root.querySelector( '[data-tiers]' ).innerHTML = [ 'table', 'group', 'event' ]
			.map( ( name ) => '<li data-tier="' + name + '"><span>' + escape( names[ name ] ) + '</span></li>' )
			.join( '' );
	}
	root.querySelectorAll( '[data-chip]' ).forEach( ( chip ) => {
		const value = Number( chip.dataset.chip );
		chip.setAttribute( 'aria-pressed', String( 'event' === chip.dataset.tier ? state.guests > restaurant.xp_above : value === state.guests ) );
	} );
	root.querySelectorAll( '[data-tiers] li' ).forEach( ( li ) => li.classList.toggle( 'is-on', li.dataset.tier === key ) );
	const hints = {
		table: [ i18n.tableTitle, i18n.tableText ],
		group: [ i18n.groupTitle, booked ? restaurant.group_text : i18n.groupAny ],
		event: [ i18n.eventTitle, format( i18n.eventText, restaurant.xp_above ) ],
	};
	root.querySelector( '[data-hint-icon]' ).innerHTML = ICONS[ key ];
	root.querySelector( '[data-hint-title]' ).textContent = hints[ key ][ 0 ];
	root.querySelector( '[data-hint-text]' ).textContent = hints[ key ][ 1 ];
}

/** Draws attention to what the visitor has to fill first. */
function signal( element, focus ) {
	element.classList.remove( 'is-asking' );
	void element.offsetWidth; // Restarts the animation.
	element.classList.add( 'is-asking' );
	focus.focus();
}

function go( instance ) {
	const restaurant = restaurantOf( instance );
	const action = goState( { chosen: state.chosen, guests: state.guests, restaurant }, ! instance.modal );
	if ( 'empty' === action ) {
		signal( instance.root.querySelector( '.livia-sel-count' ), instance.input );
	} else if ( 'pick' === action && instance.modal ) {
		signal( picks, picks.querySelector( 'input' ) );
	} else if ( 'pick' === action ) {
		openWindow( '', false );
	} else {
		if ( ! instance.modal ) {
			state.target = instance.target;
		}
		route( restaurant, action );
	}
}

/* Window */

let dialog;
let picks;
let modal;
let lastFocus;

function part( name ) {
	return dialog.querySelector( '[data-' + name + ']' );
}

function build() {
	if ( dialog ) {
		return;
	}
	dialog = document.createElement( 'dialog' );
	dialog.className = 'livia-xp';
	dialog.tabIndex = -1;
	dialog.setAttribute( 'aria-labelledby', 'livia-xp-title' );
	dialog.innerHTML =
		'<button type="button" class="livia-xp-x" data-close aria-label="' + escape( i18n.close ) + '">' + ICONS.close + '</button>' +
		'<div class="livia-xp-choice" data-choice>' +
		'<div class="livia-xp-photo" data-photo>' +
		[ 'table', 'group', 'event' ].map( ( name ) => '<img data-tier="' + name + '" alt="">' ).join( '' ) +
		'<span class="livia-xp-name" data-name></span>' +
		'<div class="livia-xp-leg"><small data-label></small><p data-caption></p></div>' +
		'</div>' +
		'<div class="livia-xp-main">' +
		'<div data-asks>' +
		'<span class="livia-xp-ey">' + escape( i18n.book ) + '</span>' +
		'<h2 id="livia-xp-title" data-title></h2>' +
		'<fieldset class="livia-xp-picks" data-picks><legend class="livia-xp-sr">' + escape( i18n.restaurant ) + '</legend></fieldset>' +
		'<div data-selector></div>' +
		'</div>' +
		'<div class="livia-xp-contact" data-contact hidden>' +
		'<button type="button" class="livia-xp-back" data-back>' + ICONS.back + escape( i18n.back ) + '</button>' +
		'<small data-contact-label></small><h3>' + escape( i18n.contactTitle ) + '</h3><p data-contact-text></p>' +
		'<ul data-contact-list></ul>' +
		'<a class="wp-element-button livia-sel-go" data-write><span>' + escape( i18n.write ) + '</span>' + ICONS.arrow + '</a>' +
		'</div>' +
		'</div>' +
		'</div>' +
		'<div class="livia-xp-panel" data-panel hidden>' +
		'<div class="livia-xp-bar">' +
		'<button type="button" class="livia-xp-back" data-back>' + ICONS.back + escape( i18n.back ) + '</button>' +
		'<div class="livia-xp-ttl"><b data-panel-title></b><span data-panel-sub></span></div>' +
		'<a class="livia-xp-ext" data-external target="_blank" rel="noopener">' + escape( i18n.openTab ) + ICONS.external + '</a>' +
		'</div>' +
		'<div class="livia-xp-frame" data-frame><div class="livia-xp-loading">' + escape( i18n.loading ) + '</div><iframe title="" allow="payment"></iframe><div class="livia-xp-chat" data-chat></div></div>' +
		'</div>';
	document.body.appendChild( dialog );
	picks = part( 'picks' );
	picks.insertAdjacentHTML(
		'beforeend',
		restaurants
			.map(
				( restaurant ) =>
					'<label class="livia-xp-pick"><input type="radio" name="livia-xp-pick" value="' + escape( restaurant.id ) + '">' +
					( restaurant.xp_photo ? '<img src="' + escape( restaurant.xp_photo ) + '" alt="">' : '' ) +
					'<span class="livia-xp-ck">' + ICONS.check + '</span><b>' + escape( restaurant.name ) + '</b>' +
					( restaurant.xp_phrase ? '<span>' + escape( restaurant.xp_phrase ) + '</span>' : '' ) +
					'</label>'
			)
			.join( '' )
	);
	picks.addEventListener( 'change', ( event ) => {
		state.chosenId = event.target.value;
		openOnGroup();
		instances.forEach( ( instance ) => render( instance ) );
		renderWindow();
	} );
	modal = mount( part( 'selector' ), 'modal' );
	const iframe = part( 'frame' ).querySelector( 'iframe' );
	iframe.addEventListener( 'load', () => part( 'frame' ).classList.toggle( 'is-ready', !! iframe.getAttribute( 'src' ) ) );

	dialog.addEventListener( 'click', ( event ) => {
		// A click on the backdrop reaches the dialog itself.
		if ( event.target === dialog || event.target.closest( '[data-close]' ) ) {
			dialog.close();
		} else if ( event.target.closest( '[data-back]' ) ) {
			showChoice();
		}
	} );
	dialog.addEventListener( 'close', () => {
		document.documentElement.classList.remove( 'livia-xp-open' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	} );
}

function showDialog() {
	if ( ! dialog.open ) {
		lastFocus = document.activeElement;
		dialog.showModal();
		document.documentElement.classList.add( 'livia-xp-open' );
	}
	// The screen that held the focus is hidden on every change of screen.
	dialog.focus();
}

/** The choice screen: the number of guests, and the restaurant when the site has several. */
function showChoice() {
	const chosen = pick( restaurants, state.target, state.chosenId );
	dialog.classList.toggle( 'is-multi', chosen.picker );
	part( 'title' ).textContent = chosen.picker ? i18n.whereHowMany : i18n.howMany;
	picks.hidden = ! chosen.picker;
	part( 'panel' ).hidden = true;
	part( 'choice' ).hidden = false;
	part( 'asks' ).hidden = false;
	part( 'contact' ).hidden = true;
	instances.forEach( ( instance ) => render( instance ) );
	renderWindow();
	showDialog();
}

/** The photo and the restaurant's choice follow the number and the restaurant. */
function renderWindow() {
	if ( ! dialog ) {
		return;
	}
	const restaurant = restaurantOf( modal ) || restaurants[ 0 ];
	const key = tier( state.guests, restaurant );
	part( 'photo' ).querySelectorAll( 'img' ).forEach( ( img ) => {
		const url = restaurant[ 'xp_' + img.dataset.tier + '_photo' ] || restaurant.xp_photo;
		if ( url && img.getAttribute( 'src' ) !== url ) {
			img.setAttribute( 'src', url );
		}
		img.hidden = ! url;
		img.classList.toggle( 'is-on', img.dataset.tier === key );
	} );
	part( 'name' ).textContent = restaurant.name;
	part( 'label' ).textContent = { table: i18n.labelTable, group: i18n.labelGroup, event: format( i18n.labelEvent, restaurant.xp_above ) }[ key ];
	part( 'caption' ).textContent = restaurant[ 'xp_' + key + '_caption' ] || '';
	picks.querySelectorAll( 'input' ).forEach( ( input ) => {
		input.checked = input.value === state.chosenId;
	} );
}

/** Until the visitor chooses a number, the window shows the group threshold of the restaurant it books. */
function openOnGroup() {
	const restaurant = pick( restaurants, state.target, state.chosenId ).restaurant;
	if ( ! state.chosen && restaurant ) {
		state.guests = restaurant.xp_from;
	}
}

function openWindow( target, event ) {
	if ( ! restaurants.length ) {
		return;
	}
	build();
	state.target = target || '';
	openOnGroup();
	const chosen = pick( restaurants, state.target, state.chosenId );
	if ( event ) {
		// Above every event threshold: whichever restaurant the visitor then chooses, its event path opens.
		const above = Math.max( ...( chosen.restaurant ? [ chosen.restaurant ] : restaurants ).map( ( restaurant ) => restaurant.xp_above ) );
		if ( ! state.chosen || state.guests <= above ) {
			choose( above + 1 );
		}
		if ( chosen.restaurant ) {
			route( chosen.restaurant, 'event' );
			return;
		}
	}
	showChoice();
}

/** Leads to where a tier goes. */
function route( restaurant, key ) {
	const to = destination( restaurant, key, state.guests );
	if ( 'tool' === to.kind && 'tab' === to.open ) {
		window.open( to.url, '_blank', 'noopener' );
		return;
	}
	if ( 'page' === to.kind || ( 'tool' === to.kind && 'page' === to.open ) ) {
		// An address of the same page only scrolls: the window must not stay over it.
		if ( dialog && dialog.open ) {
			dialog.close();
		}
		window.location.assign( to.url );
		return;
	}
	build();
	if ( 'contact' === to.kind ) {
		showContact( restaurant, key );
		return;
	}
	const frame = part( 'frame' );
	const iframe = frame.querySelector( 'iframe' );
	const chat = part( 'chat' );
	part( 'choice' ).hidden = true;
	part( 'panel' ).hidden = false;
	part( 'panel-sub' ).textContent = guestsText( state.guests ) + ' · ' + restaurant.name;
	frame.dataset.mode = to.kind;
	if ( 'tool' === to.kind ) {
		part( 'panel-title' ).textContent = 'event' === key ? i18n.eventTool : i18n.tableTitle;
		part( 'external' ).hidden = false;
		part( 'external' ).href = to.url;
		iframe.title = part( 'panel-title' ).textContent;
		if ( iframe.getAttribute( 'src' ) !== to.url ) {
			frame.classList.remove( 'is-ready' );
			iframe.setAttribute( 'src', to.url );
		}
	} else {
		part( 'panel-title' ).textContent = restaurant.xp_group_title || i18n.chatTitle;
		part( 'external' ).hidden = true;
		openChat( chat, to.widget );
	}
	showDialog();
}

/** The Livia conversation, its field filled with the number of guests; the widget's bundle loads on first use. */
function openChat( container, widget ) {
	const current = container.firstChild;
	if ( current && current.getAttribute( 'widget' ) === widget && current.getAttribute( 'guests' ) === String( state.guests ) ) {
		return;
	}
	const chat = document.createElement( 'livia-chat' );
	// The dialog may still be closed here: the conversation starts once the dialog shows it.
	chat.setAttribute( 'start-when-visible', '' );
	chat.setAttribute( 'widget', widget );
	chat.setAttribute( 'guests', String( state.guests ) );
	if ( config.backend ) {
		chat.setAttribute( 'backend-url', config.backend );
	}
	if ( config.preview ) {
		chat.setAttribute( 'preview', '' );
	}
	container.replaceChildren( chat );
	const loaded = customElements.get( 'livia-chat' ) || document.querySelector( 'script[src="' + config.bundle + '"], script[data-livia-bundle]' );
	if ( ! loaded && config.bundle ) {
		const script = document.createElement( 'script' );
		script.src = config.bundle;
		script.async = true;
		script.dataset.liviaBundle = '';
		document.head.appendChild( script );
	}
}

function showContact( restaurant, key ) {
	dialog.classList.toggle( 'is-multi', pick( restaurants, state.target, state.chosenId ).picker );
	part( 'choice' ).hidden = false;
	part( 'panel' ).hidden = true;
	part( 'asks' ).hidden = true;
	part( 'contact' ).hidden = false;
	renderWindow();
	part( 'contact-label' ).textContent = 'event' === key ? format( i18n.labelEvent, restaurant.xp_above ) : '';
	part( 'contact-text' ).textContent = 'event' === key ? format( i18n.contactText, restaurant.xp_above ) : i18n.contactAny;
	const lines = [
		[ 'mail', i18n.byEmail, restaurant.xp_email ],
		[ 'phone', i18n.byPhone, restaurant.xp_phone ],
		[ 'pin', i18n.onSite, restaurant.xp_address ],
	].filter( ( line ) => line[ 2 ] );
	part( 'contact-list' ).innerHTML = lines
		.map( ( line ) => '<li><i>' + ICONS[ line[ 0 ] ] + '</i><span><small>' + escape( line[ 1 ] ) + '</small>' + escape( line[ 2 ] ) + '</span></li>' )
		.join( '' );
	const write = part( 'write' );
	write.hidden = ! restaurant.xp_email;
	write.href = 'mailto:' + restaurant.xp_email;
	showDialog();
}

/* Start */

function mountAll( root ) {
	root.querySelectorAll( '[data-livia-convives]:not([data-mounted])' ).forEach( ( container ) =>
		mount( container, 'band' === container.dataset.liviaConvives ? 'band' : 'card' )
	);
}

function start() {
	if ( ! restaurants.length ) {
		return;
	}
	mountAll( document );
	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest( '.livia-reserver, a[href*="#reserver"]' );
		if ( ! trigger || ( ! trigger.classList.contains( 'livia-reserver' ) && '#reserver' !== trigger.hash ) ) {
			return;
		}
		event.preventDefault();
		const data = ( trigger.matches( '[data-livia-restaurant], [data-livia-event]' ) ? trigger : trigger.querySelector( '[data-livia-restaurant], [data-livia-event]' ) ) || trigger;
		openWindow( data.dataset.liviaRestaurant || '', 'liviaEvent' in data.dataset );
	} );
	// Elementor's editor adds elements to the page it previews without reloading it.
	if ( document.body.classList.contains( 'elementor-editor-active' ) ) {
		new MutationObserver( () => mountAll( document ) ).observe( document.body, { childList: true, subtree: true } );
	}
	// The admin's preview opens the window at once.
	if ( config.autoOpen ) {
		openWindow( '', false );
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
