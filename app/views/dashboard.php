<?php
$totalQuizzes = count($quizzes);
$publishedQuizzes = count(array_filter($quizzes, fn($quiz) => $quiz['is_published']));
$draftQuizzes = $totalQuizzes - $publishedQuizzes;
$completedAttempts = array_sum(array_column($quizzes, 'participant_count'));
$publishedPercent = $totalQuizzes ? (int) round($publishedQuizzes / $totalQuizzes * 100) : 0;
$levelCounts = array_fill_keys(['mild', 'annoying', 'hell'], 0);
foreach ($quizzes as $quiz) $levelCounts[$quiz['rage_level']]++;
$popularQuizzes = $quizzes;
usort($popularQuizzes, fn($a, $b) => (int) $b['participant_count'] <=> (int) $a['participant_count']);
$popularQuizzes = array_slice($popularQuizzes, 0, 5);
$maxAttempts = max([1, ...array_column($popularQuizzes, 'participant_count')]);
?>
<div class="dashboard-bento">
    <div class="page-heading dashboard-heading">
        <div><span class="eyebrow">CREATOR DASHBOARD</span><h1>Your little chaos lab<span class="text-acid">.</span></h1><p class="muted">Buat quiz. Bagikan tantangan. Lihat hasilnya.</p></div>
        <a class="btn btn-primary" href="<?= e(url('builder')) ?>">＋ Buat quiz</a>
    </div>
    <section class="panel dashboard-overview" aria-labelledby="overview-title">
        <div class="dashboard-panel-heading"><h2 id="overview-title">Ringkasan quiz</h2><span class="dashboard-period"><span class="live-dot"></span>Semua quiz</span></div>
        <div class="dashboard-overview-grid">
            <div class="dashboard-total">
                <span class="dashboard-label">Total quiz</span><div class="dashboard-total-number"><?= $totalQuizzes ?><span>quiz</span></div>
                <div class="dashboard-mini-grid">
                    <div class="dashboard-mini"><span><i class="dashboard-dot mild" aria-hidden="true"></i>Published</span><strong><?= $publishedQuizzes ?></strong></div>
                    <div class="dashboard-mini"><span><i class="dashboard-dot draft" aria-hidden="true"></i>Draft</span><strong><?= $draftQuizzes ?></strong></div>
                </div>
            </div>
            <div class="dashboard-activity">
                <div class="dashboard-activity-heading"><div><span class="dashboard-label">Attempt selesai</span><strong class="dashboard-attempts"><?= number_format($completedAttempts, 0, ',', '.') ?></strong></div><a href="#quiz-collection" class="dashboard-round-link" aria-label="Lihat koleksi quiz">↗</a></div>
                <?php if ($completedAttempts > 0): ?>
                    <div class="dashboard-chart" aria-label="Attempt selesai pada lima quiz teratas">
                        <?php foreach ($popularQuizzes as $quiz): ?>
                            <a class="dashboard-chart-column" href="<?= e(url('results', ['id' => $quiz['id']])) ?>" title="<?= e($quiz['title']) ?>: <?= (int) $quiz['participant_count'] ?> attempt selesai" aria-label="<?= e($quiz['title']) ?>: <?= (int) $quiz['participant_count'] ?> attempt selesai, lihat hasil">
                                <span class="dashboard-bar-area"><span class="dashboard-bar" style="height:<?= round((int) $quiz['participant_count'] / $maxAttempts * 100, 2) ?>%"><span class="dashboard-bar-value"><?= (int) $quiz['participant_count'] ?></span></span></span>
                                <span class="dashboard-chart-label"><?= e($quiz['title']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <p class="dashboard-chart-caption">Attempt selesai per quiz · <?= count($popularQuizzes) ?> teratas</p>
                <?php else: ?>
                    <div class="dashboard-chart-empty"><span aria-hidden="true">↗</span><p>Belum ada attempt selesai.<small>Hasil akan muncul setelah quiz dimainkan.</small></p></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <div class="dashboard-lower-grid">
        <section class="panel dashboard-collection" id="quiz-collection" aria-labelledby="collection-title">
            <div class="dashboard-panel-heading"><h2 id="collection-title">Quiz kamu <span class="dashboard-count" id="filtered-quiz-count"><?= $totalQuizzes ?></span></h2><?php if ($quizzes): ?><button class="dashboard-filter-toggle" id="quiz-filter-toggle" type="button" aria-expanded="false" aria-controls="quiz-filters" hidden><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span id="quiz-filter-label">Filter</span></button><?php endif; ?></div>
            <?php if ($quizzes): ?>
                <div class="dashboard-filters" id="quiz-filters" hidden>
                    <fieldset class="dashboard-filter-group" data-quiz-filter="status"><legend>Status</legend><div><?php foreach (['all' => 'Semua', 'published' => 'Published', 'draft' => 'Draft'] as $value => $label): ?><button type="button" class="dashboard-filter-option" data-value="<?= e($value) ?>" aria-pressed="<?= $value === 'all' ? 'true' : 'false' ?>"><?= e($label) ?></button><?php endforeach; ?></div></fieldset>
                    <fieldset class="dashboard-filter-group" data-quiz-filter="level"><legend>Rage Level</legend><div><?php foreach (['all' => 'Semua', 'mild' => 'Mild', 'annoying' => 'Annoying', 'hell' => 'Hell'] as $value => $label): ?><button type="button" class="dashboard-filter-option" data-value="<?= e($value) ?>" aria-pressed="<?= $value === 'all' ? 'true' : 'false' ?>"><?= e($label) ?></button><?php endforeach; ?></div></fieldset>
                    <button type="button" class="dashboard-filter-reset" id="reset-quiz-filters" hidden>Reset filter</button>
                </div>
                <p class="dashboard-filter-status muted" id="quiz-filter-status" role="status" hidden></p>
            <?php endif; ?>
            <?php if (!$quizzes): ?>
                <div class="empty-state"><span class="empty-icon">✳</span><h2>Belum ada kekacauan</h2><p class="muted">Buat quiz pertamamu dan tentukan seberapa usil pengalamannya.</p><a class="btn btn-primary mt-6" href="<?= e(url('builder')) ?>">＋ Buat quiz pertama</a></div>
            <?php else: ?>
                <div class="quiz-grid dashboard-quiz-grid">
                    <?php foreach ($quizzes as $quiz): ?>
                        <article class="panel quiz-card" data-status="<?= $quiz['is_published'] ? 'published' : 'draft' ?>" data-level="<?= e($quiz['rage_level']) ?>">
                            <div class="dashboard-quiz-status"><span class="badge <?= e($quiz['rage_level']) ?>"><?= e(strtoupper($quiz['rage_level'])) ?></span><span class="status-pill <?= $quiz['is_published'] ? 'published' : '' ?>">● <?= $quiz['is_published'] ? 'Published' : 'Draft' ?></span></div>
                            <h3><?= e($quiz['title']) ?></h3><p class="muted card-description"><?= e($quiz['description'] ?: 'Belum ada deskripsi.') ?></p>
                            <div class="quiz-meta"><span><?= (int) $quiz['question_count'] ?> soal</span><span><?= $quiz['timer_minutes'] ? (int) $quiz['timer_minutes'] . ' menit' : 'Tanpa timer' ?></span><span><?= (int) $quiz['participant_count'] ?> selesai</span></div>
                            <div class="card-actions"><a class="btn btn-secondary" href="<?= e(url('builder', ['id' => $quiz['id']])) ?>">Edit quiz ↗</a><a class="btn btn-ghost" href="<?= e(url('results', ['id' => $quiz['id']])) ?>">Hasil & analytics</a></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="empty-state" id="quiz-filter-empty" hidden><h3>Tidak ada quiz yang cocok.</h3><p class="muted">Ubah status atau Rage Level untuk melihat quiz lainnya.</p></div>
            <?php endif; ?>
        </section>
        <aside class="dashboard-insights" aria-label="Komposisi quiz">
            <section class="panel dashboard-publish">
                <div class="dashboard-panel-heading"><h2>Status publish</h2><span class="dashboard-panel-symbol" aria-hidden="true">↗</span></div>
                <div class="dashboard-publish-visual">
                    <div class="dashboard-ring" style="--published:<?= $publishedPercent ?>%" role="img" aria-label="<?= $publishedQuizzes ?> dari <?= $totalQuizzes ?> quiz dipublish, <?= $publishedPercent ?> persen"><div><strong><?= $publishedPercent ?><small>%</small></strong><span>published</span></div></div>
                    <div class="dashboard-publish-legend"><span><i class="dashboard-dot mild" aria-hidden="true"></i>Published <strong><?= $publishedQuizzes ?></strong></span><span><i class="dashboard-dot draft" aria-hidden="true"></i>Draft <strong><?= $draftQuizzes ?></strong></span></div>
                </div>
                <p class="muted dashboard-footnote"><?= $totalQuizzes ? $publishedQuizzes . ' dari ' . $totalQuizzes . ' quiz siap dibagikan.' : 'Belum ada quiz untuk dipublish.' ?></p>
            </section>
            <section class="panel dashboard-rage">
                <div class="dashboard-panel-heading"><h2>Rage Level</h2><span class="dashboard-panel-symbol text-coral" aria-hidden="true">✳</span></div>
                <div class="dashboard-levels">
                    <?php foreach ($levelCounts as $level => $count): ?>
                        <div class="dashboard-level-row"><div><span><i class="dashboard-dot <?= e($level) ?>" aria-hidden="true"></i><?= e(ucfirst($level)) ?></span><strong><?= $count ?> <small>quiz</small></strong></div><div class="dashboard-level-track" aria-hidden="true"><span class="<?= e($level) ?>" style="width:<?= $totalQuizzes ? round($count / $totalQuizzes * 100, 2) : 0 ?>%"></span></div></div>
                    <?php endforeach; ?>
                </div>
                <p class="muted dashboard-footnote">Komposisi seluruh quiz kamu.</p>
            </section>
        </aside>
    </div>
</div>
<script type="module" src="assets/js/dashboard.js?v=20260913-filters"></script>
