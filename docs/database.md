# Database tables

All names use the active WordPress `$wpdb->prefix`. Schema upgrades run through `dbDelta` when `GPSEO_DB_VERSION` changes and are additive, preserving existing records.

## Operational tables

- `gpseo_redirects`: source, destination, status type, hit count, creation date and last access.
- `gpseo_404_log`: aggregated URL, referrer, first/last access, hit count and ignored state.
- `gpseo_broken_links`: content, broken URL, anchor text, HTTP status, final URL, last check and ignored state.

## Audit tables

### gpseo_audits

One row per execution with state, scope, content/post type selection, cursor, totals, score, category scores, summary metrics, errors and execution timestamps.

### gpseo_audit_results

Immutable result snapshot per audit, content and stable check ID. It stores category, severity, result/lifecycle status, weight, raw and applied penalty, fingerprint, complete explanatory copy, found/expected values, evidence/remediation JSON, provider and detection timestamps.

### gpseo_audit_issues

Current issue lifecycle keyed by a stable SHA-256 fingerprint. It stores the affected URL, priority, open/ignored/resolved/reopened state, explanatory fields, scoring components, remediation, provider, first/last detection, resolution, last audit and occurrence count.

### gpseo_score_history

Content and site score snapshots with category scores and the persisted components required to reconstruct scoring: initial score, category cap, raw/applied penalties, ignored/resolved counts and per-check breakdown.