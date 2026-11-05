(function () {
  const root = document.querySelector('[data-home-hunts]');

  if (!root || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
    return;
  }

  const grid = root.querySelector('.organisateur-chasses-grid');

  if (!grid) {
    return;
  }

  const form = root.querySelector('[data-home-hunts-filters]');

  if (!form) {
    return;
  }

  const toolbar = root.querySelector('[data-home-hunts-toolbar]');
  const toolbarToggles = toolbar ? Array.from(toolbar.querySelectorAll('[data-home-hunts-toggle]')) : [];
  const toolbarPanels = {
    filters: toolbar ? toolbar.querySelector('[data-home-hunts-panel="filters"]') : null,
    search: toolbar ? toolbar.querySelector('[data-home-hunts-panel="search"]') : null,
  };
  const desktopQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(min-width: 1024px)')
    : null;

  function isDesktopViewport() {
    return desktopQuery ? desktopQuery.matches : true;
  }

  let currentMobilePanel = null;

  function updateToolbarPanels(nextPanel) {
    if (!toolbar || toolbarToggles.length === 0) {
      return;
    }

    const normalized = nextPanel === 'filters' || nextPanel === 'search' ? nextPanel : null;
    const isDesktop = isDesktopViewport();

    toolbarToggles.forEach((button) => {
      const toggleName = button.dataset ? button.dataset.homeHuntsToggle : '';
      const shouldExpand = !isDesktop && toggleName === normalized;
      button.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
    });

    Object.keys(toolbarPanels).forEach((panelName) => {
      const panel = toolbarPanels[panelName];

      if (!panel) {
        return;
      }

      if (isDesktop) {
        panel.dataset.collapsed = 'false';
        panel.removeAttribute('aria-hidden');
        return;
      }

      const isActive = panelName === normalized;
      panel.dataset.collapsed = isActive ? 'false' : 'true';

      if (isActive) {
        panel.removeAttribute('aria-hidden');
      } else {
        panel.setAttribute('aria-hidden', 'true');
      }
    });

    if (!isDesktop) {
      currentMobilePanel = normalized;
    }
  }

  if (toolbar && toolbarToggles.length > 0) {
    toolbar.classList.add('home-hunts__toolbar--enhanced');
    updateToolbarPanels(currentMobilePanel);

    const handleViewportChange = () => {
      updateToolbarPanels(currentMobilePanel);
    };

    if (desktopQuery) {
      if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', handleViewportChange);
      } else if (typeof desktopQuery.addListener === 'function') {
        desktopQuery.addListener(handleViewportChange);
      }
    }

    toolbarToggles.forEach((button) => {
      button.addEventListener('click', () => {
        if (isDesktopViewport()) {
          return;
        }

        const targetPanel = button.dataset ? button.dataset.homeHuntsToggle : '';

        if (!targetPanel) {
          return;
        }

        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        updateToolbarPanels(isExpanded ? null : targetPanel);
      });
    });
  }

  const localized = window.homeHuntsFilters || {};
  const endpoint = typeof localized.ajaxUrl === 'string' ? localized.ajaxUrl : '';
  const labels = localized.labels && typeof localized.labels === 'object' ? localized.labels : {};
  const errorLabel = typeof labels.error === 'string' ? labels.error : '';
  const resetLabel = typeof labels.reset === 'string' ? labels.reset : '';
  const emptyLabel = typeof labels.empty === 'string' ? labels.empty : '';

  if (!endpoint) {
    return;
  }

  let nonce = typeof localized.nonce === 'string' ? localized.nonce : (root.dataset ? root.dataset.nonce : '');

  const countElement = form.querySelector('[data-home-hunts-count]') || root.querySelector('[data-home-hunts-count]');
  const feedbackElement = root.querySelector('[data-home-hunts-feedback]');
  const resetButton = form.querySelector('[data-home-hunts-reset]');
  const filtersWrapper = form.closest('.home-hunts__filters') || form;
  const searchForm = root.querySelector('form[data-home-hunts-search]')
    || (filtersWrapper ? filtersWrapper.querySelector('form[data-home-hunts-search]') : null);
  const searchInput = searchForm ? searchForm.querySelector('input[type="search"]') : null;
  const searchResetButton = searchForm ? searchForm.querySelector('[data-table-search-reset]') : null;

  if (countElement && !countElement.dataset.template) {
    countElement.dataset.template = countElement.textContent || '';
  }

  function normalizeCount(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return Math.max(0, Math.trunc(value));
    }

    if (typeof value === 'string') {
      const trimmed = value.trim();

      if (trimmed.length === 0) {
        return null;
      }

      const cleaned = trimmed.replace(/[^0-9]/gu, '');

      if (cleaned.length === 0) {
        return null;
      }

      const parsed = parseInt(cleaned, 10);

      if (Number.isFinite(parsed)) {
        return Math.max(0, parsed);
      }
    }

    return null;
  }

  let defaultCountValue = countElement ? normalizeCount(countElement.dataset.defaultCount || '') : null;
  const defaultTemplate = countElement ? countElement.dataset.template || '' : '';

  if (countElement && defaultCountValue === null) {
    const existingCount = normalizeCount(countElement.dataset.count || countElement.textContent || '');

    if (existingCount !== null) {
      defaultCountValue = existingCount;
      countElement.dataset.defaultCount = String(defaultCountValue);
    }
  }

  if (resetButton && resetLabel) {
    resetButton.setAttribute('aria-label', resetLabel);
  }

  const locale = typeof document.documentElement.lang === 'string'
    ? document.documentElement.lang.toLowerCase()
    : '';
  const numberFormatter = typeof window.Intl === 'object' && typeof window.Intl.NumberFormat === 'function'
    ? new Intl.NumberFormat(locale || undefined)
    : null;
  const isEnglishLocale = locale.startsWith('en');

  let currentController = null;
  let userModifiedCostFilters = false;

  function getLatestNonce() {
    if (root.dataset && typeof root.dataset.nonce === 'string' && root.dataset.nonce.length > 0) {
      nonce = root.dataset.nonce;
    }

    return nonce;
  }

  function formatNumber(value) {
    if (!numberFormatter) {
      return String(value);
    }

    try {
      return numberFormatter.format(value);
    } catch (error) {
      return String(value);
    }
  }

  function setLoading(isLoading) {
    if (root.classList) {
      root.classList.toggle('home-hunts--loading', Boolean(isLoading));
    }

    if (isLoading) {
      grid.setAttribute('aria-busy', 'true');
    } else {
      grid.removeAttribute('aria-busy');
    }

    if (resetButton) {
      resetButton.disabled = Boolean(isLoading);
      if (isLoading) {
        resetButton.setAttribute('aria-disabled', 'true');
      } else {
        resetButton.removeAttribute('aria-disabled');
      }
    }
  }

  function clearFeedback() {
    if (!feedbackElement) {
      return;
    }

    feedbackElement.textContent = '';
    feedbackElement.classList.remove('home-hunts__feedback--error');
  }

  function showError(message) {
    if (!feedbackElement) {
      return;
    }

    const finalMessage = message && message.length > 0 ? message : errorLabel;

    if (!finalMessage) {
      return;
    }

    feedbackElement.textContent = finalMessage;
    feedbackElement.classList.add('home-hunts__feedback--error');
  }

  function toggleSearchReset(visible) {
    if (!searchResetButton) {
      return;
    }

    if (visible) {
      searchResetButton.hidden = false;
      searchResetButton.disabled = false;
    } else {
      searchResetButton.hidden = true;
      searchResetButton.disabled = true;
    }
  }

  function getSearchTerm() {
    if (!searchInput) {
      return '';
    }

    return searchInput.value.trim();
  }

  function setSearchTerm(value) {
    if (!searchInput) {
      return;
    }

    const normalized = typeof value === 'string' ? value.trim() : '';

    if (searchInput.value !== normalized) {
      searchInput.value = normalized;
    }

    toggleSearchReset(normalized.length > 0);
  }

  function resetSearchTerm() {
    setSearchTerm('');
  }

  function showEmptyMessage(message) {
    if (!feedbackElement) {
      return;
    }

    const finalMessage = typeof message === 'string' && message.length > 0 ? message : emptyLabel;

    if (!finalMessage) {
      return;
    }

    feedbackElement.textContent = finalMessage;
    feedbackElement.classList.remove('home-hunts__feedback--error');
  }

  function formatCountLabel(countValue) {
    const template = defaultTemplate;

    if (template && /\d/u.test(template)) {
      return template.replace(/[\d\s\u00A0,.]+/u, formatNumber(countValue));
    }

    const pluralWord = isEnglishLocale ? 'results' : 'résultats';
    const singularWord = isEnglishLocale ? 'result' : 'résultat';

    return `${formatNumber(countValue)} ${countValue > 1 ? pluralWord : singularWord}`;
  }

  function setCountVisibility(isVisible) {
    if (!countElement) {
      return;
    }

    if (isVisible) {
      countElement.hidden = false;
      countElement.removeAttribute('aria-hidden');

      if (countElement.style) {
        if (typeof countElement.style.removeProperty === 'function') {
          countElement.style.removeProperty('display');
        } else {
          countElement.style.display = '';
        }
      }

      return;
    }

    countElement.hidden = true;
    countElement.setAttribute('aria-hidden', 'true');

    if (countElement.style) {
      countElement.style.display = 'none';
    }
  }

  function updateCount(total, allowFallback = true) {
    if (!countElement) {
      return;
    }

    let value = normalizeCount(total);

    if (null === value && allowFallback) {
      value = Number.isFinite(defaultCountValue) ? Math.max(0, Math.trunc(defaultCountValue)) : null;
    }

    if (null === value) {
      countElement.textContent = '';
      if (countElement.dataset) {
        delete countElement.dataset.count;
      }
      setCountVisibility(false);
      return;
    }

    countElement.textContent = formatCountLabel(value);
    countElement.dataset.count = String(value);
    setCountVisibility(true);
  }

  function clearHunts() {
    const toolbar = root.querySelector('.home-hunts__toolbar');
    const startNode = toolbar && grid.contains(toolbar) ? toolbar.nextSibling : grid.firstChild;
    let node = startNode;

    while (node) {
      if (node === feedbackElement) {
        break;
      }

      const next = node.nextSibling;

      if (node.parentNode) {
        node.parentNode.removeChild(node);
      }

      node = next;
    }
  }

  function insertHunts(html) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;

    const newGrid = wrapper.querySelector('.organisateur-chasses-grid');
    const source = newGrid || wrapper;
    const fragment = document.createDocumentFragment();

    source.childNodes.forEach((child) => {
      if (child.nodeType === Node.ELEMENT_NODE) {
        fragment.appendChild(child);
        return;
      }

      if (child.nodeType === Node.TEXT_NODE && child.textContent && child.textContent.trim().length > 0) {
        fragment.appendChild(child);
      }
    });

    const referenceNode = feedbackElement && grid.contains(feedbackElement) ? feedbackElement : null;

    if (referenceNode) {
      grid.insertBefore(fragment, referenceNode);
    } else {
      grid.appendChild(fragment);
    }
  }

  function replaceHunts(html) {
    clearHunts();
    insertHunts(html);
  }

  function getStatusControl() {
    return form.querySelector('[data-home-hunts-select]');
  }

  function getCostControls() {
    return Array.from(form.querySelectorAll('[data-home-hunts-checkbox]'));
  }

  const defaultCostSelectionValues = new Set(
    getCostControls()
      .filter((checkbox) =>
        checkbox
        && checkbox.dataset
        && checkbox.dataset.defaultChecked === 'true')
      .map((checkbox) => checkbox.value)
  );

  const defaultCostSelectionCoversAll = (() => {
    const availableValues = getCostControls()
      .filter((checkbox) => checkbox && checkbox.disabled !== true)
      .map((checkbox) => checkbox.value);

    if (availableValues.length === 0) {
      return true;
    }

    return availableValues.every((value) => defaultCostSelectionValues.has(value));
  })();

  if (!defaultCostSelectionCoversAll) {
    userModifiedCostFilters = true;
  }

  function updateStatusOptions(availableStatuses, normalizedStatus) {
    const select = getStatusControl();

    if (!select) {
      return;
    }

    const options = Array.from(select.options || []);
    const hasAvailableData = availableStatuses && typeof availableStatuses === 'object';
    const availableValues = new Set();

    if (hasAvailableData) {
      Object.keys(availableStatuses).forEach((value) => {
        const count = Number(availableStatuses[value]);
        if (Number.isFinite(count) && count > 0) {
          availableValues.add(String(value));
        }
      });
    }

    if (hasAvailableData) {
      options.forEach((option) => {
        const value = option.value;

        if (value === 'tous') {
          option.hidden = false;
          option.disabled = false;
          return;
        }

        const isAvailable = availableValues.has(value);
        option.hidden = !isAvailable;
        option.disabled = !isAvailable;
      });
    }

    let desiredValue = typeof normalizedStatus === 'string' ? normalizedStatus : select.value;
    const optionExists = options.some((option) => option.value === desiredValue);

    if (!optionExists) {
      desiredValue = 'tous';
    }

    if (hasAvailableData && desiredValue !== 'tous' && !availableValues.has(desiredValue)) {
      const fallbackOption = options.find((option) => option.value !== 'tous' && !option.hidden);
      desiredValue = fallbackOption ? fallbackOption.value : 'tous';
    }

    if (select.value !== desiredValue) {
      select.value = desiredValue;
    }
  }

  function updateCostOptions(availableCosts, normalizedCosts) {
    const checkboxes = getCostControls();

    if (checkboxes.length === 0) {
      return;
    }

    const hasAvailableData = availableCosts && typeof availableCosts === 'object';
    const normalizedValues = Array.isArray(normalizedCosts)
      ? new Set(normalizedCosts.map((value) => String(value)))
      : null;

    checkboxes.forEach((checkbox) => {
      const value = checkbox.value;
      const wrapper = checkbox.closest('.home-hunts__filters-checkbox');

      if (hasAvailableData) {
        const count = Number(availableCosts[value]);
        const isAvailable = Number.isFinite(count) && count > 0;

        if (wrapper) {
          wrapper.hidden = !isAvailable;
          if (isAvailable) {
            wrapper.removeAttribute('aria-hidden');
          } else {
            wrapper.setAttribute('aria-hidden', 'true');
          }
        } else if (!isAvailable) {
          checkbox.hidden = true;
        } else {
          checkbox.hidden = false;
        }

        checkbox.disabled = !isAvailable;

        if (!isAvailable) {
          checkbox.checked = false;
          return;
        }

        checkbox.hidden = false;
      }

      if (normalizedValues) {
        checkbox.checked = normalizedValues.has(value);
      }
    });
  }

  function updateAvailableFilters(filtersPayload) {
    if (!filtersPayload || typeof filtersPayload !== 'object') {
      return;
    }

    const availableFilters = filtersPayload.available && typeof filtersPayload.available === 'object'
      ? filtersPayload.available
      : {};
    const normalizedFilters = filtersPayload.normalized && typeof filtersPayload.normalized === 'object'
      ? filtersPayload.normalized
      : {};

    updateStatusOptions(availableFilters.statut || null, normalizedFilters.statut);
    updateCostOptions(availableFilters.cout || null, normalizedFilters.cout || null);
  }

  function buildFormData() {
    const data = new FormData();

    data.append('action', 'ca_filter_chasses');

    const currentNonce = getLatestNonce();

    if (currentNonce) {
      data.append('nonce', currentNonce);
    }

    const statusControl = getStatusControl();

    if (statusControl && typeof statusControl.value === 'string' && statusControl.value.length > 0) {
      data.append('status', statusControl.value);
    }

    const costControls = getCostControls();
    if (userModifiedCostFilters) {
      const selectedCosts = costControls
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value);

      if (selectedCosts.length > 0) {
        selectedCosts.forEach((value) => {
          data.append('cost[]', value);
        });
      } else {
        data.append('cost[]', '');
      }
    }

    data.append('search', getSearchTerm());

    return data;
  }

  function handleSuccess(payload) {
    if (!payload || typeof payload !== 'object') {
      throw new Error('Invalid response');
    }

    if (payload.filters && typeof payload.filters === 'object') {
      updateAvailableFilters(payload.filters);

      if (payload.filters.normalized && typeof payload.filters.normalized === 'object') {
        const normalizedSearch = payload.filters.normalized.search;

        if (typeof normalizedSearch === 'string') {
          setSearchTerm(normalizedSearch);
        }
      }
    }

    if (typeof payload.html === 'string') {
      replaceHunts(payload.html);
    } else {
      replaceHunts('');
    }

    const totalValue = normalizeCount(payload.total);

    if (totalValue !== null) {
      updateCount(totalValue);

      if (totalValue === 0) {
        showEmptyMessage(payload.message);
      }
    } else {
      updateCount(null);
    }

    if (typeof payload.nonce === 'string' && payload.nonce.length > 0) {
      nonce = payload.nonce;
      root.dataset.nonce = payload.nonce;
    }
  }

  function submitFilters() {
    if (currentController && typeof currentController.abort === 'function') {
      currentController.abort();
    }

    const controller = typeof window.AbortController === 'function' ? new AbortController() : null;
    currentController = controller;

    const options = {
      method: 'POST',
      credentials: 'same-origin',
      body: buildFormData(),
    };

    if (controller) {
      options.signal = controller.signal;
    }

    setLoading(true);
    clearFeedback();

    fetch(endpoint, options)
      .then((response) => {
        if (!response || !response.ok) {
          throw new Error('Network error');
        }

        return response.json();
      })
      .then((json) => {
        if (!json || typeof json !== 'object') {
          throw new Error('Invalid response');
        }

        if (json.success !== true) {
          const message = json.data && typeof json.data === 'object' && typeof json.data.message === 'string'
            ? json.data.message
            : errorLabel;

          throw new Error(message || 'Request failed');
        }

        handleSuccess(json.data);
      })
      .catch((error) => {
        if (error && error.name === 'AbortError') {
          return;
        }

        showError(error && error.message ? error.message : errorLabel);
        if (typeof console !== 'undefined' && console.error) {
          console.error('home-hunts-filters: unable to update hunts', error);
        }
      })
      .finally(() => {
        if (currentController === controller) {
          currentController = null;
          setLoading(false);
        }
      });
  }

  function shouldHandleInput(control, eventType) {
    if (eventType === 'change') {
      return true;
    }

    if (eventType === 'input' && control.tagName === 'INPUT') {
      return ['text', 'search', 'number', 'range', 'email', 'url', 'tel'].includes(control.type);
    }

    if (eventType === 'input' && control.tagName === 'TEXTAREA') {
      return true;
    }

    return false;
  }

  function onFiltersChange(event) {
    const target = event.target;

    if (!target || typeof target.closest !== 'function') {
      return;
    }

    const control = target.closest('[data-home-hunts-select], [data-home-hunts-checkbox]');

    if (!control || !form.contains(control)) {
      return;
    }

    if (!shouldHandleInput(control, event.type)) {
      return;
    }

    if (control.hasAttribute('data-home-hunts-checkbox')) {
      userModifiedCostFilters = true;
    }

    submitFilters();
  }

  function restoreDefaults() {
    if (typeof form.reset === 'function') {
      form.reset();
    }

    const statusControl = getStatusControl();

    if (statusControl && typeof statusControl.dataset.defaultValue === 'string') {
      statusControl.value = statusControl.dataset.defaultValue;
    }

    getCostControls().forEach((checkbox) => {
      if (typeof checkbox.dataset.defaultChecked === 'string') {
        checkbox.checked = checkbox.dataset.defaultChecked === 'true';
      }
    });

    userModifiedCostFilters = !defaultCostSelectionCoversAll;

    resetSearchTerm();
  }

  function onResetClick(event) {
    event.preventDefault();
    restoreDefaults();
    updateCount(null, false);
    clearFeedback();
    submitFilters();
  }

  function onSearchSubmit(event) {
    if (!searchForm || event.target !== searchForm) {
      return;
    }

    if (event && typeof event.preventDefault === 'function') {
      event.preventDefault();
    }

    const term = event.detail && typeof event.detail.term === 'string' ? event.detail.term : getSearchTerm();
    setSearchTerm(term);
    submitFilters();
  }

  function onSearchReset(event) {
    if (!searchForm || event.target !== searchForm) {
      return;
    }

    if (event && typeof event.preventDefault === 'function') {
      event.preventDefault();
    }

    resetSearchTerm();
    submitFilters();
  }

  form.addEventListener('change', onFiltersChange);
  form.addEventListener('input', onFiltersChange);

  if (resetButton) {
    resetButton.addEventListener('click', onResetClick);
  }

  if (searchForm) {
    searchForm.addEventListener('tablesearch:submit', onSearchSubmit);
    searchForm.addEventListener('tablesearch:reset', onSearchReset);
  }

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      toggleSearchReset(getSearchTerm() !== '');
    });
  }

  if (countElement) {
    updateCount(null, false);
  }

  toggleSearchReset(getSearchTerm() !== '');
})();
