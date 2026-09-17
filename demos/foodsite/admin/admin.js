// Prévia das fotos com posição fixa.
document.querySelectorAll('[data-preview-input]').forEach(input => {
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if(!file) return;
    const slot = input.dataset.previewInput;
    const img = document.querySelector(`[data-preview-for="${slot}"]`);
    if(!img) return;
    const url = URL.createObjectURL(file);
    img.hidden = false;
    img.src = url;
    const card = input.closest('.photo-admin-card');
    const hint = card && card.querySelector('small');
    if(hint) hint.textContent = `Selecionada: ${file.name}`;
  });
});

// Galeria publicada: arrastar, subir/descer e excluir com possibilidade de desfazer.
const galleryList = document.getElementById('galleryList');
const galleryOrder = document.getElementById('galleryOrder');
let draggedItem = null;

function activeGalleryItems(){
  if(!galleryList) return [];
  return [...galleryList.querySelectorAll('.gallery-admin-item')].filter(item => !item.classList.contains('is-deleted'));
}

function syncGalleryOrder(){
  const items = activeGalleryItems();
  if(galleryOrder) galleryOrder.value = items.map(item => item.dataset.galleryId).join(',');
  items.forEach((item, index) => {
    const pos = item.querySelector('[data-position]');
    if(pos) pos.textContent = String(index + 1);
  });
}

function moveActiveItem(item, direction){
  if(!galleryList || !item || item.classList.contains('is-deleted')) return;
  const active = activeGalleryItems();
  const index = active.indexOf(item);
  const target = active[index + direction];
  if(!target) return;
  if(direction < 0) galleryList.insertBefore(item, target);
  else galleryList.insertBefore(target, item);
  syncGalleryOrder();
}

if(galleryList){
  galleryList.addEventListener('click', event => {
    const item = event.target.closest('.gallery-admin-item');
    if(!item) return;

    if(event.target.closest('[data-move-up]')) moveActiveItem(item, -1);
    if(event.target.closest('[data-move-down]')) moveActiveItem(item, 1);

    const deleteButton = event.target.closest('[data-delete-gallery]');
    if(deleteButton){
      const hidden = item.querySelector('[data-delete-input]');
      const deleting = !item.classList.contains('is-deleted');
      item.classList.toggle('is-deleted', deleting);
      if(hidden) hidden.value = deleting ? '1' : '0';
      deleteButton.textContent = deleting ? 'DESFAZER' : 'REMOVER';
      syncGalleryOrder();
    }
  });

  galleryList.addEventListener('dragstart', event => {
    const item = event.target.closest('.gallery-admin-item');
    if(!item || item.classList.contains('is-deleted')){
      event.preventDefault();
      return;
    }
    draggedItem = item;
    item.classList.add('is-dragging');
    if(event.dataTransfer){
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', item.dataset.galleryId || 'gallery');
    }
  });

  galleryList.addEventListener('dragover', event => {
    if(!draggedItem) return;
    event.preventDefault();
    const target = event.target.closest('.gallery-admin-item');
    if(!target || target === draggedItem || target.classList.contains('is-deleted')) return;
    const rect = target.getBoundingClientRect();
    const after = event.clientY > rect.top + rect.height / 2;
    galleryList.insertBefore(draggedItem, after ? target.nextSibling : target);
    syncGalleryOrder();
  });

  galleryList.addEventListener('dragend', () => {
    if(draggedItem) draggedItem.classList.remove('is-dragging');
    draggedItem = null;
    syncGalleryOrder();
  });

  syncGalleryOrder();
}

// Novas fotos: seleção múltipla, prévia, legenda e remoção antes de enviar.
const galleryNewInput = document.getElementById('galleryNewInput');
const galleryNewPreview = document.getElementById('galleryNewPreview');
const galleryNewWrap = document.getElementById('galleryNewWrap');
let pendingGallery = [];

