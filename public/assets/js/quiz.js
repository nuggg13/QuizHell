import { request } from './common.js';
import { readJsonResponse } from './response.js';
import { AudioEffects } from './audio.js?v=20260912-trap-audio';
import { RageEngine } from './rage-engine.js?v=20260912-reveals';

// Real state lives only in this module. RageEngine receives no reference to it.
let state = JSON.parse(document.getElementById('play-data').textContent);
let index = Math.max(0, state.questions.findIndex(question => question.selected === null));
if (state.questions.every(question => question.selected !== null)) index = state.questions.length - 1;
let saving = false;
let transitioning = false;
let finished = false;
let unavailable = false;
let expiring = false;
let generation = 0;
let queue = Promise.resolve();
let clockAnchor = { server: state.serverTime * 1000, local: performance.now() };
const rageConfig = JSON.parse(document.getElementById('rage-config').textContent);
const audio = new AudioEffects(rageConfig.sounds);
const next = document.getElementById('next-button');
const options = document.getElementById('answer-options');
const status = document.getElementById('answer-status');
const notice = document.getElementById('play-notice');
const rage = new RageEngine({
    next, progress: document.getElementById('display-progress'),
    background: document.getElementById('rage-background'), toast: document.getElementById('rage-toast'),
    overlay: document.getElementById('rage-overlay'), content: document.getElementById('rage-content'),
    dismiss: document.getElementById('dismiss-rage'),
}, rageConfig, audio);

function enqueue(operation) {
    const result = queue.then(operation);
    queue = result.catch(() => {});
    return result;
}

function remaining() {
    if (state.deadline === null) return null;
    return Math.max(0, Math.ceil((state.deadline * 1000 - clockAnchor.server - (performance.now() - clockAnchor.local)) / 1000));
}

function message(text, error = false) {
    notice.textContent = text; notice.hidden = false; notice.classList.toggle('error', error);
}

function handleError(error) {
    message(error.message || 'Koneksi gagal. Coba kembali.', true);
    if ([403, 404].includes(error.status)) {
        unavailable = true; rage.reset();
        document.getElementById('real-question-panel').hidden = true;
        message(`${error.message} Muat ulang jika creator sudah membuka kembali quiz ini.`, true);
    }
}

function lockInputs() {
    const locked = saving || transitioning || finished || unavailable || remaining() === 0;
    options.querySelectorAll('input').forEach(input => { input.disabled = locked; });
    next.disabled = locked || state.questions[index]?.selected === null;
}

function goResult() {
    if (finished) return;
    finished = true; rage.reset(); lockInputs();
    window.setTimeout(() => {
        window.location.replace(`index.php?r=result&token=${encodeURIComponent(state.token)}`);
    }, 700);
}

function applyState(incoming) {
    if (incoming.finished) { goResult(); return; }
    const changed = incoming.revision !== state.revision;
    const oldQuestionId = state.questions[index]?.id;
    state = incoming;
    clockAnchor = { server: incoming.serverTime * 1000, local: performance.now() };
    if (changed) {
        generation++;
        const firstUnanswered = state.questions.findIndex(question => question.selected === null);
        index = firstUnanswered >= 0 ? firstUnanswered : Math.max(0, state.questions.findIndex(question => question.id === oldQuestionId));
        message('Creator memperbarui quiz. Soal yang berubah atau baru perlu dijawab; jawaban lain tetap tersimpan.');
        render();
    } else {
        // Synchronize saved answers from another tab without restarting visual events.
        options.querySelectorAll('input').forEach(input => {
            input.checked = Number(input.value) === state.questions[index]?.selected;
        });
        lockInputs();
    }
    tick();
}

