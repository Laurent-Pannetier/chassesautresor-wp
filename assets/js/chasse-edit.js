// ✅ chasse-edit.js
var DEBUG = window.DEBUG || false;
DEBUG && console.log('✅ chasse-edit.js chargé');

let inputDateDebut;
let inputDateFin;
let erreurDebut;
let erreurFin;
let toggleDateFin;
let toggleDateDebut;

function parseDateDMY(value) {
  if (!value) return new Date(NaN);

  const raw = value.trim();

  // 📅 Format français JJ/MM/AAAA (optionnellement HH:MM)
  if (/^\d{2}\/\d{2}\/\d{4}/.test(raw)) {
    const [datePart, timePart = ''] = raw.split(' ');
    const [day, month, year] = datePart.split('/').map(Number);
    const [hour = 0, minute = 0] = timePart.split(':').map(Number);
    return new Date(year, month - 1, day, hour, minute);
  }

  // 📅 Format ISO : YYYY-MM-DD ou YYYY-MM-DDTHH:MM
  const iso = raw.replace('T', ' ');
  if (/^\d{4}-\d{2}-\d{2}/.test(iso)) {
    const [datePart, timePart = ''] = iso.split(' ');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour = 0, minute = 0] = timePart.split(':').map(Number);
    return new Date(year, month - 1, day, hour, minute);
  }

  return new Date(NaN);
}

window.parseDateDMY = parseDateDMY;

