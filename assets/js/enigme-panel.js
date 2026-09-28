(function() {
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.enigme-mobile-panel-toggle');
    const panel = document.getElementById('enigme-mobile-panel');
    if (!toggle || !panel) return;
    const sheet = panel.querySelector('.enigme-mobile-panel__sheet');
    const overlay = panel.querySelector('.enigme-mobile-panel__overlay');
    const tabs = panel.querySelectorAll('.panel-tab');
    const tabContents = panel.querySelectorAll('.panel-tab-content');
    const content = panel.querySelector('.enigme-mobile-panel__content');
    const statsContainer = document.getElementById('panel-stats');
    let lastFocused = null;
    let startY = null;
    let anchored = false;
    let activeTab = 'panel-enigmes';
    const scrollPositions = {};
    let statsCache = null;
    let statsFetchedAt = 0;
    const CACHE_MS = 5 * 60 * 1000;

    function updateMode() {
      const full = sheet.scrollHeight > window.innerHeight * 0.75;
      panel.classList.toggle('full', full);
    }

    function openPanel() {
      lastFocused = document.activeElement;
      panel.hidden = false;
      panel.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.classList.add('no-scroll');
      updateMode();
      const focusable = sheet.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable.length) {
        focusable[0].focus();
      }
      if (!anchored) {
        const current = content.querySelector('.enigme-menu li.active');
        if (current && typeof current.scrollIntoView === 'function') {
          current.scrollIntoView({ block: 'center' });
        }
        anchored = true;
      }
    }

    function closePanel() {
      panel.classList.remove('open');
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('no-scroll');
      if (lastFocused) {
        lastFocused.focus();
      }
    }

    function trapFocus(e) {
      const focusable = sheet.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    toggle.addEventListener('click', () => {
      if (panel.hidden) {
        openPanel();
      } else {
        closePanel();
      }
    });

    overlay.addEventListener('click', closePanel);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !panel.hidden) {
        closePanel();
      } else if (e.key === 'Tab' && !panel.hidden) {
        trapFocus(e);
      }
    });

    window.addEventListener('resize', () => {
      if (!panel.hidden) {
        updateMode();
      }
    });

    sheet.addEventListener('touchstart', (e) => {
      if (sheet.scrollTop === 0) {
        startY = e.touches[0].clientY;
      } else {
        startY = null;
      }
    });

    sheet.addEventListener('touchmove', (e) => {
      if (startY !== null) {
        const diff = e.touches[0].clientY - startY;
        if (diff > 50) {
          closePanel();
          startY = null;
        }
      }
    });

    function applyStats(stats) {
      if (!stats) return;
      Object.keys(stats).forEach((key) => {
        const el = statsContainer.querySelector('[data-stat="' + key + '"] .stat-value');
        if (el) {
          el.textContent = stats[key];
        }
      });
    }

    function loadStats() {
      if (!statsContainer) return;
      const now = Date.now();
      if (statsCache && now - statsFetchedAt < CACHE_MS) {
        applyStats(statsCache);
        return;
      }
      const data = new FormData();
      data.append('action', 'enigme_recuperer_stats');
      data.append('enigme_id', statsContainer.dataset.enigmeId);
      fetch(statsContainer.dataset.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data,
      })
        .then((r) => r.json())
        .then((res) => {
          if (!res.success) return;
          statsCache = res.data;
          statsFetchedAt = now;
          applyStats(res.data);
        })
        .catch(() => {});
    }

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        if (tab.getAttribute('aria-selected') === 'true') return;
        scrollPositions[activeTab] = content.scrollTop;
        tabs.forEach((t) => {
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        const target = tab.dataset.target;
        activeTab = target;
        tabContents.forEach((c) => {
          c.hidden = c.id !== target;
        });
        content.scrollTop = scrollPositions[target] || 0;
        if (target === 'panel-stats') {
          loadStats();
        }
      });
    });

    panel.querySelectorAll('.enigme-navigation a').forEach(link => {
      link.addEventListener('click', () => {
        closePanel();
      });
    });
  });
})();
