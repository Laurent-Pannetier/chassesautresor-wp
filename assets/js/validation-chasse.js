/**
 * Gestion du bouton "Valider ma chasse".
 * Une fenêtre de confirmation est affichée avant l'envoi réel du formulaire.
 */

document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const close = e.target.closest('.msg-important .message-close');
    if (close) {
      const key = close.dataset.key;
      if (key) {
        const params = new URLSearchParams();
        params.set('action', 'cta_dismiss_message');
        params.set('key', key);
        const ajaxUrl =
          typeof ctaMyAccount !== 'undefined'
            ? ctaMyAccount.ajaxUrl
            : '/wp-admin/admin-ajax.php';
        fetch(ajaxUrl, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params.toString(),
        }).catch(() => {});
      }
      close.closest('p')?.remove();
      return;
    }

    const btn = e.target.closest('.bouton-validation-chasse');
    if (!btn) return;

    e.preventDefault();
    const form = btn.closest('form');
    if (!form) return;

    ouvrirModalConfirmation(form);
  });
});

function ouvrirModalConfirmation(form) {
  // Supprimer toute ancienne instance pour éviter les doublons
  document.querySelector('.modal-confirmation-validation-chasse')?.remove();

  const modal = document.createElement('div');
  modal.className = 'modal-confirmation-validation-chasse';
  const closeLabel = wp.i18n.__('Fermer', 'chassesautresor-com');
  const title = wp.i18n.__('Valider votre chasse au trésor', 'chassesautresor-com');
  const warning1 = wp.i18n.__(
    '⚠️ Avant d\u2019envoyer votre demande de validation, assurez-vous que votre chasse est complète et prête à être publiée.',
    'chassesautresor-com'
  );
  const warning2 = wp.i18n.__(
    '📌 Après validation, vous ne pourrez plus modifier ses paramètres.',
    'chassesautresor-com'
  );
  const certification = wp.i18n.__(
    'Je certifie que ma chasse et toutes ses énigmes sont finalisées.',
    'chassesautresor-com'
  );
  const send = wp.i18n.__('Envoyer la demande de validation', 'chassesautresor-com');

  modal.innerHTML = `
    <div class="modal-contenu">
      <button class="modal-close-top" aria-label="${closeLabel}">&times;</button>
      <h2>${title}</h2>
      <p>
        ${warning1}<br>
        ${warning2}
      </p>
      <label>
        <input type="checkbox" id="confirm-validation"> ${certification}
      </label>
      <div class="boutons-modal">
        <button class="bouton-cta confirmer-envoi" disabled>${send}</button>
      </div>
    </div>`;

  document.body.appendChild(modal);

  const checkbox = modal.querySelector('#confirm-validation');
  const confirmBtn = modal.querySelector('.confirmer-envoi');
  const closeBtn = modal.querySelector('.modal-close-top');

  checkbox.addEventListener('change', () => {
    confirmBtn.disabled = !checkbox.checked;
  });

  const fermer = () => modal.remove();
  closeBtn.addEventListener('click', fermer);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) fermer();
  });

  confirmBtn.addEventListener('click', () => {
    confirmBtn.disabled = true;

    const idInput = form.querySelector('input[name="chasse_id"]');
    const key = idInput ? `correction_chasse_${idInput.value}` : null;

    if (key) {
      const params = new URLSearchParams();
      params.set('action', 'cta_dismiss_message');
      params.set('key', key);

      const ajaxUrl =
        typeof ctaMyAccount !== 'undefined'
          ? ctaMyAccount.ajaxUrl
          : '/wp-admin/admin-ajax.php';

      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString(),
      }).catch(() => {});

      const btn = document.querySelector(`.msg-important .message-close[data-key="${key}"]`);
      const parent = btn ? btn.closest('p') : null;
      if (parent) {
        parent.remove();
      }
    }

    fermer();
    form.submit();
  });
}
