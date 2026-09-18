# D'barros Pizzas — V27 — Link único + ACL

Esta versão foi reorganizada para funcionar em **um único domínio** com rotas diferentes.

Exemplo usando:

`https://pedidos.dbarros.com.br`

## Rotas

### Cliente / delivery

`https://pedidos.dbarros.com.br/`

Abre o site público da D'barros.
Os botões de pedido levam para o link do **Anota AI** configurado pelo gestor.

### Gestor

`https://pedidos.dbarros.com.br/gestor`

Área protegida por login e sessão PHP.

Credenciais iniciais da demo:

- usuário: `gestor`
- senha: `Dbarros@2026`

**Troque a senha antes de publicar.** O hash fica em `config.php`.

### Pedido no salão por QR Code

Exemplos:

- Mesa 01: `https://pedidos.dbarros.com.br/mesa/01`
- Mesa 02: `https://pedidos.dbarros.com.br/mesa/02`
- Mesa 10: `https://pedidos.dbarros.com.br/mesa/10`

Cada QR Code deve apontar para sua própria rota de mesa.

### QR Codes das mesas

`https://pedidos.dbarros.com.br/gestor/qrs`

Protegido pelo mesmo login do gestor.

### Comanda PDF

`https://pedidos.dbarros.com.br/gestor/comanda`

Também protegido.

## O que agora é compartilhado no servidor

O catálogo e as configurações do delivery deixaram de depender apenas do navegador do gestor.

Arquivos:

- `data/catalog.json`
- `data/delivery.json`
- `data/orders.json`

O painel salva catálogo, preços, promoções, pizza em destaque e link do Anota AI via API PHP. O site e o cardápio do salão consultam os mesmos dados.

Pedidos feitos pelo cardápio do salão também são registrados em `data/orders.json` como base para a próxima etapa de painel/cozinha/impressão automática.

## Requisitos de hospedagem

- Apache / HostGator / cPanel com PHP 8+
- `mod_rewrite` e `.htaccess` habilitados
- HTTPS recomendado
- permissão de escrita para a pasta `data/`

## Segurança

Antes de produção:

1. troque a senha padrão do gestor;
2. use HTTPS;
3. confirme que `/data`, `/views` e `/private` não estão acessíveis diretamente;
4. faça backup periódico de `data/` ou migre os dados para MySQL na fase operacional.


## Modo Investidor — celular

A V28 inclui uma rota específica para apresentação:

`https://SEU-DOMINIO.com/demo`

Ela abre uma interface pensada para celular com três experiências na mesma apresentação:

- **Cliente / Delivery**
- **Pedido no salão — Mesa 07**
- **Painel do Gestor**

O investidor troca entre as áreas pelas abas no topo sem precisar receber vários links.

### Painel do gestor na apresentação

`/demo/gestor`

Não exige login e trabalha em modo de demonstração. Alterações feitas nesse modo não são gravadas na operação real.

### Como apresentar

1. Publique todo o ZIP no mesmo domínio.
2. No celular, abra `/demo`.
3. Use o botão **Compartilhar** para enviar ou copiar o link.
4. Mostre primeiro o Delivery, depois o pedido da Mesa 07 e finalize no painel do gestor.

O acesso real do gestor continua protegido normalmente em `/gestor`.
