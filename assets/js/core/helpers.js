/**
 * Génère les éléments DOM représentant les liens publics (ul ou placeholder).
 * Utilisable pour organisateur, chasse, etc.
 * @param {Array} liens - Tableau d’objets : [{ type_de_lien: 'facebook', url_lien: 'https://...' }]
 * @returns {HTMLElement} Elément racine contenant les liens
 */
const LIENS_PUBLICS_META = {
  site_web: {
    icone: 'fa-solid fa-globe',
    label: 'Site Web'
  },
  discord: {
    icone: 'fa-brands fa-discord',
    label: 'Discord'
  },
  facebook: {
    icone: 'fa-brands fa-facebook-f',
    label: 'Facebook'
  },
  twitter: {
    icone: 'fa-brands fa-x-twitter',
    label: 'Twitter/X'
  },
  instagram: {
    icone: 'fa-brands fa-instagram',
    label: 'Instagram'
  }
};

function normaliserUrlLien(url) {
  if (typeof url !== 'string') {
    return '';
  }

  const valeur = url.trim();
  if (valeur === '') {
    return '';
  }

  try {
    const objetUrl = new URL(valeur);
    const pathname = objetUrl.pathname === '/' ? '' : objetUrl.pathname;
    const recherche = objetUrl.search || '';
    const fragment = objetUrl.hash || '';

    return `${objetUrl.protocol}//${objetUrl.host}${pathname}${recherche}${fragment}`;
  } catch (_) {
    return valeur;
  }
}

function renderLiensPublics(liens = []) {

  if (!Array.isArray(liens) || liens.length === 0) {
    const placeholder = document.createElement('div');
    placeholder.className = 'liens-placeholder';

    const message = document.createElement('p');
    message.className = 'liens-placeholder-message';
    message.textContent = 'Aucun lien ajouté pour le moment.';
    placeholder.appendChild(message);

    Object.entries(LIENS_PUBLICS_META).forEach(([type, meta]) => {
      const i = document.createElement('i');
      i.className = `fa ${meta.icone} icone-grisee`;
      i.title = meta.label;
      placeholder.appendChild(i);
    });

    return placeholder;
  }

  const liste = document.createElement('ul');
  liste.className = 'liste-liens-publics';

  const showLabels = liens.length <= 2;

  if (!showLabels) {
    liste.classList.add('liens-sans-intitule');
  }

  liens.forEach(({ type_de_lien, url_lien }) => {
    const type = Array.isArray(type_de_lien) ? type_de_lien[0] : type_de_lien;
    const { icone, label } = LIENS_PUBLICS_META[type] || {
      icone: 'fa-link',
      label: type
    };
    const url = url_lien || '#';

    const li = document.createElement('li');
    li.className = 'item-lien-public';

    const a = document.createElement('a');
    a.className = `lien-public lien-${type}`;
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener';

    const icon = document.createElement('i');
    icon.className = `fa ${icone}`;
    a.appendChild(icon);

    if (showLabels) {
      const span = document.createElement('span');
      span.className = 'texte-lien';
      span.textContent = label;
      a.appendChild(span);
    }

    li.appendChild(a);
    liste.appendChild(li);
  });

  return liste;
}
window.renderLiensPublicsJS = renderLiensPublics;


function creerLiensMap(donnees) {
  const map = new Map();

  (Array.isArray(donnees) ? donnees : []).forEach((item) => {
    const typeBrut = item?.type_de_lien;
    const type = Array.isArray(typeBrut) ? typeBrut[0] : typeBrut;
    const typeNettoye = typeof type === 'string' ? type.trim() : '';
    const url = normaliserUrlLien(item?.url_lien);

    if (!typeNettoye || !url) return;
    map.set(typeNettoye, url);
  });

  return map;
}

function normaliserLiens(donnees, { trier = false } = {}) {
  const map = creerLiensMap(donnees);

  let resultat = Array.from(map.entries()).map(([type, url]) => ({
    type_de_lien: type,
    url_lien: url
  }));

  if (trier) {
    resultat = resultat
      .slice()
      .sort((a, b) => {
        if (a.type_de_lien === b.type_de_lien) {
          return a.url_lien.localeCompare(b.url_lien);
        }
        return a.type_de_lien.localeCompare(b.type_de_lien);
      });
  }

  return resultat;
}

