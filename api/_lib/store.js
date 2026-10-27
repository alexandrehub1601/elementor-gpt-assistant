// Armazenamento das mensagens.
// Produção: Upstash Redis (integração "Upstash for Redis" / antigo Vercel KV no painel da Vercel).
//   Variáveis: KV_REST_API_URL e KV_REST_API_TOKEN (ou UPSTASH_REDIS_REST_URL e UPSTASH_REDIS_REST_TOKEN).
// Desenvolvimento local: arquivo JSON (FALE_DATA_FILE), só fora da Vercel.
import fs from "node:fs/promises";

const URL_ = process.env.KV_REST_API_URL || process.env.UPSTASH_REDIS_REST_URL;
const TOKEN = process.env.KV_REST_API_TOKEN || process.env.UPSTASH_REDIS_REST_TOKEN;
const LIST = "fale:mensagens";
const KEY = (id) => `fale:mensagem:${id}`;

async function redis(cmd) {
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
  if (URL_ && TOKEN) return "redis";
  if (FILE && !process.env.VERCEL) return "file";
  throw Object.assign(new Error("Armazenamento não configurado: conecte o Upstash Redis ao projeto na Vercel."), { status: 500 });
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
