<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
interface GPSEO_SEO_Provider_Interface {
	public function get_id(): string;
	public function is_available(): bool;
	public function get_title( WP_Post $post ): ?string;
	public function get_description( WP_Post $post ): ?string;
	public function get_canonical( WP_Post $post ): ?string;
	public function get_robots( WP_Post $post ): array;
	public function get_social( WP_Post $post ): array;
}
