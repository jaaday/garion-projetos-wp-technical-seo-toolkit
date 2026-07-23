<?php
require __DIR__ . '/bootstrap.php';
final class GPSEO_Test_Check implements GPSEO_Audit_Check_Interface {
	private string $id;
	public function __construct( string $id ) { $this->id = $id; }
	public function get_id(): string { return $this->id; }
	public function get_label(): string { return $this->id; }
	public function get_category(): string { return 'test'; }
	public function get_severity(): string { return 'low'; }
	public function get_weight(): float { return 1.0; }
	public function run( GPSEO_Audit_Context $context ): GPSEO_Audit_Result { return GPSEO_Audit_Result::passed( $this->id, 'test' ); }
}
$registry = new GPSEO_Audit_Registry();
$registry->register( new GPSEO_Test_Check( 'one' ) );
$registry->register( new GPSEO_Test_Check( 'one' ) );
add_filter( 'gpseo_audit_checks', static function ( $checks ) { $checks[] = new GPSEO_Test_Check( 'two' ); return $checks; } );
$checks = $registry->all();
gpseo_assert( 2 === count( $checks ), 'Registry must deduplicate built-ins and accept filtered checks.' );
gpseo_assert( 'two' === $checks[1]->get_id(), 'Public check filter did not run.' );
echo "Audit registry tests passed.\n";
