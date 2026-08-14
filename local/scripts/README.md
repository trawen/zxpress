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

# 2) без аргументов — все выпуски с meta; или выборочно
./local/scripts/run-generate-issue-description.sh
./local/scripts/run-generate-issue-description.sh --issue=z80/01
./local/scripts/run-generate-issue-description.sh --issue=z80
./local/scripts/run-generate-issue-description.sh --issue=z80,on-line
# → local/work/issue-desc-{id}.json на каждый номер (готовые пропускаются)
```

Нужны: поднятый Docker (`db` + `php`), `npm install` в соседнем `../zxpress-markdown` (playwright). Путь к markdown-репо: `ZXPRESS_MARKDOWN` или `--markdown=PATH`.

## Описание издания из meta выпусков (Gemini)

Берёт `issue.meta_description_*` всех номеров издания и через Gemini пишет JSON для `press`. **В БД ничего не пишет.**

```bash
# Chrome с remote debugging + открытый gemini.google.com/app (как выше)

./local/scripts/run-generate-press-description.sh
./local/scripts/run-generate-press-description.sh --press=z80
./local/scripts/run-generate-press-description.sh --press=z80,on-line,nicron
# → local/work/press-desc-{id}.json на каждое издание (готовые пропускаются)

# много номеров (Nicron и т.п.) — равномерная выборка meta в промпт:
./local/scripts/run-generate-press-description.sh --press=nicron --max-issues=80
```

## Прочие примеры

```bash
./tools/php.sh local/scripts/my-job.php
python3 local/scripts/fill-something.py
```

Рабочие дампы: `local/work/` (в `.gitignore`).
