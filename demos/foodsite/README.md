# CACTUS FOODSITE — Produto White‑Label

Template comercial da **Cactus** para restaurantes, lanchonetes, hamburguerias, pizzarias, bares, cafeterias e operações de delivery.

O site público é totalmente **white-label**: o cliente vê a própria marca. A administração é feita pelo **Cactus Site Manager**, sem necessidade de editar HTML, CSS ou JavaScript.

## O que o cliente consegue alterar no painel

Acesse `https://SEU-DOMINIO.com.br/admin/`.

No primeiro acesso, o próprio cliente cria usuário e senha. Não existe senha padrão.

O painel permite:

- trocar a **logo da empresa**;
- definir as **duas cores principais** da identidade visual;
- editar nome da marca, chamadas, títulos e textos institucionais;
- informar Instagram;
- trocar todas as fotos principais do layout;
- editar nome e subtítulo dos seis produtos em destaque;
- adicionar, excluir e reordenar livremente a galeria de fotos;
- adicionar, remover, ocultar e reordenar **unidades**;
- definir nome, endereço, telefone, mapa e link de pedido de cada unidade;
- usar qualquer sistema externo de pedido: PedAI / Anota AI, iFood, WhatsApp, cardápio próprio ou outro URL;
- ativar/desativar as promoções de segunda a sexta;
- editar nome, descrição, valor e link de cada promoção;
- alterar a senha do administrador.

## Conceito do produto

O site **não processa o pedido**. Ele funciona como uma vitrine institucional e comercial de alta conversão. O cliente escolhe a unidade e é direcionado para o canal de pedido que o estabelecimento já utiliza.

Isso evita a necessidade de desenvolver checkout, meios de pagamento e integração com cozinha, e permite que a Cactus entregue o produto rapidamente para negócios que já possuem um sistema de pedidos.

## Estrutura visual padrão

O template inclui:

1. cabeçalho com logo do cliente;
2. hero com duas fotos e CTA para cardápio;
3. botão flutuante **CARDÁPIO** visível durante a navegação;
4. vitrine com seis produtos em destaque;
5. galeria horizontal administrável;
6. mostrador de promoções fixas de segunda a sexta;
7. seletor de unidades com link externo de pedido;
8. banner de campanha;
9. seção institucional “Sobre”; 
10. listagem de unidades com mapa e cardápio;
11. rodapé da marca e assinatura discreta “Produto white-label por Cactus”.

## Conteúdo inicial

A versão entregue neste pacote não contém marca ou imagens de nenhum cliente anterior.

Ela abre com:

- **COLOQUE SUA LOGO AQUI**;
- **COLOQUE SUA FOTO AQUI**;
- textos genéricos de apresentação;
- duas unidades demonstrativas;
- cinco promoções demonstrativas;
- links externos vazios e botões desativados até a configuração.

Isso permite usar o pacote como uma “matriz” para novos clientes.

## Instalação

1. Envie **todo o conteúdo desta pasta** para a pasta pública do domínio.
2. Use uma hospedagem com **PHP 8.1 ou superior**.
3. Garanta permissão de escrita do PHP em:
   - `data/`
   - `uploads/`
4. Abra o domínio para conferir o template.
5. Acesse `/admin/` e crie o administrador.
6. Substitua logo, textos, fotos, unidades e links.
7. Clique em **SALVAR E PUBLICAR**.

### HostGator / cPanel

Em hospedagens compartilhadas, normalmente basta colocar os arquivos em `public_html/` ou na pasta do domínio adicional. As pastas `data/` e `uploads/` precisam ficar graváveis pelo PHP.

## Formatos de imagem

O painel aceita:

- JPG;
- PNG;
- WEBP;
- até 8 MB por arquivo.

Para logos, recomendamos PNG ou WEBP com fundo transparente. SVG não é enviado pelo painel por segurança.

A galeria permite até **40 fotos**, com envio de até **12 novas imagens por vez**.

## Segurança

- senha armazenada com `password_hash()`;
- sessão PHP com cookie `HttpOnly` e `SameSite=Strict`;
- proteção CSRF nas alterações;
- validação dos URLs externos;
- validação MIME dos uploads;
- `data/` protegido contra acesso direto em Apache;
- `uploads/` bloqueia execução de PHP;
- apenas JPG, PNG e WEBP são aceitos pelo upload.

## Reset da senha

Se o cliente perder a senha, substitua o conteúdo de `data/admin.json` por:

```json
{}
```

Ao acessar `/admin/` novamente, o sistema solicitará a criação de um novo administrador.

## Arquivos principais

- `index.html` — site público;
- `styles.css` — identidade visual do template;
- `app.js` — renderização dinâmica do conteúdo;
- `api/site-config.php` — configuração pública em JSON;
- `admin/` — Cactus Site Manager;
- `lib/site.php` — funções de persistência e upload;
- `data/site.json` — conteúdo atual do cliente;
- `data/admin.json` — credencial do administrador;
- `uploads/` — imagens enviadas pelo painel;
- `assets/placeholders/` — imagens neutras da matriz Cactus.

## Fluxo recomendado da Cactus para um novo cliente

1. duplicar esta pasta;
2. publicar em domínio ou subdomínio de homologação;
3. criar o primeiro acesso em `/admin/`;
4. inserir a logo e as cores do cliente;
5. preencher os textos;
6. enviar as fotos;
7. cadastrar unidades e links de pedido;
8. cadastrar promoções;
9. validar responsividade e links;
10. apontar o domínio definitivo.

---

**CACTUS FOODSITE** — matriz white-label para negócios de alimentação.
