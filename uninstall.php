<?php
/**
 * Uninstall routine. Data is removed only after explicit opt-in.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

global $wpdb;

wp_clear_scheduled_hook( 'gpseo_scan_broken_links_tick' );
wp_clear_scheduled_hook( 'gpseo_start_daily_broken_links_scan' );
wp_clear_scheduled_hook( 'gpseo_cleanup_404_log' );
wp_clear_scheduled_hook( 'gpseo_process_audit_batch' );
wp_clear_scheduled_hook( 'gpseo_cleanup_audit_history' );

if ( ! get_option( 'gpseo_remove_data_on_uninstall', false ) ) {
	flush_rewrite_rules();
	return;
}

$gpseo_options = array(
	'gpseo_org_name', 'gpseo_org_logo', 'gpseo_robots_txt_extra', 'gpseo_scan_cursor',
	'gpseo_scan_status', 'gpseo_scan_last_run', 'gpseo_db_version', 'gpseo_flush_rewrite_rules',
	'gpseo_audit_post_types', 'gpseo_audit_batch_size', 'gpseo_audit_detail_retention_days',
	'gpseo_audit_summary_retention_months', 'gpseo_remove_data_on_uninstall',
);
foreach ( $gpseo_options as $gpseo_option ) { delete_option( $gpseo_option ); }

$gpseo_tables = array(
	'gpseo_redirects', 'gpseo_broken_links', 'gpseo_404_log', 'gpseo_audits',
	'gpseo_audit_results', 'gpseo_audit_issues', 'gpseo_score_history',
);
foreach ( $gpseo_tables as $gpseo_table ) {
	$gpseo_table_name = $wpdb->prefix . $gpseo_table;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted plugin-owned table name.
	$wpdb->query( "DROP TABLE IF EXISTS {$gpseo_table_name}" );
}

$gpseo_meta_keys = array(
	'_gpseo_meta_description', '_gpseo_canonical_url', '_gpseo_noindex', '_gpseo_nofollow',
	'_gpseo_og_title', '_gpseo_og_description', '_gpseo_og_image',
);
foreach ( $gpseo_meta_keys as $gpseo_meta_key ) { delete_post_meta_by_key( $gpseo_meta_key ); }

flush_rewrite_rules();
