/**
 * Property Search Filter State (Alpine.js component)
 *
 * Manages search filter state, HTMX-powered partial rendering,
 * URL history updates, and pagination.
 */

declare const bmnPageData: {
  propertiesApiUrl: string;
  schoolsApiUrl: string;
  propertyTypes: string[];
};

declare const bmnTheme: {
  searchUrl: string;
};

interface FilterState {
  city: string;
  neighborhood: string;
  address: string;
  street: string;
  min_price: string;
  max_price: string;
  beds: string;
  baths: string;
  property_type: string[];
  status: string[];
  school_grade: string;
  price_reduced: string;
  new_listing_days: string;
  sort: string;
  page: number;
}

interface ServerFilters extends Partial<FilterState> {
  total?: number;
  pages?: number;
}

export function propertySearchComponent(serverFilters: ServerFilters = {}) {
  return {
    // Filter state
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

    // Pagination state
    page: serverFilters.page || 1,
    total: serverFilters.total || 0,
    pages: serverFilters.pages || 0,

    // UI state
    loading: false,
    mobileFiltersOpen: false,

    init() {
      // Listen for browser back/forward
      window.addEventListener('popstate', () => {
        this.loadFromUrl();
        this.submitFilters(false);
      });
    },

    /**
     * Build query string from current filter state
     */
    buildQueryString(): string {
      const params = new URLSearchParams();

      if (this.city) params.set('city', this.city);
      if (this.neighborhood) params.set('neighborhood', this.neighborhood);
      if (this.address) params.set('address', this.address);
      if (this.street) params.set('street', this.street);
      if (this.min_price) params.set('min_price', this.min_price);
      if (this.max_price) params.set('max_price', this.max_price);
      if (this.beds) params.set('beds', this.beds);
      if (this.baths) params.set('baths', this.baths);
      if (this.property_type.length) params.set('property_type', this.property_type.join(','));
      if (this.status.length) params.set('status', this.status.join(','));
      if (this.school_grade) params.set('school_grade', this.school_grade);
      if (this.price_reduced) params.set('price_reduced', this.price_reduced);
      if (this.new_listing_days) params.set('new_listing_days', this.new_listing_days);
      if (this.sort && this.sort !== 'newest') params.set('sort', this.sort);
      if (this.page > 1) params.set('paged', String(this.page));

      return params.toString();
    },

    /**
     * Load filter state from current URL
     */
    loadFromUrl() {
      const params = new URLSearchParams(window.location.search);
      this.city = params.get('city') || '';
      this.neighborhood = params.get('neighborhood') || '';
      this.address = params.get('address') || '';
      this.street = params.get('street') || '';
      this.min_price = params.get('min_price') || '';
      this.max_price = params.get('max_price') || '';
      this.beds = params.get('beds') || '';
      this.baths = params.get('baths') || '';
      this.property_type = params.get('property_type')?.split(',').filter(Boolean) || [];
      this.status = params.get('status')?.split(',').filter(Boolean) || ['Active'];
      this.school_grade = params.get('school_grade') || '';
      this.price_reduced = params.get('price_reduced') || '';
      this.new_listing_days = params.get('new_listing_days') || '';
      this.sort = params.get('sort') || 'newest';
      this.page = parseInt(params.get('paged') || '1', 10);
    },

    /**
     * Submit filters - resets to page 1 and fetches results via HTMX
     */
    submitFilters(pushState = true) {
      this.page = 1;
      this.fetchResults(pushState);
    },

    /**
     * Go to specific page
     */
    goToPage(n: number) {
      this.page = n;
      this.fetchResults(true);

      // Scroll to top of results
      const resultsEl = document.getElementById('results-grid');
      if (resultsEl) {
        resultsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    },

    /**
     * Fetch results via HTMX partial rendering
     */
    fetchResults(pushState = true) {
      this.loading = true;
      const qs = this.buildQueryString();
      const pageUrl = bmnTheme.searchUrl + (qs ? '?' + qs : '');

      // Use HTMX to fetch the results-grid partial
      const htmx = window.htmx;
      if (htmx) {
        htmx.ajax('GET', pageUrl, {
          target: '#results-grid',
          swap: 'innerHTML',
        }).then(() => {
          this.loading = false;
        });
      }

      // Update browser URL
      if (pushState) {
        history.pushState(null, '', pageUrl);
      }

      // Close mobile filters if open
      this.mobileFiltersOpen = false;
    },

    /**
     * Reset all filters to defaults
     */
    resetFilters() {
      this.city = '';
      this.neighborhood = '';
      this.address = '';
      this.street = '';
      this.min_price = '';
      this.max_price = '';
      this.beds = '';
      this.baths = '';
      this.property_type = [];
      this.status = ['Active'];
      this.school_grade = '';
      this.price_reduced = '';
      this.new_listing_days = '';
      this.sort = 'newest';
      this.submitFilters();
    },

    /**
     * Toggle a status value in the status array
     */
    toggleStatus(value: string) {
      const idx = this.status.indexOf(value);
      if (idx >= 0) {
        this.status.splice(idx, 1);
      } else {
        this.status.push(value);
      }
    },

    /**
     * Toggle a property type value in the property_type array
     */
    togglePropertyType(value: string) {
      const idx = this.property_type.indexOf(value);
      if (idx >= 0) {
        this.property_type.splice(idx, 1);
      } else {
        this.property_type.push(value);
      }
    },

    /**
     * Computed: total results label
     */
    get totalLabel(): string {
      if (this.total === 0) return 'No properties found';
      if (this.total === 1) return '1 property found';
      return `${this.total.toLocaleString()} properties found`;
    },

    /**
     * Sync server-rendered pagination values into Alpine state
     */
    syncFromServer(total: number, pages: number, currentPage: number) {
      this.total = total;
      this.pages = pages;
      this.page = currentPage;
      this.loading = false;
    },
  };
}
