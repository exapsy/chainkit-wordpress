<?php
/**
 * Plugin Name:       Bitcoin Payment Button & QR — chainkit
 * Plugin URI:        https://chainkit.dev/tools/payment-link
 * Description:        Add a "Pay with Bitcoin" button and BIP21 QR code to any page or post — a real bitcoin: link that opens the visitor's wallet, with an optional fixed BTC or live fiat-priced amount. No account, no custody, works without JavaScript.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            chainkit
 * Author URI:        https://chainkit.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chainkit-bitcoin-payment-button
 * Domain Path:       /languages
 *
 * @package ChainkitBitcoinPaymentButton
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CHAINKIT_BPB_VERSION', '1.0.0' );
define( 'CHAINKIT_BPB_FILE', __FILE__ );
define( 'CHAINKIT_BPB_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHAINKIT_BPB_RATES_URL', 'https://api.chainkit.dev/v1/public/btc/rates' );
define( 'CHAINKIT_BPB_SIGNUP_URL', 'https://chainkit.dev/signup?utm_source=wordpress&utm_medium=plugin&utm_campaign=payment-button' );
define( 'CHAINKIT_BPB_RATE_TTL', 5 * MINUTE_IN_SECONDS );

// Pure BIP21 / address helpers (no WordPress APIs), shared with the unit tests.
require_once CHAINKIT_BPB_DIR . 'includes/bip21.php';

/**
 * Default block/shortcode attributes. Single source of truth so the shortcode
 * and the block stay in lockstep.
 *
 * @return array<string,string>
 */
function chainkit_bpb_defaults() {
	return array(
		'address'    => '',
		'amountMode' => 'none', // none | btc | fiat.
		'amountBtc'  => '',
		'amountFiat' => '',
		'currency'   => 'EUR',
		'label'      => '',
		'message'    => '',
		'buttonText'  => __( 'Pay with Bitcoin', 'chainkit-bitcoin-payment-button' ),
		'buttonAlign' => 'left', // left | center | right.
		'theme'      => 'auto', // auto | light | dark.
		'showQr'     => true,
		'showPowered'=> true,
	);
}

/*
 * ---------------------------------------------------------------------------
 * Fiat rates — server-side fetch + transient cache (sidesteps browser CORS and
 * per-visitor tracking). See readme.txt "External services" disclosure.
 * ---------------------------------------------------------------------------
 */

/**
 * Fetch the full BTC→fiat rate table from chainkit's public endpoint, cached in
 * a transient for ~5 minutes. Returns a map keyed by upper-case currency, or
 * null when the endpoint is unreachable/malformed (caller falls back to
 * address-only).
 *
 * @return array<string,array{rate:float,source:string}>|null
 */
function chainkit_bpb_get_rates() {
	$cached = get_transient( 'chainkit_bpb_rates' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$resp = wp_remote_get(
		CHAINKIT_BPB_RATES_URL,
		array(
			'timeout' => 5,
			'headers' => array( 'Accept' => 'application/json' ),
			'user-agent' => 'chainkit-wordpress/' . CHAINKIT_BPB_VERSION,
		)
	);

	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ! is_array( $body ) || empty( $body['rates'] ) || ! is_array( $body['rates'] ) ) {
		return null;
	}

	$map = array();
	foreach ( $body['rates'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['currency'], $row['rate'] ) ) {
			continue;
		}
		$rate = (float) $row['rate'];
		if ( $rate <= 0 ) {
			continue;
		}
		$map[ strtoupper( (string) $row['currency'] ) ] = array(
			'rate'   => $rate,
			'source' => isset( $row['source'] ) ? (string) $row['source'] : '',
		);
	}

	if ( empty( $map ) ) {
		return null;
	}

	set_transient( 'chainkit_bpb_rates', $map, CHAINKIT_BPB_RATE_TTL );
	return $map;
}

/*
 * ---------------------------------------------------------------------------
 * Rendering.
 * ---------------------------------------------------------------------------
 */

/**
 * Allow the bitcoin: URI scheme through KSES so escaped hrefs survive filtering
 * (both esc_url and post-content sanitization use wp_allowed_protocols()).
 */
add_filter(
	'kses_allowed_protocols',
	function ( $protocols ) {
		$protocols[] = 'bitcoin';
		return $protocols;
	}
);

/**
 * Normalize raw attributes (from the block or the shortcode) against the
 * defaults and resolve the amount into BTC + a human note. Central so the block
 * and shortcode render identically.
 *
 * @param array $atts Raw attributes.
 * @return array{
 *   addr:string, addr_valid:bool, uri:string, amount_text:string,
 *   approx:bool, button_text:string, align:string, theme:string,
 *   label:string, message:string, show_qr:bool, show_powered:bool
 * }|null Null when there is no usable address (nothing to render).
 */
