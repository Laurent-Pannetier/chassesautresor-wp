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

  const shellMode = !!document.querySelector('.shell-edition');
  let answerBaseline = null;

  const applyAnswerValues = (values) => {
    const widget = form.querySelector('[name="widget"]');
    if (widget && values.widget) widget.value = values.widget;
    updateWidgetConfig();
    const config = form.querySelector(
      `.riddle-final-answer-widget-config[data-widget="${widget?.value || ''}"]`
    );
    config?.querySelectorAll('input, textarea, select').forEach(field => {
      if (!field.name) return;
      if (field.type === 'checkbox') {
        field.checked = values[field.name] === '1';
        return;
      }
      field.value = Object.prototype.hasOwnProperty.call(values, field.name) ? values[field.name] : '';
    });
  };

  const captureAnswerBaseline = () => {
    answerBaseline = collectValues();
    window.ctaShellEditionSave?.setExtra('reponse-finale', false);
  };

  const markAnswerDraft = () => {
    if (!shellMode || !window.ctaShellEditionSave || !answerBaseline) return;
    const dirty = JSON.stringify(collectValues()) !== JSON.stringify(answerBaseline);
    window.ctaShellEditionSave.setExtra('reponse-finale', dirty);
  };

  const persistAnswer = async () => {
    feedback.textContent = '';
    const values = collectValues();
    const data = new URLSearchParams({
      action: 'enregistrer_reponse_finale_enigme',
      nonce: RiddleFinalAnswerEdit.nonce,
      enigme_id: RiddleFinalAnswerEdit.riddleId,
      ...values,
    });
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
    const incomplete = summary?.querySelector('.riddle-final-answer-editor__notice');
    if (result.data.complete) {
      incomplete?.remove();
      row?.classList.remove('champ-vide', 'champ-attention');
      row?.classList.add('champ-rempli');
    } else if (!incomplete && summary) {
      const notice = document.createElement('span');
      notice.className = 'riddle-final-answer-editor__notice';
      notice.textContent = RiddleFinalAnswerEdit.texts.incomplete;
      const editButtonInSummary = summary.querySelector('.riddle-final-answer-edit');
      if (editButtonInSummary) {
        summary.insertBefore(notice, editButtonInSummary);
      } else {
        summary.appendChild(notice);
      }
      row?.classList.add('champ-vide');
      row?.classList.remove('champ-rempli');
    }
    if (typeof window.forcerRecalculStatutEnigme === 'function') {
      window.forcerRecalculStatutEnigme(RiddleFinalAnswerEdit.riddleId);
    }
    if (typeof window.mettreAJourResumeInfos === 'function') {
      window.mettreAJourResumeInfos();
    }
    return result;
  };

  editButton?.addEventListener('click', () => {
    feedback.textContent = '';
    updateWidgetConfig();
    showForm(true);
  });

  form?.querySelector('.riddle-final-answer-cancel')?.addEventListener('click', () => {
    if (shellMode && window.ctaShellEditionSave?.hasExtra('reponse-finale')) {
      window.ctaShellEditionSave.restoreExtra('reponse-finale');
    }
    feedback.textContent = '';
    showForm(false);
  });

  form?.querySelector('[name="widget"]')?.addEventListener('change', () => {
    updateWidgetConfig();
    markAnswerDraft();
  });
  if (shellMode && window.ctaShellEditionSave) {
    form?.addEventListener('input', markAnswerDraft);
    form?.addEventListener('change', markAnswerDraft);
    form?.querySelector('button[type="submit"]')?.setAttribute('hidden', '');
    captureAnswerBaseline();
    window.ctaShellEditionSave?.registerExtra('reponse-finale', {
      async save() {
        try {
          await persistAnswer();
          captureAnswerBaseline();
          showForm(false);
          return true;
        } catch (error) {
          feedback.textContent = error instanceof Error && error.message
            ? error.message
            : RiddleFinalAnswerEdit.texts.error;
          throw error;
        }
      },
      restore() {
        if (!answerBaseline) return;
        applyAnswerValues(answerBaseline);
        feedback.textContent = '';
      },
    });
  }

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (shellMode && window.ctaShellEditionSave) {
      window.ctaShellEditionSave.save();
      return;
    }
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      await persistAnswer();
      showForm(false);
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
