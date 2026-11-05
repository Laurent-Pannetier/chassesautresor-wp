function initSiteProtectionCard() {
    const toggle = document.getElementById('site-protection-toggle');
    const status = document.getElementById('site-protection-status');
    if (!toggle || !status || typeof siteProtectionCard === 'undefined') {
        return;
    }

    toggle.addEventListener('change', () => {
        const enabled = toggle.checked ? '1' : '0';
        fetch(siteProtectionCard.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: `action=cta_toggle_site_protection&nonce=${encodeURIComponent(siteProtectionCard.nonce)}&enabled=${enabled}`,
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (!data.success) {
                    alert('Erreur: ' + data.data);
                    toggle.checked = !toggle.checked;
                    return;
                }
                status.textContent = toggle.checked
                    ? siteProtectionCard.activated
                    : siteProtectionCard.deactivated;
            })
            .catch(() => {
                alert('Erreur AJAX');
                toggle.checked = !toggle.checked;
            });
    });
}

document.addEventListener('DOMContentLoaded', initSiteProtectionCard);
document.addEventListener('myaccountSectionLoaded', (e) => {
    if (e.detail.section === 'outils') {
        initSiteProtectionCard();
    }
});
