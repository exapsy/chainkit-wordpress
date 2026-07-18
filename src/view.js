/**
 * Frontend progressive enhancement for the Bitcoin Payment Button.
 *
 * The button link, amount, and address are already in the server-rendered HTML
 * and work with JavaScript disabled. This script only *adds*:
 *   - a QR code (rendered from the BIP21 URI in `data-uri` via uqr),
 *   - a copy-address button,
 *   - middle-truncation of the long address,
 *   - a switchable fiat reference, defaulting to the visitor's browser locale
 *     and converted entirely client-side from the rate table in `data-rates`
 *     (no per-visitor network request, no IP lookup — nothing is sent anywhere).
 *
 * The QR SVG encodes the URI as modules — no user text is placed as SVG markup,
 * so setting it via innerHTML is safe.
 */
/* eslint-env browser */
import { renderSVG } from 'uqr';

/**
 * Currencies the rate feed supports, and the region → currency guesses used to
 * pick a sensible default from the visitor's browser locale. Eurozone regions
 * map to EUR; anything unmapped falls back to the server's default currency.
 */
const SUPPORTED = [ 'USD', 'EUR', 'GBP', 'JPY', 'CAD' ];
const REGION_CCY = {
	US: 'USD',
	CA: 'CAD',
	GB: 'GBP',
	JP: 'JPY',
	AT: 'EUR',
	BE: 'EUR',
	CY: 'EUR',
	DE: 'EUR',
	EE: 'EUR',
	ES: 'EUR',
	FI: 'EUR',
	FR: 'EUR',
	GR: 'EUR',
	IE: 'EUR',
	IT: 'EUR',
	LT: 'EUR',
	LU: 'EUR',
	LV: 'EUR',
	MT: 'EUR',
	NL: 'EUR',
	PT: 'EUR',
	SI: 'EUR',
	SK: 'EUR',
};

function guessCurrency( fallback ) {
	const lang = ( navigator.language || '' ).toUpperCase();
	const region = lang.split( '-' )[ 1 ];
	if ( region && REGION_CCY[ region ] ) {
		return REGION_CCY[ region ];
	}
	if ( lang.startsWith( 'JA' ) ) {
		return 'JPY';
	}
	return fallback;
}

function formatFiat( value, currency ) {
	try {
		return new Intl.NumberFormat( navigator.language || 'en', {
			style: 'currency',
			currency,
			maximumFractionDigits: currency === 'JPY' || value >= 100 ? 0 : 2,
		} ).format( value );
	} catch ( e ) {
		return `${ value.toFixed( 2 ) } ${ currency }`;
	}
}

/**
 * Turn the server-rendered static fiat line into a switchable reference.
 *
 * @param {HTMLElement} root The widget root.
 * @param {HTMLElement} fiat The `.chainkit-bpb__fiat` element.
 */
function enhanceFiat( root, fiat ) {
	let rates;
	try {
		rates = JSON.parse( root.getAttribute( 'data-rates' ) || '{}' );
	} catch ( e ) {
		return;
	}
	const currencies = SUPPORTED.filter( ( c ) => rates[ c ] > 0 );
	if ( currencies.length < 2 ) {
		return; // Nothing to switch between; keep the static server text.
	}

	const btc = parseFloat( root.getAttribute( 'data-btc' ) );
	const hasBtc = isFinite( btc ) && btc > 0;
	const isPrice = ! hasBtc; // "none" mode → show the price of 1 BTC.
	const fallback = root.getAttribute( 'data-ref-currency' ) || 'EUR';
	let current = guessCurrency( fallback );
	if ( ! currencies.includes( current ) ) {
		current = currencies.includes( fallback ) ? fallback : currencies[ 0 ];
	}

	const prefix = document.createElement( 'span' );
	prefix.className = 'chainkit-bpb__fiat-prefix';
	const value = document.createElement( 'span' );
	value.className = 'chainkit-bpb__fiat-value';
	const select = document.createElement( 'select' );
	select.className = 'chainkit-bpb__fiat-select';
	select.setAttribute( 'aria-label', 'Display currency' );
	currencies.forEach( ( c ) => {
		const opt = document.createElement( 'option' );
		opt.value = c;
		opt.textContent = c;
		select.appendChild( opt );
	} );

	const paint = () => {
		const amount = isPrice ? rates[ current ] : btc * rates[ current ];
		prefix.textContent = isPrice ? '1 BTC ≈ ' : '≈ ';
		value.textContent = formatFiat( amount, current );
		select.value = current;
	};

	select.addEventListener( 'change', () => {
		current = select.value;
		paint();
	} );

	paint();
	fiat.textContent = '';
	fiat.append( prefix, value, select );
	fiat.classList.add( 'is-interactive' );
}

/**
 * Shorten a long address to first…last, keeping the full value available for
 * copy and in the title. Falls back to the full string on short inputs.
 *
 * @param {HTMLElement} root The widget root.
 */
function truncateAddress( root ) {
	const el = root.querySelector( '.chainkit-bpb__addr' );
	if ( ! el ) {
		return;
	}
	const full = el.getAttribute( 'data-full' ) || el.textContent;
	el.title = full;
	if ( full.length > 21 ) {
		el.textContent = `${ full.slice( 0, 9 ) }…${ full.slice( -8 ) }`;
	}
}

function enhanceCopy( root ) {
	const copyBtn = root.querySelector( '.chainkit-bpb__copy' );
	if ( ! copyBtn || ! navigator.clipboard ) {
		return;
	}
	copyBtn.addEventListener( 'click', async () => {
		const val = copyBtn.getAttribute( 'data-copy' ) || '';
		try {
			await navigator.clipboard.writeText( val );
			copyBtn.classList.add( 'is-copied' );
			setTimeout( () => copyBtn.classList.remove( 'is-copied' ), 1600 );
		} catch ( e ) {
			// Clipboard blocked — the address stays visible for manual copy.
		}
	} );
}

function enhanceQr( root, uri ) {
	const qr = root.querySelector( '.chainkit-bpb__qr' );
	const toggle = root.querySelector( '.chainkit-bpb__qr-toggle' );
	const labelEl =
		toggle && toggle.querySelector( '.chainkit-bpb__qr-toggle-label' );
	if ( ! qr || ! toggle ) {
		return;
	}

	let rendered = false;
	const render = () => {
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

	toggle.hidden = false; // No-JS hides it; reveal now that we can build a QR.
	toggle.addEventListener( 'click', () => {
		const willShow = qr.hasAttribute( 'hidden' );
		if ( willShow ) {
			render();
			qr.removeAttribute( 'hidden' );
			qr.setAttribute( 'aria-hidden', 'false' );
		} else {
			qr.setAttribute( 'hidden', '' );
			qr.setAttribute( 'aria-hidden', 'true' );
		}
		toggle.setAttribute( 'aria-expanded', willShow ? 'true' : 'false' );
		toggle.classList.toggle( 'is-open', willShow );
		if ( labelEl ) {
			labelEl.textContent = willShow ? 'Hide QR code' : 'Show QR code';
		}
	} );
}

function enhance( root ) {
	const uri = root.getAttribute( 'data-uri' );
	if ( ! uri ) {
		return;
	}
	enhanceCopy( root );
	truncateAddress( root );
	const fiat = root.querySelector( '.chainkit-bpb__fiat' );
	if ( fiat ) {
		enhanceFiat( root, fiat );
	}
	enhanceQr( root, uri );
}

function init() {
	document.querySelectorAll( '.chainkit-bpb[data-uri]' ).forEach( enhance );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
