const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
  content: ["./app/**/*.{ts,tsx,jsx,js}"],
  theme: {
    extend: {
      // animation: {
      //   fade: "fade 5s ease-in-out",
      // },
      // keyframes: theme => {
      //   fade: {
      //     '0%': { hidden: theme('colors.red.300') },
      //     '100%': { backgroundColor: theme('colors.transparent') },
      //   }
      // },
      fontFamily: {
        sans: ["Source Sans Pro", ...defaultTheme.fontFamily.sans],
        serif: ["Source Serif Pro", ...defaultTheme.fontFamily.serif],
      },
    },
  },
  plugins: [],
};
