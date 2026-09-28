// ✅ enigme-edit.js
var DEBUG = window.DEBUG || false;
DEBUG && console.log('✅ enigme-edit.js chargé');

let boutonsToggle;
let panneauEdition;
let solutionsWrapper;
let solutionHeading;

function updateSolutionHeading(type) {
  if (!solutionHeading) return;
  const isEnigme = type === 'enigme';
  const template = isEnigme
    ? solutionHeading.dataset.titreEnigme
    : solutionHeading.dataset.titreChasse;
  const titre = isEnigme
    ? solutionHeading.dataset.enigmeTitle
    : solutionHeading.dataset.chasseTitle;
  if (template && typeof titre === 'string') {
    solutionHeading.textContent = template.replace('%s', titre);
  }
}

function rafraichirCarteSolutions() {
  solutionsWrapper = document.querySelector('.liste-solutions');
  solutionHeading = document.getElementById('enigme-section-solutions');

  const card = document.querySelector('.dashboard-card.champ-solutions');
  if (!card) return;
  const btnChasse = card.querySelector('.cta-solution-chasse');
  const btnEnigme = card.querySelector('.cta-solution-enigme');
  const chasseId =
    (btnChasse && btnChasse.dataset.objetId) ||
    (btnEnigme && btnEnigme.dataset.chasseId);
  const enigmeId =
    (btnEnigme && btnEnigme.dataset.objetId) ||
    (btnChasse && btnChasse.dataset.enigmeId);
  const ajaxUrl =
    (window.solutionsCreate && solutionsCreate.ajaxUrl) || window.ajaxurl;
  if (!ajaxUrl || !chasseId) return;

  const fd = new FormData();
  fd.append('action', 'chasse_solution_status');
  fd.append('chasse_id', chasseId);
  if (enigmeId) fd.append('enigme_id', enigmeId);
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
        const disableEnigme = !!res.data.has_solution_enigme;
        btnEnigme.classList.toggle('disabled', disableEnigme);
        btnEnigme.setAttribute('aria-disabled', disableEnigme);
        if (ChasseSolutions && ChasseSolutions.tooltipEnigme) {
          btnEnigme.title = disableEnigme ? ChasseSolutions.tooltipEnigme : '';
        }
      }

      const hasSolutions = !!res.data.has_solutions;
      card.classList.toggle('champ-rempli', hasSolutions);
      card.classList.toggle('champ-vide', !hasSolutions);

      if (solutionsWrapper) {
        const header = document.querySelector('.solutions-table-header');
        const toggleBtn = header ? header.querySelector('.solutions-toggle') : null;
        const total = parseInt(res.data.total_solutions || '0', 10);
        const hasEnigme = !!res.data.has_solution_enigme;
        const hasOther = hasEnigme ? total > 1 : total > 0;
        if (hasEnigme) {
          solutionsWrapper.dataset.objetType = 'enigme';
          solutionsWrapper.dataset.objetId = enigmeId;
          solutionsWrapper.dataset.page = '1';
          if (header && toggleBtn) {
            header.style.display = hasOther ? '' : 'none';
            toggleBtn.textContent =
              (ChasseSolutions && ChasseSolutions.toggleChasse) ||
              wp.i18n.__('Voir toutes les solutions de la chasse', 'chassesautresor-com');
          }
          updateSolutionHeading('enigme');
        } else {
          solutionsWrapper.dataset.objetType = 'chasse';
          solutionsWrapper.dataset.objetId = chasseId;
          solutionsWrapper.dataset.page = '1';
          if (header) header.style.display = 'none';
          if (toggleBtn) {
            toggleBtn.textContent =
              (ChasseSolutions && ChasseSolutions.toggleEnigme) ||
              wp.i18n.__('Voir la solution de cette énigme', 'chassesautresor-com');
          }
          updateSolutionHeading('chasse');
        }
        if (window.reloadSolutionsTable) {
          window.reloadSolutionsTable(solutionsWrapper);
        }
      }

      initDisabledSolutionButtons();
    })
    .catch(() => {});
}

window.rafraichirCarteSolutions = rafraichirCarteSolutions;

