// Gestion des onglets pour les panneaux d'édition modaux

(function() {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edition-tabs').forEach(tabs => {
      const container = tabs.closest('.edition-panel-modal');
      if (!container) return;
      const buttons = tabs.querySelectorAll('.edition-tab');
      buttons.forEach(btn => {
        btn.addEventListener('click', () => {
          const target = btn.dataset.target;
          if (!target) return;
          buttons.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          container.querySelectorAll('.edition-tab-content').forEach(content => {
            if (content.id === target) {
              content.style.display = '';
              content.classList.add('active');
            } else {
              content.style.display = 'none';
              content.classList.remove('active');
            }
          });
        });
      });
    });
  });
})();
