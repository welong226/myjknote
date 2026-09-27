# myjknote

Personal shortcut pages + PHP APIs on Zeabur.

- Site: https://myjknote.zeabur.app
- PocketBase: https://pocktbase.zeabur.app

## Pages (index)

| Link | File |
|------|------|
| link | `link.htm` |
| FB | `FB.htm` |
| shopping | `shopping.htm` |
| Invest | `Invest.htm` |
| Book | `Book.htm` |
| Ukulele | `Ukulele.html` |
| JCBcount | `CardManager.html` → `card_api.php` |
| BankQRcode | `Bank.html` |
| BankUsage | `BankManager.html` → `bank_api.php` |
| Calendar | `shift-calendar.html` → `api.php` |

Writable data lives under `data/` (mount `/var/www/data` on Zeabur).

## Deploy

Dockerfile (nginx + php-fpm, port 8080). Redeploy after push. Volume ID e.g. `data` → `/var/www/data`.
