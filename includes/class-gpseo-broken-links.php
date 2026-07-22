<?php
/**
 * Broken link scanner: batches through posts via WP-Cron and stores findings in a custom table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Direct $wpdb queries against wp_gpseo_broken_links are intentional: there is no
 * WP_Query equivalent for an arbitrary custom table, and scan results must always
 * be read fresh (never cached) so the report reflects the latest completed scan.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class GP_SEO_Broken_Links {

	const CRON_HOOK  = 'gpseo_scan_broken_links_tick';
	const BATCH_SIZE = 15;

	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'scan_batch' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
	}

	public static function register_schedule( $schedules ) {
		$schedules['gpseo_ten_minutes'] = array(
			'interval' => 10 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 10 minutes (Technical SEO Toolkit link scan)', 'garion-projetos-technical-seo-toolkit' ),
		);

		return $schedules;
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gpseo_broken_links';
	}

	public static function activate() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			url TEXT NOT NULL,
			anchor_text VARCHAR(255) NOT NULL DEFAULT '',
			http_status VARCHAR(20) NOT NULL DEFAULT '',
			last_checked_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'gpseo_ten_minutes', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function trigger_scan_now() {
		update_option( 'gpseo_scan_cursor', 0 );
		update_option( 'gpseo_scan_status', 'running' );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	public function get_status() {
		return array(
			'status'     => get_option( 'gpseo_scan_status', 'idle' ),
			'cursor'     => (int) get_option( 'gpseo_scan_cursor', 0 ),
			'total'      => (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish,
			'last_run'   => get_option( 'gpseo_scan_last_run', '' ),
			'found'      => $this->count_results(),
		);
	}

	private function count_results() {
		global $wpdb;

		$table = self::table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function count_all( $search = '' ) {
		global $wpdb;

		$table = self::table_name();

		if ( $search ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE url LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' )
			);
		}

		return $this->count_results();
	}

	public function get_results( $search = '', $per_page = 20, $paged = 1 ) {
		global $wpdb;

		$table  = self::table_name();
		$offset = ( max( 1, $paged ) - 1 ) * $per_page;

		if ( $search ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE url LIKE %s ORDER BY last_checked_at DESC LIMIT %d OFFSET %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					$per_page,
					$offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY last_checked_at DESC LIMIT %d OFFSET %d", $per_page, $offset )
		);
	}

	/**
	 * Cron callback: process one batch of posts, then reschedule immediately if more remain.
	 */
	public function scan_batch() {
		$cursor = (int) get_option( 'gpseo_scan_cursor', 0 );

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => self::BATCH_SIZE,
				'offset'         => $cursor,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $posts ) ) {
			update_option( 'gpseo_scan_status', 'idle' );
			update_option( 'gpseo_scan_last_run', current_time( 'mysql' ) );
			update_option( 'gpseo_scan_cursor', 0 );

			return;
		}

		foreach ( $posts as $post ) {
			$this->scan_post( $post );
		}

		update_option( 'gpseo_scan_cursor', $cursor + count( $posts ) );

		if ( 'running' === get_option( 'gpseo_scan_status', 'idle' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 2, self::CRON_HOOK );
		}
	}

	private function scan_post( $post ) {
		global $wpdb;

		$table = self::table_name();
		$links = $this->extract_links( $post->post_content );

		foreach ( $links as $url => $anchor_text ) {
			$status = $this->check_url( $url );

			$existing = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d AND url = %s", $post->ID, $url )
			);

			if ( false === $status ) {
				if ( $existing ) {
					$wpdb->delete( $table, array( 'id' => (int) $existing ), array( '%d' ) );
				}
				continue;
			}

			$data = array(
				'post_id'          => $post->ID,
				'url'              => $url,
				'anchor_text'      => wp_trim_words( $anchor_text, 10 ),
				'http_status'      => (string) $status,
				'last_checked_at'  => current_time( 'mysql' ),
			);

			if ( $existing ) {
				$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			} else {
				$wpdb->insert( $table, $data );
			}
		}
	}

	private function extract_links( $content ) {
		$links = array();

		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
			return $links;
		}

		foreach ( $matches as $match ) {
			$url = trim( $match[1] );

			if ( '' === $url || 0 === strpos( $url, '#' ) || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) ) {
				continue;
			}

			$links[ $url ] = wp_strip_all_tags( $match[2] );
		}

		return $links;
	}

	/**
	 * Returns the HTTP status code for a broken link, or false if the link works.
	 */
	private function check_url( $url ) {
		$args = array(
			'timeout'     => 10,
			'redirection' => 5,
		);

		$response = wp_remote_head( $url, $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return 'error';
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $code >= 400 ? $code : false;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
