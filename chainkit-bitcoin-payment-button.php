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
// Settings screen + global defaults (address, currency, theme, …).
require_once CHAINKIT_BPB_DIR . 'includes/settings.php';

/**
 * Currency symbol for the no-JS server-rendered fiat reference. (In the browser,
 * view.js uses Intl.NumberFormat with the visitor's locale for proper
 * localization; this is only the fallback.)
 *
 * @param string $currency ISO code.
 * @return string Symbol.
 */
function chainkit_bpb_currency_symbol( $currency ) {
	$symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'JPY' => '¥',
		'CAD' => 'CA$',
	);
	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';
}

/**
 * Format a fiat amount for the server-rendered (no-JS) reference line.
 * JPY has no minor unit; everything else gets 2 decimals, dropped when whole.
 *
 * @param float  $amount   Amount.
 * @param string $currency ISO code.
 * @return string e.g. "£268" or "€49".
 */
function chainkit_bpb_format_fiat( $amount, $currency ) {
	$decimals = ( 'JPY' === $currency ) ? 0 : 2;
	$str      = number_format( $amount, $decimals, '.', ',' );
	if ( $decimals > 0 ) {
		$str = rtrim( rtrim( $str, '0' ), '.' );
	}
	return chainkit_bpb_currency_symbol( $currency ) . $str;
}

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
 * Normalize raw attributes (from the block or the shortcode) against the global
 * settings + defaults, and resolve the amount into an on-chain BTC figure plus a
 * fiat reference. Central so the block and shortcode render identically.
 *
 * @param array $atts Raw attributes.
 * @return array{
 *   addr:string, uri:string, mode:string, btc:?float, btc_text:string,
 *   ref_currency:string, ref_text:string, approx:bool, rates:array<string,float>,
 *   button_text:string, align:string, theme:string, show_qr:bool, show_powered:bool
 * }|null Null when there is no usable address (nothing to render).
 */
