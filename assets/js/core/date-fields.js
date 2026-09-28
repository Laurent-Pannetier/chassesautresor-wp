document.addEventListener('DOMContentLoaded', () => {
  // On cible de manière plus large les champs de date pour prendre en charge
  // les inputs générés dynamiquement ou ceux dont le type peut varier (text,
  // date, datetime-local...). L'important est qu'ils possèdent la classe
  // `.champ-date-edit`.
  document.querySelectorAll('input.champ-date-edit').forEach(initChampDate);
  initResponsiveDateVisibility();
});




// ==============================
// 📅 Formatage des dates avec Intl.DateTimeFormat (fr-FR)
// ==============================
function formatDateFr(dateStr) {
  console.log('[formatDateFr] input=', dateStr);
  if (!dateStr) return '';

  // Conversion en objet Date (gestion des espaces ou du "T")
  const parsed = new Date(dateStr.replace(' ', 'T'));
  if (Number.isNaN(parsed.getTime())) return dateStr;

  return parsed.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
}

function getPreferredLocale() {
  const wpLocale = (window.catI18n && window.catI18n.locale) ? String(window.catI18n.locale) : '';
  const docLang = (document.documentElement && document.documentElement.lang) ? document.documentElement.lang : '';
  const navLang = (navigator && (navigator.language || (navigator.languages && navigator.languages[0])));
  return wpLocale || docLang || navLang || 'fr-FR';
}

function formatHuntDateShort(dateStr) {
  if (!dateStr) return '';
  const parsed = new Date(String(dateStr).replace(' ', 'T'));
  if (Number.isNaN(parsed.getTime())) return dateStr;

  const locale = getPreferredLocale();
  try {
    return parsed.toLocaleDateString(locale, {
      day: '2-digit',
      month: '2-digit',
      year: '2-digit'
    });
  } catch (error) {
    return parsed.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: '2-digit'
    });
  }
}

function normalizeDateText(value, fallback) {
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed !== '') {
      return trimmed;
    }
  }

  if (typeof fallback === 'string') {
    const fallbackTrimmed = fallback.trim();
    if (fallbackTrimmed !== '') {
      return fallbackTrimmed;
    }
  }

  return '';
}

function updateHuntDateDisplay(element, options = {}) {
  if (!element) return;

  const currentLong = element.dataset?.dateLong || '';
  const currentShort = element.dataset?.dateShort || currentLong;
  const longText = normalizeDateText(options.longText, currentLong);
  const shortText = normalizeDateText(options.shortText, currentShort || longText);

  element.dataset.dateLong = longText;
  element.dataset.dateShort = shortText;

  const shortNode = element.querySelector('.date-short');
  const longNode = element.querySelector('.date-long');

  if (shortNode) {
    shortNode.textContent = shortText;
  }

  if (longNode) {
    longNode.textContent = longText;
  }

  if (!shortNode && !longNode) {
    element.textContent = longText;
  }

  applyResponsiveDateVisibility(element);
}

const DATE_FORMAT_MEDIA_QUERY = '(min-width: 1024px)';
let dateFormatMediaMatcher = null;

function handleDateFormatMediaChange() {
  applyResponsiveDateVisibility();
}

function ensureDateFormatMediaMatcher() {
  if (dateFormatMediaMatcher || typeof window.matchMedia !== 'function') {
    return dateFormatMediaMatcher;
  }

  dateFormatMediaMatcher = window.matchMedia(DATE_FORMAT_MEDIA_QUERY);

  if (typeof dateFormatMediaMatcher.addEventListener === 'function') {
    dateFormatMediaMatcher.addEventListener('change', handleDateFormatMediaChange);
  } else if (typeof dateFormatMediaMatcher.addListener === 'function') {
    dateFormatMediaMatcher.addListener(handleDateFormatMediaChange);
  }

  return dateFormatMediaMatcher;
}

function isDesktopForDateFormatting() {
  const matcher = ensureDateFormatMediaMatcher();
  return matcher ? matcher.matches : false;
}