function render() {
    const question = state.questions[index];
    if (!question) return;
    document.getElementById('play-title').textContent = state.title;
    document.getElementById('question-number').textContent = `Pertanyaan ${index + 1} / ${state.questions.length}`;
    document.getElementById('real-progress-label').textContent = `${state.questions.filter(item => item.selected !== null).length} jawaban tersimpan`;
    document.getElementById('question-type').textContent = question.type === 'true_false' ? 'TRUE / FALSE' : 'MULTIPLE CHOICE';
    document.getElementById('question-prompt').textContent = question.prompt;
    options.replaceChildren();
    const legend = document.createElement('legend'); legend.className = 'sr-only'; legend.textContent = 'Pilih satu jawaban'; options.append(legend);
    question.options.forEach((text, position) => {
        const label = document.createElement('label'); label.className = 'answer-option';
        const input = document.createElement('input'); input.type = 'radio'; input.name = 'answer'; input.value = String(position);
        input.checked = question.selected === position;
        input.addEventListener('change', () => saveAnswer(question.id, position));
        const letter = document.createElement('span'); letter.className = 'letter'; letter.textContent = String.fromCharCode(65 + position);
        const caption = document.createElement('span'); caption.textContent = text;
        label.append(input, letter, caption); options.append(label);
    });
    status.textContent = question.selected === null ? 'Pilih satu jawaban untuk melanjutkan.' : 'Jawaban tersimpan ✓';
    next.textContent = index === state.questions.length - 1 ? 'Submit quiz ↗' : 'Lanjut →';
    rage.enter({ key: `${question.id}:${question.revision}:${state.rageLevel}`, level: state.rageLevel, index,
        progress: (index + 1) / state.questions.length * 100, remainingSeconds: remaining() });
    lockInputs();
}

async function saveAnswer(questionId, position) {
    if (saving || transitioning || finished || unavailable || remaining() === 0) return;
    saving = true; lockInputs(); status.textContent = 'Menyimpan jawaban…';
    const revision = state.revision;
    try {
        const incoming = await enqueue(() => request('api/answer', { token: state.token, revision, questionId, position }));
        applyState(incoming);
        if (!incoming.updated && !incoming.finished) {
            status.textContent = 'Jawaban tersimpan ✓';
            document.getElementById('real-progress-label').textContent = `${state.questions.filter(item => item.selected !== null).length} jawaban tersimpan`;
        }
    } catch (error) {
        handleError(error);
        status.textContent = 'Belum tersimpan. Pilih jawaban lagi untuk mencoba ulang.';
        options.querySelectorAll('input').forEach(input => { input.checked = Number(input.value) === state.questions[index]?.selected; });
    } finally { saving = false; lockInputs(); }
}

next.addEventListener('click', async () => {
    if (next.disabled || transitioning) return;
    transitioning = true; lockInputs();
    const beforeGeneration = generation;
    try {
        // Answers are already acknowledged by the server before transition effects.
        await rage.beforeTransition();
        if (finished || unavailable || beforeGeneration !== generation) return;
        if (remaining() === 0) { await expire(); return; }
        if (index < state.questions.length - 1) {
            index++; render(); document.getElementById('question-prompt').focus({ preventScroll: true });
        } else {
            const incoming = await enqueue(() => request('api/submit', { token: state.token, revision: state.revision }));
            applyState(incoming);
        }
    } catch (error) {
        handleError(error);
        if (error.status === 422) {
            try { await synchronize(); } catch (syncError) { handleError(syncError); }
            const missing = state.questions.findIndex(question => question.selected === null);
            if (missing >= 0) { index = missing; render(); }
        }
    } finally { transitioning = false; lockInputs(); }
});

async function synchronize() {
    await enqueue(async () => {
        const response = await fetch(`index.php?r=api%2Fstate&token=${encodeURIComponent(state.token)}`, {
            credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(12000),
        });
        const incoming = await readJsonResponse(response, 'Sinkronisasi gagal.');
        applyState(incoming);
    });
}

async function expire() {
    if (expiring || finished || unavailable) return;
    expiring = true; rage.reset(); lockInputs();
    message('Waktu habis. Memproses jawaban yang sudah tersimpan…');
    try {
        const result = await enqueue(() => request('api/submit', { token: state.token, revision: state.revision }));
        applyState(result);
    } catch (error) {
        handleError(error);
        if (!unavailable) message('Waktu habis. Koneksi terputus; hasil akan diproses dari jawaban tersimpan saat koneksi kembali.', true);
    } finally {
        // Retry after transient failures, without ever reopening expired answer inputs.
        window.setTimeout(() => { expiring = false; }, 3000);
    }
}

function tick() {
    const seconds = remaining();
    document.getElementById('timer-value').textContent = seconds === null ? 'OFF'
        : `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    document.getElementById('real-timer').classList.toggle('urgent', seconds !== null && seconds <= 30);
    if (seconds === 0 && !finished && !unavailable) { lockInputs(); void expire(); }
}

let syncing = false;
async function poll() {
    if (finished || unavailable || syncing || saving) return;
    syncing = true;
    try { await synchronize(); } catch (error) { handleError(error); }
    finally { syncing = false; }
}

document.addEventListener('visibilitychange', () => { if (!document.hidden) void poll(); });
window.addEventListener('online', () => { void poll(); });
window.setInterval(poll, 2500);
window.setInterval(tick, 200);
render(); tick();
