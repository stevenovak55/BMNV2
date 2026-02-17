<?php
/**
 * Agent Card + Contact Form
 *
 * Shows assigned agent (for logged-in users) or default agent from Customizer.
 * Contact form uses HTMX POST to admin-ajax.php.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$property = $args['property'] ?? array();
$address  = $args['address'] ?? '';

// V2: Agent info comes from the property detail response
$agent_data  = $property['agent'] ?? array();
$agent_name  = $agent_data['name'] ?? '';
$agent_email = $agent_data['email'] ?? '';
$agent_phone = $agent_data['phone'] ?? '';
$agent_photo = '';
$is_assigned = false;

// Fall back to theme customizer settings
if (empty($agent_name)) {
    $agent_name  = get_theme_mod('bne_agent_name', 'Steven Novak');
    $agent_email = get_theme_mod('bne_agent_email', 'mail@steve-novak.com');
    $agent_phone = get_theme_mod('bne_phone_number', '(617) 955-2224');
    $agent_photo = get_theme_mod('bne_agent_photo', '');
}

// Use office phone as fallback
if (empty($agent_phone) && !empty($property['office']['phone'])) {
    $agent_phone = $property['office']['phone'];
}
?>

<!-- Agent Card -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    <!-- Agent Info -->
    <div class="p-5">
        <div class="flex items-center gap-3">
            <?php if ($agent_photo) : ?>
                <img src="<?php echo esc_url($agent_photo); ?>"
                     alt="<?php echo esc_attr($agent_name); ?>"
                     class="w-14 h-14 rounded-full object-cover flex-shrink-0">
            <?php else : ?>
                <div class="w-14 h-14 rounded-full bg-navy-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-navy-700 font-semibold text-lg">
                        <?php echo esc_html(mb_substr($agent_name, 0, 1)); ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="min-w-0">
                <p class="font-semibold text-gray-900"><?php echo esc_html($agent_name); ?></p>
                <?php if ($is_assigned) : ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium bg-navy-50 text-navy-700 rounded-full">Your Agent</span>
                <?php else : ?>
                    <p class="text-sm text-gray-500">Listing Agent</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Call button -->
        <?php if ($agent_phone) : ?>
            <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9]/', '', $agent_phone)); ?>"
               class="flex items-center justify-center gap-2 w-full mt-4 px-4 py-2.5 bg-navy-700 text-white text-sm font-medium rounded-lg hover:bg-navy-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Call Agent
            </a>
        <?php endif; ?>
    </div>

    <!-- Contact Form -->
    <div class="border-t border-gray-200 p-5" x-data="{ submitted: false, sending: false }">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Ask About This Property</h3>

        <!-- Success message -->
        <div x-show="submitted" x-cloak class="text-center py-4">
            <svg class="w-10 h-10 text-green-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium text-gray-900">Message Sent!</p>
            <p class="text-xs text-gray-500 mt-1">We'll get back to you shortly.</p>
        </div>

        <!-- Form -->
        <form x-show="!submitted"
              hx-post="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
              hx-trigger="submit"
              hx-swap="none"
              @htmx:after-request.camel="if(event.detail.successful) submitted = true; sending = false"
              class="space-y-3">

            <input type="hidden" name="action" value="mld_contact_form">
            <input type="hidden" name="property_address" value="<?php echo esc_attr($address); ?>">
            <input type="hidden" name="agent_email" value="<?php echo esc_attr($agent_email); ?>">

            <input type="text"
                   name="name"
                   placeholder="Your name"
                   required
                   class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">

            <input type="email"
                   name="email"
                   placeholder="Your email"
                   required
                   class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">

            <input type="tel"
                   name="phone"
                   placeholder="Your phone (optional)"
                   class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">

            <textarea name="message"
                      rows="3"
                      placeholder="I'm interested in this property..."
                      required
                      class="w-full text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500 resize-none"></textarea>

            <button type="submit"
                    :disabled="sending"
                    @click="sending = true"
                    class="w-full btn-primary text-sm !py-2.5 disabled:opacity-50">
                <span x-show="!sending">Send Message</span>
                <span x-show="sending" x-cloak>Sending...</span>
            </button>
        </form>
    </div>
</div>