function chainkit_bpb_prepare( $atts ) {
	$a = wp_parse_args( $atts, chainkit_bpb_defaults() );

	$addr = chainkit_bpb_sanitize_address( $a['address'] );
	if ( '' === $addr || ! chainkit_bpb_addr_looks_valid( $addr ) ) {
		return null;
	}

	$mode        = in_array( $a['amountMode'], array( 'none', 'btc', 'fiat' ), true ) ? $a['amountMode'] : 'none';
	$btc         = null;
	$amount_text = '';
	$approx      = false;

	if ( 'btc' === $mode ) {
		$v = is_numeric( $a['amountBtc'] ) ? (float) $a['amountBtc'] : 0.0;
		if ( $v > 0 ) {
			$btc         = $v;
			$amount_text = chainkit_bpb_format_btc( $v ) . ' BTC';
		}
	} elseif ( 'fiat' === $mode ) {
		$currency = strtoupper( (string) $a['currency'] );
		if ( ! in_array( $currency, chainkit_bpb_currencies(), true ) ) {
			$currency = 'EUR';
		}
		$fiat = is_numeric( $a['amountFiat'] ) ? (float) $a['amountFiat'] : 0.0;
		if ( $fiat > 0 ) {
			$rates = chainkit_bpb_get_rates();
			if ( isset( $rates[ $currency ]['rate'] ) && $rates[ $currency ]['rate'] > 0 ) {
				$btc         = $fiat / $rates[ $currency ]['rate'];
				$approx      = true;
				$amount_text = sprintf(
					/* translators: 1: fiat amount, 2: currency, 3: BTC amount. */
					__( '%1$s %2$s ≈ %3$s BTC', 'chainkit-bitcoin-payment-button' ),
					rtrim( rtrim( number_format( $fiat, 2, '.', '' ), '0' ), '.' ),
					$currency,
					chainkit_bpb_format_btc( $btc )
				);
			} else {
				// Rate unreachable — degrade to an address-only request, honestly noted.
				$amount_text = sprintf(
					/* translators: %s: fiat amount + currency, e.g. "49 EUR". */
					__( 'Rate for %s unavailable — pay any amount', 'chainkit-bitcoin-payment-button' ),
					rtrim( rtrim( number_format( $fiat, 2, '.', '' ), '0' ), '.' ) . ' ' . $currency
				);
			}
		}
	}

	$align = in_array( $a['buttonAlign'], array( 'left', 'center', 'right' ), true ) ? $a['buttonAlign'] : 'left';
	$theme = in_array( $a['theme'], array( 'auto', 'light', 'dark' ), true ) ? $a['theme'] : 'auto';

	$button_text = trim( (string) $a['buttonText'] );
	if ( '' === $button_text ) {
		$button_text = __( 'Pay with Bitcoin', 'chainkit-bitcoin-payment-button' );
	}

	return array(
		'addr'         => $addr,
		'addr_valid'   => true,
		'uri'          => chainkit_bpb_build_uri( $addr, $btc, $a['label'], $a['message'] ),
		'amount_text'  => $amount_text,
		'approx'       => $approx,
		'button_text'  => $button_text,
		'align'        => $align,
		'theme'        => $theme,
		'label'        => trim( (string) $a['label'] ),
		'message'      => trim( (string) $a['message'] ),
		'show_qr'      => (bool) filter_var( $a['showQr'], FILTER_VALIDATE_BOOLEAN ),
		'show_powered' => (bool) filter_var( $a['showPowered'], FILTER_VALIDATE_BOOLEAN ),
	);
}

/**
 * Render the button markup from prepared, trusted values. Every dynamic value
 * is escaped at output. The bitcoin: link and address render server-side so the
 * button works with JavaScript disabled; view.js only enhances (QR + copy).
 *
 * @param array       $atts    Raw attributes.
 * @param string|null $wrapper Optional wrapper attributes from get_block_wrapper_attributes().
 * @return string HTML, or '' when there is no usable address.
 */
