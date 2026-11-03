// Credenciais do Vercel Blob: token antigo (BLOB_READ_WRITE_TOKEN) ou conexão nova (BLOB_STORE_ID + OIDC),
// com ou sem prefixo no nome da variável.
function envValue(name) {
  if (process.env[name]) return process.env[name];
  const hit = Object.entries(process.env).find(([k, v]) => v && k.endsWith("_" + name));
  return hit ? hit[1] : "";
}
export function blobCredentials(req) {
  const token = envValue("BLOB_READ_WRITE_TOKEN") ||
    Object.values(process.env).find((v) => typeof v === "string" && v.startsWith("vercel_blob_rw_"));
  if (token) return { token };
  const storeId = envValue("BLOB_STORE_ID");
  const oidcToken = req?.headers?.["x-vercel-oidc-token"] || process.env.VERCEL_OIDC_TOKEN;
  if (storeId) return oidcToken ? { storeId, oidcToken } : { storeId };
  return null;
}