function initEnigmeEdit() {
  if (typeof initZonesClicEdition === 'function') initZonesClicEdition();
  boutonsToggle = document.querySelectorAll(
    '#toggle-mode-edition-enigme, .toggle-mode-edition-enigme'
  );
  panneauEdition = document.querySelector('.edition-panel-enigme');

  // ==============================
  // 🛠️ Contrôles panneau principal
  // ==============================
  const toggleEdition = () => {
    document.body.classList.toggle('edition-active-enigme');
    document.body.classList.toggle('panneau-ouvert');
    document.body.classList.toggle('mode-edition');
  };

  boutonsToggle.forEach((btn) => {
    btn.addEventListener('click', toggleEdition);
  });


  panneauEdition?.querySelector('.panneau-fermer')?.addEventListener('click', () => {
    document.body.classList.remove('edition-active-enigme');
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
  if (doitOuvrir && boutonsToggle.length > 0) {
    boutonsToggle[0].click();
    if (tab) {
      const btn = panneauEdition?.querySelector(
        `.edition-tab[data-target="enigme-tab-${tab}"]`
      );
      btn?.click();
    }
    DEBUG && console.log('🔧 Ouverture auto du panneau édition énigme via ?edition=open');
  }


  // ==============================
  // 🟢 Initialisation des champs
  // ==============================
  document.querySelectorAll('.champ-enigme[data-champ]').forEach((bloc) => {
    const champ = bloc.dataset.champ;
    if (champ === 'enigme_reponse_bonne') {
      return;
    }

    if (bloc.classList.contains('champ-img') && champ !== 'enigme_visuel_image') {
      if (typeof initChampImage === 'function') initChampImage(bloc);
    } else {
      if (typeof initChampTexte === 'function') initChampTexte(bloc);
    }
  });

  // ==============================
  // 🏷️ Titre dynamique du tableau des indices
  // ==============================
  const titreInput = document.querySelector('.champ-enigme[data-champ="post_title"] .champ-input');
  const indiceHeading = document.getElementById('enigme-section-indices');
  const indicesWrapper = document.querySelector('.liste-indices');

  function updateIndiceHeading(type) {
    if (!indiceHeading) return;
    const isEnigme = type === 'enigme';
    const template = isEnigme
      ? indiceHeading.dataset.titreEnigme
      : indiceHeading.dataset.titreChasse;
    const titre = isEnigme
      ? indiceHeading.dataset.enigmeTitle
      : indiceHeading.dataset.chasseTitle;
    if (template && typeof titre === 'string') {
      indiceHeading.textContent = template.replace('%s', titre);
    }
  }

  if (titreInput && indiceHeading) {
    titreInput.addEventListener('input', () => {
      indiceHeading.dataset.enigmeTitle = titreInput.value.trim();
      if (indicesWrapper?.dataset.objetType === 'enigme') {
        updateIndiceHeading('enigme');
      }
    });
  }

  updateIndiceHeading(indicesWrapper?.dataset.objetType || 'enigme');

  // ==============================
  // 🔀 Toggle indices table between enigme and chasse
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.indices-toggle');
    if (!btn || !indicesWrapper) return;
    const isEnigme = indicesWrapper.dataset.objetType === 'enigme';
    indicesWrapper.dataset.objetType = isEnigme ? 'chasse' : 'enigme';
    indicesWrapper.dataset.objetId = isEnigme
      ? indicesWrapper.dataset.chasseId
      : indicesWrapper.dataset.enigmeId;
    indicesWrapper.dataset.page = '1';
    btn.textContent = isEnigme
      ? wp.i18n.__('Voir les indices de cette énigme', 'chassesautresor-com')
      : wp.i18n.__('Voir tous les indices de la chasse', 'chassesautresor-com');
    updateIndiceHeading(indicesWrapper.dataset.objetType);
    if (typeof window.reloadIndicesTable === 'function') {
      window.reloadIndicesTable(indicesWrapper);
    }
  });

  // ==============================
  // 🏷️ Titre dynamique du tableau des solutions
  // ==============================
  solutionHeading = document.getElementById('enigme-section-solutions');
  solutionsWrapper = document.querySelector('.liste-solutions');
  if (titreInput && solutionHeading) {
    titreInput.addEventListener('input', () => {
      solutionHeading.dataset.enigmeTitle = titreInput.value.trim();
      if (solutionsWrapper?.dataset.objetType === 'enigme') {
        updateSolutionHeading('enigme');
      }
    });
  }
  updateSolutionHeading(solutionsWrapper?.dataset.objetType || 'enigme');

  // ==============================
  // 🔀 Toggle solutions table between enigme and chasse
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.solutions-toggle');
    if (!btn || !solutionsWrapper) return;
    const isEnigme = solutionsWrapper.dataset.objetType === 'enigme';
    solutionsWrapper.dataset.objetType = isEnigme ? 'chasse' : 'enigme';
    solutionsWrapper.dataset.objetId = isEnigme
      ? solutionsWrapper.dataset.chasseId
      : solutionsWrapper.dataset.enigmeId;
    solutionsWrapper.dataset.page = '1';
    btn.textContent = isEnigme
      ? (ChasseSolutions && ChasseSolutions.toggleEnigme) ||
        wp.i18n.__('Voir la solution de cette énigme', 'chassesautresor-com')
      : (ChasseSolutions && ChasseSolutions.toggleChasse) ||
        wp.i18n.__('Voir toutes les solutions de la chasse', 'chassesautresor-com');
    updateSolutionHeading(solutionsWrapper.dataset.objetType);
    if (typeof window.reloadSolutionsTable === 'function') {
      window.reloadSolutionsTable(solutionsWrapper);
    }
  });

  // ==============================
  // 🧩 Affichage conditionnel – Champs radio
  // ==============================
  initChampConditionnel('acf[enigme_mode_validation]', {
    'aucune': [],
    'manuelle': ['.champ-cout-points'],
    'automatique': ['.champ-groupe-reponse-automatique', '.champ-cout-points', '.champ-enigme.champ-nb-tentatives']
  });

  // ==============================
  // 📨 Onglet Tentatives – affichage selon mode de validation
  // ==============================
  const radiosValidation = document.querySelectorAll('input[name="acf[enigme_mode_validation]"]');
  const tabTentatives = panneauEdition?.querySelector('.edition-tab[data-target="enigme-tab-soumission"]');
  const contenuTentatives = document.getElementById('enigme-tab-soumission');
  const blocCoutValidation = document.querySelector('.champ-cout-points');

  function toggleTentativesTab(mode) {
    const afficher = mode !== 'aucune';
    if (tabTentatives) {
      tabTentatives.style.display = afficher ? '' : 'none';
      if (!afficher && tabTentatives.classList.contains('active')) {
        panneauEdition?.querySelector('.edition-tab[data-target="enigme-tab-param"]')?.click();
      }
    }
    if (!afficher && contenuTentatives) {
      contenuTentatives.style.display = 'none';
      contenuTentatives.classList.remove('active');
    }
  }

  function toggleCoutBloc(mode) {
    if (blocCoutValidation) {
      blocCoutValidation.style.display = mode === 'aucune' ? 'none' : '';
    }
  }

  const radioChecked = document.querySelector('input[name="acf[enigme_mode_validation]"]:checked');
  const modeInitial = radioChecked ? radioChecked.value : 'aucune';
  toggleTentativesTab(modeInitial);
  toggleCoutBloc(modeInitial);

  radiosValidation.forEach((radio) => {
    radio.addEventListener('change', (e) => {
      toggleTentativesTab(e.target.value);
      toggleCoutBloc(e.target.value);
    });
  });



  // ==============================
  // 🧰 Déclencheurs de résumé
  // ==============================
  document.querySelectorAll('.edition-panel-enigme .champ-modifier[data-champ]').forEach((btn) => {
    if (typeof initChampDeclencheur === 'function') initChampDeclencheur(btn);
  });


  // ==============================
  // 📜 Panneau description (wysiwyg)
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-description');
    if (!btn || btn.dataset.cpt !== 'enigme') return;

    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-description-enigme');
    }
  });
  document.querySelector('#panneau-description-enigme .panneau-fermer')?.addEventListener('click', () => {
    if (typeof window.closePanel === 'function') {
      window.closePanel('panneau-description-enigme');
    }
  });


  // ==============================
  // 🧪 Panneau variantes (réponses alternatives)
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-variantes');
    if (!btn || btn.dataset.cpt !== 'enigme') return;

    const panneau = document.getElementById('panneau-variantes-enigme');
    if (!panneau) return;

    document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
      p.classList.remove('ouvert');
      p.setAttribute('aria-hidden', 'true');
    });

    panneau.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'false');
  });
  document.querySelector('#panneau-variantes-enigme .panneau-fermer')?.addEventListener('click', () => {
    const panneau = document.getElementById('panneau-variantes-enigme');
    panneau.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'true');
  });


  // ==============================
  // 💰 Affichage dynamique tentatives (message coût)
  // ==============================
  const blocCoutTentative = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"]');
  if (blocCoutTentative && typeof window.onCoutPointsUpdated === 'function') {
    const champ = blocCoutTentative.dataset.champ;
    const valeur = parseInt(blocCoutTentative.querySelector('.champ-input')?.value || '0', 10);
    const postId = blocCoutTentative.dataset.postId;
    const cpt = blocCoutTentative.dataset.cpt;

    window.onCoutPointsUpdated(blocCoutTentative, champ, valeur, postId, cpt);
  }


  // ==============================
  // 🔐 Champ bonne réponse – Limite 75 caractères + message d’alerte
  // ==============================
  const bloc = document.querySelector('[data-champ="enigme_reponse_bonne"]');
  if (bloc) {
    const input = bloc.querySelector('.champ-input');
    if (input) {
      let alerte = bloc.querySelector('.message-limite');
      if (!alerte) {
        alerte = document.createElement('p');
        alerte.className = 'message-limite';
        alerte.style.color = 'var(--color-editor-error)';
        alerte.style.fontSize = '0.85em';
        alerte.style.margin = '4px 0 0 5px';
        alerte.style.display = 'none';
        input.insertAdjacentElement('afterend', alerte);
      }

      input.setAttribute('maxlength', '75');

      input.addEventListener('input', () => {
        const longueur = input.value.length;

        if (longueur > 75) input.value = input.value.slice(0, 75);

        if (longueur >= 75) {
          alerte.textContent = '75 caractères maximum atteints.';
          alerte.style.display = '';
        } else {
          alerte.textContent = '';
          alerte.style.display = 'none';
        }
      });
    }
  }


  document.querySelectorAll('[data-champ="enigme_reponse_casse"]').forEach(bloc => {
    if (typeof initChampBooleen === 'function') initChampBooleen(bloc);
  });
  initChampNbTentatives();
  initChampRadioAjax('acf[enigme_mode_validation]');
  mettreAJourCartesStats();
  const enigmeId = panneauEdition?.dataset.postId;

  document.querySelectorAll('input[name="acf[enigme_mode_validation]"]').forEach(radio => {
    radio.addEventListener('change', () => {
      if (enigmeId) {
        forcerRecalculStatutEnigme(enigmeId);
      }
      mettreAJourCartesStats();
    });
  });

  initChampAccesCondition();
  initChampPreRequis();
  initChampCoutPoints();

  initPanneauVariantes();
  initPagerTentatives();

  function forcerRecalculStatutEnigme(postId) {
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'forcer_recalcul_statut_enigme',
        post_id: postId
      })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          DEBUG && console.log('🔄 Statut système de l’énigme recalculé');
          mettreAJourCTAValidationChasse(postId);
        } else {
          console.warn('⚠️ Échec recalcul statut énigme :', res.data);
        }
      });
  }

  function mettreAJourCTAValidationChasse(postId) {
    const conteneur = document.getElementById('cta-validation-chasse');
    if (!conteneur) return;

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'actualiser_cta_validation_chasse',
        enigme_id: postId
      })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          conteneur.innerHTML = res.data?.html || '';
        } else {
          console.warn('⚠️ CTA validation non mis à jour :', res.data);
        }
      })
      .catch(err => console.error('❌ Erreur réseau CTA validation', err));
  }
  window.forcerRecalculStatutEnigme = forcerRecalculStatutEnigme;
  window.mettreAJourCTAValidationChasse = mettreAJourCTAValidationChasse;


  const boutonSupprimer = document.getElementById('bouton-supprimer-enigme');
  if (boutonSupprimer) {
    boutonSupprimer.addEventListener('click', () => {
      const postId = panneauEdition?.dataset.postId;
      if (!postId) return;

      if (!confirm('Voulez-vous vraiment supprimer cette énigme ?')) return;

      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'supprimer_enigme',
          post_id: postId
        })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success && res.data?.redirect) {
            window.location.href = res.data.redirect;
          } else {
            alert('Échec suppression : ' + (res.data || 'inconnue'));
          }
        })
        .catch(() => alert('Erreur réseau'));
    });
  }

  rafraichirCarteSolutions();

}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initEnigmeEdit);
} else {
  initEnigmeEdit();
}

