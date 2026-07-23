<?php
/**
 * Meta robots (per post) and robots.txt (site-wide extra rules) control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Robots {

	const NOINDEX_META_KEY  = '_gpseo_noindex';
	const NOFOLLOW_META_KEY = '_gpseo_nofollow';

	public function __construct() {
		if ( ! GP_SEO_Rank_Math_Compatibility::is_active() ) {
			add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ) );
		}

		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
	}

	public function filter_wp_robots( $robots ) {
		if ( ! is_singular() ) {
			return $robots;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $robots;
		}

		if ( get_post_meta( $post_id, self::NOINDEX_META_KEY, true ) ) {
			unset( $robots['index'], $robots['follow'] );
			$robots['noindex'] = true;
		}

		if ( get_post_meta( $post_id, self::NOFOLLOW_META_KEY, true ) ) {
			unset( $robots['follow'] );
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	public function filter_robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$extra_rules = trim( (string) get_option( 'gpseo_robots_txt_extra', '' ) );
		if ( $extra_rules && false === strpos( $output, $extra_rules ) ) {
			$output .= "\n" . $extra_rules . "\n";
		}

		$sitemap_line = 'Sitemap: ' . GP_SEO_Sitemap::sitemap_url();
		if ( false === strpos( $output, $sitemap_line ) ) {
			$output .= "\n" . $sitemap_line . "\n";
		}

		return $output;
	}
}
