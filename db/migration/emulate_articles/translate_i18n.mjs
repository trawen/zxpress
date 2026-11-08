#!/usr/bin/env node
/**
 * EN→RU for Emulate articles (site/cli/imports/emulate_i18n/*.json).
 *
 *   node db/migration/emulate_articles/translate_i18n.mjs
 *   node db/migration/emulate_articles/translate_i18n.mjs --resume --workers=2
 *   node db/migration/emulate_articles/translate_i18n.mjs --only=01_03
 */
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const I18N_DIRS = [
  path.join(ROOT, "site/cli/imports/emulate_i18n"),
  path.join(__dirname, "i18n"),
];
const PROGRESS = path.join(__dirname, "generated/translate_progress.json");

const ENV_CANDIDATES = [
  path.join(ROOT, ".env"),
  path.join(ROOT, "local/.env"),
];

async function loadEnvFile(file) {
  let raw;
  try {
    raw = await fs.readFile(file, "utf8");
  } catch {
    return false;
  }
  for (const line of raw.split(/\r?\n/)) {
    const s = line.trim();
    if (!s || s.startsWith("#")) continue;
    const i = s.indexOf("=");
    if (i < 1) continue;
    const k = s.slice(0, i).trim();
    let v = s.slice(i + 1).trim();
    if (
      (v.startsWith('"') && v.endsWith('"')) ||
      (v.startsWith("'") && v.endsWith("'"))
    ) {
      v = v.slice(1, -1);
    }
    if (!(k in process.env) || process.env[k] === "") process.env[k] = v;
  }
  return true;
}

for (const p of ENV_CANDIDATES) {
  if (await loadEnvFile(p)) break;
}

