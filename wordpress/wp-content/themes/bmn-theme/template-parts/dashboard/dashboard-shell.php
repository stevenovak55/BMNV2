<?php
/**
 * Dashboard Shell — Tab Navigation
 *
 * Renders the tab bar for the user dashboard.
 * Used by page-my-dashboard.php.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$tabs = array(
    'favorites'      => array('label' => 'Favorites',      'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'),
    'saved-searches' => array('label' => 'Saved Searches', 'icon' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'),
    'profile'        => array('label' => 'Settings',       'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'),
);
?>

<!-- Tab Navigation -->
<div class="border-b border-gray-200 mb-8">
    <nav class="flex gap-1 -mb-px overflow-x-auto" aria-label="Dashboard tabs">
        <?php foreach ($tabs as $key => $tab) : ?>
            <button @click="setTab('<?php echo esc_attr($key); ?>')"
                    :class="activeTab === '<?php echo esc_attr($key); ?>'
                        ? 'border-navy-700 text-navy-700'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr($tab['icon']); ?>"/>
                </svg>
                <?php echo esc_html($tab['label']); ?>
            </button>
        <?php endforeach; ?>
    </nav>
</div>
