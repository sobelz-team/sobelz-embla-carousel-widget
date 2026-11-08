#!/usr/bin/env python3
"""Create a WordPress-installable archive containing only runtime files."""
import hashlib
import json
from pathlib import Path
import shutil
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = "sobelz-embla-carousel-widget"
VERSION = "1.1.0"
RUNTIME = ["sobelz-embla-carousel-widget.php", "includes", "assets", "languages", "readme.txt", "README.fa.md", "LICENSE", "THIRD-PARTY.md"]


def main():
    manifest = json.loads((ROOT / "assets/vendor/manifest.json").read_text())
    for asset in manifest:
        path = ROOT / "assets/vendor" / asset["file"]
        if hashlib.sha256(path.read_bytes()).hexdigest() != asset["sha256"]:
            raise ValueError(f"Vendor file changed: {path}")
        if not (path.parent / "LICENSE").is_file():
            raise ValueError(f"Missing license: {path}")
    destination = ROOT / "dist" / SLUG
    if destination.exists():
        shutil.rmtree(destination)
    destination.mkdir(parents=True)
    for name in RUNTIME:
        source = ROOT / name
        if source.is_dir():
            shutil.copytree(source, destination / name)
        else:
            shutil.copy2(source, destination / name)
    archive = ROOT / "dist" / f"{SLUG}-{VERSION}.zip"
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as zip_file:
        for path in sorted(destination.rglob("*")):
            if path.is_file():
                zip_file.write(path, f"{SLUG}/{path.relative_to(destination)}")
    print(f"{archive} ({archive.stat().st_size:,} bytes)")


if __name__ == "__main__":
    main()
