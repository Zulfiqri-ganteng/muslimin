<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Kesediaan Guru Mengajar') ?> &mdash; <?= esc($setting['school_name'] ?? 'Sekolah') ?></title>
    <meta name="robots" content="noindex">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            500: '#1e6fd6', 600: '#1b5fb8', 700: '#1a3a6b',
                            800: '#15315a', 900: '#0f2545',
                        },
                        gold: { 400: '#fcc419', 500: '#f5a623' },
                    },
                    fontFamily: { sans: ['Inter', 'Segoe UI', 'system-ui', 'sans-serif'] },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <?= $this->renderSection('content') ?>

    <footer class="py-6 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> <?= esc($setting['school_name'] ?? '') ?> &middot; Sistem Kesediaan Guru Mengajar
    </footer>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
