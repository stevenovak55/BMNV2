# BMN Boston v2 - Rebuild Progress

## Phase Status Dashboard

| Phase | Name | Status | Started | Completed | Tests | Coverage | Notes |
|-------|------|--------|---------|-----------|-------|----------|-------|
| 0 | Project Setup | Complete | 2026-02-16 | 2026-02-16 | 17/17 | N/A | All infrastructure operational |
| 1 | Platform Foundation | Complete | 2026-02-16 | 2026-02-17 | 142/142 | ~80% | Auth, DB, Cache, Email, Geo, Logging, Spatial queries |
| 2 | Data Pipeline | Complete | 2026-02-16 | 2026-02-17 | 136/136 | ~85% | Bridge MLS extraction, 7 repos, admin dashboard, media fix, schema expansion, spatial indexing |
| 3 | Core Property System | Complete | 2026-02-16 | 2026-02-17 | 140/140 | ~85% | Search, filters, autocomplete, detail, spatial bounds |
| 4 | User System | Complete | 2026-02-16 | 2026-02-16 | 169/169 | ~85% | Auth, favorites, saved searches, profile, password reset |
| 5 | Schools | Complete | 2026-02-16 | 2026-02-16 | 165/165 | ~85% | Rankings, data import, filter hook, 7 REST endpoints |
| 6 | Appointments | Complete | 2026-02-16 | 2026-02-17 | 160/160 | ~85% | Booking, availability, notifications, 10 REST endpoints |
| 7 | Agent-Client System | Complete | 2026-02-17 | 2026-02-17 | 197/197 | ~85% | Profiles, relationships, sharing, referrals, activity, 21 REST endpoints |
| 8 | CMA and Analytics | Complete | 2026-02-17 | 2026-02-17 | 233/233 | ~85% | CMA reports, comparables, adjustments, analytics tracking, 22 REST endpoints |
| 9 | Flip Analyzer | Complete | 2026-02-17 | 2026-02-17 | 132/132 | ~85% | ARV, financials, scoring, 15 REST endpoints, 4 tables |
| 10 | Exclusive Listings | Not Started | - | - | - | - | Agent-created listings |
| 11 | Theme and Web Frontend | Not Started | - | - | - | - | Templates, Vite build |
| 12 | iOS App | Not Started | - | - | - | - | SwiftUI rebuild |
| 13 | Migration and Cutover | Not Started | - | - | - | - | Data migration, DNS |

## Current Phase: 9 - Flip Analyzer - COMPLETE

### Objectives
- [x] Research v1 flip analyzer business logic (ARV, financials, scoring, strategies)
- [x] Docker verification for Phase 7+8 (activated plugins, verified 13 tables, tested endpoints, fixed bmn-cma/bmn-analytics bootstrap bug)
- [x] Build bmn-flip plugin with 4 migrations, 4 repositories, 4 services, 2 controllers, 1 provider
- [x] Implement ARV calculation with Haversine comp search, appraisal-style adjustments, confidence scoring
- [x] Implement financial analysis: rehab estimation, transaction/holding costs, cash/financed scenarios, MAO, BRRRR, rental
- [x] Implement multi-strategy viability scoring, disqualification checks, risk grading (A-F)
- [x] Implement report management with cascade deletes and monitor tracking
- [x] Write 132 tests (405 assertions) covering all components
- [x] All 1,474 tests pass across 10 suites (zero regressions)

### Deliverables

**bmn-flip plugin:**
- 15 PHP source files (4 migrations, 4 repositories, 4 services, 2 controllers, 1 provider)
- 12 test files + 1 test bootstrap (132 tests, 405 assertions)
- 4 database tables: bmn_flip_analyses, bmn_flip_comparables, bmn_flip_reports, bmn_flip_monitor_seen
- 15 REST endpoints (9 flip analysis + 6 report management)
- Haversine-based ARV comp search with expanding radius tiers [0.5, 1.0, 2.0, 5.0, 10.0] miles
- Appraisal-style adjustments: bedroom $7,500, bathroom $5,000, sqft $50/sf, year_built $500/yr, garage $5,000, lot_size $10,000/ac; 25% per-adjustment cap, 40% gross cap
- 5-factor confidence scoring (comp count 40pts, distance 30pts, recency 20pts, variance 10pts)
- Neighborhood ceiling at P90 of comp sale prices
- Financial modeling: rehab cost (age-based with lead paint), hold period, transaction costs (4.5% commission, 1.5%/1% closing, 0.456% transfer tax), holding costs, hard money (10.5%/2pts/80%LTV)
- Multi-strategy analysis: Flip (cash + financed ROI), Rental (NOI, cap rate, depreciation, tax shelter), BRRRR (refi 75%LTV/7.2%/30yr, DSCR)
- MAO calculation (classic 70% rule + adjusted), breakeven ARV
- Disqualification checks (price<$100K, no comps, area<600 sqft)
- Viability thresholds: Flip (profit>$25K & ROI>15%), Rental (cap>=3% & cash_flow>-$200), BRRRR (DSCR>=0.9)
- Composite scoring (70% financial + 20% property + 10% market)
- Risk grading: A(>=80), B(>=65), C(>=50), D(>=35), F(<35)
- Report management with ownership checks and cascade delete

