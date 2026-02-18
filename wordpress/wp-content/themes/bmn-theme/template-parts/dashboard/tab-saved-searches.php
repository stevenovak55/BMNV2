<?php
/**
 * Dashboard: Saved Searches Tab
 *
 * Lists saved searches with filter summary, run and delete actions.
 * Data loaded via Alpine.js from REST API.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div x-show="activeTab === 'saved-searches'">
    <!-- Loading -->
    <div x-show="loading && !searchesLoaded" class="flex justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-navy-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- Empty State -->
    <div x-show="searchesLoaded && savedSearches.length === 0 && !loading" class="text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <h3 class="text-lg font-semibold text-gray-900 mb-1">No saved searches</h3>
        <p class="text-gray-600 mb-6">Save a search from the property search page to get started.</p>
        <a href="<?php echo esc_url(bmn_get_search_url()); ?>" class="btn-primary">
            Search Properties
        </a>
    </div>

    <!-- Saved Searches List -->
    <div x-show="searchesLoaded && savedSearches.length > 0" class="space-y-4">
        <template x-for="search in savedSearches" :key="search.id">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate" x-text="search.name"></h3>
                    <p class="text-sm text-gray-500 mt-1 truncate" x-text="formatFilters(search.filters || {})"></p>
                    <p class="text-xs text-gray-400 mt-1">
                        Saved <span x-text="formatDate(search.created_at)"></span>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="runSearch(search.filters || {})"
                            class="btn-primary text-sm !py-2 !px-4">
                        Run Search
                    </button>
                    <button @click="confirmDelete = (confirmDelete === search.id ? null : search.id)"
                            class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50"
                            title="Delete search">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>

                <!-- Delete Confirmation -->
                <div x-show="confirmDelete === search.id"
                     x-transition
                     class="w-full sm:w-auto flex items-center gap-2 bg-red-50 rounded-lg p-3 text-sm">
                    <span class="text-red-700">Delete this search?</span>
                    <button @click="deleteSavedSearch(search.id)"
                            class="px-3 py-1 bg-red-500 text-white rounded-md text-xs font-medium hover:bg-red-600">
                        Yes, delete
                    </button>
                    <button @click="confirmDelete = null"
                            class="px-3 py-1 bg-white text-gray-600 rounded-md text-xs font-medium hover:bg-gray-50 border border-gray-200">
                        Cancel
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
