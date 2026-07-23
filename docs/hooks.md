# Public hooks

## Register a check

```php
add_filter( 'gpseo_audit_checks', function ( array $checks ): array {
	$checks[] = new My_Custom_Audit_Check();
	return $checks;
} );
```

## Lifecycle actions

- `gpseo_audit_started`: receives the audit ID.
- `gpseo_audit_completed`: receives audit ID and summary.
- `gpseo_audit_failed`: receives audit ID and Throwable.

## Result and score filters

- `gpseo_audit_result`: receives result and context.
- `gpseo_score_weights`: filters severity penalty values.
- `gpseo_providers`: filters ordered metadata providers.

Checks and providers must implement their respective public interfaces.
