// ✅ champ-init.js bien chargé
console.log('✅ champ-init.js bien chargé');

// ==============================
// 🔄 MAJ dynamique des classes champ-vide / champ-rempli
// ==============================
window.mettreAJourResumeInfos = function () {

  // 🔵 ORGANISATEUR
  const panneauOrganisateur = document.querySelector('.panneau-organisateur');
  if (panneauOrganisateur) {
    panneauOrganisateur.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;
      const bloc = document.querySelector('.champ-organisateur[data-champ="' + champ + '"]');

      let estRempli = bloc && !bloc.classList.contains('champ-vide');

      if (champ === 'post_title') {
        const valeurTitre = bloc?.querySelector('.champ-input')?.value.trim().toLowerCase();
        const titreParDefaut = "votre nom d’organisateur";
        estRempli = valeurTitre && valeurTitre !== titreParDefaut;
      }

      if (champ === 'coordonnees_bancaires') {
        const iban = document.getElementById('champ-iban')?.value.trim();
        const bic = document.getElementById('champ-bic')?.value.trim();
        estRempli = !!(iban && bic);
      }

      if (champ === 'liens_publics') {
        const ul = bloc?.querySelector('.liste-liens-publics');
        estRempli = ul && ul.children.length > 0;
      }

      // Mise à jour visuelle
      ligne.classList.toggle('champ-rempli', estRempli);
      ligne.classList.toggle('champ-vide', !estRempli);

      ligne.querySelectorAll('.icone-check, .icon-attente').forEach(i => i.remove());

      const icone = document.createElement('i');
      icone.className = estRempli
        ? 'fa-solid fa-circle-check icone-check'
        : 'fa-regular fa-circle icon-attente';
      icone.setAttribute('aria-hidden', 'true');
      ligne.prepend(icone);
    });
  }

  // 🟠 CHASSE
  const panneauChasse = document.querySelector('.edition-panel-chasse');
  if (panneauChasse) {
    panneauChasse.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;

      // 🎯 [NOUVEAU] Ignorer les champs du groupe caractéristiques
      if (champ.startsWith('caracteristiques.') && champ !== 'caracteristiques_chasse_infos_recompense_valeur') {
        return; // On saute toutes sauf la récompense
      }


      const blocEdition = document.querySelector('.champ-chasse[data-champ="' + champ + '"]');

      let estRempli = false;

      if (blocEdition && !blocEdition.classList.contains('champ-vide')) {
        estRempli = true;
      }

      // Cas spécifiques chasse
      if (champ === 'post_title') {
        const valeurTitre = blocEdition?.querySelector('.champ-input')?.value.trim().toLowerCase();
        const titreParDefaut = window.CHP_CHASSE_DEFAUT?.titre || 'nouvelle chasse';
        estRempli = valeurTitre && valeurTitre !== titreParDefaut;
      }

      if (champ === 'chasse_principale_description') {
        const texte = document.querySelector('#panneau-description-chasse textarea')?.value?.trim();
        estRempli = !!texte;
      }

      if (champ === 'chasse_principale_image') {
        const image = blocEdition?.querySelector('img');
        estRempli = image && !image.src.includes('defaut-chasse');
      }

      if (champ === 'chasse_principale_liens') {
        const ul = document.querySelector('.champ-chasse[data-champ="chasse_principale_liens"] .liste-liens-publics');
        estRempli = ul && ul.children.length > 0;
      }

      if (champ === 'caracteristiques_chasse_infos_recompense_valeur') {
        const titre = document.getElementById('champ-recompense-titre')?.value.trim();
        const texte = document.getElementById('champ-recompense-texte')?.value.trim();
        const valeur = parseFloat(document.getElementById('champ-recompense-valeur')?.value || '0');

        estRempli = (titre.length > 0) && (texte.length > 0) && (valeur > 0);
      }

      // Mise à jour visuelle
      ligne.classList.toggle('champ-rempli', estRempli);
      ligne.classList.toggle('champ-vide', !estRempli);

      ligne.querySelectorAll('.icone-check, .icon-attente').forEach(i => i.remove());

      const icone = document.createElement('i');
      icone.className = estRempli
        ? 'fa-solid fa-circle-check icone-check'
        : 'fa-regular fa-circle icon-attente';
      icone.setAttribute('aria-hidden', 'true');
      ligne.prepend(icone);
    });
  }
  // 🔵 Affichage du CTA création chasse (organisateur)
  const cta = document.getElementById('cta-creer-chasse');
  if (cta) {
    const champsObligatoires = [
      '.panneau-organisateur .champ-titre',
      '.panneau-organisateur .champ-logo',
      '.panneau-organisateur .champ-description'
    ];

    const incomplets = champsObligatoires.filter(sel => {
      const champ = document.querySelector(sel);
      return champ && champ.classList.contains('champ-vide');
    }).length;

    if (incomplets === 0) {
      cta.style.display = ''; // Affiche le CTA
    } else {
      cta.style.display = 'none'; // Cache le CTA
    }
  }
  // 🧩 ENIGME
  const panneauEnigme = document.querySelector('.edition-panel-enigme');
  if (panneauEnigme) {
    panneauEnigme.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;
      const blocEdition = document.querySelector(`.champ-enigme[data-champ="${champ}"]`);

      let estRempli = false;

      // Règles spécifiques pour les énigmes
      if (champ === 'post_title') {
        const valeur = blocEdition?.querySelector('.champ-input')?.value.trim().toLowerCase();
        const titreParDefaut = 'nouvelle énigme';
        estRempli = valeur && valeur !== titreParDefaut;
      }

      if (champ === 'enigme_visuel_image') {
        const ligne = panneauEnigme.querySelector(`[data-champ="enigme_visuel_image"]`);
        estRempli = ligne?.dataset.rempli === '1';
      }

      if (champ === 'enigme_visuel_legende') {
        const val = blocEdition?.querySelector('.champ-input')?.value.trim();
        estRempli = !!val;
      }

      if (champ === 'enigme_visuel_texte') {
        const textarea = document.querySelector('#panneau-description-enigme textarea');
        estRempli = textarea && textarea.value.trim().length > 0;
      }

      if (champ === 'enigme_mode_validation') {
        const checked = document.querySelector('input[name="acf[enigme_mode_validation]"]:checked');
        estRempli = !!checked;
      }

      if (champ === 'enigme_tentative.enigme_tentative_cout_points') {
        const val = parseInt(blocEdition?.querySelector('input')?.value || '', 10);
        estRempli = !isNaN(val);
      }

      if (champ === 'enigme_tentative.enigme_tentative_max') {
        const val = parseInt(blocEdition?.querySelector('input')?.value || '', 10);
        estRempli = !isNaN(val) && val > 0;
      }

      if (champ === 'enigme_reponse_bonne') {
        const val = blocEdition?.querySelector('input')?.value?.trim();
        estRempli = !!val;

        if (blocEdition) {
          blocEdition.classList.toggle('champ-attention', !estRempli);
        }
      }

      if (champ === 'enigme_reponse_variantes') {
        const bouton = blocEdition?.querySelector('.champ-modifier');
        estRempli = bouton && !bouton.textContent.includes('Créer');
      }

      if (champ === 'enigme_acces_condition') {
        const checked = document.querySelector('input[name="acf[enigme_acces_condition]"]:checked');
        estRempli = !!checked;
      }

      if (champ === 'enigme_acces_date') {
        const input = blocEdition?.querySelector('input[type="date"]');
        const val = input?.value?.trim();
        estRempli = val && val.length === 10;
      }

      if (champ === 'enigme_acces_pre_requis') {
        const checked = blocEdition?.querySelectorAll('input[type="checkbox"]:checked')?.length > 0;
        estRempli = checked;
      }

      if (champ === 'enigme_style_affichage') {
        const select = blocEdition?.querySelector('select');
        estRempli = !!select?.value;
      }

      if (champ === 'enigme_solution_mode') {
        const checked = blocEdition?.querySelector('input[name="acf[enigme_solution_mode]"]:checked');
        estRempli = !!checked;
      }

      if (champ === 'enigme_solution_delai') {
        const val = parseInt(document.querySelector('#solution-delai')?.value || '', 10);
        estRempli = !isNaN(val) && val >= 0;
      }

      if (champ === 'enigme_solution_heure') {
        const val = document.querySelector('#solution-heure')?.value?.trim();
        estRempli = !!val;
      }
      mettreAJourLigneResume(ligne, champ, estRempli, 'enigme');
    });
    // ✅ Marquage spécial si bonne réponse manquante
    const blocBonneReponse = panneauEnigme.querySelector('[data-champ="enigme_reponse_bonne"]');
    const inputBonneReponse = blocBonneReponse?.querySelector('input');
    if (blocBonneReponse && inputBonneReponse) {
      const estVide = !inputBonneReponse.value.trim();
      blocBonneReponse.classList.toggle('champ-attention', estVide);
    }

  }
};



