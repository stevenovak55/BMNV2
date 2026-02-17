/**
 * Lightweight Testimonial Carousel (Alpine.js, no Swiper dependency)
 *
 * Supports prev/next navigation, dot indicators, autoplay, and touch swipe.
 */

interface CarouselOptions {
  totalSlides: number;
  autoplayInterval?: number;
}

export function carouselComponent(options: CarouselOptions) {
  return {
    currentSlide: 0,
    totalSlides: options.totalSlides || 1,
    autoplayTimer: null as ReturnType<typeof setInterval> | null,
    touchStartX: 0,

    init() {
      // Autoplay every 6 seconds
      this.startAutoplay(options.autoplayInterval || 6000);

      // Touch support
      const el = this.$el as HTMLElement;
      el.addEventListener('touchstart', (e: TouchEvent) => {
        this.touchStartX = e.touches[0].clientX;
        this.stopAutoplay();
      }, { passive: true });

      el.addEventListener('touchend', (e: TouchEvent) => {
        const diff = this.touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
          diff > 0 ? this.next() : this.prev();
        }
        this.startAutoplay(options.autoplayInterval || 6000);
      }, { passive: true });
    },

    next() {
      this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
    },

    prev() {
      this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
    },

    goTo(index: number) {
      this.currentSlide = index;
    },

    startAutoplay(interval: number) {
      this.stopAutoplay();
      this.autoplayTimer = setInterval(() => this.next(), interval);
    },

    stopAutoplay() {
      if (this.autoplayTimer) {
        clearInterval(this.autoplayTimer);
        this.autoplayTimer = null;
      }
    },

    destroy() {
      this.stopAutoplay();
    },
  };
}
