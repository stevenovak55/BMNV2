Session 25 — Data Structure & API Validation (Pre-iOS Gate)

Read these files first:
- ~/Development/BMNBoston-v2/CLAUDE.md
- ~/Development/BMNBoston-v2/.context/sessions/latest-session.md
- ~/Development/BMNBoston-v2/.context/sessions/session-24-db-comparison.md

Context

Sessions 1-23 built the entire V2 backend (10 domain plugins, 100+ REST
endpoints) and web theme (Alpine.js + HTMX + Tailwind). Session 24 ran a
comprehensive V1 vs V2 database comparison — result was GO V2 WITH FIXES.
All 10 fixes were applied:

- DB: MA compliance columns (lead_paint, title5, disclosures), pet columns
  (pets_dogs_allowed, pets_cats_allowed), archive sort indexes, SRID 4326
  spatial fix, bmn_rooms table, dropped low-value boolean indexes
- Code: DataNormalizer field mappings + pet parsing + room extraction,
  explicit 29-column SELECT for search queries, V1-parity API fields
  (baths_full, baths_half, grouping_address)
- 1,646 tests across 11 suites, all passing

IMPORTANT: The new columns and tables exist but have NO DATA yet. No
extraction has run since the schema changes. This session needs to verify
the full pipeline works end-to-end before we build the iOS app.

V2 database state:
- wp_bmn_properties: 96,016 rows, 129 columns
- wp_bmn_rooms: 0 rows (created but never populated)
- wp_bmn_property_history: 0 rows (exists but never populated)
- New columns (lead_paint, title5, disclosures, pets_dogs_allowed,
  pets_cats_allowed) all NULL — need extraction to populate
- Spatial index: SRID 4326, verified working

V2 REST API: /bmn/v1/ namespace at http://localhost:8082
V1 production API (READ-ONLY reference): https://bmnboston.com/wp-json/mld-mobile/v1/

Goals for this session:

1. DATA PIPELINE VALIDATION
   - Run a test extraction (small batch) to verify DataNormalizer changes
     populate lead_paint, title5, disclosures, pets_dogs_allowed,
     pets_cats_allowed correctly from the RESO API response
   - Verify normalizeRooms() populates bmn_rooms table
   - Verify property_history tracking works
   - Check that extra_data JSON still stores complete API responses
   - Confirm the explicit SELECT list (Fix 7) works correctly in live queries

2. V1 vs V2 API PARITY CHECK
   - Compare V1 production API response shape vs V2 API response shape
   - Ensure all fields the iOS app needs are present in V2 API output
   - Test: GET /bmn/v1/properties (list), GET /bmn/v1/properties/{id} (detail)
   - Verify baths_full, baths_half, grouping_address appear in responses
   - Check pagination metadata format matches what iOS will expect
   - Test autocomplete endpoints

3. SEARCH FUNCTIONALITY AUDIT
   - Test all filter combinations: city, price range, beds/baths, property
     type, status, map bounds, keyword, amenities (pool, waterfront, etc.)
   - Verify spatial queries work with SRID 4326 coordinates
   - Test archive queries (sold/expired listings)
   - Verify sort options work correctly
   - Test deep pagination

4. MISSING DATA / EDGE CASES
   - Are there listings with zero-date values that could cause issues?
   - Do all 6,001 active listings have valid lat/lng for map display?
   - Are media URLs (photos) all valid CDN URLs?
   - Check for any NULL listing_id or listing_key values

5. API DOCUMENTATION
   - Document the complete V2 REST API contract (all endpoints, request
     params, response shapes) for the iOS app to consume
   - This becomes the spec for Phase 12

Start Docker first:
cd ~/Development/BMNBoston-v2/wordpress && docker-compose up -d

Key files:
- Extractor: bmn-extractor/src/Service/DataNormalizer.php
- Search repo: bmn-properties/src/Repository/PropertySearchRepository.php
- Search service: bmn-properties/src/Service/PropertySearchService.php
- List model: bmn-properties/src/Model/PropertyListItem.php
- Filter builder: bmn-properties/src/Service/Filter/FilterBuilder.php
- REST controllers: bmn-properties/src/Controller/