**Bug fixes (prior phases):**
- Fixed bmn-cma and bmn-analytics bootstrap: `bmn_platform()` → `$app->getContainer()`
- Docker verified Phase 7+8: all 13 tables created, all endpoints responding

### Test Breakdown — bmn-flip (132 tests, 405 assertions)
| Test File | Tests | Assertions |
|-----------|-------|------------|
| MigrationsTest | 8 | ~16 |
| FlipAnalysisRepositoryTest | 10 | ~30 |
| FlipComparableRepositoryTest | 6 | ~18 |
| FlipReportRepositoryTest | 8 | ~24 |
| MonitorSeenRepositoryTest | 6 | ~18 |
| ArvServiceTest | 12 | ~48 |
| FinancialServiceTest | 25 | ~100 |
| FlipAnalysisServiceTest | 12 | ~48 |
| ReportServiceTest | 10 | ~30 |
| FlipControllerTest | 14 | ~35 |
| ReportControllerTest | 10 | ~25 |
| FlipServiceProviderTest | 11 | ~33 |
| **Total** | **132** | **405** |

### Flip REST Endpoints (9)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/bmn/v1/flip/analyze` | Yes | Analyze a single property |
| GET | `/bmn/v1/flip/results` | Yes | List analysis results for a report |
| GET | `/bmn/v1/flip/results/{id}` | Yes | Get single analysis result |
| GET | `/bmn/v1/flip/results/{id}/comps` | Yes | Get comparables for an analysis |
| GET | `/bmn/v1/flip/summary` | Yes | Per-city summary stats |
| POST | `/bmn/v1/flip/arv` | Yes | Calculate ARV only (without full analysis) |
| GET | `/bmn/v1/flip/config/cities` | Yes | Get target cities |
| POST | `/bmn/v1/flip/config/cities` | Yes | Update target cities (admin) |
| GET | `/bmn/v1/flip/config/weights` | Yes | Get scoring weights |

### Report REST Endpoints (6)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/bmn/v1/flip/reports` | Yes | List user's reports |
| POST | `/bmn/v1/flip/reports` | Yes | Create a new report |
| GET | `/bmn/v1/flip/reports/{id}` | Yes | Get single report |
| PUT | `/bmn/v1/flip/reports/{id}` | Yes | Update report |
| DELETE | `/bmn/v1/flip/reports/{id}` | Yes | Delete report (cascade) |
| POST | `/bmn/v1/flip/reports/{id}/favorite` | Yes | Toggle favorite |

### Architecture
```
bmn-flip:
  FlipController (REST - 9 routes, resource='flip')
    └── FlipAnalysisService → ArvService → wpdb (bmn_properties, Haversine comp search)
                             → FinancialService (pure logic, no deps)
                             → FlipAnalysisRepository (bmn_flip_analyses)
                             → FlipComparableRepository (bmn_flip_comparables)
    └── ArvService → wpdb (standalone ARV calculation)

  ReportController (REST - 6 routes, resource='flip/reports')
    └── ReportService → FlipReportRepository (bmn_flip_reports)
                       → FlipAnalysisRepository
                       → FlipComparableRepository
                       → MonitorSeenRepository (bmn_flip_monitor_seen)
```

### Key Design Decisions
1. **Pure financial logic** — `FinancialService` has zero dependencies (no DB, no WP). All rehab estimation, ROI calculation, MAO, rental analysis, and BRRRR logic is deterministic and fully unit-testable.
2. **ARV reads extractor's bmn_properties table** — Comparable search uses Haversine formula directly against the extractor's properties table. No data duplication.
3. **Multi-strategy analysis** — Every property gets analyzed for Flip, Rental, and BRRRR viability with separate thresholds per strategy.
4. **Disqualification before scoring** — Properties that fail hard rules (too cheap, no comps, too small) are flagged immediately without wasting analysis cycles.
5. **Cascade delete on reports** — Deleting a report removes all associated analyses, comparables, and monitor-seen records.
6. **Monitor tracking** — `bmn_flip_monitor_seen` with UNIQUE KEY (report_id, listing_id) and INSERT ON DUPLICATE KEY UPDATE pattern for efficient new-listing detection.
7. **Expanding radius tiers** — ARV comp search starts at 0.5 miles and expands through [1.0, 2.0, 5.0, 10.0] until enough comps found (min 3).

---

## Previous Phase: 8 - CMA and Analytics - COMPLETE

