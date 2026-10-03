function initFormulaireAutomatique() {
  const form = document.querySelector('.formulaire-reponse-auto');
  if (!form || form.dataset.responseHandlerReady === '1') return;
  form.dataset.responseHandlerReady = '1';
  const feedback = form.querySelector('.reponse-feedback');
  const soldeFooter = document.querySelector('.participation-infos .solde');
  const soldeInfo = form.querySelector('.points-sousligne');
  const headerPoints = document.querySelector('.zone-points .points-value');
  const cout = parseInt(form.dataset.cout || '0', 10);
  let soldeAvant = parseInt(form.dataset.soldeAvant || '0', 10);
  let soldeApres = parseInt(form.dataset.soldeApres || '0', 10);
  const seuil = parseInt(form.dataset.seuil || '300', 10);
  const __ = window.wp?.i18n?.__ || (s => s);
  const sprintf = window.wp?.i18n?.sprintf;
  let hideTimer = null;

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (cout >= seuil) {
      const ok = confirm(
        __("Confirmer l'envoi ? Cette tentative coûtera %1$d pts. Solde après : %2$d pts.", 'chassesautresor-com')
          .replace('%1$d', cout)
          .replace('%2$d', soldeApres)
      );
      if (!ok) return;
    }
    const data = new URLSearchParams(new FormData(form));
    data.append('action', 'soumettre_reponse_automatique');

    fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data
    })
      .then(async r => {
        const text = await r.text();
        try {
          return JSON.parse(text);
        } catch (e) {
          if (feedback) {
            feedback.textContent = __('Erreur serveur', 'chassesautresor-com');
            feedback.style.display = 'block';
            hideTimer = setTimeout(() => { feedback.style.display = 'none'; }, 5000);
          }
          throw e;
        }
      })
      .then(res => {
        if (!feedback) return;
        if (!res.success && res.data?.blocked) {
          window.RiddleRetryCountdown?.apply(form, res.data);
        }
        if (res.success) {
          window.RiddleRetryCountdown?.apply(form, res.data?.retry);
        }
        if (hideTimer) {
          clearTimeout(hideTimer);
          hideTimer = null;
        }
        feedback.style.display = 'none';

        if (res.success) {
          form.reset();
          if (headerPoints && typeof res.data.points !== 'undefined') {
            headerPoints.textContent = res.data.points;
          }
          if (soldeFooter && typeof res.data.points !== 'undefined') {
            soldeFooter.textContent = `${__('Solde', 'chassesautresor-com')} : ${res.data.points} ${__('pts', 'chassesautresor-com')}`;
          }
          if (soldeInfo && typeof res.data.points !== 'undefined') {
            soldeAvant = parseInt(res.data.points, 10);
            soldeApres = soldeAvant - cout;
            form.dataset.soldeAvant = soldeAvant;
            form.dataset.soldeApres = soldeApres;
            const baseSolde = __('Solde : %1$d → %2$d pts', 'chassesautresor-com');
            soldeInfo.textContent = sprintf
              ? sprintf(baseSolde, soldeAvant, soldeApres)
              : `${__('Solde', 'chassesautresor-com')} : ${soldeAvant} → ${soldeApres} ${__('pts', 'chassesautresor-com')}`;
          }

          if (res.data.resultat === 'variante') {
            if (res.data.message) {
              feedback.textContent = res.data.message;
              feedback.style.display = 'block';
            }
          } else if (res.data.resultat === 'bon') {
            document.dispatchEvent(new CustomEvent('cta:riddle-resolved'));
            feedback.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--color-success);"></i> ${__('Bonne réponse', 'chassesautresor-com')}`;
            feedback.style.display = 'block';
            const enigmeId = form.querySelector('input[name="enigme_id"]')?.value;
            const titre = form.querySelector('h3');
            form.replaceChildren(titre, feedback);
            const currentMenuItem = document.querySelector('.enigme-menu li.active');
            if (currentMenuItem) {
              currentMenuItem.classList.remove('non-engagee', 'bloquee', 'en-attente');
              currentMenuItem.classList.add('succes');
            }
            const sectionGagnants = document.querySelector('.enigme-gagnants');
            const sectionStats = document.querySelector('.enigme-statistiques');

            const navigation = document.querySelector('.enigme-navigation');
            const chasseId = navigation ? navigation.dataset.chasseId : null;
            const bloc = document.querySelector('.menu-lateral__accordeons .accordeon-bloc');
            const toggle = bloc ? bloc.querySelector('.accordeon-toggle') : null;
            const contenu = bloc ? bloc.querySelector('.accordeon-contenu') : null;
            const requests = [];

            if (sectionGagnants && enigmeId) {
              const dataW = new URLSearchParams();
              dataW.append('action', 'enigme_recuperer_gagnants');
              dataW.append('enigme_id', enigmeId);
              dataW.append('nonce', RiddleSidebarAjax.nonce);
              const req = fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: dataW
              })
                .then(r => r.json())
                .then(r => {
                  if (r.success) {
                    sectionGagnants.innerHTML = r.data.html;
                  }
                });
              requests.push(req);
            }

            if (sectionStats && chasseId && enigmeId) {
              const dataP = new URLSearchParams();
              dataP.append('action', 'enigme_recuperer_progression');
              dataP.append('chasse_id', chasseId);
              dataP.append('enigme_id', enigmeId);
              dataP.append('nonce', RiddleSidebarAjax.nonce);
              const req = fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: dataP
              })
                .then(r => r.json())
                .then(r => {
                  if (r.success) {
                    sectionStats.innerHTML = r.data.html;
                  }
                });
              requests.push(req);
            }

            Promise.allSettled(requests).then(() => {
              if (window.enigmeAside?.show) {
                window.enigmeAside.show();
              }
              if (toggle && contenu) {
                toggle.setAttribute('aria-expanded', 'true');
                contenu.classList.remove('accordeon-ferme');
              }
            });
          } else {
            feedback.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color:var(--color-gris-3);"></i> ${__('Mauvaise réponse', 'chassesautresor-com')}`;
            feedback.style.display = 'block';
            hideTimer = setTimeout(() => { feedback.style.display = 'none'; }, 5000);
          }

        } else {
          feedback.textContent = res.data;
          feedback.style.display = 'block';
          hideTimer = setTimeout(() => { feedback.style.display = 'none'; }, 5000);

        }
      });
  });
}

document.addEventListener('DOMContentLoaded', initFormulaireAutomatique);
document.addEventListener('riddle-step-content-updated', initFormulaireAutomatique);
