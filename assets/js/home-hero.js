(function () {
  const onReady = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  };

  onReady(() => {
    const wrapper = document.querySelector('[data-home-hero-wrapper]');

    if (!wrapper) {
      return;
    }

    const initialHero = wrapper.querySelector('[data-home-hero="initial"]');
    const latestHero = wrapper.querySelector('[data-home-hero="latest"]');

    if (!initialHero || !latestHero) {
      return;
    }

    const prefersReducedMotion = typeof window.matchMedia === 'function'
      ? window.matchMedia('(prefers-reduced-motion: reduce)')
      : null;
    const delay = 5000;

    const updateWrapperHeight = () => {
      window.requestAnimationFrame(() => {
        const visibleHero = wrapper.querySelector('[data-home-hero][aria-hidden="false"]');

        if (!visibleHero) {
          return;
        }

        wrapper.style.height = `${visibleHero.offsetHeight}px`;
      });
    };

    initialHero.setAttribute('aria-hidden', 'false');
    latestHero.setAttribute('aria-hidden', 'true');

    updateWrapperHeight();

    const swapHeroes = () => {
      initialHero.classList.add('is-home-hero-hidden');
      initialHero.setAttribute('aria-hidden', 'true');

      latestHero.classList.add('is-home-hero-visible');
      latestHero.setAttribute('aria-hidden', 'false');

      updateWrapperHeight();
    };

    if (prefersReducedMotion && prefersReducedMotion.matches) {
      swapHeroes();
      return;
    }

    window.addEventListener('resize', updateWrapperHeight);
    window.addEventListener('load', updateWrapperHeight);

    window.setTimeout(swapHeroes, delay);
  });
})();
