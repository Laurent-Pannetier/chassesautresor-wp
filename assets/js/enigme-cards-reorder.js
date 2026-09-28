/**
 * Reorder enigme cards within the cards grid.
 */
function initEnigmeCardsReorder() {
  const grid = document.querySelector('.cards-grid');
  if (!grid) return;

  grid
    .querySelectorAll('.carte-enigme a')
    .forEach((a) => a.setAttribute('draggable', 'false'));

  const addCard = grid.querySelector('#carte-ajout-enigme');
  const addWrapper = addCard?.closest('.carte-ajout-wrapper');
  let dragged = null;
  let startX = 0;
  let startY = 0;

  grid.addEventListener('dragstart', (e) => {
    const card = e.target.closest('.carte-enigme');
    if (!card || card.id === 'carte-ajout-enigme') return;
    dragged = card;
    startX = e.clientX;
    startY = e.clientY;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', '');
    grid.classList.add('dragging');
    dragged.classList.add('dragging');
  });

  grid.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (!dragged) return;
    e.dataTransfer.dropEffect = 'move';
    const target = e.target.closest('.carte-enigme');
    if (!target || target === dragged || target.id === 'carte-ajout-enigme') return;
    grid.querySelectorAll('.drag-over').forEach((el) => el.classList.remove('drag-over'));
    target.classList.add('drag-over');
    const rect = target.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    const horizontal = Math.abs(e.clientX - startX) > Math.abs(e.clientY - startY);
    const next = horizontal ? e.clientX > centerX : e.clientY > centerY;
    grid.insertBefore(dragged, next ? target.nextSibling : target);
  });

  const ensureAddLast = () => {
    if (addWrapper) {
      grid.appendChild(addWrapper);
    } else if (addCard) {
      grid.appendChild(addCard);
    }
  };

  const updateNavOrder = () => {
    const menu = document.querySelector('.enigme-navigation .enigme-menu');
    if (!menu) return;
    const ids = Array.from(
      grid.querySelectorAll('.carte-enigme[data-enigme-id]')
    ).map((el) => el.dataset.enigmeId);
    ids.forEach((id) => {
      const item = menu.querySelector(`li[data-enigme-id="${id}"]`);
      if (item) menu.appendChild(item);
    });
  };

  const saveOrder = () => {
    const order = Array.from(
      grid.querySelectorAll('.carte-enigme[data-enigme-id]')
    ).map((el) => el.dataset.enigmeId);
    if (!order.length) return;
    const fd = new FormData();
    fd.append('action', 'reordonner_enigmes');
    fd.append('chasse_id', grid.dataset.chasseId);
    order.forEach((id) => fd.append('ordre[]', id));
    fetch(window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd,
    })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success) {
          alert(wp.i18n.__("Erreur lors de l'enregistrement de l'ordre", 'chassesautresor-com'));
        }
        if (res.success && window.sidebarAside?.reload) {
          window.sidebarAside.reload(grid.dataset.chasseId);
        }
      })
      .catch(() => {
        alert(wp.i18n.__("Erreur lors de l'enregistrement de l'ordre", 'chassesautresor-com'));
      });
  };

  const cleanClasses = () => {
    grid.classList.remove('dragging');
    grid.querySelectorAll('.drag-over').forEach((el) => el.classList.remove('drag-over'));
    dragged?.classList.remove('dragging');
  };

  grid.addEventListener('drop', (e) => {
    e.preventDefault();
    cleanClasses();
    ensureAddLast();
    updateNavOrder();
    saveOrder();
    dragged = null;
  });

  grid.addEventListener('dragend', () => {
    cleanClasses();
    ensureAddLast();
    updateNavOrder();
    saveOrder();
    dragged = null;
  });
}

document.addEventListener('DOMContentLoaded', initEnigmeCardsReorder);
