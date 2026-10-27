// POST /api/logout — encerra a sessão do painel.
import { send, clearCookie } from "./_lib/http.js";
export default function handler(req, res) {
  if (req.method !== "POST") return send(res, 405, { erro: "Use POST." });
  return send(res, 200, { ok: true }, { "Set-Cookie": clearCookie() });
}
