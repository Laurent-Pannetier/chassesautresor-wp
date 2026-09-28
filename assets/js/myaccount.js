// ========================================
// 📁 myaccount.js
// Charge les sections admin via AJAX dans l'espace "Mon Compte".
// ========================================

document.addEventListener('DOMContentLoaded', () => {
  const navs = document.querySelectorAll('.dashboard-nav');
  const content = document.querySelector('.myaccount-content');
  const header = document.querySelector('.myaccount-title');
  const siteMessages = document.querySelector('.msg-important');
  const sidebar = document.querySelector('.myaccount-sidebar');
  const sidebarToggle = document.querySelector('.myaccount-sidebar-toggle');
  const sidebarClose = document.querySelector('.myaccount-sidebar-close');
  const sidebarBackdrop = document.querySelector('.myaccount-sidebar-backdrop');
  let outsideClickHandler = null;
  let keydownHandler = null;

  const initRecommendedSlider = (root = document) => {
    if (window.caRecommendedSlider && typeof window.caRecommendedSlider.init === 'function') {
      window.caRecommendedSlider.init(root);
    }
  };

  initRecommendedSlider(document);

  const setSidebarExpanded = (isOpen) => {
    if (!sidebarToggle) {
      return;
    }
    sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  };

  const removeDocumentListeners = () => {
    if (outsideClickHandler) {
      document.removeEventListener('pointerdown', outsideClickHandler);
      outsideClickHandler = null;
    }
    if (keydownHandler) {
      document.removeEventListener('keydown', keydownHandler);
      keydownHandler = null;
    }
  };

  const closeSidebar = () => {
    if (sidebar) {
      sidebar.classList.remove('is-open');
    }
    if (sidebarBackdrop) {
      sidebarBackdrop.classList.remove('is-visible');
    }
    setSidebarExpanded(false);
    removeDocumentListeners();
  };

  const openSidebar = () => {
    if (!sidebar) {
      return;
    }
    sidebar.classList.add('is-open');
    if (sidebarBackdrop) {
      sidebarBackdrop.classList.add('is-visible');
    }
    setSidebarExpanded(true);

    if (!outsideClickHandler) {
      outsideClickHandler = (event) => {
        const target = event.target;
        const clickedToggle = sidebarToggle && sidebarToggle.contains(target);
        if (!sidebar.contains(target) && !clickedToggle) {
          closeSidebar();
        }
      };
      document.addEventListener('pointerdown', outsideClickHandler);
    }

    if (!keydownHandler) {
      keydownHandler = (event) => {
        if (event.key === 'Escape') {
          closeSidebar();
        }
      };
      document.addEventListener('keydown', keydownHandler);
    }
  };

  if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      if (sidebar.classList.contains('is-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    if (sidebarClose) {
      sidebarClose.addEventListener('click', () => {
        closeSidebar();
      });
    }

    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', () => {
        closeSidebar();
      });
    }

    sidebar.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        closeSidebar();
      });
    });

    const mobileMedia = window.matchMedia('(min-width: 600px)');
    const handleMediaChange = (event) => {
      if (event.matches) {
        closeSidebar();
      }
    };

    handleMediaChange(mobileMedia);
    if (typeof mobileMedia.addEventListener === 'function') {
      mobileMedia.addEventListener('change', handleMediaChange);
    } else if (typeof mobileMedia.addListener === 'function') {
      mobileMedia.addListener(handleMediaChange);
    }
  }

  if (!navs.length || !content || !siteMessages || typeof ctaMyAccount === 'undefined') {
    return;
  }

  const accountPath = window.location.pathname && window.location.pathname !== '/'
    ? window.location.pathname
    : '/mon-compte/';

  const fadeFlash = () => {
    const flash = siteMessages ? siteMessages.querySelector('.flash') : null;
    if (flash) {
      setTimeout(() => {
        flash.remove();
      }, 3000);
    }
  };

  const decorateMessages = () => {
    if (!siteMessages) {
      return;
    }
    siteMessages.querySelectorAll('p').forEach((p) => {
      if (p.classList.contains('message-erreur')) {
        p.setAttribute('role', 'alert');
        p.setAttribute('aria-live', 'assertive');
      } else {
        if (!p.className.match(/message-(info|succes)/) && !p.classList.contains('flash')) {
          p.classList.add('message-info');
        }
        p.setAttribute('role', 'status');
        p.setAttribute('aria-live', 'polite');
      }
    });
  };

  const loadSection = async (link, push = true) => {
    const section = link.dataset.section;
    if (!section) {
      window.location.href = link.href;
      return;
    }

    const searchParams = new URLSearchParams(window.location.search);
    searchParams.set('action', 'cta_load_admin_section');
    searchParams.set('section', section);
    const url = `${ctaMyAccount.ajaxUrl}?${searchParams.toString()}`;

    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      const data = await response.json();
      if (!data.success) {
        throw new Error('Request failed');
      }
      const messages = data.data.messages || '';
      if (siteMessages) {
        siteMessages.innerHTML = messages;
      }
      content.innerHTML = data.data.html;
      initRecommendedSlider(content);
      decorateMessages();
      fadeFlash();
      document
        .querySelectorAll('.dashboard-nav-link[data-section]')
        .forEach((a) => a.classList.remove('active'));
      link.classList.add('active');
      if (header) {
        const newTitle = link.dataset.title || link.textContent.trim();
        if (newTitle) {
          header.textContent = newTitle;
        }
      }
      document.dispatchEvent(
        new CustomEvent('myaccountSectionLoaded', { detail: { section } })
      );
      if (push) {
        window.history.pushState(null, '', link.href);
      } else {
        window.history.replaceState(null, '', accountPath);
      }
    } catch (err) {
      if (siteMessages) {
        siteMessages.innerHTML = `
          <p class="message-erreur" role="alert" aria-live="assertive">Impossible de charger la section.</p>
          <p class="message-info" role="status" aria-live="polite"><a href="#" class="reload-section">Recharger</a> ou <a href="${link.href}">ouvrir la page complète</a>.</p>
        `;
      }
      content.innerHTML = '';
      const reload = siteMessages ? siteMessages.querySelector('.reload-section') : null;
      if (reload) {
        reload.addEventListener('click', (e) => {
          e.preventDefault();
          loadSection(link);
        });
      }
    }
  };

  navs.forEach((nav) => {
    nav.addEventListener('click', (e) => {
      const link = e.target.closest('.dashboard-nav-link');
      if (!link || !link.dataset.section) {
        return;
      }

      e.preventDefault();
      loadSection(link);
      closeSidebar();
    });
  });

  const params = new URLSearchParams(window.location.search);
  const initialSection = params.get('section');
  if (initialSection) {
    const initialLink = document.querySelector(`.dashboard-nav-link[data-section="${initialSection}"]`);
    if (initialLink) {
      loadSection(initialLink, false);
    } else {
      params.delete('section');
      const newQuery = params.toString();
      const targetUrl = newQuery ? `${accountPath}?${newQuery}` : accountPath;
      window.history.replaceState(null, '', targetUrl);
    }
  }

  decorateMessages();
  fadeFlash();
});
