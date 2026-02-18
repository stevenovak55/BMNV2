<?php
/**
 * Template Name: About
 *
 * About page: agent bio, stats, value propositions, brokerage info.
 * All content from get_theme_mod() with sensible defaults.
 *
 * @package bmn_theme
 * @version 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$agent_name     = get_theme_mod('bne_agent_name', 'Steven Novak');
$agent_photo    = get_theme_mod('bne_agent_photo', '');
$agent_bio      = get_theme_mod('bne_agent_bio', 'With over 15 years of experience in the Greater Boston real estate market, we pride ourselves on delivering exceptional service and results. Our deep knowledge of local neighborhoods, combined with a client-first approach, has helped hundreds of families find their perfect home. Whether you\'re buying your first property or selling a luxury estate, our team provides personalized guidance every step of the way.');
$agent_tagline  = get_theme_mod('bne_agent_tagline', 'Your Trusted Guide to Greater Boston Real Estate');
$brokerage_name = get_theme_mod('bne_brokerage_name', 'Douglas Elliman Real Estate');
$brokerage_logo = get_theme_mod('bne_brokerage_logo', '');
$phone_number   = get_theme_mod('bne_phone_number', '(617) 955-2224');

get_header();
?>

<main id="main" class="flex-1">
    <!-- Hero -->
    <section class="bg-navy-700 text-white py-16 md:py-24">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex flex-col md:flex-row items-center gap-8 md:gap-12">
                <?php if ($agent_photo) : ?>
                    <div class="flex-shrink-0">
                        <img src="<?php echo esc_url($agent_photo); ?>"
                             alt="<?php echo esc_attr($agent_name); ?>"
                             class="w-40 h-40 md:w-52 md:h-52 rounded-2xl object-cover shadow-xl">
                    </div>
                <?php endif; ?>
                <div class="text-center md:text-left">
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold">
                        <?php echo esc_html($agent_name); ?>
                    </h1>
                    <p class="mt-3 text-lg md:text-xl text-navy-200">
                        <?php echo esc_html($agent_tagline); ?>
                    </p>
                    <p class="mt-1 text-navy-300"><?php echo esc_html($brokerage_name); ?></p>
                    <div class="mt-6 flex flex-wrap gap-3 justify-center md:justify-start">
                        <a href="<?php echo esc_url(bmn_get_contact_url()); ?>" class="btn-red">
                            Get in Touch
                        </a>
                        <a href="<?php echo esc_url(bmn_get_search_url()); ?>" class="inline-flex items-center justify-center px-6 py-3 bg-white/10 text-white font-medium rounded-lg hover:bg-white/20 transition-colors">
                            Browse Properties
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="py-12 md:py-16 bg-white">
        <div class="max-w-5xl mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php
                $stats = array(
                    array('number' => '500+', 'label' => 'Properties Sold', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'),
                    array('number' => '15+',  'label' => 'Years of Experience', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'),
                    array('number' => '#1',   'label' => 'Team in Boston', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'),
                );
                foreach ($stats as $stat) : ?>
                    <div class="text-center p-6 bg-navy-50 rounded-xl">
                        <svg class="w-8 h-8 text-navy-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M<?php echo esc_attr($stat['icon']); ?>"/>
                        </svg>
                        <p class="text-3xl font-bold text-navy-700"><?php echo esc_html($stat['number']); ?></p>
                        <p class="text-sm text-gray-600 mt-1"><?php echo esc_html($stat['label']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Why Work With Us -->
    <section class="py-12 md:py-16 bg-gray-50" aria-labelledby="why-title">
        <div class="max-w-5xl mx-auto px-4 lg:px-8">
            <h2 id="why-title" class="section-title text-center mb-10">Why Work With Us</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                $values = array(
                    array(
                        'title' => 'Local Expertise',
                        'desc'  => 'Deep knowledge of every neighborhood in Greater Boston, from pricing trends to school districts to hidden gems.',
                        'icon'  => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
                    ),
                    array(
                        'title' => 'Client-First Approach',
                        'desc'  => 'Your goals drive every decision. We listen, advise honestly, and work tirelessly to achieve the best outcome for you.',
                        'icon'  => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                    ),
                    array(
                        'title' => 'Proven Results',
                        'desc'  => 'A track record of 500+ successful transactions, consistently achieving top-market prices for our clients.',
                        'icon'  => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
                    ),
                );
                foreach ($values as $value) : ?>
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                        <div class="w-12 h-12 bg-navy-50 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M<?php echo esc_attr($value['icon']); ?>"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 text-lg mb-2"><?php echo esc_html($value['title']); ?></h3>
                        <p class="text-sm text-gray-600 leading-relaxed"><?php echo esc_html($value['desc']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Bio -->
    <section class="py-12 md:py-16 bg-white">
        <div class="max-w-3xl mx-auto px-4 lg:px-8">
            <h2 class="section-title text-center mb-6">About <?php echo esc_html($agent_name); ?></h2>
            <div class="prose prose-gray max-w-none text-gray-600 leading-relaxed">
                <?php echo wp_kses_post(wpautop($agent_bio)); ?>
            </div>
        </div>
    </section>

    <!-- Brokerage + CTA -->
    <section class="py-12 md:py-16 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 lg:px-8 text-center">
            <?php if ($brokerage_logo) : ?>
                <img src="<?php echo esc_url($brokerage_logo); ?>"
                     alt="<?php echo esc_attr($brokerage_name); ?>"
                     class="h-10 w-auto mx-auto mb-4 opacity-60">
            <?php endif; ?>
            <p class="text-gray-600 mb-6">
                Proudly affiliated with <?php echo esc_html($brokerage_name); ?>.
            </p>
            <a href="<?php echo esc_url(bmn_get_contact_url()); ?>" class="btn-primary">
                Let's Work Together
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </section>
</main>

<?php get_footer(); ?>
