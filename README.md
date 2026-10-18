# myjknote

Personal shortcut pages + PHP APIs.

- **Primary:** https://myjk.up.railway.app
- **Backup:** https://myjknote.byethost18.com
- **Repo / autodeploy:** GitHub `welong226/myjknote` → Railway service `myjknote`

詳見 [BACKUP.md](./BACKUP.md)。

## Pages

link / FB / shopping / Invest / Book / Ukulele / JCBcount / BankQRcode / BankUsage / Calendar / PairDrop / 我的票券

## Data (Railway)

- Runtime writable: `/var/www/data`（Volume）
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

> 這只同步 **Git 裡的檔案**。Railway Volume 上後來改過的班表／卡片／票券，請用下面的 `sync_data.py` 匯出後再 commit 或 FTP。

## Runtime data export / backup

班表、BankUsage、JCBcount、票券存在 Railway Volume，**不會**隨一般 git push 自動到 byethost。

```bash
# 1) 從 Railway 拉最新資料到本機 data/
python3 scripts/sync_data.py export

# 2a) 再 FTP 上傳整個站（含剛匯出的 data/）到 byethost
./scripts/sync-byethost.sh
# 或一步完成：
python3 scripts/sync_data.py backup-byethost

# 本機 data/ 覆寫回線上（還原用）
python3 scripts/sync_data.py import
```

可選：`export --also-seed` 會同步更新 `seed/`（空 Volume 開機用）。