function calculerMessageDate(debutStr = '', finStr = '') {
  const pad = (n) => String(n).padStart(2, '0');
  const format = (date, withTime) => {
    const base = `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
    return withTime
      ? `${base} ${pad(date.getHours())}:${pad(date.getMinutes())}`
      : base;
  };

  const debut = parseDateDMY(debutStr);
  const fin = parseDateDMY(finStr);
  const hasDebut = !isNaN(debut.getTime());
  const hasFin = !isNaN(fin.getTime());

  if (hasDebut && hasFin) {
    return `${format(debut, debutStr.includes(':'))} – ${format(fin, finStr.includes(':'))}`;
  }
  if (hasDebut) {
    return format(debut, debutStr.includes(':'));
  }
  if (hasFin) {
    return format(fin, finStr.includes(':'));
  }
  return '';
}

function mettreAJourMessageDate() {
  if (!inputDateDebut && !inputDateFin) return;
  const span = document.getElementById('message-date');
  if (!span) return;
  const debutVal = inputDateDebut?.value.trim() || '';
  const finVal = inputDateFin?.value.trim() || '';
  const message = calculerMessageDate(debutVal, finVal);
  if (message) {
    span.textContent = message;
  }
}

window.calculerMessageDate = calculerMessageDate;
window.mettreAJourMessageDate = mettreAJourMessageDate;

const translateChasseEdit = (msg) => {
  const i18n = window.wp && window.wp.i18n;
  if (i18n && typeof i18n.__ === 'function') {
    return i18n.__(msg, 'chassesautresor-com');
  }
  return msg;
};

function rafraichirCarteIndices() {
  const card = document.querySelector('.dashboard-card.champ-indices');
  if (!card || !window.ChasseIndices) return;

  card.classList.add('loading');
  card.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

  const formData = new FormData();
  formData.append('action', 'chasse_lister_indices');
  formData.append('chasse_id', ChasseIndices.chasseId);

  fetch(ChasseIndices.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data?.html) {
          const tmp = document.createElement('div');
          tmp.innerHTML = res.data.html;
          const nouvelleCarte = tmp.firstElementChild;
          if (nouvelleCarte) {
            card.replaceWith(nouvelleCarte);
            initIndicesOptions(nouvelleCarte);
          }
        } else {
          throw new Error('invalid');
        }
      })
      .catch(() => {
        card.classList.remove('loading');
        card.innerHTML = `<p class="error">${ChasseIndices.errorText}</p>`;
      });
}

window.rafraichirCarteIndices = rafraichirCarteIndices;

function rafraichirCarteSolutions() {
  document.querySelectorAll('.liste-solutions').forEach((wrapper) => {
    if (window.reloadSolutionsTable) {
      window.reloadSolutionsTable(wrapper);
    }
  });

  const card = document.querySelector('.dashboard-card.champ-solutions');
  if (!card) return;
  const btnChasse = card.querySelector('.cta-solution-chasse');
  const btnEnigme = card.querySelector('.cta-solution-enigme');
  const chasseId =
    (btnChasse && btnChasse.dataset.objetId) ||
    (btnEnigme && btnEnigme.dataset.chasseId);
  const ajaxUrl =
    (window.solutionsCreate && solutionsCreate.ajaxUrl) || window.ajaxurl;
  if (!ajaxUrl || !chasseId) return;

  const fd = new FormData();
  fd.append('action', 'chasse_solution_status');
  fd.append('chasse_id', chasseId);
  fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
    .then((r) => r.json())
    .then((res) => {
      if (!res.success || !res.data) return;
      if (btnChasse) {
        const disableChasse = !!res.data.has_solution_chasse;
        btnChasse.classList.toggle('disabled', disableChasse);
        btnChasse.setAttribute('aria-disabled', disableChasse);
        if (ChasseSolutions && ChasseSolutions.tooltipChasse) {
          btnChasse.title = disableChasse ? ChasseSolutions.tooltipChasse : '';
        }
      }
      if (btnEnigme) {
        const disableEnigme = !res.data.has_enigmes;
        btnEnigme.classList.toggle('disabled', disableEnigme);
        btnEnigme.setAttribute('aria-disabled', disableEnigme);
        if (ChasseSolutions && ChasseSolutions.tooltipEnigme) {
          btnEnigme.title = disableEnigme ? ChasseSolutions.tooltipEnigme : '';
        }
      }

      const hasSolutions = !!res.data.has_solutions;
      card.classList.toggle('champ-rempli', hasSolutions);
      card.classList.toggle('champ-vide', !hasSolutions);

      initDisabledSolutionButtons();
    })
    .catch(() => {});
}

window.rafraichirCarteSolutions = rafraichirCarteSolutions;

  function initChasseEdit() {
  if (typeof initZonesClicEdition === 'function') initZonesClicEdition();
  inputDateDebut = document.getElementById('chasse-date-debut');
  inputDateFin = document.getElementById('chasse-date-fin');
  erreurDebut = document.getElementById('erreur-date-debut');
  erreurFin = document.getElementById('erreur-date-fin');
  toggleDateDebut = document.getElementById('date-debut-differee');
  toggleDateFin = document.getElementById('date-fin-limitee');
  mettreAJourCaracteristiqueDate();
  inputDateDebut?.addEventListener('change', mettreAJourCaracteristiqueDate);
  inputDateFin?.addEventListener('change', mettreAJourCaracteristiqueDate);
  toggleDateDebut?.addEventListener('change', mettreAJourCaracteristiqueDate);
  toggleDateFin?.addEventListener('change', mettreAJourCaracteristiqueDate);

  const panelEdition = document.querySelector('.edition-panel-chasse');
  const boutonSupprimer = panelEdition?.querySelector('#bouton-supprimer-chasse');

  if (boutonSupprimer) {
    boutonSupprimer.addEventListener('click', () => {
      const chasseId = boutonSupprimer.dataset.chasseId;
      if (!chasseId) {
        return;
      }

      const enigmesCount = parseInt(boutonSupprimer.dataset.enigmesCount || '0', 10);
      const hasEnigmes = !Number.isNaN(enigmesCount) && enigmesCount > 0;

      const confirmationMessage = hasEnigmes
        ? translateChasseEdit('Voulez-vous vraiment supprimer cette chasse et toutes ses énigmes ?')
        : translateChasseEdit('Voulez-vous vraiment supprimer cette chasse ?');

      if (!window.confirm(confirmationMessage)) {
        return;
      }

      const ajaxUrl = window.ajaxurl || (window.ChasseIndices && window.ChasseIndices.ajaxUrl) || '';
      if (!ajaxUrl) {
        return;
      }

      const zoneErreur = panelEdition?.querySelector('#erreur-global');
      if (zoneErreur) {
        zoneErreur.style.display = 'none';
        zoneErreur.textContent = '';
      }

      const fd = new FormData();
      fd.append('action', 'supprimer_chasse');
      fd.append('chasse_id', chasseId);

      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
      })
        .then((response) => response.json())
        .then((resultat) => {
          if (!resultat?.success || !resultat.data?.redirect) {
            throw new Error('invalid');
          }
          window.location.href = resultat.data.redirect;
        })
        .catch(() => {
          const messageErreur = translateChasseEdit('Une erreur est survenue lors de la suppression de la chasse.');
          if (zoneErreur) {
            zoneErreur.textContent = messageErreur;
            zoneErreur.style.display = 'block';
          } else {
            window.alert(messageErreur);
          }
        });
    });
  }

  // ==============================
  // 🟢 Initialisation des champs
  // ==============================
  document.querySelectorAll('.champ-chasse[data-champ]').forEach((bloc) => {
    const champ = bloc.dataset.champ;

    if (bloc.classList.contains('champ-img')) {
      if (typeof initChampImage === 'function') initChampImage(bloc);
    } else if (champ === 'chasse_principale_liens') {
      const bouton = bloc.querySelector('.champ-modifier');
      if (bouton && typeof initLiensChasse === 'function') initLiensChasse(bloc);
    } else {
      if (typeof initChampTexte === 'function') initChampTexte(bloc);
    }
  });

  // ==============================
  // 🧰 Déclencheurs de résumé
  // ==============================
  document.querySelectorAll('.edition-panel-chasse .champ-modifier[data-champ]').forEach((btn) => {
    if (typeof initChampDeclencheur === 'function') initChampDeclencheur(btn);
  });

  // ==============================
  // 🛠️ Contrôles panneau principal
  // ==============================
  document.getElementById('toggle-mode-edition-chasse')?.addEventListener('click', () => {
    document.body.classList.toggle('edition-active-chasse');
    document.body.classList.toggle('panneau-ouvert');
    document.body.classList.toggle('mode-edition');
  });
  document.querySelector('.edition-panel-chasse .panneau-fermer')?.addEventListener('click', () => {
    document.body.classList.remove('edition-active-chasse');
    document.body.classList.remove('panneau-ouvert');
    document.body.classList.remove('mode-edition');
    document.activeElement?.blur();
  });

  // ==============================
  // 🧭 Déclencheur automatique
  // ==============================
  const params = new URLSearchParams(window.location.search);
  const doitOuvrir = params.get('edition') === 'open';
  const tab = params.get('tab');
  const panel = params.get('panel');
  const skipAuto = document.body.classList.contains('scroll-to-enigmes');
  const forceDescription =
    doitOuvrir && tab === 'param' && window.location.hash === '#chasse-description';

  if (doitOuvrir && panel !== 'organisateur' && (!skipAuto || forceDescription)) {
    document.body.classList.add('edition-active-chasse', 'panneau-ouvert', 'mode-edition');
    if (tab) {
      const btn = document.querySelector(`.edition-tab[data-target="chasse-tab-${tab}"]`);
      btn?.click();
    }
    DEBUG && console.log('🔧 Ouverture auto du panneau édition chasse via ?edition=open');
  } else if (skipAuto && !forceDescription && (doitOuvrir || tab)) {
    params.delete('edition');
    params.delete('tab');
    const nouvelleUrl = `${window.location.pathname}${
      params.toString() ? `?${params.toString()}` : ''
    }${window.location.hash}`;
    window.history.replaceState({}, '', nouvelleUrl);
  }

  if (skipAuto && !forceDescription) {
    const cible = document.getElementById('carte-ajout-enigme');
    if (cible) {
      cible.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  // ==============================
  // 📜 Panneau description (wysiwyg)
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-description');
    if (!btn || btn.dataset.cpt !== 'chasse') return;
    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-description-chasse');
    }
  });
  document.querySelector('#panneau-description-chasse .panneau-fermer')?.addEventListener('click', () => {
    if (typeof window.closePanel === 'function') {
      window.closePanel('panneau-description-chasse');
    }
  });

  // ==============================
  // 🏱 Panneau récompense
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-recompense');
    if (!btn || btn.dataset.cpt !== 'chasse') return;
    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-recompense-chasse');
    }

  });
  document.querySelector('#panneau-recompense-chasse .panneau-fermer')?.addEventListener('click', () => {
    if (typeof window.closePanel === 'function') {
      window.closePanel('panneau-recompense-chasse');
    }
  });

  // ==============================
  // 🎯 Badge dynamique récompense
  // ==============================
  if (typeof window.mettreAJourResumeInfos === 'function') {
    window.mettreAJourResumeInfos();
  }
  if (typeof window.mettreAJourCarteAjoutEnigme === 'function') {
    window.mettreAJourCarteAjoutEnigme();
  }
  if (typeof window.mettreAJourEtatIntroChasse === 'function') {
    window.mettreAJourEtatIntroChasse();
  }

  if (inputDateDebut) {
    // L'enregistrement et la validation sont gérés par `date-fields.js`.
    // Ce fichier ne fait que fournir les messages d'erreur via
    // `validerDatesAvantEnvoi` appelé par `initChampDate()`.
  }

  // ================================
  // 🏆 Gestion de l'enregistrement de la récompense (titre, texte, valeur)
  // ================================
  const boutonRecompense = document.getElementById('bouton-enregistrer-recompense');
  const inputTitreRecompense = document.getElementById('champ-recompense-titre');
  const inputTexteRecompense = document.getElementById('champ-recompense-texte');
  const inputValeurRecompense = document.getElementById('champ-recompense-valeur');
  const panneauRecompense = document.getElementById('panneau-recompense-chasse');
  const boutonSupprimerRecompense = document.getElementById('bouton-supprimer-recompense');

  function formaterValeurRecompense() {
    if (!inputValeurRecompense) return;
    let brut = inputValeurRecompense.value.replace(/\s+/g, '').replace(',', '.');
    if (brut === '') return;
    const nombre = parseFloat(brut);
    if (!isNaN(nombre)) {
      const dec = brut.includes('.') ? Math.min(2, brut.split('.')[1].length) : 0;
      inputValeurRecompense.value = nombre.toLocaleString('fr-FR', {
        minimumFractionDigits: dec,
        maximumFractionDigits: 2
      });
    }
  }

  if (inputValeurRecompense) {
    inputValeurRecompense.addEventListener('input', formaterValeurRecompense);
    formaterValeurRecompense();
  }

  function majAffichageRecompense(titre, texte, valeur) {
    const ligne = document.querySelector('.champ-chasse[data-champ="chasse_infos_recompense_valeur"]');
    if (!ligne) return;
    const champTexte = ligne.querySelector('.champ-texte');
    if (!champTexte) return;
    const peutEditer = !ligne.classList.contains('champ-desactive');
    champTexte.innerHTML = '';

    const complet = titre && texte && valeur && valeur > 0;
    ligne.classList.toggle('champ-rempli', complet);
    ligne.classList.toggle('champ-vide', !complet);

    const span = document.createElement('span');
    span.className = 'champ-texte-contenu';

    if (complet) {
      const valeurSpan = document.createElement('span');
      valeurSpan.className = 'recompense-valeur';
      const labelSpan = document.createElement('span');
      labelSpan.className = 'recompense-valeur__label';
      labelSpan.textContent = wp.i18n.__('Valeur estimée', 'chassesautresor-com');
      valeurSpan.appendChild(labelSpan);
      const arrondi = Math.round(valeur);
      valeurSpan.appendChild(document.createTextNode(arrondi.toLocaleString('fr-FR')));
      const deviseSpan = document.createElement('span');
      deviseSpan.className = 'recompense-valeur__devise';
      deviseSpan.textContent = '€';
      valeurSpan.appendChild(deviseSpan);

      const titreSpan = document.createElement('span');
      titreSpan.className = 'recompense-titre';
      titreSpan.textContent = titre;

      span.appendChild(valeurSpan);
      span.appendChild(document.createTextNode('\u00A0\u2013\u00A0'));
      span.appendChild(titreSpan);
      span.appendChild(document.createTextNode('\u00A0\u2013\u00A0'));
      const descSpan = document.createElement('span');
      descSpan.className = 'recompense-description';
      const texteLimite = texte.length > 200 ? texte.slice(0, 200) + '…' : texte;
      descSpan.textContent = texteLimite;
      span.appendChild(descSpan);
    }

    champTexte.appendChild(span);

    if (peutEditer) {
      const bouton = document.createElement('button');
      bouton.type = 'button';
      bouton.className = 'champ-modifier txt-small ouvrir-panneau-recompense';
      bouton.dataset.champ = 'chasse_infos_recompense_valeur';
      bouton.dataset.cpt = 'chasse';
      bouton.dataset.postId = ligne.dataset.postId || '';
      const action = complet ? 'modifier' : 'ajouter';
      const aria = complet ? 'Modifier la récompense' : 'Ajouter la récompense';
      bouton.setAttribute('aria-label', wp.i18n.__(aria, 'chassesautresor-com'));
      bouton.textContent = wp.i18n.__(action, 'chassesautresor-com');
      champTexte.appendChild(bouton);
      if (typeof initZoneClicEdition === 'function') initZoneClicEdition(bouton);
    }

    if (typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
  }

  if (boutonSupprimerRecompense) {
    boutonSupprimerRecompense.addEventListener('click', () => {
      const panneauEdition = document.querySelector('.edition-panel-chasse');
      if (!panneauEdition) return;
      const postId = panneauEdition.dataset.postId;
      if (!postId) return;

      if (!confirm(wp.i18n.__(
        'Voulez-vous vraiment supprimer la récompense ?',
        'chassesautresor-com'
      ))) return;

      const champsASupprimer = [
        'chasse_infos_recompense_titre',
        'chasse_infos_recompense_texte',
        'chasse_infos_recompense_valeur'
      ];

      Promise.all(
        champsASupprimer.map((champ) => {
          return fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: 'modifier_champ_chasse',
              champ,
              valeur: '',
              post_id: postId
            })
          });
        })
      ).then(() => {
        majAffichageRecompense('', '', 0);
        inputTitreRecompense.value = '';
        inputTexteRecompense.value = '';
        inputValeurRecompense.value = '';
        if (typeof window.closePanel === 'function') {
          window.closePanel('panneau-recompense-chasse');
        }
      });
    });

  }

  if (boutonRecompense && inputTitreRecompense && inputTexteRecompense && inputValeurRecompense) {
    boutonRecompense.addEventListener('click', () => {
      const titre = inputTitreRecompense.value.trim();
      const texte = inputTexteRecompense.value.trim();
      const valeur = parseFloat(
        inputValeurRecompense.value.replace(/\s+/g, '').replace(',', '.')
      );
      const panneauEdition = document.querySelector('.edition-panel-chasse');
      if (!panneauEdition) return;
      const postId = panneauEdition.dataset.postId;
      if (!postId) return;

      // 🚨 Vérification des 3 champs
      if (!titre.length) {
        alert('Veuillez saisir un titre de récompense.');
        return;
      }

      if (!texte.length) {
        alert('Veuillez saisir une description de récompense.');
        return;
      }

      if (isNaN(valeur) || valeur <= 0 || valeur > 5000000) {
        alert('Veuillez saisir une valeur en euros comprise entre 0 et 5\u00a0000\u00a0000.');
        return;
      }

      // 🔵 Envoi titre de récompense d'abord
      fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'modifier_champ_chasse',
          champ: 'chasse_infos_recompense_titre',
          valeur: titre,
          post_id: postId
        })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            DEBUG && console.log('✅ Titre récompense enregistré.');

            // 🔵 Ensuite, envoi texte récompense
            return fetch('/wp-admin/admin-ajax.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'modifier_champ_chasse',
                champ: 'chasse_infos_recompense_texte',
                valeur: texte,
                post_id: postId
              })
            });
          } else {
            throw new Error('Erreur enregistrement titre récompense');
          }
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            DEBUG && console.log('✅ Texte récompense enregistré.');

            // 🔵 Ensuite, envoi valeur récompense
            return fetch('/wp-admin/admin-ajax.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'modifier_champ_chasse',
                champ: 'chasse_infos_recompense_valeur',
                valeur: valeur,
                post_id: postId
              })
            });
          } else {
            throw new Error('Erreur enregistrement texte récompense');
          }
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            majAffichageRecompense(titre, texte, valeur);
            if (document.activeElement && panneauRecompense.contains(document.activeElement)) {
              document.activeElement.blur();
              document.body.focus();
            }
            if (typeof window.closePanel === 'function') {
              window.closePanel('panneau-recompense-chasse');
            }
          } else {
            console.error('❌ Erreur valeur récompense', res.data);
          }
        })
        .catch(err => {
          console.error('❌ Erreur sur sauvegarde récompense', err);
        });
    });
  }

  // ==============================
  // ==============================
  // 🏁 Terminaison manuelle
  // ==============================
  let zoneFinChasse;
  let btnFinChasse;

  function resetFinChasse() {
    if (zoneFinChasse) {
      const textarea = zoneFinChasse.querySelector('#chasse-gagnants');
      const valider = zoneFinChasse.querySelector('.valider-fin-chasse-btn');
      zoneFinChasse.style.display = 'none';
      if (textarea) textarea.value = '';
      if (valider) valider.disabled = true;
    }
    if (btnFinChasse) btnFinChasse.style.display = 'inline-block';
    document.removeEventListener('keydown', onEscapeFinChasse);
    zoneFinChasse = null;
    btnFinChasse = null;
  }

  function onEscapeFinChasse(e) {
    if (e.key === 'Escape') {
      resetFinChasse();
    }
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.terminer-chasse-btn');
    if (btn) {
      const zone = btn.nextElementSibling;
      if (zone) {
        zone.style.display = 'block';
        const textarea = zone.querySelector('#chasse-gagnants');
        const valider = zone.querySelector('.valider-fin-chasse-btn');
        textarea.addEventListener('input', () => {
          valider.disabled = textarea.value.trim() === '';
        });
        zoneFinChasse = zone;
        btnFinChasse = btn;
        document.addEventListener('keydown', onEscapeFinChasse);
      }
      btn.style.display = 'none';
      return;
    }

    const annuler = e.target.closest('.annuler-fin-chasse-btn');
    if (annuler) {
      resetFinChasse();
      return;
    }

    const valider = e.target.closest('.valider-fin-chasse-btn');
    if (valider) {
      document.removeEventListener('keydown', onEscapeFinChasse);
      const postId = valider.dataset.postId;
      const zone = valider.closest('.zone-validation-fin');
      const textarea = zone.querySelector('#chasse-gagnants');
      const gagnants = textarea.value.trim();
      if (!gagnants) return;
      if (
        !confirm(
          wp.i18n.__(
            'Voulez-vous vraiment arrêter la chasse ?',
            'chassesautresor-com'
          )
        )
      ) {
        return;
      }
      valider.disabled = true;
      const now = new Date();
      const dateValue = now.toISOString().slice(0, 19).replace('T', ' ');
      const dateDisplay = `${String(now.getDate()).padStart(2, '0')}/${String(
        now.getMonth() + 1
      ).padStart(2, '0')}/${now.getFullYear()} à ${String(
        now.getHours()
      ).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
      const gagnantsEsc = gagnants.replace(/[&<>\"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
      })[c]);

      modifierChampSimple(
        'champs_caches.chasse_cache_gagnants',
        gagnants,
        postId,
        'chasse'
      )
        .then((ok) =>
          ok &&
          modifierChampSimple(
            'champs_caches.chasse_cache_date_decouverte',
            dateValue,
            postId,
            'chasse'
          )
        )
        .then((ok) =>
          ok &&
          modifierChampSimple(
            'champs_caches.chasse_cache_statut',
            'termine',
            postId,
            'chasse'
          )
        )
        .then((ok) => {
          if (ok) {
            document
              .querySelectorAll('.fin-chasse-actions')
              .forEach((container) => {
                container.innerHTML = `<p class="message-chasse-terminee">Chasse gagnée le ${dateDisplay} par ${gagnantsEsc}</p>`;
              });
          } else {
            valider.disabled = false;
          }
        });
    }
    });

    window.addEventListener('message', (e) => {
      if (e.data && (e.data.type === 'indice-created' || e.data === 'indice-created')) {
        rafraichirCarteIndices();
      }
    });
    window.addEventListener('indice-created', rafraichirCarteIndices);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChasseEdit);
  } else {
    initChasseEdit();
  }

  window.addEventListener('solution-created', () => {
    rafraichirCarteSolutions();
  });

  function rafraichirCarteLiens() {
    const card = document.querySelector('.dashboard-card.champ-liens');
    if (!card) return;
    const dataEl = card.querySelector('.champ-donnees');
    let valeurs = [];
    if (dataEl && dataEl.dataset.valeurs) {
      try {
        valeurs = JSON.parse(dataEl.dataset.valeurs);
      } catch (e) {
        valeurs = [];
      }
    }
    const filled = Array.isArray(valeurs) && valeurs.length > 0;
    card.classList.toggle('champ-rempli', filled);
    card.classList.toggle('champ-vide', !filled);
  }

  window.addEventListener('liens-publics-updated', rafraichirCarteLiens);

