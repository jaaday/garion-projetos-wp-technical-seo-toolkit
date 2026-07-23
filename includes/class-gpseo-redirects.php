<?php
/**
 * Redirect management: custom table + request-time matching.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class GP_SEO_Redirects {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 0 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'gpseo_redirects';
	}

	public static function activate() {
		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_path VARCHAR(255) NOT NULL,
			destination_url TEXT NOT NULL,
			redirect_type SMALLINT UNSIGNED NOT NULL DEFAULT 301,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
			last_accessed_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_path (source_path)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function normalize_path( $path ) {
		$path = wp_parse_url( $path, PHP_URL_PATH );
		$path = '/' . ltrim( (string) $path, '/' );
		$path = rtrim( $path, '/' );
		return '' === $path ? '/' : $path;
	}

	public static function normalize_destination( $destination_url ) {
		$destination_url = trim( (string) $destination_url );
		if ( 0 === strpos( $destination_url, '/' ) && 0 !== strpos( $destination_url, '//' ) ) {
			$destination_url = home_url( $destination_url );
		}

		$destination_url = esc_url_raw( $destination_url, array( 'http', 'https' ) );
		$scheme          = strtolower( (string) wp_parse_url( $destination_url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $destination_url : '';
	}

	public function get_all( $search = '', $per_page = 20, $paged = 1 ) {
		global $wpdb;
		$table    = self::table_name();
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset   = ( max( 1, (int) $paged ) - 1 ) * $per_page;

		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_path LIKE %s OR destination_url LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $per_page, $offset ) );
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
	}

	public function count_all( $search = '' ) {
		global $wpdb;
		$table = self::table_name();
		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source_path LIKE %s OR destination_url LIKE %s", $like, $like ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function get_all_unpaginated() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	public function add( $source_path, $destination_url, $redirect_type ) {
		global $wpdb;
		$source_path     = self::normalize_path( $source_path );
		$destination_url = self::normalize_destination( $destination_url );
		$redirect_type   = in_array( (int) $redirect_type, array( 301, 302, 307, 308 ), true ) ? (int) $redirect_type : 301;

		if ( ! $destination_url || $this->is_loop( $source_path, $destination_url ) ) {
			return false;
		}

		return $wpdb->replace(
			self::table_name(),
			array(
				'source_path'     => $source_path,
				'destination_url' => $destination_url,
				'redirect_type'   => $redirect_type,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s' )
		);
	}

	private function is_loop( $source_path, $destination_url ) {
		$home_host        = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$destination_host = strtolower( (string) wp_parse_url( $destination_url, PHP_URL_HOST ) );
		return $home_host === $destination_host && $source_path === self::normalize_path( $destination_url );
	}

	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		global $wpdb;
		$table       = self::table_name();
		$current_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = self::normalize_path( $current_uri );
		$row         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_path = %s", $path ) );
		if ( ! $row ) {
			return;
		}

		$destination = self::normalize_destination( $row->destination_url );
		if ( ! $destination || $this->is_loop( $path, $destination ) ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_accessed_at = %s WHERE id = %d", current_time( 'mysql' ), (int) $row->id ) );
		nocache_headers();
		wp_redirect( $destination, (int) $row->redirect_type, 'Garion Technical SEO Toolkit' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- administrator-managed HTTP(S) redirects may intentionally target external domains.
		exit;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
