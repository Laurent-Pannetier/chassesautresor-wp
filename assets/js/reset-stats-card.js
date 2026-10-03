function initResetStatsCard() {
    const buttons = document.querySelectorAll('[data-reset-stats]');
    if (!buttons.length || typeof resetStatsCard === 'undefined') {
        return;
    }

    buttons.forEach((btn) => {
        if (btn.dataset.resetStatsBound === 'true') {
            return;
        }

        btn.dataset.resetStatsBound = 'true';
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!confirm(resetStatsCard.confirm)) {
                return;
            }
            buttons.forEach((button) => {
                button.disabled = true;
            });
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
                        window.location.reload();
                    } else {
                        alert(`${resetStatsCard.error} ${data.data || ''}`.trim());
                    }
                })
                .catch(() => {
                    alert(resetStatsCard.ajaxError);
                })
                .finally(() => {
                    buttons.forEach((button) => {
                        button.disabled = false;
                    });
                });
        });
    });
}

document.addEventListener('DOMContentLoaded', initResetStatsCard);
document.addEventListener('myaccountSectionLoaded', (e) => {
    if (e.detail.section === 'outils') {
        initResetStatsCard();
    }
});
