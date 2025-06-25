document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('input[type="date"], input[type="datetime-local"]')
    .forEach(initChampDate);
});



// ==============================
// 📅 Formatage des dates Y-m-d ➔ d/m/Y
// ==============================
function formatDateFr(dateStr) {
  if (!dateStr) return '';
  if (dateStr.includes('T')) {
    const [datePart, timePart] = dateStr.split('T');
    const parts = datePart.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[2]}/${parts[1]}/${parts[0]} ${timePart}`;
  }
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}


// ==============================
// 📅 Mise à jour affichage Date Fin
// ==============================
function mettreAJourAffichageDateFin() {
  const spanDateFin = document.querySelector('.chasse-date-plage .date-fin');
  const inputDateFin = document.getElementById('chasse-date-fin');
  const checkboxIllimitee = document.getElementById('duree-illimitee');

  if (!spanDateFin || !inputDateFin || !checkboxIllimitee) return;

  if (checkboxIllimitee.checked) {
    spanDateFin.textContent = 'Illimitée';
  } else {
    spanDateFin.textContent = formatDateFr(inputDateFin.value);
  }
}
// ==============================
// 📅 initChampDate
// ==============================
function initChampDate(input) {
  console.log('⏱️ Attachement initChampDate à', input, '→ ID:', input.id);

  if (input.disabled) {
    return;
  }

  const bloc = input.closest('[data-champ]');
  const champ = bloc?.dataset.champ;
  const postId = bloc?.dataset.postId;
  const cpt = bloc?.dataset.cpt || 'chasse';

  if (!champ || !postId) return;

  // 🕒 Pré-remplissage si vide
  if (!input.value && bloc.dataset.date) {
    const dateInit = bloc.dataset.date;
    if (/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/.test(dateInit)) {
      input.value = dateInit;
    }
  }

  input.addEventListener('change', () => {
    const valeurBrute = input.value.trim();
    console.log('[🧪 initChampDate]', champ, '| valeur saisie :', valeurBrute);
    const regexDate = /^\d{4}-\d{2}-\d{2}$/;
    const regexDateTime = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;
    if (!regexDate.test(valeurBrute) && !regexDateTime.test(valeurBrute)) {
      console.warn(`❌ Date invalide (${champ}) :`, valeurBrute);
      input.value = input.dataset.previous || '';
      return;
    }

    let valeur = valeurBrute;
    if (regexDateTime.test(valeurBrute)) {
      valeur = valeurBrute.replace('T', ' ') + ':00';
    }

    if (cpt === 'chasse' && typeof window.validerDatesAvantEnvoi === 'function') {
      let type = '';
      if (champ.endsWith('_date_debut')) type = 'debut';
      if (champ.endsWith('_date_fin')) type = 'fin';
      if (type && !window.validerDatesAvantEnvoi(type)) {
        input.value = input.dataset.previous || '';
        return;
      }
    }

    modifierChampSimple(champ, valeur, postId, cpt).then(success => {
      if (success) {
        input.dataset.previous = valeur;
        if (typeof window.onDateFieldUpdated === 'function') {
          window.onDateFieldUpdated(input, valeur);
        }
      } else {
        input.value = input.dataset.previous || '';
      }
    });
  });
  if (typeof window.onDateFieldUpdated === 'function') {
    const valeurInit = input.value?.trim() || ''; // 🔹 protection + fallback vide
    window.onDateFieldUpdated(input, valeurInit);
  }
  input.dataset.previous = input.value?.trim() || '';

}
