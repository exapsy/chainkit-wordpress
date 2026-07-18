/**
 * Frontend progressive enhancement for the Bitcoin Payment Button.
 *
 * The button link, amount, and address are already in the server-rendered HTML
 * and work with JavaScript disabled. This script only *adds*:
 *   - the QR code (rendered from the BIP21 URI in `data-uri` via uqr and shown
 *     immediately — it's a primary way to pay, so it isn't hidden behind a
 *     toggle; it's simply absent when JavaScript is off),
 *   - a copy-address button,
 *   - middle-truncation of the long address,
 *   - a switchable fiat reference, defaulting to the visitor's browser locale
 *     and converted entirely client-side from the rate table in `data-rates`
 *     (no per-visitor network request, no IP lookup — nothing is sent anywhere).
 *
 * The QR SVG encodes the URI as modules — no user text is placed as SVG markup,
 * so setting it via innerHTML is safe.
 */
import { renderSVG } from 'uqr';

/**
 * Currencies the rate feed supports, and the region → currency guesses used to
 * pick a sensible default from the visitor's browser locale. Eurozone regions
 * map to EUR; anything unmapped falls back to the server's default currency.
 */
const SUPPORTED = [ 'USD', 'EUR', 'GBP', 'JPY', 'CAD' ];
const REGION_CCY: Record< string, string > = {
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

function guessCurrency( fallback: string ): string {
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

function formatFiat( value: number, currency: string ): string {
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
 * @param root
 * @param fiat
 */
function enhanceFiat( root: HTMLElement, fiat: HTMLElement ): void {
	let rates: Record< string, number >;
	try {
		rates = JSON.parse( root.getAttribute( 'data-rates' ) || '{}' );
	} catch ( e ) {
		return;
	}
	const currencies = SUPPORTED.filter( ( c ) => rates[ c ] > 0 );
	if ( currencies.length < 2 ) {
		return; // Nothing to switch between; keep the static server text.
	}

	const btc = parseFloat( root.getAttribute( 'data-btc' ) || '' );
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

function enhanceCopy( root: HTMLElement ): void {
	const copyBtn = root.querySelector< HTMLElement >( '.chainkit-bpb__copy' );
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

/**
 * Render the QR from the URI and reveal it (no-JS leaves it hidden/empty).
 * @param root
 * @param uri
 */
function renderQr( root: HTMLElement, uri: string ): void {
	const wrap = root.querySelector< HTMLElement >( '.chainkit-bpb__qr' );
	const host = root.querySelector< HTMLElement >( '.chainkit-bpb__qr-svg' );
	if ( ! wrap || ! host ) {
		return;
	}
	try {
		host.innerHTML = renderSVG( uri, { border: 2 } );
		wrap.removeAttribute( 'hidden' );
		wrap.setAttribute( 'aria-hidden', 'false' );
	} catch ( e ) {
		// Leave the QR hidden if it can't be built.
	}
}

function enhance( root: HTMLElement ): void {
	const uri = root.getAttribute( 'data-uri' );
	if ( ! uri ) {
		return;
	}
	enhanceCopy( root );
	const fiat = root.querySelector< HTMLElement >( '.chainkit-bpb__fiat' );
	if ( fiat ) {
		enhanceFiat( root, fiat );
	}
	renderQr( root, uri );
}

function init(): void {
	document
		.querySelectorAll< HTMLElement >( '.chainkit-bpb[data-uri]' )
		.forEach( enhance );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
