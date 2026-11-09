#!/usr/bin/env node
/**
 * OCR Electra (Latvian ZX magazine) screen PNGs via OdiRouter.
 *
 * Usage:
 *   node local/scripts/ocr-electra-odirouter.mjs
 *   node local/scripts/ocr-electra-odirouter.mjs --limit=5
 *   node local/scripts/ocr-electra-odirouter.mjs path/to/a.png path/to/b.png
 */

import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";
import os from "node:os";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../..");
const DEFAULT_LIST = path.join(
  ROOT,
  "local/work/ELECTRA_dump/article_pngs.txt",
);
const DEFAULT_OUT = path.join(ROOT, "local/work/ELECTRA_texts");

const MAX_EDGE = Number(process.env.ODIROUTER_LETTER_MAX_EDGE) || 1024;
const JPEG_QUALITY = Number(process.env.ODIROUTER_LETTER_JPEG_Q) || 90;
const API_RETRIES = Number(process.env.ODIROUTER_LETTER_RETRIES) || 3;

async function loadEnv(file) {
  try {
    const raw = await fs.readFile(file, "utf8");
    for (const line of raw.split(/\r?\n/)) {
      const t = line.trim();
      if (!t || t.startsWith("#")) continue;
      const i = t.indexOf("=");
      if (i <= 0) continue;
      const key = t.slice(0, i).trim();
      let val = t.slice(i + 1).trim();
      if (
        (val.startsWith('"') && val.endsWith('"')) ||
        (val.startsWith("'") && val.endsWith("'"))
      ) {
        val = val.slice(1, -1);
      }
      if (process.env[key] === undefined) process.env[key] = val;
    }
  } catch {
    /* optional */
  }
}

await loadEnv(path.join(ROOT, ".env"));

const PROMPT = `ZX Spectrum diskmag Electra (Latvian, Ogre, 1995). One 256×192 screen: white pixel text on magenta, yellow frame, icons at bottom.

Transcribe the page text verbatim.

Rules:
- Keep Latvian diacritics: ā č ē ģ ī ķ ļ ņ š ū ž (and capitals).
- Keep game/company names (DIZZY, CODEMASTERS, ELECTRA STAR, TASM, GENS, etc.).
- Join hyphenated line-breaks (sa- / vu → savu).
- Ignore yellow border and bottom navigation icons; do not describe colours or layout.
- Title first, then body paragraphs.
- INFO pages (Nodaļa / Raksts / Autori / Datums / address): keep as labeled fields.
- Pure icon menus with no readable article body: output exactly [MENU] under LV.
- Output exactly this format (no preamble):
LV:
<latvian text>
EN:
<english translation>
RU:
<russian translation>`;

function parseArgs(argv) {
  const files = [];
  let model =
    process.env.ODIROUTER_MODEL ||
    process.env.ODIROUTER_MODEL_LETTER ||
    "gemini-3.5-flash";
  let outDir = DEFAULT_OUT;
  let limit = 0;
  let concurrency = 3;
  let maxTokens = Number(process.env.ODIROUTER_MAX_TOKENS) || 8192;
  for (const a of argv) {
    if (a.startsWith("--model=")) model = a.slice(8);
    else if (a.startsWith("--out=")) outDir = path.resolve(ROOT, a.slice(6));
    else if (a.startsWith("--limit=")) limit = Number(a.slice(8)) || 0;
    else if (a.startsWith("--concurrency="))
      concurrency = Math.max(1, Number(a.slice(14)) || 1);
    else if (a.startsWith("--max-tokens="))
      maxTokens = Number(a.slice(13)) || maxTokens;
    else if (a.startsWith("-")) {
      console.error(`Unknown flag: ${a}`);
      process.exit(2);
    } else files.push(path.resolve(a));
  }
  return { files, model, outDir, limit, concurrency, maxTokens };
}

function run(cmd, args) {
  return new Promise((resolve, reject) => {
    const p = spawn(cmd, args, { stdio: ["ignore", "pipe", "pipe"] });
    let stderr = "";
    p.stderr.on("data", (d) => {
      stderr += d.toString();
    });
    p.on("error", reject);
    p.on("close", (code) => {
      if (code === 0) resolve();
      else reject(new Error(`${cmd} exit ${code}: ${stderr.slice(0, 400)}`));
    });
  });
}

async function prepareImage(filePath) {
  // Electra SCR PNGs are tiny (~6KB); send PNG as-is for crisp glyphs.
  const original = await fs.readFile(filePath);
  return { buf: original, mediaType: "image/png" };
}

function extractText(data) {
  if (!data || typeof data !== "object") return "";
  if (typeof data.output_text === "string" && data.output_text.trim()) {
    return data.output_text.trim();
  }
  const parts = [];
  if (Array.isArray(data.content)) {
    for (const block of data.content) {
      if (block?.type === "text" && typeof block.text === "string") {
        parts.push(block.text);
      }
    }
  }
  if (Array.isArray(data.output)) {
    for (const item of data.output) {
      if (!item || typeof item !== "object") continue;
      if (Array.isArray(item.content)) {
        for (const c of item.content) {
          if (
            (c?.type === "output_text" || c?.type === "text") &&
            typeof c.text === "string"
          ) {
            parts.push(c.text);
          }
        }
      }
    }
  }
  return parts.join("\n").trim();
}