function parseArgs(argv) {
  const out = {
    resume: false,
    dryRun: false,
    workers: 1,
    delay: 400,
    only: null,
    limit: 0,
  };
  for (const a of argv) {
    if (a === "--resume") out.resume = true;
    else if (a === "--dry-run") out.dryRun = true;
    else if (a.startsWith("--workers="))
      out.workers = Math.max(1, Number(a.slice(10)) || 1);
    else if (a.startsWith("--delay="))
      out.delay = Math.max(0, Number(a.slice(8)) || 0);
    else if (a.startsWith("--only=")) out.only = a.slice(7);
    else if (a.startsWith("--limit="))
      out.limit = Math.max(0, Number(a.slice(8)) || 0);
  }
  return out;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function resolveI18nDir() {
  for (const d of I18N_DIRS) {
    try {
      const st = await fs.stat(d);
      if (st.isDirectory()) return d;
    } catch {
      /* */
    }
  }
  throw new Error("emulate i18n dir not found");
}

function chunkText(text, maxLen) {
  if (text.length <= maxLen) return [text];
  const chunks = [];
  let rest = text;
  while (rest.length > maxLen) {
    let cut = rest.lastIndexOf("\n\n", maxLen);
    if (cut < maxLen * 0.5) cut = rest.lastIndexOf("\n", maxLen);
    if (cut < maxLen * 0.3) cut = maxLen;
    chunks.push(rest.slice(0, cut));
    rest = rest.slice(cut);
  }
  if (rest) chunks.push(rest);
  return chunks;
}

async function odiMessages({
  model,
  apiKey,
  base,
  system,
  user,
  maxTokens,
  timeoutMs = 600_000,
}) {
  const body = JSON.stringify({
    model,
    max_tokens: maxTokens,
    temperature: 0.15,
    system,
    messages: [{ role: "user", content: user }],
  });

  for (let attempt = 0; attempt < 8; attempt++) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    let res;
    try {
      res = await fetch(`${base}/messages`, {
        method: "POST",
        signal: controller.signal,
        headers: {
          "content-type": "application/json",
          Authorization: `Bearer ${apiKey}`,
          "x-api-key": apiKey,
          "anthropic-version": "2023-06-01",
        },
        body,
      });
    } catch (err) {
      clearTimeout(timer);
      const wait = Math.min(60_000, 2_000 * 2 ** attempt);
      console.warn(`fetch error attempt ${attempt + 1}: ${err.message}; wait ${wait}ms`);
      await sleep(wait);
      continue;
    } finally {
      clearTimeout(timer);
    }
    const raw = await res.text();
    if (res.status === 429 || res.status === 503 || res.status === 502 || res.status === 504) {
      const wait = Math.min(90_000, 2_500 * 2 ** attempt);
      console.warn(`HTTP ${res.status} attempt ${attempt + 1}; wait ${wait}ms`);
      await sleep(wait);
      continue;
    }
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${raw.slice(0, 500)}`);
    }
    const data = JSON.parse(raw);
    return Array.isArray(data.content)
      ? data.content.map((c) => c.text || "").join("")
      : String(data.content || raw);
  }
  throw new Error("ODIROUTER: retries exhausted");
}

const SYSTEM_META = `Ты профессиональный переводчик с английского на русский для архива ZX Spectrum (журнал Emulate, 1995–1996).
Переведи заголовок и meta description. Сохраняй имена игр, ники, URL, e-mail, POKE-адреса и английские названия журналов без перевода.
Верни ТОЛЬКО JSON: {"title_ru":"...","meta_description_ru":"..."}`;

const SYSTEM_BODY = `Ты профессиональный переводчик с английского на русский для архива ZX Spectrum (журнал Emulate).
Сохраняй моноширинную вёрстку, ASCII-рамки, отступы и переносы строк. Не сокращай списки POKE/cheat.
Имена игр, компаний, журналов (Crash, Your Spectrum…), URL, e-mail оставляй как в оригинале.
Верни ТОЛЬКО перевод фрагмента тела статьи, без пояснений и markdown-обёртки.`;

async function translateArticle(data, cfg) {
  const longThreshold = Number(process.env.ODIROUTER_LONG_CHARS || 25_000);
  const useLong = (data.body_en || "").length > longThreshold;
  const model = useLong
    ? process.env.ODIROUTER_MODEL_LONG ||
      process.env.ODIROUTER_MODEL ||
      "gemini-3.7-flash"
    : process.env.ODIROUTER_MODEL_FAST ||
      process.env.ODIROUTER_MODEL ||
      "gemini-3.1-flash-lite";
  const apiKey = useLong
    ? process.env.ODIROUTER_API_KEY_LONG || process.env.ODIROUTER_API_KEY
    : process.env.ODIROUTER_API_KEY;
  if (!apiKey) throw new Error("ODIROUTER_API_KEY missing");

  const metaRaw = await odiMessages({
    model: process.env.ODIROUTER_MODEL_FAST || model,
    apiKey: process.env.ODIROUTER_API_KEY,
    base: cfg.base,
    system: SYSTEM_META,
    user: JSON.stringify(
      {
        title_en: data.title_en,
        meta_description_en: data.meta_description_en || "",
      },
      null,
      0
    ),
    maxTokens: 2048,
    timeoutMs: 180_000,
  });
  let meta;
  try {
    let cleaned = metaRaw.trim();
    cleaned = cleaned
      .replace(/^```(?:json)?\s*/i, "")
      .replace(/\s*```$/i, "");
    const brace = cleaned.match(/\{[\s\S]*\}/);
    if (brace) cleaned = brace[0];
    meta = JSON.parse(cleaned);
  } catch {
    // Fallback: pull title/meta from loose text if model broke JSON
    const titleM = metaRaw.match(/"title_ru"\s*:\s*"((?:\\.|[^"\\])*)"/);
    const metaM = metaRaw.match(
      /"meta_description_ru"\s*:\s*"((?:\\.|[^"\\])*)"/
    );
    if (!titleM) {
      throw new Error(`meta JSON parse fail: ${metaRaw.slice(0, 200)}`);
    }
    meta = {
      title_ru: JSON.parse(`"${titleM[1]}"`),
      meta_description_ru: metaM ? JSON.parse(`"${metaM[1]}"`) : "",
    };
  }

  const chunkSize = useLong ? 6_000 : 10_000;
  const chunks = chunkText(data.body_en || "", chunkSize);
  const bodyParts = [];
  for (let i = 0; i < chunks.length; i++) {
    process.stdout.write(`chunk ${i + 1}/${chunks.length} `);
    const user = [
      `Фрагмент ${i + 1}/${chunks.length} статьи «${data.title_en}».`,
      i > 0
        ? "Продолжай перевод с того же стиля; это середина/конец текста."
        : "",
      "",
      chunks[i],
    ].join("\n");
    const part = await odiMessages({
      model,
      apiKey,
      base: cfg.base,
      system: SYSTEM_BODY,
      user,
      maxTokens: useLong ? 16_384 : 16_384,
      timeoutMs: useLong ? 300_000 : 600_000,
    });
    bodyParts.push(part.trim());
    if (cfg.delay > 0) await sleep(cfg.delay);
  }

  return {
    title_ru: String(meta.title_ru || "").trim(),
    meta_description_ru: String(meta.meta_description_ru || "").trim(),
    body_ru: bodyParts.join(""),
  };
}

async function loadProgress() {
  try {
    return JSON.parse(await fs.readFile(PROGRESS, "utf8"));
  } catch {
    return { done: {} };
  }
}

async function saveProgress(p) {
  await fs.mkdir(path.dirname(PROGRESS), { recursive: true });
  await fs.writeFile(PROGRESS, JSON.stringify(p, null, 2) + "\n");
}

async function runPool(items, worker, concurrency) {
  let idx = 0;
  const runners = Array.from({ length: concurrency }, async () => {
    while (idx < items.length) {
      const i = idx++;
      await worker(items[i], i);
    }
  });
  await Promise.all(runners);
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const dir = await resolveI18nDir();
  const files = (await fs.readdir(dir))
    .filter((f) => f.endsWith(".json") && !f.endsWith(".src.json"))
    .sort();
  let items = [];
  for (const f of files) {
    const stem = f.replace(/\.json$/, "");
    if (args.only && !stem.includes(args.only) && !f.includes(args.only)) continue;
    const raw = await fs.readFile(path.join(dir, f), "utf8");
    const data = JSON.parse(raw);
    items.push({
      file: f,
      stem,
      path: path.join(dir, f),
      data,
      chars: String(data.body_en || "").length,
    });
  }
  // Smaller articles first; huge Special Edition last
  items.sort((a, b) => a.chars - b.chars || a.stem.localeCompare(b.stem));
  if (args.limit > 0) items = items.slice(0, args.limit);

  const progress = await loadProgress();
  const done = progress.done || {};

  const base = (
    process.env.ODIROUTER_BASE_URL || "https://api.odirouter.ai/v1"
  ).replace(/\/$/, "");

  const todo = args.resume
    ? items.filter((it) => {
        const key = it.stem;
        if (done[key]?.ok) return false;
        const d = it.data;
        return !(String(d.body_ru || "").trim() && String(d.title_ru || "").trim());
      })
    : items;

  console.log(`emulate translate: ${todo.length}/${items.length} pending (dir=${dir})`);

  if (args.dryRun) {
    console.log("dry-run, exit");
    return;
  }

  let ok = 0;
  let fail = 0;

  await runPool(
    todo,
    async (item, i) => {
      process.stdout.write(`[${i + 1}/${todo.length}] ${item.stem} … `);
      try {
        const tr = await translateArticle(item.data, {
          base,
          delay: args.delay,
        });
        const merged = {
          ...item.data,
          title_ru: tr.title_ru || item.data.title_ru,
          meta_description_ru:
            tr.meta_description_ru || item.data.meta_description_ru,
          body_ru: tr.body_ru,
        };
        await fs.writeFile(
          item.path,
          JSON.stringify(merged, null, 2) + "\n",
          "utf8"
        );
        const mirror = path.join(
          path.join(ROOT, "db/migration/emulate_articles/i18n"),
          item.file
        );
        await fs.writeFile(
          mirror,
          JSON.stringify(merged, null, 2) + "\n",
          "utf8"
        );
        done[item.stem] = { ok: true, at: new Date().toISOString() };
        progress.done = done;
        await saveProgress(progress);
        console.log(`OK (${merged.body_ru.length} chars)`);
        ok++;
      } catch (e) {
        done[item.stem] = {
          ok: false,
          err: e.message,
          at: new Date().toISOString(),
        };
        progress.done = done;
        await saveProgress(progress);
        console.log(`FAIL ${e.message}`);
        fail++;
      }
      if (args.delay > 0) await sleep(args.delay);
    },
    args.workers
  );

  console.log(`done ok=${ok} fail=${fail}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
