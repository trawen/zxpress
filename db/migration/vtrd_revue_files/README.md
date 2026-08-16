# ZX-Ревю files from vtrd.in → `books_files` + activity feed

Импорт архивов с [https://vtrd.in/book.php](https://vtrd.in/book.php) (секция Inforcom ZX Ревю)
в `books_files` / `books.file_id` и публичный `activity_batch` для `/ru/updates-activity`.

Источник файлов (кэш скачивания): `local/work/vtrd-books/*.ZIP`  
(или `ZXPRESS_VTRD_FILES_DIR`).

## Что делает миграция

1. Для книг 62–101 (ZX-Ревю 1991–1997) вставляет ZIP в `books_files` (если у книги ещё нет файлов).
2. Ставит `books.file_id` на новый файл.
3. Создаёт один публичный `activity_batch` (`source=migration:vtrd_revue_files`, «ZX-Ревю: файлы выпусков»).
4. Добавляет события `book_file.uploaded`.
5. Копирует ZIP в `data/uploads/books-files/`.

SQL **идемпотентный**: повторный apply не плодит дубликаты `books_files` / `activity`.

## Подготовка (только генерация)

```bash
# из корня репозитория; ZIP уже должны лежать в local/work/vtrd-books/
python3 db/migration/vtrd_revue_files/build.py
```

Пишет в `generated/`:

- `01_books_files_activity.sql`
- `copy_manifest.tsv` — `dest_name<TAB>source_path`
- `summary.json`
- `missing.txt` — если чего-то не хватает

## Применение локально

```bash
python3 db/migration/vtrd_revue_files/apply.py
# или:
#   python3 db/migration/vtrd_revue_files/apply.py --sql-only
#   python3 db/migration/vtrd_revue_files/apply.py --files-only
```

## Применение на сервере

ZIP (~72 MB) в git не кладём — залить отдельно.

```bash
# с машины разработки
rsync -av --progress \
  local/work/vtrd-books/*.ZIP \
  dockeruser@SERVER:/home/dockeruser/zxpress/local/work/vtrd-books/

# код миграции — через обычный deploy/git pull, либо:
rsync -av db/migration/vtrd_revue_files/ \
  dockeruser@SERVER:/home/dockeruser/zxpress/db/migration/vtrd_revue_files/
```

На сервере:

```bash
cd /home/dockeruser/zxpress
set -a; . ./.env; set +a

# при необходимости пересобрать SQL (пути в manifest)
mkdir -p local/work/vtrd-books
ZXPRESS_VTRD_FILES_DIR=/home/dockeruser/zxpress/local/work/vtrd-books \
  python3 db/migration/vtrd_revue_files/build.py

ZXPRESS_VTRD_FILES_DIR=/home/dockeruser/zxpress/local/work/vtrd-books \
  python3 db/migration/vtrd_revue_files/apply.py
```

Креды: `DB_USER` / `DB_PASS` / `DB_NAME` / `ZXPRESS_MYSQL_CONTAINER` (как у других миграций).

Проверка:

- `/ru/books/80` — ссылка на `REVU9404.ZIP`
- `/ru/updates-activity` — «ZX-Ревю: файлы выпусков», +N файлов

Откат (осторожно):

```sql
DELETE FROM activity WHERE batch_id IN (
  SELECT id FROM activity_batch WHERE source='migration:vtrd_revue_files'
);
DELETE FROM activity_batch WHERE source='migration:vtrd_revue_files';
-- файлы/books_files — вручную по author='vtrd.in' при необходимости
```