// ==============================
// 🔗 Initialisation des liens chasse
// ==============================
function initLiensChasse(bloc) {
  if (typeof window.initLiensPublics === 'function') {
    initLiensPublics(bloc, {
      panneauId: 'panneau-liens-chasse',
      formId: 'formulaire-liens-chasse',
      action: 'modifier_champ_chasse'
    });
  }
}

// ==============================
// 🔎 Validation logique entre date de début et date de fin
// ==============================
function validerDatesAvantEnvoi(champModifie) {
  console.log('[validerDatesAvantEnvoi] champModifie=', champModifie);
  // ✅ Si illimitée, on n'applique aucun contrôle
  if (!toggleDateFin?.checked) return true;

  if (erreurDebut) erreurDebut.style.display = 'none';
  if (erreurFin) erreurFin.style.display = 'none';

  if (!inputDateDebut || !inputDateFin) return true;

  const maintenant = new Date();
  const dateMinimum = new Date();
  dateMinimum.setFullYear(maintenant.getFullYear() - 10);
  const dateMaximum = new Date();
  dateMaximum.setFullYear(dateMaximum.getFullYear() + 5);

  console.log('[validerDatesAvantEnvoi] bornes=', dateMinimum.toISOString(), dateMaximum.toISOString());

  const debut = parseDateDMY(inputDateDebut.value);
  const fin = parseDateDMY(inputDateFin.value);

  console.log('[validerDatesAvantEnvoi] debut=', debut.toISOString(), 'fin=', fin.toISOString());

  if (debut < dateMinimum || debut > dateMaximum) {
    if (champModifie === 'debut' && erreurDebut) {
      erreurDebut.textContent = '❌ La date de début est trop ancienne (10 ans maximum d\'ancienneté).';
      erreurDebut.style.display = 'block';
      afficherErreurGlobale('❌ La date de début est trop ancienne (10 ans maximum d\'ancienneté).');
    }
    return false;
  }

  if (isNaN(debut.getTime()) && champModifie === 'debut') {
    if (erreurDebut) {
      erreurDebut.textContent = '❌ Date de début invalide.';
      erreurDebut.style.display = 'block';
      afficherErreurGlobale('❌ Date de début invalide.');
    }
    return false;
  }

  if (isNaN(fin.getTime()) && champModifie === 'fin') {
    if (erreurFin) {
      erreurFin.textContent = '❌ Date de fin invalide.';
      erreurFin.style.display = 'block';
      afficherErreurGlobale('❌ Date de fin invalide.');
    }
    return false;
  }

  if (debut.getTime() >= fin.getTime()) {
    const msg = '❌ La date de début doit être antérieure à la date de fin.';
    if (champModifie === 'debut' && erreurDebut) {
      erreurDebut.textContent = msg;
      erreurDebut.style.display = 'block';
      afficherErreurGlobale(msg);
    }
    if (champModifie === 'fin' && erreurFin) {
      erreurFin.textContent = msg;
      erreurFin.style.display = 'block';
      afficherErreurGlobale(msg);
    }
    return false;
  }

  return true;
}

