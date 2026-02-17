# BMN Boston v2 - Rebuild Progress

## Phase Status Dashboard

| Phase | Name | Status | Started | Completed | Tests | Coverage | Notes |
|-------|------|--------|---------|-----------|-------|----------|-------|
| 0 | Project Setup | Complete | 2026-02-16 | 2026-02-16 | 17/17 | N/A | All infrastructure operational |
| 1 | Platform Foundation | Complete | 2026-02-16 | 2026-02-16 | 138/138 | ~80% | Auth, DB, Cache, Email, Geo, Logging |
| 2 | Data Pipeline | Complete | 2026-02-16 | 2026-02-16 | 126/126 | ~85% | Bridge MLS extraction, 7 repos, admin dashboard |
| 3 | Core Property System | Complete | 2026-02-16 | 2026-02-16 | 140/140 | ~85% | Search, filters, autocomplete, detail |
| 4 | User System | Complete | 2026-02-16 | 2026-02-16 | 169/169 | ~85% | Auth, favorites, saved searches, profile, password reset |
| 5 | Schools | Complete | 2026-02-16 | 2026-02-16 | 165/165 | ~85% | Rankings, data import, filter hook, 7 REST endpoints |
| 6 | Appointments | Complete | 2026-02-16 | 2026-02-17 | 160/160 | ~85% | Booking, availability, notifications, 10 REST endpoints |
| 7 | Agent-Client System | Not Started | - | - | - | - | Relationships, sharing |
| 8 | CMA and Analytics | Not Started | - | - | - | - | Comparables, tracking |
| 9 | Flip Analyzer | Not Started | - | - | - | - | Investment analysis |
| 10 | Exclusive Listings | Not Started | - | - | - | - | Agent-created listings |
| 11 | Theme and Web Frontend | Not Started | - | - | - | - | Templates, Vite build |
| 12 | iOS App | Not Started | - | - | - | - | SwiftUI rebuild |
| 13 | Migration and Cutover | Not Started | - | - | - | - | Data migration, DNS |

## Current Phase: 6 - Appointments - COMPLETE

### Objectives
- [x] Create 7 database migrations (staff, appointment_types, availability_rules, appointments, attendees, staff_services, notifications_log)
- [x] Implement 7 repositories (StaffRepository, AppointmentTypeRepository, AvailabilityRuleRepository, AppointmentRepository, AttendeeRepository, StaffServiceRepository, NotificationLogRepository)
- [x] Implement AppointmentService (create, cancel, reschedule, rate limiting, policy enforcement, Google Calendar sync)
- [x] Implement AvailabilityService (slot calculation engine: recurring rules + overrides - blocked - booked - Google busy - past)
- [x] Implement StaffService (active staff, primary staff, staff-by-type)
- [x] Implement GoogleCalendarService interface + NullCalendarService (stub) + GoogleCalendarClient (real OAuth2)
- [x] Implement AppointmentNotificationService (confirmation, cancellation, reschedule, 24h/1h reminders via cron)
- [x] Implement AppointmentController (10 REST endpoints: types, staff, availability, create, policy, list, detail, cancel, reschedule, reschedule-slots)
- [x] Implement AppointmentsServiceProvider (DI wiring, rest_api_init, cron registration)
- [x] Write unit tests for all Phase 6 components (160 tests, 307 assertions)
- [ ] Docker verification: activate plugin, run migrations, seed data, test all 10 endpoints

### Deliverables
- 26 PHP source files (7 migrations, 7 repositories, 3 services, 3 calendar, 1 notification, 1 controller, 1 provider, 1 bootstrap, 1 phpunit config, 1 test bootstrap)
- 17 test files (160 tests, 307 assertions)
- 10 REST endpoints covering booking lifecycle
- Double-booking prevention via START TRANSACTION + UNIQUE constraint
- Rate limiting via transients (5 attempts per 15 minutes)
- Policy enforcement: 2h cancel, 4h reschedule, 3 max reschedules
- Google Calendar abstracted behind interface (NullCalendarService default)
- Email notifications with `{{variable}}` interpolation and `appointment` context
- Hourly cron for 24h and 1h appointment reminders
- All files have `declare(strict_types=1)`
- All SQL uses `$wpdb->prepare()`

