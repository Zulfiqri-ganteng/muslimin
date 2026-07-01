<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin &mdash; Kesediaan Guru</title>
    <meta name="robots" content="noindex">
    <?= view('partials/favicon') ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-700 to-brand-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gold-400 text-brand-900 text-2xl font-extrabold shadow-lg">K</div>
            <h1 class="mt-4 text-white text-2xl font-bold">Panel Admin</h1>
            <p class="text-brand-200 text-sm">Sistem Kesediaan Guru Mengajar</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-7">
            <?php if (session('error')): ?>
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"><?= esc(session('error')) ?></div>
            <?php endif; ?>
            <?php if (session('success')): ?>
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700"><?= esc(session('success')) ?></div>
            <?php endif; ?>

            <form action="<?= site_url('admin/login') ?>" method="post" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Username atau Email</label>
                    <input type="text" name="login" value="<?= old('login') ?>" required autofocus
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
                           placeholder="admin">
                </div>
                <div x-data="{show:false}">
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Password</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
                           placeholder="••••••••">
                </div>
                <button type="submit" class="w-full bg-brand-700 hover:bg-brand-800 text-white font-semibold py-3 rounded-lg transition active:scale-95">
                    Masuk
                </button>
            </form>
        </div>
        <p class="text-center text-brand-200 text-xs mt-6">&copy; <?= date('Y') ?> &middot; Sistem Kesediaan Guru Mengajar</p>
    </div>
</body>
</html>