window.validerDatesAvantEnvoi = validerDatesAvantEnvoi;

// ==============================
// 🔥 Affichage d'un message global temporaire
// ==============================
function afficherErreurGlobale(message) {
  console.log('[afficherErreurGlobale]', message);
  const erreurGlobal = document.getElementById('erreur-global');
  if (!erreurGlobal) return;

  erreurGlobal.textContent = message;
  erreurGlobal.style.display = 'block';
  erreurGlobal.style.position = 'fixed';
  erreurGlobal.style.top = '0';
  erreurGlobal.style.left = '0';
  erreurGlobal.style.width = '100%';
  erreurGlobal.style.zIndex = '9999';

  setTimeout(() => {
    erreurGlobal.style.display = 'none';
  }, 4000); // Disparition après 4 secondes
}

function mettreAJourCaracteristiqueDate() {
  const container = document.querySelector('.caracteristique-date');
  if (!container || !inputDateDebut) return;
  const labelSpan = container.querySelector('.caracteristique-label');
  const valueSpan = container.querySelector('.caracteristique-valeur');
  if (!labelSpan || !valueSpan) return;
  const lang = document.documentElement.lang || '';
  if (
    !lang.startsWith('fr') &&
    wp.i18n.__('jours restants', 'chassesautresor-com') === 'jours restants'
  ) {
    return;
  }
  const debut = parseDateDMY(inputDateDebut.value);
  const fin = parseDateDMY(inputDateFin?.value || '');
  if (!isNaN(debut.getTime())) debut.setHours(0, 0, 0, 0);
  if (!isNaN(fin.getTime())) fin.setHours(0, 0, 0, 0);
  const illimite = toggleDateFin ? !toggleDateFin.checked : false;

  labelSpan.textContent = '';
  valueSpan.textContent = '';
  container.style.display = 'none';

  if (illimite) {
    labelSpan.textContent = wp.i18n.__('durée', 'chassesautresor-com');
    valueSpan.textContent = wp.i18n.__('illimitée', 'chassesautresor-com');
    container.style.display = '';
    return;
  }
  if (isNaN(debut.getTime())) return;

  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  if (!isNaN(fin.getTime())) {
    if (today > fin) {
      labelSpan.textContent = wp.i18n.__('terminée depuis', 'chassesautresor-com');
      const pad = (n) => String(n).padStart(2, '0');
      valueSpan.textContent = `${pad(fin.getDate())}/${pad(fin.getMonth() + 1)}/${fin.getFullYear()}`;
    } else if (today < debut) {
      const diff = Math.max(
        0,
        Math.floor((debut - today) / (1000 * 60 * 60 * 24))
      );
      labelSpan.textContent = wp.i18n.__('début dans', 'chassesautresor-com');
      const tpl = wp.i18n._n('%d jour', '%d jours', diff, 'chassesautresor-com');
      valueSpan.textContent = tpl.replace('%d', diff);
    } else {
      const diff = Math.max(
        0,
        Math.floor((fin - today) / (1000 * 60 * 60 * 24))
      );
      labelSpan.textContent = wp.i18n.__('jours restants', 'chassesautresor-com');
      const tpl = wp.i18n._n('%d jour', '%d jours', diff, 'chassesautresor-com');
      valueSpan.textContent = tpl.replace('%d', diff);
    }
  } else if (today < debut) {
    const diff = Math.max(
      0,
      Math.floor((debut - today) / (1000 * 60 * 60 * 24))
    );
    labelSpan.textContent = wp.i18n.__('début dans', 'chassesautresor-com');
    const tpl = wp.i18n._n('%d jour', '%d jours', diff, 'chassesautresor-com');
    valueSpan.textContent = tpl.replace('%d', diff);
  } else {
    return;
  }

  container.style.display = '';
}

