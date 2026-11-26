// Tri par date et filtre par état du tableau des organisateurs.
// Le tableau arrive aussi après coup, quand Mon compte charge la section en AJAX.

(function () {
  function bindOrganizerTable(table) {
    if (!table || table.dataset.triReady === '1') {
      return;
    }

    var tbody = table.querySelector('tbody');
    if (!tbody) {
      return;
    }

    table.dataset.triReady = '1';
    var scope = table.closest('section') || document;
    var filter = scope.querySelector('#filtre-etat');
    var header = table.querySelector('th[data-col="date"]');

    if (filter) {
      filter.addEventListener('change', function () {
        var val = filter.value;
        tbody.querySelectorAll('tr').forEach(function (row) {
          var etat = row.dataset.etat || '';
          row.style.display = val === 'tous' || val === '' || etat === val ? '' : 'none';
        });
      });
    }

    if (header) {
      header.style.cursor = 'pointer';
      var asc = false;
      header.addEventListener('click', function () {
        var rows = Array.from(tbody.querySelectorAll('tr')).sort(function (a, b) {
          var da = new Date(a.dataset.date);
          var db = new Date(b.dataset.date);
          return asc ? da - db : db - da;
        });
        rows.forEach(function (row) {
          tbody.appendChild(row);
        });
        asc = !asc;
      });
    }
  }

  function initOrganizerTables(root) {
    var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
    scope.querySelectorAll('.table-organisateurs').forEach(bindOrganizerTable);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initOrganizerTables(document);
  });

  document.addEventListener('myaccountSectionLoaded', function () {
    initOrganizerTables(document.querySelector('.myaccount-content') || document);
  });
})();
