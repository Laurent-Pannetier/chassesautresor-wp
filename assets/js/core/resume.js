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
        // ➕ Ajout d'une condition fallback sur le dataset ou la classe
        const aDesLiens = bloc?.classList.contains('champ-rempli') || bloc?.dataset.valeurs?.length > 0;
        estRempli = (ul && ul.children.length > 0) || aDesLiens;
      }


      // Mise à jour visuelle + marquage obligatoire
      mettreAJourLigneResume(ligne, champ, estRempli, 'organisateur');
    });
  }

  // 🟠 CHASSE
  const panneauChasse = document.querySelector('.edition-panel-chasse');
  if (panneauChasse) {
    panneauChasse.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;

      // 🎯 [NOUVEAU] Ignorer les champs du groupe caractéristiques
      if (champ.startsWith('chasse_infos_') && champ !== 'chasse_infos_recompense_valeur') {
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

      if (champ === 'chasse_principale_image') {
        const image = blocEdition?.querySelector('img');
        estRempli = image && !image.src.includes('defaut-chasse');
      }

      if (champ === 'chasse_principale_liens') {
        const ul = document.querySelector('.champ-chasse[data-champ="chasse_principale_liens"] .liste-liens-publics');
        estRempli = ul && ul.children.length > 0;
      }

      if (champ === 'chasse_infos_recompense_valeur') {
        const titre = document.getElementById('champ-recompense-titre')?.value.trim();
        const texte = document.getElementById('champ-recompense-texte')?.value.trim();
        const valeur = parseFloat(document.getElementById('champ-recompense-valeur')?.value || '0');

        estRempli = (titre.length > 0) && (texte.length > 0) && (valeur > 0);
      }

      // Mise à jour visuelle + marquage obligatoire
      mettreAJourLigneResume(ligne, champ, estRempli, 'chasse');
    });
  }

  // 🧩 ENIGME
  const panneauEnigme = document.querySelector('.edition-panel-enigme');
  if (panneauEnigme) {
    panneauEnigme.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;
      const blocEdition = document.querySelector(`.champ-enigme[data-champ="${champ}"]`);

      let estRempli = blocEdition && !blocEdition.classList.contains('champ-vide');

      // Règles spécifiques pour les énigmes
      if (champ === 'post_title') {
        const valeur = blocEdition?.querySelector('.champ-input')?.value.trim().toLowerCase();
        const titreParDefaut = 'en création';
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

      if (champ === 'enigme_mode_validation') {
        const checked = document.querySelector('input[name="acf[enigme_mode_validation]"]:checked');
        estRempli = !!checked;
      }

      if (champ.endsWith('enigme_tentative_cout_points')) {
        const val = parseInt(blocEdition?.querySelector('input')?.value || '', 10);
        estRempli = !isNaN(val);
      }

      if (champ.endsWith('enigme_tentative_max')) {
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
        const nbVar = blocEdition?.querySelectorAll('.variantes-table .variante-resume')?.length || 0;
        estRempli = nbVar > 0;
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
  if (typeof window.mettreAJourCarteAjoutEnigme === 'function') {
    window.mettreAJourCarteAjoutEnigme();
  }
  if (typeof window.mettreAJourEtatIntroChasse === 'function') {
    window.mettreAJourEtatIntroChasse();
  }
};

// ==============================
// ✅ Hook unifié – Réagit à toute modification simple de champ pour tous les CPTs
// ==============================
window.onChampSimpleMisAJour = function (champ, postId, valeur, cpt, extra) {
  cpt = cpt?.toLowerCase?.() || cpt;

  if (champ === 'post_title') {
    mettreAJourResumeTitre(cpt, valeur);
    if (typeof window.mettreAJourTitreHeader === 'function') {
      window.mettreAJourTitreHeader(cpt, valeur);
    }
    if (cpt === 'enigme' && typeof window.mettreAJourTitreMenuEnigme === 'function') {
      window.mettreAJourTitreMenuEnigme(valeur);
    }
  }

  // ✅ ORGANISATEUR : mise à jour image
  if (cpt === 'organisateur') {
    if (champ === 'logo_organisateur') {
      const bloc = document.querySelector(`.champ-organisateur[data-champ="${champ}"][data-post-id="${postId}"]`);
      if (bloc && typeof bloc.__ouvrirMedia === 'function') bloc.__ouvrirMedia();
    }
    const champsResume = [
      'post_title',
      'description_longue',
      'logo',
      'logo_organisateur',
      'email_contact',
      'coordonnees_bancaires',
      'liens_publics'
    ];
    if (champsResume.includes(champ) && typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
  }

  // ✅ CHASSE : image + statut
  if (cpt === 'chasse') {
    if (champ === 'chasse_principale_image') {
      const bloc = document.querySelector(`.champ-chasse[data-champ="${champ}"][data-post-id="${postId}"]`);
      if (bloc && typeof bloc.__ouvrirMedia === 'function') bloc.__ouvrirMedia();
    }
    const champsStatut = [
      'chasse_infos_date_debut',
      'chasse_infos_date_fin',
      'chasse_infos_duree_illimitee',
      'chasse_infos_cout_points',
      'chasse_cache_statut',
      'chasse_cache_statut_validation'
    ];
    if (champsStatut.includes(champ)) {
      rafraichirStatutChasse(postId);
      if (champ === 'chasse_infos_cout_points' && typeof window.mettreAJourBadgeCoutChasse === 'function') {
        window.mettreAJourBadgeCoutChasse(postId, parseInt(valeur, 10));
      }
    }
    const champsResume = ['post_title', 'chasse_principale_image'];
    if (champsResume.includes(champ) && typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
  }

  // ✅ ENIGME : résumé uniquement
  if (cpt === 'enigme') {
    const champsResume = [
      'post_title',
      'enigme_visuel_legende',
      'enigme_visuel_texte',
      'enigme_mode_validation',
      'enigme_tentative_cout_points',
      'enigme_tentative.enigme_tentative_cout_points',
      'enigme_tentative_max',
      'enigme_tentative.enigme_tentative_max',
      'enigme_reponse_bonne',
      'enigme_reponse_casse',
      'enigme_acces_condition',
      'enigme_acces_date',
      'enigme_acces_pre_requis',
      'enigme_style_affichage'
    ];

    if (champ === 'enigme_visuel_legende') {
      if (typeof window.mettreAJourLegendeEnigme === 'function') {
        window.mettreAJourLegendeEnigme(valeur);
      }
    }

    if (champ === 'enigme_reponse_bonne' && typeof window.forcerRecalculStatutEnigme === 'function') {
      window.forcerRecalculStatutEnigme(postId);
    }

    if (champsResume.includes(champ) && typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
    if (
      extra &&
      extra.complet_changed &&
      window.sidebarAside &&
      typeof window.sidebarAside.reload === 'function'
    ) {
      const cid = extra.chasse_id || document.querySelector('.enigme-navigation')?.dataset.chasseId;
      window.sidebarAside.reload(cid);
    }
  }

};

function mettreAJourResumeTitre(cpt, valeur) {
  const span = document.querySelector(`.edition-panel-${cpt} .resume-infos li[data-champ="post_title"] .champ-valeur`);
  if (!span) return;

  const titre = valeur?.trim() || '';
  let placeholder = '';
  let defaut = '';

  switch (cpt) {
    case 'chasse':
      placeholder = wp.i18n.__('renseigner le titre de la chasse', 'chassesautresor-com');
      defaut = window.CHP_CHASSE_DEFAUT?.titre || 'nouvelle chasse';
      break;
    case 'enigme':
      placeholder = 'renseigner le titre de l’énigme';
      defaut = 'en création';
      break;
    default:
      placeholder = 'renseigner le titre de l’organisateur';
      defaut = 'votre nom d’organisateur';
  }

  const estVide = !titre || titre.toLowerCase() === defaut.toLowerCase();
  span.textContent = estVide ? placeholder : titre;
}



// ================================
// 📦 Petite fonction utilitaire commune pour éviter de répéter du code
// ================================
function mettreAJourLigneResume(ligne, champ, estRempli, type) {
  ligne.classList.toggle('champ-rempli', estRempli);
  ligne.classList.toggle('champ-vide', !estRempli);
  const estObligatoire =
    ligne.closest('.resume-bloc')?.classList.contains('resume-obligatoire') &&
    !(
      (type === 'chasse' && champ === 'chasse_infos_recompense_valeur') ||
      (type === 'enigme' && ['enigme_visuel_legende', 'enigme_visuel_texte'].includes(champ))
    );
  ligne.classList.toggle('champ-attention', estObligatoire && !estRempli);

  const input = ligne.querySelector('input, textarea, select');
  if (input) {
    input.classList.toggle('champ-vide-obligatoire', estObligatoire && !estRempli);
  }

  const container = ligne.querySelector('.edition-row-label .edition-row-icon');

  // Nettoyer anciennes icônes
  if (container) {
    container.innerHTML = '';
  } else {
    ligne
      .querySelectorAll(':scope > .icone-check, :scope > .icon-attente')
      .forEach((i) => i.remove());
  }

  // Ajouter nouvelle icône si autorisé
  if (ligne.dataset.noIcon === undefined) {
    const icone = document.createElement('i');
    icone.className = estRempli
      ? 'fa-solid fa-circle-check icone-check'
      : 'fa-regular fa-circle icon-attente';
    icone.setAttribute('aria-hidden', 'true');
    if (container) {
      container.appendChild(icone);
    } else {
      ligne.prepend(icone);
    }
  }

  // Ajouter bouton édition ✏️ si besoin
  const dejaBouton = ligne.querySelector('.champ-modifier');
  const pasDEdition =
    ligne.dataset.noEdit !== undefined ||
    (champ === 'enigme_visuel_texte' && !estRempli);

  if (pasDEdition) {
    ligne.style.cursor = '';
    // Ne supprimer le bouton existant que s'il a été ajouté automatiquement
    if (ligne.dataset.noEdit === undefined) {
      dejaBouton?.remove();
    }
    return;
  }

  if (!dejaBouton && !ligne.querySelector('.champ-ajouter')) {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'champ-modifier txt-small';
    bouton.textContent = wp.i18n.__('modifier', 'chassesautresor-com');
    bouton.setAttribute('aria-label', 'Modifier le champ ' + champ);

    bouton.addEventListener('click', () => {
      const blocCible = document.querySelector(`.champ-${type}[data-champ="${champ}"]`);
      const boutonInterne = blocCible?.querySelector('.champ-modifier');
      boutonInterne?.click();
    });

    const champTexte = ligne.querySelector('.champ-texte');
    if (champTexte) {
      champTexte.appendChild(bouton);
    } else {
      ligne.appendChild(bouton);
    }
    if (typeof initZoneClicEdition === 'function') initZoneClicEdition(bouton);
  }
}
