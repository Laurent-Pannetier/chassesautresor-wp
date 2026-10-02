document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('.riddle-steps-editor');
  if (!editor || typeof RiddleStepsEdit === 'undefined') return;

  const overview = editor.querySelector('.riddle-steps-editor__overview');
  const list = editor.querySelector('.riddle-steps-editor__list');
  const feedback = editor.querySelector('.riddle-steps-editor__feedback');
  const form = editor.querySelector('.riddle-step-form');
  const formFeedback = editor.querySelector('.riddle-step-form__feedback');
  const heading = editor.querySelector('.riddle-step-form__heading');
  const imageInput = form?.querySelector('[name="image_id"]');
  const contentInput = form?.querySelector('[name="contenu"]');
  const contentEditor = form?.querySelector('.riddle-step-form__content-editor');
  const imagePreview = editor.querySelector('.riddle-step-form__image-preview');
  const imageRemove = editor.querySelector('.riddle-step-image-remove');
  const structureLocked = editor.dataset.structureLocked === '1';
  let dragged = null;
  let savedScroll = 0;

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

  const setImage = (id = '', url = '') => {
    imageInput.value = id;
    imagePreview.innerHTML = url ? `<img src="${url}" alt="">` : '';
    imageRemove.hidden = !url;
  };

  const openForm = async stepId => {
    savedScroll = window.scrollY;
    form.reset();
    contentEditor.innerHTML = '';
    formFeedback.textContent = '';
    setImage();
    form.querySelector('[name="etape_id"]').value = stepId || '';
    heading.textContent = stepId ? RiddleStepsEdit.texts.editTitle : RiddleStepsEdit.texts.newTitle;
    if (stepId) {
      try {
        const step = await request('charger_etape_enigme', { etape_id: stepId });
        form.querySelector('[name="titre"]').value = step.title;
        contentEditor.innerHTML = step.content;
        setImage(step.image_id || '', step.image_url || '');
        if (!structureLocked) {
          form.querySelector('[name="widget"]').value = step.widget || 'click';
          form.querySelector('[name="button_label"]').value = step.button_label;
        }
      } catch (error) {
        feedback.textContent = error.message;
        return;
      }
    }
    overview.hidden = true;
    form.hidden = false;
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
    renumber();
  };

  editor.querySelector('.riddle-step-add')?.addEventListener('click', () => openForm(0));
  editor.querySelectorAll('.riddle-step-cancel').forEach(button => button.addEventListener('click', closeForm));

  list?.addEventListener('click', async event => {
    const card = event.target.closest('.riddle-step-card');
    if (!card) return;
    if (event.target.closest('.riddle-step-edit')) {
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
    contentInput.value = contentEditor.innerHTML;
    const values = Object.fromEntries(new FormData(form).entries());
    formFeedback.textContent = '';
    try {
      const step = await request('enregistrer_etape_enigme', values);
      updateCard(step);
      closeForm();
    } catch (error) {
      formFeedback.textContent = error.message;
    }
  });

  editor.querySelector('.riddle-step-image-select')?.addEventListener('click', () => {
    if (!window.wp?.media) return;
    const frame = wp.media({ title: RiddleStepsEdit.texts.imageTitle, multiple: false, library: { type: 'image' } });
    frame.on('select', () => {
      const image = frame.state().get('selection').first().toJSON();
      setImage(image.id, image.sizes?.medium?.url || image.url);
    });
    frame.open();
  });
  imageRemove?.addEventListener('click', () => setImage());

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
