/**
 * Gestion du bouton "Valider ma chasse".
 * Une fenêtre de confirmation est affichée avant l'envoi réel du formulaire.
 */

document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
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
  modal.innerHTML = `
    <div class="modal-contenu">
      <button class="modal-close-top" aria-label="Fermer">&times;</button>
      <p>
        ⚠️ <strong>En cochant cette case, je certifie avoir finalisé la création de cette chasse et de toutes ses énigmes.</strong><br>
        📌 <strong>Une fois la demande envoyée, aucune modification ne sera possible.</strong>
      </p>
      <label>
        <input type="checkbox" id="confirm-validation"> Je certifie avoir finalisé la création de cette chasse et de toutes ses énigmes.
      </label>
      <div class="boutons-modal">
        <button class="bouton-cta confirmer-envoi" disabled>Confirmer l'envoi de la demande</button>
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
    fermer();
    form.submit();
  });
}
