# myjknote 備份拓撲

目標：換主機（Zeabur → Railway → 下一間）時，程式與資料都不會困在單一平台。

| 層 | 角色 | 位置 |
|----|------|------|
| 程式碼 | 真相來源 + 自動部署 | GitHub `welong226/myjknote` → Railway `myjknote` |
| 熱備 mirror | 站台檔（含 git 裡的 `data/`/`seed/`/`Bank/`） | byethost（push `main` 觸發 FTP Action） |
| 執行時資料 | Volume（班表／卡片／票券圖） | Railway `/var/www/data` |
| 離線備份 | 本機匯出 | `python3 scripts/sync_data.py export` |

**Primary URL:** https://myjk.up.railway.app  
**Backup URL:** https://myjknote.byethost18.com

---

## 日常

1. 改程式 → `git push origin main`  
   - Railway 自動建置部署  
   - GitHub Action 同步檔案到 byethost  
2. 線上改過班表／卡片／票券後，定期（建議每週）：

```bash
python3 scripts/sync_data.py export --also-seed
# 可選：把 data/ 與 seed/ 也 commit，讓 byethost mirror 帶最新 JSON
git add data seed && git commit -m "Sync runtime data snapshot" && git push
# 或一步：匯出後 FTP 整站（含圖片）到 byethost
python3 scripts/sync_data.py backup-byethost
```

## 還原到新主機

```bash
# 1) 程式：連 GitHub 或 clone 後 railway up / Docker
# 2) 資料：本機 data/ 覆寫線上
python3 scripts/sync_data.py import --base https://NEW_HOST
```

空 Volume 開機會從映像內 `seed/` 種一次；之後以 Volume 為準。

## 檢查清單（換平台前）

- [ ] `git status` 乾淨；`main` 已 push
- [ ] Railway / GitHub deploy = SUCCESS
- [ ] `curl -sI https://myjk.up.railway.app` → 200
- [ ] `python3 scripts/sync_data.py export` 成功（班表月檔 + cards + banks + vouchers）
- [ ] byethost Action 最近一次成功（或本機 `./scripts/sync-byethost.sh`）
- [ ] PocketBase（若有）另做 `pb_data` 壓縮備份（不在此 repo）

## GitHub Secrets（byethost FTP）

| Secret | 說明 |
|--------|------|
| `BYEHOST_FTP_SERVER` | 例如 `ftpupload.net` |
| `BYEHOST_FTP_USERNAME` | FTP 帳號 |
| `BYEHOST_FTP_PASSWORD` | FTP 密碼 |
| `BYEHOST_FTP_SERVER_DIR` | 可選，預設 `/htdocs/` |

本機 FTP：複製 `.env.byethost.example` → `.env.byethost`（勿 commit）。
