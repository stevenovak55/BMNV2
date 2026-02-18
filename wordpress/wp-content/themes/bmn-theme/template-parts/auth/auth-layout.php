<?php
/**
 * Auth Layout Wrapper
 *
 * Shared centered card layout for login and signup pages.
 *
 * Expected $args:
 *   heading  - Page heading text
 *   mode     - 'login' or 'register'
 *   content  - Slot content (not used, caller includes inline)
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$heading = $args['heading'] ?? 'Welcome';
$mode    = $args['mode'] ?? 'login';
?>

<div class="min-h-[calc(100vh-5rem)] flex items-center justify-center bg-gray-50 py-12 px-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <?php if (has_custom_logo()) : ?>
                <div class="[&_img]:h-12 [&_img]:w-auto [&_img]:mx-auto">
                    <?php the_custom_logo(); ?>
                </div>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="text-2xl font-bold text-navy-700">
                    <?php bloginfo('name'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8">
            <h1 class="text-2xl font-bold text-gray-900 text-center mb-6">
                <?php echo esc_html($heading); ?>
            </h1>
