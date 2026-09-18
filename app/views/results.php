<?php
$resultCount = count($stats['results']);
$categoryCounts = array_fill_keys(['Low', 'Mid', 'Good'], 0);
foreach ($stats['results'] as $result) $categoryCounts[$result['category']]++;
$average = $stats['average_score'] !== null ? (float) $stats['average_score'] : null;
$completion = (float) $stats['completion_rate'];
?>
<div class="results-bento">
    <a class="back-link" href="<?= e(url('dashboard')) ?>">← Dashboard</a>
    <div class="page-heading results-heading"><div><span class="eyebrow">RESULTS & ANALYTICS</span><h1><?= e($quiz['title']) ?></h1><p class="muted">Lihat siapa yang bertahan dan soal yang paling menantang.</p></div><div class="actions results-actions"><a class="btn btn-secondary" href="<?= e(url('builder', ['id' => $quiz['id']])) ?>">Edit quiz ↗</a><a class="btn btn-primary" href="<?= e(url('results/export', ['id' => $quiz['id']])) ?>">Export Excel ↓</a></div></div>

    <section class="panel results-overview" aria-labelledby="results-overview-title">
        <div class="results-panel-heading"><h2 id="results-overview-title">Ringkasan hasil</h2><span class="results-period">Semua attempt</span></div>
        <div class="results-overview-grid">
            <div class="results-participants"><span class="results-label">Total participants</span><strong class="results-big-number"><?= number_format((int) $stats['participants'], 0, ',', '.') ?></strong><span class="muted results-note">attempt selesai</span><div class="results-starts"><span>Attempt dimulai</span><strong><?= number_format((int) $stats['starts'], 0, ',', '.') ?></strong></div></div>
            <div class="results-average"><div class="results-average-heading"><span class="results-label">Average score</span><span class="results-spark" aria-hidden="true">✳</span></div><div class="results-average-number"><strong><?= $average !== null ? number_format($average, 1) : '—' ?></strong><span>/ 10</span></div><div class="results-score-track" aria-hidden="true"><span style="width:<?= round(($average ?? 0) * 10, 2) ?>%"></span></div><div class="results-scale" aria-hidden="true"><span>0</span><span>10</span></div><p><?= $average !== null ? 'Rata-rata dari attempt selesai.' : 'Belum ada skor untuk dihitung.' ?></p></div>
            <div class="results-completion"><span class="results-label">Completion rate</span><div class="results-ring" style="--completion:<?= $completion ?>%" role="img" aria-label="<?= $completion ?> persen attempt selesai"><div><strong><?= $completion ?><small>%</small></strong><span>selesai</span></div></div><p class="muted results-note"><?= (int) $stats['participants'] ?> dari <?= (int) $stats['starts'] ?> attempt</p></div>
        </div>
    </section>

    <div class="results-lower-grid">
        <section class="panel results-players" aria-labelledby="player-results-title">
            <div class="results-panel-heading"><h2 id="player-results-title">Hasil player <span class="results-count"><?= $resultCount ?></span></h2><span class="muted results-note">Attempt selesai</span></div>
            <?php if (!$stats['results']): ?>
                <div class="empty-state"><span class="empty-icon">⌁</span><h2>Panggungnya masih sepi.</h2><p class="muted">Publish dan bagikan quiz untuk mulai mengumpulkan hasil.</p></div>
            <?php else: ?>
                <div class="table-wrap results-table-wrap"><table class="results-table"><caption class="sr-only">Hasil player, diurutkan dari attempt yang selesai paling baru.</caption><thead><tr><th scope="col">Player nickname</th><th scope="col">Score</th><th scope="col">Kategori</th></tr></thead><tbody>
                <?php foreach ($stats['results'] as $result): ?>
                    <tr><td><div class="results-player-name"><span class="player-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($result['nickname'], 0, 1))) ?></span><span class="player-name"><?= e($result['nickname']) ?></span></div></td><td><div class="results-player-score"><strong><?= (int) $result['score'] ?></strong><span class="muted"> / 10</span></div><div class="results-player-track" aria-hidden="true"><span class="<?= e(Presentation::scoreClass($result['category'])) ?>" style="width:<?= (int) $result['score'] * 10 ?>%"></span></div></td><td><span class="badge <?= e(Presentation::scoreClass($result['category'])) ?>"><?= e($result['category']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
        <aside class="results-insights" aria-label="Analisis hasil quiz">
            <section class="panel results-hardest"><div class="results-panel-heading"><h2>Soal tersulit</h2><span class="results-panel-symbol text-coral" aria-hidden="true">↗</span></div><span class="eyebrow">HARDEST QUESTION</span>
                <?php if ($stats['hardest']): ?>
                    <h3><?= e($stats['hardest']['prompt']) ?></h3><div class="results-wrong-rate"><strong><?= round((float) $stats['hardest']['wrong_rate'], 1) ?><small>%</small></strong><span>jawaban salah</span></div><div class="results-wrong-track" aria-hidden="true"><span style="width:<?= round((float) $stats['hardest']['wrong_rate'], 2) ?>%"></span></div><p class="muted results-note"><?= (int) $stats['hardest']['wrong_count'] ?> dari <?= (int) $stats['hardest']['attempts'] ?> attempt selesai untuk versi soal ini.</p>
                <?php else: ?>
                    <h3>Belum ada data soal.</h3><p class="muted results-note">Analytics muncul setelah ada attempt yang selesai.</p>
                <?php endif; ?>
            </section>
            <section class="panel results-distribution"><div class="results-panel-heading"><h2>Kategori skor</h2><span class="results-panel-symbol" aria-hidden="true">⌁</span></div><div class="results-category-list">
                <?php foreach ($categoryCounts as $category => $count): ?>
                    <div class="results-category"><div><span><i class="results-dot <?= e(Presentation::scoreClass($category)) ?>" aria-hidden="true"></i><?= e($category) ?></span><strong><?= $count ?> <small>hasil</small></strong></div><div class="results-category-track" aria-hidden="true"><span class="<?= e(Presentation::scoreClass($category)) ?>" style="width:<?= $resultCount ? round($count / $resultCount * 100, 2) : 0 ?>%"></span></div></div>
                <?php endforeach; ?>
                </div><p class="muted results-note">Komposisi attempt yang sudah selesai.</p>
            </section>
        </aside>
    </div>
</div>
