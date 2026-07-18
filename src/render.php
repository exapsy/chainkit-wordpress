<?php
/**
 * Server render for the chainkit/bitcoin-payment-button block.
 *
 * Referenced by block.json ("render": "file:./render.php"). WordPress passes
 * $attributes, $content and $block into scope. All markup and escaping lives in
 * chainkit_bpb_render() (main plugin file) so the block and the
 * [chainkit_bitcoin_button] shortcode render identically.
 *
 * @package ChainkitBitcoinPaymentButton
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content (unused).
 * @var WP_Block $block      Block instance (unused).
 */

if ( ! function_exists( 'chainkit_bpb_render' ) ) {
	return;
}

// Merge WordPress' block-support wrapper (alignment, spacing, custom classes)
// with our component class so both the editor toolbar controls and our styling
// apply. The custom class carries the theme + internal alignment modifiers.
$chainkit_bpb_theme = isset( $attributes['theme'] ) ? (string) $attributes['theme'] : 'auto';
$chainkit_bpb_ba    = isset( $attributes['buttonAlign'] ) ? (string) $attributes['buttonAlign'] : 'left';

$chainkit_bpb_wrapper = get_block_wrapper_attributes(
	array(
		'class' => sprintf(
			'chainkit-bpb chainkit-bpb--theme-%s chainkit-bpb--align-%s',
			preg_replace( '/[^a-z]/', '', strtolower( $chainkit_bpb_theme ) ),
			preg_replace( '/[^a-z]/', '', strtolower( $chainkit_bpb_ba ) )
		),
	)
);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped inside chainkit_bpb_render().
echo chainkit_bpb_render( $attributes, $chainkit_bpb_wrapper );
