# myjknote

Personal shortcut pages + PHP APIs.

- **Primary:** https://myjknote.zeabur.app
- **Backup:** https://myjknote.byethost18.com

## Pages

link / FB / shopping / Invest / Book / Ukulele / JCBcount / BankQRcode / BankUsage / Calendar

## Data (Zeabur)

- Runtime writable: `/var/www/data`（Volume ID e.g. `data`）
- Seed defaults: `seed/`
- Bank QR images: `Bank/`

## Backup sync → byethost

Keeps byethost as a failover mirror of this repo (site files + `data/`/`seed/`/`Bank/` in git).

### A) Local one-click script

```bash
cp .env.byethost.example .env.byethost
# edit FTP_SERVER / FTP_USERNAME / FTP_PASSWORD / FTP_SERVER_DIR

./scripts/sync-byethost.sh --dry-run   # list files
./scripts/sync-byethost.sh             # upload
```

### B) GitHub Action (auto on push to `main`)

Repo → **Settings → Secrets and variables → Actions** → add:

| Secret | Example |
|--------|---------|
| `BYEHOST_FTP_SERVER` | `ftpupload.net`（以面板為準） |
| `BYEHOST_FTP_USERNAME` | FTP 帳號 |
| `BYEHOST_FTP_PASSWORD` | FTP 密碼 |
| `BYEHOST_FTP_SERVER_DIR` | `/htdocs/`（可選，預設就是這個） |

然後 **Actions → Sync to byethost → Run workflow** 可手動跑一次。之後每次 push `main` 也會同步。

> 這只同步 **Git 裡的檔案**。Zeabur Volume 上後來改過的班表／卡片，若要比 byethost 更新，請另存 `data/` 或之後再加「從 Zeabur 拉資料」步驟。

## Runtime data export / backup

班表、BankUsage、JCBcount 存在 Zeabur Volume，**不會**隨一般 git push 自動到 byethost。

```bash
# 1) 從 Zeabur 拉最新資料到本機 data/
python3 scripts/sync_data.py export

# 2a) 再 FTP 上傳整個站（含剛匯出的 data/）到 byethost
./scripts/sync-byethost.sh
# 或一步完成：
python3 scripts/sync_data.py backup-byethost

# 本機 data/ 覆寫回 Zeabur（還原用）
python3 scripts/sync_data.py import-zeabur
```

可選：`export --also-seed` 會同步更新 `seed/`（空 Volume 開機用）。
