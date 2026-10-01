#!/usr/bin/env node
/**
 * Пробный OCR/разбор сканов бумажных писем через OdiRouter + Gemini.
 *
 * Usage:
 *   node local/scripts/ocr-letter-odirouter.mjs
 *   node local/scripts/ocr-letter-odirouter.mjs local/letters/test1.jpg local/letters/test2.jpg
 *   # несколько файлов = страницы одного письма (один запрос). Постранично: --per-page
 *   node local/scripts/ocr-letter-odirouter.mjs --per-page local/letters/test1.jpg
 *   node local/scripts/ocr-letter-odirouter.mjs --model=gemini-3.8-flash --out=tmp/letter-ocr
 *
 * Env (из корня репо .env):
 *   ODIROUTER_API_KEY
 *   ODIROUTER_BASE_URL   default https://api.odirouter.ai/v1
 *   ODIROUTER_MODEL      default gemini-3.8-flash (для этого скрипта)
 */

import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";
import os from "node:os";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../..");

/** Max long side (px) before upload — large scans often trip gateway timeouts. */
const MAX_EDGE = Number(process.env.ODIROUTER_LETTER_MAX_EDGE) || 1800;
const JPEG_QUALITY = Number(process.env.ODIROUTER_LETTER_JPEG_Q) || 82;
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

const PROMPT = `Ты обрабатываешь сканы старых писем (сцена ZX Spectrum, 90-е и 2000-е).
Если приложено несколько изображений — это страницы ОДНОГО письма (в порядке page1, page2, …). Читай их подряд как единый текст, не обрабатывай каждую страницу отдельно и не пиши «письмо обрывается», пока не кончатся все страницы.

По каждому письму выдай результат в таком порядке:

1. РАСПОЗНАННЫЙ ТЕКСТ
- Перепиши текст письма дословно, сохраняя ники и названия программ.
- Склей переносы слов через дефис в конце строки: «пос-» + «мотрел» → «посмотрел», «ва-» + «ляться» → «валяться». Дефис переноса убирай; настоящие дефисы в словах (Rulez-z-z, BK-шки) оставляй.
- Не копируй построчную вёрстку скана. Разбей на нормальные абзацы по смыслу (приветствие, основной текст, просьбы, подпись/дата — отдельные абзацы). Внутри абзаца — одна непрерывная строка/несколько предложений, без искусственных обрывов посередине слова или фразы.
- Сомнительные места при плохом почерке помечай (?), ничего не выдумывай.

2. ПЕРЕВОД НА АНГЛИЙСКИЙ
- Полный перевод письма. Ники, названия программ и групп не переводи, города и имена транслитерируй.
- Те же абзацы, что и в распознанном тексте.

3. САММЕРИ (на русском и на английском)
Правила:
- Начинай с самостоятельного предложения с подлежащим: кто отправитель (по подписи/нику) и что он сделал. Не пиши «автор письма»: кто ещё это мог быть.
- Заканчивай законченной мыслью, а не обрубком.
- Пиши в прошедшем времени, если письмо датировано прошлым (события уже произошли).
- Только суть: что отправлено, что предложено, о чём спрашивают, что просят.
- Не включай то, что очевидно из самого письма или есть в любом письме: откуда автор, какая сцена, приветствия, прощания, «желает удачи», «передаёт привет всем».
- Не додумывай: не приписывай автору или адресату ролей, которых нет в тексте.
- Коротко, без воды, без пересказа всего подряд.

4. META DESCRIPTION (на русском и на английском)
- До ~155 символов, одно предложение, с ключевыми словами: ZX Spectrum, город, год, что именно в письме.

5. TITLE (на русском и на английском)
- Точно называй предмет письма, а не общую тему. Например: не «письмо о музыке», а «бандероль с дисками с музыкой челябинских музыкантов».
- Английский вариант до ~60 символов, по возможности с ключевыми словами.
- Дай 2 варианта и отметь лучший.

Общие правила:
- Отвечай сразу по делу, без оправданий и лишних извинений.
- Если ник в подписи или другое место читается неуверенно, укажи это одной строкой в конце.
- Если просят написать имя маленькими буквами или транслитерацию, отвечай коротко, без лишних вариантов.`;

const MIME = {
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".png": "image/png",
  ".webp": "image/webp",
  ".gif": "image/gif",
};

