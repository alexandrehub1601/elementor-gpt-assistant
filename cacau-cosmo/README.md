# Cacau Cosmo — páginas e templates

Mesmo visual da Home (cores, Recoleta, Pinopolis e Syne, céu estrelado, menu lateral, PT/EN e o mesmo header e footer).

## O que tem aqui

| Arquivo | O que é | Como usar |
|---|---|---|
| `paginas/quem-somos.html` | Página Quem Somos | Widget **HTML** (Elementor) |
| `paginas/contato.html` | Página Contato | Widget **HTML** (Elementor) |
| `elementor/loja-woocommerce.json` | Template da loja (arquivo de produtos) | Importar no Theme Builder |
| `elementor/produto-woocommerce.json` | Template de produto | Importar no Theme Builder |
| `elementor/post-journal.json` | Template de post do Journal | Importar no Theme Builder |
| `elementor/carrinho-woocommerce.json` | Página de carrinho | Importar e inserir na página /carrinho/ |
| `elementor/checkout-woocommerce.json` | Finalizar compra + pedido confirmado | Importar e inserir na página /finalizar-compra/ |
| `elementor/minha-conta-woocommerce.json` | Minha conta (Círculo) | Importar e inserir na página /minha-conta/ |
| `wordpress/circulo.php` | O Círculo: cadastro, curadoria, e-mails e site fechado | Colar no plugin WPCode |
| `paginas/home.html` | Home com o formulário novo do Círculo | Widget **HTML** da Home |
| `paginas/formulario-circulo.html` | Só o formulário (para o popup) | Widget **HTML** do popup |
| `wordpress/mensagens-contato.php` | Painel "Mensagens" do Contato | Colar no plugin WPCode |

## 1. Quem Somos e Contato (igual à Home)

1. **Páginas → Adicionar nova**: título "Quem Somos", slug `quem-somos`. Para a outra: título "Contato", slug `contato`.
2. Em **Atributos da página → Modelo**, escolha **Elementor Canvas**.
3. Clique em **Editar com Elementor**, arraste um widget **HTML** e cole o arquivo inteiro.
4. Publique.

### Mensagens do Contato no painel do WordPress

O formulário do Contato envia para o painel do WordPress. Para ativar (uma vez só):

1. Instale o plugin gratuito **WPCode** (Plugins → Adicionar novo → "WPCode").
2. **Code Snippets → Add Snippet → Add Your Custom Code**, tipo **PHP Snippet**.
3. Cole o conteúdo de `wordpress/mensagens-contato.php` **sem a primeira linha `<?php`**.
4. Em *Insert Method*, deixe **Auto Insert / Run Everywhere**. Ative e salve.

Pronto: aparece o menu **Mensagens** no wp-admin, com contador de mensagens novas, e cada mensagem também chega em `ola@cacaucosmo.com.br`. Responder esse e-mail responde direto ao cliente. A lista tem botão **Exportar CSV**. Para trocar o e-mail, mude `CC_CONTATO_EMAIL` no início do código.

Para mostrar o WhatsApp na página, preencha `WHATSAPP: '5521999999999'` em `window.CC_CONTACT`, no fim do `contato.html`.

## 1b. O Círculo (cadastro → aprovação → acesso)

Segue o fluxo combinado em 23/10: a Home fica aberta com o formulário; Loja, Journal, Quem somos, Contato e Minha conta só abrem para membros aprovados.

**Instalar (uma vez):**
1. **WPCode → Add Snippet → PHP Snippet**: cole `wordpress/circulo.php` sem a primeira linha `<?php`. Antes de ativar, preencha a CONFIGURAÇÃO no topo:
   - `CC_MC_API_KEY`: no Mailchimp, **Perfil → Extras → Chaves de API → Criar chave** (a chave termina em `-us22`). O ID da audiência já está certo. Os campos WhatsApp, Instagram, Estado, Cidade e Idade são criados sozinhos na audiência.
   - `CC_WHATSAPP_AVISOS`: telefone e apikey da Nicole e da Isa no CallMeBot. Cada uma salva o contato **+34 694 25 79 57** e manda "I allow callmebot to send me messages"; o bot responde com a apikey.
   - `CC_CIRCULO_IDADE_MIN` (18) e `CC_CIRCULO_EMAIL_AVISO` (ola@cacaucosmo.com.br).
