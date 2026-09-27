# myjknote

Personal static pages + PHP APIs (from byethost), deployed on Zeabur.

- Site: https://myjknote.zeabur.app
- PocketBase (NoteTick sync): https://pocktbase.zeabur.app

## Zeabur

This repo is a **PHP** app (`index.php` + `composer.json`). Provider should show **php**, not static.

### Persistent data (required for saving)

Mount a volume on the service:

| Mount path | Contents |
|---|---|
| `/var/www/data` | `shift_data/`, `banks_quota.json`, `cards_data.json`, `calendar_data.json` |

Without a volume, writes work until the next redeploy, then reset.

### After pushing PHP changes

1. Redeploy the `myjknote` service (or wait for auto-deploy).
2. Build plan should be **php** (nginx + php-fpm).
3. Test: `https://myjknote.zeabur.app/api.php?ym=2026-09` should return JSON, not PHP source.

## Local files

- HTML pages in repo root
- PHP APIs: `api.php`, `bank_api.php`, `card_api.php`, `calendar_api.php`, …
- Writable JSON under `data/`
