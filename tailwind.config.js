// Warna brand dibaca dari variabel CSS (resources/css/app.css): bawaan biru, admin = ungu lilac (.tema-lilac).
const brand = (n) => `rgb(var(--brand-${n}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
module.exports = {
  // Dua berkas non-tampilan menyimpan kelas warna lencana (jenis & status Surat Sekolah) sebagai teks;
  // kartu-guru.js membangun "chip" kelas lewat JavaScript (tampilan Per guru di SKBM & Koreksi).
  content: ['./app/Views/**/*.php', './app/Libraries/SuratJenis.php', './app/Models/SuratSekolahModel.php', './public/assets/js/admin/kartu-guru.js'],
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
