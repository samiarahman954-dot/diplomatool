/**
 * Lilo Cafe front-end behavior: header, live open status, menu tabs, anchor scrolling.
 * Works on the live site and inside the Elementor editor preview.
 */
( function () {
	'use strict';

	var root = document.documentElement;

	/* ------------------------------------------------------------------
	 * Header height → CSS variable (used for sticky offsets)
	 * ---------------------------------------------------------------- */
	function headerEl() {
		return document.querySelector( '.lilo-site-header' );
	}

	function updateHeaderHeight() {
		var h = headerEl();
		if ( ! h ) {
			return;
		}
		var sticky = document.body.classList.contains( 'lilo-has-sticky-header' );
		root.style.setProperty( '--lilo-header-h', ( sticky ? h.offsetHeight : 0 ) + 'px' );
	}

	function adminBarHeight() {
		var bar = document.getElementById( 'wpadminbar' );
		return bar && getComputedStyle( bar ).position === 'fixed' ? bar.offsetHeight : 0;
	}

	/* ------------------------------------------------------------------
	 * Mobile navigation toggle
	 * ---------------------------------------------------------------- */
	function initHeader( scope ) {
		( scope || document ).querySelectorAll( '.lilo-header__toggle' ).forEach( function ( btn ) {
			if ( btn.dataset.liloReady ) {
				return;
			}
			btn.dataset.liloReady = '1';
			var header = btn.closest( '.lilo-header' );
			btn.addEventListener( 'click', function () {
				var open = ! header.classList.contains( 'is-open' );
				header.classList.toggle( 'is-open', open );
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
			header.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.lilo-nav a' ) ) {
					header.classList.remove( 'is-open' );
					btn.setAttribute( 'aria-expanded', 'false' );
				}
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && header.classList.contains( 'is-open' ) ) {
					header.classList.remove( 'is-open' );
					btn.setAttribute( 'aria-expanded', 'false' );
					btn.focus();
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Live open / closed status
	 * ---------------------------------------------------------------- */
	function storeNow( tz ) {
		var now = new Date();
		if ( tz && /^[+-]\d{2}:\d{2}$/.test( tz ) ) {
			var sign = tz[ 0 ] === '-' ? -1 : 1;
			var offset = sign * ( parseInt( tz.substr( 1, 2 ), 10 ) * 60 + parseInt( tz.substr( 4, 2 ), 10 ) );
			var shifted = new Date( now.getTime() + ( offset + now.getTimezoneOffset() ) * 60000 );
			return { day: shifted.getDay(), minutes: shifted.getHours() * 60 + shifted.getMinutes() };
		}
		if ( tz ) {
			try {
				var parts = new Intl.DateTimeFormat( 'en-US', { timeZone: tz, weekday: 'short', hour: 'numeric', minute: 'numeric', hour12: false } ).formatToParts( now );
				var map = {};
				parts.forEach( function ( p ) {
					map[ p.type ] = p.value;
				} );
				var days = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
				var hour = parseInt( map.hour, 10 ) % 24;
				return { day: days.indexOf( map.weekday ), minutes: hour * 60 + parseInt( map.minute, 10 ) };
			} catch ( err ) {
				// Unknown timezone: fall back to the visitor's clock.
			}
		}
		return { day: now.getDay(), minutes: now.getHours() * 60 + now.getMinutes() };
	}

	function fill( template, values ) {
		return String( template ).replace( /\{(\w+)\}/g, function ( m, key ) {
			return Object.prototype.hasOwnProperty.call( values, key ) ? values[ key ] : m;
		} );
	}

	function renderStatus( el ) {
		var cfg;
		try {
			cfg = JSON.parse( el.getAttribute( 'data-lilo-status' ) );
		} catch ( err ) {
			return;
		}
		var now = storeNow( cfg.tz );
		var today = cfg.schedule[ now.day ];
		var text = el.querySelector( '.lilo-status__text' );
		el.classList.remove( 'is-open', 'is-later', 'is-closed' );

		if ( ! today ) {
			var next = null;
			for ( var i = 1; i <= 7; i++ ) {
				var d = ( now.day + i ) % 7;
				if ( cfg.schedule[ d ] ) {
					next = d;
					break;
				}
			}
			el.classList.add( 'is-closed' );
			text.textContent = next === null ? fill( cfg.closed, { day: cfg.days[ now.day ], next_day: '', next_open: '', hours: '' } ).replace( /\s*·\s*$/, '' ) : fill( cfg.closed, {
				day: cfg.days[ now.day ],
				next_day: cfg.days[ next ],
				next_open: cfg.schedule[ next ][ 2 ].split( '–' )[ 0 ],
				hours: '',
			} );
			return;
		}

		var isOpen = now.minutes >= today[ 0 ] && now.minutes < today[ 1 ];
		el.classList.add( isOpen ? 'is-open' : 'is-later' );
		text.textContent = fill( isOpen ? cfg.open : cfg.later, { hours: today[ 2 ], day: cfg.days[ now.day ] } );
	}

	function initStatus( scope ) {
		( scope || document ).querySelectorAll( '[data-lilo-status]' ).forEach( renderStatus );
	}

	/* ------------------------------------------------------------------
	 * Menu tabs: auto-build, scroll spy
	 * ---------------------------------------------------------------- */
	function buildAutoTabs( scope ) {
		( scope || document ).querySelectorAll( '[data-lilo-auto-tabs]' ).forEach( function ( nav ) {
			var inner = nav.querySelector( '.lilo-mtabs__inner' );
			inner.innerHTML = '';
			document.querySelectorAll( '[data-lilo-tab]' ).forEach( function ( sec ) {
				if ( ! sec.id ) {
					return;
				}
				var a = document.createElement( 'a' );
				a.className = 'lilo-mtabs__tab';
				a.href = '#' + sec.id;
				a.textContent = sec.getAttribute( 'data-lilo-tab' );
				inner.appendChild( a );
			} );
		} );
	}

	function tabsOffset() {
		var tabs = document.querySelector( '.lilo-mtabs' );
		if ( ! tabs ) {
			return 0;
		}
		var wrap = tabs.closest( '.lilo-mtabs-sticky-yes' ) || tabs.closest( '.lilo-mtabs-sticky' );
		return wrap ? tabs.offsetHeight : 0;
	}

	function scrollOffset( target ) {
		var offset = adminBarHeight();
		var h = headerEl();
		if ( h && document.body.classList.contains( 'lilo-has-sticky-header' ) ) {
			offset += h.offsetHeight;
		}
		if ( target && target.closest( '.lilo-msec' ) ) {
			offset += tabsOffset();
		}
		return offset + 4;
	}

	var spyTicking = false;
	function scrollSpy() {
		spyTicking = false;
		document.querySelectorAll( '.lilo-mtabs.is-spy' ).forEach( function ( nav ) {
			var links = nav.querySelectorAll( '.lilo-mtabs__tab' );
			var line = scrollOffset( document.querySelector( '.lilo-msec' ) ) + 24;
			var current = null;
			links.forEach( function ( a ) {
				var sec = document.getElementById( decodeURIComponent( a.hash.slice( 1 ) ) );
				if ( sec && sec.getBoundingClientRect().top <= line ) {
					current = a;
				}
			} );
			links.forEach( function ( a ) {
				a.classList.toggle( 'is-active', a === current );
			} );
			if ( current && current.dataset.liloShown !== '1' ) {
				links.forEach( function ( a ) {
					delete a.dataset.liloShown;
				} );
				current.dataset.liloShown = '1';
				var bar = current.parentNode;
				var left = current.offsetLeft - ( bar.clientWidth - current.offsetWidth ) / 2;
				if ( bar.scrollTo ) {
					bar.scrollTo( { left: left, behavior: 'smooth' } );
				}
			}
		} );
	}

	function onScroll() {
		if ( ! spyTicking ) {
			spyTicking = true;
			window.requestAnimationFrame( scrollSpy );
		}
	}

	/* ------------------------------------------------------------------
	 * Same-page anchor links with sticky offsets
	 * ---------------------------------------------------------------- */
	function scrollToTarget( target, smooth ) {
		var top = target.getBoundingClientRect().top + window.pageYOffset - scrollOffset( target );
		window.scrollTo( { top: Math.max( 0, top ), behavior: smooth ? 'smooth' : 'auto' } );
	}

	function samePage( link ) {
		return link.pathname.replace( /\/$/, '' ) === window.location.pathname.replace( /\/$/, '' ) && link.host === window.location.host;
	}

	document.addEventListener(
		'click',
		function ( e ) {
			var link = e.target.closest && e.target.closest( 'a[href*="#"]' );
			if ( ! link || ! link.hash || link.hash === '#' || link.target === '_blank' || ! samePage( link ) ) {
				return;
			}
			if ( document.body.classList.contains( 'elementor-editor-active' ) ) {
				return;
			}
			var target;
			try {
				target = document.getElementById( decodeURIComponent( link.hash.slice( 1 ) ) );
			} catch ( err ) {
				return;
			}
			if ( ! target ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			scrollToTarget( target, true );
			if ( window.history && window.history.pushState ) {
				window.history.pushState( null, '', link.hash );
			}
		},
		true
	);

	/* ------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------- */
	function init( scope ) {
		initHeader( scope );
		initStatus( scope );
		buildAutoTabs( scope );
		updateHeaderHeight();
		scrollSpy();
	}

	function boot() {
		init( document );

		if ( window.ResizeObserver && headerEl() ) {
			new ResizeObserver( updateHeaderHeight ).observe( headerEl() );
		}
		window.addEventListener( 'resize', updateHeaderHeight );
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.setInterval( function () {
			initStatus( document );
		}, 60000 );

		// Arriving with a #hash (e.g. /menu/#ice): correct for the sticky header once layout settles.
		if ( window.location.hash.length > 1 ) {
			var fix = function () {
				var t;
				try {
					t = document.getElementById( decodeURIComponent( window.location.hash.slice( 1 ) ) );
				} catch ( err ) {
					return;
				}
				if ( t ) {
					scrollToTarget( t, false );
				}
			};
			window.addEventListener( 'load', function () {
				window.setTimeout( fix, 60 );
			} );
			if ( document.fonts && document.fonts.ready ) {
				document.fonts.ready.then( fix );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	// Elementor editor: re-run when a Lilo widget is (re)rendered.
	function hookElementor() {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return false;
		}
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
			var el = $scope && $scope[ 0 ];
			if ( el && el.className && String( el.className ).indexOf( 'elementor-widget-lilo-' ) !== -1 ) {
				init( el );
				buildAutoTabs( document );
			}
		} );
		return true;
	}
	if ( ! hookElementor() && window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}
} )();
