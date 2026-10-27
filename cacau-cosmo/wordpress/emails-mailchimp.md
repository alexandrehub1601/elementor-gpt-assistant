# E-mails do Círculo pelo Mailchimp (opcional)

Use só se quiser que os e-mails saiam pelo Mailchimp em vez do WordPress.
Requer um plano do Mailchimp com **Customer Journeys** (confira no seu plano).

1. No snippet "Círculo" do WPCode, troque `define( 'CC_EMAILS_PELO_MAILCHIMP', false );` por `true`.
2. No WordPress, menu **Círculo → Sincronizar todas com o Mailchimp** (cria o campo LINKSENHA e aplica as tags).
3. No Mailchimp, crie 3 jornadas em **Automações → Customer Journeys → Criar**, cada uma com
   o ponto de partida **Contact activity → Tag added** e um passo **Send email**.
   Remetente: **Cacau Cosmo** · ola@cacaucosmo.com.br.
   Em cada jornada, deixe marcado que o contato só pode entrar **uma vez**.

> Faça o passo 2 **antes** de ligar as jornadas. Senão, quem já foi aprovado antes recebe de novo o e-mail de aprovação, sem link.

---

## Jornada 1 · Tag `circulo-pendente`

**Assunto:** Recebemos sua solicitação para o Círculo ✦

Olá, *|FNAME|*.

Recebemos sua solicitação para entrar no Círculo. Que bom ter você por aqui.

Lemos cada pedido com calma e respondemos em até 48 horas, por este e-mail. Se for aprovado, você recebe o link para criar sua senha e acessar a loja, o Journal e os próximos lotes.

Enquanto isso, acompanhe o que estamos criando no Instagram: @cacaucosmo.

Com carinho,
Cacau Cosmo

---

## Jornada 2 · Tag `circulo-aprovada`

**Assunto:** Você foi selecionada para o Círculo ✦

Olá, *|FNAME|*.

Que alegria: sua entrada no Círculo foi aprovada. A partir de agora, a loja, o Journal e os próximos lotes estão abertos para você.

Para entrar, crie sua senha no botão abaixo. Seu login é este e-mail.

**Botão:** CRIAR MINHA SENHA → link: `*|LINKSENHA|*`

O link vale por 24 horas. Se expirar, acesse cacaucosmo.com.br/minha-conta/ e clique em "Perdeu sua senha?".

Boas-vindas ao Círculo. O próximo ritual começa agora.

---

## Jornada 3 · Tag `circulo-espera`

**Assunto:** Sua solicitação para o Círculo

Olá, *|FNAME|*.

Obrigada por querer fazer parte do Círculo. Cada lote nosso é pequeno, feito à mão e no tempo do cacau, por isso as vagas também são poucas.

Neste momento não conseguimos abrir espaço para todo mundo, mas seu nome ficou na nossa lista de espera. Quando abrirmos novas vagas, você estará entre as primeiras pessoas a saber.

Enquanto isso, acompanhe o que estamos criando no Instagram: @cacaucosmo.

Com carinho,
Cacau Cosmo
