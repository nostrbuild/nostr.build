/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  safelist: [
    'z-0',
    'z-[5]',
    'z-10',
    'z-20',
    'z-30',
    'z-40',
    'z-50',
    'text-nbpurple-50',
    'text-nbpurple-100',
    'text-nbpurple-200',
    'text-nbpurple-300',
    'text-nbpurple-400',
    'size-2',
    'size-3',
    'size-4',
    'size-5',
    'size-6',
    'size-7',
    'size-8',
    'size-9',
    'size-10',
    'grid-cols-1',
    'grid-cols-2',
    'grid-cols-3',
    'grid-cols-4',
    'grid-cols-5',
    'grid-cols-6',
    'grid-cols-8',
    'grid-cols-10',
    'grid-cols-12',
    'grid-cols-16',
  ],
  content: [
    "./src/*.js",
    "./css/*.css",
    "../**/*.php",
    "../**/*.html",
    "../**/*.js",
    "../*.php",
    "!./",
    "!./vendor/",
  ],
  theme: {
    screens: {
      'xs': '390px',
      ...defaultTheme.screens,
    },
    extend: {
      gridTemplateColumns: {
        '16': 'repeat(16, minmax(0, 1fr))',
      },
      colors: {
        /* Lightness */
        "darknbpurple": {
          50: "color(display-p3 0.945 0.945 0.976 / <alpha-value>)",
          100: "color(display-p3 0.894 0.886 0.953 / <alpha-value>)",
          200: "color(display-p3 0.773 0.761 0.898 / <alpha-value>)",
          300: "color(display-p3 0.667 0.651 0.851 / <alpha-value>)",
          400: "color(display-p3 0.545 0.522 0.796 / <alpha-value>)",
          500: "color(display-p3 0.439 0.412 0.749 / <alpha-value>)",
          600: "color(display-p3 0.306 0.275 0.643 / <alpha-value>)",
          700: "color(display-p3 0.231 0.208 0.49 / <alpha-value>)",
          800: "color(display-p3 0.153 0.137 0.322 / <alpha-value>)",
          900: "color(display-p3 0.078 0.071 0.169 / <alpha-value>)",
          950: "color(display-p3 0.039 0.035 0.082 / <alpha-value>)"
        },
        /* Luminance */
        "nbpurple": {
          50: "color(display-p3 0.961 0.957 0.98 / <alpha-value>)",
          100: "color(display-p3 0.922 0.918 0.965 / <alpha-value>)",
          200: "color(display-p3 0.839 0.831 0.929 / <alpha-value>)",
          300: "color(display-p3 0.733 0.718 0.882 / <alpha-value>)",
          400: "color(display-p3 0.612 0.592 0.827 / <alpha-value>)",
          500: "color(display-p3 0.439 0.412 0.749 / <alpha-value>)",
          600: "color(display-p3 0.4 0.369 0.729 / <alpha-value>)",
          700: "color(display-p3 0.325 0.294 0.686 / <alpha-value>)",
          800: "color(display-p3 0.275 0.247 0.576 / <alpha-value>)",
          900: "color(display-p3 0.18 0.161 0.38 / <alpha-value>)",
          950: "color(display-p3 0.141 0.125 0.294 / <alpha-value>)"
        },
        "nostrpurple": {
          50: "color(display-p3 0.976 0.953 0.988 / <alpha-value>)",
          100: "color(display-p3 0.945 0.89 0.969 / <alpha-value>)",
          200: "color(display-p3 0.882 0.765 0.933 / <alpha-value>)",
          300: "color(display-p3 0.816 0.624 0.894 / <alpha-value>)",
          400: "color(display-p3 0.714 0.42 0.839 / <alpha-value>)",
          500: "color(display-p3 0.4 0.141 0.51 / <alpha-value>)",
          600: "color(display-p3 0.38 0.133 0.486 / <alpha-value>)",
          700: "color(display-p3 0.294 0.106 0.376 / <alpha-value>)",
          800: "color(display-p3 0.231 0.082 0.298 / <alpha-value>)",
          900: "color(display-p3 0.161 0.055 0.204 / <alpha-value>)",
          950: "color(display-p3 0.161 0.055 0.204 / <alpha-value>)"
        },
        "darknborange": {
          50: "color(display-p3 1 0.965 0.898 / <alpha-value>)",
          100: "color(display-p3 1 0.929 0.8 / <alpha-value>)",
          200: "color(display-p3 1 0.859 0.6 / <alpha-value>)",
          300: "color(display-p3 1 0.788 0.4 / <alpha-value>)",
          400: "color(display-p3 1 0.722 0.2 / <alpha-value>)",
          500: "color(display-p3 1 0.647 0 / <alpha-value>)",
          600: "color(display-p3 0.8 0.522 0 / <alpha-value>)",
          700: "color(display-p3 0.6 0.388 0 / <alpha-value>)",
          800: "color(display-p3 0.4 0.259 0 / <alpha-value>)",
          900: "color(display-p3 0.2 0.129 0 / <alpha-value>)",
          950: "color(display-p3 0.098 0.067 0 / <alpha-value>)"
        },
        "nborange": {
          50: "color(display-p3 1 0.973 0.922 / <alpha-value>)",
          100: "color(display-p3 1 0.953 0.859 / <alpha-value>)",
          200: "color(display-p3 1 0.886 0.678 / <alpha-value>)",
          300: "color(display-p3 1 0.824 0.502 / <alpha-value>)",
          400: "color(display-p3 1 0.749 0.278 / <alpha-value>)",
          500: "color(display-p3 1 0.647 0 / <alpha-value>)",
          600: "color(display-p3 0.902 0.584 0 / <alpha-value>)",
          700: "color(display-p3 0.8 0.522 0 / <alpha-value>)",
          800: "color(display-p3 0.659 0.427 0 / <alpha-value>)",
          900: "color(display-p3 0.478 0.314 0 / <alpha-value>)",
          950: "color(display-p3 0.341 0.22 0 / <alpha-value>)"
        }
      },
      animation: {
        shake: 'shake 0.82s cubic-bezier(.36,.07,.19,.97) both',
        wiggle: 'wiggle 1s ease-in-out infinite',
      },
      keyframes: {
        wiggle: {
          '0%, 100%': { transform: 'rotate(-3deg)' },
          '50%': { transform: 'rotate(3deg)' },
        },
        shake: {
          '10%, 90%': {
            transform: 'translate3d(-1px, 0, 0)'
          },
          '20%, 80%': {
            transform: 'translate3d(2px, 0, 0)'
          },
          '30%, 50%, 70%': {
            transform: 'translate3d(-4px, 0, 0)'
          },
          '40%, 60%': {
            transform: 'translate3d(4px, 0, 0)'
          }
        }
      }
    }
  },
  "colors": {
    "purple": {
      50: "#F9EEFB",
      100: "#F2DDF8",
      200: "#E7C0F2",
      300: "#DA9EEB",
      400: "#CD7CE3",
      500: "#C25EDD",
      600: "#AB2CCE",
      700: "#81219C",
      800: "#571669",
      900: "#2A0B33",
      950: "#150519"
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
}

