<?php
/**
 * Homepage: Promotional Video
 *
 * YouTube/Vimeo/self-hosted video with autoplay on scroll and mute toggle.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$video_url = get_theme_mod('bne_promo_video_url', '');
$video_title = get_theme_mod('bne_promo_video_title', 'Discover Greater Boston');
$video_subtitle = get_theme_mod('bne_promo_video_subtitle', 'See why families choose to call this area home.');

if (empty($video_url)) {
    return;
}

// Determine embed type
$is_youtube = (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false);
$is_vimeo = (strpos($video_url, 'vimeo.com') !== false);

// Extract YouTube video ID
$youtube_id = '';
if ($is_youtube) {
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $matches)) {
        $youtube_id = $matches[1];
    }
}

// Extract Vimeo video ID
$vimeo_id = '';
if ($is_vimeo) {
    if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
        $vimeo_id = $matches[1];
    }
}
?>

<section class="py-12 md:py-16 lg:py-20 bg-gray-900" aria-labelledby="video-title"
         x-data="{ muted: true }"
         x-intersect:enter.half="$refs.videoFrame && $refs.videoFrame.contentWindow.postMessage('{&quot;event&quot;:&quot;command&quot;,&quot;func&quot;:&quot;playVideo&quot;}', '*')"
         x-intersect:leave="$refs.videoFrame && $refs.videoFrame.contentWindow.postMessage('{&quot;event&quot;:&quot;command&quot;,&quot;func&quot;:&quot;pauseVideo&quot;}', '*')">
    <div class="max-w-6xl mx-auto px-4 lg:px-8">
        <?php if ($video_title) : ?>
            <div class="text-center mb-8">
                <h2 id="video-title" class="section-title !text-white"><?php echo esc_html($video_title); ?></h2>
                <?php if ($video_subtitle) : ?>
                    <p class="section-subtitle mx-auto !text-gray-400"><?php echo esc_html($video_subtitle); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="relative rounded-2xl overflow-hidden shadow-2xl">
            <!-- Aspect ratio container -->
            <div class="relative w-full" style="padding-bottom: 56.25%;">
                <?php if ($youtube_id) : ?>
                    <iframe x-ref="videoFrame"
                            src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($youtube_id); ?>?enablejsapi=1&mute=1&autoplay=0&rel=0&modestbranding=1"
                            class="absolute inset-0 w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            loading="lazy"
                            title="<?php echo esc_attr($video_title); ?>"></iframe>
                <?php elseif ($vimeo_id) : ?>
                    <iframe x-ref="videoFrame"
                            src="https://player.vimeo.com/video/<?php echo esc_attr($vimeo_id); ?>?muted=1&autoplay=0&background=0"
                            class="absolute inset-0 w-full h-full"
                            frameborder="0"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                            loading="lazy"
                            title="<?php echo esc_attr($video_title); ?>"></iframe>
                <?php else : ?>
                    <video x-ref="videoFrame"
                           src="<?php echo esc_url($video_url); ?>"
                           class="absolute inset-0 w-full h-full object-cover"
                           muted
                           playsinline
                           loop
                           preload="metadata"></video>
                <?php endif; ?>
            </div>

            <!-- Mute toggle -->
            <button @click="muted = !muted"
                    class="absolute bottom-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors backdrop-blur-sm"
                    :aria-label="muted ? 'Unmute' : 'Mute'">
                <svg x-show="muted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                </svg>
                <svg x-show="!muted" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                </svg>
            </button>
        </div>
    </div>
</section>
