<div class="auth-layout">
    <section class="auth-intro"><span class="eyebrow">CREATOR ACCESS</span><h1><?= $mode === 'register' ? 'Great quizzes.<br><span class="text-acid">Terrible distractions.</span>' : 'Back for<br><span class="text-acid">more chaos?</span>' ?></h1><p class="muted">Satu akun untuk membuat quiz, mengatur kekacauan, dan melihat siapa yang berhasil bertahan.</p><div class="mini-quote">“Yakin itu jawabanmu?”<span>— QuizHell</span></div></section>
    <section class="panel auth-card">
        <span class="badge mild">LET’S GET STARTED</span><h2><?= $mode === 'register' ? 'Buat akun creator' : 'Login creator' ?></h2><p class="muted mb-6"><?= $mode === 'register' ? 'Quiz pertama kamu dimulai di sini.' : 'Quiz dan hasil playermu sudah menunggu.' ?></p>
        <?php if (!empty($error)): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form action="<?= e(url($mode)) ?>" method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>Email<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($email ?? '') ?>" placeholder="kamu@example.com"></label>
            <label>Password<input type="password" name="password" minlength="8" maxlength="72" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" required placeholder="Minimal 8 karakter"></label>
            <button class="btn btn-primary w-full" type="submit"><?= $mode === 'register' ? 'Buat akun' : 'Masuk' ?> <span>↗</span></button>
        </form>
        <p class="text-sm muted mt-6"><?= $mode === 'register' ? 'Sudah punya akun?' : 'Belum punya akun?' ?> <a class="text-acid" href="<?= e(url($mode === 'register' ? 'login' : 'register')) ?>"><?= $mode === 'register' ? 'Login' : 'Daftar di sini' ?></a></p>
    </section>
</div>
