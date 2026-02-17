<?php
/**
 * Homepage: Featured Cities
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$cities = bmn_get_cities();
?>

<section class="py-12 md:py-16 lg:py-20 bg-white" aria-labelledby="cities-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="cities-title" class="section-title">Featured Cities</h2>
            <p class="section-subtitle mx-auto">Browse properties across the Greater Boston area</p>
        </div>

        <?php if (!empty($cities)) : ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($cities as $city) : ?>
                    <a href="<?php echo esc_url($city['url'] ?? bmn_get_search_url(array('city' => $city['name'] ?? ''))); ?>"
                       class="group bg-white rounded-xl border border-gray-100 overflow-hidden card-hover">
                        <?php if (!empty($city['image'])) : ?>
                            <div class="aspect-[16/10] overflow-hidden">
                                <img src="<?php echo esc_url($city['image']); ?>"
                                     alt="<?php echo esc_attr($city['name'] ?? ''); ?>"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                     loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 group-hover:text-navy-700 transition-colors">
                                <?php echo esc_html($city['name'] ?? ''); ?>
                            </h3>
                            <?php if (isset($city['count'])) : ?>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    <?php echo esc_html($city['count']); ?> active listings
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">No cities to display.</p>
        <?php endif; ?>
    </div>
</section>
