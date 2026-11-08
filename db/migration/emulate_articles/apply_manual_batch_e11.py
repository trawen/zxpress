#!/usr/bin/env python3
"""One-off: apply EN→RU for emulate i18n batch (subagent e11)."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
TRANS_DIR = Path(__file__).resolve().parent / "manual_translations_e11"
NAMES = [
    "04_11_chetvertaya-chast-istorii-zx-spectrum-obzor-port.json",
    "04_05_kriticheskiy-analiz-plagiarizma-v-kompyuternyh-i.json",
    "05_14_katalog-veb-saytov-ftp-serverov-i-bbs-dlya-poklo.json",
    "06_03_sovety-i-kody-dlya-prohozhdeniya-klassicheskih-i.json",
    "02_04_rukovodstvo-po-konvertacii-kasset-v-formaty-emul.json",
    "03_02_tretiy-nomer-zhurnala-emulate-s-novostyami-spect.json",
    "01_09_reyting-populyarnyh-igr-zx-spectrum-za-sentyabr-.json",
    "05_06_perepiska-chitateley-zhurnala-emulate-o-emulyato.json",
    "01_07_istoriya-kompyuterov-zx-spectrum-ot-pervyh-model.json",
    "04_02_vypusk-zhurnala-emulate-s-novostyami-spektrumnoy.json",
    "03_12_istoriya-sozdaniya-portativnogo-kompyutera-sincl.json",
]
DIRS = [
    ROOT / "site/cli/imports/emulate_i18n",
    ROOT / "db/migration/emulate_articles/i18n",
]

def main() -> None:
    for name in NAMES:
        stem = name.replace(".json", "")
        meta_path = TRANS_DIR / f"{stem}.meta.json"
        body_path = TRANS_DIR / f"{stem}.body.ru"
        if not meta_path.is_file() or not body_path.is_file():
            raise SystemExit(f"missing {stem} translation files")
        meta = json.loads(meta_path.read_text(encoding="utf-8"))
        body_ru = body_path.read_text(encoding="utf-8")
        for d in DIRS:
            p = d / name
            data = json.loads(p.read_text(encoding="utf-8"))
            data["title_ru"] = meta["title_ru"]
            data["meta_description_ru"] = meta["meta_description_ru"]
            data["body_ru"] = body_ru
            p.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"OK {name} ({len(body_ru)} chars)")


if __name__ == "__main__":
    main()