window.addEventListener('solution-created', () => {
  rafraichirCarteSolutions();
});

// ================================
// 🖼️ Panneau images galerie (ACF gallery)
// ================================
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ouvrir-panneau-images');
  if (!btn || btn.dataset.cpt !== 'enigme') return;

  const panneau = document.getElementById('panneau-images-enigme');
  if (!panneau) return;

  const postId = btn.dataset.postId;
  if (!postId) return;

  // ❌ Ne PAS ouvrir le panneau ici

  fetch(ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'desactiver_htaccess_enigme',
      post_id: postId
    })
  })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        console.warn(`⚠️ Désactivation htaccess échouée ou inutile : ${data.data}`);
        return;
      }

      DEBUG && console.log(`🔓 htaccess désactivé pour énigme ${postId}`);

      // ✅ Ouverture du panneau uniquement maintenant
      if (typeof window.openPanel === 'function') {
        window.openPanel('panneau-images-enigme');
      }
    })
    .catch(err => {
      console.error('❌ Erreur réseau AJAX htaccess :', err);
    });
});



// ==============================
// 🔐 Restauration htaccess à la fermeture du panneau images
// ==============================
document.querySelector('#panneau-images-enigme .panneau-fermer')?.addEventListener('click', () => {
  if (typeof window.closePanel === 'function') {
    window.closePanel('panneau-images-enigme');
  }

  const postId = document.querySelector('.edition-panel-enigme')?.dataset.postId;
  if (postId) {
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'reactiver_htaccess_immediat_enigme',
        post_id: postId
      })
    }).then(r => r.json())
      .then(res => {
        if (res.success) {
          DEBUG && console.log(`🔒 htaccess restauré immédiatement pour énigme ${postId}`);
        } else {
          console.warn('⚠️ Erreur restauration htaccess immédiate :', res.data);
        }
      });
  }
});
// ================================
// 🔢 Initialisation champ enigme_tentative_max (tentatives/jour)
// ================================
function initChampNbTentatives() {
  const bloc = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_max"]');
  if (!bloc) return;

  const input = bloc.querySelector('.champ-input');
  const postId = bloc.dataset.postId;
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt || 'enigme';

  let timerDebounce;

  function mettreAJourAideTentatives() {
    const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
    if (!coutInput) return;

    const cout = parseInt(coutInput.value.trim(), 10);
    const estGratuit = isNaN(cout) || cout === 0;
    const valeur = parseInt(input.value.trim(), 10);

    if (estGratuit) {
      input.max = 24;
      if (valeur > 24) {
        input.value = '24';
      }
    } else {
      input.removeAttribute('max');
    }
  }

  // 💾 Enregistrement avec limite si nécessaire
  input.addEventListener('input', () => {
    clearTimeout(timerDebounce);

    let valeur = parseInt(input.value.trim(), 10);

    // 🔐 Forcer affichage visuel et valeur logique à 1 min
    if (isNaN(valeur) || valeur < 1) {
      valeur = 1;
      input.value = '1';
    }

    const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
    const cout = parseInt(coutInput?.value.trim() || '0', 10);
    const estGratuit = isNaN(cout) || cout === 0;

    if (estGratuit && valeur > 24) {
      valeur = 24;
      input.value = '24';
    }

    timerDebounce = setTimeout(() => {
      modifierChampSimple(champ, valeur, postId, cpt);
    }, 400);
  });


  // 💬 Mise à jour immédiate au chargement
  mettreAJourAideTentatives();

  // 🔁 Lié aux modifs de coût (input + checkbox)
  const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
  const checkbox = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] input[type="checkbox"]');
  if (coutInput) coutInput.addEventListener('input', mettreAJourAideTentatives);
  if (checkbox) checkbox.addEventListener('change', mettreAJourAideTentatives);

  // 🔄 Fonction exportée globalement
  window.mettreAJourMessageTentatives = mettreAJourAideTentatives;
}

