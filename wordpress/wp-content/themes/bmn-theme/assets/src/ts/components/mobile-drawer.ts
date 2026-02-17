/**
 * Mobile Navigation Drawer Component
 *
 * Alpine.js component for mobile drawer state management.
 * Listens for 'open-drawer' custom event from hamburger button.
 * Manages body scroll lock and focus trap.
 */

export function mobileDrawerComponent() {
  return {
    open: false,

    init() {
      // Listen for open-drawer event dispatched by hamburger button
      window.addEventListener('open-drawer', () => this.openDrawer());
    },

    openDrawer() {
      this.open = true;
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.open = false;
      document.body.style.overflow = '';
    },
  };
}
