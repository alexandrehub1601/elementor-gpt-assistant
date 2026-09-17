#!/usr/bin/env node
// tickets/src/find.js
// Descobre o ID de um evento na Discovery API:
//   node tickets/src/find.js "harry styles" GB
//   node tickets/src/find.js "harry styles" IT

import { fetchWithTimeout } from "./probes/discovery.js";

const keyword = process.argv[2];
const countryCode = process.argv[3];
const apiKey = process.env.TICKETMASTER_API_KEY;

if (!keyword) {
  console.error('Uso: node tickets/src/find.js "<palavra-chave>" [CÓDIGO_DO_PAÍS]');
  process.exit(2);
}
if (!apiKey) {
  console.error("Configure TICKETMASTER_API_KEY (chave grátis em developer.ticketmaster.com).");
  process.exit(2);
}

const params = new URLSearchParams({ apikey: apiKey, keyword, size: "50", locale: "*" });
if (countryCode) params.set("countryCode", countryCode);

const res = await fetchWithTimeout(`https://app.ticketmaster.com/discovery/v2/events.json?${params}`);
if (!res.ok) {
  console.error(`Discovery API respondeu HTTP ${res.status}: ${await res.text()}`);
  process.exit(1);
}

const data = await res.json();
const events = data?._embedded?.events || [];
if (!events.length) {
  console.log("Nenhum evento encontrado para essa busca.");
  process.exit(0);
}

for (const ev of events) {
  const venue = ev._embedded?.venues?.[0];
  console.log(
    [
      `id:     ${ev.id}`,
      `nome:   ${ev.name}`,
      `data:   ${ev.dates?.start?.localDate || "?"}`,
      `local:  ${venue?.name || "?"} — ${venue?.city?.name || "?"} (${venue?.country?.countryCode || "?"})`,
      `status: ${ev.dates?.status?.code || "?"}`,
      `url:    ${ev.url}`,
      "",
    ].join("\n"),
  );
}
