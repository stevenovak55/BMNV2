/**
 * Property Search Filter State (Alpine.js component)
 *
 * Thin Alpine wrapper delegating to filter-engine.
 * Manages HTMX-powered partial rendering, URL history, and pagination.
 *
 * @version 3.0.0
 */

import {
  type SearchFilters,
  createFilterState,
  filtersToParams,
  filtersFromParams,
  filtersToUrl,
  toggleArrayValue,
  getActiveChips,
  removeChip as engineRemoveChip,
  hasActiveFilters,
  type FilterChip,
} from '../lib/filter-engine';
import { favStore } from '../lib/favorites-store';

declare const bmnTheme: {
  searchUrl: string;
  mapSearchUrl: string;
  homeUrl: string;
};

interface ServerFilters extends Partial<SearchFilters> {
  total?: number;
  pages?: number;
}

export function propertySearchComponent(serverFilters: ServerFilters = {}) {
  return {
    // Filter state (flat properties for Alpine template binding)
    city: serverFilters.city || '',
    neighborhood: serverFilters.neighborhood || '',
    address: serverFilters.address || '',
    street: serverFilters.street || '',
    min_price: serverFilters.min_price || '',
    max_price: serverFilters.max_price || '',
    beds: serverFilters.beds || '',
    baths: serverFilters.baths || '',
    property_type: serverFilters.property_type || [] as string[],
    status: serverFilters.status || ['Active'] as string[],
    school_grade: serverFilters.school_grade || '',
    price_reduced: serverFilters.price_reduced || '',
    new_listing_days: serverFilters.new_listing_days || '',
    sort: serverFilters.sort || 'newest',

    // Advanced filters
    sqft_min: serverFilters.sqft_min || '',
    sqft_max: serverFilters.sqft_max || '',
    lot_size_min: serverFilters.lot_size_min || '',
    lot_size_max: serverFilters.lot_size_max || '',
    year_built_min: serverFilters.year_built_min || '',
    year_built_max: serverFilters.year_built_max || '',
    max_dom: serverFilters.max_dom || '',
    garage: serverFilters.garage || '',
    virtual_tour: serverFilters.virtual_tour || '',
    fireplace: serverFilters.fireplace || '',
    open_house: serverFilters.open_house || '',
    exclusive: serverFilters.exclusive || '',

    // Pagination state
    page: serverFilters.page || 1,
    total: serverFilters.total || 0,
    pages: serverFilters.pages || 0,

    // UI state
    loading: false,
    mobileFiltersOpen: false,
    moreFiltersOpen: false,
    saveSearchOpen: false,

    // Favorites store
    favStore,
    _favVersion: 0,

    init() {
      // Track favorites changes for Alpine reactivity
      favStore.onChange(() => { this._favVersion++; });

      window.addEventListener('popstate', () => {
        this.loadFromUrl();
        this.submitFilters(false);
      });
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
        page: this.page,
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

    /** Apply a SearchFilters object back to flat state */
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
      this.page = f.page;
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

    buildQueryString(): string {
      return filtersToParams(this._getFilters()).toString();
    },

    loadFromUrl() {
      const params = new URLSearchParams(window.location.search);
      const f = filtersFromParams(params);
      this._setFilters(f);
    },

    submitFilters(pushState = true) {
      this.page = 1;
      this.fetchResults(pushState);
    },

    goToPage(n: number) {
      this.page = n;
      this.fetchResults(true);
      const el = document.getElementById('results-grid');
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    fetchResults(pushState = true) {
      this.loading = true;
      const qs = this.buildQueryString();
      const pageUrl = bmnTheme.searchUrl + (qs ? '?' + qs : '');

      const htmx = window.htmx;
      if (htmx) {
        htmx.ajax('GET', pageUrl, {
          target: '#results-grid',
          swap: 'innerHTML',
        }).then(() => {
          this.loading = false;
        });
      }

      if (pushState) {
        history.pushState(null, '', pageUrl);
      }

      this.mobileFiltersOpen = false;
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

    get totalLabel(): string {
      if (this.total === 0) return 'No properties found';
      if (this.total === 1) return '1 property found';
      return `${this.total.toLocaleString()} properties found`;
    },

    syncFromServer(total: number, pages: number, currentPage: number) {
      this.total = total;
      this.pages = pages;
      this.page = currentPage;
      this.loading = false;
    },

    /** Build URL to map search preserving current filters */
    getMapSearchUrl(): string {
      const qs = this.buildQueryString();
      return bmnTheme.mapSearchUrl + (qs ? '?' + qs : '');
    },

    /** Build URL to list search preserving current filters */
    getListSearchUrl(): string {
      const qs = this.buildQueryString();
      return bmnTheme.searchUrl + (qs ? '?' + qs : '');
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
