(function() {
    const switcher = document.querySelector('.lang-switcher');
    if (!switcher) {
        return;
    }

    const toggle = switcher.querySelector('.lang-switcher__toggle');
    const options = switcher.querySelector('.lang-switcher__options');

    if (!toggle || !options) {
        return;
    }

    toggle.addEventListener('click', function(event) {
        event.preventDefault();
        const open = switcher.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function(event) {
        if (!switcher.contains(event.target)) {
            switcher.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();
