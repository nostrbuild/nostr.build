/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  safelist: [
    'z-0',
    'z-10',
    'z-20',
    'z-30',
    'z-40',
    'z-50',
  ],
  content: [
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
      colors: {
        /* Luminance */
        /*
        "nbpurple": {
          50: "#F5F4FA",
          100: "#EEEDF7",
          200: "#E0DFF1",
          300: "#D3D1EB",
          400: "#C5C2E5",
          500: "#B4B1DD",
          600: "#A39FD6",
          700: "#8C86CB",
          800: "#7069BF",
          900: "#463F92",
          950: "#292556"
        },
        */
        /* Lightness */
        "nbpurple": {
          50: "#E4E2F3",
          100: "#DAD8EE",
          200: "#BEBBE2",
          300: "#A39FD6",
          400: "#8C86CB",
          500: "#7069BF",
          600: "#5950B4",
          700: "#494299",
          800: "#3C367D",
          900: "#302B64",
          950: "#292556"
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

