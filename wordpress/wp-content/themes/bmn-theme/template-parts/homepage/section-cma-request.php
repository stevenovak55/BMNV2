<?php
/**
 * Homepage: CMA Request Form
 *
 * Two-column layout: benefits list + lead capture form.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<section class="py-12 md:py-16 lg:py-20 bg-gradient-to-br from-navy-800 to-navy-900 text-white" aria-labelledby="cma-title">
    <div class="max-w-6xl mx-auto px-4 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">

            <!-- Benefits -->
            <div>
                <h2 id="cma-title" class="text-2xl md:text-3xl font-bold">What's Your Home Worth?</h2>
                <p class="mt-3 text-gray-300">Get a free, no-obligation Comparative Market Analysis from our team.</p>

                <ul class="mt-8 space-y-4">
                    <?php
                    $benefits = array(
                        'Detailed analysis of comparable sales in your area',
                        'Current market conditions and pricing trends',
                        'Professional recommendations for maximum value',
                        'No obligation &mdash; completely free',
                    );
                    foreach ($benefits as $benefit) : ?>
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-gray-200"><?php echo wp_kses_post($benefit); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Form -->
            <div class="glass-card !bg-white/10 !border-white/10"
                 x-data="{ submitted: false, loading: false }"
                 x-show="!submitted">
                <form hx-post="<?php echo esc_url(rest_url('bmn/v1/leads/cma')); ?>"
                      hx-trigger="submit"
                      hx-swap="outerHTML"
                      hx-headers='{"Content-Type": "application/json"}'
                      @htmx:before-request="loading = true"
                      @htmx:after-request="loading = false"
                      class="space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="cma-name" class="block text-sm font-medium text-gray-200 mb-1">Name</label>
                            <input type="text" id="cma-name" name="name" required
                                   class="input-field !bg-white/10 !border-white/20 !text-white !placeholder-gray-400">
                        </div>
                        <div>
                            <label for="cma-email" class="block text-sm font-medium text-gray-200 mb-1">Email</label>
                            <input type="email" id="cma-email" name="email" required
                                   class="input-field !bg-white/10 !border-white/20 !text-white !placeholder-gray-400">
                        </div>
                    </div>

                    <div>
                        <label for="cma-phone" class="block text-sm font-medium text-gray-200 mb-1">Phone</label>
                        <input type="tel" id="cma-phone" name="phone"
                               class="input-field !bg-white/10 !border-white/20 !text-white !placeholder-gray-400">
                    </div>

                    <div>
                        <label for="cma-address" class="block text-sm font-medium text-gray-200 mb-1">Property Address</label>
                        <input type="text" id="cma-address" name="address" required
                               placeholder="Enter your property address"
                               class="input-field !bg-white/10 !border-white/20 !text-white !placeholder-gray-400">
                    </div>

                    <div>
                        <label for="cma-type" class="block text-sm font-medium text-gray-200 mb-1">Property Type</label>
                        <select id="cma-type" name="property_type"
                                class="input-field !bg-white/10 !border-white/20 !text-white">
                            <option value="single_family">Single Family</option>
                            <option value="condo">Condo / Townhouse</option>
                            <option value="multi_family">Multi-Family</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="cma-timeline" class="block text-sm font-medium text-gray-200 mb-1">Timeline</label>
                        <select id="cma-timeline" name="timeline"
                                class="input-field !bg-white/10 !border-white/20 !text-white">
                            <option value="asap">As soon as possible</option>
                            <option value="1-3months">1-3 months</option>
                            <option value="3-6months">3-6 months</option>
                            <option value="just_curious">Just curious</option>
                        </select>
                    </div>

                    <button type="submit"
                            class="w-full btn-red !py-3"
                            :disabled="loading"
                            :class="loading && 'opacity-75 cursor-wait'">
                        <span x-show="!loading">Get My Free CMA</span>
                        <span x-show="loading" x-cloak>Submitting...</span>
                    </button>
                </form>
            </div>

            <!-- Success message -->
            <div x-show="submitted" x-cloak class="glass-card !bg-white/10 !border-white/10 text-center py-12">
                <svg class="w-16 h-16 text-green-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-semibold">Request Received!</h3>
                <p class="mt-2 text-gray-300">We'll prepare your CMA and get back to you shortly.</p>
            </div>
        </div>
    </div>
</section>
