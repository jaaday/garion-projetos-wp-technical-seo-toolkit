<?php
/**
 * Open Graph and Twitter Card meta tags for singular content, with per-post overrides.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Social_Meta {

	const TITLE_META_KEY       = '_gpseo_og_title';
	const DESCRIPTION_META_KEY = '_gpseo_og_description';
	const IMAGE_META_KEY       = '_gpseo_og_image';

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 4 );
	}

	public function output() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$data = self::get_data( $post );

		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $data['type'] ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $data['title'] ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $data['url'] ) );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );

		if ( $data['image'] ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $data['image'] ) );
		}

		printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( $data['image'] ? 'summary_large_image' : 'summary' ) );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $data['title'] ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $data['description'] ) );

		if ( $data['image'] ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $data['image'] ) );
		}
	}

	/**
	 * @return array {type, title, description, image, url}
	 */
	public static function get_data( $post ) {
		$title       = get_post_meta( $post->ID, self::TITLE_META_KEY, true );
		$description = get_post_meta( $post->ID, self::DESCRIPTION_META_KEY, true );
		$image       = get_post_meta( $post->ID, self::IMAGE_META_KEY, true );

		if ( ! $title ) {
			$title = get_the_title( $post );
		}

		if ( ! $description ) {
			$description = get_post_meta( $post->ID, '_gpseo_meta_description', true );
		}

		if ( ! $description ) {
			$excerpt     = get_the_excerpt( $post );
			$description = $excerpt ? wp_strip_all_tags( $excerpt ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}

		if ( ! $image ) {
			$image = get_the_post_thumbnail_url( $post, 'large' );
		}

		return array(
			'type'        => 'post' === $post->post_type ? 'article' : 'website',
			'title'       => $title,
			'description' => $description,
			'image'       => $image ? $image : '',
			'url'         => get_permalink( $post ),
		);
	}
}
