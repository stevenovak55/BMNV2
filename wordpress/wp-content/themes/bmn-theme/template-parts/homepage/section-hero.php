<?php
/**
 * Homepage Hero Section
 *
 * Full-width hero with agent info, autocomplete search, and quick filter buttons.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$agent_name    = get_theme_mod('bne_agent_name', 'Steven Novak');
$agent_title   = get_theme_mod('bne_agent_title', 'Licensed Real Estate Salesperson');
$license_number = get_theme_mod('bne_license_number', 'MA: 9517748');
$agent_photo   = get_theme_mod('bne_agent_photo', '');
$phone_number  = get_theme_mod('bne_phone_number', '617.955.2224');
$agent_email   = get_theme_mod('bne_agent_email', 'mail@steve-novak.com');
$group_name    = get_theme_mod('bne_group_name', 'Brody Murphy Novak Group');

$instagram = get_theme_mod('bne_social_instagram', '');
$facebook  = get_theme_mod('bne_social_facebook', '');
$youtube   = get_theme_mod('bne_social_youtube', '');
$linkedin  = get_theme_mod('bne_social_linkedin', '');

$search_url = bmn_get_search_url();
?>

<section class="relative bg-gradient-to-br from-slate-900 via-navy-800 to-navy-900 text-white overflow-hidden">
    <!-- Decorative background -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-navy-700/30 via-transparent to-transparent"></div>

    <div class="relative max-w-7xl mx-auto px-4 lg:px-8 py-16 md:py-20 lg:py-24">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">

            <!-- Agent Info -->
            <div class="text-center lg:text-left">
                <?php if ($agent_photo) : ?>
                    <div class="mb-6 lg:mb-8">
                        <img src="<?php echo esc_url($agent_photo); ?>"
                             alt="<?php echo esc_attr($agent_name); ?>"
                             class="w-32 h-32 lg:w-40 lg:h-40 rounded-full object-cover mx-auto lg:mx-0 ring-4 ring-white/20 shadow-2xl">
                    </div>
                <?php endif; ?>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight"><?php echo esc_html($agent_name); ?></h1>
                <p class="mt-2 text-lg text-gray-300"><?php echo esc_html($agent_title); ?></p>
                <p class="mt-1 text-sm text-gray-400"><?php echo esc_html($license_number); ?></p>

                <?php if ($group_name) : ?>
                    <p class="mt-2 text-sm text-gray-400">
                        Member of <span class="text-white/80 font-medium"><?php echo esc_html($group_name); ?></span>
                    </p>
                <?php endif; ?>

                <!-- Contact links -->
                <div class="flex flex-wrap justify-center lg:justify-start gap-4 mt-6">
                    <?php if ($phone_number) : ?>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9]/', '', $phone_number)); ?>"
                           class="inline-flex items-center gap-2 text-sm text-gray-300 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <?php echo esc_html($phone_number); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent_email) : ?>
                        <a href="mailto:<?php echo esc_attr($agent_email); ?>"
                           class="inline-flex items-center gap-2 text-sm text-gray-300 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <?php echo esc_html($agent_email); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Social icons -->
                <div class="flex justify-center lg:justify-start gap-3 mt-5">
                    <?php foreach (array('instagram' => $instagram, 'facebook' => $facebook, 'youtube' => $youtube, 'linkedin' => $linkedin) as $platform => $url) :
                        if (empty($url)) continue;
                    ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                           class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-gray-300 hover:bg-white/20 hover:text-white transition-colors"
                           aria-label="<?php echo esc_attr(ucfirst($platform)); ?>">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <?php if ($platform === 'instagram') : ?>
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                <?php elseif ($platform === 'facebook') : ?>
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                <?php elseif ($platform === 'youtube') : ?>
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                <?php elseif ($platform === 'linkedin') : ?>
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                <?php endif; ?>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Search Box -->
            <div class="glass-card !bg-white/10 !border-white/10"
                 x-data="autocomplete"
                 @click.outside="showSuggestions = false">
                <h2 class="text-xl font-semibold mb-4">Find Your Dream Home</h2>

                <form action="<?php echo esc_url($search_url); ?>" method="get" class="space-y-4">
                    <!-- Autocomplete search input -->
                    <div class="relative">
                        <div class="flex items-center bg-white/10 rounded-lg border border-white/20 focus-within:border-white/40 transition-colors">
                            <svg class="w-5 h-5 ml-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text"
                                   name="search"
                                   x-model="query"
                                   @input.debounce.300ms="fetchSuggestions()"
                                   @focus="query.length >= 2 && (showSuggestions = true)"
                                   @keydown.escape="showSuggestions = false"
                                   @keydown.arrow-down.prevent="highlightNext()"
                                   @keydown.arrow-up.prevent="highlightPrev()"
                                   @keydown.enter.prevent="selectHighlighted()"
                                   placeholder="Search by city, neighborhood, address, or MLS#"
                                   autocomplete="off"
                                   class="w-full bg-transparent border-0 text-white placeholder-gray-400 py-3 px-3 focus:ring-0 text-sm">
                        </div>

                        <!-- Suggestions dropdown -->
                        <div x-show="showSuggestions && suggestions.length > 0"
                             x-transition
                             class="absolute z-20 mt-2 w-full bg-white rounded-lg shadow-xl border border-gray-100 overflow-hidden max-h-80 overflow-y-auto"
                             x-cloak>
                            <template x-for="(suggestion, index) in suggestions" :key="index">
                                <button type="button"
                                        @click="selectSuggestion(suggestion)"
                                        @mouseenter="highlightedIndex = index"
                                        :class="highlightedIndex === index ? 'bg-navy-50' : ''"
                                        class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-navy-50 flex items-center gap-3 transition-colors">
                                    <span class="flex-shrink-0 w-5 h-5 text-gray-400" x-html="getSuggestionIcon(suggestion.type)"></span>
                                    <span>
                                        <span class="font-medium text-gray-900" x-text="suggestion.text"></span>
                                        <span class="text-xs text-gray-500 ml-1" x-text="suggestion.type_label"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Quick filter buttons -->
                    <div class="flex flex-wrap gap-2">
                        <a href="<?php echo esc_url(bmn_get_search_url(array('status' => 'Active'))); ?>"
                           class="px-3 py-1.5 text-xs font-medium bg-white/10 text-white/90 rounded-full hover:bg-white/20 transition-colors">
                            All Active
                        </a>
                        <a href="<?php echo esc_url(bmn_get_search_url(array('new_listing_days' => '7'))); ?>"
                           class="px-3 py-1.5 text-xs font-medium bg-white/10 text-white/90 rounded-full hover:bg-white/20 transition-colors">
                            New This Week
                        </a>
                        <a href="<?php echo esc_url(bmn_get_search_url(array('price_reduced' => '1'))); ?>"
                           class="px-3 py-1.5 text-xs font-medium bg-white/10 text-white/90 rounded-full hover:bg-white/20 transition-colors">
                            Price Reduced
                        </a>
                        <a href="<?php echo esc_url(bmn_get_search_url(array('school_grade' => 'A'))); ?>"
                           class="px-3 py-1.5 text-xs font-medium bg-white/10 text-white/90 rounded-full hover:bg-white/20 transition-colors">
                            A+ Schools
                        </a>
                    </div>

                    <button type="submit" class="w-full btn-red !py-3">
                        Search Properties
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