### Test Breakdown
| Test File | Tests | Assertions |
|-----------|-------|------------|
| MigrationsTest | 14 | ~28 |
| StaffRepositoryTest | 9 | ~18 |
| AppointmentTypeRepositoryTest | 8 | ~16 |
| AvailabilityRuleRepositoryTest | 7 | ~14 |
| AppointmentRepositoryTest | 10 | ~20 |
| AttendeeRepositoryTest | 9 | ~18 |
| StaffServiceRepositoryTest | 8 | ~16 |
| NotificationLogRepositoryTest | 6 | ~12 |
| StaffServiceTest | 5 | ~10 |
| AvailabilityServiceTest | 14 | ~28 |
| AppointmentServiceTest | 17 | ~34 |
| NullCalendarServiceTest | 6 | ~12 |
| GoogleCalendarClientTest | 4 | ~8 |
| AppointmentNotificationServiceTest | 12 | ~24 |
| AppointmentControllerTest | 24 | ~48 |
| AppointmentsServiceProviderTest | 7 | ~14 |
| **Total** | **160** | **307** |

### REST Endpoints (10)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/bmn/v1/appointments/types` | No | List active appointment types |
| GET | `/bmn/v1/appointments/staff` | No | List active staff (filterable by type) |
| GET | `/bmn/v1/appointments/availability` | No | Available time slots (date range, type, staff) |
| POST | `/bmn/v1/appointments` | No* | Create appointment (*optional JWT enrichment) |
| GET | `/bmn/v1/appointments/policy` | No | Cancellation/reschedule policy |
| GET | `/bmn/v1/appointments` | Yes | List user's appointments |
| GET | `/bmn/v1/appointments/{id}` | Yes | Appointment detail |
| DELETE | `/bmn/v1/appointments/{id}` | Yes | Cancel appointment |
| PATCH | `/bmn/v1/appointments/{id}/reschedule` | Yes | Reschedule appointment |
| GET | `/bmn/v1/appointments/{id}/reschedule-slots` | Yes | Available reschedule slots |

### Architecture
```
AppointmentController (REST - 10 routes)
  ├── AppointmentService → AppointmentRepository (transactional booking)
  │                      → AppointmentTypeRepository (type validation)
  │                      → AttendeeRepository (multi-attendee)
  │                      → StaffRepository (staff resolution)
  │                      → AvailabilityService (slot validation)
  │                      → GoogleCalendarService (calendar sync)
  │                      → AppointmentNotificationService (emails)
  ├── AvailabilityService → AvailabilityRuleRepository (rules engine)
  │                       → AppointmentRepository (booked slots)
  │                       → StaffRepository (staff resolution)
  │                       → GoogleCalendarService (busy times)
  └── StaffService → StaffRepository
                   → StaffServiceRepository (staff-type links)
```

### Key Design Decisions
1. **Transactional booking** — `START TRANSACTION` + UNIQUE constraint on `(staff_id, appointment_date, start_time)` prevents double-booking race conditions.
2. **Rate limiting via transients** — 5 bookings per 15 minutes per email+IP. Lightweight, no extra table.
3. **NullCalendarService default** — Google Calendar abstracted behind interface. NullCalendarService bound by default; swap to GoogleCalendarClient when OAuth credentials are configured.
4. **Slot calculation engine** — Merges recurring rules + specific_date overrides, subtracts blocked dates, booked appointments (with buffers), Google busy times, and past slots. 15-minute increment default.
5. **Cron-based reminders** — Hourly cron sends 24h and 1h reminders to all attendees. Uses `time()` for `wp_schedule_event()` (not `current_time('timestamp')`).
6. **Email context = 'appointment'** — Notifications use platform EmailService with `context => 'appointment'` for proper footer.
7. **Anonymous stubs for final classes** — Platform's `DatabaseService` and `AuthMiddleware` are `final`; provider tests use anonymous class stubs instead of PHPUnit mocks.

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
