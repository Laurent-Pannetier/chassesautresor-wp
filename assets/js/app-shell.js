(function () {
  var root = document.documentElement;
  var shell = document.querySelector('.app-shell');
  if (!shell) {
    return;
  }

  var readTheme = function () {
    return root.getAttribute('data-theme') === 'day' ? 'day' : 'night';
  };

  var closeNav = function () {
    shell.classList.remove('is-nav-open');
    var toggle = shell.querySelector('[data-app-nav-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }
  };

  var applyTheme = function (theme) {
    var next = theme === 'day' ? 'day' : 'night';
    root.setAttribute('data-theme', next);
    shell.setAttribute('data-theme', next);
    document.cookie = 'cta_theme=' + next + '; path=/; max-age=31536000; samesite=lax';
    shell.querySelectorAll('[data-theme-value]').forEach(function (button) {
      var active = button.getAttribute('data-theme-value') === next;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  applyTheme(readTheme());

  shell.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var themeButton = target.closest('[data-theme-value]');
    if (themeButton) {
      applyTheme(themeButton.getAttribute('data-theme-value'));
      return;
    }

    var toggle = target.closest('[data-app-nav-toggle]');
    if (toggle) {
      var open = !shell.classList.contains('is-nav-open');
      shell.classList.toggle('is-nav-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }

    if (target.closest('[data-app-nav-close]')) {
      closeNav();
    }
  });

  shell.querySelectorAll('.app-sidebar a').forEach(function (link) {
    link.addEventListener('click', function () {
      if (window.matchMedia('(max-width: 767px)').matches) {
        closeNav();
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeNav();
    }
  });
})();
