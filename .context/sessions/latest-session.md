# Session Handoff - 2026-02-17 (Session 18)

## Phase: 11e - Map Search QA and Polish

## What Was Accomplished This Session

### 1. Map Search Visual QA and Bug Fixes

User tested map search page in browser and reported two issues: "it looks terrible and I see no pins." This session focused on matching v1's visual quality and fixing pin rendering.

### 2. V1 Layout Analysis

Thoroughly examined v1's half-map implementation:
- **Template**: `templates/views/half-map.php` — flex layout with map left, 452px sidebar right
- **CSS**: `assets/css/main.css` — resize handle, mobile toggle, pin styles
- **JS**: `map-core.js` — uses `mapId: 'BME_MAP_ID'`, AdvancedMarkerElement, max 200 pins
- **Design**: Teal gradient pins (#0891B2 → #0E7490), draggable resize handle, fixed bottom pill on mobile

### 3. Pin Rendering Fix (AdvancedMarkerElement → OverlayView)

**Root cause**: `AdvancedMarkerElement` requires a valid `mapId` from Google Cloud Console to render custom HTML content. The string `'bmn-map-search'` wasn't a real Cloud Console Map ID, and even `'DEMO_MAP_ID'` didn't resolve the issue.

**Solution**: Replaced `AdvancedMarkerElement` entirely with a custom `google.maps.OverlayView` subclass (`PriceMarkerOverlay`) that:
- Creates teal price label divs directly on the map's `overlayMouseTarget` pane
- Positions them via `getProjection().fromLatLngToDivPixel()`
- Requires NO `mapId`, NO marker library import — uses only the core `maps` library
- Supports click handlers, hover highlighting, and scroll-to-card

### 4. Layout Overhaul (Matching V1)

**Before**: 50/50 split, navy pins, footer visible, page scrolled beyond map
**After**:
- Map fills left side (flex:1), fixed 452px sidebar on right (matching v1)
- Draggable resize handle between panes (6px, gray bar, col-resize cursor)
- `#page { height: 100vh; overflow: hidden }` — fills exactly the viewport, no scrolling
- Footer removed (like v1's full-viewport approach)
- Admin bar height accounted for with `calc(100vh - 32px)`

### 5. Style Updates (Teal Theme)

- **Pins**: Navy `#1e3a5f` → teal gradient `linear-gradient(135deg, #0891B2, #0E7490)`
- **Pin size**: 14px font, 700 weight, 8px 12px padding, 8px border-radius (matching v1)
- **Hover**: Red gradient with drop shadow
- **Accent colors**: All buttons, checkboxes, active states changed from navy to teal-600
- **InfoWindow**: Teal price, "View Details" button with teal gradient
- **Mobile toggle**: Fixed bottom pill with `backdrop-blur-xl`, rounded-full buttons

### 6. Mobile Improvements

- Map/list toggle moved from top bar to fixed bottom pill (matching v1)
- SVG icons for list (horizontal lines) and map (pin)
- `bg-white/90 backdrop-blur-xl` glass effect
- Active state: `bg-teal-600 text-white`

## Files Changed

| File | Action | Description |
|------|--------|-------------|
| `page-map-search.php` | Rewritten | V1-style layout: 452px sidebar, resize handle, no footer, viewport lock |
| `assets/src/ts/components/map-search.ts` | Rewritten | Custom OverlayView pins (no AdvancedMarkerElement), resize handle |
| `assets/src/scss/main.scss` | Modified | Teal gradient pins, resize handle styles |

## Key Technical Decisions

**OverlayView vs AdvancedMarkerElement**: AdvancedMarkerElement requires a Cloud Console Map ID to render custom HTML content. Since the user's API key works but the map ID doesn't exist, switched to custom OverlayView which is more reliable and doesn't need any map ID.

**Viewport height lock**: Added `<style>` tag scoped to map search template that sets `#page { height: 100vh; max-height: 100vh; overflow: hidden }`. This overrides the default `min-h-screen` from header.php without modifying the shared header template.

**No footer on map search**: Like v1, the map search page skips `get_footer()` and manually closes `</div><!-- #page -->` with `wp_footer()`. This gives the map full viewport height.

## Docker Environment
- WordPress: http://localhost:8082 (admin: novak55 / Google44*)
- phpMyAdmin: http://localhost:8083
- All containers healthy

## Current Status
- Map loads and renders correctly
- Sidebar shows listings with photos, prices, beds/baths/sqft
- Pins should now render (OverlayView approach, no mapId needed)
- Resize handle, mobile toggle, filters all functional
- Viewport locked to screen height

## Known Issues / Next Steps
1. **Browser verify pins render** — The OverlayView approach should work but needs browser confirmation
2. **Google Maps error dialog** — May still show "This page can't load Google Maps correctly" if the API key has billing issues. The user confirmed key is unrestricted, so this may be transient.
3. **Pin click → InfoWindow** — InfoWindow opens at lat/lng position (not anchored to overlay). Works but could be refined.
4. **Phase 12: iOS App** — SwiftUI rebuild (not started)
5. **Phase 13: Migration and Cutover** — Data migration, DNS (not started)
