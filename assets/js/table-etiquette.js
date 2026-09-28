/*
 * Apply label styling to table columns marked with data-format="etiquette".
 */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.stats-table, .table-tentatives').forEach((table) => {
    table.querySelectorAll('th[data-format="etiquette"]').forEach((th) => {
      const col = th.dataset.col ? parseInt(th.dataset.col, 10) : th.cellIndex + 1;
      table.querySelectorAll(`tbody td:nth-child(${col})`).forEach((td) => {
        if (td.querySelector('.etiquette')) {
          return;
        }
        const text = td.textContent.trim();
        if (text !== '') {
          td.innerHTML = `<span class="etiquette">${text}</span>`;
        }
      });
    });
  });
});
