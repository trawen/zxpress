# ZXNet `title_en` — `zx.spectrum` + `real.speccy`

Google Translate (gtx) EN titles for topics in:

- `zx.spectrum`
- `real.speccy`

## Подготовка

```bash
set -a; . ./.env; set +a
python3 tools/fill-echos-subjs-title-en.py --echo=zx.spectrum
python3 tools/fill-echos-subjs-title-en.py --echo=real.speccy
python3 db/migration/echos_subjs2_zx_spectrum_title_en/build.py
```

Пишет:

- `db/migrate/<timestamp>_echos_subjs2_zxnet_title_en.sql` — в git, автонакат через `update.sh`
- `generated/01_update_title_en.sql` — локальная копия (в `.gitignore`)
- `generated/summary.json`

SQL идемпотентен: обновляет только пустые `title_en`, с привязкой к `echos_titles2.title`.

## Применение

**Прод:** закоммитить файл из `db/migrate/` и:

```bash
./deploy/scripts/remote-update.sh --fast
```

**Вручную:**

```bash
python3 db/migration/echos_subjs2_zx_spectrum_title_en/apply.py
```
