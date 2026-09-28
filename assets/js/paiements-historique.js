document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.getElementById('historique-paiements-admin');
    if (!wrapper) {
        return;
    }

    function charger(page = 1) {
        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'lister_historique_paiements',
                page,
            }),
        })
            .then((r) => r.json())
            .then((res) => {
                if (!res.success) {
                    return;
                }
                wrapper.innerHTML = res.data.html;
                wrapper.dataset.page = res.data.page;
                wrapper.dataset.pages = res.data.pages;
            })
            .catch(() => {});
    }

    wrapper.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const page = parseInt(wrapper.dataset.page || '1', 10);
        const pages = parseInt(wrapper.dataset.pages || '1', 10);
        if (btn.classList.contains('pager-first')) {
            e.preventDefault();
            charger(1);
        }
        if (btn.classList.contains('pager-prev')) {
            e.preventDefault();
            if (page > 1) charger(page - 1);
        }
        if (btn.classList.contains('pager-next')) {
            e.preventDefault();
            if (page < pages) charger(page + 1);
        }
        if (btn.classList.contains('pager-last')) {
            e.preventDefault();
            charger(pages);
        }
    });
});
