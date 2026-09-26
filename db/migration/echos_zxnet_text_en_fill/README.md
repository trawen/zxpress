# ZXNet `echos_zxnet.text_en` — `zx.spectrum` + `real.speccy`

Google Translate (gtx) EN message bodies. **Not** auto-applied via `db/migrate/`
(too large). Batched `INSERT` into a staging table, then `UPDATE … JOIN`.

## Limits (build.py)

| Setting | Default | Purpose |
|--------|---------|---------|
| `--max-insert-bytes` | 512 KiB | size of one `INSERT … VALUES` |
| `--max-rows-per-insert` | 80 | hard cap rows per INSERT |
| part rotate | ~4 MiB uncompressed | one `.sql.gz` part |

## Build (machine with filled local DB)

```bash
MYSQL_ROOT_PASSWORD=… python3 db/migration/echos_zxnet_text_en_fill/build.py
```

Пишет `generated/parts/*.sql.gz` + `generated/summary.json`.

Удобный архив для scp:

```bash
# после build.py уже есть:
# generated/echos_zxnet_text_en_parts.tar.gz
scp db/migration/echos_zxnet_text_en_fill/generated/echos_zxnet_text_en_parts.tar.gz dockeruser@HOST:/tmp/
# на сервере:
mkdir -p db/migration/echos_zxnet_text_en_fill/generated/parts
tar -xzf /tmp/echos_zxnet_text_en_parts.tar.gz -C db/migration/echos_zxnet_text_en_fill/generated/parts
```

Скопируй `generated/parts/` на сервер (scp/rsync), либо весь каталог миграции.

## Apply (prod / local)

```bash
cd /home/dockeruser/zxpress   # or local repo
set -a; . ./.env; set +a
python3 db/migration/echos_zxnet_text_en_fill/apply.py
```

Идемпотентно: `UPDATE` только где `text_en` пустой.

Resume с part N:

```bash
python3 db/migration/echos_zxnet_text_en_fill/apply.py --from 5
```
