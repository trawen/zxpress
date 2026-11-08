#!/usr/bin/env node
/**
 * ACNews #76–#80: plain UTF-8 → markdown + meta + tags + categories.
 * No translation — RU/EN already exist in source.
 *
 *   node db/migration/acnews_76_80/enrich.mjs --resume --workers=2
 *   node db/migration/acnews_76_80/enrich.mjs --only=76/01_news
 */
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const TEXTS = path.join(ROOT, "local/work/ACN7680_texts");
const OUT = path.join(TEXTS, "articles");
const CATS_FILE = path.join(
  ROOT,
  "db/migration/subxtc_article_categories/categories_catalog.txt"
);
const PROGRESS = path.join(__dirname, "generated/enrich_progress.json");

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
  const out = { resume: false, workers: 1, delay: 500, only: null, limit: 0 };
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
    } catch {
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

function pickModel(chars) {
  const longLim = Number(process.env.ODIROUTER_LONG_CHARS || 25_000);
  const long = chars > longLim;
  return {
    long,
    model: long
      ? process.env.ODIROUTER_MODEL_LONG ||
        process.env.ODIROUTER_MODEL ||
        "gemini-3.7-flash"
      : process.env.ODIROUTER_MODEL_FAST ||
        process.env.ODIROUTER_MODEL ||
        "gemini-3.1-flash-lite",
    apiKey: long
      ? process.env.ODIROUTER_API_KEY_LONG || process.env.ODIROUTER_API_KEY
      : process.env.ODIROUTER_API_KEY,
  };
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

const MD_SYSTEM = `Ты конвертируешь текст газеты ACNews (Alone Coder, ZX Spectrum / ATM / NedoOS) в Markdown для zxpress.ru.

Правила:
- Заголовки: ## / ###
- Списки — markdown-списки; таблицы где уместно
- ASCII-арт, рамки, моноширинные блоки, листинги, словари → \`\`\`text … \`\`\`
- Имена людей, URL, e-mail, названия программ/платформ не меняй
- Не переводи и не сокращай содержимое
- Верни ТОЛЬКО markdown фрагмента, без пояснений`;

async function plainToMd(plain, title, lang, cfg) {
  if (!plain || !plain.trim()) return "";
  const { model, apiKey } = pickModel(plain.length);
  const chunks = chunkText(plain, cfg.longChunk);
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
      system: MD_SYSTEM,
      user,
      maxTokens: 16_384,
    });
    parts.push(out.trim());
    if (cfg.delay) await sleep(cfg.delay);
  }
  return parts.join("\n\n");
}

function extractJson(raw) {
  const t = String(raw || "").trim();
  const fence = t.match(/```(?:json)?\s*([\s\S]*?)```/i);
  const body = fence ? fence[1].trim() : t;
  const start = body.indexOf("{");
  const end = body.lastIndexOf("}");
  if (start < 0 || end <= start) throw new Error("no JSON object in model output");
  return JSON.parse(body.slice(start, end + 1));
}

async function loadCategories() {
  const raw = await fs.readFile(CATS_FILE, "utf8");
  const lines = [];
  const ids = new Set();
  for (const line of raw.split(/\r?\n/)) {
    const s = line.trim();
    if (!s || s.startsWith("#")) continue;
    const m = s.match(/^(\d+)\t(.+)$/);
    if (!m) continue;
    const id = Number(m[1]);
    ids.add(id);
    lines.push(`${id}\t${m[2]}`);
  }
  return { catalogText: lines.join("\n"), ids };
}

