<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Check_Title implements GPSEO_Audit_Check_Interface {
	public function get_id(): string { return 'title_length'; }
	public function get_label(): string { return __( 'SEO title length', 'garion-projetos-technical-seo-toolkit' ); }
	public function get_category(): string { return 'titles'; }
	public function get_severity(): string { return 'high'; }
	public function get_weight(): float { return 1.0; }
	public function run( GPSEO_Audit_Context $context ): GPSEO_Audit_Result {
		$resolved = $context->get_providers()->resolve_with_source( 'title', $context->get_post() );
		$title = trim( (string) $resolved['value'] );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
		if ( $length >= 30 && $length <= 60 ) { return GPSEO_Audit_Result::passed( $this->get_id(), $this->get_category(), __( 'Title length is within the recommended range.', 'garion-projetos-technical-seo-toolkit' ), $resolved['provider'] ); }
		$message = 0 === $length ? __( 'SEO title is missing.', 'garion-projetos-technical-seo-toolkit' ) : __( 'SEO title length is outside the recommended range.', 'garion-projetos-technical-seo-toolkit' );
		return new GPSEO_Audit_Result(
			$this->get_id(), $this->get_category(), $this->get_severity(), GPSEO_Audit_Result::FAIL, $this->get_weight(), $message,
			__( 'The resolved SEO title is empty or outside the configured safe range.', 'garion-projetos-technical-seo-toolkit' ),
			__( 'Use a descriptive title between 30 and 60 characters.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'length' => $length, 'resolved_title' => $title ), $this->get_label(), $title ?: __( '(empty)', 'garion-projetos-technical-seo-toolkit' ),
			__( '30 to 60 characters', 'garion-projetos-technical-seo-toolkit' ),
			__( 'The title is commonly the main search-result headline and influences relevance and click-through rate.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'target_type' => 'post_field', 'target' => 'post_title', 'label' => __( 'Edit title', 'garion-projetos-technical-seo-toolkit' ), 'edit_url' => get_edit_post_link( $context->get_post_id(), 'raw' ), 'admin_section' => __( 'WordPress title', 'garion-projetos-technical-seo-toolkit' ) ),
			$resolved['provider']
		);
	}
}
