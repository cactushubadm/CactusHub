const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const money=v=>Number(v).toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0});
const num=(v,u)=>u==='R$'?money(v):u==='%'?Number(v).toLocaleString('pt-BR',{maximumFractionDigits:1})+'%':Number(v).toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+u;
const chartBars=[82,65,74,88,71,79],chartLabels=['Receita','Margem','Compras','Estoque','Frota','Safra'];
let DATA=null;

async function loadData(){
  if(DATA)return DATA;
  const r=await fetch('data.json',{cache:'no-store'});
  if(!r.ok)throw new Error('Base demonstrativa indisponível.');
  DATA=await r.json();
  return DATA;
}
function badge(status=''){
  const s=String(status).toLowerCase();
  const cls=s.includes('crítico')||s.includes('critico')||s.includes('atenção')||s.includes('atencao')||s.includes('em aprovação')?'att':s.includes('manutenção')||s.includes('programado')||s.includes('negociação')?'warn':'';
  return '<span class="badge '+cls+'">'+status+'</span>';
}
function kpis(cards){
  return '<div class="kpis">'+cards.map(x=>'<article class="kpi"><small>'+x.label+'</small><strong>'+num(x.value,x.unit)+'</strong><div class="trend '+(Number(x.trend)<0?'neg':'')+'">'+(Number(x.trend)>=0?'▲':'▼')+' '+Math.abs(Number(x.trend)).toFixed(1)+'% • '+x.note+'</div></article>').join('')+'</div>';
}
function chart(){
  const max=Math.max(...chartBars,1);
  return '<div class="chart">'+chartBars.map((v,i)=>'<div class="bar" style="height:'+(35+(v/max)*65)+'%"><span>'+chartLabels[i]+'</span></div>').join('')+'</div>';
}
function table(headers,rows){
  return '<div class="table-wrap"><table class="table"><thead><tr>'+headers.map(h=>'<th>'+h+'</th>').join('')+'</tr></thead><tbody>'+rows.join('')+'</tbody></table></div>';
}
const sectionKpis=(d,s)=>d.kpis.filter(x=>x.section===s);

