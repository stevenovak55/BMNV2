# Session Handoff - 2026-02-18 (Session 22)

## Phase: 11f - Map Search UI/UX Polish + Clustering

## What Was Accomplished This Session

### 1. Map Search UI/UX Polish (15 fixes across 3 tiers)

**Tier 1 — Functional Bugs (5 fixes):**

1. **Pin click idle suppression** — `_suppressIdle = true` in `onPinClick()` and `centerOnProperty()` prevents idle → refetch → info window disappearance
2. **Filter reframe** — `fetchProperties(useBounds)` parameter; `submitFilters()` does non-bounds fetch then `fitBounds()` to reframe map to show all matching results
3. **Orphaned activeMarkerId** — Reset `activeMarkerId = ''` when `updateMarkers()` closes info window; sidebar card no longer stays highlighted after info window closes
4. **history.pushState** — Filter changes push URL state; `popstate` listener re-hydrates filters on back/forward; URLs are shareable
5. **Stale fetch error handling** — Catch block preserves existing listings instead of wiping them; sets `fetchError` flag

**Tier 2 — UX Polish (5 fixes):**

6. **Sidebar scroll preservation** — Saves/restores `scrollTop` around re-renders so sidebar doesn't jump to top
7. **Error state UI** — Dedicated error template with warning icon + "Try Again" button; distinguishes network errors from empty results
8. **Bidirectional pin hover** — Map pin mouseenter/leave highlights sidebar card via direct DOM manipulation (no Alpine re-render)
9. **Active pin highlight** — Clicked pin gets `.bmn-pin-active` class (persistent red), cleared on close/click-another
10. **Card selection ring** — Upgraded from subtle `ring-1 ring-teal-200` to visible `ring-2 ring-teal-400 shadow-sm`

**Tier 3 — Minor Polish (4 fixes):**

11. **Resize handle idle suppression** — `_suppressIdle = true` before resize trigger prevents refetch
12. **Mobile toggle z-index** — `x-show="!saveSearchOpen"` hides mobile pill behind save search modal
13. **Debounce cleanup** — `beforeunload` listener clears pending debounce timer
14. **Unhighlight z-index reset** — Uses `'1'` instead of `''` for consistent overlay base layer

### 2. Map Pin Clustering

Implemented grid-based spatial clustering using Google Maps' Mercator projection — no external library needed.

**How it works:**
- `computeClusters()` converts listing lat/lng to world-pixel coordinates at current zoom level
- Listings in the same 100px grid cell are grouped into a `ClusterGroup`
- Single-listing groups render as price pins; multi-listing groups render as cluster bubbles
- Clusters naturally break apart as users zoom in (grid cells shrink in geographic space)

**Components added:**
- `ClusterMarkerOverlay` class (extends `google.maps.OverlayView`) — circular teal bubble with count
- `computeClusters()` function — grid-based clustering with centroid calculation
- `onClusterClick()` — zooms to `fitBounds()` of cluster's listings
- 3 CSS size tiers: default 40px, medium 48px (10+), large 56px (50+)

**Capacity increase:**
- `MAX_PINS`: 200 → 1000
- `per_page`: 250 → 1000

## Files Changed

| File | Action | Description |
|------|--------|-------------|
| `themes/bmn-theme/assets/src/ts/components/map-search.ts` | MODIFIED | 15 UX fixes + clustering (ClusterMarkerOverlay, computeClusters, onClusterClick) |
| `themes/bmn-theme/page-map-search.php` | MODIFIED | Error state template, card ring upgrade, mobile toggle z-index fix |
| `themes/bmn-theme/assets/src/scss/main.scss` | MODIFIED | .bmn-pin-active style, .bmn-cluster styles (3 size tiers) |
| `themes/bmn-theme/assets/dist/*` | REBUILT | Vite production build |

## Browser Testing Completed

All 12 test items from the plan were verified:
1. Pin click stability — info window stays open
2. Filter reframe — map zooms to show filtered results
3. Active card/pin highlighting — bidirectional
4. Back button and shareable URLs
5. Sidebar scroll preservation
6. Error state display
7. Resize handle doesn't refetch
8. Clustering — pins merge into count bubbles, click to zoom in

## Known Issues / Next Session

### Remaining QA Items (from earlier sessions, not yet browser-tested)
- View toggle (List ↔ Map) preserves all current filters in URL params
- Mobile responsive — filter bar wraps, bottom pill toggle
- HTMX partial rendering still works on list view
- Homepage cards show images, status badges, DOM labels with teal accents
- Autocomplete dispatch mode on both search pages
- Save Search modal on both search pages

### Not Included (intentional scope boundaries)
- Map clustering improvements (dynamic grid size, animation)
- Loading skeleton cards
- Accessibility (ARIA labels, focus traps)
- Touch resize handle (hidden on mobile)

## Docker Environment
- WordPress: http://localhost:8082 (admin: novak55 / Google44*)
- phpMyAdmin: http://localhost:8083
- All containers healthy

## Commit
- `feat(theme): map search UX polish (15 fixes) + grid-based pin clustering`
