/**
 * Frontend progressive enhancement for the Bitcoin Payment Button.
 *
 * The button link, amount, and address are server-rendered and work without
 * JavaScript. This script adds, on top:
 *   - the QR code (rendered from the BIP21 URI via uqr, shown immediately),
 *   - a copy-address button,
 *   - in fixed-BTC mode: a switchable local-currency reference (browser-locale
 *     default, converted client-side from the embedded rate table — no request,
 *     no IP lookup),
 *   - in payer-decides mode: an amount picker (presets / range / free) that
 *     updates the headline, the bitcoin: link, and the QR live. Without JS the
 *     payer simply enters the amount in their wallet.
 *
 * The QR SVG encodes the URI as modules — no user text becomes SVG markup, so
 * setting it via innerHTML is safe.
 */
import { renderSVG } from 'uqr';
import { buildURI, formatBtc } from './lib/bip21';

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

interface PickerConfig {
	enabled: boolean;
	unit: 'fiat' | 'btc';
	presets: number[];
	range: boolean;
	min: number;
	max: number;
	free: boolean;
}

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
			minimumFractionDigits: 0,
			maximumFractionDigits:
				currency === 'JPY' || value >= 100 || Number.isInteger( value )
					? 0
					: 2,
		} ).format( value );
	} catch ( e ) {
		return `${ value } ${ currency }`;
	}
}

function ratesOf( root: HTMLElement ): Record< string, number > {
	try {
		return JSON.parse( root.getAttribute( 'data-rates' ) || '{}' );
	} catch ( e ) {
		return {};
	}
}

/**
 * Point the pay button and QR at a new URI (used as the picker changes it).
 * @param root
 * @param uri
 */
function setUri( root: HTMLElement, uri: string ): void {
	const btn = root.querySelector< HTMLAnchorElement >( '.chainkit-bpb__btn' );
	if ( btn ) {
		btn.href = uri;
	}
	const host = root.querySelector< HTMLElement >( '.chainkit-bpb__qr-svg' );
	const wrap = root.querySelector< HTMLElement >( '.chainkit-bpb__qr' );
	if ( host && wrap ) {
		try {
			host.innerHTML = renderSVG( uri, { border: 2 } );
			wrap.removeAttribute( 'hidden' );
			wrap.setAttribute( 'aria-hidden', 'false' );
		} catch ( e ) {
			wrap.setAttribute( 'hidden', '' );
		}
	}
}

/**
 * Fixed-BTC mode: turn the static fiat line into a switchable reference.
 * @param root
 * @param fiat
 */