// ================================
// 📦 Petite fonction utilitaire commune pour éviter de répéter du code
// ================================
function mettreAJourLigneResume(ligne, champ, estRempli, type) {
  ligne.classList.toggle('champ-rempli', estRempli);
  ligne.classList.toggle('champ-vide', !estRempli);

  // Nettoyer anciennes icônes
  ligne.querySelectorAll(':scope > .icone-check, :scope > .icon-attente').forEach((i) => i.remove());

  // Ajouter nouvelle icône
  const icone = document.createElement('i');
  icone.className = estRempli
    ? 'fa-solid fa-circle-check icone-check'
    : 'fa-regular fa-circle icon-attente';
  icone.setAttribute('aria-hidden', 'true');
  ligne.prepend(icone);

  // Ajouter bouton édition ✏️ si besoin
  const dejaBouton = ligne.querySelector('.champ-modifier');

  if (!dejaBouton) {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'champ-modifier';
    bouton.textContent = '✏️';
    bouton.setAttribute('aria-label', 'Modifier le champ ' + champ);

    bouton.addEventListener('click', () => {
      const blocCible = document.querySelector(`.champ-${type}[data-champ="${champ}"]`);
      const boutonInterne = blocCible?.querySelector('.champ-modifier');
      boutonInterne?.click();
    });

    ligne.appendChild(bouton);
  }
}

