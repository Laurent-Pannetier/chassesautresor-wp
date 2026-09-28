(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.enigme-menu__toggle').forEach(function (button) {
            var overflow = button.previousElementSibling;
            if (!overflow) {
                return;
            }

            button.addEventListener('click', function () {
                var isHidden = overflow.hasAttribute('hidden');
                if (isHidden) {
                    overflow.removeAttribute('hidden');
                    button.setAttribute('aria-expanded', 'true');
                } else {
                    overflow.setAttribute('hidden', '');
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        });
    });
})();
