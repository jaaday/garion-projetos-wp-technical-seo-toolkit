<?php
/**
 * Admin screen: tabbed UI for Overview, Audits, Issues, Redirects, 404 Monitor,
 * Broken Links, Contents and Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Admin_Page {

	const MENU_SLUG  = 'gpseo-toolkit';
	const PER_PAGE    = 20;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
		add_action( 'admin_post_gpseo_add_redirect', array( $this, 'handle_add_redirect' ) );
		add_action( 'admin_post_gpseo_delete_redirect', array( $this, 'handle_delete_redirect' ) );
		add_action( 'admin_post_gpseo_delete_404', array( $this, 'handle_delete_404' ) );
		add_action( 'admin_post_gpseo_export_redirects', array( $this, 'handle_export_redirects' ) );
		add_action( 'admin_post_gpseo_import_redirects', array( $this, 'handle_import_redirects' ) );
		add_action( 'admin_post_gpseo_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_gpseo_export_audit', array( $this, 'handle_export_audit' ) );
		add_action( 'admin_post_gpseo_ignore_404', array( $this, 'handle_ignore_404' ) );
		add_action( 'admin_post_gpseo_ignore_broken_link', array( $this, 'handle_ignore_broken_link' ) );
		add_action( 'admin_post_gpseo_rescan_broken_link', array( $this, 'handle_rescan_broken_link' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Garion Projetos - Technical SEO Toolkit', 'garion-projetos-technical-seo-toolkit' ),
			__( 'Technical SEO', 'garion-projetos-technical-seo-toolkit' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-search',
			80
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'gpseo-admin', GPSEO_URL . 'assets/css/admin.css', array( 'common' ), GPSEO_VERSION );
		wp_enqueue_script( 'gpseo-admin', GPSEO_URL . 'assets/js/admin.js', array( 'wp-api-fetch' ), GPSEO_VERSION, true );

		wp_localize_script(
			'gpseo-admin',
			'gpseoData',
			array(
				'restNamespace' => GP_SEO_REST_Controller::NAMESPACE_,
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'          => array(
					'scanning'      => __( 'Scanning... this page will refresh automatically when it finishes.', 'garion-projetos-technical-seo-toolkit' ),
					'done'          => __( 'Scan finished. Refreshing...', 'garion-projetos-technical-seo-toolkit' ),
					'auditRunning'  => __( 'SEO audit is running...', 'garion-projetos-technical-seo-toolkit' ),
					'auditDone'     => __( 'Audit completed.', 'garion-projetos-technical-seo-toolkit' ),
					'auditFailed'   => __( 'The audit could not be completed.', 'garion-projetos-technical-seo-toolkit' ),
					'ignoreReason'  => __( 'Ignore this issue', 'garion-projetos-technical-seo-toolkit' ),
					'ignoreReasonHelp' => __( 'Optional: add a reason for ignoring this issue.', 'garion-projetos-technical-seo-toolkit' ),
					'confirmTitle'  => __( 'Please confirm', 'garion-projetos-technical-seo-toolkit' ),
					'confirm'       => __( 'Confirm', 'garion-projetos-technical-seo-toolkit' ),
					'cancel'        => __( 'Cancel', 'garion-projetos-technical-seo-toolkit' ),
				),
			)
		);
	}

	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.

		return in_array( $tab, array_keys( $this->tabs() ), true ) ? $tab : 'overview';
	}

	private function get_search() {
		return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search/filter input.
	}

	private function get_paged() {
		return isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination input.
	}

	public function show_notice() {
		if ( empty( $_GET['gpseo_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice trigger.
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sanitize_text_field( wp_unslash( $_GET['gpseo_notice'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	private function tabs() {
		return array(
			'overview'     => __( 'Overview', 'garion-projetos-technical-seo-toolkit' ),
			'audits'       => __( 'Audits', 'garion-projetos-technical-seo-toolkit' ),
			'issues'       => __( 'Issues', 'garion-projetos-technical-seo-toolkit' ),
			'redirects'    => __( 'Redirects', 'garion-projetos-technical-seo-toolkit' ),
			'404-monitor'  => __( '404 Monitor', 'garion-projetos-technical-seo-toolkit' ),
			'broken-links' => __( 'Broken Links', 'garion-projetos-technical-seo-toolkit' ),
			'audit'        => __( 'Contents', 'garion-projetos-technical-seo-toolkit' ),
			'settings'     => __( 'Settings', 'garion-projetos-technical-seo-toolkit' ),
		);
	}

	private function tab_icons() {
		return array(
			'overview'     => 'dashicons-dashboard',
			'audits'       => 'dashicons-backup',
			'issues'       => 'dashicons-flag',
			'redirects'    => 'dashicons-randomize',
			'404-monitor'  => 'dashicons-warning',
			'broken-links' => 'dashicons-editor-unlink',
			'audit'        => 'dashicons-analytics',
			'settings'     => 'dashicons-admin-generic',
		);
	}

	/**
	 * Breadcrumb trail for the current screen, including any drill-down detail view.
	 */
	private function render_breadcrumbs( $tab ) {
		$base = admin_url( 'admin.php' );
		$tabs = $this->tabs();
		$items = array(
			array(
				'label' => __( 'Technical SEO', 'garion-projetos-technical-seo-toolkit' ),
				'url'   => add_query_arg( array( 'page' => self::MENU_SLUG ), $base ),
			),
			array(
				'label' => $tabs[ $tab ],
				'url'   => add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab ), $base ),
			),
		);

		$view      = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$audit_id  = isset( $_GET['audit_id'] ) ? absint( $_GET['audit_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$issue_id  = isset( $_GET['issue_id'] ) ? absint( $_GET['issue_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'audits' === $tab && $audit_id && in_array( $view, array( 'detail', 'compare' ), true ) ) {
			/* translators: %d: audit ID number. */
			$items[] = array( 'label' => sprintf( __( 'Audit #%d', 'garion-projetos-technical-seo-toolkit' ), $audit_id ) );
		} elseif ( 'issues' === $tab && $issue_id ) {
			$items[] = array( 'label' => __( 'Issue detail', 'garion-projetos-technical-seo-toolkit' ) );
		} elseif ( 'audit' === $tab && $post_id ) {
			$items[] = array( 'label' => __( 'Content detail', 'garion-projetos-technical-seo-toolkit' ) );
		}

		GP_SEO_Admin_UI::breadcrumbs( $items );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap gpseo-wrap">
			<?php GP_SEO_Admin_UI::header( __( 'Garion Projetos - Technical SEO Toolkit', 'garion-projetos-technical-seo-toolkit' ), GPSEO_VERSION, __( 'Redirects, 404 monitoring, broken-link detection, content auditing and technical metadata — all in one place.', 'garion-projetos-technical-seo-toolkit' ) ); ?>

			<?php $this->render_breadcrumbs( $tab ); ?>

			<?php if ( GP_SEO_Rank_Math_Compatibility::is_active() ) : ?>
				<?php GP_SEO_Admin_UI::alert( __( '<strong>Rank Math detected:</strong> duplicate sitemap, Schema, canonical and social output is disabled. Toolkit overrides are passed to Rank Math through its official filters.', 'garion-projetos-technical-seo-toolkit' ), 'info' ); ?>
			<?php endif; ?>

			<?php GP_SEO_Admin_UI::tabs( $this->tabs(), $tab, add_query_arg( array( 'page' => self::MENU_SLUG ), admin_url( 'admin.php' ) ), $this->tab_icons() ); ?>

			<div class="gpseo-panel">
				<?php
				switch ( $tab ) {
					case 'overview':
						$this->render_overview();
						break;
					case 'audits':
						$this->render_audits();
						break;
					case 'issues':
						$this->render_issues();
						break;
					case 'redirects':
						$this->render_redirects();
						break;
					case '404-monitor':
						$this->render_404_monitor();
						break;
					case 'broken-links':
						$this->render_broken_links();
						break;
					case 'audit':
						$this->render_audit();
						break;
					case 'settings':
						$this->render_settings();
						break;
					default:
						$this->render_overview();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_search_box( $tab, $placeholder = '' ) {
		$value = $this->get_search();
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gpseo-search-box">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
			<label class="screen-reader-text" for="gpseo-search-<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $placeholder ? $placeholder : __( 'Search', 'garion-projetos-technical-seo-toolkit' ) ); ?></label>
			<span class="dashicons dashicons-search gpseo-search-box__icon" aria-hidden="true"></span>
			<input type="search" id="gpseo-search-<?php echo esc_attr( $tab ); ?>" name="s" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ? $placeholder : __( 'Search', 'garion-projetos-technical-seo-toolkit' ) ); ?>" />
			<?php submit_button( __( 'Search', 'garion-projetos-technical-seo-toolkit' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}

	private function render_pagination( $tab, $current_page, $total_items, $per_page ) {
		$total_pages = (int) ceil( $total_items / $per_page );

		if ( $total_pages <= 1 ) {
			return;
		}

		$search = $this->get_search();
		echo '<div class="tablenav gpseo-pagination"><div class="tablenav-pages" aria-label="' . esc_attr__( 'Pagination', 'garion-projetos-technical-seo-toolkit' ) . '">';

		for ( $page = 1; $page <= $total_pages; $page++ ) {
			$args = array(
				'page'  => self::MENU_SLUG,
				'tab'   => $tab,
				'paged' => $page,
			);

			if ( $search ) {
				$args['s'] = $search;
			}

			printf(
				'<a class="page-numbers%s" %s href="%s">%d</a> ',
				$page === $current_page ? ' current' : '',
				$page === $current_page ? 'aria-current="page"' : '',
				esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ),
				(int) $page
			);
		}

		echo '</div></div>';
	}

	private function render_overview() {
		$data    = ( new GPSEO_Audit_Repository() )->overview();
		$latest  = $data['latest'];
		$active  = $data['active'];
		$counts  = $data['issue_counts'];
		$history = $data['history'];

		$score           = $latest ? round( (float) $latest['score'] ) : null;
		$pages_analyzed  = $latest ? (int) $latest['processed_items'] : 0;
		$critical        = (int) ( $counts['critical'] ?? 0 );
		$warnings        = (int) ( $counts['high'] ?? 0 ) + (int) ( $counts['medium'] ?? 0 );
		$recommendations = (int) ( $counts['low'] ?? 0 ) + (int) ( $counts['recommendation'] ?? 0 ) + (int) ( $counts['informational'] ?? 0 );
		$resolved        = $history ? (int) ( $history[0]['metrics']['resolved_problems'] ?? 0 ) : 0;
		$last_audit_text = $latest ? $latest['completed_at'] : __( 'Not run yet', 'garion-projetos-technical-seo-toolkit' );

		GP_SEO_Admin_UI::card_start( __( 'SEO health', 'garion-projetos-technical-seo-toolkit' ), sprintf( /* translators: %s: date of the last full audit. */ __( 'Last full audit: %s', 'garion-projetos-technical-seo-toolkit' ), $last_audit_text ) );
		?>
		<div class="gpseo-quick-actions">
			<?php if ( $active ) : ?>
				<p id="gpseo-audit-message" class="gpseo-quick-actions__status" aria-live="polite"><?php esc_html_e( 'SEO audit is running...', 'garion-projetos-technical-seo-toolkit' ); ?></p>
				<div id="gpseo-audit-progress" class="gpseo-audit-progress" data-audit-id="<?php echo esc_attr( $active['id'] ); ?>">
					<progress max="100" value="<?php echo esc_attr( $active['total_items'] ? round( $active['processed_items'] / $active['total_items'] * 100 ) : 0 ); ?>"></progress>
					<span class="gpseo-audit-progress-label"></span>
					<button type="button" class="button button-link-delete" id="gpseo-cancel-audit"><?php esc_html_e( 'Cancel audit', 'garion-projetos-technical-seo-toolkit' ); ?></button>
				</div>
			<?php else : ?>
				<button type="button" class="button button-primary" id="gpseo-start-audit"><?php echo GP_SEO_Admin_UI::icon( 'dashicons-controls-play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts. ?><?php esc_html_e( 'Run full audit', 'garion-projetos-technical-seo-toolkit' ); ?></button>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audits' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View audit reports', 'garion-projetos-technical-seo-toolkit' ); ?></a>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View issues', 'garion-projetos-technical-seo-toolkit' ); ?></a>
				<span id="gpseo-audit-message" class="gpseo-quick-actions__status" aria-live="polite"></span>
				<div id="gpseo-audit-progress" class="gpseo-audit-progress" data-audit-id="0" hidden><progress max="100" value="0"></progress><span class="gpseo-audit-progress-label"></span></div>
			<?php endif; ?>
		</div>

		<div class="gpseo-metrics">
			<?php
			GP_SEO_Admin_UI::metric_card( null === $score ? '—' : $score, __( 'Overall SEO score', 'garion-projetos-technical-seo-toolkit' ), 'primary', 'dashicons-chart-line' );
			GP_SEO_Admin_UI::metric_card( $critical, __( 'Critical errors', 'garion-projetos-technical-seo-toolkit' ), 'critical', 'dashicons-dismiss' );
			GP_SEO_Admin_UI::metric_card( $warnings, __( 'Warnings', 'garion-projetos-technical-seo-toolkit' ), 'warning', 'dashicons-warning' );
			GP_SEO_Admin_UI::metric_card( $recommendations, __( 'Recommendations', 'garion-projetos-technical-seo-toolkit' ), 'info', 'dashicons-lightbulb' );
			GP_SEO_Admin_UI::metric_card( $pages_analyzed, __( 'Pages analyzed', 'garion-projetos-technical-seo-toolkit' ), 'neutral', 'dashicons-media-document' );
			GP_SEO_Admin_UI::metric_card( $resolved, __( 'Issues resolved (last audit)', 'garion-projetos-technical-seo-toolkit' ), 'success', 'dashicons-yes-alt' );
			?>
		</div>
		<?php
		GP_SEO_Admin_UI::card_end();

		GP_SEO_Admin_UI::card_start( __( 'Recent score history', 'garion-projetos-technical-seo-toolkit' ) );
		GP_SEO_Admin_UI::table_start(
			array(
				__( 'Date', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Score', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Change', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Content', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Issues', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Critical', 'garion-projetos-technical-seo-toolkit' ),
				__( 'New', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Resolved', 'garion-projetos-technical-seo-toolkit' ),
				'',
			)
		);
		if ( ! $history ) {
			GP_SEO_Admin_UI::empty_row( 9, __( 'No completed full audits yet.', 'garion-projetos-technical-seo-toolkit' ), __( 'Run your first full audit to start tracking SEO score history.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $history as $index => $row ) :
			$metrics  = $row['metrics'];
			$previous = $history[ $index + 1 ]['score'] ?? null;
			$change   = null === $previous ? null : (float) $row['score'] - (float) $previous;
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Date', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row['created_at'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Score', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( round( (float) $row['score'], 1 ) ); ?>/100</td>
				<td data-label="<?php esc_attr_e( 'Change', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo null === $change ? '&#8212;' : esc_html( ( $change > 0 ? '+' : '' ) . round( $change, 1 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Content', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( (int) ( $metrics['content_items'] ?? 0 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Issues', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( (int) ( $metrics['problems'] ?? 0 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Critical', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( (int) ( $metrics['critical'] ?? 0 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'New', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( (int) ( $metrics['new_problems'] ?? 0 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Resolved', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( (int) ( $metrics['resolved_problems'] ?? 0 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>"><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audits', 'view' => 'detail', 'audit_id' => $row['audit_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open', 'garion-projetos-technical-seo-toolkit' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		GP_SEO_Admin_UI::card_end();
	}

	private function render_audits() {
		$audit_id = isset( $_GET['audit_id'] ) ? absint( $_GET['audit_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $audit_id && in_array( $view, array( 'detail', 'compare' ), true ) ) {
			$this->render_audit_detail( $audit_id, 'compare' === $view );
			return;
		}

		$audits = ( new GPSEO_Audit_Repository() )->recent_audits( 50 );

		GP_SEO_Admin_UI::card_start( __( 'Audit executions', 'garion-projetos-technical-seo-toolkit' ), __( 'Every full-site and single-content audit run, with progress, score and export options.', 'garion-projetos-technical-seo-toolkit' ) );
		GP_SEO_Admin_UI::table_start(
			array(
				'ID',
				__( 'Status', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Scope', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Progress', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Score', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Created', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Actions', 'garion-projetos-technical-seo-toolkit' ),
			)
		);
		if ( ! $audits ) {
			GP_SEO_Admin_UI::empty_row( 7, __( 'No audits have been run.', 'garion-projetos-technical-seo-toolkit' ), __( 'Start one from the Overview tab to see it listed here.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $audits as $audit ) :
			$percent    = $audit['total_items'] ? round( $audit['processed_items'] / $audit['total_items'] * 100 ) : 0;
			$detail_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audits', 'view' => 'detail', 'audit_id' => $audit['id'] ), admin_url( 'admin.php' ) );
			?>
			<tr>
				<td data-label="ID"><a href="<?php echo esc_url( $detail_url ); ?>">#<?php echo esc_html( $audit['id'] ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Status', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo GP_SEO_Admin_UI::status_badge( $audit['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td data-label="<?php esc_attr_e( 'Scope', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $audit['scope'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Progress', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $audit['processed_items'] . '/' . $audit['total_items'] . ' (' . $percent . '%)' ); ?></td>
				<td data-label="<?php esc_attr_e( 'Score', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo null !== $audit['score'] ? esc_html( round( (float) $audit['score'], 1 ) . '/100' ) : '&#8212;'; ?></td>
				<td data-label="<?php esc_attr_e( 'Created', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $audit['created_at'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>" class="gpseo-row-actions">
					<a href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Details', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'audit_id' => $audit['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Issues', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audits', 'view' => 'compare', 'audit_id' => $audit['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Compare', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<?php foreach ( array( 'json' => 'JSON', 'csv' => 'CSV' ) as $format => $label ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_export_audit&audit_id=' . $audit['id'] . '&format=' . $format ), 'gpseo_export_audit_' . $audit['id'] ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
					<button type="button" class="button-link gpseo-rerun-audit" data-post-id="<?php echo esc_attr( $audit['post_id'] ); ?>"><?php esc_html_e( 'Run again', 'garion-projetos-technical-seo-toolkit' ); ?></button>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		GP_SEO_Admin_UI::card_end();
	}

	private function render_redirects() {
		$search    = $this->get_search();
		$paged     = $this->get_paged();
		$manager   = new GP_SEO_Redirects();
		$redirects = $manager->get_all( $search, self::PER_PAGE, $paged );
		$total     = $manager->count_all( $search );

		$prefill_source      = isset( $_GET['prefill_source'] ) ? sanitize_text_field( wp_unslash( $_GET['prefill_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill from the 404 monitor tab.
		$prefill_destination = isset( $_GET['prefill_destination'] ) ? esc_url_raw( wp_unslash( $_GET['prefill_destination'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$prefill_type        = isset( $_GET['prefill_type'] ) ? absint( $_GET['prefill_type'] ) : 301; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		GP_SEO_Admin_UI::card_start( __( 'Add redirect', 'garion-projetos-technical-seo-toolkit' ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gpseo_add_redirect' ); ?>
			<input type="hidden" name="action" value="gpseo_add_redirect" />
			<table class="form-table">
				<tr>
					<th><label for="source_path"><?php esc_html_e( 'Source path', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="text" id="source_path" name="source_path" class="regular-text" value="<?php echo esc_attr( $prefill_source ); ?>" placeholder="/old-page" required /></td>
				</tr>
				<tr>
					<th><label for="destination_url"><?php esc_html_e( 'Destination URL', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="text" id="destination_url" name="destination_url" class="regular-text" value="<?php echo esc_attr( $prefill_destination ); ?>" placeholder="https://example.com/new-page" required /></td>
				</tr>
				<tr>
					<th><label for="redirect_type"><?php esc_html_e( 'Type', 'garion-projetos-technical-seo-toolkit' ); ?> <?php echo GP_SEO_Admin_UI::tooltip( __( '301/308 are permanent; 302/307 are temporary. 307/308 preserve the HTTP method.', 'garion-projetos-technical-seo-toolkit' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
					<td>
						<select id="redirect_type" name="redirect_type">
							<option value="301" <?php selected( $prefill_type, 301 ); ?>>301 (<?php esc_html_e( 'Permanent', 'garion-projetos-technical-seo-toolkit' ); ?>)</option>
							<option value="302" <?php selected( $prefill_type, 302 ); ?>>302 (<?php esc_html_e( 'Temporary', 'garion-projetos-technical-seo-toolkit' ); ?>)</option>
							<option value="307" <?php selected( $prefill_type, 307 ); ?>>307 (<?php esc_html_e( 'Temporary, preserve method', 'garion-projetos-technical-seo-toolkit' ); ?>)</option>
							<option value="308" <?php selected( $prefill_type, 308 ); ?>>308 (<?php esc_html_e( 'Permanent, preserve method', 'garion-projetos-technical-seo-toolkit' ); ?>)</option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add redirect', 'garion-projetos-technical-seo-toolkit' ) ); ?>
		</form>
		<?php
		GP_SEO_Admin_UI::card_end();

		GP_SEO_Admin_UI::card_start( __( 'Import / export', 'garion-projetos-technical-seo-toolkit' ) );
		?>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_export_redirects' ), 'gpseo_export_redirects' ) ); ?>"><?php esc_html_e( 'Export CSV', 'garion-projetos-technical-seo-toolkit' ); ?></a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'gpseo_import_redirects' ); ?>
			<input type="hidden" name="action" value="gpseo_import_redirects" />
			<label class="screen-reader-text" for="gpseo_import_file"><?php esc_html_e( 'CSV file to import', 'garion-projetos-technical-seo-toolkit' ); ?></label>
			<input type="file" id="gpseo_import_file" name="import_file" accept=".csv" required />
			<?php submit_button( __( 'Import CSV', 'garion-projetos-technical-seo-toolkit' ), 'secondary', 'submit', false ); ?>
			<p class="description"><?php esc_html_e( 'Columns: source_path, destination_url, redirect_type', 'garion-projetos-technical-seo-toolkit' ); ?></p>
		</form>
		<?php
		GP_SEO_Admin_UI::card_end();

		GP_SEO_Admin_UI::card_start( __( 'Existing redirects', 'garion-projetos-technical-seo-toolkit' ) );
		$this->render_search_box( 'redirects', __( 'Search redirects...', 'garion-projetos-technical-seo-toolkit' ) );
		GP_SEO_Admin_UI::table_start(
			array(
				__( 'Source', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Destination', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Type', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Hits', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Last access', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Actions', 'garion-projetos-technical-seo-toolkit' ),
			)
		);
		if ( empty( $redirects ) ) {
			GP_SEO_Admin_UI::empty_row( 6, __( 'No redirects found.', 'garion-projetos-technical-seo-toolkit' ), __( 'Add your first redirect above, or create one straight from the 404 Monitor tab.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $redirects as $redirect ) :
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Source', 'garion-projetos-technical-seo-toolkit' ); ?>"><code><?php echo esc_html( $redirect->source_path ); ?></code></td>
				<td data-label="<?php esc_attr_e( 'Destination', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $redirect->destination_url ); ?></td>
				<td data-label="<?php esc_attr_e( 'Type', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $redirect->redirect_type ); ?></td>
				<td data-label="<?php esc_attr_e( 'Hits', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $redirect->hits ); ?></td>
				<td data-label="<?php esc_attr_e( 'Last access', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo $redirect->last_accessed_at ? esc_html( $redirect->last_accessed_at ) : '&#8212;'; ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>" class="gpseo-row-actions">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects', 'prefill_source' => $redirect->source_path, 'prefill_destination' => $redirect->destination_url, 'prefill_type' => $redirect->redirect_type ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a href="<?php echo esc_url( home_url( $redirect->source_path ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Test', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a class="gpseo-link-destructive" data-gpseo-confirm="<?php echo esc_attr__( 'Delete this redirect? This cannot be undone.', 'garion-projetos-technical-seo-toolkit' ); ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_delete_redirect&id=' . $redirect->id ), 'gpseo_delete_redirect_' . $redirect->id ) ); ?>"><?php esc_html_e( 'Delete', 'garion-projetos-technical-seo-toolkit' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		$this->render_pagination( 'redirects', $paged, $total, self::PER_PAGE );
		GP_SEO_Admin_UI::card_end();
	}

	private function render_404_monitor() {
		$search  = $this->get_search();
		$paged   = $this->get_paged();
		$monitor = new GP_SEO_404_Monitor();
		$results = $monitor->get_results( $search, self::PER_PAGE, $paged );
		$total   = $monitor->count_results( $search );

		GP_SEO_Admin_UI::card_start( __( '404 Monitor', 'garion-projetos-technical-seo-toolkit' ), __( 'Real "page not found" hits from visitors. Turn any of these into a redirect with one click.', 'garion-projetos-technical-seo-toolkit' ) );

		if ( GP_SEO_Rank_Math_Compatibility::is_module_active( '404-monitor' ) ) {
			GP_SEO_Admin_UI::alert( __( 'Rank Math 404 Monitor is active, so new hits are recorded there to avoid duplicate logging. Existing toolkit records remain available below.', 'garion-projetos-technical-seo-toolkit' ), 'info' );
		}

		$this->render_search_box( '404-monitor', __( 'Search 404s...', 'garion-projetos-technical-seo-toolkit' ) );
		GP_SEO_Admin_UI::table_start(
			array(
				__( 'URL', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Hits', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Referrer', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Last seen', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Actions', 'garion-projetos-technical-seo-toolkit' ),
			)
		);
		if ( empty( $results ) ) {
			GP_SEO_Admin_UI::empty_row( 5, __( 'No 404s logged yet.', 'garion-projetos-technical-seo-toolkit' ), __( 'Hits will appear here as visitors reach missing pages on your site.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $results as $row ) :
			?>
			<tr>
				<td data-label="URL"><code><?php echo esc_html( $row->url ); ?></code></td>
				<td data-label="<?php esc_attr_e( 'Hits', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row->hit_count ); ?></td>
				<td data-label="<?php esc_attr_e( 'Referrer', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo $row->referrer ? esc_html( $row->referrer ) : '&#8212;'; ?></td>
				<td data-label="<?php esc_attr_e( 'Last seen', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row->last_seen ); ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>" class="gpseo-row-actions">
					<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects', 'prefill_source' => $row->url ), admin_url( 'admin.php' ) ) ); ?>">
						<?php esc_html_e( 'Create redirect', 'garion-projetos-technical-seo-toolkit' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_ignore_404&id=' . $row->id . '&ignored=' . ( empty( $row->is_ignored ) ? 1 : 0 ) ), 'gpseo_ignore_404_' . $row->id ) ); ?>">
						<?php echo empty( $row->is_ignored ) ? esc_html__( 'Ignore', 'garion-projetos-technical-seo-toolkit' ) : esc_html__( 'Reopen', 'garion-projetos-technical-seo-toolkit' ); ?>
					</a>
					<a class="gpseo-link-destructive" data-gpseo-confirm="<?php echo esc_attr__( 'Delete this 404 record?', 'garion-projetos-technical-seo-toolkit' ); ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_delete_404&id=' . $row->id ), 'gpseo_delete_404_' . $row->id ) ); ?>">
						<?php esc_html_e( 'Delete', 'garion-projetos-technical-seo-toolkit' ); ?>
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		$this->render_pagination( '404-monitor', $paged, $total, self::PER_PAGE );
		GP_SEO_Admin_UI::card_end();
	}

	private function render_broken_links() {
		$search       = $this->get_search();
		$paged        = $this->get_paged();
		$broken_links = new GP_SEO_Broken_Links();
		$results      = $broken_links->get_results( $search, self::PER_PAGE, $paged );
		$total        = $broken_links->count_all( $search );
		$status       = $broken_links->get_status();

		GP_SEO_Admin_UI::card_start( __( 'Broken Links', 'garion-projetos-technical-seo-toolkit' ) );
		?>
		<p class="gpseo-quick-actions">
			<button type="button" class="button button-primary" id="gpseo-scan-now" data-scanning="<?php echo esc_attr( 'running' === $status['status'] ? '1' : '0' ); ?>">
				<?php esc_html_e( 'Scan for broken links now', 'garion-projetos-technical-seo-toolkit' ); ?>
			</button>
			<span id="gpseo-scan-message" class="gpseo-quick-actions__status"></span>
		</p>
		<p class="description">
			<?php
			if ( $status['last_run'] ) {
				printf(
					/* translators: %s: date/time of the last completed scan. */
					esc_html__( 'Last full scan completed: %s. A full scan starts automatically once per day.', 'garion-projetos-technical-seo-toolkit' ),
					esc_html( $status['last_run'] )
				);
			} else {
				esc_html_e( 'No scan has completed yet. A full scan starts automatically once per day, or click the button above.', 'garion-projetos-technical-seo-toolkit' );
			}
			?>
		</p>

		<?php $this->render_search_box( 'broken-links', __( 'Search broken links...', 'garion-projetos-technical-seo-toolkit' ) ); ?>

		<?php
		GP_SEO_Admin_UI::table_start(
			array(
				__( 'Post', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Broken URL', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Final URL', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Anchor text', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Status', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Last checked', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Actions', 'garion-projetos-technical-seo-toolkit' ),
			)
		);
		if ( empty( $results ) ) {
			GP_SEO_Admin_UI::empty_row( 7, __( 'No broken links found.', 'garion-projetos-technical-seo-toolkit' ), __( 'Run a scan to check every link in your published content.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $results as $row ) :
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Post', 'garion-projetos-technical-seo-toolkit' ); ?>"><a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>"><?php echo esc_html( get_the_title( $row->post_id ) ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Broken URL', 'garion-projetos-technical-seo-toolkit' ); ?>"><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->url ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Final URL', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo $row->final_url ? esc_html( $row->final_url ) : '&#8212;'; ?></td>
				<td data-label="<?php esc_attr_e( 'Anchor text', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row->anchor_text ); ?></td>
				<td data-label="<?php esc_attr_e( 'Status', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row->http_status ); ?></td>
				<td data-label="<?php esc_attr_e( 'Last checked', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row->last_checked_at ); ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>" class="gpseo-row-actions">
					<a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>"><?php esc_html_e( 'Edit content', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_rescan_broken_link&id=' . $row->id ), 'gpseo_rescan_broken_link_' . $row->id ) ); ?>"><?php esc_html_e( 'Rescan', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_ignore_broken_link&id=' . $row->id . '&ignored=' . ( empty( $row->is_ignored ) ? 1 : 0 ) ), 'gpseo_ignore_broken_link_' . $row->id ) ); ?>"><?php echo empty( $row->is_ignored ) ? esc_html__( 'Ignore', 'garion-projetos-technical-seo-toolkit' ) : esc_html__( 'Reopen', 'garion-projetos-technical-seo-toolkit' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		$this->render_pagination( 'broken-links', $paged, $total, self::PER_PAGE );
		GP_SEO_Admin_UI::card_end();
	}

	private function render_audit() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $post_id ) {
			$this->render_content_detail( $post_id );
			return;
		}

		$search = $this->get_search();
		$paged  = $this->get_paged();
		$result = ( new GP_SEO_Audit() )->run( $search, $paged, self::PER_PAGE );

		GP_SEO_Admin_UI::card_start( __( 'Contents', 'garion-projetos-technical-seo-toolkit' ), __( 'Every published page or post with its latest SEO score and open issues.', 'garion-projetos-technical-seo-toolkit' ) );
		$this->render_search_box( 'audit', __( 'Search content...', 'garion-projetos-technical-seo-toolkit' ) );
		GP_SEO_Admin_UI::table_start(
			array(
				__( 'Content', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Score', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Issues', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Last audit', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Actions', 'garion-projetos-technical-seo-toolkit' ),
			)
		);
		if ( ! $result['rows'] ) {
			GP_SEO_Admin_UI::empty_row( 5, __( 'No published content found.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $result['rows'] as $row ) :
			$id     = $row['post']->ID;
			$detail = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audit', 'post_id' => $id ), admin_url( 'admin.php' ) );
			$issues = (int) ( $row['open_issues'] ?? 0 );
			if ( $issues ) {
				/* translators: %d: number of open SEO issues found for this content. */
				$issues_label = sprintf( _n( '%d open SEO issue', '%d open SEO issues', $issues, 'garion-projetos-technical-seo-toolkit' ), $issues );
			}
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Content', 'garion-projetos-technical-seo-toolkit' ); ?>"><a href="<?php echo esc_url( $detail ); ?>"><?php echo esc_html( get_the_title( $row['post'] ) ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Score', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo null === $row['score'] ? '&#8212;' : esc_html( round( (float) $row['score'], 1 ) . '/100' ); ?></td>
				<td data-label="<?php esc_attr_e( 'Issues', 'garion-projetos-technical-seo-toolkit' ); ?>">
					<?php if ( $issues ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'post_id' => $id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $issues_label ); ?></a>
					<?php else : ?>
						<?php echo GP_SEO_Admin_UI::badge( __( 'No open issues', 'garion-projetos-technical-seo-toolkit' ), 'success', 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'Last audit', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo $row['last_audit'] ? esc_html( $row['last_audit'] ) : '&#8212;'; ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>" class="gpseo-row-actions">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'post_id' => $id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View issues', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php esc_html_e( 'Edit', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<button type="button" class="button-link gpseo-rerun-audit" data-post-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Audit again', 'garion-projetos-technical-seo-toolkit' ); ?></button>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		$this->render_pagination( 'audit', $paged, $result['total'], self::PER_PAGE );
		GP_SEO_Admin_UI::card_end();
	}

	private function render_issues() {
		$repository = new GPSEO_Audit_Repository();
		$issue_id   = isset( $_GET['issue_id'] ) ? absint( $_GET['issue_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $issue_id ) {
			$issue = $repository->get_issue( $issue_id );
			$this->render_issue_detail( $issue );
			return;
		}

		$status_param = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
		$filters      = array(
			'search'    => $this->get_search(),
			'page'      => $this->get_paged(),
			'per_page'  => self::PER_PAGE,
			'audit_id'  => isset( $_GET['audit_id'] ) ? absint( $_GET['audit_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
			'post_id'   => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
			'severity'  => isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
			'category'  => isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
			'status'    => $status_param ? array( $status_param ) : array( 'open', 'reopened' ),
			'post_type' => isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
			'order_by'  => isset( $_GET['order_by'] ) ? sanitize_key( wp_unslash( $_GET['order_by'] ) ) : 'date', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
			'order'     => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter input.
		);

		$items = $repository->list_issues( $filters );
		$count = $repository->issue_counts( $filters );

		$audits     = $repository->recent_audits( 50 );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		GP_SEO_Admin_UI::card_start( __( 'Issues', 'garion-projetos-technical-seo-toolkit' ), sprintf( /* translators: %d: number of matching issues. */ _n( '%d issue found.', '%d issues found.', $count['total'], 'garion-projetos-technical-seo-toolkit' ), $count['total'] ) );
		?>
		<form method="get" class="gpseo-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" /><input type="hidden" name="tab" value="issues" />
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-search"><?php esc_html_e( 'Search issues', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<input type="search" id="gpseo-issues-search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search issues...', 'garion-projetos-technical-seo-toolkit' ); ?>" />
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-audit"><?php esc_html_e( 'Filter by audit', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-audit" name="audit_id"><option value="0"><?php esc_html_e( 'All audits', 'garion-projetos-technical-seo-toolkit' ); ?></option><?php foreach ( $audits as $audit ) : ?><option value="<?php echo esc_attr( $audit['id'] ); ?>" <?php selected( $filters['audit_id'], $audit['id'] ); ?>>#<?php echo esc_html( $audit['id'] . ' - ' . $audit['created_at'] ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-post"><?php esc_html_e( 'Filter by content ID', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<input type="number" min="1" id="gpseo-issues-post" name="post_id" value="<?php echo $filters['post_id'] ? esc_attr( $filters['post_id'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Content ID', 'garion-projetos-technical-seo-toolkit' ); ?>" />
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-severity"><?php esc_html_e( 'Filter by severity', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-severity" name="severity"><option value=""><?php esc_html_e( 'All severities', 'garion-projetos-technical-seo-toolkit' ); ?></option><?php foreach ( array( 'critical', 'high', 'medium', 'low', 'recommendation', 'informational' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['severity'], $value ); ?>><?php echo esc_html( ucfirst( $value ) ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-category"><?php esc_html_e( 'Filter by category', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-category" name="category"><option value=""><?php esc_html_e( 'All categories', 'garion-projetos-technical-seo-toolkit' ); ?></option><?php foreach ( array( 'titles', 'metadata', 'images', 'indexability', 'canonical', 'content', 'links', 'schema', 'sitemap', 'social' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['category'], $value ); ?>><?php echo esc_html( ucfirst( $value ) ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-status"><?php esc_html_e( 'Filter by status', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-status" name="status"><option value=""><?php esc_html_e( 'Open and reopened', 'garion-projetos-technical-seo-toolkit' ); ?></option><?php foreach ( array( 'open', 'reopened', 'ignored', 'resolved' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( count( $filters['status'] ) === 1 ? $filters['status'][0] : '', $value ); ?>><?php echo esc_html( ucfirst( $value ) ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-post-type"><?php esc_html_e( 'Filter by content type', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-post-type" name="post_type"><option value=""><?php esc_html_e( 'All content types', 'garion-projetos-technical-seo-toolkit' ); ?></option><?php foreach ( $post_types as $type ) : ?><option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $filters['post_type'], $type->name ); ?>><?php echo esc_html( $type->labels->singular_name ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-orderby"><?php esc_html_e( 'Order by', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-orderby" name="order_by"><option value="date" <?php selected( $filters['order_by'], 'date' ); ?>><?php esc_html_e( 'Last detected', 'garion-projetos-technical-seo-toolkit' ); ?></option><option value="severity" <?php selected( $filters['order_by'], 'severity' ); ?>><?php esc_html_e( 'Severity', 'garion-projetos-technical-seo-toolkit' ); ?></option><option value="score" <?php selected( $filters['order_by'], 'score' ); ?>><?php esc_html_e( 'Penalty', 'garion-projetos-technical-seo-toolkit' ); ?></option></select>
			</div>
			<div class="gpseo-filters__field">
				<label class="screen-reader-text" for="gpseo-issues-order"><?php esc_html_e( 'Order direction', 'garion-projetos-technical-seo-toolkit' ); ?></label>
				<select id="gpseo-issues-order" name="order"><option value="DESC" <?php selected( strtoupper( $filters['order'] ), 'DESC' ); ?>>DESC</option><option value="ASC" <?php selected( strtoupper( $filters['order'] ), 'ASC' ); ?>>ASC</option></select>
			</div>
			<?php submit_button( __( 'Filter', 'garion-projetos-technical-seo-toolkit' ), 'secondary', '', false ); ?>
		</form>
		<?php
		GP_SEO_Admin_UI::table_start(
			array(
				__( 'Issue', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Content', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Severity', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Category', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Penalty', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Status', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Affected URLs', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Last detected', 'garion-projetos-technical-seo-toolkit' ),
				__( 'Actions', 'garion-projetos-technical-seo-toolkit' ),
			)
		);
		if ( ! $items ) {
			GP_SEO_Admin_UI::empty_row( 9, __( 'No issues match these filters.', 'garion-projetos-technical-seo-toolkit' ), __( 'Try widening the filters above, or run a new audit from the Overview tab.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		foreach ( $items as $issue ) :
			$detail_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'issue_id' => $issue['id'] ), admin_url( 'admin.php' ) );
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Issue', 'garion-projetos-technical-seo-toolkit' ); ?>"><a href="<?php echo esc_url( $detail_url ); ?>"><strong><?php echo esc_html( $issue['title'] ?: $issue['message'] ); ?></strong></a><br><code><?php echo esc_html( $issue['check_id'] ); ?></code></td>
				<td data-label="<?php esc_attr_e( 'Content', 'garion-projetos-technical-seo-toolkit' ); ?>"><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audit', 'post_id' => $issue['post_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $issue['post_title'] ?: '#' . $issue['post_id'] ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Severity', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo GP_SEO_Admin_UI::severity_badge( $issue['severity'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td data-label="<?php esc_attr_e( 'Category', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $issue['category'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Penalty', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( number_format_i18n( $issue['penalty'], 2 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Status', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo GP_SEO_Admin_UI::status_badge( $issue['issue_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td data-label="<?php esc_attr_e( 'Affected URLs', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( (int) ( $issue['affected_urls'] ?? 1 ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Last detected', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $issue['last_seen_at'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Actions', 'garion-projetos-technical-seo-toolkit' ); ?>" class="gpseo-row-actions">
					<a href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'garion-projetos-technical-seo-toolkit' ); ?></a>
					<?php if ( 'ignored' === $issue['issue_status'] ) : ?>
						<button type="button" class="button-link gpseo-issue-status" data-issue-id="<?php echo esc_attr( $issue['id'] ); ?>" data-action="reopen"><?php esc_html_e( 'Reopen', 'garion-projetos-technical-seo-toolkit' ); ?></button>
					<?php else : ?>
						<button type="button" class="button-link gpseo-issue-status" data-issue-id="<?php echo esc_attr( $issue['id'] ); ?>" data-action="ignore"><?php esc_html_e( 'Ignore', 'garion-projetos-technical-seo-toolkit' ); ?></button>
					<?php endif; ?>
					<button type="button" class="button-link gpseo-rerun-audit" data-post-id="<?php echo esc_attr( $issue['post_id'] ); ?>"><?php esc_html_e( 'Test again', 'garion-projetos-technical-seo-toolkit' ); ?></button>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		$this->render_issue_pagination( $count['total'], $filters );
		GP_SEO_Admin_UI::card_end();
	}

	private function render_issue_detail( $issue ) {
		if ( ! $issue ) {
			GP_SEO_Admin_UI::alert( __( 'Issue not found.', 'garion-projetos-technical-seo-toolkit' ), 'danger' );
			return;
		}

		$remediation = $issue['remediation'];
		$evidence    = $issue['evidence'];
		?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues' ), admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Back to issues', 'garion-projetos-technical-seo-toolkit' ); ?></a></p>
		<div class="gpseo-detail-heading">
			<h2><?php echo esc_html( $issue['title'] ?: $issue['message'] ); ?></h2>
			<?php echo GP_SEO_Admin_UI::severity_badge( $issue['severity'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- severity_badge() escapes its own parts. ?>
			<?php echo GP_SEO_Admin_UI::status_badge( $issue['issue_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_badge() escapes its own parts. ?>
		</div>
		<div class="gpseo-issue-layout">
			<div class="gpseo-card">
				<div class="gpseo-card__body">
					<table class="gpseo-table gpseo-table--plain"><tbody>
						<tr><th><?php esc_html_e( 'Check ID', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><code><?php echo esc_html( $issue['check_id'] ); ?></code></td></tr>
						<tr><th><?php esc_html_e( 'Category', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo esc_html( ucfirst( $issue['category'] ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Weight / penalty', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo esc_html( number_format_i18n( $issue['weight'], 2 ) . ' / ' . number_format_i18n( $issue['penalty'], 2 ) ); ?> <?php if ( $issue['raw_penalty'] > $issue['penalty'] ) : ?><small><?php esc_html_e( '(limited by category cap)', 'garion-projetos-technical-seo-toolkit' ); ?></small><?php endif; ?></td></tr>
						<tr><th><?php esc_html_e( 'Affected page', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><a href="<?php echo esc_url( $issue['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $issue['url'] ); ?></a><br>#<?php echo esc_html( $issue['post_id'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Found value', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo nl2br( esc_html( $issue['found_value'] ?: __( '(not recorded by an older audit)', 'garion-projetos-technical-seo-toolkit' ) ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Expected value', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo nl2br( esc_html( $issue['expected_value'] ?: __( '(not recorded by an older audit)', 'garion-projetos-technical-seo-toolkit' ) ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Explanation', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo nl2br( esc_html( $issue['explanation'] ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Impact on SEO', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo nl2br( esc_html( $issue['why_matters'] ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'How to fix it', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo nl2br( esc_html( $issue['recommendation'] ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Data source', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo esc_html( $issue['source_provider'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'First / last detected', 'garion-projetos-technical-seo-toolkit' ); ?></th><td><?php echo esc_html( $issue['first_seen_at'] . ' / ' . $issue['last_seen_at'] ); ?></td></tr>
					</tbody></table>
					<h3><?php esc_html_e( 'Technical evidence', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
					<pre class="gpseo-evidence"><?php echo esc_html( wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				</div>
			</div>
			<aside class="gpseo-card gpseo-remediation">
				<div class="gpseo-card__body">
					<h3><?php esc_html_e( 'Where to fix', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
					<p><strong><?php echo esc_html( $remediation['admin_section'] ?? __( 'Manual review', 'garion-projetos-technical-seo-toolkit' ) ); ?></strong></p>
					<p><code><?php echo esc_html( $remediation['target_type'] ?? 'manual_review' ); ?></code></p>
					<?php if ( ! empty( $remediation['edit_url'] ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $remediation['edit_url'] ); ?>"><?php echo esc_html( $remediation['label'] ?: __( 'Open editor', 'garion-projetos-technical-seo-toolkit' ) ); ?></a>
					<?php endif; ?>
					<hr>
					<?php if ( 'ignored' === $issue['issue_status'] ) : ?>
						<button type="button" class="button gpseo-issue-status" data-issue-id="<?php echo esc_attr( $issue['id'] ); ?>" data-action="reopen"><?php esc_html_e( 'Reopen issue', 'garion-projetos-technical-seo-toolkit' ); ?></button>
					<?php else : ?>
						<button type="button" class="button gpseo-issue-status" data-issue-id="<?php echo esc_attr( $issue['id'] ); ?>" data-action="ignore"><?php esc_html_e( 'Ignore issue', 'garion-projetos-technical-seo-toolkit' ); ?></button>
					<?php endif; ?>
					<button type="button" class="button gpseo-rerun-audit" data-post-id="<?php echo esc_attr( $issue['post_id'] ); ?>"><?php esc_html_e( 'Test again', 'garion-projetos-technical-seo-toolkit' ); ?></button>
				</div>
			</aside>
		</div>
		<?php
	}

	private function render_content_detail( $post_id ) {
		$repository = new GPSEO_Audit_Repository();
		$details    = $repository->content_details( (int) $post_id );

		if ( ! $details ) {
			GP_SEO_Admin_UI::alert( __( 'Content not found.', 'garion-projetos-technical-seo-toolkit' ), 'danger' );
			return;
		}

		$post    = $details['post'];
		$summary = $details['summary'];
		$metrics = $summary['metrics'] ?? array();
		/* translators: %1$s: initial score, %2$s: total applied penalty, %3$s: final score, %4$s: maximum penalty per category. */
		$score_calculation_text = sprintf( __( 'Initial score: %1$s - applied penalties: %2$s = final score: %3$s. Maximum penalty per category: %4$s.', 'garion-projetos-technical-seo-toolkit' ), $metrics['initial_score'] ?? 100, $metrics['total_penalty'] ?? 0, $summary['score'] ?? 100, $metrics['category_cap'] ?? 30 );
		/* translators: %d: number of open SEO issues for this content. */
		$view_issues_label = sprintf( _n( 'View %d issue', 'View %d issues', $details['total_issues'], 'garion-projetos-technical-seo-toolkit' ), $details['total_issues'] );
		?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audit' ), admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Back to contents', 'garion-projetos-technical-seo-toolkit' ); ?></a></p>
		<div class="gpseo-detail-heading">
			<h2><?php echo esc_html( get_the_title( $post ) ); ?></h2>
		</div>
		<p><a href="<?php echo esc_url( $details['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $details['url'] ); ?></a> &middot; <?php echo esc_html( $post->post_type ); ?> &middot; #<?php echo esc_html( $post->ID ); ?></p>
		<?php GP_SEO_Admin_UI::card_start(); ?>
		<div class="gpseo-metrics">
			<?php
			GP_SEO_Admin_UI::metric_card( isset( $summary['score'] ) ? round( (float) $summary['score'], 1 ) : '—', __( 'SEO score', 'garion-projetos-technical-seo-toolkit' ), 'primary', 'dashicons-chart-line' );
			GP_SEO_Admin_UI::metric_card( $details['total_issues'], __( 'Open issues', 'garion-projetos-technical-seo-toolkit' ), 'warning', 'dashicons-flag' );
			GP_SEO_Admin_UI::metric_card( (int) ( $details['severity_counts']['critical'] ?? 0 ), __( 'Critical', 'garion-projetos-technical-seo-toolkit' ), 'critical', 'dashicons-dismiss' );
			GP_SEO_Admin_UI::metric_card( (int) ( $details['severity_counts']['high'] ?? 0 ), __( 'High priority', 'garion-projetos-technical-seo-toolkit' ), 'high', 'dashicons-warning' );
			?>
		</div>
		<p><strong><?php esc_html_e( 'Last audit:', 'garion-projetos-technical-seo-toolkit' ); ?></strong> <?php echo esc_html( $summary['last_audit'] ?? __( 'Not audited', 'garion-projetos-technical-seo-toolkit' ) ); ?> <button type="button" class="button gpseo-rerun-audit" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Audit again', 'garion-projetos-technical-seo-toolkit' ); ?></button> <a class="button" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit content', 'garion-projetos-technical-seo-toolkit' ); ?></a></p>
		<h3><?php esc_html_e( 'Category scores', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
		<div class="gpseo-category-scores">
			<?php foreach ( (array) ( $summary['category_scores'] ?? array() ) as $category => $score ) : ?>
				<span class="gpseo-summary-chip"><strong><?php echo esc_html( ucfirst( $category ) ); ?></strong> <?php echo esc_html( $score ); ?>/100</span>
			<?php endforeach; ?>
		</div>
		<h3><?php esc_html_e( 'Score calculation', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
		<p><?php echo esc_html( $score_calculation_text ); ?></p>
		<?php
		GP_SEO_Admin_UI::table_start( array( __( 'Check', 'garion-projetos-technical-seo-toolkit' ), __( 'Raw penalty', 'garion-projetos-technical-seo-toolkit' ), __( 'Applied penalty', 'garion-projetos-technical-seo-toolkit' ) ) );
		foreach ( (array) ( $metrics['breakdown'] ?? array() ) as $check => $penalty ) :
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Check', 'garion-projetos-technical-seo-toolkit' ); ?>"><code><?php echo esc_html( $check ); ?></code></td>
				<td data-label="<?php esc_attr_e( 'Raw penalty', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $penalty['raw_penalty'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Applied penalty', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $penalty['applied_penalty'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		?>
		<h3><?php esc_html_e( 'Recent score history', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
		<?php
		GP_SEO_Admin_UI::table_start( array( __( 'Date', 'garion-projetos-technical-seo-toolkit' ), __( 'Score', 'garion-projetos-technical-seo-toolkit' ), __( 'Penalty', 'garion-projetos-technical-seo-toolkit' ) ) );
		foreach ( $details['history'] as $row ) :
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Date', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row['created_at'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Score', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row['score'] ); ?>/100</td>
				<td data-label="<?php esc_attr_e( 'Penalty', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row['metrics']['total_penalty'] ?? 0 ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		?>
		<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'post_id' => $post->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $view_issues_label ); ?></a></p>
		<?php
		GP_SEO_Admin_UI::card_end();
	}

	private function render_audit_detail( $audit_id, $compare = false ) {
		$repository = new GPSEO_Audit_Repository();
		$details    = $repository->audit_details( (int) $audit_id );

		if ( ! $details ) {
			GP_SEO_Admin_UI::alert( __( 'Audit not found.', 'garion-projetos-technical-seo-toolkit' ), 'danger' );
			return;
		}

		$audit    = $details['audit'];
		$metrics  = $details['metrics'];
		$duration = $audit->started_at && $audit->completed_at ? max( 0, strtotime( $audit->completed_at ) - strtotime( $audit->started_at ) ) : null;
		/* translators: %d: audit ID number. */
		$audit_title = sprintf( __( 'Audit #%d', 'garion-projetos-technical-seo-toolkit' ), $audit->id );
		/* translators: %1$s: audit scope (site or content), %2$s: audit status, %3$d: number of new problems, %4$d: number of resolved problems, %5$d: number of reopened problems. */
		$scope_summary_text = sprintf( __( 'Scope: %1$s. Status: %2$s. New: %3$d. Resolved: %4$d. Reopened: %5$d.', 'garion-projetos-technical-seo-toolkit' ), $audit->scope, $audit->status, $metrics['new_problems'] ?? 0, $metrics['resolved_problems'] ?? 0, $metrics['reopened_problems'] ?? 0 );
		?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audits' ), admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Back to audits', 'garion-projetos-technical-seo-toolkit' ); ?></a></p>
		<div class="gpseo-detail-heading">
			<h2><?php echo esc_html( $audit_title ); ?></h2>
			<?php echo GP_SEO_Admin_UI::status_badge( $audit->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php GP_SEO_Admin_UI::card_start(); ?>
		<div class="gpseo-metrics">
			<?php
			GP_SEO_Admin_UI::metric_card( null !== $audit->score ? round( (float) $audit->score, 1 ) : '—', __( 'Score', 'garion-projetos-technical-seo-toolkit' ), 'primary', 'dashicons-chart-line' );
			GP_SEO_Admin_UI::metric_card( $audit->processed_items . '/' . $audit->total_items, __( 'Processed', 'garion-projetos-technical-seo-toolkit' ), 'neutral', 'dashicons-media-document' );
			GP_SEO_Admin_UI::metric_card( null === $duration ? '—' : ( $duration < 1 ? __( '< 1 second', 'garion-projetos-technical-seo-toolkit' ) : human_time_diff( 0, $duration ) ), __( 'Duration', 'garion-projetos-technical-seo-toolkit' ), 'neutral', 'dashicons-clock' );
			GP_SEO_Admin_UI::metric_card( $metrics['problems'] ?? 0, __( 'Problems', 'garion-projetos-technical-seo-toolkit' ), 'warning', 'dashicons-flag' );
			?>
		</div>
		<p><?php echo esc_html( $scope_summary_text ); ?></p>
		<?php if ( $compare ) : $history = $repository->history( 100 ); $current_index = array_search( (int) $audit->id, array_map( 'intval', wp_list_pluck( $history, 'audit_id' ) ), true ); $previous = false !== $current_index ? ( $history[ $current_index + 1 ] ?? null ) : null; ?>
			<?php
			if ( $previous ) {
				/* translators: %1$d: previous audit ID number, %2$s: score change since that audit, %3$s: problem count change since that audit. */
				$comparison_text = sprintf( __( 'Compared with audit #%1$d: score change %2$s; problem change %3$s.', 'garion-projetos-technical-seo-toolkit' ), $previous['audit_id'], round( (float) $audit->score - (float) $previous['score'], 1 ), (int) ( $metrics['problems'] ?? 0 ) - (int) ( $previous['metrics']['problems'] ?? 0 ) );
			} else {
				$comparison_text = __( 'No previous full audit is available for comparison.', 'garion-projetos-technical-seo-toolkit' );
			}
			GP_SEO_Admin_UI::alert( esc_html( $comparison_text ), 'info' );
			?>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Problems by severity', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
		<p><?php foreach ( $details['severity_counts'] as $key => $value ) : ?><?php echo GP_SEO_Admin_UI::severity_badge( $key, ucfirst( $key ) . ': ' . $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php endforeach; ?></p>
		<h3><?php esc_html_e( 'Problems by category', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
		<p><?php foreach ( $details['category_counts'] as $key => $value ) : ?><span class="gpseo-summary-chip"><?php echo esc_html( ucfirst( $key ) . ': ' . $value ); ?></span> <?php endforeach; ?></p>
		<h3><?php esc_html_e( 'Lowest-scoring content', 'garion-projetos-technical-seo-toolkit' ); ?></h3>
		<?php
		GP_SEO_Admin_UI::table_start( array( __( 'Content', 'garion-projetos-technical-seo-toolkit' ), __( 'Score', 'garion-projetos-technical-seo-toolkit' ) ) );
		foreach ( $details['worst_content'] as $row ) :
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Content', 'garion-projetos-technical-seo-toolkit' ); ?>"><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'audit', 'post_id' => $row['post_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $row['post_title'] ?: '#' . $row['post_id'] ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Score', 'garion-projetos-technical-seo-toolkit' ); ?>"><?php echo esc_html( $row['score'] ); ?>/100</td>
			</tr>
		<?php endforeach; ?>
		<?php
		GP_SEO_Admin_UI::table_end();
		?>
		<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'audit_id' => $audit->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View audit issues', 'garion-projetos-technical-seo-toolkit' ); ?></a></p>
		<?php
		GP_SEO_Admin_UI::card_end();
	}

	private function render_issue_pagination( $total, array $filters ) {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<div class="tablenav gpseo-pagination"><div class="tablenav-pages" aria-label="' . esc_attr__( 'Pagination', 'garion-projetos-technical-seo-toolkit' ) . '">';
		for ( $page = 1; $page <= $pages; $page++ ) {
			$args = array( 'page' => self::MENU_SLUG, 'tab' => 'issues', 'paged' => $page, 's' => $filters['search'], 'audit_id' => $filters['audit_id'], 'post_id' => $filters['post_id'], 'severity' => $filters['severity'], 'category' => $filters['category'], 'status' => count( $filters['status'] ) === 1 ? $filters['status'][0] : '', 'post_type' => $filters['post_type'], 'order_by' => $filters['order_by'], 'order' => $filters['order'] );
			printf( '<a class="page-numbers%s" %s href="%s">%d</a> ', $page === (int) $filters['page'] ? ' current' : '', $page === (int) $filters['page'] ? 'aria-current="page"' : '', esc_url( add_query_arg( array_filter( $args, static fn( $value ) => '' !== $value && 0 !== $value ), admin_url( 'admin.php' ) ) ), (int) $page );
		}
		echo '</div></div>';
	}

	private function render_settings() {
		$org_name            = get_option( 'gpseo_org_name', get_bloginfo( 'name' ) );
		$org_logo            = get_option( 'gpseo_org_logo', '' );
		$robots_extra        = get_option( 'gpseo_robots_txt_extra', '' );
		$audit_post_types    = (array) get_option( 'gpseo_audit_post_types', array( 'post', 'page' ) );
		$audit_batch_size    = (int) get_option( 'gpseo_audit_batch_size', 10 );
		$detail_retention    = (int) get_option( 'gpseo_audit_detail_retention_days', 90 );
		$summary_retention   = (int) get_option( 'gpseo_audit_summary_retention_months', 12 );
		$remove_on_uninstall = (bool) get_option( 'gpseo_remove_data_on_uninstall', false );
		$public_post_types   = get_post_types( array( 'public' => true ), 'objects' );

		GP_SEO_Admin_UI::alert(
			sprintf(
				/* translators: %s: XML sitemap URL. */
				__( '<strong>XML sitemap:</strong> %s (also linked automatically from robots.txt)', 'garion-projetos-technical-seo-toolkit' ),
				'<code>' . esc_html( GP_SEO_Sitemap::sitemap_url() ) . '</code>'
			),
			'info'
		);

		GP_SEO_Admin_UI::card_start( __( 'Settings', 'garion-projetos-technical-seo-toolkit' ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gpseo_save_settings' ); ?>
			<input type="hidden" name="action" value="gpseo_save_settings" />
			<table class="form-table">
				<tr>
					<th><label for="gpseo_org_name"><?php esc_html_e( 'Organization name', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="text" id="gpseo_org_name" name="gpseo_org_name" class="regular-text" value="<?php echo esc_attr( $org_name ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="gpseo_org_logo"><?php esc_html_e( 'Organization logo URL', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="url" id="gpseo_org_logo" name="gpseo_org_logo" class="regular-text" value="<?php echo esc_attr( $org_logo ); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Audited post types', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<td><?php foreach ( $public_post_types as $post_type ) : ?><label class="gpseo-checkbox-row"><input type="checkbox" name="gpseo_audit_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $audit_post_types, true ) ); ?> /> <?php echo esc_html( $post_type->labels->singular_name ); ?></label><?php endforeach; ?></td>
				</tr>
				<tr>
					<th><label for="gpseo_audit_batch_size"><?php esc_html_e( 'Audit batch size', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="number" min="1" max="50" id="gpseo_audit_batch_size" name="gpseo_audit_batch_size" value="<?php echo esc_attr( $audit_batch_size ); ?>" /><p class="description"><?php esc_html_e( 'Maximum content items processed per cron batch (1-50).', 'garion-projetos-technical-seo-toolkit' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gpseo_audit_detail_retention_days"><?php esc_html_e( 'Detailed history retention', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="number" min="7" max="365" id="gpseo_audit_detail_retention_days" name="gpseo_audit_detail_retention_days" value="<?php echo esc_attr( $detail_retention ); ?>" /> <?php esc_html_e( 'days', 'garion-projetos-technical-seo-toolkit' ); ?></td>
				</tr>
				<tr>
					<th><label for="gpseo_audit_summary_retention_months"><?php esc_html_e( 'Summary retention', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td><input type="number" min="1" max="36" id="gpseo_audit_summary_retention_months" name="gpseo_audit_summary_retention_months" value="<?php echo esc_attr( $summary_retention ); ?>" /> <?php esc_html_e( 'months', 'garion-projetos-technical-seo-toolkit' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Uninstall data', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<td><label><input type="checkbox" name="gpseo_remove_data_on_uninstall" value="1" <?php checked( $remove_on_uninstall ); ?> /> <?php esc_html_e( 'Permanently remove plugin tables, settings and metadata when the plugin is deleted.', 'garion-projetos-technical-seo-toolkit' ); ?></label><p class="description"><?php esc_html_e( 'Disabled by default. Deactivation never removes stored data.', 'garion-projetos-technical-seo-toolkit' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gpseo_robots_txt_extra"><?php esc_html_e( 'Extra robots.txt rules', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td>
						<textarea id="gpseo_robots_txt_extra" name="gpseo_robots_txt_extra" rows="5" class="large-text" placeholder="Disallow: /private/"><?php echo esc_textarea( $robots_extra ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Appended to the virtual robots.txt generated by WordPress.', 'garion-projetos-technical-seo-toolkit' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
		GP_SEO_Admin_UI::card_end();
	}

	public function handle_add_redirect() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gpseo_add_redirect' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		$source      = isset( $_POST['source_path'] ) ? sanitize_text_field( wp_unslash( $_POST['source_path'] ) ) : '';
		$destination = isset( $_POST['destination_url'] ) ? esc_url_raw( wp_unslash( $_POST['destination_url'] ) ) : '';
		$type        = isset( $_POST['redirect_type'] ) ? (int) $_POST['redirect_type'] : 301;

		if ( $source && $destination ) {
			( new GP_SEO_Redirects() )->add( $source, $destination, $type );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		check_admin_referer( 'gpseo_delete_redirect_' . $id );

		if ( $id ) {
			( new GP_SEO_Redirects() )->delete( $id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_404() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		check_admin_referer( 'gpseo_delete_404_' . $id );

		if ( $id ) {
			( new GP_SEO_404_Monitor() )->delete( $id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => '404-monitor' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_export_redirects() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gpseo_export_redirects' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		$redirects = ( new GP_SEO_Redirects() )->get_all_unpaginated();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=redirects.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a CSV download directly to the browser, not writing to the filesystem.
		fputcsv( $out, array( 'source_path', 'destination_url', 'redirect_type' ) );

		foreach ( $redirects as $redirect ) {
			fputcsv( $out, array( $redirect->source_path, $redirect->destination_url, $redirect->redirect_type ) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function handle_import_redirects() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gpseo_import_redirects' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		$imported = 0;

		$import_tmp_name = isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ) : '';
		if ( $import_tmp_name && is_uploaded_file( $import_tmp_name ) ) {
			$handle = fopen( $import_tmp_name, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading a just-uploaded temporary file to parse it, not a persistent filesystem operation.

			if ( $handle ) {
				$manager = new GP_SEO_Redirects();
				$row     = fgetcsv( $handle );

				while ( false !== $row ) {
					$source      = isset( $row[0] ) ? trim( $row[0] ) : '';
					$destination = isset( $row[1] ) ? trim( $row[1] ) : '';

					if ( '' !== $source && '' !== $destination && 'source_path' !== $source ) {
						$type = isset( $row[2] ) ? (int) $row[2] : 301;
						if ( false !== $manager->add( $source, $destination, $type ) ) {
							++$imported;
						}
					}

					$row = fgetcsv( $handle );
				}

				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::MENU_SLUG,
					'tab'          => 'redirects',
					'gpseo_notice' => sprintf(
						/* translators: %d: number of redirects imported. */
						_n( '%d redirect imported.', '%d redirects imported.', $imported, 'garion-projetos-technical-seo-toolkit' ),
						$imported
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_export_audit() {
		$audit_id = isset( $_GET['audit_id'] ) ? absint( $_GET['audit_id'] ) : 0;
		$format   = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'json';
		if ( ! current_user_can( 'manage_options' ) || ! $audit_id || ! check_admin_referer( 'gpseo_export_audit_' . $audit_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		$repository = new GPSEO_Audit_Repository();
		$details    = $repository->audit_details( $audit_id );
		if ( ! $details ) {
			wp_die( esc_html__( 'Audit not found.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		$results = array();
		$page = 1;
		do {
			$batch = $repository->audit_results( $audit_id, $page, 100 );
			$results = array_merge( $results, $batch['items'] );
			++$page;
		} while ( count( $results ) < $batch['total'] );

		nocache_headers();
		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=seo-audit-' . $audit_id . '.csv' );
			$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			fputcsv( $out, array( 'content_id', 'content', 'post_type', 'check', 'title', 'severity', 'category', 'penalty', 'status', 'found', 'expected', 'why_it_matters', 'remediation' ) );
			foreach ( $results as $row ) {
				fputcsv( $out, array( $row['post_id'], $row['post_title'], $row['post_type'], $row['check_id'], $row['title'], $row['severity'], $row['category'], $row['penalty'], $row['issue_status'], $row['found_value'], $row['expected_value'], $row['why_matters'], wp_json_encode( $row['remediation'] ) ) );
			}
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			exit;
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seo-audit-' . $audit_id . '.json' );
		echo wp_json_encode( array( 'audit' => $details, 'issues' => $results ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public function handle_ignore_404() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! check_admin_referer( 'gpseo_ignore_404_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		( new GP_SEO_404_Monitor() )->set_ignored( $id, ! empty( $_GET['ignored'] ) );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => '404-monitor' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_ignore_broken_link() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! check_admin_referer( 'gpseo_ignore_broken_link_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		( new GP_SEO_Broken_Links() )->set_ignored( $id, ! empty( $_GET['ignored'] ) );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'broken-links' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_rescan_broken_link() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! check_admin_referer( 'gpseo_rescan_broken_link_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}
		$manager = new GP_SEO_Broken_Links();
		$row = $manager->get( $id );
		if ( $row ) {
			$manager->rescan_post( (int) $row->post_id );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'broken-links' ), admin_url( 'admin.php' ) ) );
		exit;
	}
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gpseo_save_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		update_option( 'gpseo_org_name', isset( $_POST['gpseo_org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gpseo_org_name'] ) ) : '' );
		update_option( 'gpseo_org_logo', isset( $_POST['gpseo_org_logo'] ) ? esc_url_raw( wp_unslash( $_POST['gpseo_org_logo'] ) ) : '' );
		update_option( 'gpseo_robots_txt_extra', isset( $_POST['gpseo_robots_txt_extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gpseo_robots_txt_extra'] ) ) : '' );
		$public_types = get_post_types( array( 'public' => true ), 'names' );
		$requested_types = isset( $_POST['gpseo_audit_post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['gpseo_audit_post_types'] ) ) : array();
		$valid_types = array_values( array_intersect( $requested_types, $public_types ) );
		update_option( 'gpseo_audit_post_types', $valid_types ? $valid_types : array( 'post', 'page' ) );
		update_option( 'gpseo_audit_batch_size', max( 1, min( 50, isset( $_POST['gpseo_audit_batch_size'] ) ? absint( $_POST['gpseo_audit_batch_size'] ) : 10 ) ) );
		update_option( 'gpseo_audit_detail_retention_days', max( 7, min( 365, isset( $_POST['gpseo_audit_detail_retention_days'] ) ? absint( $_POST['gpseo_audit_detail_retention_days'] ) : 90 ) ) );
		update_option( 'gpseo_audit_summary_retention_months', max( 1, min( 36, isset( $_POST['gpseo_audit_summary_retention_months'] ) ? absint( $_POST['gpseo_audit_summary_retention_months'] ) : 12 ) ) );
		update_option( 'gpseo_remove_data_on_uninstall', ! empty( $_POST['gpseo_remove_data_on_uninstall'] ) ? 1 : 0 );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
