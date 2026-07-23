<?php
/**
 * Persistence and reporting queries for audits, results, issues and score history.
 *
 * This repository owns four custom tables (gpseo_audits, gpseo_audit_results,
 * gpseo_audit_issues, gpseo_score_history). Table names are only ever built from
 * $wpdb->prefix plus a hardcoded suffix (see self::tables()) — never from user
 * input — so interpolating {$t['...']} into query strings is safe; there is no
 * $wpdb->prepare() placeholder for identifiers (a %s placeholder would quote the
 * name as a string literal and break the query). Every value that does come from
 * a caller is passed through %d/%s placeholders and $wpdb->prepare().
 *
 * Rows here change on every audit run/scan, so results are not cached: showing a
 * stale progress/score snapshot to an admin mid-audit would be actively wrong.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GPSEO_Audit_Repository {

	public static function tables(): array {
		global $wpdb;
		return array(
			'audits' => $wpdb->prefix . 'gpseo_audits',
			'results' => $wpdb->prefix . 'gpseo_audit_results',
			'issues' => $wpdb->prefix . 'gpseo_audit_issues',
			'history' => $wpdb->prefix . 'gpseo_score_history',
		);
	}

	public static function activate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::tables(); $c = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$t['audits']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'pending',
			scope varchar(20) NOT NULL DEFAULT 'site',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_types text NULL,
			total_items bigint(20) unsigned NOT NULL DEFAULT 0,
			processed_items bigint(20) unsigned NOT NULL DEFAULT 0,
			cursor_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score decimal(5,2) NULL,
			category_scores longtext NULL,
			summary_metrics longtext NULL,
			error_message text NULL,
			created_at datetime NOT NULL,
			started_at datetime NULL,
			completed_at datetime NULL,
			heartbeat_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY post_id (post_id),
			KEY cursor_post_id (cursor_post_id),
			KEY created_at (created_at)
		) $c;" );

		dbDelta( "CREATE TABLE {$t['results']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			audit_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			check_id varchar(100) NOT NULL,
			category varchar(50) NOT NULL,
			severity varchar(20) NOT NULL,
			result_status varchar(20) NOT NULL,
			weight decimal(5,2) NOT NULL DEFAULT 0,
			raw_penalty decimal(6,2) NOT NULL DEFAULT 0,
			penalty decimal(6,2) NOT NULL DEFAULT 0,
			lifecycle_status varchar(20) NOT NULL DEFAULT '',
			fingerprint char(64) NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			message text NOT NULL,
			explanation text NULL,
			why_matters text NULL,
			recommendation text NULL,
			found_value text NULL,
			expected_value text NULL,
			evidence longtext NULL,
			remediation longtext NULL,
			source_provider varchar(50) NOT NULL DEFAULT 'unknown',
			first_detected_at datetime NULL,
			last_detected_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY audit_post_check (audit_id,post_id,check_id),
			KEY audit_id (audit_id),
			KEY post_id (post_id),
			KEY category_severity (category,severity),
			KEY fingerprint (fingerprint)
		) $c;" );

		dbDelta( "CREATE TABLE {$t['issues']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fingerprint char(64) NOT NULL,
			check_id varchar(100) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			url varchar(2048) NOT NULL,
			category varchar(50) NOT NULL,
			severity varchar(20) NOT NULL,
			issue_status varchar(20) NOT NULL DEFAULT 'open',
			impact tinyint(3) unsigned NOT NULL DEFAULT 0,
			ease tinyint(3) unsigned NOT NULL DEFAULT 3,
			priority tinyint(3) unsigned NOT NULL DEFAULT 0,
			weight decimal(5,2) NOT NULL DEFAULT 0,
			raw_penalty decimal(6,2) NOT NULL DEFAULT 0,
			penalty decimal(6,2) NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			message text NOT NULL,
			explanation text NULL,
			why_matters text NULL,
			recommendation text NULL,
			found_value text NULL,
			expected_value text NULL,
			evidence longtext NULL,
			remediation longtext NULL,
			source_provider varchar(50) NOT NULL DEFAULT 'unknown',
			ignored_reason text NULL,
			first_seen_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			resolved_at datetime NULL,
			last_audit_id bigint(20) unsigned NOT NULL,
			occurrences bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY post_id (post_id),
			KEY category (category),
			KEY severity (severity),
			KEY issue_status (issue_status),
			KEY last_audit_id (last_audit_id),
			KEY last_seen_at (last_seen_at)
		) $c;" );

		dbDelta( "CREATE TABLE {$t['history']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			audit_id bigint(20) unsigned NOT NULL,
			scope varchar(20) NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score decimal(5,2) NOT NULL,
			category_scores longtext NULL,
			metrics longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY audit_scope_post (audit_id,scope,post_id),
			KEY audit_id (audit_id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) $c;" );
	}

	public function create( string $scope, int $post_id, array $post_types, int $total ): int {
		global $wpdb; $t = self::tables();
		$wpdb->insert( $t['audits'], array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
			'status' => 'pending', 'scope' => $scope, 'post_id' => $post_id,
			'post_types' => wp_json_encode( array_values( $post_types ) ), 'total_items' => max( 0, $total ),
			'processed_items' => 0, 'created_at' => current_time( 'mysql', true ),
		) );
		return (int) $wpdb->insert_id;
	}

	public function get( int $audit_id ): ?object {
		global $wpdb; $t = self::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['audits']} WHERE id = %d", $audit_id ) );
		return $row ?: null;
	}

	public function find_active(): ?object {
		global $wpdb; $t = self::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); no external input in this query.
		$row = $wpdb->get_row( "SELECT * FROM {$t['audits']} WHERE status IN ('pending','running') ORDER BY id DESC LIMIT 1" );
		return $row ?: null;
	}

	public function update( int $audit_id, array $data ): bool {
		global $wpdb; $t = self::tables();
		$allowed = array( 'status', 'processed_items', 'cursor_post_id', 'score', 'category_scores', 'summary_metrics', 'error_message', 'started_at', 'completed_at', 'heartbeat_at' );
		$data = array_intersect_key( $data, array_flip( $allowed ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
		return ! empty( $data ) && false !== $wpdb->update( $t['audits'], $data, array( 'id' => $audit_id ) );
	}

	public function save_post_results( int $audit_id, WP_Post $post, array $results, array $score ): void {
		global $wpdb; $t = self::tables(); $now = current_time( 'mysql', true );
		$wpdb->delete( $t['results'], array( 'audit_id' => $audit_id, 'post_id' => $post->ID ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
		$seen = array();

		foreach ( $results as $result ) {
			if ( ! $result instanceof GPSEO_Audit_Result ) { continue; }
			$fingerprint = $result->fingerprint( (int) $post->ID );
			$breakdown = $score['breakdown'][ $result->get_check_id() ] ?? array( 'raw_penalty' => 0, 'applied_penalty' => 0 );
			$lifecycle = ''; $first_detected = null;
			if ( $result->is_failure() ) {
				$seen[] = $fingerprint;
				$issue_data = $this->upsert_issue( $audit_id, $post, $result, $fingerprint, $breakdown, $now );
				$lifecycle = $issue_data['lifecycle'];
				$first_detected = $issue_data['first_detected_at'];
			}
			$wpdb->insert( $t['results'], array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
				'audit_id' => $audit_id, 'post_id' => $post->ID, 'check_id' => $result->get_check_id(),
				'category' => $result->get_category(), 'severity' => $result->get_severity(),
				'result_status' => $result->get_status(), 'weight' => $result->get_weight(),
				'raw_penalty' => $breakdown['raw_penalty'], 'penalty' => $breakdown['applied_penalty'],
				'lifecycle_status' => $lifecycle, 'fingerprint' => $fingerprint, 'title' => $result->get_title(),
				'message' => $result->get_message(), 'explanation' => $result->get_explanation(),
				'why_matters' => $result->get_why_matters(), 'recommendation' => $result->get_recommendation(),
				'found_value' => $result->get_found_value(), 'expected_value' => $result->get_expected_value(),
				'evidence' => wp_json_encode( $result->get_evidence() ), 'remediation' => wp_json_encode( $result->get_remediation() ),
				'source_provider' => $result->get_source_provider(), 'first_detected_at' => $first_detected,
				'last_detected_at' => $result->is_failure() ? $now : null, 'created_at' => $now,
			) );
		}

		$resolved = $this->resolve_missing_issues( (int) $post->ID, $audit_id, $seen, $now );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$ignored = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['issues']} WHERE post_id = %d AND issue_status = 'ignored'", $post->ID ) );
		$metrics = array(
			'initial_score' => $score['initial_score'], 'total_penalty' => $score['total_penalty'],
			'category_cap' => $score['category_cap'], 'category_penalties' => $score['category_penalties'],
			'failed_checks' => $score['failed_checks'], 'resolved_issues' => $resolved, 'ignored_issues' => $ignored,
			'breakdown' => $score['breakdown'],
		);
		$wpdb->replace( $t['history'], array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
			'audit_id' => $audit_id, 'scope' => 'content', 'post_id' => $post->ID, 'score' => $score['score'],
			'category_scores' => wp_json_encode( $score['category_scores'] ), 'metrics' => wp_json_encode( $metrics ), 'created_at' => $now,
		) );
	}

	private function upsert_issue( int $audit_id, WP_Post $post, GPSEO_Audit_Result $result, string $fingerprint, array $breakdown, string $now ): array {
		global $wpdb; $t = self::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, issue_status, occurrences, first_seen_at FROM {$t['issues']} WHERE fingerprint = %s", $fingerprint ) );
		$impact_map = array( 'critical' => 5, 'high' => 4, 'medium' => 3, 'low' => 2, 'recommendation' => 1, 'informational' => 0 );
		$impact = $impact_map[ $result->get_severity() ] ?? 0;
		$data = array(
			'check_id' => $result->get_check_id(), 'post_id' => $post->ID, 'url' => (string) get_permalink( $post ),
			'category' => $result->get_category(), 'severity' => $result->get_severity(), 'impact' => $impact,
			'priority' => min( 10, $impact + 3 ), 'weight' => $result->get_weight(),
			'raw_penalty' => $breakdown['raw_penalty'], 'penalty' => $breakdown['applied_penalty'],
			'title' => $result->get_title(), 'message' => $result->get_message(),
			'explanation' => $result->get_explanation(), 'why_matters' => $result->get_why_matters(),
			'recommendation' => $result->get_recommendation(), 'found_value' => $result->get_found_value(),
			'expected_value' => $result->get_expected_value(), 'evidence' => wp_json_encode( $result->get_evidence() ),
			'remediation' => wp_json_encode( $result->get_remediation() ), 'source_provider' => $result->get_source_provider(),
			'last_seen_at' => $now, 'last_audit_id' => $audit_id, 'resolved_at' => null,
		);
		if ( $existing ) {
			if ( 'ignored' === $existing->issue_status ) { $lifecycle = 'ignored'; }
			elseif ( 'resolved' === $existing->issue_status ) { $lifecycle = 'reopened'; $data['issue_status'] = 'reopened'; }
			else { $lifecycle = 'persistent'; $data['issue_status'] = 'open'; }
			$data['occurrences'] = (int) $existing->occurrences + 1;
			$wpdb->update( $t['issues'], $data, array( 'id' => $existing->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
			return array( 'lifecycle' => $lifecycle, 'first_detected_at' => $existing->first_seen_at );
		}
		$data['fingerprint'] = $fingerprint; $data['issue_status'] = 'open'; $data['first_seen_at'] = $now; $data['occurrences'] = 1;
		$wpdb->insert( $t['issues'], $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
		return array( 'lifecycle' => 'new', 'first_detected_at' => $now );
	}

	private function resolve_missing_issues( int $post_id, int $audit_id, array $seen, string $now ): int {
		global $wpdb; $t = self::tables();
		if ( $seen ) {
			$placeholders = implode( ',', array_fill( 0, count( $seen ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); write operation, nothing to cache; $placeholders is a fixed run of %s tokens matching count($seen), all values placeholdered via prepare().
			return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$t['issues']} SET issue_status = 'resolved', resolved_at = %s WHERE post_id = %d AND issue_status IN ('open','reopened') AND last_audit_id <> %d AND fingerprint NOT IN ($placeholders)", $now, $post_id, $audit_id, ...$seen ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); write operation, nothing to cache; values are placeholdered via prepare().
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$t['issues']} SET issue_status = 'resolved', resolved_at = %s WHERE post_id = %d AND issue_status IN ('open','reopened') AND last_audit_id <> %d", $now, $post_id, $audit_id ) );
	}

	public function complete( int $audit_id ): array {
		global $wpdb; $t = self::tables(); $audit = $this->get( $audit_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT AVG(score) average_score, COUNT(*) items FROM {$t['history']} WHERE audit_id = %d AND scope = 'content'", $audit_id ) );
		$score = $row && null !== $row->average_score ? round( (float) $row->average_score, 2 ) : 100.0;
		$category_totals = array(); $category_counts = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$snapshots = $wpdb->get_col( $wpdb->prepare( "SELECT category_scores FROM {$t['history']} WHERE audit_id = %d AND scope = 'content'", $audit_id ) );
		foreach ( $snapshots as $snapshot ) {
			foreach ( (array) json_decode( (string) $snapshot, true ) as $category => $value ) {
				$category_totals[ $category ] = ( $category_totals[ $category ] ?? 0.0 ) + (float) $value;
				$category_counts[ $category ] = ( $category_counts[ $category ] ?? 0 ) + 1;
			}
		}
		$category_scores = array();
		foreach ( $category_totals as $category => $total ) { $category_scores[ $category ] = round( $total / $category_counts[ $category ], 2 ); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$severity_rows = $wpdb->get_results( $wpdb->prepare( "SELECT severity, COUNT(*) total FROM {$t['results']} WHERE audit_id = %d AND result_status = 'fail' GROUP BY severity", $audit_id ), OBJECT_K );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$category_rows = $wpdb->get_results( $wpdb->prepare( "SELECT category, COUNT(*) total FROM {$t['results']} WHERE audit_id = %d AND result_status = 'fail' GROUP BY category", $audit_id ), OBJECT_K );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$lifecycle_rows = $wpdb->get_results( $wpdb->prepare( "SELECT lifecycle_status, COUNT(*) total FROM {$t['results']} WHERE audit_id = %d AND result_status = 'fail' GROUP BY lifecycle_status", $audit_id ), OBJECT_K );
		$severities = array(); $categories = array(); $lifecycles = array();
		foreach ( $severity_rows as $key => $value ) { $severities[ $key ] = (int) $value->total; }
		foreach ( $category_rows as $key => $value ) { $categories[ $key ] = (int) $value->total; }
		foreach ( $lifecycle_rows as $key => $value ) { $lifecycles[ $key ] = (int) $value->total; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$resolved = $audit && $audit->started_at ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['issues']} WHERE resolved_at >= %s", $audit->started_at ) ) : 0;
		$metrics = array(
			'content_items' => (int) ( $row->items ?? 0 ), 'problems' => array_sum( $severities ),
			'critical' => (int) ( $severities['critical'] ?? 0 ), 'severities' => $severities, 'categories' => $categories,
			'new_problems' => (int) ( $lifecycles['new'] ?? 0 ), 'reopened_problems' => (int) ( $lifecycles['reopened'] ?? 0 ),
			'resolved_problems' => $resolved, 'broken_links' => class_exists( 'GP_SEO_Broken_Links' ) ? ( new GP_SEO_Broken_Links() )->count_all() : 0,
			'errors_404' => class_exists( 'GP_SEO_404_Monitor' ) ? ( new GP_SEO_404_Monitor() )->count_results() : 0,
		);
		$now = current_time( 'mysql', true );
		$this->update( $audit_id, array(
			'status' => 'completed', 'score' => $score, 'category_scores' => wp_json_encode( $category_scores ),
			'summary_metrics' => wp_json_encode( $metrics ), 'completed_at' => $now, 'heartbeat_at' => $now,
		) );
		$wpdb->replace( $t['history'], array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
			'audit_id' => $audit_id, 'scope' => 'site', 'post_id' => 0, 'score' => $score,
			'category_scores' => wp_json_encode( $category_scores ), 'metrics' => wp_json_encode( $metrics ), 'created_at' => $now,
		) );
		return array( 'score' => $score, 'category_scores' => $category_scores, 'metrics' => $metrics );
	}

	public function history( int $limit = 20, int $post_id = 0 ): array {
		global $wpdb; $t = self::tables(); $limit = max( 1, min( 100, $limit ) );
		$rows = $post_id
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['history']} WHERE scope = 'content' AND post_id = %d ORDER BY id DESC LIMIT %d", $post_id, $limit ), ARRAY_A ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); values placeholdered via prepare().
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['history']} WHERE scope = 'site' ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value placeholdered via prepare().
		foreach ( $rows as &$row ) {
			$row['category_scores'] = json_decode( (string) $row['category_scores'], true ) ?: array();
			$row['metrics'] = json_decode( (string) $row['metrics'], true ) ?: array();
		}
		return $rows;
	}

	public function content_summaries( array $post_ids ): array {
		global $wpdb; $t = self::tables(); $ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );
		if ( ! $ids ) { return array(); }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin tables (see class docblock); $placeholders is a fixed run of %d tokens matching count($ids).
		$history = $wpdb->get_results( $wpdb->prepare( "SELECT h.post_id, h.score, h.category_scores, h.metrics, h.created_at FROM {$t['history']} h INNER JOIN (SELECT post_id, MAX(id) latest_id FROM {$t['history']} WHERE scope = 'content' AND post_id IN ($placeholders) GROUP BY post_id) latest ON h.id = latest.latest_id", ...$ids ), OBJECT_K );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); $placeholders is a fixed run of %d tokens matching count($ids).
		$issues = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, COUNT(*) total FROM {$t['issues']} WHERE post_id IN ($placeholders) AND issue_status IN ('open','reopened') GROUP BY post_id", ...$ids ), OBJECT_K );
		$out = array();
		foreach ( $ids as $post_id ) {
			$out[ $post_id ] = array(
				'score' => isset( $history[ $post_id ] ) ? (float) $history[ $post_id ]->score : null,
				'category_scores' => isset( $history[ $post_id ] ) ? ( json_decode( (string) $history[ $post_id ]->category_scores, true ) ?: array() ) : array(),
				'metrics' => isset( $history[ $post_id ] ) ? ( json_decode( (string) $history[ $post_id ]->metrics, true ) ?: array() ) : array(),
				'last_audit' => isset( $history[ $post_id ] ) ? $history[ $post_id ]->created_at : null,
				'open_issues' => isset( $issues[ $post_id ] ) ? (int) $issues[ $post_id ]->total : 0,
			);
		}
		return $out;
	}

	public function content_details( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) { return null; }
		$summary = $this->content_summaries( array( $post_id ) )[ $post_id ] ?? array();
		$counts = $this->issue_counts( array( 'post_id' => $post_id, 'status' => array( 'open', 'reopened' ) ) );
		return array(
			'post' => $post, 'url' => get_permalink( $post ), 'summary' => $summary,
			'severity_counts' => $counts['severities'], 'total_issues' => $counts['total'], 'history' => $this->history( 10, $post_id ),
		);
	}

	private function issue_where( array $filters, array &$params ): string {
		global $wpdb; $clauses = array( '1=1' );
		if ( ! empty( $filters['audit_id'] ) ) { $clauses[] = 'i.last_audit_id = %d'; $params[] = absint( $filters['audit_id'] ); }
		if ( ! empty( $filters['post_id'] ) ) { $clauses[] = 'i.post_id = %d'; $params[] = absint( $filters['post_id'] ); }
		if ( ! empty( $filters['severity'] ) ) { $clauses[] = 'i.severity = %s'; $params[] = sanitize_key( $filters['severity'] ); }
		if ( ! empty( $filters['category'] ) ) { $clauses[] = 'i.category = %s'; $params[] = sanitize_key( $filters['category'] ); }
		if ( ! empty( $filters['post_type'] ) ) { $clauses[] = 'p.post_type = %s'; $params[] = sanitize_key( $filters['post_type'] ); }
		if ( ! empty( $filters['status'] ) ) {
			$statuses = array_values( array_intersect( (array) $filters['status'], array( 'open', 'ignored', 'resolved', 'reopened' ) ) );
			if ( $statuses ) { $clauses[] = 'i.issue_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')'; array_push( $params, ...$statuses ); }
		}
		if ( ! empty( $filters['search'] ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$clauses[] = '(i.title LIKE %s OR i.message LIKE %s OR i.url LIKE %s OR p.post_title LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}
		return implode( ' AND ', $clauses );
	}

	public function list_issues( array $filters = array() ): array {
		global $wpdb; $t = self::tables(); $params = array(); $where = $this->issue_where( $filters, $params );
		$page = max( 1, absint( $filters['page'] ?? 1 ) ); $per_page = max( 1, min( 100, absint( $filters['per_page'] ?? 20 ) ) ); $offset = ( $page - 1 ) * $per_page;
		$order_map = array(
			'severity' => "FIELD(i.severity,'critical','high','medium','low','recommendation','informational')",
			'score' => 'i.penalty', 'date' => 'i.last_seen_at',
		);
		$order_by = $order_map[ sanitize_key( $filters['order_by'] ?? 'date' ) ] ?? $order_map['date'];
		$order = 'ASC' === strtoupper( (string) ( $filters['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		array_push( $params, $per_page, $offset );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); $where holds only %s/%d placeholders (values in $params); $order_by/$order come from a fixed whitelist above, never from raw input.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.*, p.post_title, p.post_type, affected.affected_urls FROM {$t['issues']} i LEFT JOIN {$wpdb->posts} p ON p.ID = i.post_id LEFT JOIN (SELECT check_id, COUNT(DISTINCT url) affected_urls FROM {$t['issues']} WHERE issue_status IN ('open','reopened') GROUP BY check_id) affected ON affected.check_id = i.check_id WHERE $where ORDER BY $order_by $order, i.id DESC LIMIT %d OFFSET %d", ...$params ), ARRAY_A );
		foreach ( $rows as &$row ) { $row = $this->decode_issue( $row ); }
		return $rows;
	}

	public function issue_counts( array $filters = array() ): array {
		global $wpdb; $t = self::tables(); $params = array(); $where = $this->issue_where( $filters, $params );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); $where holds only %s/%d placeholders (values in $params).
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.severity, COUNT(*) total FROM {$t['issues']} i LEFT JOIN {$wpdb->posts} p ON p.ID = i.post_id WHERE $where GROUP BY i.severity", ...$params ), OBJECT_K );
		$severities = array(); $total = 0;
		foreach ( $rows as $key => $row ) { $severities[ $key ] = (int) $row->total; $total += (int) $row->total; }
		return array( 'total' => $total, 'severities' => $severities );
	}

	public function get_issue( int $issue_id ): ?array {
		global $wpdb; $t = self::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT i.*, p.post_title, p.post_type FROM {$t['issues']} i LEFT JOIN {$wpdb->posts} p ON p.ID = i.post_id WHERE i.id = %d", $issue_id ), ARRAY_A );
		return $row ? $this->decode_issue( $row ) : null;
	}

	public function set_issue_status( int $issue_id, string $status, string $reason = '' ): bool {
		global $wpdb; $t = self::tables();
		if ( ! in_array( $status, array( 'ignored', 'open' ), true ) ) { return false; }
		return false !== $wpdb->update( $t['issues'], array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no public WP API; write operation, nothing to cache.
			'issue_status' => $status, 'ignored_reason' => 'ignored' === $status ? sanitize_textarea_field( $reason ) : '',
			'resolved_at' => null,
		), array( 'id' => $issue_id ) );
	}

	private function decode_issue( array $row ): array {
		$row['id'] = (int) $row['id']; $row['post_id'] = (int) $row['post_id'];
		$row['weight'] = (float) $row['weight']; $row['raw_penalty'] = (float) $row['raw_penalty']; $row['penalty'] = (float) $row['penalty'];
		$row['evidence'] = json_decode( (string) $row['evidence'], true ) ?: array();
		$row['remediation'] = json_decode( (string) $row['remediation'], true ) ?: array();
		return $row;
	}

	public function audit_details( int $audit_id ): ?array {
		global $wpdb; $t = self::tables(); $audit = $this->get( $audit_id );
		if ( ! $audit ) { return null; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$severities = $wpdb->get_results( $wpdb->prepare( "SELECT severity, COUNT(*) total FROM {$t['results']} WHERE audit_id = %d AND result_status = 'fail' GROUP BY severity", $audit_id ), OBJECT_K );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$categories = $wpdb->get_results( $wpdb->prepare( "SELECT category, COUNT(*) total FROM {$t['results']} WHERE audit_id = %d AND result_status = 'fail' GROUP BY category", $audit_id ), OBJECT_K );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin tables (see class docblock); value is placeholdered via prepare().
		$worst = $wpdb->get_results( $wpdb->prepare( "SELECT h.post_id, h.score, p.post_title FROM {$t['history']} h LEFT JOIN {$wpdb->posts} p ON p.ID = h.post_id WHERE h.audit_id = %d AND h.scope = 'content' ORDER BY h.score ASC LIMIT 10", $audit_id ), ARRAY_A );
		$severity_counts = array(); $category_counts = array();
		foreach ( $severities as $key => $row ) { $severity_counts[ $key ] = (int) $row->total; }
		foreach ( $categories as $key => $row ) { $category_counts[ $key ] = (int) $row->total; }
		return array( 'audit' => $audit, 'metrics' => json_decode( (string) $audit->summary_metrics, true ) ?: array(), 'severity_counts' => $severity_counts, 'category_counts' => $category_counts, 'worst_content' => $worst );
	}

	public function audit_results( int $audit_id, int $page = 1, int $per_page = 20 ): array {
		global $wpdb; $t = self::tables(); $page = max( 1, $page ); $per_page = max( 1, min( 100, $per_page ) ); $offset = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin tables (see class docblock); values are placeholdered via prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT r.*, p.post_title, p.post_type, i.id issue_id, i.issue_status FROM {$t['results']} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.post_id LEFT JOIN {$t['issues']} i ON i.fingerprint = r.fingerprint WHERE r.audit_id = %d AND r.result_status = 'fail' ORDER BY r.penalty DESC, r.id DESC LIMIT %d OFFSET %d", $audit_id, $per_page, $offset ), ARRAY_A );
		foreach ( $rows as &$row ) { $row['evidence'] = json_decode( (string) $row['evidence'], true ) ?: array(); $row['remediation'] = json_decode( (string) $row['remediation'], true ) ?: array(); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['results']} WHERE audit_id = %d AND result_status = 'fail'", $audit_id ) );
		return array( 'items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page );
	}

	public function cleanup( int $detail_days, int $summary_months ): void {
		global $wpdb; $t = self::tables();
		$detail_days = max( 7, min( 365, $detail_days ) ); $summary_months = max( 1, min( 36, $summary_months ) );
		$detail_before = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $detail_days );
		$summary_before = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $summary_months . ' months' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['audits']} WHERE completed_at IS NOT NULL AND completed_at < %s", $detail_before ) );
		if ( $ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); write operation, nothing to cache; $placeholders is a fixed run of %d tokens matching count($ids), values placeholdered via prepare().
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['results']} WHERE audit_id IN ($placeholders)", ...$ids ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); write operation, nothing to cache; value is placeholdered via prepare().
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['history']} WHERE scope = 'content' AND created_at < %s", $detail_before ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); write operation, nothing to cache; value is placeholdered via prepare().
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['history']} WHERE scope = 'site' AND created_at < %s", $summary_before ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); write operation, nothing to cache; value is placeholdered via prepare().
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['audits']} WHERE completed_at IS NOT NULL AND completed_at < %s", $summary_before ) );
	}

	public function recent_audits( int $limit = 20 ): array {
		global $wpdb; $t = self::tables(); $limit = max( 1, min( 100, $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); value is placeholdered via prepare().
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['audits']} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public function overview(): array {
		global $wpdb; $t = self::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); no external input in this query.
		$latest = $wpdb->get_row( "SELECT * FROM {$t['audits']} WHERE status = 'completed' AND scope = 'site' ORDER BY id DESC LIMIT 1", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom plugin table (see class docblock); no external input in this query.
		$active = $wpdb->get_row( "SELECT * FROM {$t['audits']} WHERE status IN ('pending','running') ORDER BY id DESC LIMIT 1", ARRAY_A );
		$counts = $this->issue_counts( array( 'status' => array( 'open', 'reopened' ) ) );
		return array( 'latest' => $latest, 'active' => $active, 'issue_counts' => $counts['severities'], 'history' => $this->history( 10 ) );
	}
}
