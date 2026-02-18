# BMN Boston Platform v2 - CLAUDE.md

## Project Overview
Complete rebuild of the BMN Boston real estate platform. New codebase, clean architecture.

## Current Phase: 11f (Unified Search Experience) - COMPLETE
**Status:** Session 23 — All 7 remaining QA items verified and passed. Phase 11f closed out.
**What's done:** Phases A-E of the unified search plan, 7 QA bug fixes, filter param translation, dynamic property types, spatial polygon optimization, map pin Alpine proxy fix, 15 UX polish fixes, pin clustering (1000 pins), all QA items verified (view toggle, mobile responsive, HTMX partial rendering, homepage cards, autocomplete, save search, filter chips).
**Previous Phase:** 11e (Map Search) - COMPLETE (Session 18)
**Next Phase:** Phase 12 (iOS SwiftUI rebuild)

## Critical Rules (NEVER VIOLATE)

1. **One Service, Two Interfaces** - Every feature has ONE service. REST controllers (iOS) and AJAX handlers (web) both call the same service. No dual code paths.
2. **Year Rollover** - Never use `date('Y')` for time-series queries. Use `MAX(year)` from the database.
3. **Timezone** - Use `current_time('timestamp')` not `time()`. Use `current_time('mysql')` not `date('Y-m-d H:i:s')`.
4. **Property URLs** - Use `listing_id` (MLS number), not `listing_key` (hash) in URLs.
5. **Performance** - Property search queries must use the `bmn_properties` table (not JOINs across normalized tables).
6. **Prepared SQL** - Always use `$wpdb->prepare()` for dynamic SQL. No exceptions.
7. **No v1 Modifications** - The old codebase at `~/Development/BMNBoston/` is READ-ONLY reference. Never modify v1 files.
8. **Production Isolation** - v2 Docker environment uses its own database. Never connect to production.
9. **NEVER Deploy V2 to Production** - V2 is localhost-only (Docker at port 8082). NEVER rsync, scp, or deploy any V2 code to bmnboston.com or steve-novak.com. Production runs v1. The two systems have completely different database structures, plugin architectures, and REST API namespaces.
10. **V2 Testing is Localhost Only** - All testing happens at http://localhost:8082. Never run WP-CLI or curl against production for V2 testing. The V2 database has `bmn_*` tables; production has `bme_*` tables. They are incompatible.

## Project Location
`~/Development/BMNBoston-v2/`

## Architecture
- WordPress Multisite backend with clean plugin architecture
- 1 mu-plugin (bmn-platform) + 10 domain plugins
- PSR-4 autoloading, DI container, service providers
- REST API namespace: `/wp-json/bmn/v1/`
- New SwiftUI iOS app
- Vite build system for theme

## Docker Environment (v2)
| Service | URL |
|---------|-----|
| WordPress | http://localhost:8082 |
| phpMyAdmin | http://localhost:8083 |
| Mailhog UI | http://localhost:8026 |
| MySQL | localhost:3307 |

Ports configured in `wordpress/.env` to coexist with v1 environment.

## Health Check Endpoints
- Basic: `GET /wp-json/bmn/v1/health` (or `?rest_route=/bmn/v1/health`)
- Full: `GET /wp-json/bmn/v1/health/full` — tests all 6 platform services

## Namespace Map
| Plugin | Namespace |
|--------|-----------|
| bmn-platform | BMN\Platform\ |
| bmn-properties | BMN\Properties\ |
| bmn-users | BMN\Users\ |
| bmn-schools | BMN\Schools\ |
| bmn-appointments | BMN\Appointments\ |
| bmn-agents | BMN\Agents\ |
| bmn-extractor | BMN\Extractor\ |
| bmn-exclusive | BMN\Exclusive\ |
| bmn-cma | BMN\CMA\ |
| bmn-analytics | BMN\Analytics\ |
| bmn-flip | BMN\Flip\ |

## Quick Commands
```bash
# Start dev environment
cd ~/Development/BMNBoston-v2/wordpress && docker-compose up -d

# Run platform tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/mu-plugins/bmn-platform && composer test

# Run extractor tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-extractor && composer test

# Run properties tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-properties && composer test

# Run users tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-users && composer test

# Run schools tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-schools && composer test

# Run appointments tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-appointments && composer test

# Run agents tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-agents && composer test

# Run CMA tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-cma && composer test

# Run analytics tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-analytics && composer test

# Run flip tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-flip && composer test

# Run exclusive tests
cd ~/Development/BMNBoston-v2/wordpress/wp-content/plugins/bmn-exclusive && composer test

# Run PHP linter
composer lint

# Test health endpoint
curl -s "http://localhost:8082/?rest_route=/bmn/v1/health/full" | python3 -m json.tool

# Start Vite dev server
cd ~/Development/BMNBoston-v2/wordpress/wp-content/themes/bmn-theme && npm run dev

# Build theme assets
npm run build
```

