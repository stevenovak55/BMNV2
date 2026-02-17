<?php
/**
 * Homepage: Client Testimonials
 *
 * Alpine.js carousel (no Swiper dependency).
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$testimonials = bmn_get_testimonials(10);
?>

<section class="py-12 md:py-16 lg:py-20 bg-beige-100" aria-labelledby="testimonials-title">
    <div class="max-w-6xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="testimonials-title" class="section-title">What Our Clients Say</h2>
            <p class="section-subtitle mx-auto">Real stories from real clients</p>
        </div>

        <?php if (!empty($testimonials)) : ?>
            <div x-data="carousel({ totalSlides: <?php echo count($testimonials); ?> })"
                 class="relative">
                <!-- Slides container -->
                <div class="overflow-hidden rounded-2xl">
                    <div class="flex transition-transform duration-500 ease-in-out"
                         :style="'transform: translateX(-' + (currentSlide * 100) + '%)'">
                        <?php foreach ($testimonials as $testimonial) : ?>
                            <div class="w-full flex-shrink-0 px-4">
                                <div class="bg-white rounded-xl shadow-sm p-8 md:p-10 max-w-2xl mx-auto">
                                    <!-- Rating stars -->
                                    <?php if (!empty($testimonial['rating'])) : ?>
                                        <div class="flex gap-1 mb-4">
                                            <?php for ($i = 1; $i <= 5; $i++) : ?>
                                                <svg class="w-5 h-5 <?php echo $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-200'; ?>"
                                                     fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Quote -->
                                    <blockquote class="text-gray-700 text-lg leading-relaxed">
                                        <svg class="w-8 h-8 text-navy-200 mb-2" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/>
                                        </svg>
                                        <?php echo wp_kses_post($testimonial['excerpt'] ?? ''); ?>
                                    </blockquote>

                                    <!-- Client -->
                                    <div class="flex items-center gap-3 mt-6">
                                        <?php if (!empty($testimonial['photo'])) : ?>
                                            <img src="<?php echo esc_url($testimonial['photo']); ?>"
                                                 alt="<?php echo esc_attr($testimonial['client_name'] ?? ''); ?>"
                                                 class="w-12 h-12 rounded-full object-cover"
                                                 loading="lazy">
                                        <?php else : ?>
                                            <div class="w-12 h-12 rounded-full bg-navy-100 flex items-center justify-center">
                                                <span class="text-navy-700 font-semibold text-lg">
                                                    <?php echo esc_html(substr($testimonial['client_name'] ?? '?', 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo esc_html($testimonial['client_name'] ?? ''); ?></p>
                                            <?php if (!empty($testimonial['location'])) : ?>
                                                <p class="text-sm text-gray-500"><?php echo esc_html($testimonial['location']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex justify-center items-center gap-4 mt-6">
                    <button @click="prev()" class="w-10 h-10 flex items-center justify-center rounded-full bg-white shadow-sm border border-gray-200 text-gray-600 hover:bg-navy-50 hover:border-navy-200 transition-colors" aria-label="Previous">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <!-- Dots -->
                    <div class="flex gap-2">
                        <?php for ($i = 0; $i < count($testimonials); $i++) : ?>
                            <button @click="goTo(<?php echo $i; ?>)"
                                    :class="currentSlide === <?php echo $i; ?> ? 'bg-navy-700 w-6' : 'bg-gray-300 w-2'"
                                    class="h-2 rounded-full transition-all duration-300"
                                    aria-label="Slide <?php echo $i + 1; ?>"></button>
                        <?php endfor; ?>
                    </div>

                    <button @click="next()" class="w-10 h-10 flex items-center justify-center rounded-full bg-white shadow-sm border border-gray-200 text-gray-600 hover:bg-navy-50 hover:border-navy-200 transition-colors" aria-label="Next">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">Testimonials coming soon.</p>
        <?php endif; ?>
    </div>
</section>