function mettreAJourHeaderOrganisateurLiens(donnees) {
  const row = document.querySelector('.header-organisateur__liens-row');
  if (!row) return;

  const contact = row.querySelector('.lien-contact');
  if (!contact) return;

  const liens = normaliserLiens(donnees);
  let liste = row.querySelector('.header-organisateur__liens');

  if (liens.length === 0) {
    liste?.remove();
    return;
  }

  if (!liste) {
    liste = document.createElement('ul');
    liste.className = 'header-organisateur__liens';
    row.insertBefore(liste, contact);
  } else {
    liste.innerHTML = '';
  }

  liens.forEach(({ type_de_lien, url_lien }) => {
    const meta = LIENS_PUBLICS_META[type_de_lien] || {};
    const li = document.createElement('li');
    li.className = 'item-lien-public';

    const lien = document.createElement('a');
    lien.href = url_lien;
    lien.target = '_blank';
    lien.rel = 'noopener';
    lien.className = `lien-public lien-${type_de_lien}`;
    lien.setAttribute('aria-label', meta.label || type_de_lien);

    const icon = document.createElement('i');
    icon.className = `fa ${meta.icone || 'fa-link'}`;
    icon.setAttribute('aria-hidden', 'true');

    lien.appendChild(icon);
    li.appendChild(lien);
    liste.appendChild(li);
  });
}


/**
 * 🔁 met à jour dynamiquement le titre dans le header pour un CPT donné
 * @param {string} cpt - Le type de post (ex: 'organisateur', 'chasse', 'enigme')
 * @param {string} valeur - Le nouveau titre à afficher
 */
window.mettreAJourTitreHeader = function (cpt, valeur) {
  const selecteurs = {
    organisateur: ['.header-organisateur__nom', '.titre-objet[data-cpt="organisateur"]'],
    chasse: ['.titre-objet[data-cpt="chasse"]'],
    enigme: ['.titre-objet[data-cpt="enigme"]']
  };

  const cibles = selecteurs[cpt]
    ? selecteurs[cpt].flatMap((sel) => Array.from(document.querySelectorAll(sel)))
    : [];

  if (cibles.length > 0) {
    cibles.forEach((el) => {
      el.textContent = valeur;
    });
  } else {
    console.warn('❌ Impossible de trouver le header pour le CPT :', cpt);
  }
};

/**
 * 🔁 Met à jour dynamiquement la légende (sous-titre) d’une énigme dans le header.
 * @param {string} valeur - La nouvelle légende à afficher
 */
window.mettreAJourLegendeEnigme = function (valeur) {
  const texte = valeur?.trim() || '';
  let legende =
    document.querySelector('.enigme-soustitre') ||
    document.querySelector('.enigme-legende');

  if (texte) {
    if (!legende) {
      const header = document.querySelector('.enigme-header');
      if (!header) return;
      legende = document.createElement('p');
      legende.className = 'enigme-soustitre';
      header.appendChild(legende);
    }
    legende.textContent = texte;
    legende.classList.add('modifiee');
  } else if (legende) {
    legende.remove();
  }
};

/**
 * 🔁 Met à jour dynamiquement le titre de l’énigme dans le menu latéral.
 * @param {string} valeur - Le nouveau titre à afficher
 */
window.mettreAJourTitreMenuEnigme = function (valeur) {
  const item = document.querySelector('.enigme-menu li.active a');
  if (item) {
    item.textContent = valeur;
  }
};



/**
 * 🖼️ Met à jour dynamiquement l’image visible pour un CPT donné
 * après modification via un panneau d’édition.
 *
 * @param {string} cpt - Le nom du CPT (ex. "organisateur", "chasse", "enigme")
 * @param {number|string} postId - L’ID du post
 * @param {string} nouvelleUrl - L’URL de l’image mise à jour pour l’affichage
 * @param {string} [fullUrl=nouvelleUrl] - L’URL de l’image originale pour la lightbox
 */
