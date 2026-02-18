<?php
/**
 * Search Filter Bar — Redfin-style horizontal filter bar
 *
 * Shared by BOTH list (page-property-search.php) and map (page-map-search.php).
 * Renders inside a parent x-data scope that provides filter state.
 *
 * Includes: Location autocomplete, Price, Beds/Baths, Type, More, Sort,
 *           List/Map view toggle, Save Search, and active filter chips.
 *
 * @package bmn_theme
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$property_types = bmn_get_property_types();
$current_view   = $args['view'] ?? 'list'; // 'list' or 'map'
?>

<!-- Filter Bar -->
<div class="bg-white border-b border-gray-200 z-20 relative">
    <div class="flex items-center gap-2 px-4 py-2.5 flex-wrap">

        <!-- Location Autocomplete -->
        <div x-data="autocomplete" data-mode="dispatch" class="relative flex-shrink-0">
            <input type="text"
                   x-model="query"
                   @input.debounce.300ms="fetchSuggestions()"
                   @keydown.arrow-down.prevent="highlightNext()"
                   @keydown.arrow-up.prevent="highlightPrev()"
                   @keydown.enter.prevent="selectHighlighted()"
                   @click.outside="showSuggestions = false"
                   placeholder="City, neighborhood, address..."
                   class="w-48 sm:w-56 text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600 py-1.5 pl-8">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <!-- Suggestions dropdown -->
            <div x-show="showSuggestions && suggestions.length > 0"
                 x-transition
                 class="absolute z-50 w-72 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                 x-cloak>
                <template x-for="(s, i) in suggestions" :key="i">
                    <button @click="selectSuggestion(s)"
                            :class="i === highlightedIndex ? 'bg-teal-50' : ''"
                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-gray-50">
                        <span class="text-gray-400 flex-shrink-0" x-html="getSuggestionIcon(s.type)"></span>
                        <span x-text="s.text" class="truncate"></span>
                        <span x-text="s.type_label" class="ml-auto text-xs text-gray-400 flex-shrink-0"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="w-px h-6 bg-gray-200 flex-shrink-0"></div>

        <!-- Price Dropdown -->
        <div x-data="{ open: false }" @click.outside="open = false" class="relative flex-shrink-0">
            <button @click="open = !open"
                    :class="(min_price || max_price) ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-gray-200 text-gray-700'"
                    class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium border rounded-lg hover:bg-gray-50 transition-colors">
                Price
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="filter-popover" x-cloak>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Min Price</label>
                        <select x-model="min_price" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                            <option value="">No Min</option>
                            <option value="100000">$100K</option>
                            <option value="200000">$200K</option>
                            <option value="300000">$300K</option>
                            <option value="400000">$400K</option>
                            <option value="500000">$500K</option>
                            <option value="600000">$600K</option>
                            <option value="750000">$750K</option>
                            <option value="1000000">$1M</option>
                            <option value="1500000">$1.5M</option>
                            <option value="2000000">$2M</option>
                            <option value="3000000">$3M</option>
                            <option value="5000000">$5M</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Max Price</label>
                        <select x-model="max_price" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                            <option value="">No Max</option>
                            <option value="200000">$200K</option>
                            <option value="300000">$300K</option>
                            <option value="400000">$400K</option>
                            <option value="500000">$500K</option>
                            <option value="600000">$600K</option>
                            <option value="750000">$750K</option>
                            <option value="1000000">$1M</option>
                            <option value="1500000">$1.5M</option>
                            <option value="2000000">$2M</option>
                            <option value="3000000">$3M</option>
                            <option value="5000000">$5M</option>
                            <option value="10000000">$10M</option>
                        </select>
                    </div>
                    <button @click="submitFilters(); open = false" class="btn-search w-full">Apply</button>
                </div>
            </div>
        </div>

        <!-- Beds/Baths Dropdown -->
        <div x-data="{ open: false }" @click.outside="open = false" class="relative flex-shrink-0">
            <button @click="open = !open"
                    :class="(beds || baths) ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-gray-200 text-gray-700'"
                    class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium border rounded-lg hover:bg-gray-50 transition-colors">
                Beds / Baths
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="filter-popover" x-cloak>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Bedrooms</label>
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
                        <label class="block text-xs font-medium text-gray-500 mb-1">Bathrooms</label>
                        <select x-model="baths" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                            <option value="">Any</option>
                            <option value="1">1+</option>
                            <option value="1.5">1.5+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                        </select>
                    </div>
                    <button @click="submitFilters(); open = false" class="btn-search w-full">Apply</button>
                </div>
            </div>
        </div>

        <!-- Property Type Dropdown -->
        <div x-data="{ open: false }" @click.outside="open = false" class="relative flex-shrink-0">
            <button @click="open = !open"
                    :class="property_type.length ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-gray-200 text-gray-700'"
                    class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium border rounded-lg hover:bg-gray-50 transition-colors">
                Type
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="filter-popover min-w-[260px]" x-cloak>
                <div class="space-y-2">
                    <div class="max-h-52 overflow-y-auto space-y-1.5 pr-1">
                        <?php foreach ($property_types as $type) : ?>
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                <input type="checkbox"
                                       value="<?php echo esc_attr($type); ?>"
                                       :checked="property_type.includes('<?php echo esc_js($type); ?>')"
                                       @change="togglePropertyType('<?php echo esc_js($type); ?>')"
                                       class="rounded border-gray-300 text-teal-600 focus:ring-teal-500 flex-shrink-0">
                                <?php echo esc_html($type); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="pt-2 border-t border-gray-100">
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Status</label>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array('Active', 'Pending', 'Sold') as $status_val) : ?>
                                <button type="button"
                                        @click="toggleStatus('<?php echo esc_js($status_val); ?>')"
                                        :class="status.includes('<?php echo esc_js($status_val); ?>') ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600 border-gray-200'"
                                        class="px-2 py-1 text-xs font-medium border rounded-md transition-colors">
                                    <?php echo esc_html($status_val); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button @click="submitFilters(); open = false" class="btn-search w-full mt-2">Apply</button>
                </div>
            </div>
        </div>

        <!-- More Filters Toggle -->
        <button @click="moreFiltersOpen = !moreFiltersOpen"
                :class="moreFiltersOpen ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-gray-200 text-gray-700'"
                class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium border rounded-lg hover:bg-gray-50 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            More
        </button>

        <!-- Spacer -->
        <div class="flex-1"></div>

        <!-- Sort -->
        <select x-model="sort" @change="submitFilters()"
                class="text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600 py-1.5 flex-shrink-0">
            <option value="newest">Newest First</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="beds_desc">Most Bedrooms</option>
            <option value="sqft_desc">Largest</option>
        </select>

        <!-- View Toggle -->
        <?php if ($current_view === 'list') : ?>
            <a :href="getMapSearchUrl()"
               class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-500 hover:text-teal-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors flex-shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                Map
            </a>
        <?php else : ?>
            <a :href="getListSearchUrl()"
               class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-500 hover:text-teal-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                List
            </a>
        <?php endif; ?>

        <!-- Save Search Button -->
        <button @click="saveSearchOpen = true"
                class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-500 hover:text-teal-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors flex-shrink-0"
                title="Save this search">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            Save
        </button>
    </div>

    <!-- Active Filter Chips -->
    <template x-if="activeChips.length > 0">
        <div class="flex items-center gap-2 px-4 pb-2.5 overflow-x-auto scrollbar-hide">
            <template x-for="chip in activeChips" :key="chip.key + (chip.value || '')">
                <span class="filter-chip">
                    <span x-text="chip.label"></span>
                    <button @click="removeChip(chip)" class="ml-0.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </span>
            </template>
            <button @click="resetFilters()" class="text-xs text-gray-400 hover:text-teal-600 whitespace-nowrap transition-colors">
                Clear All
            </button>
        </div>
    </template>

    <!-- Results count (list view only) -->
    <?php if ($current_view === 'list') : ?>
        <div class="px-4 pb-2">
            <p class="text-sm text-gray-500" x-text="totalLabel"></p>
        </div>
    <?php endif; ?>
</div>

<!-- More Filters Panel (slide-down) -->
<?php get_template_part('template-parts/search/more-filters'); ?>
