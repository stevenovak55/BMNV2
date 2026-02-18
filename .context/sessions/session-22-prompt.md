This is a continuation of Session 21 for BMN Boston V2, Phase 11f (Unified
Enterprise-Grade Search Experience — QA + Polish).

Read these files first to get full context:
- ~/Development/BMNBoston-v2/CLAUDE.md
- ~/Development/BMNBoston-v2/.context/sessions/latest-session.md

Session 21 summary (commit ee212d9, pushed):
- Fixed P0 map pin filtering bug — Alpine.js reactive Proxy was wrapping the
  Google Maps Map object. Google Maps APIs (OverlayView.setMap, getPanes,
  getProjection) use internal WeakMap lookups keyed by object identity;
  Proxy !== original broke these lookups, causing overlay divs to never render
  after filter-triggered re-renders.
- Fix: stored raw Map reference in module-level `_rawMap` variable before
  Alpine proxies it. All Google Maps API calls now use `_rawMap` instead of
  `this.map`. Added `_suppressIdle` flag to prevent idle cascade after filter
  submissions.
- All tests pass: Platform 145/145, Properties 140/140

FIRST PRIORITY — Browser-verify the map pin fix:
1. Start Docker if needed: cd ~/Development/BMNBoston-v2/wordpress && docker-compose up -d
2. Build theme if needed: cd ~/Development/BMNBoston-v2/wordpress/wp-content/themes/bmn-theme && npm run build
3. Open http://localhost:8082/map-search/ in browser
4. Open DevTools Console (filter to [MapSearch])
5. Apply a price filter (e.g., Min $100K, Max $400K)
6. EXPECTED: sidebar count drops (386 → ~17) AND map pins update to match
7. Console logs should show: "idle suppressed", "fetch #N complete: 17 listings, updating 17 markers"

If the map pin fix is CONFIRMED working, move on to the remaining QA items:

QA items to browser-test:
- View toggle (List ↔ Map) preserves all current filters in URL params
- Mobile responsive — filter bar wraps properly, bottom pill toggle on map
- HTMX partial rendering still works on list view (pagination, sort changes)
- Homepage cards show images, status badges, DOM labels with teal accents
- Autocomplete works in dispatch mode on both search pages
- Save Search modal opens and submits on both search pages
- Filter chips appear and can be removed
- Reset filters clears everything

If the map pin fix is NOT working:
- Add logging inside PriceMarkerOverlay.onAdd() and draw() methods to confirm
  whether getPanes() returns a valid object and getProjection() returns a valid
  projection
- Check if the issue is that onAdd() is never called (setMap not registering)
  vs onAdd() runs but draw() never fires
- The file to edit: themes/bmn-theme/assets/src/ts/components/map-search.ts
- After changes, rebuild: npm run build (from theme dir)

Key files:
- themes/bmn-theme/assets/src/ts/components/map-search.ts — Alpine component
- themes/bmn-theme/assets/src/ts/lib/filter-engine.ts — Filter state management
- themes/bmn-theme/assets/src/ts/components/property-search.ts — List search
- themes/bmn-theme/page-map-search.php — Map search template
- themes/bmn-theme/page-property-search.php — List search template
- themes/bmn-theme/template-parts/search/filter-bar.php — Shared filter bar

Docker environment:
- WordPress: http://localhost:8082 (admin: novak55 / Google44*)
- phpMyAdmin: http://localhost:8083
