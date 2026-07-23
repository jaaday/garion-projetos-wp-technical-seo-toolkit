<?php
require __DIR__ . '/bootstrap.php';
$results = array(
	new GPSEO_Audit_Result( 'a', 'metadata', 'critical', GPSEO_Audit_Result::FAIL, 1.0, 'A' ),
	new GPSEO_Audit_Result( 'b', 'metadata', 'high', GPSEO_Audit_Result::FAIL, 1.0, 'B' ),
	new GPSEO_Audit_Result( 'c', 'links', 'medium', GPSEO_Audit_Result::FAIL, 1.0, 'C' ),
	new GPSEO_Audit_Result( 'd', 'content', 'recommendation', GPSEO_Audit_Result::FAIL, 1.0, 'D' ),
);
$score = ( new GPSEO_Score_Calculator() )->calculate( $results );
gpseo_assert( 64 === $score['score'], 'Weighted score or category cap is incorrect.' );
gpseo_assert( 30.0 === $score['category_penalties']['metadata'], 'Category penalty must be capped at 30.' );
gpseo_assert( 4 === $score['failed_checks'], 'All failed checks must be counted.' );
gpseo_assert( 20.0 === $score['breakdown']['a']['applied_penalty'], 'Critical penalty must be persisted exactly.' );
gpseo_assert( 10.0 === $score['breakdown']['b']['applied_penalty'], 'Second metadata penalty must be reduced by the category cap.' );
gpseo_assert( true === $score['breakdown']['b']['capped'], 'Capped checks must be explicitly marked.' );
echo "Score calculator tests passed.\n";
