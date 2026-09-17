const FALLBACK_CONFIG = {
  brand: {
    businessName: 'SUA MARCA', businessSubname: 'RESTAURANTE & DELIVERY', categoryLabel: 'SEU NEGÓCIO, SUA IDENTIDADE',
    heroTitle1: 'SEU SABOR', heroTitle2: 'EM DESTAQUE.',
    heroText: 'Apresente sua marca, seus produtos e suas unidades em um site rápido, moderno e conectado ao seu sistema de pedidos.',
    showcaseEyebrow: 'COLOQUE SUAS FOTOS AQUI', showcaseTitle1: 'MOSTRE SEUS', showcaseTitle2: 'PRODUTOS.',
    showcaseText: 'Use fotos reais do seu cardápio para criar uma vitrine visual forte. Todas as imagens podem ser trocadas pelo painel administrativo.',
    aboutEyebrow: 'CONTE A SUA HISTÓRIA', aboutTitle1: 'SUA MARCA.', aboutTitle2: 'SEU JEITO.',
    aboutText: 'Use este espaço para contar a história do negócio, destacar diferenciais, atendimento, tradição, ingredientes, ambiente ou qualquer mensagem importante para seus clientes.',
    aboutBadge1: '✦ Destaque seu diferencial', aboutBadge2: '✦ Fale sobre sua experiência', aboutBadge3: '✦ Direcione para o pedido online',
    footerText: 'Seu negócio apresentado de forma profissional, rápida e fácil de atualizar.', instagramHandle: '@seuinstagram', instagramUrl: '',
    logo: 'assets/placeholders/client-logo.svg', accentColor: '#ff8a00', accentColor2: '#ff3b30'
  },
  units: [
    {id:'unidade-1',name:'Unidade 01',address:'Coloque o endereço da unidade aqui',phoneDisplay:'(00) 00000-0000',maps:'',orderUrl:'',enabled:true},
    {id:'unidade-2',name:'Unidade 02',address:'Coloque o endereço da unidade aqui',phoneDisplay:'(00) 00000-0000',maps:'',orderUrl:'',enabled:true}
  ],
  promotions: [1,2,3,4,5].map((weekday,i)=>({weekday,day:['SEG','TER','QUA','QUI','SEX'][i],fullDay:['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira'][i],title:`Promoção de ${['Segunda','Terça','Quarta','Quinta','Sexta'][i]}`,description:'Descreva aqui a oferta fixa do dia.',price:'R$ 00,00',orderUrl:'',direct:false,enabled:true})),
  images: {
    heroMain:'assets/placeholders/hero-main.svg',heroSmall:'assets/placeholders/hero-small.svg',featureBanner:'assets/placeholders/banner.svg',aboutImage:'assets/placeholders/about.svg',
    showcase1:'assets/placeholders/showcase-1.svg',showcase2:'assets/placeholders/showcase-2.svg',showcase3:'assets/placeholders/showcase-3.svg',showcase4:'assets/placeholders/showcase-4.svg',showcase5:'assets/placeholders/showcase-5.svg',showcase6:'assets/placeholders/showcase-6.svg'
  },
  showcase: [1,2,3,4,5,6].map(i=>({slot:`showcase${i}`,title:`PRODUTO ${String(i).padStart(2,'0')}`,subtitle:'Coloque seu produto aqui'})),
  gallery: [1,2,3,4,5,6].map(i=>({id:`demo-${i}`,src:`assets/placeholders/gallery-${i}.svg`,title:`Sua foto ${String(i).padStart(2,'0')}`}))
};

function escapeHtml(value=''){
  return String(value).replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
}
function validWebUrl(value=''){
  try{ const u=new URL(String(value)); return ['http:','https:'].includes(u.protocol); }catch{return false;}
}
function setText(id,value){ const el=document.getElementById(id); if(el && value !== undefined && value !== null) el.textContent=String(value); }
function setLink(el,url,label){
  if(!el) return;
  if(validWebUrl(url)){ el.href=url; el.hidden=false; if(label) el.textContent=label; }
  else { el.removeAttribute('href'); el.hidden=true; }
}

