function initEnigmeStats() {
  const container = document.getElementById('enigme-stats');
  const select = document.getElementById('enigme-periode');
  if (!container || !select) {
    return;
  }

  const cards = {
    participants: container.querySelector('[data-stat="participants"] .stat-value'),
    tentatives: container.querySelector('[data-stat="tentatives"] .stat-value'),
    points: container.querySelector('[data-stat="points"] .stat-value'),
    solutions: container.querySelector('[data-stat="solutions"] .stat-value'),
  };

  select.addEventListener('change', () => {
    const periode = select.value;

    const data = new FormData();
    data.append('action', 'enigme_recuperer_stats');
    data.append('enigme_id', EnigmeStats.enigmeId);
    data.append('periode', periode);

    fetch(EnigmeStats.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: data,
    })
      .then((response) => response.json())
      .then((res) => {
        if (!res.success) {
          return;
        }
        const stats = res.data;
        if (cards.participants && typeof stats.participants !== 'undefined') {
          cards.participants.textContent = stats.participants;
        }
        if (cards.tentatives && typeof stats.tentatives !== 'undefined') {
          cards.tentatives.textContent = stats.tentatives;
        }
        if (cards.points && typeof stats.points !== 'undefined') {
          cards.points.textContent = stats.points;
        }
        if (cards.solutions && typeof stats.solutions !== 'undefined') {
          cards.solutions.textContent = stats.solutions;
        }
      })
      .catch(() => {});
  });

  const participantsWrapper = document.querySelector('#enigme-tab-stats .liste-participants');
  if (participantsWrapper) {
    const postId = EnigmeStats.enigmeId;

    function charger(page = 1, orderby = participantsWrapper.dataset.orderby || 'date', order = participantsWrapper.dataset.order || 'asc') {
      fetch(EnigmeStats.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'enigme_lister_participants',
          enigme_id: postId,
          page,
          orderby,
          order,
        }),
      })
        .then((r) => r.json())
        .then((res) => {
          if (!res.success) return;
          participantsWrapper.innerHTML = res.data.html;
          participantsWrapper.dataset.page = res.data.page;
          participantsWrapper.dataset.pages = res.data.pages;
          participantsWrapper.dataset.order = order;
          participantsWrapper.dataset.orderby = orderby;
        });
    }

    participantsWrapper.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      if (btn.classList.contains('pager-first')) {
        e.preventDefault();
        charger(1);
      }
      if (btn.classList.contains('pager-prev')) {
        e.preventDefault();
        const page = parseInt(participantsWrapper.dataset.page || '1', 10);
        if (page > 1) charger(page - 1);
      }
      if (btn.classList.contains('pager-next')) {
        e.preventDefault();
        const page = parseInt(participantsWrapper.dataset.page || '1', 10);
        const pages = parseInt(participantsWrapper.dataset.pages || '1', 10);
        if (page < pages) charger(page + 1);
      }
      if (btn.classList.contains('pager-last')) {
        e.preventDefault();
        const pages = parseInt(participantsWrapper.dataset.pages || '1', 10);
        charger(pages);
      }
      if (btn.classList.contains('sort')) {
        e.preventDefault();
        const orderby = btn.dataset.orderby || 'date';
        let order = participantsWrapper.dataset.order || 'asc';
        if (participantsWrapper.dataset.orderby !== orderby) {
          order = 'asc';
        } else {
          order = order === 'asc' ? 'desc' : 'asc';
        }
        charger(1, orderby, order);
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', initEnigmeStats);
initEnigmeStats();

