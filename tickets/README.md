# 🎟️ Bot de alerta de ingressos (Ticketmaster)

Verifica de tempos em tempos se apareceu ingresso nos shows configurados e manda
e-mail para a lista de cada show. Já vem configurado com:

| Show | Quem recebe |
| --- | --- |
| Harry Styles — Londres, 28/08/2027 | l.leticiapontes@gmail.com, isabelalaise4@gmail.com, alexndrre@gmail.com |
| Harry Styles — Roma, 07/08/2027 | viccberbel@gmail.com, alexndrre@gmail.com |

Tudo isso fica em [`events.config.json`](./events.config.json) — para mudar shows
ou destinatários, edite só esse arquivo.

---

## Como ele verifica

Duas sondas, nessa ordem:

1. **Discovery API da Ticketmaster** (oficial, chave grátis). É a via suportada
   pela própria Ticketmaster e a mais confiável. Lê o status do evento
   (`onsale` / `offsale`), a data de abertura da venda e a faixa de preço.
2. **Página pública do evento** (best-effort), só quando a API não dá resposta
   conclusiva. Lê o bloco `application/ld+json` que a Ticketmaster publica na
   página e olha `offers.availability` (`InStock` / `SoldOut`).

Se a segunda sonda cair na fila virtual (Queue-it) ou no anti-bot, o resultado é
`BLOCKED` e o bot simplesmente espera o próximo ciclo. **Ele não tenta contornar
fila, captcha nem bloqueio** — sem proxy rotativo, sem token de fila reaproveitado,
sem compra automática. É um vigia que avisa você; a compra é sua, no site oficial.

> O `queueittoken` que estava na URL de Roma foi removido da config de propósito:
> ele é pessoal, expira, e reutilizá-lo não funcionaria.

---

## Instalação (5 minutos)

### 1) Chave da Ticketmaster
Crie uma conta grátis em <https://developer.ticketmaster.com>, crie um app e
copie a **Consumer Key** da Discovery API.

### 2) Confirmar os IDs dos eventos
Os IDs que estão na config vieram das URLs. Se a Discovery API não reconhecer
algum deles, o bot avisa no log e você acha o ID certo assim:

```bash
export TICKETMASTER_API_KEY=sua_chave
npm run tickets:find -- "harry styles" GB   # Reino Unido
npm run tickets:find -- "harry styles" IT   # Itália
```

Copie o `id:` do show certo para o campo `discoveryId` em `events.config.json`.

### 3) E-mail
Copie `.env.example` para `.env` e preencha. Com Gmail, use uma
**senha de app** (<https://myaccount.google.com/apppasswords>), nunca a senha da conta:

```bash
cp tickets/.env.example .env
```

Teste o envio antes de ligar o bot:

```bash
npm install nodemailer
node --env-file=.env tickets/src/run.js test
```

Chegou o e-mail marcado `[TESTE]` nas caixas de entrada? Está pronto.

---

## Como rodar

### Opção A — GitHub Actions (grátis, sem servidor)

O workflow [`.github/workflows/ticket-monitor.yml`](../.github/workflows/ticket-monitor.yml)
já está pronto e roda a cada 10 minutos. Em **Settings » Secrets and variables » Actions**,
crie os secrets:

`TICKETMASTER_API_KEY`, `MAIL_FROM`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`
(ou `RESEND_API_KEY`, com a variable `MAIL_TRANSPORT=resend`).

Dois avisos honestos sobre essa opção:
- agendamento no GitHub é "melhor esforço" e **atrasa em horários de pico** — às
  vezes 20–30 minutos;
- workflows agendados são **desativados após 60 dias sem commits** no repositório.

### Opção B — servidor sempre ligado (mais confiável)

Numa VPS, Raspberry Pi ou no seu próprio computador:

```bash
npm install nodemailer
node --env-file=.env tickets/src/run.js watch
```

Fica rodando, verifica a cada `pollSeconds` (padrão 300s) com um jitter
aleatório, e mantém o estado em `tickets/.state/`. Para deixar de pé de verdade,
use `pm2 start tickets/src/run.js --name tickets -- watch` ou um serviço systemd.

---

## Ajustes

Em `events.config.json`:

| Campo | O que faz |
| --- | --- |
| `pollSeconds` | intervalo entre verificações (mínimo 60) |
| `jitterSeconds` | variação aleatória para não bater no site em horário cravado |
| `renotifyHours` | reavisa se continuar disponível depois de N horas (0 = nunca repete) |
| `notifyErrorsTo` | e-mails que recebem aviso se o bot ficar cego muitos ciclos seguidos |
| `events[].recipients` | quem recebe o alerta daquele show |

Variáveis de ambiente úteis: `DRY_RUN=1` (imprime em vez de enviar),
`TICKETS_PAGE_PROBE=0` (usa só a API oficial), `TICKETS_STATE_FILE` (caminho do estado).

---

## Testes

```bash
npm run tickets:selftest
```

Roda 13 verificações da lógica de decisão (status da API, leitura do JSON-LD,
regra anti-spam de e-mail) sem tocar na rede.

---

## Limites que você precisa saber

- **Um ingresso de revenda pode durar segundos.** Nenhum monitor por e-mail
  garante que você chegue a tempo; ele aumenta a chance, não a certeza.
- **Ative também o alerta oficial da Ticketmaster** na página do evento
  ("Notify me" / "Avvisami"). Custa nada e é a fonte mais direta.
- **A Ticketmaster restringe acesso automatizado ao site** nos termos de uso — por
  isso a sonda principal é a API oficial e os intervalos são folgados. Não diminua
  o `pollSeconds` para poucos segundos: além de violar o limite da API (5 req/s,
  5.000/dia), é o caminho mais rápido para o seu IP ser bloqueado.
