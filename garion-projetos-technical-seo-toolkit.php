<?php
/**
 * Plugin Name: Garion Projetos - Technical SEO Toolkit
 * Description: Independent technical SEO tools for WordPress: redirects, 404 monitoring, broken-link detection, auditing, metadata controls and optional third-party interoperability.
 * Version: 0.6.2
 * Author: Garion Projetos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: garion-projetos-technical-seo-toolkit
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPSEO_VERSION', '0.6.2' );
define( 'GPSEO_DB_VERSION', '0.5.1' );
define( 'GPSEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'GPSEO_URL', plugin_dir_url( __FILE__ ) );

require_once GPSEO_PATH . 'includes/class-gpseo-redirects.php';
require_once GPSEO_PATH . 'includes/class-gpseo-404-monitor.php';
require_once GPSEO_PATH . 'includes/class-gpseo-broken-links.php';
require_once GPSEO_PATH . 'includes/class-gpseo-sitemap.php';
require_once GPSEO_PATH . 'includes/class-gpseo-structured-data.php';
require_once GPSEO_PATH . 'includes/class-gpseo-canonical.php';
require_once GPSEO_PATH . 'includes/class-gpseo-robots.php';
require_once GPSEO_PATH . 'includes/class-gpseo-social-meta.php';
require_once GPSEO_PATH . 'includes/class-gpseo-rank-math-compatibility.php';
require_once GPSEO_PATH . 'includes/providers/interface-gpseo-seo-provider.php';
require_once GPSEO_PATH . 'includes/providers/class-gpseo-toolkit-provider.php';
require_once GPSEO_PATH . 'includes/providers/class-gpseo-rank-math-provider.php';
require_once GPSEO_PATH . 'includes/providers/class-gpseo-yoast-provider.php';
require_once GPSEO_PATH . 'includes/providers/class-gpseo-wordpress-provider.php';
require_once GPSEO_PATH . 'includes/providers/class-gpseo-provider-registry.php';
require_once GPSEO_PATH . 'includes/audit/class-gpseo-score-calculator.php';
require_once GPSEO_PATH . 'includes/audit/class-gpseo-audit-result.php';
require_once GPSEO_PATH . 'includes/audit/class-gpseo-audit-context.php';
require_once GPSEO_PATH . 'includes/audit/interface-gpseo-audit-check.php';
require_once GPSEO_PATH . 'includes/audit/class-gpseo-audit-registry.php';
require_once GPSEO_PATH . 'includes/audit/checks/class-gpseo-check-title.php';
require_once GPSEO_PATH . 'includes/audit/checks/class-gpseo-check-description.php';
require_once GPSEO_PATH . 'includes/audit/checks/class-gpseo-check-featured-image.php';
require_once GPSEO_PATH . 'includes/audit/checks/class-gpseo-check-indexability.php';
require_once GPSEO_PATH . 'includes/audit/class-gpseo-audit-repository.php';
require_once GPSEO_PATH . 'includes/audit/class-gpseo-audit-runner.php';
require_once GPSEO_PATH . 'includes/class-gpseo-audit.php';
require_once GPSEO_PATH . 'includes/class-gpseo-rest-controller.php';
require_once GPSEO_PATH . 'admin/class-gpseo-admin-ui.php';
require_once GPSEO_PATH . 'admin/class-gpseo-metabox.php';
require_once GPSEO_PATH . 'admin/class-gpseo-admin-page.php';

register_activation_hook( __FILE__, 'gpseo_activate' );
register_deactivation_hook( __FILE__, 'gpseo_deactivate' );

function gpseo_activate() {
	GP_SEO_Redirects::activate();
	GP_SEO_404_Monitor::activate();
	GP_SEO_Broken_Links::activate();
	GPSEO_Audit_Repository::activate();
	GPSEO_Audit_Runner::activate();
	if ( ! GP_SEO_Rank_Math_Compatibility::is_module_active( 'sitemap' ) ) {
		GP_SEO_Sitemap::register_rewrite_rules();
	}
	update_option( 'gpseo_db_version', GPSEO_DB_VERSION );
	flush_rewrite_rules();
}

function gpseo_deactivate() {
	GP_SEO_404_Monitor::deactivate();
	GP_SEO_Broken_Links::deactivate();
	GPSEO_Audit_Runner::deactivate();
	flush_rewrite_rules();
}

/**
 * Run schema upgrades after a plugin update, not only on first activation.
 */
function gpseo_maybe_upgrade() {
	if ( GPSEO_DB_VERSION === get_option( 'gpseo_db_version' ) ) {
		return;
	}

	GP_SEO_Redirects::activate();
	GP_SEO_404_Monitor::activate();
	GP_SEO_Broken_Links::activate();
	GPSEO_Audit_Repository::activate();
	GPSEO_Audit_Runner::activate();
	update_option( 'gpseo_db_version', GPSEO_DB_VERSION );
	update_option( 'gpseo_flush_rewrite_rules', '1', false );
}

/**
 * Flush rewrite rules only after WordPress has initialized its rewrite engine.
 */
function gpseo_maybe_flush_rewrite_rules() {
	if ( '1' !== get_option( 'gpseo_flush_rewrite_rules' ) ) {
		return;
	}

	if ( ! GP_SEO_Rank_Math_Compatibility::is_module_active( 'sitemap' ) ) {
		GP_SEO_Sitemap::register_rewrite_rules();
	}

	flush_rewrite_rules( false );
	delete_option( 'gpseo_flush_rewrite_rules' );
}

/**
 * Load the plugin's own .mo files. Not on wordpress.org, so translations are
 * never fetched automatically and must ship in /languages.
 */
function gpseo_load_textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- this plugin is not distributed via wordpress.org, so WP never auto-loads its translations; this call is required for the bundled languages/*.mo files to load at all.
	load_plugin_textdomain( 'garion-projetos-technical-seo-toolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'gpseo_load_textdomain' );
add_action( 'plugins_loaded', 'gpseo_maybe_upgrade', 5 );
add_action( 'init', 'gpseo_maybe_flush_rewrite_rules', 99 );
add_action( 'plugins_loaded', 'gpseo_init', 20 );

function gpseo_init() {
	new GP_SEO_Redirects();
	new GP_SEO_404_Monitor();
	new GP_SEO_Broken_Links();
	new GP_SEO_Sitemap();
	new GP_SEO_Structured_Data();
	new GP_SEO_Canonical();
	new GP_SEO_Robots();
	new GP_SEO_Social_Meta();
	$GLOBALS['gpseo_audit_runner'] = new GPSEO_Audit_Runner();
	new GP_SEO_REST_Controller();

	if ( GP_SEO_Rank_Math_Compatibility::is_active() ) {
		new GP_SEO_Rank_Math_Compatibility();
	}

	if ( is_admin() ) {
		new GP_SEO_Metabox();
		new GP_SEO_Admin_Page();
	}
}
