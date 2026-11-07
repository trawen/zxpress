#!/usr/bin/env node
/**
 * Classify Subliminal Extacy articles → ezine_categories by meta/title (OdiRouter).
 *
 * Usage:
 *   node classify.mjs
 *   node classify.mjs --resume
 *   node classify.mjs --dry-run
 *   node classify.mjs --workers=6 --delay=200
 */
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const IMPORTS = path.join(ROOT, "site/cli/imports");
const OUT = path.join(__dirname, "generated");
const CATS_FILE = path.join(__dirname, "categories_catalog.txt");
const PROGRESS = path.join(OUT, "classify_progress.json");

const ENV_CANDIDATES = [
  path.join(ROOT, ".env"),
  path.join(ROOT, "local/.env"),
  path.resolve(ROOT, "../zxpress-markdown/.env"),
  path.resolve(__dirname, "../../../../zxpress-markdown/.env"),
];

const PRESS_ID = 213;

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
    delay: 800,
    only: null,
  };
  for (const a of argv) {
    if (a === "--resume") out.resume = true;
    else if (a === "--dry-run") out.dryRun = true;
    else if (a.startsWith("--workers=")) out.workers = Math.max(1, Number(a.slice(10)) || 4);
    else if (a.startsWith("--delay=")) out.delay = Math.max(0, Number(a.slice(8)) || 0);
    else if (a.startsWith("--only=")) out.only = a.slice(7);
  }
  return out;
}

function tocNumber(stem, issue) {
  if (/^\d+_big_belly/i.test(stem)) return 16;
  if (issue === "07") {
    const m = stem.match(/^(\d+)_(\d+)_/);
    if (m) return Number(m[2]);
  }
  const m = stem.match(/^(\d+)_/);
  return m ? Number(m[1]) : 0;
}

function issueFromDir(dirName) {
  const m = dirName.match(/^subxtc(\d{2})_i18n$/);
  return m ? m[1] : null;
}

async function loadCategories(file) {
  const raw = await fs.readFile(file, "utf8");
  const byId = new Map();
  const lines = [];
  for (const line of raw.split(/\r?\n/)) {
    const s = line.trim();
    if (!s || s.startsWith("#")) continue;
    const m = s.match(/^(\d+)\t(.+)$/);
    if (!m) continue;
    const id = Number(m[1]);
    const name = m[2].trim();
    byId.set(id, name);
    lines.push(`${id}\t${name}`);
  }
  return { byId, text: lines.join("\n") };
}

function systemPrompt(catsText) {
  return `You classify ZX Spectrum press articles for zxpress.ru into exactly ONE ezine category.

Source: Subliminal Extacy diskmag (humour, demoscene, coding, party reports, satire).

Input: Russian and English titles and meta descriptions only.

Rules:
- category_id: ONE integer from ALLOWED CATEGORIES. Never invent ids.
- Prefer a specific leaf over a vague parent when both fit.
- confidence: 0.0–1.0 how sure you are.

Typical Subliminal Extacy mapping:
- редакционные / about this issue / disclaimer → 123 (Пресса) or 130
- party reports, Forever, demoparty travel → 92 (Отчёты) or 89 (Демопати)
- demoscene news, release roundups → 94 (Обзоры) under демосцена (88) or 88
- Z80/AY/graphics coding tutorials → 29 (Программирование), 32 (Оптимизация), 35 (Графика), 34 (Музыка, AY)
- interviews → 77 (Интервью)
- reader letters / who to write → 154 (Читательские письма) or 175
- humour, satire, horoscopes, silly columns → 117 (Юмор и развлечение)
- platform compare / scene opinion → 135 or 103
- ads, classifieds → 118
- how to use the mag / contribute → 123 or 121 (Прочее)
- music composition / soundtrack making → 34 or 68
- history / trivia (Did you know) → 110 or 116
- food/recipe joke columns → 117 or 121

Return ONLY valid JSON (no markdown fences):
{"category_id":0,"category_name_ru":"","confidence":0.0,"reason_ru":""}

category_name_ru must match the chosen id from the list (copy exact name_ru).

======== ALLOWED CATEGORIES (id + name_ru) ========
${catsText}
======== END ALLOWED CATEGORIES ========`;
}

function repairJsonEscapes(text) {
  let out = "";
  let inStr = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (!inStr) {
      out += c;
      if (c === '"') inStr = true;
      continue;
    }
    if (c === '"') {
      let bs = 0;
      for (let j = out.length - 1; j >= 0 && out[j] === "\\"; j--) bs++;
      out += c;
      if (bs % 2 === 0) inStr = false;
      continue;
    }
    if (c === "\\") {
      const n = text[i + 1];
      if (n === undefined) {
        out += "\\\\";
        continue;
      }
      if ('"\\/bfnrt'.includes(n)) {
        out += c + n;
        i++;
        continue;
      }
      if (n === "u" && /^[0-9a-fA-F]{4}/.test(text.slice(i + 2, i + 6))) {
        out += text.slice(i, i + 6);
        i += 5;
        continue;
      }
      out += "\\\\";
      continue;
    }
    if (c === "\n") {
      out += "\\n";
      continue;
    }
    out += c;
  }
  return out;
}

