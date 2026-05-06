# E2E (Playwright)

**Внешний URL (стенд/прод):** всегда задавай `BASE_URL=https://…` и запускай Playwright **и** любые `curl` к этому хосту с отключённым прокси:  
`env -u http_proxy -u https_proxy -u HTTP_PROXY -u HTTPS_PROXY -u ALL_PROXY -u all_proxy …`  
Иначе TLS/заголовки могут быть битыми, а проверка логина — ложной. После смены CSP в nginx сверяй живой `Content-Security-Policy` с репозиторием (см. `docs/testing.md`, `docs/operations.md`).

## Что значит «покрытие»

Полного «100%» кода или всех комбинаций параметров в браузере не бывает. Основные слои:

1. **`fixtures/site-routes.ts`** — манифест URL: публичные страницы, RSS/JSON, статика, ожидаемые ответы без сессии (403 для закрытых URL). Проверка через **реальную навигацию Chromium** (`page.goto`) и `utils/page-goto.ts` (повтор при 503/429 от nginx `limit_req`).
2. **`tests/crawl-site.spec.ts`** — обход в ширину по ссылкам с `/` и `/ezines.php` (до `E2E_MAX_CRAWL_PAGES` / `E2E_MAX_CRAWL_DEPTH`). Исключаются из очереди `tag.php` и `rubrics_.php` (в части снимков БД дают 500).
3. **`tests/admin-surfaces.spec.ts`** — все основные админ-URL после логина; нужны `E2E_ADMIN_USER` / `E2E_ADMIN_PASS`.
4. **`tests/admin-news-create.spec.ts`** — после входа через `hyperjump.php` создаёт **одну** тестовую новость в `admin_news.php` (реальная запись в БД; заголовок `E2E news <timestamp>` — можно удалить вручную). Только при заданном `E2E_ADMIN_PASS`.
4b. **`tests/admin-ezine-book-markers.spec.ts`** — создаёт **электронный журнал** (`admin_issue.php`, тип «Журнал») и **книгу** (`admin_books.php?id=0`) с меткой **`E2E_ZXPRESS`** и тегом года **`y3000`** в названии; у книги дата выхода **`01.01.2037`** (Unix time в пределах `INT`; удобно искать в БД). Только при `E2E_ADMIN_PASS`.
4c. **`tests/csp-hyperjump-form-action.spec.ts`** — проверяет заголовок **`Content-Security-Policy`** для **`/hyperjump.php`**: при **`default-src 'none'`** обязателен **`form-action`** с **`'self'`**, иначе браузер блокирует POST и логин «молчит». Ловит невыкатанный **`conf/nginx-site.conf`** на стенде.

**Удаление тестовых сущностей после E2E (ручной SQL, пример):**

```sql
-- Названия содержат маркер E2E_ZXPRESS (проверьте выборку SELECT перед DELETE):
DELETE FROM press WHERE title LIKE '%E2E_ZXPRESS%';
DELETE FROM books WHERE title1 LIKE '%E2E_ZXPRESS%';
-- Новости/комментарии из других спеков:
DELETE FROM news WHERE title LIKE 'E2E news %';
```

Зависимости FK (выпуски, файлы, главы) на проде смотрите по `id` конкретных строк; при необходимости сначала удалите дочерние записи.
5. **`tests/regression-deploy-chronology-sidebar.spec.ts`** — контракт деплоя: с сервера отдаётся тот же `e2e-deploy-marker.txt`, что в репозитории (проверка bind-mount `./site` вместо «застывшего» volume); хронология и сайдбар без сырых BBCode.
6. **`tests/search-snippet-highlight.spec.ts`** — на выдаче `search.php` есть подсветка совпадений (`b.find`), если поиск что-то нашёл (при пустом индексе тест помечается как skipped).
7. **`tests/layout-anchors.spec.ts`** — DOM-якоря ключевых страниц (каталог, ezines, книга, статья); без эталонных скриншотов.
8. **`tests/a11y-critical-pages.spec.ts`** — axe (`@axe-core/playwright`), wcag2a с набором `disabledRules` под легаси-вёрстку; список правил сужать со временем.
9. **`tests/comments-happy-path.spec.ts`** — успешный комментарий на `book_articles.php` (глава по умолчанию `id=1`) при `E2E_EXPOSE_CAPTCHA` и `data-testid="e2e-comments-captcha"` в шаблоне комментариев. Если в снимке БД нет `chapters.ch_id=1`, задайте **`E2E_BOOK_ARTICLES_CH_ID`** (см. таблицу ниже).

