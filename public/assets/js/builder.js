import { request } from './common.js';

const data = JSON.parse(document.getElementById('builder-data').textContent);
const form = document.getElementById('builder-form');
const container = document.getElementById('questions');
const add = document.getElementById('add-question');
const errorBox = document.getElementById('builder-error');
const saveButton = document.getElementById('save-quiz');
let keySequence = 0;
const nextKey = () => `question-${++keySequence}`;
let questions = data.questions.map(question => ({ ...question, key: nextKey() }));
let dirty = false;
let saving = false;

function markDirty() {
    dirty = true;
    document.getElementById('save-status').textContent = 'Ada perubahan yang belum disimpan.';
}

function make(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
}

function tool(text, label, callback, disabled = false) {
    const button = make('button', 'icon-button', text);
    button.type = 'button';
    button.setAttribute('aria-label', label);
    button.title = label;
    button.disabled = disabled;
    button.addEventListener('click', callback);
    return button;
}

function render(focusKey = null) {
    container.replaceChildren();
    questions.forEach((question, index) => {
        const card = make('article', 'panel question-card');
        card.dataset.key = question.key;
        const header = make('div', 'question-card-header');
        header.append(make('strong', '', `PERTANYAAN ${String(index + 1).padStart(2, '0')}`));
        const controls = make('div', 'question-tools');
        controls.append(
            tool('↑', `Naikkan soal ${index + 1}`, () => move(index, -1), index === 0),
            tool('↓', `Turunkan soal ${index + 1}`, () => move(index, 1), index === questions.length - 1),
            tool('×', `Hapus soal ${index + 1}`, () => {
                questions.splice(index, 1); markDirty(); render();
            }),
        );
        header.append(controls);
        card.append(header);
        const grid = make('div', 'question-edit-grid');
        const promptLabel = make('label', '', 'Pertanyaan');
        const prompt = make('textarea');
        prompt.rows = 3; prompt.maxLength = 3000; prompt.required = true;
        prompt.placeholder = 'Tulis pertanyaanmu…'; prompt.value = question.prompt;
        prompt.addEventListener('input', () => { question.prompt = prompt.value; markDirty(); });
        promptLabel.append(prompt);
        const typeLabel = make('label', '', 'Tipe pertanyaan');
        const type = make('select');
        [['multiple_choice', 'Multiple Choice'], ['true_false', 'True / False']].forEach(([value, label]) => {
            const option = make('option', '', label); option.value = value; type.append(option);
        });
        type.value = question.type;
        type.addEventListener('change', () => {
            question.type = type.value;
            question.options = type.value === 'true_false' ? ['True', 'False'] : ['', ''];
            question.correct = null;
            markDirty(); render(question.key);
        });
        typeLabel.append(type); grid.append(promptLabel, typeLabel); card.append(grid);
        const options = make('fieldset', 'builder-options');
        options.append(make('legend', 'muted text-xs', 'Pilih lingkaran pada satu jawaban yang benar.'));
        question.options.forEach((value, optionIndex) => {
            const row = make('div', 'builder-option');
            const radio = make('input'); radio.type = 'radio'; radio.name = `correct-${question.key}`;
            radio.required = true; radio.checked = question.correct === optionIndex;
            radio.setAttribute('aria-label', `Jawaban ${String.fromCharCode(65 + optionIndex)} benar`);
            radio.addEventListener('change', () => { question.correct = optionIndex; markDirty(); });
            const input = make('input'); input.type = 'text'; input.maxLength = 500; input.required = true;
            input.value = value; input.readOnly = question.type === 'true_false';
            input.placeholder = `Pilihan ${String.fromCharCode(65 + optionIndex)}`;
            input.setAttribute('aria-label', `Soal ${index + 1}, pilihan ${String.fromCharCode(65 + optionIndex)}`);
            input.addEventListener('input', () => { question.options[optionIndex] = input.value; markDirty(); });
            row.append(radio, make('span', 'option-letter', String.fromCharCode(65 + optionIndex)), input);
            if (question.type === 'multiple_choice') {
                row.append(tool('×', `Hapus pilihan ${String.fromCharCode(65 + optionIndex)}`, () => {
                    question.options.splice(optionIndex, 1);
                    if (question.correct === optionIndex) question.correct = null;
                    else if (question.correct > optionIndex) question.correct--;
                    markDirty(); render();
                }, question.options.length <= 2));
            }
            options.append(row);
        });
        if (question.type === 'multiple_choice' && question.options.length < 4) {
            const addOption = make('button', 'add-option', '＋ Tambah pilihan'); addOption.type = 'button';
            addOption.addEventListener('click', () => { question.options.push(''); markDirty(); render(); });
            options.append(addOption);
        }
        card.append(options); container.append(card);
    });
    document.getElementById('question-count').textContent = `${questions.length} / 30`;
    document.getElementById('question-limit').hidden = questions.length < 30;
    add.disabled = questions.length >= 30;
    if (!questions.length) container.append(make('p', 'panel empty-state muted', 'Belum ada soal. Tambahkan pertanyaan pertama di bawah.'));
    if (focusKey) container.querySelector(`[data-key="${focusKey}"] textarea`)?.focus();
}

function move(index, direction) {
    [questions[index], questions[index + direction]] = [questions[index + direction], questions[index]];
    markDirty(); render();
    container.children[index + direction]?.scrollIntoView({ block: 'nearest' });
}

add.addEventListener('click', () => {
    if (questions.length >= 30) return;
    const question = { id: 0, key: nextKey(), prompt: '', type: 'multiple_choice', options: ['', ''], correct: null };
    questions.push(question); markDirty(); render(question.key);
});

const timerOn = document.getElementById('timer-on');
const timerMinutes = document.getElementById('timer-minutes');
function toggleTimer() {
    document.getElementById('timer-field').hidden = !timerOn.checked;
    timerMinutes.disabled = !timerOn.checked;
    timerMinutes.required = timerOn.checked;
}
timerOn.addEventListener('change', toggleTimer);
form.addEventListener('input', markDirty);
form.addEventListener('change', markDirty);
window.addEventListener('beforeunload', event => {
    if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; }
});
form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving) return;
    errorBox.hidden = true;
    const payload = {
        id: data.id, revision: data.revision,
        title: document.getElementById('quiz-title').value,
        description: document.getElementById('quiz-description').value,
        rage_level: form.querySelector('[name="rage_level"]:checked').value,
        timer_minutes: timerOn.checked ? Number(timerMinutes.value) : null,
        questions: questions.map(({ id, prompt, type, options, correct }) => ({ id, prompt, type, options, correct })),
    };
    saving = true; saveButton.disabled = true; saveButton.textContent = 'Menyimpan…';
    try {
        const response = await request('api/quiz/save', payload);
        dirty = false;
        window.location.assign(response.redirect);
    } catch (error) {
        saving = false; saveButton.disabled = false; saveButton.textContent = 'Simpan quiz ↗';
        errorBox.textContent = error.message; errorBox.hidden = false;
        errorBox.scrollIntoView({ block: 'center' });
    }
});

toggleTimer(); render();
