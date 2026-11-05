/**
 * Viewer d’images d’énigme : lecture verticale et loupe.
 * Couvre le hero (`[data-enigme-gallery]`). Les images d’étapes débloquées
 * s’ajoutent sous la bande (y compris via AJAX).
 *
 * La colonne fait au plus 1380 px et touche les bords sur un écran plus
 * étroit. Le défilement est celui de la page. La réponse, les indices et
 * le texte restent sous la dernière image. Le lien Accueil reste dans le
 * coin haut droit.
 *
 * Avec une souris, un clic arme une loupe ×4 qui suit le pointeur sur
 * l’image. Un second clic ou Échap l’éteint. Au toucher, un double tap ou
 * un pincement ouvre la lightbox en taille native.
 */
(function () {
  window.__enigmeViewerDispose?.();

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
  const showControlsTemplate =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.showControlsLabel) ||
    'Afficher les commandes, page %1$d / %2$d';
  const homeLabel =
    (window.EnigmeImageViewer && window.EnigmeImageViewer.homeLabel) || 'Accueil';
  const homeUrl = (window.EnigmeImageViewer && window.EnigmeImageViewer.homeUrl) || '/';

  const formatPageLabel = (current, total) =>
    pageLabelTemplate
      .replace('%1$d', String(current))
      .replace('%2$d', String(total))
      .replace('%d', String(current));

  const formatShowPageLabel = (pageNumber) =>
    showPageLabel.replace('%d', String(pageNumber));

  const formatShowControlsLabel = (current, total) =>
    showControlsTemplate
      .replace('%1$d', String(current))
      .replace('%2$d', String(total))
      .replace('%d', String(current));

  const paintPageCounter = (current, total) => {
    const counter = document.querySelector('.comic-page-counter');
    if (!counter) {
      return;
    }
    if (total < 2) {
      counter.hidden = true;
      counter.textContent = '';
      counter.removeAttribute('aria-label');
      return;
    }
    counter.hidden = false;
    counter.textContent = `${current} / ${total}`;
    counter.setAttribute('aria-label', formatShowControlsLabel(current, total));
  };

  const DOT_PAGE_LIMIT = 6;

  const pagerElement = (gallery) =>
    gallery.querySelector('.galerie-enigme__pager') ||
    document.querySelector('.galerie-enigme__pager');

  const comicTrackActive = (gallery) =>
    gallery.classList.contains('is-comic-active') ||
    (gallery.hasAttribute('data-comic-reader') &&
      document.body.classList.contains('comic-reader'));

  const clearPagerTrack = (gallery) => {
    const pager = pagerElement(gallery);
    if (!pager) {
      return;
    }
    pager.querySelector('.galerie-enigme__track')?.remove();
    pager.classList.remove('is-counter-only');
    delete pager.dataset.enigmeGalleryRef;
    const label = pager.querySelector('.galerie-enigme__page-label');
    if (label?.hasAttribute('aria-live')) {
      pager.setAttribute('aria-live', label.getAttribute('aria-live'));
      label.removeAttribute('aria-live');
    }
  };

  const syncPagerTrack = (gallery, activeIndex) => {
    const pager = pagerElement(gallery);
    const total = gallery.querySelectorAll('.galerie-enigme__slide').length;
    if (!pager || total < 2) {
      return;
    }
    if (gallery.id) {
      pager.dataset.enigmeGalleryRef = gallery.id;
    }

    let track = pager.querySelector('.galerie-enigme__track');
    if (!track) {
      track = document.createElement('div');
      track.className = 'galerie-enigme__track';
      const label = pager.querySelector('.galerie-enigme__page-label');
      if (pager.hasAttribute('aria-live') && label && !label.hasAttribute('aria-live')) {
        label.setAttribute('aria-live', pager.getAttribute('aria-live'));
        pager.removeAttribute('aria-live');
      }
      pager.insertBefore(track, label || null);
    }

    const ensureNav = (direction) => {
      const name = direction < 0 ? 'prev' : 'next';
      let button = track.querySelector(`.galerie-enigme__pager-nav--${name}`);
      if (!button) {
        button = document.createElement('button');
        button.type = 'button';
        button.className = `galerie-enigme__pager-nav galerie-enigme__pager-nav--${name}`;
        button.dataset.galleryStep = String(direction);
        button.setAttribute('aria-label', direction < 0 ? prevPageLabel : nextPageLabel);
        const glyph = document.createElement('span');
        glyph.setAttribute('aria-hidden', 'true');
        glyph.textContent = direction < 0 ? '\u2039' : '\u203a';
        button.appendChild(glyph);
        if (direction < 0) {
          track.prepend(button);
        } else {
          track.appendChild(button);
        }
      }
      button.disabled = direction < 0 ? activeIndex <= 0 : activeIndex >= total - 1;
      return button;
    };

    const prev = ensureNav(-1);
    const next = ensureNav(1);
    const showDots = total <= DOT_PAGE_LIMIT;
    pager.classList.toggle('is-counter-only', !showDots);

    const existingDots = track.querySelector('.galerie-enigme__dots');
    if (!showDots) {
      existingDots?.remove();
      return;
    }

    const dots = existingDots || document.createElement('div');
    if (!existingDots) {
      dots.className = 'galerie-enigme__dots';
      dots.setAttribute('role', 'tablist');
      dots.setAttribute('aria-label', pagesListLabel);
    }
    track.insertBefore(dots, next);
    if (track.firstElementChild !== prev) {
      track.prepend(prev);
    }
    if (dots.childElementCount !== total) {
      dots.replaceChildren();
      for (let index = 0; index < total; index += 1) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'galerie-enigme__dot';
        dot.setAttribute('role', 'tab');
        dot.dataset.galleryGoto = String(index);
        dot.setAttribute('aria-label', formatShowPageLabel(index + 1));
        dots.appendChild(dot);
      }
    }
    dots.querySelectorAll('.galerie-enigme__dot').forEach((dot) => {
      const isActive = Number(dot.dataset.galleryGoto) === activeIndex;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  };

  const galleryFor = (node) => {
    const host = node.closest?.('[data-enigme-gallery]');
    if (host) {
      return host;
    }
    const ref = node.closest?.('[data-enigme-gallery-ref]')?.getAttribute('data-enigme-gallery-ref');
    if (ref) {
      const match = document.getElementById(ref);
      if (match) {
        return match;
      }
    }
    return document.querySelector('[data-enigme-gallery]');
  };

  const updateGalleryChrome = (gallery, activeIndex) => {
    const slides = gallery.querySelectorAll('.galerie-enigme__slide');
    const total = slides.length;
    gallery.dataset.galleryPageCount = String(total);

    const pager = gallery.querySelector('.galerie-enigme__page-label')
      || document.querySelector('.galerie-enigme__page-label');
    if (pager && total > 0) {
      pager.textContent = formatPageLabel(activeIndex + 1, total);
    }
    if (total > 0) {
      paintPageCounter(activeIndex + 1, total);
    }

    const prev = gallery.querySelector('.galerie-enigme__nav--prev');
    const next = gallery.querySelector('.galerie-enigme__nav--next');
    if (prev) {
      prev.disabled = activeIndex <= 0;
    }
    if (next) {
      next.disabled = activeIndex >= total - 1;
    }

    if (comicTrackActive(gallery)) {
      syncPagerTrack(gallery, activeIndex);
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

  const PAGE_TURN_MS = 340;

  const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true;

  const clearPageTurn = (gallery) => {
    if (gallery._pageTurnTimer) {
      window.clearTimeout(gallery._pageTurnTimer);
      gallery._pageTurnTimer = null;
    }
    gallery.querySelectorAll('.galerie-enigme__slide').forEach((slide) => {
      slide.classList.remove('is-leaving', 'is-incoming', 'is-from-next', 'is-from-prev');
      if (!slide.classList.contains('is-active')) {
        slide.hidden = true;
      }
    });
  };

  const preloadNearbySlides = (gallery, index) => {
    const slides = gallery.querySelectorAll('.galerie-enigme__slide');
    [index - 1, index + 1].forEach((neighbor) => {
      const img = slides[neighbor]?.querySelector('img');
      if (img && img.loading === 'lazy') {
        img.loading = 'eager';
      }
    });
  };

  const scrollStripIntoView = (slide) => {
    if (!slide || typeof slide.scrollIntoView !== 'function') {
      return;
    }
    slide.scrollIntoView({
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
      block: 'start',
    });
  };

  const selectGallerySlide = (gallery, index, options = {}) => {
    const slides = [...gallery.querySelectorAll('.galerie-enigme__slide')];
    const thumbs = gallery.querySelectorAll('[data-gallery-goto]');
    if (!slides.length) {
      return -1;
    }

    const previousIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
    const target = Math.max(0, Math.min(index, slides.length - 1));

    slides.forEach((slide, slideIndex) => {
      const isActive = slideIndex === target;
      slide.classList.remove('is-leaving', 'is-incoming', 'is-from-next', 'is-from-prev');
      slide.classList.toggle('is-active', isActive);
      slide.hidden = false;
      slide.dataset.galleryIndex = String(slideIndex);
      const img = slide.querySelector('img');
      if (img) {
        img.classList.toggle('image-active', isActive);
        if (isActive) {
          img.id = 'image-enigme-active';
          if (img.loading === 'lazy') {
            img.loading = 'eager';
          }
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
    if (options.scroll !== false && previousIndex !== target) {
      scrollStripIntoView(slides[target]);
    }
    if (previousIndex !== target) {
      gallery.dispatchEvent(
        new CustomEvent('comic-page-change', {
          bubbles: true,
          detail: { index: target, source: options.source || '' },
        })
      );
    }
    return target;
  };

  const bandIntersectsViewport = (node) => {
    const rect = node.getBoundingClientRect();
    const viewport = window.innerHeight || document.documentElement?.clientHeight || 0;
    return rect.height > 0 && rect.bottom > 0 && rect.top < viewport;
  };

  const createWebtoonBandScheduler = (gallery) => {
    const priority = new Set();
    let released = false;

    const images = () => [...gallery.querySelectorAll('.galerie-enigme__slide img')];

    const settle = () => {
      if (released || priority.size === 0) {
        return;
      }
      const pending = [...priority].some((image) => image.dataset.bandState !== 'ready');
      if (pending) {
        return;
      }
      released = true;
      images().forEach((image) => startImage(image));
    };

    const startImage = (image) => {
      if (!(image instanceof HTMLImageElement)) {
        return;
      }
      if (image.dataset.bandState === 'loading' || image.dataset.bandState === 'ready') {
        return;
      }
      const finish = () => {
        image.dataset.bandState = 'ready';
        settle();
      };
      if (image.complete && image.getAttribute('src')) {
        image.dataset.bandState = 'ready';
        return;
      }
      image.dataset.bandState = 'loading';
      image.addEventListener('load', finish, { once: true });
      image.addEventListener('error', finish, { once: true });
      const next = image.dataset.bandSrc || '';
      if (next && image.getAttribute('src') !== next) {
        image.src = next;
      }
    };

    const sync = () => {
      if (released) {
        images().forEach((image) => startImage(image));
        return;
      }
      const list = images();
      let lastVisible = -1;
      list.forEach((image, index) => {
        if (bandIntersectsViewport(image)) {
          lastVisible = index;
        }
      });
      const unmeasured = list.every((image) => image.getBoundingClientRect().height === 0);
      if (lastVisible < 0 && unmeasured && list[0]) {
        lastVisible = 0;
      }
      for (let index = 0; index <= lastVisible; index += 1) {
        priority.add(list[index]);
        startImage(list[index]);
      }
      settle();
    };

    const onScroll = () => sync();
    window.addEventListener('scroll', onScroll, { passive: true });
    sync();

    return { sync, stop: () => window.removeEventListener('scroll', onScroll) };
  };

  const ensureWebtoonBandScheduler = (gallery) => {
    if (!(gallery instanceof Element)) {
      return;
    }
    if (!gallery.__webtoonBandSync) {
      const scheduler = createWebtoonBandScheduler(gallery);
      gallery.__webtoonBandSync = scheduler.sync;
      gallery.__webtoonBandStop = scheduler.stop;
      return;
    }
    gallery.__webtoonBandSync();
  };

  const appendSlideVisual = (zoom, page, alt) => {
    const bands = Array.isArray(page.bands) ? page.bands : [];
    if (bands.length > 1) {
      const stack = document.createElement('span');
      stack.className = 'webtoon-bands';
      bands.forEach((band, index) => {
        const image = document.createElement('img');
        image.className = 'webtoon-band enigme-image--limited';
        image.dataset.webtoonBand = '';
        image.dataset.bandSrc = band.url || band.src || '';
        image.alt = index === 0 ? alt : '';
        image.decoding = 'async';
        image.referrerPolicy = 'no-referrer';
        if (page.sizes) {
          image.sizes = page.sizes;
        }
        const width = Number(band.width || 0);
        const height = Number(band.height || 0);
        if (width > 0) {
          image.width = width;
        }
        if (height > 0) {
          image.height = height;
        }
        stack.appendChild(image);
      });
      zoom.appendChild(stack);
      return;
    }

    const img = document.createElement('img');
    img.className = 'enigme-image--limited';
    img.src = page.previewUrl;
    img.alt = alt;
    img.loading = 'lazy';
    img.decoding = 'async';
    img.referrerPolicy = 'no-referrer';
    if (page.srcset) {
      img.srcset = page.srcset;
    }
    if (page.sizes) {
      img.sizes = page.sizes;
    }
    if (page.width) {
      img.width = Number(page.width);
    }
    if (page.height) {
      img.height = Number(page.height);
    }

    const picture = document.createElement('picture');
    if (page.webpSrcset) {
      const source = document.createElement('source');
      source.type = 'image/webp';
      source.srcset = page.webpSrcset;
      if (page.sizes) {
        source.sizes = page.sizes;
      }
      picture.appendChild(source);
    }
    picture.appendChild(img);
    zoom.appendChild(picture);
  };

  const appendGalleryPage = (page) => {
    const gallery = document.querySelector('[data-enigme-gallery]');
    if (!gallery || !page || !page.previewUrl || !page.fullUrl) {
      return -1;
    }

    const stepId = page.stepId ? String(page.stepId).replace(/[^\d]/g, '') : '';
    const imageId = page.imageId ? String(page.imageId).replace(/[^\d]/g, '') : '';
    const selectPage = page.select !== false;
    if (imageId) {
      const existing = gallery.querySelector(
        `.galerie-enigme__slide[data-gallery-image-id="${imageId}"]`
      );
      if (existing) {
        const index = [...gallery.querySelectorAll('.galerie-enigme__slide')].indexOf(existing);
        if (!selectPage) {
          return index;
        }
        return index >= 0 ? selectGallerySlide(gallery, index) : -1;
      }
    }

    const stage = gallery.querySelector('.galerie-enigme__stage');
    const thumbs = gallery.querySelector('.galerie-enigme__thumbs');
    const nextNav = gallery.querySelector('.galerie-enigme__nav--next');
    const homeLink = stage?.querySelector('.galerie-enigme__home') || null;
    const index = gallery.querySelectorAll('.galerie-enigme__slide').length;
    const galleryId = gallery.id || 'galerie-enigme';
    const slideId = `${galleryId}-slide-${index}`;
    const alt = page.alt || '';

    const figure = document.createElement('figure');
    figure.className =
      'image-principale galerie-enigme__slide galerie-enigme__slide--step';
    figure.id = slideId;
    figure.dataset.galleryIndex = String(index);
    figure.dataset.galleryImageId = imageId;
    if (stepId) {
      figure.dataset.galleryStepId = stepId;
    }

    const zoom = document.createElement('button');
    zoom.type = 'button';
    zoom.className = 'enigme-media-zoom';
    zoom.dataset.enigmeLightboxSrc = page.fullUrl;
    zoom.dataset.enigmeLightboxAlt = alt;
    zoom.setAttribute('aria-label', nativeSizeLabel);
    appendSlideVisual(zoom, page, alt);

    const hint = document.createElement('span');
    hint.className = 'enigme-media-zoom__hint';
    hint.setAttribute('aria-hidden', 'true');
    hint.textContent =
      (window.EnigmeImageViewer && window.EnigmeImageViewer.zoomHint) || 'Agrandir';

    zoom.appendChild(hint);
    figure.appendChild(zoom);
    if (nextNav) {
      stage.insertBefore(figure, nextNav);
    } else if (homeLink) {
      stage.insertBefore(figure, homeLink);
    } else if (stage) {
      stage.appendChild(figure);
    }

    if (thumbs) {
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
        thumbImg.decoding = 'async';
        thumbImg.referrerPolicy = 'no-referrer';
        thumbImg.width = 72;
        thumbImg.height = 72;
        thumb.appendChild(thumbImg);
      }
      thumbs.appendChild(thumb);
    }

    if (gallery.hasAttribute('data-webtoon') || gallery.dataset.webtoonReady === '1') {
      ensureWebtoonBandScheduler(gallery);
    }

    if (!selectPage) {
      return index;
    }
    return selectGallerySlide(gallery, index);
  };

  const initWebtoonReader = (gallery) => {
    if (!(gallery instanceof Element) || gallery.dataset.webtoonReady === '1') {
      return;
    }
    gallery.dataset.webtoonReady = '1';
    window.__comicReaderTeardown?.();

    const stage = gallery.querySelector('.galerie-enigme__stage');
    if (!stage) {
      return;
    }

    gallery.querySelectorAll('.galerie-enigme__slide').forEach((slide) => {
      slide.hidden = false;
    });

    const HOME_INSET = 12;
    const ensureHomeLink = () => {
      let link = gallery.querySelector('.galerie-enigme__home');
      if (!(link instanceof HTMLAnchorElement)) {
        link = document.createElement('a');
        link.className = 'galerie-enigme__home';
        link.href = homeUrl;
        const icon = document.createElement('span');
        icon.className = 'galerie-enigme__home-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '\u2190';
        const label = document.createElement('span');
        label.className = 'galerie-enigme__home-label';
        label.textContent = homeLabel;
        link.append(icon, label);
        stage.appendChild(link);
      }
      return link;
    };

    const homeLink = ensureHomeLink();

    const topEdgeOffset = () => {
      if (!document.body.classList.contains('admin-bar')) {
        return 0;
      }
      return window.matchMedia?.('(max-width: 782px)')?.matches ? 46 : 32;
    };

    const placeHomeLink = () => {
      const top = topEdgeOffset() + HOME_INSET;
      homeLink.style.setProperty('--galerie-home-top', `${top}px`);
      homeLink.style.setProperty('--galerie-home-right', `${HOME_INSET}px`);
      const edit = document.querySelector('.enigme-edit-toggle--desktop');
      if (edit instanceof HTMLElement) {
        edit.style.setProperty('--galerie-home-top', `${top}px`);
      }
    };

    placeHomeLink();

    let lastTapAt = 0;
    let lastTapX = 0;
    let lastTapY = 0;
    let pinchBase = 0;
    let pinchOpened = false;
    const pointers = new Map();

    const openFromEvent = (event) => {
      const zoom = event.target?.closest?.('[data-enigme-lightbox-src]');
      if (!zoom || !stage.contains(zoom)) {
        return;
      }
      openLightbox(
        zoom.getAttribute('data-enigme-lightbox-src'),
        zoom.getAttribute('data-enigme-lightbox-alt') || '',
        zoom
      );
    };

    const onPointerDown = (event) => {
      if (event.pointerType === 'mouse') {
        return;
      }
      pointers.set(event.pointerId, {
        x: event.clientX,
        y: event.clientY,
      });
      if (pointers.size === 2) {
        const [first, second] = [...pointers.values()];
        pinchBase = Math.hypot(first.x - second.x, first.y - second.y) || 1;
        pinchOpened = false;
      }
    };

    const onPointerMove = (event) => {
      const point = pointers.get(event.pointerId);
      if (!point) {
        return;
      }
      point.x = event.clientX;
      point.y = event.clientY;
      if (pointers.size < 2 || pinchOpened || pinchBase <= 0) {
        return;
      }
      const [first, second] = [...pointers.values()];
      const distance = Math.hypot(first.x - second.x, first.y - second.y);
      if (distance / pinchBase >= 1.18) {
        pinchOpened = true;
        openFromEvent(event);
      }
    };

    const onPointerUp = (event) => {
      if (event.pointerType === 'mouse') {
        return;
      }
      const wasPinch = pointers.size >= 2 || pinchOpened;
      pointers.delete(event.pointerId);
      if (wasPinch) {
        if (pointers.size === 0) {
          pinchBase = 0;
          pinchOpened = false;
        }
        return;
      }
      const now = Date.now();
      const near = Math.hypot(event.clientX - lastTapX, event.clientY - lastTapY) < 28;
      if (lastTapAt && now - lastTapAt < 320 && near) {
        lastTapAt = 0;
        openFromEvent(event);
        return;
      }
      lastTapAt = now;
      lastTapX = event.clientX;
      lastTapY = event.clientY;
    };

    const onResize = () => placeHomeLink();
    stage.addEventListener('pointerdown', onPointerDown);
    stage.addEventListener('pointermove', onPointerMove);
    stage.addEventListener('pointerup', onPointerUp);
    stage.addEventListener('pointercancel', onPointerUp);
    window.addEventListener('resize', onResize);
    ensureWebtoonBandScheduler(gallery);

    window.__comicReaderTeardown = () => {
      gallery.__webtoonBandStop?.();
      delete gallery.__webtoonBandSync;
      delete gallery.__webtoonBandStop;
      stage.removeEventListener('pointerdown', onPointerDown);
      stage.removeEventListener('pointermove', onPointerMove);
      stage.removeEventListener('pointerup', onPointerUp);
      stage.removeEventListener('pointercancel', onPointerUp);
      window.removeEventListener('resize', onResize);
      homeLink.style.removeProperty('--galerie-home-top');
      homeLink.style.removeProperty('--galerie-home-right');
      document.querySelector('.enigme-edit-toggle--desktop')
        ?.style.removeProperty('--galerie-home-top');
      delete gallery.dataset.webtoonReady;
      document.body.classList.remove('comic-reader', 'is-hud-visible', 'is-hud-quiet');
    };
  };

  window.EnigmeGallery = {
    selectSlide: (gallery, index, options) => selectGallerySlide(gallery, index, options),
    appendPage: appendGalleryPage,
    getGallery: () => document.querySelector('[data-enigme-gallery]'),
    initReader: initWebtoonReader,
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
    image.referrerPolicy = 'no-referrer';

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

  const onViewerClick = (event) => {
    if (event.target.closest?.('.galerie-enigme__home')) {
      return;
    }

    const stepButton = event.target.closest('[data-gallery-step]');
    if (stepButton) {
      const gallery = galleryFor(stepButton);
      if (!gallery) {
        return;
      }
      event.preventDefault();
      const current = [...gallery.querySelectorAll('.galerie-enigme__slide')].findIndex(
        (slide) => slide.classList.contains('is-active')
      );
      const edgeNav = stepButton.classList.contains('galerie-enigme__nav');
      selectGallerySlide(gallery, current + Number(stepButton.dataset.galleryStep || 0), {
        source: edgeNav ? 'edge' : '',
      });
      return;
    }

    const thumb = event.target.closest('[data-gallery-goto]');
    if (thumb) {
      const gallery = galleryFor(thumb);
      if (!gallery) {
        return;
      }
      event.preventDefault();
      selectGallerySlide(gallery, Number(thumb.dataset.galleryGoto));
      return;
    }

    const zoomTrigger = event.target.closest?.('[data-enigme-lightbox-src]');
    if (!zoomTrigger) {
      if (mouseDriven() && event.target.closest?.('.galerie-enigme__stage')) {
        event.preventDefault();
        toggleLens(event);
      }
      return;
    }

    if (zoomTrigger.closest('[data-enigme-gallery]') || mouseDriven()) {
      event.preventDefault();
      if (mouseDriven()) {
        toggleLens(event);
      }
      return;
    }

    event.preventDefault();
    openLightbox(
      zoomTrigger.getAttribute('data-enigme-lightbox-src'),
      zoomTrigger.getAttribute('data-enigme-lightbox-alt') || '',
      zoomTrigger
    );
  };
  const editionActive = () =>
    document.body.classList.contains('edition-active-enigme') ||
    document.body.classList.contains('edition-active');

  const finePointerQuery = () =>
    window.matchMedia?.('(hover: hover) and (pointer: fine)') || { matches: false };

  let lastPointerType = '';
  const mouseDriven = () =>
    lastPointerType === 'mouse' ||
    (lastPointerType !== 'touch' && finePointerQuery().matches);
  let toggleLens = () => {};
  let disarmLens = () => {};

  document.addEventListener('click', onViewerClick);

  const onViewerKeydown = (event) => {
    if (event.key === 'Escape') {
      if (document.querySelector('form.is-reply-widget.is-immersive-open')) {
        return;
      }
      if (document.querySelector('.enigme-lightbox-overlay')) {
        event.preventDefault();
        closeLightbox();
        return;
      }
      disarmLens();
      return;
    }

  };
  document.addEventListener('keydown', onViewerKeydown, true);

  document.querySelectorAll('[data-enigme-gallery]').forEach((gallery) => {
    gallery.querySelectorAll('.galerie-enigme__slide').forEach((slide) => {
      slide.hidden = false;
    });
    const active = [...gallery.querySelectorAll('.galerie-enigme__slide')].findIndex(
      (slide) => slide.classList.contains('is-active')
    );
    updateGalleryChrome(gallery, active >= 0 ? active : 0);
    initWebtoonReader(gallery);
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

  const LOUPE_SIZE = 240;
  const LOUPE_SCALE = 4;
  const LOUPE_DRAG_PX = 12;

  const axisFraction = (token) => {
    const value = token || '50%';
    if (value.endsWith('%')) {
      const fraction = parseFloat(value) / 100;
      return Number.isFinite(fraction) ? Math.min(1, Math.max(0, fraction)) : 0.5;
    }
    if (value === 'left' || value === 'top') {
      return 0;
    }
    if (value === 'right' || value === 'bottom') {
      return 1;
    }
    return 0.5;
  };

  const contentRect = (img) => {
    const bounds = img.getBoundingClientRect();
    const frame = {
      left: bounds.left,
      top: bounds.top,
      width: bounds.width,
      height: bounds.height,
    };
    if (!frame.width || !frame.height) {
      return null;
    }
    const style = window.getComputedStyle(img);
    const fit = style.objectFit;
    const naturalW = img.naturalWidth;
    const naturalH = img.naturalHeight;
    if ((fit !== 'contain' && fit !== 'cover') || !naturalW || !naturalH) {
      return frame;
    }
    const parts = (style.objectPosition || '50% 50%').trim().split(/\s+/);
    const fractionX = axisFraction(parts[0]);
    const fractionY = axisFraction(parts[1]);
    const elementRatio = frame.width / frame.height;
    const imageRatio = naturalW / naturalH;
    const wider = imageRatio > elementRatio;
    let width = frame.width;
    let height = frame.height;
    if (fit === 'contain') {
      if (wider) {
        height = frame.width / imageRatio;
      } else {
        width = frame.height * imageRatio;
      }
    } else if (wider) {
      width = frame.height * imageRatio;
    } else {
      height = frame.width / imageRatio;
    }
    return {
      left: frame.left + (frame.width - width) * fractionX,
      top: frame.top + (frame.height - height) * fractionY,
      width,
      height,
    };
  };

  const containsPoint = (rect, x, y) =>
    x >= rect.left &&
    y >= rect.top &&
    x <= rect.left + rect.width &&
    y <= rect.top + rect.height;

  const initPointerLens = () => {
    const lens = document.createElement('div');
    lens.className = 'enigme-loupe';
    lens.hidden = true;
    lens.setAttribute('aria-hidden', 'true');

    const image = document.createElement('img');
    image.className = 'enigme-loupe__image';
    image.alt = '';
    image.decoding = 'async';
    image.draggable = false;
    image.referrerPolicy = 'no-referrer';

    const cross = document.createElement('span');
    cross.className = 'enigme-loupe__cross';
    cross.setAttribute('aria-hidden', 'true');

    lens.append(image, cross);
    document.body.appendChild(lens);

    let activeTrigger = null;
    let press = null;
    let armed = false;
    let dragClick = false;
    let pressedZoom = null;

    const zoomTriggerFrom = (event) =>
      eventElement(event)?.closest?.('[data-enigme-lightbox-src]') ||
      (event.type === 'click' ? pressedZoom : null);

    const clearTarget = () => {
      activeTrigger?.classList.remove('is-loupe-target');
      activeTrigger = null;
    };

    const conceal = () => {
      lens.hidden = true;
      clearTarget();
    };

    const disarm = () => {
      armed = false;
      conceal();
    };

    const eventElement = (event) => {
      if (event.target instanceof Element) {
        return event.target;
      }
      return event.target?.parentElement || null;
    };

    const mouseEvent = (event) =>
      event.pointerType === 'mouse' ||
      (event.pointerType !== 'touch' && mouseDriven());

    const place = (event) => {
      if (editionActive()) {
        disarm();
        return;
      }
      if (!armed || !mouseEvent(event)) {
        return;
      }
      if (press?.dragged) {
        conceal();
        return;
      }
      const trigger = zoomTriggerFrom(event);
      const source = trigger?.getAttribute('data-enigme-lightbox-src') || '';
      const picture = trigger?.querySelector('img') || null;
      if (!trigger || !source || !picture) {
        conceal();
        return;
      }
      const rect = contentRect(picture);
      if (!rect || !containsPoint(rect, event.clientX, event.clientY)) {
        conceal();
        return;
      }
      if (image.getAttribute('src') !== source) {
        image.src = source;
      }
      const ratioX = (event.clientX - rect.left) / rect.width;
      const ratioY = (event.clientY - rect.top) / rect.height;
      const zoomedWidth = rect.width * LOUPE_SCALE;
      const zoomedHeight = rect.height * LOUPE_SCALE;
      lens.style.left = `${event.clientX - LOUPE_SIZE / 2}px`;
      lens.style.top = `${event.clientY - LOUPE_SIZE / 2}px`;
      image.style.width = `${zoomedWidth}px`;
      image.style.height = `${zoomedHeight}px`;
      image.style.left = `${LOUPE_SIZE / 2 - ratioX * zoomedWidth}px`;
      image.style.top = `${LOUPE_SIZE / 2 - ratioY * zoomedHeight}px`;
      if (activeTrigger !== trigger) {
        clearTarget();
        activeTrigger = trigger;
        trigger.classList.add('is-loupe-target');
      }
      lens.hidden = false;
    };

    toggleLens = (event) => {
      if (dragClick) {
        dragClick = false;
        return;
      }
      if (editionActive() || !mouseEvent(event)) {
        return;
      }
      const trigger = zoomTriggerFrom(event);
      if (!trigger) {
        pressedZoom = null;
        return;
      }
      if (armed) {
        pressedZoom = null;
        disarm();
        return;
      }
      const picture = trigger.querySelector('img');
      const rect = picture ? contentRect(picture) : null;
      if (!rect || !containsPoint(rect, event.clientX, event.clientY)) {
        pressedZoom = null;
        return;
      }
      armed = true;
      place(event);
      pressedZoom = null;
    };
    disarmLens = disarm;

    const onPointerDown = (event) => {
      lastPointerType = event.pointerType || lastPointerType;
      dragClick = false;
      pressedZoom = event.pointerType === 'mouse'
        ? eventElement(event)?.closest?.('[data-enigme-lightbox-src]') || null
        : null;
      if (event.pointerType !== 'mouse' || event.button !== 0) {
        return;
      }
      press = {
        id: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        dragged: false,
      };
    };

    const onPointerMove = (event) => {
      if (press && press.id === event.pointerId) {
        const distance = Math.hypot(event.clientX - press.x, event.clientY - press.y);
        if (distance > LOUPE_DRAG_PX) {
          press.dragged = true;
        }
      }
      place(event);
    };

    const onPointerUp = (event) => {
      if (!press || press.id !== event.pointerId) {
        return;
      }
      const wasDrag = press.dragged;
      press = null;
      dragClick = wasDrag;
      if (wasDrag) {
        place(event);
      }
    };

    const onPointerLeave = (event) => {
      if (!event.relatedTarget) {
        press = null;
        conceal();
      }
    };

    const onEditionChange = () => {
      if (editionActive()) {
        press = null;
        disarm();
      }
    };

    const editionObserver = new MutationObserver(onEditionChange);
    editionObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', onPointerUp);
    document.addEventListener('pointercancel', onPointerUp);
    document.addEventListener('pointerleave', onPointerLeave);

    return () => {
      document.removeEventListener('pointerdown', onPointerDown);
      document.removeEventListener('pointermove', onPointerMove);
      document.removeEventListener('pointerup', onPointerUp);
      document.removeEventListener('pointercancel', onPointerUp);
      document.removeEventListener('pointerleave', onPointerLeave);
      editionObserver.disconnect();
      disarm();
      lens.remove();
    };
  };

  const teardownLens = initPointerLens();

  window.__enigmeViewerDispose = () => {
    document.removeEventListener('click', onViewerClick);
    document.removeEventListener('keydown', onViewerKeydown, true);
    teardownLens();
    window.__comicReaderTeardown?.();
    if (noticeTimer) {
      window.clearTimeout(noticeTimer);
      noticeTimer = null;
    }
  };
})();
