// tickets/src/notify.js
// Envio de e-mail. Dois transportes:
//   MAIL_TRANSPORT=smtp   (padrão) — qualquer SMTP, inclusive Gmail com "senha de app"
//   MAIL_TRANSPORT=resend         — API HTTP da Resend (boa para serverless, onde SMTP costuma ser bloqueado)
// DRY_RUN=1 imprime o e-mail no console em vez de enviar.

let cachedTransport = null;

export function mailConfig() {
  return {
    transport: (process.env.MAIL_TRANSPORT || "smtp").toLowerCase(),
    from: process.env.MAIL_FROM || process.env.SMTP_USER || "tickets-bot@localhost",
    dryRun: process.env.DRY_RUN === "1",
  };
}

export async function sendMail({ to, subject, text, html }) {
  const cfg = mailConfig();

  if (cfg.dryRun) {
    console.log("\n--- DRY RUN (nada foi enviado) ---");
    console.log("Para:", to.join(", "));
    console.log("Assunto:", subject);
    console.log(text);
    console.log("--- fim ---\n");
    return { dryRun: true };
  }

  if (cfg.transport === "resend") return sendViaResend({ to, subject, text, html, from: cfg.from });
  return sendViaSmtp({ to, subject, text, html, from: cfg.from });
}

async function sendViaSmtp({ to, subject, text, html, from }) {
  const { default: nodemailer } = await import("nodemailer");

  if (!cachedTransport) {
    const host = requireEnv("SMTP_HOST");
    const port = Number(process.env.SMTP_PORT || 587);
    cachedTransport = nodemailer.createTransport({
      host,
      port,
      secure: process.env.SMTP_SECURE ? process.env.SMTP_SECURE === "1" : port === 465,
      auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: requireEnv("SMTP_PASS") } : undefined,
    });
  }

  const info = await cachedTransport.sendMail({ from, to: to.join(", "), subject, text, html });
  return { messageId: info.messageId, accepted: info.accepted };
}

async function sendViaResend({ to, subject, text, html, from }) {
  const key = requireEnv("RESEND_API_KEY");
  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
    body: JSON.stringify({ from, to, subject, text, html }),
  });
  const body = await res.text();
  if (!res.ok) throw new Error(`Resend respondeu HTTP ${res.status}: ${body}`);
  return JSON.parse(body);
}

function requireEnv(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Variável de ambiente ${name} não configurada`);
  return v;
}

export function buildAlert(event, result, reason, kind = "available") {
  const when = new Date().toLocaleString("pt-BR", { timeZone: process.env.TZ || "America/Sao_Paulo" });
  const isChange = kind === "change";

  const subject = isChange
    ? `👀 Mexeu algo no show: ${event.name}`
    : `🎟️ Ingresso disponível: ${event.name}`;

  const headline = isChange
    ? `A Ticketmaster mudou alguma informação de ${event.name}. Pode ser lote novo, revenda liberada ou só ajuste do site — vale abrir e conferir.`
    : `Apareceu ingresso para ${event.name} (${reason}).`;

  const text = [
    headline,
    "",
    `Comprar: ${event.url}`,
    "",
    "Detalhes da verificação:",
    result.detail,
    "",
    `Verificado em ${when}.`,
    "",
    isChange
      ? "Aviso automático de mudança — não é garantia de que há ingresso à venda."
      : "Corra — a disponibilidade na Ticketmaster muda em segundos.",
    "A compra continua sendo feita por você, no site oficial.",
  ].join("\n");

  const html = `
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:560px">
  <h2 style="margin:0 0 4px">${isChange ? "👀 Mexeu algo no show" : "🎟️ Ingresso disponível"}</h2>
  <p style="margin:0 0 16px;color:#555">${escapeHtml(event.name)}</p>
  <p style="margin:0 0 16px">${escapeHtml(headline)}</p>
  <p style="margin:0 0 20px">
    <a href="${escapeHtml(event.url)}"
       style="background:#026cdf;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;display:inline-block">
      Abrir na Ticketmaster
    </a>
  </p>
  <pre style="background:#f5f5f5;padding:12px;border-radius:6px;white-space:pre-wrap;font-size:13px">${escapeHtml(result.detail)}</pre>
  <p style="color:#777;font-size:12px">Verificado em ${escapeHtml(when)}. Aviso automático — a compra é feita por você, no site oficial.</p>
</div>`.trim();

  return { subject, text, html };
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}
