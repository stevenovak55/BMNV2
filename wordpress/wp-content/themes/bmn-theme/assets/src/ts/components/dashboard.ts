/**
 * Dashboard Alpine.js Component
 *
 * Manages user dashboard: favorites, saved searches, profile.
 * Reads JWT from localStorage for authenticated API calls.
 */

declare const bmnTheme: {
  restUrl: string;
  homeUrl: string;
  searchUrl: string;
  loginUrl: string;
  dashboardUrl: string;
};

declare const bmnPageData: {
  propertiesApiUrl: string;
};

interface FavoriteListing {
  listing_id: string;
  url: string;
  photo: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  price: string;
  beds: string;
  baths: string;
  sqft: string;
  type: string;
}

interface SavedSearch {
  id: number;
  name: string;
  filters: Record<string, string>;
  created_at: string;
}

interface UserProfile {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  display_name: string;
  created_at: string;
}

interface DashboardData {
  activeTab: string;
  loading: boolean;
  favorites: FavoriteListing[];
  favoritesLoaded: boolean;
  savedSearches: SavedSearch[];
  searchesLoaded: boolean;
  profile: UserProfile | null;
  profileLoaded: boolean;
  confirmDelete: number | null;
  confirmAccountDelete: boolean;
  init(): void;
  setTab(tab: string): void;
  authHeaders(): Record<string, string>;
  loadFavorites(): Promise<void>;
  removeFavorite(listingId: string): Promise<void>;
  loadSavedSearches(): Promise<void>;
  runSearch(filters: Record<string, string>): void;
  deleteSavedSearch(id: number): Promise<void>;
  loadProfile(): Promise<void>;
  logout(): void;
  deleteAccount(): Promise<void>;
  formatDate(dateStr: string): string;
  formatFilters(filters: Record<string, string>): string;
}

