// Armazenamento das mensagens.
// Produção: Upstash for Redis (REST: KV_REST_API_URL/KV_REST_API_TOKEN, com ou sem prefixo)
//   ou qualquer Redis com endereço redis:// em REDIS_URL (ex.: Redis Cloud).
// Desenvolvimento local: arquivo JSON (FALE_DATA_FILE), só fora da Vercel.
import fs from "node:fs/promises";

// Aceita os nomes com ou sem prefixo (ex.: STORAGE_KV_REST_API_URL), que a Vercel pode criar ao conectar.
function findEnv(names) {
  for (const n of names) if (process.env[n]) return process.env[n];
  for (const [k, v] of Object.entries(process.env)) if (v && names.some((n) => k.endsWith("_" + n))) return v;
  return "";
}
let URL_ = findEnv(["KV_REST_API_URL", "UPSTASH_REDIS_REST_URL"]);
let TOKEN = findEnv(["KV_REST_API_TOKEN", "UPSTASH_REDIS_REST_TOKEN"]);
// Se só existir o endereço redis:// do Upstash, monta a conexão REST a partir dele.
if (!URL_ || !TOKEN) {
  const raw = findEnv(["KV_URL", "REDIS_URL", "UPSTASH_REDIS_URL"]);
  try {
    const u = new URL(raw);
    if (/\.upstash\.io$/.test(u.hostname) && u.password) { URL_ = "https://" + u.hostname; TOKEN = decodeURIComponent(u.password); }
  } catch { /* sem conexão */ }
}
// Outros provedores de Redis (ex.: Redis Cloud) usam conexão direta redis:// em vez de REST.
const TCP_URL = URL_ && TOKEN ? "" : findEnv(["REDIS_URL", "KV_URL", "UPSTASH_REDIS_URL"]);
let tcpClient = null;
async function tcp() {
  if (!tcpClient) {
    const { createClient } = await import("redis");
    const c = createClient({ url: TCP_URL, socket: { connectTimeout: 5000 } });
    c.on("error", () => {});
    await c.connect();
    tcpClient = c;
  }
  return tcpClient;
}
export const storageInfo = () => ({ redis: !!((URL_ && TOKEN) || TCP_URL) });
const LIST = "fale:mensagens";
const KEY = (id) => `fale:mensagem:${id}`;

async function redis(cmd) {
  if (!(URL_ && TOKEN)) {
    try { return await (await tcp()).sendCommand(cmd.map(String)); }
    catch (e) { tcpClient = null; throw Object.assign(new Error("Erro no banco de dados: " + e.message), { status: 502 }); }
  }
  const r = await fetch(URL_, { method: "POST", headers: { Authorization: `Bearer ${TOKEN}` }, body: JSON.stringify(cmd) });
  const j = await r.json().catch(() => ({}));
  if (!r.ok || j.error) throw Object.assign(new Error("Erro no banco de dados: " + (j.error || r.status)), { status: 502 });
  return j.result;
}

/* ---------- arquivo local (dev) ---------- */
const FILE = process.env.FALE_DATA_FILE;
async function readFile() {
  try { return JSON.parse(await fs.readFile(FILE, "utf8")); } catch { return { mensagens: [], contadores: {} }; }
}
async function writeFile(d) { await fs.writeFile(FILE, JSON.stringify(d, null, 2)); }

function backend() {
  if ((URL_ && TOKEN) || TCP_URL) return "redis";
  if (FILE && !process.env.VERCEL) return "file";
  throw Object.assign(new Error("Armazenamento não configurado: conecte o Upstash for Redis ao projeto na Vercel e faça Redeploy."), { status: 500 });
}

export async function addMessage(msg) {
  if (backend() === "redis") {
    await redis(["SET", KEY(msg.id), JSON.stringify(msg)]);
    await redis(["ZADD", LIST, Date.parse(msg.criadaEm), msg.id]);
    return;
  }
  const d = await readFile(); d.mensagens.unshift(msg); await writeFile(d);
}

export async function listMessages() {
  if (backend() === "redis") {
    const ids = (await redis(["ZRANGE", LIST, 0, -1, "REV"])) || [];
    if (!ids.length) return [];
    const vals = await redis(["MGET", ...ids.map(KEY)]);
    return vals.filter(Boolean).map((v) => JSON.parse(v));
  }
  return (await readFile()).mensagens;
}

export async function updateMessage(id, patch) {
  if (backend() === "redis") {
    const v = await redis(["GET", KEY(id)]);
    if (!v) return null;
    const m = { ...JSON.parse(v), ...patch };
    await redis(["SET", KEY(id), JSON.stringify(m)]);
    return m;
  }
  const d = await readFile(); const m = d.mensagens.find((x) => x.id === id);
  if (!m) return null; Object.assign(m, patch); await writeFile(d); return m;
}