function mettreAJourVisuelCPT(cpt, postId, nouvelleUrl, fullUrl = nouvelleUrl) {
  document
    .querySelectorAll(`img.visuel-cpt[data-cpt="${cpt}"][data-post-id="${postId}"]`)
    .forEach(img => {
      img.src = nouvelleUrl;
      img.srcset = nouvelleUrl;

      const lien = img.closest('a');
      if (lien) {
        lien.href = fullUrl;
      }
    });
}

/**
 * Initialise la logique d'édition des liens publics pour un bloc donné.
 * Regroupe l'ouverture/fermeture du panneau, la collecte des données et
 * l'envoi AJAX.
 *
 * @param {HTMLElement} bloc - Le bloc contenant les métadonnées (data-champ, data-post-id)
 * @param {Object} params - Identifiants et action AJAX
 * @param {string} params.panneauId - ID du panneau latéral contenant le formulaire
 * @param {string} params.formId - ID du formulaire de liens
 * @param {string} params.action - Action AJAX à appeler
 */
function openLocalPanel(panneau, panneauId) {
  if (typeof window.openPanel === 'function') {
    window.openPanel(panneauId);
  } else {
    document
      .querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert')
      .forEach((p) => {
        p.classList.remove('ouvert');
        p.setAttribute('aria-hidden', 'true');
      });
    panneau.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'false');
  }
}

function closeLocalPanel(panneau, panneauId) {
  if (typeof window.closePanel === 'function') {
    window.closePanel(panneauId);
  } else {
    panneau.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'true');
  }
}

function setupPanelHandlers(bouton, panneau, panneauId) {
  bouton.addEventListener('click', (e) => {
    e.preventDefault();
    openLocalPanel(panneau, panneauId);
  });

  panneau.querySelector('.panneau-fermer')?.addEventListener('click', () => {
    closeLocalPanel(panneau, panneauId);
  });
}

function serializeLiensForm(formulaire) {
  const donnees = [];
  formulaire.querySelectorAll('.champ-url-lien').forEach((input) => {
    input.classList.remove('champ-erreur');
    const ligne = input.closest('[data-type]');
    const type = ligne?.dataset.type;
    const url = input.value.trim();

    if (type && url !== '') {
      try {
        new URL(url);
        donnees.push({ type_de_lien: type, url_lien: normaliserUrlLien(url) });
      } catch (_) {
        input.classList.add('champ-erreur');
      }
    }
  });

  return donnees;
}

function updateTargetBlocks(bloc, champ, postId, donnees) {
  const champDonnees = bloc.querySelector('.champ-donnees');
  if (champDonnees) {
    champDonnees.dataset.valeurs = JSON.stringify(donnees);
  }

  let zoneAffichage = bloc.querySelector('.champ-affichage');
  if (!zoneAffichage) {
    const fiche = document.querySelector(
      `.champ-chasse.champ-fiche-publication[data-champ="${champ}"][data-post-id="${postId}"]`
    );
    zoneAffichage = fiche?.querySelector('.champ-affichage');
  }

  if (zoneAffichage && typeof renderLiensPublicsJS === 'function') {
    zoneAffichage.replaceChildren(renderLiensPublicsJS(donnees));
  }

  bloc.classList.toggle('champ-vide', donnees.length === 0);
  bloc.classList.toggle('champ-rempli', donnees.length > 0);

  document
    .querySelectorAll(`.champ-chasse[data-champ="${champ}"][data-post-id="${postId}"]`)
    .forEach((blocCible) => {
      if (blocCible === bloc) return;

      const zone = blocCible.querySelector('.champ-affichage');
      if (zone && typeof renderLiensPublicsJS === 'function') {
        zone.replaceChildren(renderLiensPublicsJS(donnees));
      }

      const donneesCible = blocCible.querySelector('.champ-donnees');
      if (donneesCible) {
        donneesCible.dataset.valeurs = JSON.stringify(donnees);
      }

      blocCible.classList.toggle('champ-vide', donnees.length === 0);
      blocCible.classList.toggle('champ-rempli', donnees.length > 0);
    });

  document
    .querySelectorAll(`.champ-organisateur[data-champ="${champ}"][data-post-id="${postId}"]`)
    .forEach((blocCible) => {
      const zone = blocCible.querySelector('.champ-affichage');

      if (zone && typeof renderLiensPublicsJS === 'function') {
        zone.replaceChildren(renderLiensPublicsJS(donnees));
      }

      const donneesCible = blocCible.querySelector('.champ-donnees');
      if (donneesCible) {
        donneesCible.dataset.valeurs = JSON.stringify(donnees);
      }

      blocCible.classList.toggle('champ-vide', donnees.length === 0);
      blocCible.classList.toggle('champ-rempli', donnees.length > 0);
    });

  if (champ === 'liens_publics') {
    mettreAJourHeaderOrganisateurLiens(donnees);
  }

  window.dispatchEvent(new Event('liens-publics-updated'));
}

