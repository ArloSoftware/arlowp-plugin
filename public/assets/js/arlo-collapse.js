/**
 * Show and hide panels for the Starter themes.
 *
 * Original Arlo code. It reads the markup the themes already use: a trigger marked
 * data-toggle="collapse" with a data-target selector (or a #fragment href), and a target with the
 * "collapse" class that is open while it also has "in". The trigger gets "collapsed" and aria-expanded
 * to match, and the theme CSS (.collapse, .collapse.in, .collapsing, .collapsed) does the styling.
 *
 * Supported: click triggers in Arlo content (#arlo or .arlo) or that open a panel in it,
 * window.ArloCollapse.show/hide/toggle( element ), and the jQuery calls .collapse( 'show' | 'hide' | 'toggle' )
 * when jQuery has no collapse plugin of its own.
 * Clicks are left to a Bootstrap 3 collapse plugin loaded by the site, which handles them itself.
 * Not supported: data-parent accordions and the *.bs.collapse events.
 */
( function ( window, document ) {
	'use strict';

	var TRIGGER = '[data-toggle="collapse"]';
	// Arlo content is inside #arlo, or inside another .arlo element when an edited template drops the id or the class.
	// On Arlo pages the body also has the "arlo" class, so it must not count, or the site theme's own collapse
	// controls would be taken over.
	var ARLO_CONTENT = '#arlo, .arlo';
	// Matches the transition-duration of .collapsing in the theme CSS.
	var DURATION = 350;

	function targetsOf( trigger ) {
		var selector = trigger.getAttribute( 'data-target' );
		var href = trigger.getAttribute( 'href' );

		if ( ! selector && href && href.length > 1 && '#' === href.charAt( 0 ) ) {
			selector = href;
		}
		if ( ! selector ) {
			return [];
		}

		try {
			return Array.prototype.slice.call( document.querySelectorAll( selector ) );
		} catch ( error ) {
			return [];
		}
	}

	function setTriggerState( target, expanded ) {
		var triggers = document.querySelectorAll( TRIGGER );

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			if ( targetsOf( trigger ).indexOf( target ) === -1 ) {
				return;
			}
			trigger.classList.toggle( 'collapsed', ! expanded );
			trigger.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}

	function canAnimate( target ) {
		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		return ! reduced && ! /^(TR|TBODY)$/.test( target.tagName );
	}

	// Runs done once, after the height transition ends or, if it never fires (nothing to animate), after DURATION.
	function afterTransition( target, done ) {
		var finished = false;
		var timer;

		function finish( event ) {
			if ( finished || ( event && event.target !== target ) ) {
				return;
			}
			finished = true;
			window.clearTimeout( timer );
			target.removeEventListener( 'transitionend', finish );
			done();
		}

		target.addEventListener( 'transitionend', finish );
		timer = window.setTimeout( finish, DURATION + 50 );
	}

	// show(), hide() and toggle() return true when they changed the panel, and false when it was already in that state
	// or still animating. A caller that keeps its own state in step with the panel needs to know the difference.
	function show( target ) {
		if ( target.arloCollapseBusy || target.classList.contains( 'in' ) ) {
			return false;
		}
		setTriggerState( target, true );

		if ( ! canAnimate( target ) ) {
			target.classList.add( 'in' );
			return true;
		}

		target.arloCollapseBusy = true;
		target.classList.remove( 'collapse' );
		target.classList.add( 'collapsing' );
		target.style.height = '0px';
		target.style.height = target.scrollHeight + 'px';

		afterTransition( target, function () {
			target.classList.remove( 'collapsing' );
			target.classList.add( 'collapse', 'in' );
			target.style.height = '';
			target.arloCollapseBusy = false;
		} );

		return true;
	}

	function hide( target ) {
		if ( target.arloCollapseBusy || ! target.classList.contains( 'in' ) ) {
			return false;
		}
		setTriggerState( target, false );

		if ( ! canAnimate( target ) ) {
			target.classList.remove( 'in' );
			return true;
		}

		target.arloCollapseBusy = true;
		target.style.height = target.getBoundingClientRect().height + 'px';
		target.classList.add( 'collapsing' );
		target.classList.remove( 'collapse', 'in' );
		// Reading offsetHeight makes the browser apply the start height before the transition runs.
		void target.offsetHeight;
		target.style.height = '0px';

		afterTransition( target, function () {
			target.classList.remove( 'collapsing' );
			target.classList.add( 'collapse' );
			target.style.height = '';
			target.arloCollapseBusy = false;
		} );

		return true;
	}

	function toggle( target ) {
		return target.classList.contains( 'in' ) ? hide( target ) : show( target );
	}

	function isArloContent( element ) {
		var wrapper = element.closest( ARLO_CONTENT );

		return !! wrapper && wrapper !== document.body && wrapper !== document.documentElement;
	}

	// A control belongs to Arlo when it, or the panel it opens, is inside Arlo content. A button outside the wrapper
	// can still open a panel inside it.
	function isArloControl( trigger, targets ) {
		return isArloContent( trigger ) || targets.some( isArloContent );
	}

	// A Bootstrap 3 collapse plugin that the site's own theme or plugins load opens and closes the same panels
	// through the same "in" class, so a second handler would undo its toggle. Bootstrap 4 and later use "show"
	// instead, so they can run alongside this script.
	function bootstrapThreeHandlesClicks() {
		var plugin = window.jQuery && window.jQuery.fn.collapse;
		var version = plugin && plugin.Constructor ? plugin.Constructor.VERSION : '';

		return !! plugin && ! plugin.arloCollapse && ( ! version || '3' === String( version ).charAt( 0 ) );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest ? event.target.closest( TRIGGER ) : null;
		var targets;

		if ( ! trigger || bootstrapThreeHandlesClicks() ) {
			return;
		}

		targets = targetsOf( trigger );
		if ( ! isArloControl( trigger, targets ) ) {
			return;
		}
		if ( ! trigger.getAttribute( 'data-target' ) ) {
			event.preventDefault();
		}

		targets.forEach( toggle );
	} );

	function initialiseState() {
		Array.prototype.forEach.call( document.querySelectorAll( TRIGGER ), function ( trigger ) {
			var targets = targetsOf( trigger );

			if ( targets.length && isArloControl( trigger, targets ) ) {
				trigger.setAttribute( 'aria-expanded', targets[ 0 ].classList.contains( 'in' ) ? 'true' : 'false' );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialiseState );
	} else {
		initialiseState();
	}

	window.ArloCollapse = { show: show, hide: hide, toggle: toggle };

	if ( window.jQuery && ! window.jQuery.fn.collapse ) {
		window.jQuery.fn.collapse = function ( command ) {
			if ( 'show' !== command && 'hide' !== command && 'toggle' !== command ) {
				return this;
			}

			return this.each( function () {
				window.ArloCollapse[ command ]( this );
			} );
		};
		window.jQuery.fn.collapse.arloCollapse = true;
	}
}( window, document ) );
