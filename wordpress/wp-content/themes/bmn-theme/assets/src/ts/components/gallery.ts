/**
 * Photo Gallery + Lightbox Component (Alpine.js)
 *
 * Supports keyboard navigation (Escape/Left/Right arrows),
 * touch swipe (50px threshold, same as carousel.ts), and body scroll lock.
 */

interface GalleryOptions {
  total: number;
}

export function photoGalleryComponent(options: GalleryOptions) {
  return {
    lightboxOpen: false,
    currentIndex: 0,
    total: options.total || 0,
    photos: [] as string[],
    touchStartX: 0,

    init() {
      // Collect photo URLs from server-rendered data attributes
      const container = this.$el as HTMLElement;
      const photoEls = container.querySelectorAll<HTMLElement>('[data-gallery-src]');
      this.photos = Array.from(photoEls).map(el => el.dataset.gallerySrc || '');

      if (this.photos.length) {
        this.total = this.photos.length;
      }

      // Keyboard navigation
      const handleKey = (e: KeyboardEvent) => {
        if (!this.lightboxOpen) return;
        if (e.key === 'Escape') this.closeLightbox();
        if (e.key === 'ArrowRight') this.next();
        if (e.key === 'ArrowLeft') this.prev();
      };
      document.addEventListener('keydown', handleKey);

      // Touch support on lightbox overlay
      const lightbox = container.querySelector('[data-lightbox]') as HTMLElement | null;
      if (lightbox) {
        lightbox.addEventListener('touchstart', (e: TouchEvent) => {
          this.touchStartX = e.touches[0].clientX;
        }, { passive: true });

        lightbox.addEventListener('touchend', (e: TouchEvent) => {
          const diff = this.touchStartX - e.changedTouches[0].clientX;
          if (Math.abs(diff) > 50) {
            diff > 0 ? this.next() : this.prev();
          }
        }, { passive: true });
      }
    },

    openLightbox(index: number) {
      this.currentIndex = index;
      this.lightboxOpen = true;
      document.body.style.overflow = 'hidden';
    },

    closeLightbox() {
      this.lightboxOpen = false;
      document.body.style.overflow = '';
    },

    next() {
      this.currentIndex = (this.currentIndex + 1) % this.total;
    },

    prev() {
      this.currentIndex = (this.currentIndex - 1 + this.total) % this.total;
    },

    get currentPhoto(): string {
      return this.photos[this.currentIndex] || '';
    },

    get counterText(): string {
      return `${this.currentIndex + 1} / ${this.total}`;
    },
  };
}