function parseModelJson(text) {
  const start = text.indexOf("{");
  const end = text.lastIndexOf("}");
  const sliced = start >= 0 && end > start ? text.slice(start, end + 1) : text;
  try {
    return JSON.parse(sliced);
  } catch (e1) {
    return JSON.parse(repairJsonEscapes(sliced));
  }
}

async function classifyOne(item, catsText, model, key, base) {
  const payload = [
    `Issue: SE#${item.issue}, TOC #${item.number}`,
    `File: ${item.stem}`,
    `title_ru: ${item.title_ru}`,
    `title_en: ${item.title_en}`,
    `meta_description_ru: ${item.meta_ru}`,
    `meta_description_en: ${item.meta_en}`,
  ].join("\n");

  const body = JSON.stringify({
    model,
    max_tokens: 512,
    temperature: 0.05,
    system: systemPrompt(catsText),
    messages: [{ role: "user", content: payload }],
  });

  for (let attempt = 0; attempt < 8; attempt++) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 90_000);
    let res;
    try {
      res = await fetch(`${base}/messages`, {
        method: "POST",
        signal: controller.signal,
        headers: {
          "content-type": "application/json",
          Authorization: `Bearer ${key}`,
          "x-api-key": key,
          "anthropic-version": "2023-06-01",
        },
        body,
      });
    } finally {
      clearTimeout(timer);
    }
    const raw = await res.text();
    if (res.status === 429 || res.status === 503) {
      const wait = Math.min(60_000, 2_500 * 2 ** attempt);
      await sleep(wait);
      continue;
    }
    if (!res.ok) {
      const err = new Error(`HTTP ${res.status}: ${raw.slice(0, 400)}`);
      err.status = res.status;
      throw err;
    }
    const data = JSON.parse(raw);
    let text = Array.isArray(data.content)
      ? data.content.map((c) => c.text || "").join("")
      : String(data.content || raw);
    text = text.trim().replace(/^```(?:json)?\n?/, "").replace(/\n?```$/, "");
    return parseModelJson(text);
  }
  throw new Error("rate limit retries exhausted");
}

function normalizeAssignment(parsed, byId, item) {
  const id = Number(parsed.category_id);
  if (!Number.isInteger(id) || !byId.has(id)) {
    throw new Error(`invalid category_id ${parsed.category_id}`);
  }
  const confidence = Math.min(1, Math.max(0, Number(parsed.confidence) || 0));
  return {
    issue: item.issue,
    number: item.number,
    stem: item.stem,
    title_ru: item.title_ru,
    title_en: item.title_en,
    meta_description_ru: item.meta_ru,
    meta_description_en: item.meta_en,
    category_id: id,
    category_name_ru: byId.get(id),
    confidence,
    reason_ru: String(parsed.reason_ru || "").slice(0, 300),
  };
}

async function collectArticles() {
  const ents = await fs.readdir(IMPORTS, { withFileTypes: true });
  const items = [];
  for (const ent of ents) {
    if (!ent.isDirectory()) continue;
    const issue = issueFromDir(ent.name);
    if (!issue) continue;
    const dir = path.join(IMPORTS, ent.name);
    const files = (await fs.readdir(dir)).filter((f) => f.endsWith(".json")).sort();
    for (const f of files) {
      const stem = f.replace(/\.json$/, "");
      if (stem.includes(".src")) continue;
      const raw = await fs.readFile(path.join(dir, f), "utf8");
      const data = JSON.parse(raw);
      const number = tocNumber(stem, issue);
      if (number <= 0) {
        throw new Error(`bad toc ${ent.name}/${stem}`);
      }
      items.push({
        issue,
        number,
        stem,
        rel: `${ent.name}/${f}`,
        title_ru: String(data.title_ru || data.title_en || "").trim(),
        title_en: String(data.title_en || data.title_cs || "").trim(),
        meta_ru: String(data.meta_description_ru || "").trim(),
        meta_en: String(data.meta_description_en || data.meta_description_cs || "").trim(),
      });
    }
  }
  items.sort(
    (a, b) => a.issue.localeCompare(b.issue) || a.number - b.number
  );
  return items;
}

function sqlQuote(s) {
  return `'${String(s).replace(/\\/g, "\\\\").replace(/'/g, "''")}'`;
}

