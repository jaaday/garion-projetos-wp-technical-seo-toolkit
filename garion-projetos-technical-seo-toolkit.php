<?php
/**
 * Plugin Name: Garion Projetos Technical SEO Toolkit
 * Description: Technical SEO tools for WordPress: redirects, 404 monitor, broken link detection, XML sitemap, structured data, canonical control, robots configuration and Open Graph/Twitter Card tags.
 * Version: 0.3.0
 * Author: Garion Projetos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: garion-projetos-technical-seo-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPSEO_VERSION', '0.3.0' );
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
require_once GPSEO_PATH . 'includes/class-gpseo-audit.php';
require_once GPSEO_PATH . 'includes/class-gpseo-rest-controller.php';
require_once GPSEO_PATH . 'admin/class-gpseo-metabox.php';
require_once GPSEO_PATH . 'admin/class-gpseo-admin-page.php';

register_activation_hook(
	__FILE__,
	static function () {
		GP_SEO_Redirects::activate();
		GP_SEO_404_Monitor::activate();
		GP_SEO_Broken_Links::activate();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		GP_SEO_404_Monitor::deactivate();
		GP_SEO_Broken_Links::deactivate();
		flush_rewrite_rules();
	}
);

add_action( 'plugins_loaded', 'gpseo_init' );

function gpseo_init() {
	new GP_SEO_Redirects();
	new GP_SEO_404_Monitor();
	new GP_SEO_Broken_Links();
	new GP_SEO_Sitemap();
	new GP_SEO_Structured_Data();
	new GP_SEO_Canonical();
	new GP_SEO_Robots();
	new GP_SEO_Social_Meta();
	new GP_SEO_REST_Controller();

	if ( is_admin() ) {
		new GP_SEO_Metabox();
		new GP_SEO_Admin_Page();
	}
}
