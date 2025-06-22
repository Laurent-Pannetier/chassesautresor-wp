console.log('✅ organisateur-edit.js chargé');

document.addEventListener('DOMContentLoaded', () => {

  // 🟢 Champs inline
  document.querySelectorAll('.champ-organisateur[data-champ]').forEach((bloc) => {
    const champ = bloc.dataset.champ;
    if (bloc.classList.contains('champ-img')) {
      if (typeof initChampImage === 'function') initChampImage(bloc);
    } else if (champ === 'liens_publics') {
      if (typeof initLiensOrganisateur === 'function') initLiensOrganisateur(bloc);
    } else {
      if (typeof initChampTexte === 'function') initChampTexte(bloc);
    }
  });

  // 🟠 Déclencheurs de résumé
  document.querySelectorAll('.resume-infos .champ-modifier[data-champ]').forEach((btn) => {
    if (typeof initChampDeclencheur === 'function') initChampDeclencheur(btn);
  });

  // 🔗 Panneau liens
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-liens');

    // ✅ Ce bouton existe ET il est contenu dans le panneau organisateur
    if (!btn || !btn.closest('.panneau-organisateur')) return;

    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-liens-publics');
    }
  });


  // ⚙️ Bouton toggle header
  document.getElementById('toggle-mode-edition')?.addEventListener('click', () => {
    document.body.classList.toggle('edition-active');
  });

  // ✖ Fermeture du panneau organisateur
  document.querySelector('.panneau-organisateur .panneau-fermer')?.addEventListener('click', () => {
    document.body.classList.remove('edition-active');
    document.activeElement?.blur();
  });


  // 🏦 Coordonnées bancaires
  const panneauCoord = document.getElementById('panneau-coordonnees');
  const formCoord = document.getElementById('formulaire-coordonnees');
  const boutonOuvrirCoord = document.getElementById('ouvrir-coordonnees');
  const boutonFermerCoord = panneauCoord?.querySelector('.panneau-fermer');
  const champIban = document.getElementById('champ-iban');
  const champBic = document.getElementById('champ-bic');
  const feedbackIban = document.getElementById('feedback-iban');
  const feedbackBic = document.getElementById('feedback-bic');

  const validerIban = (iban) => /^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(iban.replace(/\s/g, '').toUpperCase());
  const validerBic = (bic) => /^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/.test(bic.toUpperCase());

  boutonOuvrirCoord?.addEventListener('click', () => {
    panneauCoord?.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneauCoord?.setAttribute('aria-hidden', 'false');
  });

  boutonFermerCoord?.addEventListener('click', () => {
    panneauCoord?.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneauCoord?.setAttribute('aria-hidden', 'true');
  });

  formCoord?.addEventListener('submit', (e) => {
    e.preventDefault();

    const iban = champIban.value.trim();
    const bic = champBic.value.trim();
    const postId = formCoord.dataset.postId;

    feedbackIban.textContent = '';
    feedbackBic.textContent = '';
    feedbackIban.className = 'champ-feedback';
    feedbackBic.className = 'champ-feedback';
    champIban.classList.remove('iban-invalide');
    champBic.classList.remove('bic-invalide');

    let erreur = false;
    if (iban && !validerIban(iban)) {
      feedbackIban.textContent = '❌ Format IBAN invalide.';
      feedbackIban.classList.add('champ-error');
      champIban.classList.add('iban-invalide');
      erreur = true;
    }
    if (bic && !validerBic(bic)) {
      feedbackBic.textContent = '❌ Format BIC invalide.';
      feedbackBic.classList.add('champ-error');
      champBic.classList.add('bic-invalide');
      erreur = true;
    }
    if (erreur) return;

    fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'modifier_champ_organisateur',
        champ: 'coordonnees_bancaires',
        post_id: postId,
        valeur: JSON.stringify({ iban, bic })
      })
    })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          feedbackIban.textContent = '✔️ Coordonnées enregistrées.';
          feedbackIban.classList.add('champ-confirmation');
          setTimeout(() => {
            panneauCoord?.classList.remove('ouvert');
            document.body.classList.remove('panneau-ouvert');
            panneauCoord?.setAttribute('aria-hidden', 'true');
            feedbackIban.textContent = '';
            feedbackIban.className = 'champ-feedback';
            if (typeof window.mettreAJourResumeInfos === 'function') window.mettreAJourResumeInfos();
          }, 800);
        } else {
          feedbackIban.textContent = '❌ La sauvegarde a échoué.';
          feedbackIban.classList.add('champ-error');
        }
      })
      .catch(() => {
        feedbackIban.textContent = '❌ Erreur réseau.';
        feedbackIban.classList.add('champ-error');
      });
  });

  if (typeof window.mettreAJourResumeInfos === 'function') {
    window.mettreAJourResumeInfos();
  }

});


