from __future__ import annotations

import json
import re
import sys
from datetime import datetime
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PUBLIC_ROOT = ROOT / "public_html"


HTML_SUFFIXES = {".html", ".php"}
HTML_PATTERNS = [
    (
        re.compile(r'(<link\b[^>]*\brel="manifest"[^>]*\bhref=")[^"]+(")', re.IGNORECASE),
        lambda _match, version: "/manifest.webmanifest?v=" + version,
    ),
    (
        re.compile(r'(<link\b[^>]*\brel="icon"[^>]*\bhref=")[^"]+(")', re.IGNORECASE),
        lambda _match, version: "/assets/images/favicon.png?v=" + version,
    ),
    (
        re.compile(r'(<link\b[^>]*\brel="shortcut icon"[^>]*\bhref=")[^"]+(")', re.IGNORECASE),
        lambda _match, version: "/assets/images/favicon.png?v=" + version,
    ),
    (
        re.compile(r'(<link\b[^>]*\brel="apple-touch-icon"[^>]*\bhref=")[^"]+(")', re.IGNORECASE),
        lambda _match, version: "/assets/icons/apple-touch-icon.png?v=" + version,
    ),
    (
        re.compile(r'(<script\b[^>]*\bsrc=")(?:/assets/site/scripts/pwa\.js\?v=[^"]+|P\d+(?:-\d+)?)("></script>)', re.IGNORECASE),
        lambda _match, version: "/assets/site/scripts/pwa.js?v=" + version,
    ),
]


def build_version() -> str:
    if len(sys.argv) > 1 and sys.argv[1].strip():
        return sys.argv[1].strip()
    return datetime.now().astimezone().strftime("%Y%m%d-%H%M%S")


def replace_all(text: str, patterns: list[tuple[re.Pattern[str], object]], version: str) -> str:
    updated = text
    for pattern, replacement_factory in patterns:
        updated = pattern.sub(
            lambda match: match.group(1) + replacement_factory(match, version) + match.group(2),
            updated,
        )
    return updated


def write_text_if_changed(path: Path, text: str, changed: list[str]) -> None:
    original = path.read_text(encoding="utf-8")
    if original == text:
        return
    path.write_text(text, encoding="utf-8", newline="\n")
    changed.append(str(path.relative_to(ROOT)).replace("\\", "/"))


def stamp_html(version: str, changed: list[str]) -> None:
    for path in PUBLIC_ROOT.rglob("*"):
        if path.suffix.lower() not in HTML_SUFFIXES or not path.is_file():
            continue
        original = path.read_text(encoding="utf-8")
        updated = replace_all(original, HTML_PATTERNS, version)
        if updated != original:
            path.write_text(updated, encoding="utf-8", newline="\n")
            changed.append(str(path.relative_to(ROOT)).replace("\\", "/"))


def stamp_script_versions(version: str, changed: list[str]) -> None:
    pwa_path = PUBLIC_ROOT / "assets" / "site" / "scripts" / "pwa.js"
    pwa_text = pwa_path.read_text(encoding="utf-8")
    pwa_text = re.sub(
        r'var CURRENT_VERSION = "[^"]+";',
        'var CURRENT_VERSION = "{version}";'.format(version=version),
        pwa_text,
        count=1,
    )
    write_text_if_changed(pwa_path, pwa_text, changed)

    sw_path = PUBLIC_ROOT / "sw.js"
    sw_text = sw_path.read_text(encoding="utf-8")
    sw_text = re.sub(
        r'const APP_VERSION = "[^"]+";',
        'const APP_VERSION = "{version}";'.format(version=version),
        sw_text,
        count=1,
    )
    write_text_if_changed(sw_path, sw_text, changed)


def stamp_manifest(version: str, changed: list[str]) -> None:
    manifest_path = PUBLIC_ROOT / "manifest.webmanifest"
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    icons = manifest.get("icons", [])
    desired = [
        "/assets/icons/icon-192.png?v=" + version,
        "/assets/icons/icon-512.png?v=" + version,
        "/assets/icons/icon-maskable-192.png?v=" + version,
        "/assets/icons/icon-maskable-512.png?v=" + version,
    ]
    for index, src in enumerate(desired):
        if index >= len(icons):
            break
        icons[index]["src"] = src
        if "purpose" in icons[index] and "maskable" in str(icons[index]["purpose"]):
            icons[index]["purpose"] = "any maskable"
    updated = json.dumps(manifest, ensure_ascii=False, indent=2) + "\n"
    existing = manifest_path.read_text(encoding="utf-8")
    if existing == updated:
        return
    manifest_path.write_text(updated, encoding="utf-8", newline="\n")
    changed.append(str(manifest_path.relative_to(ROOT)).replace("\\", "/"))


def stamp_app_version_file(version: str, changed: list[str]) -> None:
    payload = {
        "version": version,
        "generatedAt": datetime.now().astimezone().isoformat(timespec="seconds"),
    }
    path = PUBLIC_ROOT / "app-version.json"
    updated = json.dumps(payload, ensure_ascii=False, indent=2) + "\n"
    existing = path.read_text(encoding="utf-8") if path.exists() else ""
    if existing == updated:
        return
    path.write_text(updated, encoding="utf-8", newline="\n")
    changed.append(str(path.relative_to(ROOT)).replace("\\", "/"))


def main() -> int:
    version = build_version()
    changed: list[str] = []

    stamp_html(version, changed)
    stamp_script_versions(version, changed)
    stamp_manifest(version, changed)
    stamp_app_version_file(version, changed)

    print(f"STAMP_VERSION={version}")
    print(f"STAMP_CHANGED={len(changed)}")
    for item in changed:
        print(f"STAMP_FILE={item}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
