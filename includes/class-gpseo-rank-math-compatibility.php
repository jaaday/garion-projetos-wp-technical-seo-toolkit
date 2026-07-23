<?php
/**
 * Rank Math compatibility layer.
 *
 * Keeps the toolkit's per-post overrides useful while preventing duplicate SEO
 * output when Rank Math owns the corresponding frontend feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Rank_Math_Compatibility {

	/**
	 * Whether Rank Math is loaded.
	 */
	public static function is_active() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
	}

	/**
	 * Check an individual Rank Math module, defaulting to active when its helper
	 * is unavailable so duplicate output is still avoided.
	 */
	public static function is_module_active( $module ) {
		if ( ! self::is_active() ) {
			return false;
		}

		if ( class_exists( '\RankMath\Helper' ) && is_callable( array( '\RankMath\Helper', 'is_module_active' ) ) ) {
			return (bool) \RankMath\Helper::is_module_active( $module );
		}

		return true;
	}

	public function __construct() {
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ), 99 );
		add_filter( 'rank_math/frontend/description', array( $this, 'description' ), 99 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'robots' ), 99 );

		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'social_title' ), 99 );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'social_description' ), 99 );
		add_filter( 'rank_math/opengraph/facebook/og_image', array( $this, 'social_image' ), 99 );
		add_filter( 'rank_math/opengraph/twitter/twitter_title', array( $this, 'social_title' ), 99 );
		add_filter( 'rank_math/opengraph/twitter/twitter_description', array( $this, 'social_description' ), 99 );
		add_filter( 'rank_math/opengraph/twitter/twitter_image', array( $this, 'social_image' ), 99 );

		add_filter( 'rank_math/sitemap/posts_to_exclude', array( $this, 'exclude_noindex_posts' ), 99 );
	}

	private function current_post_id() {
		return is_singular() ? (int) get_queried_object_id() : 0;
	}

	public function canonical( $canonical ) {
		$post_id = $this->current_post_id();
		$override = $post_id ? get_post_meta( $post_id, GP_SEO_Canonical::META_KEY, true ) : '';

		return $override ? esc_url_raw( $override ) : $canonical;
	}

	public function description( $description ) {
		$post_id = $this->current_post_id();
		$override = $post_id ? get_post_meta( $post_id, '_gpseo_meta_description', true ) : '';

		return $override ? wp_strip_all_tags( $override ) : $description;
	}

	public function robots( $robots ) {
		$post_id = $this->current_post_id();
		if ( ! $post_id || ! is_array( $robots ) ) {
			return $robots;
		}

		$noindex  = (bool) get_post_meta( $post_id, GP_SEO_Robots::NOINDEX_META_KEY, true );
		$nofollow = (bool) get_post_meta( $post_id, GP_SEO_Robots::NOFOLLOW_META_KEY, true );

		if ( $noindex ) {
			$robots = array_values( array_diff( $robots, array( 'index', 'noindex' ) ) );
			$robots[] = 'noindex';
		}

		if ( $nofollow ) {
			$robots = array_values( array_diff( $robots, array( 'follow', 'nofollow' ) ) );
			$robots[] = 'nofollow';
		}

		return array_values( array_unique( $robots ) );
	}

	public function social_title( $title ) {
		return $this->social_override( GP_SEO_Social_Meta::TITLE_META_KEY, $title, 'sanitize_text_field' );
	}

	public function social_description( $description ) {
		return $this->social_override( GP_SEO_Social_Meta::DESCRIPTION_META_KEY, $description, 'sanitize_textarea_field' );
	}

	public function social_image( $image ) {
		return $this->social_override( GP_SEO_Social_Meta::IMAGE_META_KEY, $image, 'esc_url_raw' );
	}

	private function social_override( $meta_key, $fallback, $sanitize_callback ) {
		$post_id = $this->current_post_id();
		$value   = $post_id ? get_post_meta( $post_id, $meta_key, true ) : '';

		return $value ? call_user_func( $sanitize_callback, $value ) : $fallback;
	}

	public function exclude_noindex_posts( $post_ids ) {
		$excluded = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => GP_SEO_Robots::NOINDEX_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single fixed meta key used to exclude noindex posts from the sitemap; no public-facing search or scale concern.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- fixed comparison value, not user input.
			)
		);

		return array_values( array_unique( array_merge( (array) $post_ids, array_map( 'absint', $excluded ) ) ) );
	}
}
