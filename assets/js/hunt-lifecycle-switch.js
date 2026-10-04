(function () {
  const config = window.ctaHuntLifecycle;
  if (!config) {
    return;
  }

  const applyView = (card, toggle, status, help, badgeLabel, icon, labelOff, labelOn, view) => {
    toggle.checked = !!view.checked;
    toggle.disabled = !!view.disabled;
    card.setAttribute('data-state', view.state || '');

    if (status) {
      status.textContent = view.status_label || '';
    }

    if (help) {
      if (view.help) {
        help.hidden = false;
        help.textContent = view.help;
      } else {
        help.hidden = true;
        help.textContent = '';
      }
    }

    if (badgeLabel) {
      badgeLabel.textContent = view.badge_label || '';
    }

    if (icon) {
      const nextIcon = view.badge_icon || 'fa-pen';
      icon.className = 'fas ' + nextIcon;
    }

    if (labelOff) {
      labelOff.classList.toggle('is-current', !view.checked);
    }
    if (labelOn) {
      labelOn.classList.toggle('is-current', !!view.checked);
    }
  };

  const init = (root = document) => {
    root.querySelectorAll('[data-hunt-lifecycle]').forEach((card) => {
      if (card.dataset.lifecycleBound === '1') {
        return;
      }
      card.dataset.lifecycleBound = '1';

      const toggle = card.querySelector('[data-hunt-lifecycle-toggle]');
      const status = card.querySelector('[data-hunt-lifecycle-status]');
      const help = card.querySelector('[data-hunt-lifecycle-help]');
      const badgeLabel = card.querySelector('[data-hunt-lifecycle-badge-label]');
      const icon = card.querySelector('[data-hunt-lifecycle-icon]');
      const labelOff = card.querySelector('[data-hunt-lifecycle-label-off]');
      const labelOn = card.querySelector('[data-hunt-lifecycle-label-on]');
      if (!toggle) {
        return;
      }

      toggle.addEventListener('change', () => {
        const huntId = card.getAttribute('data-hunt-id');
        const activate = toggle.checked ? '1' : '0';
        const previous = !toggle.checked;
        const nonce = config.nonces && config.nonces[huntId] ? config.nonces[huntId] : '';
        if (!nonce) {
          toggle.checked = previous;
          window.alert(config.errorMessage);
          return;
        }

        toggle.disabled = true;
        const body = new URLSearchParams({
          action: 'cta_toggle_hunt_lifecycle',
          nonce,
          hunt_id: huntId,
          activate,
        });

        fetch(config.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: body.toString(),
        })
          .then((response) => response.json())
          .then((payload) => {
            const view = payload && payload.data && payload.data.view ? payload.data.view : null;
            if (!payload.success || !view) {
              toggle.checked = previous;
              toggle.disabled = false;
              window.alert(
                (payload && payload.data && payload.data.message) || config.errorMessage
              );
              return;
            }
            applyView(card, toggle, status, help, badgeLabel, icon, labelOff, labelOn, view);
          })
          .catch(() => {
            toggle.checked = previous;
            toggle.disabled = false;
            window.alert(config.errorMessage);
          });
      });
    });
  };

  document.addEventListener('DOMContentLoaded', () => init(document));
})();