async function metaTagsCats(article, cats, cfg) {
  const excerptRu = (article.body_ru_plain || "").slice(0, 3500);
  const excerptEn = (article.body_en_plain || "").slice(0, 3500);
  const system = `Ты размечаешь статью газеты ACNews для архива zxpress.ru.
Не переводи текст. Верни ТОЛЬКО JSON-объект:
{
  "meta_description_ru": "до 220 символов, по-русски",
  "meta_description_en": "до 220 символов, по-английски",
  "tags": [{"tag_name":"до 24 символов","tag_alias":"latin-kebab"}],
  "category_ids": [числа id из каталога]
}
Правила тегов: 3–6 штук; tag_name ≤24; alias латиницей/цифры/дефис.
Категории: 1–3 id ТОЛЬКО из каталога ниже (предпочтительны листовые).
Каталог категорий (id\\tname_ru):
${cats.catalogText}`;

  const user = [
    `Выпуск: ACNews #${article.issue}`,
    `title_ru: ${article.title_ru}`,
    `title_en: ${article.title_en}`,
    "",
    "Фрагмент RU:",
    excerptRu || "(нет)",
    "",
    "Фрагмент EN:",
    excerptEn || "(нет)",
  ].join("\n");

  const { model, apiKey } = pickModel(8000);
  const raw = await odiMessages({
    model,
    apiKey,
    base: cfg.base,
    system,
    user,
    maxTokens: 2048,
  });
  const data = extractJson(raw);
  const tags = Array.isArray(data.tags)
    ? data.tags
        .map((t) => ({
          tag_name: String(t.tag_name || "").trim().slice(0, 24),
          tag_alias: String(t.tag_alias || "")
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9-]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .slice(0, 32),
        }))
        .filter((t) => t.tag_name && t.tag_alias)
        .slice(0, 6)
    : [];
  const category_ids = Array.isArray(data.category_ids)
    ? [...new Set(data.category_ids.map((n) => Number(n)).filter((n) => cats.ids.has(n)))]
        .slice(0, 3)
    : [];
  const clip = (s, n) => {
    const t = String(s || "").replace(/\s+/g, " ").trim();
    if (t.length <= n) return t;
    return t.slice(0, n - 1).replace(/\s+\S*$/, "") + "…";
  };
  return {
    meta_description_ru: clip(data.meta_description_ru, 220),
    meta_description_en: clip(data.meta_description_en, 220),
    tags,
    category_ids,
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
  const manifest = JSON.parse(
    await fs.readFile(path.join(TEXTS, "manifest.json"), "utf8")
  );
  const cats = await loadCategories();
  await fs.mkdir(OUT, { recursive: true });

  let items = [];
  for (const [issue, cfg] of Object.entries(manifest.issues)) {
    for (const a of cfg.articles) {
      const key = `${issue}/${a.id}`;
      if (args.only && !key.includes(args.only) && !a.id.includes(args.only))
        continue;
      const ruPath = path.join(TEXTS, issue, `${a.id}_ru.txt`);
      const enPath = path.join(TEXTS, issue, `${a.id}_en.txt`);
      const body_ru_plain = await fs.readFile(ruPath, "utf8");
      let body_en_plain = "";
      try {
        body_en_plain = await fs.readFile(enPath, "utf8");
      } catch {
        body_en_plain = "";
      }
      items.push({
        key,
        issue,
        id: a.id,
        title_ru: a.title_ru,
        title_en: a.title_en,
        body_ru_plain,
        body_en_plain,
        chars: body_ru_plain.length + body_en_plain.length,
      });
    }
  }
  items.sort((a, b) => a.chars - b.chars || a.key.localeCompare(b.key));
  if (args.limit) items = items.slice(0, args.limit);

  const progress = await loadProgress();
  const done = progress.done || {};
  const todo = args.resume
    ? items.filter((it) => !done[it.key]?.ok)
    : items;

  const base = (
    process.env.ODIROUTER_BASE_URL || "https://api.odirouter.ai/v1"
  ).replace(/\/$/, "");
  console.log(`acnews enrich: ${todo.length}/${items.length} pending`);

  let ok = 0;
  let fail = 0;
  await runPool(
    todo,
    async (item, idx) => {
      process.stdout.write(`[${idx + 1}/${todo.length}] ${item.key} …`);
      try {
        const cfg = {
          base,
          delay: args.delay,
          longChunk: item.chars > 40_000 ? 6_000 : 8_500,
        };
        const body_ru = await plainToMd(
          item.body_ru_plain,
          item.title_ru,
          "ru",
          cfg
        );
        const body_en = await plainToMd(
          item.body_en_plain,
          item.title_en,
          "en",
          cfg
        );
        process.stdout.write(" meta");
        const meta = await metaTagsCats(item, cats, cfg);
        const out = {
          press: "ACNews",
          press_id: 8,
          issue: item.issue,
          article_id: item.id,
          title_ru: item.title_ru,
          title_en: item.title_en,
          meta_description_ru: meta.meta_description_ru,
          meta_description_en: meta.meta_description_en,
          tags: meta.tags,
          category_ids: meta.category_ids,
          body_ru_plain: item.body_ru_plain,
          body_en_plain: item.body_en_plain,
          body_ru,
          body_en,
          text_type: 3,
        };
        const outFile = path.join(OUT, `${item.issue}_${item.id}.json`);
        await fs.writeFile(
          outFile,
          JSON.stringify(out, null, 2) + "\n",
          "utf8"
        );
        done[item.key] = { ok: true, file: path.basename(outFile) };
        await saveProgress({ done });
        ok++;
        console.log(" OK");
      } catch (err) {
        fail++;
        done[item.key] = { ok: false, error: String(err) };
        await saveProgress({ done });
        console.log(" FAIL", err.message || err);
      }
    },
    args.workers
  );

  console.log(`done ok=${ok} fail=${fail}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