function initLiensOrganisateur(bloc) {
  const champ = bloc.dataset.champ;
  const postId = bloc.dataset.postId;
  const zoneAffichage = bloc.querySelector('.champ-affichage');
  const bouton = bloc.querySelector('.champ-modifier');
  const panneau = document.getElementById('panneau-liens-publics');
  const formulaire = document.getElementById('formulaire-liens-publics');
  const feedback = bloc.querySelector('.champ-feedback');

  if (!champ || !postId || !zoneAffichage || !formulaire || !bouton || !panneau) return;

  bouton.addEventListener('click', () => {
    if (typeof window.openPanel === 'function') {
      window.openPanel('panneau-liens-publics');
    }
  });

  panneau.querySelector('.panneau-fermer')?.addEventListener('click', () => {
    if (typeof window.closePanel === 'function') {
      window.closePanel('panneau-liens-publics');
    }
  });

  formulaire.addEventListener('submit', (e) => {
    e.preventDefault();
    const lignes = formulaire.querySelectorAll('.ligne-lien-formulaire');
    const donnees = [];

    lignes.forEach((ligne) => {
      const type = ligne.dataset.type;
      const input = ligne.querySelector('input[type="url"]');
      const url = input?.value.trim();
      if (type && url) {
        try {
          new URL(url);
          donnees.push({ type_de_lien: type, url_lien: url });
        } catch (_) {
          input.classList.add('champ-erreur');
        }
      }
    });

    fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'modifier_champ_organisateur',
        champ,
        post_id: postId,
        valeur: JSON.stringify(donnees)
      })
    })
      .then(res => res.json())
      .then((res) => {
        if (res.success) {
          const icones = {
            site_web: 'fa-solid fa-globe',
            discord: 'fa-brands fa-discord',
            facebook: 'fa-brands fa-facebook-f',
            twitter: 'fa-brands fa-x-twitter',
            instagram: 'fa-brands fa-instagram'
          };

          const labels = {
            site_web: 'Site Web',
            discord: 'Discord',
            facebook: 'Facebook',
            twitter: 'Twitter/X',
            instagram: 'Instagram'
          };

          let html = '';
          if (donnees.length > 0) {
            html = '<ul class="liste-liens-publics">' + donnees.map(item => {
              const type = item.type_de_lien;
              const url = item.url_lien;
              const icone = icones[type] || 'fa-link';
              const label = labels[type] || type;
              return `
                <li class="item-lien-public">
                  <a href="${url}" class="lien-public lien-${type}" target="_blank" rel="noopener">
                    <i class="fa ${icone}"></i>
                    <span class="texte-lien">${label}</span>
                  </a>
                </li>`;
            }).join('') + '</ul>';
          } else {
            html = `
              <div class="liens-placeholder">
                <p class="liens-placeholder-message">Aucun lien ajouté pour le moment.</p>
                <i class="fa fa-solid fa-globe icone-grisee" title="Site Web"></i>
                <i class="fa fa-brands fa-discord icone-grisee" title="Discord"></i>
                <i class="fa fa-brands fa-facebook-f icone-grisee" title="Facebook"></i>
                <i class="fa fa-brands fa-x-twitter icone-grisee" title="Twitter/X"></i>
                <i class="fa fa-brands fa-instagram icone-grisee" title="Instagram"></i>
              </div>`;

          }

          zoneAffichage.innerHTML = html;
          // 🔁 Ajouter le bouton ✏️ s’il a été perdu
          if (!bloc.querySelector('.champ-modifier')) {
            const bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.className = 'champ-modifier ouvrir-panneau-liens';
            bouton.setAttribute('aria-label', 'Configurer vos liens');
            bouton.textContent = '✏️';
            bloc.querySelector('.champ-affichage')?.appendChild(bouton);
          }

          bloc.classList.toggle('champ-vide', donnees.length === 0);
          panneau.classList.remove('ouvert');
          document.body.classList.remove('panneau-ouvert');
          panneau.setAttribute('aria-hidden', 'true');

          if (typeof window.mettreAJourResumeInfos === 'function') {
            window.mettreAJourResumeInfos();
          }
        } else {
          feedback.textContent = 'Erreur : ' + (res.data || 'mise à jour échouée');
          feedback.className = 'champ-feedback champ-error';
        }
      })
      .catch(() => {
        feedback.textContent = 'Erreur réseau.';
        feedback.className = 'champ-feedback champ-error';
      });
  });
}

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
    '[data-champ="profil_public_logo_organisateur"]'
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

  console.log('🧩 Vérif carte-ajout → champs vides détectés :', incomplets);
  console.log('🧩 carte actuelle :', carte);

  let overlay = carte.querySelector('.overlay-message');

  if (incomplets.length === 0) {
    carte.classList.remove('disabled');
    overlay?.remove();
  } else {
    carte.classList.add('disabled');

    const texte = incomplets.map(sel => {
      if (sel.includes('post_title')) return 'titre';
      if (sel.includes('logo')) return 'logo';
      if (sel.includes('description')) return 'description';
      return 'champ requis';
    }).join(', ');

    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'overlay-message';
      carte.appendChild(overlay);
    }

    overlay.innerHTML = `
      <i class="fa-solid fa-circle-info"></i>
      <p>Complétez d’abord : ${texte}</p>
    `;
  }
};