## Session Protocol
At session start: Read CLAUDE.md, docs/REBUILD_PROGRESS.md, .context/sessions/latest-session.md
At session end: Update CLAUDE.md, write session handoff, commit and push

## v1 Reference (READ-ONLY)
| What | Where in v1 |
|------|-------------|
| Property search logic | class-mld-mobile-rest-api.php |
| Web queries | class-mld-query.php |
| Filter builder | class-mld-shared-query-builder.php |
| JWT auth | class-mld-jwt-handler.php |
| School rankings | class-ranking-calculator.php |
| MLS extraction | class-bme-data-processor.php |
| All 45 pitfalls | CLAUDE.md |

## Token Revocation
The platform `AuthMiddleware` fires the `bmn_is_token_revoked` filter after JWT validation. The `bmn-users` plugin hooks into this to check the `bmn_revoked_tokens` table. Any plugin can hook into this filter to reject tokens.

## V2 vs V1 Architecture Differences

**V2 and V1 are completely separate systems. Do NOT mix them.**

| Aspect | V1 (Production) | V2 (Localhost) |
|--------|-----------------|----------------|
| Location | `~/Development/BMNBoston/` | `~/Development/BMNBoston-v2/` |
| Site URL | https://bmnboston.com | http://localhost:8082 |
| REST namespace | `/mld-mobile/v1/` | `/bmn/v1/` |
| DB tables | `bme_listings`, `bme_media`, `bme_listing_summary` | `bmn_properties`, `bmn_media` |
| Theme | `flavor-flavor-flavor` (v1.5.9) | `bmn-theme` (v3.0.0) |
| Plugins | `mls-listings-display`, `bmn-schools` (v1) | `bmn-properties`, `bmn-schools` (v2) |
| Query classes | `MLD_Query`, `BNE_MLS_Helpers` | REST API via `rest_do_request()` |
| Field: address | `unparsed_address` | `address` |
| Field: price | `list_price` | `price` |
| Field: beds | `bedrooms_total` | `beds` |
| Field: baths | `bathrooms_total` / `bathrooms_total_integer` | `baths` |
| Field: sqft | `building_area_total` | `sqft` |
| Field: status | `standard_status` | `status` |
| Search response | `{success, data: {listings, total, total_pages}}` | `{success, data: [...], meta: {total, total_pages}}` |

## Theme Search Architecture (v3.0.0)

### Shared TypeScript Modules (`assets/src/ts/lib/`)
| Module | Purpose |
|--------|---------|
| `filter-engine.ts` | SearchFilters interface, serialization (`paged` param), chips, reset. Pure functions — no Alpine/DOM dependency |
| `favorites-store.ts` | Optimistic localStorage + JWT API sync singleton |
| `property-utils.ts` | formatPrice, escapeHtml, getPropertyUrl, getStatusColor, getDomLabel |

### Alpine Components
| Component | Registration | Used By |
|-----------|-------------|---------|
| `filterState` | `property-search.ts` | `page-property-search.php` |
| `mapSearch` | `map-search.ts` | `page-map-search.php` |
| `saveSearchModal` | `save-search-modal.ts` | Both search pages |
| `autocomplete` | `autocomplete.ts` | Filter bar (dispatch mode) + homepage (navigate mode) |

### PHP Templates
| Template | Purpose |
|----------|---------|
| `filter-bar.php` | Shared horizontal filter bar (autocomplete, dropdowns, chips, view toggle) |
| `more-filters.php` | Collapsible advanced filters (sqft, lot, year, DOM, amenities) |
| `property-card.php` | Unified card with status badges, DOM labels, favorite hearts, teal accents |
| `results-grid.php` | 4-column grid, normalizes API keys for card |

### Color Convention
- **Teal-600** — All search UI (filter bar, buttons, badges, pins, pagination)
- **Navy-700** — Site header/footer/CTAs (btn-primary stays navy)

*Last updated: 2026-02-18 (Session 23 - Phase 11f QA complete, phase closed out)*
