// ✅ champ-init.js bien chargé
console.log('✅ champ-init.js bien chargé');



// ================================
// 🛠️ Envoi AJAX d'un champ simple (texte, number, boolean)
// ================================
function modifierChampSimple(champ, valeur, postId, cpt = 'enigme') {
  console.log('📤 modifierChampSimple()', { champ, valeur, postId, cpt }); // ⬅️ test

  const action = (cpt === 'enigme') ? 'modifier_champ_enigme' :
    (cpt === 'organisateur') ? 'modifier_champ_organisateur' :
      'modifier_champ_chasse';

  return fetch(ajaxurl, {
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
        console.log(`✅ Champ ${champ} enregistré`);
        if (typeof window.onChampSimpleMisAJour === 'function') {
          window.onChampSimpleMisAJour(champ, postId, valeur, cpt);
        }
        return true; // important : pour pouvoir chaîner dans le .then(...)
      } else {
        console.warn(`⚠️ Échec enregistrement champ ${champ} :`, res?.data);
        return false;
      }
    })
    .catch(err => {
      console.error('❌ Erreur réseau AJAX :', err);
      return false;
    });
}




/// ==============================
// 📝 initChampTexte
// ==============================
function initChampTexte(bloc) {
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt;
  const postId = bloc.dataset.postId;
  const input = bloc.querySelector('.champ-input');
  const boutonEdit = bloc.querySelector('.champ-modifier');
  const boutonSave = bloc.querySelector('.champ-enregistrer');
  const boutonCancel = bloc.querySelector('.champ-annuler');
  const affichage = bloc.querySelector('.champ-affichage') || bloc;
  const edition = bloc.querySelector('.champ-edition');
  const isEditionDirecte = bloc.dataset.direct === 'true';

  const action = (cpt === 'chasse') ? 'modifier_champ_chasse'
    : (cpt === 'enigme') ? 'modifier_champ_enigme'
      : 'modifier_champ_organisateur';

  if (!champ || !cpt || !postId || !input) return;

  let feedback = bloc.querySelector('.champ-feedback');
  if (!feedback) {
    feedback = document.createElement('div');
    feedback.className = 'champ-feedback';
    bloc.appendChild(feedback);
  }

  // ✏️ Ouverture édition
  boutonEdit?.addEventListener('click', () => {
    if (affichage?.style) affichage.style.display = 'none';
    if (edition?.style) edition.style.display = 'flex';
    input.focus();

    feedback.textContent = '';
    feedback.className = 'champ-feedback';

    if (champ === 'profil_public_email_contact') {
      const fallback = window.organisateurData?.defaultEmail || '…';
      const affichageTexte = affichage.querySelector('p');
      if (affichageTexte && input.value.trim() === '') {
        affichageTexte.innerHTML = '<strong>Email de contact :</strong> <em>' + fallback + '</em>';
      }
    }
  });

  // ❌ Annulation
  boutonCancel?.addEventListener('click', () => {
    if (edition?.style) edition.style.display = 'none';
    if (affichage?.style) affichage.style.display = '';
    feedback.textContent = '';
    feedback.className = 'champ-feedback';
  });

  // ✅ Sauvegarde
  boutonSave?.addEventListener('click', () => {
    const valeur = input.value.trim();
    if (!champ || !postId) return;

    if (champ === 'profil_public_email_contact') {
      const isValide = valeur === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valeur);
      if (!isValide) {
        feedback.textContent = '⛔ Adresse email invalide';
        feedback.className = 'champ-feedback champ-error';
        return;
      }
    }

    if (champ === 'enigme_visuel_legende') {
      const legendeDOM = document.querySelector('.enigme-legende');
      if (legendeDOM) {
        legendeDOM.textContent = valeur;
        legendeDOM.classList.add('modifiee');
      }
    }

    if (champ === 'post_title') {
      if (!valeur) {
        feedback.textContent = '❌ Le titre est obligatoire.';
        feedback.className = 'champ-feedback champ-error';
        return;
      }
    }

    feedback.textContent = 'Enregistrement en cours...';
    feedback.className = 'champ-feedback champ-loading';

    modifierChampSimple(champ, valeur, postId, cpt).then(success => {
      if (success) {
        const affichageTexte = affichage.querySelector('h1, h2, p, span');

        if (champ === 'profil_public_email_contact') {
          const fallbackEmail = window.organisateurData?.defaultEmail || '…';
          const p = affichage.querySelector('p');
          if (p) {
            p.innerHTML = '<strong>Email de contact :</strong> ' + (valeur ? valeur : '<em>' + fallbackEmail + '</em>');
          }
        } else if (affichageTexte) {
          affichageTexte.textContent = valeur;
        }

        if (edition?.style) edition.style.display = 'none';
        if (affichage?.style) affichage.style.display = '';
        bloc.classList.toggle('champ-vide', !valeur);

        feedback.textContent = '';
        feedback.className = 'champ-feedback champ-success';

        if (typeof window.mettreAJourResumeInfos === 'function') {
          window.mettreAJourResumeInfos();
        }
      } else {
        feedback.textContent = 'Erreur lors de l’enregistrement.';
        feedback.className = 'champ-feedback champ-error';
      }
    });
  });

}



