<?php
/**
 * 404 monitor: logs real front-end "not found" hits so they can be turned into redirects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Direct $wpdb queries against wp_gpseo_404_log are intentional: there is no
 * WP_Query equivalent for an arbitrary custom table, and every 404 request
 * must check/update this table, so the data must always be read fresh.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class GP_SEO_404_Monitor {

	const CRON_HOOK = 'gpseo_cleanup_404_log';

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_log' ), 20 );
		add_action( self::CRON_HOOK, array( $this, 'cleanup_old' ) );
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gpseo_404_log';
	}

	public static function activate() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url VARCHAR(500) NOT NULL,
			referrer VARCHAR(500) NOT NULL DEFAULT '',
			hit_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url (url(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function maybe_log() {
		if ( ! is_404() || is_admin() ) {
			return;
		}

		global $wpdb;

		$table    = self::table_name();
		$path     = GP_SEO_Redirects::normalize_path( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, hit_count FROM {$table} WHERE url = %s", $path ) );

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'hit_count' => (int) $existing->hit_count + 1,
					'last_seen' => current_time( 'mysql' ),
					'referrer'  => $referrer,
				),
				array( 'id' => $existing->id )
			);
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'url'        => $path,
				'referrer'   => $referrer,
				'hit_count'  => 1,
				'first_seen' => current_time( 'mysql' ),
				'last_seen'  => current_time( 'mysql' ),
			)
		);
	}

	public function get_results( $search = '', $per_page = 20, $paged = 1 ) {
		global $wpdb;

		$table  = self::table_name();
		$offset = ( max( 1, $paged ) - 1 ) * $per_page;

		if ( $search ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE url LIKE %s ORDER BY last_seen DESC LIMIT %d OFFSET %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					$per_page,
					$offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY last_seen DESC LIMIT %d OFFSET %d", $per_page, $offset )
		);
	}

	public function count_results( $search = '' ) {
		global $wpdb;

		$table = self::table_name();

		if ( $search ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE url LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' )
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function delete( $id ) {
		global $wpdb;

		return $wpdb->delete( self::table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public function cleanup_old( $days = 90 ) {
		global $wpdb;

		$table  = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", $cutoff ) );
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
