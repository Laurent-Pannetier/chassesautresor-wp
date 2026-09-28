/**
 * Global pager interactions.
 *
 * Listens for clicks and selection changes on elements with the `.pager` class
 * and dispatches a `pager:change` CustomEvent with the selected page number.
 */
document.addEventListener('DOMContentLoaded', () => {
  document.body.addEventListener('click', (e) => {
    const btn = e.target.closest('.pager-first, .pager-prev, .pager-next, .pager-last');
    if (!btn) {
      return;
    }
    const pager = btn.closest('.pager');
    if (!pager) {
      return;
    }
    e.preventDefault();
    const total = parseInt(pager.dataset.total || '1', 10);
    let current = parseInt(pager.dataset.current || '1', 10);
    if (btn.classList.contains('pager-first')) {
      current = 1;
    } else if (btn.classList.contains('pager-prev')) {
      if (current > 1) current -= 1;
    } else if (btn.classList.contains('pager-next')) {
      if (current < total) current += 1;
    } else if (btn.classList.contains('pager-last')) {
      current = total;
    }

    // Show a loading spinner while data is fetched.
    btn.disabled = true;
    btn.innerHTML = '<span class="pager-loading" aria-hidden="true"></span>';

    pager.dataset.current = String(current);
    const select = pager.querySelector('.pager-select');
    if (select) {
      select.value = String(current);
    }
    pager.dispatchEvent(
      new CustomEvent('pager:loading', { detail: { page: current }, bubbles: true })
    );
    pager.dispatchEvent(
      new CustomEvent('pager:change', { detail: { page: current }, bubbles: true })
    );
  });

  document.body.addEventListener('change', (e) => {
    const select = e.target.closest('.pager-select');
    if (!select) {
      return;
    }
    const pager = select.closest('.pager');
    if (!pager) {
      return;
    }
    const page = parseInt(select.value, 10);
    pager.dataset.current = String(page);
    pager.dispatchEvent(
      new CustomEvent('pager:loading', { detail: { page }, bubbles: true })
    );
    pager.dispatchEvent(
      new CustomEvent('pager:change', { detail: { page }, bubbles: true })
    );
  });
});
