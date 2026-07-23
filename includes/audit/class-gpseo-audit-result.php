<?php
/**
 * Normalized and explainable result returned by every audit check.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GPSEO_Audit_Result {

	public const PASS = 'pass';
	public const FAIL = 'fail';
	public const INCONCLUSIVE = 'inconclusive';
	public const NOT_APPLICABLE = 'not_applicable';

	private const TARGET_TYPES = array(
		'post_field', 'post_content', 'featured_image', 'media_attachment', 'seo_metabox',
		'toolkit_settings', 'permalink_settings', 'theme_template', 'robots_settings',
		'sitemap_settings', 'external_plugin', 'manual_review',
	);

	private string $check_id;
	private string $category;
	private string $severity;
	private string $status;
	private float $weight;
	private string $title;
	private string $message;
	private string $explanation;
	private string $why_matters;
	private string $recommendation;
	private string $found_value;
	private string $expected_value;
	private array $evidence;
	private array $remediation;
	private string $source_provider;

	public function __construct(
		string $check_id,
		string $category,
		string $severity,
		string $status,
		float $weight,
		string $message,
		string $explanation = '',
		string $recommendation = '',
		array $evidence = array(),
		string $title = '',
		string $found_value = '',
		string $expected_value = '',
		string $why_matters = '',
		array $remediation = array(),
		string $source_provider = 'toolkit'
	) {
		$allowed = array( self::PASS, self::FAIL, self::INCONCLUSIVE, self::NOT_APPLICABLE );
		$this->check_id = sanitize_key( $check_id );
		$this->category = sanitize_key( $category );
		$this->severity = GPSEO_Score_Calculator::normalize_severity( $severity );
		$this->status = in_array( $status, $allowed, true ) ? $status : self::INCONCLUSIVE;
		$this->weight = max( 0.0, min( 2.0, $weight ) );
		$this->title = sanitize_text_field( $title ?: $message );
		$this->message = sanitize_text_field( $message );
		$this->explanation = sanitize_textarea_field( $explanation );
		$this->why_matters = sanitize_textarea_field( $why_matters ?: $explanation );
		$this->recommendation = sanitize_textarea_field( $recommendation );
		$this->found_value = sanitize_textarea_field( $found_value );
		$this->expected_value = sanitize_textarea_field( $expected_value );
		$this->evidence = $evidence;
		$this->remediation = self::sanitize_remediation( $remediation );
		$this->source_provider = sanitize_key( $source_provider ?: 'unknown' );
	}

	public static function passed( string $check_id, string $category, string $message = '', string $source_provider = 'toolkit' ): self {
		return new self( $check_id, $category, 'informational', self::PASS, 0.0, $message, '', '', array(), $message, '', '', '', array(), $source_provider );
	}

	private static function sanitize_remediation( array $remediation ): array {
		$type = sanitize_key( (string) ( $remediation['target_type'] ?? 'manual_review' ) );
		if ( ! in_array( $type, self::TARGET_TYPES, true ) ) { $type = 'manual_review'; }
		return array(
			'target_type' => $type,
			'target' => sanitize_key( (string) ( $remediation['target'] ?? '' ) ),
			'label' => sanitize_text_field( (string) ( $remediation['label'] ?? '' ) ),
			'edit_url' => esc_url_raw( (string) ( $remediation['edit_url'] ?? '' ) ),
			'admin_section' => sanitize_text_field( (string) ( $remediation['admin_section'] ?? '' ) ),
		);
	}

	public function get_check_id(): string { return $this->check_id; }
	public function get_category(): string { return $this->category; }
	public function get_severity(): string { return $this->severity; }
	public function get_status(): string { return $this->status; }
	public function get_weight(): float { return $this->weight; }
	public function get_title(): string { return $this->title; }
	public function get_message(): string { return $this->message; }
	public function get_explanation(): string { return $this->explanation; }
	public function get_why_matters(): string { return $this->why_matters; }
	public function get_recommendation(): string { return $this->recommendation; }
	public function get_found_value(): string { return $this->found_value; }
	public function get_expected_value(): string { return $this->expected_value; }
	public function get_evidence(): array { return $this->evidence; }
	public function get_remediation(): array { return $this->remediation; }
	public function get_source_provider(): string { return $this->source_provider; }
	public function is_failure(): bool { return self::FAIL === $this->status; }
	public function fingerprint( int $post_id ): string { return hash( 'sha256', $this->check_id . '|' . $post_id ); }

	public function to_array(): array {
		return array(
			'check_id' => $this->check_id, 'category' => $this->category, 'severity' => $this->severity,
			'status' => $this->status, 'weight' => $this->weight, 'title' => $this->title,
			'message' => $this->message, 'explanation' => $this->explanation,
			'why_matters' => $this->why_matters, 'recommendation' => $this->recommendation,
			'found_value' => $this->found_value, 'expected_value' => $this->expected_value,
			'evidence' => $this->evidence, 'remediation' => $this->remediation,
			'source_provider' => $this->source_provider,
		);
	}
}
