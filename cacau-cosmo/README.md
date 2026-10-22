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

## 1. Quem Somos e Contato (igual à Home)

1. **Páginas → Adicionar nova**: título "Quem Somos", slug `quem-somos`. Para a outra: título "Contato", slug `contato`.
2. Em **Atributos da página → Modelo**, escolha **Elementor Canvas**.
3. Clique em **Editar com Elementor**, arraste um widget **HTML** e cole o arquivo inteiro.
4. Publique.

**No Contato**, no fim do arquivo, procure `window.CC_CONTACT`:
- `WHATSAPP: '5521999999999'`: com o número preenchido, o WhatsApp aparece na página e ganha um botão "Enviar pelo WhatsApp". Sem número, o WhatsApp fica escondido.
- `FORM_ENDPOINT`: sem nada preenchido, o formulário abre o app de e-mail do visitante com a mensagem pronta. Para receber direto no e-mail, crie um formulário grátis no Formspree e cole a URL, por exemplo `'https://formspree.io/f/SEU_ID'`. Outra opção é `'https://formsubmit.co/ajax/ola@cacaucosmo.com.br'` (o primeiro envio pede uma confirmação por e-mail).

## 2. Templates WooCommerce e Post (Elementor Pro)

1. **Templates → Templates salvos → Importar templates** e envie os 3 `.json`.
2. Abra cada template em **Templates → Theme Builder** e:
   - em **Configurações (engrenagem) → Layout da página**, escolha **Elementor Canvas**. Sem isso aparece o header padrão do tema Hello;
   - clique em **Publicar** e defina as **Condições**:
     - Loja: **Arquivo de produtos → Todos os arquivos de produtos**
     - Produto: **Produtos → Todos**
     - Post: **Posts → Todos**
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
