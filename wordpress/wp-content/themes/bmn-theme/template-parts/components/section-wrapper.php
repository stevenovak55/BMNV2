<?php
/**
 * Section Wrapper Component
 *
 * Provides consistent padding, background, and container for homepage sections.
 *
 * Usage:
 *   $args['id']         - Section HTML id
 *   $args['class']      - Additional CSS classes
 *   $args['bg']         - Background variant: 'white' (default), 'gray', 'navy', 'beige'
 *   $args['title']      - Section title
 *   $args['subtitle']   - Section subtitle
 *   $args['narrow']     - Use narrow max-width (for forms)
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$id       = $args['id'] ?? '';
$class    = $args['class'] ?? '';
$bg       = $args['bg'] ?? 'white';
$title    = $args['title'] ?? '';
$subtitle = $args['subtitle'] ?? '';
$narrow   = $args['narrow'] ?? false;

$bg_classes = match ($bg) {
    'gray'  => 'bg-gray-50',
    'navy'  => 'bg-navy-700 text-white',
    'beige' => 'bg-beige-100',
    default => 'bg-white',
};

$container_class = $narrow ? 'max-w-4xl' : 'max-w-7xl';
?>

<section <?php echo $id ? 'id="' . esc_attr($id) . '"' : ''; ?>
         class="py-12 md:py-16 lg:py-20 <?php echo esc_attr($bg_classes . ' ' . $class); ?>"
         aria-labelledby="<?php echo $title && $id ? esc_attr($id . '-title') : ''; ?>">
    <div class="<?php echo esc_attr($container_class); ?> mx-auto px-4 lg:px-8">
        <?php if ($title) : ?>
            <div class="text-center mb-8 md:mb-12">
                <h2 <?php echo $id ? 'id="' . esc_attr($id . '-title') . '"' : ''; ?>
                    class="section-title <?php echo $bg === 'navy' ? '!text-white' : ''; ?>">
                    <?php echo esc_html($title); ?>
                </h2>
                <?php if ($subtitle) : ?>
                    <p class="section-subtitle mx-auto <?php echo $bg === 'navy' ? '!text-gray-300' : ''; ?>">
                        <?php echo esc_html($subtitle); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