function enhanceFiat( root: HTMLElement, fiat: HTMLElement ): void {
	const rates = ratesOf( root );
	const currencies = SUPPORTED.filter( ( c ) => rates[ c ] > 0 );
	const btc = parseFloat( root.getAttribute( 'data-btc' ) || '' );
	if ( currencies.length < 2 || ! isFinite( btc ) || btc <= 0 ) {
		return;
	}

	const fallback = root.getAttribute( 'data-ref-currency' ) || 'EUR';
	let current = guessCurrency( fallback );
	if ( ! currencies.includes( current ) ) {
		current = currencies.includes( fallback ) ? fallback : currencies[ 0 ];
	}

	const prefix = document.createElement( 'span' );
	prefix.className = 'chainkit-bpb__fiat-prefix';
	prefix.textContent = '≈ ';
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
		value.textContent = formatFiat( btc * rates[ current ], current );
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
 * Payer-decides mode: build the amount picker and keep everything in sync.
 * @param root
 * @param host
 */
function enhancePicker( root: HTMLElement, host: HTMLElement ): void {
	let cfg: PickerConfig;
	try {
		cfg = JSON.parse( host.getAttribute( 'data-picker' ) || '{}' );
	} catch ( e ) {
		return;
	}
	if ( ! cfg.enabled ) {
		return;
	}

	const rates = ratesOf( root );
	const currency = root.getAttribute( 'data-ref-currency' ) || 'EUR';
	const address = root.getAttribute( 'data-address' ) || '';
	const label = root.getAttribute( 'data-label' ) || '';
	const message = root.getAttribute( 'data-message' ) || '';
	const rate = rates[ currency ];
	const isFiat = cfg.unit === 'fiat';
	if ( isFiat && ! ( rate > 0 ) ) {
		return; // Can't convert fiat picks without a rate.
	}

	const hero = root.querySelector< HTMLElement >( '[data-hero]' );
	const sub = root.querySelector< HTMLElement >( '[data-sub]' );

	const toBtc = ( amt: number ) => ( isFiat ? amt / rate : amt );
	const fmtAmt = ( amt: number ) =>
		isFiat ? formatFiat( amt, currency ) : `${ formatBtc( amt ) } BTC`;

	const presetBtns: HTMLButtonElement[] = [];
	const clearActive = () =>
		presetBtns.forEach( ( b ) => b.classList.remove( 'is-active' ) );

	const apply = ( amt: number, activeBtn?: HTMLButtonElement ) => {
		clearActive();
		if ( activeBtn ) {
			activeBtn.classList.add( 'is-active' );
		}
		if ( ! ( amt > 0 ) ) {
			if ( hero ) {
				hero.textContent =
					hero.getAttribute( 'data-any' ) || 'Any amount';
			}
			if ( sub ) {
				sub.textContent = '';
			}
			setUri( root, buildURI( address, null, label, message ) );
			return;
		}
		const btc = toBtc( amt );
		if ( hero ) {
			hero.textContent = fmtAmt( amt );
		}
		if ( sub ) {
			let subText = '';
			if ( isFiat ) {
				subText = `≈ ${ formatBtc( btc ) } BTC`;
			} else if ( rate > 0 ) {
				subText = `≈ ${ formatFiat( amt * rate, currency ) }`;
			}
			sub.textContent = subText;
		}
		setUri( root, buildURI( address, btc, label, message ) );
	};

	if ( hero && ! hero.getAttribute( 'data-any' ) ) {
		hero.setAttribute( 'data-any', hero.textContent || 'Any amount' );
	}

	// --- Presets ---
	if ( cfg.presets && cfg.presets.length ) {
		const row = document.createElement( 'div' );
		row.className = 'chainkit-bpb__presets';
		cfg.presets.forEach( ( amt ) => {
			const b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'chainkit-bpb__preset';
			b.textContent = fmtAmt( amt );
			b.addEventListener( 'click', () => {
				resetInputs();
				apply( amt, b );
			} );
			presetBtns.push( b );
			row.appendChild( b );
		} );
		host.appendChild( row );
	}

	// --- Range ---
	let range: HTMLInputElement | null = null;
	if ( cfg.range && cfg.max > cfg.min ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'chainkit-bpb__range';
		range = document.createElement( 'input' );
		range.type = 'range';
		range.className = 'chainkit-bpb__range-input';
		range.min = String( cfg.min );
		range.max = String( cfg.max );
		range.step = String( isFiat ? 1 : ( cfg.max - cfg.min ) / 100 || 1 );
		range.value = String( cfg.min );
		range.setAttribute( 'aria-label', 'Amount' );
		range.addEventListener( 'input', () => {
			if ( free ) {
				free.value = '';
			}
			apply( parseFloat( range!.value ) );
		} );
		wrap.appendChild( range );
		host.appendChild( wrap );
	}

	// --- Free input ---
	let free: HTMLInputElement | null = null;
	if ( cfg.free ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'chainkit-bpb__free';
		free = document.createElement( 'input' );
		free.type = 'number';
		free.className = 'chainkit-bpb__free-input';
		free.min = '0';
		free.step = isFiat ? '0.01' : '0.00000001';
		free.placeholder = isFiat ? 'Custom amount' : 'Custom BTC';
		free.setAttribute( 'aria-label', 'Custom amount' );
		free.addEventListener( 'input', () => {
			if ( range ) {
				range.value = String( cfg.min );
			}
			apply( parseFloat( free!.value ) );
		} );
		const unit = document.createElement( 'span' );
		unit.className = 'chainkit-bpb__free-unit';
		unit.textContent = isFiat ? currency : 'BTC';
		wrap.append( free, unit );
		host.appendChild( wrap );
	}

	function resetInputs() {
		if ( range ) {
			range.value = String( cfg.min );
		}
		if ( free ) {
			free.value = '';
		}
	}

	host.removeAttribute( 'hidden' );

	// Sensible default: first preset, else range minimum, else "any amount".
	if ( cfg.presets && cfg.presets.length ) {
		apply( cfg.presets[ 0 ], presetBtns[ 0 ] );
	} else if ( range ) {
		apply( cfg.min );
	} else {
		apply( 0 );
	}
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
 * Render the QR from the current URI (fixed-amount modes).
 * @param root
 */
function renderQr( root: HTMLElement ): void {
	const uri = root.getAttribute( 'data-uri' );
	if ( uri ) {
		setUri( root, uri );
	}
}

function enhance( root: HTMLElement ): void {
	if ( ! root.getAttribute( 'data-uri' ) ) {
		return;
	}
	enhanceCopy( root );

	const picker = root.querySelector< HTMLElement >( '.chainkit-bpb__picker' );
	const fiat = root.querySelector< HTMLElement >( '.chainkit-bpb__fiat' );
	if ( picker ) {
		enhancePicker( root, picker ); // Sets its own URI + QR.
	} else {
		renderQr( root );
		if ( fiat ) {
			enhanceFiat( root, fiat );
		}
	}
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
