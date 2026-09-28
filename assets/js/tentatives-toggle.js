(function () {
  'use strict';

  function toggleExcerpt(button) {
    var cell = button.closest('.proposition-cell');
    if (!cell) {
      return;
    }

    var excerpt = cell.querySelector('.proposition-excerpt');
    var full = cell.querySelector('.proposition-full');
    var expanded = button.getAttribute('aria-expanded') === 'true';

    if (expanded) {
      if (full) {
        full.hidden = true;
      }
      if (excerpt) {
        excerpt.hidden = false;
      }
      cell.classList.remove('expanded');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', button.dataset.more || 'Voir plus');
      button.innerHTML = '<i class="fa-solid fa-ellipsis" aria-hidden="true"></i>';
    } else {
      if (full) {
        full.hidden = false;
      }
      if (excerpt) {
        excerpt.hidden = true;
      }
      cell.classList.add('expanded');
      button.setAttribute('aria-expanded', 'true');
      button.setAttribute('aria-label', button.dataset.less || 'Voir moins');
      button.innerHTML = '<i class="fa-solid fa-minus" aria-hidden="true"></i>';
    }
  }

  function showPrompt(button, text) {
    var promptLabel = button.dataset.prompt || 'Proposition :';
    window.prompt(promptLabel, text);
  }

  function resolveAjaxUrl(button) {
    if (button.dataset.ajaxUrl) {
      return button.dataset.ajaxUrl;
    }
    if (window.caTentativesPager && window.caTentativesPager.ajaxUrl) {
      return window.caTentativesPager.ajaxUrl;
    }
    if (window.ctaMyAccount && window.ctaMyAccount.ajaxUrl) {
      return window.ctaMyAccount.ajaxUrl;
    }

    return '';
  }

  function handleMasked(button) {
    if (button.__propositionText) {
      showPrompt(button, button.__propositionText);
      return;
    }

    var uid = button.dataset.uid || '';
    var ajaxUrl = resolveAjaxUrl(button);
    var action = button.dataset.action || 'ca_view_tentative_proposition';
    var nonce = button.dataset.nonce || '';
    var errorMessage = button.dataset.error || 'Une erreur est survenue.';
    var loadingLabel = button.dataset.loading || '';
    var originalLabel = button.dataset.originalLabel || button.textContent;

    if (!uid || !ajaxUrl) {
      window.alert(errorMessage);
      return;
    }

    button.dataset.originalLabel = originalLabel;
    button.disabled = true;
    button.classList.add('is-loading');
    if (loadingLabel) {
      button.textContent = loadingLabel;
    }

    var formData = new FormData();
    formData.append('action', action);
    formData.append('uid', uid);
    if (nonce) {
      formData.append('nonce', nonce);
    }

    var resetButton = function () {
      button.disabled = false;
      button.classList.remove('is-loading');
      button.textContent = button.dataset.originalLabel || originalLabel;
    };

    fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('http');
        }
        return response.json();
      })
      .then(function (payload) {
        if (!payload || payload.success !== true || !payload.data || typeof payload.data.proposition !== 'string') {
          throw new Error('payload');
        }

        var text = payload.data.proposition;
        button.__propositionText = text;
        showPrompt(button, text);
        resetButton();
      })
      .catch(function () {
        window.alert(errorMessage);
        resetButton();
      });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.toggle-proposition');
    if (!button) {
      return;
    }

    event.preventDefault();

    if (button.dataset.mode === 'mask') {
      handleMasked(button);
      return;
    }

    toggleExcerpt(button);
  });
})();
