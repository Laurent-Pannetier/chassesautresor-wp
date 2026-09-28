(function () {
    function getButton(event) {
        if (!event) {
            return null;
        }

        var target = event.target;

        if (target && typeof target.closest === 'function') {
            return target.closest('[data-ca-demo-reset]');
        }

        if (target && target.parentElement && typeof target.parentElement.closest === 'function') {
            return target.parentElement.closest('[data-ca-demo-reset]');
        }

        return null;
    }

    function handleClick(event) {
        var button = getButton(event);
        if (!button) {
            return;
        }

        if (typeof caDemoReset === 'undefined') {
            return;
        }

        event.preventDefault();

        if (!window.confirm(caDemoReset.confirm)) {
            return;
        }

        var nonce = button.getAttribute('data-nonce');
        var chasseId = button.getAttribute('data-chasse-id');

        if (!nonce || !chasseId) {
            alert(caDemoReset.nonceError);
            return;
        }

        button.disabled = true;

        var params = new URLSearchParams();
        params.append('action', 'ca_demo_reset_chasse');
        params.append('nonce', nonce);
        params.append('chasse_id', chasseId);

        fetch(caDemoReset.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            credentials: 'same-origin',
            body: params.toString(),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    alert(caDemoReset.success);
                    window.location.reload();
                    return;
                }

                var message = caDemoReset.error;
                if (data && data.data && data.data.message) {
                    message = data.data.message;
                }

                alert(message);
            })
            .catch(function () {
                alert(caDemoReset.error);
            })
            .finally(function () {
                button.disabled = false;
            });
    }

    document.addEventListener('click', handleClick);
})();
