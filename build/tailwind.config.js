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
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
}

