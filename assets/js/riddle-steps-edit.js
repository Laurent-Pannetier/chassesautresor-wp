document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('.riddle-steps-editor');
  if (!editor || typeof RiddleStepsEdit === 'undefined') return;

  const list = editor.querySelector('.riddle-steps-editor__list');
  const feedback = editor.querySelector('.riddle-steps-editor__feedback');
  let dragged = null;

  const request = async (action, values = {}) => {
    const data = new URLSearchParams({
      action,
      nonce: RiddleStepsEdit.nonce,
      enigme_id: RiddleStepsEdit.riddleId
    });
    Object.entries(values).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach(item => data.append(key, item));
      } else {
        data.append(key, value);
      }
    });
    const response = await fetch(RiddleStepsEdit.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.data || 'request_failed');
    return result.data;
  };

  editor.querySelector('.riddle-step-add')?.addEventListener('click', async () => {
    const title = window.prompt(RiddleStepsEdit.texts.newTitle, '');
    if (title === null) return;
    try {
      await request('creer_etape_enigme', { titre: title });
      window.location.reload();
    } catch (error) {
      feedback.textContent = RiddleStepsEdit.texts.error;
    }
  });

  list?.addEventListener('click', async event => {
    const button = event.target.closest('.riddle-step-delete');
    if (!button || !window.confirm(RiddleStepsEdit.texts.confirmDelete)) return;
    const card = button.closest('.riddle-step-card');
    try {
      await request('supprimer_etape_enigme', { etape_id: card.dataset.stepId });
      card.remove();
      [...list.children].forEach((item, index) => {
        item.querySelector('.riddle-step-card__rank').textContent = index + 1;
      });
    } catch (error) {
      feedback.textContent = RiddleStepsEdit.texts.error;
    }
  });

  list?.addEventListener('dragstart', event => {
    dragged = event.target.closest('.riddle-step-card');
    dragged?.classList.add('is-dragging');
  });
  list?.addEventListener('dragend', async () => {
    dragged?.classList.remove('is-dragging');
    dragged = null;
    const ids = [...list.querySelectorAll('.riddle-step-card')].map(card => card.dataset.stepId);
    try {
      await request('reordonner_etapes_enigme', { 'etape_ids[]': ids });
      [...list.children].forEach((item, index) => {
        item.querySelector('.riddle-step-card__rank').textContent = index + 1;
      });
    } catch (error) {
      feedback.textContent = RiddleStepsEdit.texts.error;
      window.location.reload();
    }
  });
  list?.addEventListener('dragover', event => {
    event.preventDefault();
    const target = event.target.closest('.riddle-step-card');
    if (!dragged || !target || target === dragged) return;
    const bounds = target.getBoundingClientRect();
    list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextSibling);
  });
});
