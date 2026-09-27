# myjknote

Personal shortcut pages + PHP APIs on Zeabur.

- Site: https://myjknote.zeabur.app

## Pages

link / FB / shopping / Invest / Book / Ukulele / JCBcount / BankQRcode / BankUsage / Calendar

## Data

- Runtime writable: `/var/www/data`（Zeabur Volume）
- Seed defaults: `seed/`（Volume 空時由 entrypoint 自動複製）
- Bank QR images: put files in `Bank/` then push

## Volume

- Volume ID: `data`
- Mount Directory: `/var/www/data`
