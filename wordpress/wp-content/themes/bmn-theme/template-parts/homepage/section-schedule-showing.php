<?php
/**
 * Homepage: Schedule Tour / Showing
 *
 * Tour booking form with tour type selector.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<section class="py-12 md:py-16 lg:py-20 bg-white" aria-labelledby="showing-title">
    <div class="max-w-4xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="showing-title" class="section-title">Schedule a Tour</h2>
            <p class="section-subtitle mx-auto">See a property in person or virtually — we'll arrange everything.</p>
        </div>

        <div class="glass-card"
             x-data="{
                 tourType: 'in-person',
                 submitted: false,
                 loading: false
             }">
            <form hx-post="<?php echo esc_url(rest_url('bmn/v1/leads/tour')); ?>"
                  hx-trigger="submit"
                  hx-swap="outerHTML"
                  hx-headers='{"Content-Type": "application/json"}'
                  class="space-y-5"
                  x-show="!submitted">

                <!-- Tour Type Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tour Type</label>
                    <div class="grid grid-cols-3 gap-3">
                        <?php
                        $tour_types = array(
                            'in-person' => array('label' => 'In Person', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'),
                            'video'     => array('label' => 'Video Call', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'),
                            'self'      => array('label' => 'Self-Guided', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'),
                        );
                        foreach ($tour_types as $value => $type) : ?>
                            <button type="button"
                                    @click="tourType = '<?php echo esc_attr($value); ?>'"
                                    :class="tourType === '<?php echo esc_attr($value); ?>' ? 'border-navy-700 bg-navy-50 text-navy-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
                                    class="flex flex-col items-center gap-2 p-3 rounded-lg border-2 transition-colors text-sm font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr($type['icon']); ?>"/>
                                </svg>
                                <?php echo esc_html($type['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="tour_type" :value="tourType">
                </div>

                <div>
                    <label for="tour-address" class="block text-sm font-medium text-gray-700 mb-1">Property Address or MLS #</label>
                    <input type="text" id="tour-address" name="address" required placeholder="Enter address or MLS number" class="input-field">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tour-date" class="block text-sm font-medium text-gray-700 mb-1">Preferred Date</label>
                        <input type="date" id="tour-date" name="date" required class="input-field">
                    </div>
                    <div>
                        <label for="tour-time" class="block text-sm font-medium text-gray-700 mb-1">Preferred Time</label>
                        <select id="tour-time" name="time" class="input-field">
                            <option value="morning">Morning (9-12)</option>
                            <option value="afternoon">Afternoon (12-5)</option>
                            <option value="evening">Evening (5-8)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tour-name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="tour-name" name="name" required class="input-field">
                    </div>
                    <div>
                        <label for="tour-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="tour-email" name="email" required class="input-field">
                    </div>
                </div>

                <div>
                    <label for="tour-phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" id="tour-phone" name="phone" class="input-field">
                </div>

                <button type="submit" class="w-full btn-primary !py-3" :disabled="loading">
                    <span x-show="!loading">Schedule Tour</span>
                    <span x-show="loading" x-cloak>Scheduling...</span>
                </button>
            </form>

            <div x-show="submitted" x-cloak class="text-center py-8">
                <svg class="w-12 h-12 text-green-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900">Tour Requested!</h3>
                <p class="mt-1 text-gray-600">We'll confirm your tour details shortly.</p>
            </div>
        </div>
    </div>
</section>
