#!/usr/bin/env node
/**
 * Emulate: plain body_en/body_ru → markdown (SE-style).
 * Keeps originals in body_en_plain / body_ru_plain.
 *
 *   node db/migration/emulate_articles/to_markdown.mjs --resume --workers=2
 *   node db/migration/emulate_articles/to_markdown.mjs --only=01_01 --resume
 */
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const I18N = path.join(ROOT, "site/cli/imports/emulate_i18n");
const MIRROR = path.join(__dirname, "i18n");
const PROGRESS = path.join(__dirname, "generated/md_progress.json");

for (const file of [path.join(ROOT, ".env"), path.join(ROOT, "local/.env")]) {
  try {
    const raw = await fs.readFile(file, "utf8");
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
    break;
  } catch {
    /* */
  }
}

function parseArgs(argv) {
  const out = { resume: false, workers: 1, delay: 400, only: null, limit: 0 };
  for (const a of argv) {
    if (a === "--resume") out.resume = true;
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

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function odiMessages({ model, apiKey, base, system, user, maxTokens }) {
  const body = JSON.stringify({
    model,
    max_tokens: maxTokens,
    temperature: 0.1,
    system,
    messages: [{ role: "user", content: user }],
  });
  for (let attempt = 0; attempt < 8; attempt++) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 600_000);
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
      await sleep(Math.min(60_000, 2_000 * 2 ** attempt));
      continue;
    } finally {
      clearTimeout(timer);
    }
    const raw = await res.text();
    if ([429, 502, 503, 504].includes(res.status)) {
      await sleep(Math.min(90_000, 2_500 * 2 ** attempt));
      continue;
    }
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${raw.slice(0, 400)}`);
    const data = JSON.parse(raw);
    return Array.isArray(data.content)
      ? data.content.map((c) => c.text || "").join("")
      : String(data.content || raw);
  }
  throw new Error("ODIROUTER retries exhausted");
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

const SYSTEM = `Ты конвертируешь текст журнала Emulate (ZX Spectrum, 1995–96) в Markdown для zxpress.ru.

Правила:
- Заголовки: ## / ###
- Списки POKE/cheat/tips — markdown-списки или таблицы, где уместно
- ASCII-арт, рамки, моноширинные блоки → в ограждение \`\`\`text … \`\`\`
- Имена игр, URL, e-mail, адреса POKE не меняй
- Не сокращай содержимое
- Верни ТОЛЬКО markdown фрагмента, без пояснений`;

async function plainToMd(plain, title, lang, cfg) {
  const long = plain.length > Number(process.env.ODIROUTER_LONG_CHARS || 25_000);
  const model = long
    ? process.env.ODIROUTER_MODEL_LONG ||
      process.env.ODIROUTER_MODEL ||
      "gemini-3.7-flash"
    : process.env.ODIROUTER_MODEL_FAST ||
      process.env.ODIROUTER_MODEL ||
      "gemini-3.1-flash-lite";
  const apiKey = long
    ? process.env.ODIROUTER_API_KEY_LONG || process.env.ODIROUTER_API_KEY
    : process.env.ODIROUTER_API_KEY;
  const chunks = chunkText(plain, long ? 7_000 : 9_000);
  const parts = [];
  for (let i = 0; i < chunks.length; i++) {
    process.stdout.write(` ${lang}${i + 1}/${chunks.length}`);
    const user = [
      `Язык: ${lang === "ru" ? "русский" : "английский"}`,
      `Статья: ${title}`,
      `Фрагмент ${i + 1}/${chunks.length}. Конвертируй в Markdown.`,
      "",
      chunks[i],
    ].join("\n");
    const out = await odiMessages({
      model,
      apiKey,
      base: cfg.base,
      system: SYSTEM,
      user,
      maxTokens: 16_384,
    });
    parts.push(out.trim());
    if (cfg.delay) await sleep(cfg.delay);
  }
  return parts.join("\n\n");
}

function alreadyMd(s) {
  const t = (s || "").trim();
  if (!t) return false;
  if (t.startsWith("##") || t.startsWith("# ")) return true;
  if (t.includes("```text") || t.includes("```\n")) return true;
  // mono plain usually has many \r or dense fixed-width
  return false;
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

async function runPool(items, worker, n) {
  let i = 0;
  await Promise.all(
    Array.from({ length: n }, async () => {
      while (i < items.length) {
        const idx = i++;
        await worker(items[idx], idx);
      }
    })
  );
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const files = (await fs.readdir(I18N))
    .filter((f) => f.endsWith(".json"))
    .sort();
  let items = [];
  for (const f of files) {
    const stem = f.replace(/\.json$/, "");
    if (args.only && !stem.includes(args.only) && !f.includes(args.only))
      continue;
    const p = path.join(I18N, f);
    const data = JSON.parse(await fs.readFile(p, "utf8"));
    items.push({
      file: f,
      stem,
      path: p,
      data,
      chars: String(data.body_en_plain || data.body_en || "").length,
    });
  }
  items.sort((a, b) => a.chars - b.chars || a.stem.localeCompare(b.stem));
  if (args.limit) items = items.slice(0, args.limit);

  const progress = await loadProgress();
  const done = progress.done || {};
  const todo = args.resume
    ? items.filter((it) => {
        if (done[it.stem]?.ok) return false;
        // skip if both bodies look like md and plains exist
        const d = it.data;
        const hasPlain = d.body_en_plain && d.body_ru_plain;
        const mdReady =
          hasPlain && alreadyMd(d.body_en) && alreadyMd(d.body_ru);
        return !mdReady;
      })
    : items;

  const base = (
    process.env.ODIROUTER_BASE_URL || "https://api.odirouter.ai/v1"
  ).replace(/\/$/, "");
  console.log(`emulate→md: ${todo.length}/${items.length} pending`);

  let ok = 0;
  let fail = 0;
  await runPool(
    todo,
    async (item, idx) => {
      process.stdout.write(`[${idx + 1}/${todo.length}] ${item.stem} …`);
      try {
        const d = { ...item.data };
        // freeze plain once
        if (!d.body_en_plain) d.body_en_plain = d.body_en || "";
        if (!d.body_ru_plain) d.body_ru_plain = d.body_ru || "";
        if (!String(d.body_ru_plain).trim()) {
          throw new Error("empty body_ru_plain — translate first");
        }

        const title = d.title_en || d.title_ru || item.stem;
        d.body_en = await plainToMd(d.body_en_plain, title, "en", {
          base,
          delay: args.delay,
        });
        d.body_ru = await plainToMd(d.body_ru_plain, title, "ru", {
          base,
          delay: args.delay,
        });
        d.format = "markdown";

        const json = JSON.stringify(d, null, 2) + "\n";
        await fs.writeFile(item.path, json, "utf8");
        await fs.writeFile(path.join(MIRROR, item.file), json, "utf8");
        done[item.stem] = { ok: true, at: new Date().toISOString() };
        progress.done = done;
        await saveProgress(progress);
        console.log(
          ` OK md_en=${d.body_en.length} md_ru=${d.body_ru.length}`
        );
        ok++;
      } catch (e) {
        done[item.stem] = {
          ok: false,
          err: e.message,
          at: new Date().toISOString(),
        };
        progress.done = done;
        await saveProgress(progress);
        console.log(` FAIL ${e.message}`);
        fail++;
      }
      if (args.delay) await sleep(args.delay);
    },
    args.workers
  );
  console.log(`done ok=${ok} fail=${fail}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
