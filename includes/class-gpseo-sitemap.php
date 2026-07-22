<?php
/**
 * XML sitemap: a sitemap index plus paginated per-post-type sitemaps.
 * Posts/pages marked noindex (via the plugin's own meta box) are excluded.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Sitemap {

	const PER_PAGE = 500;

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?gpseo_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z]+)-([0-9]+)\.xml$', 'index.php?gpseo_sitemap=$matches[1]&gpseo_sitemap_page=$matches[2]', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'gpseo_sitemap';
		$vars[] = 'gpseo_sitemap_page';

		return $vars;
	}

	public static function sitemap_url() {
		return home_url( '/sitemap.xml' );
	}

	public function maybe_render() {
		$type = get_query_var( 'gpseo_sitemap' );

		if ( ! $type ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );

		if ( 'index' === $type ) {
			$this->render_index();
		} else {
			$page = max( 1, (int) get_query_var( 'gpseo_sitemap_page' ) );
			$this->render_urlset( $type, $page );
		}

		exit;
	}

	private function post_types_with_content() {
		$types = array();

		foreach ( array( 'post', 'page' ) as $post_type ) {
			$count = $this->count_indexable( $post_type );

			if ( $count > 0 ) {
				$types[ $post_type ] = $count;
			}
		}

		return $types;
	}

	private function count_indexable( $post_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- sitemap generation must reflect the current, published, indexable content.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				WHERE p.post_type = %s AND p.post_status = 'publish'
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = p.ID AND pm.meta_key = '_gpseo_noindex' AND pm.meta_value = '1'
				)",
				$post_type
			)
		);
	}

	private function render_index() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $this->post_types_with_content() as $post_type => $count ) {
			$pages = (int) ceil( $count / self::PER_PAGE );

			for ( $page = 1; $page <= $pages; $page++ ) {
				printf(
					"<sitemap><loc>%s</loc></sitemap>\n",
					esc_url( home_url( "/sitemap-{$post_type}-{$page}.xml" ) )
				);
			}
		}

		echo '</sitemapindex>';
	}

	private function render_urlset( $post_type, $page ) {
		global $wpdb;

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
			$offset = ( $page - 1 ) * self::PER_PAGE;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- sitemap generation must reflect the current, published, indexable content.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_modified_gmt FROM {$wpdb->posts} p
					WHERE p.post_type = %s AND p.post_status = 'publish'
					AND NOT EXISTS (
						SELECT 1 FROM {$wpdb->postmeta} pm
						WHERE pm.post_id = p.ID AND pm.meta_key = '_gpseo_noindex' AND pm.meta_value = '1'
					)
					ORDER BY p.post_modified_gmt DESC
					LIMIT %d OFFSET %d",
					$post_type,
					self::PER_PAGE,
					$offset
				)
			);

			foreach ( $rows as $row ) {
				printf(
					"<url><loc>%s</loc><lastmod>%s</lastmod></url>\n",
					esc_url( get_permalink( $row->ID ) ),
					esc_html( mysql2date( DATE_W3C, $row->post_modified_gmt, false ) )
				);
			}
		}

		echo '</urlset>';
	}
}