// ==============================
// initChampDeclencheur (déclenche ouverture + init JS au clic sur ✏️ résumé)
// ==============================
function initChampDeclencheur(bouton) {
  const champ = bouton.dataset.champ;
  const postId = bouton.dataset.postId;
  const cpt = bouton.dataset.cpt || 'organisateur';

  if (!champ || !postId || !cpt) return;

  bouton.addEventListener('click', () => {
    const bloc = document.querySelector(
      `.champ-${cpt}[data-champ="${champ}"][data-post-id="${postId}"]`
    );

    if (!bloc) return;

    // 🛡️ Sécurité : ignorer si c'est un résumé
    if (bloc.classList.contains('resume-ligne')) return;

    // ✅ Initialiser l’image dynamiquement si besoin
    if (bloc.classList.contains('champ-img') && typeof initChampImage === 'function') {
      initChampImage(bloc);
    }
    // ✅ Cas particulier : clic sur le stylo image → déclencher manuellement l’ouverture
    if (bloc.classList.contains('champ-img') && typeof bloc.__ouvrirMedia === 'function') {
      bloc.__ouvrirMedia();
      return; // rien d’autre à faire
    }


    // 🎯 Simuler clic sur vrai bouton si présent
    const vraiBouton = [...bloc.querySelectorAll('.champ-modifier')].find(b => b !== bouton);
    if (vraiBouton) vraiBouton.click();
  });
}







// ================================
// 💰 Initialisation affichage coût en points (Gratuit / Payant) — multi-CPT
// ================================
function initChampCoutPoints() {
  document.querySelectorAll('.champ-cout-points').forEach(bloc => {
    const input = bloc.querySelector('.champ-input.champ-cout[type="number"]');
    const checkbox = bloc.querySelector('input[type="checkbox"]');
    if (!input || !checkbox) return;

    const postId = bloc.dataset.postId;
    const champ = bloc.dataset.champ;
    const cpt = bloc.dataset.cpt;
    if (!postId || !champ || !cpt) return;

    let timerDebounce;
    let ancienneValeur = input.value.trim();

    // ✅ Initialisation checkbox (mais on ne désactive rien ici)
    const valeurInitiale = parseInt(input.value.trim(), 10);
    checkbox.checked = valeurInitiale === 0;

    const enregistrerCout = () => {
      clearTimeout(timerDebounce);
      timerDebounce = setTimeout(() => {
        let valeur = parseInt(input.value.trim(), 10);
        if (isNaN(valeur) || valeur < 0) valeur = 0;
        input.value = valeur;
        modifierChampSimple(champ, valeur, postId, cpt);

        if (typeof window.onCoutPointsUpdated === 'function') {
          window.onCoutPointsUpdated(bloc, champ, valeur, postId, cpt);
        }
      }, 500);
    };

    input.addEventListener('input', enregistrerCout);

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        ancienneValeur = input.value.trim();
        input.value = 0;
      } else {
        const valeur = parseInt(ancienneValeur, 10);
        input.value = valeur > 0 ? valeur : 10;
      }
      enregistrerCout();
    });
  });
}

document.addEventListener('DOMContentLoaded', initChampCoutPoints);



// ================================
// 💰 Affichage conditionnel des boutons d'édition coût
// ================================
function initAffichageBoutonsCout() {
  document.querySelectorAll('.champ-cout-points .champ-input').forEach(input => {
    const container = input.closest('.champ-enigme');
    if (!container) return; // 🔐 sécurise
    const boutons = container.querySelector('.champ-inline-actions');
    if (!boutons) return;

    // Sauvegarde la valeur initiale
    input.dataset.valeurInitiale = input.value.trim();

    /// Avant d'ajouter les événements
    boutons.style.transition = 'none';
    boutons.style.opacity = '0';
    boutons.style.visibility = 'hidden';

    // Ensuite (petit timeout pour réactiver les transitions après masquage immédiat)
    setTimeout(() => {
      boutons.style.transition = 'opacity 0.3s ease, visibility 0.3s ease';
    }, 50);

    input.addEventListener('input', () => {
      let val = input.value.trim();
      if (val === '') val = '0'; // Vide = 0

      const initiale = input.dataset.valeurInitiale;
      if (val !== initiale) {
        boutons.style.opacity = '1';
        boutons.style.visibility = 'visible';
      } else {
        boutons.style.opacity = '0';
        boutons.style.visibility = 'hidden';
      }
    });

    input.addEventListener('blur', () => {
      if (input.value.trim() === '') {
        input.value = '0';
      }
    });
  });
}
initAffichageBoutonsCout();


