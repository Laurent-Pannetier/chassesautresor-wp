(function () {
  function getResetParams(form) {
    var params = ['page', 'paged'];
    var dataset = form.getAttribute('data-reset-pagination');

    if (dataset) {
      dataset
        .split(',')
        .map(function (item) {
          return item.trim();
        })
        .forEach(function (item) {
          if (item && params.indexOf(item) === -1) {
            params.push(item);
          }
        });
    }

    return params;
  }

  function shouldResetParam(name, list) {
    if (list.indexOf(name) !== -1) {
      return true;
    }

    return /(?:^|[-_])page$/i.test(name);
  }

  function buildActionUrl(form, searchKey) {
    var action = form.getAttribute('action') || window.location.href;
    var url;

    try {
      url = new URL(action, window.location.href);
    } catch (error) {
      url = new URL(window.location.href);
    }

    var resetParams = getResetParams(form);

    Array.from(url.searchParams.keys()).forEach(function (name) {
      if (shouldResetParam(name, resetParams)) {
        url.searchParams.delete(name);
      }
    });

    if (searchKey) {
      url.searchParams.set('search[context]', searchKey);
    }

    return url;
  }

  function ensureContextField(form, searchKey) {
    var input = form.querySelector('input[name="search[context]"]');

    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'search[context]';
      form.appendChild(input);
    }

    if (searchKey && (!input.value || input.value !== searchKey)) {
      input.value = searchKey;
    }

    if (!form.dataset.searchKey && input.value) {
      form.dataset.searchKey = input.value;
    }

    return input.value;
  }

  function resetPaginationFields(form) {
    var resetParams = getResetParams(form);
    var fields = form.querySelectorAll('input[name], select[name]');

    Array.prototype.forEach.call(fields, function (field) {
      var name = field.getAttribute('name');

      if (!name || name === 'search[context]') {
        return;
      }

      if (!shouldResetParam(name, resetParams)) {
        return;
      }

      if (field.tagName === 'INPUT' && field.type === 'hidden') {
        field.parentNode.removeChild(field);
        return;
      }

      if (field.tagName === 'INPUT') {
        field.value = '';
        return;
      }

      if (field.tagName === 'SELECT') {
        field.selectedIndex = 0;
      }
    });
  }

  function getSearchField(form) {
    if (!(form instanceof HTMLFormElement)) {
      return null;
    }

    return form.querySelector('input[type="search"]');
  }

  function getSearchFieldName(form) {
    var field = getSearchField(form);

    if (!field || !field.name) {
      return '';
    }

    return field.name;
  }

  function buildResetUrl(form) {
    var url = buildActionUrl(form, '');
    var searchParam = getSearchFieldName(form);

    if (searchParam) {
      url.searchParams.delete(searchParam);
    }

    url.searchParams.delete('search[context]');

    var hiddenFields = form.querySelectorAll('input[type="hidden"][name]');

    Array.prototype.forEach.call(hiddenFields, function (field) {
      var name = field.getAttribute('name');

      if (!name || name === 'search[context]' || name === searchParam) {
        return;
      }

      var value = field.value;

      if (value === undefined) {
        return;
      }

      if (value === '') {
        url.searchParams.delete(name);
        return;
      }

      url.searchParams.set(name, value);
    });

    return url;
  }

  function getSearchTerm(form) {
    var field = form.querySelector('input[type="search"]');
    return field ? field.value.trim() : '';
  }

  function handleReset(event) {
    if (!event.target || typeof event.target.closest !== 'function') {
      return;
    }

    var trigger = event.target.closest('[data-table-search-reset]');

    if (!trigger) {
      return;
    }

    var form = trigger.closest('form.table-search');

    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    event.preventDefault();

    var field = getSearchField(form);

    if (field) {
      field.value = '';
    }

    resetPaginationFields(form);

    var url = buildResetUrl(form);
    var detail = {
      form: form,
      searchKey: form.dataset.searchKey || '',
      term: '',
      actionUrl: url,
      resetParams: getResetParams(form)
    };

    var customEvent = new CustomEvent('tablesearch:reset', {
      bubbles: true,
      cancelable: true,
      detail: detail
    });

    if (!form.dispatchEvent(customEvent)) {
      return;
    }

    window.location.href = url.toString();
  }

  function handleSubmit(event) {
    var form = event.target;

    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (!form.classList.contains('table-search')) {
      return;
    }

    var searchKey = form.dataset.searchKey || '';
    searchKey = ensureContextField(form, searchKey);

    var actionUrl = buildActionUrl(form, searchKey);
    form.setAttribute('action', actionUrl.toString());

    resetPaginationFields(form);

    var detail = {
      form: form,
      searchKey: searchKey,
      term: getSearchTerm(form),
      actionUrl: actionUrl,
      resetParams: getResetParams(form)
    };

    var customEvent = new CustomEvent('tablesearch:submit', {
      bubbles: true,
      cancelable: true,
      detail: detail
    });

    if (!form.dispatchEvent(customEvent)) {
      event.preventDefault();
    }
  }

  document.addEventListener('submit', function (event) {
    handleSubmit(event);
  });

  document.addEventListener('click', function (event) {
    handleReset(event);
  });

  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('form.table-search');

    Array.prototype.forEach.call(forms, function (form) {
      var searchKey = form.dataset.searchKey || '';
      ensureContextField(form, searchKey);
    });
  });
})();
