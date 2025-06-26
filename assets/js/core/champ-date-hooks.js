// ✅ Hook JS global pour l'affichage dynamique des champs date
// À inclure après champ-init.js et avant les fichiers CPT (enigme-edit.js, chasse-edit.js...)
var DEBUG = window.DEBUG || false;

window.onDateFieldUpdated = function(input, valeur) {
  console.log('[onDateFieldUpdated]', input, valeur);
  const champ = input.closest('[data-champ]')?.dataset.champ;
  if (!champ) return;

  const handlers = {
    'enigme_acces_date': (val) => {
      const span = document.querySelector('.date-deblocage');
      if (span) span.textContent = formatDateFr(val);
    },
    'chasse_infos_date_debut': (val) => {
      const span = document.querySelector('.date-debut');
      if (span) span.textContent = formatDateFr(val);
    },
    'chasse_infos_date_fin': (val) => {
      const span = document.querySelector('.date-fin');
      if (span) span.textContent = formatDateFr(val);
    }
    // Ajoutez ici d'autres handlers spécifiques si nécessaire
  };

  if (handlers[champ]) {
    handlers[champ](valeur);
  } else {
    DEBUG && console.log(`[onDateFieldUpdated] Aucun handler défini pour : ${champ}`);
  }
};