// ==============================
// 📅 Initialisation globale des champs date
// ==============================
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

// ================================
// 🛠️ Envoi AJAX d'un champ simple (texte, number, boolean)
// ================================
function modifierChampSimple(champ, valeur, postId, cpt = 'enigme') {
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

    fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action, champ, valeur, post_id: postId })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
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
      })
      .catch(() => {
        feedback.textContent = 'Erreur réseau.';
        feedback.className = 'champ-feedback champ-error';
      });
  });
}



// ==============================
// initChampDeclencheur
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
    if (bloc.classList.contains('resume-ligne')) {
      return; // Ne pas essayer d'ouvrir l'édition sur une ligne résumé
    }

    const vraiBouton = [...bloc.querySelectorAll('.champ-modifier')].find(b => b !== bouton);

    if (vraiBouton) vraiBouton.click();
  });

}


// ==============================
// initChampImage (édition uniquement via panneau)
// ==============================
function initChampImage(bloc) {
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt;
  const postId = bloc.dataset.postId;

  const input = bloc.querySelector('.champ-input');
  const image = bloc.querySelector('img');
  const feedback = bloc.querySelector('.champ-feedback');

  // ✅ Injection dynamique du bouton si manquant ET panneau édition détecté
  let boutonEdit = bloc.querySelector('.champ-modifier');
  if (!boutonEdit && bloc.closest('.edition-panel-chasse, .panneau-organisateur')) {
    boutonEdit = document.createElement('button');
    boutonEdit.type = 'button';
    boutonEdit.className = 'champ-modifier bouton-modif-auto';
    boutonEdit.textContent = '✏️';
    const affichage = bloc.querySelector('.champ-affichage');
    if (affichage) affichage.appendChild(boutonEdit);
  }

  if (!champ || !cpt || !postId || !input || !image || !boutonEdit) return;

  let frame = null;

  boutonEdit.addEventListener('click', () => {
    if (frame) return frame.open();

    frame = wp.media({
      title: 'Choisir une image',
      multiple: false,
      library: { type: 'image' },
      button: { text: 'Utiliser cette image' }
    });

    frame.on('select', () => {
      const selection = frame.state().get('selection').first();
      const id = selection?.id;
      const url = selection?.attributes?.url;
      if (!id || !url) return;

      image.src = url;
      input.value = id;

      if (feedback) {
        feedback.textContent = 'Enregistrement...';
        feedback.className = 'champ-feedback champ-loading';
      }

      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: (cpt === 'chasse') ? 'modifier_champ_chasse' :
                  (cpt === 'enigme') ? 'modifier_champ_enigme' :
                  'modifier_champ_organisateur',
          champ,
          valeur: id,
          post_id: postId
        })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            bloc.classList.remove('champ-vide');
            if (feedback) {
              feedback.textContent = '';
              feedback.className = 'champ-feedback champ-success';
            }
            if (typeof window.mettreAJourResumeInfos === 'function') {
              window.mettreAJourResumeInfos();
            }
            if (typeof window.mettreAJourVisuelCPT === 'function') {
              mettreAJourVisuelCPT(cpt, postId, url);
            }
          } else {
            if (feedback) {
              feedback.textContent = '❌ Erreur : ' + (res.data || 'inconnue');
              feedback.className = 'champ-feedback champ-error';
            }
          }
        })
        .catch(() => {
          if (feedback) {
            feedback.textContent = '❌ Erreur réseau.';
            feedback.className = 'champ-feedback champ-error';
          }
        });
    });

    frame.open();
  });
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
