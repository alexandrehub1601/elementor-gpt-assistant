// GET /api/status — diagnóstico da instalação. Mostra só NOMES de variáveis (nunca valores).
import { send } from "./_lib/http.js";
import { storageInfo } from "./_lib/store.js";

export default function handler(req, res) {
  const names = Object.keys(process.env).filter((k) => /KV|REDIS|UPSTASH|BLOB/i.test(k)).sort();
  const has = (n) => !!process.env[n];
  return send(res, 200, {
    banco_conectado: storageInfo().redis,
    imagens_conectadas: !!(process.env.BLOB_READ_WRITE_TOKEN || names.some((k) => k.endsWith("BLOB_READ_WRITE_TOKEN"))),
    senha_do_painel_criada: has("ADMIN_PASSWORD"),
    session_secret_ok: (process.env.SESSION_SECRET || "").length >= 32,
    site_url: process.env.SITE_URL || null,
    ambiente: process.env.VERCEL_ENV || "local",
    variaveis_de_armazenamento_encontradas: names,
  });
}
