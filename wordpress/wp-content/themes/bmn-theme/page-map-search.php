<?php
/**
 * Template Name: Map Search
 *
 * Split-screen property search with interactive Google Map on the left
 * and scrollable results sidebar on the right (452px, resizable).
 * Uses shared filter-bar.php for consistent filter UI across views.
 *
 * @package bmn_theme
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$google_maps_key = get_option('bmn_google_maps_api_key', '');

get_header();
?>

<!-- Lock page to viewport height (no footer on map search, like v1) -->
<style>
    #page { height: 100vh; max-height: 100vh; overflow: hidden; }
    body.admin-bar #page { height: calc(100vh - 32px); max-height: calc(100vh - 32px); }
    @media screen and (max-width: 782px) {
        body.admin-bar #page { height: calc(100vh - 46px); max-height: calc(100vh - 46px); }
    }
</style>

<main id="main" class="flex-1 flex flex-col overflow-hidden" x-data="mapSearch"
      @autocomplete:select="handleAutocompleteSelect($event.detail)">

    <!-- Shared Filter Bar -->
    <?php get_template_part('template-parts/search/filter-bar', null, array('view' => 'map')); ?>

    <!-- Split Screen: Map (flex) | Resize Handle | Sidebar (452px) -->
    <div id="map-wrapper" class="flex-1 flex min-h-0 relative">

        <!-- Map Container (fills remaining space) -->
        <div :class="mobileView === 'list' ? 'hidden lg:block' : ''"
             class="flex-1 relative min-h-[50vh] lg:min-h-0">
            <div id="map-container" class="absolute inset-0"></div>

            <!-- Loading overlay -->
            <div x-show="loading" x-transition.opacity
                 class="absolute inset-0 bg-white/30 flex items-center justify-center z-10 pointer-events-none" x-cloak>
                <div class="bg-white rounded-full shadow-lg px-4 py-2.5 flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-teal-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-600">Updating...</span>
                </div>
            </div>
        </div>

        <!-- Resize Handle (desktop only) -->
        <div id="resize-handle" class="hidden lg:flex bmn-resize-handle group"
             title="Drag to resize">
            <div class="bmn-resize-handle-bar group-hover:opacity-100"></div>
        </div>

        <!-- Results Sidebar (fixed width, right side) -->
        <div id="results-sidebar"
             :class="mobileView === 'map' ? 'hidden lg:flex' : 'flex'"
             class="flex-col bg-gray-50 border-l border-gray-200 min-h-0 w-full lg:w-[452px] lg:flex-none">

            <!-- Sidebar Header -->
            <div class="hidden lg:flex items-center justify-between px-4 py-2.5 bg-white border-b border-gray-200">
                <p class="text-sm font-medium text-gray-700" x-text="totalLabel"></p>
            </div>

            <!-- Scrollable Results -->
            <div class="flex-1 overflow-y-auto">
                <!-- Initial loading -->
                <template x-if="initialLoad">
                    <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                        <svg class="animate-spin h-8 w-8 text-teal-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="text-sm text-gray-500">Loading properties...</p>
                    </div>
                </template>

                <!-- Error state -->
                <template x-if="!initialLoad && fetchError && listings.length === 0">
                    <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                        <svg class="w-14 h-14 text-red-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <h3 class="text-base font-semibold text-gray-700 mb-1">Something went wrong</h3>
                        <p class="text-sm text-gray-500 max-w-xs">We couldn't load properties. Check your connection and try again.</p>
                        <button @click="fetchProperties()"
                                class="mt-3 px-4 py-2 text-sm font-medium text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-colors">
                            Try Again
                        </button>
                    </div>
                </template>

                <!-- Empty state -->
                <template x-if="!initialLoad && !fetchError && listings.length === 0">
                    <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                        <svg class="w-14 h-14 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="text-base font-semibold text-gray-700 mb-1">No Properties Found</h3>
                        <p class="text-sm text-gray-500 max-w-xs">Try zooming out or adjusting your filters.</p>
                        <button @click="resetFilters()"
                                class="mt-3 px-4 py-2 text-sm font-medium text-teal-700 bg-teal-50 rounded-lg hover:bg-teal-100 transition-colors">
                            Reset Filters
                        </button>
                    </div>
                </template>

                <!-- Property Cards -->
                <div x-show="!initialLoad && listings.length > 0" class="divide-y divide-gray-100">
                    <template x-for="listing in listings" :key="listing.listing_id">
                        <a :href="'<?php echo esc_url(home_url('/property/')); ?>' + listing.listing_id + '/'"
                           :data-listing-id="listing.listing_id"
                           class="flex gap-3 p-3 hover:bg-white transition-colors cursor-pointer relative"
                           :class="activeMarkerId === listing.listing_id ? 'bg-teal-50 ring-2 ring-teal-400 shadow-sm' : ''"
                           @mouseenter="highlightMarker(listing.listing_id)"
                           @mouseleave="unhighlightMarker(listing.listing_id)"
                           @click.prevent="centerOnProperty(listing.listing_id)">

                            <!-- Photo -->
                            <div class="flex-shrink-0 w-28 h-20 rounded-lg overflow-hidden bg-gray-100 relative">
                                <img x-show="listing.main_photo_url"
                                     :src="listing.main_photo_url"
                                     :alt="listing.address"
                                     class="w-full h-full object-cover"
                                     loading="lazy">
                                <div x-show="!listing.main_photo_url" class="flex items-center justify-center h-full text-gray-300">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                </div>
                                <!-- Status badge -->
                                <span x-show="listing.status && listing.status !== 'Active'"
                                      x-text="listing.status"
                                      :class="listing.status === 'Pending' || listing.status === 'Active Under Contract' ? 'bg-yellow-100 text-yellow-800' : listing.status === 'Closed' || listing.status === 'Sold' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'"
                                      class="absolute top-1 left-1 text-[10px] font-semibold px-1.5 py-0.5 rounded"></span>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-teal-700 text-sm" x-text="formatPrice(listing.price)"></span>
                                    <span x-show="listing.dom && listing.dom < 7"
                                          class="text-[10px] font-semibold bg-green-500 text-white px-1.5 py-0.5 rounded">New</span>
                                </div>
                                <div class="text-sm font-medium text-gray-900 truncate mt-0.5" x-text="listing.address"></div>
                                <div class="text-xs text-gray-500 mt-0.5" x-text="listing.city + ', ' + listing.state + ' ' + listing.zip"></div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                    <span x-show="listing.beds" x-text="listing.beds + ' bd'"></span>
                                    <span x-show="listing.beds && listing.baths" class="text-gray-300">|</span>
                                    <span x-show="listing.baths" x-text="listing.baths + ' ba'"></span>
                                    <span x-show="listing.baths && listing.sqft" class="text-gray-300">|</span>
                                    <span x-show="listing.sqft" x-text="Number(listing.sqft).toLocaleString() + ' sqft'"></span>
                                </div>
                            </div>

                            <!-- Favorite heart -->
                            <button class="absolute top-2 right-2 w-7 h-7 flex items-center justify-center rounded-full bg-white/80 shadow-sm hover:bg-white transition-all"
                                    :class="(_favVersion, favStore?.isFavorite(listing.listing_id)) ? 'text-red-500' : 'text-gray-400'"
                                    @click.prevent.stop="favStore?.toggle(listing.listing_id)">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                     :fill="(_favVersion, favStore?.isFavorite(listing.listing_id)) ? 'currentColor' : 'none'">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                        </a>
                    </template>
                </div>

                <!-- Max results note -->
                <div x-show="!initialLoad && total > listings.length" class="px-4 py-3 bg-gray-50 text-center border-t border-gray-100">
                    <p class="text-xs text-gray-400">Showing <span x-text="listings.length"></span> of <span x-text="total.toLocaleString()"></span>. Zoom in to see more.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Search Modal -->
    <div x-data="saveSearchModal" x-effect="if (saveSearchOpen) show()" x-show="saveSearchOpen || open" x-cloak>
        <div x-show="saveSearchOpen || open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50 z-50"
             @click="saveSearchOpen = false; close()">
        </div>
        <div x-show="saveSearchOpen || open"
             x-transition
             class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl p-6 z-50 w-full max-w-md"
             @click.outside="saveSearchOpen = false; close()">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Save This Search</h3>
            <input type="text" x-model="name" placeholder="Name your search..."
                   class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600 mb-3"
                   @keydown.enter="save(_getFilters())">
            <p x-show="error" x-text="error" class="text-sm text-red-600 mb-3"></p>
            <p x-show="success" class="text-sm text-green-600 mb-3">Search saved!</p>
            <div class="flex gap-3">
                <button @click="save(_getFilters())" :disabled="saving"
                        class="btn-search flex-1" x-text="saving ? 'Saving...' : 'Save Search'"></button>
                <button @click="saveSearchOpen = false; close()"
                        class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Mobile View Toggle (fixed bottom pill, hidden when save search modal is open) -->
    <div x-show="!saveSearchOpen" class="lg:hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-[9998]
                bg-white/90 backdrop-blur-xl border border-white/30
                rounded-full shadow-lg flex p-1 gap-1">
        <button @click="mobileView = 'list'"
                :class="mobileView === 'list' ? 'bg-teal-600 text-white' : 'text-gray-500'"
                class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-full transition-all">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                <rect x="4" y="5" width="16" height="2"/>
                <rect x="4" y="11" width="16" height="2"/>
                <rect x="4" y="17" width="16" height="2"/>
            </svg>
            List
        </button>
        <button @click="mobileView = 'map'"
                :class="mobileView === 'map' ? 'bg-teal-600 text-white' : 'text-gray-500'"
                class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-full transition-all">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
            </svg>
            Map
        </button>
    </div>
</main>

<?php if ($google_maps_key) : ?>
    <script>
        (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries","marker");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.googleapis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
            key: <?php echo wp_json_encode($google_maps_key); ?>,
            v: "weekly"
        });
    </script>
<?php endif; ?>

<?php // No footer on map search — full viewport height like v1 ?>
</div><!-- #page -->
<?php wp_footer(); ?>
</body>
</html>
