<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class GPSEO_Audit_Registry {
	private array $checks = array();
	public function register( GPSEO_Audit_Check_Interface $check ): void { $this->checks[ $check->get_id() ] = $check; }
	public function all(): array {
		$checks = apply_filters( 'gpseo_audit_checks', array_values( $this->checks ) );
		$valid = array();
		foreach ( $checks as $check ) { if ( $check instanceof GPSEO_Audit_Check_Interface ) { $valid[ $check->get_id() ] = $check; } }
		return array_values( $valid );
	}
}
