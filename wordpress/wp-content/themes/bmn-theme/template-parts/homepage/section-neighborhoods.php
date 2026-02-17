<?php
/**
 * Homepage: Featured Neighborhoods
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$neighborhoods = bmn_get_neighborhoods();
?>

<section class="py-12 md:py-16 lg:py-20 bg-gray-50" aria-labelledby="neighborhoods-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="neighborhoods-title" class="section-title">Featured Neighborhoods</h2>
            <p class="section-subtitle mx-auto">Explore Boston's most sought-after neighborhoods</p>
        </div>

        <?php if (!empty($neighborhoods)) : ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($neighborhoods as $i => $hood) : ?>
                    <a href="<?php echo esc_url($hood['url'] ?? bmn_get_search_url(array('neighborhood' => $hood['name'] ?? ''))); ?>"
                       class="group relative rounded-xl overflow-hidden <?php echo $i === 0 ? 'col-span-2 row-span-2 aspect-square' : 'aspect-[4/3]'; ?> card-hover">
                        <?php if (!empty($hood['image'])) : ?>
                            <img src="<?php echo esc_url($hood['image']); ?>"
                                 alt="<?php echo esc_attr($hood['name'] ?? ''); ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                 loading="lazy">
                        <?php else : ?>
                            <div class="w-full h-full bg-gradient-to-br from-navy-600 to-navy-800"></div>
                        <?php endif; ?>

                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>

                        <!-- Content -->
                        <div class="absolute bottom-0 left-0 right-0 p-4">
                            <h3 class="text-white font-semibold <?php echo $i === 0 ? 'text-xl' : 'text-base'; ?>">
                                <?php echo esc_html($hood['name'] ?? ''); ?>
                            </h3>
                            <?php if (isset($hood['count'])) : ?>
                                <p class="text-white/80 text-sm mt-0.5">
                                    <?php echo esc_html($hood['count']); ?> <?php echo (int)($hood['count'] ?? 0) === 1 ? 'listing' : 'listings'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">No neighborhoods to display.</p>
        <?php endif; ?>
    </div>
</section>
