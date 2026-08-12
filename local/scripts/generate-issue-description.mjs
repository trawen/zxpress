#!/usr/bin/env node
/**
 * Генерирует description / meta_description выпуска из meta description статей
 * через Gemini (Playwright CDP, как zxpress-markdown/translate.mjs).
 * Результат только в JSON-файл (БД не меняет).
 *
 * Подготовка Chrome:
 *   /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
 *     --remote-debugging-port=9222 --user-data-dir="$HOME/chrome-gemini-debug"
 *   # откройте https://gemini.google.com/app и войдите
 *
 * Запуск (из корня репо, Docker DB уже up — только чтение meta статей):
 *   node local/scripts/generate-issue-description.mjs                 # все издания
 *   node local/scripts/generate-issue-description.mjs --issue=123
 *   node local/scripts/generate-issue-description.mjs --issue=z80          # все выпуски
 *   node local/scripts/generate-issue-description.mjs --issue=z80,on-line  # несколько изданий
 *   node local/scripts/generate-issue-description.mjs --issue=z80 --issue=on-line
 *   node local/scripts/generate-issue-description.mjs --press=z80 --press=spectrofon
 *
 * Опции:
 *   (без --issue/--press)              все выпуски, у которых есть meta статей
 *   --issue=ID|PRESS|PRESS/ISSUE[,…]   можно повторять и через запятую
 *   --press=SLUG[,…] [--issue-slug=SLUG]
 *   --out=PATH           один выпуск → файл; batch → каталог (или PATH как префикс каталога)
 *   --cdp                подключиться к Chrome :9222 (по умолчанию)
 *   --no-cdp             свой Chrome + профиль .chrome-profile-issue-desc
 *   --cdp-url=URL
 *   --markdown=PATH      путь к zxpress-markdown (playwright + providers.mjs)
 *   --delay=MS           мин. пауза между выпусками (по умолчанию 8000)
 *   --delay-max=MS       макс. пауза между выпусками (по умолчанию 25000)
 *   --force              перезаписывать уже готовые JSON
 *
 * Env: ZXPRESS_MARKDOWN, DB_* (через docker compose run php)
 */

import fs from "node:fs/promises";
import path from "node:path";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";
import {
  loadPlaywrightAndProvider,
  connectGeminiCdp,
  openGeminiPersistent,
  geminiChat,
  resolveMarkdownRoot,
} from "./lib/gemini-cdp.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../..");
const META_MAX = 256;
const DESC_SOFT_MAX = 1200;

const PROMPT = `Ты редактор архива ZX Spectrum прессы (zxpress.ru).
По meta description статей одного выпуска газеты/журнала напиши краткое описание номера для сайта.

Правила:
1. description_ru — 3–5 связных предложений по-русски о содержании выпуска (без списка «статья 1, статья 2»).
2. description_en — тот же смысл по-английски (качественный перевод, не дословный калька).
3. meta_description_ru — SEO-текст до ${META_MAX} символов (можно начать с названия издания и номера).
4. meta_description_en — то же по-английски, до ${META_MAX} символов.
5. Не трать предложения на «паспорт» выпуска: номер, дату, жанр издания («информационно-развлекательная газета»), платформу («для ZX Spectrum») и прочие факты, уже ясные из названия. Сразу о содержании материалов. Плохо: «Выпуск 126 независимой информационно-развлекательной газеты Nicron для ZX Spectrum от 21 декабря 2003 года».
6. Избегай штампов и клише о читателях («читатели найдут», «читатели узнают», «вас ждёт»). Пиши обезличенно или обращайся напрямую.
7. Не упоминай в описаниях технические секции об авторах, контактах и составе редакции (разделы вида «Авторы», «Над номером работали» и т.п.).
8. Не выдумывай факты, которых нет во входных meta.
9. Не упоминай ИИ, промпт, «на основе описаний».
10. Верни ТОЛЬКО JSON-объект без markdown-обёртки:
{"description_ru":"...","description_en":"...","meta_description_ru":"...","meta_description_en":"..."}`;

const args = parseArgs(process.argv.slice(2));

