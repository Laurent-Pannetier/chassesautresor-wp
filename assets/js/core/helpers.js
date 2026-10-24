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
 * Met à jour dynamiquement l’image visible pour un CPT donné
 * après modification via un panneau d’édition.
 *
 * @param {string} cpt - Le nom du CPT (ex. "organisateur", "chasse", "enigme")
 * @param {number|string} postId - L’ID du post
 * @param {string} nouvelleUrl - L’URL de l’image mise à jour pour l’affichage
 */
function mettreAJourVisuelCPT(cpt, postId, nouvelleUrl) {
  document
    .querySelectorAll(`img.visuel-cpt[data-cpt="${cpt}"][data-post-id="${postId}"]`)
    .forEach(img => {
      img.src = nouvelleUrl;
      img.srcset = nouvelleUrl;
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
