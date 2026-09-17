// tickets/src/checker.js
// Roda as sondas de um evento e consolida um status único.

import { probeDiscovery } from "./probes/discovery.js";
import { probePage } from "./probes/page.js";

// AVAILABLE = estoque confirmado. ONSALE = janela de venda aberta, estoque desconhecido.
const PRIORITY = { AVAILABLE: 4, ONSALE: 3, UNAVAILABLE: 2, BLOCKED: 1, UNKNOWN: 0 };

export async function checkEvent(event, options = {}) {
  const apiKey = options.apiKey ?? process.env.TICKETMASTER_API_KEY;
  const usePage = options.usePage ?? process.env.TICKETS_PAGE_PROBE !== "0";

  const probes = [];
  const discovery = await probeDiscovery(event, { apiKey });
  if (discovery) probes.push(discovery);

  // Só vamos à página pública se a via oficial não deu uma resposta conclusiva.
  // ONSALE não é conclusivo sobre estoque: vale a pena tentar a página.
  const officialConclusive =
    discovery && discovery.status !== "UNKNOWN" && discovery.status !== "ONSALE";
  if (usePage && !officialConclusive) {
    probes.push(await probePage(event));
  }

  if (probes.length === 0) {
    return {
      eventId: event.id,
      status: "UNKNOWN",
      detail: "nenhuma sonda ativa: configure TICKETMASTER_API_KEY ou deixe a sonda de página ligada",
      probes,
    };
  }

  const winner = probes.reduce((a, b) => (PRIORITY[b.status] > PRIORITY[a.status] ? b : a));

  return {
    eventId: event.id,
    status: winner.status,
    detail: probes.map((p) => `[${p.probe}] ${p.status}\n${p.detail}`).join("\n\n"),
    priceRanges: probes.find((p) => p.raw?.priceRanges?.length)?.raw.priceRanges || [],
    signature: probes.find((p) => p.raw?.signature)?.raw.signature,
    probes,
  };
}
