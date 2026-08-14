# Issue emulator screenshots → `screens` + activity feed

Импорт скриншотов из  
`/Users/spectrumism/Projects/zxpress-screenshots/screenshots/issues`  
в таблицу `screens` и публичные `activity_batch` / `activity`, чтобы они появились на `/ru/updates-activity`.

## Что делает миграция

На **каждое издание (press)** с найденными скриншотами:

1. Вставляет строки в `screens` (`format=webp`, `type=0`).
2. Создаёт один публичный `activity_batch` (`domain=ezine`, `root_type=press`, `source=migration:issue_emulator_screenshots`).
3. Добавляет события `screen.uploaded` (по одному на кадр).
4. Копирует/конвертирует PNG → `data/uploads/screens/1/{id}.webp` (+ `.png` оригинал).

Матчинг файла: имя скрина `{press}__{STEM}_{page}.png` ↔ `files.name` (через `issues.json` / slugify как в `run_issues_screenshots.py`).

## Подготовка (уже можно гонять — только генерация)

```bash
# из корня репозитория, docker db должен быть up
python3 db/migration/issue_emulator_screenshots/build.py
```

Пишет в `generated/`:

- `01_screens_activity.sql` — SQL (не применять без `apply`)
- `copy_manifest.tsv` — `screen_id<TAB>source_png`
- `unmatched.txt` — кадры без `files` в БД
- `summary.json`

## Применение (когда будешь готов)

```bash
python3 db/migration/issue_emulator_screenshots/apply.py
# или по шагам:
#   mysql … < generated/01_screens_activity.sql
#   python3 db/migration/issue_emulator_screenshots/apply.py --files-only
```

`apply.py` по умолчанию **не** запускается из build. Идемпотентность: повторный apply с тем же SQL без очистки даст дубликаты — перед повтором откатывай по `source` / диапазону id из `summary.json`.

SQL **не** делает `ALTER TABLE` (у `zxpress_u` часто нет права ALTER); InnoDB сам сдвигает `AUTO_INCREMENT` после INSERT с явными id.

Если SQL уже прошёл, а упало только на старом `ALTER` — файлы догоняй так:

```bash
python3 db/migration/issue_emulator_screenshots/apply.py --files-only
```
