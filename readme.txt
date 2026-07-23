=== Garion Projetos - Technical SEO Toolkit ===
Contributors: garionprojetos
Tags: seo, rank-math, redirects, sitemap, broken-links
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Independent technical SEO, auditing and maintenance tools with optional third-party interoperability.

== Description ==

Technical SEO Toolkit adds:

* Redirect management with 301, 302, 307 and 308 status codes and CSV import/export
* 404 monitoring with 90-day retention
* Safe, asynchronous broken-link detection
* Page and post auditing
* XML sitemap when Rank Math Sitemap is not active
* Structured data when Rank Math Schema is not active
* Canonical, meta description and robots controls
* Open Graph and Twitter Card overrides with a live preview
* Extra robots.txt rules
* Protected WordPress REST API endpoints
* Full admin translations: English (default), Brazilian Portuguese, Spanish, Russian and Simplified Chinese — matches the site's or user's WordPress language automatically

When Rank Math is active, the toolkit uses its public filters and avoids duplicate canonical, Schema, sitemap, robots and social metadata output. The broken-link scanner and audit continue as complementary features.

All plugin code is an original Garion Projetos implementation. Rank Math was reviewed only as a reference for product, security, performance and WordPress interoperability practices. No Rank Math source code, classes, functions, algorithms, text, interface elements or assets are copied, adapted or redistributed. Optional interoperability uses public hooks and WordPress APIs only, and the toolkit works without Rank Math.

This plugin does not send site data to external services. The broken-link scanner only contacts URLs found in published content to verify their HTTP response.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins screen.
3. Open Technical SEO in the admin sidebar.
4. If Rank Math is active, the toolkit detects its enabled modules automatically.

== Frequently Asked Questions ==

= Does it work without Rank Math? =

Yes. The toolkit is independent and all standalone features work without Rank Math.

= What languages are available? =

The admin interface ships with translations for Brazilian Portuguese (pt_BR), Spanish (es_ES), Russian (ru_RU) and Simplified Chinese (zh_CN), in addition to the default English source strings. WordPress automatically loads the matching translation based on the site's General Settings language or the current user's profile language. A `.pot` template is included in `languages/` for anyone who wants to add another language.

= Can it interoperate with Rank Math? =

Yes. Per-post overrides are passed through public Rank Math filters. The toolkit disables duplicate output whenever Rank Math owns the equivalent feature.

= Where do I manage redirects? =

Under Technical SEO > Redirects. You can also import and export CSV files.

= How does the 404 monitor work? =

The toolkit records real not-found requests with URL, referrer and hit count. If Rank Math 404 Monitor is active, new records are left to Rank Math to avoid duplicate database writes.

= Where is my sitemap? =

Without Rank Math Sitemap, the toolkit uses `/sitemap.xml`. With Rank Math Sitemap active, the interface points to `/sitemap_index.xml` and the toolkit does not register competing routes.

= How often does the broken-link scan run? =

A full scan starts once per day and runs in small background batches. An administrator can also trigger an immediate scan.

= How is the link scanner protected? =

It validates HTTP(S) URLs and uses the WordPress safe HTTP API, which rejects unsafe private-network destinations. Results are replaced on every scan so repaired links do not remain listed.

== Changelog ==

= 0.6.2 =
* Added the `NoCaching` sniff code to the ignore comments on every write query (insert/update/delete/replace) in the audit repository — it was only exempted from `DirectQuery` before, so it kept getting flagged separately.

