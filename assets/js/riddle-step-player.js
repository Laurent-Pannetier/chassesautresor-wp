document.addEventListener('DOMContentLoaded', async () => {
  const target = window.sessionStorage.getItem('riddleStepScrollTarget');
  if (!target) return;
  const images = [...document.querySelectorAll('.riddle-steps-player img')];
  await Promise.allSettled(images.map(image => {
    if (image.complete) return image.decode?.() || Promise.resolve();
    return new Promise(resolve => {
      image.addEventListener('load', resolve, { once: true });
      image.addEventListener('error', resolve, { once: true });
    });
  }));
  await document.fonts?.ready;
  const element = target === 'final'
    ? document.querySelector('.formulaire-reponse-auto, .formulaire-reponse-manuelle') ||
      [...document.querySelectorAll('.riddle-player-step')].pop()
    : document.querySelector(`[data-player-step-id="${target}"]`);
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
    element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.sessionStorage.removeItem('riddleStepScrollTarget');
  }));
});

document.addEventListener('submit', async event => {
  const form = event.target.closest('.riddle-step-click-form, .riddle-step-text-form');
  if (!form || typeof RiddleStepPlayer === 'undefined') return;
  event.preventDefault();
  const button = form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('.riddle-step-click-form__feedback');
  const data = new FormData(form);
  data.append('action', form.classList.contains('riddle-step-text-form')
    ? 'soumettre_reponse_etape'
    : 'confirmer_etape_enigme');
  button.disabled = true;
  try {
    const response = await fetch(RiddleStepPlayer.ajaxUrl, { method: 'POST', body: data });
    const result = await response.json();
    if (!result.success) throw new Error(result.data?.message || RiddleStepPlayer.error);
    if (result.data.resultat && result.data.resultat !== 'bon') {
      feedback.textContent = result.data.resultat === 'variante' && result.data.message
        ? result.data.message
        : RiddleStepPlayer.wrong;
      const answerInput = form.querySelector('input[name="reponse"]');
      if (answerInput) answerInput.value = '';
      const counter = document.querySelector('.tentatives-counter .valeur');
      const footer = document.querySelector('.participation-infos .tentatives');
      if (counter) counter.textContent = result.data.compteur;
      if (footer) {
        const maximum = footer.dataset.max || footer.textContent.split('/')[1]?.trim() || '∞';
        footer.dataset.max = maximum;
        footer.textContent = `${RiddleStepPlayer.attemptsLabel} ${result.data.compteur}/${maximum}`;
      }
      const maximum = Number.parseInt(form.dataset.maxFailures || '0', 10);
      if (maximum > 0 && result.data.compteur >= maximum) {
        answerInput?.setAttribute('disabled', 'disabled');
        button.disabled = true;
        feedback.textContent = RiddleStepPlayer.limitReached;
        return;
      }
      button.disabled = false;
      return;
    }
    window.sessionStorage.setItem(
      'riddleStepScrollTarget',
      result.data.current_step_id ? String(result.data.current_step_id) : 'final'
    );
    window.location.reload();
  } catch (error) {
    feedback.textContent = error.message;
    button.disabled = false;
  }
});
