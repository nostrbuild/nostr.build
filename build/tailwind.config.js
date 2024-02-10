/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "../**/*.php",
    "../**/*.html",
    "../**/*.js",
    "../*.php",
    "!./",
    "!./vendor/",
  ],
  theme: {},
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
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
}