function toggleDateFormatNodes(element, isDesktop) {
  if (!(element instanceof Element)) {
    return;
  }

  const shortNode = element.querySelector('.date-short');
  const longNode = element.querySelector('.date-long');

  if (shortNode) {
    if (isDesktop) {
      shortNode.hidden = true;
      shortNode.setAttribute('aria-hidden', 'true');
    } else {
      shortNode.hidden = false;
      shortNode.setAttribute('aria-hidden', 'false');
    }
  }

  if (longNode) {
    if (isDesktop) {
      longNode.hidden = false;
      longNode.setAttribute('aria-hidden', 'false');
    } else {
      longNode.hidden = true;
      longNode.setAttribute('aria-hidden', 'true');
    }
  }

  if (!shortNode && !longNode && element.dataset) {
    const dataset = element.dataset;
    const fallbackText = isDesktop
      ? normalizeDateText(dataset.dateLong, dataset.dateShort)
      : normalizeDateText(dataset.dateShort, dataset.dateLong);

    if (fallbackText !== '') {
      element.textContent = fallbackText;
    }
  }
}

function applyResponsiveDateVisibility(context) {
  const isDesktop = isDesktopForDateFormatting();

  if (context instanceof Element) {
    toggleDateFormatNodes(context, isDesktop);
    return;
  }

  document
    .querySelectorAll('.chasse-date-plage .date-debut, .chasse-date-plage .date-fin')
    .forEach((element) => {
      toggleDateFormatNodes(element, isDesktop);
    });
}

function initResponsiveDateVisibility() {
  ensureDateFormatMediaMatcher();
  applyResponsiveDateVisibility();
}

function getUnlimitedLabel() {
  return (window.catI18n && window.catI18n.texts && window.catI18n.texts.unlimited)
    ? window.catI18n.texts.unlimited
    : 'Illimitée';
}

function isChasseUnlimited() {
  const toggleLimitee = document.getElementById('date-fin-limitee');
  const checkboxIllimitee = document.getElementById('duree-illimitee');

  if (toggleLimitee && checkboxIllimitee) {
    return !toggleLimitee.checked || checkboxIllimitee.checked;
  }

  if (toggleLimitee) {
    return !toggleLimitee.checked;
  }

  if (checkboxIllimitee) {
    return checkboxIllimitee.checked;
  }

  return false;
}

window.formatHuntDateShort = formatHuntDateShort;
window.catUpdateHuntDateDisplay = updateHuntDateDisplay;



