/**
 * Viewer d’images d’énigme : feuilletage BD (vignettes + prev/next) et lightbox.
 * Couvre le hero (`[data-enigme-gallery]`). Les pages d’étapes débloquées
 * s’ajoutent à cette galerie (y compris via AJAX).
 *
 * La lightbox charge l’URL full et l’affiche en taille native (1:1),
 * avec défilement si l’image dépasse le viewport — indispensable pour
 * repérer de petits détails.
 */
(function () {
  const CLOSE_LABEL =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.closeLabel) || 'Fermer';
  const nativeSizeLabel =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.nativeSizeLabel) ||
    'Taille originale';
  const pageLabelTemplate =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.pageLabel) || 'Page %1$d / %2$d';
  const prevPageLabel =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.prevPageLabel) || 'Page précédente';
  const nextPageLabel =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.nextPageLabel) || 'Page suivante';
  const pagesListLabel =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.pagesListLabel) ||
    'Pages de l’énigme';
  const showPageLabel =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.showPageLabel) ||
    'Afficher la page %d';

  const formatPageLabel = (current, total) =>
    pageLabelTemplate
      .replace('%1$d', String(current))
      .replace('%2$d', String(total))
      .replace('%d', String(current));

  const formatShowPageLabel = (pageNumber) =>
    showPageLabel.replace('%d', String(pageNumber));

  const updateGalleryChrome = (gallery, activeIndex) => {
    const slides = gallery.querySelectorAll('.galerie-enigme__slide');
    const total = slides.length;
    gallery.dataset.galleryPageCount = String(total);

    const pager = gallery.querySelector('.galerie-enigme__page-label');
    if (pager && total > 0) {
      pager.textContent = formatPageLabel(activeIndex + 1, total);
    }

    const prev = gallery.querySelector('.galerie-enigme__nav--prev');
    const next = gallery.querySelector('.galerie-enigme__nav--next');
    if (prev) {
      prev.disabled = activeIndex <= 0;
    }
    if (next) {
      next.disabled = activeIndex >= total - 1;
    }
  };

  const ensureGalleryControls = (gallery) => {
    const stage = gallery.querySelector('.galerie-enigme__stage');
    if (!stage) {
      return;
    }

    if (!gallery.querySelector('.galerie-enigme__nav--prev')) {
      const prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'galerie-enigme__nav galerie-enigme__nav--prev';
      prev.dataset.galleryStep = '-1';
      prev.setAttribute('aria-label', prevPageLabel);
      prev.innerHTML = '<span aria-hidden="true">&lsaquo;</span>';
      stage.insertBefore(prev, stage.firstChild);
    }

    if (!gallery.querySelector('.galerie-enigme__nav--next')) {
      const next = document.createElement('button');
      next.type = 'button';
      next.className = 'galerie-enigme__nav galerie-enigme__nav--next';
      next.dataset.galleryStep = '1';
      next.setAttribute('aria-label', nextPageLabel);
      next.innerHTML = '<span aria-hidden="true">&rsaquo;</span>';
      stage.appendChild(next);
    }

    if (!gallery.querySelector('.galerie-enigme__pager')) {
      const pager = document.createElement('div');
      pager.className = 'galerie-enigme__pager';
      pager.setAttribute('aria-live', 'polite');
      const label = document.createElement('span');
      label.className = 'galerie-enigme__page-label';
      pager.appendChild(label);
      const thumbs = gallery.querySelector('.galerie-enigme__thumbs');
      if (thumbs) {
        gallery.insertBefore(pager, thumbs);
      } else {
        gallery.appendChild(pager);
      }
    }

    if (!gallery.querySelector('.galerie-enigme__thumbs')) {
      const thumbs = document.createElement('div');
      thumbs.className = 'galerie-enigme__thumbs';
      thumbs.setAttribute('role', 'tablist');
      thumbs.setAttribute('aria-label', pagesListLabel);
      gallery.appendChild(thumbs);
    }
  };

  const selectGallerySlide = (gallery, index) => {
    const slides = [...gallery.querySelectorAll('.galerie-enigme__slide')];
    const thumbs = gallery.querySelectorAll('[data-gallery-goto]');
    if (!slides.length) {
      return -1;
    }

    const target = Math.max(0, Math.min(index, slides.length - 1));

    slides.forEach((slide, slideIndex) => {
      const isActive = slideIndex === target;
      slide.classList.toggle('is-active', isActive);
      slide.hidden = !isActive;
      slide.dataset.galleryIndex = String(slideIndex);
      const img = slide.querySelector('img');
      if (img) {
        img.classList.toggle('image-active', isActive);
        if (isActive) {
          img.id = 'image-enigme-active';
        } else if (img.id === 'image-enigme-active') {
          img.removeAttribute('id');
        }
      }
    });

    thumbs.forEach((thumb) => {
      const isActive = Number(thumb.dataset.galleryGoto) === target;
      thumb.classList.toggle('is-active', isActive);
      thumb.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    updateGalleryChrome(gallery, target);
    return target;
  };

  const appendGalleryPage = (page) => {
    const gallery = document.querySelector('[data-enigme-gallery]');
    if (!gallery || !page || !page.previewUrl || !page.fullUrl) {
      return -1;
    }

    const stepId = page.stepId ? String(page.stepId) : '';
    if (stepId && gallery.querySelector(`[data-gallery-step-id="${stepId}"]`)) {
      const existing = gallery.querySelector(
        `.galerie-enigme__slide[data-gallery-step-id="${stepId}"]`
      );
      const index = existing
        ? [...gallery.querySelectorAll('.galerie-enigme__slide')].indexOf(existing)
        : -1;
      return index >= 0 ? selectGallerySlide(gallery, index) : -1;
    }

    ensureGalleryControls(gallery);
    const stage = gallery.querySelector('.galerie-enigme__stage');
    const thumbs = gallery.querySelector('.galerie-enigme__thumbs');
    const nextNav = gallery.querySelector('.galerie-enigme__nav--next');
    const index = gallery.querySelectorAll('.galerie-enigme__slide').length;
    const galleryId = gallery.id || 'galerie-enigme';
    const slideId = `${galleryId}-slide-${index}`;
    const alt = page.alt || '';

    const figure = document.createElement('figure');
    figure.className =
      'image-principale galerie-enigme__slide galerie-enigme__slide--step';
    figure.id = slideId;
    figure.dataset.galleryIndex = String(index);
    figure.dataset.galleryImageId = String(page.imageId || '');
    if (stepId) {
      figure.dataset.galleryStepId = stepId;
    }
    figure.hidden = true;

    const zoom = document.createElement('button');
    zoom.type = 'button';
    zoom.className = 'enigme-media-zoom';
    zoom.dataset.enigmeLightboxSrc = page.fullUrl;
    zoom.dataset.enigmeLightboxAlt = alt;
    zoom.setAttribute('aria-label', nativeSizeLabel);
    const hotspotZone = String(page.hotspotZone || '').trim();
    if (hotspotZone && stepId) {
      zoom.dataset.riddleHotspotZone = hotspotZone;
      zoom.dataset.riddleHotspotStep = stepId;
      zoom.dataset.riddleHotspotLabel = page.hotspotLabel || '';
    }

    const img = document.createElement('img');
    img.className = 'enigme-image--limited';
    img.src = page.previewUrl;
    img.alt = alt;
    img.loading = 'lazy';
    if (page.width) {
      img.width = Number(page.width);
    }
    if (page.height) {
      img.height = Number(page.height);
    }

    const hint = document.createElement('span');
    hint.className = 'enigme-media-zoom__hint';
    hint.setAttribute('aria-hidden', 'true');
    hint.textContent =
      (window.EnigmeImageViewer && window.EnigmeImageViewer.zoomHint) || 'Agrandir';

    zoom.appendChild(img);
    zoom.appendChild(hint);
    figure.appendChild(zoom);
    if (nextNav) {
      stage.insertBefore(figure, nextNav);
    } else {
      stage.appendChild(figure);
    }

    const thumb = document.createElement('button');
    thumb.type = 'button';
    thumb.className = 'galerie-enigme__thumb galerie-enigme__thumb--step';
    thumb.setAttribute('role', 'tab');
    thumb.setAttribute('aria-selected', 'false');
    thumb.setAttribute('aria-controls', slideId);
    thumb.dataset.galleryGoto = String(index);
    if (stepId) {
      thumb.dataset.galleryStepId = stepId;
    }
    thumb.setAttribute('aria-label', formatShowPageLabel(index + 1));
    if (page.thumbUrl) {
      const thumbImg = document.createElement('img');
      thumbImg.src = page.thumbUrl;
      thumbImg.alt = '';
      thumbImg.loading = 'lazy';
      thumbImg.width = 72;
      thumbImg.height = 72;
      thumb.appendChild(thumbImg);
    }
    thumbs.appendChild(thumb);

    return selectGallerySlide(gallery, index);
  };

  window.EnigmeGallery = {
    selectSlide: (gallery, index) => selectGallerySlide(gallery, index),
    appendPage: appendGalleryPage,
    getGallery: () => document.querySelector('[data-enigme-gallery]'),
  };

  const closeLightbox = () => {
    const overlay = document.querySelector('.enigme-lightbox-overlay');
    if (!overlay) {
      return;
    }
    const triggerId = overlay.dataset.triggerId;
    overlay.remove();
    document.body.classList.remove('no-scroll');
    if (triggerId) {
      document.getElementById(triggerId)?.focus();
    }
  };

  const mountLightboxHotspot = (dialog, image, trigger) => {
    const zone = trigger?.getAttribute('data-riddle-hotspot-zone') || '';
    const stepId = trigger?.getAttribute('data-riddle-hotspot-step') || '';
    if (!zone || !stepId) {
      return;
    }

    const parts = zone.split(/[\s,;]+/).map(Number);
    if (parts.length !== 4 || parts.some((value) => !Number.isFinite(value))) {
      return;
    }

    const stage = document.createElement('div');
    stage.className = 'enigme-lightbox__hotspot-stage';
    stage.dataset.riddleHotspotZone = zone;
    stage.dataset.riddleHotspotStep = stepId;

    image.replaceWith(stage);
    stage.appendChild(image);

    const hotspot = document.createElement('button');
    hotspot.type = 'button';
    hotspot.className = 'riddle-gallery-hotspot';
    hotspot.dataset.riddleOpenWidget = '';
    hotspot.setAttribute(
      'aria-label',
      trigger.getAttribute('data-riddle-hotspot-label') || 'Zone interactive'
    );
    hotspot.style.left = `${parts[0]}%`;
    hotspot.style.top = `${parts[1]}%`;
    hotspot.style.width = `${parts[2]}%`;
    hotspot.style.height = `${parts[3]}%`;
    stage.appendChild(hotspot);
  };

  const openLightbox = (src, alt, trigger) => {
    if (!src) {
      return;
    }

    closeLightbox();

    const triggerId =
      trigger?.id ||
      `enigme-lightbox-trigger-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
    if (trigger && !trigger.id) {
      trigger.id = triggerId;
    }

    const overlay = document.createElement('div');
    overlay.className = 'enigme-lightbox-overlay';
    overlay.dataset.triggerId = triggerId;

    const dialog = document.createElement('div');
    dialog.className = 'enigme-lightbox';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-label', nativeSizeLabel);

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'enigme-lightbox__close';
    closeButton.setAttribute('aria-label', CLOSE_LABEL);
    closeButton.textContent = '×';

    const image = document.createElement('img');
    image.className = 'enigme-lightbox__image';
    image.src = src;
    image.alt = alt || '';
    image.decoding = 'async';

    dialog.appendChild(closeButton);
    dialog.appendChild(image);
    mountLightboxHotspot(dialog, image, trigger);
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    document.body.classList.add('no-scroll');

    closeButton.focus();
    closeButton.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        closeLightbox();
      }
    });
  };

  document.addEventListener('click', (event) => {
    const stepButton = event.target.closest('[data-gallery-step]');
    if (stepButton) {
      const gallery = stepButton.closest('[data-enigme-gallery]');
      if (!gallery) {
        return;
      }
      event.preventDefault();
      const current = [...gallery.querySelectorAll('.galerie-enigme__slide')].findIndex(
        (slide) => slide.classList.contains('is-active')
      );
      selectGallerySlide(gallery, current + Number(stepButton.dataset.galleryStep || 0));
      return;
    }

    const thumb = event.target.closest('[data-gallery-goto]');
    if (thumb) {
      const gallery = thumb.closest('[data-enigme-gallery]');
      if (!gallery) {
        return;
      }
      event.preventDefault();
      selectGallerySlide(gallery, Number(thumb.dataset.galleryGoto));
      return;
    }

    const zoomTrigger = event.target.closest('[data-enigme-lightbox-src]');
    if (!zoomTrigger) {
      return;
    }

    event.preventDefault();
    openLightbox(
      zoomTrigger.getAttribute('data-enigme-lightbox-src'),
      zoomTrigger.getAttribute('data-enigme-lightbox-alt') || '',
      zoomTrigger
    );
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (document.querySelector('form.is-hotspot-widget.is-immersive-open')) {
        return;
      }
      closeLightbox();
      return;
    }

    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
      return;
    }
    if (document.querySelector('.enigme-lightbox-overlay')) {
      return;
    }

    const gallery = event.target.closest?.('[data-enigme-gallery]') ||
      document.querySelector('[data-enigme-gallery]');
    if (!gallery || gallery.querySelectorAll('.galerie-enigme__slide').length < 2) {
      return;
    }

    const tag = (event.target && event.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || event.target?.isContentEditable) {
      return;
    }

    const current = [...gallery.querySelectorAll('.galerie-enigme__slide')].findIndex(
      (slide) => slide.classList.contains('is-active')
    );
    const delta = event.key === 'ArrowLeft' ? -1 : 1;
    selectGallerySlide(gallery, current + delta);
  });

  document.querySelectorAll('[data-enigme-gallery]').forEach((gallery) => {
    const active = [...gallery.querySelectorAll('.galerie-enigme__slide')].findIndex(
      (slide) => slide.classList.contains('is-active')
    );
    updateGalleryChrome(gallery, active >= 0 ? active : 0);
  });

  const noticeConfig = window.EnigmeImageViewer || {};
  let noticeTimer = null;

  const appendNoticeMessage = (container, text, allowLinks) => {
    if (!allowLinks) {
      container.textContent = text;
      return;
    }

    const pattern = /\bhttps?:\/\/[^\s<>"']+/gi;
    let lastIndex = 0;
    let match;
    while ((match = pattern.exec(text)) !== null) {
      if (match.index > lastIndex) {
        container.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
      }

      const raw = match[0];
      const href = raw.replace(/[),.;:!?]+$/u, '');
      const trailing = raw.slice(href.length);
      let linked = false;
      try {
        const url = new URL(href);
        if (url.protocol === 'http:' || url.protocol === 'https:') {
          const link = document.createElement('a');
          link.href = url.href;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = href;
          container.appendChild(link);
          linked = true;
        }
      } catch (error) {
        linked = false;
      }

      if (!linked) {
        container.appendChild(document.createTextNode(raw));
      } else if (trailing) {
        container.appendChild(document.createTextNode(trailing));
      }

      lastIndex = match.index + raw.length;
    }

    if (lastIndex < text.length) {
      container.appendChild(document.createTextNode(text.slice(lastIndex)));
    }
  };

  const resolveNoticeHost = (anchor) => {
    if (anchor) {
      return null;
    }
    return (
      document.querySelector('.riddle-player-step.is-current') ||
      document.querySelector('.participation .zone-reponse') ||
      document.querySelector('.participation') ||
      document.body
    );
  };

  const mountNotice = (notice, anchor) => {
    if (anchor) {
      if (anchor.classList?.contains('reponse-feedback')) {
        anchor.style.display = 'block';
      }
      const existingWrong = anchor.querySelector('.riddle-ephemeral-notice--wrong');
      if (notice.classList.contains('riddle-ephemeral-notice--hint') && existingWrong) {
        anchor.insertBefore(notice, existingWrong);
      } else {
        anchor.appendChild(notice);
      }
      return;
    }

    resolveNoticeHost(null).appendChild(notice);
  };

  window.buildRiddleHintStorageKey = (form) => {
    if (!(form instanceof Element)) {
      return '';
    }
    const enigmeId = form.querySelector('input[name="enigme_id"]')?.value || '';
    if (!enigmeId) {
      return '';
    }
    const etapeId = form.querySelector('input[name="etape_id"]')?.value || '';
    return etapeId
      ? `riddle-hint:${enigmeId}:step:${etapeId}`
      : `riddle-hint:${enigmeId}:final`;
  };

  window.clearRiddleSessionHint = (storageKey) => {
    if (!storageKey) {
      return;
    }
    try {
      window.sessionStorage.removeItem(storageKey);
    } catch (error) {
      // Ignore quota / private-mode failures.
    }
    document.querySelectorAll('.riddle-ephemeral-notice--hint').forEach((node) => {
      if (node.dataset.storageKey !== storageKey) {
        return;
      }
      const host = node.parentElement;
      node.remove();
      if (
        host?.classList?.contains('reponse-feedback') &&
        !host.querySelector('.riddle-ephemeral-notice')
      ) {
        host.style.display = 'none';
      }
    });
  };

  window.restoreRiddleSessionHint = (storageKey, anchor) => {
    if (!storageKey || !(anchor instanceof Element)) {
      return;
    }
    let message = '';
    try {
      message = window.sessionStorage.getItem(storageKey) || '';
    } catch (error) {
      message = '';
    }
    if (!message) {
      return;
    }
    window.showRiddleEphemeralNotice(message, {
      tone: 'hint',
      persistent: true,
      storageKey,
      anchor,
    });
  };

  window.showRiddleEphemeralNotice = (message, options = {}) => {
    const text = String(message || '').trim();
    if (!text) {
      return;
    }

    const tone = options.tone === 'hint' ? 'hint' : 'wrong';
    const persistent = tone === 'hint' ? options.persistent !== false : false;
    const duration = Number(options.duration) > 0 ? Number(options.duration) : 3800;
    const anchor = options.anchor instanceof Element ? options.anchor : null;
    const storageKey = typeof options.storageKey === 'string' ? options.storageKey : '';

    if (persistent) {
      document.querySelectorAll('.riddle-ephemeral-notice--hint').forEach((node) => {
        const sameKey = storageKey && node.dataset.storageKey === storageKey;
        const sameAnchor = anchor && anchor.contains(node);
        if (sameKey || sameAnchor || (!storageKey && !anchor)) {
          node.remove();
        }
      });
    } else {
      document.querySelectorAll('.riddle-ephemeral-notice--wrong').forEach((node) => node.remove());
      if (noticeTimer) {
        window.clearTimeout(noticeTimer);
        noticeTimer = null;
      }
    }

    const notice = document.createElement('div');
    notice.className = `riddle-ephemeral-notice riddle-ephemeral-notice--${tone}`;
    if (persistent) {
      notice.classList.add('riddle-session-hint');
      notice.setAttribute('role', 'status');
      notice.setAttribute('aria-live', 'polite');
    } else {
      notice.setAttribute('role', 'alert');
      notice.setAttribute('aria-live', 'assertive');
    }
    if (storageKey) {
      notice.dataset.storageKey = storageKey;
    }

    const eyebrow = document.createElement('span');
    eyebrow.className = 'riddle-ephemeral-notice__eyebrow';
    eyebrow.textContent =
      tone === 'hint'
        ? noticeConfig.hintEyebrow || 'Indice'
        : noticeConfig.wrongEyebrow || 'Accès refusé';

    const body = document.createElement('span');
    body.className = 'riddle-ephemeral-notice__message';
    appendNoticeMessage(body, text, persistent);

    notice.appendChild(eyebrow);
    notice.appendChild(body);
    mountNotice(notice, anchor);

    if (persistent && storageKey) {
      try {
        window.sessionStorage.setItem(storageKey, text);
      } catch (error) {
        // Ignore quota / private-mode failures.
      }
    }

    if (!persistent) {
      noticeTimer = window.setTimeout(() => {
        notice.classList.add('is-leaving');
        window.setTimeout(() => {
          const host = notice.parentElement;
          notice.remove();
          if (
            host?.classList?.contains('reponse-feedback') &&
            !host.querySelector('.riddle-ephemeral-notice')
          ) {
            host.style.display = 'none';
          }
        }, 280);
        noticeTimer = null;
      }, duration);
    }
  };
})();
