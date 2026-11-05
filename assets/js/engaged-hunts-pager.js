/**
 * Handle AJAX pagination for engaged hunts on the dashboard.
 */
(function () {
  const SECTION_SELECTOR = '[data-engaged-hunts]';

  function updateUrl(param, page) {
    if (!window.history || !window.history.replaceState) {
      return;
    }

    try {
      const url = new URL(window.location.href);
      if (page > 1) {
        url.searchParams.set(param, String(page));
      } else {
        url.searchParams.delete(param);
      }
      window.history.replaceState({}, document.title, url.toString());
    } catch (error) {
      // Ignore URL parsing issues (e.g. unsupported browser).
    }
  }

  function showError(container, message) {
    if (!container) {
      return;
    }

    container.innerHTML = '';
    const paragraph = document.createElement('p');
    paragraph.className = 'myaccount-placeholder';
    paragraph.textContent = message;
    container.appendChild(paragraph);
  }

  document.addEventListener('pager:change', (event) => {
    const pager = event.target;
    if (!pager.classList || !pager.classList.contains('engaged-hunts-pager')) {
      return;
    }

    const section = pager.closest(SECTION_SELECTOR);
    if (!section) {
      return;
    }

    if (section.dataset.loading === '1') {
      return;
    }

    const detailPage = event.detail && typeof event.detail.page === 'number'
      ? event.detail.page
      : null;
    const fallbackPage = parseInt(pager.dataset.current || '1', 10) || 1;
    const page = detailPage && detailPage > 0 ? detailPage : fallbackPage;

    const endpoint = section.dataset.endpoint;
    const action = section.dataset.action;
    const nonce = section.dataset.nonce;
    const param = section.dataset.param || 'engaged-page';
    const errorMessage = section.dataset.error || '';
    const container = section.querySelector('[data-engaged-hunts-content]');

    if (!endpoint || !action || !nonce || !container) {
      return;
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('nonce', nonce);
    formData.append('page', String(page));

    section.dataset.loading = '1';
    section.classList.add('engaged-hunts-loading');
    container.setAttribute('aria-busy', 'true');

    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Network error');
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || payload.success !== true || !payload.data) {
          throw new Error('Invalid response');
        }

        const data = payload.data;

        if (typeof data.html === 'string') {
          container.innerHTML = data.html;
          if (window.caRecommendedSlider && typeof window.caRecommendedSlider.init === 'function') {
            window.caRecommendedSlider.init(container);
          }
        }

        const newPage = typeof data.page === 'number' && data.page > 0 ? data.page : page;
        const totalPages = typeof data.total_pages === 'number' && data.total_pages > 0
          ? data.total_pages
          : null;
        const totalItems = typeof data.total_items === 'number' && data.total_items >= 0
          ? data.total_items
          : null;

        section.dataset.currentPage = String(newPage);
        if (totalPages !== null) {
          section.dataset.totalPages = String(totalPages);
        }
        if (totalItems !== null) {
          section.dataset.totalCount = String(totalItems);
        }

        if (typeof data.nonce === 'string' && data.nonce.length > 0) {
          section.dataset.nonce = data.nonce;
        }

        updateUrl(param, newPage);
      })
      .catch((error) => {
        if (errorMessage) {
          showError(container, errorMessage);
        }
        // eslint-disable-next-line no-console
        console.error('Unable to load engaged hunts page', error);
      })
      .finally(() => {
        container.setAttribute('aria-busy', 'false');
        section.classList.remove('engaged-hunts-loading');
        delete section.dataset.loading;
      });
  });
})();