### Objectives
- [x] Build bmn-cma plugin with 4 migrations, 4 repositories, 4 services, 2 controllers, 1 provider
- [x] Build bmn-analytics plugin with 3 migrations, 3 repositories, 2 services, 2 controllers, 1 provider
- [x] Implement CMA report generation with comparable search, adjustments, confidence scoring, valuation
- [x] Implement analytics event tracking, session management, daily aggregation via WP-Cron
- [x] Write 145 CMA tests (292 assertions) and 88 analytics tests (177 assertions)
- [x] All 1,342 tests pass across 9 suites (zero regressions)
- [x] Docker verification: activate plugins, run migrations, test endpoints

### Deliverables

**bmn-cma plugin:**
- 16 PHP source files (4 migrations, 4 repositories, 4 services, 2 controllers, 1 provider, 1 bootstrap)
- 12 test files + 1 test bootstrap (145 tests, 292 assertions)
- 4 database tables: bmn_cma_reports, bmn_comparables, bmn_cma_value_history, bmn_market_snapshots
- 13 REST endpoints (10 CMA + 3 market conditions)
- Haversine-based comparable search with expanding radius tiers (1, 2, 3, 5, 10 mi)
- FHA-style price adjustments: bedroom 2.5%, bathroom 1%, sqft proportional (10% cap), year built 0.4%/yr (10% cap), garage 2.5%/1.5%, lot 2%/0.25ac (10% cap), gross 40% cap
- 6-factor confidence scoring (sample size 25pts, data completeness 20pts, market stability 20pts, time relevance 15pts, geographic concentration 10pts, comparability quality 10pts)
- Comparable grading: A(<10%), B(<15%), C(<25%), D(<35%), F(>=35%)
- Market conditions service with snapshots and historical trends

**bmn-analytics plugin:**
- 12 PHP source files (3 migrations, 3 repositories, 2 services, 2 controllers, 1 provider, 1 bootstrap)
- 9 test files + 1 test bootstrap (88 tests, 177 assertions)
- 3 database tables: bmn_analytics_events, bmn_analytics_sessions, bmn_analytics_daily
- 9 REST endpoints (4 tracking + 5 reporting)
- Event tracking (pageviews, property views, searches) with session management
- Device detection (mobile/tablet/desktop) and traffic source classification (organic/social/direct/referral)
- Daily aggregation via WP-Cron with upsert for pre-computed metrics
- Active visitor count with configurable time window

### Test Breakdown — bmn-cma (145 tests, 292 assertions)
| Test File | Tests | Assertions |
|-----------|-------|------------|
| MigrationsTest | 11 | ~22 |
| CmaReportRepositoryTest | 10 | ~20 |
| ComparableRepositoryTest | 8 | ~16 |
| ValueHistoryRepositoryTest | 7 | ~14 |
| MarketSnapshotRepositoryTest | 7 | ~14 |
| AdjustmentServiceTest | 30 | ~60 |
| ComparableSearchServiceTest | 9 | ~18 |
| CmaReportServiceTest | 16 | ~32 |
| MarketConditionsServiceTest | 8 | ~16 |
| CmaControllerTest | 20 | ~40 |
| MarketControllerTest | 8 | ~16 |
| CmaServiceProviderTest | 11 | ~22 |
| **Total** | **145** | **292** |

### Test Breakdown — bmn-analytics (88 tests, 177 assertions)
| Test File | Tests | Assertions |
|-----------|-------|------------|
| MigrationsTest | 7 | ~14 |
| EventRepositoryTest | 9 | ~18 |
| SessionRepositoryTest | 8 | ~16 |
| DailyAggregateRepositoryTest | 7 | ~14 |
| TrackingServiceTest | 19 | ~38 |
| ReportingServiceTest | 8 | ~16 |
| TrackingControllerTest | 10 | ~20 |
| ReportingControllerTest | 10 | ~20 |
| AnalyticsServiceProviderTest | 10 | ~20 |
| **Total** | **88** | **177** |

### CMA REST Endpoints (13)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/bmn/v1/cma` | Yes | Generate CMA report |
| GET | `/bmn/v1/cma/sessions` | Yes | List user's CMA sessions (paginated) |
| POST | `/bmn/v1/cma/sessions` | Yes | Save session (alias for generate) |
| GET | `/bmn/v1/cma/sessions/{id}` | Yes | Get single session with comparables |
| PUT | `/bmn/v1/cma/sessions/{id}` | Yes | Update session name/mode |
| DELETE | `/bmn/v1/cma/sessions/{id}` | Yes | Delete session |
| POST | `/bmn/v1/cma/sessions/{id}/favorite` | Yes | Toggle favorite |
| GET | `/bmn/v1/cma/comparables/{listing_id}` | Yes | Find comparables for listing |
| GET | `/bmn/v1/cma/history/{listing_id}` | Yes | Property value history |
| GET | `/bmn/v1/cma/history/trends` | Yes | Value trend data for charting |
| GET | `/bmn/v1/market-conditions` | No | Current market conditions |
| GET | `/bmn/v1/market-conditions/summary` | No | Market summary stats |
| GET | `/bmn/v1/market-conditions/trends` | No | Historical market trends |

