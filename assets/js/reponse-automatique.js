function timeUntilMidnight() {
  const now = new Date();
  const midnight = new Date();
  midnight.setHours(24, 0, 0, 0);
  const diff = midnight - now;
  const h = Math.floor(diff / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  return `${h}h et ${m}mn avant réactivation`;
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.formulaire-reponse-auto');
  if (!form) return;
  const feedback = form.querySelector('.reponse-feedback');
  const compteur = document.querySelector('.tentatives-counter');
  const compteurValeur = compteur ? compteur.querySelector('.valeur') : null;
  const soldeFooter = document.querySelector('.participation-infos .solde');
  const tentativesFooter = document.querySelector('.participation-infos .tentatives');
  const soldeInfo = form.querySelector('.points-sousligne');
  const input = form.querySelector('input[name="reponse"]');
  const limiteMsg = document.querySelector('.message-limite');
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
          if (tentativesFooter && typeof res.data.compteur !== 'undefined') {
            let maxFooter = tentativesFooter.dataset.max;
            if (!maxFooter) {
              const split = tentativesFooter.textContent.split('/');
              maxFooter = split[1] ? split[1].trim() : '∞';
              tentativesFooter.dataset.max = maxFooter;
            }
            const baseText = __('Tentatives quotidiennes : %1$s/%2$s', 'chassesautresor-com');
            tentativesFooter.textContent = sprintf
              ? sprintf(baseText, res.data.compteur, maxFooter)
              : `${__('Tentatives quotidiennes :', 'chassesautresor-com')} ${res.data.compteur}/${maxFooter}`;
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
            feedback.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--color-success);"></i> ${__('Bonne réponse', 'chassesautresor-com')}`;
            feedback.style.display = 'block';
            const enigmeId = form.querySelector('input[name="enigme_id"]')?.value;
            const titre = form.querySelector('h3');
            form.replaceChildren(titre, feedback);
            if (compteur) {
              compteur.remove();
            }
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

          if (compteur && typeof res.data.compteur !== 'undefined') {
            const max = parseInt(compteur.dataset.max || '0', 10);
            if (compteurValeur) {
              compteurValeur.textContent = `${res.data.compteur}/${max}`;
            } else {
              compteur.textContent = `${__('Tentatives quotidiennes', 'chassesautresor-com')} ${res.data.compteur}/${max}`;
            }
            if (max && res.data.compteur >= max) {
              if (input) input.remove();
              if (limiteMsg) {
                limiteMsg.style.display = 'block';
              } else {
                const p = document.createElement('p');
                p.className = 'message-limite';
                p.dataset.tentatives = 'epuisees';
                p.textContent = __('tentatives quotidiennes épuisées', 'chassesautresor-com');
                form.insertBefore(p, form.querySelector('input[name="enigme_id"]'));
              }
              const btn = form.querySelector('button[type="submit"]');
              if (btn) {
                btn.disabled = true;
                btn.textContent = timeUntilMidnight();
              }
            }
          }
        } else {
          feedback.textContent = res.data;
          feedback.style.display = 'block';
          hideTimer = setTimeout(() => { feedback.style.display = 'none'; }, 5000);
          if (res.data === 'tentatives_epuisees') {
            if (input) input.remove();
            if (limiteMsg) {
              limiteMsg.style.display = 'block';
            } else {
              const p = document.createElement('p');
              p.className = 'message-limite';
              p.dataset.tentatives = 'epuisees';
              p.textContent = __('tentatives quotidiennes épuisées', 'chassesautresor-com');
              form.insertBefore(p, form.querySelector('input[name="enigme_id"]'));
            }
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
              btn.disabled = true;
              btn.textContent = timeUntilMidnight();
            }
          }
        }
      });
  });
});
