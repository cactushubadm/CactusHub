# Cactus Eventos — White Label v1

Produto genérico da Cactus para casas de shows, bares, baladas, festivais, arenas e produtores de eventos.

A mesma base pode ser entregue a clientes diferentes sem editar o código-fonte. O administrador troca nome, logo, cores, textos, fotos, contatos, endereço e integrações pelo próprio painel.

## O que o produto entrega

- Site institucional responsivo e personalizável.
- Agenda pública de eventos.
- Criação e edição de eventos pelo administrador.
- Banner/capa individual por evento.
- Lotes de ingressos com preço, estoque, quantidade vendida e limite por pedido.
- Reserva atômica de estoque para reduzir risco de venda acima da capacidade.
- Pedidos manuais ou Mercado Pago Checkout Pro.
- PIX/cartão quando as credenciais reais do cliente forem configuradas.
- Ingresso digital individual.
- QR Code local, sem depender de serviço externo para gerar a imagem.
- Portaria com leitura pela câmera do celular.
- Bloqueio de QR Code já utilizado e histórico de check-in.
- Lista VIP por evento administrada pela equipe.
- Acompanhantes, importação CSV e controle de entrada VIP.
- Recuperação de pedidos pelo cliente.
- E-mails transacionais com fila e novas tentativas.
- SMTP, PHP mail() ou spool local para homologação.
- Dashboard, relatórios CSV, backups, auditoria e múltiplos usuários administrativos.
- Termos, privacidade e política de cancelamento.
- Checklist de produção e diagnóstico para hospedagem.
- Preparado para HostGator/cPanel.

## White label / personalização

Depois da instalação, acesse:

`Administração > Personalização`

O cliente pode alterar:

- nome da marca;
- slogan;
- cidade/UF;
- assinatura/ano;
- descrição SEO;
- quatro cores principais do tema;
- logo;
- foto principal da home;
- foto institucional;
- foto de destaque;
- duas fotos de galeria;
- foto da localização;
- títulos e textos das principais seções;
- endereço e Google Maps;
- telefone e WhatsApp;
- Instagram;
- e-mails de atendimento;
- dados legais;
- Mercado Pago;
- SMTP.

Por padrão o pacote vem com placeholders explícitos como `COLOQUE SUA LOGO AQUI` e `COLOQUE SUA FOTO AQUI`, para facilitar a demonstração comercial e a implantação em novos clientes.

## Instalação local no Windows

1. Extraia a pasta inteira.
2. Execute `INICIAR_CACTUS_EVENTOS.bat`.
3. O inicializador baixa e configura PHP portátil com SQLite.
4. O navegador abre `install.php`.
5. Informe nome da marca, cidade/UF e conta do administrador.
6. Entre em `Administração > Personalização` e troque os placeholders.

Para testar o backend depois do PHP portátil instalado, execute `TESTAR_SISTEMA.bat`.

## Banco de dados

SQLite em:

`storage/cactus-eventos.sqlite`

A pasta `storage` deve ser gravável. O banco não deve ser exposto publicamente; o `.htaccess` incluído protege a pasta na hospedagem Apache/cPanel.

## HostGator

Veja `HOSTGATOR_DEPLOY.txt`.

Fluxo resumido:

1. PHP 8.2 ou 8.3 no cPanel.
2. Enviar os arquivos para `public_html`.
3. Confirmar SSL/HTTPS.
4. Verificar `diagnostico.php`.
5. Concluir `install.php`.
6. Personalizar o site.
7. Criar e-mail transacional do cliente.
8. Configurar cron.
9. Inserir credenciais reais do Mercado Pago, se houver venda online.
10. Homologar compra, e-mail, QR, check-in e reembolso.

## Mercado Pago

O produto não contém credenciais de nenhum cliente. Para operação real, cada contratante informa no painel:

- Access Token;
- Webhook Secret;
- URL pública HTTPS.

O modo `Manual / caixa` permite demonstrar e homologar o sistema sem Mercado Pago.

## QR Code

Novos ingressos usam payload:

`CACTUS:TICKET:EV-...`

O leitor utiliza o payload próprio do produto Cactus Eventos (`CACTUS:TICKET:`).

## Produto Cactus

O site público é white label para o cliente. O painel administrativo e o rodapé mantêm uma assinatura discreta `Plataforma de eventos por CACTUS`, identificando a solução.

## Arquivos úteis

- `preview.html` — demonstração visual sem banco.
- `install.php` — instalação inicial.
- `diagnostico.php` — diagnóstico técnico.
- `GUIA_RAPIDO.txt` — implantação resumida.
- `PERSONALIZACAO_CLIENTE.txt` — checklist de material a solicitar ao cliente.
- `PRODUTO_CACTUS.txt` — escopo comercial do produto.
- `HOSTGATOR_DEPLOY.txt` — publicação na HostGator.
- `PRODUCAO_CHECKLIST.txt` — checklist antes da abertura das vendas.