// ================================
// 🔓 Gestion du champ d'accès (Libre / Date programmée)
// ================================
function initChampAccesCondition() {
  const radios = document.querySelectorAll('input[name="acf[enigme_acces_condition]"]');
  const blocDate = document.getElementById('champ-enigme-date');
  const inputDate = blocDate?.querySelector('input');
  const blocPre = document.getElementById('champ-enigme-pre-requis');
  if (!radios.length || !blocDate || !inputDate || !blocPre) return;

  const bloc = document.querySelector('[data-champ="enigme_acces_condition"]');
  const postId = bloc?.dataset.postId;
  const cpt = bloc?.dataset.cpt || 'enigme';

  function appliquerEtat() {
    const valeur = document.querySelector('input[name="acf[enigme_acces_condition]"]:checked')?.value || 'immediat';
    if (valeur === 'date_programmee') {
      blocDate.classList.remove('cache');
      blocPre.classList.add('cache');
      inputDate.disabled = false;
    } else if (valeur === 'pre_requis') {
      blocPre.classList.remove('cache');
      blocDate.classList.add('cache');
      inputDate.disabled = true;
    } else {
      blocDate.classList.add('cache');
      blocPre.classList.add('cache');
      inputDate.disabled = true;
    }
  }

  function enregistrer(valeur) {
    if (!postId) return;
    modifierChampSimple('enigme_acces_condition', valeur, postId, cpt);
  }

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      const valeur = radio.value;
      appliquerEtat();
      enregistrer(valeur);
      if (typeof window.forcerRecalculStatutEnigme === 'function' && postId) {
        window.forcerRecalculStatutEnigme(postId);
      }
    });
  });

  appliquerEtat();
}
// ================================
// 💸 Gestion du champ coût (Gratuit / Points)
// ================================
function initChampCoutPoints() {
  const toggle = document.getElementById('enigme-cout-toggle');
  const input = document.getElementById('enigme-tentative-cout');
  const blocInput = document.getElementById('champ-enigme-cout');
  if (!toggle || !input || !blocInput) return;

  const bloc = input.closest('[data-champ]');
  const postId = bloc?.dataset.postId;
  const cpt = bloc?.dataset.cpt || 'enigme';

  function appliquerEtat() {
    if (toggle.checked) {
      blocInput.classList.remove('cache');
      blocInput.style.display = '';
      input.disabled = false;
    } else {
      blocInput.classList.add('cache');
      blocInput.style.display = 'none';
      input.disabled = true;
      input.value = '0';
    }

    if (typeof window.onCoutPointsUpdated === 'function') {
      const valeur = parseInt(input.value || '0', 10);
      window.onCoutPointsUpdated(bloc, 'enigme_tentative_cout_points', valeur, postId, cpt);
    }
  }

  function enregistrer() {
    if (!postId) return;
    const valeur = toggle.checked ? input.value : '0';
    modifierChampSimple('enigme_tentative_cout_points', valeur, postId, cpt);
  }

  toggle.addEventListener('change', () => {
    appliquerEtat();
    enregistrer();
    mettreAJourCartesStats();
    if (typeof window.mettreAJourMessageTentatives === 'function') {
      window.mettreAJourMessageTentatives();
    }
  });

  input.addEventListener('change', () => {
    if (!toggle.checked) return;
    enregistrer();
    mettreAJourCartesStats();
    if (typeof window.mettreAJourMessageTentatives === 'function') {
      window.mettreAJourMessageTentatives();
    }
  });

  ['input'].forEach(evt => input.addEventListener(evt, () => {
    if (!toggle.checked) return;
    mettreAJourCartesStats();
    if (typeof window.mettreAJourMessageTentatives === 'function') {
      window.mettreAJourMessageTentatives();
    }
  }));

  appliquerEtat();
}



