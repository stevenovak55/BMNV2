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
}

/* ------------------------------------------------------------------ */
/*  Module-level state                                                 */
/* ------------------------------------------------------------------ */

type OverlayInstance = google.maps.OverlayView & {
  div: HTMLDivElement | null;
  remove(): void;
};

const overlayMap = new Map<string, OverlayInstance>();
let activeInfoWindow: google.maps.InfoWindow | null = null;
const MAX_PINS = 200;

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

    // Favorites store
    favStore,
    _favVersion: 0,

    init() {
      // Track favorites changes for Alpine reactivity
      favStore.onChange(() => { this._favVersion++; });

      // Hydrate from URL params
      this._hydrateFromUrl();

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

      this.map = new google.maps.Map(container, {
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

      this.map.addListener('idle', () => {
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
        if (this.map) google.maps.event.trigger(this.map, 'resize');
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

    async fetchProperties() {
      if (!this.map) return;

      this.loading = true;

      const bounds = this.map.getBounds();
      if (!bounds) {
        this.loading = false;
        return;
      }

      const sw = bounds.getSouthWest();
      const ne = bounds.getNorthEast();
      const params = filtersToParams(this._getFilters());

      params.set('bounds', [
        sw.lat().toFixed(6),
        sw.lng().toFixed(6),
        ne.lat().toFixed(6),
        ne.lng().toFixed(6),
      ].join(','));
      params.set('per_page', '250');
      // Remove paged for map (no pagination)
      params.delete('paged');

      try {
        const url = bmnPageData.propertiesApiUrl + '?' + params.toString();
        const resp = await fetch(url);
        const json = await resp.json();

        if (json.success && json.data) {
          this.listings = json.data;
          this.total = json.meta?.total ?? json.data.length;
        } else {
          this.listings = [];
          this.total = 0;
        }
      } catch (err) {
        console.error('Map search fetch error:', err);
        this.listings = [];
        this.total = 0;
      }

      this.loading = false;
      this.initialLoad = false;
      this.updateMarkers();
    },

    updateMarkers() {
      if (!this.map || !PriceMarkerOverlayClass) return;

      if (activeInfoWindow) {
        activeInfoWindow.close();
        activeInfoWindow = null;
      }

      overlayMap.forEach((overlay) => overlay.remove());
      overlayMap.clear();

      const toRender = this.listings.slice(0, MAX_PINS);

      toRender.forEach((listing: PropertyListing) => {
        if (!listing.latitude || !listing.longitude) return;

        const priceLabel = formatPrice(listing.price);
        const position = new google.maps.LatLng(
          Number(listing.latitude),
          Number(listing.longitude),
        );

        const overlay = new PriceMarkerOverlayClass!(
          position,
          priceLabel,
          listing.listing_id,
          this.map!,
          () => this.onPinClick(listing, priceLabel),
        );

        overlayMap.set(listing.listing_id, overlay as OverlayInstance);
      });
    },

    onPinClick(listing: PropertyListing, priceLabel: string) {
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

      activeInfoWindow = new google.maps.InfoWindow({
        content: infoContent,
        maxWidth: 320,
        position: new google.maps.LatLng(Number(listing.latitude), Number(listing.longitude)),
      });
      activeInfoWindow.open(this.map!);
      activeInfoWindow.addListener('closeclick', () => { this.activeMarkerId = ''; });

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
        overlay.div.style.zIndex = '';
      }
    },

    centerOnProperty(listingId: string) {
      const overlay = overlayMap.get(listingId) as OverlayInstance | undefined;
      if (overlay && this.map) {
        const listing = this.listings.find((l: PropertyListing) => l.listing_id === listingId);
        if (listing) {
          this.map.panTo(new google.maps.LatLng(Number(listing.latitude), Number(listing.longitude)));
          this.onPinClick(listing, formatPrice(listing.price));
        }
      }
    },

    submitFilters() {
      this.mobileFiltersOpen = false;
      this.moreFiltersOpen = false;
      this.fetchProperties();
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
