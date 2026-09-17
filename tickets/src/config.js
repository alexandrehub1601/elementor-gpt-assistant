// tickets/src/config.js
// Carrega e valida o arquivo de configuração dos eventos monitorados.

import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

export const DEFAULT_CONFIG_PATH = resolve(here, "..", "events.config.json");

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function fail(msg) {
  throw new Error(`Config inválida: ${msg}`);
}

export function loadConfig(configPath = process.env.TICKETS_CONFIG || DEFAULT_CONFIG_PATH) {
  let raw;
  try {
    raw = readFileSync(configPath, "utf8");
  } catch (err) {
    fail(`não consegui ler ${configPath} (${err.code || err.message})`);
  }

  let cfg;
  try {
    cfg = JSON.parse(raw);
  } catch (err) {
    fail(`${configPath} não é um JSON válido — ${err.message}`);
  }

  if (!Array.isArray(cfg.events) || cfg.events.length === 0) {
    fail("a lista 'events' está vazia");
  }

  const seen = new Set();
  for (const ev of cfg.events) {
    if (!ev.id) fail("todo evento precisa de um 'id' único");
    if (seen.has(ev.id)) fail(`id de evento duplicado: ${ev.id}`);
    seen.add(ev.id);
    if (!ev.url) fail(`evento ${ev.id} está sem 'url'`);
    if (!Array.isArray(ev.recipients) || ev.recipients.length === 0) {
      fail(`evento ${ev.id} está sem destinatários ('recipients')`);
    }
    for (const mail of ev.recipients) {
      if (!EMAIL_RE.test(mail)) fail(`e-mail inválido em ${ev.id}: ${mail}`);
    }
    ev.name = ev.name || ev.id;
    // O queueittoken da URL é pessoal e expira: ele nunca deve ser reaproveitado.
    ev.url = stripQueueToken(ev.url);
  }

  return {
    configPath,
    pollSeconds: clamp(cfg.pollSeconds ?? 300, 60, 86400),
    jitterSeconds: clamp(cfg.jitterSeconds ?? 90, 0, 3600),
    renotifyHours: clamp(cfg.renotifyHours ?? 6, 0, 24 * 30),
    notifyErrorsTo: Array.isArray(cfg.notifyErrorsTo) ? cfg.notifyErrorsTo.filter((m) => EMAIL_RE.test(m)) : [],
    events: cfg.events,
  };
}

export function stripQueueToken(url) {
  try {
    const u = new URL(url);
    u.searchParams.delete("queueittoken");
    u.searchParams.delete("queueitToken");
    return u.toString().replace(/\?$/, "");
  } catch {
    return url;
  }
}

function clamp(n, min, max) {
  const v = Number(n);
  if (!Number.isFinite(v)) return min;
  return Math.min(max, Math.max(min, v));
}