function parseArgs(argv) {
  const files = [];
  let model = process.env.ODIROUTER_MODEL_LETTER || "gemini-3.8-flash";
  // Several images = pages of one letter by default.
  let together = true;
  let outDir = path.join(ROOT, "tmp", "letter-ocr");
  let maxTokens = Number(process.env.ODIROUTER_MAX_TOKENS) || 16_384;
  for (const a of argv) {
    if (a === "--together") together = true;
    else if (a === "--per-page" || a === "--separate") together = false;
    else if (a.startsWith("--model=")) model = a.slice(8);
    else if (a.startsWith("--out=")) outDir = path.resolve(ROOT, a.slice(6));
    else if (a.startsWith("--max-tokens=")) maxTokens = Number(a.slice(13)) || maxTokens;
    else if (a.startsWith("-")) {
      console.error(`Unknown flag: ${a}`);
      process.exit(2);
    } else files.push(path.resolve(a));
  }
  if (!files.length) {
    files.push(
      path.join(ROOT, "local/letters/test1.jpg"),
      path.join(ROOT, "local/letters/test2.jpg"),
    );
  }
  if (files.length < 2) together = false;
  return { files, model, together, outDir, maxTokens };
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

/**
 * Shrink scan via macOS `sips` (no sharp/deps). Falls back to original on failure.
 * @returns {Promise<{buf: Buffer, mediaType: string, note: string}>}
 */
async function prepareImage(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  const mediaType = MIME[ext];
  if (!mediaType) {
    throw new Error(`Unsupported image type: ${filePath}`);
  }
  const original = await fs.readFile(filePath);
  const tmp = path.join(
    os.tmpdir(),
    `zxpress-letter-${process.pid}-${Date.now()}${ext === ".png" ? ".png" : ".jpg"}`,
  );
  try {
    await fs.copyFile(filePath, tmp);
    // Fit inside MAX_EDGE×MAX_EDGE, keep aspect.
    await run("sips", [
      "-Z",
      String(MAX_EDGE),
      "-s",
      "format",
      "jpeg",
      "-s",
      "formatOptions",
      String(JPEG_QUALITY),
      tmp,
    ]);
    const buf = await fs.readFile(tmp);
    const note = `${(original.length / 1024 / 1024).toFixed(2)}MB → ${(buf.length / 1024 / 1024).toFixed(2)}MB (≤${MAX_EDGE}px)`;
    return { buf, mediaType: "image/jpeg", note };
  } catch (err) {
    console.error(
      `[ocr] WARN resize failed (${err.message}); sending original`,
    );
    return { buf: original, mediaType, note: "original" };
  } finally {
    await fs.unlink(tmp).catch(() => {});
  }
}

async function fileToImageBlock(filePath) {
  const { buf, mediaType, note } = await prepareImage(filePath);
  console.error(`[ocr] ${path.basename(filePath)} ${note}`);
  return {
    type: "image",
    source: {
      type: "base64",
      media_type: mediaType,
      data: buf.toString("base64"),
    },
  };
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
  const body = {
    model,
    max_tokens: maxTokens,
    temperature: 0.2,
    messages: [{ role: "user", content }],
  };
  const res = await fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
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
    throw new Error(
      `Empty model response: ${JSON.stringify(data).slice(0, 800)}`,
    );
  }
  return { text, data, usage: data.usage || null };
}

async function callMessages(opts) {
  let lastErr;
  for (let attempt = 1; attempt <= API_RETRIES; attempt++) {
    try {
      return await callMessagesOnce(opts);
    } catch (err) {
      lastErr = err;
      const status = err.status || 0;
      const retryable = status === 429 || status === 502 || status === 503 || status === 504;
      if (!retryable || attempt === API_RETRIES) break;
      const wait = Math.min(30_000, 2000 * attempt);
      console.error(
        `[ocr] retry ${attempt}/${API_RETRIES} after ${status || "err"} in ${wait}ms…`,
      );
      await new Promise((r) => setTimeout(r, wait));
    }
  }
  throw lastErr;
}

async function processJob({ label, images, apiKey, baseUrl, model, maxTokens }) {
  const content = [
    { type: "text", text: PROMPT },
    ...images,
  ];
  console.error(`[ocr] ${label} → ${model} (${images.length} image(s))…`);
  const t0 = Date.now();
  const result = await callMessages({
    apiKey,
    baseUrl,
    model,
    maxTokens,
    content,
  });
  console.error(
    `[ocr] ${label} done in ${((Date.now() - t0) / 1000).toFixed(1)}s` +
      (result.usage
        ? ` (in=${result.usage.input_tokens ?? "?"} out=${result.usage.output_tokens ?? "?"})`
        : ""),
  );
  return result;
}

async function main() {
  const { files, model, together, outDir, maxTokens } = parseArgs(
    process.argv.slice(2),
  );
  const apiKey = process.env.ODIROUTER_API_KEY || "";
  const baseUrl =
    process.env.ODIROUTER_BASE_URL || "https://api.odirouter.ai/v1";
  if (!apiKey) {
    console.error("Missing ODIROUTER_API_KEY in .env");
    process.exit(1);
  }

  for (const f of files) {
    await fs.access(f);
  }
  await fs.mkdir(outDir, { recursive: true });

  const jobs = together
    ? [
        {
          label: files.map((f) => path.basename(f)).join("+"),
          outName: "together.md",
          images: await Promise.all(files.map(fileToImageBlock)),
        },
      ]
    : await Promise.all(
        files.map(async (f) => ({
          label: path.basename(f),
          outName: path.basename(f).replace(/\.[^.]+$/, "") + ".md",
          images: [await fileToImageBlock(f)],
        })),
      );

  const results = [];
  for (const job of jobs) {
    const { text, usage } = await processJob({
      label: job.label,
      images: job.images,
      apiKey,
      baseUrl,
      model,
      maxTokens,
    });
    const outPath = path.join(outDir, job.outName);
    const header =
      `# ${job.label}\n\n` +
      `_model: ${model}_\n\n` +
      (usage
        ? `_tokens: in=${usage.input_tokens ?? "?"} out=${usage.output_tokens ?? "?"}_\n\n`
        : "") +
      `---\n\n`;
    await fs.writeFile(outPath, header + text + "\n", "utf8");
    console.error(`[ocr] wrote ${outPath}`);
    results.push({ label: job.label, outPath, text });
    console.log("\n" + "=".repeat(72));
    console.log(job.label);
    console.log("=".repeat(72) + "\n");
    console.log(text);
  }

  console.error(`\n[ocr] done → ${outDir}`);
}

main().catch((err) => {
  console.error("[ocr] FAILED:", err?.message || err);
  process.exit(1);
});
