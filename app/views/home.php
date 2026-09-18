<section class="hero">
    <div>
        <div class="eyebrow"><span class="live-dot"></span> WELCOME TO YOUR NEXT BAD IDEA</div>
        <h1>Test their brains.<br><span class="text-acid">Try their patience.</span></h1>
        <p class="hero-copy">Bikin quiz kamu sendiri. Bagikan link-nya. Biarkan temanmu bertarung dengan soal… dan sedikit kekacauan.</p>
        <div class="actions mt-8"><a class="btn btn-primary" href="<?= e(url(isset($_SESSION['user']) ? 'builder' : 'register')) ?>">Buat quiz pertama kamu <span>↗</span></a><a class="btn btn-ghost" href="<?= e(url('login')) ?>">Login creator</a></div>
        <p class="muted mt-5 text-sm">Player cukup pakai nickname. Tidak perlu akun.</p>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <span class="floating-tag tag-one">U sure?</span>
        <div class="mascot"><span class="horn horn-left"></span><span class="horn horn-right"></span><div class="eyes"><i></i><i></i></div><div class="grin"></div><span class="cheek">✦</span></div>
        <span class="floating-tag tag-two">100% quiz. 0% peace.</span><span class="art-spark">✳</span>
    </div>
</section>
<section class="home-strip" aria-label="Batas quiz"><span>30 soal maksimal</span><span>2 tipe pertanyaan</span><span>Timer hingga 30 menit</span><span>3 level kekacauan</span></section>
<section class="section-space">
    <div class="section-heading"><div><span class="eyebrow">PICK YOUR POISON</span><h2>Satu quiz. Tiga tingkat emosi.</h2></div><p class="muted">Skor tetap jujur.<br>Pengalamannya? Belum tentu.</p></div>
    <div class="level-grid">
        <article class="panel level-card"><span class="level-symbol text-acid">✳</span><span class="badge mild">MILD</span><h3>Sedikit usil.</h3><p>Pesan iseng, gambar random, dan loading yang terlalu dramatis.</p><span class="level-frequency">Minimal 1 event / 4 soal</span></article>
        <article class="panel level-card"><span class="level-symbol text-amber">⌁</span><span class="badge annoying">ANNOYING</span><h3>Mulai menguji sabar.</h3><p>Progress palsu, countdown jebakan, sampai tombol yang bergeser.</p><span class="level-frequency">Minimal 1 event / 2 soal</span></article>
        <article class="panel level-card hell-card"><span class="level-symbol text-coral">♨</span><span class="badge hell">HELL</span><h3>Welcome to the chaos.</h3><p>Setiap soal punya kejutan. Semoga kamu dan tombol Next baik-baik saja.</p><span class="level-frequency">Minimal 1 event / soal</span></article>
    </div>
</section>
