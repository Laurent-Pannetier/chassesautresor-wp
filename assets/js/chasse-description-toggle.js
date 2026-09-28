document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.chasse-description');
    if (!container) {
        return;
    }

    var toggle = container.querySelector('.description-toggle');
    var shortDesc = container.querySelector('.description-short');
    var fullDesc = container.querySelector('.description-full');

    if (!toggle || !shortDesc || !fullDesc) {
        return;
    }

    var moreLabel = toggle.getAttribute('data-label-more');
    var lessLabel = toggle.getAttribute('data-label-less');

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';

        if (expanded) {
            fullDesc.hidden = true;
            shortDesc.hidden = false;
            toggle.textContent = moreLabel;
            toggle.setAttribute('aria-expanded', 'false');
        } else {
            fullDesc.hidden = false;
            shortDesc.hidden = true;
            toggle.textContent = lessLabel;
            toggle.setAttribute('aria-expanded', 'true');
        }
    });
});
