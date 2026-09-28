(function () {
  const SLIDER_SELECTOR = '[data-recommended-slider]';
  const TRACK_SELECTOR = '[data-recommended-slider-track]';
  const SLIDE_SELECTOR = '[data-recommended-slide]';
  const PREV_SELECTOR = '[data-recommended-slider-prev]';
  const NEXT_SELECTOR = '[data-recommended-slider-next]';

  function toElement(root) {
    if (root && typeof root.querySelectorAll === 'function') {
      return root;
    }
    return document;
  }

  function initSlider(slider) {
    if (!slider || slider.dataset.sliderReady === '1') {
      return;
    }

    const track = slider.querySelector(TRACK_SELECTOR);
    const viewport = slider.querySelector('[data-recommended-slider-viewport]');
    const slides = track ? Array.from(track.querySelectorAll(SLIDE_SELECTOR)) : [];

    if (!track || !slides.length || !viewport) {
      return;
    }

    const prev = slider.querySelector(PREV_SELECTOR);
    const next = slider.querySelector(NEXT_SELECTOR);
    const totalSlides = slides.length;
    let currentIndex = 0;
    let slideOffsets = [];
    let resizeObserver = null;
    let resizeHandler = null;
    let autoplayId = null;

    const label = slider.getAttribute('data-slider-label');
    if (label) {
      slider.setAttribute('aria-label', label);
    }

    slider.setAttribute('role', 'region');
    slider.setAttribute('aria-roledescription', 'carousel');
    slider.setAttribute('tabindex', '0');
    slider.setAttribute('data-slides-count', String(totalSlides));
    slider.style.setProperty('--recommended-slider-count', String(totalSlides));
    track.style.setProperty('--recommended-slider-count', String(totalSlides));

    slides.forEach((slide, index) => {
      slide.setAttribute('role', 'group');
      slide.setAttribute('aria-roledescription', 'slide');
      slide.setAttribute('aria-label', `${index + 1} / ${totalSlides}`);
    });

    function refreshOffsets() {
      const firstOffset = slides.length > 0 ? slides[0].offsetLeft : 0;
      slideOffsets = slides.map((slide) => slide.offsetLeft - firstOffset);
    }

    function applyTransform() {
      const offset = slideOffsets[currentIndex] || 0;
      track.style.transform = `translate3d(-${offset}px, 0, 0)`;
    }

    function update() {
      applyTransform();
      slides.forEach((slide, index) => {
        const isActive = index === currentIndex;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });

      if (prev) {
        prev.disabled = currentIndex === 0;
      }
      if (next) {
        next.disabled = currentIndex === totalSlides - 1;
      }
    }

    function goTo(index, options = {}) {
      const settings = Object.assign({ wrap: false }, options);

      if (totalSlides <= 1) {
        return false;
      }

      const maxIndex = totalSlides - 1;
      let targetIndex = index;

      if (settings.wrap) {
        targetIndex = ((index % totalSlides) + totalSlides) % totalSlides;
      } else {
        targetIndex = Math.max(0, Math.min(index, maxIndex));
      }

      if (targetIndex === currentIndex) {
        return false;
      }

      currentIndex = targetIndex;
      update();
      return true;
    }

    function stopAutoplay() {
      if (autoplayId) {
        window.clearInterval(autoplayId);
        autoplayId = null;
      }
    }

    function startAutoplay() {
      if (autoplayId || totalSlides <= 1) {
        return;
      }

      const delay = Number(slider.getAttribute('data-slider-autoplay-delay')) || 6000;
      autoplayId = window.setInterval(() => {
        const changed = goTo(currentIndex + 1, { wrap: true });
        if (!changed && totalSlides > 1) {
          currentIndex = 0;
          update();
        }
      }, Math.max(delay, 1000));
    }

    function restartAutoplay() {
      stopAutoplay();
      startAutoplay();
    }

    if (prev) {
      prev.addEventListener('click', () => {
        const moved = goTo(currentIndex - 1);
        if (moved) {
          restartAutoplay();
        }
      });
    }

    if (next) {
      next.addEventListener('click', () => {
        const moved = goTo(currentIndex + 1);
        if (moved) {
          restartAutoplay();
        }
      });
    }

    slider.addEventListener('keydown', (event) => {
      if (event.defaultPrevented) {
        return;
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        const moved = goTo(currentIndex - 1);
        if (moved) {
          restartAutoplay();
        }
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        const moved = goTo(currentIndex + 1);
        if (moved) {
          restartAutoplay();
        }
      }
    });

    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);
    slider.addEventListener('focusin', stopAutoplay);
    slider.addEventListener('focusout', (event) => {
      if (!slider.contains(event.relatedTarget)) {
        startAutoplay();
      }
    });

    const handleResize = () => {
      refreshOffsets();
      applyTransform();
    };

    const cleanup = () => {
      slider.removeEventListener('recommended-slider:destroy', cleanup);
      if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
      }
      if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler);
        resizeHandler = null;
      }
      stopAutoplay();
    };

    slider.addEventListener('recommended-slider:destroy', cleanup);

    refreshOffsets();
    update();
    startAutoplay();

    if (typeof ResizeObserver === 'function') {
      resizeObserver = new ResizeObserver(handleResize);
      resizeObserver.observe(viewport);
    } else {
      resizeHandler = handleResize;
      window.addEventListener('resize', resizeHandler);
    }

    if (typeof MutationObserver === 'function') {
      const observer = new MutationObserver(() => {
        if (!document.body.contains(slider)) {
          cleanup();
          observer.disconnect();
        }
      });

      observer.observe(document.body, { childList: true, subtree: true });
    }

    slider.dataset.sliderReady = '1';
  }

  function init(root) {
    const context = toElement(root);
    const sliders = context.querySelectorAll(SLIDER_SELECTOR);
    sliders.forEach(initSlider);
  }

  window.caRecommendedSlider = window.caRecommendedSlider || {};
  window.caRecommendedSlider.init = function initRecommendedSlider(root) {
    init(root);
  };
})();
