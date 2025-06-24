// ✅ enigme-edit.js
var DEBUG = window.DEBUG || false;
DEBUG && console.log('✅ enigme-edit.js chargé (readyState =', document.readyState, ')');

let boutonToggle;
let panneauEdition;



function initEnigmeEdit() {
  DEBUG && console.log('[enigme-edit] initEnigmeEdit');
  boutonToggle = document.getElementById('toggle-mode-edition-enigme');
  panneauEdition = document.querySelector('.edition-panel-enigme');
  DEBUG && console.log('[enigme-edit] boutonToggle=', !!boutonToggle, '| panneauEdition=', !!panneauEdition);

  // ==============================
  // 🛠️ Contrôles panneau principal
  // ==============================
  boutonToggle?.addEventListener('click', () => {
    DEBUG && console.log('[enigme-edit] toggle clicked');
    document.body.classList.toggle('edition-active-enigme');
    document.body.classList.toggle('panneau-ouvert');
  });


  panneauEdition?.querySelector('.panneau-fermer')?.addEventListener('click', () => {
    DEBUG && console.log('[enigme-edit] panneau fermé');
    document.body.classList.remove('edition-active-enigme');
    document.body.classList.remove('panneau-ouvert');
    document.activeElement?.blur();
  });


  // ==============================
  // 🧭 Déclencheur automatique
  // ==============================
  const params = new URLSearchParams(window.location.search);
  const doitOuvrir = params.get('edition') === 'open';
  if (doitOuvrir && boutonToggle) {
    boutonToggle.click();
    DEBUG && console.log('🔧 Ouverture auto du panneau édition énigme via ?edition=open');
  }


  // ==============================
  // 🟢 Initialisation des champs
  // ==============================
  document.querySelectorAll('.champ-enigme[data-champ]').forEach((bloc) => {
    const champ = bloc.dataset.champ;

    if (bloc.classList.contains('champ-img') && champ !== 'enigme_visuel_image') {
      if (typeof initChampImage === 'function') initChampImage(bloc);
    } else {
      if (typeof initChampTexte === 'function') initChampTexte(bloc);
    }
  });


  // ==============================
  // 🧩 Affichage conditionnel – Champs radio
  // ==============================
  initChampConditionnel('acf[enigme_mode_validation]', {
    'aucune': [],
    'manuelle': ['.champ-groupe-tentatives'],
    'automatique': ['.champ-groupe-reponse-automatique', '.champ-groupe-tentatives']
  });


  // ==============================
  // 🧠 Explication dynamique – Mode de validation de l’énigme
  // ==============================
  const explicationValidation = {
    'aucune': "Aucun formulaire de réponse ne sera affiché pour cette énigme.",
    'manuelle': "Le joueur devra rédiger une réponse libre. Vous recevrez sa proposition par email, et pourrez la valider ou la refuser directement depuis ce message.",
    'automatique': "Le joueur devra saisir une réponse exacte. Celle-ci sera automatiquement vérifiée selon les critères définis (réponse attendue, casse, variantes)."
  };

  const zoneExplication = document.querySelector('.champ-explication-validation');
  if (zoneExplication) {
    document.querySelectorAll('input[name="acf[enigme_mode_validation]"]').forEach((radio) => {
      radio.addEventListener('change', () => {
        const val = radio.value;
        DEBUG && console.log(val)
        zoneExplication.textContent = explicationValidation[val] || '';
      });
      if (radio.checked) {
        zoneExplication.textContent = explicationValidation[radio.value] || '';
      }
    });
  }


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
  const blocCout = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"]');
  if (blocCout && typeof window.onCoutPointsUpdated === 'function') {
    const champ = blocCout.dataset.champ;
    const valeur = parseInt(blocCout.querySelector('.champ-input')?.value || '0', 10);
    const postId = blocCout.dataset.postId;
    const cpt = blocCout.dataset.cpt;

    window.onCoutPointsUpdated(blocCout, champ, valeur, postId, cpt);
  }


  // ==============================
  // 🔐 Champ bonne réponse – Limite 75 caractères + message d’alerte
  // ==============================
  const bloc = document.querySelector('[data-champ="enigme_reponse_bonne"]');
  if (bloc) {
    const input = bloc.querySelector('.champ-input');
    const champ = bloc.dataset.champ;
    const postId = bloc.dataset.postId;
    const cptChamp = bloc.dataset.cpt || 'enigme';
    let timerSauvegarde;

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

        if (champ && postId) {
          clearTimeout(timerSauvegarde);
          timerSauvegarde = setTimeout(() => {
            modifierChampSimple(champ, input.value.trim(), postId, cptChamp);
          }, 400);
        }
      });
      const enigmeId = panneauEdition?.dataset.postId;
      if (enigmeId) {
        forcerRecalculStatutEnigme(enigmeId);
      }
    }
  }


  document.querySelectorAll('[data-champ="enigme_reponse_casse"]').forEach(bloc => {
    if (typeof initChampBooleen === 'function') initChampBooleen(bloc);
  });
  initChampNbTentatives();
  initChampRadioAjax('acf[enigme_mode_validation]');
  const enigmeId = panneauEdition?.dataset.postId;

  initChampPreRequis();
  initChampSolution();
  initSolutionInline();
  initChampConditionnel('acf[enigme_acces_condition]', {
    'date_programmee': ['#champ-enigme-date'],
    'pre_requis': ['#champ-enigme-pre-requis']
  });
  initChampRadioAjax('acf[enigme_acces_condition]');
  appliquerEtatGratuitEnLive(); // ✅ Synchronise état initial de "Gratuit"

  if (enigmeId) {
    document.querySelectorAll('input[name="acf[enigme_acces_condition]"]').forEach(radio => {
      radio.addEventListener('change', () => {
        forcerRecalculStatutEnigme(enigmeId);
      });
    });
  }

  initPanneauVariantes();

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
        } else {
          console.warn('⚠️ Échec recalcul statut énigme :', res.data);
        }
      });
  }


  (() => {
    const $cout = document.querySelector('.champ-cout');
    const $checkbox = document.getElementById('cout-gratuit-enigme');

    if (!$cout || !$checkbox) return;

    const raw = $cout.value;
    const trimmed = raw.trim();
    const valeur = trimmed === '' ? null : parseInt(trimmed, 10);

    DEBUG && console.log('[INIT GRATUIT] valeur brute =', raw, '| valeur interprétée =', valeur);

  const estGratuit = valeur === 0;

  $checkbox.checked = estGratuit;
  $cout.disabled = estGratuit;
  })();

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

  // ✅ Crée un message d'aide dynamique
  let aide = bloc.querySelector('.champ-aide-tentatives');
  if (!aide) {
    aide = document.createElement('p');
    aide.className = 'champ-aide champ-aide-tentatives';
    aide.style.margin = '5px 0 0 10px';
    aide.style.fontSize = '0.9em';
    aide.style.color = '#ccc';
    bloc.appendChild(aide);
  }

  // 🔄 Fonction centralisée
  function mettreAJourAideTentatives() {
    const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
    if (!coutInput) return;

    const cout = parseInt(coutInput.value.trim(), 10);
    const estGratuit = isNaN(cout) || cout === 0;
    const valeur = parseInt(input.value.trim(), 10); // ✅ ligne manquante

    aide.textContent = estGratuit
      ? "Mode gratuit : maximum 24 tentatives par jour."
      : "Mode payant : tentatives illimitées.";

    if (estGratuit && valeur > 24) {
      input.value = '24';
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

if (document.readyState === 'loading') {
  DEBUG && console.log('[enigme-edit] waiting DOMContentLoaded');
  document.addEventListener('DOMContentLoaded', () => {
    DEBUG && console.log('[enigme-edit] DOMContentLoaded');
    initEnigmeEdit();
  });
} else {
  initEnigmeEdit();
}



// ================================
// 💰 Hook personnalisé – Réaction au champ coût (CPT énigme uniquement)
// ================================
window.onCoutPointsUpdated = function (bloc, champ, valeur, postId, cpt) {
  if (champ === 'enigme_tentative.enigme_tentative_cout_points') {
    const champMax = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_max"] .champ-input');
    if (champMax) {
      const valeurActuelle = parseInt(champMax.value, 10);

      if (valeur === 0) {
        // Mode gratuit → limite à 24 max
        champMax.max = 24;

        // Si supérieur, on ramène à 24 (ou 5 selon logique métier ? à vérifier)
        if (valeurActuelle > 24) {
          champMax.value = '24';
          modifierChampSimple('enigme_tentative.enigme_tentative_max', 24, postId, cpt);
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
document.addEventListener('DOMContentLoaded', () => {
  const bloc = document.querySelector('[data-champ="enigme_reponse_bonne"]');
  if (!bloc) return;

  const input = bloc.querySelector('.champ-input');
  if (!input) return;

  const champ = bloc.dataset.champ;
  const postId = bloc.dataset.postId;
  const cptChamp = bloc.dataset.cpt || 'enigme';
  let timerSauvegarde;

  // Crée ou récupère l’alerte si déjà existante
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

    if (longueur > 75) {
      input.value = input.value.slice(0, 75);
    }

    if (longueur >= 75) {
      alerte.textContent = '75 caractères maximum atteints.';
      alerte.style.display = '';
    } else {
      alerte.textContent = '';
      alerte.style.display = 'none';
    }

    if (champ && postId) {
      clearTimeout(timerSauvegarde);
      timerSauvegarde = setTimeout(() => {
        modifierChampSimple(champ, input.value.trim(), postId, cptChamp);
      }, 400);
    }
  });
});


// ==============================
// 🧩 Gestion du panneau variantes
// ==============================
function initPanneauVariantes() {
  const boutonOuvrir = document.querySelector('.ouvrir-panneau-variantes');
  const panneau = document.getElementById('panneau-variantes-enigme');
  const formulaire = document.getElementById('formulaire-variantes-enigme');
  const postId = formulaire?.dataset.postId;
  const wrapper = formulaire?.querySelector('.liste-variantes-wrapper');
  const boutonAjouter = document.getElementById('bouton-ajouter-variante');
  const messageLimite = document.querySelector('.message-limite-variantes');

  if (!boutonOuvrir || !panneau || !formulaire || !postId || !wrapper || !boutonAjouter || !messageLimite) return;

  // Ouvrir le panneau
  boutonOuvrir.addEventListener('click', () => {
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
    if (!e.target.classList.contains('bouton-supprimer-ligne')) return;
    const ligne = e.target.closest('.ligne-variante');
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
    nouvelle.querySelector('.input-texte').placeholder = 'réponse déclenchante';

    nouvelle.querySelector('.input-message').value = '';
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

    const donnees = {};
    const lignes = wrapper.querySelectorAll('.ligne-variante');
    let index = 1;

    lignes.forEach((ligne) => {
      const texte = ligne.querySelector('.input-texte')?.value.trim();
      const message = ligne.querySelector('.input-message')?.value.trim();
      const casse = ligne.querySelector('input[type="checkbox"]')?.checked;

      if (texte && message) {
        donnees[`variante_${index}`] = {
          [`texte_${index}`]: texte,
          [`message_${index}`]: message,
          [`respecter_casse_${index}`]: casse ? 1 : 0
        };
        index++;
      }
    });

    const payload = JSON.stringify(donnees);
    const feedback = formulaire.querySelector('.champ-feedback-variantes');
    if (feedback) {
      feedback.style.display = 'block';
      feedback.textContent = 'Enregistrement...';
      feedback.className = 'champ-feedback champ-loading';
    }

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'modifier_champ_enigme',
        champ: 'enigme_reponse_variantes',
        valeur: payload,
        post_id: postId
      })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          if (feedback) {
            feedback.textContent = '✔️ Variantes enregistrées';
            feedback.className = 'champ-feedback champ-success';
          }

          setTimeout(() => {
            panneau.classList.remove('ouvert');
            document.body.classList.remove('panneau-ouvert');
            panneau.setAttribute('aria-hidden', 'true');

            const resume = document.querySelector('[data-champ="enigme_reponse_variantes"] .champ-modifier');
            if (resume) {
              let nb = 0;
              Object.values(donnees).forEach(v => {
                const texte = (v[Object.keys(v).find(k => k.startsWith('texte_'))] || '').trim();
                const message = (v[Object.keys(v).find(k => k.startsWith('message_'))] || '').trim();
                if (texte && message) nb++;
              });

              resume.textContent = nb === 0
                ? '➕ Créer des variantes'
                : (nb === 1 ? '1 variante ✏️' : `${nb} variantes ✏️`);
            }

            if (feedback) feedback.textContent = '';
          }, 1000);
        } else {
          if (feedback) {
            feedback.textContent = '❌ Erreur : ' + (res.data || 'inconnue');
            feedback.className = 'champ-feedback champ-error';
          }
        }
      })
      .catch(() => {
        if (feedback) {
          feedback.textContent = '❌ Erreur réseau';
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

  if (champ !== 'enigme_acces.date') return;

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

    bloc.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const checkboxes = [...bloc.querySelectorAll('input[type="checkbox"]')];
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
        });
      });
    });
  });
}




