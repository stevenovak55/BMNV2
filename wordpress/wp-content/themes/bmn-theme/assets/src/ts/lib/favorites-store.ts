/**
 * Favorites Store — optimistic localStorage + API sync singleton.
 *
 * Reads `bmn_favorites` from localStorage on init.
 * Toggle immediately updates Set + localStorage, then syncs to API with JWT.
 */

declare const bmnTheme: {
  homeUrl: string;
  loginUrl: string;
};

type FavoritesListener = () => void;

class FavoritesStore {
  private static instance: FavoritesStore | null = null;
  private favorites: Set<string> = new Set();
  private listeners: FavoritesListener[] = [];
  private storageKey = 'bmn_favorites';

  private constructor() {
    this.load();
  }

  static getInstance(): FavoritesStore {
    if (!FavoritesStore.instance) {
      FavoritesStore.instance = new FavoritesStore();
    }
    return FavoritesStore.instance;
  }

  private load() {
    try {
      const raw = localStorage.getItem(this.storageKey);
      if (raw) {
        const arr = JSON.parse(raw);
        if (Array.isArray(arr)) {
          this.favorites = new Set(arr);
        }
      }
    } catch {
      this.favorites = new Set();
    }
  }

  private save() {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify([...this.favorites]));
    } catch {
      // localStorage full or unavailable
    }
  }

  private notify() {
    for (const fn of this.listeners) fn();
  }

  isFavorite(listingId: string): boolean {
    return this.favorites.has(listingId);
  }

  async toggle(listingId: string): Promise<void> {
    const wasInSet = this.favorites.has(listingId);

    // Optimistic update
    if (wasInSet) {
      this.favorites.delete(listingId);
    } else {
      this.favorites.add(listingId);
    }
    this.save();
    this.notify();

    // Sync to API if logged in
    const token = localStorage.getItem('bmn_access_token');
    if (!token) return; // Guest — local-only

    try {
      const method = wasInSet ? 'DELETE' : 'POST';
      const resp = await fetch(
        `${bmnTheme.homeUrl}wp-json/bmn/v1/favorites/${listingId}`,
        {
          method,
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        },
      );

      if (!resp.ok) {
        // Rollback on failure
        if (wasInSet) {
          this.favorites.add(listingId);
        } else {
          this.favorites.delete(listingId);
        }
        this.save();
        this.notify();
      }
    } catch {
      // Network error — rollback
      if (wasInSet) {
        this.favorites.add(listingId);
      } else {
        this.favorites.delete(listingId);
      }
      this.save();
      this.notify();
    }
  }

  onChange(fn: FavoritesListener) {
    this.listeners.push(fn);
  }

  getAll(): string[] {
    return [...this.favorites];
  }
}

export const favStore = FavoritesStore.getInstance();
