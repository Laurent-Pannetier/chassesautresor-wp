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
      <h2>Valider votre chasse au trésor</h2>
      <p>
        ⚠️ Avant d\u2019envoyer votre demande de validation, assurez-vous que votre chasse est complète et prête à être publiée.<br>
        📌 Après validation, vous ne pourrez plus modifier ses paramètres.
      </p>
      <label>
        <input type="checkbox" id="confirm-validation"> Je certifie que ma chasse et toutes ses énigmes sont finalisées.
      </label>
      <div class="boutons-modal">
        <button class="bouton-cta confirmer-envoi" disabled>Envoyer la demande de validation</button>
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
