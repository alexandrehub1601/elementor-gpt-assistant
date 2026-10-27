// POST /api/login — entra no painel com a senha (ADMIN_PASSWORD). GET /api/login — diz se a sessão é válida.
import crypto from "node:crypto";
import { send, readJson, clientIp, sessionCookie, checkPassword, isAuthed, isPanelRequest } from "./_lib/http.js";
import { hit } from "./_lib/store.js";

export default async function handler(req, res) {
  try {
    if (req.method === "GET") return send(res, 200, { autenticado: isAuthed(req) });
    if (req.method !== "POST") return send(res, 405, { erro: "Use POST." });
    if (!isPanelRequest(req)) return send(res, 403, { erro: "Requisição não permitida." });
    const ipHash = crypto.createHash("sha256").update(clientIp(req)).digest("hex").slice(0, 16);
    if ((await hit("login:" + ipHash, 900)) > 10) return send(res, 429, { erro: "Muitas tentativas. Aguarde 15 minutos e tente de novo." });
    const { senha } = await readJson(req, 4096);
    if (!checkPassword(senha)) return send(res, 401, { erro: "Senha incorreta." });
    return send(res, 200, { ok: true }, { "Set-Cookie": sessionCookie() });
  } catch (e) {
    return send(res, e.status || 500, { erro: e.message || "Erro ao entrar." });
  }
}
