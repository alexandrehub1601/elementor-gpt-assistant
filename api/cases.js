// GET /api/cases — até 8 cases publicados marcados para a home (público, na ordem do painel).
import { send } from "./_lib/http.js";
import { listCases } from "./_lib/store.js";
import { publicSummary } from "./_lib/cases.js";

export default async function handler(req, res) {
  if (req.method !== "GET") return send(res, 405, { erro: "Use GET." });
  try {
    const all = await listCases();
    const cases = all.filter((c) => c.publicado && c.home && c.capa?.url).slice(0, 8).map(publicSummary);
    return send(res, 200, { cases }, { "Cache-Control": "public, s-maxage=60, stale-while-revalidate=300" });
  } catch (e) {
    return send(res, e.status || 500, { erro: e.message });
  }
}