### Analytics REST Endpoints (9)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/bmn/v1/analytics/event` | No | Record a generic event |
| POST | `/bmn/v1/analytics/pageview` | No | Record a pageview |
| POST | `/bmn/v1/analytics/property-view` | No | Record a property view |
| GET | `/bmn/v1/analytics/active-visitors` | Yes | Active visitor count |
| GET | `/bmn/v1/analytics/trends` | Yes | Trend data for date range |
| GET | `/bmn/v1/analytics/top-properties` | Yes | Most viewed properties |
| GET | `/bmn/v1/analytics/top-content` | Yes | Most viewed pages |
| GET | `/bmn/v1/analytics/traffic-sources` | Yes | Traffic source breakdown |
| GET | `/bmn/v1/analytics/property/{listing_id}` | Yes | Property-specific stats |

### Architecture
```
bmn-cma:
  CmaController (REST - 10 routes, resource='cma')
    └── CmaReportService → CmaReportRepository (bmn_cma_reports)
                          → ComparableRepository (bmn_comparables)
                          → ValueHistoryRepository (bmn_cma_value_history)
                          → ComparableSearchService → wpdb (bmn_properties, Haversine)
                          → AdjustmentService (pure logic, no deps)

  MarketController (REST - 3 routes, resource='market-conditions')
    └── MarketConditionsService → MarketSnapshotRepository (bmn_market_snapshots)

bmn-analytics:
  TrackingController (REST - 4 routes, resource='analytics')
    └── TrackingService → EventRepository (bmn_analytics_events)
                         → SessionRepository (bmn_analytics_sessions)

  ReportingController (REST - 5 routes, resource='analytics')
    └── ReportingService → EventRepository
                          → SessionRepository
                          → DailyAggregateRepository (bmn_analytics_daily)
```

### Key Design Decisions
1. **CMA reads extractor's bmn_properties table** — Comparable search uses Haversine formula directly against the extractor's properties table. No data duplication.
2. **Pure adjustment logic** — `AdjustmentService` has zero dependencies (no DB, no WP functions). All 6 adjustment types, confidence scoring, and valuation are deterministic and fully unit-testable.
3. **Expanding radius search** — If initial radius returns fewer comps than `min_comps`, automatically tries radius tiers [1, 2, 3, 5, 10] miles until enough results found.
4. **Analytics tracking is public** — POST tracking endpoints require no auth so anonymous visitors can be tracked. Only reporting endpoints require auth.
5. **Daily aggregation via WP-Cron** — `bmn_analytics_daily_aggregate` hook runs daily, pre-computing metrics for fast dashboard queries. Uses UPSERT pattern.
6. **WP_User stub in test bootstraps** — Defined before platform bootstrap so `wp_set/get_current_user` returns `WP_User` instances (matching `RestController::getCurrentUser()` return type).
7. **Anonymous class for DatabaseService** — `DatabaseService` is `final`, so provider tests use anonymous class stand-ins with `getWpdb()` method (matching agents pattern).

---

## Previous Phase: 7 - Agent-Client System - COMPLETE

### Objectives
- [x] Create 6 database migrations (agent_profiles, relationships, shared_properties, referral_codes, referral_signups, activity_log)
- [x] Implement 8 repositories (AgentReadRepository, OfficeReadRepository, AgentProfileRepository, RelationshipRepository, SharedPropertyRepository, ReferralCodeRepository, ReferralSignupRepository, ActivityLogRepository)
- [x] Implement AgentProfileService (merge MLS + profile + office data, search, featured, save profile, link user)
- [x] Implement RelationshipService (assign/unassign agent, create client, get agent clients, authorization checks)
- [x] Implement SharedPropertyService (bulk share, respond, dismiss, record view, agent/client queries)
- [x] Implement ReferralService (code generation, tracking, stats, agent resolution)
- [x] Implement ActivityService (log with auto-agent-resolve, feed, client activity, metrics)
- [x] Implement 5 REST controllers (AgentController, RelationshipController, SharedPropertyController, ReferralController, ActivityController) with 21 endpoints
- [x] Implement AgentsServiceProvider (DI wiring, migrations, rest_api_init)
- [x] Write unit tests for all Phase 7 components (197 tests, 377 assertions)
- [ ] Docker verification: activate plugin, run migrations, test endpoints

### Deliverables
- 25 PHP source files (6 migrations, 8 repositories, 5 services, 5 controllers, 1 provider)
- 20 test files + 1 test bootstrap (197 tests, 377 assertions)
- 1 platform modification (esc_like added to wpdb test stub)
- 21 REST endpoints covering agent profiles, relationships, sharing, referrals, and activity
- Read-only access to extractor's bmn_agents/bmn_offices tables
- Activity logging with automatic agent resolution from relationships
- Bulk property sharing with upsert (agent shares N listings with N clients)
- Referral code system with signup tracking and stats
- All files have `declare(strict_types=1)`
- All SQL uses `$wpdb->prepare()`

