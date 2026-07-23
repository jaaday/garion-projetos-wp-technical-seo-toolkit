<?php
/**
 * Weighted SEO score with explainable category caps.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GPSEO_Score_Calculator {

	public const INITIAL_SCORE = 100.0;
	public const CATEGORY_CAP = 30.0;

	public static function default_weights(): array {
		return array( 'critical' => 20.0, 'high' => 12.0, 'medium' => 6.0, 'low' => 2.0, 'recommendation' => 0.0, 'informational' => 0.0 );
	}

	public static function normalize_severity( string $severity ): string {
		$severity = sanitize_key( $severity );
		return array_key_exists( $severity, self::default_weights() ) ? $severity : 'informational';
	}

	public function calculate( array $results ): array {
		$weights = apply_filters( 'gpseo_score_weights', self::default_weights() );
		$category_penalties = array();
		$breakdown = array();
		$failed = 0;

		foreach ( $results as $result ) {
			if ( ! $result instanceof GPSEO_Audit_Result ) { continue; }
			$raw = 0.0; $applied = 0.0;
			if ( $result->is_failure() ) {
				$failed++;
				$category = $result->get_category();
				$base = isset( $weights[ $result->get_severity() ] ) ? max( 0.0, (float) $weights[ $result->get_severity() ] ) : 0.0;
				$raw = $base * max( 0.0, min( 2.0, $result->get_weight() ) );
				$current = (float) ( $category_penalties[ $category ] ?? 0.0 );
				$applied = min( $raw, max( 0.0, self::CATEGORY_CAP - $current ) );
				$category_penalties[ $category ] = $current + $applied;
			}
			$breakdown[ $result->get_check_id() ] = array( 'raw_penalty' => round( $raw, 2 ), 'applied_penalty' => round( $applied, 2 ), 'capped' => $applied < $raw );
		}

		$total = min( self::INITIAL_SCORE, array_sum( $category_penalties ) );
		$category_scores = array();
		foreach ( $category_penalties as $category => $penalty ) {
			$category_scores[ $category ] = (int) round( max( 0.0, self::INITIAL_SCORE - ( $penalty / self::CATEGORY_CAP * self::INITIAL_SCORE ) ) );
		}

		return array(
			'initial_score' => self::INITIAL_SCORE, 'score' => (int) round( self::INITIAL_SCORE - $total ),
			'failed_checks' => $failed, 'total_penalty' => round( $total, 2 ),
			'category_cap' => self::CATEGORY_CAP, 'category_penalties' => $category_penalties,
			'category_scores' => $category_scores, 'breakdown' => $breakdown,
		);
	}
}
