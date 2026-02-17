<?php
/**
 * Homepage: City Market Insights
 *
 * Tabbed city selector with market stats per city (Alpine.js tabs).
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$cities = bmn_get_cities();

// Default cities if none configured
if (empty($cities)) {
    $cities = array(
        array('name' => 'Boston'),
        array('name' => 'Cambridge'),
        array('name' => 'Somerville'),
        array('name' => 'Brookline'),
    );
}

// Limit to 6 cities for tabs
$tab_cities = array_slice($cities, 0, 6);
?>

<section class="py-12 md:py-16 lg:py-20 bg-navy-700 text-white" aria-labelledby="market-analytics-title"
         x-data="{
             activeCity: '<?php echo esc_js($tab_cities[0]['name'] ?? ''); ?>',
             loading: false,
             data: null,
             async loadCityData(city) {
                 this.activeCity = city;
                 this.loading = true;
                 try {
                     const res = await fetch('<?php echo esc_url(rest_url('mld-mobile/v1/market/city')); ?>?city=' + encodeURIComponent(city));
                     if (res.ok) this.data = await res.json();
                 } catch(e) { /* fail silently */ }
                 this.loading = false;
             }
         }">
    <div class="max-w-6xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="market-analytics-title" class="section-title !text-white">City Market Insights</h2>
            <p class="section-subtitle mx-auto !text-gray-300">Compare market conditions across Greater Boston cities</p>
        </div>

        <!-- City Tabs -->
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            <?php foreach ($tab_cities as $city) : ?>
                <button @click="loadCityData('<?php echo esc_js($city['name'] ?? ''); ?>')"
                        :class="activeCity === '<?php echo esc_js($city['name'] ?? ''); ?>'
                            ? 'bg-white text-navy-700'
                            : 'bg-white/10 text-white/80 hover:bg-white/20'"
                        class="px-5 py-2 rounded-full text-sm font-medium transition-colors">
                    <?php echo esc_html($city['name'] ?? ''); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Market Data Display -->
        <div class="glass-card !bg-white/10 !border-white/10 min-h-[200px]">
            <!-- Loading state -->
            <div x-show="loading" class="flex items-center justify-center py-12">
                <svg class="animate-spin w-8 h-8 text-white/60" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            <!-- Data display -->
            <div x-show="!loading">
                <div class="text-center mb-6">
                    <h3 class="text-xl font-semibold" x-text="activeCity + ' Market Overview'"></h3>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Active Listings</p>
                        <p class="text-2xl font-bold mt-1" x-text="data?.active_count || '—'"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Median Price</p>
                        <p class="text-2xl font-bold mt-1" x-text="data?.median_price ? '$' + Number(data.median_price).toLocaleString() : '—'"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Avg Days on Market</p>
                        <p class="text-2xl font-bold mt-1" x-text="data?.avg_dom || '—'"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">New This Week</p>
                        <p class="text-2xl font-bold mt-1" x-text="data?.new_this_week || '—'"></p>
                    </div>
                </div>

                <!-- CTA -->
                <div class="text-center mt-8">
                    <a :href="'<?php echo esc_url(bmn_get_search_url()); ?>?city=' + encodeURIComponent(activeCity)"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-navy-700 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                        Browse <span x-text="activeCity"></span> Properties
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