// ================================
// 💰 Hook personnalisé – Réaction au champ coût (CPT énigme uniquement)
// ================================
window.onCoutPointsUpdated = function (bloc, champ, valeur, postId, cpt) {
  if (champ === 'enigme_tentative_cout_points') {
    const champMax = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_max"] .champ-input');
    if (champMax) {
      const valeurActuelle = parseInt(champMax.value, 10);

      if (valeur === 0) {
        // Mode gratuit → limite à 24 max
        champMax.max = 24;

        // Si supérieur, on ramène à 24 (ou 5 selon logique métier ? à vérifier)
        if (valeurActuelle > 24) {
          champMax.value = '24';
          modifierChampSimple('enigme_tentative_max', 24, postId, cpt);
        }
      } else {
        // Mode payant → aucune limite
        champMax.removeAttribute('max');
      }
    }
  }
};



// ==============================
// 🔐 Champ bonne réponse – Limite 75 caractères + message d’alerte
// ==============================
function initChampBonnesReponses() {
  const bloc = document.querySelector('[data-champ="enigme_reponse_bonne"]');
  if (!bloc) return;

  const wrapper = bloc.querySelector('.bonnes-reponses-wrapper');
  const postId = bloc.dataset.postId;
  const cpt = bloc.dataset.cpt || 'enigme';
  const max = 5;
  let reponses = [];

  try {
    reponses = JSON.parse(bloc.dataset.reponses || '[]');
  } catch (e) {
    reponses = [];
  }

  const sauvegarder = () => {
    return modifierChampSimple(
      'enigme_reponse_bonne',
      JSON.stringify(reponses),
      postId,
      cpt
    );
  };

  const render = () => {
    wrapper.innerHTML = '';
    const estVide = reponses.length === 0;
    wrapper.classList.toggle('champ-vide-obligatoire', estVide);
    bloc.classList.toggle('champ-vide', estVide);
    bloc.classList.toggle('champ-rempli', !estVide);
    bloc.classList.toggle('champ-attention', estVide);

    if (reponses.length === 0) {
      const input = document.createElement('input');
      input.type = 'text';
      input.className = 'champ-input champ-texte-edit champ-vide-obligatoire';
      input.setAttribute('maxlength', '75');
      input.placeholder = wp.i18n.__('Ex : soleil', 'chassesautresor-com');

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'champ-modifier bonne-reponse-valider btn-obligatoire';
      btn.textContent = wp.i18n.__('valider', 'chassesautresor-com');

      const updateBlink = () => {
        const hasValue = input.value.trim().length > 0;
        wrapper.classList.toggle('champ-vide-obligatoire', !hasValue);
        input.classList.toggle('champ-vide-obligatoire', !hasValue);
        btn.classList.toggle('champ-vide-obligatoire', hasValue);
      };

      input.addEventListener('input', updateBlink);

      btn.addEventListener('click', () => {
        const val = input.value.trim();
        if (!val) return;
        reponses.push(val);
        sauvegarder().then((ok) => {
          if (ok) render();
        });
      });

      wrapper.appendChild(input);
      wrapper.appendChild(btn);
      updateBlink();
      return;
    }

    reponses.forEach((rep, index) => {
      const tag = document.createElement('span');
      tag.className = 'etiquette bonne-reponse-etiquette';
      tag.textContent = rep;

      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'bonne-reponse-supprimer';
      rm.setAttribute('aria-label', wp.i18n.__('Supprimer', 'chassesautresor-com'));
      rm.textContent = '×';
      rm.addEventListener('click', () => {
        reponses.splice(index, 1);
        sauvegarder().then((ok) => {
          if (ok) render();
        });
      });
      tag.appendChild(rm);
      wrapper.appendChild(tag);
    });

    if (reponses.length < max) {
      const btnAjout = document.createElement('button');
      btnAjout.type = 'button';
      btnAjout.className = 'champ-modifier bonne-reponse-ajouter';
      btnAjout.textContent = wp.i18n.__('ajouter', 'chassesautresor-com');

      btnAjout.addEventListener('click', (e) => {
        e.stopPropagation();
        btnAjout.remove();
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'champ-input champ-texte-edit';
        input.setAttribute('maxlength', '75');

        const valider = document.createElement('button');
        valider.type = 'button';
        valider.className = 'champ-modifier bonne-reponse-valider';
        valider.textContent = wp.i18n.__('valider', 'chassesautresor-com');

        const annuler = document.createElement('button');
        annuler.type = 'button';
        annuler.className = 'bonne-reponse-annuler';
        annuler.setAttribute('aria-label', wp.i18n.__('Annuler', 'chassesautresor-com'));
        annuler.textContent = '×';

        const cleanup = () => {
          input.remove();
          valider.remove();
          annuler.remove();
          document.removeEventListener('click', outsideClick);
          document.removeEventListener('keydown', escHandler);
          wrapper.appendChild(btnAjout);
        };

        const outsideClick = (e) => {
          if (!wrapper.contains(e.target)) {
            cleanup();
          }
        };

        const escHandler = (e) => {
          if (e.key === 'Escape') {
            cleanup();
          }
        };

        annuler.addEventListener('click', (e) => {
          e.stopPropagation();
          cleanup();
        });

        valider.addEventListener('click', () => {
          const val = input.value.trim();
          if (!val) return;
          reponses.push(val);
          document.removeEventListener('click', outsideClick);
          document.removeEventListener('keydown', escHandler);
          sauvegarder().then((ok) => {
            if (ok) render();
          });
        });

        document.addEventListener('click', outsideClick);
        document.addEventListener('keydown', escHandler);

        wrapper.appendChild(input);
        wrapper.appendChild(valider);
        wrapper.appendChild(annuler);
        input.focus();
      });

      wrapper.appendChild(btnAjout);
    }

    if (typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
  };

  render();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initChampBonnesReponses);
} else {
  initChampBonnesReponses();
}


