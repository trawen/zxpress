/**
 * Canonical smoke list for zxpress.ru public pages.
 * Includes legacy *.php entry points and pretty /ru|/en|/eng paths.
 * Partials that are not meant to be opened directly (e.g. comments.php without init) are omitted.
 * Admin and hidden tools expect HTTP 403 without a session.
 */

export type RouteExpectation = {
  path: string;
  /** Inclusive; default 200–399 for okHtml */
  statusMin?: number;
  statusMax?: number;
  /** If true, only check status (no HTML fatal check — e.g. JSON, RSS XML) */
  textOnly?: boolean;
  /** Response triggers attachment download — Chromium does not render DOM; use waitForEvent('download'). */
  expectDownload?: boolean;
  note?: string;
};

/** Legacy PHP entry points that must return HTML without PHP fatals (typical 200). */
export const publicHtmlRoutesLegacy: RouteExpectation[] = [
  { path: '/' },
  { path: '/ezines.php' },
  { path: '/ezines.php?lng=eng' },
  { path: '/news.php' },
  { path: '/news/p1/' },
  { path: '/search.php' },
  { path: '/books.php' },
  { path: '/catalog.php' },
  { path: '/gallery.php' },
  { path: '/map.php' },
  { path: '/chronology.php' },
  { path: '/updates.php' },
  { path: '/stats.php' },
  { path: '/guestbook.php' },
  { path: '/zxnet.php' },
  { path: '/zxnet/' },
  { path: '/menu.php' },
  { path: '/menu/1/' },
  { path: '/rubrics.php' },
  { path: '/articles_list.php' },
  { path: '/wanted.php' },
  { path: '/whois.php' },
  { path: '/periodicals.php' },
  { path: '/authors.php' },
  { path: '/categories.php' },
  { path: '/snailmail.php' },
  { path: '/pure-text.php', expectDownload: true },
  /** /d.php без параметров ведёт себя по-разному (404 или attachment в зависимости от БД) — см. smoke-all-routes «d.php». */
  { path: '/print.php' },
  { path: '/issue.php?id=1' },
  { path: '/book.php?id=1' },
  { path: '/book_articles.php?id=1' },
  { path: '/article.php?id=1' },
  { path: '/echos_subjs.php?id=1' },
  { path: '/hyperjump.php' },
];

/** Canonical pretty URLs (new UI) — must open without 5xx / PHP fatals. */
export const publicHtmlRoutesPretty: RouteExpectation[] = [
  { path: '/ru' },
  { path: '/en' },
  { path: '/ru/ezines' },
  { path: '/en/ezines' },
  { path: '/ru/books' },
  { path: '/en/books' },
  { path: '/ru/periodicals' },
  { path: '/en/periodicals' },
  { path: '/ru/gallery' },
  { path: '/en/gallery' },
  { path: '/ru/guestbook' },
  { path: '/en/guestbook' },
  { path: '/ru/updates' },
  { path: '/en/updates' },
  { path: '/ru/search' },
  { path: '/en/search' },
  { path: '/ru/map' },
  { path: '/en/map' },
  { path: '/ru/zxnet' },
  { path: '/en/zxnet' },
  { path: '/ru/snailmail' },
  { path: '/en/snailmail' },
  { path: '/ru/authors' },
  { path: '/en/authors' },
  { path: '/ru/categories' },
  { path: '/en/categories' },
];

/** All public HTML routes for smoke (legacy + pretty). */
export const publicHtmlRoutes: RouteExpectation[] = [
  ...publicHtmlRoutesLegacy,
  ...publicHtmlRoutesPretty,
];

/** Non-HTML OK responses */
export const publicNonHtmlRoutes: RouteExpectation[] = [
  { path: '/rss.php', textOnly: true },
  { path: '/rss/news.rss', textOnly: true },
  { path: '/suggest.php?q=zx', textOnly: true },
];

/** Must be 403 for anonymous users */
export const forbiddenAnonymousRoutes: RouteExpectation[] = [
  /** Should be 403; if init outputs early, may be 500 — still not a public 200. */
  { path: '/hidden.php', statusMin: 400, statusMax: 599, textOnly: true },
  { path: '/confirm_code.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/add_tree.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/rubrics_set.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/admin_articles.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/admin_books.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/admin_books_light.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/admin_issue.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/admin_news.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/admin_news_upload.php', statusMin: 403, statusMax: 403, textOnly: true },
  { path: '/gallery_admin.php', statusMin: 403, statusMax: 403, textOnly: true },
];

/** Static / error pages served by nginx */
export const staticAssetRoutes: RouteExpectation[] = [
  { path: '/style.css', statusMin: 200, statusMax: 304, textOnly: true },
  { path: '/error/404.html', statusMin: 200, statusMax: 200, textOnly: true },
  { path: '/jsspeccy/index.html', statusMin: 200, statusMax: 200, textOnly: true },
];
