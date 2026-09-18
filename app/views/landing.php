<section class="landing-layout">
    <div>
        <span class="eyebrow">YOU’VE BEEN CHALLENGED</span>

        <h1>
            <?= e($quiz['title']) ?>
            <span class="text-acid">.</span>
        </h1>

        <div class="quiz-facts">
            <div>
                <strong><?= $questionCount ?></strong>
                <span>pertanyaan</span>
            </div>

            <div>
                <strong>
                    <?= $quiz['timer_minutes']
                        ? (int) $quiz['timer_minutes'] . 'm'
                        : 'OFF'
                    ?>
                </strong>
                <span>timer</span>
            </div>

            <div>
                <strong class="<?= e($quiz['rage_level']) ?>-text">
                    <?= e(ucfirst($quiz['rage_level'])) ?>
                </strong>
                <span>rage level</span>
            </div>
        </div>

        <div class="note-box">
            <span>✳</span>
            <p>
                Akan ada kejutan di sepanjang quiz.
                Tetap tenang, pilih jawabanmu, dan lanjutkan sampai selesai.
            </p>
        </div>
    </div>

    <section class="panel auth-card">
        <span class="badge <?= e($quiz['rage_level']) ?>">
            <?= e(strtoupper($quiz['rage_level'])) ?> MODE
        </span>

        <h2>Siap masuk?</h2>

        <p class="muted mb-6">
            Cukup nickname. Ego ditanggung sendiri.
        </p>

        <?php if (!empty($error)): ?>
            <div class="notice error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(url('start')) ?>"
            class="form-stack"
        >
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="quiz"
                value="<?= e($quiz['public_id']) ?>"
            >

            <label>
                Nickname

                <input
                    name="nickname"
                    maxlength="60"
                    required
                    autocomplete="nickname"
                    placeholder="Nama yang akan dikenang"
                >
            </label>

            <button
                class="btn btn-primary w-full"
                type="submit"
            >
                Mulai quiz <span>→</span>
            </button>

            <p class="text-xs muted">
                Jawaban wajib diisi sebelum lanjut.

                <?= $quiz['timer_minutes']
                    ? 'Timer dimulai setelah menekan Mulai quiz dan tetap berjalan selama efek berlangsung.'
                    : 'Tanpa timer. Nikmati kekacauannya.'
                ?>
            </p>
        </form>
    </section>
</section>