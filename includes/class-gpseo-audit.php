<?php
/**
 * Backward-compatible content audit list backed by persisted weighted scores.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GP_SEO_Audit {

	/**
	 * Return the latest persisted audit summary for published content.
	 *
	 * @return array{rows: array, total: int}
	 */
	public function run( $search = '', $paged = 1, $per_page = 20 ) {
		$query_args = array(
			'post_type' => array_values( array_intersect( (array) get_option( 'gpseo_audit_post_types', array( 'post', 'page' ) ), get_post_types( array( 'public' => true ), 'names' ) ) ),
			'post_status' => 'publish', 'posts_per_page' => max( 1, (int) $per_page ),
			'paged' => max( 1, (int) $paged ), 'orderby' => 'date', 'order' => 'DESC',
		);
		if ( ! $query_args['post_type'] ) { $query_args['post_type'] = array( 'post', 'page' ); }
		if ( $search ) { $query_args['s'] = sanitize_text_field( $search ); }

		$query = new WP_Query( $query_args );
		$summaries = ( new GPSEO_Audit_Repository() )->content_summaries( wp_list_pluck( $query->posts, 'ID' ) );
		$rows = array();

		foreach ( $query->posts as $post ) {
			$summary = $summaries[ $post->ID ] ?? array( 'score' => null, 'open_issues' => 0, 'last_audit' => null, 'category_scores' => array() );
			$issues = array();
			if ( null === $summary['score'] ) {
				$issues[] = __( 'This content has not been audited by the new weighted engine yet.', 'garion-projetos-technical-seo-toolkit' );
			} elseif ( $summary['open_issues'] > 0 ) {
				$issues[] = sprintf(
					/* translators: %d: number of open SEO issues. */
					_n( '%d open SEO issue.', '%d open SEO issues.', $summary['open_issues'], 'garion-projetos-technical-seo-toolkit' ),
					$summary['open_issues']
				);
			}
			$rows[] = array( 'post' => $post, 'issues' => $issues, 'score' => $summary['score'], 'last_audit' => $summary['last_audit'], 'category_scores' => $summary['category_scores'], 'open_issues' => (int) $summary['open_issues'], 'metrics' => $summary['metrics'] ?? array() );
		}

		return array( 'rows' => $rows, 'total' => (int) $query->found_posts );
	}
}
