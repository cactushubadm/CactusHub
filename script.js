const header = document.querySelector('.site-header');
const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.primary-nav');
const year = document.querySelector('#year');
const form = document.querySelector('#contact-form');
const success = document.querySelector('.form-success');

year.textContent = new Date().getFullYear();

const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 20);
onScroll();
window.addEventListener('scroll', onScroll, { passive: true });

navToggle.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  navToggle.setAttribute('aria-expanded', String(open));
});
nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  nav.classList.remove('open');
  navToggle.setAttribute('aria-expanded', 'false');
}));

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const delay = entry.target.dataset.delay || 0;
    setTimeout(() => entry.target.classList.add('visible'), Number(delay));
    observer.unobserve(entry.target);
  });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

form.addEventListener('submit', (event) => {
  event.preventDefault();
  success.classList.add('show');
  form.querySelector('button[type="submit"]').textContent = 'Contato registrado ✓';
});


const productTabs = Array.from(document.querySelectorAll('.variant-card[data-product]'));
const productPanels = Array.from(document.querySelectorAll('.product-detail'));

if (productTabs.length && productPanels.length) {
  const activateProduct = (product) => {
    productTabs.forEach(tab => {
      const isActive = tab.dataset.product === product;
      tab.classList.toggle('active', isActive);
      tab.setAttribute('aria-selected', String(isActive));
    });

    productPanels.forEach(panel => {
      const isActive = panel.id === `product-detail-${product}`;
      panel.classList.toggle('active', isActive);
      panel.hidden = !isActive;
    });
  };

  productTabs.forEach(tab => {
    tab.addEventListener('click', () => activateProduct(tab.dataset.product));
  });
}
