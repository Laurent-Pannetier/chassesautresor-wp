(function () {
  const timers = new WeakMap();

  const formatRemaining = seconds => {
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;
    return `${minutes}:${String(remainder).padStart(2, '0')}`;
  };

  const clear = form => {
    const timer = timers.get(form);
    if (timer) window.clearInterval(timer);
    timers.delete(form);
  };

  const apply = (form, state) => {
    if (!form || !state || !state.retry_at) return;
    clear(form);
    const controls = [...form.querySelectorAll('input:not([type="hidden"]), button[type="submit"]')];
    let output = form.querySelector('[data-retry-countdown]');
    if (!output) {
      output = document.createElement('p');
      output.dataset.retryCountdown = 'true';
      output.className = 'message-limite riddle-retry-countdown';
      output.setAttribute('role', 'status');
      output.setAttribute('aria-live', 'polite');
      form.append(output);
    }
    const serverNow = Date.parse(state.server_now || '') || Date.now();
    const retryAt = Date.parse(state.retry_at);
    const offset = serverNow - Date.now();

    const update = () => {
      const remaining = Math.max(0, Math.ceil((retryAt - (Date.now() + offset)) / 1000));
      const blocked = remaining > 0;
      controls.forEach(control => { control.disabled = blocked; });
      output.hidden = !blocked;
      output.textContent = blocked
        ? `${state.message || window.RiddleRetryCountdownConfig?.message || ''} ${formatRemaining(remaining)}`.trim()
        : '';
      if (!blocked) clear(form);
    };

    update();
    if (!output.hidden) timers.set(form, window.setInterval(update, 1000));
    document.addEventListener('visibilitychange', update, { once: true });
  };

  const initialize = root => {
    (root || document).querySelectorAll('form[data-retry-state]').forEach(form => {
      try {
        apply(form, JSON.parse(form.dataset.retryState));
      } catch (error) {
        // Ignore malformed markup and keep server authorization authoritative.
      }
    });
  };

  window.RiddleRetryCountdown = { apply, clear, formatRemaining, initialize };
  document.addEventListener('DOMContentLoaded', () => initialize(document));
  document.addEventListener('riddle-step-content-updated', () => initialize(document));
}());