// ================================
// 💰 Initialisation affichage coût en points (Gratuit / Payant) — multi-CPT
// ================================
function initChampCoutPoints() {
  document.querySelectorAll('.champ-cout-points').forEach(bloc => {
    const input = bloc.querySelector('.champ-input.champ-cout[type="number"]');
    const checkbox = bloc.querySelector('input[type="checkbox"]');
    if (!input || !checkbox) return;

    const postId = bloc.dataset.postId;
    const champ = bloc.dataset.champ;
    if (!postId || !champ) return;

    let timerDebounce;
    let ancienneValeur = input.value.trim();

    const enregistrerCout = () => {
      clearTimeout(timerDebounce);
      timerDebounce = setTimeout(() => {
        let valeur = parseInt(input.value.trim(), 10);
        if (isNaN(valeur) || valeur < 0) valeur = 0;
        input.value = valeur;
        modifierChampSimple(champ, valeur, postId, bloc.dataset.cpt);

        // ✅ Mise à jour visuelle du badge coût pour la chasse
        if (
          champ === 'caracteristiques.chasse_infos_cout_points' &&
          typeof mettreAJourAffichageCout === 'function'
        ) {
          mettreAJourAffichageCout(postId, valeur);
        }
      }, 500);
    };

    // ✅ état initial : disable si gratuit
    const valeurInitiale = parseInt(input.value.trim(), 10);
    if (valeurInitiale === 0) {
      input.disabled = true;
      checkbox.checked = true;
    } else {
      input.disabled = false;
      checkbox.checked = false;
    }

    input.addEventListener('input', enregistrerCout);

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        ancienneValeur = input.value.trim();
        input.value = 0;
        input.disabled = true;
      } else {
        const valeur = parseInt(ancienneValeur, 10);
        input.value = valeur > 0 ? valeur : 10;
        input.disabled = false;
      }
      enregistrerCout();
    });
  });
}
document.addEventListener('DOMContentLoaded', initChampCoutPoints);


/**
 * Initialisation d’un champ conditionnel basé sur un groupe de radios.
 * 
 * @param {string} nomChamp       → nom de l’attribut name (ex. "acf[enigme_acces_condition]")
 * @param {Object} correspondance → mapping { valeurRadio: [selectors à afficher] }
 */
function initChampConditionnel(nomChamp, correspondance) {
  const radios = document.querySelectorAll(`input[name="${nomChamp}"]`);
  if (!radios.length) {
    console.warn('❌ Aucun input trouvé pour', nomChamp);
    return;
  }

  function toutMasquer() {
    const tousSelectors = new Set(Object.values(correspondance).flat());
    tousSelectors.forEach(sel => {
      document.querySelectorAll(sel).forEach(el => el.classList.add('cache'));
    });
  }

  function mettreAJourAffichageCondition() {
    const valeur = [...radios].find(r => r.checked)?.value;
    console.log(`🔁 ${nomChamp} → valeur sélectionnée :`, valeur);

    toutMasquer();

    const selectorsAAfficher = correspondance[valeur];
    if (selectorsAAfficher) {
      console.log(`✅ Affiche :`, selectorsAAfficher);
      selectorsAAfficher.forEach(sel => {
        document.querySelectorAll(sel).forEach(el => el.classList.remove('cache'));
      });
    } else {
      console.warn('⚠️ Aucune correspondance prévue pour :', valeur);
    }
  }

  radios.forEach(r =>
    r.addEventListener('change', () => {
      console.log('🖱️ Changement détecté →', r.value);
      mettreAJourAffichageCondition();
    })
  );

  mettreAJourAffichageCondition();
}


// ==============================
// 📩 Enregistrement dynamique – Champs radio simples
// ==============================
function initChampRadioAjax(nomChamp, cpt = 'enigme') {
  const radios = document.querySelectorAll(`input[name="${nomChamp}"]`);
  if (!radios.length) return;

  radios.forEach(radio => {
    radio.addEventListener('change', function () {
      const bloc = radio.closest('[data-champ]');
      const champ = bloc?.dataset.champ;
      const postId = bloc?.dataset.postId;

      if (!champ || !postId) return;

      modifierChampSimple(champ, this.value, postId, cpt);
    });
  });
}


// ==============================
// 🔘 Initialisation des cases à cocher true_false
// ==============================
function initChampBooleen(bloc) {
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt;
  const postId = bloc.dataset.postId;
  const checkbox = bloc.querySelector('input[type="checkbox"]');
  if (!champ || !cpt || !postId || !checkbox) return;

  checkbox.addEventListener('change', () => {
    const valeur = checkbox.checked ? 1 : 0;
    modifierChampSimple(champ, valeur, postId, cpt);
  });
}
