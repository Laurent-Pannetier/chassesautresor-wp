// ✅ Hook JS global pour l'affichage dynamique des champs date
// À inclure après champ-init.js et avant les fichiers CPT (enigme-edit.js, chasse-edit.js...)
var DEBUG = window.DEBUG || false;

window.onDateFieldUpdated = function(input, valeur) {
  console.log('[onDateFieldUpdated]', input, valeur);
  const champ = input.closest('[data-champ]')?.dataset.champ;
  if (!champ) return;

  const updateDisplay = (element, longText, shortText) => {
    if (!element) return;
    if (typeof window.catUpdateHuntDateDisplay === 'function') {
      window.catUpdateHuntDateDisplay(element, {
        longText,
        shortText
      });
    } else {
      element.textContent = longText;
    }
  };

  const formatLocalized = (val) => (
    (typeof window.formatDateLocalized === 'function')
      ? window.formatDateLocalized(val)
      : val
  );

  const formatShort = (val) => (
    (typeof window.formatHuntDateShort === 'function')
      ? window.formatHuntDateShort(val)
      : val
  );

  const handlers = {
    'enigme_acces_date': (val) => {
      const span = document.querySelector('.date-deblocage');
      if (span) span.textContent = (typeof window.formatDateLocalized === 'function') ? window.formatDateLocalized(val) : val;
    },
    'chasse_infos_date_debut': (val) => {
      const span = document.querySelector('.date-debut');
      if (!span) return;
      updateDisplay(span, formatLocalized(val), formatShort(val));
    },
    'chasse_infos_date_fin': (val) => {
      const span = document.querySelector('.date-fin');
      if (!span) return;

      const checkboxIllimitee = document.getElementById('duree-illimitee');
      const toggleLimitee = document.getElementById('date-fin-limitee');
      const isUnlimited = (toggleLimitee && !toggleLimitee.checked)
        || (checkboxIllimitee && checkboxIllimitee.checked);

      if (isUnlimited) {
        const txtUnlimited = (window.catI18n && window.catI18n.texts && window.catI18n.texts.unlimited)
          ? window.catI18n.texts.unlimited
          : 'Illimitée';
        updateDisplay(span, txtUnlimited, txtUnlimited);
      } else {
        updateDisplay(span, formatLocalized(val), formatShort(val));
      }
    }
    // Ajoutez ici d'autres handlers spécifiques si nécessaire
  };

  if (handlers[champ]) {
    handlers[champ](valeur);
  } else {
    DEBUG && console.log(`[onDateFieldUpdated] Aucun handler défini pour : ${champ}`);
  }
};
