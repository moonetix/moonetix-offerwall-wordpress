( function () {
	var root = document.querySelector( '.moonetix-ow' );
	if ( ! root ) {
		return;
	}
	var dialog = root.querySelector( '.moonetix-ow-dialog' );
	var last = null;
	var close = function () {
		if ( dialog && dialog.open ) {
			dialog.close();
		}
	};
	if ( dialog && typeof dialog.showModal === 'function' ) {
		var frame = dialog.querySelector( 'iframe' );
		var open = function ( from ) {
			if ( frame && ! frame.getAttribute( 'src' ) ) {
				frame.setAttribute( 'src', frame.getAttribute( 'data-src' ) );
			}
			last = from || null;
			if ( ! dialog.open ) {
				dialog.showModal();
			}
			document.documentElement.classList.add( 'moonetix-ow-lock' );
		};
		document.addEventListener( 'click', function ( e ) {
			if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || ! e.target.closest ) {
				return;
			}
			var hit = e.target.closest( '[data-moonetix-ow-open], .moonetix-ow-open' );
			if ( ! hit || dialog.contains( hit ) ) {
				return;
			}
			var link = e.target.closest( 'a, button' ) || hit;
			if ( link !== hit && hit.contains( link ) ) {
				var item = link.parentElement ? link.parentElement.closest( 'li' ) : null;
				if ( item && item !== hit && hit.contains( item ) ) {
					return;
				}
				if ( link.tagName === 'BUTTON' && link.hasAttribute( 'aria-expanded' ) ) {
					return;
				}
			}
			e.preventDefault();
			open( link );
		} );
		dialog.addEventListener( 'close', function () {
			document.documentElement.classList.remove( 'moonetix-ow-lock' );
			if ( last && typeof last.focus === 'function' ) {
				last.focus();
			}
		} );
		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === dialog ) {
				close();
			}
		} );
		var closer = dialog.querySelector( '[data-moonetix-ow-close]' );
		if ( closer ) {
			closer.addEventListener( 'click', close );
		}
		window.addEventListener( 'message', function ( e ) {
			if ( e.origin === 'https://wall.moonetix.com' && frame && e.source === frame.contentWindow && e.data && e.data.shk === 'close' ) {
				close();
			}
		} );
	}
	var toasts = root.querySelectorAll( '.moonetix-ow-toast' );
	Array.prototype.forEach.call( toasts, function ( t, i ) {
		setTimeout( function () {
			t.classList.add( 'is-out' );
			setTimeout( function () {
				t.remove();
			}, 400 );
		}, 6000 + i * 800 );
		t.addEventListener( 'click', function () {
			t.remove();
		} );
	} );
} )();
