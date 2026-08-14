#!/usr/bin/env node
/**
 * Генерирует description / meta_description издания (press) из meta description
 * всех номеров через Gemini (Playwright CDP, как generate-issue-description.mjs).
 * Результат только в JSON-файл (БД не меняет).
 *
 * Подготовка Chrome:
 *   /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
 *     --remote-debugging-port=9222 --user-data-dir="$HOME/chrome-gemini-debug"
 *   # откройте https://gemini.google.com/app и войдите
 *
 * Запуск (из корня репо, Docker DB уже up — только чтение meta выпусков):
 *   node local/scripts/generate-press-description.mjs                 # все издания с meta номеров
 *   node local/scripts/generate-press-description.mjs --press=z80
 *   node local/scripts/generate-press-description.mjs --press=z80,on-line,nicron
 *   node local/scripts/generate-press-description.mjs --press=123
 *
 * Опции:
 *   (без --press)              все издания, у которых есть meta выпусков
 *   --press=ID|SLUG[,…]        можно повторять и через запятую
 *   --out=PATH                 одно издание → файл; batch → каталог
 *   --cdp / --no-cdp / --cdp-url=URL
 *   --markdown=PATH
 *   --delay=MS --delay-max=MS
 *   --force                    перезаписывать уже готовые JSON
 *   --max-issues=N             ограничить число meta в промпте (равномерная выборка)
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
const DESC_SOFT_MAX = 2000;

const PROMPT = `Ты редактор архива ZX Spectrum прессы (zxpress.ru).
По meta description выпусков одного электронного издания (газеты/журнала) напиши описание всего издания для страницы на сайте.

Правила:
1. description_ru — 5–10 связных предложений по-русски:
   - основные темы и устойчивые рубрики;
   - динамика: как менялась тематика и фокус от ранних номеров к поздним (если это видно из входа);
   - что ещё менялось со временем (тон, жанры материалов, интерес к играм/софту/железу/сцене/сообществу), если читается из meta.
   Не перечень «выпуск 1, выпуск 2».
2. description_en — тот же смысл по-английски (качественный перевод, не дословный калька).
3. meta_description_ru — SEO-текст до ${META_MAX} символов (можно начать с названия издания).
4. meta_description_en — то же по-английски, до ${META_MAX} символов.
5. Не трать предложения на «паспорт»: платформу («для ZX Spectrum»), жанр («электронная газета демосцены») и прочие факты, уже ясные из названия. Сразу о содержании и своеобразии издания.
6. Избегай штампов о читателях («читатели найдут», «вас ждёт»). Пиши обезличенно.
7. Не выдумывай факты, которых нет во входных meta.
8. Не упоминай ИИ, промпт, «на основе описаний».
9. Если номеров много — обобщай тренды и постоянные рубрики, не пытайся охватить каждый номер.
10. Верни ТОЛЬКО JSON-объект без markdown-обёртки:
{"description_ru":"...","description_en":"...","meta_description_ru":"...","meta_description_en":"..."}
11. Внутри строк JSON не используй неэкранированные двойные кавычки ". Для названий в EN бери одинарные '…' или «…»; если нужна ", пиши \\".`;

const args = parseArgs(process.argv.slice(2));

async function main() {
  const items = await loadAllItems(args);
  if (items.length === 0) {
    console.error("Нет изданий для обработки.");
    process.exit(1);
  }

  const isBatch = items.length > 1;
  console.log(`К обработке: ${items.length} издани(е/я)`);

  const pending = [];
  let skippedExisting = 0;
  for (const item of items) {
    const pressId = item.press?.id ?? item.id;
    const stubPress = item.press || { id: pressId };
    const outPath = resolveOutPath(args.out, stubPress, isBatch);
    if (!args.force && (await isDoneOutput(outPath))) {
      skippedExisting += 1;
      console.log(
        `skip #${pressId} (уже есть ${path.relative(ROOT, outPath)})`
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
    const profileDir = path.join(__dirname, ".chrome-profile-press-desc");
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
        `\n[${i + 1}/${pending.length}] dump FAIL #${item.press?.id ?? item.id}: ${err.message || err}`
      );
      continue;
    }
    const press = item.press;
    let issues = item.issues || [];
    const label = `[${i + 1}/${pending.length}] #${press.id} «${press.title}»`;

    console.log(`\n${label} — выпусков с meta: ${issues.length}`);

    if (issues.length === 0) {
      console.warn(`  skip: нет meta_description у выпусков`);
      skipped += 1;
      continue;
    }

    if (args.maxIssues > 0 && issues.length > args.maxIssues) {
      const before = issues.length;
      issues = sampleEvenly(issues, args.maxIssues);
      console.log(
        `  ↳ выборка ${issues.length} из ${before} (--max-issues=${args.maxIssues})`
      );
    }

    const issueBlock = issues
      .map((iss, idx) => {
        const meta =
          iss.meta_description_ru || iss.meta_description_en || "";
        const title = iss.title ? ` [${iss.title}]` : "";
        const date = iss.date ? ` (${iss.date})` : "";
        return `${idx + 1}. id=${iss.id}${title}${date}\n   ${meta}`;
      })
      .join("\n");

    const numbersHint =
      press.numbers > 0 ? `\nЗаявлено номеров в карточке: ${press.numbers}` : "";

    const userMessage =
      `${PROMPT}\n\n` +
      `Издание: ${press.title}` +
      (press.slug_ru ? ` (slug: ${press.slug_ru})` : "") +
      numbersHint +
      `\nВыпусков с meta во входе: ${issues.length}` +
      `\n\n--- META DESCRIPTION ВЫПУСКОВ ---\n${issueBlock}\n--- КОНЕЦ ---`;

    try {
      console.log("  Gemini: генерирую…");
      const raw = await geminiChat(session.page, provider, userMessage, {
        sourceLen: userMessage.length,
      });
      const parsed = parseJsonResponse(raw);
      const descriptions = normalizeResult(parsed, press);
      const result = {
        press_id: press.id,
        press_title: press.title,
        press_slug_ru: press.slug_ru || "",
        press_slug_en: press.slug_en || "",
        issues_used: issues.length,
        ...descriptions,
      };

      const outPath = resolveOutPath(args.out, press, isBatch);
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

function resolveOutPath(outArg, press, isBatch) {
  if (!outArg) {
    return path.join(ROOT, `local/work/press-desc-${press.id}.json`);
  }
  const abs = path.isAbsolute(outArg) ? outArg : path.join(ROOT, outArg);
  if (isBatch) {
    if (looksLikeDir(outArg)) {
      return path.join(abs, `press-desc-${press.id}.json`);
    }
    return path.join(ROOT, `local/work/press-desc-${press.id}.json`);
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

/** Равномерная выборка, всегда включая первый и последний. */
function sampleEvenly(items, max) {
  if (items.length <= max) return items;
  if (max <= 1) return [items[0]];
  if (max === 2) return [items[0], items[items.length - 1]];
  const out = [];
  const seen = new Set();
  for (let i = 0; i < max; i++) {
    const idx = Math.round((i * (items.length - 1)) / (max - 1));
    if (!seen.has(idx)) {
      seen.add(idx);
      out.push(items[idx]);
    }
  }
  return out;
}

