# Implementation roadmap

## Technical risks identified

1. The original admin screen is one large class. Phase 1 adds only the minimum overview/audit views; future Problems, Internal Links and Reports screens should move to dedicated controllers.
2. Existing operational modules own their migrations. Phase 1 remains compatible and adds an audit repository; a future migration coordinator can sequence module migrations without renaming public methods.
3. The broken-link scanner has safe URL validation, but external HTTP validation is not yet a shared service. Phase 2 should extract it before canonical, image, sitemap and social checks make network requests.
4. WordPress cron depends on site traffic. Heartbeats and recovery prevent permanent stalls, but production sites that require strict schedules should use a real system cron calling wp-cron.php.
5. Rank Math and Yoast providers read their persisted metadata centrally. If a provider cannot safely resolve a dynamic template, checks must return unknown/inconclusive rather than infer a value.
6. Rendering final theme HTML is expensive and context-dependent. Heading checks in Phase 2 must distinguish parsed content from verified frontend HTML.
7. Audit issue screens, internal-link graph and report exports are deliberately not included in Phase 1.

## Proposed directory structure

- `includes/audit/`: contracts, runner, registry, scoring and persistence.
- `includes/audit/checks/`: one independent class per check.
- `includes/providers/`: ordered interoperability adapters.
- `includes/http/`: Phase 2 reusable external URL safety/client services.
- `includes/internal-links/`: Phase 3 graph extraction and persistence.
- `includes/reports/`: Phase 4 datasets and CSV/JSON/HTML exporters.
- `admin/`: existing controller plus future dedicated screen classes.
- `docs/`: architecture, database, hooks, REST and operations.
- `tests/`: pure unit tests and future WordPress integration suites.

## Phase 2: essential checks

Add independent title, duplicate metadata, canonical, robots/indexability, headings, images, sitemap, broken-link and social checks. Introduce shared safe HTTP and rendered-document services. Every uncertain result must be inconclusive.

## Phase 3: internal links

Add an indexed edge table, incremental extraction, incoming/outgoing counts, orphan detection, redirect states and the Internal Links screen. Reuse the audit runner and stable fingerprints.

## Phase 4: issues and reports

Add server-side issue filters/actions, comparisons, executive datasets, CSV/JSON exporters and printable HTML reports. Apply `gpseo_report_data` before rendering.

## Phase 5: advanced modules

Add bounded similarity fingerprints, optional cached PageSpeed Insights integration, scheduling controls, notifications and diagnostic logging. PageSpeed remains disabled without explicit configuration and an API key.