export function dashboardAppComponent(): DashboardData {
  return {
    activeTab: 'favorites',
    loading: false,
    favorites: [],
    favoritesLoaded: false,
    savedSearches: [],
    searchesLoaded: false,
    profile: null,
    profileLoaded: false,
    confirmDelete: null,
    confirmAccountDelete: false,

    init() {
      // Check auth
      const token = localStorage.getItem('bmn_token');
      if (!token) {
        window.location.href = bmnTheme.loginUrl;
        return;
      }

      // Read tab from hash
      const hash = window.location.hash.replace('#', '');
      if (['favorites', 'saved-searches', 'profile'].includes(hash)) {
        this.activeTab = hash;
      }

      // Load initial tab data
      this.loadTabData();

      // Listen for hash changes
      window.addEventListener('hashchange', () => {
        const newHash = window.location.hash.replace('#', '');
        if (['favorites', 'saved-searches', 'profile'].includes(newHash)) {
          this.activeTab = newHash;
          this.loadTabData();
        }
      });
    },

    setTab(tab: string) {
      this.activeTab = tab;
      window.location.hash = tab;
      this.loadTabData();
    },

    authHeaders(): Record<string, string> {
      const token = localStorage.getItem('bmn_token');
      return {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      };
    },

    async loadTabData() {
      if (this.activeTab === 'favorites' && !this.favoritesLoaded) {
        await this.loadFavorites();
      } else if (this.activeTab === 'saved-searches' && !this.searchesLoaded) {
        await this.loadSavedSearches();
      } else if (this.activeTab === 'profile' && !this.profileLoaded) {
        await this.loadProfile();
      }
    },

    async loadFavorites() {
      this.loading = true;
      try {
        const res = await fetch(`${bmnTheme.restUrl}bmn/v1/favorites`, {
          headers: this.authHeaders(),
        });

        if (res.status === 401) {
          localStorage.removeItem('bmn_token');
          window.location.href = bmnTheme.loginUrl;
          return;
        }

        const data = await res.json();
        const ids: string[] = data.data || [];

        if (ids.length === 0) {
          this.favorites = [];
          this.favoritesLoaded = true;
          return;
        }

        // Batch-fetch property details
        const listingsRes = await fetch(
          `${bmnPageData.propertiesApiUrl}?listing_ids=${ids.join(',')}&per_page=${ids.length}`
        );
        const listingsData = await listingsRes.json();
        const listings = listingsData.data || [];

        this.favorites = listings.map((l: Record<string, unknown>) => ({
          listing_id: l.listing_id || '',
          url: `${bmnTheme.homeUrl}property/${l.listing_id}/`,
          photo: (l.photos as string[])?.[0] || l.photo || '',
          address: l.address || '',
          city: l.city || '',
          state: l.state || 'MA',
          zip: l.zip || '',
          price: l.price ? `$${Number(l.price).toLocaleString()}` : '',
          beds: String(l.beds || ''),
          baths: String(l.baths || ''),
          sqft: l.sqft ? Number(l.sqft).toLocaleString() : '',
          type: (l.type as string) || '',
        }));

        this.favoritesLoaded = true;
      } catch {
        // Silently fail - UI shows empty state
      } finally {
        this.loading = false;
      }
    },

    async removeFavorite(listingId: string) {
      try {
        await fetch(`${bmnTheme.restUrl}bmn/v1/favorites/${listingId}`, {
          method: 'DELETE',
          headers: this.authHeaders(),
        });
        this.favorites = this.favorites.filter(
          (f) => f.listing_id !== listingId
        );
      } catch {
        // Silently fail
      }
    },

    async loadSavedSearches() {
      this.loading = true;
      try {
        const res = await fetch(`${bmnTheme.restUrl}bmn/v1/saved-searches`, {
          headers: this.authHeaders(),
        });

        if (res.status === 401) {
          localStorage.removeItem('bmn_token');
          window.location.href = bmnTheme.loginUrl;
          return;
        }

        const data = await res.json();
        this.savedSearches = data.data || [];
        this.searchesLoaded = true;
      } catch {
        // Silently fail
      } finally {
        this.loading = false;
      }
    },

    runSearch(filters: Record<string, string>) {
      const params = new URLSearchParams(filters).toString();
      window.location.href = `${bmnTheme.searchUrl}?${params}`;
    },

    async deleteSavedSearch(id: number) {
      try {
        await fetch(`${bmnTheme.restUrl}bmn/v1/saved-searches/${id}`, {
          method: 'DELETE',
          headers: this.authHeaders(),
        });
        this.savedSearches = this.savedSearches.filter((s) => s.id !== id);
        this.confirmDelete = null;
      } catch {
        // Silently fail
      }
    },

    async loadProfile() {
      this.loading = true;
      try {
        const res = await fetch(`${bmnTheme.restUrl}bmn/v1/auth/me`, {
          headers: this.authHeaders(),
        });

        if (res.status === 401) {
          localStorage.removeItem('bmn_token');
          window.location.href = bmnTheme.loginUrl;
          return;
        }

        const data = await res.json();
        this.profile = data.data || null;
        this.profileLoaded = true;
      } catch {
        // Silently fail
      } finally {
        this.loading = false;
      }
    },

    logout() {
      const token = localStorage.getItem('bmn_token');
      if (token) {
        // Fire-and-forget logout to invalidate server-side token
        fetch(`${bmnTheme.restUrl}bmn/v1/auth/logout`, {
          method: 'POST',
          headers: this.authHeaders(),
        }).catch(() => {});
      }
      localStorage.removeItem('bmn_token');
      window.location.href = bmnTheme.homeUrl;
    },

    async deleteAccount() {
      try {
        await fetch(`${bmnTheme.restUrl}bmn/v1/auth/me`, {
          method: 'DELETE',
          headers: this.authHeaders(),
        });
        localStorage.removeItem('bmn_token');
        window.location.href = bmnTheme.homeUrl;
      } catch {
        // Silently fail
      }
    },

    formatDate(dateStr: string): string {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },

    formatFilters(filters: Record<string, string>): string {
      const parts: string[] = [];
      if (filters.city) parts.push(filters.city);
      if (filters.type) parts.push(filters.type);
      if (filters.min_price || filters.max_price) {
        const min = filters.min_price
          ? `$${Number(filters.min_price).toLocaleString()}`
          : 'Any';
        const max = filters.max_price
          ? `$${Number(filters.max_price).toLocaleString()}`
          : 'Any';
        parts.push(`${min} - ${max}`);
      }
      if (filters.beds) parts.push(`${filters.beds}+ beds`);
      if (filters.baths) parts.push(`${filters.baths}+ baths`);
      return parts.join(' · ') || 'All properties';
    },
  };
}
