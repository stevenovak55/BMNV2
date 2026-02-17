<?php
/**
 * Property Price History
 *
 * Vertical timeline with connected dots.
 * Color coding: green=Listed, yellow=Price Change, blue=Sold.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$history = $args['history'] ?? array();
if (empty($history)) {
    return;
}

// Color map for event types
$event_colors = array(
    'Listed'       => array('bg' => 'bg-green-500', 'ring' => 'ring-green-100', 'text' => 'text-green-700', 'bg-light' => 'bg-green-50'),
    'Price Change' => array('bg' => 'bg-yellow-500', 'ring' => 'ring-yellow-100', 'text' => 'text-yellow-700', 'bg-light' => 'bg-yellow-50'),
    'Sold'         => array('bg' => 'bg-blue-500', 'ring' => 'ring-blue-100', 'text' => 'text-blue-700', 'bg-light' => 'bg-blue-50'),
);
$default_colors = array('bg' => 'bg-gray-400', 'ring' => 'ring-gray-100', 'text' => 'text-gray-700', 'bg-light' => 'bg-gray-50');
?>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Price History</h2>

    <div class="relative">
        <?php foreach ($history as $i => $event) :
            $event_type = $event['event_type'] ?? 'Unknown';
            $date       = $event['date'] ?? '';
            $price      = floatval($event['price'] ?? 0);
            $colors     = $event_colors[$event_type] ?? $default_colors;
            $is_last    = ($i === count($history) - 1);
        ?>
            <div class="flex gap-4 <?php echo !$is_last ? 'pb-6' : ''; ?>">
                <!-- Timeline dot + line -->
                <div class="relative flex flex-col items-center">
                    <div class="w-3 h-3 rounded-full <?php echo esc_attr($colors['bg']); ?> ring-4 <?php echo esc_attr($colors['ring']); ?> flex-shrink-0 mt-1"></div>
                    <?php if (!$is_last) : ?>
                        <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                    <?php endif; ?>
                </div>

                <!-- Event content -->
                <div class="flex-1 min-w-0 pb-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full <?php echo esc_attr($colors['bg-light']); ?> <?php echo esc_attr($colors['text']); ?>">
                            <?php echo esc_html($event_type); ?>
                        </span>
                        <?php if ($date) : ?>
                            <span class="text-sm text-gray-500">
                                <?php echo esc_html(date('M j, Y', strtotime($date))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($price > 0) : ?>
                        <p class="text-base font-semibold text-gray-900 mt-1">
                            <?php echo esc_html(bmn_format_price($price)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
