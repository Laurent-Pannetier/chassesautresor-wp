document.addEventListener('DOMContentLoaded', () => {
  // On cible de manière plus large les champs de date pour prendre en charge
  // les inputs générés dynamiquement ou ceux dont le type peut varier (text,
  // date, datetime-local...). L'important est qu'ils possèdent la classe
  // `.champ-date-edit`.
  document.querySelectorAll('input.champ-date-edit').forEach(initChampDate);
});




// ==============================
// 📅 Formatage des dates Y-m-d ➔ d/m/Y
// ==============================
function formatDateFr(dateStr) {
  if (!dateStr) return '';
  if (dateStr.includes('T')) {
    const [datePart] = dateStr.split('T');
    const parts = datePart.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

// ==============================
// 📅 Tentative de normalisation d'une valeur de champ date
// ==============================
function normaliserValeurDate(brute, type) {
  if (!brute) return '';

  const regexIsoDate = /^\d{4}-\d{2}-\d{2}$/;
  const regexIsoDateTime = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;
  if (regexIsoDate.test(brute) || regexIsoDateTime.test(brute)) {
    return brute;
  }

  const m = brute.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})\s*(am|pm))?$/i);
  if (m) {
    const day = m[1];
    const month = m[2];
    const year = m[3];
    let hour = '00';
    let minute = '00';
    if (m[4]) {
      hour = parseInt(m[4], 10);
      const mer = (m[6] || '').toLowerCase();
      if (mer === 'pm' && hour < 12) hour += 12;
      if (mer === 'am' && hour === 12) hour = 0;
      hour = String(hour).padStart(2, '0');
      minute = m[5];
    }
    if (type === 'datetime-local') {
      return `${year}-${month}-${day}T${hour}:${minute}`;
    }
    return `${year}-${month}-${day}`;
  }

  // Dernier recours : tentative via Date()
  const d = new Date(brute);
  if (!isNaN(d.getTime())) {
    const yyyy = d.getFullYear().toString().padStart(4, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    if (type === 'datetime-local') {
      const hh = String(d.getHours()).padStart(2, '0');
      const ii = String(d.getMinutes()).padStart(2, '0');
      return `${yyyy}-${mm}-${dd}T${hh}:${ii}`;
    }
    return `${yyyy}-${mm}-${dd}`;
  }

  return '';
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
    const norm = normaliserValeurDate(dateInit, input.type);
    if (norm) {
      input.value = norm;
    }
  } else if (input.value) {
    const norm = normaliserValeurDate(input.value, input.type);
    if (norm) {
      input.value = norm;
    }
  }

  const enregistrer = () => {
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
    if (regexDateTime.test(valeurBrute) && input.type === 'datetime-local') {
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

    if (
      cpt === 'chasse' &&
      (champ.endsWith('_date_debut') || champ.endsWith('_date_fin'))
    ) {
      if (typeof window.enregistrerDatesChasse === 'function') {
        window.enregistrerDatesChasse().then(success => {
          if (success) {
            input.dataset.previous = valeurBrute;
            if (typeof window.onDateFieldUpdated === 'function') {
              window.onDateFieldUpdated(input, valeurBrute);
            }
          } else {
            input.value = input.dataset.previous || '';
          }
        });
      } else {
        console.warn('enregistrerDatesChasse non disponible');
        modifierChampSimple(champ, valeur, postId, cpt).then(success => {
          if (success) {
            input.dataset.previous = valeurBrute;
            if (typeof window.onDateFieldUpdated === 'function') {
              window.onDateFieldUpdated(input, valeurBrute);
            }
          } else {
            input.value = input.dataset.previous || '';
          }
        });
      }
    } else {
      modifierChampSimple(champ, valeur, postId, cpt).then(success => {
        if (success) {
          input.dataset.previous = valeurBrute;
          if (typeof window.onDateFieldUpdated === 'function') {
            window.onDateFieldUpdated(input, valeurBrute);
          }
        } else {
          input.value = input.dataset.previous || '';
        }
      });
    }
  };

  input.addEventListener('change', enregistrer);

  // Certains navigateurs ne déclenchent pas toujours l'évènement "change" après
  // sélection dans le datepicker. On ajoute donc un fallback sur "blur" si la
  // valeur a effectivement été modifiée.
  input.addEventListener('blur', () => {
    if (input.value.trim() !== (input.dataset.previous || '')) {
      enregistrer();
    }
  });

  if (typeof window.onDateFieldUpdated === 'function') {
    const valeurInit = input.value?.trim() || ''; // 🔹 protection + fallback vide
    window.onDateFieldUpdated(input, valeurInit);
  }
  input.dataset.previous = input.value?.trim() || '';

}
