# Painel de mensagens do site (Estúdio F/Ale)

O formulário "Vamos falar sobre o seu projeto?" (home e página de contato) envia as respostas para
`/api/contato`. Elas ficam guardadas num banco de dados e aparecem no painel `/admin`, protegido por senha.

## Como colocar no ar (Vercel)

1. **Projeto na Vercel**: importe este repositório em vercel.com (Add New → Project).
2. **Banco de dados**: no projeto, abra **Storage** (ou Marketplace) e conecte **Upstash for Redis**
   (plano gratuito). A Vercel cria sozinha as variáveis `KV_REST_API_URL` e `KV_REST_API_TOKEN`.
3. **Variáveis de ambiente** (Settings → Environment Variables):
   - `ADMIN_PASSWORD`: a senha do painel. Use uma senha longa.
   - `SESSION_SECRET`: um texto aleatório com pelo menos 32 caracteres (gere em um gerador de senhas).
   - `ALLOWED_ORIGINS` (só se o site ficar em outro endereço, como o WordPress): ex.
     `https://www.estudiofale.com.br`
4. **Deploy**: clique em Redeploy depois de criar as variáveis.
5. **Acesse** `https://SEU-DOMINIO/admin` e entre com a senha.

## Se o site for montado no WordPress

- O formulário pode continuar usando esta API: no HTML do formulário, troque
  `data-endpoint="/api/contato"` pela URL completa, ex. `https://SEU-PROJETO.vercel.app/api/contato`,
  e coloque o domínio do WordPress em `ALLOWED_ORIGINS`. O painel continua em `https://SEU-PROJETO.vercel.app/admin`.
- Alternativa sem servidor próprio: usar o widget Formulário do Elementor Pro, que guarda as respostas em
  **Elementor → Envios** dentro do próprio WordPress.

## O que o painel faz

- Lista as mensagens, da mais nova para a mais antiga, com selo "Nova" nas que ainda não foram lidas.
- Abrir uma mensagem marca como lida (dá para voltar para "nova").
- Busca por nome, negócio ou texto e filtro "Novas".
- Contato vira link direto: e-mail abre o programa de e-mail; telefone abre o WhatsApp.
- Exporta tudo para planilha (CSV, abre no Excel e no Google Planilhas).
- Exclui mensagens (com confirmação).

## Segurança

- Sessão de 12 horas em cookie protegido (HttpOnly, Secure, SameSite=Strict).
- Limite de 10 tentativas de senha a cada 15 minutos e de 5 envios do formulário a cada 10 minutos por conexão.
- Campo invisível contra robôs de spam e validação de todos os campos no servidor.
- Painel e API fora do Google (noindex e robots.txt).
