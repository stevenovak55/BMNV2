<?php
/**
 * Property Photo Gallery
 *
 * Grid layout: 1 large + 4 small photos on desktop, single photo on mobile.
 * Lightbox with Alpine.js photoGallery component.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$photos = $args['photos'] ?? array();
if (empty($photos)) {
    return;
}

$total       = count($photos);
$main_photo  = $photos[0] ?? '';
$thumbs      = array_slice($photos, 1, 4);
$remaining   = max(0, $total - 5);
?>

<div x-data="photoGallery({total: <?php echo intval($total); ?>})" class="bg-gray-100">
    <!-- Grid: 1 large + 4 thumbnails on desktop -->
    <div class="container mx-auto max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 md:grid-rows-2 gap-1 md:h-[28rem]">
            <!-- Main photo (spans 2 cols + 2 rows on desktop) -->
            <div class="md:col-span-2 md:row-span-2 relative cursor-pointer overflow-hidden"
                 @click="openLightbox(0)"
                 data-gallery-src="<?php echo esc_url($main_photo); ?>">
                <img src="<?php echo esc_url($main_photo); ?>"
                     alt="Property photo 1"
                     class="w-full h-64 md:h-full object-cover hover:scale-105 transition-transform duration-500">
            </div>

            <!-- Thumbnails (4 small) -->
            <?php foreach ($thumbs as $i => $thumb_url) :
                $thumb_index = $i + 1;
                $is_last = ($i === count($thumbs) - 1) && $remaining > 0;
            ?>
                <div class="hidden md:block relative cursor-pointer overflow-hidden"
                     @click="openLightbox(<?php echo $thumb_index; ?>)"
                     data-gallery-src="<?php echo esc_url($thumb_url); ?>">
                    <img src="<?php echo esc_url($thumb_url); ?>"
                         alt="Property photo <?php echo $thumb_index + 1; ?>"
                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                         loading="lazy">
                    <?php if ($is_last) : ?>
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span class="text-white font-semibold text-lg">+<?php echo intval($remaining); ?> photos</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Hidden photo sources for lightbox -->
            <?php for ($i = 5; $i < $total; $i++) : ?>
                <div class="hidden" data-gallery-src="<?php echo esc_url($photos[$i]); ?>"></div>
            <?php endfor; ?>
        </div>

        <!-- Mobile: "View all photos" button -->
        <?php if ($total > 1) : ?>
            <div class="md:hidden flex justify-center py-3">
                <button @click="openLightbox(0)"
                        class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    View all <?php echo intval($total); ?> photos
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox -->
    <div x-show="lightboxOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center"
         data-lightbox
         x-cloak>

        <!-- Close button -->
        <button @click="closeLightbox()"
                class="absolute top-4 right-4 z-10 p-2 text-white/70 hover:text-white transition-colors">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <!-- Counter -->
        <div class="absolute top-4 left-4 z-10 text-white/70 text-sm font-medium" x-text="counterText"></div>

        <!-- Previous button -->
        <button @click="prev()"
                class="absolute left-2 md:left-4 z-10 p-2 text-white/70 hover:text-white transition-colors">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        <!-- Image -->
        <img :src="currentPhoto"
             alt="Property photo"
             class="max-w-full max-h-[85vh] object-contain select-none"
             @click.stop>

        <!-- Next button -->
        <button @click="next()"
                class="absolute right-2 md:right-4 z-10 p-2 text-white/70 hover:text-white transition-colors">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <!-- Click outside image to close -->
        <div @click="closeLightbox()" class="absolute inset-0 -z-10"></div>
    </div>
</div>
