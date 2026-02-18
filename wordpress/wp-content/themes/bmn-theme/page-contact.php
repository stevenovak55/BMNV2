<?php
/**
 * Template Name: Contact
 *
 * Contact page: form + agent info sidebar.
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
    <!-- Header -->
    <section class="bg-white border-b border-gray-100 py-10 md:py-14">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Contact Us</h1>
            <p class="mt-3 text-gray-600 max-w-xl mx-auto">
                Have a question about a property or the Boston market? We'd love to hear from you.
            </p>
        </div>
    </section>

    <!-- Content -->
    <section class="py-10 md:py-14">
        <div class="max-w-5xl mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 lg:gap-12">
                <!-- Form (3 cols) -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-5">Send a Message</h2>
                        <?php get_template_part('template-parts/contact/contact-form'); ?>
                    </div>
                </div>

                <!-- Sidebar (2 cols) -->
                <div class="lg:col-span-2">
                    <?php get_template_part('template-parts/contact/contact-info'); ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
