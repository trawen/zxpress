/**
 * Slim Gemini chat client via Playwright CDP.
 * Patterns mirrored from zxpress-markdown/translate.mjs + providers.mjs.
 */
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath, pathToFileURL } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DEFAULT_MARKDOWN_ROOT = path.resolve(
  __dirname,
  "../../../../zxpress-markdown"
);

export function resolveMarkdownRoot(explicit) {
  return (
    explicit ||
    process.env.ZXPRESS_MARKDOWN ||
    DEFAULT_MARKDOWN_ROOT
  );
}

export async function loadPlaywrightAndProvider(markdownRoot) {
  const root = resolveMarkdownRoot(markdownRoot);
  const require = createRequire(path.join(root, "package.json"));
  let chromium;
  try {
    ({ chromium } = require("playwright"));
  } catch (err) {
    throw new Error(
      `playwright не найден в ${root}. Сделайте: cd "${root}" && npm install\n` +
        `(или задайте ZXPRESS_MARKDOWN). ${err.message}`
    );
  }
  const { resolveProvider } = await import(
    pathToFileURL(path.join(root, "providers.mjs")).href
  );
  return { chromium, provider: resolveProvider("gemini"), markdownRoot: root };
}

export async function connectGeminiCdp({
  chromium,
  provider,
  cdpUrl = "http://127.0.0.1:9222",
} = {}) {
  let lastErr;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const browser = await chromium.connectOverCDP(cdpUrl, { timeout: 30_000 });
      const contexts = browser.contexts();
      if (!contexts.length) {
        throw new Error(
          "CDP: нет browser context. Запустите Chrome с --remote-debugging-port=9222"
        );
      }
      await sleep(300);
      const page = await findExistingProviderPage(contexts, provider);
      if (!page) {
        const listed = contexts
          .flatMap((c) => c.pages())
          .map((p) => p.url() || "(пустая)")
          .slice(0, 12);
        throw new Error(
          `CDP: вкладка Gemini не найдена. Откройте ${provider.url} и перезапустите.\n` +
            `Сейчас видно: ${listed.join(" | ") || "(нет вкладок)"}`
        );
      }
      console.log(`CDP: ${page.url()}`);
      return { browser, context: page.context(), page, owned: false };
    } catch (err) {
      lastErr = err;
      console.warn(`CDP попытка ${attempt}/3: ${err.message}`);
      await sleep(1000 * attempt);
    }
  }
  throw new Error(
    `Не удалось подключиться к Chrome через CDP (${cdpUrl}).\n` +
      `  /Applications/Google\\ Chrome.app/Contents/MacOS/Google\\ Chrome \\\n` +
      `    --remote-debugging-port=9222 --user-data-dir="$HOME/chrome-gemini-debug"\n` +
      `Ошибка: ${lastErr?.message || lastErr}`
  );
}

