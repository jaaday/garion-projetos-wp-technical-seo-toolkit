<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_WordPress_Provider implements GPSEO_SEO_Provider_Interface {
	public function get_id(): string { return 'wordpress'; }
	public function is_available(): bool { return true; }
	public function get_title( WP_Post $post ): ?string { return get_the_title( $post ) ?: null; }
	public function get_description( WP_Post $post ): ?string { $v = get_the_excerpt( $post ); return $v ? wp_strip_all_tags( $v ) : null; }
	public function get_canonical( WP_Post $post ): ?string { return get_permalink( $post ) ?: null; }
	public function get_robots( WP_Post $post ): array { return array(); }
	public function get_social( WP_Post $post ): array { return array(); }
}
