// Gestion du tri par date et du filtre par état pour le tableau des organisateurs

document.addEventListener('DOMContentLoaded', () => {
  const table = document.querySelector('.table-organisateurs');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  const filter = document.getElementById('filtre-etat');
  const header = table.querySelector('th[data-col="date"]');

  if (filter) {
    filter.addEventListener('change', () => {
      const val = filter.value;
      tbody.querySelectorAll('tr').forEach(row => {
        const etat = row.dataset.etat || '';
        row.style.display = val === 'tous' || val === '' || etat === val ? '' : 'none';
      });
    });
  }

  if (header) {
    header.style.cursor = 'pointer';
    let asc = false;
    header.addEventListener('click', () => {
      const rows = Array.from(tbody.querySelectorAll('tr')).sort((a, b) => {
        const da = new Date(a.dataset.date);
        const db = new Date(b.dataset.date);
        return asc ? da - db : db - da;
      });
      rows.forEach(r => tbody.appendChild(r));
      asc = !asc;
    });
  }
});
