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
// Formulário da landing de Black Friday para advogados (2 etapas, campos próprios).
const BF = {
  marca: ["Ainda não tenho", "Fiz sozinho, com IA ou com um conhecido", "Tenho, mas não representa mais o escritório", "Tenho e quero cuidar também do Instagram"],
  tamanho: ["Atuo sozinho", "2 a 5 pessoas", "6 a 15 pessoas", "Mais de 15 pessoas"],
  investimento: ["Até R$ 3 mil", "De R$ 3 mil a R$ 8 mil", "De R$ 8 mil a R$ 15 mil", "Acima de R$ 15 mil"],
};
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

    if (b.origem === "black-friday") return blackFriday(req, res, b);
    const m = {
      nome: clean(b.nome, 120),
      contato: clean(b.contato, 160),
      negocio: clean(b.negocio, 200),
      momento: clean(b.momento, 120),
      projeto: clean(b.projeto, 5000),
      link: clean(b.link, 300),
      origem: /^[a-z0-9-]{1,40}$/.test(String(b.origem || "")) ? String(b.origem) : "site",
    };
    const erros = [];
    if (m.nome.length < 2) erros.push("nome");
    if (!isContact(m.contato)) erros.push("contato");
    if (m.negocio.length < 2) erros.push("negocio");
    if (!MOMENTOS.includes(m.momento)) erros.push("momento");
    if (m.projeto.length < 10) erros.push("projeto");
    if (erros.length) return send(res, 422, { erro: "Confira os campos.", campos: erros });

    return save(req, res, m);
  } catch (e) {
    return send(res, e.status || 500, { erro: e.status && e.status < 500 ? e.message : "Não foi possível salvar a mensagem agora." });
  }
}

async function save(req, res, m) {
  const ipHash = crypto.createHash("sha256").update(clientIp(req)).digest("hex").slice(0, 16);
  if ((await hit("envio:" + ipHash, 600)) > 5) return send(res, 429, { erro: "Muitos envios em pouco tempo. Tente de novo em alguns minutos." });
  await addMessage({ id: crypto.randomUUID(), criadaEm: new Date().toISOString(), lida: false, ...m });
  return send(res, 201, { ok: true });
}

function blackFriday(req, res, b) {
  const m = {
    nome: clean(b.nome, 120),
    contato: clean(b.whatsapp, 40),
    link: clean(b.instagram, 120),
    marca: clean(b.marca, 120),
    tamanho: clean(b.tamanho, 60),
    investimento: clean(b.investimento, 60),
    negocio: "",
    momento: "",
    projeto: "",
    origem: "black-friday",
  };
  const erros = [];
  if (m.nome.length < 2) erros.push("nome");
  if (m.contato.replace(/\D/g, "").length < 10) erros.push("whatsapp");
  if (!BF.marca.includes(m.marca)) erros.push("marca");
  if (!BF.tamanho.includes(m.tamanho)) erros.push("tamanho");
  if (!BF.investimento.includes(m.investimento)) erros.push("investimento");
  if (erros.length) return send(res, 422, { erro: "Confira os campos.", campos: erros });
  return save(req, res, m);
}
