<?php
/**
 * Resumable asynchronous audit execution.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GPSEO_Audit_Runner {

	public const CRON_HOOK = 'gpseo_process_audit_batch';
	public const CLEANUP_HOOK = 'gpseo_cleanup_audit_history';
	private const LOCK_TTL = 120;
	private GPSEO_Audit_Repository $repository;
	private GPSEO_Audit_Registry $registry;
	private GPSEO_Provider_Registry $providers;
	private GPSEO_Score_Calculator $scorer;

	public function __construct( ?GPSEO_Audit_Repository $repository = null, ?GPSEO_Audit_Registry $registry = null ) {
		$this->repository = $repository ?: new GPSEO_Audit_Repository();
		$this->registry = $registry ?: self::default_registry();
		$this->providers = new GPSEO_Provider_Registry();
		$this->scorer = new GPSEO_Score_Calculator();
		add_action( self::CRON_HOOK, array( $this, 'process_batch' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_history' ) );
		self::activate();
		add_action( 'init', array( $this, 'recover_stalled_audit' ), 100 );
	}

	public static function activate(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK ); }
	}

	public static function deactivate(): void {
		wp_unschedule_hook( self::CRON_HOOK );
		wp_unschedule_hook( self::CLEANUP_HOOK );
	}

	public function cleanup_history(): void {
		$this->repository->cleanup( (int) get_option( 'gpseo_audit_detail_retention_days', 90 ), (int) get_option( 'gpseo_audit_summary_retention_months', 12 ) );
	}
	public static function default_registry(): GPSEO_Audit_Registry {
		$registry = new GPSEO_Audit_Registry();
		$registry->register( new GPSEO_Check_Title() );
		$registry->register( new GPSEO_Check_Description() );
		$registry->register( new GPSEO_Check_Featured_Image() );
		$registry->register( new GPSEO_Check_Indexability() );
		return $registry;
	}

	public function start( int $post_id = 0 ) {
		$active = $this->repository->find_active();
		if ( $active ) {
			return new WP_Error( 'gpseo_audit_running', __( 'Another SEO audit is already pending or running.', 'garion-projetos-technical-seo-toolkit' ), array( 'status' => 409, 'audit_id' => (int) $active->id ) );
		}

		$post_types = $this->get_post_types();
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error( 'gpseo_invalid_post', __( 'The requested published content cannot be audited.', 'garion-projetos-technical-seo-toolkit' ), array( 'status' => 400 ) );
			}
			$post_types = array( $post->post_type );
			$total = 1;
			$scope = 'content';
		} else {
			$query = new WP_Query( array( 'post_type' => $post_types, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false ) );
			$total = (int) $query->found_posts;
			$scope = 'site';
		}

		$audit_id = $this->repository->create( $scope, $post_id, $post_types, $total );
		if ( ! $audit_id ) {
			return new WP_Error( 'gpseo_audit_create_failed', __( 'The audit could not be created.', 'garion-projetos-technical-seo-toolkit' ), array( 'status' => 500 ) );
		}

		do_action( 'gpseo_audit_started', $audit_id );
		$this->schedule( $audit_id, 1 );
		return $audit_id;
	}

	public function cancel( int $audit_id ): bool {
		$audit = $this->repository->get( $audit_id );
		if ( ! $audit || ! in_array( $audit->status, array( 'pending', 'running' ), true ) ) { return false; }
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $audit_id ) );
		return $this->repository->update( $audit_id, array( 'status' => 'cancelled', 'completed_at' => current_time( 'mysql', true ) ) );
	}

	public function process_batch( int $audit_id ): void {
		$token = $this->acquire_lock( $audit_id );
		if ( ! $token ) { $this->schedule( $audit_id, 15 ); return; }

		try {
			$audit = $this->repository->get( $audit_id );
			if ( ! $audit || ! in_array( $audit->status, array( 'pending', 'running' ), true ) ) { return; }
			$now = current_time( 'mysql', true );
			$this->repository->update( $audit_id, array( 'status' => 'running', 'started_at' => $audit->started_at ?: $now, 'heartbeat_at' => $now ) );
			$posts = $this->next_posts( $audit );
			if ( empty( $posts ) ) { $this->finish( $audit_id ); return; }

			$processed = (int) $audit->processed_items;
			foreach ( $posts as $post ) {
				$results = $this->run_checks( $post, $audit_id );
				$score = $this->scorer->calculate( $results );
				$this->repository->save_post_results( $audit_id, $post, $results, $score );
				$processed++;
				$this->repository->update( $audit_id, array( 'processed_items' => $processed, 'cursor_post_id' => (int) $post->ID, 'heartbeat_at' => current_time( 'mysql', true ) ) );
			}

			if ( $processed >= (int) $audit->total_items ) { $this->finish( $audit_id ); } else { $this->schedule( $audit_id, 2 ); }
		} catch ( Throwable $error ) {
			$this->repository->update( $audit_id, array( 'status' => 'failed', 'error_message' => sanitize_text_field( $error->getMessage() ), 'completed_at' => current_time( 'mysql', true ) ) );
			do_action( 'gpseo_audit_failed', $audit_id, $error );
		} finally {
			$this->release_lock( $audit_id, $token );
		}
	}

	private function run_checks( WP_Post $post, int $audit_id ): array {
		$context = new GPSEO_Audit_Context( $post, $this->providers, array( 'audit_id' => $audit_id ) );
		$results = array();
		foreach ( $this->registry->all() as $check ) {
			try {
				$result = $check->run( $context );
				$result = apply_filters( 'gpseo_audit_result', $result, $context );
				if ( $result instanceof GPSEO_Audit_Result ) { $results[] = $result; }
			} catch ( Throwable $error ) {
				$results[] = new GPSEO_Audit_Result( $check->get_id(), $check->get_category(), 'informational', GPSEO_Audit_Result::INCONCLUSIVE, 0.0, __( 'The check could not be completed.', 'garion-projetos-technical-seo-toolkit' ), '', '', array( 'error' => sanitize_text_field( $error->getMessage() ) ) );
			}
		}
		return $results;
	}

	private function next_posts( object $audit ): array {
		if ( 'content' === $audit->scope && $audit->post_id ) {
			$post = get_post( (int) $audit->post_id );
			return $post instanceof WP_Post && 0 === (int) $audit->processed_items ? array( $post ) : array();
		}
		global $wpdb;
		$post_types = json_decode( (string) $audit->post_types, true );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', is_array( $post_types ) ? $post_types : array( 'post', 'page' ) ) ) );
		if ( ! $post_types ) { return array(); }
		$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$params = array_merge( array( 'publish' ), $post_types, array( (int) $audit->cursor_post_id, $this->batch_size() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $type_placeholders is a fixed run of %s (one per post type) and $params is unpacked with the matching count via ...$params; content must be read fresh on every batch, not cached.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ($type_placeholders) AND ID > %d ORDER BY ID ASC LIMIT %d", ...$params ) );
		return array_values( array_filter( array_map( 'get_post', $ids ) ) );
	}

	private function finish( int $audit_id ): void {
		$summary = $this->repository->complete( $audit_id );
		do_action( 'gpseo_audit_completed', $audit_id, $summary );
	}

	private function schedule( int $audit_id, int $delay ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $audit_id ) ) ) { wp_schedule_single_event( time() + max( 1, $delay ), self::CRON_HOOK, array( $audit_id ) ); }
	}

	public function recover_stalled_audit(): void {
		$audit = $this->repository->find_active();
		if ( ! $audit ) { return; }
		$heartbeat = $audit->heartbeat_at ? strtotime( $audit->heartbeat_at . ' UTC' ) : 0;
		if ( 'pending' === $audit->status || $heartbeat < time() - 300 ) { $this->schedule( (int) $audit->id, 1 ); }
	}

	private function acquire_lock( int $audit_id ): ?string {
		$key = 'gpseo_audit_lock_' . $audit_id; $token = wp_generate_uuid4(); $now = time();
		$value = get_option( $key );
		if ( is_array( $value ) && (int) ( $value['expires'] ?? 0 ) < $now ) { delete_option( $key ); }
		return add_option( $key, array( 'token' => $token, 'expires' => $now + self::LOCK_TTL ), '', false ) ? $token : null;
	}

	private function release_lock( int $audit_id, string $token ): void {
		$key = 'gpseo_audit_lock_' . $audit_id; $value = get_option( $key );
		if ( is_array( $value ) && hash_equals( (string) ( $value['token'] ?? '' ), $token ) ) { delete_option( $key ); }
	}

	private function batch_size(): int { return max( 1, min( 50, (int) get_option( 'gpseo_audit_batch_size', 10 ) ) ); }

	private function get_post_types(): array {
		$configured = get_option( 'gpseo_audit_post_types', array( 'post', 'page' ) );
		$public = get_post_types( array( 'public' => true ), 'names' );
		$types = array_values( array_intersect( array_map( 'sanitize_key', (array) $configured ), $public ) );
		return $types ? $types : array_values( array_intersect( array( 'post', 'page' ), $public ) );
	}
}
