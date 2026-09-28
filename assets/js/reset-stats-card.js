function initResetStatsCard() {
    const btn = document.getElementById('reset-stats-btn');
    if (!btn || typeof resetStatsCard === 'undefined') {
        return;
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!confirm(resetStatsCard.confirm)) {
            return;
        }
        btn.disabled = true;
        fetch(resetStatsCard.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: `action=cta_reset_stats&nonce=${encodeURIComponent(resetStatsCard.nonce)}`,
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (data.success) {
                    alert(resetStatsCard.success);
                } else {
                    alert('Erreur : ' + data.data);
                }
            })
            .catch(() => {
                alert('Erreur AJAX');
            })
            .finally(() => {
                btn.disabled = false;
            });
    });
}

document.addEventListener('DOMContentLoaded', initResetStatsCard);
document.addEventListener('myaccountSectionLoaded', (e) => {
    if (e.detail.section === 'outils') {
        initResetStatsCard();
    }
});
