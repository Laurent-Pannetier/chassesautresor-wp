// Fonctions utilitaires UI

/** Ouvre le panneau latéral spécifié par son ID */
window.openPanel = function(id) {
  const panel = document.getElementById(id);
  if (!panel) return;

  document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach(p => {
    p.classList.remove('ouvert');
    p.setAttribute('aria-hidden', 'true');
  });

  panel.classList.add('ouvert');
  document.body.classList.add('panneau-ouvert');
  panel.setAttribute('aria-hidden', 'false');
};

/** Ferme le panneau latéral spécifié par son ID */
window.closePanel = function(id) {
  const panel = document.getElementById(id);
  if (!panel) return;

  panel.classList.remove('ouvert');
  document.body.classList.remove('panneau-ouvert');
  panel.setAttribute('aria-hidden', 'true');
};
