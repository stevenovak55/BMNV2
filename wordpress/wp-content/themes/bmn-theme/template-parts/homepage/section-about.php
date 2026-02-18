<?php
/**
 * Homepage: About Section
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$agent_name = get_theme_mod('bne_agent_name', 'Steven Novak');
?>

<section class="py-12 md:py-16 lg:py-20 bg-white" aria-labelledby="about-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-10">
            <h2 id="about-title" class="section-title">Why Work With Us</h2>
            <p class="section-subtitle mx-auto">
                With deep local knowledge and a client-first approach, we help you navigate the Greater Boston
                real estate market with confidence.
            </p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <?php
            $stats = array(
                array('number' => '500+', 'label' => 'Properties Sold', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'),
                array('number' => '15+', 'label' => 'Years of Experience', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'),
                array('number' => '#1', 'label' => 'Team in Boston', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'),
            );
            foreach ($stats as $stat) : ?>
                <div class="text-center p-6 bg-navy-50 rounded-xl">
                    <svg class="w-8 h-8 text-navy-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr($stat['icon']); ?>"/>
                    </svg>
                    <p class="text-3xl font-bold text-navy-700"><?php echo esc_html($stat['number']); ?></p>
                    <p class="text-sm text-gray-600 mt-1"><?php echo esc_html($stat['label']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center">
            <a href="<?php echo esc_url(bmn_get_contact_url()); ?>" class="btn-primary">
                Get in Touch
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>
