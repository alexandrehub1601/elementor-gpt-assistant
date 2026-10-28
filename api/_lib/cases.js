// Validação e limpeza dos dados de um case.
export const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export function slugify(s) {
  return String(s || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase()
    .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80);
}
const txt = (v, max) => String(v ?? "").replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, "").trim().slice(0, max);

// Aceita imagens do próprio site (/assets/...), do Vercel Blob (https) e, em desenvolvimento, /uploads/...
export function safeImageUrl(u) {
  const s = String(u || "").trim();
  if (/^\/(assets|uploads)\/[\w\-./%]+$/.test(s) && !s.includes("..")) return s;
  try { const x = new URL(s); return x.protocol === "https:" ? x.href : ""; } catch { return ""; }
}

export function normalizeCase(b) {
  const titulo = txt(b.titulo, 120);
  const slug = slugify(b.slug || titulo);
  const erros = [];
  if (titulo.length < 2) erros.push("Informe o título do case.");
  if (!SLUG_RE.test(slug) || slug.length < 2) erros.push("Endereço inválido: use letras, números e hífens.");
  const capaUrl = safeImageUrl(b.capa?.url);
  const imagens = (Array.isArray(b.imagens) ? b.imagens : []).slice(0, 40).map((i) => ({
    url: safeImageUrl(i?.url),
    alt: txt(i?.alt, 300),
    largura: i?.largura === "metade" ? "metade" : "inteira",
  })).filter((i) => i.url);
  const publicado = !!b.publicado;
  if (publicado && !capaUrl) erros.push("Para publicar, adicione uma imagem de capa.");
  if (erros.length) throw Object.assign(new Error(erros.join(" ")), { status: 422 });
  return {
    slug, titulo,
    descricao: txt(b.descricao, 6000),
    capa: { url: capaUrl, alt: txt(b.capa?.alt, 300) },
    imagens, publicado, home: !!b.home,
  };
}

// Versão pública (sem campos internos), para a home.
export function publicSummary(c) {
  return { slug: c.slug, titulo: c.titulo, capa: c.capa };
}