### Test Breakdown
| Test File | Tests | Assertions |
|-----------|-------|------------|
| MigrationsTest | 12 | ~24 |
| AgentReadRepositoryTest | 8 | ~16 |
| OfficeReadRepositoryTest | 6 | ~12 |
| AgentProfileRepositoryTest | 9 | ~18 |
| RelationshipRepositoryTest | 10 | ~20 |
| SharedPropertyRepositoryTest | 10 | ~20 |
| ReferralCodeRepositoryTest | 8 | ~16 |
| ReferralSignupRepositoryTest | 7 | ~14 |
| ActivityLogRepositoryTest | 7 | ~14 |
| AgentProfileServiceTest | 14 | ~28 |
| RelationshipServiceTest | 14 | ~28 |
| SharedPropertyServiceTest | 12 | ~24 |
| ReferralServiceTest | 12 | ~24 |
| ActivityServiceTest | 11 | ~22 |
| AgentControllerTest | 12 | ~24 |
| RelationshipControllerTest | 10 | ~20 |
| SharedPropertyControllerTest | 10 | ~20 |
| ReferralControllerTest | 10 | ~20 |
| ActivityControllerTest | 7 | ~14 |
| AgentsServiceProviderTest | 8 | ~18 |
| **Total** | **197** | **377** |

### REST Endpoints (21)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/bmn/v1/agents` | No | List active agents (paginated, with office) |
| GET | `/bmn/v1/agents/featured` | No | Featured agents for homepage |
| GET | `/bmn/v1/agents/{agent_mls_id}` | No | Single agent with profile + office |
| PUT | `/bmn/v1/agents/{agent_mls_id}/profile` | Yes | Update extended profile (admin) |
| POST | `/bmn/v1/agents/{agent_mls_id}/link-user` | Yes | Link MLS agent to WP user (admin) |
| GET | `/bmn/v1/my-agent` | Yes | Get client's assigned agent |
| GET | `/bmn/v1/agent/clients` | Yes | Agent's client list (paginated) |
| POST | `/bmn/v1/agent/clients` | Yes | Create new client + auto-assign |
| PUT | `/bmn/v1/agent/clients/{client_id}/status` | Yes | Update relationship status |
| DELETE | `/bmn/v1/agent/clients/{client_id}` | Yes | Unassign client |
| POST | `/bmn/v1/agent/share-properties` | Yes | Share listing(s) with client(s) |
| GET | `/bmn/v1/shared-properties` | Yes | Get properties shared with me |
| PUT | `/bmn/v1/shared-properties/{id}/respond` | Yes | Client responds interested/not |
| PUT | `/bmn/v1/shared-properties/{id}/dismiss` | Yes | Client dismisses shared listing |
| GET | `/bmn/v1/agent/referral` | Yes | Get agent's referral code + URL + stats |
| POST | `/bmn/v1/agent/referral` | Yes | Set/update custom referral code |
| POST | `/bmn/v1/agent/referral/regenerate` | Yes | Generate new code |
| GET | `/bmn/v1/agent/referral/stats` | Yes | Detailed referral statistics |
| GET | `/bmn/v1/agent/activity` | Yes | Agent's client activity feed |
| GET | `/bmn/v1/agent/metrics` | Yes | Agent dashboard metrics |
| GET | `/bmn/v1/agent/clients/{client_id}/activity` | Yes | Specific client's activity |

### Architecture
```
AgentController (REST - 5 routes, resource='agents')
  └── AgentProfileService → AgentReadRepository (bmn_agents, read-only)
                           → OfficeReadRepository (bmn_offices, read-only)
                           → AgentProfileRepository (bmn_agent_profiles, CRUD)

RelationshipController (REST - 5 routes)
  └── RelationshipService → RelationshipRepository (bmn_agent_client_relationships)

SharedPropertyController (REST - 4 routes)
  └── SharedPropertyService → SharedPropertyRepository (bmn_shared_properties)

ReferralController (REST - 4 routes)
  └── ReferralService → ReferralCodeRepository (bmn_agent_referral_codes)
                       → ReferralSignupRepository (bmn_referral_signups)

ActivityController (REST - 3 routes)
  └── ActivityService → ActivityLogRepository (bmn_agent_activity_log)
                       → RelationshipRepository (auto-resolve agent)
```

### Key Design Decisions
1. **Read extractor tables, don't duplicate** — Agent name/email/phone come from `bmn_agents` (maintained by extractor). `bmn_agent_profiles` only adds extended fields (bio, photo, specialties). Merge at service layer.
2. **`agent_mls_id` for profiles, `user_id` for relationships** — Profiles extend MLS agent records (may not have WP accounts). Relationships are between WP users (both parties must have accounts).
3. **Activity log resolves agent automatically** — `logActivity()` called with just `client_user_id`, service looks up assigned agent. Simplifies integration hooks.
4. **Bulk property sharing with upsert** — `shareProperties()` accepts arrays of clientUserIds and listingIds, creates/updates all combinations. Uses INSERT + ON DUPLICATE KEY UPDATE pattern.
5. **Referral codes are unique globally** — Custom codes validated for uniqueness. Auto-generated codes use 8-char random strings.
6. **Real AuthMiddleware in tests** — `AuthMiddleware` is `final`, so provider tests create real instances with mocked `AuthService` interface (not anonymous stubs).

