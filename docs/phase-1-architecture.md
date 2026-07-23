# Phase 1 architecture

## Current architecture diagnosis

Version 0.4.1 had stable operational modules, but its audit was synchronous, monolithic, calculated a fixed 20-point penalty, had no run history, and exposed only broken-link REST routes. Database migrations were spread across module activation methods. Rank Math interoperability existed, while Yoast metadata had no centralized adapter.

## Added components

- Audit check contract, context, normalized result and registry.
- Weighted scorer with a per-category penalty cap.
- Ordered metadata providers: Toolkit, Rank Math, Yoast and WordPress.
- Additive audit repository and versioned database migration.
- Resumable WP-Cron runner with bounded batches and expiring locks.
- Site and content audit history.
- Persistent issues with open, ignored, resolved and reopened states.
- Overview, audit execution list and asynchronous content audit control.
- Protected REST endpoints for execution, progress, cancellation and history.

## Execution flow

1. REST creates one pending audit after checking that no audit is active.
2. The run stores scope, post types and total item count in the audits table.
3. WP-Cron acquires an atomic expiring option lock.
4. A batch of 1-50 published items is loaded in stable ID order.
5. Every registered check receives an audit context and returns one normalized result.
6. Results, issue state and content score snapshot are committed per content item.
7. Cursor and heartbeat are persisted after every item.
8. The next single event is scheduled, or the run is completed and summarized.
9. A stalled run without a recent heartbeat is scheduled again.
10. Cancellation changes state and clears pending batch events.

## Scoring

Default base penalties are: critical 20, high 12, medium 6, low 2, recommendation 0 and informational 0. A check may apply a weight from 0 to 2. Penalties in one category are capped at 30 points. The content score is `max(0, 100 - sum(category penalties))`. The site score is the average of audited content scores. Both weight maps and results are filterable.

## Limits

- Batch size defaults to 10 and is clamped to 1-50.
- One site/content audit may run at a time.
- Locks expire after 120 seconds.
- A heartbeat older than five minutes is considered stalled.
- Existing link-scanner limits remain unchanged.
- Detailed results default to 90 days; site summaries default to 12 months.

## Compatibility and migration

The migration only creates or updates plugin-owned tables through `dbDelta()`. Existing redirects, 404 logs, broken links, metadata, settings, hooks and REST routes are preserved. No table is dropped during activation or update. Data deletion requires explicit opt-in before plugin deletion.
