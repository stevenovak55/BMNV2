/**
 * Map Search Component (Alpine.js + Google Maps)
 *
 * Split-screen map search with:
 * - Custom OverlayView price pins (no mapId needed)
 * - Shared filter-engine for state management
 * - Draggable resize handle between panes
 * - Mobile toggle between map and list
 * - Favorite hearts on sidebar cards
 *
 * @version 3.0.0
 */

import {
  type SearchFilters,
  createFilterState,
  filtersToParams,
  filtersToApiParams,
  filtersFromParams,
  toggleArrayValue,
  getActiveChips,
  removeChip as engineRemoveChip,
  hasActiveFilters,
  type FilterChip,
} from '../lib/filter-engine';
import { favStore } from '../lib/favorites-store';
import { formatPrice, escapeHtml, getPropertyUrl } from '../lib/property-utils';

declare const bmnTheme: {
  restUrl: string;
  searchUrl: string;
  mapSearchUrl: string;
  homeUrl: string;
};

declare const bmnPageData: {
  propertiesApiUrl: string;
  schoolsApiUrl: string;
  propertyTypes: string[];
};

interface PropertyListing {
  listing_id: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  price: number;
  beds: number;
  baths: number;
  sqft: number;
  latitude: number;
  longitude: number;
  main_photo_url: string;
  property_type: string;
  property_sub_type: string;
  status: string;
  dom: number;
}

/* ------------------------------------------------------------------ */
/*  Price Label Overlay – custom OverlayView for teal price pins      */
/* ------------------------------------------------------------------ */

let PriceMarkerOverlayClass: {
  new (
    position: google.maps.LatLng,
    label: string,
    listingId: string,
    map: google.maps.Map,
    onClick: () => void,
  ): google.maps.OverlayView & {
    div: HTMLDivElement | null;
    remove(): void;
  };
} | null = null;

let ClusterMarkerOverlayClass: {
  new (
    position: google.maps.LatLng,
    count: number,
    map: google.maps.Map,
    onClick: () => void,
  ): google.maps.OverlayView & {
    div: HTMLDivElement | null;
    remove(): void;
  };
} | null = null;

function ensureOverlayClass() {
  if (PriceMarkerOverlayClass) return;

  class PriceMarkerOverlay extends google.maps.OverlayView {
    position: google.maps.LatLng;
    label: string;
    listingId: string;
    div: HTMLDivElement | null = null;
    private clickHandler: () => void;

    constructor(
      position: google.maps.LatLng,
      label: string,
      listingId: string,
      map: google.maps.Map,
      onClick: () => void,
    ) {
      super();
      this.position = position;
      this.label = label;
      this.listingId = listingId;
      this.clickHandler = onClick;
      this.setMap(map);
    }

    onAdd() {
      this.div = document.createElement('div');
      this.div.className = 'bmn-pin';
      this.div.textContent = this.label;
      this.div.setAttribute('data-id', this.listingId);
      this.div.addEventListener('click', (e) => {
        e.stopPropagation();
        this.clickHandler();
      });

      // Bidirectional hover: highlight sidebar card when hovering map pin
      this.div.addEventListener('mouseenter', () => {
        document.querySelector(`[data-listing-id="${this.listingId}"]`)
          ?.classList.add('bg-teal-50');
      });
      this.div.addEventListener('mouseleave', () => {
        document.querySelector(`[data-listing-id="${this.listingId}"]`)
          ?.classList.remove('bg-teal-50');
      });

      const panes = this.getPanes();
      if (panes) panes.overlayMouseTarget.appendChild(this.div);
    }

    draw() {
      if (!this.div) return;
      const projection = this.getProjection();
      if (!projection) return;
      const pixel = projection.fromLatLngToDivPixel(this.position);
      if (pixel) {
        this.div.style.position = 'absolute';
        this.div.style.left = pixel.x + 'px';
        this.div.style.top = pixel.y + 'px';
        this.div.style.transform = 'translate(-50%, -100%)';
      }
    }

    onRemove() {
      if (this.div && this.div.parentNode) {
        this.div.parentNode.removeChild(this.div);
      }
      this.div = null;
    }

    remove() {
      this.setMap(null);
    }
  }

  PriceMarkerOverlayClass = PriceMarkerOverlay as any;

  class ClusterMarkerOverlay extends google.maps.OverlayView {
    position: google.maps.LatLng;
    count: number;
    div: HTMLDivElement | null = null;
    private clickHandler: () => void;

    constructor(
      position: google.maps.LatLng,
      count: number,
      map: google.maps.Map,
      onClick: () => void,
    ) {
      super();
      this.position = position;
      this.count = count;
      this.clickHandler = onClick;
      this.setMap(map);
    }

    onAdd() {
      this.div = document.createElement('div');
      // Size class based on count
      const sizeClass = this.count >= 50 ? 'bmn-cluster-large'
        : this.count >= 10 ? 'bmn-cluster-medium'
        : '';
      this.div.className = `bmn-cluster ${sizeClass}`.trim();
      this.div.textContent = String(this.count);
      this.div.addEventListener('click', (e) => {
        e.stopPropagation();
        this.clickHandler();
      });

      const panes = this.getPanes();
      if (panes) panes.overlayMouseTarget.appendChild(this.div);
    }

    draw() {
      if (!this.div) return;
      const projection = this.getProjection();
      if (!projection) return;
      const pixel = projection.fromLatLngToDivPixel(this.position);
      if (pixel) {
        this.div.style.position = 'absolute';
        this.div.style.left = pixel.x + 'px';
        this.div.style.top = pixel.y + 'px';
        this.div.style.transform = 'translate(-50%, -50%)';
      }
    }

    onRemove() {
      if (this.div && this.div.parentNode) {
        this.div.parentNode.removeChild(this.div);
      }
      this.div = null;
    }

    remove() {
      this.setMap(null);
    }
  }

  ClusterMarkerOverlayClass = ClusterMarkerOverlay as any;
}

