function initHuntBannerSphereCard() {
    const toggle = document.getElementById('hunt-banner-sphere-toggle');
    const status = document.getElementById('hunt-banner-sphere-status');
    if (!toggle || !status || typeof huntBannerSphereCard === 'undefined') {
        return;
    }
    if (toggle.dataset.bound === '1') {
        return;
    }
    toggle.dataset.bound = '1';

    toggle.addEventListener('change', () => {
        const enabled = toggle.checked ? '1' : '0';
        toggle.disabled = true;
        fetch(huntBannerSphereCard.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: [
                'action=cta_toggle_hunt_banner_sphere',
                `nonce=${encodeURIComponent(huntBannerSphereCard.nonce)}`,
                `enabled=${enabled}`,
            ].join('&'),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (!data.success) {
                    toggle.checked = !toggle.checked;
                    return;
                }
                status.textContent = toggle.checked
                    ? huntBannerSphereCard.activated
                    : huntBannerSphereCard.deactivated;
            })
            .catch(() => {
                toggle.checked = !toggle.checked;
            })
            .finally(() => {
                toggle.disabled = false;
            });
    });
}

document.addEventListener('DOMContentLoaded', initHuntBannerSphereCard);
document.addEventListener('myaccountSectionLoaded', (event) => {
    if (event.detail && event.detail.section === 'outils') {
        initHuntBannerSphereCard();
    }
});
