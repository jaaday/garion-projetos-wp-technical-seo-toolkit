<?php
/**
 * Basic page auditing: scans recent published content for common technical SEO issues.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Audit {

	/**
	 * @return array {rows: array[], total: int}
	 */
	public function run( $search = '', $paged = 1, $per_page = 20 ) {
		$query_args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => max( 1, $paged ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $search ) {
			$query_args['s'] = $search;
		}

		$query = new WP_Query( $query_args );
		$posts = $query->posts;

		$broken_links_by_post = $this->broken_link_counts();

		$rows = array();

		foreach ( $posts as $post ) {
			$issues = array();

			$title_length = strlen( $post->post_title );
			if ( $title_length < 30 || $title_length > 60 ) {
				$issues[] = __( 'Title length outside the recommended 30-60 characters.', 'garion-projetos-technical-seo-toolkit' );
			}

			if ( ! has_post_thumbnail( $post ) ) {
				$issues[] = __( 'No featured image set.', 'garion-projetos-technical-seo-toolkit' );
			}

			if ( ! $this->has_meta_description( $post ) ) {
				$issues[] = __( 'No meta description found.', 'garion-projetos-technical-seo-toolkit' );
			}

			if ( get_post_meta( $post->ID, '_gpseo_noindex', true ) ) {
				$issues[] = __( 'Marked as noindex.', 'garion-projetos-technical-seo-toolkit' );
			}

			$broken_count = $broken_links_by_post[ $post->ID ] ?? 0;
			if ( $broken_count > 0 ) {
				/* translators: %d: number of broken links found. */
				$issues[] = sprintf( _n( '%d broken link found.', '%d broken links found.', $broken_count, 'garion-projetos-technical-seo-toolkit' ), $broken_count );
			}

			$rows[] = array(
				'post'   => $post,
				'issues' => $issues,
				'score'  => max( 0, 100 - ( count( $issues ) * 20 ) ),
			);
		}

		return array(
			'rows'  => $rows,
			'total' => (int) $query->found_posts,
		);
	}

	private function has_meta_description( $post ) {
		$keys = array( '_gpseo_meta_description', '_yoast_wpseo_metadesc', 'rank_math_description' );

		foreach ( $keys as $key ) {
			if ( get_post_meta( $post->ID, $key, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function broken_link_counts() {
		global $wpdb;

		$table = GP_SEO_Broken_Links::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregate read from the plugin's own custom table, must reflect the latest scan.
		$results = $wpdb->get_results( "SELECT post_id, COUNT(*) as total FROM {$table} GROUP BY post_id" );

		$counts = array();

		foreach ( $results as $row ) {
			$counts[ (int) $row->post_id ] = (int) $row->total;
		}

		return $counts;
	}
}
