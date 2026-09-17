document.addEventListener('DOMContentLoaded', () => {
  const menuBtn = document.querySelector('.menu-btn');
  const nav = document.querySelector('.topbar nav');
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      menuBtn.classList.toggle('active', open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      nav.classList.remove('open');
      menuBtn.classList.remove('active');
      menuBtn.setAttribute('aria-expanded', 'false');
    }));
  }

  const header = document.querySelector('[data-header]');
  if (header && !header.classList.contains('compact')) {
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 45);
    onScroll(); window.addEventListener('scroll', onScroll, {passive:true});
  }

  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, {threshold:.1, rootMargin:'0px 0px -40px 0px'});
    revealItems.forEach(el => observer.observe(el));
  } else revealItems.forEach(el => el.classList.add('visible'));

  const addTicket = document.getElementById('add-ticket-type');
  const container = document.getElementById('ticket-types');
  const template = document.getElementById('ticket-type-template');
  if (addTicket && container && template) {
    addTicket.addEventListener('click', () => container.appendChild(template.content.cloneNode(true)));
    container.addEventListener('click', (event) => {
      const btn = event.target.closest('.remove-ticket');
      if (btn) btn.closest('.ticket-type-row').remove();
    });
  }
});
