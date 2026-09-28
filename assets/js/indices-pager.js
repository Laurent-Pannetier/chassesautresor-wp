(function () {
  function reloadTable(wrapper) {
    var page = wrapper.dataset.page || '1';
    var formData = new FormData();
    formData.append('action', 'indices_lister_table');
    formData.append('objet_id', wrapper.dataset.objetId);
    formData.append('objet_type', wrapper.dataset.objetType);
    formData.append('page', page);
    if (wrapper.dataset.chasseId) {
      formData.append('chasse_id', wrapper.dataset.chasseId);
    }
    if (wrapper.dataset.enigmeId) {
      formData.append('enigme_id', wrapper.dataset.enigmeId);
    }

    fetch(wrapper.dataset.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) return;
        wrapper.innerHTML = res.data.html;
        wrapper.dataset.page = String(res.data.page);
        wrapper.dataset.pages = String(res.data.pages);
      })
      .catch(function () {
        var txt =
          window.indicesCreate &&
          indicesCreate.texts &&
          indicesCreate.texts.ajaxError
            ? indicesCreate.texts.ajaxError
            : wp.i18n.__('Erreur réseau', 'chassesautresor-com');
        wrapper.innerHTML = '<p class="error">' + txt + '</p>';
      });
  }

  window.reloadIndicesTable = reloadTable;

  document.addEventListener('pager:change', function (e) {
    var pager = e.target;
    if (!pager.classList.contains('indices-pager')) {
      return;
    }
    var wrapper = pager.closest('.liste-indices');
    if (!wrapper) {
      return;
    }
    wrapper.dataset.page = String(e.detail.page || 1);
    reloadTable(wrapper);
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.badge-action.delete');
    if (!btn) return;
    var wrapper = btn.closest('.liste-indices');
    if (!wrapper) return;
    var confirmText = btn.getAttribute('data-confirm');
    if (confirmText && !window.confirm(confirmText)) return;

    var formData = new FormData();
    formData.append('action', 'supprimer_indice');
    formData.append('indice_id', btn.dataset.indiceId);

    fetch(wrapper.dataset.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) return;
        reloadTable(wrapper);
        window.dispatchEvent(new Event('indice-created'));
      })
      .catch(function () {
        var txt =
          window.indicesCreate &&
          indicesCreate.texts &&
          indicesCreate.texts.ajaxError
            ? indicesCreate.texts.ajaxError
            : wp.i18n.__('Erreur réseau', 'chassesautresor-com');
        wrapper.innerHTML = '<p class="error">' + txt + '</p>';
      });
  });
})();

