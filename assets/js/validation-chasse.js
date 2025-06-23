document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.bouton-validation-chasse');
    if (!btn) return;
    if (typeof window.onValiderChasseClick === 'function') {
      window.onValiderChasseClick(btn);
    }
  });
});
