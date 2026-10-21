// Sur ordinateur, le survol ouvre la liste des éléments manquants.
// Le clic reste géré par l'attribut popover, y compris sur écran tactile.

(function () {
  function bind(button) {
    const id = button.getAttribute('popovertarget');
    const popover = id ? document.getElementById(id) : null;
    if (!popover || typeof popover.showPopover !== 'function') return;

    button.addEventListener('mouseenter', () => {
      if (!popover.matches(':popover-open')) {
        popover.showPopover();
      }
    });
    button.addEventListener('mouseleave', () => {
      if (popover.matches(':popover-open')) {
        popover.hidePopover();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document
      .querySelectorAll('.app-nav__marker--incomplete, .hunt-lifecycle-card__gaps')
      .forEach(bind);
  });
})();
