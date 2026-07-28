(() => {
  const initialise = () => {
    const button = document.querySelector('.docs-menu-toggle');
    const sidebar = document.querySelector('.docs-sidebar');
    const backdrop = document.querySelector('.docs-menu-backdrop');
    const closeButton = document.querySelector('.docs-menu-close');
    if (!button || !sidebar || !backdrop) return;

    const mobile = () => window.matchMedia('(max-width: 760px)').matches;
    const setOpen = (open) => {
      const shouldOpen = open && mobile();
      sidebar.classList.toggle('is-open', shouldOpen);
      backdrop.classList.toggle('is-visible', shouldOpen);
      document.body.classList.toggle('docs-menu-open', shouldOpen);
      button.setAttribute('aria-expanded', String(shouldOpen));
      button.setAttribute('aria-label', shouldOpen ? 'Close documentation menu' : 'Open documentation menu');
    };

    button.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
    closeButton?.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.addEventListener('click', (event) => {
      if (event.target.closest('a') && mobile()) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', () => {
      if (!mobile()) setOpen(false);
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise, { once: true });
  } else {
    initialise();
  }
})();
