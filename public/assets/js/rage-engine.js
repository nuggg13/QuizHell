// This module receives presentation elements and scalar display values only.
// It cannot reach answers, the quiz controller, deadline, storage, or scoring.
export function chooseEvent(level, index, config, random = Math.random, previousEvent = null) {
    const settings = config.levels[level] || config.levels.mild;
    if (index % settings.interval !== 0) return null;
    const pool = settings.events.filter(event => event !== previousEvent && event !== 'progress');
    return pool.length ? pool[Math.min(pool.length - 1, Math.floor(random() * pool.length))] : null;
}

export class RageEngine {
    #ui;
    #config;
    #audio;
    #timeouts = new Set();
    #cleanups = [];
    #resolveOverlay = null;
    #event = null;
    #currentKey = null;
    #assignments = new Map();
    #lastEvent = null;
    #previousFocus = null;
    #overlayMode = null;
    #dismissLabel;

    constructor(ui, config, audio) {
        this.#ui = ui;
        this.#config = config;
        this.#audio = audio;
        this.#dismissLabel = ui.dismiss.textContent;
        ui.dismiss.addEventListener('click', () => {
            if (this.#resolveOverlay && this.#overlayMode !== 'trap') this.#closeOverlay();
        });
        ui.overlay.addEventListener('keydown', event => {
            if (!this.#resolveOverlay) return;
            if (event.key === 'Tab') {
                const buttons = [...ui.overlay.querySelectorAll('button:not(:disabled)')]
                    .filter(button => !button.hidden && button.getClientRects().length);
                const first = buttons[0], last = buttons[buttons.length - 1];
                if (!first) event.preventDefault();
                else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            }
        });
    }

    #later(callback, delay) {
        const id = setTimeout(() => { this.#timeouts.delete(id); callback(); }, delay);
        this.#timeouts.add(id);
    }

    #pick(pool) { return Array.isArray(pool) && pool.length ? pool[Math.floor(Math.random() * pool.length)] : null; }

    reset() {
        this.#timeouts.forEach(clearTimeout);
        this.#timeouts.clear();
        this.#cleanups.forEach(cleanup => cleanup());
        this.#cleanups = [];
        this.#closeOverlay();
        this.#ui.toast.hidden = true;
        this.#ui.toast.classList.remove('rage-countdown', 'revealed');
        this.#ui.background.style.backgroundImage = '';
        this.#ui.next.style.transform = '';
        this.#event = null;
        this.#currentKey = null;
    }

    enter({ key, level, index, progress, remainingSeconds }) {
        // Answer saves/polling may render the same question; keep its effects alive.
        this.#ui.progress.style.width = `${progress}%`;
        if (this.#currentKey === key) return;
        this.reset();
        this.#currentKey = key;
        if (!this.#assignments.has(key)) {
            const event = chooseEvent(level, index, this.#config, Math.random, this.#lastEvent);
            this.#assignments.set(key, event);
            if (event) this.#lastEvent = event;
        }
        this.#event = this.#assignments.get(key);
        switch (this.#event) {
            case 'countdown': this.#countdown(remainingSeconds); break;
            case 'moving': this.#moving(); break;
            case 'background': this.#background(); break;
        }
    }

    async beforeTransition() {
        if (this.#event === 'taunt') await this.#taunt();
        if (this.#event === 'loading') await this.#loading();
        if (this.#event === 'trap') await this.#trap();
    }

    #toast(message) {
        this.#ui.toast.textContent = message;
        this.#ui.toast.hidden = false;
        this.#later(() => { this.#ui.toast.hidden = true; }, 4200);
    }

    #countdown(remainingSeconds) {
        const toast = this.#ui.toast;
        const duration = this.#pick([3, 5, 10]);
        const format = seconds => `00:${String(seconds).padStart(2, '0')}`;
        const title = this.#element('p', 'WAKTUMU HABIS DALAM…', 'countdown-title');
        const digits = this.#element('strong', format(duration), 'countdown-digits');
        const caption = this.#element('p', 'CEPAT, TENTUKAN JAWABANMU!', 'countdown-caption');
        toast.replaceChildren(title, digits, caption);
        toast.classList.add('rage-countdown');
        toast.hidden = false;
        document.body.classList.add('rage-countdown-active');
        const stopSound = this.#audio.countdown?.();
        let active = true;
        const stop = () => {
            if (!active) return;
            active = false;
            document.body.classList.remove('rage-countdown-active');
            if (typeof stopSound === 'function') stopSound();
        };
        this.#cleanups.push(stop);
        for (let elapsed = 1; elapsed <= duration; elapsed++) {
            this.#later(() => { digits.textContent = format(duration - elapsed); }, elapsed * 1000);
        }
        this.#later(() => {
            stop();
            toast.classList.add('revealed');
            const reveal = this.#pick(this.#config.countdown_reveals);
            title.textContent = reveal?.title || 'chill, itu cuma prank ✌️';
            digits.textContent = reveal?.text || 'GOTCHA.';
            caption.textContent = remainingSeconds === null ? 'Timer quiz ini OFF.' : 'Lihat timer asli di atas. Waktumu tetap berjalan.';
        }, duration * 1000 + 400);
        this.#later(() => { toast.hidden = true; }, duration * 1000 + 3500);
    }

    #moving() {
        const button = this.#ui.next;
        const originalStyle = button.getAttribute('style');
        const anchor = document.createComment('next-button-home');
        const placeholder = document.createElement('span');
        placeholder.setAttribute('aria-hidden', 'true');
        let detached = false;
        let dodges = 0;
        let skipClick = false;
        const limit = 3;
        const visited = new Set();
        const detach = rect => {
            if (detached) return;
            detached = true;
            placeholder.style.cssText = `display:inline-block;width:${rect.width}px;height:${rect.height}px`;
            button.before(anchor, placeholder);
            document.body.append(button);
            button.classList.add('rage-fleeing-button');
            button.style.width = `${rect.width}px`;
            button.style.height = `${rect.height}px`;
        };
        const move = event => {
            if (button.disabled || dodges >= limit) return false;
            const rect = button.getBoundingClientRect();
            // Attach to body: ancestor transforms/overflow cannot trap this button.
            detach(rect);
            const left = 16;
            const right = Math.max(left, innerWidth - 16 - rect.width);
            const top = 16;
            const bottom = Math.max(top, innerHeight - 16 - rect.height);
            const middle = (top + bottom) / 2;
            const candidates = [[left, top], [right, top], [left, bottom], [right, bottom], [left, middle], [right, middle]];
            const distance = ([x, y]) => Math.hypot(x - rect.left, y - rect.top);
            const minimumDistance = Math.min(240, Math.max(...candidates.map(distance)) * 0.5);
            // Randomize across all six regions, while keeping the landing away from the pointer.
            const safe = candidates.filter(([x, y]) => distance([x, y]) >= minimumDistance
                && !(event.clientX >= x - 48 && event.clientX <= x + rect.width + 48
                    && event.clientY >= y - 48 && event.clientY <= y + rect.height + 48));
            const fresh = safe.filter(position => !visited.has(candidates.indexOf(position)));
            const destination = this.#pick(fresh.length ? fresh : safe)
                || candidates.reduce((best, point) => distance(point) > distance(best) ? point : best);
            visited.add(candidates.indexOf(destination));
            const [x, y] = destination;
            dodges++;
            button.style.left = `${x}px`;
            button.style.top = `${y}px`;
            this.#audio.move();
            return true;
        };
        const approach = event => {
            if (event.pointerType === 'touch' || button.disabled || dodges >= limit) return;
            const rect = button.getBoundingClientRect();
            const margin = 48;
            if (event.clientX >= rect.left - margin && event.clientX <= rect.right + margin
                && event.clientY >= rect.top - margin && event.clientY <= rect.bottom + margin) move(event);
        };
        const press = event => {
            skipClick = move(event);
            if (skipClick) { event.preventDefault(); event.stopImmediatePropagation(); }
        };
        const click = event => {
            // Keyboard activation always works. Catch a fast click/tap during a dodge.
            if (event.detail === 0) return;
            if (skipClick || move(event)) {
                skipClick = false;
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        };
        const keepVisible = () => {
            if (!detached) return;
            const rect = button.getBoundingClientRect();
            button.style.left = `${Math.max(16, Math.min(rect.left, innerWidth - rect.width - 16))}px`;
            button.style.top = `${Math.max(16, Math.min(rect.top, innerHeight - rect.height - 16))}px`;
        };
        document.addEventListener('pointermove', approach, true);
        button.addEventListener('pointerenter', approach);
        button.addEventListener('pointerdown', press, true);
        button.addEventListener('click', click, true);
        window.addEventListener('resize', keepVisible);
        this.#cleanups.push(() => {
            document.removeEventListener('pointermove', approach, true);
            button.removeEventListener('pointerenter', approach);
            button.removeEventListener('pointerdown', press, true);
            button.removeEventListener('click', click, true);
            window.removeEventListener('resize', keepVisible);
            if (detached) {
                anchor.replaceWith(button);
                placeholder.remove();
                button.classList.remove('rage-fleeing-button');
                if (originalStyle === null) button.removeAttribute('style');
                else button.setAttribute('style', originalStyle);
            }
        });
    }

    #element(tag, text, className = '') {
        const element = document.createElement(tag);
        element.textContent = text;
        element.className = className;
        return element;
    }

    #background() {
        const source = this.#pick(this.#config.images?.backgrounds);
        if (!source) return;
        const picture = new Image();
        let active = true;
        const clear = () => {
            active = false;
            picture.onload = null;
            picture.onerror = null;
            this.#ui.background.style.backgroundImage = '';
        };
        picture.onload = () => {
            if (active) this.#ui.background.style.backgroundImage = `url(${JSON.stringify(source)})`;
        };
        picture.onerror = clear;
        this.#cleanups.push(clear);
        picture.src = source;
    }

