#!/usr/bin/env python3
"""
Export / import myjknote runtime data (班表 / BankUsage / JCBcount / 票券).

  # Railway → 本機 data/
  python3 scripts/sync_data.py export

  # 本機 data/ → Railway（還原／覆寫線上）
  python3 scripts/sync_data.py import

  # Railway → 本機 data/ → byethost FTP（需 .env.byethost）
  python3 scripts/sync_data.py backup-byethost
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "data"
SEED = ROOT / "seed"
DEFAULT_BASE = "https://myjk.up.railway.app"


def http_json(method: str, url: str, body: object | None = None):
    data = None
    headers = {"Accept": "application/json"}
    if body is not None:
        data = json.dumps(body, ensure_ascii=False).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    with urllib.request.urlopen(req, timeout=60) as resp:
        raw = resp.read().decode("utf-8")
        return json.loads(raw) if raw else None


def http_bytes(url: str) -> bytes:
    req = urllib.request.Request(url, headers={"Accept": "*/*"})
    with urllib.request.urlopen(req, timeout=120) as resp:
        return resp.read()


def write_json(path: Path, obj) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(obj, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def month_list() -> list[str]:
    """Current month −6 … +12, plus months already under data/ or seed/."""
    today = date.today()
    y, m = today.year, today.month
    months: set[str] = set()
    for delta in range(-6, 13):
        mm = m + delta
        yy = y
        while mm < 1:
            mm += 12
            yy -= 1
        while mm > 12:
            mm -= 12
            yy += 1
        months.add(f"{yy:04d}-{mm:02d}")
    for folder in (DATA / "shift_data", SEED / "shift_data"):
        if folder.is_dir():
            for f in folder.glob("????-??.json"):
                months.add(f.stem)
    return sorted(months)


def export_vouchers(base: str) -> int:
    """Download voucher metadata + images into data/."""
    base = base.rstrip("/")
    try:
        items = http_json("GET", f"{base}/voucher_api.php?action=list")
    except urllib.error.HTTPError as e:
        print(f"  vouchers: skip (HTTP {e.code})")
        return 0
    if not isinstance(items, list):
        print("  vouchers: unexpected response")
        return 0

    write_json(DATA / "vouchers.json", items)
    img_dir = DATA / "vouchers"
    img_dir.mkdir(parents=True, exist_ok=True)
    n_img = 0
    for item in items:
        if not isinstance(item, dict):
            continue
        vid = item.get("id")
        if not vid:
            continue
        # Prefer stored filename extension when present
        ext = "jpg"
        fn = item.get("file") or item.get("image") or ""
        if isinstance(fn, str) and "." in fn:
            ext = fn.rsplit(".", 1)[-1].lower() or "jpg"
        out = img_dir / f"{vid}.{ext}"
        url = f"{base}/voucher_api.php?action=img&id={urllib.parse.quote(str(vid))}"
        try:
            out.write_bytes(http_bytes(url))
            n_img += 1
        except urllib.error.HTTPError as e:
            print(f"  voucher img {vid}: HTTP {e.code}")
        except urllib.error.URLError as e:
            print(f"  voucher img {vid}: {e}")
    print(f"  vouchers: {len(items)} meta, {n_img} images")
    return len(items)


def cmd_export(base: str, also_seed: bool) -> int:
    base = base.rstrip("/")
    print(f"Export from {base}")

    cards = http_json("GET", f"{base}/card_api.php")
    banks = http_json("GET", f"{base}/bank_api.php")
    write_json(DATA / "cards_data.json", cards)
    write_json(DATA / "banks_quota.json", banks)
    print(f"  cards: {len(cards) if isinstance(cards, list) else '?'} items")
    print(f"  banks: {len(banks) if isinstance(banks, list) else '?'} items")

    n = 0
    for ym in month_list():
        try:
            month = http_json("GET", f"{base}/api.php?ym={ym}")
        except urllib.error.HTTPError as e:
            print(f"  skip {ym}: HTTP {e.code}")
            continue
        if not isinstance(month, dict):
            continue
        out = DATA / "shift_data" / f"{ym}.json"
        if not month and not out.exists():
            continue
        write_json(out, month)
        n += 1
        print(f"  shift {ym}: {len(month)} days")

    export_vouchers(base)

    if also_seed:
        for name in ("cards_data.json", "banks_quota.json", "vouchers.json"):
            src = DATA / name
            if src.is_file():
                write_json(SEED / name, json.loads(src.read_text(encoding="utf-8")))
        for f in (DATA / "shift_data").glob("*.json"):
            write_json(SEED / "shift_data" / f.name, json.loads(f.read_text(encoding="utf-8")))
        print("  also copied → seed/")

    print(f"Done. Wrote data/ ({n} month files).")
    return 0


def cmd_import(base: str) -> int:
    base = base.rstrip("/")
    print(f"Import local data/ → {base}")

    cards = json.loads((DATA / "cards_data.json").read_text(encoding="utf-8"))
    banks = json.loads((DATA / "banks_quota.json").read_text(encoding="utf-8"))
    print(f"  cards: {http_json('POST', f'{base}/card_api.php', cards)}")
    print(f"  banks: {http_json('POST', f'{base}/bank_api.php', banks)}")

    for f in sorted((DATA / "shift_data").glob("????-??.json")):
        ym = f.stem
        body = json.loads(f.read_text(encoding="utf-8"))
        print(f"  shift {ym}: {http_json('POST', f'{base}/api.php?ym={ym}', body)}")

    vouchers_meta = DATA / "vouchers.json"
    if vouchers_meta.is_file():
        print(
            "  vouchers: meta/images are in data/; restore images via vouchers UI "
            "or multipart upsert (API has no bulk import)."
        )

    print("Done.")
    return 0


def cmd_backup_byethost(base: str, also_seed: bool) -> int:
    rc = cmd_export(base, also_seed)
    if rc != 0:
        return rc
    print("Uploading via sync_byethost.py …")
    return subprocess.call([sys.executable, str(ROOT / "scripts" / "sync_byethost.py")])


def main() -> int:
    p = argparse.ArgumentParser(description="Sync myjknote runtime JSON data")
    p.add_argument(
        "command",
        choices=["export", "import", "import-zeabur", "backup-byethost"],
        help="import-zeabur is an alias for import (legacy name)",
    )
    p.add_argument("--base", default=DEFAULT_BASE, help="Site base URL")
    p.add_argument(
        "--also-seed",
        action="store_true",
        help="On export, also copy into seed/",
    )
    args = p.parse_args()

    try:
        if args.command == "export":
            return cmd_export(args.base, args.also_seed)
        if args.command in ("import", "import-zeabur"):
            return cmd_import(args.base)
        if args.command == "backup-byethost":
            return cmd_backup_byethost(args.base, args.also_seed)
    except urllib.error.URLError as e:
        print(f"Network error: {e}", file=sys.stderr)
        return 1
    except FileNotFoundError as e:
        print(f"Missing file: {e}", file=sys.stderr)
        return 1
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
