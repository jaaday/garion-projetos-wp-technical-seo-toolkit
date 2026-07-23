<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Provider_Registry {
	private array $providers;
	public function __construct( array $providers = array() ) {
		$this->providers = $providers ? $providers : array( new GPSEO_Toolkit_Provider(), new GPSEO_Rank_Math_Provider(), new GPSEO_Yoast_Provider(), new GPSEO_WordPress_Provider() );
	}
	public function providers(): array {
		$providers = apply_filters( 'gpseo_providers', $this->providers );
		return array_values( array_filter( $providers, static fn( $provider ) => $provider instanceof GPSEO_SEO_Provider_Interface && $provider->is_available() ) );
	}
	public function resolve( string $field, WP_Post $post ) {
		return $this->resolve_with_source( $field, $post )['value'];
	}

	public function resolve_with_source( string $field, WP_Post $post ): array {
		$method = 'get_' . sanitize_key( $field );
		foreach ( $this->providers() as $provider ) {
			if ( ! is_callable( array( $provider, $method ) ) ) { continue; }
			$value = $provider->{$method}( $post );
			if ( is_array( $value ) ? ! empty( $value ) : null !== $value && '' !== trim( (string) $value ) ) {
				return array( 'value' => $value, 'provider' => $provider->get_id() );
			}
		}
		return array( 'value' => in_array( $field, array( 'robots', 'social' ), true ) ? array() : null, 'provider' => 'unknown' );
	}
}
