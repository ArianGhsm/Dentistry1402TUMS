from __future__ import annotations

import argparse
import json
import re
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
]

ASSET_VERSION_PATTERNS = [
    re.compile(r'(<link\b[^>]*\brel="stylesheet"[^>]*\bhref=")(/assets/site/styles/[^"?]+\.css)(?:\?v=[^"]*)?(")', re.IGNORECASE),
    re.compile(r'(<script\b[^>]*\bsrc=")(/assets/site/scripts/[^"?]+\.js)(?:\?v=[^"]*)?(")', re.IGNORECASE),
]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stamp or preview the shared PWA version across public_html.")
    parser.add_argument("version", nargs="?", help="Optional explicit version token.")
    parser.add_argument("--dry-run", action="store_true", help="Preview changed files without writing them.")
    return parser.parse_args()


def build_version(explicit_version: str | None) -> str:
    if explicit_version and explicit_version.strip():
        return explicit_version.strip()
    return datetime.now().astimezone().strftime("%Y%m%d-%H%M%S")


def relative_path(path: Path) -> str:
    return str(path.relative_to(ROOT)).replace("\\", "/")


def replace_all(text: str, patterns: list[tuple[re.Pattern[str], object]], version: str) -> str:
    updated = text
    for pattern, replacement_factory in patterns:
        updated = pattern.sub(
            lambda match: match.group(1) + replacement_factory(match, version) + match.group(2),
            updated,
        )
    return updated


def stamp_asset_versions(text: str, version: str) -> str:
    updated = text
    for pattern in ASSET_VERSION_PATTERNS:
        updated = pattern.sub(
            lambda match: match.group(1) + match.group(2) + "?v=" + version + match.group(3),
            updated,
        )
    return updated


def record_changed_text(path: Path, updated_text: str, changed: list[str], dry_run: bool) -> None:
    original = path.read_text(encoding="utf-8")
    if original == updated_text:
        return
    if not dry_run:
        path.write_text(updated_text, encoding="utf-8", newline="\n")
    changed.append(relative_path(path))


def stamp_html(version: str, changed: list[str], dry_run: bool) -> None:
    for path in PUBLIC_ROOT.rglob("*"):
        if path.suffix.lower() not in HTML_SUFFIXES or not path.is_file():
            continue
        original = path.read_text(encoding="utf-8")
        updated = replace_all(original, HTML_PATTERNS, version)
        updated = stamp_asset_versions(updated, version)
        if updated != original:
            if not dry_run:
                path.write_text(updated, encoding="utf-8", newline="\n")
            changed.append(relative_path(path))


def stamp_script_versions(version: str, changed: list[str], dry_run: bool) -> None:
    pwa_path = PUBLIC_ROOT / "assets" / "site" / "scripts" / "pwa.js"
    pwa_text = pwa_path.read_text(encoding="utf-8")
    pwa_text = re.sub(
        r'var CURRENT_VERSION = "[^"]+";',
        'var CURRENT_VERSION = "{version}";'.format(version=version),
        pwa_text,
        count=1,
    )
    record_changed_text(pwa_path, pwa_text, changed, dry_run)

    sw_path = PUBLIC_ROOT / "sw.js"
    sw_text = sw_path.read_text(encoding="utf-8")
    sw_text = re.sub(
        r'const APP_VERSION = "[^"]+";',
        'const APP_VERSION = "{version}";'.format(version=version),
        sw_text,
        count=1,
    )
    record_changed_text(sw_path, sw_text, changed, dry_run)


def stamp_manifest(version: str, changed: list[str], dry_run: bool) -> None:
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
    record_changed_text(manifest_path, updated, changed, dry_run)


def stamp_app_version_file(version: str, changed: list[str], dry_run: bool) -> None:
    payload = {
        "version": version,
        "generatedAt": datetime.now().astimezone().isoformat(timespec="seconds"),
    }
    path = PUBLIC_ROOT / "app-version.json"
    updated = json.dumps(payload, ensure_ascii=False, indent=2) + "\n"
    existing = path.read_text(encoding="utf-8") if path.exists() else ""
    if existing == updated:
        return
    if not dry_run:
        path.write_text(updated, encoding="utf-8", newline="\n")
    changed.append(relative_path(path))


def main() -> int:
    args = parse_args()
    version = build_version(args.version)
    changed: list[str] = []

    stamp_html(version, changed, args.dry_run)
    stamp_script_versions(version, changed, args.dry_run)
    stamp_manifest(version, changed, args.dry_run)
    stamp_app_version_file(version, changed, args.dry_run)

    print(f"STAMP_VERSION={version}")
    print(f"STAMP_CHANGED={len(changed)}")
    for item in changed:
        print(f"STAMP_FILE={item}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
