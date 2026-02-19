# Session 25: Data Structure & API Validation (Pre-iOS Gate)

**Date:** 2026-02-18
**Status:** COMPLETE
**Previous:** Session 24 (DB Comparison + Fixes)
**Next:** Phase 12 (iOS SwiftUI Rebuild)

---

## What Was Accomplished

### 1. Data Pipeline Validation

**Created missing RoomRepository** (`bmn-extractor/src/Repository/RoomRepository.php`):
- `replaceForListing()` — delete + batch insert rooms
- `getForListing()` — ordered by room_type
- `deleteForListing()` — cleanup

**Integrated rooms into extraction pipeline** (`ExtractionEngine.php`):
- Added RoomRepository to constructor (11th dependency)
- Modified `processBatch()` to collect raw API listings
- Added room extraction to `processRelatedData()` — calls `normalizeRooms()` per listing
- Rooms now extracted from RESO API `Room*` fields on every extraction run

**Fixed DataNormalizer** (`DataNormalizer.php`):
- Removed `lead_paint` from PROPERTY_FIELD_MAP (RESO returns array, not boolean)
- Added `parseLeadPaint()` method: parses `MLSPIN_LEAD_PAINT` array → 1/0/null
- Fixed room name formatting: "Bedroom2" → "Bedroom 2"

**Updated ServiceProvider** (`ExtractorServiceProvider.php`):
- Added RoomRepository to DI container
- Added missing migrations: `AddComparisonFixes`, `CreateRoomsTable`
- Wired RoomRepository into ExtractionEngine

**Backfilled existing data from extra_data JSON:**
- 3,030 properties updated with new column data
- 911 lead_paint values (893 no, 18 yes)
- 484 title5 values (A: 158, F: 130, D: 147, others)
- 2,182 disclosures entries
- 1,335 pet-allowed entries
- 56,000 room records created (avg 14 rooms per listing)

### 2. V1 vs V2 API Parity Check

**Response shape comparison (list endpoint):**
- V1: 36 fields in `{data: {listings, total, page, ...}}`
- V2: 32 fields in `{data: [...], meta: {total, page, ...}}`
- All critical iOS fields present in V2
- V1-only fields not needed: `district_grade` (school plugin), `exclusive_tag` (derive from `is_exclusive`), `is_shared_by_agent` (different sharing model)

**Naming differences documented:**
- V1 `mls_number` → V2 `listing_id`
- V1 `photo_url` → V2 `main_photo_url`
- V1 `property_subtype` → V2 `property_sub_type`

**Fixed API output quality:**
- Zero dates (`0000-00-00`) → null in both PropertyListItem and PropertyDetail
- Empty strings → null for optional fields
- Zero prices on non-sold listings → null

### 3. Search Functionality Audit

**16 tests PASS, 1 bug found and fixed, 1 not implemented:**

| # | Test | Result |
|---|------|--------|
| 1 | City filter | PASS |
| 2 | Price range | PASS |
| 3 | Beds/Baths | PASS |
| 4 | Property type | PASS |
| 5 | Status Active | PASS |
| 6 | Status Sold | PASS |
| 7 | Map bounds | **FIXED** (was SRID mismatch) |
| 8 | Keyword search | Not implemented (documented) |
| 9 | Sort options (4 variants) | PASS |
| 10 | Pagination | PASS |
| 11 | Deep pagination (p50) | PASS |
| 12 | Combined filters | PASS |
| 13 | Autocomplete city | PASS |
| 14 | Autocomplete zip | PASS |
| 15 | Autocomplete address | PASS |
| 16 | Empty results | PASS |
| 17 | Price reduced | PASS |
| 18 | Year built range | PASS |
| 19 | Max DOM | PASS (data-dependent) |

**Critical bug fixed: SRID 4326 mismatch in spatial queries**
- File: `bmn-platform/src/Geocoding/SpatialService.php`
- All three spatial methods (`buildSpatialBoundsCondition`, `buildSpatialRadiusCondition`, `buildSpatialPolygonCondition`) were missing SRID 4326 parameter
- Map bounds queries returned 0 results instead of expected 320
- Fixed by adding `, 4326` to all `ST_GeomFromText()` calls

### 4. Data Quality Fixes

**Collation mismatch fixed:**
- `wp_bmn_rooms` was `utf8mb4_unicode_ci`, `wp_bmn_properties` was `utf8mb4_unicode_520_ci`
- Fixed: `ALTER TABLE wp_bmn_rooms CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci`

**Zero-dates cleaned:**
- 6,000 rows with `close_date = '0000-00-00'` converted to NULL

