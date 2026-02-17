<?php
/**
 * Homepage: Latest Blog Posts
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$blog_query = new WP_Query(array(
    'posts_per_page'      => 3,
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
));
?>

<section class="py-12 md:py-16 lg:py-20 bg-gray-50" aria-labelledby="blog-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="blog-title" class="section-title">Latest from Our Blog</h2>
            <p class="section-subtitle mx-auto">Market insights, tips, and local area guides</p>
        </div>

        <?php if ($blog_query->have_posts()) : ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
                    <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden card-hover">
                        <?php if (has_post_thumbnail()) : ?>
                            <a href="<?php the_permalink(); ?>" class="block aspect-[16/10] overflow-hidden">
                                <?php the_post_thumbnail('property-card-lg', array(
                                    'class'   => 'w-full h-full object-cover',
                                    'loading' => 'lazy',
                                )); ?>
                            </a>
                        <?php endif; ?>

                        <div class="p-5">
                            <?php $categories = get_the_category();
                            if (!empty($categories)) : ?>
                                <span class="inline-block text-xs font-medium text-navy-700 bg-navy-50 px-2.5 py-1 rounded-full mb-2">
                                    <?php echo esc_html($categories[0]->name); ?>
                                </span>
                            <?php endif; ?>

                            <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                                <a href="<?php the_permalink(); ?>" class="hover:text-navy-700 transition-colors">
                                    <?php the_title(); ?>
                                </a>
                            </h3>

                            <p class="text-sm text-gray-600 line-clamp-3 mb-3"><?php echo get_the_excerpt(); ?></p>

                            <div class="flex items-center justify-between">
                                <time class="text-xs text-gray-400" datetime="<?php echo get_the_date('c'); ?>">
                                    <?php echo get_the_date(); ?>
                                </time>
                                <a href="<?php the_permalink(); ?>" class="text-sm font-medium text-navy-700 hover:text-navy-800 transition-colors">
                                    Read More &rarr;
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php wp_reset_postdata(); ?>

            <div class="text-center mt-8">
                <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>" class="btn-secondary">
                    View All Posts
                </a>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">No blog posts yet.</p>
        <?php endif; ?>
    </div>
</section>
