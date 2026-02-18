<?php
/**
 * Contact Info Sidebar
 *
 * Agent contact info, office address, and social links.
 * All data from get_theme_mod() — same source as footer.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$agent_name     = get_theme_mod('bne_agent_name', 'Steven Novak');
$agent_email    = get_theme_mod('bne_agent_email', 'mail@steve-novak.com');
$phone_number   = get_theme_mod('bne_phone_number', '(617) 955-2224');
$brokerage_name = get_theme_mod('bne_brokerage_name', 'Douglas Elliman Real Estate');
$office_address = get_theme_mod('bne_office_address', '1 International Place, Suite 2400, Boston, MA 02110');
$instagram      = get_theme_mod('bne_social_instagram', '');
$facebook       = get_theme_mod('bne_social_facebook', '');
$youtube        = get_theme_mod('bne_social_youtube', '');
$linkedin       = get_theme_mod('bne_social_linkedin', '');
?>

<div class="space-y-6">
    <!-- Agent Info -->
    <div class="bg-navy-50 rounded-xl p-6">
        <h3 class="font-semibold text-gray-900 text-lg mb-4">Get in Touch</h3>

        <div class="space-y-3">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-navy-700 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <div>
                    <p class="font-medium text-gray-900"><?php echo esc_html($agent_name); ?></p>
                    <p class="text-sm text-gray-600"><?php echo esc_html($brokerage_name); ?></p>
                </div>
            </div>

            <?php if ($phone_number) : ?>
                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9]/', '', $phone_number)); ?>"
                   class="flex items-center gap-3 text-gray-700 hover:text-navy-700 transition-colors">
                    <svg class="w-5 h-5 text-navy-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span class="text-sm"><?php echo esc_html($phone_number); ?></span>
                </a>
            <?php endif; ?>

            <?php if ($agent_email) : ?>
                <a href="mailto:<?php echo esc_attr($agent_email); ?>"
                   class="flex items-center gap-3 text-gray-700 hover:text-navy-700 transition-colors">
                    <svg class="w-5 h-5 text-navy-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm"><?php echo esc_html($agent_email); ?></span>
                </a>
            <?php endif; ?>

            <?php if ($office_address) : ?>
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-navy-700 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-sm text-gray-700"><?php echo esc_html($office_address); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Social Links -->
    <?php if ($instagram || $facebook || $youtube || $linkedin) : ?>
        <div>
            <h3 class="font-semibold text-gray-900 mb-3">Follow Us</h3>
            <div class="flex gap-3">
                <?php if ($instagram) : ?>
                    <a href="<?php echo esc_url($instagram); ?>" target="_blank" rel="noopener noreferrer"
                       class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-600 hover:bg-navy-700 hover:text-white transition-colors"
                       aria-label="Instagram">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($facebook) : ?>
                    <a href="<?php echo esc_url($facebook); ?>" target="_blank" rel="noopener noreferrer"
                       class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-600 hover:bg-navy-700 hover:text-white transition-colors"
                       aria-label="Facebook">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($youtube) : ?>
                    <a href="<?php echo esc_url($youtube); ?>" target="_blank" rel="noopener noreferrer"
                       class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-600 hover:bg-navy-700 hover:text-white transition-colors"
                       aria-label="YouTube">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($linkedin) : ?>
                    <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer"
                       class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-600 hover:bg-navy-700 hover:text-white transition-colors"
                       aria-label="LinkedIn">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Map Placeholder -->
    <div class="bg-gray-100 rounded-xl h-48 flex items-center justify-center">
        <div class="text-center text-gray-400">
            <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="text-sm">Boston, MA</p>
        </div>
    </div>
</div>
