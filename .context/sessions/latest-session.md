# Session Handoff - 2026-02-17 (Session 19)

## Phase: 11f - Unified Enterprise-Grade Search Experience

## What Was Accomplished This Session

### 1. Shared TypeScript Foundation (Phase A)

Created three new modules in `assets/src/ts/lib/`:

- **`filter-engine.ts`** — `SearchFilters` interface covering 30+ filter keys, pure functions: `createFilterState()`, `filtersToParams()` (uses `paged` for V2), `filtersFromParams()`, `filtersToUrl()`, `toggleArrayValue()`, `getActiveChips()`, `removeChip()`, `hasActiveFilters()`, `formatPriceRange()`
- **`favorites-store.ts`** — Singleton with optimistic localStorage + JWT API sync. `isFavorite()`, `toggle()`, `onChange()`. Guest users get local-only storage.
- **`property-utils.ts`** — `formatPrice()`, `escapeHtml()`, `getPropertyUrl()`, `getStatusColor()`, `getDomLabel()`

Created `components/save-search-modal.ts` — Alpine component for naming/saving searches via POST `/bmn/v1/saved-searches`.

### 2. List View Conversion (Phase B)

- **`property-search.ts`** — Rewritten as thin Alpine wrapper delegating to filter-engine. Added: `_getFilters()/_setFilters()` for engine interop, `activeChips` computed, `removeChip()`, `getMapSearchUrl()`, `moreFiltersOpen`, `saveSearchOpen`, `favStore`, all advanced filter fields.
- **`filter-bar.php`** (NEW) — Redfin-style horizontal bar: location autocomplete, Price/Beds-Baths/Type dropdown popovers, More toggle, Sort, List/Map view toggle, Save Search button, active filter chips row with Clear All.
- **`more-filters.php`** (NEW) — Collapsible panel: sqft min/max, lot size, year built, days on market, garage, school grade, checkboxes (price reduced, new this week, virtual tour, fireplace, open house, exclusive).
- **`property-card.php`** — Added: status badge (green/yellow/red, top-left), "New" badge for DOM < 7, favorite heart (top-right, Alpine-bound), teal price badge, teal hover, DOM days in specs row.
- **`page-property-search.php`** — Full-width layout (no sidebar), includes filter-bar.php, 24 per_page, save search modal.
- **`results-grid.php`** — 4-column grid (`xl:grid-cols-4`), passes `status`, `dom`, `listing_id` to cards.
- **`pagination.php`** — Active page color `bg-navy-700` → `bg-teal-600`.
- **`filter-sidebar.php`** — DELETED (replaced by filter-bar.php).

### 3. Map View Conversion (Phase C)

- **`map-search.ts`** — Integrated filter-engine while preserving OverlayView pins, resize handle, all map functionality. Added: `_getFilters()/_setFilters()`, `_hydrateFromUrl()`, `activeChips`, `removeChip()`, `getListSearchUrl()`, `favStore`, all advanced filter fields. Uses `formatPrice`/`escapeHtml`/`getPropertyUrl` from property-utils.
- **`page-map-search.php`** — Replaced 80-line inline filter panel with `get_template_part('template-parts/search/filter-bar', null, array('view' => 'map'))`. Added status badges and favorite hearts to sidebar cards.
- **`autocomplete.ts`** — Added dispatch mode: when inside a parent with `submitFilters` (search pages), fires `$dispatch('autocomplete:select', {...})` instead of navigating. Homepage retains navigate mode.

### 4. Config Updates (Phase D)

- **`tailwind.config.js`** — Added teal color scale (50-900).
- **`main.scss`** — Added: `.btn-search`, `.filter-popover`, `.filter-chip`, `.fav-heart`, `.scrollbar-hide`. Preserved existing `.bmn-pin`, `.bmn-resize-handle` styles.
- **`functions.php`** — Added `favoritesApiUrl`, `savedSearchesApiUrl` to `bmnTheme` localized object.
- **`helpers.php`** — Changed `per_page` from 21 to 24.
- **`main.ts`** — Registered `saveSearchModal` component.

