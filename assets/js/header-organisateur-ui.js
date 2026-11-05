// ========================================
// 📁 header-organisateur-ui.js
// Affiche le modal de description du header organisateur.
// ========================================

document.addEventListener('DOMContentLoaded', () => {
  const descriptionModal = document.getElementById('description-modal');
  document.querySelector('.header-organisateur__voir-plus')?.addEventListener('click', () => {
    descriptionModal?.classList.remove('masque');
  });
  document.querySelector('#description-modal .description-modal__close')?.addEventListener('click', () => {
    descriptionModal?.classList.add('masque');
    document.activeElement?.blur();
  });
});
