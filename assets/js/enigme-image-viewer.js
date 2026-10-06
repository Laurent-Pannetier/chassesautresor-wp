/**
 * Viewer d’images d’énigme : bascule par vignettes + lightbox au clic.
 * Couvre le hero (`[data-enigme-gallery]`) et les images d’étapes
 * (`[data-enigme-lightbox-src]`), y compris le HTML injecté en AJAX.
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

  const selectGallerySlide = (gallery, index) => {
    const slides = gallery.querySelectorAll('.galerie-enigme__slide');
    const thumbs = gallery.querySelectorAll('[data-gallery-goto]');
    if (!slides.length) {
      return;
    }

    const target = Math.max(0, Math.min(index, slides.length - 1));

    slides.forEach((slide, slideIndex) => {
      const isActive = slideIndex === target;
      slide.classList.toggle('is-active', isActive);
      slide.hidden = !isActive;
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
      closeLightbox();
    }
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
