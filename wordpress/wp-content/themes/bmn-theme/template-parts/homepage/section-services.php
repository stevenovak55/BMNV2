<?php
/**
 * Homepage: Our Services
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$services = array(
    array(
        'title' => 'Buyer Representation',
        'desc'  => 'Expert guidance through every step of the home buying process, from search to closing.',
        'icon'  => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    ),
    array(
        'title' => 'Seller Services',
        'desc'  => 'Strategic pricing, professional marketing, and skilled negotiation to maximize your home\'s value.',
        'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ),
    array(
        'title' => 'Market Analysis',
        'desc'  => 'Data-driven insights and comparative market analysis to help you make informed decisions.',
        'icon'  => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    ),
);
?>

<section class="py-12 md:py-16 lg:py-20 bg-white" aria-labelledby="services-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="services-title" class="section-title">Our Services</h2>
            <p class="section-subtitle mx-auto">Comprehensive real estate services tailored to your needs</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            <?php foreach ($services as $service) : ?>
                <div class="text-center p-6 lg:p-8 rounded-xl bg-gray-50 hover:bg-navy-50 transition-colors group">
                    <div class="w-14 h-14 mx-auto mb-4 flex items-center justify-center rounded-xl bg-navy-100 text-navy-700 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M<?php echo esc_attr($service['icon']); ?>"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo esc_html($service['title']); ?></h3>
                    <p class="text-sm text-gray-600 leading-relaxed"><?php echo esc_html($service['desc']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
