/**
 * Form Validation + HTMX Submit Handlers
 *
 * Adds form validation and handles HTMX responses for lead capture forms
 * (CMA request, property alerts, tour booking).
 */

export function initForms(): void {
  // Listen for HTMX events to handle form responses
  document.addEventListener('htmx:afterRequest', ((event: CustomEvent) => {
    const detail = event.detail;
    const target = detail.target as HTMLElement;

    if (!target) return;

    // Handle successful form submissions
    if (detail.successful) {
      // Find the Alpine.js component scope and set submitted = true
      const alpineEl = target.closest('[x-data]');
      if (alpineEl) {
        const component = (window as Record<string, unknown>).Alpine;
        if (component && typeof (component as Record<string, unknown>).$data === 'function') {
          // Use Alpine's reactivity system
          const data = alpineEl as unknown as { _x_dataStack?: Array<{ submitted: boolean }> };
          if (data._x_dataStack && data._x_dataStack[0]) {
            data._x_dataStack[0].submitted = true;
          }
        }
      }
    }

    // Handle errors
    if (detail.failed) {
      const errorDiv = target.querySelector('.form-error') || document.createElement('div');
      errorDiv.className = 'form-error mt-3 p-3 bg-red-50 text-red-700 text-sm rounded-lg';

      let message = 'Something went wrong. Please try again.';
      try {
        const response = JSON.parse(detail.xhr?.responseText || '{}');
        message = response.message || message;
      } catch {
        // Keep default message
      }

      errorDiv.textContent = message;
      if (!target.querySelector('.form-error')) {
        target.appendChild(errorDiv);
      }

      // Remove error after 5 seconds
      setTimeout(() => errorDiv.remove(), 5000);
    }
  }) as EventListener);

  // Client-side validation enhancement
  document.addEventListener('htmx:validation:validate', ((event: CustomEvent) => {
    const form = event.detail.elt as HTMLFormElement;
    if (!form || form.tagName !== 'FORM') return;

    // Check HTML5 validity
    if (!form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
    }
  }) as EventListener);
}
