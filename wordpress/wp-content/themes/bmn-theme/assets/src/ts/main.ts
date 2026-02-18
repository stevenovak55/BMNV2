import Alpine from 'alpinejs';
import htmx from 'htmx.org';

// Import component modules
import { autocompleteComponent } from './components/autocomplete';
import { mortgageCalcComponent } from './components/mortgage-calc';
import { carouselComponent } from './components/carousel';
import { mobileDrawerComponent } from './components/mobile-drawer';
import { propertySearchComponent } from './components/property-search';
import { photoGalleryComponent } from './components/gallery';
import { authFormComponent } from './components/auth';
import { dashboardAppComponent } from './components/dashboard';
import { initForms } from './components/forms';

// Register Alpine.js data components
Alpine.data('autocomplete', autocompleteComponent);
Alpine.data('mortgageCalc', mortgageCalcComponent);
Alpine.data('carousel', carouselComponent);
Alpine.data('mobileDrawer', mobileDrawerComponent);
Alpine.data('filterState', propertySearchComponent);
Alpine.data('photoGallery', photoGalleryComponent);
Alpine.data('authForm', authFormComponent);
Alpine.data('dashboardApp', dashboardAppComponent);

// Make htmx available globally for inline attributes
declare global {
  interface Window {
    Alpine: typeof Alpine;
    htmx: typeof htmx;
  }
}
window.Alpine = Alpine;
window.htmx = htmx;

// Initialize Alpine.js
Alpine.start();

// Initialize form handlers
initForms();
