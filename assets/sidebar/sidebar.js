(function() {
  function init() {
    const aside = document.querySelector('.menu-lateral');
    if (!aside) return;
    const bp = getComputedStyle(document.documentElement)
      .getPropertyValue('--breakpoint-desktop')
      .trim() || '1280px';
    const mq = window.matchMedia(`(min-width: ${bp})`);
    if (!mq.matches) return;
    const __ = window.wp?.i18n?.__ || (s => s);
    const opener = document.createElement('button');
    opener.className = 'menu-lateral__reveal';
    opener.type = 'button';
    opener.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>' +
      '<span class="screen-reader-text">' + __('Afficher le panneau', 'chassesautresor-com') + '</span>';
    document.body.appendChild(opener);

    function handleViewportChange(e) {
      if (!e.matches) {
        opener.remove();
        aside.classList.remove('is-hidden');
      }
    }
    if (mq.addEventListener) {
      mq.addEventListener('change', handleViewportChange);
    } else if (mq.addListener) {
      mq.addListener(handleViewportChange);
    }
    const closer = aside.querySelector('.menu-lateral__close');
    let timer = null;
    const HIDE_DELAY = 3500;
    function hideAside() {
      aside.classList.add('is-hidden');
      opener.style.display = 'flex';
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
    }
    function showAside() {
      aside.classList.remove('is-hidden');
      opener.style.display = 'none';
      if (timer) clearTimeout(timer);
      timer = setTimeout(hideAside, HIDE_DELAY);
    }
    opener.addEventListener('click', showAside);
    opener.addEventListener('mouseenter', showAside);
    if (closer) {
      closer.addEventListener('click', hideAside);
    }
    aside.addEventListener('mouseenter', () => {
      if (timer) clearTimeout(timer);
    });
    aside.addEventListener('mouseleave', () => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(hideAside, HIDE_DELAY);
    });
    function reloadNav(chasseId) {
      if (!chasseId) return;
      const data = new URLSearchParams();
      data.append('action', 'chasse_recuperer_navigation');
      data.append('chasse_id', chasseId);
      fetch(sidebarData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
      })
        .then(r => r.json())
        .then(res => {
          if (!res.success) return;
          const nav = document.querySelector('.enigme-navigation');
          const menu = nav ? nav.querySelector('.enigme-menu') : null;
          if (menu) {
            menu.innerHTML = res.data.html;
          }
          if (nav && Array.isArray(res.data.ids)) {
            nav.dataset.visibleIds = res.data.ids.join(',');
          }
        });
    }
    document.addEventListener('enigmeDebloquee', () => {
      const nav = document.querySelector('.enigme-navigation');
      const chasseId = nav ? nav.dataset.chasseId : null;
      reloadNav(chasseId);
    });
    showAside();
    window.sidebarAside = { show: showAside, reload: reloadNav };
    window.enigmeAside = window.sidebarAside;
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
