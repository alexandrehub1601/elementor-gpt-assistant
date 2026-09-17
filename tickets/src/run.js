#!/usr/bin/env node
// tickets/src/run.js
// Uso:
//   node tickets/src/run.js once     -> verifica uma vez e sai (ideal para cron/GitHub Actions)
//   node tickets/src/run.js watch    -> fica rodando e verifica a cada pollSeconds (ideal para VPS)
//   node tickets/src/run.js test     -> envia um e-mail de teste para todos os destinatários

import { loadConfig } from "./config.js";
import { checkEvent } from "./checker.js";
import { buildAlert, sendMail } from "./notify.js";
import { loadState, recordResult, saveState, shouldNotify } from "./state.js";

const ERROR_ALERT_AFTER = Number(process.env.TICKETS_ERROR_ALERT_AFTER || 12);

function log(...args) {
  console.log(`[${new Date().toISOString()}]`, ...args);
}

export async function runOnce(cfg = loadConfig()) {
  const state = loadState();
  const summary = [];

  for (const event of cfg.events) {
    let result;
    try {
      result = await checkEvent(event);
    } catch (err) {
      result = { eventId: event.id, status: "UNKNOWN", detail: `erro inesperado: ${err.message}`, probes: [] };
    }

    const previous = state[event.id];
    const decision = shouldNotify(previous, result, cfg.renotifyHours);
    let notified = false;

    if (decision.notify) {
      const { subject, text, html } = buildAlert(event, result, decision.reason);
      try {
        await sendMail({ to: event.recipients, subject, text, html });
        notified = true;
        log(`ALERTA enviado — ${event.name} -> ${event.recipients.join(", ")}`);
      } catch (err) {
        log(`FALHA ao enviar e-mail de ${event.name}: ${err.message}`);
      }
    }

    const saved = recordResult(state, event.id, result, notified);
    log(`${event.name}: ${result.status}${notified ? " (e-mail enviado)" : ""}`);
    if (result.status !== "AVAILABLE") log(`  ${result.detail.replace(/\n/g, "\n  ")}`);

    // Se o bot está cego há muitos ciclos seguidos, avisa quem administra.
    if (saved.consecutiveErrors === ERROR_ALERT_AFTER && cfg.notifyErrorsTo.length) {
      try {
        await sendMail({
          to: cfg.notifyErrorsTo,
          subject: `⚠️ Monitor sem leitura confiável: ${event.name}`,
          text: `Últimas ${ERROR_ALERT_AFTER} verificações voltaram ${saved.status}.\n\n${result.detail}\n\n${event.url}`,
        });
      } catch (err) {
        log(`não consegui avisar sobre a falha: ${err.message}`);
      }
    }

    summary.push({ event: event.name, status: result.status, notified });
  }

  saveState(state);
  return summary;
}

async function watch(cfg) {
  log(`Monitorando ${cfg.events.length} evento(s) a cada ~${cfg.pollSeconds}s (jitter ${cfg.jitterSeconds}s)`);
  for (;;) {
    await runOnce(cfg);
    const jitter = Math.floor(Math.random() * (cfg.jitterSeconds + 1));
    const waitMs = (cfg.pollSeconds + jitter) * 1000;
    log(`próxima verificação em ${Math.round(waitMs / 1000)}s`);
    await new Promise((r) => setTimeout(r, waitMs));
  }
}

async function testEmail(cfg) {
  for (const event of cfg.events) {
    const fake = { status: "AVAILABLE", detail: "Teste de configuração — nenhum ingresso foi encontrado agora." };
    const { subject, text, html } = buildAlert(event, fake, "teste de configuração");
    await sendMail({ to: event.recipients, subject: `[TESTE] ${subject}`, text, html });
    log(`teste enviado para ${event.recipients.join(", ")}`);
  }
}

const mode = process.argv[2] || "once";
const cfg = loadConfig();

const actions = { once: () => runOnce(cfg), watch: () => watch(cfg), test: () => testEmail(cfg) };

if (!actions[mode]) {
  console.error(`Modo desconhecido: ${mode}. Use: once | watch | test`);
  process.exit(2);
}

actions[mode]().catch((err) => {
  console.error("Erro fatal:", err.message);
  process.exit(1);
});
