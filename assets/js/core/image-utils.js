// ==============================
// initChampImage (édition uniquement via panneau)
// ==============================
function initChampImage(bloc) {
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt;
  const postId = bloc.dataset.postId;

  const feedback = bloc.querySelector('.champ-feedback');
  const bouton = bloc.querySelector('.champ-modifier');

  if (!champ || !cpt || !postId) return;

  const estBlocVerrouille = () =>
    bloc.classList.contains('champ-desactive') ||
    bloc.dataset.noEdit === '1' ||
    bloc.dataset.noEdit === 'true' ||
    bouton?.disabled ||
    bouton?.getAttribute('aria-disabled') === 'true';

  if (!bouton || estBlocVerrouille()) {
    delete bloc.__ouvrirMedia;
    return;
  }

  // ✅ Création du frame à la volée quand appelé
  const ouvrirMedia = () => {
    if (estBlocVerrouille()) {
      return;
    }
    // ✅ Empêcher double ouverture : reuse si déjà initialisé
    if (bloc.__mediaFrame) {
      bloc.__mediaFrame.open();
      return;
    }

    wp.media.view.settings.post.id = postId;

    const frame = wp.media({
      title: 'Choisir une image',
      multiple: false,
      library: { type: 'image' },
      button: { text: 'Utiliser cette image' }
    });

    bloc.__mediaFrame = frame; // 💾 stocké pour usage unique

    frame.on('select', () => {
      const selection = frame.state().get('selection').first();
      const id = selection?.id;
      const fullUrl = selection?.attributes?.url;
      const ficheUrl =
        selection?.attributes?.sizes?.['chasse-fiche']?.url || fullUrl;
      const mediumUrl = selection?.attributes?.sizes?.medium?.url || ficheUrl;
      const thumbUrl = selection?.attributes?.sizes?.thumbnail?.url || mediumUrl;
      if (!id || !fullUrl) return;

      document
        .querySelectorAll(`.champ-${cpt}[data-champ="${champ}"][data-post-id="${postId}"]`)
        .forEach((el) => {
          el.classList.remove('champ-vide');
          el.classList.add('champ-rempli');
          const imgEl = el.querySelector('img');
          if (imgEl) {
            imgEl.src = thumbUrl;
            imgEl.srcset = thumbUrl;
          }
          const hidden = el.querySelector('.champ-input');
          if (hidden) {
            hidden.value = id;
          }
        });

      if (typeof window.mettreAJourResumeInfos === 'function') {
        window.mettreAJourResumeInfos();
      }

      if (feedback) {
        feedback.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
        feedback.className = 'champ-feedback champ-loading';
      }

      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: (cpt === 'chasse') ? 'modifier_champ_chasse' :
            (cpt === 'enigme') ? 'modifier_champ_enigme' :
              (cpt === 'indice') ? 'modifier_champ_indice' :
                'modifier_champ_organisateur',
          champ,
          valeur: id,
          post_id: postId
        })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            if (feedback) {
              feedback.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
              feedback.className = 'champ-feedback champ-success';
              setTimeout(() => { feedback.innerHTML = ''; feedback.className = 'champ-feedback'; }, 1000);
            }
            if (typeof window.mettreAJourResumeInfos === 'function') {
              window.mettreAJourResumeInfos();
            }
            if (typeof window.mettreAJourVisuelCPT === 'function') {
              mettreAJourVisuelCPT(cpt, postId, ficheUrl, fullUrl);
            }
          } else {
            if (feedback) {
              feedback.innerHTML = '';
              feedback.textContent = '❌ Erreur : ' + (res.data || 'inconnue');
              feedback.className = 'champ-feedback champ-error';
            }
          }
        })
        .catch(() => {
          if (feedback) {
            feedback.innerHTML = '';
            feedback.textContent = '❌ Erreur réseau.';
            feedback.className = 'champ-feedback champ-error';
          }
        });
    });

    frame.open();
  };

  if (bouton && !bouton.classList.contains('ouvrir-panneau-images')) {
    bouton.addEventListener('click', ouvrirMedia);
  }

  // ✅ On expose la fonction pour la déclencher manuellement
  bloc.__ouvrirMedia = ouvrirMedia;
}
