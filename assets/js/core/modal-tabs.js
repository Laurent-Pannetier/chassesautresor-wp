// Onglets des écrans d'édition. Sur le dashboard, l'onglet actif reste dans l'adresse.

(function() {
  const sectionTargets = {
    parametres: ['enigme-tab-param', 'chasse-tab-param', 'organisateur-tab-param'],
    stats: ['enigme-tab-stats', 'chasse-tab-stats', 'organisateur-tab-stats'],
    animation: ['enigme-tab-animation', 'chasse-tab-animation', 'organisateur-tab-animation'],
    tentatives: ['enigme-tab-soumission'],
  };

  function sectionForTarget(target) {
    return Object.keys(sectionTargets).find((section) => sectionTargets[section].includes(target)) || '';
  }

  function activateTab(container, buttons, button, syncUrl) {
    const target = button.dataset.target;
    if (!target || button.style.display === 'none') return;
    buttons.forEach((item) => item.classList.remove('active'));
    button.classList.add('active');
    container.querySelectorAll('.edition-tab-content').forEach((content) => {
      const selected = content.id === target;
      content.style.display = selected ? '' : 'none';
      content.classList.toggle('active', selected);
    });
    if (!syncUrl) return;
    const section = sectionForTarget(target);
    const shell = document.querySelector('.shell-edition');
    if (!section || !shell) return;
    const url = new URL(window.location.href);
    url.searchParams.set('section', section);
    window.history.replaceState({}, '', url);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.shell-edition');
    document.querySelectorAll('.edition-tabs').forEach((tabs) => {
      const container = tabs.closest('.edition-panel-modal') || tabs.closest('.edition-panel');
      if (!container) return;
      const buttons = Array.from(tabs.querySelectorAll('.edition-tab'));
      buttons.forEach((button) => {
        button.addEventListener('click', () => {
          activateTab(container, buttons, button, true);
        });
      });

      if (!shell) return;
      const wanted = sectionTargets[shell.dataset.editionSection] || [];
      const initial = buttons.find(
        (button) => wanted.includes(button.dataset.target) && button.style.display !== 'none'
      );
      if (initial) {
        activateTab(container, buttons, initial, false);
      }
    });
    document.body.classList.add('shell-edition-tabs-ready');
  });
})();
