document.addEventListener('DOMContentLoaded', function () {
  function formatCountdown(remainingSeconds) {
    var remaining = Math.max(0, Math.floor(remainingSeconds));
    var minute = 60;
    var hour = 3600;
    var day = 86400;
    if (remaining >= day) {
      return Math.floor(remaining / day) + 'j';
    }
    if (remaining >= hour) {
      var hours = Math.floor(remaining / hour);
      var hourMinutes = Math.floor((remaining % hour) / minute);
      return hourMinutes > 0 ? hours + 'h' + String(hourMinutes).padStart(2, '0') : hours + 'h';
    }
    var minutes = Math.floor(remaining / minute);
    var seconds = remaining % minute;
    return minutes + ':' + String(seconds).padStart(2, '0');
  }

  function promoteDelayChip(chip) {
    var link = document.createElement('a');
    link.href = '#';
    link.className = 'indice-link indice-link--locked etiquette';
    link.dataset.indiceId = chip.dataset.indiceId || '';
    link.dataset.cout = chip.dataset.cout || '0';
    link.dataset.unlocked = '0';
    var icon = document.createElement('i');
    icon.className = 'fa-solid fa-lightbulb';
    icon.setAttribute('aria-hidden', 'true');
    var label = document.createElement('span');
    label.className = 'indice-link__label';
    var source = chip.querySelector('.indice-link__label');
    label.textContent = source ? source.textContent : '';
    link.appendChild(icon);
    link.appendChild(document.createTextNode(' '));
    link.appendChild(label);
    var cost = parseInt(chip.dataset.cout || '0', 10);
    if (cost > 0) {
      link.appendChild(document.createTextNode(' - ' + cost + ' '));
      var sup = document.createElement('sup');
      sup.textContent = (indicesUnlock.texts && indicesUnlock.texts.pts) || 'pts';
      link.appendChild(sup);
    }
    chip.replaceWith(link);
  }

  function tickCountdowns() {
    document.querySelectorAll('.indice-link--delay[data-available-at]').forEach(function (chip) {
      var availableAt = parseInt(chip.getAttribute('data-available-at') || '', 10);
      if (!availableAt) {
        return;
      }
      var remaining = availableAt - Math.floor(Date.now() / 1000);
      if (remaining <= 0) {
        promoteDelayChip(chip);
        return;
      }
      var countdown = chip.querySelector('.indice-link__countdown');
      if (countdown) {
        countdown.textContent = formatCountdown(remaining);
      }
    });
  }

  tickCountdowns();
  window.setInterval(tickCountdowns, 1000);

  function displayContent(container, html) {
    container.innerHTML = '<button type="button" class="indice-close" aria-label="' + indicesUnlock.texts.close + '">&times;</button>' + html;
  }

  function replaceHintLists(zone, html) {
    if (!zone || !html) {
      return;
    }
    var display = zone.querySelector('.indice-display');
    var active = zone.querySelector('.indice-link--active');
    var openId = active && active.dataset.indiceId ? String(active.dataset.indiceId).replace(/\D/g, '') : '';
    zone.querySelectorAll('.zone-indices-line').forEach(function (node) {
      node.remove();
    });
    var holder = document.createElement('div');
    holder.innerHTML = html;
    while (holder.firstChild) {
      if (display) {
        zone.insertBefore(holder.firstChild, display);
      } else {
        zone.appendChild(holder.firstChild);
      }
    }
    if (openId) {
      var fresh = zone.querySelector('.indice-link[data-indice-id="' + openId + '"]');
      if (fresh) {
        fresh.classList.add('indice-link--active');
      }
    }
    tickCountdowns();
  }

  function errorCode(data) {
    if (typeof data === 'string') {
      return data;
    }
    return data && data.code ? data.code : '';
  }

  function fetchIndice(id, link, container) {
    var zone = container ? container.closest('.zone-indices') : null;
    var fd = new FormData();
    fd.append('action', 'debloquer_indice');
    fd.append('indice_id', id);
    fd.append('nonce', indicesUnlock.nonce);
    if (zone && zone.dataset.enigmeId) {
      fd.append('enigme_id', zone.dataset.enigmeId);
    }
    fetch(indicesUnlock.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          displayContent(container, res.data.html);
          if (link) {
            link.dataset.unlocked = '1';
            link.classList.remove('indice-link--locked');
            link.classList.add('indice-link--unlocked', 'indice-link--consulted');
            link.setAttribute('aria-label', (link.querySelector('.indice-link__label') || link).textContent.trim()
              + ', ' + indicesUnlock.texts.consulted);
            var icon = link.querySelector('i');
            if (icon) {
              icon.classList.remove('fa-lightbulb', 'fa-hourglass', 'fa-lock', 'fa-eye', 'fa-stopwatch');
              icon.classList.add('fa-check');
            }
          }
          if (res.data.points !== undefined) {
            var solde = document.querySelector('.participation-infos .solde');
            if (solde) {
              solde.textContent = indicesUnlock.texts.solde + ' : ' + res.data.points + ' ' + indicesUnlock.texts.pts;
            }
          }
          if (res.data.hints_html) {
            replaceHintLists(zone, res.data.hints_html);
          }
        } else if (errorCode(res.data) === 'indice_indisponible' && res.data && res.data.hints_html) {
          replaceHintLists(zone, res.data.hints_html);
        } else if (container) {
          displayContent(container, '<p>' + indicesUnlock.texts.unavailable + '</p>');
        }
      });
  }

  document.body.addEventListener('click', function (e) {
    var eye = e.target.closest('.eyebox-trigger');
    if (eye) {
      e.preventDefault();
      var url = eye.dataset.full || eye.getAttribute('href');
      var overlay = document.createElement('div');
      overlay.className = 'eyebox-overlay';
      overlay.innerHTML = '<img src="' + url + '" alt="" />';
      document.body.appendChild(overlay);
      overlay.addEventListener('click', function () {
        overlay.remove();
      });
      return;
    }

    var link = e.target.closest('.indice-link');
    if (link) {
      e.preventDefault();
      var zone = link.closest('.zone-indices');
      var container = zone ? zone.querySelector('.indice-display') : null;
      if (!container) return;
      var active = zone ? zone.querySelector('.indice-link--active') : null;
      if (active) {
        active.classList.remove('indice-link--active');
      }
      link.classList.add('indice-link--active');
      var cout = link.dataset.cout || '0';
      if (link.dataset.unlocked === '1' || cout === '0') {
        fetchIndice(link.dataset.indiceId, link, container);
      } else {
        var html = '<p>' + indicesUnlock.texts.unlock + ' - ' + cout + ' ' + indicesUnlock.texts.pts + '</p>'
          + '<button type="button" class="btn-debloquer-indice" data-indice-id="' + link.dataset.indiceId + '">'
          + indicesUnlock.texts.unlock + '</button>';
        displayContent(container, html);
      }
      return;
    }

    var closeBtn = e.target.closest('.indice-close');
    if (closeBtn) {
      e.preventDefault();
      var closeContainer = closeBtn.closest('.indice-display');
      if (closeContainer) {
        closeContainer.innerHTML = '';
      }
      var closeZone = closeBtn.closest('.zone-indices');
      if (closeZone) {
        var activeLink = closeZone.querySelector('.indice-link--active');
        if (activeLink) {
          activeLink.classList.remove('indice-link--active');
        }
      }
      return;
    }

    var btn = e.target.closest('.btn-debloquer-indice');
    if (btn) {
      e.preventDefault();
      btn.disabled = true;
      var zoneBtn = btn.closest('.zone-indices');
      var containerBtn = zoneBtn ? zoneBtn.querySelector('.indice-display') : null;
      var id = btn.dataset.indiceId;
      var linkSel = zoneBtn ? zoneBtn.querySelector('.indice-link[data-indice-id="' + id + '"]') : null;
      if (containerBtn) {
        fetchIndice(id, linkSel, containerBtn);
      }
    }
  });
});
