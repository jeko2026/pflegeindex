(() => {
  const button = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');

  if (!button || !nav) return;

  button.addEventListener('click', () => {
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    nav.classList.toggle('is-open', !isOpen);
  });
})();
