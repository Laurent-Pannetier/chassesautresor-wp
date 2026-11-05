// Le survol d'un point d'exclamation ouvre, à côté du marqueur,
// la liste des éléments qui bloquent l'activation.
// Le clic reste géré par l'attribut popover, y compris sur écran tactile.

(function () {
  function isOpen(popover) {
    try {
      return popover.matches(':popover-open');
    } catch (error) {
      return popover.dataset.open === '1';
    }
  }

  function place(button, popover) {
    const margin = 8;
    const rect = button.getBoundingClientRect();
    popover.style.position = 'fixed';
    popover.style.margin = '0';
    popover.style.inset = 'auto';
    const width = popover.offsetWidth;
    const height = popover.offsetHeight;
    let left = rect.right + margin;
    if (left + width > window.innerWidth - margin) {
      left = Math.max(margin, rect.left - width - margin);
    }
    let top = rect.top;
    if (top + height > window.innerHeight - margin) {
      top = Math.max(margin, window.innerHeight - height - margin);
    }
    popover.style.left = left + 'px';
    popover.style.top = top + 'px';
  }

  function bind(button) {
    const id = button.getAttribute('popovertarget');
    const popover = id ? document.getElementById(id) : null;
    if (!popover || typeof popover.showPopover !== 'function') return;

    // Le titre natif doublerait l'explication déjà affichée au survol.
    button.removeAttribute('title');

    let hideTimer = 0;
    const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const open = () => {
      window.clearTimeout(hideTimer);
      if (!isOpen(popover)) {
        popover.showPopover();
      }
      place(button, popover);
    };
    const scheduleHide = () => {
      if (!finePointer()) return;
      window.clearTimeout(hideTimer);
      hideTimer = window.setTimeout(() => {
        if (isOpen(popover)) {
          popover.hidePopover();
        }
      }, 180);
    };

    button.addEventListener('mouseenter', () => {
      if (!finePointer()) return;
      open();
    });
    button.addEventListener('mouseleave', scheduleHide);
    button.addEventListener('focus', () => {
      if (finePointer() && button.matches(':focus-visible')) {
        open();
      }
    });
    button.addEventListener('blur', (event) => {
      if (popover.contains(event.relatedTarget)) return;
      scheduleHide();
    });
    popover.addEventListener('mouseenter', () => {
      window.clearTimeout(hideTimer);
    });
    popover.addEventListener('mouseleave', scheduleHide);
    popover.addEventListener('toggle', (event) => {
      if (event.newState === 'open') {
        place(button, popover);
      }
    });
  }

  function start() {
    document
      .querySelectorAll('.app-nav__marker--incomplete, .hunt-lifecycle-card__gaps')
      .forEach(bind);
  }

  function copy() {
    const i18n = window.CtaShellEditionNav || {};
    return {
      summary: i18n.incomplete || 'Énigme incomplète : %s',
      heading: i18n.heading || 'Cette énigme bloque l’activation :',
    };
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function summaryText(labels) {
    const template = copy().summary;
    const joined = labels.join(' ; ');
    return template.includes('%s') ? template.replace('%s', joined) : template + ' ' + joined;
  }

  function addRiddleLabel() {
    const i18n = window.CtaShellEditionNav || {};
    return i18n.addRiddle || 'Ajouter une énigme';
  }

  function addRiddleItem() {
    return document.querySelector('#app-nav-outils-enigmes .app-nav__subitem--add');
  }

  function syncAddLink() {
    const add = addRiddleItem();
    if (!add) return;
    add.hidden = Boolean(document.querySelector('.app-nav__marker--incomplete'));
  }

  // « Éditer » réaffiche l'ajout si le serveur l'autorise.
  // « Activer » retire l'entrée : un simple hidden ne suffit pas,
  // car .app-nav__subitem est en display:flex.
  function syncAddRiddleAvailability(allowed, url) {
    const list = document.getElementById('app-nav-outils-enigmes');
    let add = addRiddleItem();
    if (!allowed) {
      if (add) {
        add.remove();
      }
      return;
    }

    if (!add) {
      if (!list || !url) return;
      add = document.createElement('li');
      add.className = 'app-nav__subitem app-nav__subitem--add';
      const link = document.createElement('a');
      link.className = 'app-nav__add';
      const icon = document.createElement('i');
      icon.className = 'fa-solid fa-circle-plus';
      icon.setAttribute('aria-hidden', 'true');
      const label = document.createElement('span');
      label.textContent = addRiddleLabel();
      link.appendChild(icon);
      link.appendChild(label);
      add.appendChild(link);
      list.appendChild(add);
    }

    const link = add.querySelector('a.app-nav__add');
    if (link && url) {
      link.setAttribute('href', url);
    }
    syncAddLink();
  }

  window.ctaSyncAddRiddleAvailability = syncAddRiddleAvailability;

  function removeGap(riddleId) {
    const button = document.getElementById('enigme-gaps-btn-' + riddleId);
    const popover = document.getElementById('enigme-gaps-' + riddleId);
    if (popover && isOpen(popover) && typeof popover.hidePopover === 'function') {
      popover.hidePopover();
    }
    if (button) button.remove();
    if (popover) popover.remove();
  }

  function fillList(popover, labels) {
    const items = labels.map((label) => '<li>' + escapeHtml(label) + '</li>').join('');
    const list = popover.querySelector('ul');
    if (list) {
      list.innerHTML = items;
      return;
    }
    popover.insertAdjacentHTML('beforeend', '<ul>' + items + '</ul>');
  }

  // Le marqueur suit l'enregistrement : il disparaît dès que l'énigme est complète.
  function syncRiddleNavGap(riddleId, labels) {
    const id = String(riddleId).replace(/[^\d]/g, '');
    const row = id
      ? document.querySelector('.app-nav__subitem--riddle[data-riddle-id="' + id + '"]')
      : null;
    if (!row) return;

    const clean = Array.isArray(labels)
      ? labels.map((label) => String(label).trim()).filter((label) => label !== '')
      : [];
    if (clean.length === 0) {
      removeGap(id);
      syncAddLink();
      return;
    }

    const text = summaryText(clean);
    let button = document.getElementById('enigme-gaps-btn-' + id);
    let popover = document.getElementById('enigme-gaps-' + id);
    if (!button || !popover) {
      if (button) button.remove();
      if (popover) popover.remove();
      const markers = row.querySelector('.app-nav__markers');
      if (!markers) return;
      const html = '<button type="button" class="app-nav__marker app-nav__marker--incomplete"'
        + ' id="enigme-gaps-btn-' + id + '"'
        + ' popovertarget="enigme-gaps-' + id + '"'
        + ' aria-label="' + escapeHtml(text) + '">'
        + '<i class="fa-solid fa-exclamation" aria-hidden="true"></i></button>'
        + '<div id="enigme-gaps-' + id + '" popover class="app-nav__status-pop"'
        + ' anchor="enigme-gaps-btn-' + id + '">'
        + '<p class="app-nav__status-pop-title">' + escapeHtml(copy().heading) + '</p>'
        + '<ul></ul></div>';
      const draft = markers.querySelector('.app-nav__marker--draft');
      if (draft) {
        draft.insertAdjacentHTML('beforebegin', html);
      } else {
        markers.insertAdjacentHTML('beforeend', html);
      }
      button = document.getElementById('enigme-gaps-btn-' + id);
      popover = document.getElementById('enigme-gaps-' + id);
      if (button) bind(button);
    }

    if (button) button.setAttribute('aria-label', text);
    if (popover) fillList(popover, clean);
    syncAddLink();
  }

  window.ctaSyncRiddleNavGap = syncRiddleNavGap;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
