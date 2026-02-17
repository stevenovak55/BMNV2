<?php
/**
 * Homepage Template
 *
 * Loads section manager and renders enabled sections in order.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$sections = BMN_Section_Manager::get_sections();
?>

<main id="main" class="flex-1" role="main">

    <?php
    foreach ($sections as $section) {
        if (empty($section['enabled'])) {
            continue;
        }

        if ($section['type'] === 'custom') {
            if (!empty($section['html'])) {
                echo wp_kses_post($section['html']);
            }
        } elseif (!empty($section['override_html'])) {
            echo wp_kses_post($section['override_html']);
        } else {
            bmn_get_homepage_section($section['id']);
        }
    }
    ?>

</main>

<?php get_footer(); ?>