/* ------------------------------------------------------------------ */
/*  Module-level state                                                 */
/* ------------------------------------------------------------------ */

type OverlayInstance = google.maps.OverlayView & {
  div: HTMLDivElement | null;
  remove(): void;
};

const overlayMap = new Map<string, OverlayInstance>();
const clusterOverlays: OverlayInstance[] = [];
let activeInfoWindow: google.maps.InfoWindow | null = null;
const MAX_PINS = 1000;

/** Grid cell size in world pixels for clustering. Pins within the same cell
 *  are grouped into a cluster marker. ~100px works well across zoom levels. */
const CLUSTER_GRID_PX = 100;

interface ClusterGroup {
  listings: PropertyListing[];
  center: google.maps.LatLng;
}

/** Grid-based spatial clustering using the map's Mercator projection.
 *  Divides world-pixel space into cells of CLUSTER_GRID_PX² and groups
 *  listings that fall in the same cell. */
function computeClusters(
  listings: PropertyListing[],
  map: google.maps.Map,
): ClusterGroup[] {
  const projection = map.getProjection();
  const zoom = map.getZoom();

  // Fallback: no clustering if projection unavailable
  if (!projection || zoom === undefined) {
    return listings
      .filter(l => l.latitude && l.longitude)
      .map(l => ({
        listings: [l],
        center: new google.maps.LatLng(Number(l.latitude), Number(l.longitude)),
      }));
  }

  const scale = Math.pow(2, zoom);
  const cells = new Map<string, PropertyListing[]>();

  for (const l of listings) {
    if (!l.latitude || !l.longitude) continue;
    const point = projection.fromLatLngToPoint(
      new google.maps.LatLng(Number(l.latitude), Number(l.longitude)),
    );
    if (!point) continue;

    // World-pixel → grid cell
    const cx = Math.floor((point.x * scale) / CLUSTER_GRID_PX);
    const cy = Math.floor((point.y * scale) / CLUSTER_GRID_PX);
    const key = `${cx}_${cy}`;

    if (!cells.has(key)) cells.set(key, []);
    cells.get(key)!.push(l);
  }

  const groups: ClusterGroup[] = [];
  for (const group of cells.values()) {
    let latSum = 0, lngSum = 0;
    for (const l of group) {
      latSum += Number(l.latitude);
      lngSum += Number(l.longitude);
    }
    groups.push({
      listings: group,
      center: new google.maps.LatLng(latSum / group.length, lngSum / group.length),
    });
  }

  return groups;
}