2. **WooCommerce → Configurações → Visibilidade do site → Ao vivo.** O modo "Em breve" do WooCommerce sai; quem fecha o site agora é o Círculo.
3. **Home:** no widget HTML da Home, troque o código por `paginas/home.html` (é a Home atual com o formulário novo no lugar do Mailchimp).
4. **Popup** "O próximo ritual está chegando": troque o conteúdo do widget HTML por `paginas/formulario-circulo.html`.
5. Instale **WP Mail SMTP** para os e-mails de aprovação não caírem no spam.

**No dia a dia (menu Círculo no wp-admin):**
- Cada solicitação chega no WhatsApp da Nicole e da Isa e por e-mail, com o link para decidir.
- Filtros: Aguardando, Aprovadas, Lista de espera. Busca por nome, e-mail, cidade ou Instagram. Exportar CSV.
- **Aprovar**: cria o login (cliente do WooCommerce, já com WhatsApp, estado e cidade no cadastro), envia o e-mail "Você foi selecionada" com o botão para criar a senha e troca a tag no Mailchimp para `circulo-aprovada`.
- **Não aprovar**: envia o e-mail de lista de espera e marca `circulo-espera` no Mailchimp.
- Dá para aprovar vários de uma vez (Ações em massa) e reenviar o e-mail de acesso.
- Clientes antigos ou convidados: **Usuários → editar → "Membro do Círculo"**.
- Pedido pago: a Nicole recebe no WhatsApp os dados de envio.

Os textos dos e-mails ficam nas funções `cc_circulo_email_aprovada` e `cc_circulo_email_espera`, no próprio snippet.

## 2. Templates WooCommerce e Post (Elementor Pro)

1. **Templates → Templates salvos → Importar templates** e envie os 3 `.json`.
2. Abra cada template em **Templates → Theme Builder** e:
   - em **Configurações (engrenagem) → Layout da página**, escolha **Elementor Canvas**. Sem isso aparece o header padrão do tema Hello;
   - clique em **Publicar** e defina as **Condições**:
     - Loja: **Arquivo de produtos → Todos os arquivos de produtos**
     - Produto: **Produtos → Todos**
     - Post: **Posts → Todos**
   > **Erro "This source does not support import"?** Importe pelo painel (**wp-admin → Templates → Templates salvos → botão Importar templates**), e não pela janela de dentro do editor. Se o erro continuar, importe `loja-woocommerce-alternativo.json`. Depois crie em **Theme Builder → Adicionar novo → Arquivo de produtos**, clique no ícone de pasta, vá em **Meus modelos** e clique em **Inserir** no modelo "Cacau Cosmo — Loja".
3. Para ver os templates, você precisa de pelo menos 1 produto (com imagem e descrição curta) e 1 post com imagem destacada.

Tudo que é dinâmico vem do WooCommerce ou do WordPress: título, preço, galeria, comprar, abas, relacionados, conteúdo do post, autor e posts relacionados. As categorias da loja aparecem como filtros automaticamente. O ícone de sacola no topo mostra a quantidade de itens no carrinho.

## Antes de publicar

- Procure por **`[ TROCAR FOTO ]`** e **`[ REVISAR ]`**. Os textos de FAQ, os selos ("Envio para todo o Brasil", "Como guardar") e as etapas do processo são genéricos: confirme prazos e políticas reais.
- O menu agora aponta para `/loja/`, `/quem-somos/`, `/journal/` e `/contato/`. **Na Home** o link Shop ainda vai para `/loja-soon/` com o selo "Em breve": troque quando a loja abrir.
- Os links legais apontam para `/politica-de-privacidade/` e `/termos/`. Crie essas páginas ou ajuste os links.

## Editar o código-fonte

Os arquivos de `src/` são a fonte: `shared/` tem header, footer e base, `pages/` tem as páginas e `elementor/` tem o CSS dos templates. Depois de editar, rode:

```bash
python3 cacau-cosmo/build.py
```

O comando gera de novo `paginas/` e `elementor/`. Header e footer saem iguais em todos os arquivos.

## 2b. Carrinho

1. **Templates → Templates salvos → Importar templates** e envie `carrinho-woocommerce.json`.
2. Abra a página **Carrinho** (Páginas → Carrinho, a que está em `/carrinho/`). Em **Modelo**, escolha **Elementor Canvas** e clique em **Editar com Elementor**.
3. Apague o que estiver na página. Clique no ícone de pasta, vá em **Meus modelos** e clique em **Inserir** em "Cacau Cosmo — Carrinho". Publique.
4. Confira em **WooCommerce → Configurações → Avançado** se a **Página do carrinho** continua sendo essa.

