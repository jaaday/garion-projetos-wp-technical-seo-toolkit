<?php
/**
 * Canonical URL control: lets an editor override the canonical URL per post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Canonical {

	const META_KEY = '_gpseo_canonical_url';

	public function __construct() {
		if ( GP_SEO_Rank_Math_Compatibility::is_active() ) {
			return;
		}

		add_filter( 'get_canonical_url', array( $this, 'filter_canonical' ), 10, 2 );
	}

	public function filter_canonical( $canonical_url, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return $canonical_url;
		}

		$override = get_post_meta( $post->ID, self::META_KEY, true );

		return $override ? esc_url_raw( $override ) : $canonical_url;
	}
}
