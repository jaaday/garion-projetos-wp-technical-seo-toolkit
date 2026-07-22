<?php
/**
 * Admin screen: tabbed UI for Redirects, 404 Monitor, Broken Links, Audit and Settings.
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
	}

	public function add_menu() {
		add_menu_page(
			__( 'Technical SEO Toolkit', 'garion-projetos-technical-seo-toolkit' ),
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

		wp_enqueue_style( 'gpseo-admin', GPSEO_URL . 'assets/css/admin.css', array(), GPSEO_VERSION );
		wp_enqueue_script( 'gpseo-admin', GPSEO_URL . 'assets/js/admin.js', array( 'wp-api-fetch' ), GPSEO_VERSION, true );

		wp_localize_script(
			'gpseo-admin',
			'gpseoData',
			array(
				'restNamespace' => GP_SEO_REST_Controller::NAMESPACE_,
				'i18n'          => array(
					'scanning' => __( 'Scanning... this page will refresh automatically when it finishes.', 'garion-projetos-technical-seo-toolkit' ),
					'done'     => __( 'Scan finished. Refreshing...', 'garion-projetos-technical-seo-toolkit' ),
				),
			)
		);
	}

	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'redirects'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.

		return in_array( $tab, array_keys( $this->tabs() ), true ) ? $tab : 'redirects';
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

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Technical SEO Toolkit', 'garion-projetos-technical-seo-toolkit' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $this->tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="gpseo-tab-content">
				<?php
				switch ( $tab ) {
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
						$this->render_redirects();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function tabs() {
		return array(
			'redirects'    => __( 'Redirects', 'garion-projetos-technical-seo-toolkit' ),
			'404-monitor'  => __( '404 Monitor', 'garion-projetos-technical-seo-toolkit' ),
			'broken-links' => __( 'Broken Links', 'garion-projetos-technical-seo-toolkit' ),
			'audit'        => __( 'Page Audit', 'garion-projetos-technical-seo-toolkit' ),
			'settings'     => __( 'Settings', 'garion-projetos-technical-seo-toolkit' ),
		);
	}

	private function render_search_box( $tab, $placeholder = '' ) {
		$value = $this->get_search();
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
			<p class="search-box">
				<input type="search" name="s" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ? $placeholder : __( 'Search', 'garion-projetos-technical-seo-toolkit' ) ); ?>" />
				<?php submit_button( __( 'Search', 'garion-projetos-technical-seo-toolkit' ), '', '', false ); ?>
			</p>
		</form>
		<?php
	}

	private function render_pagination( $tab, $current_page, $total_items, $per_page ) {
		$total_pages = (int) ceil( $total_items / $per_page );

		if ( $total_pages <= 1 ) {
			return;
		}

		$search = $this->get_search();
		echo '<div class="tablenav"><div class="tablenav-pages">';

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
				'<a class="page-numbers%s" href="%s">%d</a> ',
				$page === $current_page ? ' current' : '',
				esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ),
				(int) $page
			);
		}

		echo '</div></div>';
	}

	private function render_redirects() {
		$search    = $this->get_search();
		$paged     = $this->get_paged();
		$manager   = new GP_SEO_Redirects();
		$redirects = $manager->get_all( $search, self::PER_PAGE, $paged );
		$total     = $manager->count_all( $search );

		$prefill_source = isset( $_GET['prefill_source'] ) ? sanitize_text_field( wp_unslash( $_GET['prefill_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill from the 404 monitor tab.
		?>
		<h2><?php esc_html_e( 'Add redirect', 'garion-projetos-technical-seo-toolkit' ); ?></h2>
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
					<td><input type="text" id="destination_url" name="destination_url" class="regular-text" placeholder="https://example.com/new-page" required /></td>
				</tr>
				<tr>
					<th><label for="redirect_type"><?php esc_html_e( 'Type', 'garion-projetos-technical-seo-toolkit' ); ?></label></th>
					<td>
						<select id="redirect_type" name="redirect_type">
							<option value="301">301 (<?php esc_html_e( 'Permanent', 'garion-projetos-technical-seo-toolkit' ); ?>)</option>
							<option value="302">302 (<?php esc_html_e( 'Temporary', 'garion-projetos-technical-seo-toolkit' ); ?>)</option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add redirect', 'garion-projetos-technical-seo-toolkit' ) ); ?>
		</form>

		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_export_redirects' ), 'gpseo_export_redirects' ) ); ?>"><?php esc_html_e( 'Export CSV', 'garion-projetos-technical-seo-toolkit' ); ?></a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-bottom:20px;">
			<?php wp_nonce_field( 'gpseo_import_redirects' ); ?>
			<input type="hidden" name="action" value="gpseo_import_redirects" />
			<input type="file" name="import_file" accept=".csv" required />
			<?php submit_button( __( 'Import CSV', 'garion-projetos-technical-seo-toolkit' ), 'secondary', 'submit', false ); ?>
			<p class="description"><?php esc_html_e( 'Columns: source_path, destination_url, redirect_type', 'garion-projetos-technical-seo-toolkit' ); ?></p>
		</form>

		<h2><?php esc_html_e( 'Existing redirects', 'garion-projetos-technical-seo-toolkit' ); ?></h2>
		<?php $this->render_search_box( 'redirects', __( 'Search redirects...', 'garion-projetos-technical-seo-toolkit' ) ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Source', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Destination', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Type', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Hits', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $redirects ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No redirects found.', 'garion-projetos-technical-seo-toolkit' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $redirects as $redirect ) : ?>
						<tr>
							<td><code><?php echo esc_html( $redirect->source_path ); ?></code></td>
							<td><?php echo esc_html( $redirect->destination_url ); ?></td>
							<td><?php echo esc_html( $redirect->redirect_type ); ?></td>
							<td><?php echo esc_html( $redirect->hits ); ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_delete_redirect&id=' . $redirect->id ), 'gpseo_delete_redirect_' . $redirect->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect?', 'garion-projetos-technical-seo-toolkit' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'garion-projetos-technical-seo-toolkit' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( 'redirects', $paged, $total, self::PER_PAGE );
	}

	private function render_404_monitor() {
		$search  = $this->get_search();
		$paged   = $this->get_paged();
		$monitor = new GP_SEO_404_Monitor();
		$results = $monitor->get_results( $search, self::PER_PAGE, $paged );
		$total   = $monitor->count_results( $search );
		?>
		<p class="description"><?php esc_html_e( 'Real "page not found" hits hit by visitors. Turn any of these into a redirect with one click.', 'garion-projetos-technical-seo-toolkit' ); ?></p>
		<?php $this->render_search_box( '404-monitor', __( 'Search 404s...', 'garion-projetos-technical-seo-toolkit' ) ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Hits', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Referrer', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Last seen', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $results ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No 404s logged yet.', 'garion-projetos-technical-seo-toolkit' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $results as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row->url ); ?></code></td>
							<td><?php echo esc_html( $row->hit_count ); ?></td>
							<td><?php echo $row->referrer ? esc_html( $row->referrer ) : '&#8212;'; ?></td>
							<td><?php echo esc_html( $row->last_seen ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects', 'prefill_source' => $row->url ), admin_url( 'admin.php' ) ) ); ?>">
									<?php esc_html_e( 'Create redirect', 'garion-projetos-technical-seo-toolkit' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gpseo_delete_404&id=' . $row->id ), 'gpseo_delete_404_' . $row->id ) ); ?>">
									<?php esc_html_e( 'Dismiss', 'garion-projetos-technical-seo-toolkit' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( '404-monitor', $paged, $total, self::PER_PAGE );
	}

	private function render_broken_links() {
		$search       = $this->get_search();
		$paged        = $this->get_paged();
		$broken_links = new GP_SEO_Broken_Links();
		$results      = $broken_links->get_results( $search, self::PER_PAGE, $paged );
		$total        = $broken_links->count_all( $search );
		$status       = $broken_links->get_status();
		?>
		<p>
			<button type="button" class="button button-primary" id="gpseo-scan-now" data-scanning="<?php echo esc_attr( 'running' === $status['status'] ? '1' : '0' ); ?>">
				<?php esc_html_e( 'Scan for broken links now', 'garion-projetos-technical-seo-toolkit' ); ?>
			</button>
			<span id="gpseo-scan-message"></span>
		</p>
		<p class="description">
			<?php
			if ( $status['last_run'] ) {
				printf(
					/* translators: %s: date/time of the last completed scan. */
					esc_html__( 'Last full scan completed: %s. A new batch also runs automatically every 10 minutes.', 'garion-projetos-technical-seo-toolkit' ),
					esc_html( $status['last_run'] )
				);
			} else {
				esc_html_e( 'No scan has completed yet. A batch runs automatically every 10 minutes, or click the button above.', 'garion-projetos-technical-seo-toolkit' );
			}
			?>
		</p>

		<?php $this->render_search_box( 'broken-links', __( 'Search broken links...', 'garion-projetos-technical-seo-toolkit' ) ); ?>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Broken URL', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Anchor text', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Status', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Last checked', 'garion-projetos-technical-seo-toolkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $results ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No broken links found.', 'garion-projetos-technical-seo-toolkit' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $results as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>"><?php echo esc_html( get_the_title( $row->post_id ) ); ?></a></td>
							<td><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->url ); ?></a></td>
							<td><?php echo esc_html( $row->anchor_text ); ?></td>
							<td><?php echo esc_html( $row->http_status ); ?></td>
							<td><?php echo esc_html( $row->last_checked_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( 'broken-links', $paged, $total, self::PER_PAGE );
	}

	private function render_audit() {
		$search = $this->get_search();
		$paged  = $this->get_paged();
		$result = ( new GP_SEO_Audit() )->run( $search, $paged, self::PER_PAGE );
		$rows   = $result['rows'];
		$total  = $result['total'];
		?>
		<?php $this->render_search_box( 'audit', __( 'Search content...', 'garion-projetos-technical-seo-toolkit' ) ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Score', 'garion-projetos-technical-seo-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Issues', 'garion-projetos-technical-seo-toolkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No published content to audit.', 'garion-projetos-technical-seo-toolkit' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $row['post']->ID ) ); ?>"><?php echo esc_html( get_the_title( $row['post'] ) ); ?></a></td>
							<td><?php echo esc_html( $row['score'] ); ?>/100</td>
							<td>
								<?php if ( empty( $row['issues'] ) ) : ?>
									&#10003; <?php esc_html_e( 'No issues found.', 'garion-projetos-technical-seo-toolkit' ); ?>
								<?php else : ?>
									<ul style="margin:0;">
										<?php foreach ( $row['issues'] as $issue ) : ?>
											<li><?php echo esc_html( $issue ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( 'audit', $paged, $total, self::PER_PAGE );
	}

	private function render_settings() {
		$org_name     = get_option( 'gpseo_org_name', get_bloginfo( 'name' ) );
		$org_logo     = get_option( 'gpseo_org_logo', '' );
		$robots_extra = get_option( 'gpseo_robots_txt_extra', '' );
		?>
		<p class="description">
			<?php esc_html_e( 'XML sitemap:', 'garion-projetos-technical-seo-toolkit' ); ?>
			<code><?php echo esc_html( GP_SEO_Sitemap::sitemap_url() ); ?></code>
			(<?php esc_html_e( 'also linked automatically from robots.txt', 'garion-projetos-technical-seo-toolkit' ); ?>)
		</p>
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

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- streaming a CSV download directly to the browser, not writing to the filesystem.
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

		if ( ! empty( $_FILES['import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			$handle = fopen( $_FILES['import_file']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- reading a just-uploaded temporary file to parse it, not a persistent filesystem operation.

			if ( $handle ) {
				$manager = new GP_SEO_Redirects();
				$row     = fgetcsv( $handle );

				while ( false !== $row ) {
					$source      = isset( $row[0] ) ? trim( $row[0] ) : '';
					$destination = isset( $row[1] ) ? trim( $row[1] ) : '';

					if ( '' !== $source && '' !== $destination && 'source_path' !== $source ) {
						$type = isset( $row[2] ) ? (int) $row[2] : 301;
						$manager->add( $source, $destination, $type );
						++$imported;
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

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gpseo_save_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'garion-projetos-technical-seo-toolkit' ) );
		}

		update_option( 'gpseo_org_name', isset( $_POST['gpseo_org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gpseo_org_name'] ) ) : '' );
		update_option( 'gpseo_org_logo', isset( $_POST['gpseo_org_logo'] ) ? esc_url_raw( wp_unslash( $_POST['gpseo_org_logo'] ) ) : '' );
		update_option( 'gpseo_robots_txt_extra', isset( $_POST['gpseo_robots_txt_extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gpseo_robots_txt_extra'] ) ) : '' );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
