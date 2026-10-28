// POST /api/upload?nome=arquivo.webp — painel envia uma imagem (já reduzida no navegador).
// Produção: Vercel Blob (BLOB_READ_WRITE_TOKEN). Desenvolvimento: pasta local (FALE_UPLOAD_DIR).
import crypto from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { send, isAuthed, isPanelRequest } from "./_lib/http.js";
import { slugify } from "./_lib/cases.js";

const TYPES = { "image/webp": "webp", "image/jpeg": "jpg", "image/png": "png", "image/avif": "avif" };
const MAX = 4 * 1024 * 1024; // limite das funções da Vercel é 4,5 MB

async function readRaw(req) {
  // Lê o corpo cru direto do stream (não acessa req.body, para a Vercel não tentar interpretar).
  const chunks = []; let size = 0;
  for await (const c of req) { size += c.length; if (size > MAX) throw Object.assign(new Error("Imagem grande demais (máximo 4 MB)."), { status: 413 }); chunks.push(c); }
  return Buffer.concat(chunks);
}
// Confere a assinatura do arquivo (não confia só no cabeçalho).
function sniff(buf) {
  if (buf.slice(0, 4).toString("ascii") === "RIFF" && buf.slice(8, 12).toString("ascii") === "WEBP") return "image/webp";
  if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return "image/jpeg";
  if (buf.slice(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return "image/png";
  if (buf.slice(4, 12).toString("ascii").startsWith("ftypavi")) return "image/avif";
  return null;
}

export default async function handler(req, res) {
  try {
    if (!isAuthed(req)) return send(res, 401, { erro: "Faça login para continuar." });
    if (req.method !== "POST") return send(res, 405, { erro: "Use POST." });
    if (!isPanelRequest(req)) return send(res, 403, { erro: "Requisição não permitida." });
    const buf = await readRaw(req);
    const type = sniff(buf);
    if (!type) return send(res, 415, { erro: "Formato não aceito. Use JPG, PNG ou WebP." });
    const base = slugify(new URL(req.url, "http://x").searchParams.get("nome")?.replace(/\.[a-z0-9]+$/i, "")) || "imagem";
    const name = `cases/${base}-${crypto.randomBytes(4).toString("hex")}.${TYPES[type]}`;

    if (process.env.BLOB_READ_WRITE_TOKEN) {
      const { put } = await import("@vercel/blob");
      const blob = await put(name, buf, { access: "public", contentType: type, addRandomSuffix: false });
      return send(res, 201, { url: blob.url });
    }
    if (process.env.FALE_UPLOAD_DIR && !process.env.VERCEL) {
      const file = path.join(process.env.FALE_UPLOAD_DIR, name);
      await fs.mkdir(path.dirname(file), { recursive: true });
      await fs.writeFile(file, buf);
      return send(res, 201, { url: "/uploads/" + name });
    }
    return send(res, 500, { erro: "Armazenamento de imagens não configurado: conecte o Vercel Blob ao projeto." });
  } catch (e) {
    return send(res, e.status || 500, { erro: e.message || "Erro ao enviar a imagem." });
  }
}
