document.querySelectorAll('.accordeon-bloc').forEach(bloc => {
  const toggle = bloc.querySelector('.accordeon-toggle');
  const contenu = bloc.querySelector('.accordeon-contenu');

  if (!toggle || !contenu) return;

  // Synchronise l’état initial
  const estOuvert = toggle.getAttribute('aria-expanded') === 'true';
  contenu.classList.toggle('accordeon-ferme', !estOuvert);

  toggle.addEventListener('click', () => {
    const estActuellementOuvert = toggle.getAttribute('aria-expanded') === 'true';

    // Ferme tous les autres blocs
    document.querySelectorAll('.accordeon-bloc').forEach(otherBloc => {
      const otherToggle = otherBloc.querySelector('.accordeon-toggle');
      const otherContenu = otherBloc.querySelector('.accordeon-contenu');

      if (!otherToggle || !otherContenu) return;

      otherToggle.setAttribute('aria-expanded', 'false');
      otherContenu.classList.add('accordeon-ferme');
    });

    // Ouvre uniquement si ce n’était pas déjà ouvert
    if (!estActuellementOuvert) {
      toggle.setAttribute('aria-expanded', 'true');
      contenu.classList.remove('accordeon-ferme');
    }
  });
});
