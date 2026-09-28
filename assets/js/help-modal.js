// Global help modal handler

document.addEventListener(
  'click',
  (e) => {
    const btn = e.target.closest('[data-message]');
    if (!btn) return;

    // Empêche les anciens gestionnaires de cliquer et les liens de s'exécuter
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const title = btn.dataset.title || '';
    const message = btn.dataset.message || '';
    const icon = btn.dataset.icon || 'fa-regular fa-circle-question';
    const variant = btn.dataset.variant || '';
    const closeLabel =
      btn.dataset.close || wp.i18n.__('Fermer', 'chassesautresor-com');
    openHelpModal({ title, message, icon, variant, closeLabel });
  },
  true
);

function openHelpModal({ title, message, icon, variant, closeLabel }) {
  // Ferme toute modale existante avant d'en ouvrir une nouvelle
  document.querySelector('.help-modal-overlay')?.remove();

  const overlay = document.createElement('div');
  overlay.className = 'help-modal-overlay';
  const modalClass = variant ? ` help-modal--${variant}` : '';
  overlay.innerHTML = `
    <div class="help-modal${modalClass}" role="dialog" aria-modal="true">
      <button type="button" class="help-modal-close" aria-label="${closeLabel}">&times;</button>
      <header class="help-modal-header">
        <i class="${icon}" aria-hidden="true"></i>
        <h2 class="help-modal-title">${title}</h2>
      </header>
      <div class="help-modal-content"></div>
    </div>`;
  document.body.appendChild(overlay);

  const content = overlay.querySelector('.help-modal-content');
  message.split('\n\n').forEach((block, index) => {
    const lines = block.split('\n');
    const p = document.createElement('p');
    if (lines.length > 1) {
      const strong = document.createElement('strong');
      strong.textContent = lines[0];
      p.appendChild(strong);
      p.appendChild(document.createElement('br'));
      p.appendChild(document.createTextNode(lines.slice(1).join(' ')));
    } else {
      p.textContent = block;
    }
    if (index === 0) {
      p.classList.add('help-definition');
    }
    content.appendChild(p);
  });

  const close = () => overlay.remove();
  overlay.querySelector('.help-modal-close').addEventListener('click', close);
  overlay.addEventListener('click', (ev) => {
    if (ev.target === overlay) close();
  });
}
