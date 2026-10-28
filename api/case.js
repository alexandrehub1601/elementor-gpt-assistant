// GET /projetos/:slug (reescrito para /api/case?slug=) — página do case montada a partir do painel.
import { isAuthed } from "./_lib/http.js";
import { listCases } from "./_lib/store.js";
import { SLUG_RE } from "./_lib/cases.js";
import { renderCase, renderNotFound } from "./_lib/case-template.js";

function html(res, status, body, cache) {
  res.statusCode = status;
  res.setHeader("Content-Type", "text/html; charset=utf-8");
  res.setHeader("Cache-Control", cache);
  res.end(body);
}

export default async function handler(req, res) {
  try {
    const slug = new URL(req.url, "http://x").searchParams.get("slug") || "";
    if (!SLUG_RE.test(slug)) return html(res, 404, renderNotFound(), "public, s-maxage=60");
    const all = await listCases();
    const c = all.find((x) => x.slug === slug);
    const draft = c && !c.publicado;
    let authed = false;
    try { authed = isAuthed(req); } catch { authed = false; }
    if (!c || (draft && !authed)) return html(res, 404, renderNotFound(), "public, s-maxage=60");
    // "Veja mais": os próximos projetos publicados, na ordem do painel.
    const pub = all.filter((x) => x.publicado && x.capa?.url && x.slug !== slug);
    const idx = all.findIndex((x) => x.slug === slug);
    const after = pub.filter((x) => all.indexOf(x) > idx), before = pub.filter((x) => all.indexOf(x) < idx);
    const others = [...after, ...before].slice(0, 4);
    return html(res, 200, renderCase(c, others, { draft }), draft ? "private, no-store" : "public, s-maxage=60, stale-while-revalidate=300");
  } catch (e) {
    return html(res, 500, "<!doctype html><meta charset=utf-8><title>Erro</title><p>Não foi possível carregar este projeto agora.</p>", "no-store");
  }
}
