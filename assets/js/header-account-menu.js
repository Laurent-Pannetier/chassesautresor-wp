// Header account menu: hover on desktop, tap toggle on touch.
(function () {
  const config = window.ctaHeaderAccountMenu;
  if (!config || !config.labels) {
    return;
  }

  const wrap = document.querySelector('.ast-header-account-wrap');
  if (!wrap || !document.body.classList.contains('logged-in')) {
    return;
  }

  const inner = wrap.querySelector('.ast-header-account-inner-wrap') || wrap;
  const trigger = inner.querySelector('.ast-header-account-link');
  if (!trigger) {
    return;
  }

  wrap.classList.add('has-cta-account-menu');

  const menu = document.createElement('div');
  menu.className = 'cta-header-account-menu';
  menu.hidden = true;
  menu.setAttribute('role', 'menu');
  menu.setAttribute('aria-label', config.labels.menu);

  const links = [
    { href: config.accountUrl, label: config.labels.account, className: 'cta-header-account-menu__link' },
    { href: config.settingsUrl, label: config.labels.settings, className: 'cta-header-account-menu__link' },
    {
      href: config.logoutUrl,
      label: config.labels.logout,
      className: 'cta-header-account-menu__link cta-header-account-menu__link--logout',
    },
  ];

  links.forEach((item) => {
    const link = document.createElement('a');
    link.href = item.href;
    link.className = item.className;
    link.setAttribute('role', 'menuitem');
    link.textContent = item.label;
    menu.appendChild(link);
  });

  inner.appendChild(menu);
  trigger.setAttribute('aria-haspopup', 'menu');
  trigger.setAttribute('aria-expanded', 'false');

  let closeTimer = null;
  const isCoarsePointer = () =>
    window.matchMedia('(hover: none), (pointer: coarse)').matches;

  const openMenu = () => {
    if (closeTimer) {
      window.clearTimeout(closeTimer);
      closeTimer = null;
    }
    menu.hidden = false;
    wrap.classList.add('is-account-menu-open');
    trigger.setAttribute('aria-expanded', 'true');
  };

  const closeMenu = () => {
    menu.hidden = true;
    wrap.classList.remove('is-account-menu-open');
    trigger.setAttribute('aria-expanded', 'false');
  };

  const scheduleClose = () => {
    if (closeTimer) {
      window.clearTimeout(closeTimer);
    }
    closeTimer = window.setTimeout(closeMenu, 160);
  };

  wrap.addEventListener('mouseenter', () => {
    if (!isCoarsePointer()) {
      openMenu();
    }
  });

  wrap.addEventListener('mouseleave', () => {
    if (!isCoarsePointer()) {
      scheduleClose();
    }
  });

  trigger.addEventListener('click', (event) => {
    if (!isCoarsePointer() && !wrap.classList.contains('is-account-menu-open')) {
      return;
    }
    event.preventDefault();
    if (wrap.classList.contains('is-account-menu-open')) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  document.addEventListener('pointerdown', (event) => {
    if (!wrap.contains(event.target)) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });
})();
