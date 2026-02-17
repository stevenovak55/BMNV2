<?php
/**
 * Homepage: Property Alerts Signup
 *
 * Alert signup form with email, cities, price range, beds, property types, frequency.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$property_types = bmn_get_property_types();
?>

<section class="py-12 md:py-16 lg:py-20 bg-beige-100" aria-labelledby="alerts-title">
    <div class="max-w-4xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="alerts-title" class="section-title">Never Miss a Listing</h2>
            <p class="section-subtitle mx-auto">Set up alerts to get notified when properties matching your criteria hit the market.</p>
        </div>

        <div class="glass-card"
             x-data="{
                 minPrice: 200000,
                 maxPrice: 1500000,
                 submitted: false,
                 loading: false,
                 formatPrice(val) { return '$' + Number(val).toLocaleString(); }
             }">
            <form hx-post="<?php echo esc_url(rest_url('bmn/v1/leads/alert')); ?>"
                  hx-trigger="submit"
                  hx-swap="outerHTML"
                  hx-headers='{"Content-Type": "application/json"}'
                  class="space-y-5"
                  x-show="!submitted">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="alert-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="alert-email" name="email" required class="input-field">
                    </div>
                    <div>
                        <label for="alert-cities" class="block text-sm font-medium text-gray-700 mb-1">Cities</label>
                        <input type="text" id="alert-cities" name="cities" placeholder="e.g. Boston, Cambridge, Somerville" class="input-field">
                    </div>
                </div>

                <!-- Price Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Price Range: <span class="text-navy-700" x-text="formatPrice(minPrice) + ' – ' + formatPrice(maxPrice)"></span>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="range" name="min_price" x-model="minPrice" min="0" max="5000000" step="25000"
                                   class="w-full accent-navy-700">
                        </div>
                        <div>
                            <input type="range" name="max_price" x-model="maxPrice" min="0" max="5000000" step="25000"
                                   class="w-full accent-navy-700">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="alert-beds" class="block text-sm font-medium text-gray-700 mb-1">Min Bedrooms</label>
                        <select id="alert-beds" name="beds" class="input-field">
                            <option value="">Any</option>
                            <option value="1">1+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                        </select>
                    </div>
                    <div>
                        <label for="alert-frequency" class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
                        <select id="alert-frequency" name="frequency" class="input-field">
                            <option value="instant">Instant</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                </div>

                <!-- Property Types -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Property Types</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($property_types as $type) : ?>
                            <label class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200 cursor-pointer hover:bg-navy-50 hover:border-navy-200 transition-colors text-sm">
                                <input type="checkbox" name="property_types[]" value="<?php echo esc_attr($type); ?>" class="rounded border-gray-300 text-navy-700 focus:ring-navy-500">
                                <?php echo esc_html($type); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="w-full btn-primary !py-3" :disabled="loading">
                    <span x-show="!loading">Create Alert</span>
                    <span x-show="loading" x-cloak>Setting up...</span>
                </button>
            </form>

            <div x-show="submitted" x-cloak class="text-center py-8">
                <svg class="w-12 h-12 text-green-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900">Alert Created!</h3>
                <p class="mt-1 text-gray-600">You'll be notified when matching properties become available.</p>
            </div>
        </div>
    </div>
</section>