function chainkit_bpb_render( $atts, $wrapper = null ) {
	$p = chainkit_bpb_prepare( $atts );
	if ( null === $p || '' === $p['uri'] ) {
		return '';
	}

	$classes = sprintf(
		'chainkit-bpb chainkit-bpb--theme-%s chainkit-bpb--align-%s',
		esc_attr( $p['theme'] ),
		esc_attr( $p['align'] )
	);

	$mark = '<svg class="chainkit-bpb__glyph" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="11" y="3" width="2" height="18" fill="currentColor"/><rect x="2" y="5" width="8" height="2" fill="currentColor"/><rect x="2" y="11" width="8" height="2" fill="currentColor"/><rect x="2" y="17" width="8" height="2" fill="currentColor"/><rect x="14" y="11" width="8" height="2" fill="#bfdb00"/></svg>';

	ob_start();
	?>
	<div
		<?php
		// $wrapper is get_block_wrapper_attributes() output (already escaped by core);
		// the shortcode path has no wrapper and uses our own escaped class string.
		echo $wrapper ? $wrapper : 'class="' . esc_attr( $classes ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		data-uri="<?php echo esc_attr( $p['uri'] ); ?>"
	>
		<a class="chainkit-bpb__btn" href="<?php echo esc_url( $p['uri'], array( 'bitcoin' ) ); ?>">
			<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
			<span class="chainkit-bpb__btn-text"><?php echo esc_html( $p['button_text'] ); ?></span>
		</a>

		<?php if ( '' !== $p['amount_text'] ) : ?>
			<p class="chainkit-bpb__amount<?php echo $p['approx'] ? ' is-approx' : ''; ?>">
				<?php echo esc_html( $p['amount_text'] ); ?>
				<?php if ( $p['approx'] ) : ?>
					<span class="chainkit-bpb__note"><?php esc_html_e( 'approximate — rate not locked', 'chainkit-bitcoin-payment-button' ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<div class="chainkit-bpb__addr-row">
			<code class="chainkit-bpb__addr"><?php echo esc_html( $p['addr'] ); ?></code>
			<button type="button" class="chainkit-bpb__copy" data-copy="<?php echo esc_attr( $p['addr'] ); ?>" aria-label="<?php esc_attr_e( 'Copy Bitcoin address', 'chainkit-bitcoin-payment-button' ); ?>">
				<?php esc_html_e( 'Copy', 'chainkit-bitcoin-payment-button' ); ?>
			</button>
		</div>

		<?php if ( $p['show_qr'] ) : ?>
			<button type="button" class="chainkit-bpb__qr-toggle" aria-expanded="false" hidden>
				<?php esc_html_e( 'Show QR code', 'chainkit-bitcoin-payment-button' ); ?>
			</button>
			<div class="chainkit-bpb__qr" hidden aria-hidden="true"></div>
		<?php endif; ?>

		<?php if ( $p['show_powered'] ) : ?>
			<a class="chainkit-bpb__powered" href="<?php echo esc_url( CHAINKIT_BPB_SIGNUP_URL ); ?>" target="_blank" rel="noopener nofollow">
				<?php esc_html_e( 'Powered by chainkit — fiat-priced invoices, rate locked, settled to your wallet', 'chainkit-bitcoin-payment-button' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	return trim( ob_get_clean() );
}

/*
 * ---------------------------------------------------------------------------
 * Registration.
 * ---------------------------------------------------------------------------
 */

/**
 * Register the block from the compiled build/ metadata. The render callback
 * lives in render.php (referenced by block.json), which delegates here.
 */
add_action(
	'init',
	function () {
		// build/ is produced by `npm run build`; block.json there points render
		// at render.php and enqueues the compiled scripts/styles.
		if ( file_exists( CHAINKIT_BPB_DIR . 'build/block.json' ) ) {
			register_block_type( CHAINKIT_BPB_DIR . 'build' );
		}
	}
);

/**
 * Load translations.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain(
			'chainkit-bitcoin-payment-button',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
);

/**
 * `[chainkit_bitcoin_button]` shortcode for the Classic editor and page
 * builders. Same attributes as the block, kebab/camel both accepted.
 */
add_shortcode(
	'chainkit_bitcoin_button',
	function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'address'      => '',
				'amount_mode'  => 'none',
				'amount_btc'   => '',
				'amount_fiat'  => '',
				'currency'     => 'EUR',
				'label'        => '',
				'message'      => '',
				'button_text'  => '',
				'align'        => 'left',
				'theme'        => 'auto',
				'show_qr'      => 'true',
				'show_powered' => 'true',
			),
			$atts,
			'chainkit_bitcoin_button'
		);

		// Enqueue the frontend enhancer + styles only when the shortcode is used.
		if ( file_exists( CHAINKIT_BPB_DIR . 'build/view.js' ) ) {
			$asset = CHAINKIT_BPB_DIR . 'build/view.asset.php';
			$meta  = file_exists( $asset ) ? include $asset : array( 'dependencies' => array(), 'version' => CHAINKIT_BPB_VERSION );
			wp_enqueue_script(
				'chainkit-bpb-view',
				plugins_url( 'build/view.js', __FILE__ ),
				$meta['dependencies'],
				$meta['version'],
				true
			);
		}
		if ( file_exists( CHAINKIT_BPB_DIR . 'build/style-index.css' ) ) {
			wp_enqueue_style(
				'chainkit-bpb-style',
				plugins_url( 'build/style-index.css', __FILE__ ),
				array(),
				CHAINKIT_BPB_VERSION
			);
		}

		return chainkit_bpb_render(
			array(
				'address'     => $atts['address'],
				'amountMode'  => $atts['amount_mode'],
				'amountBtc'   => $atts['amount_btc'],
				'amountFiat'  => $atts['amount_fiat'],
				'currency'    => $atts['currency'],
				'label'       => $atts['label'],
				'message'     => $atts['message'],
				'buttonText'  => $atts['button_text'],
				'buttonAlign' => $atts['align'],
				'theme'       => $atts['theme'],
				'showQr'      => $atts['show_qr'],
				'showPowered' => $atts['show_powered'],
			)
		);
	}
);

/**
 * Clear the cached rate table on uninstall-adjacent cleanup. (Transients also
 * expire on their own; this is belt-and-suspenders for deactivation.)
 */
register_deactivation_hook(
	__FILE__,
	function () {
		delete_transient( 'chainkit_bpb_rates' );
	}
);
