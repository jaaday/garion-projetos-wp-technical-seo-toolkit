<?php
/**
 * Deterministic Schema.org output for sites that do not use Rank Math Schema.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Structured_Data {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 5 );
	}

	public function output() {
		if ( GP_SEO_Rank_Math_Compatibility::is_module_active( 'rich-snippet' ) || ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$permalink      = get_permalink( $post );
		$website_id     = trailingslashit( home_url( '/' ) ) . '#website';
		$organization_id = trailingslashit( home_url( '/' ) ) . '#organization';
		$author         = get_userdata( $post->post_author );
		$image          = get_the_post_thumbnail_url( $post, 'full' );
		$logo           = esc_url_raw( get_option( 'gpseo_org_logo', '' ) );

		$content = array(
			'@type'         => 'post' === $post->post_type ? 'Article' : 'WebPage',
			'@id'           => $permalink . '#content',
			'headline'      => get_the_title( $post ),
			'description'   => $this->get_description( $post ),
			'url'           => $permalink,
			'datePublished' => get_the_date( DATE_W3C, $post ),
			'dateModified'  => get_the_modified_date( DATE_W3C, $post ),
			'inLanguage'    => get_bloginfo( 'language' ),
			'isPartOf'      => array( '@id' => $website_id ),
			'mainEntityOfPage' => array( '@id' => $permalink ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => $author ? $author->display_name : get_bloginfo( 'name' ),
				'url'   => $author ? get_author_posts_url( $author->ID ) : home_url( '/' ),
			),
		);

		if ( 'post' === $post->post_type ) {
			$content['publisher'] = array( '@id' => $organization_id );
		}
		if ( $image ) {
			$content['image'] = array( '@type' => 'ImageObject', 'url' => esc_url_raw( $image ) );
		}

		$organization = array(
			'@type' => 'Organization',
			'@id'   => $organization_id,
			'name'  => get_option( 'gpseo_org_name', get_bloginfo( 'name' ) ),
			'url'   => home_url( '/' ),
		);
		if ( $logo ) {
			$organization['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$content,
				array(
					'@type'      => 'WebSite',
					'@id'        => $website_id,
					'name'       => get_bloginfo( 'name' ),
					'url'        => home_url( '/' ),
					'publisher'  => array( '@id' => $organization_id ),
					'inLanguage' => get_bloginfo( 'language' ),
				),
				$organization,
			),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode safely serializes the server-built graph.
	}

	private function get_description( $post ) {
		$meta_description = get_post_meta( $post->ID, '_gpseo_meta_description', true );
		if ( $meta_description ) {
			return wp_strip_all_tags( $meta_description );
		}

		$excerpt = get_the_excerpt( $post );
		return $excerpt ? wp_strip_all_tags( $excerpt ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
	}
}
