#!/usr/bin/env python3
"""Create a WordPress-installable archive containing only runtime files."""
import hashlib
import json
from pathlib import Path
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = "sobelz-embla-carousel-widget"
RUNTIME = ["sobelz-embla-carousel-widget.php", "includes", "assets", "languages", "readme.txt", "README.fa.md", "LICENSE", "THIRD-PARTY.md"]


def main():
    release = json.loads((ROOT / "update.json").read_text())
    bootstrap = (ROOT / f"{SLUG}.php").read_text()
    readme = (ROOT / "readme.txt").read_text()
    versions = {
        "plugin header": re.search(r"^ \* Version:\s*(\S+)", bootstrap, re.M)[1],
        "EEW_VERSION": re.search(r"define\( 'EEW_VERSION', '([^']+)' \)", bootstrap)[1],
        "readme": re.search(r"^Stable tag:\s*(\S+)", readme, re.M)[1],
        "update.json": release["version"],
        "package.json": json.loads((ROOT / "package.json").read_text())["version"],
        "package-lock.json": json.loads((ROOT / "package-lock.json").read_text())["version"],
    }
    if len(set(versions.values())) != 1:
        raise ValueError(f"Release version mismatch: {versions}")
    if release["slug"] != SLUG:
        raise ValueError("The update manifest slug does not match the plugin")
    update_uri = re.search(r"^ \* Update URI:\s*(\S+)", bootstrap, re.M)[1]
    if release["homepage"].rstrip("/") != update_uri.rstrip("/"):
        raise ValueError("The update manifest homepage does not match Update URI")
    version = release["version"]
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
    archive = ROOT / "dist" / f"{SLUG}-{version}.zip"
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as zip_file:
        for path in sorted(destination.rglob("*")):
            if path.is_file():
                zip_file.write(path, f"{SLUG}/{path.relative_to(destination)}")
    # Publish these two files side by side, matching the manifest download URL.
    shutil.copy2(archive, ROOT / "dist" / f"{SLUG}.zip")
    shutil.copy2(ROOT / "update.json", ROOT / "dist/update.json")
    print(f"{archive} ({archive.stat().st_size:,} bytes)")
    print(f"Update feed: {ROOT / 'dist/update.json'}")
    print(f"Update package: {ROOT / 'dist' / f'{SLUG}.zip'}")


if __name__ == "__main__":
    main()
