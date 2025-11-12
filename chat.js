// api/chat.js
// Serverless function para Vercel (Node.js).
// Faz uma chamada à OpenAI e retorna texto (sem streaming, mais simples).

import OpenAI from "openai";

const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY,
});

// Ajuste o modelo aqui, se quiser
const MODEL = "gpt-4.1-mini";

// Configure seu domínio (ou * para testes). Ideal: coloque o domínio do seu site.
const ALLOWED_ORIGINS = [
  "*", // troque por "https://seu-dominio.com" quando for colocar em produção
];

export default async function handler(req, res) {
  // CORS básico
  const origin = req.headers.origin || "";
  if (ALLOWED_ORIGINS.includes("*") || ALLOWED_ORIGINS.includes(origin)) {
    res.setHeader("Access-Control-Allow-Origin", origin || "*");
  }
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");
  if (req.method === "OPTIONS") {
    return res.status(200).end();
  }

  if (req.method !== "POST") {
    return res.status(405).json({ error: "Use POST" });
  }

  try {
    const { message, history } = req.body || {};
    if (!message || typeof message !== "string") {
      return res.status(400).json({ error: "Campo 'message' é obrigatório (string)" });
    }

    const systemPrompt = process.env.SYSTEM_PROMPT || 
      "Você é um assistente útil, educado e objetivo. Responda em português do Brasil.";

    // Monta o histórico no formato aceito pela Responses API
    const input = [
      { role: "system", content: systemPrompt },
    ];

    if (Array.isArray(history)) {
      // history esperado: [{role:"user"|"assistant", content:"..."}]
      for (const m of history.slice(-10)) {
        if (m && (m.role === "user" || m.role === "assistant") && typeof m.content === "string") {
          input.push({ role: m.role, content: m.content });
        }
      }
    }

    input.push({ role: "user", content: message });

    // Chamada simples sem streaming
    const resp = await client.responses.create({
      model: MODEL,
      input,
    });

    // Extrai texto da resposta
    const text = resp?.output?.[0]?.content?.[0]?.text
      || resp?.output_text
      || "Desculpe, não consegui gerar uma resposta agora.";

    return res.status(200).json({ reply: text });
  } catch (err) {
    console.error("Erro na API:", err);
    return res.status(500).json({ error: "Falha ao consultar o modelo." });
  }
}
