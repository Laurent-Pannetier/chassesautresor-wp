(function () {
    var toggle = document.querySelector('[data-auth-email-toggle]');
    var panel = document.querySelector('[data-auth-email]');
    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            var open = panel.hasAttribute('hidden');
            if (open) {
                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                var field = panel.querySelector('input[name="log"]');
                if (field) {
                    field.focus();
                }
            } else {
                panel.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-auth-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-auth-password-toggle');
            var input = id ? document.getElementById(id) : null;
            if (!input) {
                return;
            }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            var nextLabel = show ? button.getAttribute('data-label-hide') : button.getAttribute('data-label-show');
            if (nextLabel) {
                button.textContent = nextLabel;
            }
        });
    });
}());
