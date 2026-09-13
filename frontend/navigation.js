(() => {
  const header = document.querySelector('#navigation-section');
  const nav = header?.querySelector('#nav-bar');
  const links = nav?.querySelector('#nav-links');
  const actions = nav?.querySelector('#nav-buttons');
  if (!nav || !links || !actions) return;

  // Keep this breakpoint in sync with the navigation rules in common.css.
  const mobile = window.matchMedia('(max-width: 1100px)');
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'nav-toggle';
  toggle.setAttribute('aria-controls', `${links.id} ${actions.id}`);
  toggle.innerHTML = '<span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>';

  function setOpen(open, restoreFocus = false) {
    header.classList.toggle('is-menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    if (restoreFocus) toggle.focus();
  }

  toggle.addEventListener('click', () => {
    setOpen(toggle.getAttribute('aria-expanded') !== 'true');
  });

  header.addEventListener('keydown', event => {
    if (event.key === 'Escape' && mobile.matches && header.classList.contains('is-menu-open')) {
      event.preventDefault();
      setOpen(false, true);
    }
  });

  header.addEventListener('click', event => {
    if (mobile.matches && event.target.closest('a')) setOpen(false, true);
  });

  document.addEventListener('click', event => {
    if (mobile.matches && !header.contains(event.target)) {
      const focusInMenu = links.contains(document.activeElement) || actions.contains(document.activeElement);
      setOpen(false, focusInMenu);
    }
  });

  header.addEventListener('focusout', event => {
    if (mobile.matches && event.relatedTarget && !header.contains(event.relatedTarget)) setOpen(false);
  });

  mobile.addEventListener('change', () => {
    const focusInMenu = links.contains(document.activeElement) || actions.contains(document.activeElement);
    if (!mobile.matches && document.activeElement === toggle) nav.querySelector('#logo')?.focus();
    setOpen(false, mobile.matches && focusInMenu);
  });

  // Keep the original links visible if JavaScript is unavailable.
  setOpen(false);
  nav.insertBefore(toggle, links);
  header.classList.add('has-mobile-menu');
})();
