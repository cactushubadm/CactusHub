
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

const phoneInput = document.querySelector('input[name="telefone"]');
if (phoneInput) {
  phoneInput.addEventListener('input', () => {
    let value = phoneInput.value.replace(/\D/g, '').slice(0, 11);
    if (value.length > 10) {
      value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
    } else if (value.length > 6) {
      value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
    } else if (value.length > 2) {
      value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    } else if (value.length > 0) {
      value = value.replace(/^(\d*)/, '($1');
    }
    phoneInput.value = value;
  });
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
      const payload = Object.fromEntries(formData.entries());
      const response = await fetch('/api/commerce/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
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


// V17 — Alternância visual entre categorias de produtos
const catalogSwitches = Array.from(document.querySelectorAll('[data-catalog-target]'));
const catalogPanes = Array.from(document.querySelectorAll('[data-catalog-pane]'));

if (catalogSwitches.length && catalogPanes.length) {
  const activateCatalog = (targetId, updateHash = true) => {
    catalogSwitches.forEach(button => {
      const active = button.dataset.catalogTarget === targetId;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', String(active));
    });

    catalogPanes.forEach(pane => {
      const active = pane.id === targetId;
      pane.hidden = !active;
      pane.classList.toggle('active', active);
    });

    if (updateHash) {
      history.replaceState(null, '', `#${targetId}`);
    }
  };

  catalogSwitches.forEach(button => {
    button.addEventListener('click', () => activateCatalog(button.dataset.catalogTarget));
  });

  const initialTarget = window.location.hash === '#sites-prontos' ? 'sites-prontos' : 'gestao-produtos';
  activateCatalog(initialTarget, false);
}


const checkoutLinks = Array.from(document.querySelectorAll('[data-checkout-product][data-checkout-plan]'));
checkoutLinks.forEach(link => {
  link.addEventListener('click', event => {
    event.preventDefault();
    const billing = document.body.classList.contains('billing-annual') ? 'annual' : 'monthly';
    const params = new URLSearchParams({
      product: link.dataset.checkoutProduct,
      plan: link.dataset.checkoutPlan,
      billing
    });
    window.location.href = 'checkout.html?' + params.toString();
  });
});


const runtimePlanPrices = Array.from(document.querySelectorAll('.plan-runtime-price[data-commerce-product][data-commerce-plan]'));
if (runtimePlanPrices.length) {
  const formatMoney = minor => 'R$ ' + Math.round(minor / 100).toLocaleString('pt-BR');
  const refreshRuntimePrices = async () => {
    try {
      const response = await fetch('/api/commerce/catalog', { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) return;
      const catalog = await response.json();
      runtimePlanPrices.forEach(node => {
        const product = catalog.products?.find(item => item.id === node.dataset.commerceProduct);
        const plan = product?.plans?.find(item => item.plan === node.dataset.commercePlan);
        if (!plan || plan.quoteOnly) return;
        const annual = document.body.classList.contains('billing-annual');
        const price = annual ? plan.annual : plan.monthly;
        if (price?.amountMinor) {
          node.textContent = formatMoney(price.amountMinor) + (annual ? '/ano' : '/mês');
        } else {
          node.textContent = 'Valor em definição';
        }
      });
    } catch {}
  };
  billingButtons.forEach(button => button.addEventListener('click', refreshRuntimePrices));
  refreshRuntimePrices();
}


const tierPlanPrices = {
  BASIC: { monthly: 29000, annual: 290000 },
  INTERMEDIATE: { monthly: 49000, annual: 490000 },
  ADVANCED: { monthly: 60000, annual: 600000 }
};
const tierPriceCards = Array.from(document.querySelectorAll('.corporate-price[data-plan-tier]'));
const renderTierPlanPrices = () => {
  const annual = document.body.classList.contains('billing-annual');
  tierPriceCards.forEach(node => {
    const tier = tierPlanPrices[node.dataset.planTier];
    if (!tier) return;
    const minor = annual ? tier.annual : tier.monthly;
    node.textContent = 'R$ ' + Math.round(minor / 100).toLocaleString('pt-BR') + (annual ? '/ano' : '/mês');
  });
};
billingButtons.forEach(button => button.addEventListener('click', renderTierPlanPrices));
renderTierPlanPrices();