export async function deleteMessage(id) {
  if (backend() === "redis") {
    await redis(["DEL", KEY(id)]); await redis(["ZREM", LIST, id]); return;
  }
  const d = await readFile(); d.mensagens = d.mensagens.filter((x) => x.id !== id); await writeFile(d);
}

// Limite de tentativas (envios e logins) por janela de tempo.
export async function hit(key, windowSec) {
  if (backend() === "redis") {
    const n = await redis(["INCR", "fale:limite:" + key]);
    if (n === 1) await redis(["EXPIRE", "fale:limite:" + key, windowSec]);
    return n;
  }
  const d = await readFile(); const now = Date.now();
  const c = d.contadores[key];
  d.contadores[key] = c && c.ate > now ? { n: c.n + 1, ate: c.ate } : { n: 1, ate: now + windowSec * 1000 };
  await writeFile(d); return d.contadores[key].n;
}

/* ======================= CASES ======================= */
import SEED from "./seed.js";
const ORDER = "fale:cases:ordem";
const CKEY = (slug) => `fale:case:${slug}`;

async function ensureSeed() {
  if (backend() === "redis") {
    const order = await redis(["GET", ORDER]);
    if (order) return JSON.parse(order);
    for (const c of SEED) await redis(["SET", CKEY(c.slug), JSON.stringify(c)]);
    const slugs = SEED.map((c) => c.slug);
    await redis(["SET", ORDER, JSON.stringify(slugs)]);
    return slugs;
  }
  const d = await readFile();
  if (!d.casos) { d.casos = JSON.parse(JSON.stringify(SEED)); await writeFile(d); }
  return d.casos.map((c) => c.slug);
}

// Sem banco conectado, as páginas públicas usam os cases iniciais (o site nunca fica quebrado).
function configured() { try { backend(); return true; } catch { return false; } }
const seedCopy = () => JSON.parse(JSON.stringify(SEED));

export async function listCases() {
  if (!configured()) return seedCopy();
  const slugs = await ensureSeed();
  if (backend() === "redis") {
    if (!slugs.length) return [];
    const vals = await redis(["MGET", ...slugs.map(CKEY)]);
    return vals.filter(Boolean).map((v) => JSON.parse(v));
  }
  return (await readFile()).casos;
}

export async function getCase(slug) {
  if (!configured()) return seedCopy().find((c) => c.slug === slug) || null;
  await ensureSeed();
  if (backend() === "redis") {
    const v = await redis(["GET", CKEY(slug)]);
    return v ? JSON.parse(v) : null;
  }
  return (await readFile()).casos.find((c) => c.slug === slug) || null;
}

// Cria ou atualiza. oldSlug permite trocar o endereço do case.
export async function saveCase(c, oldSlug) {
  const slugs = await ensureSeed();
  const isNew = !oldSlug;
  if ((isNew || oldSlug !== c.slug) && slugs.includes(c.slug)) {
    throw Object.assign(new Error("Já existe um case com esse endereço."), { status: 409 });
  }
  if (!isNew && !slugs.includes(oldSlug)) throw Object.assign(new Error("Case não encontrado."), { status: 404 });
  if (backend() === "redis") {
    const order = isNew ? [...slugs, c.slug] : slugs.map((s) => (s === oldSlug ? c.slug : s));
    await redis(["SET", CKEY(c.slug), JSON.stringify(c)]);
    if (!isNew && oldSlug !== c.slug) await redis(["DEL", CKEY(oldSlug)]);
    await redis(["SET", ORDER, JSON.stringify(order)]);
    return c;
  }
  const d = await readFile();
  if (isNew) d.casos.push(c); else d.casos = d.casos.map((x) => (x.slug === oldSlug ? c : x));
  await writeFile(d); return c;
}

export async function deleteCase(slug) {
  const slugs = await ensureSeed();
  if (backend() === "redis") {
    await redis(["DEL", CKEY(slug)]);
    await redis(["SET", ORDER, JSON.stringify(slugs.filter((s) => s !== slug))]);
    return;
  }
  const d = await readFile(); d.casos = d.casos.filter((c) => c.slug !== slug); await writeFile(d);
}

export async function setCaseOrder(newOrder) {
  const slugs = await ensureSeed();
  const valid = newOrder.filter((s) => slugs.includes(s));
  const final = [...new Set([...valid, ...slugs])];
  if (backend() === "redis") { await redis(["SET", ORDER, JSON.stringify(final)]); return final; }
  const d = await readFile(); d.casos = final.map((s) => d.casos.find((c) => c.slug === s)); await writeFile(d); return final;
}
