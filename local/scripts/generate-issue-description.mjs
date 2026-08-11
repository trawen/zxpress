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
 *   node local/scripts/generate-issue-description.mjs --issue=123
 *   node local/scripts/generate-issue-description.mjs --press=spectrofon --issue-slug=01
 *
 * Опции:
 *   --issue=ID
 *   --press=SLUG --issue-slug=SLUG
 *   --out=PATH           JSON (по умолчанию local/work/issue-desc-{id}.json)
 *   --cdp                подключиться к Chrome :9222 (по умолчанию)
 *   --no-cdp             свой Chrome + профиль .chrome-profile-issue-desc
 *   --cdp-url=URL
 *   --markdown=PATH      путь к zxpress-markdown (playwright + providers.mjs)
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
1. description_ru — 3–5 связных предложений по-русски: о чём выпуск, без списка «статья1, статья2».
2. description_en — тот же смысл по-английски (перевод, не дословный калька).
3. meta_description_ru — SEO-текст до ${META_MAX} символов (можно начать с названия издания и номера).
4. meta_description_en — то же по-английски, до ${META_MAX} символов.
5. Не выдумывай факты, которых нет во входных meta.
6. Не упоминай ИИ, промпт, «на основе описаний».
7. Верни ТОЛЬКО JSON-объект без markdown-обёртки:
{"description_ru":"...","description_en":"...","meta_description_ru":"...","meta_description_en":"..."}`;

const args = parseArgs(process.argv.slice(2));

async function main() {
  if (!args.issue && !(args.press && args.issueSlug)) {
    console.error(
      "Нужно --issue=ID или --press=SLUG --issue-slug=SLUG\n" +
        "См. --help"
    );
    process.exit(1);
  }

  const dump = await dbDump(args);
  const issue = dump.issue;
  const articles = dump.articles || [];

  console.log(
    `Выпуск #${issue.id} «${issue.press_title}» ${issue.title} — статей с meta: ${articles.length}`
  );

  if (articles.length === 0) {
    console.error("Нет статей с meta_description_ru/en — сначала импортируйте meta статей.");
    process.exit(1);
  }

  const articleBlock = articles
    .map((a, i) => {
      const meta = a.meta_description_ru || a.meta_description_en || "";
      const title = a.title ? ` [${a.title}]` : "";
      return `${i + 1}. id=${a.id}${title}\n   ${meta}`;
    })
    .join("\n");

  const userMessage =
    `${PROMPT}\n\n` +
    `Издание: ${issue.press_title}\n` +
    `Выпуск: ${issue.title}` +
    (issue.date ? ` (${issue.date})` : "") +
    `\n\n--- META DESCRIPTION СТАТЕЙ ---\n${articleBlock}\n--- КОНЕЦ ---`;

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

  console.log("Gemini: генерирую описание выпуска…");
  const raw = await geminiChat(session.page, provider, userMessage, {
    sourceLen: userMessage.length,
  });

  if (session.owned) {
    await session.context.close().catch(() => {});
  }

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

  console.log("\n=== result ===");
  console.log(JSON.stringify(result, null, 2));

  const outRel =
    args.out || `local/work/issue-desc-${issue.id}.json`;
  const outPath = path.isAbsolute(outRel)
    ? outRel
    : path.join(ROOT, outRel);
  await fs.mkdir(path.dirname(outPath), { recursive: true });
  await fs.writeFile(outPath, JSON.stringify(result, null, 2) + "\n", "utf8");
  console.log(`saved: ${outPath}`);
}

function parseArgs(argv) {
  const out = {
    issue: null,
    press: null,
    issueSlug: null,
    cdp: true,
    cdpUrl: process.env.CDP_URL || "http://127.0.0.1:9222",
    markdown: resolveMarkdownRoot(),
    out: null,
  };
  for (const a of argv) {
    if (a === "--help" || a === "-h") {
      console.log(`Usage: node local/scripts/generate-issue-description.mjs --issue=ID [--out=PATH] [--cdp]
  --press=SLUG --issue-slug=SLUG
  --no-cdp  --cdp-url=URL  --markdown=PATH
  По умолчанию пишет local/work/issue-desc-{id}.json (БД не трогает)`);
      process.exit(0);
    } else if (a === "--cdp") out.cdp = true;
    else if (a === "--no-cdp") out.cdp = false;
    else if (a.startsWith("--issue=")) out.issue = a.slice(8);
    else if (a.startsWith("--press=")) out.press = a.slice(8);
    else if (a.startsWith("--issue-slug=")) out.issueSlug = a.slice(13);
    else if (a.startsWith("--cdp-url=")) out.cdpUrl = a.slice(10);
    else if (a.startsWith("--markdown=")) out.markdown = a.slice(11);
    else if (a.startsWith("--out=")) out.out = a.slice(6);
  }
  return out;
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

  // если meta пустой после клипа — собрать короткий fallback
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

function dbArgs(args) {
  const a = [];
  if (args.issue) a.push(`--issue=${args.issue}`);
  if (args.press) a.push(`--press=${args.press}`);
  if (args.issueSlug) a.push(`--issue-slug=${args.issueSlug}`);
  return a;
}

async function dbDump(args) {
  const text = await runPhpDb(["dump", ...dbArgs(args)]);
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
