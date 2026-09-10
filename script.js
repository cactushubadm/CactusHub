
const header = document.querySelector('.site-header');
const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.primary-nav');
const year = document.querySelector('#year');
const form = document.querySelector('#contact-form');
const success = document.querySelector('.form-success');

if (year) year.textContent = new Date().getFullYear();

if (header) {
  const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 20);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
}

if (navToggle && nav) {
  navToggle.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', String(open));
  });

  nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    nav.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
  }));
}

const pageName = document.body?.dataset.page;
if (pageName && nav) {
  const active = nav.querySelector(`[data-nav="${pageName}"]`);
  if (active) active.classList.add('active');
}

if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const delay = Number(entry.target.dataset.delay || 0);
      setTimeout(() => entry.target.classList.add('visible'), delay);
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
} else {
  document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
}

if (form && success) {
  const params = new URLSearchParams(window.location.search);
  const interest = params.get('interest');
  const select = form.querySelector('select[name="interesse"]');

  if (interest && select) {
    const existing = Array.from(select.options).find(opt => opt.text === interest);
    if (existing) {
      select.value = interest;
    } else {
      const option = new Option(interest, interest, true, true);
      select.add(option);
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.innerHTML : '';
    const formData = new FormData(form);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Enviando...';
    }

    try {
      const response = await fetch('/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(formData).toString()
      });

      if (!response.ok) throw new Error('Falha no envio');

      success.classList.add('show');
      if (submitButton) submitButton.textContent = 'Contato enviado ✓';
      form.reset();
    } catch (error) {
      alert('Não foi possível enviar agora. Tente novamente ou entre em contato conosco diretamente.');
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
      }
      return;
    }

    if (submitButton) submitButton.disabled = false;
  });
}

const productTabs = Array.from(document.querySelectorAll('.product-quick-card[data-product]'));
const productPanels = Array.from(document.querySelectorAll('.product-sales-panel'));

if (productTabs.length && productPanels.length) {
  const activateProduct = (product) => {
    productTabs.forEach(tab => {
      const active = tab.dataset.product === product;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', String(active));
    });

    productPanels.forEach(panel => {
      const active = panel.id === `product-detail-${product}`;
      panel.hidden = !active;
      panel.classList.toggle('active', active);
    });
  };

  productTabs.forEach(tab => {
    tab.addEventListener('click', () => activateProduct(tab.dataset.product));
  });
}

const billingButtons = Array.from(document.querySelectorAll('.billing-btn'));
const dynamicPrices = Array.from(document.querySelectorAll('.dynamic-price'));
const dynamicPeriods = Array.from(document.querySelectorAll('.dynamic-period'));
const dynamicNotes = Array.from(document.querySelectorAll('.dynamic-price-note'));

if (billingButtons.length) {
  const updateBilling = (mode) => {
    const annual = mode === 'annual';
    document.body.classList.toggle('billing-annual', annual);
    document.body.classList.toggle('billing-monthly', !annual);

    billingButtons.forEach(button => {
      button.classList.toggle('active', button.dataset.billing === mode);
    });

    dynamicPrices.forEach(item => {
      item.textContent = annual ? item.dataset.annual : item.dataset.monthly;
    });

    dynamicPeriods.forEach(item => {
      item.textContent = annual ? item.dataset.annual : item.dataset.monthly;
    });

    dynamicNotes.forEach(item => {
      item.textContent = annual ? item.dataset.annual : item.dataset.monthly;
    });
  };

  billingButtons.forEach(button => {
    button.addEventListener('click', () => updateBilling(button.dataset.billing));
  });

  updateBilling('monthly');
}
