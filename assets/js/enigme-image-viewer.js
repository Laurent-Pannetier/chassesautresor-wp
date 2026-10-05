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

  window.showRiddleEphemeralNotice = (message, options = {}) => {
    const text = String(message || '').trim();
    if (!text) {
      return;
    }

    const tone = options.tone === 'hint' ? 'hint' : 'wrong';
    const duration = Number(options.duration) > 0 ? Number(options.duration) : 3800;
    const anchor = options.anchor instanceof Element ? options.anchor : null;

    document.querySelectorAll('.riddle-ephemeral-notice').forEach((node) => node.remove());
    if (noticeTimer) {
      window.clearTimeout(noticeTimer);
      noticeTimer = null;
    }

    const notice = document.createElement('div');
    notice.className = `riddle-ephemeral-notice riddle-ephemeral-notice--${tone}`;
    notice.setAttribute('role', 'alert');
    notice.setAttribute('aria-live', 'assertive');

    const eyebrow = document.createElement('span');
    eyebrow.className = 'riddle-ephemeral-notice__eyebrow';
    eyebrow.textContent =
      tone === 'hint'
        ? noticeConfig.hintEyebrow || 'Indice'
        : noticeConfig.wrongEyebrow || 'Accès refusé';

    const body = document.createElement('span');
    body.className = 'riddle-ephemeral-notice__message';
    body.textContent = text;

    notice.appendChild(eyebrow);
    notice.appendChild(body);

    if (anchor) {
      anchor.replaceChildren();
      if (anchor.classList?.contains('reponse-feedback')) {
        anchor.style.display = 'block';
      }
      anchor.appendChild(notice);
    } else {
      const host =
        document.querySelector('.riddle-player-step.is-current') ||
        document.querySelector('.participation .zone-reponse') ||
        document.querySelector('.participation');
      if (host) {
        host.appendChild(notice);
      } else {
        document.body.appendChild(notice);
      }
    }

    noticeTimer = window.setTimeout(() => {
      notice.classList.add('is-leaving');
      window.setTimeout(() => notice.remove(), 280);
      noticeTimer = null;
    }, duration);
  };
})();
