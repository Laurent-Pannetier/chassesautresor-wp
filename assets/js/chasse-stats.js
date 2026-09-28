function initChasseStats() {
  const container = document.getElementById('chasse-stats');
  const select = document.getElementById('chasse-periode');
  if (!container || !select) {
    return;
  }

  const cards = {
    participants: container.querySelector('[data-stat="participants"] .stat-value'),
    tentatives: container.querySelector('[data-stat="tentatives"] .stat-value'),
    points: container.querySelector('[data-stat="points"] .stat-value'),
    engagementRate: container.querySelector('[data-stat="engagement-rate"] .stat-value'),
  };

  container.querySelectorAll('.stat-help').forEach((btn) => {
    btn.addEventListener('click', () => {
      const message = btn.dataset.message;
      if (message) {
        alert(message);
      }
    });
  });

  select.addEventListener('change', () => {
    const periode = select.value;

    const params = new URLSearchParams({
      action: 'chasse_recuperer_stats',
      chasse_id: ChasseStats.chasseId,
      periode,
    });

    fetch(ChasseStats.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: params,
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
        if (cards.engagementRate && typeof stats.engagement_rate !== 'undefined') {
          cards.engagementRate.textContent = `${stats.engagement_rate}%`;
        }
      })
      .catch(() => {});
  });

  const participantsWrapper = document.querySelector('#chasse-tab-stats .liste-participants');
  if (participantsWrapper) {
    function charger(page = 1, orderby = participantsWrapper.dataset.orderby || 'inscription', order = participantsWrapper.dataset.order || 'asc') {
      fetch(ChasseStats.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'chasse_lister_participants',
          chasse_id: ChasseStats.chasseId,
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

    participantsWrapper.addEventListener('pager:change', (e) => {
      const orderby = participantsWrapper.dataset.orderby || 'inscription';
      const order = participantsWrapper.dataset.order || 'asc';
      charger(e.detail.page, orderby, order);
    });

    participantsWrapper.addEventListener('click', (e) => {
      const btn = e.target.closest('button.sort');
      if (!btn) {
        return;
      }
      e.preventDefault();
      const orderby = btn.dataset.orderby || 'inscription';
      let order = participantsWrapper.dataset.order || 'asc';
      if (participantsWrapper.dataset.orderby !== orderby) {
        order = 'asc';
      } else {
        order = order === 'asc' ? 'desc' : 'asc';
      }
      charger(1, orderby, order);
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initChasseStats);
} else {
  initChasseStats();
}