// ==============================
// 🧩 Initialisation panneau solution
// ==============================
function initChampSolution() {
  const modeSelecteurs = document.querySelectorAll('input[name="acf[enigme_solution_mode]"]');
  if (!modeSelecteurs.length) return;

  const wrapperDelai = document.querySelector('.acf-field[data-name="enigme_solution_delai"]');
  const wrapperDate = document.querySelector('.acf-field[data-name="enigme_solution_date"]');
  const wrapperExplication = document.querySelector('.acf-field[data-name="enigme_solution_explication"]');
  const wrapperFichier = document.querySelector('.acf-field[data-name="enigme_solution_fichier"]');

  function afficherChamps(valeur) {
    if (wrapperDelai) wrapperDelai.style.display = (valeur === 'delai_fin_chasse') ? '' : 'none';
    if (wrapperDate) wrapperDate.style.display = (valeur === 'date_fin_chasse') ? '' : 'none';

    if (valeur === 'jamais') {
      wrapperExplication?.classList.add('acf-hidden');
      wrapperFichier?.classList.add('acf-hidden');
      return;
    }
  }

  modeSelecteurs.forEach(radio => {
    radio.addEventListener('change', () => afficherChamps(radio.value));
    if (radio.checked) afficherChamps(radio.value);
  });
}


