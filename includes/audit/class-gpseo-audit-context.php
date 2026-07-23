<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Audit_Context {
	private WP_Post $post;
	private GPSEO_Provider_Registry $providers;
	private array $runtime;
	public function __construct( WP_Post $post, GPSEO_Provider_Registry $providers, array $runtime = array() ) {
		$this->post = $post; $this->providers = $providers; $this->runtime = $runtime;
	}
	public function get_post(): WP_Post { return $this->post; }
	public function get_post_id(): int { return (int) $this->post->ID; }
	public function get_url(): string { return (string) get_permalink( $this->post ); }
	public function get_providers(): GPSEO_Provider_Registry { return $this->providers; }
	public function get_runtime( string $key, $default = null ) { return array_key_exists( $key, $this->runtime ) ? $this->runtime[ $key ] : $default; }
}
