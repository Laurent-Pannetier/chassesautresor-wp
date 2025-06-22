// ========================================
// 📁 header-organisateur-ui.js
// Gère les interactions visuelles du header organisateur :
// - Sliders édition 
// - Navigation à onglets (chasses / présentation / contact)
// - Panneau latéral ACF (présentation)
// ========================================

document.addEventListener('DOMContentLoaded', () => {

  // ✅ Nav menu : clic sur #presentation => affichage section
  document.querySelectorAll('.header-organisateur__menu a[href^="#"]').forEach((lien) => {
    lien.addEventListener('click', () => {
      const target = lien.getAttribute('href');
      const presentation = document.getElementById('presentation');

      // Section à afficher / cacher
      if (target === '#presentation') {
        presentation?.classList.remove('masque');
      } else {
        presentation?.classList.add('masque');
      }

      // Liens actifs
      document.querySelectorAll('.header-organisateur__menu li').forEach((li) => li.classList.remove('active'));
      lien.closest('li')?.classList.add('active');
    });
  });

  // ✅ Hash auto (si présentation dans l’URL)
  if (window.location.hash === '#presentation') {
    document.getElementById('presentation')?.classList.remove('masque');
    const liPresentation = document.querySelector('.onglet-presentation');
    if (liPresentation) {
      document.querySelectorAll('.header-organisateur__menu li').forEach(li => li.classList.remove('active'));
      liPresentation.classList.add('active');
    }
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