/**
 * Raw (non-proxied) Google Maps Map instance.
 * Alpine wraps this.map in a reactive Proxy, but Google Maps APIs
 * (OverlayView.setMap, InfoWindow.open, event.trigger) use internal
 * WeakMap lookups keyed by object identity — a Proxy !== the original.
 * Passing the proxy causes getPanes()/getProjection() to return null,
 * so overlays never render. We store the raw reference here for all
 * Google Maps API calls.
 */
let _rawMap: google.maps.Map | null = null;

/** When true, the idle listener skips its debounced fetch.
 *  Set by submitFilters() to prevent an idle cascade from
 *  overwriting filter-triggered results. */
let _suppressIdle = false;

/* ------------------------------------------------------------------ */
/*  Alpine component                                                   */
/* ------------------------------------------------------------------ */

export function mapSearchComponent() {
  return {
    // Filter state (flat for Alpine binding)
    city: '',
    neighborhood: '',
    address: '',
    street: '',
    min_price: '',
    max_price: '',
    beds: '',
    baths: '',
    property_type: [] as string[],
    status: ['Active'] as string[],
    school_grade: '',
    price_reduced: '',
    new_listing_days: '',
    sort: 'newest',
    // Advanced
    sqft_min: '',
    sqft_max: '',
    lot_size_min: '',
    lot_size_max: '',
    year_built_min: '',
    year_built_max: '',
    max_dom: '',
    garage: '',
    virtual_tour: '',
    fireplace: '',
    open_house: '',
    exclusive: '',

    // Results
    listings: [] as PropertyListing[],
    total: 0,
    loading: false,
    initialLoad: true,
    fetchError: false,

    // Map
    map: null as google.maps.Map | null,
    activeMarkerId: '',
    hoveredMarkerId: '',

    // Mobile
    mobileView: 'map' as 'map' | 'list',
    mobileFiltersOpen: false,
    moreFiltersOpen: false,
    saveSearchOpen: false,

    // Resize handle
    isResizing: false,
    sidebarWidth: 452,

    // Debounce timer
    _debounceTimer: null as ReturnType<typeof setTimeout> | null,
    _retryCount: 0,
    _fetchId: 0,

    // Favorites store
    favStore,
    _favVersion: 0,

    init() {
      // Track favorites changes for Alpine reactivity
      favStore.onChange(() => { this._favVersion++; });

      // Hydrate from URL params
      this._hydrateFromUrl();

      // Back/forward navigation: re-hydrate filters and re-fetch
      window.addEventListener('popstate', () => {
        this._hydrateFromUrl();
        this.submitFilters();
      });

      // Clean up debounce timer on page unload
      window.addEventListener('beforeunload', () => {
        if (this._debounceTimer) {
          clearTimeout(this._debounceTimer);
          this._debounceTimer = null;
        }
      });

      this.$nextTick(() => {
        this.initMap();
        this.initResizeHandle();
      });
    },

    _hydrateFromUrl() {
      const params = new URLSearchParams(window.location.search);
      const f = filtersFromParams(params);
      this._setFilters(f);
    },

    /** Snapshot current flat state → SearchFilters object */
    _getFilters(): SearchFilters {
      return {
        city: this.city,
        neighborhood: this.neighborhood,
        address: this.address,
        street: this.street,
        min_price: this.min_price,
        max_price: this.max_price,
        beds: this.beds,
        baths: this.baths,
        property_type: [...this.property_type],
        status: [...this.status],
        school_grade: this.school_grade,
        price_reduced: this.price_reduced,
        new_listing_days: this.new_listing_days,
        sort: this.sort,
        page: 1,
        sqft_min: this.sqft_min,
        sqft_max: this.sqft_max,
        lot_size_min: this.lot_size_min,
        lot_size_max: this.lot_size_max,
        year_built_min: this.year_built_min,
        year_built_max: this.year_built_max,
        max_dom: this.max_dom,
        garage: this.garage,
        virtual_tour: this.virtual_tour,
        fireplace: this.fireplace,
        open_house: this.open_house,
        exclusive: this.exclusive,
      };
    },

    _setFilters(f: SearchFilters) {
      this.city = f.city;
      this.neighborhood = f.neighborhood;
      this.address = f.address;
      this.street = f.street;
      this.min_price = f.min_price;
      this.max_price = f.max_price;
      this.beds = f.beds;
      this.baths = f.baths;
      this.property_type = [...f.property_type];
      this.status = [...f.status];
      this.school_grade = f.school_grade;
      this.price_reduced = f.price_reduced;
      this.new_listing_days = f.new_listing_days;
      this.sort = f.sort;
      this.sqft_min = f.sqft_min;
      this.sqft_max = f.sqft_max;
      this.lot_size_min = f.lot_size_min;
      this.lot_size_max = f.lot_size_max;
      this.year_built_min = f.year_built_min;
      this.year_built_max = f.year_built_max;
      this.max_dom = f.max_dom;
      this.garage = f.garage;
      this.virtual_tour = f.virtual_tour;
      this.fireplace = f.fireplace;
      this.open_house = f.open_house;
      this.exclusive = f.exclusive;
    },

    /** Format price for Alpine template use */
    formatPrice(price: number): string {
      return formatPrice(price);
    },

    async initMap() {
      const container = document.getElementById('map-container');
      if (!container || this.map) return;

      if (typeof google === 'undefined' || !google.maps?.importLibrary) {
        if (this._retryCount < 50) {
          this._retryCount++;
          setTimeout(() => this.initMap(), 100);
        }
        return;
      }

      try {
        await google.maps.importLibrary('maps');
      } catch (err) {
        console.error('Google Maps failed to load:', err);
        return;
      }

      ensureOverlayClass();

      const mapInstance = new google.maps.Map(container, {
        center: { lat: 42.36, lng: -71.06 },
        zoom: 13,
        gestureHandling: 'greedy',
        streetViewControl: false,
        fullscreenControl: true,
        mapTypeControl: true,
        mapTypeControlOptions: {
          position: google.maps.ControlPosition.TOP_RIGHT,
          style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
        },
        zoomControlOptions: {
          position: google.maps.ControlPosition.RIGHT_BOTTOM,
        },
      });

      // Store raw reference BEFORE Alpine proxies it
      _rawMap = mapInstance;
      this.map = mapInstance; // Alpine proxies this — used only for reactivity checks

      mapInstance.addListener('idle', () => {
        if (_suppressIdle) {
          console.debug('[MapSearch] idle suppressed (filter fetch in progress)');
          _suppressIdle = false;
          return;
        }
        if (this._debounceTimer) clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
          this.fetchProperties();
        }, 300);
      });
    },

    initResizeHandle() {
      const handle = document.getElementById('resize-handle');
      const wrapper = document.getElementById('map-wrapper');
      const sidebar = document.getElementById('results-sidebar');
      if (!handle || !wrapper || !sidebar) return;

      let startX = 0;
      let startWidth = 0;

      const onMouseMove = (e: MouseEvent) => {
        if (!this.isResizing) return;
        const dx = startX - e.clientX;
        const newWidth = Math.min(Math.max(startWidth + dx, 280), wrapper.clientWidth * 0.8);
        this.sidebarWidth = newWidth;
        sidebar.style.width = newWidth + 'px';
        sidebar.style.flex = 'none';
      };

      const onMouseUp = () => {
        this.isResizing = false;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        if (_rawMap) {
          // Suppress idle — resize trigger shouldn't cause a refetch
          _suppressIdle = true;
          google.maps.event.trigger(_rawMap, 'resize');
        }
      };

      handle.addEventListener('mousedown', (e: MouseEvent) => {
        this.isResizing = true;
        startX = e.clientX;
        startWidth = sidebar.clientWidth;
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
        e.preventDefault();
      });
    },

    async fetchProperties(useBounds = true) {
      if (!_rawMap) return;

      const fetchId = ++this._fetchId;
      this.loading = true;
      this.fetchError = false;

      if (useBounds) {
        const bounds = _rawMap.getBounds();
        if (!bounds) {
          this.loading = false;
          return;
        }
      }

      const filters = this._getFilters();
      const params = filtersToApiParams(filters);

      if (useBounds) {
        const bounds = _rawMap.getBounds()!;
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();
        params.set('bounds', [
          sw.lat().toFixed(6),
          sw.lng().toFixed(6),
          ne.lat().toFixed(6),
          ne.lng().toFixed(6),
        ].join(','));
      }
      params.set('per_page', '1000');
      // Remove paged for map (no pagination)
      params.delete('paged');

      const url = bmnPageData.propertiesApiUrl + '?' + params.toString();
      console.debug('[MapSearch] fetch #%d, useBounds=%s, beds=%s, city=%s, url=%s', fetchId, useBounds, filters.beds, filters.city, url);

      // Save sidebar scroll position before re-render
      const scrollContainer = document.querySelector('#results-sidebar .overflow-y-auto') as HTMLElement | null;
      const scrollTop = scrollContainer?.scrollTop ?? 0;

      try {
        const resp = await fetch(url);
        const json = await resp.json();

        // Discard stale response if a newer fetch has started
        if (fetchId !== this._fetchId) {
          console.debug('[MapSearch] fetch #%d discarded (stale, current=#%d)', fetchId, this._fetchId);
          return;
        }

        if (json.success && json.data) {
          this.listings = json.data;
          this.total = json.meta?.total ?? json.data.length;
        } else {
          this.listings = [];
          this.total = 0;
        }
      } catch (err) {
        console.error('[MapSearch] fetch error:', err);
        if (fetchId !== this._fetchId) return;
        this.fetchError = true;
        // Don't wipe listings — keep showing previous results on error
      }

      this.loading = false;
      this.initialLoad = false;
      console.debug('[MapSearch] fetch #%d complete: %d listings, updating %d markers', fetchId, this.listings.length, Math.min(this.listings.length, MAX_PINS));
      this.updateMarkers();

      // Restore sidebar scroll position after Alpine re-renders
      this.$nextTick(() => {
        if (scrollContainer) scrollContainer.scrollTop = scrollTop;
      });

      // If non-bounds fetch (filter submit), reframe map to show all results
      if (!useBounds && this.listings.length > 0) {
        const resultBounds = new google.maps.LatLngBounds();
        let hasCoords = false;
        this.listings.forEach((l: PropertyListing) => {
          if (l.latitude && l.longitude) {
            resultBounds.extend({ lat: Number(l.latitude), lng: Number(l.longitude) });
            hasCoords = true;
          }
        });
        if (hasCoords) {
          _suppressIdle = true;
          _rawMap.fitBounds(resultBounds, { top: 50, right: 50, bottom: 50, left: 50 });
        }
      }
    },

    updateMarkers() {
      if (!_rawMap || !PriceMarkerOverlayClass || !ClusterMarkerOverlayClass) {
        console.debug('[MapSearch] updateMarkers skipped: map=%o, overlay=%o', !!_rawMap, !!PriceMarkerOverlayClass);
        return;
      }

      if (activeInfoWindow) {
        activeInfoWindow.close();
        activeInfoWindow = null;
        this.activeMarkerId = '';
      }

      // 1. Remove tracked pin overlays
      const prevTracked = overlayMap.size;
      overlayMap.forEach((overlay) => {
        try { overlay.remove(); } catch (_) { /* ignore */ }
      });
      overlayMap.clear();

      // 2. Remove tracked cluster overlays
      const prevClusters = clusterOverlays.length;
      clusterOverlays.forEach((overlay) => {
        try { overlay.remove(); } catch (_) { /* ignore */ }
      });
      clusterOverlays.length = 0;

      // 3. Nuclear cleanup: remove ALL pin/cluster elements from the DOM
      document.querySelectorAll('.bmn-pin, .bmn-cluster').forEach((el) => el.remove());

      // 4. Cluster listings and render
      const toRender = this.listings.slice(0, MAX_PINS);
      const clusters = computeClusters(toRender, _rawMap);
      let pinCount = 0;
      let clusterCount = 0;

      clusters.forEach((cluster) => {
        if (cluster.listings.length === 1) {
          // Single listing — render as price pin
          const listing = cluster.listings[0];
          const priceLabel = formatPrice(listing.price);
          const position = new google.maps.LatLng(
            Number(listing.latitude),
            Number(listing.longitude),
          );

          const overlay = new PriceMarkerOverlayClass!(
            position,
            priceLabel,
            listing.listing_id,
            _rawMap!,
            () => this.onPinClick(listing, priceLabel),
          );

          overlayMap.set(listing.listing_id, overlay as OverlayInstance);
          pinCount++;
        } else {
          // Multiple listings — render as cluster marker
          const overlay = new ClusterMarkerOverlayClass!(
            cluster.center,
            cluster.listings.length,
            _rawMap!,
            () => this.onClusterClick(cluster.listings),
          );

          clusterOverlays.push(overlay as OverlayInstance);
          clusterCount++;
        }
      });

      console.debug('[MapSearch] updateMarkers: prev=%d pins + %d clusters, now=%d pins + %d clusters',
        prevTracked, prevClusters, pinCount, clusterCount);
    },

    /** Zoom into a cluster to reveal individual pins */
    onClusterClick(listings: PropertyListing[]) {
      if (!_rawMap) return;
      const bounds = new google.maps.LatLngBounds();
      listings.forEach((l) => {
        if (l.latitude && l.longitude) {
          bounds.extend({ lat: Number(l.latitude), lng: Number(l.longitude) });
        }
      });
      // Let idle fire naturally to re-fetch/re-cluster at the new zoom
      _rawMap.fitBounds(bounds, { top: 60, right: 60, bottom: 60, left: 60 });
    },

    onPinClick(listing: PropertyListing, priceLabel: string) {
      // Suppress idle — opening an info window can cause a tiny map pan
      _suppressIdle = true;
      this.activeMarkerId = listing.listing_id;
      if (activeInfoWindow) activeInfoWindow.close();

      const photoHtml = listing.main_photo_url
        ? `<img src="${listing.main_photo_url}" alt="${escapeHtml(listing.address)}" style="width:100%;height:160px;object-fit:cover;border-radius:12px 12px 0 0;display:block;">`
        : '';

      const detailParts: string[] = [];
      if (listing.beds) detailParts.push(listing.beds + ' bd');
      if (listing.baths) detailParts.push(listing.baths + ' ba');
      if (listing.sqft) detailParts.push(Number(listing.sqft).toLocaleString() + ' sqft');

      // Status badge
      const statusColors: Record<string, string> = {
        Active: '#16a34a',
        Pending: '#ca8a04',
        'Active Under Contract': '#ca8a04',
        Closed: '#dc2626',
        Sold: '#dc2626',
      };
      const statusColor = statusColors[listing.status] || '#6b7280';
      const statusBadge = listing.status && listing.status !== 'Active'
        ? `<span style="display:inline-block;font-size:11px;font-weight:600;color:${statusColor};margin-top:4px;">${escapeHtml(listing.status)}</span>`
        : '';

      const infoContent = `
        <a href="${getPropertyUrl(listing.listing_id)}" style="text-decoration:none;color:inherit;display:block;width:280px;">
          ${photoHtml}
          <div style="padding:14px;">
            <div style="font-weight:700;font-size:20px;color:#0891B2;">${priceLabel}</div>
            ${statusBadge}
            <div style="font-weight:600;font-size:14px;margin-top:6px;color:#111;">${escapeHtml(listing.address)}</div>
            <div style="font-size:13px;color:#6b7280;margin-top:2px;">${escapeHtml(listing.city)}, ${escapeHtml(listing.state)} ${escapeHtml(listing.zip)}</div>
            ${detailParts.length ? `<div style="font-size:13px;color:#6b7280;margin-top:8px;">${detailParts.join(' &middot; ')}</div>` : ''}
            <div style="margin-top:10px;padding:8px 16px;background:linear-gradient(135deg,#0891B2,#0E7490);color:white;border-radius:8px;font-size:13px;font-weight:600;text-align:center;">View Details</div>
          </div>
        </a>
      `;

      // Unhighlight previously active pin, highlight clicked pin
      overlayMap.forEach((o) => {
        if (o.div) o.div.classList.remove('bmn-pin-active');
      });
      const activeOverlay = overlayMap.get(listing.listing_id);
      if (activeOverlay?.div) activeOverlay.div.classList.add('bmn-pin-active');

      activeInfoWindow = new google.maps.InfoWindow({
        content: infoContent,
        maxWidth: 320,
        position: new google.maps.LatLng(Number(listing.latitude), Number(listing.longitude)),
      });
      activeInfoWindow.open(_rawMap!);
      activeInfoWindow.addListener('closeclick', () => {
        this.activeMarkerId = '';
        // Remove active pin highlight when info window is closed
        overlayMap.forEach((o) => {
          if (o.div) o.div.classList.remove('bmn-pin-active');
        });
      });

      const card = document.querySelector(`[data-listing-id="${listing.listing_id}"]`);
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },

    highlightMarker(listingId: string) {
      this.hoveredMarkerId = listingId;
      const overlay = overlayMap.get(listingId) as OverlayInstance | undefined;
      if (overlay?.div) {
        overlay.div.classList.add('bmn-pin-highlighted');
        overlay.div.style.zIndex = '10000';
      }
    },

    unhighlightMarker(listingId: string) {
      this.hoveredMarkerId = '';
      const overlay = overlayMap.get(listingId) as OverlayInstance | undefined;
      if (overlay?.div) {
        overlay.div.classList.remove('bmn-pin-highlighted');
        overlay.div.style.zIndex = '1';
      }
    },

    centerOnProperty(listingId: string) {
      const overlay = overlayMap.get(listingId) as OverlayInstance | undefined;
      if (overlay && _rawMap) {
        const listing = this.listings.find((l: PropertyListing) => l.listing_id === listingId);
        if (listing) {
          // Suppress idle — panTo triggers idle which would refetch and close the info window
          _suppressIdle = true;
          _rawMap.panTo(new google.maps.LatLng(Number(listing.latitude), Number(listing.longitude)));
          this.onPinClick(listing, formatPrice(listing.price));
        }
      }
    },

    submitFilters() {
      // Cancel any pending idle-triggered fetch to prevent race conditions
      if (this._debounceTimer) {
        clearTimeout(this._debounceTimer);
        this._debounceTimer = null;
      }
      // Suppress the next idle event — overlay removal/addition can trigger idle,
      // which would re-fetch and potentially cause a stale response race
      _suppressIdle = true;
      this.mobileFiltersOpen = false;
      this.moreFiltersOpen = false;
      // Non-bounds fetch: get all matching results, then reframe map
      this.fetchProperties(false);
      // Push filter state to URL for shareability and back/forward navigation
      const qs = filtersToParams(this._getFilters()).toString();
      const url = bmnTheme.mapSearchUrl + (qs ? '?' + qs : '');
      history.pushState(null, '', url);
    },

    resetFilters() {
      const defaults = createFilterState();
      this._setFilters(defaults);
      this.submitFilters();
    },

    toggleStatus(value: string) {
      this.status = toggleArrayValue(this.status, value);
    },

    togglePropertyType(value: string) {
      this.property_type = toggleArrayValue(this.property_type, value);
    },

    get activeChips(): FilterChip[] {
      return getActiveChips(this._getFilters());
    },

    removeChip(chip: FilterChip) {
      const updated = engineRemoveChip(this._getFilters(), chip);
      this._setFilters(updated);
      this.submitFilters();
    },

    /** Build URL to list search preserving current filters */
    getListSearchUrl(): string {
      const qs = filtersToParams(this._getFilters()).toString();
      return bmnTheme.searchUrl + (qs ? '?' + qs : '');
    },

    /** Build URL to map search preserving current filters */
    getMapSearchUrl(): string {
      const qs = filtersToParams(this._getFilters()).toString();
      return bmnTheme.mapSearchUrl + (qs ? '?' + qs : '');
    },

    get totalLabel(): string {
      if (this.initialLoad) return 'Loading...';
      if (this.total === 0) return 'No properties in view';
      if (this.total === 1) return '1 property';
      const showing = this.listings.length;
      if (this.total > showing) return `${showing.toLocaleString()} of ${this.total.toLocaleString()} properties`;
      return `${this.total.toLocaleString()} properties`;
    },

    /** Handle autocomplete selection from dispatch mode */
    handleAutocompleteSelect(detail: { type: string; value: string; text: string }) {
      // Clear all location fields, then set the selected one
      this.city = '';
      this.neighborhood = '';
      this.address = '';
      this.street = '';

      switch (detail.type) {
        case 'city': this.city = detail.value; break;
        case 'neighborhood': this.neighborhood = detail.value; break;
        case 'address': this.address = detail.value; break;
        case 'street': this.street = detail.value; break;
        case 'mls_number':
          window.location.href = `/property/${detail.value}/`;
          return;
      }

      this.submitFilters();
    },
  };
}
