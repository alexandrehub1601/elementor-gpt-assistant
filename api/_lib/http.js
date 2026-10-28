// Utilitários HTTP compartilhados pelas funções da Vercel (também funcionam num servidor Node puro).
import crypto from "node:crypto";

export function send(res, status, data, headers = {}) {
  res.statusCode = status;
  res.setHeader("Content-Type", "application/json; charset=utf-8");
  res.setHeader("Cache-Control", "no-store");
  for (const [k, v] of Object.entries(headers)) res.setHeader(k, v);
  res.end(JSON.stringify(data));
}

export async function readJson(req, limit = 64 * 1024) {
  if (req.body && typeof req.body === "object" && !Buffer.isBuffer(req.body)) return req.body;
  if (typeof req.body === "string") return safeParse(req.body);
  const chunks = [];
  let size = 0;
  for await (const c of req) {
    size += c.length;
    if (size > limit) throw Object.assign(new Error("payload grande demais"), { status: 413 });
    chunks.push(c);
  }
  return safeParse(Buffer.concat(chunks).toString("utf8"));
}
function safeParse(s) {
  try { return s ? JSON.parse(s) : {}; } catch { throw Object.assign(new Error("JSON inválido"), { status: 400 }); }
}

export function clientIp(req) {
  const xf = req.headers["x-forwarded-for"];
  return (Array.isArray(xf) ? xf[0] : xf || "").split(",")[0].trim() || req.socket?.remoteAddress || "desconhecido";
}

// CORS para o formulário (útil se o site estiver num domínio e a API em outro).
export function cors(req, res) {
  const allowed = (process.env.ALLOWED_ORIGINS || "").split(",").map((s) => s.trim()).filter(Boolean);
  const origin = req.headers.origin;
  if (origin && allowed.includes(origin)) {
    res.setHeader("Access-Control-Allow-Origin", origin);
    res.setHeader("Vary", "Origin");
    res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
    res.setHeader("Access-Control-Allow-Headers", "Content-Type");
  }
}

/* ---------- sessão do painel (cookie assinado) ---------- */
const COOKIE = "fale_admin";
const MAX_AGE = 60 * 60 * 12; // 12 horas

function secret() {
  const s = process.env.SESSION_SECRET;
  if (!s || s.length < 32) throw Object.assign(new Error("SESSION_SECRET ausente ou curto (mínimo 32 caracteres)"), { status: 500 });
  return s;
}
function sign(value) {
  return crypto.createHmac("sha256", secret()).update(value).digest("base64url");
}
export function sessionCookie() {
  const exp = String(Math.floor(Date.now() / 1000) + MAX_AGE);
  const secure = process.env.NODE_ENV === "development" || process.env.FALE_INSECURE_COOKIE === "1" ? "" : " Secure;";
  return `${COOKIE}=${exp}.${sign(exp)}; Path=/; HttpOnly;${secure} SameSite=Strict; Max-Age=${MAX_AGE}`;
}
export function clearCookie() {
  return `${COOKIE}=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0`;
}
export function isAuthed(req) {
  const raw = req.headers.cookie || "";
  const m = raw.split(/;\s*/).find((c) => c.startsWith(COOKIE + "="));
  if (!m) return false;
  const [exp, sig] = m.slice(COOKIE.length + 1).split(".");
  if (!exp || !sig) return false;
  const expected = sign(exp);
  const a = Buffer.from(sig), b = Buffer.from(expected);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return false;
  return Number(exp) > Date.now() / 1000;
}
export function checkPassword(given) {
  const real = process.env.ADMIN_PASSWORD;
  if (!real) throw Object.assign(new Error("ADMIN_PASSWORD não configurada"), { status: 500 });
  const a = crypto.createHash("sha256").update(String(given || "")).digest();
  const b = crypto.createHash("sha256").update(real).digest();
  return crypto.timingSafeEqual(a, b);
}
// Exige cabeçalho próprio em ações do painel (proteção extra contra CSRF).
export function isPanelRequest(req) {
  return req.headers["x-fale-painel"] === "1";
}