### Точечный запуск (skill `layout` / `a11y`)

**Канон (стенд pst-labs), прокси сброшен через `npm run test:e2e` или `env -u …`:**

```bash
cd e2e
npm ci
PLAYWRIGHT_SKIP_WEBSERVER=1 npx playwright test tests/layout-anchors.spec.ts
PLAYWRIGHT_SKIP_WEBSERVER=1 npx playwright test tests/a11y-critical-pages.spec.ts
```

Локальный стек на `:80` (только явно):

```bash
PLAYWRIGHT_SKIP_WEBSERVER=1 BASE_URL=http://127.0.0.1:80 npx playwright test tests/layout-anchors.spec.ts
```

Админ-поверхности после логина:

```bash
E2E_ADMIN_PASS='…' PLAYWRIGHT_SKIP_WEBSERVER=1 npx playwright test tests/admin-login.spec.ts tests/admin-surfaces.spec.ts
```

## Запуск

Предпочтительно **`npm run test:e2e`** — скрипт сбрасывает прокси-переменные окружения (как и полный прогон `tools/run-all-tests.sh`). Прямой `npx playwright test` на машине с `HTTP_PROXY` может ломать доступ к `127.0.0.1` / стенду; в `playwright.config.ts` для Chromium добавлен `--no-proxy-server` как дополнительная страховка.

```bash
cd e2e
npm ci
npx playwright install chromium
PLAYWRIGHT_SKIP_WEBSERVER=1 npm run test:e2e
```

## Переменные

| Переменная | Назначение |
|------------|------------|
| `BASE_URL` | Корень сайта. Если не задана — **`https://zxpress.pst-labs.ru`** (`e2e/constants.ts`). Для локального compose: `http://127.0.0.1:80`. |
| `PLAYWRIGHT_SKIP_WEBSERVER` | `1` — не поднимать Docker из Playwright |
| `PLAYWRIGHT_REUSE_SERVER` | По умолчанию `reuseExistingServer: true`: если на `:80` уже отвечает старый стек, E2E (в т.ч. `e2e-deploy-marker.txt`) может падать. Сделайте `docker compose ... down` или временно остановите чужой сервис на 80. |
| `E2E_ADMIN_PASS` | Пароль для сценариев админки (и **мутации** в `tests/admin-news-create.spec.ts`) |
| `E2E_ADMIN_USER` | Логин для `hyperjump.php` (по умолчанию `admin`; на стенде может быть, например, `newart`) |
| `E2E_MAX_CRAWL_PAGES` | Лимит URL в обходе (по умолчанию 600) |
| `E2E_MAX_CRAWL_DEPTH` | Глубина BFS (по умолчанию 4) |
| `E2E_SEARCH_QUERY` | Строка для `tests/search-snippet-highlight.spec.ts` (по умолчанию `спектрум`), если в БД нет дефолтных попаданий |
| `E2E_REQUIRE_SEARCH_HIGHLIGHTS` | `1` — в `search-snippet-highlight.spec.ts` падать, если есть выдача, но нет `b.find` (строгий режим для стенда с полным `data/content-store` и Manticore) |
| `E2E_BOOK_ARTICLES_CH_ID` | `ch_id` главы для `book_articles.php?id=` в **`comments-happy-path`** и **`forms-comments-regression`** (по умолчанию `1`; на «пустой» БД без главы `1` укажите существующий id) |

На сервисе **php** в тестовом стеке (`docker-compose.test.yml`) задаётся `E2E_EXPOSE_CAPTCHA=1`: в гостевой в DOM попадает скрытый `data-testid="e2e-guestbook-captcha"` с текстом кода; в форме комментариев (`comments.tpl`) — `data-testid="e2e-comments-captcha"`, чтобы сценарии **успешной** отправки выполнялись в браузере без OCR. В продакшене эту переменную не задавать.