window.mettreAJourCaracteristiqueDate = mettreAJourCaracteristiqueDate;

function mettreAJourBadgeCoutChasse(postId, cout) {
  const container = document.querySelector('.header-chasse__image');
  if (!container) return;
  const labelTpl = container.dataset.coutLabel || '';
  const ptsLabel = container.dataset.ptsLabel || 'pts';
  let badge = container.querySelector('.badge-cout');
  if (cout > 0) {
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge-cout';
      badge.dataset.postId = postId;
      container.appendChild(badge);
    }
    badge.textContent = `${cout} ${ptsLabel}`;
    if (labelTpl) {
      badge.setAttribute('aria-label', labelTpl.replace('%d', cout));
    }
  } else if (badge) {
    badge.remove();
  }
}

window.mettreAJourBadgeCoutChasse = mettreAJourBadgeCoutChasse;

function mettreAJourBadgeModeFinChasse(mode) {
  const container = document.querySelector('.header-chasse__image');
  if (!container) return;
  const icone = container.querySelector('.mode-fin-icone');
  if (!icone) return;
  const autoLabel = container.dataset.modeAutoLabel || '';
  const manuelLabel = container.dataset.modeManuelLabel || '';
  const autoIcon = container.dataset.modeAutoIcon || '';
  const manuelIcon = container.dataset.modeManuelIcon || '';
  if (mode === 'automatique') {
    icone.innerHTML = autoIcon;
    if (autoLabel) {
      icone.setAttribute('title', autoLabel);
      icone.setAttribute('aria-label', autoLabel);
    }
  } else {
    icone.innerHTML = manuelIcon;
    if (manuelLabel) {
      icone.setAttribute('title', manuelLabel);
      icone.setAttribute('aria-label', manuelLabel);
    }
  }
}

