#!/usr/bin/env python3
"""Download fixed upstream UMD distributions; never runs in WordPress."""
import base64
import hashlib
import io
import json
from pathlib import Path
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[1] / "assets" / "vendor"
PACKAGES = [
    ("embla-carousel", "8.6.0"),
    ("embla-carousel-autoplay", "8.6.0"),
    ("embla-carousel-auto-scroll", "8.6.0"),
    ("embla-carousel-auto-height", "8.6.0"),
    ("embla-carousel-class-names", "8.6.0"),
    ("embla-carousel-fade", "8.6.0"),
    ("embla-carousel-wheel-gestures", "8.0.2"),
]


def fetch(url):
    # curl honours the host's proxy configuration and certificate store.
    return subprocess.check_output([
        "curl", "--fail", "--silent", "--show-error", "--location",
        "--retry", "2", "--max-time", "60", url,
    ])


def main():
    manifest = []
    for name, version in PACKAGES:
        meta = json.loads(fetch(f"https://registry.npmjs.org/{name}/{version}"))
        archive = fetch(meta["dist"]["tarball"])
        algorithm, expected = meta["dist"]["integrity"].split("-", 1)
        actual = base64.b64encode(hashlib.new(algorithm, archive).digest()).decode()
        if actual != expected:
            raise ValueError(f"Integrity mismatch: {name}")
        folder = ROOT / name
        folder.mkdir(parents=True, exist_ok=True)
        source = f"package/{name}.umd.js"
        if name.endswith("wheel-gestures"):
            source = f"package/dist/{name}.umd.js"
        with tarfile.open(fileobj=io.BytesIO(archive), mode="r:gz") as tar:
            files = [source]
            if source + ".map" in tar.getnames():
                files.append(source + ".map")
            licenses = [p for p in tar.getnames() if p.lower() in ("package/license", "package/license.md", "package/license.txt")]
            files += licenses
            for path in files:
                (folder / Path(path).name).write_bytes(tar.extractfile(path).read())
            if not licenses:
                # Upstream npm distributions omit their MIT license files.
                wheel = name.endswith("wheel-gestures")
                license_name = "embla-carousel-wheel-gestures" if wheel else "embla-carousel"
                cached_license = ROOT / license_name / "LICENSE"
                license_url = (
                    "https://raw.githubusercontent.com/xiel/embla-carousel-wheel-gestures/master/LICENSE"
                    if wheel else "https://raw.githubusercontent.com/davidjerleke/embla-carousel/master/LICENSE"
                )
                data = cached_license.read_bytes() if cached_license.exists() else fetch(license_url)
                (folder / "LICENSE").write_bytes(data)
                if wheel:
                    # The embedded wheel-gestures library has the same MIT
                    # notice and copyright holder as this upstream plugin.
                    (folder / "LICENSE-wheel-gestures").write_bytes(data)
        manifest.append({
            "name": name, "version": version, "license": meta["license"],
            "source": meta["dist"]["tarball"], "integrity": meta["dist"]["integrity"],
            "file": f"{name}/{name}.umd.js",
            "sha256": hashlib.sha256((folder / f"{name}.umd.js").read_bytes()).hexdigest(),
        })
        print(f"Vendored {name}@{version}")
    (ROOT / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n")


if __name__ == "__main__":
    main()
