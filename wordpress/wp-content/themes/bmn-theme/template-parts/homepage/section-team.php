<?php
/**
 * Homepage: Team Section
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$team = bmn_get_featured_agents();
?>

<section class="py-12 md:py-16 lg:py-20 bg-gray-50" aria-labelledby="team-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="team-title" class="section-title">Our Team</h2>
            <p class="section-subtitle mx-auto">Meet the professionals dedicated to your real estate success</p>
        </div>

        <?php if (!empty($team)) : ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($team as $member) : ?>
                    <div class="text-center group">
                        <div class="relative w-32 h-32 mx-auto mb-4">
                            <?php if (!empty($member['photo'])) : ?>
                                <img src="<?php echo esc_url($member['photo']); ?>"
                                     alt="<?php echo esc_attr($member['name'] ?? ''); ?>"
                                     class="w-full h-full rounded-full object-cover ring-4 ring-white shadow-md group-hover:ring-navy-100 transition-all"
                                     loading="lazy">
                            <?php else : ?>
                                <div class="w-full h-full rounded-full bg-navy-100 flex items-center justify-center ring-4 ring-white shadow-md">
                                    <svg class="w-12 h-12 text-navy-300" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-semibold text-gray-900"><?php echo esc_html($member['name'] ?? ''); ?></h3>
                        <?php if (!empty($member['title'])) : ?>
                            <p class="text-sm text-gray-500 mt-0.5"><?php echo esc_html($member['title']); ?></p>
                        <?php endif; ?>
                        <div class="flex justify-center gap-2 mt-3">
                            <?php if (!empty($member['email'])) : ?>
                                <a href="mailto:<?php echo esc_attr($member['email']); ?>"
                                   class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-navy-100 hover:text-navy-700 transition-colors"
                                   aria-label="Email <?php echo esc_attr($member['name'] ?? ''); ?>">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($member['phone'])) : ?>
                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9]/', '', $member['phone'])); ?>"
                                   class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-navy-100 hover:text-navy-700 transition-colors"
                                   aria-label="Call <?php echo esc_attr($member['name'] ?? ''); ?>">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">Team information coming soon.</p>
        <?php endif; ?>
    </div>
</section>
