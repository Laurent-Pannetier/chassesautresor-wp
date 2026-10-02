document.addEventListener('submit', async event => {
  const form = event.target.closest('.riddle-step-click-form');
  if (!form || typeof RiddleStepPlayer === 'undefined') return;
  event.preventDefault();
  const button = form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('.riddle-step-click-form__feedback');
  const data = new FormData(form);
  data.append('action', 'confirmer_etape_enigme');
  button.disabled = true;
  try {
    const response = await fetch(RiddleStepPlayer.ajaxUrl, { method: 'POST', body: data });
    const result = await response.json();
    if (!result.success) throw new Error(result.data?.message || RiddleStepPlayer.error);
    window.location.reload();
  } catch (error) {
    feedback.textContent = error.message;
    button.disabled = false;
  }
});
