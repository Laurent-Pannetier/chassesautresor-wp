/**
 * Handle pager for winners table.
 */
document.addEventListener('pager:change', (e) => {
  const pager = e.target;
  if (!pager.classList.contains('enigme-gagnants-pager')) {
    return;
  }
  const section = pager.closest('.enigme-gagnants');
  if (!section) {
    return;
  }
  const enigmeId = section.dataset.enigmeId;
  const page = e.detail.page || 1;
  const data = new URLSearchParams();
  data.append('action', 'enigme_recuperer_gagnants');
  data.append('enigme_id', enigmeId);
  data.append('page', String(page));
  fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: data
  })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        section.innerHTML = res.data.html;
      }
    });
});
