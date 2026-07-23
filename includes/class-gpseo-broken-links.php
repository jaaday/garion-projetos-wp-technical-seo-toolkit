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

	const CRON_HOOK   = 'gpseo_scan_broken_links_tick';
	const DAILY_HOOK  = 'gpseo_start_daily_broken_links_scan';
	const BATCH_SIZE         = 15;
	const MAX_LINKS_PER_POST = 100;

	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'scan_batch' ) );
		add_action( self::DAILY_HOOK, array( $this, 'start_scheduled_scan' ) );
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
			final_url TEXT NULL,
			is_ignored TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			last_checked_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			UNIQUE KEY post_url (post_id, url(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$legacy_event = wp_get_scheduled_event( self::CRON_HOOK );
		if ( $legacy_event && ! empty( $legacy_event->schedule ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}

	public function start_scheduled_scan() {
		if ( 'running' !== get_option( 'gpseo_scan_status', 'idle' ) ) {
			$this->trigger_scan_now();
		}
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

	public function rescan_post( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) { return false; }
		$this->scan_post( $post );
		return true;
	}

	private function scan_post( $post ) {
		global $wpdb;

		$table = self::table_name();
		$links = array_slice( $this->extract_links( $post->post_content ), 0, self::MAX_LINKS_PER_POST, true );

		$ignored_urls = $wpdb->get_col( $wpdb->prepare( "SELECT url FROM {$table} WHERE post_id = %d AND is_ignored = 1", (int) $post->ID ) );
		$wpdb->delete( $table, array( 'post_id' => (int) $post->ID ), array( '%d' ) );

		foreach ( $links as $url => $anchor_text ) {
			$check = $this->check_url( $url );
			if ( false === $check ) { continue; }
			$wpdb->insert( $table, array(
				'post_id' => (int) $post->ID, 'url' => $url, 'anchor_text' => wp_trim_words( $anchor_text, 10 ),
				'http_status' => (string) $check['status'], 'final_url' => $check['final_url'],
				'is_ignored' => in_array( $url, $ignored_urls, true ) ? 1 : 0, 'last_checked_at' => current_time( 'mysql' ),
			) );
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
		$url = $this->normalize_url( $url );
		if ( ! $url ) {
			return false;
		}

		$args = array(
			'timeout'             => 8,
			'redirection'         => 5,
			'limit_response_size' => 1024,
			'user-agent'          => 'Garion Technical SEO Toolkit/' . GPSEO_VERSION . '; ' . home_url( '/' ),
		);

		$response = wp_safe_remote_head( $url, $args );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$response = wp_safe_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) { return array( 'status' => 'error', 'final_url' => $url ); }
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code > 0 && $code < 400 ) { return false; }
		$final_url = $url;
		if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
			$object = $response['http_response']->get_response_object();
			if ( is_object( $object ) && ! empty( $object->url ) ) { $final_url = esc_url_raw( $object->url ); }
		}
		return array( 'status' => $code ? $code : 'error', 'final_url' => $final_url );
	}

	public function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}
	public function set_ignored( $id, $ignored = true ) {
		global $wpdb;
		return false !== $wpdb->update( self::table_name(), array( 'is_ignored' => $ignored ? 1 : 0 ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
	}
	private function normalize_url( $url ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		if ( '' === $url || 0 === strpos( $url, '//' ) ) {
			return false;
		}

		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		} elseif ( ! wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			$url = home_url( '/' . ltrim( $url, '/' ) );
		}

		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		return esc_url_raw( $url );
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
