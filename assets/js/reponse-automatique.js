function resetInteractiveFinalAnswerWidget(form) {
  const answerInput = form.querySelector('input[name="reponse"]');
  if (answerInput && answerInput.type === 'hidden') answerInput.value = '';
  form.querySelector('.riddle-gps-reset')?.click();
  if (form.classList.contains('riddle-step-piano-form')) form.querySelector('.riddle-widget-reset')?.click();
  form.querySelector('.riddle-directions-reset')?.click();
  form.querySelector('.riddle-colors-reset')?.click();
  form.querySelectorAll('.riddle-numbers .riddle-widget-reset, .riddle-step-numbers-form .riddle-widget-reset')
    .forEach(button => button.click());
  form.querySelector('.riddle-step-safe_dial-form .riddle-widget-reset, .riddle-widget-reset')?.click();
  form.querySelector('.riddle-directions__sequence')?.replaceChildren();
  form.querySelector('.riddle-colors__sequence')?.replaceChildren();
  form.querySelector('.riddle-numbers__sequence')?.replaceChildren();
  form.querySelector('.riddle-safe__sequence')?.replaceChildren();
}

function initFormulaireAutomatique() {
  const form = document.querySelector('.formulaire-reponse-auto');
  if (!form || form.dataset.responseHandlerReady === '1') return;
  form.dataset.responseHandlerReady = '1';
  if (form.dataset.submitDisabled === '1') {
    form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
  }
  const feedback = form.querySelector('.reponse-feedback');
  const hintStorageKey = window.buildRiddleHintStorageKey?.(form) || '';
  if (hintStorageKey && feedback) {
    window.restoreRiddleSessionHint?.(hintStorageKey, feedback);
  }
  const soldeFooter = document.querySelector('.participation-infos .solde');
  const soldeInfo = form.querySelector('.points-sousligne');
  const headerPoints = document.querySelector('.zone-points .points-value');
  const cout = parseInt(form.dataset.cout || '0', 10);
  let soldeAvant = parseInt(form.dataset.soldeAvant || '0', 10);
  let soldeApres = parseInt(form.dataset.soldeApres || '0', 10);
  const seuil = parseInt(form.dataset.seuil || '300', 10);
  const __ = window.wp?.i18n?.__ || (s => s);
  const sprintf = window.wp?.i18n?.sprintf;

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
    data.append('action', form.dataset.widgetAction || 'soumettre_reponse_automatique');

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
            window.showRiddleEphemeralNotice?.(
              __('Erreur serveur', 'chassesautresor-com'),
              { tone: 'wrong', duration: 4200, anchor: feedback }
            );
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
        feedback.querySelectorAll('.riddle-ephemeral-notice--wrong').forEach((node) => node.remove());
        if (!feedback.querySelector('.riddle-ephemeral-notice--hint')) {
          feedback.style.display = 'none';
        }

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
              feedback.className = 'reponse-feedback';
              window.showRiddleEphemeralNotice?.(res.data.message, {
                tone: 'hint',
                persistent: true,
                storageKey: hintStorageKey,
                anchor: feedback
              });
            }
          } else if (res.data.resultat === 'bon') {
            document.dispatchEvent(new CustomEvent('cta:riddle-resolved'));
            if (hintStorageKey) {
              window.clearRiddleSessionHint?.(hintStorageKey);
            }
            feedback.className = 'reponse-feedback';
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
            feedback.className = 'reponse-feedback';
            window.showRiddleEphemeralNotice?.(
              __('Cette réponse n’est pas correcte.', 'chassesautresor-com'),
              { tone: 'wrong', duration: 3800, anchor: feedback }
            );
            resetInteractiveFinalAnswerWidget(form);
          }

        } else {
          const errorMessage = typeof res.data === 'string'
            ? res.data
            : (res.data?.message || __('Erreur serveur', 'chassesautresor-com'));
          window.showRiddleEphemeralNotice?.(errorMessage, {
            tone: 'wrong',
            duration: 4200,
            anchor: feedback
          });
        }
      });
  });
}

document.addEventListener('DOMContentLoaded', initFormulaireAutomatique);
document.addEventListener('riddle-step-content-updated', initFormulaireAutomatique);
