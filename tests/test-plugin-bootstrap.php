<?php
define( 'ABSPATH', __DIR__ . '/' );
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/plugin/'; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function add_action() {}
function add_filter() {}
require dirname( __DIR__ ) . '/garion-projetos-technical-seo-toolkit.php';
if ( ! class_exists( 'GPSEO_Audit_Runner' ) || ! class_exists( 'GPSEO_Audit_Repository' ) || ! class_exists( 'GP_SEO_REST_Controller' ) ) {
	throw new RuntimeException( 'Plugin foundation classes did not load.' );
}
echo "Plugin bootstrap test passed.\n";
