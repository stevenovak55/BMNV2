/**
 * Auth Alpine.js Component
 *
 * Handles login, signup, and forgot-password forms.
 * Stores JWT token in localStorage for authenticated API calls.
 */

declare const bmnTheme: {
  authApiUrl: string;
  homeUrl: string;
  dashboardUrl: string;
  loginUrl: string;
};

interface AuthFormData {
  mode: 'login' | 'register' | 'forgot';
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  phone: string;
  loading: boolean;
  error: string;
  fieldErrors: Record<string, string>;
  success: string;
  submit(): Promise<void>;
  validate(): boolean;
  clearErrors(): void;
}

export function authFormComponent(
  initialMode: 'login' | 'register' = 'login'
): () => AuthFormData {
  return () => ({
    mode: initialMode,
    firstName: '',
    lastName: '',
    email: '',
    password: '',
    phone: '',
    loading: false,
    error: '',
    fieldErrors: {},
    success: '',

    clearErrors() {
      this.error = '';
      this.fieldErrors = {};
      this.success = '';
    },

    validate(): boolean {
      this.clearErrors();
      const errors: Record<string, string> = {};

      if (!this.email.trim()) {
        errors.email = 'Email is required';
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
        errors.email = 'Please enter a valid email';
      }

      if (this.mode !== 'forgot') {
        if (!this.password) {
          errors.password = 'Password is required';
        } else if (this.mode === 'register' && this.password.length < 8) {
          errors.password = 'Password must be at least 8 characters';
        }
      }

      if (this.mode === 'register') {
        if (!this.firstName.trim()) {
          errors.firstName = 'First name is required';
        }
        if (!this.lastName.trim()) {
          errors.lastName = 'Last name is required';
        }
      }

      this.fieldErrors = errors;
      return Object.keys(errors).length === 0;
    },

    async submit() {
      if (!this.validate()) return;

      this.loading = true;
      this.error = '';
      this.success = '';

      try {
        let endpoint = '';
        let body: Record<string, string> = {};

        if (this.mode === 'login') {
          endpoint = `${bmnTheme.authApiUrl}/login`;
          body = { email: this.email, password: this.password };
        } else if (this.mode === 'register') {
          endpoint = `${bmnTheme.authApiUrl}/register`;
          body = {
            first_name: this.firstName,
            last_name: this.lastName,
            email: this.email,
            password: this.password,
          };
          if (this.phone.trim()) {
            body.phone = this.phone;
          }
        } else if (this.mode === 'forgot') {
          endpoint = `${bmnTheme.authApiUrl}/forgot-password`;
          body = { email: this.email };
        }

        const response = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });

        const data = await response.json();

        if (!response.ok) {
          this.error = data.message || 'Something went wrong. Please try again.';
          return;
        }

        if (this.mode === 'forgot') {
          this.success =
            'If an account exists with that email, you will receive a password reset link.';
          return;
        }

        // Store token and redirect
        const token = data.data?.token || data.token;
        if (token) {
          localStorage.setItem('bmn_token', token);
        }

        window.location.href = bmnTheme.dashboardUrl;
      } catch {
        this.error = 'Network error. Please check your connection and try again.';
      } finally {
        this.loading = false;
      }
    },
  });
}