async function renderDashboard(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'dashboard'))+
    '<div class="grid-2">'+
      '<section class="card"><h3>Visão de performance da operação</h3><p>Indicadores estratégicos da base demonstrativa Manga Bela.</p>'+chart()+'</section>'+
      '<section class="card"><h3>Atividades recentes</h3>'+
        d.activities.slice().sort((a,b)=>String(b.date).localeCompare(String(a.date))).map(a=>'<div class="activity"><div><b>'+a.title+'</b><small>'+a.module+' • '+a.detail+'</small></div><div>'+badge(a.status)+'</div></div>').join('')+
      '</section>'+
    '</div>'+
    '<section class="card"><h3>Observação da demonstração</h3><div class="notice">'+d.meta.notice+'</div></section>';
}
async function renderFinance(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'finance'))+
  '<section class="card"><h3>Fluxo financeiro da safra</h3>'+table(['Tipo','Descrição','Categoria','Vencimento','Status','Valor'],d.finance.map(x=>'<tr><td>'+x.type+'</td><td>'+x.description+'</td><td>'+x.category+'</td><td>'+x.due_date+'</td><td>'+badge(x.status)+'</td><td>'+money(x.amount)+'</td></tr>'))+'</section>';
}
async function renderPurchases(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'purchases'))+
  '<section class="card"><h3>Compras e negociações</h3>'+table(['Pedido','Fornecedor','Item','Status','Valor','Saving','Data'],d.purchases.map(x=>'<tr><td>'+x.code+'</td><td>'+x.supplier+'</td><td>'+x.item+'</td><td>'+badge(x.status)+'</td><td>'+money(x.amount)+'</td><td>'+money(x.saving)+'</td><td>'+x.date+'</td></tr>'))+'</section>';
}
async function renderInventory(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'inventory'))+
  '<section class="card"><h3>Almoxarifado e insumos</h3>'+table(['SKU','Item','Categoria','Estoque','Mínimo','Valor','Status'],d.inventory.map(x=>'<tr><td>'+x.sku+'</td><td>'+x.item+'</td><td>'+x.category+'</td><td>'+Number(x.stock).toLocaleString('pt-BR')+' '+x.unit+'</td><td>'+Number(x.min_stock).toLocaleString('pt-BR')+' '+x.unit+'</td><td>'+money(x.value)+'</td><td>'+badge(x.status)+'</td></tr>'))+'</section>';
}
async function renderFleet(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'fleet'))+
  '<section class="card"><h3>Frota e máquinas</h3>'+table(['Ativo','Tipo','Status','Horímetro/km','Disponibilidade','Combustível','Custo/mês'],d.fleet.map(x=>'<tr><td>'+x.asset+'</td><td>'+x.type+'</td><td>'+badge(x.status)+'</td><td>'+Number(x.meter).toLocaleString('pt-BR')+'</td><td>'+Number(x.availability).toLocaleString('pt-BR',{maximumFractionDigits:1})+'%</td><td>'+Number(x.fuel).toLocaleString('pt-BR')+' L</td><td>'+money(x.monthly_cost)+'</td></tr>'))+'</section>';
}
async function renderSales(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'sales'))+
  '<section class="card"><h3>Comercial e contratos</h3>'+table(['Cliente','Produto','Etapa','Valor','Probabilidade','Previsão'],d.sales.map(x=>'<tr><td>'+x.customer+'</td><td>'+x.product+'</td><td>'+badge(x.stage)+'</td><td>'+money(x.value)+'</td><td>'+Number(x.probability).toLocaleString('pt-BR')+'%</td><td>'+x.expected_date+'</td></tr>'))+'</section>';
}
async function renderFarming(){
  const d=await loadData();
  $('#content').innerHTML=kpis(sectionKpis(d,'farming'))+
  '<section class="card"><h3>Produção agrícola e talhões</h3>'+table(['Talhão','Cultura','Área','Produtividade','Colhido','Status','Observação'],d.farming.map(x=>'<tr><td>'+x.field+'</td><td>'+x.crop+'</td><td>'+Number(x.area).toLocaleString('pt-BR')+' ha</td><td>'+Number(x.productivity).toLocaleString('pt-BR',{maximumFractionDigits:1})+' t/ha</td><td>'+Number(x.harvested).toLocaleString('pt-BR')+' t</td><td>'+badge(x.status)+'</td><td>'+x.note+'</td></tr>'))+'</section>';
}
const pages={dashboard:renderDashboard,finance:renderFinance,purchases:renderPurchases,inventory:renderInventory,fleet:renderFleet,sales:renderSales,farming:renderFarming};
const titles={dashboard:'Visão Executiva',finance:'Financeiro',purchases:'Compras',inventory:'Almoxarifado',fleet:'Frota & Máquinas',sales:'Comercial',farming:'Produção Agrícola'};
async function openPage(name){
  $$('#appView [data-page]').forEach(b=>b.classList.toggle('active',b.dataset.page===name));
  $('#pageTitle').textContent=titles[name]||'Cactus One Agro';
  $('#content').innerHTML='<section class="card"><p>Carregando dados...</p></section>';
  try{await(pages[name]||renderDashboard)()}catch(e){$('#content').innerHTML='<section class="error">Falha ao carregar a demonstração: '+e.message+'</section>'}
}
$('#enterBtn').addEventListener('click',async()=>{
  try{
    await loadData();
    $('#loginView').classList.add('hidden');
    $('#appView').classList.remove('hidden');
    openPage('dashboard');
  }catch(e){alert(e.message)}
});
$$('#appView [data-page]').forEach(b=>b.addEventListener('click',()=>openPage(b.dataset.page)));
$('#logoutBtn').addEventListener('click',()=>location.reload());
