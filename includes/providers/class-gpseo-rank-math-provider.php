<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Rank_Math_Provider implements GPSEO_SEO_Provider_Interface {
	public function get_id(): string { return 'rank-math'; }
	public function is_available(): bool { return GP_SEO_Rank_Math_Compatibility::is_active(); }
	private function meta( WP_Post $post, string $key ): ?string { $v = get_post_meta( $post->ID, $key, true ); return is_scalar( $v ) && '' !== trim( (string) $v ) ? (string) $v : null; }
	public function get_title( WP_Post $post ): ?string { return $this->meta( $post, 'rank_math_title' ); }
	public function get_description( WP_Post $post ): ?string { return $this->meta( $post, 'rank_math_description' ); }
	public function get_canonical( WP_Post $post ): ?string { return $this->meta( $post, 'rank_math_canonical_url' ); }
	public function get_robots( WP_Post $post ): array { $v = get_post_meta( $post->ID, 'rank_math_robots', true ); return is_array( $v ) ? array_map( 'sanitize_key', $v ) : array(); }
	public function get_social( WP_Post $post ): array { return array_filter( array( 'title' => $this->meta( $post, 'rank_math_facebook_title' ), 'description' => $this->meta( $post, 'rank_math_facebook_description' ), 'image' => $this->meta( $post, 'rank_math_facebook_image' ) ) ); }
}
