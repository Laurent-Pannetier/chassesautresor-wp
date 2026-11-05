var DEBUG = window.DEBUG || false;
DEBUG && console.log('✅ organisateur-edit.js chargé');

document.addEventListener('DOMContentLoaded', () => {
  if (typeof initZonesClicEdition === 'function') initZonesClicEdition();

  // ==============================
  // 🟢 Initialisation des champs
  // ==============================
  document.querySelectorAll('.champ-organisateur[data-champ]').forEach((bloc) => {
    const champ = bloc.dataset.champ;

    if (bloc.classList.contains('champ-img')) {
      if (typeof initChampImage === 'function') initChampImage(bloc);
    } else if (
      champ === 'liens_publics'
      || champ === 'coordonnees_bancaires'
      || bloc.querySelector('.champ-texte-editeur')
    ) {
      return;
    } else if (typeof initChampTexte === 'function') {
      initChampTexte(bloc);
    }
  });

    // 🟠 Déclencheurs de résumé
    document.querySelectorAll('.resume-infos .champ-modifier[data-champ]').forEach((btn) => {
      if (typeof initChampDeclencheur === 'function') initChampDeclencheur(btn);
    });

  if (typeof window.mettreAJourResumeInfos === 'function') {
    window.mettreAJourResumeInfos();
  }

});


// 🗺️ Met à jour la carte d'ajout de chasse en fonction des champs remplis
window.mettreAJourCarteAjoutChasse = function () {
  const carte = document.getElementById('carte-ajout-chasse');
  if (!carte) return;

  // 🔍 État du champ "Présentation"
  const champDesc = document.querySelector('.panneau-organisateur .resume-infos li[data-champ="description_longue"]');
  const descriptionEstRemplie = champDesc && !champDesc.classList.contains('champ-vide');

  // 🔍 Champs JS dynamiques
  const champsJS = [
    '[data-champ="post_title"]',
    '[data-champ="logo_organisateur"]'
  ];

  // 🔁 Vérifie visuellement ceux qui sont vides
  const incomplets = champsJS.filter(sel => {
    const champ = document.querySelector('.panneau-organisateur .resume-infos li' + sel);
    return champ?.classList.contains('champ-vide');
  });

  // ✅ Ajout manuel si la présentation est vide
  if (!descriptionEstRemplie) {
    incomplets.push('[data-champ="description_longue"]');
  }

  DEBUG && console.log('🧩 Vérif carte-ajout → champs vides détectés :', incomplets);
  DEBUG && console.log('🧩 carte actuelle :', carte);

  const message = carte.querySelector('.carte-ajout-message');

  if (incomplets.length === 0) {
    carte.classList.remove('disabled');
    if (message) {
      message.textContent = '';
    }
  } else {
    carte.classList.add('disabled');

    const texte = incomplets.map(sel => {
      if (sel.includes('post_title')) return 'titre';
      if (sel.includes('logo')) return 'logo';
      if (sel.includes('description')) return 'description';
      return 'champ requis';
    }).join(', ');

    if (message) {
      message.textContent = `Complétez d’abord : ${texte}`;
    }
  }
};

// 🔄 Met à jour l'état (active/désactivée) de la carte de conversion
window.mettreAJourCarteConversion = function () {
  const carte = document.querySelector('.dashboard-card[data-stat="conversion"]');
  if (!carte) return;

  fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'conversion_modal_content',
      nonce: (window.organisateurData && organisateurData.conversionNonce)
        || (window.ConversionModalAjax && ConversionModalAjax.nonce)
        || (window.ConversionHistoryAjax && ConversionHistoryAjax.nonce)
        || '',
    }),
  })
    .then(res => res.json())
    .then(res => {
      if (!res.success) return;
      const access = res.data?.access;
      if (access) {
        carte.classList.remove('disabled');
      } else {
        carte.classList.add('disabled');
      }
    })
    .catch(() => {});
};
