// Staff trophy wall: the count is a native summary, so a second press closes it.
// A disclosure does not close on an outside press or Escape; this adds those gestures.
(function () {
  if (!document.querySelector('.trophies-wall')) {
    return;
  }

  var openLists = function () {
    return document.querySelectorAll('.trophies-wall .trophy-card__details[open]');
  };

  var dismissOutside = function (event) {
    var target = event.target;
    openLists().forEach(function (details) {
      if (target instanceof Node && details.contains(target)) {
        return;
      }
      details.open = false;
    });
  };

  document.addEventListener('pointerdown', dismissOutside);
  document.addEventListener('click', dismissOutside);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || event.isComposing) {
      return;
    }

    var open = Array.prototype.slice.call(openLists());
    if (open.length === 0) {
      return;
    }

    var active = document.activeElement;
    open.forEach(function (details) {
      var summary = details.querySelector('summary');
      var restore = summary && active instanceof Node && details.contains(active);
      details.open = false;
      if (restore) {
        summary.focus();
      }
    });
  });

  document.addEventListener('toggle', function (event) {
    var details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open) {
      return;
    }
    if (!details.classList.contains('trophy-card__details') || !details.closest('.trophies-wall')) {
      return;
    }

    openLists().forEach(function (other) {
      if (other !== details) {
        other.open = false;
      }
    });
  }, true);
})();
