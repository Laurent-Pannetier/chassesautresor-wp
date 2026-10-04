document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('.riddle-final-answer-editor');
  if (!editor || typeof RiddleFinalAnswerEdit === 'undefined') return;
  if (editor.dataset.editable !== '1') return;

  const summary = editor.querySelector('.riddle-final-answer-editor__summary');
  const label = editor.querySelector('.riddle-final-answer-editor__label');
  const editButton = editor.querySelector('.riddle-final-answer-edit');
  const form = editor.querySelector('.riddle-final-answer-form');
  const feedback = editor.querySelector('.riddle-final-answer-form__feedback');
  const row = editor.closest('.champ-enigme');

  const updateWidgetConfig = () => {
    const widget = form.querySelector('[name="widget"]').value;
    form.querySelectorAll('.riddle-final-answer-widget-config').forEach(config => {
      config.hidden = config.dataset.widget !== widget;
    });
  };

  const showForm = show => {
    form.hidden = !show;
    if (editButton) editButton.hidden = show;
    if (summary) summary.hidden = show;
  };

  const collectValues = () => {
    const widget = form.querySelector('[name="widget"]').value;
    const config = form.querySelector(`.riddle-final-answer-widget-config[data-widget="${widget}"]`);
    const values = { widget };
    config?.querySelectorAll('input, textarea, select').forEach(field => {
      if (!field.name) return;
      if (field.type === 'checkbox') {
        if (field.checked) values[field.name] = '1';
        return;
      }
      values[field.name] = field.value;
    });
    return values;
  };

  editButton?.addEventListener('click', () => {
    feedback.textContent = '';
    updateWidgetConfig();
    showForm(true);
  });

  form?.querySelector('.riddle-final-answer-cancel')?.addEventListener('click', () => {
    feedback.textContent = '';
    showForm(false);
  });

  form?.querySelector('[name="widget"]')?.addEventListener('change', updateWidgetConfig);

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    feedback.textContent = '';
    const values = collectValues();
    const data = new URLSearchParams({
      action: 'enregistrer_reponse_finale_enigme',
      nonce: RiddleFinalAnswerEdit.nonce,
      enigme_id: RiddleFinalAnswerEdit.riddleId,
      ...values,
    });
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      const response = await fetch(RiddleFinalAnswerEdit.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data,
      });
      const result = await response.json();
      if (!result.success) {
        throw new Error(result.data?.message || RiddleFinalAnswerEdit.texts.error);
      }
      if (label) label.textContent = result.data.summary || label.textContent;
      const incomplete = summary?.querySelector('.champ-ajout-image');
      if (result.data.complete) {
        incomplete?.remove();
        row?.classList.remove('champ-vide', 'champ-attention');
        row?.classList.add('champ-rempli');
      } else if (!incomplete && summary) {
        const notice = document.createElement('span');
        notice.className = 'champ-ajout-image';
        notice.textContent = RiddleFinalAnswerEdit.texts.incomplete;
        summary.appendChild(notice);
        row?.classList.add('champ-vide');
        row?.classList.remove('champ-rempli');
      }
      showForm(false);
      if (typeof window.forcerRecalculStatutEnigme === 'function') {
        window.forcerRecalculStatutEnigme(RiddleFinalAnswerEdit.riddleId);
      }
      if (typeof window.mettreAJourResumeInfos === 'function') {
        window.mettreAJourResumeInfos();
      }
    } catch (error) {
      feedback.textContent = error instanceof Error && error.message
        ? error.message
        : RiddleFinalAnswerEdit.texts.error;
    } finally {
      submit.disabled = false;
    }
  });

  updateWidgetConfig();
});
