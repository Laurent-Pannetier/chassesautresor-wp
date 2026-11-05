/**
 * Handle AJAX pagination for the points history table.
 * Relies on the global pager component which emits `pager:change` events.
 */
(function () {
  function loadPage(wrapper, page) {
    var params = new URLSearchParams({
      action: 'load_points_history',
      nonce: PointsHistoryAjax.nonce,
      page: String(page),
    });
    fetch(PointsHistoryAjax.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(),
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (response) {
        if (response.success) {
          var tbody = wrapper.querySelector('tbody');
          if (tbody) {
            tbody.innerHTML = response.data.rows;
          }
        }
      });
  }

  document.addEventListener('pager:change', function (e) {
    var pager = e.target;
    if (!pager.classList.contains('points-history-pager')) {
      return;
    }
    var wrapper = pager.closest('.stats-table-wrapper');
    if (wrapper) {
      loadPage(wrapper, e.detail.page);
    }
  });
})();

