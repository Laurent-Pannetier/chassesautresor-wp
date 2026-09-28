/**
 * Handle AJAX interactions for the Tentatives table search and pager.
 */
(function () {
  var config = window.caTentativesPager || {};
  var SEARCH_ACTION =
    (config && typeof config.action === 'string' && config.action) ||
    'ca_fetch_tentatives';
  var PAGE_PARAM = 'tentatives-page';
  var SEARCH_KEY =
    (config && typeof config.searchKey === 'string' && config.searchKey) ||
    'tentatives';
  var ERROR_MESSAGE =
    (config && typeof config.errorMessage === 'string' && config.errorMessage) ||
    'Unable to load attempts. Please try again.';
  var SUBMIT_STATE_KEY = 'tentativesSubmitSource';
  var RESET_STATE_KEY = 'tentativesResetSource';
  var LOADING_STATE_KEY = 'tentativesLoading';
  var BOUND_STATE_KEY = 'tentativesBound';
  var activeController = null;

  function getAjaxUrl() {
    if (config && typeof config.ajaxUrl === 'string' && config.ajaxUrl) {
      return config.ajaxUrl;
    }

    if (typeof window.ajaxurl === 'string' && window.ajaxurl) {
      return window.ajaxurl;
    }

    return '';
  }

  function matchesTentativesForm(form) {
    if (
      !form ||
      typeof form !== 'object' ||
      typeof form.classList === 'undefined' ||
      !form.classList.contains('table-search')
    ) {
      return false;
    }

    var dataset = form.dataset || {};

    if (dataset.ajaxAction === SEARCH_ACTION) {
      return true;
    }

    if (dataset.searchKey === SEARCH_KEY) {
      return true;
    }

    var searchParam = dataset.searchParameter || '';
    if (searchParam && searchParam.indexOf('[' + SEARCH_KEY + ']') !== -1) {
      return true;
    }

    var contextField = form.querySelector('input[name="search[context]"]');
    if (contextField && contextField.value === SEARCH_KEY) {
      return true;
    }

    return false;
  }

  function getSearchForm() {
    var selectors = [
      'form.table-search[data-ajax-action="' + SEARCH_ACTION + '"]',
      'form.table-search[data-search-key="' + SEARCH_KEY + '"]',
      'form.table-search[data-search-parameter="search[' + SEARCH_KEY + ']"]'
    ];

    for (var index = 0; index < selectors.length; index += 1) {
      var node = document.querySelector(selectors[index]);
      if (matchesTentativesForm(node)) {
        return node;
      }
    }

    var forms = document.querySelectorAll('form.table-search');
    for (var i = 0; i < forms.length; i += 1) {
      if (matchesTentativesForm(forms[i])) {
        return forms[i];
      }
    }

    return null;
  }

  function getSearchParameter(form) {
    if (!form) {
      return '';
    }

    if (form.dataset && form.dataset.searchParameter) {
      return form.dataset.searchParameter;
    }

    var field = getSearchField(form);
    if (field && field.name) {
      return field.name;
    }

    return '';
  }

  function getSearchKey(form) {
    if (!form) {
      return SEARCH_KEY;
    }

    if (form.dataset && form.dataset.searchKey) {
      return form.dataset.searchKey;
    }

    var contextField = form.querySelector('input[name="search[context]"]');
    if (contextField && contextField.value) {
      return contextField.value;
    }

    return SEARCH_KEY;
  }

  function getWrapper(form) {
    if (!form) {
      return null;
    }

    var target = form.dataset.ajaxTarget;
    if (target) {
      try {
        var node = document.querySelector(target);
        if (node) {
          return node;
        }
      } catch (error) {
        // Ignore invalid selector errors.
      }
    }

    var scope = form.closest('.myaccount-tentatives');
    if (scope) {
      var wrapper = scope.querySelector('.stats-table-wrapper');
      if (wrapper) {
        return wrapper;
      }
    }

    return null;
  }

  function getSearchField(form) {
    if (!form) {
      return null;
    }

    return form.querySelector('input[type="search"]');
  }

  function getSearchValue(form) {
    var field = getSearchField(form);
    if (!field) {
      return '';
    }

    return field.value.trim();
  }

  function toggleReset(form, visible) {
    if (!form) {
      return;
    }

    var button = form.querySelector('[data-table-search-reset]');
    if (!button) {
      return;
    }

    if (visible) {
      button.hidden = false;
      button.disabled = false;
    } else {
      button.hidden = true;
      button.disabled = true;
    }
  }

  function setState(form, key, value) {
    if (!form || !form.dataset) {
      return;
    }

    if (value === '' || value === null || typeof value === 'undefined') {
      delete form.dataset[key];
      return;
    }

    form.dataset[key] = value;
  }

  function getState(form, key) {
    if (!form || !form.dataset) {
      return '';
    }

    return form.dataset[key] || '';
  }

  function setFormLoading(form, loading) {
    if (!form || !form.dataset) {
      return;
    }

    if (loading) {
      form.dataset[LOADING_STATE_KEY] = '1';
    } else {
      delete form.dataset[LOADING_STATE_KEY];
    }
  }

  function setLoading(wrapper, loading) {
    if (!wrapper) {
      return;
    }

    if (loading) {
      wrapper.classList.add('is-loading');
      wrapper.setAttribute('aria-busy', 'true');
    } else {
      wrapper.classList.remove('is-loading');
      wrapper.removeAttribute('aria-busy');
    }
  }

  function getHiddenFields(form) {
    if (!form) {
      return [];
    }

    return form.querySelectorAll('input[type="hidden"][name]');
  }

  function buildStateUrl(form, term, page) {
    var url = new URL(window.location.href);
    var searchParam = getSearchParameter(form);
    var contextParam = 'search[context]';
    var hiddenFields = getHiddenFields(form);
    var contextValue = '';

    Array.prototype.forEach.call(hiddenFields, function (field) {
      if (field.name === contextParam) {
        contextValue = field.value || '';
      }
    });

    if (searchParam && term) {
      url.searchParams.set(searchParam, term);
      if (contextValue) {
        url.searchParams.set(contextParam, contextValue);
      } else if (form && form.dataset.searchKey) {
        url.searchParams.set(contextParam, form.dataset.searchKey);
      } else {
        url.searchParams.set(contextParam, getSearchKey(form));
      }
    } else {
      if (searchParam) {
        url.searchParams.delete(searchParam);
      }
      url.searchParams.delete(contextParam);
    }

    if (page > 1) {
      url.searchParams.set(PAGE_PARAM, String(page));
    } else {
      url.searchParams.delete(PAGE_PARAM);
    }

    Array.prototype.forEach.call(hiddenFields, function (field) {
      var name = field.name;
      if (!name || name === searchParam || name === contextParam) {
        return;
      }

      if (field.value === '') {
        url.searchParams.delete(name);
      } else {
        url.searchParams.set(name, field.value);
      }
    });

    return url;
  }

  function buildRequestBody(form, wrapper, page, term) {
    var data = new URLSearchParams();
    var action = form ? form.dataset.ajaxAction || SEARCH_ACTION : SEARCH_ACTION;
    data.set('action', action);
    data.set('page', String(page));

    var perPageAttr = wrapper ? wrapper.getAttribute('data-per-page') : null;
    var perPage = parseInt(perPageAttr || '10', 10);
    if (!perPage || perPage < 1) {
      perPage = 10;
    }
    data.set('per_page', String(perPage));

    var hiddenFields = getHiddenFields(form);
    Array.prototype.forEach.call(hiddenFields, function (field) {
      var name = field.name;
      if (!name) {
        return;
      }

      if (name === 'search[context]' || (form && name === getSearchParameter(form))) {
        return;
      }

      data.append(name, field.value);
    });

    var searchParam = getSearchParameter(form);
    if (searchParam) {
      data.set(searchParam, term);
    }

    var contextParam = 'search[context]';
    if (form) {
      var contextInput = form.querySelector('input[name="search[context]"]');
      if (contextInput && contextInput.value) {
        data.set(contextParam, contextInput.value);
      } else {
        data.set(contextParam, getSearchKey(form));
      }
    }

    return data;
  }

  function updatePager(wrapper, html) {
    if (!wrapper) {
      return;
    }

    var current = wrapper.querySelector('.tentatives-pager');
    if (typeof html === 'string' && html.trim() !== '') {
      var template = document.createElement('template');
      template.innerHTML = html.trim();
      var next = template.content.firstElementChild;

      if (next) {
        if (current && current.parentNode) {
          current.parentNode.replaceChild(next, current);
        } else {
          var table = wrapper.querySelector('table');
          if (table) {
            table.insertAdjacentElement('afterend', next);
          } else {
            wrapper.appendChild(next);
          }
        }
      }

      return;
    }

    if (current && current.parentNode) {
      current.parentNode.removeChild(current);
    }
  }

  function updateTable(wrapper, rowsHtml) {
    if (!wrapper) {
      return;
    }

    var tbody = wrapper.querySelector('tbody');
    if (tbody) {
      tbody.innerHTML = typeof rowsHtml === 'string' ? rowsHtml : '';
    }
  }

  function showError(wrapper, message) {
    if (!wrapper) {
      return;
    }

    var tbody = wrapper.querySelector('tbody');
    if (!tbody) {
      return;
    }

    var columns = 0;
    var headerRow = wrapper.querySelector('thead tr');
    if (headerRow && headerRow.children) {
      columns = headerRow.children.length;
    }

    if (!columns) {
      var firstRow = tbody.querySelector('tr');
      if (firstRow && firstRow.children) {
        columns = firstRow.children.length;
      }
    }

    if (!columns) {
      columns = 1;
    }

    var row = document.createElement('tr');
    row.className = 'tentatives-error';
    var cell = document.createElement('td');
    cell.colSpan = columns;
    cell.textContent = message;
    row.appendChild(cell);

    tbody.innerHTML = '';
    tbody.appendChild(row);
  }

  function updateHistory(form, wrapper, page, term) {
    if (!window.history || typeof window.history.replaceState !== 'function') {
      return;
    }

    var url = buildStateUrl(form, term, page);
    window.history.replaceState({}, '', url.toString());
  }

  function loadTentatives(form, options) {
    var wrapper = getWrapper(form);
    var ajaxUrl = getAjaxUrl();

    if (!form || !wrapper || !ajaxUrl) {
      return Promise.resolve();
    }

    if (activeController && typeof activeController.abort === 'function') {
      activeController.abort();
    }

    var controller = null;
    if (typeof window.AbortController === 'function') {
      controller = new AbortController();
      activeController = controller;
    } else {
      activeController = null;
    }

    var page = options && typeof options.page === 'number' ? options.page : 1;
    if (page < 1) {
      page = 1;
    }

    var term = '';
    if (options && Object.prototype.hasOwnProperty.call(options, 'term')) {
      term = String(options.term || '').trim();
    } else {
      term = getSearchValue(form);
    }

    var requestBody = buildRequestBody(form, wrapper, page, term);

    setLoading(wrapper, true);
    setFormLoading(form, true);

    var fetchOptions = {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: requestBody
    };

    if (controller) {
      fetchOptions.signal = controller.signal;
    }

    return fetch(ajaxUrl, fetchOptions)
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }

        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success) {
          var message = payload && payload.data && payload.data.message ? payload.data.message : 'Unknown error';
          throw new Error(message);
        }

        return payload.data || {};
      })
      .then(function (data) {
        updateTable(wrapper, data.rows || '');
        updatePager(wrapper, data.pager || '');
        wrapper.setAttribute('data-page', String(data.page || 1));
        toggleReset(form, term !== '');

        var field = getSearchField(form);
        if (field) {
          field.value = term;
        }

        updateHistory(form, wrapper, data.page || 1, term);
      })
      .catch(function (error) {
        if (controller && error && error.name === 'AbortError') {
          return;
        }

        console.error('Tentatives table update failed', error);
        showError(wrapper, ERROR_MESSAGE);
      })
      .finally(function () {
        setLoading(wrapper, false);
        setFormLoading(form, false);

        if (controller && activeController === controller) {
          activeController = null;
        }
      });
  }

  function isTentativesForm(form, detail) {
    if (matchesTentativesForm(form)) {
      return true;
    }

    if (detail && detail.searchKey && detail.searchKey === SEARCH_KEY) {
      return true;
    }

    return false;
  }

  function handleSearchSubmit(event) {
    var detail = event.detail;
    if (!detail || !detail.form) {
      return;
    }

    var form = detail.form;
    if (!isTentativesForm(form, detail)) {
      return;
    }

    event.preventDefault();

    if (getState(form, SUBMIT_STATE_KEY) === 'native') {
      return;
    }

    setState(form, SUBMIT_STATE_KEY, 'custom');

    loadTentatives(form, { page: 1, term: detail.term || '' }).finally(function () {
      if (getState(form, SUBMIT_STATE_KEY) === 'custom') {
        setState(form, SUBMIT_STATE_KEY, '');
      }
    });
  }

  function handleSearchReset(event) {
    var detail = event.detail;
    if (!detail || !detail.form) {
      return;
    }

    var form = detail.form;
    if (!isTentativesForm(form, detail)) {
      return;
    }

    event.preventDefault();

    if (getState(form, RESET_STATE_KEY) === 'native') {
      return;
    }

    setState(form, RESET_STATE_KEY, 'custom');

    var field = getSearchField(form);
    if (field) {
      field.value = '';
    }

    toggleReset(form, false);
    loadTentatives(form, { page: 1, term: '' }).finally(function () {
      if (getState(form, RESET_STATE_KEY) === 'custom') {
        setState(form, RESET_STATE_KEY, '');
      }
    });
  }

  function handlePagerChange(event) {
    var pager = event.target;
    if (!pager || !pager.classList || !pager.classList.contains('tentatives-pager')) {
      return;
    }

    var form = getSearchForm();
    if (!form) {
      return;
    }

    var page = event.detail && typeof event.detail.page === 'number' ? event.detail.page : 1;
    loadTentatives(form, { page: page });
  }

  function handleNativeSubmit(event) {
    var form = event.target;

    if (!isTentativesForm(form)) {
      return;
    }

    if (event.defaultPrevented) {
      return;
    }

    event.preventDefault();

    setState(form, SUBMIT_STATE_KEY, 'native');

    loadTentatives(form, { page: 1 }).finally(function () {
      if (getState(form, SUBMIT_STATE_KEY) === 'native') {
        setState(form, SUBMIT_STATE_KEY, '');
      }
    });
  }

  function handleNativeReset(event) {
    var trigger =
      event.target && typeof event.target.closest === 'function'
        ? event.target.closest('[data-table-search-reset]')
        : null;

    if (!trigger) {
      return;
    }

    var form = trigger.closest('form.table-search');
    if (!isTentativesForm(form)) {
      return;
    }

    if (event.defaultPrevented) {
      return;
    }

    event.preventDefault();

    setState(form, RESET_STATE_KEY, 'native');

    var field = getSearchField(form);
    if (field) {
      field.value = '';
    }

    toggleReset(form, false);
    loadTentatives(form, { page: 1, term: '' }).finally(function () {
      if (getState(form, RESET_STATE_KEY) === 'native') {
        setState(form, RESET_STATE_KEY, '');
      }
    });
  }

  function bindNativeEvents(form) {
    if (!form || !form.dataset) {
      return;
    }

    if (form.dataset[BOUND_STATE_KEY] === '1') {
      return;
    }

    form.addEventListener('submit', handleNativeSubmit);
    form.addEventListener('click', handleNativeReset);
    form.dataset[BOUND_STATE_KEY] = '1';
  }

  function initialise() {
    var form = getSearchForm();
    if (form) {
      toggleReset(form, getSearchValue(form) !== '');
      bindNativeEvents(form);
    }
  }

  document.addEventListener('tablesearch:submit', handleSearchSubmit);
  document.addEventListener('tablesearch:reset', handleSearchReset);
  document.addEventListener('pager:change', handlePagerChange);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise);
  } else {
    initialise();
  }
})();
