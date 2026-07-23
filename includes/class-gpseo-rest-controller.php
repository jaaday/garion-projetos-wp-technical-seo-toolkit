<?php
/**
 * Protected administrative REST endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GP_SEO_REST_Controller {

	const NAMESPACE_ = 'garion-projetos-technical-seo-toolkit/v1';

	public function __construct() { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		register_rest_route( self::NAMESPACE_, '/broken-links/status', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_broken_links_status' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( self::NAMESPACE_, '/broken-links/scan', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'start_broken_links_scan' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( self::NAMESPACE_, '/audits', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'start_audit' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( self::NAMESPACE_, '/audits/history', array(
			'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_history' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'limit' => $this->bounded_int_arg( 20, 1, 100 ), 'post_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ) ),
		) );
		register_rest_route( self::NAMESPACE_, '/audits/(?P<id>[\d]+)', array(
			'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_audit' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'id' => $this->positive_id_arg() ),
		) );
		register_rest_route( self::NAMESPACE_, '/audits/(?P<id>[\d]+)/issues', array(
			'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_audit_issues' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'id' => $this->positive_id_arg(), 'page' => $this->bounded_int_arg( 1, 1, 100000 ), 'per_page' => $this->bounded_int_arg( 20, 1, 100 ) ),
		) );
		register_rest_route( self::NAMESPACE_, '/audits/(?P<id>[\d]+)/cancel', array(
			'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'cancel_audit' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'id' => $this->positive_id_arg() ),
		) );
		register_rest_route( self::NAMESPACE_, '/contents/(?P<id>[\d]+)/audit', array(
			'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'audit_content' ), 'permission_callback' => array( $this, 'check_content_permission' ),
			'args' => array( 'id' => $this->positive_id_arg() ),
		) );
		register_rest_route( self::NAMESPACE_, '/contents/(?P<id>[\d]+)/issues', array(
			'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_content_issues' ), 'permission_callback' => array( $this, 'check_content_permission' ),
			'args' => array( 'id' => $this->positive_id_arg(), 'page' => $this->bounded_int_arg( 1, 1, 100000 ), 'per_page' => $this->bounded_int_arg( 20, 1, 100 ) ),
		) );
		register_rest_route( self::NAMESPACE_, '/issues', array(
			'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_issues' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array(
				'page' => $this->bounded_int_arg( 1, 1, 100000 ), 'per_page' => $this->bounded_int_arg( 20, 1, 100 ),
				'audit_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ), 'post_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
				'search' => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'severity' => array( 'default' => '', 'sanitize_callback' => 'sanitize_key' ), 'category' => array( 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
				'status' => array( 'default' => '', 'sanitize_callback' => 'sanitize_key' ), 'post_type' => array( 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
				'order_by' => array( 'default' => 'date', 'sanitize_callback' => 'sanitize_key' ), 'order' => array( 'default' => 'DESC', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
		register_rest_route( self::NAMESPACE_, '/issues/(?P<id>[\d]+)', array(
			'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_issue' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'id' => $this->positive_id_arg() ),
		) );
		register_rest_route( self::NAMESPACE_, '/issues/(?P<id>[\d]+)/ignore', array(
			'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'ignore_issue' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'id' => $this->positive_id_arg(), 'reason' => array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) ),
		) );
		register_rest_route( self::NAMESPACE_, '/issues/(?P<id>[\d]+)/reopen', array(
			'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reopen_issue' ), 'permission_callback' => array( $this, 'check_permission' ),
			'args' => array( 'id' => $this->positive_id_arg() ),
		) );
	}

	private function positive_id_arg(): array {
		return array( 'sanitize_callback' => 'absint', 'validate_callback' => static fn( $value ) => (int) $value > 0 );
	}

	private function bounded_int_arg( int $default, int $minimum, int $maximum ): array {
		return array( 'default' => $default, 'sanitize_callback' => 'absint', 'validate_callback' => static fn( $value ) => (int) $value >= $minimum && (int) $value <= $maximum );
	}

	public function check_permission() { return current_user_can( 'manage_options' ); }

	public function check_content_permission( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		return current_user_can( 'manage_options' ) && $post_id && null !== get_post( $post_id );
	}

	public function get_broken_links_status() { return rest_ensure_response( ( new GP_SEO_Broken_Links() )->get_status() ); }
	public function start_broken_links_scan() { $scanner = new GP_SEO_Broken_Links(); $scanner->trigger_scan_now(); return rest_ensure_response( $scanner->get_status() ); }

	public function start_audit() {
		$result = $this->runner()->start();
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'audit_id' => $result, 'status' => 'pending' ), 202 );
	}

	public function audit_content( WP_REST_Request $request ) {
		$result = $this->runner()->start( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'audit_id' => $result, 'status' => 'pending' ), 202 );
	}

	public function get_audit( WP_REST_Request $request ) {
		$details = $this->repository()->audit_details( absint( $request['id'] ) );
		if ( ! $details ) { return $this->not_found( 'gpseo_audit_not_found', __( 'Audit not found.', 'garion-projetos-technical-seo-toolkit' ) ); }
		$response = $this->format_audit( $details['audit'] );
		$response['metrics'] = $details['metrics']; $response['severity_counts'] = $details['severity_counts'];
		$response['category_counts'] = $details['category_counts']; $response['worst_content'] = $details['worst_content'];
		return rest_ensure_response( $response );
	}

	public function get_audit_issues( WP_REST_Request $request ) {
		if ( ! $this->repository()->get( absint( $request['id'] ) ) ) { return $this->not_found( 'gpseo_audit_not_found', __( 'Audit not found.', 'garion-projetos-technical-seo-toolkit' ) ); }
		return rest_ensure_response( $this->repository()->audit_results( absint( $request['id'] ), absint( $request['page'] ), absint( $request['per_page'] ) ) );
	}

	public function get_content_issues( WP_REST_Request $request ) {
		$filters = array( 'post_id' => absint( $request['id'] ), 'status' => array( 'open', 'reopened', 'ignored' ), 'page' => absint( $request['page'] ), 'per_page' => absint( $request['per_page'] ) );
		return rest_ensure_response( array( 'items' => $this->repository()->list_issues( $filters ), 'total' => $this->repository()->issue_counts( $filters )['total'] ) );
	}

	public function get_issues( WP_REST_Request $request ) {
		$filters = array(
			'page' => absint( $request['page'] ), 'per_page' => absint( $request['per_page'] ), 'audit_id' => absint( $request['audit_id'] ),
			'post_id' => absint( $request['post_id'] ), 'search' => $request['search'], 'severity' => $request['severity'],
			'category' => $request['category'], 'status' => $request['status'] ? array( $request['status'] ) : array(),
			'post_type' => $request['post_type'], 'order_by' => $request['order_by'], 'order' => $request['order'],
		);
		return rest_ensure_response( array( 'items' => $this->repository()->list_issues( $filters ), 'total' => $this->repository()->issue_counts( $filters )['total'], 'page' => $filters['page'], 'per_page' => $filters['per_page'] ) );
	}

	public function get_issue( WP_REST_Request $request ) {
		$issue = $this->repository()->get_issue( absint( $request['id'] ) );
		return $issue ? rest_ensure_response( $issue ) : $this->not_found( 'gpseo_issue_not_found', __( 'Issue not found.', 'garion-projetos-technical-seo-toolkit' ) );
	}

	public function ignore_issue( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		if ( ! $this->repository()->get_issue( $id ) ) { return $this->not_found( 'gpseo_issue_not_found', __( 'Issue not found.', 'garion-projetos-technical-seo-toolkit' ) ); }
		$this->repository()->set_issue_status( $id, 'ignored', (string) $request['reason'] );
		return rest_ensure_response( $this->repository()->get_issue( $id ) );
	}

	public function reopen_issue( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		if ( ! $this->repository()->get_issue( $id ) ) { return $this->not_found( 'gpseo_issue_not_found', __( 'Issue not found.', 'garion-projetos-technical-seo-toolkit' ) ); }
		$this->repository()->set_issue_status( $id, 'open' );
		return rest_ensure_response( $this->repository()->get_issue( $id ) );
	}

	public function cancel_audit( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		if ( ! $this->repository()->get( $id ) ) { return $this->not_found( 'gpseo_audit_not_found', __( 'Audit not found.', 'garion-projetos-technical-seo-toolkit' ) ); }
		if ( ! $this->runner()->cancel( $id ) ) { return new WP_Error( 'gpseo_audit_not_cancellable', __( 'This audit can no longer be cancelled.', 'garion-projetos-technical-seo-toolkit' ), array( 'status' => 409 ) ); }
		return rest_ensure_response( array( 'audit_id' => $id, 'status' => 'cancelled' ) );
	}

	public function get_history( WP_REST_Request $request ) {
		return rest_ensure_response( $this->repository()->history( absint( $request['limit'] ), absint( $request['post_id'] ) ) );
	}

	private function repository(): GPSEO_Audit_Repository { return new GPSEO_Audit_Repository(); }

	private function runner(): GPSEO_Audit_Runner {
		if ( isset( $GLOBALS['gpseo_audit_runner'] ) && $GLOBALS['gpseo_audit_runner'] instanceof GPSEO_Audit_Runner ) { return $GLOBALS['gpseo_audit_runner']; }
		$GLOBALS['gpseo_audit_runner'] = new GPSEO_Audit_Runner();
		return $GLOBALS['gpseo_audit_runner'];
	}

	private function not_found( string $code, string $message ): WP_Error { return new WP_Error( $code, $message, array( 'status' => 404 ) ); }

	private function format_audit( object $audit ): array {
		$total = (int) $audit->total_items; $processed = (int) $audit->processed_items;
		$duration = $audit->started_at && $audit->completed_at ? max( 0, strtotime( $audit->completed_at ) - strtotime( $audit->started_at ) ) : null;
		return array(
			'id' => (int) $audit->id, 'status' => $audit->status, 'scope' => $audit->scope, 'post_id' => (int) $audit->post_id,
			'total_items' => $total, 'processed_items' => $processed,
			'progress' => $total > 0 ? min( 100, round( $processed / $total * 100, 2 ) ) : ( 'completed' === $audit->status ? 100 : 0 ),
			'score' => null === $audit->score ? null : (float) $audit->score,
			'category_scores' => json_decode( (string) $audit->category_scores, true ) ?: array(),
			'error_message' => $audit->error_message ? sanitize_text_field( $audit->error_message ) : null,
			'created_at' => $audit->created_at, 'started_at' => $audit->started_at, 'completed_at' => $audit->completed_at, 'duration_seconds' => $duration,
		);
	}
}
