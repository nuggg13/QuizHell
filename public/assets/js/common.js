import { readJsonResponse } from './response.js';

export const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

export async function request(route, data = null) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
        const response = await fetch(`index.php?r=${encodeURIComponent(route)}`, {
            method: data === null ? 'GET' : 'POST',
            headers: data === null ? {} : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: data === null ? undefined : JSON.stringify(data),
            credentials: 'same-origin', signal: controller.signal,
        });
        return await readJsonResponse(response, 'Permintaan gagal. Coba kembali.');
    } catch (error) {
        if (error.name === 'AbortError') throw new Error('Koneksi terlalu lama. Coba kembali; jawaban yang sudah tersimpan tetap aman.');
        throw error;
    } finally {
        clearTimeout(timeout);
    }
}

document.querySelectorAll('[data-share-input]').forEach(input => {
    input.value = new URL(input.dataset.shareInput, window.location.href).href;
    input.addEventListener('click', () => input.select());
});
document.querySelectorAll('[data-copy-link]').forEach(button => {
    button.addEventListener('click', async () => {
        const link = new URL(button.dataset.copyLink, window.location.href).href;
        try {
            await navigator.clipboard.writeText(link);
            button.textContent = 'Tersalin ✓';
        } catch {
            const input = button.parentElement.querySelector('input');
            input?.focus();
            input?.select();
            button.textContent = 'Tekan Ctrl/Cmd+C';
        }
    });
});
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});
