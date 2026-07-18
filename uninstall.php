<?php
/**
 * Uninstall cleanup — runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the stored settings and the cached rate transient so nothing is left
 * behind. (Deactivation keeps settings; only deletion clears them.)
 *
 * @package ChainkitBitcoinPaymentButton
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'chainkit_bpb_settings' );
delete_transient( 'chainkit_bpb_rates' );

// Multisite: clear per-site copies too.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'chainkit_bpb_settings' );
		delete_transient( 'chainkit_bpb_rates' );
		restore_current_blog();
	}
}
