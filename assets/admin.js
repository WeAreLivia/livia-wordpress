/**
 * Livia settings screen: the widget menus redraw their label, the page list follows the display rule, the address
 * state clears once the address is edited (checked again on save), a removal asks first, a widget's shortcode copies
 * in one click, the booking experience picks its photos in the media library, orders its restaurants and shows one
 * restaurant at a time, and the preview window shows the settings in progress, saved or not. Every text comes from the
 * screen's markup.
 */
( function () {
	document.querySelectorAll( '[data-livia-widget]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			var option = select.options[ select.selectedIndex ];
			var label = select.parentNode.querySelector( '.lv-sel-label' );
			label.querySelector( 'b' ).textContent = option.dataset.name;
			label.querySelector( 'span' ).textContent = option.dataset.format;
		} );
	} );

	document.querySelectorAll( '[data-livia-restaurant]' ).forEach( function ( card ) {
		var pages = card.querySelector( '[data-livia-pages]' );
		card.querySelectorAll( 'input[type="radio"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( pages ) {
					pages.hidden = 'all' === radio.value;
				}
			} );
		} );

		var address = card.querySelector( '[data-livia-address]' );
		if ( address ) {
			var slug = address.querySelector( 'input' );
			slug.addEventListener( 'input', function () {
				var state = address.querySelector( '.lv-strip' );
				if ( state ) {
					state.hidden = true;
				}
				address.classList.remove( 'lv-att' );
				slug.classList.remove( 's-ok-in', 's-err-in' );
				slug.removeAttribute( 'aria-invalid' );
			} );
		}
	} );

	document.querySelectorAll( '[data-livia-copy]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			navigator.clipboard.writeText( button.dataset.liviaCopy ).then( function () {
				var label = button.querySelector( 'span' );
				var text = label.textContent;
				label.textContent = button.dataset.done;
				setTimeout( function () {
					label.textContent = text;
				}, 2000 );
			} );
		} );
	} );

	document.querySelectorAll( '[data-livia-media]' ).forEach( function ( media ) {
		var input = media.querySelector( 'input' );
		var img = media.querySelector( 'img' );
		var clear = media.querySelector( '[data-livia-media-clear]' );
		function use( id, url ) {
			input.value = id;
			input.dataset.url = url;
			img.src = url;
			img.hidden = ! url;
			clear.hidden = ! url;
		}
		media.querySelector( '[data-livia-media-pick]' ).addEventListener( 'click', function () {
			var frame = window.wp.media( { library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var image = frame.state().get( 'selection' ).first().toJSON();
				use( image.id, image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url );
			} );
			frame.open();
		} );
		clear.addEventListener( 'click', function () {
			use( '', '' );
		} );
	} );

	var order = document.querySelector( '[data-livia-order]' );
	if ( order ) {
		var dragged = null;
		order.addEventListener( 'dragstart', function ( event ) {
			dragged = event.target.closest( 'li' );
		} );
		order.addEventListener( 'dragover', function ( event ) {
			var over = event.target.closest( 'li' );
			event.preventDefault();
			if ( dragged && over && over !== dragged ) {
				order.insertBefore( dragged, over.compareDocumentPosition( dragged ) & Node.DOCUMENT_POSITION_FOLLOWING ? over : over.nextSibling );
			}
		} );
		order.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-livia-move]' );
			var item = button && button.closest( 'li' );
			if ( ! item ) {
				return;
			}
			var sibling = '-1' === button.dataset.liviaMove ? item.previousElementSibling : item.nextElementSibling;
			if ( sibling ) {
				order.insertBefore( item, '-1' === button.dataset.liviaMove ? sibling : sibling.nextSibling );
				button.focus();
			}
		} );
	}

	document.querySelectorAll( '[data-livia-panel]' ).forEach( function ( button, index, buttons ) {
		button.addEventListener( 'click', function () {
			buttons.forEach( function ( other ) {
				other.setAttribute( 'aria-pressed', String( other === button ) );
				document.querySelector( '[data-livia-restaurant="' + other.dataset.liviaPanel + '"]' ).hidden = other !== button;
			} );
		} );
	} );

	document.querySelectorAll( '[data-tiers]' ).forEach( function ( panel ) {
		var formats = JSON.parse( panel.dataset.tiers );
		var id = panel.dataset.liviaRestaurant;
		var from = document.querySelector( '[name="livia[' + id + '][xp_from]"]' );
		var above = document.querySelector( '[name="livia[' + id + '][xp_above]"]' );
		function fill( text, values ) {
			var next = 0;
			return text.replace( /%(?:(\d+)\$)?d/g, function ( match, index ) {
				return String( values[ index ? Number( index ) - 1 : next++ ] );
			} );
		}
		function tiers() {
			var a = parseInt( from.value, 10 );
			var b = parseInt( above.value, 10 );
			if ( a >= 2 && b >= a ) {
				panel.querySelector( '[data-livia-tier="table"]' ).textContent = fill( formats.table, [ a - 1 ] );
				panel.querySelector( '[data-livia-tier="group"]' ).textContent = fill( formats.group, [ a, b ] );
				panel.querySelector( '[data-livia-tier="event"]' ).textContent = fill( formats.event, [ b + 1 ] );
			}
		}
		function event() {
			var checked = panel.querySelector( '[name="livia[' + id + '][xp_event_dest]"]:checked' );
			panel.querySelectorAll( '[data-livia-when]' ).forEach( function ( row ) {
				row.hidden = ! checked || row.dataset.liviaWhen !== checked.value;
			} );
		}
		[ from, above ].forEach( function ( input ) {
			input.addEventListener( 'input', tiers );
		} );
		panel.addEventListener( 'change', event );
		event();
	} );

	document.querySelectorAll( '[data-livia-confirm]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( form.dataset.liviaConfirm ) ) {
				event.preventDefault();
			}
		} );
	} );

	var dialog = document.querySelector( '[data-livia-dialog]' );
	var form = document.querySelector( '[data-livia-tab]' );
	if ( ! dialog || ! form ) {
		return;
	}
	var frame = dialog.querySelector( '[data-livia-frame]' );
	var iframe = frame.querySelector( 'iframe' );
	var cards = form.querySelectorAll( '[data-livia-restaurant]' );
	var current = cards[ 0 ];

	function field( card, name ) {
		return card.querySelector( '[name="livia[' + card.dataset.liviaRestaurant + '][' + name + ']"]' );
	}

	/**
	 * The booking experience as saved, with the values of the form in place of the saved ones.
	 */
	function experience() {
		var data = JSON.parse( form.querySelector( '[data-livia-xp-preview]' ).textContent );
		var saved = {};
		data.config.restaurants.forEach( function ( restaurant ) {
			saved[ restaurant.id ] = restaurant;
		} );
		var ids = Array.prototype.map.call( form.querySelectorAll( '[name="livia_xp_order[]"]' ), function ( input ) {
			return input.value;
		} );
		data.config.restaurants = ( ids.length ? ids : Object.keys( saved ) ).map( function ( id ) {
			var restaurant = Object.assign( {}, saved[ id ] );
			var prefix = 'livia[' + id + '][';
			form.querySelectorAll( '[name^="' + prefix + 'xp_"]' ).forEach( function ( input ) {
				var key = input.name.slice( prefix.length, -1 );
				if ( 'radio' === input.type && ! input.checked ) {
					return;
				}
				if ( undefined !== input.dataset.url ) {
					restaurant[ key ] = input.dataset.url;
				} else if ( 'xp_from' === key || 'xp_above' === key ) {
					restaurant[ key ] = parseInt( input.value, 10 ) || restaurant[ key ];
				} else {
					restaurant[ key ] = input.value;
				}
			} );
			return restaurant;
		} );
		return data;
	}

	function showExperience() {
		var data = experience();
		var text = function ( value ) {
			return String( value ).replace( /[&<>"]/g, function ( c ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
			} );
		};
		var json = JSON.stringify( data.config ).replace( /</g, '\\u003c' );
		frame.hidden = false;
		dialog.querySelector( '[data-livia-empty]' ).hidden = true;
		dialog.querySelector( '[data-livia-address-bar]' ).textContent = current.dataset.address;
		iframe.tabIndex = 0;
		iframe.srcdoc =
			'<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
			'<style>' + data.theme.replace( /<\//g, '<\\/' ) + '</style><link rel="stylesheet" href="' + text( data.css ) + '">' +
			'<style>body{margin:0;min-height:100vh;font-family:var(--livia-font,system-ui,sans-serif);background:var(--livia-bg,#fff);color:var(--livia-ink,#1e1e1e)}' +
			'.pv-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 24px;border-bottom:1px solid rgba(0,0,0,.1)}' +
			'.pv-head b{font-family:var(--livia-heading,inherit);font-size:20px}.pv-main{padding:32px 24px}</style></head><body>' +
			'<header class="pv-head"><b>' + text( data.site ) + '</b><a class="wp-element-button" href="#reserver">' + text( data.config.i18n.book ) + '</a></header>' +
			'<main class="pv-main"><div data-livia-convives="card"></div></main>' +
			'<script>var liviaExperience = ' + json + ';<\/script><script src="' + text( data.js ) + '"><\/script></body></html>';
	}

	function show() {
		if ( 'experience' === form.dataset.liviaTab ) {
			showExperience();
			return;
		}
		var page = 'page' === form.dataset.liviaTab;
		var select = current.querySelector( '[data-livia-widget]' );
		var phone = field( current, 'phone_widget' );
		if ( phone && phone.value && frame.classList.contains( 'phone' ) ) {
			select = phone;
		}
		var siteOn = form.querySelector( '[name="livia_site_on"]' );
		// The page tab's empty choice follows the Livia booking page's widget; a bubble needs a widget of its own.
		var shown = select && ( page || ( siteOn.checked && field( current, 'bubble_on' ).checked && select.value ) );
		// Printed only when the phone bar shows on the site, where it hides the bubble on phones.
		var underBar = dialog.querySelector( '[data-livia-under-bar]' );
		var barred = !! ( shown && underBar && frame.classList.contains( 'phone' ) );
		if ( underBar ) {
			underBar.hidden = ! barred;
		}
		shown = shown && ! barred;
		var src = new URL( current.dataset.preview );
		var address = current.dataset.address;
		if ( shown && select.value ) {
			src.searchParams.set( 'widget', select.value );
		}
		if ( page ) {
			var headline = field( current, 'headline' ).value.trim();
			if ( headline ) {
				src.searchParams.set( 'headline', headline );
			}
			address += field( current, 'slug' ).value.trim() + '/';
		}
		frame.hidden = ! shown;
		dialog.querySelector( '[data-livia-empty]' ).hidden = !! shown || barred;
		dialog.querySelector( '[data-livia-caption]' ).textContent = shown ? select.options[ select.selectedIndex ].dataset.description : '';
		dialog.querySelector( '[data-livia-address-bar]' ).textContent = address;
		if ( shown && iframe.src !== src.toString() ) {
			iframe.src = src.toString();
		}
		dialog.querySelectorAll( '[data-livia-pick]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', String( button.dataset.liviaPick === current.dataset.liviaRestaurant ) );
		} );
	}

	document.querySelector( '[data-livia-open]' ).addEventListener( 'click', function () {
		show();
		dialog.showModal();
	} );
	dialog.querySelector( '[data-livia-close]' ).addEventListener( 'click', function () {
		dialog.close();
	} );
	dialog.querySelectorAll( '[data-livia-pick]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			current = form.querySelector( '[data-livia-restaurant="' + button.dataset.liviaPick + '"]' );
			show();
		} );
	} );
	dialog.querySelectorAll( '[data-livia-device]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			frame.classList.toggle( 'phone', 'phone' === button.dataset.liviaDevice );
			dialog.querySelectorAll( '[data-livia-device]' ).forEach( function ( other ) {
				other.setAttribute( 'aria-pressed', String( other === button ) );
			} );
			if ( 'widget' === form.dataset.liviaTab ) {
				show();
			}
		} );
	} );
}() );
