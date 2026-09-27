# myjknote

Personal static pages + PHP APIs on Zeabur.

- Site: https://myjknote.zeabur.app
- PocketBase: https://pocktbase.zeabur.app

## Deploy (PHP)

Repo includes a `Dockerfile` (nginx + php-fpm on port **8080**).

1. Zeabur → myjknote 服務 → **Redeploy**（或刪掉舊服務後用 GitHub 重加一次）
2. 建置方案應為 **Dockerfile / docker**，不是 static
3. Domains 維持 `myjknote.zeabur.app`（不要對外開 :8080）
4. **Volumes** 新增持久化磁碟，掛載路徑：

   `/var/www/data`

5. 驗證：開 `https://myjknote.zeabur.app/api.php?ym=2026-09`  
   應回 JSON，不是 PHP 原始碼

## Layout

- HTML in repo root
- PHP: `api.php`, `bank_api.php`, `card_api.php`, `calendar_api.php`, …
- Writable data: `data/` (`shift_data/`, `*_data.json`, …)
