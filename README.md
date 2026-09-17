# Chat GPT no seu site (WordPress + Elementor) usando Vercel + OpenAI

Este pacote cria um **endpoint seguro** (na Vercel) e um **snippet HTML** para você colar no Elementor.
O chat NÃO expõe sua chave da OpenAI no navegador.

## Passo a passo (bem simples)

### 1) Criar a conta e a chave da OpenAI
- Acesse a sua conta em platform.openai.com e gere uma **API Key** (começa com `sk-...`). Guarde-a.

### 2) Clonar/Enviar este projeto para a Vercel
- Crie uma conta em vercel.com.
- Crie um projeto novo e faça **Import** deste repositório/zip.
- Na Vercel, abra **Settings » Environment Variables** e adicione:
  - `OPENAI_API_KEY` = *sua chave sk-...*
  - `SYSTEM_PROMPT` (opcional) = instruções fixas do seu GPT (ex.: tom de voz, escopo, o que pode e não pode fazer).

> Dica: você pode colar as mesmas instruções do seu “GPT” (aquele que você montou no ChatGPT) aqui no `SYSTEM_PROMPT`.

### 3) Fazer o deploy
- Clique em **Deploy**. Ao final, você terá uma URL do tipo: `https://seu-projeto.vercel.app`

### 4) Pegar a URL do endpoint
- Seu endpoint estará em: `https://SEU-PROJETO.vercel.app/api/chat`
- Teste rapidamente (no terminal):
  ```bash
  curl -X POST https://SEU-PROJETO.vercel.app/api/chat     -H "Content-Type: application/json"     -d '{"message":"Olá! Explique como você funciona."}'
  ```

### 5) Colar o snippet no Elementor
- No WordPress, edite a página com Elementor.
- Arraste um **Widget HTML**.
- Cole o conteúdo de `elementor-chat.html` dentro do widget.
- Substitua a variável `API_URL` pelo seu endpoint real (linha indicada). Salve/atualize a página.

Pronto! Você terá um chat funcional na sua página, falando com o seu “GPT” via API.

---

## Como personalizar

- **Tom de voz e regras:** edite a env `SYSTEM_PROMPT` na Vercel (sem redeploy).
- **Modelo:** altere a constante `MODEL` no arquivo `api/chat.js` (padrão `gpt-4.1-mini`).
- **Histórico:** o snippet envia o histórico recente. Se quiser manter conversas por usuário, adicione um backend/DB.
- **CORS/Segurança:** `api/chat.js` já libera CORS para a sua origem. Altere a variável `ALLOWED_ORIGINS` para o seu domínio.

> Importante: **não coloque a chave da OpenAI no HTML/JS do Elementor**. Ela deve ficar SOMENTE como variável de ambiente no servidor (Vercel).

---

## Estrutura dos arquivos

```
elementor-gpt-chat/
├─ api/
│  └─ chat.js           # Função serverless (Vercel) que chama a OpenAI
├─ elementor-chat.html  # Snippet para colar no Widget HTML do Elementor
├─ package.json         # Dependência "openai"
├─ vercel.json          # Configuração de rotas
└─ README.md            # Este guia
```

---

## Suporte a base de conhecimento (RAG) – opcional
Se quiser que o chat consulte seus PDFs/CSVs:
- Suba seus arquivos para uma base própria (ex.: banco vetorial) **ou** use a Files API da OpenAI no seu backend.
- Durante a requisição, busque trechos relevantes e inclua no `messages` (role `system` ou `assistant`) como contexto.
- Para começar simples, mantenha o fluxo do exemplo e evolua depois.

---

## Licença
Livre para usar e modificar.

---

## Bot de alerta de ingressos (Ticketmaster)

Este repositório também contém um monitor que avisa por e-mail quando aparece
ingresso nos shows configurados. Guia completo em [`tickets/README.md`](./tickets/README.md).