window.mettreAJourBadgeModeFinChasse = mettreAJourBadgeModeFinChasse;

// ================================
// 💾 Enregistrement du coût en points après clic bouton "✓"
// ================================
document.querySelectorAll('.champ-cout-points .champ-enregistrer').forEach(bouton => {
  bouton.addEventListener('click', (e) => {
    e.preventDefault();
    const li = bouton.closest('li');
    const input = li.querySelector('.champ-input');
    if (!li || !input) return;

    const champ = li.dataset.champ;
    const postId = li.dataset.postId;
    const valeur = input.value.trim() === '' ? '0' : input.value.trim();

    if (!champ || !postId) return;

    modifierChampSimple(champ, valeur, postId, 'chasse');

    if (champ === 'chasse_infos_cout_points') {
      rafraichirStatutChasse(postId);
      mettreAJourBadgeCoutChasse(postId, parseInt(valeur, 10));
    }

    // Cache les boutons après envoi
    const boutons = li.querySelector('.champ-inline-actions');
    if (boutons) {
      boutons.style.opacity = '0';
      boutons.style.visibility = 'hidden';
      input.dataset.valeurInitiale = valeur;
    }
  });
});

// ================================
// 💰 Gestion de l'enregistrement du coût en points
// ================================
document.querySelectorAll('.champ-cout-points .champ-annuler').forEach(bouton => {
  bouton.addEventListener('click', (e) => {
    e.preventDefault();
    const li = bouton.closest('li');
    const input = li.querySelector('.champ-input');
    if (!li || !input) return;
    const postId = li.dataset.postId;

    // Restaure l'ancienne valeur
    input.value = input.dataset.valeurInitiale || '0';
    mettreAJourBadgeCoutChasse(postId, parseInt(input.value.trim(), 10) || 0);

    // Cache les boutons
    const boutons = li.querySelector('.champ-inline-actions');
    if (boutons) {
      boutons.style.opacity = '0';
      boutons.style.visibility = 'hidden';
    }
  });
});

// ================================
// 🎯 Gestion du champ Nombre de gagnants + Illimité (avec debounce)
// ================================
function initChampNbGagnants() {
  const inputNb = document.getElementById('chasse-nb-gagnants');
  const toggleLimite = document.getElementById('nb-gagnants-limite');
  const actions = inputNb?.closest('.nb-gagnants-actions');

  if (!inputNb || !toggleLimite || !actions) return;

  const li = inputNb.closest('li');
  const status = li?.querySelector('.champ-status');
  if (status && status.parentElement === actions) {
    actions.insertAdjacentElement('afterend', status);
  }

  let timerDebounce;

  function updateVisibility() {
    const postId = li?.dataset.postId;
    if (!postId) return;

    if (toggleLimite.checked) {
      actions.style.display = '';
      inputNb.disabled = false;
      if (
        parseInt(inputNb.value.trim(), 10) === 0 ||
        inputNb.value.trim() === ''
      ) {
        inputNb.value = '1';
      }
      inputNb.dispatchEvent(new Event('input', { bubbles: true }));
      inputNb.dispatchEvent(new Event('change', { bubbles: true }));
      mettreAJourAffichageNbGagnants(postId, inputNb.value.trim());
    } else {
      actions.style.display = 'none';
      inputNb.value = '0';
      inputNb.dispatchEvent(new Event('input', { bubbles: true }));
      inputNb.dispatchEvent(new Event('change', { bubbles: true }));
      inputNb.disabled = true;
      mettreAJourAffichageNbGagnants(postId, 0);
    }
  }

  toggleLimite.addEventListener('change', updateVisibility);

  inputNb.addEventListener('input', function () {
    if (!toggleLimite.checked) return;

    const postId = li?.dataset.postId;
    if (!postId) return;

    clearTimeout(timerDebounce);
    timerDebounce = setTimeout(() => {
      let valeur = parseInt(inputNb.value.trim(), 10);
      if (isNaN(valeur) || valeur < 1) {
        valeur = 1;
        inputNb.value = '1';
      }
      inputNb.dispatchEvent(new Event('change', { bubbles: true }));
      mettreAJourAffichageNbGagnants(postId, valeur);
    }, 500);
  });

  updateVisibility();
}

