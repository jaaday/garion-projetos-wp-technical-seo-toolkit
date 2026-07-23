<?php
define( 'ABSPATH', __DIR__ );
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
$GLOBALS['gpseo_test_filters'] = array();
function add_filter( $hook, $callback ) { $GLOBALS['gpseo_test_filters'][ $hook ][] = $callback; }
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['gpseo_test_filters'][ $hook ] ?? array() as $callback ) { $value = $callback( $value, ...$args ); }
	return $value;
}
require_once dirname( __DIR__ ) . '/includes/audit/class-gpseo-score-calculator.php';
require_once dirname( __DIR__ ) . '/includes/audit/class-gpseo-audit-result.php';
require_once dirname( __DIR__ ) . '/includes/audit/interface-gpseo-audit-check.php';
require_once dirname( __DIR__ ) . '/includes/audit/class-gpseo-audit-registry.php';
function gpseo_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
