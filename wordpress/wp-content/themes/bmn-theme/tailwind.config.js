/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './**/*.php',
    './assets/src/**/*.ts',
  ],
  theme: {
    extend: {
      colors: {
        navy: {
          50: '#eef2f9',
          100: '#d5ddef',
          200: '#aabbdf',
          300: '#7f99cf',
          400: '#5477bf',
          500: '#3a5a9f',
          600: '#2d4780',
          700: '#1a365d',
          800: '#122548',
          900: '#0b1733',
        },
        red: {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#e53e3e',
          600: '#dc2626',
          700: '#b91c1c',
          800: '#991b1b',
          900: '#7f1d1d',
        },
        teal: {
          50: '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
        },
        beige: {
          50: '#faf8f8',
          100: '#f5eff0',
          200: '#ebe0e2',
          300: '#decdd2',
          400: '#c4b0b6',
          500: '#a8919a',
        },
      },
      fontFamily: {
        sans: ['Roboto', 'system-ui', '-apple-system', 'sans-serif'],
      },
      screens: {
        sm: '640px',
        md: '768px',
        lg: '1024px',
        xl: '1280px',
      },
      container: {
        center: true,
        padding: {
          DEFAULT: '1rem',
          sm: '1.5rem',
          lg: '2rem',
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
};
