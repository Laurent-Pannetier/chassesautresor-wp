document.addEventListener('submit', (e) => {
    if (!e.target.matches('.js-update-request')) {
        return;
    }
    e.preventDefault();
    const form = e.target;
    const select = form.querySelector('select[name="statut"]');
    const id = form.dataset.id;
    const row = form.closest('tr');
    const statusCell = row.querySelector('.col-status');
    const actionCell = form.closest('td');

    fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'update_conversion_status',
            paiement_id: id,
            statut: select.value,
        }),
    })
        .then((res) => res.json())
        .then((res) => {
            if (!res.success) {
                return;
            }
            let label = '🟡 En attente';
            switch (res.data.status) {
                case 'paid':
                    label = '✅ Réglé';
                    break;
                case 'cancelled':
                    label = '❌ Annulé';
                    break;
                case 'refused':
                    label = '🚫 Refusé';
                    break;
            }
            statusCell.textContent = label;
            actionCell.textContent = '-';
        })
        .catch(() => {});
});
