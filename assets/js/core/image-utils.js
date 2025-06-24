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

  if (!champ || !cpt || !postId || !input || !image) return;

  // ✅ Création du frame à la volée quand appelé
  const ouvrirMedia = () => {
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
  };

  // ✅ On expose la fonction pour la déclencher manuellement
  bloc.dataset.imageInitReady = '1';
  bloc.dataset.imageInitTrigger = ouvrirMedia;
  bloc.__ouvrirMedia = ouvrirMedia;
}
