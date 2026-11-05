function initStatsFixturesCard() {
    const root = document.querySelector('[data-stats-fixtures]');
    if (!root || typeof statsFixturesCard === 'undefined' || root.dataset.statsFixturesBound === 'true') {
        return;
    }

    root.dataset.statsFixturesBound = 'true';
    root.querySelectorAll('[data-stats-fixtures-action]').forEach((button) => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const action = button.getAttribute('data-stats-fixtures-action');
            const confirmText = action === 'purge'
                ? statsFixturesCard.confirmPurge
                : statsFixturesCard.confirmSeed;
            if (!window.confirm(confirmText)) {
                return;
            }

            const buttons = root.querySelectorAll('button');
            buttons.forEach((item) => {
                item.disabled = true;
            });
            const ajaxAction = action === 'purge'
                ? 'cta_purge_statistics_fixtures'
                : 'cta_seed_statistics_fixtures';
            const body = new URLSearchParams({
                action: ajaxAction,
                nonce: statsFixturesCard.nonce,
            });

            window.fetch(statsFixturesCard.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        window.alert((data.data && data.data.message) || statsFixturesCard.error);
                        window.location.reload();
                        return;
                    }
                    const message = typeof data.data === 'string' ? data.data : statsFixturesCard.error;
                    window.alert(message);
                })
                .catch(() => {
                    window.alert(statsFixturesCard.ajaxError);
                })
                .finally(() => {
                    buttons.forEach((item) => {
                        item.disabled = false;
                    });
                });
        });
    });
}

document.addEventListener('DOMContentLoaded', initStatsFixturesCard);
document.addEventListener('myaccountSectionLoaded', (event) => {
    if (event.detail && event.detail.section === 'outils') {
        initStatsFixturesCard();
    }
});