// ==============================
// 🧩 Initialisation inline – solution de l’énigme
// ==============================
function initSolutionInline() {
  const bloc = document.querySelector('.champ-solution-mode');
  if (!bloc) {
    console.warn('initSolutionInline() : .champ-solution-mode introuvable');
    return;
  }

  const postId = bloc.dataset.postId;
  const cpt = bloc.dataset.cpt || 'enigme';

  const radios = bloc.querySelectorAll('input[name="acf[enigme_solution_mode]"]');
  const zoneFichier = bloc.querySelector('.champ-solution-fichier');
  const zoneTexte = bloc.querySelector('.champ-solution-texte');
  const boutonTexte = bloc.querySelector('#ouvrir-panneau-solution');

  const inputDelai = bloc.querySelector('#solution-delai');
  const selectHeure = bloc.querySelector('#solution-heure');
  const inputFichier = bloc.querySelector('#solution-pdf-upload');
  const feedbackFichier = bloc.querySelector('.champ-feedback');

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      const val = radio.value;
      modifierChampSimple('enigme_solution_mode', val, postId, cpt);

      if (val === 'pdf') {
        zoneFichier.style.display = '';
        zoneTexte.style.display = 'none';

        // Déclenche automatiquement la sélection de fichier PDF
        setTimeout(() => {
          inputFichier?.click();
        }, 100); // petit délai pour laisser le DOM s'afficher
      }

      if (val === 'texte') {
        zoneFichier.style.display = 'none';
        zoneTexte.style.display = ''; // on montre l'encart avec le bouton
        setTimeout(() => {
          boutonTexte?.click(); // on simule le clic pour ouvrir le panneau latéral
        }, 100); // petit délai pour laisser le DOM se stabiliser
      }
    });
  });

  // 🔄 Affichage initial selon valeur radio
  const checked = bloc.querySelector('input[name="acf[enigme_solution_mode]"]:checked');
  if (checked?.value === 'pdf') {
    zoneFichier.style.display = '';
    zoneTexte.style.display = 'none';
  } else if (checked?.value === 'texte') {
    zoneFichier.style.display = 'none';
    zoneTexte.style.display = '';
  }

  // ⏳ Modification du délai (jours)
  inputDelai?.addEventListener('input', () => {
    const valeur = parseInt(inputDelai.value.trim(), 10);
    if (!isNaN(valeur)) {
      modifierChampSimple('enigme_solution_delai', valeur, postId, cpt);
    }
  });

  // 🕒 Modification de l'heure
  selectHeure?.addEventListener('change', () => {
    const valeur = selectHeure.value;
    modifierChampSimple('enigme_solution_heure', valeur, postId, cpt);
  });

  // 📎 Upload fichier PDF
  inputFichier?.addEventListener('change', () => {
    const fichier = inputFichier.files[0];
    if (!fichier || fichier.type !== 'application/pdf') {
      feedbackFichier.textContent = '❌ Fichier invalide. PDF uniquement.';
      feedbackFichier.className = 'champ-feedback champ-error';
      return;
    }

    const formData = new FormData();
    formData.append('action', 'enregistrer_fichier_solution_enigme');
    formData.append('post_id', postId);
    formData.append('fichier_pdf', fichier);

    feedbackFichier.textContent = '⏳ Enregistrement en cours...';
    feedbackFichier.className = 'champ-feedback champ-loading';

    fetch(ajaxurl, {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          feedbackFichier.textContent = '✅ Fichier enregistré';
          feedbackFichier.className = 'champ-feedback champ-success';
        } else {
          feedbackFichier.textContent = '❌ Erreur : ' + (res.data || 'inconnue');
          feedbackFichier.className = 'champ-feedback champ-error';
        }
      })
      .catch(() => {
        feedbackFichier.textContent = '❌ Erreur réseau';
        feedbackFichier.className = 'champ-feedback champ-error';
      });
  });
}


