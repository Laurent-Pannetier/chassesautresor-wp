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
  if (typeof window.mettreAJourCarteAjoutChasse === 'function') {
    window.mettreAJourCarteAjoutChasse();
  }
};

// ==============================
// ✅ Hook unifié – Réagit à toute modification simple de champ pour tous les CPTs
// ==============================
window.onChampSimpleMisAJour = function (champ, postId, valeur, cpt) {
  cpt = cpt?.toLowerCase?.() || cpt;

  // ✅ ORGANISATEUR : mise à jour titre + image
  if (cpt === 'organisateur') {
    if (champ === 'post_title' && typeof window.mettreAJourTitreHeader === 'function') {
      window.mettreAJourTitreHeader(cpt, valeur);
    }
    if (champ === 'profil_public_logo_organisateur') {
      const bloc = document.querySelector(`.champ-organisateur[data-champ="${champ}"][data-post-id="${postId}"]`);
      if (bloc && typeof bloc.__ouvrirMedia === 'function') bloc.__ouvrirMedia();
    }
    const champsResume = [
      'post_title',
      'description_longue',
      'profil_public_logo',
      'profil_public_logo_organisateur',
      'profil_public_email_contact',
      'coordonnees_bancaires',
      'liens_publics'
    ];
    if (champsResume.includes(champ) && typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
  }

  // ✅ CHASSE : titre + image + statut
  if (cpt === 'chasse') {
    if (champ === 'post_title' && typeof window.mettreAJourTitreHeader === 'function') {
      window.mettreAJourTitreHeader(cpt, valeur);
    }
    if (champ === 'chasse_principale_image') {
      const bloc = document.querySelector(`.champ-chasse[data-champ="${champ}"][data-post-id="${postId}"]`);
      if (bloc && typeof bloc.__ouvrirMedia === 'function') bloc.__ouvrirMedia();
    }
    const champsStatut = [
      'caracteristiques.chasse_infos_date_debut',
      'caracteristiques.chasse_infos_date_fin',
      'caracteristiques.chasse_infos_duree_illimitee',
      'caracteristiques.chasse_infos_cout_points',
      'champs_caches.chasse_cache_statut',
      'champs_caches.chasse_cache_statut_validation'
    ];
    if (champsStatut.includes(champ)) {
      rafraichirStatutChasse(postId);
    }
  }

  // ✅ ENIGME : résumé uniquement
  if (cpt === 'enigme') {
    const champsResume = [
      'post_title',
      'enigme_visuel_legende',
      'enigme_visuel_texte',
      'enigme_mode_validation',
      'enigme_tentative.enigme_tentative_cout_points',
      'enigme_tentative.enigme_tentative_max',
      'enigme_reponse_bonne',
      'enigme_reponse_casse',
      'enigme_acces_condition',
      'enigme_acces_date',
      'enigme_acces_pre_requis',
      'enigme_style_affichage',
      'enigme_solution_mode',
      'enigme_solution_delai',
      'enigme_solution_heure'
    ];

    if (champ === 'post_title' && typeof window.mettreAJourTitreHeader === 'function') {
      window.mettreAJourTitreHeader(cpt, valeur);
    }

    if (champ === 'enigme_visuel_legende') {
      const legende = document.querySelector('.enigme-soustitre');
      if (legende) legende.textContent = valeur;
    }

    if (champsResume.includes(champ) && typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
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
