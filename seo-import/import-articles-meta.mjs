#!/usr/bin/env node
/**
 * Reads ../descriptions/*.txt, writes articles.meta_description_ru/en.
 *
 * Layout:
 *   zxpress-claude-descriptions/
 *     descriptions/12345.txt
 *     seo-import/import-articles-meta.mjs
 *     seo-import/.env
 */

import { readdir, readFile, stat } from 'node:fs/promises';
import { basename, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

const META_MAX_LEN = 512;
const ROOT = dirname(fileURLToPath(import.meta.url));

dotenv.config({ path: join(ROOT, '.env') });

const args = process.argv.slice(2);
if (args.includes('--help') || args.includes('-h')) {
	console.log('Usage: node import-articles-meta.mjs [--dry-run|--apply] [--dir=PATH]');
	process.exit(0);
}

const dryRun = !args.includes('--apply');
const dirArg = args.find((a) => a.startsWith('--dir='));
const descriptionsDir = dirArg ? dirArg.slice(6) : join(ROOT, '..', 'descriptions');

const dbHost = process.env.DB_HOST || '127.0.0.1';
const dbPort = Number(process.env.DB_PORT || 3306);
const dbUser = process.env.DB_USER || 'zxpress_u';
const dbPass = process.env.DB_PASS || '';
const dbName = process.env.DB_NAME || 'zxpress_db';

if (!dbPass) {
	console.error('[import] ERROR: set DB_PASS in .env');
	process.exit(1);
}

function logWarn(msg) {
	console.error(`[import] WARN ${msg}`);
}

function logInfo(msg) {
	console.error(`[import] INFO ${msg}`);
}

function extractArticleId(filename) {
	const stem = basename(filename).replace(/\.[^.]+$/, '');
	const m = stem.match(/^(\d+)/);
	return m ? Number(m[1]) : 0;
}

function extractSeoField(content, key) {
	const re = new RegExp(`^${key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*:\\s*(.*)$`, 'im');
	const m = content.match(re);
	if (!m) return null;
	const value = m[1].trim();
	return value === '' ? null : value;
}

function normalizeMeta(value) {
	let t = value.replace(/\[\/?[a-zA-Z*]+(?:=[^\]]*)?\]/g, '');
	t = t.replace(/<[^>]*>/g, '');
	t = t
		.replace(/&nbsp;/gi, ' ')
		.replace(/&amp;/gi, '&')
		.replace(/&lt;/gi, '<')
		.replace(/&gt;/gi, '>')
		.replace(/&quot;/gi, '"')
		.replace(/&#39;/g, "'");
	t = t.replace(/\s+/g, ' ').trim();
	if (t.length > META_MAX_LEN) t = t.slice(0, META_MAX_LEN);
	return t;
}

function previewMeta(value) {
	const short = value.length > 60 ? `${value.slice(0, 57)}...` : value;
	return `"${short.replace(/"/g, '\\"')}"`;
}

async function listFiles(dir) {
	const names = await readdir(dir);
	return names.map((n) => join(dir, n)).sort();
}

async function columnsExist(conn) {
	for (const col of ['meta_description_ru', 'meta_description_en']) {
		const [rows] = await conn.query('SHOW COLUMNS FROM articles LIKE ?', [col]);
		if (!rows.length) return false;
	}
	return true;
}

async function articleExists(conn, id) {
	const [rows] = await conn.query('SELECT 1 FROM articles WHERE id=? LIMIT 1', [id]);
	return rows.length > 0;
}

async function main() {
	let files;
	try {
		files = await listFiles(descriptionsDir);
	} catch {
		console.error(`[import] ERROR descriptions dir not found: ${descriptionsDir}`);
		process.exit(1);
	}

	const onlyFiles = [];
	for (const path of files) {
		try {
			if ((await stat(path)).isFile()) onlyFiles.push(path);
		} catch {
			// skip
		}
	}

	if (onlyFiles.length === 0) {
		logWarn(`no files in ${descriptionsDir}`);
		process.exit(0);
	}

	logInfo(`mode=${dryRun ? 'dry-run' : 'apply'} dir=${descriptionsDir}`);

	const conn = await mysql.createConnection({
		host: dbHost,
		port: dbPort,
		user: dbUser,
		password: dbPass,
		database: dbName,
		charset: 'utf8mb4',
	});

	try {
		if (!(await columnsExist(conn))) {
			console.error('[import] ERROR: apply db/migration/articles_meta_description.sql on server first');
			process.exit(1);
		}

		const stats = {
			processed: 0,
			updated: 0,
			skippedNoId: 0,
			skippedNoFields: 0,
			skippedMissingArticle: 0,
			missingSeoRu: 0,
			missingSeoEn: 0,
		};

		for (const path of onlyFiles) {
			const name = basename(path);
			const id = extractArticleId(name);
			if (id <= 0) {
				logWarn(`${name}: cannot extract article id from filename`);
				stats.skippedNoId++;
				continue;
			}

			const content = await readFile(path, 'utf8');
			const seoRu = extractSeoField(content, 'seo-ru');
			const seoEn = extractSeoField(content, 'seo-en');

			if (seoRu === null) {
				logWarn(`${name} (id=${id}): seo-ru not found`);
				stats.missingSeoRu++;
			}
			if (seoEn === null) {
				logWarn(`${name} (id=${id}): seo-en not found`);
				stats.missingSeoEn++;
			}
			if (seoRu === null && seoEn === null) {
				stats.skippedNoFields++;
				continue;
			}

			stats.processed++;

			if (!(await articleExists(conn, id))) {
				logWarn(`${name} (id=${id}): article not found in database`);
				stats.skippedMissingArticle++;
				continue;
			}

			const metaRu = seoRu !== null ? normalizeMeta(seoRu) : null;
			const metaEn = seoEn !== null ? normalizeMeta(seoEn) : null;

			const sets = [];
			const params = [];
			if (metaRu !== null) {
				sets.push('meta_description_ru=?');
				params.push(metaRu);
			}
			if (metaEn !== null) {
				sets.push('meta_description_en=?');
				params.push(metaEn);
			}
			params.push(id);

			if (dryRun) {
				console.log(
					`[dry-run] ${name} id=${id}` +
						(metaRu !== null ? ` ru=${previewMeta(metaRu)}` : '') +
						(metaEn !== null ? ` en=${previewMeta(metaEn)}` : ''),
				);
				stats.updated++;
				continue;
			}

			await conn.query(`UPDATE articles SET ${sets.join(', ')} WHERE id=? LIMIT 1`, params);
			console.log(
				`[ok] ${name} id=${id}` +
					(metaRu !== null ? ` ru=${metaRu.length}b` : '') +
					(metaEn !== null ? ` en=${metaEn.length}b` : ''),
			);
			stats.updated++;
		}

		logInfo(
			`done files=${onlyFiles.length} processed=${stats.processed} updated=${stats.updated} ` +
				`missing_seo_ru=${stats.missingSeoRu} missing_seo_en=${stats.missingSeoEn} ` +
				`skipped_no_id=${stats.skippedNoId} skipped_no_fields=${stats.skippedNoFields} ` +
				`skipped_missing_article=${stats.skippedMissingArticle}`,
		);
	} finally {
		await conn.end();
	}
}

main().catch((err) => {
	console.error('[import] ERROR', err.message);
	process.exit(1);
});
