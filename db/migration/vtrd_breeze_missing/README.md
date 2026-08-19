# Breeze missing issues from vtrd.in

Импортирует недостающие выпуски `Breeze` из `vtrd.in`:

- создаёт missing issues `#01..#05` в `issue`
- проставляет `slug_ru` / `slug_en`
- скачивает `BREEZE01.ZIP .. BREEZE05.ZIP`
- добавляет записи в `files`
- копирует ZIP в `data/uploads/files/`
- пишет публичные `activity_batch` / `activity`, чтобы изменения сразу появились на `/ru/updates-activity`

Скриншоты миграция **не** трогает.

## Что считается missing

В локальной/серверной БД у `Breeze` уже есть:

- `#06`
- `#09`
- `#10`

Миграция добавляет только:

- `#01`
- `#02`
- `#03`
- `#04`
- `#05`

Повторный запуск идемпотентен:

- issue не создаётся, если уже есть `issue.title`
- file не создаётся, если уже есть `files.name` для этого issue
- activity пишется только если в этом запуске реально создали issue и/или file

## Запуск локально

```bash
php db/migration/vtrd_breeze_missing/apply.php
```

По умолчанию ZIP кэшируются в:

```bash
local/work/vtrd-breeze/
```

Можно переопределить:

```bash
ZXPRESS_VTRD_BREEZE_DIR=/path/to/cache php db/migration/vtrd_breeze_missing/apply.php
```

## Запуск на сервере

После обычного деплоя кода:

```bash
cd /home/dockeruser/zxpress
docker compose exec -T php php db/migration/vtrd_breeze_missing/apply.php
```

или с кастомным кэшем:

```bash
cd /home/dockeruser/zxpress
docker compose exec -T \
  -e ZXPRESS_VTRD_BREEZE_DIR=/home/zxpress/tmp/vtrd-breeze \
  php php db/migration/vtrd_breeze_missing/apply.php
```

## Проверка

- `/ru/ezines/breeze` — должны появиться missing issues `#01..#05`
- `admin_issue.php?id=33` — у Breeze должны появиться выпуски и ZIP-файлы
- `/ru/updates-activity` — должны появиться события `issue.created` и `file.uploaded`