// ==============================
// 📅 Mise à jour affichage Date Fin
// ==============================
function mettreAJourAffichageDateFin() {
  console.log('[mettreAJourAffichageDateFin]');
  const spanDateFin = document.querySelector('.chasse-date-plage .date-fin');
  const inputDateFin = document.getElementById('chasse-date-fin');
  if (!spanDateFin) return;

  if (isChasseUnlimited()) {
    const unlimitedLabel = getUnlimitedLabel();
    updateHuntDateDisplay(spanDateFin, {
      longText: unlimitedLabel,
      shortText: unlimitedLabel
    });
    return;
  }

  if (!inputDateFin || !inputDateFin.value) {
    updateHuntDateDisplay(spanDateFin, {
      longText: '',
      shortText: ''
    });
    return;
  }

  const formattedLong = (typeof window.formatDateLocalized === 'function')
    ? window.formatDateLocalized(inputDateFin.value)
    : formatDateFr(inputDateFin.value);
  const formattedShort = window.formatHuntDateShort
    ? window.formatHuntDateShort(inputDateFin.value)
    : formatHuntDateShort(inputDateFin.value);

  updateHuntDateDisplay(spanDateFin, {
    longText: formattedLong,
    shortText: formattedShort || formattedLong
  });
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

  let status = bloc?.querySelector('.champ-status');
  if (!status) {
    status = document.createElement('span');
    status.className = 'champ-status';
    input.insertAdjacentElement('afterend', status);
  }

  // 🕒 Pré-remplissage si vide
  if (!input.value && bloc.dataset.date) {
    const dateInit = bloc.dataset.date;
    if (/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/.test(dateInit)) {
      input.value = dateInit;
    }
  }

  let saving = false;

  const enregistrer = () => {
    if (saving) return;
    saving = true;
    const valeurBrute = input.value.trim();
    console.log('[🧪 initChampDate]', champ, '| valeur saisie :', valeurBrute, '| previous :', input.dataset.previous);
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

    if (status) {
      status.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
    }

    const afterSave = success => {
      saving = false;
      if (success) {
        if (status) {
          status.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
          setTimeout(() => { status.innerHTML = ''; }, 1000);
        }
        input.dataset.previous = valeurBrute;
        if (typeof window.onDateFieldUpdated === 'function') {
          window.onDateFieldUpdated(input, valeurBrute);
        }
      } else {
        if (status) status.innerHTML = '';
        input.value = input.dataset.previous || '';
      }
    };

    if (
      cpt === 'chasse' &&
      typeof window.enregistrerDatesChasse === 'function' &&
      (champ.endsWith('_date_debut') || champ.endsWith('_date_fin'))
    ) {
      console.log('[initChampDate] appel enregistrerDatesChasse pour', champ);
      window.enregistrerDatesChasse().then(afterSave);
    } else {
      modifierChampSimple(champ, valeur, postId, cpt).then(afterSave);
    }
  };

  input.addEventListener('change', enregistrer);

  // Certains navigateurs ne déclenchent pas toujours l'évènement "change" après
  // sélection dans le datepicker. On ajoute donc un fallback sur "blur" si la
  // valeur a effectivement été modifiée.
  input.addEventListener('blur', () => {
    if (saving) return;
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

// === Helpers i18n pour formatage de dates (sans dépendre d'autres modules)
(function(){
  if (typeof window.formatDateLocalized === 'function') return; // déjà défini
  function getPreferredLocale(){
    const wpLocale = (window.catI18n && window.catI18n.locale) ? String(window.catI18n.locale) : '';
    const docLang = (document.documentElement && document.documentElement.lang) ? document.documentElement.lang : '';
    const navLang = (navigator && (navigator.language || (navigator.languages && navigator.languages[0]))) || '';
    return (wpLocale || docLang || navLang || 'fr-FR');
  }
  function formatDateLocalized(dateStr){
    if (!dateStr) return '';
    const parsed = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return dateStr;
    const locale = getPreferredLocale();
    try {
      return parsed.toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' });
    } catch(e) {
      return parsed.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    }
  }
  window.formatDateLocalized = formatDateLocalized;
})();

// Remplace la logique d'affichage de la date de fin pour respecter la locale
(function(){
  const override = function(){
    const spanDateFin = document.querySelector('.chasse-date-plage .date-fin');
    const inputDateFin = document.getElementById('chasse-date-fin');
    const toggleLimitee = document.getElementById('date-fin-limitee');
    if (!spanDateFin || !toggleLimitee) return;
    const txtUnlimited = (window.catI18n && window.catI18n.texts && window.catI18n.texts.unlimited)
      ? window.catI18n.texts.unlimited
      : 'Illimitée';
    if (!toggleLimitee.checked) {
      spanDateFin.textContent = txtUnlimited;
    } else if (inputDateFin) {
      spanDateFin.textContent = (typeof window.formatDateLocalized === 'function')
        ? window.formatDateLocalized(inputDateFin.value)
        : inputDateFin.value;
    }
  };
  window.mettreAJourAffichageDateFin = override;
})();

const previousOnDateFieldUpdated = window.onDateFieldUpdated;
window.onDateFieldUpdated = function(input, valeur) {
  if (typeof previousOnDateFieldUpdated === 'function') {
    previousOnDateFieldUpdated(input, valeur);
  }

  const champ = input?.closest('[data-champ]')?.dataset?.champ;
  if (!champ) return;

  if (champ === 'chasse_infos_date_debut') {
    const spanDateDebut = document.querySelector('.chasse-date-plage .date-debut');
    if (!spanDateDebut) return;

    const formattedLong = valeur
      ? ((typeof window.formatDateLocalized === 'function')
        ? window.formatDateLocalized(valeur)
        : formatDateFr(valeur))
      : '';
    const formattedShort = valeur
      ? (window.formatHuntDateShort
        ? window.formatHuntDateShort(valeur)
        : formatHuntDateShort(valeur))
      : '';

    updateHuntDateDisplay(spanDateDebut, {
      longText: formattedLong,
      shortText: formattedShort || formattedLong
    });
  } else if (champ === 'chasse_infos_date_fin') {
    mettreAJourAffichageDateFin();
  }
};
