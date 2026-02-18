<?php
/**
 * More Filters Panel — collapsible advanced filters below filter bar.
 *
 * Sits inside parent x-data scope. Uses Alpine x-show + x-collapse.
 *
 * @package bmn_theme
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div x-show="moreFiltersOpen" x-collapse x-cloak
     class="bg-white border-b border-gray-200 z-10 relative">
    <div class="max-w-5xl mx-auto px-4 py-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">

            <!-- Sqft Range -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sqft Min</label>
                <select x-model="sqft_min" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="500">500</option>
                    <option value="750">750</option>
                    <option value="1000">1,000</option>
                    <option value="1250">1,250</option>
                    <option value="1500">1,500</option>
                    <option value="2000">2,000</option>
                    <option value="2500">2,500</option>
                    <option value="3000">3,000</option>
                    <option value="5000">5,000</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sqft Max</label>
                <select x-model="sqft_max" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="1000">1,000</option>
                    <option value="1500">1,500</option>
                    <option value="2000">2,000</option>
                    <option value="2500">2,500</option>
                    <option value="3000">3,000</option>
                    <option value="4000">4,000</option>
                    <option value="5000">5,000</option>
                    <option value="7500">7,500</option>
                    <option value="10000">10,000</option>
                </select>
            </div>

            <!-- Lot Size Range -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Lot Min (acres)</label>
                <select x-model="lot_size_min" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="0.25">0.25</option>
                    <option value="0.5">0.5</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="5">5</option>
                    <option value="10">10</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Lot Max (acres)</label>
                <select x-model="lot_size_max" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="0.5">0.5</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="20">20+</option>
                </select>
            </div>

            <!-- Year Built -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Year Built Min</label>
                <input type="number" x-model="year_built_min" placeholder="e.g. 1990" min="1800" max="2030"
                       class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Year Built Max</label>
                <input type="number" x-model="year_built_max" placeholder="e.g. 2025" min="1800" max="2030"
                       class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
            </div>

            <!-- Days on Market -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Days on Market</label>
                <select x-model="max_dom" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="1">1 day</option>
                    <option value="3">3 days</option>
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                    <option value="90">90 days</option>
                </select>
            </div>

            <!-- Garage -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Garage Spaces</label>
                <select x-model="garage" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="1">1+</option>
                    <option value="2">2+</option>
                    <option value="3">3+</option>
                </select>
            </div>

            <!-- School Grade -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">School Grade</label>
                <select x-model="school_grade" class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600">
                    <option value="">Any</option>
                    <option value="A">A or higher</option>
                    <option value="B">B or higher</option>
                    <option value="C">C or higher</option>
                </select>
            </div>

            <!-- Checkboxes -->
            <div class="col-span-2 sm:col-span-3 lg:col-span-4">
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="price_reduced === '1'"
                               @change="price_reduced = $event.target.checked ? '1' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Price Reduced
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="new_listing_days === '7'"
                               @change="new_listing_days = $event.target.checked ? '7' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        New This Week
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="virtual_tour === '1'"
                               @change="virtual_tour = $event.target.checked ? '1' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Virtual Tour
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="fireplace === '1'"
                               @change="fireplace = $event.target.checked ? '1' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Fireplace
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="open_house === '1'"
                               @change="open_house = $event.target.checked ? '1' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Open House
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               :checked="exclusive === '1'"
                               @change="exclusive = $event.target.checked ? '1' : ''"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Exclusive
                    </label>
                </div>
            </div>
        </div>

        <!-- Apply / Reset -->
        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100">
            <button @click="submitFilters(); moreFiltersOpen = false" class="btn-search">
                Apply Filters
            </button>
            <button @click="resetFilters()" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                Reset All
            </button>
        </div>
    </div>
</div>
