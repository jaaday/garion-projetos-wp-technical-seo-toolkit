<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Yoast_Provider implements GPSEO_SEO_Provider_Interface {
	public function get_id(): string { return 'yoast'; }
	public function is_available(): bool { return defined( 'WPSEO_VERSION' ); }
	private function meta( WP_Post $post, string $key ): ?string { $v = get_post_meta( $post->ID, $key, true ); return is_scalar( $v ) && '' !== trim( (string) $v ) ? (string) $v : null; }
	public function get_title( WP_Post $post ): ?string { return $this->meta( $post, '_yoast_wpseo_title' ); }
	public function get_description( WP_Post $post ): ?string { return $this->meta( $post, '_yoast_wpseo_metadesc' ); }
	public function get_canonical( WP_Post $post ): ?string { return $this->meta( $post, '_yoast_wpseo_canonical' ); }
	public function get_robots( WP_Post $post ): array { $r = array(); if ( '1' === $this->meta( $post, '_yoast_wpseo_meta-robots-noindex' ) ) { $r[] = 'noindex'; } if ( '1' === $this->meta( $post, '_yoast_wpseo_meta-robots-nofollow' ) ) { $r[] = 'nofollow'; } return $r; }
	public function get_social( WP_Post $post ): array { return array_filter( array( 'title' => $this->meta( $post, '_yoast_wpseo_opengraph-title' ), 'description' => $this->meta( $post, '_yoast_wpseo_opengraph-description' ), 'image' => $this->meta( $post, '_yoast_wpseo_opengraph-image' ) ) ); }
}