= 0.6.1 =
* Fixed a leftover `WordPress.DB.PreparedSQL.NotPrepared` error where a query string was still built in an intermediate variable before `$wpdb->prepare()`; each branch now inlines its own literal query.
* Fixed several `phpcs:ignore` comments that were placed one line above a multi-line statement (where they don't apply) instead of on the exact flagged line; queries built across several source lines were collapsed to one line each so the exception comment reliably covers the whole statement.
* Added the placeholder-count sniff codes (`UnfinishedPrepare`, `ReplacementsWrongNumber`) to the ignore comments on queries with a dynamically-sized `IN (...)` list, which PHPCS cannot statically count.

= 0.6.0 =
* **Breaking change:** renamed all public hooks to a consistent `gpseo_` prefix (see Upgrade Notice below). If you have custom code hooking into the old `garion_technical_seo/...` names, update it to the new names.
* Fixed all remaining WordPress Plugin Check errors: SQL queries built through an intermediate `$sql` variable now call `$wpdb->prepare()` directly (audit repository, broken-link lookup), and hardcoded `LIKE` wildcards in the sitemap query now go through `$wpdb->esc_like()` and a placeholder.
* Added justified `phpcs:ignore` comments (with reasons) for warnings that are false positives for this plugin's custom tables: table-name interpolation (there is no placeholder for identifiers), direct-query/no-caching on reporting queries that must always show fresh audit/scan state, and a single fixed `meta_key` lookup.
* Renamed unprefixed variables in `uninstall.php` and trimmed `readme.txt` to 5 tags (WordPress.org limit).
* Sanitized `$_FILES['import_file']['tmp_name']` before use in the redirects CSV importer.

= 0.5.10 =
* Fixed all Plugin Check errors in the redesigned admin screen: missing escaping ignore-comments on internally-escaped helper output, an unescaped pagination integer, and a mismatched phpcs:ignore code on CSV file reads.
* Fixed missing nonce-verification ignore comments and an unsanitized `$_GET['status']` read on the Issues filter form (read-only GET filters, no state change).

= 0.5.9 =
* Fixed 7 WordPress.WP.I18n.MissingTranslatorsComment code-standards warnings in the admin screen by adding `translators:` comments above every translation call with printf-style placeholders.

= 0.5.8 =
* Added full admin translations for Brazilian Portuguese, Spanish, Russian and Simplified Chinese (277 strings each), loaded automatically from `languages/` based on the site or user language.

= 0.5.7 =
* Fixed the Issues screen ignoring severity, category, status, content type and sort order whenever an audit was selected in the filters — it now always combines every filter together, as expected.

= 0.5.6 =
* Fixed the per-audit JSON/CSV export links always failing with "You are not allowed to do this." — the handler was reading the wrong query parameter (`id` instead of `audit_id`).

= 0.5.5 =
* Fixed the audit detail "Duration" metric showing an absurd value (years) for audits that completed in under a second.
* Wrapped the audit and content detail screens in a card and tightened heading/paragraph spacing for a more consistent layout.

= 0.5.4 =
* Renamed the plugin title to "Garion Projetos - Technical SEO Toolkit" across the Plugins list, admin menu, dashboard header and post-editor metabox.
* Added cache-busting to the audit/broken-link status polling requests so a caching plugin or CDN cannot serve a stale "running" state after a background job finishes.

= 0.5.3 =
* Redesigned the admin interface: new dashboard with SEO health metrics, badges for severity/status, breadcrumbs, responsive tables that collapse into cards on small screens, empty states and accessible confirmation modals.
* No changes to stored data, REST endpoints or business logic.

= 0.5.2 =
* Fixed individual audit authorization for administrators using customized WordPress roles.
* Added an explicit REST nonce middleware for reliable wp-admin actions.

= 0.5.1 =
* Added complete issue discovery, filtering, pagination, details, remediation and lifecycle management.
* Added explainable scoring with persisted raw and applied penalties, category caps and provider attribution.
* Added audit/content detail screens, comparisons and JSON/CSV audit exports.
* Added protected REST endpoints for audits, content issues and issue actions.
* Expanded redirects, 404 and broken-link operational data without deleting existing records.
= 0.5.0 =
* Added an extensible audit engine with independent checks and public hooks.
* Added weighted, category-capped scoring and persistent score history.
* Added resumable WP-Cron batches, expiring locks, cancellation and stalled-run recovery.
* Added centralized Toolkit, WordPress, Rank Math and Yoast metadata providers.
* Added audit run, progress, cancellation, content audit and history REST endpoints.
* Added four indexed audit tables and opt-in data removal on uninstall.


= 0.4.1 =
* Fixed a fatal error during upgrades by deferring rewrite-rule registration and flushing until WordPress init.

= 0.4.0 =
* Added Rank Math filters for canonical, description, robots, Open Graph, Twitter and sitemap exclusions.
* Prevented duplicate Schema, sitemap, canonical and social metadata output.
* Prevented duplicate 404 logging when Rank Math 404 Monitor is active.
* Hardened broken-link checks against SSRF, limited links per post and removed stale results.
* Changed automatic full scans from continuous ten-minute cycles to daily background batches.
* Added automatic database migrations after plugin updates.
* Added redirect loop prevention, atomic hit counts, external HTTP(S) validation and 307/308 support.
* Improved standalone Schema and Rank Math-aware page audits.

= 0.3.0 =
* Added a 404 monitor, XML sitemap and social metadata overrides.
* Added search, pagination and CSV redirect import/export.

= 0.2.0 =
* Added redirects, broken-link scanning, Schema, canonical, robots, auditing and REST endpoints.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.6.0 =
Breaking: hook names changed from `garion_technical_seo/audit/checks` (and siblings) to `gpseo_audit_checks` etc. Update any custom code that hooks into this plugin. No data migration needed otherwise.

= 0.5.3 =
Admin interface redesign only. No data migration needed; safe to update in place.

= 0.5.0 =
Adds the modular asynchronous audit foundation. Existing operational data is preserved.

= 0.4.1 =
Fixes a fatal error that could occur while initializing rewrite rules during an upgrade.

= 0.4.0 =
Adds optional Rank Math interoperability through public hooks, safer link scanning, automatic database migrations and more robust redirects.
