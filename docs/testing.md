# Testing and diagnosis

Run pure unit checks:

```powershell
C:\xamp\php\php.exe tests\test-score-calculator.php
C:\xamp\php\php.exe tests\test-audit-registry.php
```

## Manual WordPress integration plan

1. Upgrade from 0.4.1 and confirm all seven plugin tables exist without data loss.
2. Start a full audit and verify pending, running and completed transitions.
3. Attempt a concurrent run and expect HTTP 409.
4. Cancel a run and confirm no later batch changes its counters.
5. Delete one scheduled batch event, wait five minutes and confirm recovery.
6. Force a check exception and confirm an inconclusive result without a failed run.
7. Fix an open issue, re-audit and confirm resolved; recreate it and confirm reopened.
8. Ignore an issue directly in storage during Phase 1 validation and confirm later scans preserve ignored state.
9. Verify history retention without deleting redirects, 404 data or link records.
10. Test all REST routes as administrator and as a user without `manage_options`.
11. Test with no SEO plugin, Rank Math and Yoast separately; inspect frontend for duplicate metadata.
12. Test classic editor, Gutenberg, custom public post types, subdirectory installs and different permalink structures.
13. Confirm scripts load only on toolkit/editor screens.
14. Run SSRF regression tests against loopback, private, link-local and redirected private destinations using the existing link scanner.
