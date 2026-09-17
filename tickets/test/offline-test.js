#!/usr/bin/env node
// tickets/test/offline-test.js
// Testa a lógica de decisão sem tocar na rede (respostas da Ticketmaster são simuladas).

import assert from "node:assert/strict";
import { interpret } from "../src/probes/discovery.js";
import { readJsonLd } from "../src/probes/page.js";
import { shouldNotify } from "../src/state.js";
import { stripQueueToken } from "../src/config.js";

let passed = 0;
function t(name, fn) {
  try {
    fn();
    passed++;
    console.log(`  ok  ${name}`);
  } catch (err) {
    console.error(`FALHOU  ${name}\n        ${err.message}`);
    process.exitCode = 1;
  }
}

console.log("Discovery API:");
t("onsale com venda já aberta => AVAILABLE", () => {
  const r = interpret({
    name: "Harry Styles",
    dates: { status: { code: "onsale" }, start: { localDate: "2027-08-28" } },
    sales: { public: { startDateTime: "2026-01-01T10:00:00Z" } },
    priceRanges: [{ type: "standard", currency: "GBP", min: 60, max: 250 }],
  });
  assert.equal(r.status, "AVAILABLE");
  assert.match(r.detail, /60–250 GBP/);
});

t("onsale com venda no futuro => UNAVAILABLE", () => {
  const future = new Date(Date.now() + 86400000).toISOString();
  const r = interpret({ dates: { status: { code: "onsale" } }, sales: { public: { startDateTime: future } } });
  assert.equal(r.status, "UNAVAILABLE");
});

t("offsale => UNAVAILABLE", () => {
  assert.equal(interpret({ dates: { status: { code: "offsale" } } }).status, "UNAVAILABLE");
});

t("status desconhecido => UNKNOWN", () => {
  assert.equal(interpret({ dates: {} }).status, "UNKNOWN");
});

console.log("JSON-LD da página:");
t("InStock => AVAILABLE", () => {
  const html = `<script type="application/ld+json">${JSON.stringify({
    "@type": "Event",
    offers: [{ availability: "https://schema.org/InStock", price: "89", priceCurrency: "EUR" }],
  })}</script>`;
  const r = readJsonLd(html);
  assert.equal(r.status, "AVAILABLE");
  assert.match(r.detail, /89 EUR/);
});

t("SoldOut => UNAVAILABLE", () => {
  const html = `<script type="application/ld+json">${JSON.stringify({
    "@graph": [{ "@type": "Event", offers: { availability: "http://schema.org/SoldOut" } }],
  })}</script>`;
  assert.equal(readJsonLd(html).status, "UNAVAILABLE");
});

t("HTML sem JSON-LD => null", () => {
  assert.equal(readJsonLd("<html><body>oi</body></html>"), null);
});

console.log("Anti-spam de e-mail:");
t("primeira vez disponível => notifica", () => {
  assert.equal(shouldNotify(undefined, { status: "AVAILABLE" }, 6).notify, true);
});

t("já estava disponível e foi avisado agora => não repete", () => {
  const prev = { status: "AVAILABLE", notifiedAt: new Date().toISOString() };
  assert.equal(shouldNotify(prev, { status: "AVAILABLE" }, 6).notify, false);
});

t("disponível há mais que renotifyHours => reavisa", () => {
  const prev = { status: "AVAILABLE", notifiedAt: new Date(Date.now() - 7 * 3600_000).toISOString() };
  assert.equal(shouldNotify(prev, { status: "AVAILABLE" }, 6).notify, true);
});

t("indisponível nunca notifica", () => {
  assert.equal(shouldNotify({ status: "AVAILABLE" }, { status: "UNAVAILABLE" }, 6).notify, false);
  assert.equal(shouldNotify(undefined, { status: "BLOCKED" }, 6).notify, false);
});

t("voltou a ficar disponível depois de sumir => notifica de novo", () => {
  const prev = { status: "UNAVAILABLE", notifiedAt: new Date().toISOString() };
  assert.equal(shouldNotify(prev, { status: "AVAILABLE" }, 6).notify, true);
});

console.log("URL:");
t("queueittoken é removido da URL", () => {
  const out = stripQueueToken("https://www.ticketmaster.it/x/event/72zs3kweso14/ticketmaster?queueittoken=e_abc~ts_123");
  assert.ok(!out.includes("queueittoken"));
});

console.log(`\n${passed} verificações passaram.`);