// ================================
// 🕒 Gestion du champ Date de début (Maintenant / Autre date)
// ================================
function initChampDateDebut() {
  const input = document.getElementById('chasse-date-debut');
  const toggle = document.getElementById('date-debut-differee');
  const actions = input?.closest('.date-debut-actions');

  if (!input || !toggle || !actions) return;

  function updateVisibility() {
    if (toggle.checked) {
      actions.style.display = '';
      input.disabled = false;
      if (typeof window.initChampDate === 'function') {
        window.initChampDate(input);
      }
    } else {
      actions.style.display = 'none';
      input.disabled = true;
      const now = new Date();
      const iso = now.toISOString().slice(0, 16);
      input.value = iso;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  toggle.addEventListener('change', updateVisibility);
  updateVisibility();
}

// ================================
// 📅 Gestion du champ Date de fin (Illimitée / Limitée)
// ================================
function initChampDateFin() {
  const input = document.getElementById('chasse-date-fin');
  const toggle = document.getElementById('date-fin-limitee');
  const actions = input?.closest('.date-fin-actions');

  if (!input || !toggle || !actions) return;

  function updateVisibility() {
    if (toggle.checked) {
      actions.style.display = '';
      input.disabled = false;
      if (typeof window.initChampDate === 'function') {
        window.initChampDate(input);
      }
    } else {
      actions.style.display = 'none';
      input.disabled = true;
    }
  }

  toggle.addEventListener('change', () => {
    updateVisibility();
    const li = input.closest('li');
    const status = li?.querySelector('.champ-status');
    if (status) {
      status.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
    }

    enregistrerDatesChasse().then((ok) => {
      if (status) {
        status.innerHTML = ok
          ? '<i class="fa-solid fa-check" aria-hidden="true"></i>'
          : '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        setTimeout(() => { status.innerHTML = ''; }, 1500);
      }
      mettreAJourAffichageDateFin();
    });
  });

  updateVisibility();
}

// ================================
// 💰 Gestion du champ Accès (Gratuit / Points)
// ================================
function initChampCoutPoints() {
  const input = document.querySelector('.champ-cout-points .champ-cout');
  const toggle = document.getElementById('cout-payant');
  const actions = input?.closest('.cout-points-actions');
  const postId = input?.closest('li')?.dataset.postId;

  if (!input || !toggle || !actions || !postId) return;

  function updateVisibility() {
    if (toggle.checked) {
      actions.style.display = '';
      input.disabled = false;
      input.min = '1';
      if (parseInt(input.value.trim(), 10) === 0 || input.value.trim() === '') {
        input.value = '10';
      }
    } else {
      actions.style.display = 'none';
      input.disabled = true;
      input.value = '0';
    }

    input.dispatchEvent(new Event('input', { bubbles: true }));
  }

  input.addEventListener('input', () => {
    const valeur = parseInt(input.value.trim(), 10) || 0;
    mettreAJourBadgeCoutChasse(postId, valeur);
  });

  toggle.addEventListener('change', updateVisibility);
  updateVisibility();
}

// ================================
// 🔚 Gestion dynamique du mode de fin
// ================================
function initModeFinChasse() {
  const toggle = document.getElementById('chasse_mode_fin');
  const templateNb = document.getElementById('template-nb-gagnants');
  const modeFinLi = document.querySelector('.champ-mode-fin');
  const finCard = document.querySelector('.carte-arret-chasse');
  const caracLimite = document.querySelector('.caracteristique-limite');
  const caracFinVal = document.querySelector('.caracteristique-fin .caracteristique-valeur');

  if (!toggle || !templateNb || !modeFinLi || !finCard) return;

  const postId = modeFinLi.dataset.postId;

  function update(save = false) {
    const selected = toggle.checked ? 'manuelle' : 'automatique';
    const existingNb = document.querySelector('.champ-nb-gagnants');

    if (save) {
      modifierChampSimple('chasse_mode_fin', selected, postId, 'chasse');
    }

    if (selected === 'automatique') {
      if (!existingNb) {
        const clone = templateNb.content.firstElementChild.cloneNode(true);
        modeFinLi.insertAdjacentElement('afterend', clone);
        initChampNbGagnants();
        if (typeof initChampTexte === 'function') initChampTexte(clone);
      }

      document
        .querySelector('.annuler-fin-chasse-btn')
        ?.dispatchEvent(new Event('click', { bubbles: true }));

      if (finCard && !finCard.querySelector('.message-chasse-terminee')) {
        finCard.style.display = 'none';
      }

      const inputNb = document.getElementById('chasse-nb-gagnants');
      if (inputNb) {
        mettreAJourAffichageNbGagnants(postId, inputNb.value.trim());
      }
      if (caracLimite) caracLimite.style.display = '';
      if (caracFinVal) caracFinVal.textContent = ChasseModeFinI18n.auto;
    } else {
      if (existingNb) existingNb.remove();

      if (finCard) finCard.style.display = '';

      mettreAJourAffichageNbGagnants(postId, 0);
      if (caracLimite) caracLimite.style.display = 'none';
      if (caracFinVal) caracFinVal.textContent = ChasseModeFinI18n.manual;
    }

    mettreAJourBadgeModeFinChasse(selected);
  }

  toggle.addEventListener('change', () => update(true));

  update();
}

// À appeler :
initChampDateDebut();
initChampDateFin();
initChampNbGagnants();
initChampCoutPoints();
initModeFinChasse();

// ==============================
// ➕ Mise à jour de la carte d'ajout d'énigme
// ==============================
window.mettreAJourCarteAjoutEnigme = function () {
  const carte = document.getElementById('carte-ajout-enigme');
  if (!carte) return;

  const panel = document.querySelector('.edition-panel-chasse');
  if (!panel) return;

  const selectors = [
    '[data-champ="post_title"]',
    '[data-champ="chasse_principale_image"]',
    '[data-champ="chasse_principale_description"]'
  ];

  const incomplets = selectors.filter(sel => {
    const li = panel.querySelector('.resume-infos ' + sel);
    return li && li.classList.contains('champ-vide');
  });

  if (incomplets.length === 0) {
    carte.classList.remove('disabled');
    carte.removeAttribute('disabled');
  } else {
    carte.classList.add('disabled');
    carte.setAttribute('disabled', 'disabled');
  }
};

// ==============================
// 🎨 Mise à jour visuelle de l'intro de la chasse
// ==============================
window.mettreAJourEtatIntroChasse = function () {
  const section = document.querySelector('.chasse-section-intro');
  const panel = document.querySelector('.edition-panel-chasse');
  if (!section || !panel) return;

  const selectors = [
    '[data-champ="post_title"]',
    '[data-champ="chasse_principale_image"]',
    '[data-champ="chasse_principale_description"]'
  ];

  const incomplets = selectors.filter(sel => {
    const li = panel.querySelector('.resume-infos ' + sel);
    return li && li.classList.contains('champ-vide');
  });

  section.classList.toggle('champ-vide-obligatoire', incomplets.length > 0);
};

// ================================
// 👥 Mise à jour dynamique de l'affichage du nombre de gagnants
// ================================
function mettreAJourAffichageNbGagnants(postId, nb) {
  const nbGagnantsAffichage = document.querySelector(`.nb-gagnants-affichage[data-post-id="${postId}"]`);
  const container = nbGagnantsAffichage?.closest('.caracteristique');
  const labelSpan = container?.querySelector('.caracteristique-label');
  if (!nbGagnantsAffichage || !labelSpan) return;

  const valeur = parseInt(nb, 10);
  if (valeur === 0) {
    nbGagnantsAffichage.textContent = ChasseNbGagnantsI18n.unlimited;
    labelSpan.textContent = ChasseNbGagnantsI18n.winnersLabel;
  } else {
    const format = valeur === 1 ? ChasseNbGagnantsI18n.single : ChasseNbGagnantsI18n.plural;
    nbGagnantsAffichage.textContent = wp.i18n.sprintf(format, valeur);
    labelSpan.textContent = ChasseNbGagnantsI18n.limitLabel;
  }
}

document.addEventListener('acf/submit_success', function (e) {
  DEBUG && console.log('✅ Formulaire ACF soumis avec succès', e);
  if (typeof window.mettreAJourResumeInfos === 'function') {
    window.mettreAJourResumeInfos();
  }
});

// ================================
// 🔁 Rafraîchissement dynamique du statut de la chasse
// ================================
function rafraichirStatutChasse(postId) {
  console.log('[rafraichirStatutChasse] postId=', postId);
  if (!postId) return;

  fetch(ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'forcer_recalcul_statut_chasse',
      post_id: postId
    })
  })
    .then(res => res.json())
    .then(stat => {
      if (!stat.success) {
        console.warn('⚠️ Échec recalcul statut chasse', stat);
        return;
      }

      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'recuperer_statut_chasse',
          post_id: postId
        })
      })
        .then(r => r.json())
        .then(data => {
          if (data.success && data.data?.statut) {
            const statut = data.data.statut;
            const label = data.data.statut_label;
            const icon = data.data.statut_icon || '';
            const tooltip = data.data.statut_tooltip || label || '';
            const wantsIconFormat = icon.trim().length > 0;
            const badge = document.querySelector(`.badge-statut[data-post-id="${postId}"]`);
            DEBUG && console.log('🔎 Badge trouvé :', badge);

            if (badge) {
              let extraClasses = Array.from(badge.classList).filter(
                cls => cls !== 'badge-statut' && !cls.startsWith('statut-')
              );

              if (wantsIconFormat && !extraClasses.includes('badge-statut--format-icon')) {
                extraClasses.push('badge-statut--format-icon');
              }

              if (!wantsIconFormat) {
                extraClasses = extraClasses.filter(cls => cls !== 'badge-statut--format-icon');
              }

              badge.className = ['badge-statut', `statut-${statut}`, ...extraClasses].join(' ');

              if (wantsIconFormat) {
                badge.innerHTML = `<span class="badge-statut__icon" aria-hidden="true">${icon}</span><span class="screen-reader-text">${label}</span>`;
                if (tooltip) {
                  badge.dataset.tooltip = tooltip;
                  badge.setAttribute('title', tooltip);
                  badge.setAttribute('aria-label', tooltip);
                } else {
                  badge.removeAttribute('data-tooltip');
                  badge.removeAttribute('title');
                  badge.removeAttribute('aria-label');
                }
                badge.setAttribute('role', 'img');
                badge.setAttribute('tabindex', '0');
              } else {
                badge.textContent = label;
                if (tooltip) {
                  badge.setAttribute('aria-label', tooltip);
                  badge.setAttribute('title', tooltip);
                } else {
                  badge.removeAttribute('aria-label');
                  badge.removeAttribute('title');
                }
                badge.removeAttribute('role');
                badge.removeAttribute('data-tooltip');
                badge.removeAttribute('data-tooltip-visible');
                badge.removeAttribute('tabindex');
              }
            } else {
              console.warn('❓ Aucun badge-statut trouvé pour postId', postId);
            }
          } else {
            console.warn('⚠️ Données statut invalides', data);
          }
        })
        .catch(err => {
          console.error('❌ Erreur réseau récupération statut chasse', err);
        });
    })
    .catch(err => {
      console.error('❌ Erreur réseau recalcul statut chasse', err);
    });
}

