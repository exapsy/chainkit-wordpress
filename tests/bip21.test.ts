/**
 * Unit tests for the BIP21 URI builder (src/lib/bip21.ts).
 *
 * These are the canonical known-good cases from the plan — the plugin must
 * produce byte-identical URIs to the chainkit web tool. Run with
 * `npm run test:unit`.
 */
import { formatBtc, buildURI, addrLooksValid } from '../src/lib/bip21';

const ADDR = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';

describe( 'formatBtc', () => {
	it( 'trims trailing zeros', () => {
		expect( formatBtc( 1.5 ) ).toBe( '1.5' );
	} );
	it( 'renders a whole number with no decimal point', () => {
		expect( formatBtc( 1 ) ).toBe( '1' );
	} );
	it( 'keeps significant decimals', () => {
		expect( formatBtc( 0.005 ) ).toBe( '0.005' );
	} );
	it( 'handles small sat amounts without scientific notation', () => {
		expect( formatBtc( 0.00000001 ) ).toBe( '0.00000001' );
	} );
	it( 'returns "0" for zero', () => {
		expect( formatBtc( 0 ) ).toBe( '0' );
	} );
} );

describe( 'buildURI', () => {
	it( 'builds an address-only URI', () => {
		expect( buildURI( ADDR, null, '', '' ) ).toBe( `bitcoin:${ ADDR }` );
	} );
	it( 'trims surrounding whitespace on the address', () => {
		expect( buildURI( `  ${ ADDR }  `, null, '', '' ) ).toBe(
			`bitcoin:${ ADDR }`
		);
	} );
	it( 'adds a BTC amount', () => {
		expect( buildURI( ADDR, 0.005, '', '' ) ).toBe(
			`bitcoin:${ ADDR }?amount=0.005`
		);
	} );
	it( 'adds a whole-BTC amount without decimals', () => {
		expect( buildURI( ADDR, 1, '', '' ) ).toBe(
			`bitcoin:${ ADDR }?amount=1`
		);
	} );
	it( 'omits a zero/negative amount', () => {
		expect( buildURI( ADDR, 0, '', '' ) ).toBe( `bitcoin:${ ADDR }` );
	} );
	it( 'percent-encodes label and message', () => {
		expect( buildURI( ADDR, null, 'My Store', 'Order #1' ) ).toBe(
			`bitcoin:${ ADDR }?label=My%20Store&message=Order%20%231`
		);
	} );
	it( 'combines amount + label + message in order', () => {
		expect( buildURI( ADDR, 0.005, 'My Store', 'Order #1' ) ).toBe(
			`bitcoin:${ ADDR }?amount=0.005&label=My%20Store&message=Order%20%231`
		);
	} );
	it( 'converts fiat via a known rate (100 @ 50000/BTC = 0.002)', () => {
		const btc = 100 / 50000;
		expect( buildURI( ADDR, btc, '', '' ) ).toBe(
			`bitcoin:${ ADDR }?amount=0.002`
		);
	} );
	it( 'returns empty string with no address', () => {
		expect( buildURI( '', 1, 'x', 'y' ) ).toBe( '' );
	} );
} );

describe( 'addrLooksValid', () => {
	it( 'accepts empty (nothing typed yet)', () => {
		expect( addrLooksValid( '' ) ).toBe( true );
	} );
	it( 'accepts bech32 mainnet', () => {
		expect( addrLooksValid( ADDR ) ).toBe( true );
	} );
	it( 'accepts legacy P2PKH', () => {
		expect( addrLooksValid( '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2' ) ).toBe(
			true
		);
	} );
	it( 'accepts legacy P2SH', () => {
		expect( addrLooksValid( '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy' ) ).toBe(
			true
		);
	} );
	it( 'rejects obvious junk', () => {
		expect( addrLooksValid( 'not-an-address!!' ) ).toBe( false );
	} );
} );
