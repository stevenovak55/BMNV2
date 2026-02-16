# v1 Pitfall Mapping to v2 Prevention

This document maps every known v1 pitfall to the v2 architectural decision or implementation that prevents it.

## Critical Pitfalls

| # | v1 Pitfall | v2 Prevention | Phase |
|---|-----------|---------------|-------|
| 1 | Dual code paths (iOS vs web) | Unified service layer (ADR-002) | 1 |
| 2 | `date('Y')` in year rollover queries | PHPCS rule + grep CI check. Use MAX(year) from DB | 0 |
| 3 | `time()` instead of `current_time()` | PHPCS rule + grep CI check | 0 |
| 4 | `listing_key` in URLs instead of `listing_id` | PHPCS rule + grep CI check | 0 |
| 5 | JOINs across 6 normalized tables | Consolidated `bmn_properties` table (ADR-003) | 2 |
| 6 | Unprepared SQL queries | PHPCS rule requiring `$wpdb->prepare()` | 0 |
| 7 | 7,500-line monolithic REST API class | Max 300 lines per controller, focused plugins (ADR-004) | 1+ |
| 8 | Scattered shared infrastructure | bmn-platform mu-plugin (ADR-001) | 1 |
| 9 | No automated testing | PHPUnit from Phase 0, coverage targets per phase | 0 |
| 10 | No CI/CD pipeline | GitHub Actions from Phase 0 | 0 |

## Pitfalls Addressed by Architecture

These pitfalls are prevented by the v2 architecture itself and don't need specific rules:

- **Load order bugs** -> MU-plugin loads first (ADR-001)
- **Circular dependencies** -> DI container with service providers
- **Feature parity gaps** -> Single service layer (ADR-002)
- **Database migration issues** -> Migration runner with version tracking
- **Inconsistent API responses** -> ApiResponse helper class

## Pitfalls Needing Phase-Specific Tests

These pitfalls need dedicated regression tests written in the phase where the feature is implemented:

| Pitfall | Test Location | Phase |
|---------|---------------|-------|
| School grade "N/A" for unrated | tests/Unit/Pitfalls/SchoolGradeTest.php | 5 |
| Saved search cross-platform keys | tests/Unit/Pitfalls/SavedSearchKeyTest.php | 4 |
| Push notification deduplication | tests/Unit/Pitfalls/NotificationDedupeTest.php | 4 |
| Condo unit search pattern | tests/Unit/Pitfalls/CondoSearchTest.php | 3 |
| Direct lookup bypass | tests/Unit/Pitfalls/DirectLookupTest.php | 3 |
