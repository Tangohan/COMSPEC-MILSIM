# -*- coding: utf-8 -*-
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP_SUFFIX = "routes/web.php"


def process_file(path: str) -> bool:
    with open(path, "r", encoding="utf-8") as f:
        c = f.read()
    orig = c
    c = c.replace("admin/organization", "back-office")
    c = c.replace("admin/system/", "admin/")
    c = c.replace("url('admin/system')", "url('admin')")
    c = c.replace('url("admin/system")', 'url("admin")')
    if c != orig:
        with open(path, "w", encoding="utf-8", newline="\n") as f:
            f.write(c)
        return True
    return False


def main() -> None:
    for sub in ("app", "views"):
        root = os.path.join(ROOT, sub)
        for dirpath, _, files in os.walk(root):
            for name in files:
                if not name.endswith(".php"):
                    continue
                rel = os.path.relpath(os.path.join(dirpath, name), ROOT).replace("\\", "/")
                if rel == SKIP_SUFFIX or rel.endswith("/" + SKIP_SUFFIX):
                    continue
                p = os.path.join(dirpath, name)
                if process_file(p):
                    print(p)


if __name__ == "__main__":
    main()
    print("OK")
