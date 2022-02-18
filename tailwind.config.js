const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  content: ['./app/**/*.{ts,tsx,jsx,js}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Source Sans Pro', ...defaultTheme.fontFamily.sans],
        serif: ['Source Serif Pro', ...defaultTheme.fontFamily.serif]
      }
    },
  },
  plugins: [],
}
