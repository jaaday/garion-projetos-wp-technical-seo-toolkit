<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Check_Indexability implements GPSEO_Audit_Check_Interface {
	public function get_id(): string { return 'published_noindex'; }
	public function get_label(): string { return __( 'Published content indexability', 'garion-projetos-technical-seo-toolkit' ); }
	public function get_category(): string { return 'indexability'; }
	public function get_severity(): string { return 'recommendation'; }
	public function get_weight(): float { return 1.0; }
	public function run( GPSEO_Audit_Context $context ): GPSEO_Audit_Result {
		$resolved = $context->get_providers()->resolve_with_source( 'robots', $context->get_post() );
		$robots = (array) $resolved['value'];
		if ( ! in_array( 'noindex', $robots, true ) ) { return GPSEO_Audit_Result::passed( $this->get_id(), $this->get_category(), __( 'Published content is indexable.', 'garion-projetos-technical-seo-toolkit' ), $resolved['provider'] ); }
		return new GPSEO_Audit_Result(
			$this->get_id(), $this->get_category(), $this->get_severity(), GPSEO_Audit_Result::FAIL, $this->get_weight(), __( 'Published content is marked noindex.', 'garion-projetos-technical-seo-toolkit' ),
			__( 'A robots directive prevents this published URL from being indexable.', 'garion-projetos-technical-seo-toolkit' ),
			__( 'Confirm that excluding this content from search results is intentional.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'robots' => $robots ), $this->get_label(), implode( ', ', $robots ), __( 'Indexable unless exclusion is intentional', 'garion-projetos-technical-seo-toolkit' ),
			__( 'An unintended noindex directive can remove an important URL from search results.', 'garion-projetos-technical-seo-toolkit' ),
			array( 'target_type' => 'seo_metabox', 'target' => 'gpseo_noindex', 'label' => __( 'Review indexability', 'garion-projetos-technical-seo-toolkit' ), 'edit_url' => get_edit_post_link( $context->get_post_id(), 'raw' ), 'admin_section' => __( 'Garion Projetos - Technical SEO Toolkit → Noindex', 'garion-projetos-technical-seo-toolkit' ) ),
			$resolved['provider']
		);
	}
}
