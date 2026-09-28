/**
 * Display skeleton cards in lists while loading new data.
 */
(function () {
  function createSkeletonCard() {
    const el = document.createElement('article');
    el.className = 'carte carte-skeleton';
    el.innerHTML = '<div class="carte-core"><div class="skeleton-img"></div><h3 class="skeleton-text"></h3></div>';
    return el;
  }

  function showSkeleton(grid, count) {
    const frag = document.createDocumentFragment();
    for (let i = 0; i < count; i++) {
      frag.appendChild(createSkeletonCard());
    }
    grid.innerHTML = '';
    grid.appendChild(frag);
  }

  document.addEventListener('pager:loading', (e) => {
    const wrapper = e.target.closest('.list-with-skeleton');
    if (!wrapper) return;
    const grid = wrapper.querySelector('.cards-grid');
    if (!grid) return;
    const count = parseInt(wrapper.dataset.skeletonCount || '3', 10);
    showSkeleton(grid, count);
  });

  window.ctaSkeleton = { showSkeleton };
})();