function parseArgs(argv) {
  const out = {
    presses: [],
    cdp: true,
    cdpUrl: process.env.CDP_URL || "http://127.0.0.1:9222",
    markdown: resolveMarkdownRoot(),
    out: null,
    delay: 6000,
    delayMax: 18_750,
    force: false,
    maxIssues: 0,
    minIssues: 0,
  };
  for (const a of argv) {
    if (a === "--help" || a === "-h") {
      console.log(`Usage: node local/scripts/generate-press-description.mjs [--press=…] [--out=PATH] [--cdp]
  (без --press)                  все издания с meta выпусков
  --press=z80                    одно издание
  --press=z80,on-line,nicron     несколько (через запятую)
  --press=z80 --press=on-line    то же, повтором флага
  --press=123                    по numeric id
  --min-issues=N                 только издания с ≥N номеров (для --all)
  --max-issues=N                 ограничить meta в промпте (0 = все)
  --delay=MS --delay-max=MS      пауза между изданиями (6000–18750)
  --force                        перезаписать уже готовые JSON
  --no-cdp  --cdp-url=URL  --markdown=PATH
  По умолчанию пишет local/work/press-desc-{id}.json; готовые пропускает`);
      process.exit(0);
    } else if (a === "--cdp") out.cdp = true;
    else if (a === "--no-cdp") out.cdp = false;
    else if (a === "--force") out.force = true;
    else if (a.startsWith("--press=")) {
      out.presses.push(...splitList(a.slice(8)));
    } else if (a.startsWith("--cdp-url=")) out.cdpUrl = a.slice(10);
    else if (a.startsWith("--markdown=")) out.markdown = a.slice(11);
    else if (a.startsWith("--out=")) out.out = a.slice(6);
    else if (a.startsWith("--delay-max=")) out.delayMax = Number(a.slice(12));
    else if (a.startsWith("--delay=")) out.delay = Number(a.slice(8));
    else if (a.startsWith("--max-issues=")) out.maxIssues = Number(a.slice(13));
    else if (a.startsWith("--min-issues=")) out.minIssues = Number(a.slice(13));
  }
  if (!Number.isFinite(out.delay) || out.delay < 0) out.delay = 6000;
  if (!Number.isFinite(out.delayMax) || out.delayMax < 0) out.delayMax = 18_750;
  if (out.delayMax < out.delay) out.delayMax = out.delay;
  if (!Number.isFinite(out.maxIssues) || out.maxIssues < 0) out.maxIssues = 0;
  if (!Number.isFinite(out.minIssues) || out.minIssues < 0) out.minIssues = 0;
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
 * @returns {Promise<Array<{press: object, issues?: object[], lazy?: boolean, id?: number}>>}
 */
async function loadAllItems(args) {
  /** @type {Map<number, {press: object, issues?: object[], lazy?: boolean, id?: number}>} */
  const byId = new Map();

  const addDump = (dump) => {
    if (dump?.ids_only && Array.isArray(dump.ids)) {
      for (const id of dump.ids) {
        const n = Number(id);
        if (!byId.has(n)) {
          byId.set(n, { lazy: true, id: n, press: { id: n } });
        }
      }
      return;
    }
    for (const item of dumpItems(dump)) {
      byId.set(item.press.id, item);
    }
  };

  if (args.presses.length === 0) {
    const minLabel =
      args.minIssues > 0 ? ` --min-issues=${args.minIssues}` : "";
    console.log(`dump --all${minLabel} (издания с meta выпусков)`);
    addDump(await dbDump({ all: true, minIssues: args.minIssues }));
    return [...byId.values()].sort(
      (a, b) => (a.press?.id ?? a.id) - (b.press?.id ?? b.id)
    );
  }

  for (const pressSpec of args.presses) {
    console.log(`dump --press=${pressSpec}`);
    addDump(await dbDump({ press: pressSpec }));
  }

  return [...byId.values()].sort(
    (a, b) => (a.press?.id ?? a.id) - (b.press?.id ?? b.id)
  );
}

async function hydrateItem(item) {
  if (!item.lazy && item.issues && item.press?.title) {
    return item;
  }
  const id = item.press?.id ?? item.id;
  const dump = await dbDump({ press: String(id) });
  const full = dumpItems(dump)[0];
  if (!full) {
    throw new Error(`press #${id} not found`);
  }
  return full;
}

function dumpItems(dump) {
  if (!dump) return [];
  if (dump.batch) return dump.items || [];
  if (dump.press) return [dump];
  return [];
}

const RESULT_KEYS = [
  "description_ru",
  "description_en",
  "meta_description_ru",
  "meta_description_en",
];

function parseJsonResponse(raw) {
  let s = String(raw || "").trim();
  const fence = s.match(/```(?:json)?\s*([\s\S]*?)```/i);
  if (fence) s = fence[1].trim();
  const start = s.indexOf("{");
  const end = s.lastIndexOf("}");
  if (start >= 0 && end > start) s = s.slice(start, end + 1);

  let data;
  let parseErr;
  try {
    data = JSON.parse(s);
  } catch (err) {
    parseErr = err;
    data = extractJsonFieldsLoose(s);
    if (data) {
      console.warn(
        `  ↳ JSON починен (Gemini сломал кавычки): ${err.message}`
      );
    }
  }
  if (!data) {
    throw new Error(
      `Gemini вернул не-JSON: ${parseErr?.message || "unknown"}\n---\n${String(raw).slice(0, 800)}\n---`
    );
  }
  for (const k of RESULT_KEYS) {
    if (typeof data[k] !== "string" || !data[k].trim()) {
      throw new Error(`В ответе нет поля ${k}`);
    }
  }
  return data;
}

function extractJsonFieldsLoose(s) {
  const text = String(s || "");
  const out = {};
  for (let i = 0; i < RESULT_KEYS.length; i++) {
    const key = RESULT_KEYS[i];
    const keyRe = new RegExp(`"${key}"\\s*:\\s*"`, "i");
    const km = keyRe.exec(text);
    if (!km) return null;
    const valStart = km.index + km[0].length;
    let valEnd = -1;
    for (const nk of RESULT_KEYS.slice(i + 1)) {
      const delim = new RegExp(`"\\s*,\\s*"${nk}"`, "i");
      const dm = delim.exec(text.slice(valStart));
      if (dm) {
        valEnd = valStart + dm.index;
        break;
      }
    }
    if (valEnd < 0) {
      const close = /"\s*[,}]/.exec(text.slice(valStart));
      if (close) {
        valEnd = valStart + close.index;
      } else {
        let rest = text.slice(valStart).replace(/["\s,}]+$/g, "").trim();
        out[key] = unescapeJsonish(rest);
        continue;
      }
    }
    out[key] = unescapeJsonish(text.slice(valStart, valEnd));
  }
  return RESULT_KEYS.every((k) => out[k]?.trim()) ? out : null;
}

function unescapeJsonish(v) {
  return String(v)
    .replace(/\\n/g, "\n")
    .replace(/\\r/g, "\r")
    .replace(/\\t/g, "\t")
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, "\\")
    .trim();
}

function normalizeResult(data, press) {
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
    metaRu = clip(`«${press.title}»: ${descRu}`, META_MAX);
  }
  if (!metaEn) {
    metaEn = clip(`${press.title}: ${descEn}`, META_MAX);
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
  if (target.press) a.push(`--press=${target.press}`);
  if (target.minIssues > 0) a.push(`--min-issues=${target.minIssues}`);
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
        "/app/local/scripts/press-description-db.php",
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
            `press-description-db.php exit ${code}\n${stderr || stdout}`
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