// ==============================
// ✏️ Panneau solution (texte)
// ==============================
document.addEventListener('click', (e) => {
  const btn = e.target.closest('#ouvrir-panneau-solution'); // ou '.ouvrir-panneau-solution' si classe
  if (!btn) return;

  const panneau = document.getElementById('panneau-solution-enigme');
  if (!panneau) return;

  document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
    p.classList.remove('ouvert');
    p.setAttribute('aria-hidden', 'true');
  });

  panneau.classList.add('ouvert');
  document.body.classList.add('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'false');
});


// ==============================
// ✖️ Fermeture panneau solution (wysiwyg)
// ==============================
document.querySelector('#panneau-solution-enigme .panneau-fermer')?.addEventListener('click', () => {
  const panneau = document.getElementById('panneau-solution-enigme');
  panneau.classList.remove('ouvert');
  document.body.classList.remove('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'true');
});



// ==============================
// ✅ Enregistrement condition "pré-requis" à la sélection du radio
// ==============================
document.addEventListener('DOMContentLoaded', () => {
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
});

function appliquerEtatGratuitEnLive() {
  DEBUG && console.log('✅ enappliquerEtatGratuit() chargé');
  const $cout = document.querySelector('.champ-cout');
  const $checkbox = document.getElementById('cout-gratuit-enigme');
  if (!$cout || !$checkbox) return;

  function syncGratuit() {
    const val = parseInt($cout.value.trim(), 10);
    const estGratuit = val === 0;

    DEBUG && console.log('[🎯 syncGratuit] coût =', $cout.value, '| gratuit ?', estGratuit);
    $checkbox.checked = estGratuit;
    $cout.disabled = estGratuit;
  }

  $cout.addEventListener('input', syncGratuit);
  $cout.addEventListener('change', syncGratuit);

  // Appel initial différé de 50ms pour laisser le temps à la valeur d’être injectée
  setTimeout(syncGratuit, 50);
}
