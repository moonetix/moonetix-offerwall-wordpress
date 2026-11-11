( function () {
	var root = document.querySelector( '.mxow' );
	if ( ! root ) {
		return;
	}
	Array.prototype.forEach.call( root.querySelectorAll( '[data-mxow-copy]' ), function ( btn ) {
		var label = btn.textContent;
		btn.addEventListener( 'click', function () {
			var input = document.getElementById( btn.getAttribute( 'data-mxow-copy' ) );
			if ( ! input ) {
				return;
			}
			var done = function () {
				btn.textContent = btn.getAttribute( 'data-done' ) || label;
				setTimeout( function () {
					btn.textContent = label;
				}, 2000 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( input.value ).then( done, function () {
					input.select();
				} );
			} else {
				input.select();
				try {
					document.execCommand( 'copy' );
					done();
				} catch ( e ) {}
			}
		} );
	} );
	var value = function ( name ) {
		var all = root.querySelectorAll( '[data-mxow-switch="' + name + '"]' );
		if ( ! all.length ) {
			return '';
		}
		if ( all[ 0 ].type === 'radio' ) {
			var picked = '';
			Array.prototype.forEach.call( all, function ( r ) {
				if ( r.checked ) {
					picked = r.value;
				}
			} );
			return picked;
		}
		return all[ 0 ].type === 'checkbox' ? ( all[ 0 ].checked ? '1' : '0' ) : all[ 0 ].value;
	};
	var sync = function () {
		Array.prototype.forEach.call( root.querySelectorAll( '[data-mxow-when]' ), function ( box ) {
			var rule = box.getAttribute( 'data-mxow-when' ).split( '=' );
			box.hidden = value( rule[ 0 ] ) !== rule[ 1 ];
		} );
	};
	Array.prototype.forEach.call( root.querySelectorAll( '[data-mxow-switch]' ), function ( el ) {
		el.addEventListener( 'change', sync );
	} );
	sync();
	var fmt = function ( n, d, min ) {
		return Number( n ).toLocaleString( undefined, { minimumFractionDigits: min || 0, maximumFractionDigits: d } );
	};
	var money = function ( n, d ) {
		return fmt( n, d, d );
	};
	var num = function ( el ) {
		var v = parseFloat( String( el ? el.value : '' ).replace( ',', '.' ) );
		return isFinite( v ) ? v : 0;
	};
	var rate = root.querySelector( '[data-mxow-rate]' );
	var rateOut = root.querySelector( '[data-mxow-rate-out]' );
	var showRate = function () {
		if ( ! rate || ! rateOut ) {
			return;
		}
		var r = num( rate );
		if ( r <= 0 ) {
			rateOut.textContent = '';
			return;
		}
		var dec = parseInt( rate.getAttribute( 'data-dec' ), 10 ) || 0;
		rateOut.textContent = ( rate.getAttribute( 'data-example' ) || '' ).replace( '%1$s', fmt( 250, 0 ) ).replace( '%2$s', money( 250 / r, dec ) + ' ' + rate.getAttribute( 'data-cur' ) );
	};
	if ( rate ) {
		rate.addEventListener( 'input', showRate );
		showRate();
	}
	var calc = root.querySelector( '[data-mxow-calc]' );
	if ( calc ) {
		var en = function ( n, max, min ) {
			return Number( n ).toLocaleString( 'en-US', { minimumFractionDigits: min || 0, maximumFractionDigits: max } );
		};
		var field = function ( k ) {
			return calc.querySelector( '[data-calc="' + k + '"]' );
		};
		var label = root.querySelector( '#mxow-label' );
		var unit = function () {
			if ( value( 'destination' ) === 'builtin' ) {
				return label && label.value.trim() !== '' ? label.value.trim() : calc.getAttribute( 'data-unit' );
			}
			return calc.getAttribute( 'data-unit-other' );
		};
		var put = function ( sel, text ) {
			Array.prototype.forEach.call( calc.querySelectorAll( sel ), function ( el ) {
				el.textContent = text;
			} );
		};
		var run = function () {
			var r = Math.max( 0, num( field( 'rate' ) ) );
			var sp = Math.round( Math.min( 100, Math.max( 0, num( field( 'split' ) ) ) ) );
			var three = field( 'round' ).value === 'three';
			var reward = function ( payout ) {
				var v = payout * r * sp / 100;
				return three ? Math.round( v * 1000 ) / 1000 : Math.round( v );
			};
			var pts = function ( v ) {
				if ( v <= 0 ) {
					return calc.getAttribute( 'data-zero' );
				}
				var name = unit();
				if ( v === 1 && name.length >= 3 && /[bcdfghjklmnpqrtvwxyze]s$/i.test( name ) && ! /(ss|us|is|os|as|ies|ves|ses|xes|zes|ches|shes)$/i.test( name ) ) {
					name = name.slice( 0, -1 );
				}
				return en( v, 3 ) + ' ' + name;
			};
			var walletOn = rate && value( 'destination' ) === 'terawallet' && num( rate ) > 0;
			var dec = rate ? parseInt( rate.getAttribute( 'data-dec' ), 10 ) || 0 : 2;
			put( '[data-calc-out="rate"]', en( r, 3 ) + ' ' + unit() );
			put( '[data-calc-out="split"]', sp + '%' );
			put( '[data-calc-out="result"]', pts( reward( 1 ) ) );
			put( '[data-calc-out="keep"]', ( calc.getAttribute( 'data-keep' ) || 'for every $1 you earn, and you keep $%s' ).replace( '%s', en( ( 100 - sp ) / 100, 3, 3 ) ) );
			Array.prototype.forEach.call( calc.querySelectorAll( '[data-calc-payout]' ), function ( row ) {
				var p = row.getAttribute( 'data-calc-payout' );
				var payout = Math.max( 0, p === '' ? num( field( 'try' ) ) : parseFloat( p ) );
				var got = reward( payout );
				row.querySelector( '[data-calc-cell="user"]' ).textContent = pts( got );
				row.querySelector( '[data-calc-cell="keep"]' ).textContent = '$' + en( payout * ( 100 - sp ) / 100, 3, 3 );
				var w = row.querySelector( '[data-calc-cell="wallet"]' );
				if ( w ) {
					w.textContent = walletOn && got > 0 ? en( Math.round( got / num( rate ) * Math.pow( 10, dec ) ) / Math.pow( 10, dec ), dec, dec ) : '-';
				}
			} );
			Array.prototype.forEach.call( calc.querySelectorAll( '[data-calc-wallet]' ), function ( el ) {
				el.hidden = ! walletOn;
			} );
		};
		Array.prototype.forEach.call( calc.querySelectorAll( 'input, select' ), function ( i ) {
			i.addEventListener( 'input', run );
			i.addEventListener( 'change', run );
		} );
		if ( rate ) {
			rate.addEventListener( 'input', run );
		}
		if ( label ) {
			label.addEventListener( 'input', run );
		}
		Array.prototype.forEach.call( root.querySelectorAll( '[data-mxow-switch="destination"]' ), function ( el ) {
			el.addEventListener( 'change', run );
		} );
		run();
	}
	Array.prototype.forEach.call( root.querySelectorAll( '[data-mxow-filter]' ), function ( input ) {
		var list = document.getElementById( input.getAttribute( 'data-mxow-filter' ) );
		if ( ! list ) {
			return;
		}
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			Array.prototype.forEach.call( list.querySelectorAll( 'label' ), function ( l ) {
				l.hidden = q !== '' && l.textContent.toLowerCase().indexOf( q ) === -1;
			} );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
			}
		} );
	} );
} )();