function chainkit_bpb_prepare( $atts ) {
	$settings = chainkit_bpb_get_settings();
	$a        = wp_parse_args( $atts, chainkit_bpb_defaults() );

	// Fall back to the globally configured address when none is set on the block.
	$addr = chainkit_bpb_sanitize_address( $a['address'] );
	if ( '' === $addr ) {
		$addr = chainkit_bpb_sanitize_address( $settings['address'] );
	}
	if ( '' === $addr || ! chainkit_bpb_addr_looks_valid( $addr ) ) {
		return null;
	}

	$mode     = in_array( $a['amountMode'], array( 'none', 'btc', 'fiat' ), true ) ? $a['amountMode'] : 'none';
	$currency = strtoupper( (string) $a['currency'] );
	if ( ! in_array( $currency, chainkit_bpb_currencies(), true ) ) {
		$currency = in_array( $settings['currency'], chainkit_bpb_currencies(), true ) ? $settings['currency'] : 'EUR';
	}

	// The rate table drives both the (fiat-mode) conversion and every button's
	// switchable fiat reference. One cached fetch, shared. Map: CUR => float rate.
	$rate_rows = chainkit_bpb_get_rates();
	$rates     = array();
	if ( is_array( $rate_rows ) ) {
		foreach ( $rate_rows as $cur => $row ) {
			if ( isset( $row['rate'] ) && $row['rate'] > 0 ) {
				$rates[ $cur ] = (float) $row['rate'];
			}
		}
	}

	$btc      = null;   // On-chain amount encoded in the URI.
	$btc_text = '';     // Big primary display, e.g. "0.005 BTC".
	$approx   = false;  // True when a live rate is involved (label as approximate).

	if ( 'btc' === $mode ) {
		$v = is_numeric( $a['amountBtc'] ) ? (float) $a['amountBtc'] : 0.0;
		if ( $v > 0 ) {
			$btc      = $v;
			$btc_text = chainkit_bpb_format_btc( $v ) . ' BTC';
		}
	} elseif ( 'fiat' === $mode ) {
		$fiat = is_numeric( $a['amountFiat'] ) ? (float) $a['amountFiat'] : 0.0;
		if ( $fiat > 0 && isset( $rates[ $currency ] ) ) {
			$btc      = $fiat / $rates[ $currency ];
			$btc_text = chainkit_bpb_format_btc( $btc ) . ' BTC';
			$approx   = true;
		}
	}

	// Server-rendered (no-JS) fiat reference. view.js replaces it with a
	// locale-guessed, switchable version. For a fixed amount this is the value of
	// that amount; with no amount it's the price of 1 BTC.
	$ref_text = '';
	if ( isset( $rates[ $currency ] ) ) {
		if ( null !== $btc ) {
			$ref_text = '≈ ' . chainkit_bpb_format_fiat( $btc * $rates[ $currency ], $currency );
			$approx   = true;
		} elseif ( 'none' === $mode ) {
			$ref_text = sprintf(
				/* translators: %s: formatted price of one bitcoin, e.g. "£53,400". */
				__( '1 BTC ≈ %s', 'chainkit-bitcoin-payment-button' ),
				chainkit_bpb_format_fiat( $rates[ $currency ], $currency )
			);
			$approx = true;
		}
	}

	$align = in_array( $a['buttonAlign'], array( 'left', 'center', 'right' ), true ) ? $a['buttonAlign'] : 'left';
	$theme = in_array( $a['theme'], array( 'auto', 'light', 'dark' ), true ) ? $a['theme'] : 'auto';

	$button_text = trim( (string) $a['buttonText'] );
	if ( '' === $button_text ) {
		$button_text = trim( (string) $settings['button_text'] );
	}
	if ( '' === $button_text ) {
		$button_text = __( 'Pay with Bitcoin', 'chainkit-bitcoin-payment-button' );
	}

	return array(
		'addr'         => $addr,
		'uri'          => chainkit_bpb_build_uri( $addr, $btc, $a['label'], $a['message'] ),
		'mode'         => $mode,
		'btc'          => $btc,
		'btc_text'     => $btc_text,
		'ref_currency' => $currency,
		'ref_text'     => $ref_text,
		'approx'       => $approx,
		'rates'        => $rates,
		'button_text'  => $button_text,
		'align'        => $align,
		'theme'        => $theme,
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

	// The chainkit mark: three ledger rows + a lime accent bar.
	$mark = '<svg class="chainkit-bpb__mark" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="11" y="3" width="2" height="18" fill="currentColor"/><rect x="2" y="5" width="8" height="2" fill="currentColor"/><rect x="2" y="11" width="8" height="2" fill="currentColor"/><rect x="2" y="17" width="8" height="2" fill="currentColor"/><rect x="14" y="11" width="8" height="2" fill="var(--ck-lime)"/></svg>';
	// A copy glyph (two offset sheets).
	$copy_icon = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M4 16V4a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="2"/></svg>';
	// A wallet-open arrow.
	$arrow = '<svg class="chainkit-bpb__btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	$has_amount = ( '' !== $p['btc_text'] || '' !== $p['ref_text'] );

	ob_start();
	?>
	<div
		<?php
		// $wrapper is get_block_wrapper_attributes() output (already escaped by core);
		// the shortcode path has no wrapper and uses our own escaped class string.
		echo $wrapper ? $wrapper : 'class="' . esc_attr( $classes ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		data-uri="<?php echo esc_attr( $p['uri'] ); ?>"
		data-btc="<?php echo esc_attr( null !== $p['btc'] ? chainkit_bpb_format_btc( $p['btc'] ) : '' ); ?>"
		data-mode="<?php echo esc_attr( $p['mode'] ); ?>"
		data-ref-currency="<?php echo esc_attr( $p['ref_currency'] ); ?>"
		data-rates="<?php echo esc_attr( (string) wp_json_encode( (object) $p['rates'] ) ); ?>"
	>
		<div class="chainkit-bpb__head">
			<span class="chainkit-bpb__eyebrow">
				<span class="chainkit-bpb__dot<?php echo $p['approx'] ? ' is-live' : ''; ?>" aria-hidden="true"></span>
				<?php esc_html_e( 'Bitcoin payment', 'chainkit-bitcoin-payment-button' ); ?>
			</span>
			<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
		</div>

		<?php if ( $has_amount ) : ?>
			<div class="chainkit-bpb__amount">
				<?php if ( '' !== $p['btc_text'] ) : ?>
					<div class="chainkit-bpb__btc"><?php echo esc_html( $p['btc_text'] ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $p['ref_text'] ) : ?>
					<div class="chainkit-bpb__fiat" data-fiat><?php echo esc_html( $p['ref_text'] ); ?></div>
				<?php endif; ?>
				<?php if ( $p['approx'] ) : ?>
					<p class="chainkit-bpb__note">
						<?php esc_html_e( 'Approximate — the rate is not locked.', 'chainkit-bitcoin-payment-button' ); ?>
						<a href="<?php echo esc_url( CHAINKIT_BPB_SIGNUP_URL ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Lock it with chainkit', 'chainkit-bitcoin-payment-button' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<a class="chainkit-bpb__btn" href="<?php echo esc_url( $p['uri'], array( 'bitcoin' ) ); ?>">
			<span class="chainkit-bpb__btn-text"><?php echo esc_html( $p['button_text'] ); ?></span>
			<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
		</a>

		<div class="chainkit-bpb__addr-row">
			<span class="chainkit-bpb__addr-label"><?php esc_html_e( 'to', 'chainkit-bitcoin-payment-button' ); ?></span>
			<code class="chainkit-bpb__addr" data-full="<?php echo esc_attr( $p['addr'] ); ?>"><?php echo esc_html( $p['addr'] ); ?></code>
			<button type="button" class="chainkit-bpb__copy" data-copy="<?php echo esc_attr( $p['addr'] ); ?>" title="<?php esc_attr_e( 'Copy address', 'chainkit-bitcoin-payment-button' ); ?>">
				<?php echo $copy_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Copy Bitcoin address', 'chainkit-bitcoin-payment-button' ); ?></span>
			</button>
		</div>

		<?php if ( $p['show_qr'] ) : ?>
			<button type="button" class="chainkit-bpb__qr-toggle" aria-expanded="false" hidden>
				<span class="chainkit-bpb__qr-toggle-label"><?php esc_html_e( 'Show QR code', 'chainkit-bitcoin-payment-button' ); ?></span>
			</button>
			<div class="chainkit-bpb__qr" hidden aria-hidden="true"></div>
		<?php endif; ?>

		<?php if ( $p['show_powered'] ) : ?>
			<a class="chainkit-bpb__powered" href="<?php echo esc_url( CHAINKIT_BPB_SIGNUP_URL ); ?>" target="_blank" rel="noopener nofollow" title="<?php esc_attr_e( 'Fiat-priced invoices, rate locked, settled to your wallet', 'chainkit-bitcoin-payment-button' ); ?>">
				<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG. ?>
				<span><?php
					printf(
						/* translators: %s: chainkit (brand name, kept as one styled word). */
						esc_html__( 'Powered by %s', 'chainkit-bitcoin-payment-button' ),
						'<strong>chainkit</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
					);
				?></span>
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
		// Missing attributes inherit the global settings so a shortcode can be as
		// short as `[chainkit_bitcoin_button]` once an address is configured.
		$s    = chainkit_bpb_get_settings();
		$atts = shortcode_atts(
			array(
				'address'      => $s['address'],
				'amount_mode'  => 'none',
				'amount_btc'   => '',
				'amount_fiat'  => '',
				'currency'     => $s['currency'],
				'label'        => '',
				'message'      => '',
				'button_text'  => $s['button_text'],
				'align'        => 'left',
				'theme'        => $s['theme'],
				'show_qr'      => 'true',
				'show_powered' => $s['show_powered'] ? 'true' : 'false',
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