function buildSql(assignments) {
  const lines = [
    "-- Subliminal Extacy (press_id=213): ezine_article_categories from AI meta classification",
    `-- Generated: ${new Date().toISOString()}`,
    "SET NAMES utf8mb4;",
    "",
  ];
  const catIds = new Set();
  for (const a of assignments) {
    catIds.add(a.category_id);
    lines.push(
      `-- SE#${a.issue} #${a.number} ${a.stem} → ${a.category_id} ${a.category_name_ru} (${a.confidence})`
    );
    lines.push(
      "INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order)"
    );
    lines.push("SELECT");
    lines.push(`  ${a.category_id},`);
    lines.push("  a.id,");
    lines.push("  0");
    lines.push("FROM articles a");
    lines.push("INNER JOIN issue i ON i.id = a.id_issue");
    lines.push(`WHERE i.id_press = ${PRESS_ID}`);
    lines.push(
      `  AND (i.title = ${sqlQuote(a.issue)} OR LPAD(i.title, 2, '0') = ${sqlQuote(a.issue)} OR i.slug_ru = ${sqlQuote(a.issue)} OR i.slug_en = ${sqlQuote(a.issue)})`
    );
    lines.push(`  AND a.number = ${a.number};`);
    lines.push("");
  }
  lines.push("-- Recount articles_count for touched categories");
  for (const cid of [...catIds].sort((x, y) => x - y)) {
    lines.push(
      `UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=${cid}) WHERE id=${cid} LIMIT 1;`
    );
  }
  lines.push("");
  return lines.join("\n");
}

async function loadProgress() {
  try {
    const raw = await fs.readFile(PROGRESS, "utf8");
    return JSON.parse(raw);
  } catch {
    return { done: {} };
  }
}

async function saveProgress(progress) {
  await fs.mkdir(OUT, { recursive: true });
  await fs.writeFile(PROGRESS, JSON.stringify(progress, null, 2) + "\n");
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function runPool(queue, worker, n) {
  let idx = 0;
  async function w() {
    for (;;) {
      const i = idx++;
      if (i >= queue.length) return;
      await worker(queue[i], i);
    }
  }
  await Promise.all(Array.from({ length: n }, () => w()));
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const cats = await loadCategories(CATS_FILE);
  if (cats.byId.size < 20) {
    console.error("categories catalog too small");
    process.exit(1);
  }

  let items = await collectArticles();
  if (args.only) {
    items = items.filter(
      (x) =>
        x.stem.includes(args.only) ||
        x.rel.includes(args.only) ||
        `SE#${x.issue}` === args.only
    );
  }
  console.log(`manifest: ${items.length} articles`);

  const progress = await loadProgress();
  const done = progress.done || {};

  if (args.dryRun) {
    const assignments = items
      .map((it) => done[`${it.issue}:${it.number}`])
      .filter(Boolean);
    if (assignments.length === 0) {
      console.error("dry-run: no assignments in progress");
      process.exit(1);
    }
    await fs.mkdir(OUT, { recursive: true });
    assignments.sort(
      (a, b) => a.issue.localeCompare(b.issue) || a.number - b.number
    );
    await fs.writeFile(
      path.join(OUT, "assignments.json"),
      JSON.stringify(assignments, null, 2) + "\n"
    );
    await fs.writeFile(
      path.join(OUT, "01_insert_ezine_article_categories.sql"),
      buildSql(assignments)
    );
    console.log(`dry-run SQL: ${assignments.length} rows`);
    return;
  }

  const key = process.env.ODIROUTER_API_KEY;
  if (!key) {
    console.error("ODIROUTER_API_KEY missing");
    process.exit(1);
  }
  const base = (process.env.ODIROUTER_BASE_URL || "https://api.odirouter.ai/v1").replace(
    /\/$/,
    ""
  );
  const model =
    process.env.ODIROUTER_MODEL_FAST ||
    process.env.ODIROUTER_MODEL ||
    "gemini-3-flash";

  const todo = args.resume
    ? items.filter((it) => !done[`${it.issue}:${it.number}`])
    : items;

  let ok = 0;
  let fail = 0;

  await runPool(todo, async (item, i) => {
    const keyId = `${item.issue}:${item.number}`;
    if (done[keyId] && args.resume) return;
    process.stdout.write(`[${i + 1}/${todo.length}] SE#${item.issue} #${item.number} … `);
    try {
      const parsed = await classifyOne(item, cats.text, model, key, base);
      const row = normalizeAssignment(parsed, cats.byId, item);
      done[keyId] = row;
      progress.done = done;
      await saveProgress(progress);
      console.log(`→ ${row.category_id} ${row.category_name_ru}`);
      ok++;
    } catch (e) {
      console.log(`FAIL ${e.message}`);
      fail++;
    }
    if (args.delay > 0) await sleep(args.delay);
  }, args.workers);

  const assignments = Object.values(done).sort(
    (a, b) => a.issue.localeCompare(b.issue) || a.number - b.number
  );
  await fs.mkdir(OUT, { recursive: true });
  await fs.writeFile(
    path.join(OUT, "assignments.json"),
    JSON.stringify(assignments, null, 2) + "\n"
  );
  await fs.writeFile(
    path.join(OUT, "01_insert_ezine_article_categories.sql"),
    buildSql(assignments)
  );
  const summary = {
    generated_at: new Date().toISOString(),
    press_id: PRESS_ID,
    total_manifest: items.length,
    classified: assignments.length,
    ok,
    fail,
    model,
  };
  await fs.writeFile(
    path.join(OUT, "summary.json"),
    JSON.stringify(summary, null, 2) + "\n"
  );
  console.log(`DONE classified=${assignments.length} ok=${ok} fail=${fail}`);
  if (fail > 0) process.exitCode = 1;
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