function initLiensPublics(bloc, { panneauId, formId, action, reload = false }) {
  const champ = bloc.dataset.champ;
  const postId = bloc.dataset.postId;
  const bouton = bloc.querySelector('.champ-modifier');
  const panneau = document.getElementById(panneauId);
  let formulaire = document.getElementById(formId);
  const feedback = bloc.querySelector('.champ-feedback');
  const champDonnees = bloc.querySelector('.champ-donnees');

  if (!champ || !postId || !bouton || !panneau || !formulaire) return;

  setupPanelHandlers(bouton, panneau, panneauId);

  // ❌ Supprime les éventuels anciens écouteurs
  const clone = formulaire.cloneNode(true);
  formulaire.replaceWith(clone);
  formulaire = clone;

  let valeursInitiales = [];
  if (champDonnees?.dataset.valeurs) {
    try {
      valeursInitiales = JSON.parse(champDonnees.dataset.valeurs);
    } catch (_) {
      valeursInitiales = [];
    }
  }
  let signatureInitiale = JSON.stringify(normaliserLiens(valeursInitiales, { trier: true }));

  formulaire.addEventListener('submit', async (e) => {
    e.preventDefault();
    e.stopPropagation();

    const saisies = serializeLiensForm(formulaire);
    const donneesNormalisees = normaliserLiens(saisies, { trier: true });
    const signatureSoumise = JSON.stringify(donneesNormalisees);

    let signatureCourante = signatureInitiale;
    if (champDonnees?.dataset.valeurs) {
      try {
        const valeursCourantes = JSON.parse(champDonnees.dataset.valeurs);
        signatureCourante = JSON.stringify(normaliserLiens(valeursCourantes, { trier: true }));
      } catch (err) {
        signatureCourante = '[]';
      }
    }

    if (feedback) {
      feedback.textContent = '';
      feedback.className = 'champ-feedback';
    }

    if (signatureSoumise === signatureCourante) {
      signatureInitiale = signatureCourante;
      if (feedback) {
        feedback.textContent = '';
        feedback.className = 'champ-feedback';
      }
      closeLocalPanel(panneau, panneauId);
      return;
    }

    try {
      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action,
          champ,
          post_id: postId,
          valeur: JSON.stringify(donneesNormalisees)
        })
      });

      const res = await response.json();
      if (!res.success) throw new Error(res.data || 'Erreur AJAX');

      updateTargetBlocks(bloc, champ, postId, donneesNormalisees);
      signatureInitiale = signatureSoumise;
      closeLocalPanel(panneau, panneauId);

      if (typeof window.mettreAJourResumeInfos === 'function') {
        window.mettreAJourResumeInfos();
      }

      if (reload) {
        location.reload();
      }
    } catch (err) {
      console.error('❌ AJAX fail', err.message || err);
      if (feedback) {
        feedback.textContent = 'Erreur : ' + (err.message || 'Serveur ou réseau.');
        feedback.className = 'champ-feedback champ-error';
      }
    }
  });
}

window.initLiensPublics = initLiensPublics;
