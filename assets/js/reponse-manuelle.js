function initFormulaireManuel() {
  const form = document.querySelector('.formulaire-reponse-manuelle');
  if (!form) return;
  const feedback = form.nextElementSibling;
  const input = form.querySelector('textarea[name="reponse_manuelle"]');
  const pointsMsg = form.querySelector('.message-limite');
  const headerPoints = document.querySelector('.zone-points .points-value');
  const cout = parseInt(form.dataset.cout || '0', 10);
  const soldeApres = parseInt(form.dataset.soldeApres || '0', 10);
  const seuil = parseInt(form.dataset.seuil || '300', 10);
  let hideTimer = null;

  const i18n = window.REPONSE_MANUELLE_I18N || {};
  const sprintf = window.wp?.i18n?.sprintf;
  const txtSuccess = i18n.success || 'Tentative bien reçue.';
  const txtProcessing = i18n.processing;
  const accountUrl = i18n.accountUrl || '/mon-compte/';

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (cout >= seuil) {
      const ok = confirm(`Confirmer l'envoi ? Cette tentative coûtera ${cout} pts. Solde après : ${soldeApres} pts.`);
      if (!ok) return;
    }
    const data = new URLSearchParams(new FormData(form));
    data.append('action', 'soumettre_reponse_manuelle');

    fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data
    })
      .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); } catch (e) {
          if (feedback) {
            feedback.textContent = 'Erreur serveur';
            feedback.style.display = 'block';
            hideTimer = setTimeout(() => { feedback.style.display = 'none'; }, 5000);
          }
          throw e;
        }
      })
      .then(res => {
        if (!feedback) return;
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        feedback.style.display = 'none';

        if (res.success) {
          if (headerPoints && typeof res.data.points !== 'undefined') {
            headerPoints.textContent = res.data.points;
          }

          const msgProcessing = document.createElement('p');
          msgProcessing.className = 'message-joueur-statut';
          msgProcessing.innerHTML = sprintf
            ? sprintf(txtProcessing, `#${res.data.id}`, res.data.date, res.data.time, accountUrl)
            : txtProcessing
                .replace('%1$s', `#${res.data.id}`)
                .replace('%2$s', res.data.date)
                .replace('%3$s', res.data.time)
                .replace('%4$s', accountUrl);

          const msgSuccess = document.createElement('p');
          msgSuccess.className = 'message-feedback-success';
          msgSuccess.textContent = txtSuccess;

          if (feedback) feedback.remove();
          const parent = form.parentNode;
          parent.insertBefore(msgSuccess, form);
          parent.insertBefore(msgProcessing, form);
          form.remove();

          const currentMenuItem = document.querySelector('.enigme-menu li.active');
          if (currentMenuItem) {
            currentMenuItem.classList.remove('non-engagee', 'bloquee', 'succes');
            currentMenuItem.classList.add('en-attente');
          }

          setTimeout(() => { msgSuccess.remove(); }, 5000);
        } else {
          feedback.textContent = res.data;
          feedback.style.display = 'block';
          hideTimer = setTimeout(() => { feedback.style.display = 'none'; }, 5000);
        }
      });
  });
}

document.addEventListener('DOMContentLoaded', initFormulaireManuel);
