// ========================================
// 📁 header-organisateur-ui.js
// Gère les interactions visuelles du header organisateur :
// - Sliders édition
// - Affichage/masquage de la description via l’icône info
// - Panneau latéral ACF (présentation)
// ========================================

var DEBUG = window.DEBUG || false;
DEBUG && console.log('✅ header-organisateur-ui.js chargé');

function initHeaderOrganisateurUI() {
  DEBUG && console.log('[header-organisateur-ui] init');

  // ✅ Icône info : affichage/masquage de la description
  document.querySelector('.bouton-toggle-description')?.addEventListener('click', () => {
    DEBUG && console.log('[header-organisateur-ui] toggle description');
    const presentation = document.getElementById('presentation');
    presentation?.classList.toggle('masque');
  });

  // ✅ Hash auto (si présentation dans l’URL)
  if (window.location.hash === '#presentation') {
    document.getElementById('presentation')?.classList.remove('masque');
  }

  // ❌ Bouton de fermeture de la présentation
  document.querySelector('#presentation .presentation-fermer')?.addEventListener('click', () => {
    DEBUG && console.log('[header-organisateur-ui] fermeture presentation');
    document.getElementById('presentation')?.classList.add('masque');
    document.activeElement?.blur();
  });

  // ✅ Panneau latéral ACF – ouverture (bouton déclencheur)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-description');
    if (!btn) return;

    DEBUG && console.log('[header-organisateur-ui] ouverture panneau description');
    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-description');
    }
  });

  // ❌ Panneau latéral ACF – fermeture (croix)
  document.querySelector('#panneau-description .panneau-fermer')?.addEventListener('click', () => {
    DEBUG && console.log('[header-organisateur-ui] fermeture panneau description');
    if (typeof window.closePanel === 'function') {
      window.closePanel('panneau-description');
      document.activeElement?.blur();
    }
  });
}

if (document.readyState === 'loading') {
  DEBUG && console.log('[header-organisateur-ui] waiting DOMContentLoaded');
  document.addEventListener('DOMContentLoaded', () => {
    DEBUG && console.log('[header-organisateur-ui] DOMContentLoaded');
    initHeaderOrganisateurUI();
  });
} else {
  initHeaderOrganisateurUI();
}
