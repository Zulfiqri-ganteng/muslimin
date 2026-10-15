// app.css (Tailwind v3) = HALAMAN PUBLIK saja: beranda, form biodata & PKL siswa, berbagi dokumen. Panel admin & halaman masuk
// memakai admin.css (Tailwind v4, resources/css/admin.css) sejak rombak tampilan F12 — view admin tidak dipindai di sini.
// Warna brand dibaca dari variabel CSS (resources/css/app.css): bawaan biru, .tema-lilac = ungu lilac.
const brand = (n) => `rgb(var(--brand-${n}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/Views/{biodata,pkl,public,partials,pdf}/**/*.php',
    './app/Views/layouts/{biodata,pkl,public}.php',
    './app/Views/pager/publik.php',
    './public/assets/js/*.js',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: brand(50), 100: brand(100), 200: brand(200), 300: brand(300), 400: brand(400),
          500: brand(500), 600: brand(600), 700: brand(700), 800: brand(800), 900: brand(900),
        },
        gold: { 400: '#fcc419', 500: '#f5a623' },
      },
      fontFamily: { sans: ['Inter', 'Segoe UI', 'system-ui', 'sans-serif'] },
    },
  },
  plugins: [],
};
