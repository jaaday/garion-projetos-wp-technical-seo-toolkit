<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Check_Featured_Image implements GPSEO_Audit_Check_Interface {
	public function get_id(): string { return 'featured_image'; }
	public function get_label(): string { return __( 'Featured image', 'garion-projetos-technical-seo-toolkit' ); }
	public function get_category(): string { return 'images'; }
	public function get_severity(): string { return 'low'; }
	public function get_weight(): float { return 1.0; }
	public function run( GPSEO_Audit_Context $context ): GPSEO_Audit_Result {
		if ( has_post_thumbnail( $context->get_post() ) ) { return GPSEO_Audit_Result::passed( $this->get_id(), $this->get_category(), __( 'Featured image is set.', 'garion-projetos-technical-seo-toolkit' ), 'wordpress' ); }
		return new GPSEO_Audit_Result(
			$this->get_id(), $this->get_category(), $this->get_severity(), GPSEO_Audit_Result::FAIL, $this->get_weight(), __( 'Featured image is missing.', 'garion-projetos-technical-seo-toolkit' ),
			__( 'The content has no WordPress featured image assigned.', 'garion-projetos-technical-seo-toolkit' ),
			__( 'Set a relevant featured image with descriptive alternative text.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'thumbnail_id' => 0 ), $this->get_label(), __( 'No featured image', 'garion-projetos-technical-seo-toolkit' ), __( 'One relevant featured image', 'garion-projetos-technical-seo-toolkit' ),
			__( 'A representative image improves social previews and visual content discovery.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'target_type' => 'featured_image', 'target' => '_thumbnail_id', 'label' => __( 'Set featured image', 'garion-projetos-technical-seo-toolkit' ), 'edit_url' => get_edit_post_link( $context->get_post_id(), 'raw' ), 'admin_section' => __( 'Editor → Featured image', 'garion-projetos-technical-seo-toolkit' ) ),
			'wordpress'
		);
	}
}
