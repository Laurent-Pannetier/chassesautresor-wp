function initIndicesOptions(card) {
  if (!card) return;
  const btn = card.querySelector('.cta-indice-pour');
  const options = card.querySelector('.cta-indice-options');
  if (!btn || !options) return;

  let timeoutId;

  function hide() {
    card.classList.remove('show-options');
    if (timeoutId) {
      clearTimeout(timeoutId);
      timeoutId = null;
    }
  }

  function show() {
    card.classList.add('show-options');
    if (timeoutId) clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      hide();
    }, 5000);
  }

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    show();
  });

  options.addEventListener('click', () => {
    hide();
  });
}

function initAllIndicesOptions() {
  document.querySelectorAll('.dashboard-card.champ-indices').forEach((c) => {
    initIndicesOptions(c);
  });
}

function initSolutionsOptions(card) {
  if (!card) return;
  const btn = card.querySelector('.cta-solution-pour');
  const options = card.querySelector('.cta-solution-options');
  if (!btn || !options) return;

  let timeoutId;

  function hide() {
    card.classList.remove('show-options');
    if (timeoutId) {
      clearTimeout(timeoutId);
      timeoutId = null;
    }
  }

  function show() {
    card.classList.add('show-options');
    if (timeoutId) clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      hide();
    }, 5000);
  }

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    show();
  });

  options.addEventListener('click', () => {
    hide();
  });
}

function initAllSolutionsOptions() {
  document
    .querySelectorAll('.dashboard-card.champ-solutions')
    .forEach((c) => {
      initSolutionsOptions(c);
    });
  initDisabledSolutionButtons();
}

function initDisabledSolutionButtons() {
  document
    .querySelectorAll('.cta-solution-chasse, .cta-solution-enigme')
    .forEach((btn) => {
      if (btn.dataset.scrollBound) return;
      btn.dataset.scrollBound = '1';
      btn.addEventListener('click', (e) => {
        if (!btn.classList.contains('disabled')) return;
        e.preventDefault();
        const targetId =
          (ChasseSolutions && ChasseSolutions.scrollTarget) ||
          '#chasse-section-solutions';
        const anchor = document.querySelector(targetId);
        if (anchor) {
          anchor.scrollIntoView({ behavior: 'smooth' });
        }
      });
    });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAllIndicesOptions);
  document.addEventListener('DOMContentLoaded', initAllSolutionsOptions);
} else {
  initAllIndicesOptions();
  initAllSolutionsOptions();
}

