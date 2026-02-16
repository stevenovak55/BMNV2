/**
 * BMN Boston Theme — Main Entry Point
 *
 * This file is the primary TypeScript entry point for the theme.
 * Vite processes it and outputs a production bundle into assets/dist/.
 */

// Import styles so Vite can process and extract them.
import '../scss/main.scss';

/**
 * Initialise the theme's front-end behaviour.
 */
function init(): void {
    console.log('[BMN] Theme initialised.');

    // Register global event listeners, component bootstrapping, etc.
    setupNavigation();
}

/**
 * Basic responsive navigation toggle.
 */
function setupNavigation(): void {
    const nav = document.getElementById('primary-navigation');
    if (!nav) {
        return;
    }

    const toggle = nav.querySelector<HTMLButtonElement>('.menu-toggle');
    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        nav.classList.toggle('is-open', !expanded);
    });
}

// Run when the DOM is ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
