<!doctype html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10110f">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'QuizHell') ?> · QuizHell</title>
    <script src="assets/js/theme.js?v=20260908-theme1"></script>
    <link rel="stylesheet" href="assets/css/base.css?v=20260914-security">
    <link rel="stylesheet" href="assets/css/app.css?v=20260918-cleanup">
    <?php if ($name === 'dashboard'): ?><link rel="stylesheet" href="assets/css/dashboard.css?v=20260913-filters"><?php endif; ?>
    <?php if ($name === 'results'): ?><link rel="stylesheet" href="assets/css/results.css?v=20260913-export"><?php endif; ?>
    <script type="module" src="assets/js/common.js"></script>
</head>
<body>
    <div class="ambient" aria-hidden="true"></div>
    <header class="site-header">
        <a class="brand" href="<?= e(url('home')) ?>" aria-label="QuizHell beranda"><span class="brand-icon" aria-hidden="true">Q<span>!</span></span>Quiz<span class="text-acid">Hell</span><span class="version">v1.0</span></a>
        <nav aria-label="Navigasi utama">
            <?php if (isset($_SESSION['user'])): ?>
                <a class="nav-link" href="<?= e(url('dashboard')) ?>">Dashboard</a>
                <form action="<?= e(url('logout')) ?>" method="post"><?= csrf_field() ?><button class="nav-link" type="submit">Logout ↗</button></form>
            <?php else: ?>
                <a class="nav-link" href="<?= e(url('login')) ?>">Creator login ↗</a>
            <?php endif; ?>
            <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Aktifkan mode terang" title="Aktifkan mode terang" hidden>
                <svg class="theme-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>
                <svg class="theme-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 14a8.5 8.5 0 0 1-10.5-10.5A8.5 8.5 0 1 0 20.5 14Z"/></svg>
            </button>
        </nav>
    </header>
    <main class="page-shell">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="notice" role="status"><?= e($_SESSION['flash']) ?></div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
        <?php require __DIR__ . '/' . $name . '.php'; ?>
    </main>
    <footer class="site-footer"><span>QuizHell © <?= date('Y') ?></span><span>Real quiz. Questionable vibes.</span><span class="footer-dot">● &nbsp; Built for a little chaos</span></footer>
</body>
</html>