    #showOverlay(mode) {
        this.#previousFocus = document.activeElement;
        this.#overlayMode = mode;
        this.#ui.overlay.classList.toggle('rage-picture-overlay', mode === 'picture');
        this.#ui.overlay.classList.toggle('rage-taunt-overlay', mode === 'taunt');
        this.#ui.dismiss.textContent = 'Lewati →';
        this.#ui.dismiss.hidden = mode === 'trap';
        this.#ui.overlay.hidden = false;
        if (mode === 'trap') this.#ui.content.querySelector('button')?.focus();
        else this.#ui.dismiss.focus();
        return new Promise(resolve => {
            this.#resolveOverlay = resolve;
        });
    }

    #closeOverlay() {
        if (this.#ui.overlay.hidden && !this.#resolveOverlay) return;
        this.#ui.overlay.hidden = true;
        this.#overlayMode = null;
        this.#ui.overlay.classList.remove('rage-picture-overlay', 'rage-taunt-overlay');
        this.#ui.dismiss.textContent = this.#dismissLabel;
        this.#ui.dismiss.hidden = false;
        this.#previousFocus?.focus?.({ preventScroll: true });
        this.#previousFocus = null;
        const resolve = this.#resolveOverlay;
        this.#resolveOverlay = null;
        resolve?.();
    }

    #loading() {
        const content = this.#ui.content;
        content.replaceChildren();
        const title = this.#element('h2', 'loading…'); title.id = 'rage-dialog-title';
        content.append(this.#element('div', '', 'loader'), title);
        const source = this.#pick(this.#config.images?.loading);
        if (source) {
            const picture = document.createElement('img');
            picture.alt = 'Selingan gambar QuizHell';
            picture.hidden = true;
            picture.onload = () => { picture.hidden = false; };
            picture.onerror = () => picture.remove();
            this.#cleanups.push(() => { picture.onload = null; picture.onerror = null; });
            picture.src = source;
            content.append(picture);
        }
        content.append(this.#element('p', 'Sebentar. Semestanya lagi mikir.', 'muted text-sm'));
        return this.#showOverlay('picture');
    }

    #taunt() {
        const title = this.#element('h2', this.#pick(this.#config.taunts) || 'Yakin itu jawabanmu?');
        title.id = 'rage-dialog-title';
        this.#ui.content.replaceChildren(this.#element('span', 'WAIT A SECOND…', 'eyebrow'), title);
        return this.#showOverlay('taunt');
    }

    #trap() {
        const trap = this.#pick(this.#config.traps);
        const content = this.#ui.content;
        content.replaceChildren(this.#element('span', 'ONE QUICK QUESTION…', 'eyebrow'));
        const title = this.#element('h2', trap.prompt); title.id = 'rage-dialog-title';
        const options = this.#element('div', '', 'trap-options');
        let answered = false;
        let active = true;
        let playback;
        this.#cleanups.push(() => {
            active = false;
            playback?.stop();
        });
        trap.options.forEach(label => {
            const button = this.#element('button', label, 'btn btn-secondary'); button.type = 'button';
            button.addEventListener('click', async () => {
                if (answered || !active) return;
                answered = true;
                options.querySelectorAll('button').forEach(option => { option.disabled = true; });
                button.classList.replace('btn-secondary', 'btn-primary');
                playback = this.#audio.laugh();
                await playback?.finished;
                // Expiry, unpublish, or question changes may have already cancelled this trap.
                if (!active) return;
                this.#closeOverlay();
                this.#toast('Gotcha. Soal tadi tidak dihitung 😭');
            });
            options.append(button);
        });
        content.append(title, options);
        return this.#showOverlay('trap');
    }
}