function defaultTitle(filename){
  return String(filename || '')
    .replace(/\.[^.]+$/, '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function syncPendingFilesToInput(){
  if(!galleryNewInput || typeof DataTransfer === 'undefined') return;
  const transfer = new DataTransfer();
  pendingGallery.forEach(item => transfer.items.add(item.file));
  galleryNewInput.files = transfer.files;
}

function renderPendingGallery(){
  if(!galleryNewPreview || !galleryNewWrap) return;
  galleryNewPreview.innerHTML = '';
  galleryNewWrap.hidden = pendingGallery.length === 0;

  pendingGallery.forEach((item, index) => {
    const card = document.createElement('article');
    card.className = 'gallery-new-card';
    card.dataset.pendingIndex = String(index);

    const image = document.createElement('img');
    image.src = item.preview;
    image.alt = '';

    const body = document.createElement('div');
    body.className = 'gallery-new-card__body';

    const label = document.createElement('label');
    label.textContent = 'Legenda';
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'gallery_new_title[]';
    input.maxLength = 90;
    input.value = item.title;
    input.addEventListener('input', () => { item.title = input.value; });
    label.appendChild(input);

    const meta = document.createElement('small');
    meta.textContent = `${item.file.name} · ${(item.file.size / 1024 / 1024).toFixed(1)} MB`;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'gallery-new-remove';
    remove.textContent = 'REMOVER';
    remove.addEventListener('click', () => {
      URL.revokeObjectURL(item.preview);
      pendingGallery.splice(index, 1);
      syncPendingFilesToInput();
      renderPendingGallery();
    });

    body.append(label, meta, remove);
    card.append(image, body);
    galleryNewPreview.appendChild(card);
  });
}

if(galleryNewInput){
  galleryNewInput.addEventListener('change', () => {
    const incoming = [...(galleryNewInput.files || [])];
    if(!incoming.length) return;

    const room = Math.max(0, 12 - pendingGallery.length);
    incoming.slice(0, room).forEach(file => {
      if(!/^image\/(jpeg|png|webp)$/i.test(file.type)) return;
      pendingGallery.push({
        file,
        title: defaultTitle(file.name) || 'Foto do produto',
        preview: URL.createObjectURL(file)
      });
    });
    syncPendingFilesToInput();
    renderPendingGallery();
  });
}

const form = document.getElementById('contentForm');
if(form){
  form.addEventListener('submit', () => {
    syncGalleryOrder();
    syncUnitOrder();
    const btn = form.querySelector('.save-bar .primary');
    if(btn){ btn.disabled = true; btn.textContent = 'PUBLICANDO...'; }
  });
}


// Logo do cliente: pré-visualização imediata.
const logoInput = document.getElementById('logoInput');
const logoPreview = document.getElementById('logoPreview');
if(logoInput && logoPreview){
  logoInput.addEventListener('change', () => {
    const file = logoInput.files && logoInput.files[0];
    if(!file) return;
    logoPreview.src = URL.createObjectURL(file);
  });
}

// Unidades: adicionar, remover, reordenar e publicar/ocultar.
const unitList = document.getElementById('unitList');
const unitOrder = document.getElementById('unitOrder');
const addUnit = document.getElementById('addUnit');
const unitCount = document.getElementById('unitCount');
let draggedUnit = null;

function unitItems(){ return unitList ? [...unitList.querySelectorAll('.unit-admin-card')] : []; }
function syncUnitOrder(){
  const items = unitItems();
  if(unitOrder) unitOrder.value = items.map(el => el.dataset.unitId).join(',');
  if(unitCount) unitCount.textContent = `${items.length} unidade${items.length === 1 ? '' : 's'}`;
  items.forEach(el => {
    const name = el.querySelector('input[name^="unit_name"]');
    const title = el.querySelector('.unit-admin-head strong');
    if(name && title) title.textContent = name.value.trim() || 'Nova unidade';
  });
}
function moveUnit(item, direction){
  if(!unitList || !item) return;
  const items = unitItems();
  const index = items.indexOf(item);
  const target = items[index + direction];
  if(!target) return;
  if(direction < 0) unitList.insertBefore(item, target);
  else unitList.insertBefore(target, item);
  syncUnitOrder();
}
function makeUnitKey(){ return `u-${Date.now().toString(36)}-${Math.random().toString(36).slice(2,7)}`; }
function unitTemplate(key, number){
  return `<article class="unit-admin-card" data-unit-id="${key}" draggable="true">
    <div class="unit-admin-head"><button type="button" class="drag-handle unit-drag" title="Arrastar">⋮⋮</button><strong>Unidade ${String(number).padStart(2,'0')}</strong><label class="check"><input type="checkbox" name="unit_enabled[${key}]" checked><span>Publicada</span></label><button type="button" class="unit-remove" data-remove-unit>REMOVER</button></div>
    <div class="unit-admin-fields"><label>Nome<input name="unit_name[${key}]" value="Unidade ${String(number).padStart(2,'0')}" maxlength="80" required></label><label>Telefone<input name="unit_phone[${key}]" value="" maxlength="40" placeholder="(00) 00000-0000"></label><label class="wide">Endereço<input name="unit_address[${key}]" value="" maxlength="180" placeholder="Rua, número, bairro, cidade"></label><label>Link do mapa<input type="url" name="unit_maps[${key}]" value="" placeholder="https://maps.google.com/..."></label><label>Link do pedido<input type="url" name="unit_order_url[${key}]" value="" placeholder="https://..."></label></div>
    <div class="unit-order-actions"><button type="button" data-unit-up>↑ SUBIR</button><button type="button" data-unit-down>↓ DESCER</button></div>
  </article>`;
}

if(addUnit && unitList){
  addUnit.addEventListener('click', () => {
    const count = unitItems().length;
    if(count >= 20){ alert('O limite é de 20 unidades.'); return; }
    const key = makeUnitKey();
    unitList.insertAdjacentHTML('beforeend', unitTemplate(key, count + 1));
    syncUnitOrder();
    unitList.lastElementChild?.scrollIntoView({behavior:'smooth',block:'center'});
  });
}
if(unitList){
  unitList.addEventListener('input', event => {
    if(event.target.matches('input[name^="unit_name"]')) syncUnitOrder();
  });
  unitList.addEventListener('click', event => {
    const item = event.target.closest('.unit-admin-card'); if(!item) return;
    if(event.target.closest('[data-unit-up]')) moveUnit(item,-1);
    if(event.target.closest('[data-unit-down]')) moveUnit(item,1);
    if(event.target.closest('[data-remove-unit]')){
      if(unitItems().length <= 1){ alert('Mantenha pelo menos uma unidade cadastrada.'); return; }
      item.remove(); syncUnitOrder();
    }
  });
  unitList.addEventListener('dragstart', event => {
    const item = event.target.closest('.unit-admin-card'); if(!item) return;
    draggedUnit = item; item.classList.add('is-dragging');
    if(event.dataTransfer){ event.dataTransfer.effectAllowed='move'; event.dataTransfer.setData('text/plain',item.dataset.unitId||'unit'); }
  });
  unitList.addEventListener('dragover', event => {
    if(!draggedUnit) return; event.preventDefault();
    const target = event.target.closest('.unit-admin-card');
    if(!target || target === draggedUnit) return;
    const rect = target.getBoundingClientRect(); const after = event.clientY > rect.top + rect.height/2;
    unitList.insertBefore(draggedUnit, after ? target.nextSibling : target); syncUnitOrder();
  });
  unitList.addEventListener('dragend', () => { if(draggedUnit) draggedUnit.classList.remove('is-dragging'); draggedUnit=null; syncUnitOrder(); });
  syncUnitOrder();
}