// ================================
// 💾 Enregistrement groupé des dates de chasse
// ================================
function enregistrerDatesChasse() {
  console.log('[enregistrerDatesChasse]');
  if (!inputDateDebut || !inputDateFin) return Promise.resolve(false);

  const postId = inputDateDebut.closest('.champ-chasse')?.dataset.postId;
  if (!postId) return Promise.resolve(false);

  const params = new URLSearchParams({
    action: 'modifier_dates_chasse',
    post_id: postId,
    date_debut: inputDateDebut.value.trim(),
    // On conserve toujours la date en base, même si l'affichage est "Illimitée"
    date_fin: inputDateFin.value.trim(),
    illimitee: toggleDateFin?.checked ? 0 : 1,
    debut_differee: toggleDateDebut?.checked ? 1 : 0
  });
  console.log('[enregistrerDatesChasse] params=', params.toString());

  return fetch(ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params
  })
    .then(r => r.json())
    .then(res => {
      console.log('[enregistrerDatesChasse] reponse=', res);
      if (res.success) {
        rafraichirStatutChasse(postId);
        mettreAJourAffichageDateFin();
        mettreAJourCaracteristiqueDate();
        return true;
      }
      console.error('❌ Erreur sauvegarde dates:', res.data);
      return false;
    })
    .catch(err => {
      console.error('❌ Erreur réseau sauvegarde dates:', err);
      return false;
    });
}
if (document.getElementById('chasse-date-debut') || document.getElementById('chasse-date-fin')) {
  mettreAJourMessageDate();
}
window.enregistrerDatesChasse = enregistrerDatesChasse;

// ================================
// 📥 Téléchargement du QR code sans redirection
// ================================
const qrDownloadBtn = document.querySelector('.qr-code-download');
qrDownloadBtn?.addEventListener('click', (e) => {
  e.preventDefault();
  const url = qrDownloadBtn.getAttribute('href');
  const filename = qrDownloadBtn.getAttribute('download') || 'qr-code.png';

  fetch(url)
    .then(res => res.blob())
    .then(blob => {
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(link.href);
    })
    .catch(err => {
      console.error('Erreur téléchargement QR code', err);
      window.location.href = url;
    });
});

