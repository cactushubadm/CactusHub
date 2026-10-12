(() => {
  'use strict';

  const form=document.querySelector('[data-order-lookup-form]');
  const results=document.querySelector('[data-order-results]');
  const alertBox=document.querySelector('[data-order-alert]');
  const submitBtn=form?.querySelector('button[type="submit"]');
  const money=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
  const dateFmt=new Intl.DateTimeFormat('pt-BR',{dateStyle:'medium',timeStyle:'short'});
  let identity=null;

  const STATUS={
    AWAITING_PAYMENT:{label:'Aguardando pagamento',tone:'pending',stage:1,desc:'Finalize o pagamento para iniciar a ativação.'},
    PAYMENT_CONFIRMED_SETUP_PENDING:{label:'Pagamento confirmado',tone:'running',stage:2,desc:'Pagamento aprovado. A ativação está sendo preparada.'},
    PAYMENT_REVIEW_REQUIRED:{label:'Pagamento em análise',tone:'problem',stage:1,desc:'O pagamento precisa de revisão antes de continuar.'},
    REQUESTED:{label:'Pedido recebido',tone:'running',stage:2,desc:'A Factory recebeu a solicitação.'},
    VALIDATED:{label:'Pedido validado',tone:'running',stage:2,desc:'Dados e pagamento validados.'},
    BUILDING_RELEASE:{label:'Preparando produto',tone:'running',stage:2,desc:'Montando a versão contratada.'},
    RELEASE_READY:{label:'Produto preparado',tone:'running',stage:2,desc:'A versão está pronta para provisionamento.'},
    PROVISIONING:{label:'Criando ambiente',tone:'running',stage:2,desc:'Criando a instância exclusiva do cliente.'},
    CONFIGURING_DOMAIN:{label:'Configurando domínio',tone:'running',stage:3,desc:'Configurando o endereço seguro do sistema.'},
    STARTING:{label:'Iniciando sistema',tone:'running',stage:3,desc:'Inicializando os serviços do ambiente.'},
    HEALTHCHECK:{label:'Validando ambiente',tone:'running',stage:3,desc:'Executando os testes finais de disponibilidade.'},
    ONLINE:{label:'Ambiente disponível',tone:'online',stage:4,desc:'Seu sistema está online e pronto para acesso.'},
    FAILED:{label:'Ativação com pendência',tone:'problem',stage:3,desc:'A ativação precisa de intervenção da equipe Cactus Hub.'},
    CANCELLED:{label:'Cancelado',tone:'problem',stage:1,desc:'Este pedido foi cancelado.'}
  };

  function esc(value=''){return String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
  function digits(value=''){return String(value).replace(/\D/g,'')}
  function mensagemPt(message,status){
    const msg=String(message||'').trim();
    const lower=msg.toLowerCase();
    if(status===404 || lower.includes('not found') || lower.includes('route was not found')) return 'Não foi possível localizar esta consulta agora.';
    if(status===429 || lower.includes('too many requests')) return 'Muitas consultas em sequência. Aguarde um instante e tente novamente.';
    if(lower.includes('invalid json')) return 'Os dados enviados são inválidos.';
    if(lower.includes('payload exceeds')) return 'Os dados enviados excedem o limite permitido.';
    if(lower.includes('valid requestid is required')) return 'Não foi possível identificar esta solicitação.';
    if(lower.includes('tracking identity changed')) return 'Os dados de acompanhamento deste pedido foram alterados.';
    return msg || 'Não foi possível concluir a consulta.';
  }
  async function api(path,options={}){
    const res=await fetch(path,{...options,headers:{'Content-Type':'application/json','Accept':'application/json',...(options.headers||{})},credentials:'same-origin',cache:'no-store'});
    let body={};try{body=await res.json();}catch{}
    if(!res.ok)throw new Error(mensagemPt(body?.error?.message,res.status));
    return body;
  }
  function setAlert(message='',tone='info'){
    if(!alertBox)return;
    alertBox.textContent=message;
    alertBox.className='customer-alert'+(message?' show '+tone:'');
  }
  function maskDocument(type,last4){
    if(!last4)return '—';
    return type==='CNPJ'?'CNPJ •••• '+last4:'CPF •••• '+last4;
  }
  function formatDate(value){
    if(!value)return '—';
    const d=new Date(value);
    return Number.isNaN(d.getTime())?'—':dateFmt.format(d);
  }
  function formatAmount(billing){
    if(!Number.isSafeInteger(billing?.amountMinor))return '—';
    return money.format(billing.amountMinor/100)+(billing.period==='annual'?' / ano':billing.period==='monthly'?' / mês':'');
  }
  function statusMeta(order){
    return STATUS[order.status]||{label:order.status||'Em processamento',tone:'running',stage:2,desc:'Estamos atualizando o status do seu pedido.'};
  }
  function steps(order){
    const meta=statusMeta(order),problem=meta.tone==='problem',current=Math.max(1,Math.min(4,meta.stage||1));
    const labels=[['Pedido','Pedido registrado'],['Pagamento','Cobrança confirmada'],['Ativação','Ambiente e domínio'],['Disponível','Sistema pronto']];
    return labels.map((item,index)=>{
      const n=index+1;
      let cls='';
      if(problem&&n===current)cls='problem';
      else if(n<current||(order.status==='ONLINE'&&n===4))cls='done';
      else if(n===current)cls='active';
      return '<div class="customer-step '+cls+'"><strong>'+item[0]+'</strong><span>'+item[1]+'</span></div>';
    }).join('');
  }
  function actions(order){
    const out=[];
    if(order.paymentUrl&&!order.paymentConfirmed)out.push('<a class="btn btn-primary" href="'+esc(order.paymentUrl)+'" rel="noopener">Continuar pagamento</a>');
    if(order.publicUrl)out.push('<a class="btn btn-primary" href="'+esc(order.publicUrl)+'" target="_blank" rel="noopener noreferrer">Abrir meu sistema</a>');
    if(order.status==='ONLINE'&&order.setupRequired&&identity)out.push('<button class="btn btn-outline-dark" type="button" data-send-access="'+esc(order.orderId)+'">Enviar primeiro acesso por e-mail</button>');
    return out.join('');
  }
  function card(order){
    const meta=statusMeta(order);
    const payment=order.paymentConfirmed?'Confirmado':(order.subscriptionStatus==='authorized'?'Autorizado':'Pendente');
    const doc=maskDocument(order.documentType,order.documentLast4);
    const security=order.status==='ONLINE'&&order.setupRequired
      ? '<div class="customer-security-note"><strong>Primeiro acesso protegido.</strong> '+(identity?'Use “Enviar primeiro acesso por e-mail” para receber o link seguro no endereço cadastrado.':'Para receber o link que cria o administrador, informe seus dados acima e clique em “Consultar”.')+'</div>'
      : '';
    return '<article class="customer-order-card">'+
      '<div class="customer-order-head">'+
        '<div class="customer-order-title"><div class="customer-product-icon">C1</div><div><h2>'+esc(order.productName||'Cactus One')+'</h2><p>Pedido <span class="customer-mono">'+esc(order.orderId)+'</span></p></div></div>'+
        '<span class="customer-badge '+meta.tone+'">'+esc(meta.label)+'</span>'+
      '</div>'+
      '<div class="customer-order-body">'+
        '<div class="customer-meta-grid">'+
          '<div class="customer-meta"><span>Plano</span><strong>'+esc(order.planName||order.plan||'—')+'</strong></div>'+
          '<div class="customer-meta"><span>Valor</span><strong>'+esc(formatAmount(order.billing))+'</strong></div>'+
          '<div class="customer-meta"><span>Pagamento</span><strong>'+esc(payment)+'</strong></div>'+
          '<div class="customer-meta"><span>Documento</span><strong>'+esc(doc)+'</strong></div>'+
          '<div class="customer-meta"><span>Cliente</span><strong>'+esc(order.customerName||'—')+'</strong></div>'+
          '<div class="customer-meta"><span>Criado em</span><strong>'+esc(formatDate(order.createdAt))+'</strong></div>'+
          '<div class="customer-meta"><span>Ativação</span><strong>'+esc(order.provisioning?'Em andamento':'—')+'</strong></div>'+
          '<div class="customer-meta"><span>Status</span><strong>'+esc(meta.desc)+'</strong></div>'+
        '</div>'+
        '<div class="customer-progress" aria-label="Etapas do pedido">'+steps(order)+'</div>'+
        '<div class="customer-order-actions">'+actions(order)+'</div>'+security+
      '</div>'+
    '</article>';
  }
  function render(data){
    if(!results)return;
    if(!data.orders?.length){
      results.innerHTML='<div class="customer-lookup-card customer-empty"><strong>Nenhum pedido encontrado</strong>Confira se os dados são os mesmos informados na contratação. Para pedidos antigos, use o número do pedido junto com o e-mail.</div>';
      return;
    }
    results.innerHTML=data.orders.map(card).join('');
    results.querySelectorAll('[data-send-access]').forEach(btn=>btn.addEventListener('click',()=>sendAccess(btn.dataset.sendAccess,btn)));
  }
  async function sendAccess(orderId,button){
    if(!identity)return;
    const original=button.textContent;
    button.disabled=true;button.textContent='Enviando...';setAlert('');
    try{
      const data=await api('/api/commerce/customer/orders/'+encodeURIComponent(orderId)+'/send-access',{method:'POST',body:JSON.stringify(identity)});
      setAlert(data.setupRequired===false?'Seu ambiente já está configurado. Use “Abrir meu sistema”.':'Link de primeiro acesso enviado para o e-mail cadastrado no pedido.','success');
      button.textContent='E-mail enviado';
    }catch(err){
      setAlert(err.message,'error');button.textContent=original;
    }finally{button.disabled=false}
  }
  async function submit(event){
    event.preventDefault();setAlert('');
    const email=form.email.value.trim().toLowerCase();
    const documentValue=form.document.value.trim();
    const orderId=form.orderId.value.trim();
    const d=digits(documentValue);
    const documentOk=d.length===11||d.length===14;
    const orderOk=/^[A-Za-z0-9_-]{20,64}$/.test(orderId);
    if(!email){setAlert('Informe o e-mail usado na contratação.','error');return}
    if(!documentOk&&!orderOk){setAlert('Informe um CPF/CNPJ válido. Para pedidos antigos sem documento, informe o número do pedido.','error');return}
    identity={email};
    if(documentOk)identity.document=documentValue;
    if(orderOk)identity.orderId=orderId;
    submitBtn?.classList.add('customer-loading');if(submitBtn)submitBtn.disabled=true;if(results)results.innerHTML='';
    try{
      const data=await api('/api/commerce/customer/orders/lookup',{method:'POST',body:JSON.stringify(identity)});
      render(data);
      if(data.orders?.length)setAlert('Consulta atualizada diretamente pela Cactus Factory.','success');
    }catch(err){setAlert(err.message,'error')}
    finally{submitBtn?.classList.remove('customer-loading');if(submitBtn)submitBtn.disabled=false}
  }
  function documentMask(input){
    const d=digits(input.value).slice(0,14);
    input.value=d.length<=11
      ? d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2')
      : d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d{1,2})$/,'$1-$2');
  }

  async function restoreProtectedCheckoutStatus(){
    const params=new URLSearchParams(location.search);
    const rememberedEmail=sessionStorage.getItem('cactusCommerceEmail')||'';
    if(rememberedEmail&&form?.email)form.email.value=rememberedEmail;
    const order=params.get('order')||sessionStorage.getItem('cactusCommerceOrderId')||'';
    const token=params.get('token')||'';
    const saved=sessionStorage.getItem('cactusCommerceStatusUrl')||'';
    const statusUrl=saved||(order&&token?'/api/commerce/orders/'+encodeURIComponent(order)+'?token='+encodeURIComponent(token):'');
    if(!statusUrl)return;
    try{
      const data=await api(statusUrl,{method:'GET'});
      const productNames={buildhub:'Cactus One Build',agrohub:'Cactus One Agro',minehub:'Cactus One Miner',logihub:'Cactus One Route'};
      const planNames={BASIC:'Básico',INTERMEDIATE:'Intermediário',ADVANCED:'Avançado',CUSTOM:'Personalizado'};
      render({orders:[{
        ...data,
        productName:productNames[data.product]||'Cactus One',
        planName:planNames[data.plan]||data.plan||'',
        customerName:'',
        documentType:null,
        documentLast4:null
      }]});
      setAlert(data.status==='ONLINE'?'Pagamento confirmado e ambiente disponível.':'Pedido recuperado com segurança. Acompanhe a ativação abaixo.',data.status==='ONLINE'?'success':'info');
    }catch{}
  }

  form?.addEventListener('submit',submit);
  const documentInput=form?.querySelector('[name="document"]');
  documentInput?.addEventListener('input',()=>documentMask(documentInput));
  const prefillOrder=new URLSearchParams(location.search).get('order');
  if(prefillOrder&&form?.orderId&&/^[A-Za-z0-9_-]{20,64}$/.test(prefillOrder))form.orderId.value=prefillOrder;
  restoreProtectedCheckoutStatus();
})();