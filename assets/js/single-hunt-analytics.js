(function () {
    'use strict';

    function sendEvent(name, parameters) {
        if (typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('event', name, parameters || {});
    }

    document.addEventListener('click', function (event) {
        var action = event.target.closest('[data-single-hunt-event]');

        if (!action) {
            action = event.target.closest(
                '.single-hunt-journey__actions a, .single-hunt-journey__actions button, ' +
                '.single-hunt-home__actions a, .single-hunt-home__actions button'
            );
        }

        if (!action) {
            return;
        }

        sendEvent(action.dataset.singleHuntEvent || 'single_hunt_cta', {
            action_label: action.textContent.trim(),
            destination: action.getAttribute('href') || '',
        });
    });

    document.addEventListener('submit', function (event) {
        if (!event.target.matches('.cta-chasse-form')) {
            return;
        }

        sendEvent('single_hunt_engagement_start');
    });

    document.addEventListener('cta:riddle-resolved', function () {
        sendEvent('single_hunt_riddle_resolved');
    });

    if (document.body.classList.contains('single-enigme')) {
        sendEvent('single_hunt_riddle_open');
    }
}());
