// tickets/src/state.js
// Guarda o último status conhecido de cada evento para não reenviar e-mail repetido.

import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

export const DEFAULT_STATE_PATH =
  process.env.TICKETS_STATE_FILE || resolve(here, "..", ".state", "monitor-state.json");

export function loadState(path = DEFAULT_STATE_PATH) {
  try {
    return JSON.parse(readFileSync(path, "utf8"));
  } catch {
    // Primeira execução (ou cache perdido no CI): começa do zero.
    return {};
  }
}

export function saveState(state, path = DEFAULT_STATE_PATH) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, JSON.stringify(state, null, 2) + "\n", "utf8");
}

/**
 * Decide se este resultado merece um e-mail.
 * Avisa quando o evento ENTRA em AVAILABLE, e reavisa depois de renotifyHours
 * enquanto continuar disponível (útil se o primeiro e-mail passou batido).
 */
export function shouldNotify(previous, result, renotifyHours) {
  if (result.status !== "AVAILABLE") return { notify: false };

  if (!previous || previous.status !== "AVAILABLE") {
    return { notify: true, reason: "ficou disponível" };
  }

  if (renotifyHours > 0 && previous.notifiedAt) {
    const elapsedH = (Date.now() - Date.parse(previous.notifiedAt)) / 3_600_000;
    if (Number.isFinite(elapsedH) && elapsedH >= renotifyHours) {
      return { notify: true, reason: `continua disponível há ${Math.floor(elapsedH)}h` };
    }
  }

  return { notify: false };
}

export function recordResult(state, eventId, result, notified) {
  const previous = state[eventId];
  state[eventId] = {
    status: result.status,
    detail: result.detail,
    checkedAt: new Date().toISOString(),
    notifiedAt: notified ? new Date().toISOString() : previous?.notifiedAt,
    consecutiveErrors:
      result.status === "BLOCKED" || result.status === "UNKNOWN"
        ? (previous?.consecutiveErrors || 0) + 1
        : 0,
  };
  return state[eventId];
}
