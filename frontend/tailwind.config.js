/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx}"],
  darkMode: "class",
  theme: {
    extend: {
      fontFamily: {
        sans: ["IBM Plex Sans", "Segoe UI", "sans-serif"],
        mono: ["IBM Plex Mono", "ui-monospace", "monospace"],
      },
      colors: {
        ink: {
          950: "#070b12",
          900: "#0b1220",
          800: "#111b2e",
          700: "#18263f",
        },
        cyan: {
          glow: "#3ee0c8",
        },
      },
      boxShadow: {
        glow: "0 0 40px rgba(62, 224, 200, 0.12)",
      },
    },
  },
  plugins: [],
};
