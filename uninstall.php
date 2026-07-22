<?php
/**
 * Uninstall routine: removes options, custom tables and post meta created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'gpseo_org_name' );
delete_option( 'gpseo_org_logo' );
delete_option( 'gpseo_robots_txt_extra' );
delete_option( 'gpseo_scan_cursor' );
delete_option( 'gpseo_scan_status' );
delete_option( 'gpseo_scan_last_run' );

// Dropping the plugin's own custom tables on uninstall; no core API exists for this.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gpseo_redirects" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gpseo_broken_links" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gpseo_404_log" );

delete_post_meta_by_key( '_gpseo_meta_description' );
delete_post_meta_by_key( '_gpseo_canonical_url' );
delete_post_meta_by_key( '_gpseo_noindex' );
delete_post_meta_by_key( '_gpseo_nofollow' );
delete_post_meta_by_key( '_gpseo_og_title' );
delete_post_meta_by_key( '_gpseo_og_description' );
delete_post_meta_by_key( '_gpseo_og_image' );

wp_clear_scheduled_hook( 'gpseo_scan_broken_links_tick' );
wp_clear_scheduled_hook( 'gpseo_cleanup_404_log' );

flush_rewrite_rules();
