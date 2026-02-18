<?php
/**
 * Template Name: Map Search
 *
 * Split-screen property search with interactive Google Map on the left
 * and scrollable results sidebar on the right (452px, resizable).
 * Modeled after v1's half-map layout.
 *
 * @package bmn_theme
 * @version 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$google_maps_key = get_option('bmn_google_maps_api_key', '');
$property_types = bmn_get_property_types();

get_header();
?>

<!-- Lock page to viewport height (no footer on map search, like v1) -->
<style>
    #page { height: 100vh; max-height: 100vh; overflow: hidden; }
    /* Hide admin bar spacing if present */
    body.admin-bar #page { height: calc(100vh - 32px); max-height: calc(100vh - 32px); }
    @media screen and (max-width: 782px) {
        body.admin-bar #page { height: calc(100vh - 46px); max-height: calc(100vh - 46px); }
    }
</style>

<main id="main" class="flex-1 flex flex-col overflow-hidden" x-data="mapSearch">

    <!-- Top Bar: Filters + Sort -->
    <div class="bg-white border-b border-gray-200 z-20 relative">
        <div class="flex items-center justify-between px-4 py-2.5">
            <div class="flex items-center gap-3">
                <button @click="mobileFiltersOpen = !mobileFiltersOpen"
                        class="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filters
                </button>
                <p class="text-sm text-gray-500 hidden sm:block" x-text="totalLabel"></p>
            </div>
            <div class="flex items-center gap-3">
                <select x-model="sort" @change="submitFilters()"
                        class="text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600 py-1.5">
                    <option value="newest">Newest First</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="beds_desc">Most Bedrooms</option>
                    <option value="sqft_desc">Largest</option>
                </select>
                <a href="<?php echo esc_url(bmn_get_search_url()); ?>"
                   class="hidden sm:flex items-center gap-1.5 text-sm text-gray-500 hover:text-teal-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    List View
                </a>
            </div>
        </div>
        <!-- Mobile results count -->
        <div class="px-4 pb-2 sm:hidden">
            <p class="text-sm text-gray-500" x-text="totalLabel"></p>
        </div>
    </div>

    <!-- Filter Panel (slide-down) -->
    <div x-show="mobileFiltersOpen" x-collapse x-cloak
         class="bg-white border-b border-gray-200 z-10 relative">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Min Price</label>
                    <input type="number" x-model="min_price" placeholder="Any" min="0" step="25000"
                           class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Max Price</label>
                    <input type="number" x-model="max_price" placeholder="Any" min="0" step="25000"
                           class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Beds</label>
                    <select x-model="beds" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                        <option value="">Any</option>
                        <option value="1">1+</option>
                        <option value="2">2+</option>
                        <option value="3">3+</option>
                        <option value="4">4+</option>
                        <option value="5">5+</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Baths</label>
                    <select x-model="baths" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                        <option value="">Any</option>
                        <option value="1">1+</option>
                        <option value="1.5">1.5+</option>
                        <option value="2">2+</option>
                        <option value="3">3+</option>
                        <option value="4">4+</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
                    <div class="space-y-1">
                        <?php foreach ($property_types as $type) : ?>
                            <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                                <input type="checkbox"
                                       value="<?php echo esc_attr($type); ?>"
                                       :checked="property_type.includes('<?php echo esc_js($type); ?>')"
                                       @change="togglePropertyType('<?php echo esc_js($type); ?>')"
                                       class="rounded border-gray-300 text-teal-600 focus:ring-teal-500 w-3.5 h-3.5">
                                <span class="truncate"><?php echo esc_html($type); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <div class="flex flex-wrap gap-1 mb-2">
                        <?php foreach (array('Active', 'Pending', 'Sold') as $status_val) : ?>
                            <button type="button"
                                    @click="toggleStatus('<?php echo esc_js($status_val); ?>')"
                                    :class="status.includes('<?php echo esc_js($status_val); ?>') ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600 border-gray-200'"
                                    class="px-2 py-1 text-xs font-medium border rounded-md transition-colors">
                                <?php echo esc_html($status_val); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="price_reduced === '1'"
                               @change="price_reduced = $event.target.checked ? '1' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500 w-3.5 h-3.5">
                        Price Reduced
                    </label>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100">
                <button @click="submitFilters()"
                        class="inline-flex items-center px-5 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors">
                    Apply Filters
                </button>
                <button @click="resetFilters()"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700">
                    Reset All
                </button>
            </div>
        </div>
    </div>

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

                <!-- Empty state -->
                <template x-if="!initialLoad && listings.length === 0">
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
                        <a :href="getPropertyUrl(listing.listing_id)"
                           :data-listing-id="listing.listing_id"
                           class="flex gap-3 p-3 hover:bg-white transition-colors cursor-pointer"
                           :class="activeMarkerId === listing.listing_id ? 'bg-teal-50 ring-1 ring-teal-200' : ''"
                           @mouseenter="highlightMarker(listing.listing_id)"
                           @mouseleave="unhighlightMarker(listing.listing_id)"
                           @click.prevent="centerOnProperty(listing.listing_id)">

                            <!-- Photo -->
                            <div class="flex-shrink-0 w-28 h-20 rounded-lg overflow-hidden bg-gray-100">
                                <img x-show="listing.main_photo_url"
                                     :src="listing.main_photo_url"
                                     :alt="listing.address"
                                     class="w-full h-full object-cover"
                                     loading="lazy">
                                <div x-show="!listing.main_photo_url" class="flex items-center justify-center h-full text-gray-300">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-teal-700 text-sm" x-text="formatPrice(listing.price)"></div>
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

    <!-- Mobile View Toggle (fixed bottom pill) -->
    <div class="lg:hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-[9998]
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
