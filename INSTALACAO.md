# Site do Estúdio F/Ale: como instalar

O site tem três partes:

- **Páginas**: home, contato e as páginas de cada case (`/projetos/nome-do-case`).
- **Painel** (`/admin`): com senha. Tem duas abas:
  - **Mensagens**: as respostas do formulário de contato.
  - **Cases**: criar e editar cases (título, texto, capa e imagens), publicar, escolher o que aparece na home e mudar a ordem.
- **Servidor** (pasta `api/`): recebe o formulário, guarda os dados e monta as páginas dos cases.

Tudo roda na **Vercel**, no plano gratuito.

---

## 1. Coloque os arquivos no GitHub

1. Entre em github.com e crie um repositório novo (pode ser privado), por exemplo `estudio-fale-site`.
2. Clique em **uploading an existing file**, arraste **todo o conteúdo** da pasta do zip
   (não a pasta em si: os arquivos `index.html`, `admin.html`, as pastas `api` e `assets` etc.) e clique em **Commit changes**.

## 2. Crie o projeto na Vercel

1. Entre em vercel.com com a sua conta do GitHub.
2. **Add New → Project**, escolha o repositório `estudio-fale-site` e clique em **Import**.
3. Em **Framework Preset**, deixe **Other**. Não precisa mudar mais nada. Clique em **Deploy**.
   (O site já abre, mas o painel e o formulário só funcionam depois dos passos 3 e 4.)

## 3. Conecte os dois armazenamentos (gratuitos)

No projeto, abra a aba **Storage**:

1. **Banco de dados** (mensagens e textos dos cases): clique em **Create Database** →
   escolha **Upstash for Redis** (no Marketplace) → siga as telas → **Connect** ao projeto.
2. **Imagens** (fotos dos cases que você enviar pelo painel): **Create Database** → **Blob** →
   escolha acesso **Public** → **Connect** ao projeto.

A Vercel cria sozinha as variáveis de conexão (`KV_REST_API_URL`, `KV_REST_API_TOKEN` e `BLOB_READ_WRITE_TOKEN`).

## 4. Crie a senha do painel

Em **Settings → Environment Variables**, adicione:

| Nome | Valor |
| --- | --- |
| `ADMIN_PASSWORD` | A senha do painel. Use uma senha longa, que vocês não usam em outro lugar. |
| `SESSION_SECRET` | Um texto aleatório com **pelo menos 32 caracteres** (gere num gerador de senhas). Não precisa decorar. |
| `SITE_URL` | O endereço final do site, ex. `https://www.estudiofale.com.br` |

Depois vá em **Deployments**, clique nos três pontinhos do último deploy → **Redeploy**.

## 5. Use o painel

1. Acesse `https://SEU-SITE/admin` e entre com a senha.
2. Na aba **Cases** já estão os 8 projetos da home, com capa e imagem.
   **Os textos deles são rascunhos meus**: revise e troque em **Editar**.
3. Para adicionar fotos: **Editar → Adicionar imagens**. As imagens são reduzidas
   automaticamente antes de subir (podem ser JPG, PNG ou WebP de qualquer tamanho).
   - Marque **Metade** em duas imagens seguidas para elas ficarem lado a lado.
   - Escreva uma descrição curta em cada imagem (ajuda no Google e em acessibilidade).
4. **Novo case**: preencha título, texto, capa e imagens. Salve como rascunho para conferir
   (o botão **Ver página** mostra a página só para você) e marque **Publicado** quando estiver pronto.
5. A ordem da lista é a ordem da home. A grade mostra os **8 primeiros** marcados como "Na home".
6. Depois de salvar, o site pode levar **até 1 minuto** para mostrar a mudança.

## 6. Coloque no seu domínio

1. Em **Settings → Domains**, adicione `estudiofale.com.br` e `www.estudiofale.com.br`.
2. A Vercel mostra os registros de DNS. Cadastre-os onde o domínio está registrado
   (Registro.br ou outro). Isso substitui a página "em breve" que está no ar hoje.

## 7. Google

1. Cadastre o site no **Google Search Console** (search.google.com/search-console).
2. Envie o sitemap: `https://www.estudiofale.com.br/sitemap.xml`
   (ele é atualizado sozinho com todos os cases publicados).

---

## Dúvidas comuns

- **Esqueci a senha**: troque `ADMIN_PASSWORD` na Vercel e faça **Redeploy**.
- **Quero sair de todos os computadores**: troque o `SESSION_SECRET` e faça **Redeploy**.
- **O painel diz "Armazenamento não configurado"**: falta conectar o Redis ou o Blob (passo 3) ou fazer o Redeploy.
- **Excluí uma imagem de um case**: ela sai da página, mas o arquivo continua guardado no Blob.
  Se quiser liberar espaço, apague em **Storage → Blob** na Vercel.
- **Segurança**: login vale por 12 horas; no máximo 10 tentativas de senha a cada 15 minutos;
  o formulário aceita 5 envios a cada 10 minutos por conexão e tem um campo invisível contra robôs;
  painel e servidor ficam fora do Google.
