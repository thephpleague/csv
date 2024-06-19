/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./**/*.{html,js}"],
  theme: {
    extend: {
      fontFamily: {
        'onest': ['"Onest"', 'sans-serif'],
        'mono': ['"IBM Plex Mono"', 'mono', 'source-code-pro', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'Liberation Mono', 'Courier New', 'monospace']
      },
      colors: {
        'csv': {
          light: '#74D492',
          base: '#38C163',
          dark: '#278745'
        },
        'uri': {
          light: '#739AFF',
          base: '#376FFF',
          dark: '#274EB2'
        },
        'period': {
          light: '#FFDD77',
          base: '#FFC61D',
          dark: '#B28B14'
        },
        'dark': '#2C2C2C',
        'light': '#808080'
      }
    },
  },
  plugins: [],
}