**Data quality summary:**
- All 4,040 active listings have valid lat/lng ✓
- All listing_id and listing_key values populated, no duplicates ✓
- All active listings have valid positive prices ✓
- 9 active listings missing photos (0.22%) — newly listed
- 4 legacy `cdn.rets.ly` media URLs out of 73,327 (99.995% CDN)
- 65 listings outside tight MA bounds are legitimate western MA/NH border

### 5. API Documentation

Created **`.context/V2_REST_API_CONTRACT.md`** — complete iOS developer reference:
- 3 main endpoints documented (search, detail, autocomplete)
- All 40+ filter parameters with types and descriptions
- Complete response field inventory with types and nullability
- Status mapping reference
- V1→V2 migration guide for iOS developers

---

## Files Modified

### bmn-extractor
| File | Change |
|------|--------|
| `src/Repository/RoomRepository.php` | **NEW** — Room CRUD repository |
| `src/Service/ExtractionEngine.php` | Added RoomRepository, room extraction in pipeline |
| `src/Service/DataNormalizer.php` | `parseLeadPaint()`, room name formatting fix |
| `src/Provider/ExtractorServiceProvider.php` | RoomRepository DI, missing migrations |
| `tests/Unit/Service/ExtractionEngineTest.php` | Added RoomRepository mock |
| `backfill-columns.php` | **NEW** — One-time backfill script (can be deleted) |

### bmn-properties
| File | Change |
|------|--------|
| `src/Model/PropertyListItem.php` | `emptyToNull()` sanitization |
| `src/Model/PropertyDetail.php` | `emptyToNull()`, `zeroToNull()` sanitization |

### bmn-platform
| File | Change |
|------|--------|
| `src/Geocoding/SpatialService.php` | SRID 4326 fix in 3 spatial methods |

### Documentation
| File | Change |
|------|--------|
| `.context/V2_REST_API_CONTRACT.md` | **NEW** — Complete API contract for iOS |
| `.context/sessions/session-25-api-validation.md` | **NEW** — This session handoff |

---

## Test Results

| Plugin | Tests | Assertions | Result |
|--------|-------|------------|--------|
| bmn-platform | 145 | 287 | PASS |
| bmn-extractor | 136 | 332 | PASS |
| bmn-properties | 140 | 280 | PASS |
| bmn-users | 169 | 296 | PASS |
| bmn-schools | 165 | 284 | PASS |
| bmn-appointments | 160 | 307 | PASS |
| bmn-agents | 197 | 377 | PASS |
| bmn-cma | 145 | 292 | PASS |
| bmn-analytics | 88 | 177 | PASS |
| bmn-flip | 132 | 405 | PASS |
| bmn-exclusive | 169 | 312 | PASS |
| **TOTAL** | **1,646** | **3,349** | **ALL PASS** |

---

## V2 Database State After Session 25

| Table | Rows | Notes |
|-------|------|-------|
| wp_bmn_properties | 96,016 | 4,040 active, 1,961 pending, 90,015 archived |
| wp_bmn_rooms | 56,000 | 4,000 listings with room data |
| wp_bmn_media | 73,327 | Photos and media |
| wp_bmn_agents | 2,941 | Agent records |
| wp_bmn_offices | 1,631 | Office records |
| wp_bmn_open_houses | 31 | Active open house schedules |
| wp_bmn_property_history | 0 | Populates on next extraction |

**New columns populated:**
- lead_paint: 911 (18 yes, 893 no)
- title5: 484 (A/B/C/D/E/F)
- disclosures: 2,182
- pets_dogs_allowed: 1,335
- pets_cats_allowed: 1,335

---

## Known Gaps (Not Blocking iOS)

1. **Keyword/fulltext search** — Not implemented in REST API (search bar uses autocomplete instead)
2. **extra_data** — Only 4,000 of 96,016 rows have it (real data vs synthetic archive)
3. **property_history** — Empty until next live extraction run
4. **4 legacy cdn.rets.ly URLs** — Out of 73,327 total media

---

## Verdict: READY FOR iOS (Phase 12)

All 5 pre-iOS gates are GREEN:
1. ✅ Data pipeline validates end-to-end (normalizer → columns + rooms)
2. ✅ V1/V2 API parity confirmed (all iOS-critical fields present)
3. ✅ Search functionality audited (16/16 pass + 1 bug fixed)
4. ✅ Data quality verified (no blocking issues)
5. ✅ API contract documented for iOS developers

---

*Generated by Claude Code, Session 25, 2026-02-18*
