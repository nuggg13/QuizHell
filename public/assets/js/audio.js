// Presentation-only file playback. Missing media and autoplay rejection are silent.
export class AudioEffects {
    #pools;
    #active = new Set();

    constructor(pools = {}) {
        this.#pools = pools;
    }

    #play(category, { loop = false, waitForEnd = false } = {}) {
        const pool = this.#pools[category];
        if (!Array.isArray(pool) || pool.length === 0) return;
        const source = pool[Math.floor(Math.random() * pool.length)];
        if (typeof source !== 'string' || source === '') return;

        let audio;
        let settled = false;
        let watchdog;
        let resolveFinished;
        const finished = new Promise(resolve => { resolveFinished = resolve; });
        const cleanup = () => {
            if (settled) return;
            settled = true;
            clearInterval(watchdog);
            resolveFinished();
            if (!audio) return;
            audio.onended = null;
            audio.onerror = null;
            audio.pause();
            audio.removeAttribute('src');
            audio.load();
            this.#active.delete(audio);
        };
        try {
            audio = new Audio(source);
            audio.volume = 0.5;
            audio.loop = loop;
            audio.onended = cleanup;
            audio.onerror = cleanup;
            this.#active.add(audio);
            if (waitForEnd) {
                let lastTime = 0;
                let lastProgress = performance.now();
                // A stalled request must not trap a player. Healthy playback has no duration cap.
                watchdog = setInterval(() => {
                    if (audio.currentTime !== lastTime) {
                        lastTime = audio.currentTime;
                        lastProgress = performance.now();
                    } else if (performance.now() - lastProgress >= 12000) cleanup();
                }, 1000);
            }
            // The caller may wait for completion; other effects remain fire-and-forget.
            const playback = audio.play();
            playback?.catch(cleanup);
        } catch {
            cleanup();
        }
        return { stop: cleanup, finished };
    }

    move() { this.#play('moving_button'); }
    laugh() { return this.#play('trap_laugh', { waitForEnd: true }); }
    countdown() { return this.#play('fake_countdown', { loop: true })?.stop; }
    result(category) {
        const key = { Low: 'result_low', Mid: 'result_mid', Good: 'result_good' }[category];
        if (key) this.#play(key);
    }
}