### 5. Homepage + Build (Phase E)

- **`section-listings.php`** — Normalizes API keys (maps `main_photo_url` → `photo`, adds `status`, `dom`) before passing to card. Fixed missing images bug.
- **Vite build** — Compiled successfully: `main-DRHgIAWD.js` (154KB, 46KB gzip).

### 6. Bug Fixes

- **Homepage images missing**: Raw API data uses `main_photo_url` but card expects `photo`. Fixed by normalizing keys in section-listings.php (same pattern as results-grid.php).
- **Filter dropdowns hidden**: `overflow-x-auto` on filter bar container implicitly set `overflow-y: auto`, clipping absolutely positioned dropdown popovers. Fixed by replacing with `flex-wrap`.

## Files Changed

| File | Action | Description |
|------|--------|-------------|
| `assets/src/ts/lib/filter-engine.ts` | NEW | Shared filter state management (pure functions) |
| `assets/src/ts/lib/favorites-store.ts` | NEW | Optimistic localStorage + API sync singleton |
| `assets/src/ts/lib/property-utils.ts` | NEW | Shared formatters (price, HTML, URLs, status) |
| `assets/src/ts/components/save-search-modal.ts` | NEW | Save search Alpine component |
| `assets/src/ts/components/property-search.ts` | REWRITTEN | Thin wrapper over filter-engine |
| `assets/src/ts/components/map-search.ts` | REWRITTEN | Filter-engine integrated, OverlayView preserved |
| `assets/src/ts/components/autocomplete.ts` | MODIFIED | Added dispatch mode for search pages |
| `assets/src/ts/main.ts` | MODIFIED | Registered saveSearchModal |
| `template-parts/search/filter-bar.php` | NEW | Shared horizontal filter bar |
| `template-parts/search/more-filters.php` | NEW | Collapsible advanced filters |
| `template-parts/components/property-card.php` | REWRITTEN | Status badges, DOM, hearts, teal |
| `template-parts/search/results-grid.php` | MODIFIED | 4-col grid, passes status/dom |
| `template-parts/search/pagination.php` | MODIFIED | Teal active page |
| `template-parts/search/filter-sidebar.php` | DELETED | Replaced by filter-bar.php |
| `template-parts/homepage/section-listings.php` | MODIFIED | API key normalization |
| `page-property-search.php` | REWRITTEN | Full-width, filter bar, 24/page |
| `page-map-search.php` | REWRITTEN | Shared filter bar replaces inline panel |
| `tailwind.config.js` | MODIFIED | Added teal color scale |
| `assets/src/scss/main.scss` | MODIFIED | Search-specific classes |
| `functions.php` | MODIFIED | Added API URLs to bmnTheme |
| `inc/helpers.php` | MODIFIED | per_page 21→24 |

## What Needs QA / Next Steps

1. **Browser test all filter dropdowns** — Price, Beds/Baths, Type popovers open and filter correctly
2. **Active chips** — Appear below filter bar, clicking X removes filter and re-fetches
3. **View toggle** — List ↔ Map preserves all current filters in URL params
4. **Autocomplete on search pages** — Dispatches to parent filter state instead of navigating
5. **Favorite hearts** — Toggle on click (optimistic UI), persist to localStorage
6. **Save Search modal** — Opens, names, saves with JWT
7. **Status badges** — Green (Active), yellow (Pending), red (Sold) on cards
8. **"New" badge** — Appears on listings with DOM < 7
9. **HTMX partial rendering** — Still works on list view after filter changes
10. **Mobile responsive** — Filter bar wraps, bottom pill toggle on map
11. **Map pins** — Still render with OverlayView (unchanged approach)
12. **Homepage cards** — Show images, status, DOM with teal accents

## Docker Environment
- WordPress: http://localhost:8082 (admin: novak55 / Google44*)
- phpMyAdmin: http://localhost:8083
- All containers healthy
