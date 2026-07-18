/**
 * BIP21 payment-URI helpers — the single source of truth for building
 * `bitcoin:<address>?amount=&label=&message=` requests, shared by the block
 * editor (edit.js), the frontend enhancer (view.js) and the unit tests.
 *
 * Ported verbatim from the chainkit marketing tool at
 * https://chainkit.dev/tools/payment-link so the plugin and the web tool
 * produce byte-identical URIs. Keep this file dependency-free.
 */

/**
 * Format a BTC amount for a BIP21 `amount=` param: fixed 8 decimals with
 * trailing zeros (and a dangling dot) trimmed. "1.50000000" → "1.5",
 * "1.00000000" → "1", 0 → "0".
 *
 * @param {number} n BTC amount.
 * @return {string} Decimal-BTC string, never in scientific notation.
 */
export function formatBtc( n ) {
	return n.toFixed( 8 ).replace( /\.?0+$/, '' ) || '0';
}

/**
 * Lightweight *format* heuristic — NOT authoritative validation. Just enough
 * to warn on obvious junk before someone ships a broken payment link. Covers
 * bech32 (mainnet bc1 / testnet tb1 / regtest bcrt1) and base58 legacy
 * (mainnet 1/3, testnet m/n/2).
 */
export const ADDR_RE =
	/^(?:bc1|tb1|bcrt1)[0-9ac-hj-np-z]{6,}$|^[13][a-km-zA-HJ-NP-Z1-9]{25,39}$|^[mn2][a-km-zA-HJ-NP-Z1-9]{25,39}$/;

/**
 * True when the address is empty or passes the format heuristic. Empty is
 * "not invalid" so the UI does not scream before the user has typed anything.
 *
 * @param {string} addr Candidate address.
 * @return {boolean} Whether the address looks plausible.
 */
export function addrLooksValid( addr ) {
	const t = ( addr || '' ).trim();
	return t === '' || ADDR_RE.test( t );
}

/**
 * Build a BIP21 URI. `amount` is decimal BTC per spec; `label`/`message` are
 * percent-encoded. Params are omitted when empty; an address-only URI is
 * valid. Returns '' when there is no address.
 *
 * @param {string}      addr      Bitcoin address.
 * @param {number|null} btcAmount BTC amount, or null/0 to omit.
 * @param {string}      label     Optional label.
 * @param {string}      message   Optional message.
 * @return {string} The `bitcoin:` URI, or '' when addr is empty.
 */
export function buildURI( addr, btcAmount, label, message ) {
	addr = ( addr || '' ).trim();
	if ( ! addr ) {
		return '';
	}
	const params = [];
	if ( btcAmount !== null && btcAmount > 0 ) {
		params.push( 'amount=' + formatBtc( btcAmount ) );
	}
	if ( label && label.trim() ) {
		params.push( 'label=' + encodeURIComponent( label.trim() ) );
	}
	if ( message && message.trim() ) {
		params.push( 'message=' + encodeURIComponent( message.trim() ) );
	}
	return (
		'bitcoin:' + addr + ( params.length ? '?' + params.join( '&' ) : '' )
	);
}
