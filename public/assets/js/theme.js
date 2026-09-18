(() => {
    const storageKey = 'quizhell-theme';
    const root = document.documentElement;
    let theme = 'dark';
    try {
        if (localStorage.getItem(storageKey) === 'light') theme = 'light';
    } catch {
        // The toggle still works when browser storage is unavailable.
    }

    function applyTheme() {
        root.dataset.theme = theme;
        document.querySelector('meta[name="theme-color"]')?.setAttribute(
            'content', theme === 'light' ? '#f5f7ee' : '#10110f',
        );
        const button = document.getElementById('theme-toggle');
        if (button) {
            const label = theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
            button.setAttribute('aria-label', label);
            button.title = label;
            button.hidden = false;
        }
    }

    // Apply the saved palette before the page styles and content are painted.
    applyTheme();
    document.addEventListener('DOMContentLoaded', () => {
        applyTheme();
        document.getElementById('theme-toggle')?.addEventListener('click', () => {
            theme = theme === 'dark' ? 'light' : 'dark';
            applyTheme();
            try {
                localStorage.setItem(storageKey, theme);
            } catch {
                // Persistence is optional; changing the current page is not.
            }
        });
    });
})();
