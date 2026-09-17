// tickets/src/probes/discovery.js
// Sonda oficial: Ticketmaster Discovery API v2 (chave grátis em developer.ticketmaster.com).
// É o caminho suportado pela Ticketmaster — use-o sempre que possível.

const BASE = "https://app.ticketmaster.com/discovery/v2";

const ONSALE_CODES = new Set(["onsale"]);
const OFFSALE_CODES = new Set(["offsale", "canceled", "cancelled", "postponed", "rescheduled"]);

export async function probeDiscovery(event, { apiKey, timeoutMs = 15000 } = {}) {
  if (!apiKey) return null; // sonda desligada quando não há chave
  if (!event.discoveryId) return null;

  const url = `${BASE}/events/${encodeURIComponent(event.discoveryId)}.json?apikey=${encodeURIComponent(apiKey)}&locale=*`;
  let res;
  try {
    res = await fetchWithTimeout(url, { timeoutMs });
  } catch (err) {
    return { probe: "discovery", status: "UNKNOWN", detail: `falha de rede: ${err.message}` };
  }

  if (res.status === 401 || res.status === 403) {
    return { probe: "discovery", status: "UNKNOWN", detail: "chave da Discovery API inválida ou sem permissão (HTTP " + res.status + ")" };
  }
  if (res.status === 404) {
    return {
      probe: "discovery",
      status: "UNKNOWN",
      detail: `evento ${event.discoveryId} não existe na Discovery API — rode 'npm run tickets:find -- "<nome do show>"' para achar o ID correto`,
    };
  }
  if (res.status === 429) {
    return { probe: "discovery", status: "UNKNOWN", detail: "rate limit da Discovery API (429) — aumente pollSeconds" };
  }
  if (!res.ok) {
    return { probe: "discovery", status: "UNKNOWN", detail: `HTTP ${res.status} na Discovery API` };
  }

  let data;
  try {
    data = await res.json();
  } catch (err) {
    return { probe: "discovery", status: "UNKNOWN", detail: `resposta não-JSON: ${err.message}` };
  }

  return interpret(data);
}

export function interpret(data) {
  const code = String(data?.dates?.status?.code || "").toLowerCase();
  const publicSale = data?.sales?.public || {};
  const facts = [];

  if (data?.name) facts.push(`Evento: ${data.name}`);
  if (data?.dates?.start?.localDate) {
    facts.push(`Data: ${data.dates.start.localDate}${data.dates.start.localTime ? " " + data.dates.start.localTime : ""}`);
  }
  const venue = data?._embedded?.venues?.[0];
  if (venue?.name) facts.push(`Local: ${venue.name}${venue.city?.name ? " — " + venue.city.name : ""}`);

  for (const p of data?.priceRanges || []) {
    facts.push(`Preço (${p.type || "standard"}): ${p.min}–${p.max} ${p.currency}`);
  }
  if (publicSale.startDateTime) facts.push(`Venda pública abre: ${publicSale.startDateTime}`);
  if (publicSale.endDateTime) facts.push(`Venda pública fecha: ${publicSale.endDateTime}`);

  const presales = data?.sales?.presales || [];
  if (presales.length) {
    facts.push(`Pré-vendas: ${presales.map((p) => p.name || "sem nome").join(", ")}`);
  }

  const startsInFuture =
    publicSale.startDateTime && Date.parse(publicSale.startDateTime) > Date.now();

  let status = "UNKNOWN";
  if (ONSALE_CODES.has(code)) {
    // "onsale" com a venda ainda por abrir = anunciado, mas nada à venda agora.
    status = startsInFuture ? "UNAVAILABLE" : "AVAILABLE";
  } else if (OFFSALE_CODES.has(code)) {
    status = "UNAVAILABLE";
  }

  facts.unshift(`Status Ticketmaster: ${code || "desconhecido"}`);

  return {
    probe: "discovery",
    status,
    detail: facts.join("\n"),
    raw: { code, priceRanges: data?.priceRanges || [], url: data?.url },
  };
}

export async function fetchWithTimeout(url, { timeoutMs = 15000, ...init } = {}) {
  const ac = new AbortController();
  const t = setTimeout(() => ac.abort(), timeoutMs);
  try {
    return await fetch(url, { ...init, signal: ac.signal });
  } finally {
    clearTimeout(t);
  }
}
