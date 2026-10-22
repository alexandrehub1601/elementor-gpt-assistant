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
