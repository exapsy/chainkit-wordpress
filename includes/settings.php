<?php
/**
 * Global settings for the Bitcoin Payment Button.
 *
 * Adds a Settings → Bitcoin Payment Button screen so a merchant configures their
 * receiving address (and default currency/theme/button text) once, instead of
 * re-entering it in every block. Blocks and shortcodes inherit these as
 * defaults and can still override per instance.
 *
 * @package ChainkitBitcoinPaymentButton
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CHAINKIT_BPB_OPTION = 'chainkit_bpb_settings';

/**
 * Settings merged with defaults. Single source of truth for the fallbacks the
 * block render and shortcode read.
 *
 * @return array{address:string,currency:string,theme:string,button_text:string,show_powered:bool}
 */
function chainkit_bpb_get_settings() {
	$saved = get_option( CHAINKIT_BPB_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge(
		array(
			'address'      => '',
			'currency'     => 'EUR',
			'theme'        => 'auto',
			'button_text'  => __( 'Pay with Bitcoin', 'chainkit-bitcoin-payment-button' ),
			'show_powered' => true,
		),
		$saved
	);
}

/**
 * Register the option, its sanitizer, and the settings-page fields.
 */
add_action(
	'admin_init',
	function () {
		register_setting(
			'chainkit_bpb',
			CHAINKIT_BPB_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'chainkit_bpb_sanitize_settings',
				'default'           => array(),
			)
		);

		add_settings_section(
			'chainkit_bpb_main',
			'',
			function () {
				echo '<p>' . esc_html__( 'Set your Bitcoin address once here. Every button uses it by default — you can still override any of these on an individual button.', 'chainkit-bitcoin-payment-button' ) . '</p>';
			},
			'chainkit_bpb'
		);

		$fields = array(
			'address'      => __( 'Bitcoin address', 'chainkit-bitcoin-payment-button' ),
			'currency'     => __( 'Default currency', 'chainkit-bitcoin-payment-button' ),
			'theme'        => __( 'Default theme', 'chainkit-bitcoin-payment-button' ),
			'button_text'  => __( 'Default button text', 'chainkit-bitcoin-payment-button' ),
			'show_powered' => __( '“Powered by chainkit” link', 'chainkit-bitcoin-payment-button' ),
		);
		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'chainkit_bpb_' . $key,
				esc_html( $label ),
				'chainkit_bpb_render_field',
				'chainkit_bpb',
				'chainkit_bpb_main',
				array( 'key' => $key, 'label_for' => 'chainkit_bpb_' . $key )
			);
		}
	}
);

/**
 * Sanitize the settings array on save.
 *
 * @param mixed $input Raw submitted value.
 * @return array Clean settings.
 */
function chainkit_bpb_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$out   = array();

	$out['address'] = chainkit_bpb_sanitize_address( isset( $input['address'] ) ? $input['address'] : '' );

	$currency        = isset( $input['currency'] ) ? strtoupper( sanitize_text_field( $input['currency'] ) ) : 'EUR';
	$out['currency'] = in_array( $currency, chainkit_bpb_currencies(), true ) ? $currency : 'EUR';

	$theme        = isset( $input['theme'] ) ? sanitize_text_field( $input['theme'] ) : 'auto';
	$out['theme'] = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';

	$button_text        = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
	$out['button_text'] = '' !== $button_text ? $button_text : __( 'Pay with Bitcoin', 'chainkit-bitcoin-payment-button' );

	$out['show_powered'] = ! empty( $input['show_powered'] );

	if ( '' !== $out['address'] && ! chainkit_bpb_addr_looks_valid( $out['address'] ) ) {
		add_settings_error(
			CHAINKIT_BPB_OPTION,
			'address',
			__( 'That does not look like a Bitcoin address — saved anyway, but buttons will not render until it is valid.', 'chainkit-bitcoin-payment-button' ),
			'warning'
		);
	}

	return $out;
}

/**
 * Render a single settings field.
 *
 * @param array $args Field args ('key').
 */
function chainkit_bpb_render_field( $args ) {
	$s    = chainkit_bpb_get_settings();
	$key  = $args['key'];
	$id   = 'chainkit_bpb_' . $key;
	$name = CHAINKIT_BPB_OPTION . '[' . $key . ']';

	switch ( $key ) {
		case 'address':
			printf(
				'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text code" placeholder="bc1q… · 1… · 3…" spellcheck="false" autocomplete="off" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $s['address'] )
			);
			break;

		case 'currency':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( chainkit_bpb_currencies() as $c ) {
				printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $c ), selected( $s['currency'], $c, false ) );
			}
			echo '</select>';
			break;

		case 'theme':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( array(
				'auto'  => __( 'Auto (follow visitor’s device)', 'chainkit-bitcoin-payment-button' ),
				'light' => __( 'Light', 'chainkit-bitcoin-payment-button' ),
				'dark'  => __( 'Dark', 'chainkit-bitcoin-payment-button' ),
			) as $val => $label ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $val ), selected( $s['theme'], $val, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;

		case 'button_text':
			printf(
				'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $s['button_text'] )
			);
			break;

		case 'show_powered':
			printf(
				'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( $s['show_powered'], true, false ),
				esc_html__( 'Show a small link to chainkit under each button', 'chainkit-bitcoin-payment-button' )
			);
			break;
	}
}

/**
 * Add the settings page under the Settings menu.
 */
add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Bitcoin Payment Button', 'chainkit-bitcoin-payment-button' ),
			__( 'Bitcoin Payment Button', 'chainkit-bitcoin-payment-button' ),
			'manage_options',
			'chainkit-bpb',
			'chainkit_bpb_render_settings_page'
		);
	}
);

/**
 * Add a Settings shortcut on the Plugins screen row.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( CHAINKIT_BPB_FILE ),
	function ( $links ) {
		$url = admin_url( 'options-general.php?page=chainkit-bpb' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'chainkit-bitcoin-payment-button' ) . '</a>' );
		return $links;
	}
);

/**
 * Render the settings page shell.
 */
function chainkit_bpb_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'chainkit_bpb' );
			do_settings_sections( 'chainkit_bpb' );
			submit_button();
			?>
		</form>
		<p class="description">
			<?php
			printf(
				/* translators: %s: shortcode example. */
				esc_html__( 'Add a button with the “Bitcoin Payment Button” block, or the shortcode %s.', 'chainkit-bitcoin-payment-button' ),
				'<code>[chainkit_bitcoin_button]</code>'
			);
			?>
		</p>
	</div>
	<?php
}
