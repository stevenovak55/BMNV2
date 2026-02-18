This is a continuation of Session 22 for BMN Boston V2, Phase 11f (Map Search UX
Polish + Clustering).

Read these files first to get full context:
- ~/Development/BMNBoston-v2/CLAUDE.md
- ~/Development/BMNBoston-v2/.context/sessions/latest-session.md

Session 22 summary (commit 6653d2e, pushed):
- Implemented 15 UX/interaction fixes across 3 tiers for the map search page
- Added grid-based pin clustering (no external library) with ClusterMarkerOverlay
- Increased capacity: MAX_PINS 200→1000, per_page 250→1000
- All changes browser-verified at http://localhost:8082/map-search/

Key files modified:
- themes/bmn-theme/assets/src/ts/components/map-search.ts — 15 fixes + clustering
- themes/bmn-theme/page-map-search.php — error state, card ring, mobile toggle
- themes/bmn-theme/assets/src/scss/main.scss — .bmn-pin-active, .bmn-cluster styles

REMAINING QA ITEMS — Browser-test these at http://localhost:8082:
1. View toggle (List ↔ Map) — click "Map" on /property-search/ or "List" on
   /map-search/ and verify all current filters carry over in URL params
2. Mobile responsive — resize browser to mobile width, verify filter bar wraps
   properly, bottom pill toggle appears on map search
3. HTMX partial rendering — on /property-search/, change sort order or click
   pagination; page should NOT full-reload, only the results grid should update
4. Homepage cards — visit / and verify property cards show images, status badges,
   DOM labels ("New", "3 Days"), teal accent colors
5. Autocomplete — on both search pages, type in the search box; suggestions
   should appear (cities, neighborhoods, addresses, streets, MLS numbers)
6. Save Search modal — click "Save Search" on both search pages; modal should
   open, accept a name, and save via API
7. Filter chips — apply multiple filters, verify chips appear below filter bar;
   click X on a chip to remove that filter

After QA, if all items pass, close out Phase 11f and update CLAUDE.md/progress
docs. The next major phase is Phase 12 (iOS SwiftUI rebuild).

Docker environment:
- Start: cd ~/Development/BMNBoston-v2/wordpress && docker-compose up -d
- Build theme: cd ~/Development/BMNBoston-v2/wordpress/wp-content/themes/bmn-theme && npm run build
- WordPress: http://localhost:8082 (admin: novak55 / Google44*)
- phpMyAdmin: http://localhost:8083
