<?php
/**
 * Template Name: My Dashboard
 *
 * User dashboard: favorites, saved searches, profile/settings.
 * Requires authentication (JWT in localStorage).
 * Alpine.js dashboardApp component manages all client-side state.
 *
 * @package bmn_theme
 * @version 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main" class="flex-1 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-8 md:py-12"
         x-data="dashboardApp()"
         x-cloak>

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">My Dashboard</h1>
            <p class="mt-1 text-gray-600">Manage your favorites, saved searches, and account settings.</p>
        </div>

        <!-- Tab Navigation -->
        <?php get_template_part('template-parts/dashboard/dashboard-shell'); ?>

        <!-- Tab Content -->
        <?php get_template_part('template-parts/dashboard/tab-favorites'); ?>
        <?php get_template_part('template-parts/dashboard/tab-saved-searches'); ?>
        <?php get_template_part('template-parts/dashboard/tab-profile'); ?>
    </div>
</main>

<?php get_footer(); ?>
