<?php
/**
 * Uninstall: remove plugin options.
 *
 * @package TWKDiscovery
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'twkd_settings' );
delete_option( 'twkd_indexnow_key' );
delete_option( 'twkd_indexnow_last' );
delete_option( 'twkd_error_log' );
delete_option( 'twkd_wizard_state' );
delete_option( 'twkd_wizard_done' );
delete_transient( 'twkd_llms_file_result' );

// Note: a physical llms.txt written via the settings screen is intentionally
// left in place so its content survives uninstalling the plugin.

// Multisite: clean each site.
if ( is_multisite() ) {
	$twkd_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $twkd_sites as $twkd_site_id ) {
		switch_to_blog( $twkd_site_id );
		delete_option( 'twkd_settings' );
		delete_option( 'twkd_indexnow_key' );
		delete_option( 'twkd_indexnow_last' );
		delete_option( 'twkd_error_log' );
		delete_option( 'twkd_wizard_state' );
		delete_option( 'twkd_wizard_done' );
		restore_current_blog();
	}
}