---

## Previous Phase: 6 - Appointments - COMPLETE

(See session 8 handoff for details — 160 tests, 307 assertions, 10 REST endpoints)

---

## Previous Phase: 5 - Schools - COMPLETE

(See session handoff for details — 165 tests, 284 assertions, 7 REST endpoints)

---

## Previous Phase: 4 - User System - COMPLETE

### Objectives
- [x] Create 4 database migrations (favorites, saved_searches, revoked_tokens, password_resets)
- [x] Implement 4 repositories (FavoriteRepository, SavedSearchRepository, TokenRevocationRepository, PasswordResetRepository)
- [x] Implement UserAuthService (login, register, refresh, logout, forgot-password, reset-password, delete-account, rate limiting)
- [x] Implement FavoriteService (list, toggle, add, remove, batch listing IDs)
- [x] Implement SavedSearchService (CRUD with ownership checks, 25-per-user limit, alert infrastructure)
- [x] Implement UserProfileService (get, update, change password)
- [x] Implement UserProfileFormatter (v1-compatible profile format, WP role mapping)
- [x] Implement AuthController (7 routes: login, register, refresh, forgot-password, logout, me, delete-account)
- [x] Implement FavoriteController (3 routes: index, toggle, remove)
- [x] Implement SavedSearchController (5 routes: index, store, show, update, destroy)
- [x] Implement UserController (3 routes: show, update, changePassword)
- [x] Implement UsersServiceProvider (DI wiring, route registration, token revocation filter, daily cleanup cron)
- [x] Add bmn_is_token_revoked filter to platform AuthMiddleware
- [x] Add bmn-smtp.php mu-plugin for Mailhog SMTP in Docker
- [x] Write unit tests for all Phase 4 components (169 tests, 296 assertions)
- [x] Docker verification: all 18 endpoints tested and working

### Deliverables
- 18 PHP source files (4 migrations, 4 repositories, 5 services, 4 controllers, 1 provider)
- 16 test files + 1 test bootstrap (169 tests, 296 assertions)
- 2 platform modifications (AuthMiddleware revocation filter, test bootstrap add_filter stub)
- 1 mu-plugin (bmn-smtp.php for Docker email routing)
- 18 REST endpoints covering auth, favorites, saved searches, and user profile
- Token revocation with DB persistence and daily cleanup
- Rate limiting (20 attempts/15-min window, 5-min lockout)
- Password reset with SHA-256 hashed tokens, 1-hour expiry
- Email enumeration prevention (forgot-password always returns success)
- All files have `declare(strict_types=1)`
- All SQL uses `$wpdb->prepare()`, URLs use `listing_id` not `listing_key`

### Test Breakdown
| Test File | Tests | Assertions |
|-----------|-------|------------|
| MigrationsTest | 8 | ~16 |
| FavoriteRepositoryTest | 12 | ~24 |
| SavedSearchRepositoryTest | 10 | ~20 |
| TokenRevocationRepositoryTest | 8 | ~16 |
| PasswordResetRepositoryTest | 8 | ~16 |
| UserProfileFormatterTest | 4 | ~8 |
| FavoriteServiceTest | 14 | ~28 |
| SavedSearchServiceTest | 14 | ~28 |
| UserProfileServiceTest | 8 | ~16 |
| UserAuthServiceTest | 30 | ~60 |
| AuthControllerTest | 18 | ~36 |
| FavoriteControllerTest | 10 | ~20 |
| SavedSearchControllerTest | 12 | ~24 |
| UserControllerTest | 8 | ~16 |
| UsersServiceProviderTest | 15 | ~30 |
| **Total** | **169** | **296** |

### REST Endpoints (18)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/bmn/v1/auth/login` | No | Email/password login |
| POST | `/bmn/v1/auth/register` | No | Create account |
| POST | `/bmn/v1/auth/refresh` | No | Refresh tokens |
| POST | `/bmn/v1/auth/forgot-password` | No | Initiate password reset |
| POST | `/bmn/v1/auth/logout` | Yes | Revoke token |
| GET | `/bmn/v1/auth/me` | Yes | Current user profile |
| DELETE | `/bmn/v1/auth/delete-account` | Yes | Delete account + all data |
| GET | `/bmn/v1/users/me` | Yes | Current user profile (alias) |
| PUT | `/bmn/v1/users/me` | Yes | Update profile |
| PUT | `/bmn/v1/users/me/password` | Yes | Change password |
| GET | `/bmn/v1/favorites` | Yes | List favorites (paginated) |
| POST | `/bmn/v1/favorites/{listing_id}` | Yes | Toggle favorite |
| DELETE | `/bmn/v1/favorites/{listing_id}` | Yes | Remove favorite |
| GET | `/bmn/v1/saved-searches` | Yes | List saved searches |
| POST | `/bmn/v1/saved-searches` | Yes | Create saved search |
| GET | `/bmn/v1/saved-searches/{id}` | Yes | Get saved search |
| PUT | `/bmn/v1/saved-searches/{id}` | Yes | Update saved search |
| DELETE | `/bmn/v1/saved-searches/{id}` | Yes | Delete saved search |