function applyBrand(brand={}){
  const b={...FALLBACK_CONFIG.brand,...brand};
  const root=document.documentElement;
  if(/^#[0-9a-f]{6}$/i.test(b.accentColor||'')) root.style.setProperty('--orange',b.accentColor);
  if(/^#[0-9a-f]{6}$/i.test(b.accentColor2||'')) root.style.setProperty('--red',b.accentColor2);
  root.style.setProperty('--brand-watermark', JSON.stringify((b.businessName || 'SUA MARCA').toUpperCase()));

  setText('heroEyebrow',b.categoryLabel); setText('heroTitle1',b.heroTitle1); setText('heroTitle2',b.heroTitle2); setText('heroText',b.heroText);
  setText('showcaseEyebrow',b.showcaseEyebrow); setText('showcaseTitle1',b.showcaseTitle1); setText('showcaseTitle2',b.showcaseTitle2); setText('showcaseText',b.showcaseText);
  setText('aboutEyebrow',b.aboutEyebrow); setText('aboutTitle1',b.aboutTitle1); setText('aboutTitle2',b.aboutTitle2); setText('aboutText',b.aboutText);
  setText('badge1',b.aboutBadge1); setText('badge2',b.aboutBadge2); setText('badge3',b.aboutBadge3); setText('footerText',b.footerText);
  setText('heroSocial',b.instagramHandle || b.businessName || 'SUA MARCA'); setText('stampBrand',b.businessName || 'SUA MARCA'); setText('stampSubname',b.businessSubname || 'SEU JEITO');

  const logo=b.logo || FALLBACK_CONFIG.brand.logo;
  ['siteLogo','footerLogo'].forEach(id=>{ const el=document.getElementById(id); if(el) {el.src=logo; el.alt=`Logo ${b.businessName || 'da empresa'}`;} });
  document.title=`${b.businessName || 'Sua Marca'} | Cardápio e unidades`;

  const instaLabel=(b.instagramHandle || 'Instagram')+' ↗';
  setLink(document.getElementById('navInstagram'),b.instagramUrl,'Instagram ↗');
  setLink(document.getElementById('instagramLink'),b.instagramUrl,instaLabel);
}

function orderButton(url,label='ABRIR CARDÁPIO'){
  if(validWebUrl(url)) return `<a class="unit-card__order" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer"><span>${label}</span><b>↗</b></a>`;
  return `<span class="unit-card__order unit-card__order--disabled" aria-disabled="true"><span>CONFIGURE O LINK</span><b>—</b></span>`;
}

function renderUnits(units=[]){
  const active=units.filter(u=>u && u.enabled !== false);
  setText('statUnits',String(active.length));
  const grid=document.getElementById('unitGrid');
  if(!grid) return;
  if(!active.length){grid.innerHTML='<div class="empty-state">Cadastre pelo menos uma unidade no painel administrativo.</div>'; return;}
  grid.innerHTML=active.map((u,i)=>`
    <article class="unit-card">
      <div><div class="unit-card__top"><span>UNIDADE</span><div class="unit-card__number">${String(i+1).padStart(2,'0')}</div></div>
      <h3>${escapeHtml(u.name || `Unidade ${i+1}`)}</h3><p>${escapeHtml(u.address || 'Endereço a configurar')}</p>${u.phoneDisplay?`<p class="unit-card__phone">${escapeHtml(u.phoneDisplay)}</p>`:''}</div>
      ${orderButton(u.orderUrl)}
    </article>`).join('');
}

function renderLocations(units=[]){
  const active=units.filter(u=>u && u.enabled !== false);
  const list=document.getElementById('locationList');
  if(!list) return;
  if(!active.length){list.innerHTML='<div class="empty-state">Nenhuma unidade publicada.</div>'; return;}
  list.innerHTML=active.map((u,i)=>{
    const map=validWebUrl(u.maps) ? `<a class="icon-btn" href="${escapeHtml(u.maps)}" target="_blank" rel="noopener noreferrer" title="Ver no mapa" aria-label="Ver ${escapeHtml(u.name)} no mapa">⌖</a>` : '';
    const order=validWebUrl(u.orderUrl) ? `<a class="location-order" href="${escapeHtml(u.orderUrl)}" target="_blank" rel="noopener noreferrer">CARDÁPIO ↗</a>` : '<span class="location-order location-order--disabled">SEM LINK</span>';
    return `<article class="location-row"><div class="location-row__num">${String(i+1).padStart(2,'0')}</div><div><h3>${escapeHtml(u.name||'Unidade')}</h3><p>${escapeHtml(u.address||'Endereço a configurar')}</p>${u.phoneDisplay?`<p>${escapeHtml(u.phoneDisplay)}</p>`:''}</div><div class="location-row__actions">${map}${order}</div></article>`;
  }).join('');
}

function renderPromotions(promotions=[]){
  const active=promotions.filter(p=>p && p.enabled !== false);
  const promoList=document.getElementById('promoList'); if(!promoList) return;
  if(!active.length){document.getElementById('promocoes')?.setAttribute('hidden',''); return;}
  document.getElementById('promocoes')?.removeAttribute('hidden');
  const today=new Date().getDay();
  promoList.innerHTML=active.map(p=>{
    const cta=validWebUrl(p.orderUrl) ? `<a class="promo-card__cta" href="${escapeHtml(p.orderUrl)}" target="_blank" rel="noopener noreferrer"><span>${p.direct?'PEDIR ESTA OFERTA':'ABRIR CARDÁPIO'}</span><b>↗</b></a>` : `<span class="promo-card__cta promo-card__cta--disabled"><span>CONFIGURE O LINK</span><b>—</b></span>`;
    return `<article class="promo-card ${today===Number(p.weekday)?'is-today':''}"><div class="promo-card__day"><small>PROMO</small><strong>${escapeHtml(p.day)}</strong></div><div class="promo-card__copy"><span class="promo-card__tag">${escapeHtml(p.fullDay)}</span><h3>${escapeHtml(p.title)}</h3><p>${escapeHtml(p.description||'')}</p><div class="promo-card__price"><small>OFERTA</small><strong>${escapeHtml(p.price||'')}</strong></div></div>${cta}</article>`;
  }).join('');
}

function renderGallery(items=[]){
  const gallery=document.getElementById('foodGallery'); if(!gallery) return;
  const safe=Array.isArray(items)?items.filter(i=>i&&i.src):[];
  gallery.hidden=safe.length===0;
  gallery.innerHTML=safe.map(i=>`<figure><img src="${escapeHtml(i.src)}" alt="${escapeHtml(i.title||'Foto do produto')}" loading="lazy" decoding="async"><figcaption>${escapeHtml(i.title||'Produto')}</figcaption></figure>`).join('');
}

function applyShowcase(items=[]){
  (Array.isArray(items)?items:[]).slice(0,6).forEach((item,i)=>{
    const t=document.querySelector(`[data-showcase-title="${i}"]`); const s=document.querySelector(`[data-showcase-subtitle="${i}"]`);
    if(t && item.title) t.textContent=item.title; if(s && item.subtitle) s.textContent=item.subtitle;
  });
}

function applyImages(images={}){
  document.querySelectorAll('img[data-image-slot]').forEach(img=>{ const src=images[img.dataset.imageSlot]; if(src) img.src=src; });
  const bg=(selector,slot,overlay='')=>{const el=document.querySelector(selector); const src=images[slot]; if(el&&src) el.style.backgroundImage=`${overlay?overlay+',':''}url("${String(src).replace(/"/g,'%22')}")`;};
  bg('.hero__photo--main','heroMain','linear-gradient(180deg,transparent 48%,rgba(0,0,0,.64))');
  bg('.hero__photo--small','heroSmall');
  bg('.feature-banner__photo','featureBanner','linear-gradient(90deg,transparent 55%,rgba(23,23,27,.75))');
  bg('.about__image-layer','aboutImage','linear-gradient(0deg,rgba(0,0,0,.75),transparent 60%)');
}

async function loadConfig(){
  try{ const r=await fetch(`api/site-config.php?v=${Date.now()}`,{cache:'no-store'}); if(!r.ok) throw new Error(); const d=await r.json(); return d&&typeof d==='object'?d:FALLBACK_CONFIG; }
  catch{return FALLBACK_CONFIG;}
}

(async function init(){
  const config=await loadConfig();
  applyBrand(config.brand||FALLBACK_CONFIG.brand);
  renderUnits(Array.isArray(config.units)?config.units:FALLBACK_CONFIG.units);
  renderLocations(Array.isArray(config.units)?config.units:FALLBACK_CONFIG.units);
  renderPromotions(Array.isArray(config.promotions)?config.promotions:FALLBACK_CONFIG.promotions);
  renderGallery(Array.isArray(config.gallery)?config.gallery:FALLBACK_CONFIG.gallery);
  applyShowcase(Array.isArray(config.showcase)?config.showcase:FALLBACK_CONFIG.showcase);
  applyImages(config.images||FALLBACK_CONFIG.images);
})();
