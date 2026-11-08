#!/usr/bin/env python3
"""Apply RU translations for batch of 11 Emulate articles."""
import json
import shutil
from pathlib import Path

SRC = Path(__file__).parent
MIRROR = Path(__file__).resolve().parents[4] / "db/migration/emulate_articles/i18n"

FILES = [
    "03_01_zhurnal-emulate-issue-3-za-noyabr-dekabr-1995-go.json",
    "05_09_obzor-klassicheskoy-arkady-cybernoid-dlya-zx-spe.json",
    "04_01_zhurnal-emulate-issue-4-yanvar-fevral-1996-o-emu.json",
    "05_01_soderzhanie-pyatogo-nomera-zhurnala-emulate-za-m.json",
    "01_08_obzor-populyarnyh-saytov-zx-spectrum-v-internete.json",
    "02_12_prohozhdenie-priklyuchencheskih-igr-shadows-of-m.json",
    "05_13_reytingi-luchshih-igr-zx-spectrum-za-aprel-1986-.json",
    "03_05_obsuzhdenie-igr-zx-spectrum-kotorye-pugayut-i-za.json",
    "03_10_reytingi-i-top-10-prodavaemyh-igr-zx-spectrum-za.json",
    "05_11_desyataya-chast-spravochnika-recenziy-na-igry-zx.json",
    "04_14_charty-populyarnosti-igr-zx-spectrum-za-fevral-1.json",
]

TITLES = {
    "03_01_zhurnal-emulate-issue-3-za-noyabr-dekabr-1995-go.json": "Вступление — содержание.",
    "05_09_obzor-klassicheskoy-arkady-cybernoid-dlya-zx-spe.json": "Часть 8 — классические обзоры — Cybernoid.",
    "04_01_zhurnal-emulate-issue-4-yanvar-fevral-1996-o-emu.json": "Вступление — содержание.",
    "05_01_soderzhanie-pyatogo-nomera-zhurnala-emulate-za-m.json": "Вступление — содержание.",
    "01_08_obzor-populyarnyh-saytov-zx-spectrum-v-internete.json": "Часть 8 — Spectrum в Сети.",
    "02_12_prohozhdenie-priklyuchencheskih-igr-shadows-of-m.json": "Часть 12 — приключения.",
    "05_13_reytingi-luchshih-igr-zx-spectrum-za-aprel-1986-.json": "Часть 12 — чарты игр Spectrum.",
    "03_05_obsuzhdenie-igr-zx-spectrum-kotorye-pugayut-i-za.json": "Часть 4 — игры, от которых вздрагиваешь?",
    "03_10_reytingi-i-top-10-prodavaemyh-igr-zx-spectrum-za.json": "Часть 9 — чарты игр Spectrum.",
    "05_11_desyataya-chast-spravochnika-recenziy-na-igry-zx.json": "Часть 10 — от A до Z: обзоры игр Spectrum (часть 10).",
    "04_14_charty-populyarnosti-igr-zx-spectrum-za-fevral-1.json": "Часть 13 — чарты игр Spectrum.",
}


def load_bodies():
    bodies_dir = SRC / "_ru_bodies"
    out = {}
    for fname in FILES:
        txt = (bodies_dir / fname.replace(".json", ".txt")).read_text(encoding="utf-8")
        out[fname] = "\r\n".join(txt.splitlines())
        if txt.endswith("\n") or txt.endswith("\r\n"):
            out[fname] += "\r\n"
    return out


def main():
    bodies = load_bodies()
    MIRROR.mkdir(parents=True, exist_ok=True)
    done = []
    for fname in FILES:
        path = SRC / fname
        data = json.loads(path.read_text(encoding="utf-8"))
        data["title_ru"] = TITLES[fname]
        data["meta_description_ru"] = data.get("meta_description_en", "")
        data["body_ru"] = bodies[fname]
        text = json.dumps(data, ensure_ascii=False, indent=2) + "\n"
        path.write_text(text, encoding="utf-8")
        shutil.copy2(path, MIRROR / fname)
        done.append(fname)
    print("\n".join(done))


if __name__ == "__main__":
    main()
