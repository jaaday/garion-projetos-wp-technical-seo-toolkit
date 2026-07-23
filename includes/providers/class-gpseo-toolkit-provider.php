<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Toolkit_Provider implements GPSEO_SEO_Provider_Interface {
	public function get_id(): string { return 'toolkit'; }
	public function is_available(): bool { return true; }
	public function get_title( WP_Post $post ): ?string { $v = get_post_meta( $post->ID, GP_SEO_Social_Meta::TITLE_META_KEY, true ); return $v ? (string) $v : null; }
	public function get_description( WP_Post $post ): ?string { $v = get_post_meta( $post->ID, '_gpseo_meta_description', true ); return $v ? (string) $v : null; }
	public function get_canonical( WP_Post $post ): ?string { $v = get_post_meta( $post->ID, GP_SEO_Canonical::META_KEY, true ); return $v ? (string) $v : null; }
	public function get_robots( WP_Post $post ): array { $r = array(); if ( get_post_meta( $post->ID, GP_SEO_Robots::NOINDEX_META_KEY, true ) ) { $r[] = 'noindex'; } if ( get_post_meta( $post->ID, GP_SEO_Robots::NOFOLLOW_META_KEY, true ) ) { $r[] = 'nofollow'; } return $r; }
	public function get_social( WP_Post $post ): array { return array_filter( GP_SEO_Social_Meta::get_data( $post ) ); }
}
