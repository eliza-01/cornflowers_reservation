#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import base64
import os
from pathlib import Path
from typing import Iterable


# ===== НАСТРОЙКИ (меняются тут) =====

# Корень проекта: .../root/tools/dump.py -> root = на уровень выше tools
PROJECT_ROOT = Path(__file__).resolve().parents[1]

# Куда писать дамп
OUTPUT_FILE = PROJECT_ROOT / "dump_all_files.txt"

# Пул каталогов/файлов для дампа (указываются прямо тут)
# Сейчас: весь проект целиком (корень проекта)
INCLUDE_PATHS = [
    # Примеры (если нужно ограничить):
    PROJECT_ROOT / "api",
    PROJECT_ROOT / "public",
]

# Какие каталоги игнорировать (по имени папки)
EXCLUDE_DIR_NAMES = {
    ".git", ".svn", ".hg",
    "node_modules",
    "venv", ".venv",
    "__pycache__",
    ".pytest_cache", ".mypy_cache",
    ".idea", ".vscode",
    "dist", "build", "out", ".next",
}

# Какие файлы игнорировать (по имени файла)
EXCLUDE_FILE_NAMES = {
    "package-lock.json",  # если мешает — уберите
}

# Бинарники: "base64" (записывать в base64) или "skip" (пропускать)
BINARY_MODE = "base64"

# Разделитель между файлами в итоговом txt
SEPARATOR = "\n\n"

# Лимит размера: чтобы не уронить процесс на гигабайтных файлах.
# Если хотите строго "всё-всё" — поставьте None (но осторожно).
MAX_FILE_SIZE_MB = 50

# ==================================


def is_excluded_dir(dir_path: Path) -> bool:
    return dir_path.name in EXCLUDE_DIR_NAMES


def is_excluded_file(file_path: Path) -> bool:
    if file_path.name in EXCLUDE_FILE_NAMES:
        return True
    # не включаем сам скрипт и итоговый дамп
    try:
        if file_path.resolve() == Path(__file__).resolve():
            return True
        if file_path.resolve() == OUTPUT_FILE.resolve():
            return True
    except Exception:
        pass
    return False


def iter_files(paths: Iterable[Path]) -> list[Path]:
    collected: list[Path] = []

    for p in paths:
        p = p.resolve()
        if not p.exists():
            continue

        if p.is_file():
            if not is_excluded_file(p):
                collected.append(p)
            continue

        for root, dirnames, filenames in os.walk(p, topdown=True, followlinks=False):
            root_path = Path(root)

            # чистим список папок для обхода (чтобы os.walk не заходил в исключённые)
            dirnames[:] = [d for d in dirnames if d not in EXCLUDE_DIR_NAMES]

            if is_excluded_dir(root_path):
                continue

            for name in filenames:
                fp = root_path / name
                if fp.is_file() and not is_excluded_file(fp):
                    collected.append(fp)

    # стабильный порядок
    collected = sorted(set(collected), key=lambda x: str(x).lower())
    return collected


def is_probably_binary(head: bytes) -> bool:
    if b"\x00" in head:
        return True
    if not head:
        return False
    sample = head[:4096]
    nontext = sum(1 for b in sample if b < 9 or (13 < b < 32) or b == 127)
    return (nontext / max(1, len(sample))) > 0.25


def write_base64(src_path: Path, out_fh) -> None:
    out_fh.write("[BINARY -> BASE64]\n")
    with open(src_path, "rb") as inp:
        base64.encode(inp, out_fh)  # пишет построчно


def rel_path(p: Path) -> str:
    try:
        return str(p.resolve().relative_to(PROJECT_ROOT))
    except Exception:
        return str(p.resolve())


def dump() -> None:
    files = iter_files(INCLUDE_PATHS)

    max_bytes = None
    if MAX_FILE_SIZE_MB is not None:
        max_bytes = int(MAX_FILE_SIZE_MB * 1024 * 1024)

    with open(OUTPUT_FILE, "w", encoding="utf-8", newline="\n") as out:
        for fp in files:
            rp = fp.resolve()

            # имя файла (как просили) — одной строкой
            out.write(f"{rel_path(rp)}\n")

            try:
                size = rp.stat().st_size
            except Exception:
                size = -1

            if max_bytes is not None and size >= 0 and size > max_bytes:
                out.write(f"[SKIPPED: file too large: {size} bytes > {MAX_FILE_SIZE_MB} MB]\n")
                out.write(SEPARATOR)
                continue

            try:
                with open(rp, "rb") as f:
                    head = f.read(8192)

                if is_probably_binary(head):
                    if BINARY_MODE == "skip":
                        out.write(f"[BINARY FILE SKIPPED: {size} bytes]\n")
                    else:
                        write_base64(rp, out)
                else:
                    # Текст: читаем как UTF-8 с заменой ошибок (чтобы не падать)
                    with open(rp, "r", encoding="utf-8", errors="replace") as tf:
                        for line in tf:
                            out.write(line)
                    if not str(head).endswith("\\n"):
                        # если файл не заканчивался переводом строки — добавим
                        if not out.tell():
                            out.write("\n")

            except Exception as e:
                out.write(f"[ERROR reading file: {e.__class__.__name__}: {e}]\n")

            # следующий файл
            out.write(SEPARATOR)

    print(f"OK: written to {OUTPUT_FILE}")


if __name__ == "__main__":
    dump()
