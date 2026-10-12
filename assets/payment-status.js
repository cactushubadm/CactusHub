(() => {
  'use strict';

  const icon=document.querySelector('[data-state-icon]');
  const title=document.querySelector('[data-state-title]');
  const text=document.querySelector('[data-state-text]');
  const details=document.querySelector('[data-payment-details]');
  const actions=document.querySelector('[data-payment-actions]');
  const money=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
  const params=new URLSearchParams(location.search);
  let orderId=params.get('order')||sessionStorage.getItem('cactusCommerceOrderId')||'';
  let subscriptionId=params.get('preapproval_id')||params.get('subscription_id')||sessionStorage.getItem('cactusCommerceSubscriptionId')||'';
  const savedStatusUrl=sessionStorage.getItem('cactusCommerceStatusUrl')||'';
  let attempts=0,timer=null;

  const LABELS={
    AWAITING_PAYMENT:['Aguardando confirmação','O Mercado Pago ainda não confirmou o pagamento.'],
    PAYMENT_CONFIRMED_SETUP_PENDING:['Pagamento confirmado','Seu pagamento foi aprovado e a ativação será iniciada.'],
    REQUESTED:['Pedido recebido','A Cactus Factory recebeu a ativação.'],
    VALIDATED:['Pedido validado','Pagamento e dados validados.'],
    BUILDING_RELEASE:['Preparando produto','Estamos montando a versão contratada.'],
    RELEASE_READY:['Produto preparado','A versão contratada está pronta para provisionamento.'],
    PROVISIONING:['Criando seu ambiente','Estamos criando sua instância exclusiva.'],
    CONFIGURING_DOMAIN:['Configurando o endereço','Seu domínio seguro está sendo configurado.'],
    STARTING:['Iniciando o sistema','Os serviços do ambiente estão sendo inicializados.'],
    HEALTHCHECK:['Validando o ambiente','Estamos executando os testes finais.'],
    ONLINE:['Seu sistema está pronto','Pagamento confirmado e ambiente disponível.'],
    PAYMENT_REVIEW_REQUIRED:['Pagamento em análise','A cobrança precisa de revisão antes de continuar.'],
    FAILED:['Ativação com pendência','A equipe Cactus Hub precisa revisar esta ativação.']
  };

  function esc(v=''){return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
  function amount(b){return Number.isSafeInteger(b?.amountMinor)?money.format(b.amountMinor/100)+(b.period==='annual'?' / ano':b.period==='monthly'?' / mês':''):'—'}
  function setState(kind,heading,message){
    if(icon){icon.className='customer-state-icon'+(kind==='success'?' success':kind==='problem'?' problem':'');icon.textContent=kind==='success'?'✓':kind==='problem'?'!':'…'}
    if(title)title.textContent=heading;
    if(text)text.textContent=message;
  }
  async function fetchJson(url){
    const res=await fetch(url,{cache:'no-store',credentials:'same-origin',headers:{Accept:'application/json'}});
    let body={};try{body=await res.json()}catch{}
    if(!res.ok)throw new Error(body?.error?.message||'Pedido não encontrado.');
    return body;
  }
  async function getStatus(){
    if(savedStatusUrl){try{return await fetchJson(savedStatusUrl)}catch{}}
    if(subscriptionId)return fetchJson('/api/commerce/subscriptions/'+encodeURIComponent(subscriptionId));
    throw new Error('Não foi possível recuperar automaticamente o identificador do pedido.');
  }
  function render(data){
    orderId=data.orderId||orderId;
    subscriptionId=data.subscriptionId||subscriptionId;
    const pair=LABELS[data.status]||['Atualizando seu pedido','Estamos consultando a situação mais recente.'];
    const kind=data.status==='ONLINE'?'success':(['FAILED','PAYMENT_REVIEW_REQUIRED'].includes(data.status)?'problem':'info');
    setState(kind,pair[0],pair[1]);
    if(details){
      details.innerHTML='<div class="customer-meta-grid">'+
        '<div class="customer-meta"><span>Pedido</span><strong class="customer-mono">'+esc(data.orderId||'—')+'</strong></div>'+
        '<div class="customer-meta"><span>Produto</span><strong>'+esc(data.product||'Cactus One')+'</strong></div>'+
        '<div class="customer-meta"><span>Plano</span><strong>'+esc(data.plan||'—')+'</strong></div>'+
        '<div class="customer-meta"><span>Valor</span><strong>'+esc(amount(data.billing))+'</strong></div>'+
        '<div class="customer-meta"><span>Pagamento</span><strong>'+(data.paymentConfirmed?'Confirmado':'Aguardando')+'</strong></div>'+
        '<div class="customer-meta"><span>Assinatura</span><strong>'+esc(data.subscriptionStatus||'—')+'</strong></div>'+
        '<div class="customer-meta"><span>Ativação</span><strong>'+esc(data.status||'—')+'</strong></div>'+
        '<div class="customer-meta"><span>Acesso</span><strong>'+(data.publicUrl?'Disponível':'Em preparação')+'</strong></div>'+
      '</div>';
    }
    if(actions){
      const links=[];
      if(data.publicUrl)links.push('<a class="btn btn-primary" href="'+esc(data.publicUrl)+'" target="_blank" rel="noopener noreferrer">Abrir meu sistema</a>');
      links.push('<a class="btn btn-outline-dark" href="/pedido.html'+(orderId?'?order='+encodeURIComponent(orderId):'')+'">'+(data.status==='ONLINE'&&data.setupRequired?'Receber primeiro acesso por e-mail':'Consultar meus pedidos')+'</a>');
      links.push('<a class="btn btn-outline-dark" href="/produtos.html">Voltar aos produtos</a>');
      actions.innerHTML=links.join('');
    }
    if(data.status==='ONLINE'&&timer){clearTimeout(timer);timer=null}
  }
  async function poll(){
    attempts++;
    try{
      const data=await getStatus();
      render(data);
      if(data.status!=='ONLINE'&&attempts<50)timer=setTimeout(poll,3000);
    }catch(err){
      setState('problem','Não encontramos esse pedido',err.message);
      if(details)details.innerHTML='';
      if(actions)actions.innerHTML='<a class="btn btn-primary" href="/pedido.html'+(orderId?'?order='+encodeURIComponent(orderId):'')+'">Consultar com meus dados</a><a class="btn btn-outline-dark" href="/produtos.html">Voltar aos produtos</a>';
    }
  }
  poll();
})();