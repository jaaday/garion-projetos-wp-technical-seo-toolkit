<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Check_Description implements GPSEO_Audit_Check_Interface {
	public function get_id(): string { return 'meta_description_length'; }
	public function get_label(): string { return __( 'Meta description', 'garion-projetos-technical-seo-toolkit' ); }
	public function get_category(): string { return 'metadata'; }
	public function get_severity(): string { return 'medium'; }
	public function get_weight(): float { return 1.0; }
	public function run( GPSEO_Audit_Context $context ): GPSEO_Audit_Result {
		$resolved = $context->get_providers()->resolve_with_source( 'description', $context->get_post() );
		$value = trim( wp_strip_all_tags( strip_shortcodes( (string) $resolved['value'] ) ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		if ( $length >= 70 && $length <= 160 ) { return GPSEO_Audit_Result::passed( $this->get_id(), $this->get_category(), __( 'Meta description is within the recommended range.', 'garion-projetos-technical-seo-toolkit' ), $resolved['provider'] ); }
		$message = 0 === $length ? __( 'Meta description is missing.', 'garion-projetos-technical-seo-toolkit' ) : __( 'Meta description length needs review.', 'garion-projetos-technical-seo-toolkit' );
		return new GPSEO_Audit_Result(
			$this->get_id(), $this->get_category(), $this->get_severity(), GPSEO_Audit_Result::FAIL, $this->get_weight(), $message,
			__( 'The resolved description is empty or outside the recommended range after removing HTML and shortcodes.', 'garion-projetos-technical-seo-toolkit' ),
			__( 'Write a unique description between 70 and 160 characters.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'length' => $length, 'resolved_description' => $value ), $this->get_label(), $value ?: __( '(empty)', 'garion-projetos-technical-seo-toolkit' ),
			__( '70 to 160 characters of useful text', 'garion-projetos-technical-seo-toolkit' ),
			__( 'A useful description helps searchers understand the page before visiting it.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'target_type' => 'seo_metabox', 'target' => 'gpseo_meta_description', 'label' => __( 'Edit description', 'garion-projetos-technical-seo-toolkit' ), 'edit_url' => get_edit_post_link( $context->get_post_id(), 'raw' ), 'admin_section' => __( 'Garion Projetos - Technical SEO Toolkit → Meta description', 'garion-projetos-technical-seo-toolkit' ) ),
			$resolved['provider']
		);
	}
}
