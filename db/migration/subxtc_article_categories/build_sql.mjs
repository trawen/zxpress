#!/usr/bin/env node
/** Regenerate SQL from generated/assignments.json (after manual fixes). */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "generated");
const PRESS_ID = 213;

function sqlQuote(s) {
  return `'${String(s).replace(/\\/g, "\\\\").replace(/'/g, "''")}'`;
}

const assignments = JSON.parse(
  fs.readFileSync(path.join(OUT, "assignments.json"), "utf8")
);
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
    `-- SE#${a.issue} #${a.number} ${a.stem} → ${a.category_id} ${a.category_name_ru}`
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
  lines.push(`  AND i.title = ${sqlQuote(a.issue)}`);
  lines.push(`  AND a.number = ${a.number};`);
  lines.push("");
}
for (const cid of [...catIds].sort((x, y) => x - y)) {
  lines.push(
    `UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=${cid}) WHERE id=${cid} LIMIT 1;`
  );
}
fs.writeFileSync(
  path.join(OUT, "01_insert_ezine_article_categories.sql"),
  `${lines.join("\n")}\n`
);
const summary = {
  generated_at: new Date().toISOString(),
  press_id: PRESS_ID,
  articles: assignments.length,
  unique_categories: catIds.size,
};
fs.writeFileSync(
  path.join(OUT, "summary.json"),
  `${JSON.stringify(summary, null, 2)}\n`
);
console.log(`Wrote SQL for ${assignments.length} articles, ${catIds.size} categories`);