async function main() {
  const items = await loadAllItems(args);
  if (items.length === 0) {
    console.error("Нет выпусков для обработки.");
    process.exit(1);
  }

  const isBatch = items.length > 1;
  console.log(`К обработке: ${items.length} выпуск(ов)`);

  const pending = [];
  let skippedExisting = 0;
  for (const item of items) {
    const issueId = item.issue?.id ?? item.id;
    const stubIssue = item.issue || { id: issueId };
    const outPath = resolveOutPath(args.out, stubIssue, isBatch);
    if (!args.force && (await isDoneOutput(outPath))) {
      skippedExisting += 1;
      console.log(
        `skip #${issueId} (уже есть ${path.relative(ROOT, outPath)})`
      );
      continue;
    }
    pending.push(item);
  }

  if (pending.length === 0) {
    console.log(
      `Нечего делать: все ${items.length} уже готовы. --force чтобы перегенерировать.`
    );
    return;
  }
  console.log(
    `Осталось: ${pending.length}` +
      (skippedExisting ? ` (пропущено готовых: ${skippedExisting})` : "")
  );

  const { chromium, provider, markdownRoot } = await loadPlaywrightAndProvider(
    args.markdown
  );
  console.log(`zxpress-markdown: ${markdownRoot}`);

  let session;
  if (args.cdp) {
    session = await connectGeminiCdp({
      chromium,
      provider,
      cdpUrl: args.cdpUrl,
    });
    if (!provider.urlMatch.test(session.page.url())) {
      await session.page.goto(provider.url, {
        waitUntil: "domcontentloaded",
        timeout: 120_000,
      });
    }
  } else {
    const profileDir = path.join(__dirname, ".chrome-profile-issue-desc");
    await fs.mkdir(profileDir, { recursive: true });
    session = await openGeminiPersistent({
      chromium,
      provider,
      profileDir,
    });
  }

  const results = [];
  let ok = 0;
  let skipped = skippedExisting;
  let failed = 0;

  for (let i = 0; i < pending.length; i++) {
    let item = pending[i];
    try {
      item = await hydrateItem(item);
    } catch (err) {
      failed += 1;
      console.error(
        `\n[${i + 1}/${pending.length}] dump FAIL #${item.issue?.id ?? item.id}: ${err.message || err}`
      );
      continue;
    }
    const issue = item.issue;
    const articles = item.articles || [];
    const label = `[${i + 1}/${pending.length}] #${issue.id} «${issue.press_title}» ${issue.title}`;

    console.log(`\n${label} — статей с meta: ${articles.length}`);

    if (articles.length === 0) {
      console.warn(`  skip: нет meta_description у статей`);
      skipped += 1;
      continue;
    }

    const articleBlock = articles
      .map((a, idx) => {
        const meta = a.meta_description_ru || a.meta_description_en || "";
        const title = a.title ? ` [${a.title}]` : "";
        return `${idx + 1}. id=${a.id}${title}\n   ${meta}`;
      })
      .join("\n");

    const userMessage =
      `${PROMPT}\n\n` +
      `Издание: ${issue.press_title}\n` +
      `Выпуск: ${issue.title}` +
      (issue.date ? ` (${issue.date})` : "") +
      `\n\n--- META DESCRIPTION СТАТЕЙ ---\n${articleBlock}\n--- КОНЕЦ ---`;

    try {
      console.log("  Gemini: генерирую…");
      const raw = await geminiChat(session.page, provider, userMessage, {
        sourceLen: userMessage.length,
      });
      const parsed = parseJsonResponse(raw);
      const descriptions = normalizeResult(parsed, issue);
      const result = {
        issue_id: issue.id,
        press_title: issue.press_title,
        issue_title: issue.title,
        press_slug_ru: issue.press_slug_ru || "",
        issue_slug_ru: issue.slug_ru || "",
        ...descriptions,
      };

      const outPath = resolveOutPath(args.out, issue, isBatch);
      await fs.mkdir(path.dirname(outPath), { recursive: true });
      await fs.writeFile(
        outPath,
        JSON.stringify(result, null, 2) + "\n",
        "utf8"
      );
      console.log(`  saved: ${outPath}`);
      results.push(result);
      ok += 1;
    } catch (err) {
      failed += 1;
      console.error(`  FAIL: ${err.message || err}`);
    }

    if (i < pending.length - 1 && args.delay > 0) {
      const pause = randomBetween(args.delay, args.delayMax);
      console.log(`  … пауза ${(pause / 1000).toFixed(1)}с`);
      await sleep(pause);
    }
  }

  if (session.owned) {
    await session.context.close().catch(() => {});
  }

  if (items.length > 1 && args.out && !looksLikeDir(args.out)) {
    const batchPath = path.isAbsolute(args.out)
      ? args.out
      : path.join(ROOT, args.out);
    // if --out=file.json for batch, also write combined summary next to per-issue files
    // (per-issue already written to local/work/); write combined only when out is .json file
    if (batchPath.endsWith(".json")) {
      await fs.mkdir(path.dirname(batchPath), { recursive: true });
      await fs.writeFile(
        batchPath,
        JSON.stringify({ count: results.length, items: results }, null, 2) +
          "\n",
        "utf8"
      );
      console.log(`\nbatch summary: ${batchPath}`);
    }
  }

  console.log(`\nГотово: ok=${ok} skip=${skipped} fail=${failed}`);
  if (ok === 0) process.exit(1);
}

function looksLikeDir(p) {
  return !p.endsWith(".json");
}

