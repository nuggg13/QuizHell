<section class="play-shell">
    <div class="play-heading"><div><span class="eyebrow">THE QUIZ IS REAL. STAY FOCUSED.</span><h1 id="play-title"><?= e($state['title']) ?></h1><p class="muted text-sm mt-2">Playing as <strong><?= e($state['nickname']) ?></strong></p></div><div id="real-timer" class="timer" aria-label="Timer sebenarnya"><span>WAKTU TERSISA</span><strong id="timer-value">--:--</strong></div></div>
    <div id="play-notice" class="notice" role="status" hidden></div>
    <div class="progress-meta"><span id="question-number">Pertanyaan 1</span><span id="real-progress-label" class="muted"></span></div>
    <div class="progress-track" aria-hidden="true"><div id="display-progress" class="progress-fill"></div></div>
    <div id="rage-background" class="rage-background" aria-hidden="true"></div>
    <div class="question-layout">
        <section class="panel question-panel" id="real-question-panel"><span id="question-type" class="eyebrow"></span><h2 id="question-prompt" tabindex="-1"></h2><fieldset id="answer-options" class="answer-options"><legend class="sr-only">Pilih satu jawaban</legend></fieldset><div class="question-footer"><span id="answer-status" class="muted text-sm" role="status">Pilih satu jawaban untuk melanjutkan.</span><div class="next-button-area"><button id="next-button" type="button" class="btn btn-primary" disabled>Lanjut →</button></div></div></section>
        <div id="rage-toast" class="rage-toast" role="status" hidden></div>
    </div>
    <p class="play-bottom">✳ &nbsp; Trust your brain. Question everything else.</p>
</section>
<div id="rage-overlay" class="rage-overlay" hidden><section class="rage-dialog panel" role="dialog" aria-modal="true" aria-labelledby="rage-dialog-title"><div id="rage-content"></div><button id="dismiss-rage" class="btn btn-secondary mt-5" type="button">Lanjutkan quiz →</button></section></div>
<script type="application/json" id="play-data"><?= json_script($state) ?></script>
<script type="application/json" id="rage-config"><?= json_script($rageConfig) ?></script>
<script type="module" src="assets/js/quiz.js?v=20260912-reveals"></script>
<noscript><div class="notice error">Aktifkan JavaScript untuk mengerjakan quiz dan menjalankan Rage System.</div></noscript>
