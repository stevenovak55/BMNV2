<?php
/**
 * Default Archive/Loop Template
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main" class="flex-1" role="main">
    <div class="container mx-auto px-4 lg:px-8 py-8 lg:py-12">

        <?php if (is_archive() || is_search()) : ?>
            <header class="mb-8">
                <?php if (is_search()) : ?>
                    <h1 class="section-title">
                        <?php printf(esc_html__('Search Results for: %s', 'bmn-theme'), '<span class="text-navy-700">' . get_search_query() . '</span>'); ?>
                    </h1>
                <?php else : ?>
                    <h1 class="section-title"><?php the_archive_title(); ?></h1>
                    <?php the_archive_description('<p class="section-subtitle">', '</p>'); ?>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if (have_posts()) : ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while (have_posts()) : the_post(); ?>
                    <article <?php post_class('bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden card-hover'); ?>>
                        <?php if (has_post_thumbnail()) : ?>
                            <a href="<?php the_permalink(); ?>" class="block aspect-[16/10] overflow-hidden">
                                <?php the_post_thumbnail('property-card-lg', array(
                                    'class' => 'w-full h-full object-cover',
                                    'loading' => 'lazy',
                                )); ?>
                            </a>
                        <?php endif; ?>

                        <div class="p-5">
                            <?php
                            $categories = get_the_category();
                            if (!empty($categories)) : ?>
                                <span class="inline-block text-xs font-medium text-navy-700 bg-navy-50 px-2.5 py-1 rounded-full mb-2">
                                    <?php echo esc_html($categories[0]->name); ?>
                                </span>
                            <?php endif; ?>

                            <h2 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                                <a href="<?php the_permalink(); ?>" class="hover:text-navy-700 transition-colors">
                                    <?php the_title(); ?>
                                </a>
                            </h2>

                            <p class="text-sm text-gray-600 line-clamp-3 mb-3">
                                <?php echo get_the_excerpt(); ?>
                            </p>

                            <time class="text-xs text-gray-400" datetime="<?php echo get_the_date('c'); ?>">
                                <?php echo get_the_date(); ?>
                            </time>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <nav class="mt-10" aria-label="<?php esc_attr_e('Posts navigation', 'bmn-theme'); ?>">
                <?php
                the_posts_pagination(array(
                    'mid_size'  => 2,
                    'prev_text' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg><span class="sr-only">' . __('Previous', 'bmn-theme') . '</span>',
                    'next_text' => '<span class="sr-only">' . __('Next', 'bmn-theme') . '</span><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>',
                    'class'     => 'flex items-center justify-center gap-2 [&_.page-numbers]:px-3 [&_.page-numbers]:py-2 [&_.page-numbers]:text-sm [&_.page-numbers]:rounded-lg [&_.page-numbers]:border [&_.page-numbers]:border-gray-200 [&_.page-numbers:hover]:bg-navy-50 [&_.page-numbers:hover]:border-navy-200 [&_.current]:bg-navy-700 [&_.current]:text-white [&_.current]:border-navy-700',
                ));
                ?>
            </nav>

        <?php else : ?>
            <div class="text-center py-16">
                <svg class="mx-auto w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900 mb-2"><?php esc_html_e('Nothing Found', 'bmn-theme'); ?></h2>
                <p class="text-gray-600 mb-6"><?php esc_html_e('It seems we can\'t find what you\'re looking for.', 'bmn-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary">
                    <?php esc_html_e('Back to Home', 'bmn-theme'); ?>
                </a>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>
