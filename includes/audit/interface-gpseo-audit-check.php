<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
interface GPSEO_Audit_Check_Interface {
	public function get_id(): string;
	public function get_label(): string;
	public function get_category(): string;
	public function get_severity(): string;
	public function get_weight(): float;
	public function run( GPSEO_Audit_Context $context ): GPSEO_Audit_Result;
}
