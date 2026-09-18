const toggle = document.getElementById('quiz-filter-toggle');

if (toggle) {
    const panel = document.getElementById('quiz-filters');
    const grid = document.querySelector('.dashboard-quiz-grid');
    const cards = [...grid.querySelectorAll('.quiz-card')];
    const groups = [...panel.querySelectorAll('[data-quiz-filter]')];
    const reset = document.getElementById('reset-quiz-filters');
    const status = document.getElementById('quiz-filter-status');
    const filters = { status: 'all', level: 'all' };

    function applyFilters() {
        let visible = 0;
        cards.forEach(card => {
            const matches = (filters.status === 'all' || card.dataset.status === filters.status)
                && (filters.level === 'all' || card.dataset.level === filters.level);
            card.hidden = !matches;
            if (matches) visible++;
        });
        groups.forEach(group => {
            group.querySelectorAll('button[data-value]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.value === filters[group.dataset.quizFilter]));
            });
        });
        const active = Object.values(filters).filter(value => value !== 'all').length;
        document.getElementById('quiz-filter-label').textContent = active ? `Filter · ${active}` : 'Filter';
        document.getElementById('filtered-quiz-count').textContent = String(visible);
        status.hidden = false;
        status.textContent = `Menampilkan ${visible} dari ${cards.length} quiz.`;
        grid.hidden = visible === 0;
        document.getElementById('quiz-filter-empty').hidden = visible !== 0;
        reset.hidden = active === 0;
    }

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    });
    groups.forEach(group => {
        group.addEventListener('click', event => {
            const button = event.target.closest('button[data-value]');
            if (!button || !group.contains(button)) return;
            filters[group.dataset.quizFilter] = button.dataset.value;
            applyFilters();
        });
    });
    reset.addEventListener('click', () => {
        filters.status = 'all';
        filters.level = 'all';
        applyFilters();
        groups[0].querySelector('[data-value="all"]').focus();
    });
}
