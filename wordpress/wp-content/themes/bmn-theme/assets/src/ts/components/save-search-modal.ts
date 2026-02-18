/**
 * Save Search Modal (Alpine.js component)
 *
 * Small modal for naming and saving the current filter set.
 * Posts to /bmn/v1/saved-searches with JWT bearer token.
 */

import { type SearchFilters, filtersToParams } from '../lib/filter-engine';

declare const bmnTheme: {
  homeUrl: string;
  loginUrl: string;
};

export function saveSearchModalComponent() {
  return {
    open: false,
    name: '',
    saving: false,
    error: '',
    success: false,

    show() {
      this.open = true;
      this.error = '';
      this.success = false;
      this.name = '';
    },

    close() {
      this.open = false;
    },

    async save(filters: SearchFilters) {
      const token = localStorage.getItem('bmn_access_token');
      if (!token) {
        window.location.href = bmnTheme.loginUrl;
        return;
      }

      if (!this.name.trim()) {
        this.error = 'Please enter a name for this search.';
        return;
      }

      this.saving = true;
      this.error = '';

      try {
        const params = filtersToParams(filters);
        const resp = await fetch(
          `${bmnTheme.homeUrl}wp-json/bmn/v1/saved-searches`,
          {
            method: 'POST',
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              name: this.name.trim(),
              filters: Object.fromEntries(params),
            }),
          },
        );

        if (resp.ok) {
          this.success = true;
          setTimeout(() => this.close(), 1500);
        } else {
          const data = await resp.json().catch(() => null);
          this.error = data?.message || 'Failed to save search. Please try again.';
        }
      } catch {
        this.error = 'Network error. Please try again.';
      }

      this.saving = false;
    },
  };
}
