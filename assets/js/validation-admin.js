document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.form-traitement-validation-chasse .btn-correction');
    if (!btn) return;

    e.preventDefault();
    const form = btn.closest('form');
    if (!form) return;

    ouvrirModalCorrection(form);
  });
});

function ouvrirModalCorrection(form) {
  document.querySelector('.modal-correction-chasse')?.remove();

  const modal = document.createElement('div');
  modal.className = 'modal-correction-chasse';
  modal.innerHTML = `
    <div class="modal-contenu">
      <button class="modal-close-top" aria-label="Fermer">&times;</button>
      <h2>Demande de correction</h2>
      <textarea class="message-correction" rows="5" placeholder="Message pour l'organisateur"></textarea>
      <div class="boutons-modal">
        <button class="valider-correction">Valider</button>
        <button class="annuler-correction">Annuler</button>
      </div>
    </div>`;

  document.body.appendChild(modal);

  const fermer = () => modal.remove();

  modal.querySelector('.modal-close-top').addEventListener('click', fermer);
  modal.querySelector('.annuler-correction').addEventListener('click', fermer);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) fermer();
  });

  modal.querySelector('.valider-correction').addEventListener('click', () => {
    const message = modal.querySelector('.message-correction').value;
    const inputAction = document.createElement('input');
    inputAction.type = 'hidden';
    inputAction.name = 'validation_admin_action';
    inputAction.value = 'correction';
    form.appendChild(inputAction);

    const inputMsg = document.createElement('input');
    inputMsg.type = 'hidden';
    inputMsg.name = 'validation_admin_message';
    inputMsg.value = message;
    form.appendChild(inputMsg);

    fermer();
    form.submit();
  });
}
