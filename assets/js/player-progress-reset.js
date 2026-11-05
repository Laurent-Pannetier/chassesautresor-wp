function initPlayerProgressReset() {
    const button = document.querySelector('[data-reset-my-progress]');
    if (!button || button.dataset.resetMyProgressBound === 'true' || typeof playerProgressReset === 'undefined') {
        return;
    }

    button.dataset.resetMyProgressBound = 'true';
    button.addEventListener('click', function (event) {
        event.preventDefault();
        if (!confirm(playerProgressReset.confirm)) {
            return;
        }

        button.disabled = true;
        fetch(playerProgressReset.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: `action=cta_reset_my_progress&nonce=${encodeURIComponent(playerProgressReset.nonce)}`,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    window.location.reload();
                    return;
                }
                alert(playerProgressReset.error);
                button.disabled = false;
            })
            .catch(() => {
                alert(playerProgressReset.ajaxError);
                button.disabled = false;
            });
    });
}

document.addEventListener('DOMContentLoaded', initPlayerProgressReset);