async function callMessagesOnce({ apiKey, baseUrl, model, maxTokens, content }) {
  const url = `${baseUrl.replace(/\/$/, "")}/messages`;
  const res = await fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model,
      max_tokens: maxTokens,
      temperature: 0.1,
      messages: [{ role: "user", content }],
    }),
  });
  const raw = await res.text();
  let data;
  try {
    data = JSON.parse(raw);
  } catch {
    data = { raw };
  }
  if (!res.ok) {
    const msg =
      data?.error?.message ||
      data?.message ||
      (typeof data.raw === "string" ? data.raw.slice(0, 400) : raw.slice(0, 400)) ||
      res.statusText;
    const err = new Error(`HTTP ${res.status}: ${msg}`);
    err.status = res.status;
    throw err;
  }
  const text = extractText(data);
  if (!text) {
    throw new Error(`Empty model response: ${JSON.stringify(data).slice(0, 800)}`);
  }
  return { text, usage: data.usage || null };
}

async function callMessages(opts) {
  let lastErr;
  for (let attempt = 1; attempt <= API_RETRIES; attempt++) {
    try {
      return await callMessagesOnce(opts);
    } catch (err) {
      lastErr = err;
      const status = err.status || 0;
      const retryable =
        status === 429 || status === 502 || status === 503 || status === 504;
      if (!retryable || attempt === API_RETRIES) break;
      const wait = Math.min(30_000, 2000 * attempt);
      console.error(`[electra-ocr] retry ${attempt} after ${status} in ${wait}ms`);
      await new Promise((r) => setTimeout(r, wait));
    }
  }
  throw lastErr;
}

function parseOcr(text) {
  const raw = text.trim();
  const lvM = raw.match(/^LV:\s*([\s\S]*?)(?=\nEN:|\nRU:|$)/i);
  const enM = raw.match(/\nEN:\s*([\s\S]*?)(?=\nRU:|$)/i);
  const ruM = raw.match(/\nRU:\s*([\s\S]*?)$/i);
  let lv = lvM ? lvM[1].trim() : "";
  let en = enM ? enM[1].trim() : "";
  let ru = ruM ? ruM[1].trim() : "";
  if (!lv && !en && !ru) {
    // fallback: whole text as LV if model skipped labels
    lv = raw;
  }
  return { lv, en, ru, raw };
}

async function ocrOne(filePath, ctx) {
  const stem = path.basename(filePath, path.extname(filePath));
  const outJson = path.join(ctx.outDir, "pages", `${stem}.json`);
  const outTxt = path.join(ctx.outDir, "pages", `${stem}.txt`);
  try {
    await fs.access(outJson);
    console.error(`[electra-ocr] skip existing ${stem}`);
    return { stem, skipped: true };
  } catch {
    /* continue */
  }
  const { buf, mediaType } = await prepareImage(filePath);
  const content = [
    { type: "text", text: PROMPT },
    {
      type: "image",
      source: {
        type: "base64",
        media_type: mediaType,
        data: buf.toString("base64"),
      },
    },
  ];
  const t0 = Date.now();
  console.error(`[electra-ocr] ${stem} → ${ctx.model}`);
  const result = await callMessages({
    apiKey: ctx.apiKey,
    baseUrl: ctx.baseUrl,
    model: ctx.model,
    maxTokens: ctx.maxTokens,
    content,
  });
  const parsed = parseOcr(result.text);
  const rec = {
    id: stem,
    source_png: path.relative(ROOT, filePath),
    model: ctx.model,
    ms: Date.now() - t0,
    usage: result.usage,
    body_lv: parsed.lv,
    body_en: parsed.en,
    body_ru: parsed.ru,
    raw: parsed.raw,
  };
  await fs.mkdir(path.dirname(outJson), { recursive: true });
  await fs.writeFile(outJson, JSON.stringify(rec, null, 2), "utf8");
  await fs.writeFile(
    outTxt,
    `${parsed.lv}\n\n---\nEN:\n${parsed.en}\n\nRU:\n${parsed.ru}\n`,
    "utf8",
  );
  console.error(
    `[electra-ocr] ${stem} ok ${(rec.ms / 1000).toFixed(1)}s lv=${parsed.lv.length}c`,
  );
  return { stem, skipped: false, chars: parsed.lv.length };
}

async function poolMap(items, concurrency, fn) {
  const ret = new Array(items.length);
  let i = 0;
  async function worker() {
    while (i < items.length) {
      const idx = i++;
      ret[idx] = await fn(items[idx], idx);
    }
  }
  await Promise.all(
    Array.from({ length: Math.min(concurrency, items.length) }, () => worker()),
  );
  return ret;
}

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  const apiKey = process.env.ODIROUTER_API_KEY;
  if (!apiKey) {
    console.error("ODIROUTER_API_KEY missing");
    process.exit(1);
  }
  const baseUrl = process.env.ODIROUTER_BASE_URL || "https://api.odirouter.ai/v1";
  let files = opts.files;
  if (!files.length) {
    const list = await fs.readFile(DEFAULT_LIST, "utf8");
    files = list
      .split(/\r?\n/)
      .map((s) => s.trim())
      .filter(Boolean);
  }
  if (opts.limit > 0) files = files.slice(0, opts.limit);
  await fs.mkdir(opts.outDir, { recursive: true });
  const ctx = {
    apiKey,
    baseUrl,
    model: opts.model,
    maxTokens: opts.maxTokens,
    outDir: opts.outDir,
  };
  console.error(
    `[electra-ocr] ${files.length} pages, model=${opts.model}, concurrency=${opts.concurrency}`,
  );
  const results = await poolMap(files, opts.concurrency, (f) => ocrOne(f, ctx));
  const done = results.filter((r) => r && !r.skipped).length;
  const skipped = results.filter((r) => r && r.skipped).length;
  const index = {
    generated_at: new Date().toISOString(),
    model: opts.model,
    total: files.length,
    done,
    skipped,
    pages: results.map((r) => r?.stem).filter(Boolean),
  };
  await fs.writeFile(
    path.join(opts.outDir, "index.json"),
    JSON.stringify(index, null, 2),
  );
  console.error(`[electra-ocr] finished done=${done} skipped=${skipped}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
