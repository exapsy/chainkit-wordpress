<?php
/**
 * Standalone PHPUnit tests for the pure BIP21 helpers (includes/bip21.php).
 *
 * These need no WordPress bootstrap — they define CHAINKIT_BPB_TESTING and
 * include the helpers directly. They assert the PHP output matches the JS unit
 * tests (tests/bip21.test.js) case-for-case, so the block, the shortcode, and
 * the marketing tool all emit identical BIP21 URIs.
 *
 * Run: `phpunit tests/php/Bip21Test.php` (or via `npm run env` + wp-env).
 *
 * @package ChainkitBitcoinPaymentButton
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'CHAINKIT_BPB_TESTING' ) ) {
	define( 'CHAINKIT_BPB_TESTING', true );
}
require_once dirname( __DIR__, 2 ) . '/includes/bip21.php';

/**
 * @covers ::chainkit_bpb_format_btc
 * @covers ::chainkit_bpb_build_uri
 * @covers ::chainkit_bpb_sanitize_address
 * @covers ::chainkit_bpb_addr_looks_valid
 */
final class Bip21Test extends TestCase {

	const ADDR = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';

	public function test_format_btc_trims_and_formats() {
		$this->assertSame( '1.5', chainkit_bpb_format_btc( 1.5 ) );
		$this->assertSame( '1', chainkit_bpb_format_btc( 1 ) );
		$this->assertSame( '0.005', chainkit_bpb_format_btc( 0.005 ) );
		$this->assertSame( '0.00000001', chainkit_bpb_format_btc( 0.00000001 ) );
		$this->assertSame( '0', chainkit_bpb_format_btc( 0 ) );
		$this->assertSame( '0', chainkit_bpb_format_btc( -1 ) );
	}

	public function test_build_uri_address_only() {
		$this->assertSame( 'bitcoin:' . self::ADDR, chainkit_bpb_build_uri( self::ADDR, null, '', '' ) );
	}

	public function test_build_uri_with_btc_amount() {
		$this->assertSame( 'bitcoin:' . self::ADDR . '?amount=0.005', chainkit_bpb_build_uri( self::ADDR, 0.005, '', '' ) );
		$this->assertSame( 'bitcoin:' . self::ADDR . '?amount=1', chainkit_bpb_build_uri( self::ADDR, 1, '', '' ) );
	}

	public function test_build_uri_omits_zero_amount() {
		$this->assertSame( 'bitcoin:' . self::ADDR, chainkit_bpb_build_uri( self::ADDR, 0, '', '' ) );
	}

	public function test_build_uri_encodes_label_and_message() {
		$this->assertSame(
			'bitcoin:' . self::ADDR . '?label=My%20Store&message=Order%20%231',
			chainkit_bpb_build_uri( self::ADDR, null, 'My Store', 'Order #1' )
		);
	}

	public function test_build_uri_combines_all_params_in_order() {
		$this->assertSame(
			'bitcoin:' . self::ADDR . '?amount=0.005&label=My%20Store&message=Order%20%231',
			chainkit_bpb_build_uri( self::ADDR, 0.005, 'My Store', 'Order #1' )
		);
	}

	public function test_build_uri_fiat_conversion() {
		// 100 @ 50000/BTC = 0.002 BTC.
		$this->assertSame(
			'bitcoin:' . self::ADDR . '?amount=0.002',
			chainkit_bpb_build_uri( self::ADDR, 100 / 50000, '', '' )
		);
	}

	public function test_build_uri_empty_without_address() {
		$this->assertSame( '', chainkit_bpb_build_uri( '', 1, 'x', 'y' ) );
	}

	public function test_sanitize_address_strips_hostile_input() {
		$this->assertSame(
			'scriptalert1script',
			chainkit_bpb_sanitize_address( '"><script>alert(1)</script>' )
		);
		$this->assertSame( self::ADDR, chainkit_bpb_sanitize_address( '  ' . self::ADDR . '  ' ) );
	}

	public function test_addr_looks_valid() {
		$this->assertTrue( chainkit_bpb_addr_looks_valid( self::ADDR ) );
		$this->assertTrue( chainkit_bpb_addr_looks_valid( '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2' ) );
		$this->assertTrue( chainkit_bpb_addr_looks_valid( '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy' ) );
		$this->assertFalse( chainkit_bpb_addr_looks_valid( 'scriptalert1script' ) );
		$this->assertFalse( chainkit_bpb_addr_looks_valid( '' ) );
	}
}
