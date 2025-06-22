// ✅ chasse-edit.js
console.log('✅ chasse-edit.js chargé');

let inputDateDebut;
let inputDateFin;
let erreurDebut;
let erreurFin;
let checkboxIllimitee;
let ancienneValeurDebut = '';
let ancienneValeurFin = '';


document.addEventListener('DOMContentLoaded', () => {
  inputDateDebut = document.getElementById('chasse-date-debut');
  inputDateFin = document.getElementById('chasse-date-fin');
  erreurDebut = document.getElementById('erreur-date-debut');
  erreurFin = document.getElementById('erreur-date-fin');
  checkboxIllimitee = document.getElementById('duree-illimitee');


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
  });
  document.querySelector('.edition-panel-chasse .panneau-fermer')?.addEventListener('click', () => {
    document.body.classList.remove('edition-active-chasse');
    document.activeElement?.blur();
  });

  // ==============================
  // 📜 Panneau description (wysiwyg)
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-description');
    if (!btn || btn.dataset.cpt !== 'chasse') return;
    const panneau = document.getElementById('panneau-description-chasse');
    if (!panneau) return;

    document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
      p.classList.remove('ouvert');
      p.setAttribute('aria-hidden', 'true');
    });

    panneau.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'false');
  });
  document.querySelector('#panneau-description-chasse .panneau-fermer')?.addEventListener('click', () => {
    const panneau = document.getElementById('panneau-description-chasse');
    panneau.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'true');
  });

  // ==============================
  // 🏱 Panneau récompense
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-recompense');
    if (!btn || btn.dataset.cpt !== 'chasse') return;
    const panneau = document.getElementById('panneau-recompense-chasse');
    if (!panneau) return;

    document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
      p.classList.remove('ouvert');
      p.setAttribute('aria-hidden', 'true');
    });

    panneau.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'false');

  });
  document.querySelector('#panneau-recompense-chasse .panneau-fermer')?.addEventListener('click', () => {
    const panneau = document.getElementById('panneau-recompense-chasse');
    panneau.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'true');
  });

  // ==============================
  // 🎯 Badge dynamique récompense
  // ==============================
  if (typeof window.mettreAJourResumeInfos === 'function') {
    window.mettreAJourResumeInfos();
  }

  // ==============================
  // 📅 Gestion Date de fin + Durée illimitée
  // ==============================
  let ancienneValeurFin = '';
  if (inputDateFin) {
    let ancienneValeurFin = inputDateFin.value;

    if (checkboxIllimitee) {
      inputDateFin.disabled = checkboxIllimitee.checked;

      const postId = inputDateFin.closest('.champ-chasse')?.dataset.postId;

      checkboxIllimitee.addEventListener('change', function () {
        inputDateFin.disabled = this.checked;

        // Si la case est décochée et les dates incohérentes, corriger la date de fin
        if (!this.checked) {
          const debut = new Date(inputDateDebut.value);
          const fin = new Date(inputDateFin.value);

          if (!isNaN(debut) && !isNaN(fin) && debut >= fin) {
            const nouvelleDateFin = new Date(debut);
            nouvelleDateFin.setFullYear(nouvelleDateFin.getFullYear() + 2);

            const yyyy = nouvelleDateFin.getFullYear();
            const mm = String(nouvelleDateFin.getMonth() + 1).padStart(2, '0');
            const dd = String(nouvelleDateFin.getDate()).padStart(2, '0');

            const nouvelleValeur = `${yyyy}-${mm}-${dd}`;
            inputDateFin.value = nouvelleValeur;

            fetch(ajaxurl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'modifier_champ_chasse',
                champ: 'caracteristiques.chasse_infos_date_fin',
                valeur: nouvelleValeur,
                post_id: postId
              })
            })
              .then(r => r.json())
              .then(res => {
                if (!res.success) {
                  console.error('❌ Erreur lors de l’enregistrement de la date de fin auto-corrigée');
                }
              });
            rafraichirStatutChasse(postId);
          }
        }

        // Enregistrement de la case "illimité"
        fetch(ajaxurl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'modifier_champ_chasse',
            champ: 'caracteristiques.chasse_infos_duree_illimitee',
            valeur: this.checked ? 1 : 0,
            post_id: postId
          })
        })
          .then(r => r.json())
          .then(res => {
            if (res.success) {
              rafraichirStatutChasse(postId);
            } else {
              console.error('❌ Erreur serveur durée illimitée:', res.data);
            }
          })
          .catch(err => {
            console.error('❌ Erreur réseau durée illimitée:', err);
          });

        mettreAJourAffichageDateFin();
      });
    }

    inputDateFin.addEventListener('change', function () {
      const sauvegardeAvantChangement = this.value;

      const valid = validerDatesAvantEnvoi('fin');
      if (!valid) {
        this.value = ancienneValeurFin;
        return;
      }

      const nouvelleDateFin = this.value;
      const regexDate = /^\d{4}-\d{2}-\d{2}$/;

      if (!regexDate.test(nouvelleDateFin)) {
        console.error('❌ Format de date fin invalide:', nouvelleDateFin);
        this.value = ancienneValeurFin;
        return;
      }

      const postId = this.closest('.champ-chasse')?.dataset.postId;
      modifierChampSimple('caracteristiques.chasse_infos_date_fin', nouvelleDateFin, postId);
      rafraichirStatutChasse(postId);

      mettreAJourAffichageDateFin();

      ancienneValeurFin = nouvelleDateFin;
    });
  }
  if (inputDateDebut) {
    ancienneValeurDebut = inputDateDebut.value;

    inputDateDebut.addEventListener('change', function () {
      const nouvelleDateDebut = this.value;
      const regexDate = /^\d{4}-\d{2}-\d{2}$/;

      if (!regexDate.test(nouvelleDateDebut)) {
        console.error('❌ Format de date début invalide:', nouvelleDateDebut);
        this.value = ancienneValeurDebut;
        return;
      }

      const postId = this.closest('.champ-chasse')?.dataset.postId;
      modifierChampSimple('caracteristiques.chasse_infos_date_debut', nouvelleDateDebut, postId);
      rafraichirStatutChasse(postId);

      ancienneValeurDebut = nouvelleDateDebut;
    });
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

  if (boutonSupprimerRecompense) {
    boutonSupprimerRecompense.addEventListener('click', () => {
      const panneauEdition = document.querySelector('.edition-panel-chasse');
      if (!panneauEdition) return;
      const postId = panneauEdition.dataset.postId;
      if (!postId) return;

      if (!confirm('Voulez-vous vraiment supprimer la récompense ?')) return;

      const champsASupprimer = [
        'caracteristiques.chasse_infos_recompense_titre',
        'caracteristiques.chasse_infos_recompense_texte',
        'caracteristiques.chasse_infos_recompense_valeur'
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
        location.reload();
      });
    });

  }


  if (boutonRecompense && inputTitreRecompense && inputTexteRecompense && inputValeurRecompense) {
    boutonRecompense.addEventListener('click', () => {
      const titre = inputTitreRecompense.value.trim();
      const texte = inputTexteRecompense.value.trim();
      const valeur = parseFloat(inputValeurRecompense.value);
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

      if (isNaN(valeur) || valeur <= 0) {
        alert('Veuillez saisir une valeur en euros strictement supérieure à 0.');
        return;
      }

      // 🔵 Envoi titre de récompense d'abord
      fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'modifier_champ_chasse',
          champ: 'caracteristiques.chasse_infos_recompense_titre',
          valeur: titre,
          post_id: postId
        })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            console.log('✅ Titre récompense enregistré.');

            // 🔵 Ensuite, envoi texte récompense
            return fetch('/wp-admin/admin-ajax.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'modifier_champ_chasse',
                champ: 'caracteristiques.chasse_infos_recompense_texte',
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
            console.log('✅ Texte récompense enregistré.');

            // 🔵 Ensuite, envoi valeur récompense
            return fetch('/wp-admin/admin-ajax.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'modifier_champ_chasse',
                champ: 'caracteristiques.chasse_infos_recompense_valeur',
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
            if (typeof window.mettreAJourResumeInfos === 'function') {
              window.mettreAJourResumeInfos();
            }

            if (document.activeElement && panneauRecompense.contains(document.activeElement)) {
              document.activeElement.blur();
              document.body.focus(); // 🔥 Correction ultime ici
            }

            location.reload();

          } else {
            console.error('❌ Erreur valeur récompense', res.data);
          }
        })
        .catch(err => {
          console.error('❌ Erreur sur sauvegarde récompense', err);
        });
    });
  }
});


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
  // ✅ Si illimité, on n'applique aucun contrôle
  if (checkboxIllimitee?.checked) return true;

  if (erreurDebut) erreurDebut.style.display = 'none';
  if (erreurFin) erreurFin.style.display = 'none';

  if (!inputDateDebut || !inputDateFin) return true;

  const maintenant = new Date();
  const dateMinimum = new Date();
  dateMinimum.setFullYear(maintenant.getFullYear() - 10);
  const dateMaximum = new Date();
  dateMaximum.setFullYear(dateMaximum.getFullYear() + 5);

  const debut = new Date(inputDateDebut.value);
  const fin = new Date(inputDateFin.value);

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


