<?php
/**
 * Header Template
 *
 * Sticky header with logo, primary nav, phone, user dropdown.
 * Mobile hamburger triggers Alpine.js drawer.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$phone_number = get_theme_mod('bne_phone_number', '(617) 955-2224');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="min-h-screen flex flex-col">
    <a class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded focus:shadow-lg" href="#main">
        <?php esc_html_e('Skip to content', 'bmn-theme'); ?>
    </a>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-gray-100 shadow-sm"
            x-data="{ userMenuOpen: false }">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <?php if (has_custom_logo()) : ?>
                        <div class="[&_img]:h-10 [&_img]:lg:h-12 [&_img]:w-auto">
                            <?php the_custom_logo(); ?>
                        </div>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="text-xl font-bold text-navy-700">
                            <?php bloginfo('name'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex items-center gap-1" aria-label="<?php esc_attr_e('Primary Navigation', 'bmn-theme'); ?>">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'menu_class'     => 'flex items-center gap-1',
                        'container'      => false,
                        'fallback_cb'    => 'bmn_primary_nav_fallback',
                        'depth'          => 2,
                        'link_before'    => '<span class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-navy-700 hover:bg-navy-50 rounded-lg transition-colors">',
                        'link_after'     => '</span>',
                    ));
                    ?>
                </nav>

                <!-- Right Actions -->
                <div class="flex items-center gap-3">
                    <!-- Phone (desktop) -->
                    <?php if ($phone_number) : ?>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9]/', '', $phone_number)); ?>"
                           class="hidden md:flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-navy-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <?php echo esc_html($phone_number); ?>
                        </a>
                    <?php endif; ?>

                    <!-- User Menu (desktop) -->
                    <div class="hidden lg:block relative">
                        <?php if (is_user_logged_in()) :
                            $current_user = wp_get_current_user();
                            $avatar_url = bmn_get_user_avatar_url($current_user->ID, 36);
                            $display_name = $current_user->display_name ?: $current_user->user_login;
                        ?>
                            <button @click="userMenuOpen = !userMenuOpen"
                                    @click.outside="userMenuOpen = false"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors"
                                    aria-expanded="false"
                                    :aria-expanded="userMenuOpen">
                                <img src="<?php echo esc_url($avatar_url); ?>"
                                     alt=""
                                     class="w-8 h-8 rounded-full object-cover">
                                <span class="text-sm font-medium text-gray-700"><?php echo esc_html($display_name); ?></span>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="userMenuOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Dropdown -->
                            <div x-show="userMenuOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-50"
                                 x-cloak>
                                <a href="<?php echo esc_url(home_url('/my-dashboard/')); ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                    Dashboard
                                </a>
                                <a href="<?php echo esc_url(home_url('/my-dashboard/#favorites')); ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    Favorites
                                </a>
                                <hr class="my-1 border-gray-100">
                                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Log Out
                                </a>
                            </div>
                        <?php else : ?>
                            <div class="flex items-center gap-2">
                                <a href="<?php echo esc_url(home_url('/login/')); ?>" class="text-sm font-medium text-gray-600 hover:text-navy-700 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    Log In
                                </a>
                                <a href="<?php echo esc_url(home_url('/signup/')); ?>" class="btn-primary text-sm !py-2 !px-4">
                                    Sign Up
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mobile Hamburger -->
                    <button class="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors"
                            @click="$dispatch('open-drawer')"
                            aria-label="<?php esc_attr_e('Open menu', 'bmn-theme'); ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer -->
    <div x-data="mobileDrawer" x-cloak>
        <!-- Overlay -->
        <div x-show="open"
             x-transition:enter="transition-opacity ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="close()"
             class="fixed inset-0 z-50 bg-black/50"
             aria-hidden="true"></div>

        <!-- Drawer Panel -->
        <aside x-show="open"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="-translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="-translate-x-full"
               class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] bg-white shadow-xl flex flex-col"
               role="dialog"
               aria-modal="true"
               aria-label="<?php esc_attr_e('Mobile Navigation', 'bmn-theme'); ?>"
               @keydown.escape.window="close()">

            <!-- Drawer Header -->
            <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
                <?php if (has_custom_logo()) : ?>
                    <div class="[&_img]:h-8 [&_img]:w-auto">
                        <?php the_custom_logo(); ?>
                    </div>
                <?php else : ?>
                    <span class="font-bold text-navy-700"><?php bloginfo('name'); ?></span>
                <?php endif; ?>
                <button @click="close()" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50" aria-label="<?php esc_attr_e('Close menu', 'bmn-theme'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Drawer Nav -->
            <nav class="flex-1 overflow-y-auto px-4 py-4" aria-label="<?php esc_attr_e('Mobile Menu', 'bmn-theme'); ?>">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_class'     => 'space-y-1',
                    'container'      => false,
                    'fallback_cb'    => 'bmn_mobile_nav_fallback',
                    'link_before'    => '<span class="block px-3 py-2.5 text-base font-medium text-gray-700 hover:text-navy-700 hover:bg-navy-50 rounded-lg transition-colors">',
                    'link_after'     => '</span>',
                ));
                ?>
            </nav>

            <!-- Drawer User Section -->
            <div class="border-t border-gray-100 px-4 py-4">
                <?php if (is_user_logged_in()) :
                    $drawer_user = wp_get_current_user();
                    $drawer_avatar = bmn_get_user_avatar_url($drawer_user->ID, 40);
                    $drawer_name = $drawer_user->display_name ?: $drawer_user->user_login;
                ?>
                    <div x-data="{ expanded: false }">
                        <button @click="expanded = !expanded"
                                class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg hover:bg-gray-50 transition-colors"
                                :aria-expanded="expanded">
                            <img src="<?php echo esc_url($drawer_avatar); ?>" alt="" class="w-9 h-9 rounded-full object-cover">
                            <span class="flex-1 text-left text-sm font-medium text-gray-700"><?php echo esc_html($drawer_name); ?></span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="expanded" x-collapse class="mt-1 ml-3 space-y-1">
                            <a href="<?php echo esc_url(home_url('/my-dashboard/')); ?>" class="block px-3 py-2 text-sm text-gray-600 hover:text-navy-700 rounded-lg hover:bg-gray-50">Dashboard</a>
                            <a href="<?php echo esc_url(home_url('/my-dashboard/#favorites')); ?>" class="block px-3 py-2 text-sm text-gray-600 hover:text-navy-700 rounded-lg hover:bg-gray-50">Favorites</a>
                            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="block px-3 py-2 text-sm text-gray-600 hover:text-navy-700 rounded-lg hover:bg-gray-50">Log Out</a>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="flex gap-2">
                        <a href="<?php echo esc_url(home_url('/login/')); ?>" class="flex-1 text-center px-4 py-2.5 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            Log In
                        </a>
                        <a href="<?php echo esc_url(home_url('/signup/')); ?>" class="flex-1 text-center btn-primary text-sm !py-2.5">
                            Sign Up
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
