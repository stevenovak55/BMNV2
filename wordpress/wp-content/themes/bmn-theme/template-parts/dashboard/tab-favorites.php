<?php
/**
 * Dashboard: Favorites Tab
 *
 * Displays saved favorite properties in a grid.
 * Data loaded via Alpine.js from REST API.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div x-show="activeTab === 'favorites'">
    <!-- Loading -->
    <div x-show="loading && !favoritesLoaded" class="flex justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-navy-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- Empty State -->
    <div x-show="favoritesLoaded && favorites.length === 0 && !loading" class="text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
        </svg>
        <h3 class="text-lg font-semibold text-gray-900 mb-1">No favorites yet</h3>
        <p class="text-gray-600 mb-6">Start browsing properties and save your favorites here.</p>
        <a href="<?php echo esc_url(bmn_get_search_url()); ?>" class="btn-primary">
            Browse Properties
        </a>
    </div>

    <!-- Favorites Grid -->
    <div x-show="favoritesLoaded && favorites.length > 0"
         class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-for="listing in favorites" :key="listing.listing_id">
            <div class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden card-hover relative">
                <!-- Remove Button -->
                <button @click.prevent="removeFavorite(listing.listing_id)"
                        class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-white/90 text-red-500 hover:bg-red-500 hover:text-white transition-colors shadow-sm backdrop-blur-sm"
                        title="Remove from favorites">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </button>

                <a :href="listing.url" class="block">
                    <!-- Image -->
                    <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
                        <img :src="listing.photo"
                             :alt="listing.address"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                             loading="lazy"
                             x-show="listing.photo">
                        <div x-show="!listing.photo" class="flex items-center justify-center h-full text-gray-300">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        </div>

                        <!-- Price badge -->
                        <span x-show="listing.price"
                              x-text="listing.price"
                              class="absolute bottom-3 left-3 bg-navy-700/90 text-white text-sm font-semibold px-3 py-1 rounded-lg backdrop-blur-sm">
                        </span>

                        <!-- Type badge -->
                        <span x-show="listing.type"
                              x-text="listing.type"
                              class="absolute top-3 left-3 bg-white/90 text-gray-700 text-xs font-medium px-2 py-1 rounded-md backdrop-blur-sm">
                        </span>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <h3 class="font-semibold text-gray-900 truncate group-hover:text-navy-700 transition-colors"
                            x-text="listing.address"></h3>
                        <p class="text-sm text-gray-500 mt-0.5"
                           x-text="`${listing.city}, ${listing.state} ${listing.zip}`"></p>

                        <div class="flex items-center gap-3 mt-3 text-sm text-gray-600">
                            <span x-show="listing.beds" class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span x-text="listing.beds + ' bd'"></span>
                            </span>
                            <span x-show="listing.baths" class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                <span x-text="listing.baths + ' ba'"></span>
                            </span>
                            <span x-show="listing.sqft" class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                                <span x-text="listing.sqft + ' sqft'"></span>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        </template>
    </div>
</div>