// ==============================
// 🔥 Affichage d'un message global temporaire
// ==============================
function afficherErreurGlobale(message) {
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


// ================================
// 💰 Mise à jour dynamique de l'affichage du coût (Gratuit / Payant)
// ================================
function mettreAJourAffichageCout(postId, cout) {
  const coutAffichage = document.querySelector(`.chasse-prix[data-post-id="${postId}"] .cout-affichage`);
  if (!coutAffichage) return;

  coutAffichage.dataset.cout = cout; // Met à jour data-cout
  coutAffichage.innerHTML = ''; // Vide l'affichage

  const templateId = parseInt(cout, 10) === 0 ? 'icon-free' : 'icon-unlock';
  const template = document.getElementById(templateId);

  if (template) {
    coutAffichage.appendChild(template.content.cloneNode(true));
  }

  if (parseInt(cout, 10) === 0) {
    coutAffichage.insertAdjacentText('beforeend', ' Gratuit');
  } else {
    coutAffichage.insertAdjacentText('beforeend', ` ${cout}`);
    const devise = document.createElement('span');
    devise.className = 'prix-devise';
    devise.textContent = 'pts';
    coutAffichage.appendChild(devise);
  }
}


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

    modifierChampSimple(champ, valeur, postId);

    if (champ === 'caracteristiques.chasse_infos_cout_points') {
      mettreAJourAffichageCout(postId, valeur);
      rafraichirStatutChasse(postId);
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

    // Restaure l'ancienne valeur
    input.value = input.dataset.valeurInitiale || '0';

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
  const checkboxIllimite = document.getElementById('nb-gagnants-illimite');

  if (!inputNb || !checkboxIllimite) return;

  let timerDebounce;

  checkboxIllimite.addEventListener('change', function () {
    const postId = inputNb.closest('li').dataset.postId;
    if (!postId) return;

    if (checkboxIllimite.checked) {
      inputNb.disabled = true;
      inputNb.value = '0';
      modifierChampSimple('caracteristiques.chasse_infos_nb_max_gagants', 0, postId);
    } else {
      inputNb.disabled = false;
      if (parseInt(inputNb.value.trim(), 10) === 0 || inputNb.value.trim() === '') {
        inputNb.value = '1';
        modifierChampSimple('caracteristiques.chasse_infos_nb_max_gagants', 1, postId);
      }
    }
    // 🔥 Mise à jour dynamique après changement illimité
    mettreAJourAffichageNbGagnants(postId, inputNb.value.trim());
  });

  inputNb.addEventListener('input', function () {
    const postId = inputNb.closest('li').dataset.postId;
    if (!postId) return;

    clearTimeout(timerDebounce);
    timerDebounce = setTimeout(() => {
      let valeur = parseInt(inputNb.value.trim(), 10);
      if (isNaN(valeur) || valeur < 1) {
        valeur = 1;
        inputNb.value = '1';
      }
      modifierChampSimple('caracteristiques.chasse_infos_nb_max_gagants', valeur, postId);
      mettreAJourAffichageNbGagnants(postId, valeur); // ✅ ici, APRES avoir défini valeur
    }, 500);
  });
}

// À appeler :
initChampNbGagnants();


// ================================
// 👥 Mise à jour dynamique de l'affichage du nombre de gagnants
// ================================
function mettreAJourAffichageNbGagnants(postId, nb) {
  const nbGagnantsAffichage = document.querySelector(`.nb-gagnants-affichage[data-post-id="${postId}"]`);
  if (!nbGagnantsAffichage) return;

  if (parseInt(nb, 10) === 0) {
    nbGagnantsAffichage.textContent = 'Nombre illimité de gagnants';
  } else {
    nbGagnantsAffichage.textContent = `${nb} gagnant${nb > 1 ? 's' : ''}`;
  }
}



document.addEventListener('acf/submit_success', function (e) {
  console.log('✅ Formulaire ACF soumis avec succès', e);
  if (typeof window.mettreAJourResumeInfos === 'function') {
    window.mettreAJourResumeInfos();
  }
});


// ================================
// 🔁 Rafraîchissement dynamique du statut de la chasse
// ================================
function rafraichirStatutChasse(postId) {
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
            const badge = document.querySelector(`.badge-statut[data-post-id="${postId}"]`);
            console.log('🔎 Badge trouvé :', badge);

            if (badge) {
              badge.textContent = label;
              badge.className = `badge-statut statut-${statut}`;
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