// ==============================
// 🖼️ Libellé du bouton galerie ACF
// ==============================
function initLibelleBoutonGalerie() {
  if (!window.acf) return;

  const mettreAJour = (field) => {
    const el = field && field.nodeType ? field : field?.[0];
    if (!el) return;

    const bouton = el.querySelector('.acf-gallery-add');
    if (bouton) {
      const label = window.wp?.i18n?.__('Ajouter une illustration', 'chassesautresor-com') ?? 'Ajouter une illustration';
      bouton.textContent = label;
    }
  };

  window.acf.add_action('ready_field/type=gallery', mettreAJour);
  window.acf.add_action('append_field/type=gallery', mettreAJour);

  document.querySelectorAll('.acf-field[data-type="gallery"]').forEach(mettreAJour);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initLibelleBoutonGalerie);
} else {
  initLibelleBoutonGalerie();
}


// ==============================
// 🧩 Gestion du panneau variantes
// ==============================
function initPanneauVariantes() {
  const panneau = document.getElementById('panneau-variantes-enigme');
  const formulaire = document.getElementById('formulaire-variantes-enigme');
  const postId = formulaire?.dataset.postId;
  const wrapper = formulaire?.querySelector('.liste-variantes-wrapper');
  const boutonAjouter = document.getElementById('bouton-ajouter-variante');
  const messageLimite = document.querySelector('.message-limite-variantes');
  const resumeBloc = document.querySelector('[data-champ="enigme_reponse_variantes"]');
  const resumeContent = resumeBloc?.querySelector('.edition-row-content') || resumeBloc;
  let listeResume = resumeContent?.querySelector('.variantes-table');
  let lienAjouterResume = resumeContent?.querySelector('.champ-ajouter');
  let boutonEditerResume = resumeContent?.querySelector('.champ-modifier.ouvrir-panneau-variantes');

  if (listeResume && !boutonEditerResume) {
    boutonEditerResume = document.createElement('button');
    boutonEditerResume.type = 'button';
    boutonEditerResume.className = 'champ-modifier txt-small ouvrir-panneau-variantes';
    boutonEditerResume.dataset.cpt = 'enigme';
    boutonEditerResume.dataset.postId = postId;
    boutonEditerResume.setAttribute('aria-label', wp.i18n.__('Modifier les variantes', 'chassesautresor-com'));
    boutonEditerResume.textContent = wp.i18n.__('modifier', 'chassesautresor-com');
    resumeContent.appendChild(boutonEditerResume);
  }

  if (boutonEditerResume) {
    boutonEditerResume.style.display = 'inline-block';
    boutonEditerResume.addEventListener('click', e => {
      e.preventDefault();
      ouvrirPanneau();
    });
  }

  if (!panneau || !formulaire || !postId || !wrapper || !boutonAjouter || !messageLimite || !resumeBloc) return;

  function ouvrirPanneau() {
    document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach(p => {
      p.classList.remove('ouvert');
      p.setAttribute('aria-hidden', 'true');
    });
    panneau.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'false');

    const lignes = wrapper.querySelectorAll('.ligne-variante');
    if (lignes.length === 0) {
      ajouterLigneVariante();
    }

    mettreAJourEtatBouton();
  }

  resumeBloc.querySelectorAll('.ouvrir-panneau-variantes').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      ouvrirPanneau();
    });
  });

  // Fermer le panneau
  panneau.querySelector('.panneau-fermer')?.addEventListener('click', () => {
    panneau.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'true');
  });

  // Ajouter une ligne
  boutonAjouter.addEventListener('click', () => {
    ajouterLigneVariante();
    mettreAJourEtatBouton();
  });

  // Supprimer une ligne
  formulaire.addEventListener('click', (e) => {
    const btnSupprimer = e.target.closest('.bouton-supprimer-ligne');
    if (!btnSupprimer) return;
    const ligne = btnSupprimer.closest('.ligne-variante');
    const lignes = wrapper.querySelectorAll('.ligne-variante');

    if (!ligne) return;

    if (lignes.length > 1) {
      ligne.remove();
    } else {
      ligne.querySelector('.input-texte').value = '';
      ligne.querySelector('.input-message').value = '';
      ligne.querySelector('input[type="checkbox"]').checked = false;
    }

    mettreAJourEtatBouton();
  });

  // Recalcul du bouton à chaque frappe
  formulaire.addEventListener('input', mettreAJourEtatBouton);

  // Créer une ligne vide
  function ajouterLigneVariante() {
    const lignes = wrapper.querySelectorAll('.ligne-variante');
    const base = lignes[0];
    if (!base) return;

    const nouvelle = base.cloneNode(true);

    nouvelle.querySelector('.input-texte').value = '';
    nouvelle.querySelector('.input-texte').placeholder = wp.i18n.__("réponse déclenchant l'affichage du message", 'chassesautresor-com');

    nouvelle.querySelector('.input-message').value = '';
    nouvelle.querySelector('.input-message').placeholder = wp.i18n.__('Message affiché au joueur', 'chassesautresor-com');
    nouvelle.querySelector('input[type="checkbox"]').checked = false;

    wrapper.appendChild(nouvelle);
  }


  // Gérer affichage bouton et message
  function mettreAJourEtatBouton() {
    const lignes = wrapper.querySelectorAll('.ligne-variante');
    const nb = lignes.length;

    if (nb >= 4) {
      boutonAjouter.style.display = 'none';
      messageLimite.style.display = 'block';
      return;
    }

    const last = lignes[nb - 1];
    const texte = last?.querySelector('.input-texte')?.value.trim();
    const message = last?.querySelector('.input-message')?.value.trim();

    const ligneEstRemplie = texte && message;

    boutonAjouter.style.display = ligneEstRemplie ? 'inline-block' : 'none';
    messageLimite.style.display = 'none';
  }

  // Enregistrement
  formulaire.addEventListener('submit', (e) => {
    e.preventDefault();

    const lignes = wrapper.querySelectorAll('.ligne-variante');
    const updates = [];

    for (let i = 1; i <= 4; i++) {
      const ligne = lignes[i - 1];
      const texte = ligne?.querySelector('.input-texte')?.value.trim() || '';
      const message = ligne?.querySelector('.input-message')?.value.trim() || '';
      const casse = ligne?.querySelector('input[type="checkbox"]')?.checked ? 1 : 0;

      updates.push(['texte_' + i, texte]);
      updates.push(['message_' + i, message]);
      updates.push(['respecter_casse_' + i, casse]);
    }

    const feedback = formulaire.querySelector('.champ-feedback-variantes');
    if (feedback) {
      feedback.style.display = 'block';
      feedback.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
      feedback.className = 'champ-feedback champ-loading';
    }

    const promises = updates.map(([champ, valeur]) => {
      return fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'modifier_champ_enigme',
          champ,
          valeur,
          post_id: postId
        })
      }).then(r => r.json());
    });

    Promise.all(promises)
      .then(() => {
        if (feedback) {
          feedback.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
          feedback.className = 'champ-feedback champ-success';
          setTimeout(() => { feedback.innerHTML = ''; feedback.className = 'champ-feedback'; }, 1000);
        }

        setTimeout(() => {
          panneau.classList.remove('ouvert');
          document.body.classList.remove('panneau-ouvert');
          panneau.setAttribute('aria-hidden', 'true');

          if (resumeBloc) {
            if (!listeResume) {
              listeResume = document.createElement('table');
              listeResume.className = 'variantes-table';
              const thead = document.createElement('thead');
              const trHead = document.createElement('tr');
              const thTexte = document.createElement('th');
              thTexte.scope = 'col';
              thTexte.textContent = wp.i18n.__('Variante', 'chassesautresor-com');
              const thMessage = document.createElement('th');
              thMessage.scope = 'col';
              thMessage.textContent = wp.i18n.__('Message', 'chassesautresor-com');
              trHead.appendChild(thTexte);
              trHead.appendChild(thMessage);
              thead.appendChild(trHead);
              listeResume.appendChild(thead);
              const tbodyEl = document.createElement('tbody');
              listeResume.appendChild(tbodyEl);
              resumeContent.insertBefore(listeResume, boutonEditerResume || lienAjouterResume || null);
            }

            const tbody = listeResume.querySelector('tbody');
            tbody.innerHTML = '';
            let nb = 0;
            for (let i = 1; i <= 4; i++) {
              const t = updates.find(u => u[0] === 'texte_' + i)?.[1] || '';
              const m = updates.find(u => u[0] === 'message_' + i)?.[1] || '';
              if (t && m) {
                nb++;
                const tr = document.createElement('tr');
                tr.className = 'variante-resume';
                const tdT = document.createElement('td');
                tdT.className = 'variante-texte';
                tdT.textContent = t;
                const tdM = document.createElement('td');
                tdM.className = 'variante-message';
                tdM.textContent = m;
                tr.appendChild(tdT);
                tr.appendChild(tdM);
                tbody.appendChild(tr);
              }
            }

            if (nb === 0) {
              resumeBloc.classList.add('champ-vide');
              resumeBloc.classList.remove('champ-rempli');
              boutonEditerResume?.style.setProperty('display', 'none');

              if (listeResume) {
                listeResume.remove();
                listeResume = null;
              }

              if (!lienAjouterResume) {
                lienAjouterResume = document.createElement('a');
                lienAjouterResume.href = '#';
                lienAjouterResume.className = 'champ-ajouter ouvrir-panneau-variantes';
                lienAjouterResume.dataset.cpt = 'enigme';
                lienAjouterResume.dataset.postId = postId;
                lienAjouterResume.setAttribute('aria-label', wp.i18n.__('Ajouter des variantes', 'chassesautresor-com'));
                lienAjouterResume.textContent = wp.i18n.__('ajouter des variantes', 'chassesautresor-com');
                resumeContent.appendChild(lienAjouterResume);
                lienAjouterResume.addEventListener('click', e => {
                  e.preventDefault();
                  ouvrirPanneau();
                });
              }

              lienAjouterResume.style.setProperty('display', 'inline-block');
            } else {
              resumeBloc.classList.add('champ-rempli');
              resumeBloc.classList.remove('champ-vide');
              lienAjouterResume?.style.setProperty('display', 'none');

              if (!boutonEditerResume) {
                boutonEditerResume = document.createElement('button');
                boutonEditerResume.type = 'button';
                boutonEditerResume.className = 'champ-modifier txt-small ouvrir-panneau-variantes';
                boutonEditerResume.dataset.cpt = 'enigme';
                boutonEditerResume.dataset.postId = postId;
                boutonEditerResume.setAttribute('aria-label', wp.i18n.__('Modifier les variantes', 'chassesautresor-com'));
                boutonEditerResume.textContent = wp.i18n.__('modifier', 'chassesautresor-com');
                resumeContent.appendChild(boutonEditerResume);
                boutonEditerResume.addEventListener('click', e => {
                  e.preventDefault();
                  ouvrirPanneau();
                });
              }

              boutonEditerResume.style.setProperty('display', 'inline-block');
            }
          }

          if (feedback) feedback.textContent = '';
        }, 1000);
      })
      .catch(() => {
        if (feedback) {
          feedback.textContent = wp.i18n.__('❌ Erreur réseau', 'chassesautresor-com');
          feedback.className = 'champ-feedback champ-error';
        }
      });
  });
}

