// ========================================
// 📁 header-organisateur-ui.js
// Gère les interactions visuelles du header organisateur :
// - Sliders édition 
// - Affichage du modal de description
// - Panneau latéral ACF (présentation)
// ========================================

document.addEventListener('DOMContentLoaded', () => {

  // ✅ Modal description : ouverture et fermeture
  const descriptionModal = document.getElementById('description-modal');
  document.querySelector('.header-organisateur__voir-plus')?.addEventListener('click', () => {
    descriptionModal?.classList.remove('masque');
  });
  document.querySelector('#description-modal .description-modal__close')?.addEventListener('click', () => {
    descriptionModal?.classList.add('masque');
    document.activeElement?.blur();
  });

  // ✅ Panneau latéral ACF – ouverture (bouton déclencheur)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-description');
    if (!btn) return;

    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-description');
    }
  });

  // ❌ Panneau latéral ACF – fermeture (croix)
  document.querySelector('#panneau-description .panneau-fermer')?.addEventListener('click', () => {
    if (typeof window.closePanel === 'function') {
      window.closePanel('panneau-description');
      document.activeElement?.blur();
    }
  });
});
