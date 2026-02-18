<?php
/**
 * Search Pagination
 *
 * Previous/Next + page numbers with ellipsis.
 * Each link has both href (fallback) and hx-get/hx-target for HTMX partial rendering.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$page    = intval($args['page'] ?? 1);
$pages   = intval($args['pages'] ?? 1);
$filters = $args['filters'] ?? array();

if ($pages <= 1) {
    return;
}

// Build base URL for pagination links
$base_filters = $filters;
unset($base_filters['page'], $base_filters['paged'], $base_filters['per_page']);
$base_filters = array_filter($base_filters, function ($v) {
    return $v !== '' && $v !== null;
});
$base_url = bmn_get_search_url($base_filters);

/**
 * Build page numbers with ellipsis
 * Shows: first, last, current, and 1 page on each side of current
 */
$page_numbers = array();
for ($i = 1; $i <= $pages; $i++) {
    if (
        $i === 1 ||
        $i === $pages ||
        ($i >= $page - 1 && $i <= $page + 1)
    ) {
        $page_numbers[] = $i;
    } elseif (end($page_numbers) !== '...') {
        $page_numbers[] = '...';
    }
}
?>

<nav aria-label="Search results pagination" class="mt-8 flex items-center justify-center gap-1">
    <!-- Previous -->
    <?php if ($page > 1) :
        $prev_url = add_query_arg('paged', $page - 1, $base_url);
    ?>
        <a href="<?php echo esc_url($prev_url); ?>"
           @click.prevent="goToPage(<?php echo $page - 1; ?>)"
           hx-get="<?php echo esc_url($prev_url); ?>"
           hx-target="#results-grid"
           hx-swap="innerHTML"
           hx-push-url="true"
           class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Prev
        </a>
    <?php else : ?>
        <span class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 bg-gray-50 border border-gray-100 rounded-lg cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Prev
        </span>
    <?php endif; ?>

    <!-- Page Numbers -->
    <?php foreach ($page_numbers as $p) : ?>
        <?php if ($p === '...') : ?>
            <span class="px-2 py-2 text-sm text-gray-400">&hellip;</span>
        <?php elseif ($p === $page) : ?>
            <span class="px-3 py-2 text-sm font-semibold text-white bg-teal-600 rounded-lg min-w-[36px] text-center">
                <?php echo intval($p); ?>
            </span>
        <?php else :
            $page_url = add_query_arg('paged', $p, $base_url);
        ?>
            <a href="<?php echo esc_url($page_url); ?>"
               @click.prevent="goToPage(<?php echo intval($p); ?>)"
               hx-get="<?php echo esc_url($page_url); ?>"
               hx-target="#results-grid"
               hx-swap="innerHTML"
               hx-push-url="true"
               class="px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors min-w-[36px] text-center">
                <?php echo intval($p); ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Next -->
    <?php if ($page < $pages) :
        $next_url = add_query_arg('paged', $page + 1, $base_url);
    ?>
        <a href="<?php echo esc_url($next_url); ?>"
           @click.prevent="goToPage(<?php echo $page + 1; ?>)"
           hx-get="<?php echo esc_url($next_url); ?>"
           hx-target="#results-grid"
           hx-swap="innerHTML"
           hx-push-url="true"
           class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            Next
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    <?php else : ?>
        <span class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 bg-gray-50 border border-gray-100 rounded-lg cursor-not-allowed">
            Next
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </span>
    <?php endif; ?>
</nav>