### Architecture
```
AuthController (REST - 7 routes)
  └── UserAuthService → AuthService (platform JWT)
                       → EmailService (password reset emails)
                       → TokenRevocationRepository (logout/revocation)
                       → PasswordResetRepository (forgot-password flow)
                       → FavoriteRepository + SavedSearchRepository (delete-account cascade)

FavoriteController (REST - 3 routes)
  └── FavoriteService → FavoriteRepository ($wpdb)

SavedSearchController (REST - 5 routes)
  └── SavedSearchService → SavedSearchRepository ($wpdb)

UserController (REST - 3 routes)
  └── UserProfileService → UserProfileFormatter (static)
                          → WP user functions (wp_update_user, wp_set_password, etc.)

AuthMiddleware (platform)
  └── bmn_is_token_revoked filter → TokenRevocationRepository (via UsersServiceProvider hook)
```

### Key Design Decisions
1. **Token revocation via WordPress filter** — Platform `AuthMiddleware` fires `bmn_is_token_revoked` after JWT validation. The users plugin hooks in to check the DB revocation table. This keeps the platform generic.
2. **DB-backed revocation** — Not WP transients (can be evicted by object cache pressure). DB table guarantees durability with auto-cleanup cron.
3. **Custom tables for favorites/searches** — Not `wp_usermeta`. Enables indexed queries, pagination, and batch operations.
4. **Custom password reset table** — Not WP's `user_activation_key`. Needed for API-driven reset flow (iOS app deep links).
5. **`listing_id` everywhere** — MLS number directly, no listing_key hash translation.
6. **Saved search alert infra prepared** — `last_alert_at`, `result_count`, `new_count` columns built. Actual matching deferred to later phases.
7. **No `final` on mockable classes** — Services and repositories not `final` for PHPUnit 10 mockability.

---

## Previous Phase: 3 - Core Property System - COMPLETE

### Objectives
- [x] Build filter system (StatusResolver, SortResolver, FilterBuilder, FilterResult)
- [x] Implement PropertySearchRepository (read-only DB queries, batch media/open-house fetch, autocomplete)
- [x] Implement PropertySearchService (search orchestration, pagination, caching, school overfetch)
- [x] Implement PropertyDetailService (single listing with photos, agent, office, open houses, history)
- [x] Implement AutocompleteService (6 suggestion types, dedup, priority ranking)
- [x] Implement PropertyController (REST endpoints: search, detail, autocomplete)
- [x] Implement PropertiesServiceProvider (DI wiring, route registration, cache invalidation)
- [x] Update bmn-properties.php bootstrap
- [x] Write unit tests for all Phase 3 components (140 tests, 280 assertions)

### Deliverables
- 13 PHP source files (4 filter, 1 repository, 3 services, 2 models, 1 controller, 1 provider, 1 bootstrap update)
- 10 test files + 1 test bootstrap (140 tests, 280 assertions)
- REST endpoints: `GET /bmn/v1/properties`, `GET /bmn/v1/properties/{listing_id}`, `GET /bmn/v1/properties/autocomplete`
- 14 filter groups: direct lookup, status, location, street, geo (bounds/polygon), type, price, rooms, size, time, parking, amenity, special, school (post-query hook)
- 3-tier caching: search (2min), detail (1hr), autocomplete (5min) with extraction-triggered invalidation
- All files have `declare(strict_types=1)`
- All SQL uses `$wpdb->prepare()`, URLs use `listing_id` not `listing_key`

### Test Breakdown
| Test File | Tests | Assertions |
|-----------|-------|------------|
| StatusResolverTest | 10 | ~20 |
| SortResolverTest | 10 | ~10 |
| FilterBuilderTest | 35 | ~75 |
| PropertySearchRepositoryTest | 12 | ~25 |
| PropertySearchServiceTest | 18 | ~45 |
| PropertyDetailServiceTest | 15 | ~40 |
| AutocompleteServiceTest | 15 | ~30 |
| PropertyControllerTest | 12 | ~20 |
| PropertiesServiceProviderTest | 10 | ~15 |
| **Total** | **140** | **280** |

### Architecture
```
PropertyController (REST)
  ├── PropertySearchService → FilterBuilder → StatusResolver + SortResolver + GeocodingService
  │                         → PropertySearchRepository ($wpdb)
  │                         → CacheService (2-min TTL)
  ├── PropertyDetailService → PropertySearchRepository
  │                         → CacheService (1-hr TTL)
  └── AutocompleteService   → PropertySearchRepository
                            → CacheService (5-min TTL)
```

