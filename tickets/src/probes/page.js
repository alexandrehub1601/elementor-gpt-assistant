// tickets/src/probes/page.js
// Sonda secundária (best-effort): lê a página pública do evento e procura o
// JSON-LD schema.org que a Ticketmaster publica (offers.availability).
//
// Limites conhecidos e propositais:
//  - A Ticketmaster protege as páginas com fila (Queue-it) e anti-bot. Quando a
//    resposta for uma dessas, devolvemos BLOCKED e NÃO tentamos contornar nada:
//    sem proxies rotativos, sem resolver captcha, sem token de fila reaproveitado.
//  - Por isso esta sonda é um complemento da Discovery API, nunca a base.

import { fetchWithTimeout } from "./discovery.js";

const UA =
  process.env.TICKETS_USER_AGENT ||
  "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";

const BLOCK_MARKERS = [
  "queue-it",
  "queueit",
  "waiting room",
  "sala de espera",
  "access denied",
  "incapsula",
  "px-captcha",
  "are you a human",
  "verifica che sei umano",
];

const SOLD_OUT_MARKERS = [
  "sold out",
  "esaurito",
  "esgotado",
  "no tickets available",
  "nessun biglietto disponibile",
];

export async function probePage(event, { timeoutMs = 20000 } = {}) {
  let res;
  let body;
  try {
    res = await fetchWithTimeout(event.url, {
      timeoutMs,
      redirect: "follow",
      headers: {
        "User-Agent": UA,
        Accept: "text/html,application/xhtml+xml",
        "Accept-Language": "pt-BR,pt;q=0.9,en;q=0.8,it;q=0.7",
      },
    });
    body = await res.text();
  } catch (err) {
    return { probe: "page", status: "UNKNOWN", detail: `falha de rede: ${err.message}` };
  }

  if (res.status === 403 || res.status === 429 || res.status === 503) {
    return { probe: "page", status: "BLOCKED", detail: `página respondeu HTTP ${res.status} (anti-bot/fila)` };
  }
  if (!res.ok) {
    return { probe: "page", status: "UNKNOWN", detail: `HTTP ${res.status} na página do evento` };
  }

  const lower = body.toLowerCase();
  const marker = BLOCK_MARKERS.find((m) => lower.includes(m));
  if (marker) {
    return { probe: "page", status: "BLOCKED", detail: `página devolveu fila/anti-bot (marcador: "${marker}")` };
  }

  const fromJsonLd = readJsonLd(body);
  if (fromJsonLd) return fromJsonLd;

  if (SOLD_OUT_MARKERS.some((m) => lower.includes(m))) {
    return { probe: "page", status: "UNAVAILABLE", detail: "página indica esgotado" };
  }

  return { probe: "page", status: "UNKNOWN", detail: "não achei sinal de disponibilidade no HTML" };
}

export function readJsonLd(html) {
  const blocks = [...html.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)];
  for (const [, json] of blocks) {
    let parsed;
    try {
      parsed = JSON.parse(json.trim());
    } catch {
      continue;
    }
    for (const node of flatten(parsed)) {
      const offers = [].concat(node?.offers || []);
      for (const offer of offers) {
        const availability = String(offer?.availability || "").toLowerCase();
        if (!availability) continue;
        if (availability.includes("instock") || availability.includes("limitedavailability")) {
          const price = offer.price ? ` (a partir de ${offer.price} ${offer.priceCurrency || ""})`.trimEnd() : "";
          return { probe: "page", status: "AVAILABLE", detail: `JSON-LD: ${offer.availability}${price}` };
        }
        if (availability.includes("soldout")) {
          return { probe: "page", status: "UNAVAILABLE", detail: `JSON-LD: ${offer.availability}` };
        }
        if (availability.includes("preorder") || availability.includes("presale")) {
          return { probe: "page", status: "UNAVAILABLE", detail: `JSON-LD: ${offer.availability} (ainda não abriu)` };
        }
      }
    }
  }
  return null;
}

function* flatten(node) {
  if (Array.isArray(node)) {
    for (const n of node) yield* flatten(n);
    return;
  }
  if (node && typeof node === "object") {
    yield node;
    if (Array.isArray(node["@graph"])) yield* flatten(node["@graph"]);
  }
}
