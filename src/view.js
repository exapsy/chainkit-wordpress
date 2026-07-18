/**
 * Frontend progressive enhancement for the Bitcoin Payment Button.
 *
 * The button link and address are already in the server-rendered HTML and work
 * with JavaScript disabled. This script only *adds*: a QR code (rendered from
 * the BIP21 URI in `data-uri` via uqr), a copy-address button, and a QR toggle.
 *
 * The QR SVG encodes the URI as modules — no user text is placed as SVG markup,
 * so setting it via innerHTML is safe.
 */
import { renderSVG } from 'uqr';

function enhance( root ) {
	const uri = root.getAttribute( 'data-uri' );
	if ( ! uri ) {
		return;
	}

	// --- Copy address ---
	const copyBtn = root.querySelector( '.chainkit-bpb__copy' );
	if ( copyBtn && navigator.clipboard ) {
		const original = copyBtn.textContent;
		copyBtn.addEventListener( 'click', async () => {
			const value = copyBtn.getAttribute( 'data-copy' ) || '';
			try {
				await navigator.clipboard.writeText( value );
				copyBtn.textContent = copyBtn.getAttribute( 'data-copied' ) || 'Copied';
				copyBtn.classList.add( 'is-copied' );
				setTimeout( () => {
					copyBtn.textContent = original;
					copyBtn.classList.remove( 'is-copied' );
				}, 1600 );
			} catch ( e ) {
				// Clipboard denied — leave the visible address for manual copy.
			}
		} );
	}

	// --- QR toggle + render ---
	const qr = root.querySelector( '.chainkit-bpb__qr' );
	const toggle = root.querySelector( '.chainkit-bpb__qr-toggle' );
	if ( ! qr || ! toggle ) {
		return;
	}

	let rendered = false;
	const renderQr = () => {
		if ( rendered ) {
			return;
		}
		try {
			qr.innerHTML = renderSVG( uri, { border: 2 } );
			rendered = true;
		} catch ( e ) {
			toggle.hidden = true;
		}
	};

	// The toggle is hidden by default (no-JS shows nothing); reveal it now that
	// JS can build the QR.
	toggle.hidden = false;
	toggle.addEventListener( 'click', () => {
		const willShow = qr.hasAttribute( 'hidden' );
		if ( willShow ) {
			renderQr();
			qr.removeAttribute( 'hidden' );
			qr.setAttribute( 'aria-hidden', 'false' );
		} else {
			qr.setAttribute( 'hidden', '' );
			qr.setAttribute( 'aria-hidden', 'true' );
		}
		toggle.setAttribute( 'aria-expanded', willShow ? 'true' : 'false' );
		toggle.textContent = willShow
			? toggle.getAttribute( 'data-hide' ) || 'Hide QR code'
			: toggle.getAttribute( 'data-show' ) || 'Show QR code';
	} );
}

function init() {
	document
		.querySelectorAll( '.chainkit-bpb[data-uri]' )
		.forEach( enhance );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
