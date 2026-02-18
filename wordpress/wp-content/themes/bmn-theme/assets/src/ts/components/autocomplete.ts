/**
 * Hero Search Autocomplete Component
 *
 * Debounced input with suggestions from /bmn/v1/properties/autocomplete.
 *
 * Two modes:
 * - Navigate (default): redirects to search page on select (homepage, standalone)
 * - Dispatch: fires $dispatch('autocomplete:select', {...}) for parent components
 *   to consume (used inside filter-bar on map/list search pages)
 *
 * Mode is auto-detected: if the component is inside a parent that has a
 * `submitFilters` method (filterState or mapSearch), it dispatches.
 * Otherwise it navigates.
 */

declare const bmnTheme: {
  autocompleteUrl: string;
  searchUrl: string;
};

interface Suggestion {
  text: string;
  value: string;
  type: string;
  type_label?: string;
}

export function autocompleteComponent() {
  return {
    query: '',
    suggestions: [] as Suggestion[],
    showSuggestions: false,
    highlightedIndex: -1,
    abortController: null as AbortController | null,

    async fetchSuggestions() {
      const term = this.query.trim();
      if (term.length < 2) {
        this.suggestions = [];
        this.showSuggestions = false;
        return;
      }

      if (this.abortController) {
        this.abortController.abort();
      }
      this.abortController = new AbortController();

      try {
        const url = `${bmnTheme.autocompleteUrl}?term=${encodeURIComponent(term)}`;
        const response = await fetch(url, {
          signal: this.abortController.signal,
          headers: { 'Content-Type': 'application/json' },
        });

        if (!response.ok) return;

        const data = await response.json();
        this.suggestions = (data.data || data || []).map((item: Record<string, string>) => ({
          text: item.text || item.label || item.value || '',
          value: item.value || item.text || '',
          type: item.type || 'general',
          type_label: item.type_label || item.type || '',
        }));
        this.showSuggestions = this.suggestions.length > 0;
        this.highlightedIndex = -1;
      } catch (e) {
        if ((e as Error).name !== 'AbortError') {
          this.suggestions = [];
          this.showSuggestions = false;
        }
      }
    },

    selectSuggestion(suggestion: Suggestion) {
      this.query = suggestion.text;
      this.showSuggestions = false;

      // Dispatch mode: when inside filter-bar on search pages (data-mode="dispatch")
      const mode = (this as any).$el?.closest('[data-mode]')?.dataset?.mode || 'navigate';
      if (mode === 'dispatch') {
        (this as any).$dispatch('autocomplete:select', {
          type: suggestion.type,
          value: suggestion.value,
          text: suggestion.text,
        });
        return;
      }

      // Navigate mode: redirect to search page
      const searchUrl = bmnTheme.searchUrl;
      switch (suggestion.type) {
        case 'city':
          window.location.href = `${searchUrl}?city=${encodeURIComponent(suggestion.value)}`;
          break;
        case 'neighborhood':
          window.location.href = `${searchUrl}?neighborhood=${encodeURIComponent(suggestion.value)}`;
          break;
        case 'address':
          window.location.href = `${searchUrl}?address=${encodeURIComponent(suggestion.value)}`;
          break;
        case 'mls_number':
          window.location.href = `/property/${suggestion.value}/`;
          break;
        case 'street':
          window.location.href = `${searchUrl}?street=${encodeURIComponent(suggestion.value)}`;
          break;
        default:
          window.location.href = `${searchUrl}?search=${encodeURIComponent(suggestion.value)}`;
      }
    },

    highlightNext() {
      if (this.highlightedIndex < this.suggestions.length - 1) {
        this.highlightedIndex++;
      }
    },

    highlightPrev() {
      if (this.highlightedIndex > 0) {
        this.highlightedIndex--;
      }
    },

    selectHighlighted() {
      if (this.highlightedIndex >= 0 && this.highlightedIndex < this.suggestions.length) {
        this.selectSuggestion(this.suggestions[this.highlightedIndex]);
      }
    },

    getSuggestionIcon(type: string): string {
      const icons: Record<string, string> = {
        city: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
        neighborhood: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        address: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        mls_number: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>',
        street: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>',
      };
      return icons[type] || '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>';
    },
  };
}
