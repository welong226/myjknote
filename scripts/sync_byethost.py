#!/usr/bin/env python3
"""Upload myjknote site files to byethost via FTP (backup / failover mirror)."""

from __future__ import annotations

import argparse
import ftplib
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

# Paths relative to repo root — not uploaded to byethost
EXCLUDE_DIRS = {
    ".git",
    ".github",
    "scripts",
    "__pycache__",
}
EXCLUDE_FILES = {
    ".gitignore",
    ".dockerignore",
    ".env.byethost",
    ".env.byethost.example",
    "Dockerfile",
    "docker-entrypoint.sh",
    "zbpack.json",
    "composer.json",
    "README.md",
    "BUILD_ID",
}


def load_env(path: Path) -> dict[str, str]:
    env: dict[str, str] = {}
    if not path.is_file():
        return env
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip().strip("'").strip('"')
    return env


def should_skip(rel: Path) -> bool:
    parts = rel.parts
    if any(p in EXCLUDE_DIRS for p in parts):
        return True
    if rel.name in EXCLUDE_FILES:
        return True
    if rel.name.startswith(".env"):
        return True
    return False


def iter_files(root: Path):
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        rel = path.relative_to(root)
        if should_skip(rel):
            continue
        yield path, rel


def ensure_dirs(ftp: ftplib.FTP, remote_dir: str) -> None:
    # remote_dir like /htdocs/Bank or htdocs/Bank
    parts = [p for p in remote_dir.replace("\\", "/").split("/") if p]
    path = ""
    for part in parts:
        path += "/" + part
        try:
            ftp.mkd(path)
        except ftplib.error_perm:
            pass


def upload(ftp: ftplib.FTP, local: Path, remote: str) -> None:
    parent = remote.rsplit("/", 1)[0]
    if parent:
        ensure_dirs(ftp, parent)
    with local.open("rb") as f:
        ftp.storbinary(f"STOR {remote}", f)


def main() -> int:
    parser = argparse.ArgumentParser(description="Sync myjknote → byethost FTP")
    parser.add_argument(
        "--env",
        default=str(ROOT / ".env.byethost"),
        help="Path to env file (default: .env.byethost)",
    )
    parser.add_argument("--dry-run", action="store_true", help="List files only")
    args = parser.parse_args()

    file_env = load_env(Path(args.env))
    server = os.environ.get("FTP_SERVER") or file_env.get("FTP_SERVER", "")
    user = os.environ.get("FTP_USERNAME") or file_env.get("FTP_USERNAME", "")
    password = os.environ.get("FTP_PASSWORD") or file_env.get("FTP_PASSWORD", "")
    port = int(os.environ.get("FTP_PORT") or file_env.get("FTP_PORT") or "21")
    server_dir = (
        os.environ.get("FTP_SERVER_DIR")
        or file_env.get("FTP_SERVER_DIR")
        or "/htdocs/"
    ).rstrip("/") + "/"

    if not args.dry_run and (not server or not user or not password):
        print(
            "Missing FTP_SERVER / FTP_USERNAME / FTP_PASSWORD.\n"
            f"Copy .env.byethost.example → {args.env} and fill in values.",
            file=sys.stderr,
        )
        return 1

    files = list(iter_files(ROOT))
    print(f"Root: {ROOT}")
    print(f"Remote: {server}:{port}{server_dir}")
    print(f"Files: {len(files)}")

    if args.dry_run:
        for _, rel in files[:30]:
            print(f"  {rel}")
        if len(files) > 30:
            print(f"  ... +{len(files) - 30} more")
        return 0

    ftp = ftplib.FTP()
    ftp.connect(server, port, timeout=60)
    ftp.login(user, password)
    ftp.set_pasv(True)

    ok = 0
    for local, rel in files:
        remote = server_dir + rel.as_posix()
        try:
            upload(ftp, local, remote)
            ok += 1
            print(f"OK  {rel}")
        except Exception as e:  # noqa: BLE001
            print(f"FAIL {rel}: {e}", file=sys.stderr)

    try:
        ftp.quit()
    except Exception:
        ftp.close()

    print(f"Done: {ok}/{len(files)} uploaded")
    return 0 if ok == len(files) else 2


if __name__ == "__main__":
    raise SystemExit(main())
