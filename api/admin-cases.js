// /api/admin-cases — painel (exige login):
//   GET              lista todos os cases (inclusive rascunhos)
//   POST             cria um case
//   PUT ?slug=       atualiza o case (pode trocar o endereço)
//   DELETE ?slug=    exclui
//   PUT ?ordem=1     salva a ordem (corpo: { ordem: [slugs] })
import { send, readJson, isAuthed, isPanelRequest } from "./_lib/http.js";
import { listCases, getCase, saveCase, deleteCase, setCaseOrder } from "./_lib/store.js";
import { normalizeCase, SLUG_RE } from "./_lib/cases.js";

export default async function handler(req, res) {
  try {
    if (!isAuthed(req)) return send(res, 401, { erro: "Faça login para continuar." });
    if (req.method === "GET") return send(res, 200, { cases: await listCases() });
    if (!isPanelRequest(req)) return send(res, 403, { erro: "Requisição não permitida." });
    const q = new URL(req.url, "http://x").searchParams;

    if (req.method === "PUT" && q.get("ordem")) {
      const { ordem } = await readJson(req, 16 * 1024);
      if (!Array.isArray(ordem)) return send(res, 400, { erro: "Ordem inválida." });
      return send(res, 200, { ordem: await setCaseOrder(ordem.map(String)) });
    }
    if (req.method === "POST") {
      const c = normalizeCase(await readJson(req, 256 * 1024));
      const now = new Date().toISOString();
      return send(res, 201, { case: await saveCase({ ...c, criadoEm: now, atualizadoEm: now }) });
    }
    const slug = q.get("slug") || "";
    if (!SLUG_RE.test(slug)) return send(res, 400, { erro: "Case inválido." });
    if (req.method === "PUT") {
      const old = await getCase(slug);
      if (!old) return send(res, 404, { erro: "Case não encontrado." });
      const c = normalizeCase(await readJson(req, 256 * 1024));
      return send(res, 200, { case: await saveCase({ ...c, criadoEm: old.criadoEm, atualizadoEm: new Date().toISOString() }, slug) });
    }
    if (req.method === "DELETE") { await deleteCase(slug); return send(res, 200, { ok: true }); }
    return send(res, 405, { erro: "Método não permitido." });
  } catch (e) {
    return send(res, e.status || 500, { erro: e.message || "Erro ao salvar o case." });
  }
}
