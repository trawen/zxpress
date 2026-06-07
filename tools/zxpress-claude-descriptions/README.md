# Импорт SEO в articles (локально)

Скопируйте **эти файлы** в `~/Projects/zxpress-claude-descriptions/` — рядом с папкой `descriptions/`:

```
zxpress-claude-descriptions/
├── descriptions/          ← ваши txt с seo-ru / seo-en
├── import-articles-meta.mjs
├── package.json
├── .env                   ← из .env.example
└── node_modules/
```

## Настройка

```bash
cd ~/Projects/zxpress-claude-descriptions
cp .env.example .env   # DB_HOST, DB_PASS и т.д.
npm install
```

MySQL на сервере — через SSH-туннель в другом терминале:

```bash
ssh -L 3307:127.0.0.1:3306 dockeruser@your-server
```

В `.env`: `DB_HOST=127.0.0.1`, `DB_PORT=3307`.

На сервере один раз: `db/migration/articles_meta_description.sql`.

## Запуск

```bash
npm run dry-run
npm run apply
```

Файлы без `seo-ru` / `seo-en` — предупреждение в консоль, остальное импортируется.
