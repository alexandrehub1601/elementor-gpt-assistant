// /api/mensagens — painel: GET lista, PATCH ?id= marca como lida/não lida, DELETE ?id= exclui. Exige login.
import { send, readJson, isAuthed, isPanelRequest } from "./_lib/http.js";
import { listMessages, updateMessage, deleteMessage } from "./_lib/store.js";

export default async function handler(req, res) {
  try {
    if (!isAuthed(req)) return send(res, 401, { erro: "Faça login para continuar." });
    if (req.method === "GET") return send(res, 200, { mensagens: await listMessages() });
    if (!isPanelRequest(req)) return send(res, 403, { erro: "Requisição não permitida." });
    const id = new URL(req.url, "http://x").searchParams.get("id");
    if (!id || !/^[0-9a-f-]{36}$/.test(id)) return send(res, 400, { erro: "Mensagem inválida." });
    if (req.method === "PATCH") {
      const { lida } = await readJson(req, 1024);
      const m = await updateMessage(id, { lida: !!lida });
      return m ? send(res, 200, { mensagem: m }) : send(res, 404, { erro: "Mensagem não encontrada." });
    }
    if (req.method === "DELETE") { await deleteMessage(id); return send(res, 200, { ok: true }); }
    return send(res, 405, { erro: "Método não permitido." });
  } catch (e) {
    return send(res, e.status || 500, { erro: e.message || "Erro no painel." });
  }
}
