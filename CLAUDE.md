# BMN Boston Platform v2 - CLAUDE.md

## Project Overview
Complete rebuild of the BMN Boston real estate platform. New codebase, clean architecture.

## Current Phase: 5 (Schools) - COMPLETE
**Status:** Complete (165 tests, 284 assertions) - rankings, data import, school filter hook, 7 REST endpoints
**Previous Phase:** 4 (User System) - tagged v2.0.0-phase4
**Current Tag:** v2.0.0-phase5
**Next Phase:** 6 (Appointments) - Booking, availability, Google Calendar, notifications

## Critical Rules (NEVER VIOLATE)

1. **One Service, Two Interfaces** - Every feature has ONE service. REST controllers (iOS) and AJAX handlers (web) both call the same service. No dual code paths.
2. **Year Rollover** - Never use `date('Y')` for time-series queries. Use `MAX(year)` from the database.
3. **Timezone** - Use `current_time('timestamp')` not `time()`. Use `current_time('mysql')` not `date('Y-m-d H:i:s')`.
4. **Property URLs** - Use `listing_id` (MLS number), not `listing_key` (hash) in URLs.
5. **Performance** - Property search queries must use the `bmn_properties` table (not JOINs across normalized tables).
6. **Prepared SQL** - Always use `$wpdb->prepare()` for dynamic SQL. No exceptions.
7. **No v1 Modifications** - The old codebase at `~/Development/BMNBoston/` is READ-ONLY reference. Never modify v1 files.
8. **Production Isolation** - v2 Docker environment uses its own database. Never connect to production.

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

*Last updated: 2026-02-16*
