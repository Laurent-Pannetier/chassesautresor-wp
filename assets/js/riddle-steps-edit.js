document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('.riddle-steps-editor');
  if (!editor || typeof RiddleStepsEdit === 'undefined') return;

  const overview = editor.querySelector('.riddle-steps-editor__overview');
  const list = editor.querySelector('.riddle-steps-editor__list');
  const feedback = editor.querySelector('.riddle-steps-editor__feedback');
  const form = editor.querySelector('.riddle-step-form');
  const formFeedback = editor.querySelector('.riddle-step-form__feedback');
  const heading = editor.querySelector('.riddle-step-form__heading');
  const contentInput = form?.querySelector('[name="contenu"]');
  const contentEditor = form?.querySelector('.riddle-step-form__content-editor');
  const imagePreview = editor.querySelector('.riddle-step-form__image-preview');
  const structureLocked = editor.dataset.structureLocked === '1';
  let dragged = null;
  let savedScroll = 0;
  let stepImages = [];
  let stepImageBaseline = [];

  const legendMark = form?.querySelector('.answer-gap-marker--legend');
  const answerFieldset = form?.querySelector('fieldset');

  const showLegendIssue = message => {
    if (!legendMark) return;
    const tip = legendMark.querySelector('.answer-gap-marker__tip');
    if (!message) {
      legendMark.hidden = true;
      legendMark.removeAttribute('data-message');
      legendMark.removeAttribute('title');
      if (tip) tip.textContent = '';
      answerFieldset?.classList.remove('is-invalid');
      return;
    }
    if (tip) tip.textContent = message;
    legendMark.dataset.message = message;
    legendMark.title = message;
    legendMark.hidden = false;
    answerFieldset?.classList.add('is-invalid');
  };

  const updateWidgetConfig = () => {
    const widgetSelect = form?.querySelector('[name="widget"]');
    if (!widgetSelect) return;
    const widget = widgetSelect.value;
    form.querySelectorAll('.riddle-step-widget-config').forEach(config => {
      config.hidden = config.dataset.widget !== widget;
    });
  };

  const request = async (action, values = {}) => {
    const data = new URLSearchParams({
      action,
      nonce: RiddleStepsEdit.nonce,
      enigme_id: RiddleStepsEdit.riddleId
    });
    Object.entries(values).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach(item => data.append(key, item));
      else data.append(key, value);
    });
    const response = await fetch(RiddleStepsEdit.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.data?.message || RiddleStepsEdit.texts.error);
    return result.data;
  };

  const shellMode = !!document.querySelector('.shell-edition');
  let stepBaselineValues = null;

  const stepPayload = () => {
    contentInput.value = contentEditor.innerHTML;
    const values = {};
    new FormData(form).forEach((value, key) => {
      if (key === 'image_ids[]') {
        values[key] = values[key] || [];
        values[key].push(value);
        return;
      }
      values[key] = value;
    });
    return values;
  };

  function markStepDraft() {
    if (!shellMode || !window.ctaShellEditionSave || !stepBaselineValues || form.hidden) return;
    const dirty = JSON.stringify(stepPayload()) !== JSON.stringify(stepBaselineValues);
    window.ctaShellEditionSave.setExtra('etape', dirty);
  }

  const captureStepBaseline = () => {
    if (!form) return;
    contentInput.value = contentEditor.innerHTML;
    stepBaselineValues = stepPayload();
    stepImageBaseline = stepImages.map(image => ({ ...image }));
    window.ctaShellEditionSave?.setExtra('etape', false);
  };

  const renderImages = (silent = false) => {
    if (!imagePreview) return;
    imagePreview.replaceChildren();
    const moveUp = RiddleStepsEdit.texts.moveUp || 'Monter';
    const moveDown = RiddleStepsEdit.texts.moveDown || 'Descendre';
    const removePage = RiddleStepsEdit.texts.removePage || 'Retirer';
    stepImages.forEach((image, index) => {
      const item = document.createElement('div');
      item.className = 'riddle-step-form__image-item';

      const img = document.createElement('img');
      img.src = image.url;
      img.alt = '';
      item.append(img);

      const rank = document.createElement('span');
      rank.className = 'riddle-step-form__image-rank';
      rank.textContent = String(index + 1);
      item.append(rank);

      const actions = document.createElement('div');
      actions.className = 'riddle-step-form__image-item-actions';

      const up = document.createElement('button');
      up.type = 'button';
      up.className = 'bouton-texte riddle-step-image-up';
      up.textContent = moveUp;
      up.hidden = index === 0;
      actions.append(up);

      const down = document.createElement('button');
      down.type = 'button';
      down.className = 'bouton-texte riddle-step-image-down';
      down.textContent = moveDown;
      down.hidden = index === stepImages.length - 1;
      actions.append(down);

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'bouton-texte riddle-step-image-remove';
      remove.textContent = removePage;
      actions.append(remove);

      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'image_ids[]';
      input.value = image.id;
      item.append(actions, input);
      imagePreview.append(item);
    });
    if (!silent) markStepDraft();
  };

  const setImages = (images, silent = false) => {
    const seen = new Set();
    stepImages = [];
    images.forEach(image => {
      const id = String(image.id || '');
      if (!id || id === '0' || seen.has(id)) return;
      seen.add(id);
      stepImages.push({ id, url: String(image.url || '') });
    });
    renderImages(silent);
  };

  const applyStepValues = (values) => {
    form.querySelectorAll('input, textarea, select').forEach((field) => {
      if (!field.name || field === contentInput || field.name === 'image_ids[]') return;
      if (field.type === 'checkbox') {
        field.checked = values[field.name] === '1';
        return;
      }
      if (Object.prototype.hasOwnProperty.call(values, field.name)) {
        field.value = values[field.name];
      }
    });
    contentEditor.innerHTML = values.contenu || '';
    contentInput.value = contentEditor.innerHTML;
    setImages(stepImageBaseline, true);
    updateWidgetConfig();
  };

  const abandonStepDraft = () => {
    if (!shellMode || !window.ctaShellEditionSave?.hasExtra('etape')) return true;
    if (!window.confirm(RiddleStepsEdit.texts.abandon)) return false;
    window.ctaShellEditionSave.restoreExtra('etape');
    return true;
  };

  if (shellMode && form && window.ctaShellEditionSave) {
    form.querySelector('button[type="submit"]')?.setAttribute('hidden', '');
    form.addEventListener('input', markStepDraft);
    form.addEventListener('change', markStepDraft);
    contentEditor?.addEventListener('input', markStepDraft);
    window.ctaShellEditionSave.registerExtra('etape', {
      async save() {
        contentInput.value = contentEditor.innerHTML;
        formFeedback.textContent = '';
        try {
          const step = await request('enregistrer_etape_enigme', stepPayload());
          updateCard(step);
          closeForm();
          return true;
        } catch (error) {
          const message = error instanceof Error ? error.message : '';
          formFeedback.textContent = message;
          showLegendIssue(message);
          answerFieldset?.scrollIntoView?.({ block: 'nearest' });
          throw error;
        }
      },
      restore() {
        if (!stepBaselineValues) return;
        stepImages = stepImageBaseline.map(image => ({ ...image }));
        applyStepValues(stepBaselineValues);
        if (formFeedback) formFeedback.textContent = '';
      },
    });
  }

  const openForm = async stepId => {
    savedScroll = window.scrollY;
    form.reset();
    contentEditor.innerHTML = '';
    formFeedback.textContent = '';
    setImages([], true);
    updateWidgetConfig();
    form.querySelector('[name="etape_id"]').value = stepId || '';
    heading.textContent = stepId ? RiddleStepsEdit.texts.editTitle : RiddleStepsEdit.texts.newTitle;
    const card = stepId ? list.querySelector(`[data-step-id="${stepId}"]`) : null;
    const storedIssue = card?.querySelector('.answer-gap-marker')?.dataset.message || '';
    if (stepId) {
      try {
        const step = await request('charger_etape_enigme', { etape_id: stepId });
        form.querySelector('[name="titre"]').value = step.title;
        contentEditor.innerHTML = step.content;
        const loadedImages = Array.isArray(step.images) && step.images.length
          ? step.images
          : (step.image_id ? [{ id: step.image_id, url: step.image_url || '' }] : []);
        setImages(loadedImages, true);
        const widgetSelect = form.querySelector('[name="widget"]');
        if (widgetSelect) {
          widgetSelect.value = step.widget || 'click';
          form.querySelector('[name="button_label"]').value = step.button_label || '';
          form.querySelector('[name="accepted_answers"]').value = step.accepted_answers || '';
          const caseSensitive = form.querySelector('[name="case_sensitive"]');
          if (caseSensitive) caseSensitive.checked = Boolean(step.case_sensitive);
          form.querySelector('[name="variants"]').value = step.variants || '';
          form.querySelector('[name="direction_sequences"]').value = step.direction_sequences || '';
          form.querySelector('[name="color_sequences"]').value = step.color_sequences || '';
          form.querySelector('[name="number_sequences"]').value = step.number_sequences || '';
          form.querySelector('[name="safe_dial_sequences"]').value = step.safe_dial_sequences || '';
          form.querySelector('[name="piano_sequences"]').value = step.piano_sequences || '';
          form.querySelector('[name="gps_coordinates"]').value = step.gps_coordinates || '';
          form.querySelector('[name="gps_tolerance"]').value = step.gps_tolerance || '25';
          updateWidgetConfig();
        }
      } catch (error) {
        feedback.textContent = error.message;
        return;
      }
    }
    overview.hidden = true;
    form.hidden = false;
    showLegendIssue(storedIssue);
    captureStepBaseline();
    form.querySelector('[name="titre"]').focus();
  };

  const closeForm = () => {
    form.hidden = true;
    overview.hidden = false;
    window.scrollTo({ top: savedScroll });
  };

  const renumber = () => [...list.children].forEach((item, index) => {
    item.querySelector('.riddle-step-card__rank').textContent = index + 1;
  });

  const updateCard = step => {
    let card = list.querySelector(`[data-step-id="${step.step_id}"]`);
    if (!card) {
      card = document.createElement('li');
      card.className = 'riddle-step-card';
      card.dataset.stepId = step.step_id;
      card.draggable = true;
      card.innerHTML = '<span class="riddle-step-card__handle" aria-hidden="true">' +
        '<i class="fa-solid fa-grip-vertical"></i></span>' +
        '<span class="riddle-step-card__rank"></span>' +
        '<span class="riddle-step-card__content"><strong></strong></span>' +
        '<span class="riddle-step-card__actions"><button type="button" ' +
        'class="bouton-tertiaire riddle-step-edit"></button><button type="button" ' +
        'class="bouton-texte secondaire riddle-step-delete"></button></span>';
      card.querySelector('.riddle-step-edit').textContent = RiddleStepsEdit.texts.edit;
      card.querySelector('.riddle-step-delete').textContent = RiddleStepsEdit.texts.delete;
      list.append(card);
    }
    card.querySelector('.riddle-step-card__content strong').textContent = step.title;
    card.classList.remove('is-incomplete');
    card.querySelector('.answer-gap-marker')?.remove();
    renumber();
  };

  editor.querySelector('.riddle-step-add')?.addEventListener('click', () => {
    if (!abandonStepDraft()) return;
    openForm(0);
  });
  form?.querySelector('[name="widget"]')?.addEventListener('change', updateWidgetConfig);
  editor.querySelectorAll('.riddle-step-cancel').forEach(button => button.addEventListener('click', () => {
    if (shellMode && window.ctaShellEditionSave?.hasExtra('etape')) {
      window.ctaShellEditionSave.restoreExtra('etape');
    }
    closeForm();
  }));

  list?.addEventListener('click', async event => {
    const card = event.target.closest('.riddle-step-card');
    if (!card) return;
    if (event.target.closest('.riddle-step-edit')) {
      if (!abandonStepDraft()) return;
      openForm(card.dataset.stepId);
      return;
    }
    const button = event.target.closest('.riddle-step-delete');
    if (!button || !window.confirm(RiddleStepsEdit.texts.confirmDelete)) return;
    try {
      await request('supprimer_etape_enigme', { etape_id: card.dataset.stepId });
      card.remove();
      renumber();
    } catch (error) {
      feedback.textContent = error.message;
    }
  });

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (shellMode && window.ctaShellEditionSave) {
      window.ctaShellEditionSave.save();
      return;
    }
    const values = stepPayload();
    formFeedback.textContent = '';
    try {
      const step = await request('enregistrer_etape_enigme', values);
      updateCard(step);
      closeForm();
    } catch (error) {
      formFeedback.textContent = error.message;
      showLegendIssue(error.message);
      answerFieldset?.scrollIntoView?.({ block: 'nearest' });
    }
  });

  editor.querySelector('.riddle-step-image-select')?.addEventListener('click', () => {
    if (!window.wp?.media) return;
    const frame = wp.media({
      title: RiddleStepsEdit.texts.imageTitle,
      multiple: true,
      library: { type: 'image' }
    });
    frame.on('select', () => {
      const selected = frame.state().get('selection').toJSON();
      setImages(stepImages.concat(selected.map(image => ({
        id: image.id,
        url: image.sizes?.medium?.url || image.url
      }))));
    });
    frame.open();
  });
  imagePreview?.addEventListener('click', event => {
    const item = event.target.closest('.riddle-step-form__image-item');
    if (!item) return;
    const index = [...imagePreview.children].indexOf(item);
    if (index < 0) return;
    if (event.target.closest('.riddle-step-image-remove')) {
      stepImages.splice(index, 1);
      renderImages();
      return;
    }
    if (event.target.closest('.riddle-step-image-up') && index > 0) {
      [stepImages[index - 1], stepImages[index]] = [stepImages[index], stepImages[index - 1]];
      renderImages();
      return;
    }
    if (event.target.closest('.riddle-step-image-down') && index < stepImages.length - 1) {
      [stepImages[index + 1], stepImages[index]] = [stepImages[index], stepImages[index + 1]];
      renderImages();
    }
  });

  list?.addEventListener('dragstart', event => {
    if (structureLocked) {
      event.preventDefault();
      return;
    }
    dragged = event.target.closest('.riddle-step-card');
    dragged?.classList.add('is-dragging');
  });
  list?.addEventListener('dragend', async () => {
    if (structureLocked) return;
    dragged?.classList.remove('is-dragging');
    dragged = null;
    const ids = [...list.querySelectorAll('.riddle-step-card')].map(card => card.dataset.stepId);
    try {
      await request('reordonner_etapes_enigme', { 'etape_ids[]': ids });
      renumber();
    } catch (error) {
      feedback.textContent = error.message;
      window.location.reload();
    }
  });
  list?.addEventListener('dragover', event => {
    if (structureLocked) return;
    event.preventDefault();
    const target = event.target.closest('.riddle-step-card');
    if (!dragged || !target || target === dragged) return;
    const bounds = target.getBoundingClientRect();
    list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextSibling);
  });
});