Cupom, frete, quantidade com − e + (atualiza sozinho) e "Concluir compra" são os do próprio WooCommerce.

## 2c. Finalizar compra

1. Importe `checkout-woocommerce.json` em **Templates → Templates salvos → Importar templates**.
2. Abra a página de **Finalização de compra** (veja qual é em **WooCommerce → Configurações → Avançado → Página de finalização de compra**). Em **Modelo**, escolha **Elementor Canvas** e clique em **Editar com Elementor**.
3. Apague o bloco de checkout que está lá. Pela pasta → **Meus modelos**, clique em **Inserir** em "Cacau Cosmo — Finalizar compra" e publique.
4. Faça um pedido de teste. A mesma página vira **"Pedido confirmado"** depois do pagamento.

Usa o checkout clássico do WooCommerce (`[woocommerce_checkout]`), compatível com gateways brasileiros (PIX, Mercado Pago, Pagar.me…) e com o plugin "Brazilian Market" (CPF/CNPJ, número, bairro).

## 2d. Minha conta

1. Importe `minha-conta-woocommerce.json` em **Templates → Templates salvos → Importar templates**.
2. Abra a página **Minha conta** (veja qual é em **WooCommerce → Configurações → Avançado → Página Minha conta**). Em **Modelo**, escolha **Elementor Canvas** e clique em **Editar com Elementor**.
3. Apague o conteúdo atual. Pela pasta → **Meus modelos**, clique em **Inserir** em "Cacau Cosmo — Minha conta" e publique.
4. Para permitir que clientes criem conta por essa página: **WooCommerce → Configurações → Contas e privacidade → "Permitir que clientes criem uma conta na página Minha conta"**.

Logado: "Olá, nome · Bem-vindo ao seu Círculo", cartões para Pedidos, Endereços, Dados da conta, Loja, Journal e Contato, e o menu normal do WooCommerce. Deslogado: "Entre no seu Círculo" com Entrar e Criar conta lado a lado.

## 3. Produtos

`woocommerce/produtos-cacau-cosmo.csv` traz os produtos **com dados reais da embalagem**. Por enquanto: **Saturno 47% (Ritual das Raízes)**, com ingredientes, alérgicos, tabela nutricional completa, atributos (cacau, peso, linha, perfil, vegano, glúten), peso e medidas para o frete e posição na loja. **Sol** e **Lua** entram como **rascunho** (não aparecem na loja) até chegarem os dados reais da embalagem. Os textos marcados `[ PREENCHER ]` indicam o que falta.

Se o Saturno já foi importado, use `woocommerce/produtos-sol-lua.csv`, que traz só os dois que faltam.

1. Se você importou a versão antiga ("Saturno — Chocolate Amargo 80%"), mande esse produto para a lixeira antes.
2. **Produtos → Importar → Escolher arquivo**: envie o CSV, clique em **Continuar** e depois em **Executar o importador**.
3. Abra o produto e preencha o **preço** (veio em branco de propósito).
4. **Imagem do produto:** `woocommerce/imagens/saturno.jpg` (caixa em 3D). **Galeria:** `saturno-frente.jpg`, `saturno-verso.jpg` (tabela nutricional) e `saturno-tampa.jpg`.
5. Em **Posição** (aba Avançado do produto) ficou Sol = 1, Lua = 2, Saturno = 3. A loja e o carrinho mostram os produtos publicados nessa ordem.

As faces originais da caixa estão em `woocommerce/fotos-reais/saturno/`. Para gerar as fotos de novo, rode `python3 cacau-cosmo/woocommerce/fotos_reais.py saturno`.

## 4. Posts do Journal

`journal/journal-posts.xml` tem os 12 posts da categoria Blog de theamazonapothecary.com, com data original, resumo, tags, imagem destacada e imagens do texto. Os posts entram na categoria **Blog**.

1. **Ferramentas → Importar → WordPress**: instale o importador, se pedir, e clique em **Executar importador**.
2. Envie o `journal-posts.xml`.
3. Em *Atribuir autores*, escolha o seu usuário e marque **Baixar e importar anexos**. Esse passo traz as imagens para a sua Biblioteca de Mídia.
4. Clique em **Enviar**. As imagens são baixadas uma a uma, então pode levar 1 ou 2 minutos.

Para baixar de novo (por exemplo, se entrarem posts novos lá), rode `python3 cacau-cosmo/journal/importar_posts.py`. Para importar como rascunho, troque `STATUS = "draft"` no script.
