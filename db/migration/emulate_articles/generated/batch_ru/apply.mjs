#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../../../..");
const BATCH = __dirname;
const SRC = path.join(ROOT, "site/cli/imports/emulate_i18n");
const MIRROR = path.join(ROOT, "db/migration/emulate_articles/i18n");

const meta = JSON.parse(fs.readFileSync(path.join(BATCH, "meta.json"), "utf8"));

for (const file of Object.keys(meta)) {
  const stem = file.replace(/\.json$/, "");
  const bodyPath = path.join(BATCH, `${stem}.body.txt`);
  if (!fs.existsSync(bodyPath)) {
    console.error(`missing body: ${stem}`);
    process.exit(1);
  }
  let body = fs.readFileSync(bodyPath, "utf8");
  if (!body.startsWith("\r\n") && body.startsWith("\n")) {
    body = "\r\n" + body.slice(1);
  } else if (!body.startsWith("\r\n")) {
    body = "\r\n" + body;
  }
  body = body.replace(/\r?\n/g, "\r\n");
  if (!body.endsWith("\r\n")) body += "\r\n";

  const srcPath = path.join(SRC, file);
  const data = JSON.parse(fs.readFileSync(srcPath, "utf8"));
  Object.assign(data, meta[file], { body_ru: body });
  const out = JSON.stringify(data, null, 2) + "\n";
  fs.writeFileSync(srcPath, out, "utf8");
  fs.writeFileSync(path.join(MIRROR, file), out, "utf8");
  console.log(`OK ${file} (${body.length} chars body_ru)`);
}