export async function openGeminiPersistent({
  chromium,
  provider,
  profileDir,
} = {}) {
  const context = await chromium.launchPersistentContext(profileDir, {
    channel: "chrome",
    headless: false,
    viewport: null,
    args: [
      "--disable-blink-features=AutomationControlled",
      "--start-maximized",
    ],
    ignoreDefaultArgs: ["--enable-automation"],
  });
  const page = context.pages()[0] || (await context.newPage());
  if (!provider.urlMatch.test(page.url())) {
    await page.goto(provider.url, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
  }
  await waitForChatReady(page, provider);
  await waitUntilLoggedIn(page, provider);
  return { context, page, owned: true };
}

export async function geminiChat(page, provider, message, { sourceLen = 0 } = {}) {
  await startNewChat(page, provider);
  if (await pageHasProviderError(page, provider)) {
    throw new Error("Gemini: ошибка на странице");
  }

  const input = await findInput(page, provider);
  if (!input) throw new Error("Не найдено поле ввода Gemini");

  await input.click({ timeout: 30_000 });
  await pasteIntoInput(page, provider, message);

  const insertedLen = await page.evaluate((sels) => {
    for (const sel of sels) {
      const el = document.querySelector(sel);
      if (!el) continue;
      const t = (el.innerText || el.value || "").trim();
      if (t.length > 0) return t.length;
    }
    return 0;
  }, provider.inputSelectors);

  if (insertedLen < 20) {
    throw new Error(`Gemini: текст не вставился (len=${insertedLen})`);
  }
  console.log(`  ↳ вставлено ${insertedLen} симв.`);

  await page.waitForTimeout(400);
  const beforeCount = await countModelResponses(page, provider);
  const net = attachStreamWatcher(page, provider);

  try {
    await clickSend(page, provider, input);
    await waitForResponseComplete(page, provider, beforeCount, net, sourceLen);
  } finally {
    net.dispose();
  }

  if (await pageHasProviderError(page, provider)) {
    throw new Error("Gemini: ошибка после отправки");
  }

  const text = await extractLastResponse(page, provider);
  if (!text || text.trim().length < 20) {
    throw new Error("Пустой или слишком короткий ответ Gemini");
  }
  return stripPreamble(text.trim());
}

async function findExistingProviderPage(contexts, provider) {
  const pages = [];
  for (const context of contexts) {
    for (const p of context.pages()) {
      const u = p.url();
      if (!u || u.startsWith("chrome://") || u.startsWith("devtools://")) continue;
      pages.push(p);
    }
  }
  const matched = pages.find((p) => provider.urlMatch.test(p.url()));
  if (matched) return matched;
  await sleep(500);
  for (const context of contexts) {
    for (const p of context.pages()) {
      const u = p.url();
      if (u && provider.urlMatch.test(u)) return p;
    }
  }
  return null;
}

async function waitForChatReady(page, provider) {
  await page.waitForSelector(provider.readySelectors.join(", "), {
    timeout: 120_000,
  });
}

async function waitUntilLoggedIn(page, provider) {
  for (;;) {
    const url = page.url();
    if (provider.loginMatch.test(url)) {
      console.log("Ожидаю вход в Gemini...");
      await page.waitForURL((u) => provider.urlMatch.test(u.href), {
        timeout: 0,
      });
      await waitForChatReady(page, provider);
      return;
    }
    if (await findInput(page, provider)) return;
    await page.waitForTimeout(1000);
  }
}

async function findInput(page, provider) {
  for (const sel of provider.inputSelectors) {
    const loc = page.locator(sel).last();
    if ((await loc.count()) > 0 && (await loc.isVisible().catch(() => false))) {
      return loc;
    }
  }
  return null;
}

async function findSendButton(page, provider) {
  for (const sel of provider.sendSelectors) {
    const loc = page.locator(sel).last();
    if ((await loc.count()) > 0 && (await loc.isEnabled().catch(() => false))) {
      return loc;
    }
  }
  return null;
}

async function clickSend(page, provider, input) {
  const deadline = Date.now() + 15_000;
  let send = null;
  while (Date.now() < deadline) {
    send = await findSendButton(page, provider);
    if (send) break;
    await page.waitForTimeout(300);
  }
  if (send) {
    await send.click();
    return;
  }
  await input.press("Enter");
}

async function pasteIntoInput(page, provider, text) {
  const selectors = provider.inputSelectors;
  await page.evaluate(
    async ({ text, selectors }) => {
      let el = null;
      for (const sel of selectors) {
        const found = document.querySelector(sel);
        if (found) {
          el = found;
          break;
        }
      }
      if (!el) {
        el =
          [
            ...document.querySelectorAll(
              '[contenteditable="true"], textarea, [role="textbox"]'
            ),
          ]
            .reverse()
            .find((n) => n.offsetParent !== null) || null;
      }
      if (!el) throw new Error("input element missing");
      el.focus();
      if (el.tagName === "TEXTAREA" || el.tagName === "INPUT") {
        el.value = "";
        el.value = text;
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
        return;
      }
      document.execCommand("selectAll", false);
      document.execCommand("delete", false);
      const dt = new DataTransfer();
      dt.setData("text/plain", text);
      el.dispatchEvent(
        new ClipboardEvent("paste", {
          clipboardData: dt,
          bubbles: true,
          cancelable: true,
        })
      );
      if (!el.innerText || el.innerText.trim().length < 10) {
        el.textContent = text;
        el.dispatchEvent(new InputEvent("input", { bubbles: true }));
      }
    },
    { text, selectors }
  );
}

async function startNewChat(page, provider) {
  for (const sel of provider.newChatSelectors) {
    const loc = page.locator(sel).first();
    if ((await loc.count()) > 0 && (await loc.isVisible().catch(() => false))) {
      await loc.click().catch(() => {});
      await page.waitForTimeout(800);
      await waitForChatReady(page, provider);
      return;
    }
  }
  await page.goto(provider.url, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await waitForChatReady(page, provider);
}

async function countModelResponses(page, provider) {
  return page.evaluate((sels) => {
    return document.querySelectorAll(sels.join(",")).length;
  }, provider.responseSelectors);
}

async function lastResponseLen(page, provider) {
  const responseSel = provider.responseSelectors.join(", ");
  return page.evaluate((responseSel) => {
    const nodes = [...document.querySelectorAll(responseSel)];
    const last = nodes[nodes.length - 1];
    return last ? (last.innerText || "").length : 0;
  }, responseSel);
}

async function isStopVisible(page, provider) {
  const stopJoined = provider.stopSelectors.join(", ");
  if (!stopJoined) return false;
  return page
    .locator(stopJoined)
    .first()
    .isVisible()
    .catch(() => false);
}

async function pageHasProviderError(page, provider) {
  const patterns = provider.errorPatterns.map((re) => re.source);
  return page.evaluate((srcs) => {
    const t = document.body?.innerText || "";
    return srcs.some((s) => new RegExp(s, "i").test(t));
  }, patterns);
}

function attachStreamWatcher(page, provider) {
  const patterns = provider.networkPatterns || [];
  const pending = new Set();
  let started = 0;
  let finished = 0;
  let lastActivity = 0;
  const matches = (url) => patterns.some((re) => re.test(url));

  const onRequest = (req) => {
    try {
      const url = req.url();
      if (!matches(url)) return;
      if (
        req.method() === "GET" &&
        !/StreamGenerate|completion|conversation/i.test(url)
      ) {
        return;
      }
      pending.add(req);
      started += 1;
      lastActivity = Date.now();
    } catch {
      /* ignore */
    }
  };
  const onDone = (req) => {
    if (!pending.has(req)) return;
    pending.delete(req);
    finished += 1;
    lastActivity = Date.now();
  };

  page.on("request", onRequest);
  page.on("requestfinished", onDone);
  page.on("requestfailed", onDone);

  return {
    get started() {
      return started;
    },
    get finished() {
      return finished;
    },
    async waitUntilIdle({
      timeoutMs = 180_000,
      quietMs = 1200,
      startTimeoutMs = 45_000,
    } = {}) {
      if (!patterns.length) return false;
      const t0 = Date.now();
      while (Date.now() - t0 < startTimeoutMs) {
        if (await pageHasProviderError(page, provider)) {
          throw new Error("Gemini: ошибка во время генерации");
        }
        if (started > 0) break;
        await page.waitForTimeout(150);
      }
      if (started === 0) return false;
      while (Date.now() - t0 < timeoutMs) {
        if (await pageHasProviderError(page, provider)) {
          throw new Error("Gemini: ошибка во время генерации");
        }
        const idle =
          pending.size === 0 &&
          finished > 0 &&
          Date.now() - lastActivity >= quietMs;
        if (idle) return true;
        await page.waitForTimeout(200);
      }
      return pending.size === 0 && finished > 0;
    },
    dispose() {
      page.off("request", onRequest);
      page.off("requestfinished", onDone);
      page.off("requestfailed", onDone);
      pending.clear();
    },
  };
}

async function waitForResponseComplete(
  page,
  provider,
  beforeCount,
  net,
  sourceLen
) {
  const len = Math.max(0, Number(sourceLen) || 0);
  const quietMs = Math.min(8_000, Math.max(1_500, 1_200 + Math.floor(len / 8)));
  const timeoutMs = Math.min(300_000, Math.max(90_000, 60_000 + Math.floor(len * 8)));
  const stableNeed = len >= 5_000 ? 5 : 3;
  const baselineLen = await lastResponseLen(page, provider);
  let netIdleAt = 0;

  if (net) {
    const ok = await net.waitUntilIdle({
      timeoutMs,
      quietMs,
      startTimeoutMs: 45_000,
    });
    if (ok) {
      netIdleAt = Date.now();
      console.log(
        `  ↳ стрим idle (reqs=${net.started}) — жду DOM`
      );
      await page.waitForTimeout(800);
    }
  }

  const appearDeadline =
    Date.now() + (netIdleAt ? 60_000 : Math.min(timeoutMs, 120_000));
  let appeared = false;
  while (Date.now() < appearDeadline) {
    if (await pageHasProviderError(page, provider)) {
      throw new Error("Gemini: ошибка во время генерации");
    }
    const count = await countModelResponses(page, provider);
    const respLen = await lastResponseLen(page, provider);
    const stopping = await isStopVisible(page, provider);
    if (
      count > beforeCount ||
      respLen > baselineLen + 40 ||
      (stopping && respLen > 0)
    ) {
      appeared = true;
      break;
    }
    await page.waitForTimeout(500);
  }
  if (!appeared) {
    throw new Error("Таймаут появления ответа Gemini");
  }

  const deadline = Date.now() + timeoutMs;
  let stable = 0;
  let lastLen = -1;
  while (Date.now() < deadline) {
    if (await pageHasProviderError(page, provider)) {
      throw new Error("Gemini: ошибка во время генерации");
    }
    const stopping = await isStopVisible(page, provider);
    const respLen = await lastResponseLen(page, provider);
    if (stopping) {
      stable = 0;
      lastLen = respLen;
      await page.waitForTimeout(500);
      continue;
    }
    if (respLen > 0 && respLen === lastLen) {
      stable += 1;
      if (stable >= stableNeed) {
        console.log(`  ↳ ответ готов (${respLen} chars)`);
        return;
      }
    } else {
      stable = 0;
    }
    lastLen = respLen;
    await page.waitForTimeout(500);
  }
  throw new Error("Таймаут ожидания ответа Gemini");
}

async function extractLastResponse(page, provider) {
  const copyBtns = page.locator(provider.copySelectors.join(", "));
  const count = await copyBtns.count();
  if (count > 0) {
    try {
      await page
        .context()
        .grantPermissions(["clipboard-read", "clipboard-write"]);
    } catch {
      /* ok */
    }
    await copyBtns.last().click().catch(() => null);
    await page.waitForTimeout(400);
    const text = await page.evaluate(async () => {
      try {
        return await navigator.clipboard.readText();
      } catch {
        return "";
      }
    });
    if (text && text.trim().length > 20) return text;
  }

  return page.evaluate((sels) => {
    const pickText = (el) => (el?.innerText || el?.textContent || "").trim();
    const candidates = [...document.querySelectorAll(sels.join(","))];
    if (candidates.length === 0) return "";
    return pickText(candidates[candidates.length - 1]);
  }, provider.responseSelectors);
}

function stripPreamble(text) {
  let s = text.replace(/^\uFEFF/, "").trim();
  // unwrap ```json ... ```
  const fence = s.match(/^```(?:json|JSON)?\s*\n?([\s\S]*?)\n?```\s*$/);
  if (fence) s = fence[1].trim();
  return s;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}