async function isDoneOutput(outPath) {
  try {
    const st = await fs.stat(outPath);
    if (st.size < 40) return false;
    const raw = await fs.readFile(outPath, "utf8");
    const data = JSON.parse(raw);
    return (
      typeof data?.description_ru === "string" &&
      data.description_ru.trim() !== "" &&
      typeof data?.meta_description_ru === "string" &&
      data.meta_description_ru.trim() !== ""
    );
  } catch {
    return false;
  }
}

function resolveOutPath(outArg, issue, isBatch) {
  if (!outArg) {
    return path.join(ROOT, `local/work/issue-desc-${issue.id}.json`);
  }
  const abs = path.isAbsolute(outArg) ? outArg : path.join(ROOT, outArg);
  if (isBatch) {
    if (looksLikeDir(outArg)) {
      return path.join(abs, `issue-desc-${issue.id}.json`);
    }
    // --out=foo.json → всё равно пишем per-issue в local/work/
    return path.join(ROOT, `local/work/issue-desc-${issue.id}.json`);
  }
  return abs;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function randomBetween(min, max) {
  const a = Math.min(min, max);
  const b = Math.max(min, max);
  return Math.floor(a + Math.random() * (b - a + 1));
}

function parseArgs(argv) {
  const out = {
    issues: [],
    presses: [],
    issueSlug: null,
    cdp: true,
    cdpUrl: process.env.CDP_URL || "http://127.0.0.1:9222",
    markdown: resolveMarkdownRoot(),
    out: null,
    delay: 8000,
    delayMax: 25_000,
    force: false,
  };
  for (const a of argv) {
    if (a === "--help" || a === "-h") {
      console.log(`Usage: node local/scripts/generate-issue-description.mjs [--issue=…] [--out=PATH] [--cdp]
  (без --issue/--press)          все выпуски с meta статей
  --issue=z80                    все выпуски одного издания
  --issue=z80,on-line            несколько изданий (через запятую)
  --issue=z80 --issue=on-line    то же, повтором флага
  --issue=z80/01,on-line/05      конкретные выпуски
  --press=z80,spectrofon         несколько изданий
  --press=SLUG --issue-slug=01   один номер у каждого из --press
  --delay=MS --delay-max=MS      случайная пауза между выпусками (8000–25000)
  --force                        перезаписать уже готовые JSON
  --no-cdp  --cdp-url=URL  --markdown=PATH
  По умолчанию пишет local/work/issue-desc-{id}.json; готовые пропускает`);
      process.exit(0);
    } else if (a === "--cdp") out.cdp = true;
    else if (a === "--no-cdp") out.cdp = false;
    else if (a === "--force") out.force = true;
    else if (a.startsWith("--issue=")) {
      out.issues.push(...splitList(a.slice(8)));
    } else if (a.startsWith("--press=")) {
      out.presses.push(...splitList(a.slice(8)));
    } else if (a.startsWith("--issue-slug=")) out.issueSlug = a.slice(13);
    else if (a.startsWith("--cdp-url=")) out.cdpUrl = a.slice(10);
    else if (a.startsWith("--markdown=")) out.markdown = a.slice(11);
    else if (a.startsWith("--out=")) out.out = a.slice(6);
    else if (a.startsWith("--delay-max=")) out.delayMax = Number(a.slice(12));
    else if (a.startsWith("--delay=")) out.delay = Number(a.slice(8));
  }
  if (!Number.isFinite(out.delay) || out.delay < 0) out.delay = 8000;
  if (!Number.isFinite(out.delayMax) || out.delayMax < 0) out.delayMax = 25_000;
  if (out.delayMax < out.delay) out.delayMax = out.delay;
  return out;
}

/** @param {string} raw */
function splitList(raw) {
  return String(raw || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
}

/**
 * Загружает выпуски по всем --issue / --press (или все, если не указано).
 * Без дублей по issue.id. Большие выборки — lazy (только id), dump при обработке.
 * @returns {Promise<Array<{issue: object, articles?: object[], lazy?: boolean, id?: number}>>}
 */
async function loadAllItems(args) {
  /** @type {Map<number, {issue: object, articles?: object[], lazy?: boolean, id?: number}>} */
  const byId = new Map();

  const addDump = (dump) => {
    if (dump?.ids_only && Array.isArray(dump.ids)) {
      for (const id of dump.ids) {
        const n = Number(id);
        if (!byId.has(n)) {
          byId.set(n, { lazy: true, id: n, issue: { id: n } });
        }
      }
      return;
    }
    for (const item of dumpItems(dump)) {
      byId.set(item.issue.id, item);
    }
  };

  if (args.issues.length === 0 && args.presses.length === 0) {
    console.log("dump --all (все выпуски с meta)");
    addDump(await dbDump({ all: true }));
    return [...byId.values()].sort(
      (a, b) => (a.issue?.id ?? a.id) - (b.issue?.id ?? b.id)
    );
  }

  for (const issueSpec of args.issues) {
    console.log(`dump --issue=${issueSpec}`);
    addDump(
      await dbDump({
        issue: issueSpec,
        press: null,
        issueSlug: null,
      })
    );
  }

  for (const press of args.presses) {
    const label = args.issueSlug
      ? `--press=${press} --issue-slug=${args.issueSlug}`
      : `--press=${press}`;
    console.log(`dump ${label}`);
    addDump(
      await dbDump({
        issue: null,
        press,
        issueSlug: args.issueSlug,
      })
    );
  }

  return [...byId.values()].sort(
    (a, b) => (a.issue?.id ?? a.id) - (b.issue?.id ?? b.id)
  );
}

async function hydrateItem(item) {
  if (!item.lazy && item.articles && item.issue?.press_title) {
    return item;
  }
  const id = item.issue?.id ?? item.id;
  const dump = await dbDump({ issue: String(id) });
  const full = dumpItems(dump)[0];
  if (!full) {
    throw new Error(`issue #${id} not found`);
  }
  return full;
}

function dumpItems(dump) {
  if (!dump) return [];
  if (dump.batch) return dump.items || [];
  if (dump.issue) return [dump];
  return [];
}

function parseJsonResponse(raw) {
  let s = String(raw || "").trim();
  const fence = s.match(/```(?:json)?\s*([\s\S]*?)```/i);
  if (fence) s = fence[1].trim();
  const start = s.indexOf("{");
  const end = s.lastIndexOf("}");
  if (start >= 0 && end > start) s = s.slice(start, end + 1);
  let data;
  try {
    data = JSON.parse(s);
  } catch (err) {
    throw new Error(
      `Gemini вернул не-JSON: ${err.message}\n---\n${raw.slice(0, 800)}\n---`
    );
  }
  for (const k of [
    "description_ru",
    "description_en",
    "meta_description_ru",
    "meta_description_en",
  ]) {
    if (typeof data[k] !== "string" || !data[k].trim()) {
      throw new Error(`В ответе нет поля ${k}`);
    }
  }
  return data;
}

function normalizeResult(data, issue) {
  const clip = (t, max) => {
    let s = String(t).replace(/\s+/g, " ").trim();
    if (max > 0 && [...s].length > max) {
      s = [...s].slice(0, max).join("").replace(/[\s.,;:\-]+$/u, "");
    }
    return s;
  };
  let descRu = clip(data.description_ru, DESC_SOFT_MAX);
  let descEn = clip(data.description_en, DESC_SOFT_MAX);
  let metaRu = clip(data.meta_description_ru, META_MAX);
  let metaEn = clip(data.meta_description_en, META_MAX);

  if (!metaRu) {
    metaRu = clip(
      `«${issue.press_title}» #${issue.title}: ${descRu}`,
      META_MAX
    );
  }
  if (!metaEn) {
    metaEn = clip(
      `${issue.press_title} #${issue.title}: ${descEn}`,
      META_MAX
    );
  }

  return {
    description_ru: descRu,
    description_en: descEn,
    meta_description_ru: metaRu,
    meta_description_en: metaEn,
  };
}

function dbArgs(target) {
  const a = [];
  if (target.all) a.push("--all");
  if (target.issue) a.push(`--issue=${target.issue}`);
  if (target.press) a.push(`--press=${target.press}`);
  if (target.issueSlug) a.push(`--issue-slug=${target.issueSlug}`);
  return a;
}

async function dbDump(target) {
  const text = await runPhpDb(["dump", ...dbArgs(target)]);
  return JSON.parse(text);
}

function runPhpDb(phpArgs) {
  return new Promise((resolve, reject) => {
    const child = spawn(
      "docker",
      [
        "compose",
        "run",
        "--rm",
        "--no-deps",
        "-T",
        "--entrypoint",
        "php",
        "-v",
        `${ROOT}:/app`,
        "-w",
        "/app",
        "php",
        "/app/local/scripts/issue-description-db.php",
        ...phpArgs,
      ],
      {
        cwd: ROOT,
        env: process.env,
        stdio: ["ignore", "pipe", "pipe"],
      }
    );

    let stdout = "";
    let stderr = "";
    child.stdout.on("data", (d) => {
      stdout += d.toString();
    });
    child.stderr.on("data", (d) => {
      const s = d.toString();
      stderr += s;
      process.stderr.write(s);
    });
    child.on("error", reject);
    child.on("close", (code) => {
      if (code !== 0) {
        reject(
          new Error(
            `issue-description-db.php exit ${code}\n${stderr || stdout}`
          )
        );
        return;
      }
      const lines = stdout.trim().split("\n").filter(Boolean);
      resolve(lines[lines.length - 1] || "{}");
    });
  });
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
