document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="date"]').forEach(initChampDate);
});



// ==============================
// 📅 Formatage des dates Y-m-d ➔ d/m/Y
// ==============================
function formatDateFr(dateStr) {
  if (!dateStr) return '';
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

  const bloc = input.closest('[data-champ]');
  const champ = bloc?.dataset.champ;
  const postId = bloc?.dataset.postId;
  const cpt = bloc?.dataset.cpt || 'chasse';

  if (!champ || !postId) return;

  // 🕒 Pré-remplissage si vide
  if (!input.value && bloc.dataset.date) {
    const dateInit = bloc.dataset.date;
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateInit)) {
      input.value = dateInit;
    }
  }

  input.addEventListener('change', () => {
    const valeur = input.value.trim();
    console.log('[🧪 initChampDate]', champ, '| valeur saisie :', valeur);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(valeur)) {
      console.warn(`❌ Date invalide (${champ}) :`, valeur);
      return;
    }

    const action = (cpt === 'chasse') ? 'modifier_champ_chasse' :
      (cpt === 'enigme') ? 'modifier_champ_enigme' :
        'modifier_champ_organisateur';

    console.log('📤 Envoi AJAX date', { champ, valeur, postId });

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action,
        champ,
        valeur,
        post_id: postId
      })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          console.log('[initChampDate] Hook onDateFieldUpdated =', typeof window.onDateFieldUpdated);

          if (typeof window.onDateFieldUpdated === 'function') {
            console.log('[initChampDate] Appel de onDateFieldUpdated() avec valeur =', valeur);

            window.onDateFieldUpdated(input, valeur);
          }
        } else {
          console.warn('❌ Erreur serveur (date)', res.data || res);
        }
      })
      .catch(err => {
        console.error('❌ Erreur réseau (date)', err);
      });
  });
  if (typeof window.onDateFieldUpdated === 'function') {
    const valeurInit = input.value?.trim() || ''; // 🔹 protection + fallback vide
    window.onDateFieldUpdated(input, valeurInit);
  }
  input.dataset.previous = input.value?.trim() || '';

}
