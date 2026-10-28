// GET /sitemap.xml (reescrito para /api/sitemap) — inclui os cases publicados automaticamente.
import { listCases } from "./_lib/store.js";
const SITE = (process.env.SITE_URL || "https://www.estudiofale.com.br").replace(/\/$/, "");
const x = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&apos;" }[c]));
const abs = (u) => (u.startsWith("/") ? SITE + u : u);

export default async function handler(req, res) {
  let cases = [];
  try { cases = (await listCases()).filter((c) => c.publicado); } catch { cases = []; }
  const day = (d) => (d || new Date().toISOString()).slice(0, 10);
  const urls = [
    `<url><loc>${SITE}/</loc><lastmod>${day()}</lastmod></url>`,
    `<url><loc>${SITE}/contato</loc></url>`,
    ...cases.map((c) => `<url><loc>${x(`${SITE}/projetos/${c.slug}`)}</loc><lastmod>${day(c.atualizadoEm)}</lastmod>${c.capa?.url ? `<image:image><image:loc>${x(abs(c.capa.url))}</image:loc></image:image>` : ""}</url>`),
  ];
  res.statusCode = 200;
  res.setHeader("Content-Type", "application/xml; charset=utf-8");
  res.setHeader("Cache-Control", "public, s-maxage=3600");
  res.end(`<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n${urls.join("\n")}\n</urlset>\n`);
}