// ==============================
// 📅 Gestion post-update d’un champ de date
// ==============================
window.onDateFieldUpdated = function (input, nouvelleValeur) {
  const bloc = input.closest('[data-champ]');
  const champ = bloc?.dataset.champ;

  if (champ !== 'enigme_acces_date') return;

  const valeur = input.value?.trim() || '';

  // ❌ Champ vide → erreur et affichage
  if (!valeur) {
    afficherErreur(input, "Merci de sélectionner une date.");
    return;
  }

  // ✅ Sinon, on masque toute erreur éventuelle
  masquerErreur(input);
};


function afficherErreur(input, message) {
  const feedback = input.closest('.champ-enigme')?.querySelector('.champ-feedback');
  if (feedback) {
    feedback.textContent = message;
    feedback.style.display = 'block';
    feedback.style.color = 'red';
  }
}

function masquerErreur(input) {
  const feedback = input.closest('.champ-enigme')?.querySelector('.champ-feedback');
  if (feedback) {
    feedback.textContent = '';
    feedback.style.display = 'none';
  }
}

/**
 * 🧩 Initialisation du champ "pré-requis"
 * Corrige P1 + P2 : enregistre la condition pré-requis si nécessaire,
 * et repasse à "immediat" si toutes les cases sont décochées.
 */
