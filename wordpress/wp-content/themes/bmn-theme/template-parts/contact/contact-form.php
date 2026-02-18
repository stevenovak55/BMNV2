<?php
/**
 * Contact Form Template Part
 *
 * Reusable contact form with HTMX submission.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div x-data="{ submitted: false, loading: false }">
    <form hx-post="<?php echo esc_url(admin_url('admin-ajax.php?action=bmn_contact_form')); ?>"
          hx-trigger="submit"
          hx-swap="outerHTML"
          class="space-y-5"
          x-show="!submitted">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="contact-name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" id="contact-name" name="name" required placeholder="Your full name" class="input-field">
            </div>
            <div>
                <label for="contact-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="contact-email" name="email" required placeholder="you@example.com" class="input-field">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="contact-phone" class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-gray-400">(optional)</span></label>
                <input type="tel" id="contact-phone" name="phone" placeholder="(555) 123-4567" class="input-field">
            </div>
            <div>
                <label for="contact-subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                <select id="contact-subject" name="subject" class="input-field">
                    <option value="General Inquiry">General Inquiry</option>
                    <option value="Buying a Home">Buying a Home</option>
                    <option value="Selling a Home">Selling a Home</option>
                    <option value="Market Analysis">Market Analysis</option>
                    <option value="Schedule a Tour">Schedule a Tour</option>
                </select>
            </div>
        </div>

        <div>
            <label for="contact-message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea id="contact-message" name="message" required rows="5" placeholder="How can we help you?" class="input-field"></textarea>
        </div>

        <button type="submit" class="w-full btn-primary !py-3" :disabled="loading">
            <span x-show="!loading">Send Message</span>
            <span x-show="loading" x-cloak>Sending...</span>
        </button>
    </form>

    <!-- Success State -->
    <div x-show="submitted" x-cloak class="text-center py-8">
        <svg class="w-12 h-12 text-green-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <h3 class="text-lg font-semibold text-gray-900">Message Sent!</h3>
        <p class="mt-1 text-gray-600">We'll get back to you as soon as possible.</p>
    </div>
</div>
