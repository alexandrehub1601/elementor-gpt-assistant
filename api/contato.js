// POST /api/contato — recebe o formulário "Vamos falar sobre o seu projeto?"
import crypto from "node:crypto";
import { send, readJson, clientIp, cors } from "./_lib/http.js";
import { addMessage, hit } from "./_lib/store.js";

const MOMENTOS = [
  "Estou começando um novo negócio",
  "Já estou no mercado, mas preciso construir uma presença mais profissional",
  "Minha marca já não representa o momento atual do negócio",
  "Estou entrando em uma nova fase",
];
const clean = (v, max) => String(v ?? "").replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, "").trim().slice(0, max);
const isContact = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || v.replace(/\D/g, "").length >= 10;

export default async function handler(req, res) {
  cors(req, res);
  if (req.method === "OPTIONS") { res.statusCode = 204; return res.end(); }
  if (req.method !== "POST") return send(res, 405, { erro: "Use POST." });
  try {
    const b = await readJson(req);
    // Campo-isca contra robôs: pessoas não veem nem preenchem.
    if (b.site_empresa) return send(res, 200, { ok: true });

    const m = {
      nome: clean(b.nome, 120),
      contato: clean(b.contato, 160),
      negocio: clean(b.negocio, 200),
      momento: clean(b.momento, 120),
      projeto: clean(b.projeto, 5000),
      link: clean(b.link, 300),
    };
    const erros = [];
    if (m.nome.length < 2) erros.push("nome");
    if (!isContact(m.contato)) erros.push("contato");
    if (m.negocio.length < 2) erros.push("negocio");
    if (!MOMENTOS.includes(m.momento)) erros.push("momento");
    if (m.projeto.length < 10) erros.push("projeto");
    if (erros.length) return send(res, 422, { erro: "Confira os campos.", campos: erros });

    const ip = clientIp(req);
    const ipHash = crypto.createHash("sha256").update(ip).digest("hex").slice(0, 16);
    if ((await hit("envio:" + ipHash, 600)) > 5) return send(res, 429, { erro: "Muitos envios em pouco tempo. Tente de novo em alguns minutos." });

    await addMessage({ id: crypto.randomUUID(), criadaEm: new Date().toISOString(), lida: false, ...m });
    return send(res, 201, { ok: true });
  } catch (e) {
    return send(res, e.status || 500, { erro: e.status && e.status < 500 ? e.message : "Não foi possível salvar a mensagem agora." });
  }
}
