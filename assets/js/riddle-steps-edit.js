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
  const affichageSelect = form?.querySelector('[name="widget_affichage"]');
  const hotspotCanvas = form?.querySelector('.riddle-step-hotspot-editor__canvas');
  const hotspotStage = form?.querySelector('.riddle-step-hotspot-editor__stage');
  const hotspotImage = form?.querySelector('.riddle-step-hotspot-editor__image');
  const hotspotZoneEl = form?.querySelector('.riddle-step-hotspot-editor__zone');
  const hotspotZoneInput = form?.querySelector('[name="hotspot_zone"]');
  const hotspotClear = form?.querySelector('.riddle-step-hotspot-clear');
  const structureLocked = editor.dataset.structureLocked === '1';
  const MIN_ZONE = 1.5;
  let dragged = null;
  let savedScroll = 0;
  let drawState = null;

  const formatZone = zone => {
    if (!zone) return '';
    const round = value => {
      const text = Number(value).toFixed(2);
      return text.replace(/\.?0+$/, '');
    };
    return `${round(zone.x)},${round(zone.y)},${round(zone.w)},${round(zone.h)}`;
  };

  const parseZone = raw => {
    const parts = String(raw || '').trim().split(/[\s,;]+/).filter(Boolean);
    if (parts.length !== 4 || parts.some(part => Number.isNaN(Number(part)))) return null;
    const [x, y, w, h] = parts.map(Number);
    if (w < MIN_ZONE || h < MIN_ZONE || x < 0 || y < 0 || x + w > 100.01 || y + h > 100.01) {
      return null;
    }
    return { x, y, w, h };
  };

  const applyZone = zone => {
    hotspotZoneInput.value = formatZone(zone);
    if (!zone) {
      hotspotZoneEl.hidden = true;
      hotspotClear.hidden = true;
      return;
    }
    hotspotZoneEl.style.left = `${zone.x}%`;
    hotspotZoneEl.style.top = `${zone.y}%`;
    hotspotZoneEl.style.width = `${zone.w}%`;
    hotspotZoneEl.style.height = `${zone.h}%`;
    hotspotZoneEl.hidden = false;
    hotspotClear.hidden = false;
  };

  const syncHotspotEditor = () => {
    const hasImage = Boolean(imageInput.value && hotspotImage.getAttribute('src'));
    const isHotspot = affichageSelect?.value === 'hotspot';
    hotspotCanvas.hidden = !isHotspot;
    if (!isHotspot) return;
    hotspotImage.hidden = !hasImage;
    if (!hasImage) {
      applyZone(null);
    }
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

  const setImage = (id = '', url = '') => {
    imageInput.value = id;
    imagePreview.innerHTML = url ? `<img src="${url}" alt="">` : '';
    imageRemove.hidden = !url;
    if (url) {
      hotspotImage.src = url;
      hotspotImage.hidden = false;
    } else {
      hotspotImage.removeAttribute('src');
      hotspotImage.hidden = true;
      applyZone(null);
    }
    syncHotspotEditor();
  };

  const openForm = async stepId => {
    savedScroll = window.scrollY;
    form.reset();
    contentEditor.innerHTML = '';
    formFeedback.textContent = '';
    setImage();
    applyZone(null);
    if (affichageSelect) affichageSelect.value = 'always';
    updateWidgetConfig();
    syncHotspotEditor();
    form.querySelector('[name="etape_id"]').value = stepId || '';
    heading.textContent = stepId ? RiddleStepsEdit.texts.editTitle : RiddleStepsEdit.texts.newTitle;
    if (stepId) {
      try {
        const step = await request('charger_etape_enigme', { etape_id: stepId });
        form.querySelector('[name="titre"]').value = step.title;
        contentEditor.innerHTML = step.content;
        setImage(step.image_id || '', step.image_url || '');
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
        if (affichageSelect) {
          affichageSelect.value = step.widget_affichage || 'always';
        }
        applyZone(parseZone(step.hotspot_zone || ''));
        syncHotspotEditor();
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

  const relativePoint = event => {
    const target = hotspotImage?.getAttribute('src') ? hotspotImage : hotspotStage;
    const rect = target.getBoundingClientRect();
    if (!rect.width || !rect.height) {
      return { x: 0, y: 0 };
    }
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    return {
      x: Math.min(100, Math.max(0, x)),
      y: Math.min(100, Math.max(0, y))
    };
  };

  hotspotStage?.addEventListener('pointerdown', event => {
    if (affichageSelect?.value !== 'hotspot' || !imageInput.value || hotspotImage.hidden) return;
    event.preventDefault();
    const point = relativePoint(event);
    drawState = { startX: point.x, startY: point.y };
    hotspotStage.setPointerCapture?.(event.pointerId);
    applyZone({ x: point.x, y: point.y, w: MIN_ZONE, h: MIN_ZONE });
  });

  hotspotStage?.addEventListener('pointermove', event => {
    if (!drawState) return;
    const point = relativePoint(event);
    const x = Math.min(drawState.startX, point.x);
    const y = Math.min(drawState.startY, point.y);
    const w = Math.max(MIN_ZONE, Math.abs(point.x - drawState.startX));
    const h = Math.max(MIN_ZONE, Math.abs(point.y - drawState.startY));
    applyZone({
      x,
      y,
      w: Math.min(w, 100 - x),
      h: Math.min(h, 100 - y)
    });
  });

  const endDraw = () => {
    drawState = null;
  };
  hotspotStage?.addEventListener('pointerup', endDraw);
  hotspotStage?.addEventListener('pointercancel', endDraw);

  affichageSelect?.addEventListener('change', syncHotspotEditor);
  hotspotClear?.addEventListener('click', () => applyZone(null));

  editor.querySelector('.riddle-step-add')?.addEventListener('click', () => openForm(0));
  form?.querySelector('[name="widget"]')?.addEventListener('change', updateWidgetConfig);
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
