(function () {
  const ready = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  };

  ready(() => {
    const badges = Array.from(document.querySelectorAll('.badge-statut[data-tooltip]'));
    if (!badges.length) {
      return;
    }

    const hideAll = () => {
      badges.forEach(badge => badge.removeAttribute('data-tooltip-visible'));
    };

    document.addEventListener('click', (event) => {
      if (!event.target.closest('.badge-statut[data-tooltip]')) {
        hideAll();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        hideAll();
      }
    });

    badges.forEach((badge) => {
      const showTooltip = (event) => {
        if (badge.dataset.tooltipVisible === 'true') {
          return;
        }

        hideAll();
        badge.setAttribute('data-tooltip-visible', 'true');

        if (event) {
          if (event.type === 'keydown' && event.key === ' ') {
            event.preventDefault();
          }

          if (event.type !== 'keydown') {
            event.preventDefault();
          }

          event.stopPropagation();
        }
      };

      const hideTooltip = () => {
        badge.removeAttribute('data-tooltip-visible');
      };

      badge.addEventListener('click', (event) => {
        if (badge.dataset.tooltipVisible === 'true') {
          hideTooltip();
          return;
        }

        showTooltip(event);
      });

      badge.addEventListener('touchstart', (event) => {
        if (badge.dataset.tooltipVisible === 'true') {
          return;
        }

        showTooltip(event);
      }, { passive: false });

      badge.addEventListener('blur', hideTooltip);

      badge.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          hideTooltip();
          return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
          if (badge.dataset.tooltipVisible === 'true') {
            if (event.key === ' ') {
              event.preventDefault();
            }
            hideTooltip();
            const parentLink = badge.closest('a');
            if (parentLink) {
              parentLink.click();
            }
            return;
          }

          showTooltip(event);
        }
      });
    });
  });
})();
