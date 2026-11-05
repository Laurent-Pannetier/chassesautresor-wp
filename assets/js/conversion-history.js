/* global ConversionHistoryAjax */
(function () {
  function toggleTable(button) {
    var container = button.closest('.conversion-history');
    var tableWrapper = container.querySelector('.conversion-history-table');
    var expanded = button.getAttribute('aria-expanded') === 'true';

    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    button.setAttribute(
      'aria-label',
      expanded ? button.getAttribute('data-label-open') : button.getAttribute('data-label-close')
    );

    var textSpan = button.querySelector('.conversion-history-toggle-text');
    if (textSpan) {
      textSpan.textContent = expanded ? button.getAttribute('data-label-open') : button.getAttribute('data-label-close');
    }

    tableWrapper.style.display = expanded ? 'none' : '';
  }

  function loadPage(container, page) {
    var loading = container.querySelector('.conversion-history-loading');
    var tableWrapper = container.querySelector('.conversion-history-table');
    if (loading) {
      loading.style.display = 'inline-block';
    }

    var formData = new FormData();
    formData.append('action', 'load_conversion_history');
    formData.append('nonce', ConversionHistoryAjax.nonce);
    formData.append('page', page);

    fetch(ConversionHistoryAjax.ajax_url, {
      method: 'POST',
      body: formData,
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {
        if (response.success) {
          tableWrapper.querySelector('tbody').innerHTML = response.data.rows;
        }
      })
      .finally(function () {
        if (loading) {
          loading.style.display = 'none';
        }
      });
  }

  document.addEventListener('click', function (e) {
    var target = e.target;
    if (target.nodeType !== Node.ELEMENT_NODE) {
      target = target.parentElement;
    }
    var button = target ? target.closest('.conversion-history-toggle') : null;
    if (!button) {
      return;
    }
    e.preventDefault();
    toggleTable(button);
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest(
      '.conversion-history .points-history-pager .pager-first, .conversion-history .points-history-pager .pager-prev, .conversion-history .points-history-pager .pager-next, .conversion-history .points-history-pager .pager-last'
    );
    if (!btn) {
      return;
    }
    e.preventDefault();
    var pager = btn.closest('.points-history-pager');
    var total = parseInt(pager.getAttribute('data-total') || '1', 10);
    var current = parseInt(pager.getAttribute('data-current') || '1', 10);
    if (btn.classList.contains('pager-first')) {
      current = 1;
    } else if (btn.classList.contains('pager-prev')) {
      if (current > 1) {
        current -= 1;
      }
    } else if (btn.classList.contains('pager-next')) {
      if (current < total) {
        current += 1;
      }
    } else if (btn.classList.contains('pager-last')) {
      current = total;
    }
    pager.setAttribute('data-current', String(current));
    var select = pager.querySelector('.pager-select');
    if (select) {
      select.value = String(current);
    }
    var container = pager.closest('.conversion-history');
    loadPage(container, current);
  });

  document.addEventListener('change', function (e) {
    var select = e.target.closest('.conversion-history .points-history-pager .pager-select');
    if (!select) {
      return;
    }
    var pager = select.closest('.points-history-pager');
    var page = parseInt(select.value, 10);
    pager.setAttribute('data-current', String(page));
    var container = pager.closest('.conversion-history');
    loadPage(container, page);
  });
})();
