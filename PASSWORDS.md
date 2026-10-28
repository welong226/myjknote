# 怎麼設定兩組密碼

| 密碼 | 變數名稱 | 用途 |
|------|----------|------|
| 班表密碼 | `MYJK_SHIFT_KEY` | 看／改班表（可給同事） |
| 票券／銀行密碼 | `MYJK_PERSONAL_KEY` | 票券、銀行額度、卡片（只有你） |

---

## Railway（主站，建議這樣設）

在專案裡對 **myjknote** 服務設定兩個 Variables，然後重新部署：

```bash
cd /Users/aleon/myjknote-work/myjknote
railway variables --service myjknote set MYJK_SHIFT_KEY='你的班表密碼'
railway variables --service myjknote set MYJK_PERSONAL_KEY='你的票券銀行密碼'
railway redeploy --service myjknote --yes
```

或到 Railway 網頁 → myjknote → Variables → 新增同上兩個名稱。

開班表／票券頁時，瀏覽器會跳窗請你輸入對應密碼，輸入一次會存在本機（localStorage）。

---

## byethost（備援）

1. 複製 `data/keys.example.php` → `data/keys.php`
2. 填入與 Railway **相同** 的兩組密碼
3. 用 FTP 上傳 `data/keys.php`（不要 commit 到 Git）

---

## 本機備份腳本

在專案根目錄建 `.env.myjk`（已 gitignore）：

```
MYJK_SHIFT_KEY=班表密碼
MYJK_PERSONAL_KEY=票券銀行密碼
```

然後：

```bash
python3 scripts/sync_data.py export
```

---

## 換密碼

1. 改 Railway Variables（與 byethost 的 `keys.php`）
2. 重新部署／上傳
3. 瀏覽器若還記著舊密碼：開該頁 → 輸入錯會再問一次，或清除該網站的網站資料
