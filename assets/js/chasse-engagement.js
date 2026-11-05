(function () {
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('.cta-chasse-form');
    if (!form) return;
    e.preventDefault();
    const data = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin'
    })
      .then((res) => {
        if (res.ok) {
          window.location.reload();
        }
      });
  });
})();
