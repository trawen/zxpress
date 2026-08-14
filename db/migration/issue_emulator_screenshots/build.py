#!/usr/bin/env python3
"""Build (do not apply) SQL + file manifest for emulator issue screenshots → activity.

Reads screenshots from zxpress-screenshots and maps them to files/issue/press via DB dump.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[3]
HERE = Path(__file__).resolve().parent
OUT = HERE / "generated"
DEFAULT_SHOTS = Path(
    os.environ.get(
        "ZXPRESS_SHOTS_DIR",
        "/Users/spectrumism/Projects/zxpress-screenshots/screenshots/issues",
    )
)
DEFAULT_ISSUES_JSON = Path(
    os.environ.get(
        "ZXPRESS_ISSUES_JSON",
        "/Users/spectrumism/Projects/zxpress-screenshots/issues.json",
    )
)
SOURCE = "migration:issue_emulator_screenshots"

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
MYSQL_USER = os.environ.get("DB_USER") or os.environ.get("MYSQL_USER", "zxpress_u")
MYSQL_PASS = os.environ.get("DB_PASS") or os.environ.get("MYSQL_PASSWORD", "changeme-app-password")
MYSQL_DB = os.environ.get("DB_NAME") or os.environ.get("MYSQL_DATABASE", "zxpress_db")


def slugify_simple(press: str, fname: str) -> str:
    stem = Path(fname).stem
    safe_press = re.sub(r"[^\w\-]+", "_", press.strip().lower()).strip("_")
    safe_stem = re.sub(r"[^\w\-]+", "_", stem).strip("_")
    return f"{safe_press}__{safe_stem}"


def slugify_hex(press: str, fname: str) -> str:
    """Variant where non-word chars become _XX (ASCII hex), matching some on-disk names."""
    stem = Path(fname).stem
    safe_press = re.sub(r"[^\w\-]+", "_", press.strip().lower()).strip("_")

    def enc(s: str) -> str:
        out: list[str] = []
        for ch in s:
            if re.match(r"[\w\-]", ch):
                out.append(ch)
            else:
                out.append(f"_{ord(ch):02X}")
        return "".join(out)

    return f"{safe_press}__{enc(stem)}"


def parse_shot_name(name: str) -> tuple[str, str, int] | None:
    """Return (press_prefix, issue_stem, page) from press__STEM_page.ext."""
    base, _, ext = name.rpartition(".")
    if ext.lower() not in ("png", "jpg", "jpeg", "webp"):
        return None
    m = re.match(r"^(.+?)__(.+)_(\d+)$", base)
    if not m:
        return None
    return m.group(1), m.group(2), int(m.group(3))


def sql_str(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def docker_mysql(sql: str) -> str:
    cmd = [
        "docker",
        "exec",
        MYSQL_CONTAINER,
        "mysql",
        "-N",
        f"-u{MYSQL_USER}",
        f"-p{MYSQL_PASS}",
        MYSQL_DB,
        "-e",
        sql,
    ]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit(f"mysql failed: {r.stderr or r.stdout}")
    # strip password warning lines
    lines = [
        ln
        for ln in r.stdout.splitlines()
        if ln and not ln.startswith("mysql: [Warning]")
    ]
    return "\n".join(lines)


def load_files_index() -> dict[str, dict]:
    """Map UPPER(file name) and stem → file row."""
    raw = docker_mysql(
        "SELECT f.id, f.name, f.id_issue, i.id_press, i.title, "
        "IFNULL(i.slug_ru,''), p.title, IFNULL(p.slug_ru,'') "
        "FROM files f "
        "JOIN issue i ON i.id=f.id_issue "
        "JOIN press p ON p.id=i.id_press"
    )
    by_name: dict[str, dict] = {}
    for line in raw.splitlines():
        parts = line.split("\t")
        if len(parts) < 8:
            continue
        row = {
            "file_id": int(parts[0]),
            "name": parts[1],
            "id_issue": int(parts[2]),
            "id_press": int(parts[3]),
            "issue_title": parts[4],
            "issue_slug": parts[5],
            "press_title": parts[6],
            "press_slug": parts[7],
        }
        uname = row["name"].upper()
        by_name[uname] = row
        stem = Path(row["name"]).stem.upper()
        by_name.setdefault(stem, row)
        by_name.setdefault(stem + ".ZIP", row)
    return by_name


def load_slug_maps(issues_json: Path) -> dict[str, str]:
    """slug (press__STEM) → decoded file name from issues.json (for DB lookup)."""
    out: dict[str, str] = {}
    if not issues_json.is_file():
        return out
    data = json.loads(issues_json.read_text(encoding="utf-8"))
    for it in data:
        press = str(it.get("press") or "")
        raw = str(it.get("file") or "")
        if not press or not raw:
            continue
        fname = unquote(raw)
        # Also try data_s trailing token if present (human name).
        candidates = [fname, raw]
        data_s = str(it.get("data_s") or "").strip()
        if data_s:
            tail = data_s.split()[-1]
            if "." in tail:
                candidates.append(tail)
        for cand in candidates:
            for slug in (slugify_simple(press, cand), slugify_hex(press, cand)):
                out[slug] = fname  # always store decoded name for DB
    return out


def scan_shots(shots_dir: Path) -> list[dict]:
    """Flat list of page shots grouped later by press/issue."""
    items: list[dict] = []
    for press_dir in sorted(shots_dir.iterdir()):
        if not press_dir.is_dir() or press_dir.name.startswith("."):
            continue
        for f in press_dir.iterdir():
            if not f.is_file():
                continue
            parsed = parse_shot_name(f.name)
            if not parsed:
                continue
            _prefix, stem, page = parsed
            # full slug without page: pressfolder slugified + stem as on disk
            folder_slug = re.sub(r"[^\w\-]+", "_", press_dir.name.strip().lower()).strip(
                "_"
            )
            slug = f"{folder_slug}__{stem}"
            items.append(
                {
                    "press_folder": press_dir.name,
                    "stem": stem,
                    "page": page,
                    "slug": slug,
                    "path": f,
                    "mtime": int(f.stat().st_mtime),
                }
            )
    items.sort(key=lambda x: (x["press_folder"].lower(), x["stem"], x["page"]))
    return items


def resolve_file(
    shot: dict, by_name: dict[str, dict], slug_to_file: dict[str, str]
) -> dict | None:
    fname = slug_to_file.get(shot["slug"])
    if fname:
        row = by_name.get(fname.upper()) or by_name.get(Path(fname).stem.upper())
        if row:
            return row
    stem = shot["stem"].upper()
    return by_name.get(stem) or by_name.get(stem + ".ZIP")


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "shots_dir",
        nargs="?",
        type=Path,
        default=DEFAULT_SHOTS,
        help="Directory with per-press screenshot folders",
    )
    ap.add_argument(
        "--issues-json",
        type=Path,
        default=DEFAULT_ISSUES_JSON,
        help="issues.json for slug→filename map",
    )
    args = ap.parse_args()
    shots_dir = args.shots_dir.resolve()
    issues_json = args.issues_json.resolve()
    if not shots_dir.is_dir():
        raise SystemExit(f"shots dir not found: {shots_dir}")

    OUT.mkdir(parents=True, exist_ok=True)
    by_name = load_files_index()
    slug_to_file = load_slug_maps(issues_json)
    shots = scan_shots(shots_dir)

    max_screen = int(docker_mysql("SELECT IFNULL(MAX(id),0) FROM screens") or "0")
    max_batch = int(docker_mysql("SELECT IFNULL(MAX(id),0) FROM activity_batch") or "0")
    max_act = int(docker_mysql("SELECT IFNULL(MAX(id),0) FROM activity") or "0")

    next_screen = max_screen + 1
    next_batch = max_batch + 1
    next_act = max_act + 1

    matched: list[dict] = []
    unmatched: list[str] = []
    for s in shots:
        row = resolve_file(s, by_name, slug_to_file)
        if not row:
            unmatched.append(str(s["path"]))
            continue
        matched.append({**s, **row})

    # Group by press id → issues → pages
    by_press: dict[int, list[dict]] = defaultdict(list)
    for m in matched:
        by_press[int(m["id_press"])].append(m)

    sql: list[str] = []
    sql.append("-- Generated by issue_emulator_screenshots/build.py — DO NOT hand-edit")
    sql.append(f"-- {datetime.now(timezone.utc).isoformat()}")
    sql.append(f"-- screenshots: {shots_dir}")
    sql.append(f"-- matched pages: {len(matched)} / {len(shots)}")
    sql.append(f"-- presses: {len(by_press)}")
    sql.append("SET NAMES utf8mb4;")
    sql.append("SET FOREIGN_KEY_CHECKS=0;")
    sql.append("START TRANSACTION;")
    sql.append("")

    manifest: list[tuple[int, str]] = []
    batch_ids: list[int] = []
    screen_ids: list[int] = []

    for press_id in sorted(by_press.keys()):
        pages = by_press[press_id]
        pages.sort(key=lambda x: (x["stem"], x["page"]))
        press_title = pages[0]["press_title"]
        press_slug = pages[0]["press_slug"] or ""
        # Feed sorts by created_at — use "now" (not PNG mtime), stagger per press.
        created_at = int(datetime.now(timezone.utc).timestamp()) + len(batch_ids)
        batch_id = next_batch
        next_batch += 1
        batch_ids.append(batch_id)

        # allocate screens
        page_rows: list[tuple[dict, int]] = []
        for p in pages:
            sid = next_screen
            next_screen += 1
            screen_ids.append(sid)
            page_rows.append((p, sid))
            manifest.append((sid, str(p["path"])))

        first_sid = page_rows[0][1]
        n = len(page_rows)
        url_ru = f"/ru/ezines/{press_slug}" if press_slug else f"/issue.php?id={pages[0]['id_issue']}"
        url_en = f"/en/ezines/{press_slug}" if press_slug else url_ru
        title_ru = f"{press_title}: скриншоты выпусков"
        title_en = f"{press_title}: issue screenshots"
        summary_ru = f"+{n} " + (
            "скриншот" if n == 1 else ("скриншота" if 2 <= n % 10 <= 4 and not 12 <= n % 100 <= 14 else "скриншотов")
        )
        summary_en = f"+{n} screenshot" + ("" if n == 1 else "s")
        thumb = f"/screens/1/{first_sid}.webp"

        sql.append(f"-- press #{press_id} {press_title!r}: {n} shots → batch {batch_id}")
        sql.append(
            "INSERT INTO activity_batch ("
            "id, created_at, closed_at, actor_user_id, domain, root_type, root_id, "
            "title_ru, title_en, url_ru, url_en, summary_ru, summary_en, thumb_url, "
            "items_count, public_items_count, is_public, source"
            ") VALUES ("
            f"{batch_id}, {created_at}, {created_at}, 0, 'ezine', 'press', {press_id}, "
            f"{sql_str(title_ru)}, {sql_str(title_en)}, {sql_str(url_ru)}, {sql_str(url_en)}, "
            f"{sql_str(summary_ru)}, {sql_str(summary_en)}, {sql_str(thumb)}, "
            f"{n}, {n}, 1, {sql_str(SOURCE)}"
            ");"
        )

        for p, sid in page_rows:
            sql.append(
                "INSERT INTO screens (id, id_issue, id_press, type, date, format) VALUES ("
                f"{sid}, {int(p['id_issue'])}, {press_id}, 0, {created_at}, 'webp');"
            )
            issue_label = p["issue_title"] or p["stem"]
            ev_title_ru = f"{press_title} · #{issue_label} (кадр {p['page']})"
            ev_title_en = f"{press_title} · #{issue_label} (frame {p['page']})"
            ev_url = f"/issue.php?id={int(p['id_issue'])}"
            meta = json.dumps(
                {
                    "source": SOURCE,
                    "file_name": p["name"],
                    "file_id": p["file_id"],
                    "shot_path": str(p["path"]),
                    "page": p["page"],
                    "stem": p["stem"],
                },
                ensure_ascii=False,
            )
            aid = next_act
            next_act += 1
            sql.append(
                "INSERT INTO activity ("
                "id, batch_id, created_at, actor_user_id, verb, object_type, object_id, "
                "parent_type, parent_id, action, event_scope, is_public, "
                "title_ru, title_en, url_ru, url_en, thumb_url, meta_json"
                ") VALUES ("
                f"{aid}, {batch_id}, {created_at}, 0, 'uploaded', 'screen', {sid}, "
                f"'issue', {int(p['id_issue'])}, 'screen.uploaded', 'content', 1, "
                f"{sql_str(ev_title_ru)}, {sql_str(ev_title_en)}, "
                f"{sql_str(ev_url)}, {sql_str(ev_url)}, {sql_str(f'/screens/1/{sid}.webp')}, "
                f"CAST({sql_str(meta)} AS JSON)"
                ");"
            )
        sql.append("")

    sql.append("COMMIT;")
    sql.append("SET FOREIGN_KEY_CHECKS=1;")
    # No ALTER TABLE — app user often lacks ALTER. InnoDB bumps AUTO_INCREMENT
    # from explicit high IDs on INSERT.
    sql.append(
        f"-- Next free ids (informational): screens={next_screen}, "
        f"activity_batch={next_batch}, activity={next_act}"
    )

    sql_path = OUT / "01_screens_activity.sql"
    sql_path.write_text("\n".join(sql) + "\n", encoding="utf-8")

    man_path = OUT / "copy_manifest.tsv"
    man_path.write_text(
        "\n".join(f"{sid}\t{src}" for sid, src in manifest) + ("\n" if manifest else ""),
        encoding="utf-8",
    )

    un_path = OUT / "unmatched.txt"
    un_path.write_text("\n".join(unmatched) + ("\n" if unmatched else ""), encoding="utf-8")

    summary = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "shots_dir": str(shots_dir),
        "total_png": len(shots),
        "matched_pages": len(matched),
        "unmatched_pages": len(unmatched),
        "presses": len(by_press),
        "screen_id_range": [screen_ids[0], screen_ids[-1]] if screen_ids else None,
        "batch_id_range": [batch_ids[0], batch_ids[-1]] if batch_ids else None,
        "source": SOURCE,
        "sql": str(sql_path.relative_to(ROOT)),
        "manifest": str(man_path.relative_to(ROOT)),
    }
    (OUT / "summary.json").write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )

    print(json.dumps(summary, ensure_ascii=False, indent=2))
    print(f"Wrote {sql_path}")
    print(f"Wrote {man_path} ({len(manifest)} files)")
    if unmatched:
        print(f"Unmatched: {len(unmatched)} → {un_path}")


if __name__ == "__main__":
    main()