function initChampPreRequis() {
  document.querySelectorAll('[data-champ="enigme_acces_pre_requis"]').forEach(bloc => {
    const champ = bloc.dataset.champ;
    const cpt = bloc.dataset.cpt;
    const postId = bloc.dataset.postId;

    const radioPre = document.querySelector('input[name="acf[enigme_acces_condition]"][value="pre_requis"]');
    const radioImmediat = document.querySelector('input[name="acf[enigme_acces_condition]"][value="immediat"]');
    const radiosCondition = document.querySelectorAll('input[name="acf[enigme_acces_condition]"]');
    const checkboxes = [...bloc.querySelectorAll('input[type="checkbox"]')];

    const majClasse = () => {
      const cochés = checkboxes.filter(el => el.checked);
      if (radioPre?.checked && cochés.length === 0) {
        bloc.classList.add('champ-vide');
      } else {
        bloc.classList.remove('champ-vide');
      }
    };

    // État initial et écoute sur changement de condition
    majClasse();
    radiosCondition.forEach(r => r.addEventListener('change', majClasse));

    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const cochés = checkboxes.filter(el => el.checked).map(el => el.value);

        // ✅ 1. Mise à jour des prérequis cochés
        modifierChampSimple(champ, cochés, postId, cpt).then(() => {
          // ✅ 2. Si une ou plusieurs cases sont cochées, enregistrer condition 'pre_requis'
          if (cochés.length > 0) {
            if (radioPre && !radioPre.checked) radioPre.checked = true;

            fetch(ajaxurl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'verifier_et_enregistrer_condition_pre_requis',
                post_id: postId
              })
            })
              .then(r => r.json())
              .then(res => {
                if (res.success) {
                  DEBUG && console.log('✅ Condition "pré-requis" bien enregistrée après mise à jour des cases');
                } else {
                  console.warn('⚠️ Échec condition pré-requis :', res.data);
                }
              });
          }

          // ❌ 3. Si aucune case cochée → on repasse à immédiat
          if (cochés.length === 0) {
            if (radioImmediat) radioImmediat.checked = true;
            modifierChampSimple('enigme_acces_condition', 'immediat', postId, cpt);
          }

          if (typeof window.forcerRecalculStatutEnigme === 'function') {
            window.forcerRecalculStatutEnigme(postId);
          }

          majClasse();
        });
      });
    });
  });
}




// ==============================
// ✅ Enregistrement condition "pré-requis" à la sélection du radio
// ==============================
function initEnregistrementPreRequis() {
  const radioPreRequis = document.querySelector('input[name="acf[enigme_acces_condition]"][value="pre_requis"]');
  const champBloc = document.querySelector('[data-champ="enigme_acces_pre_requis"]');
  const postId = champBloc?.dataset.postId;

  if (!radioPreRequis || !champBloc || !postId) return;

  radioPreRequis.addEventListener('change', () => {
    const cochés = [...champBloc.querySelectorAll('input[type="checkbox"]:checked')].map(cb => cb.value);

    // 🔒 Ne rien faire si aucune case cochée
    if (cochés.length === 0) {
      console.warn('⛔ Pré-requis non enregistré : aucune case cochée.');
      return;
    }

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'verifier_et_enregistrer_condition_pre_requis',
        post_id: postId
      })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          DEBUG && console.log('✅ Condition "pré-requis" enregistrée côté serveur');
        } else {
          console.warn('⚠️ Échec enregistrement condition pré-requis :', res.data);
        }
      })
      .catch(err => {
        console.error('❌ Erreur réseau lors de l’enregistrement de la condition pré-requis', err);
      });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initEnregistrementPreRequis);
} else {
  initEnregistrementPreRequis();
}

function mettreAJourCartesStats() {
  const mode = document.querySelector('input[name="acf[enigme_mode_validation]"]:checked')?.value || 'aucune';
  const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
  const cout = coutInput ? parseInt(coutInput.value || '0', 10) : 0;
  const cardTentatives = document.querySelector('#enigme-stats [data-stat="tentatives"]');
  const cardPoints = document.querySelector('#enigme-stats [data-stat="points"]');
  const cardSolutions = document.querySelector('#enigme-stats [data-stat="solutions"]');
  const resolveursSection = document.getElementById('enigme-resolveurs');

  if (cardTentatives) {
    cardTentatives.style.display = mode === 'aucune' ? 'none' : '';
  }
  if (cardPoints) {
    cardPoints.style.display = (mode === 'aucune' || cout <= 0) ? 'none' : '';
  }
  if (cardSolutions) {
    cardSolutions.style.display = mode === 'aucune' ? 'none' : '';
  }
  if (resolveursSection) {
    resolveursSection.style.display = mode === 'aucune' ? 'none' : '';
  }
}

function initPagerTentatives() {
  const wrapper = document.querySelector('#enigme-tab-soumission .liste-tentatives');
  const postId = document.querySelector('.edition-panel-enigme')?.dataset.postId;
  const compteur = document.querySelector('#enigme-tab-soumission .total-tentatives');
  if (!wrapper || !postId) return;

  function attachPager() {
    const pager = wrapper.querySelector('.pager');
    if (!pager) return;
    pager.addEventListener('pager:change', (e) => {
      const page = e.detail?.page || 1;
      charger(page);
    });
  }

  attachPager();

  function charger(page) {
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'lister_tentatives_enigme',
        enigme_id: postId,
        page
      })
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) return;
        wrapper.innerHTML = res.data.html;
        wrapper.dataset.page = res.data.page;
        wrapper.dataset.pages = res.data.pages;
        wrapper.dataset.total = res.data.total;
        if (compteur) compteur.textContent = '(' + res.data.total + ')';
        attachPager();
      });
  }
}

