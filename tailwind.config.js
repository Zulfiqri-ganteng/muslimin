/** @type {import('tailwindcss').Config} */
module.exports = {
  // Dua berkas non-tampilan menyimpan kelas warna lencana (jenis & status Surat Sekolah) sebagai teks;
  // kartu-guru.js membangun "chip" kelas lewat JavaScript (tampilan Per guru di SKBM & Koreksi).
  content: ['./app/Views/**/*.php', './app/Libraries/SuratJenis.php', './app/Models/SuratSekolahModel.php', './public/assets/js/admin/kartu-guru.js'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa',
          500: '#1e6fd6', 600: '#1b5fb8', 700: '#1a3a6b', 800: '#15315a', 900: '#0f2545',
        },
        gold: { 400: '#fcc419', 500: '#f5a623' },
      },
      fontFamily: { sans: ['Inter', 'Segoe UI', 'system-ui', 'sans-serif'] },
    },
  },
  plugins: [],
};
