<!DOCTYPE html>
<html lang="id" class="tema-lilac">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Kesediaan Guru Mengajar') ?> &mdash; <?= esc($setting['school_name'] ?? 'Sekolah') ?></title>
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4e3778">
    <?= view('partials/favicon') ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <?= $this->renderSection('content') ?>

    <footer class="py-6 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> <?= esc($setting['school_name'] ?? '') ?>
        <?= view('partials/kredit') ?>
    </footer>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
