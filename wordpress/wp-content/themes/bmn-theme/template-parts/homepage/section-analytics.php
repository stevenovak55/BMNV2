<?php
/**
 * Homepage: Neighborhood Analytics
 *
 * Cards showing active listings, median price, avg DOM, YoY change.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$analytics = bmn_get_neighborhood_analytics();
?>

<section class="py-12 md:py-16 lg:py-20 bg-white" aria-labelledby="analytics-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="analytics-title" class="section-title">Neighborhood Market Data</h2>
            <p class="section-subtitle mx-auto">Real-time market insights for popular neighborhoods</p>
        </div>

        <?php if (!empty($analytics)) : ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($analytics as $area) : ?>
                    <div class="bg-gray-50 rounded-xl p-6 hover:shadow-md transition-shadow">
                        <h3 class="font-semibold text-gray-900 text-lg mb-4"><?php echo esc_html($area['name'] ?? ''); ?></h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wider">Active Listings</p>
                                <p class="text-xl font-bold text-navy-700 mt-1"><?php echo esc_html($area['active_count'] ?? '0'); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wider">Median Price</p>
                                <p class="text-xl font-bold text-navy-700 mt-1">
                                    <?php echo isset($area['median_price']) ? esc_html(bmn_format_price((float) $area['median_price'])) : 'N/A'; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wider">Avg Days on Market</p>
                                <p class="text-xl font-bold text-navy-700 mt-1"><?php echo esc_html($area['avg_dom'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wider">YoY Change</p>
                                <?php
                                $yoy = $area['yoy_change'] ?? null;
                                $yoy_class = $yoy > 0 ? 'text-green-600' : ($yoy < 0 ? 'text-red-600' : 'text-gray-700');
                                $yoy_prefix = $yoy > 0 ? '+' : '';
                                ?>
                                <p class="text-xl font-bold mt-1 <?php echo $yoy_class; ?>">
                                    <?php echo $yoy !== null ? esc_html($yoy_prefix . $yoy . '%') : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">Market data is being compiled. Check back soon.</p>
        <?php endif; ?>
    </div>
</section>