---

## Previous Phase: 2 - Data Pipeline - COMPLETE

### Objectives
- [x] Implement BridgeApiClient (RESO Web API client with pagination, rate limiting, retry logic)
- [x] Implement DataNormalizer (Bridge API response → normalized property/agent/office records)
- [x] Implement ExtractionEngine (full + incremental sync, batch upserts, photo queueing)
- [x] Implement PropertyRepository (CRUD, upsert, search, stats, batch operations)
- [x] Implement MediaRepository (photo storage, ordering, primary photo selection)
- [x] Implement AgentRepository (agent records linked to properties)
- [x] Implement OfficeRepository (office records linked to agents)
- [x] Implement OpenHouseRepository (open house events linked to properties)
- [x] Implement ExtractionRepository (extraction run tracking with stats)
- [x] Implement PropertyHistoryRepository (price/status change tracking)
- [x] Implement CronManager (WP cron scheduling for daily full + hourly incremental syncs)
- [x] Implement ExtractionController (REST endpoints for status, stats, trigger)
- [x] Implement AdminDashboard (WP admin page with extraction status, stats, manual trigger)
- [x] Implement ExtractorServiceProvider (DI container wiring for all services)
- [x] Create 7 database migrations (properties, media, agents, offices, open_houses, extractions, property_history)
- [x] Write unit tests for all Phase 2 components (126 tests, 298 assertions)

### Deliverables
- 15 PHP source files + 1 admin view template
- 7 database migrations
- 10 test files + 1 test bootstrap (126 tests, 298 assertions)
- Performance-optimized `bmn_properties` table with composite indexes for 100k+ listings
- ExtractorServiceProvider wires all repositories, services, cron, REST, and admin
- All files have `declare(strict_types=1)`
- Zero forbidden patterns, zero deprecation warnings

### Test Breakdown
| Test File | Tests | Assertions |
|-----------|-------|------------|
| DataNormalizerTest | 34 | ~90 |
| PropertyRepositoryTest | 15 | ~40 |
| ExtractionEngineTest | 13 | ~35 |
| BridgeApiClientTest | 13 | ~35 |
| ExtractionRepositoryTest | 13 | ~30 |
| ExtractionControllerTest | 10 | ~25 |
| CronManagerTest | 8 | ~15 |
| PropertyHistoryRepositoryTest | 8 | ~15 |
| AdminDashboardTest | 7 | ~8 |
| ExtractorServiceProviderTest | 5 | ~5 |
| **Total** | **126** | **298** |

---

## Previous Phase: 1 - Platform Foundation - COMPLETE

### Objectives
- [x] Implement AuthService (JWT encode/decode/verify, 30-day tokens, HS256)
- [x] Implement AuthMiddleware (JWT priority over WP session, CDN bypass, role checking)
- [x] Implement DatabaseService (connection wrapper, query builder, batch operations)
- [x] Implement CacheService (transient-based, group TTLs, remember pattern)
- [x] Implement EmailService (template interpolation, unified footer, agent personalization)
- [x] Implement LoggingService (activity logging to DB, CDN-aware IP, performance monitoring)
- [x] Implement GeocodingService (haversine, point-in-polygon, SQL builders, geocoding with cache)
- [x] Create PlatformServiceProvider (DI container wiring for all services)
- [x] Create activity_log database migration
- [x] Enhanced test bootstrap with comprehensive WP stubs and wpdb class
- [x] Write unit tests for all Phase 1 services (138 tests, 272 assertions)

### Deliverables
- 24 PHP source files (interfaces + implementations)
- 1 database migration
- 10 test files (138 tests, 272 assertions, 1 skipped)
- PlatformServiceProvider wires 7 services as singletons
- All files have `declare(strict_types=1)`
- Zero forbidden patterns

### Test Breakdown
| Test File | Tests | Assertions |
|-----------|-------|------------|
| ContainerTest | 8 | 8 |
| ApiResponseTest | 9 | 29 |
| JwtAuthServiceTest | 23 | 67 |
| AuthMiddlewareTest | 13 | 26 |
| TransientCacheServiceTest | 18 | 32 |
| WpEmailServiceTest | 17 | 26 |
| LoggingServiceTest | 12 | 25 |
| SpatialServiceTest | 17 | ~25 |
| DatabaseServiceTest | 12 | ~22 |
| PlatformServiceProviderTest | 9 | ~12 |
| **Total** | **138** | **272** |

## Previous Phase: 0 - Project Setup

### Deliverables
- 105 files, 13,331 lines of code
- PHPUnit: 17 tests, 37 assertions (Container + ApiResponse)
- Vite: builds in 122ms (main.js + style.css)
- All 15 PHP source files have `declare(strict_types=1)`
- Zero forbidden patterns
- Tagged v2.0.0-phase0
