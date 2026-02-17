<?php
/**
 * Search Filter Sidebar
 *
 * Sticky sidebar with all property search filters.
 * All inputs bound to Alpine.js filterState via x-model.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$property_types = bmn_get_property_types();
?>

<!-- Mobile overlay -->
<div x-show="mobileFiltersOpen"
     x-transition:enter="transition-opacity ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="mobileFiltersOpen = false"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"
     x-cloak></div>

<!-- Sidebar -->
<aside :class="mobileFiltersOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 w-80 bg-white shadow-xl overflow-y-auto transition-transform lg:relative lg:inset-auto lg:z-auto lg:w-72 lg:flex-shrink-0 lg:shadow-none lg:bg-transparent lg:overflow-visible"
       x-cloak="false">
    <div class="lg:sticky lg:top-24">
        <div class="bg-white lg:rounded-xl lg:border lg:border-gray-200 lg:shadow-sm">

            <!-- Mobile header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 lg:hidden">
                <h2 class="font-semibold text-gray-900">Filters</h2>
                <button @click="mobileFiltersOpen = false" class="p-1 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-4 space-y-5">

                <!-- Location (autocomplete) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Location</label>
                    <div x-data="autocomplete" class="relative">
                        <input type="text"
                               x-model="query"
                               @input.debounce.300ms="fetchSuggestions()"
                               @keydown.arrow-down.prevent="highlightNext()"
                               @keydown.arrow-up.prevent="highlightPrev()"
                               @keydown.enter.prevent="selectHighlighted()"
                               @click.outside="showSuggestions = false"
                               placeholder="City, neighborhood, address..."
                               class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                        <!-- Suggestions dropdown -->
                        <div x-show="showSuggestions && suggestions.length > 0"
                             x-transition
                             class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                             x-cloak>
                            <template x-for="(s, i) in suggestions" :key="i">
                                <button @click="selectSuggestion(s)"
                                        :class="i === highlightedIndex ? 'bg-navy-50' : ''"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-gray-50">
                                    <span class="text-gray-400 flex-shrink-0" x-html="getSuggestionIcon(s.type)"></span>
                                    <span x-text="s.text" class="truncate"></span>
                                    <span x-text="s.type_label" class="ml-auto text-xs text-gray-400 flex-shrink-0"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Price Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Price Range</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number"
                               x-model="min_price"
                               placeholder="Min"
                               min="0"
                               step="25000"
                               class="text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                        <input type="number"
                               x-model="max_price"
                               placeholder="Max"
                               min="0"
                               step="25000"
                               class="text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                    </div>
                </div>

                <!-- Beds / Baths -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Beds</label>
                        <select x-model="beds" class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                            <option value="">Any</option>
                            <option value="1">1+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                            <option value="5">5+</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Baths</label>
                        <select x-model="baths" class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                            <option value="">Any</option>
                            <option value="1">1+</option>
                            <option value="1.5">1.5+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                        </select>
                    </div>
                </div>

                <!-- Property Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Property Type</label>
                    <div class="space-y-1.5">
                        <?php foreach ($property_types as $type) : ?>
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                <input type="checkbox"
                                       value="<?php echo esc_attr($type); ?>"
                                       :checked="property_type.includes('<?php echo esc_js($type); ?>')"
                                       @change="togglePropertyType('<?php echo esc_js($type); ?>')"
                                       class="rounded border-gray-300 text-navy-600 focus:ring-navy-500">
                                <?php echo esc_html($type); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach (array('Active', 'Pending', 'Sold') as $status_val) : ?>
                            <button type="button"
                                    @click="toggleStatus('<?php echo esc_js($status_val); ?>')"
                                    :class="status.includes('<?php echo esc_js($status_val); ?>') ? 'bg-navy-700 text-white border-navy-700' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                                    class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors">
                                <?php echo esc_html($status_val); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- School Grade -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">School Grade</label>
                    <select x-model="school_grade" class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                        <option value="">Any</option>
                        <option value="A">A or higher</option>
                        <option value="B">B or higher</option>
                        <option value="C">C or higher</option>
                    </select>
                </div>

                <!-- Quick Filters -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Quick Filters</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox"
                                   :checked="price_reduced === '1'"
                                   @change="price_reduced = $event.target.checked ? '1' : ''"
                                   class="rounded border-gray-300 text-navy-600 focus:ring-navy-500">
                            Price Reduced
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox"
                                   :checked="new_listing_days === '7'"
                                   @change="new_listing_days = $event.target.checked ? '7' : ''"
                                   class="rounded border-gray-300 text-navy-600 focus:ring-navy-500">
                            New This Week
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2 pt-2">
                    <button @click="submitFilters()"
                            class="w-full btn-primary text-sm !py-2.5">
                        Apply Filters
                    </button>
                    <button @click="resetFilters()"
                            class="w-full px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        Reset All
                    </button>
                </div>
            </div>
        </div>
    </div>
</aside>
