# Local scripts (Mac / local Docker only)

Сюда клади **свои** скрипты обработки статей, писем, импорта SEO и т.п., которые запускаются локально и **не** едут на прод через `update.sh`.

## Зачем отдельно от `tools/`

| | `tools/` | `local/scripts/` |
|--|----------|------------------|
| Назначение | общие утилиты репозитория | личные/черновые one-off скрипты |
| Git | коммитятся выборочно (`*.mjs` / `*.php` / `*.sh`) | дампы и профили Chrome в ignore |
| Прод | могут вызываться на сервере | только локально |

## Описание выпуска из meta статей (Gemini)

Берёт `articles.meta_description_*` заданного номера (чтение БД) и через Gemini пишет JSON в файл. **В БД ничего не пишет.**

```bash
# 1) Chrome с remote debugging + открытый gemini.google.com/app
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --remote-debugging-port=9222 --user-data-dir="$HOME/chrome-gemini-debug"

# 2) результат → local/work/issue-desc-{id}.json
./local/scripts/run-generate-issue-description.sh --issue=123

# или по slug / свой путь
./local/scripts/run-generate-issue-description.sh --press=spectrofon --issue-slug=01
./local/scripts/run-generate-issue-description.sh --issue=123 --out=local/work/my.json
```

Нужны: поднятый Docker (`db` + `php`), `npm install` в соседнем `../zxpress-markdown` (playwright). Путь к markdown-репо: `ZXPRESS_MARKDOWN` или `--markdown=PATH`.

## Прочие примеры

```bash
./tools/php.sh local/scripts/my-job.php
python3 local/scripts/fill-something.py
```

Рабочие дампы: `local/work/` (в `.gitignore`).
