// ========================================
// 📁 header-organisateur-ui.js
// Gère les interactions visuelles du header organisateur :
// - Sliders édition 
// - Affichage/masquage de la description via l’icône info
// - Panneau latéral ACF (présentation)
// ========================================

document.addEventListener('DOMContentLoaded', () => {

  // ✅ Icône info : affichage/masquage de la description
  document.querySelector('.bouton-toggle-description')?.addEventListener('click', () => {
    const presentation = document.getElementById('presentation');
    presentation?.classList.toggle('masque');
  });

  // ✅ Hash auto (si présentation dans l’URL)
  if (window.location.hash === '#presentation') {
    document.getElementById('presentation')?.classList.remove('masque');
  }

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
