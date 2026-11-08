#!/usr/bin/env python3
"""Build portable PO/MO files from the checked-in Persian catalog."""
import json
from pathlib import Path
import re
import struct

ROOT = Path(__file__).resolve().parents[1]


def main():
    mapping = json.loads((ROOT / "tools/fa_IR.json").read_text())
    sources = [ROOT / "sobelz-embla-carousel-widget.php", *(ROOT / "includes").glob("*.php")]
    strings = set()
    for source in sources:
        strings.update(re.findall(r"(?:__|esc_html__|esc_attr__|esc_attr_e)\(\s*'([^']*)',\s*'sobelz-embla-carousel-widget'", source.read_text()))
    missing = strings - mapping.keys()
    if missing:
        raise ValueError(f"Untranslated strings: {sorted(missing)}")
    header = "Project-Id-Version: sobelz-embla-carousel-widget 1.1.0\nLanguage: fa_IR\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nPlural-Forms: nplurals=2; plural=(n > 1);\n"
    messages = {"": header, **{key: mapping[key] for key in sorted(strings)}}
    destination = ROOT / "languages"
    destination.mkdir(exist_ok=True)
    po = "# Persian translations for sobelz-embla-carousel-widget.\n"
    for key, value in messages.items():
        po += f"msgid {json.dumps(key, ensure_ascii=False)}\nmsgstr {json.dumps(value, ensure_ascii=False)}\n\n"
    (destination / "sobelz-embla-carousel-widget-fa_IR.po").write_text(po.rstrip() + "\n")
    items = sorted(messages.items())
    originals = b""
    translations = b""
    original_table = []
    translated_table = []
    for key, value in items:
        key_bytes, value_bytes = key.encode(), value.encode()
        original_table.append((len(key_bytes), len(originals)))
        translated_table.append((len(value_bytes), len(translations)))
        originals += key_bytes + b"\0"
        translations += value_bytes + b"\0"
    count = len(items)
    original_start = 28 + 16 * count
    translated_start = original_start + len(originals)
    data = struct.pack("<7I", 0x950412DE, 0, count, 28, 28 + 8 * count, 0, 0)
    for length, offset in original_table:
        data += struct.pack("<2I", length, original_start + offset)
    for length, offset in translated_table:
        data += struct.pack("<2I", length, translated_start + offset)
    data += originals + translations
    (destination / "sobelz-embla-carousel-widget-fa_IR.mo").write_bytes(data)
    print(f"Built Persian catalog: {len(strings)} strings")


if __name__ == "__main__":
    main()
