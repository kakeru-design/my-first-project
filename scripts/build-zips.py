#!/usr/bin/env python3
"""WordPress の管理画面からアップロードできる zip を dist/ に作る。

使い方: python3 scripts/build-zips.py
"""
import pathlib
import zipfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
OUT = ROOT / "dist"

PACKAGES = {
    # zip名: (ソース, 単一ファイルか)
    "affiliate-base": ("shared/themes/affiliate-base", False),
    "affiliate-toolkit": ("shared/mu-plugins/affiliate-toolkit.php", True),
    "adult-child": ("sites/adult/themes/adult-child", False),
    "fanza-connector": ("sites/adult/plugins/fanza-connector", False),
    "uranai-child": ("sites/uranai/themes/uranai-child", False),
    "uranai-tools": ("sites/uranai/plugins/uranai-tools", False),
}


def main():
    OUT.mkdir(exist_ok=True)
    for name, (rel, single) in PACKAGES.items():
        src = ROOT / rel
        with zipfile.ZipFile(OUT / f"{name}.zip", "w", zipfile.ZIP_DEFLATED) as z:
            if single:
                z.write(src, f"{name}/{src.name}")
            else:
                for f in sorted(src.rglob("*")):
                    if f.is_file():
                        z.write(f, f"{name}/{f.relative_to(src)}")
        print(f"dist/{name}.zip")


if __name__ == "__main__":
    main()
