<?php
/**
 * Pure BIP21 / address helpers — the PHP mirror of src/lib/bip21.js.
 *
 * These functions use only core PHP (no WordPress APIs) so they can be unit
 * tested in isolation (tests/php/Bip21Test.php) and reused by the block render,
 * the shortcode, and validation. Keep them byte-for-byte compatible with the JS
 * so the plugin and the chainkit web tool produce identical URIs.
 *
 * @package ChainkitBitcoinPaymentButton
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'CHAINKIT_BPB_TESTING' ) ) {
	exit;
}

if ( ! function_exists( 'chainkit_bpb_currencies' ) ) {
	/**
	 * Currencies the public rate feed supports (mirrors the marketing tools).
	 *
	 * @return string[]
	 */
	function chainkit_bpb_currencies() {
		return array( 'USD', 'EUR', 'GBP', 'JPY', 'CAD' );
	}
}

if ( ! function_exists( 'chainkit_bpb_format_btc' ) ) {
	/**
	 * Format a BTC amount as a locale-independent 8-dp decimal string with
	 * trailing zeros trimmed. Matches formatBtc() in src/lib/bip21.js by going
	 * through integer satoshis, so no float/locale drift can creep in.
	 *
	 * @param float $n BTC amount.
	 * @return string Decimal-BTC string ("0" when <= 0).
	 */
	function chainkit_bpb_format_btc( $n ) {
		$sats = (int) round( (float) $n * 100000000 );
		if ( $sats <= 0 ) {
			return '0';
		}
		$whole = intdiv( $sats, 100000000 );
		$frac  = $sats % 100000000;
		if ( 0 === $frac ) {
			return (string) $whole;
		}
		$frac_str = rtrim( sprintf( '%08d', $frac ), '0' );
		return $whole . '.' . $frac_str;
	}
}

if ( ! function_exists( 'chainkit_bpb_sanitize_address' ) ) {
	/**
	 * Strip an address candidate to the base58/bech32 alphanumeric charset. All
	 * editor input is untrusted; this runs before validation and before the
	 * value is ever echoed.
	 *
	 * @param string $addr Raw address.
	 * @return string Sanitized address (allowed charset only).
	 */
	function chainkit_bpb_sanitize_address( $addr ) {
		return preg_replace( '/[^0-9A-Za-z]/', '', (string) $addr );
	}
}

if ( ! function_exists( 'chainkit_bpb_addr_looks_valid' ) ) {
	/**
	 * Lightweight FORMAT heuristic — NOT authoritative validation. Mirrors
	 * ADDR_RE in src/lib/bip21.js. Covers bech32 (bc1/tb1/bcrt1) and base58.
	 *
	 * @param string $addr Sanitized address.
	 * @return bool Whether it plausibly looks like a Bitcoin address.
	 */
	function chainkit_bpb_addr_looks_valid( $addr ) {
		$re = '/^(?:bc1|tb1|bcrt1)[0-9ac-hj-np-z]{6,}$|^[13][a-km-zA-HJ-NP-Z1-9]{25,39}$|^[mn2][a-km-zA-HJ-NP-Z1-9]{25,39}$/';
		return 1 === preg_match( $re, (string) $addr );
	}
}

if ( ! function_exists( 'chainkit_bpb_build_uri' ) ) {
	/**
	 * Build a BIP21 URI. `amount` is decimal BTC; `label`/`message` are
	 * percent-encoded; empty params are omitted. Mirrors buildURI() in
	 * src/lib/bip21.js. Returns '' when there is no address.
	 *
	 * @param string     $addr    Sanitized Bitcoin address.
	 * @param float|null $btc     BTC amount, or null/0 to omit.
	 * @param string     $label   Optional label.
	 * @param string     $message Optional message.
	 * @return string The bitcoin: URI, or '' when addr is empty.
	 */
	function chainkit_bpb_build_uri( $addr, $btc, $label, $message ) {
		$addr = trim( (string) $addr );
		if ( '' === $addr ) {
			return '';
		}
		$params = array();
		if ( null !== $btc && $btc > 0 ) {
			$params[] = 'amount=' . chainkit_bpb_format_btc( $btc );
		}
		$label = trim( (string) $label );
		if ( '' !== $label ) {
			$params[] = 'label=' . rawurlencode( $label );
		}
		$message = trim( (string) $message );
		if ( '' !== $message ) {
			$params[] = 'message=' . rawurlencode( $message );
		}
		return 'bitcoin:' . $addr . ( $params ? '?' . implode( '&', $params ) : '' );
	}
}
