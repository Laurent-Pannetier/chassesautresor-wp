// Affiche un message lors du tap sur les métadonnées
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.meta-indic[data-tap]').forEach(btn => {
    const msg = btn.dataset.tap;
    if (!msg) {
      return;
    }
    btn.addEventListener('click', () => {
      alert(msg);
    });
  });
});
