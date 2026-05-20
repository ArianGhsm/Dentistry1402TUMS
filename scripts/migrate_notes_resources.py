from __future__ import annotations

import argparse
import hashlib
import json
import mimetypes
import os
import re
import socket
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from ftplib import FTP, all_errors as ftp_errors
from pathlib import Path
from typing import Any


PROJECT_ROOT = Path(__file__).resolve().parents[1]
NOTES_STORAGE_ROOT = PROJECT_ROOT / "server-only" / "storage" / "notes"
DEFAULT_BACKUP_ROOT = Path(r"D:\Arian's Documents\Lessons-Works-Projects\AI-Dev\DL-Dentistry1402TUMS")
DEFAULT_SECRET_PATH = PROJECT_ROOT / ".codex-local" / "mihan-download-host.json"
DEFAULT_LIVE_BASE_URL = "https://dentistry1402tums.ir"
DEFAULT_OWNER_LOGIN = {
    "studentNumber": "40211272003",
    "password": "AAbb11__",
}
MANIFEST_DIR_NAME = "_migration"
MANIFEST_FILE_NAME = "resource_manifest.json"

PERSIAN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
KNOWN_DIRECT_FILE_EXTENSIONS = {
    ".pdf",
    ".zip",
    ".rar",
    ".7z",
    ".jpg",
    ".jpeg",
    ".png",
    ".webp",
    ".gif",
    ".mp3",
    ".wav",
    ".ogg",
    ".mp4",
    ".webm",
    ".ppt",
    ".pptx",
    ".doc",
    ".docx",
    ".xls",
    ".xlsx",
}
CONTENT_TYPE_EXTENSION_OVERRIDES = {
    "application/pdf": ".pdf",
    "application/zip": ".zip",
    "application/x-zip-compressed": ".zip",
    "application/x-rar-compressed": ".rar",
    "application/vnd.rar": ".rar",
    "application/x-7z-compressed": ".7z",
    "image/jpeg": ".jpg",
    "image/png": ".png",
    "image/webp": ".webp",
    "image/gif": ".gif",
    "audio/mpeg": ".mp3",
    "audio/wav": ".wav",
    "audio/ogg": ".ogg",
    "video/mp4": ".mp4",
    "video/webm": ".webm",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document": ".docx",
    "application/msword": ".doc",
    "application/vnd.openxmlformats-officedocument.presentationml.presentation": ".pptx",
    "application/vnd.ms-powerpoint": ".ppt",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": ".xlsx",
    "application/vnd.ms-excel": ".xls",
}


def load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def dump_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def normalize_digits(value: str) -> str:
    return value.translate(PERSIAN_DIGITS)


def extract_visible_term_number(title: str, fallback: int) -> int:
    normalized = normalize_digits(title)
    match = re.search(r"(\d+)", normalized)
    if not match:
        return fallback
    try:
        return max(1, int(match.group(1)))
    except ValueError:
        return fallback


def sanitize_ascii_segment(value: str, fallback: str) -> str:
    cleaned = normalize_digits(value).lower()
    cleaned = re.sub(r"[^a-z0-9]+", "-", cleaned)
    cleaned = cleaned.strip("-")
    return cleaned or fallback


def build_relative_dir(cohort: str, section_slug: str) -> str:
    return f"{cohort}/{section_slug}".replace("\\", "/")


def make_entry(
    *,
    cohort: str,
    api_term: int,
    section_slug: str,
    item: dict[str, Any],
    source_store: str,
    term_title: str = "",
) -> dict[str, Any]:
    item_id = int(item.get("id", 0))
    title = str(item.get("title", "")).strip()
    button_url = str(item.get("buttonUrl", "")).strip()
    relative_dir = build_relative_dir(cohort, section_slug)
    return {
        "cohort": cohort,
        "apiTerm": api_term,
        "sectionSlug": section_slug,
        "relativeDir": relative_dir,
        "itemId": item_id,
        "badge": str(item.get("badge", "")).strip(),
        "title": title,
        "description": str(item.get("description", "")).strip(),
        "buttonLabel": str(item.get("buttonLabel", "")).strip(),
        "oldUrl": button_url,
        "termTitle": term_title,
        "sourceStore": source_store,
        "storageName": "",
        "backupRelativePath": "",
        "downloadStatus": "pending",
        "uploadStatus": "pending",
        "liveUpdateStatus": "pending",
    }


def collect_entries() -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []

    store_1402 = load_json(NOTES_STORAGE_ROOT / "1402_terms.json")
    for term_key, term_record in sorted((store_1402.get("terms") or {}).items(), key=lambda item: int(item[0])):
        term_number = int(term_key)
        section_slug = f"term-{term_number:02d}"
        for item in term_record.get("items") or []:
            if not isinstance(item, dict):
                continue
            entries.append(
                make_entry(
                    cohort="1402",
                    api_term=term_number,
                    section_slug=section_slug,
                    item=item,
                    source_store="notes/1402_terms.json",
                    term_title=str(term_record.get("title", "")).strip(),
                )
            )

    store_1403 = load_json(NOTES_STORAGE_ROOT / "1403_archive.json")
    archive = store_1403.get("archive") or {}
    for item in archive.get("items") or []:
        if not isinstance(item, dict):
            continue
        entries.append(
            make_entry(
                cohort="1403",
                api_term=0,
                section_slug="archive",
                item=item,
                source_store="notes/1403_archive.json",
                term_title=str(archive.get("title", "")).strip(),
            )
        )

    store_prosthesis = load_json(NOTES_STORAGE_ROOT / "prosthesis_1402_terms.json")
    for term_key, term_record in sorted((store_prosthesis.get("terms") or {}).items(), key=lambda item: int(item[0])):
        internal_term_id = int(term_key)
        visible_term_number = extract_visible_term_number(str(term_record.get("title", "")), internal_term_id)
        section_slug = f"term-{visible_term_number:02d}"
        for item in term_record.get("items") or []:
            if not isinstance(item, dict):
                continue
            entries.append(
                make_entry(
                    cohort="prosthesis-1402",
                    api_term=internal_term_id,
                    section_slug=section_slug,
                    item=item,
                    source_store="notes/prosthesis_1402_terms.json",
                    term_title=str(term_record.get("title", "")).strip(),
                )
            )

    entries.sort(key=lambda item: (item["cohort"], item["relativeDir"], item["itemId"]))
    return entries


def build_manifest(backup_root: Path) -> dict[str, Any]:
    return {
        "generatedAt": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        "backupRoot": str(backup_root),
        "entries": collect_entries(),
    }


def manifest_path(backup_root: Path) -> Path:
    return backup_root / MANIFEST_DIR_NAME / MANIFEST_FILE_NAME


def save_manifest(backup_root: Path, manifest: dict[str, Any]) -> Path:
    path = manifest_path(backup_root)
    dump_json(path, manifest)
    return path


def load_or_build_manifest(backup_root: Path, refresh: bool) -> tuple[dict[str, Any], Path]:
    path = manifest_path(backup_root)
    if refresh or not path.is_file():
        manifest = build_manifest(backup_root)
        save_manifest(backup_root, manifest)
        return manifest, path
    return load_json(path), path


def create_no_proxy_opener() -> urllib.request.OpenerDirector:
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    opener.addheaders = [
        ("User-Agent", "Dentistry1402TUMS-ResourceMigrator/1.0"),
        ("Accept", "*/*"),
    ]
    return opener


def normalize_request_url(url: str) -> str:
    parts = urllib.parse.urlsplit(url)
    path = urllib.parse.quote(parts.path, safe="/%")
    query = urllib.parse.quote(parts.query, safe="=&%/:?+-_.!~*'(),")
    fragment = urllib.parse.quote(parts.fragment, safe="%")
    return urllib.parse.urlunsplit((parts.scheme, parts.netloc, path, query, fragment))


def parse_content_disposition_filename(header_value: str) -> str:
    if not header_value:
        return ""
    match = re.search(r"filename\*=UTF-8''([^;]+)", header_value, flags=re.IGNORECASE)
    if match:
        return urllib.parse.unquote(match.group(1)).strip().strip('"')
    match = re.search(r'filename="([^"]+)"', header_value, flags=re.IGNORECASE)
    if match:
        return match.group(1).strip()
    match = re.search(r"filename=([^;]+)", header_value, flags=re.IGNORECASE)
    if match:
        return match.group(1).strip().strip('"')
    return ""


def guess_extension(content_type: str, filename_hint: str, final_url: str) -> str:
    hint_name = filename_hint.strip()
    if hint_name:
        suffix = Path(hint_name).suffix.lower()
        if suffix:
            return suffix

    url_suffix = Path(urllib.parse.urlparse(final_url).path).suffix.lower()
    if url_suffix:
        return url_suffix

    lowered_content_type = content_type.split(";", 1)[0].strip().lower()
    if lowered_content_type in CONTENT_TYPE_EXTENSION_OVERRIDES:
        return CONTENT_TYPE_EXTENSION_OVERRIDES[lowered_content_type]

    guessed = mimetypes.guess_extension(lowered_content_type, strict=False)
    return guessed or ".bin"


def make_storage_name(entry: dict[str, Any], extension: str) -> str:
    cohort_slug = sanitize_ascii_segment(entry["cohort"], "cohort")
    section_slug = sanitize_ascii_segment(entry["sectionSlug"], "section")
    return f"resource-{cohort_slug}-{section_slug}-{int(entry['itemId']):04d}{extension}"


def should_skip_html_response(content_type: str, candidate_name: str, final_url: str) -> bool:
    lowered = content_type.split(";", 1)[0].strip().lower()
    if lowered not in {"text/html", "application/xhtml+xml"}:
        return False
    suffix = Path(candidate_name).suffix.lower()
    if suffix in KNOWN_DIRECT_FILE_EXTENSIONS:
        return False
    final_suffix = Path(urllib.parse.urlparse(final_url).path).suffix.lower()
    if final_suffix in KNOWN_DIRECT_FILE_EXTENSIONS:
        return False
    return True


def update_entry_status(entry: dict[str, Any], **fields: Any) -> None:
    entry.update(fields)


def download_entry(opener: urllib.request.OpenerDirector, backup_root: Path, entry: dict[str, Any]) -> None:
    url = entry["oldUrl"]
    parsed = urllib.parse.urlparse(url)
    if parsed.scheme not in {"http", "https"}:
        update_entry_status(entry, downloadStatus="skipped", skipReason="unsupported-scheme")
        return

    request = urllib.request.Request(normalize_request_url(url))
    try:
        with opener.open(request, timeout=90) as response:
            final_url = response.geturl()
            content_type = response.headers.get("Content-Type", "application/octet-stream")
            content_length_raw = response.headers.get("Content-Length", "").strip()
            content_disposition = response.headers.get("Content-Disposition", "")
            source_filename = parse_content_disposition_filename(content_disposition)
            extension = guess_extension(content_type, source_filename, final_url)
            storage_name = make_storage_name(entry, extension)
            relative_path = Path(entry["relativeDir"]) / storage_name
            absolute_path = backup_root / relative_path
            absolute_path.parent.mkdir(parents=True, exist_ok=True)

            if should_skip_html_response(content_type, source_filename or storage_name, final_url):
                update_entry_status(
                    entry,
                    downloadStatus="skipped",
                    skipReason="html-page",
                    finalUrl=final_url,
                    contentType=content_type,
                    storageName=storage_name,
                    backupRelativePath=str(relative_path).replace("\\", "/"),
                )
                return

            expected_size = int(content_length_raw) if content_length_raw.isdigit() else 0
            if absolute_path.is_file():
                existing_size = absolute_path.stat().st_size
                if expected_size > 0 and existing_size == expected_size:
                    update_entry_status(
                        entry,
                        downloadStatus="downloaded",
                        finalUrl=final_url,
                        contentType=content_type,
                        sourceFilename=source_filename,
                        storageName=storage_name,
                        backupRelativePath=str(relative_path).replace("\\", "/"),
                        backupAbsolutePath=str(absolute_path),
                        bytes=existing_size,
                        reusedExistingFile=True,
                    )
                    return

            temp_path = absolute_path.with_suffix(absolute_path.suffix + ".part")
            digest = hashlib.sha256()
            total = 0
            with temp_path.open("wb") as handle:
                while True:
                    chunk = response.read(1024 * 1024)
                    if not chunk:
                        break
                    handle.write(chunk)
                    digest.update(chunk)
                    total += len(chunk)
            temp_path.replace(absolute_path)

            update_entry_status(
                entry,
                downloadStatus="downloaded",
                finalUrl=final_url,
                contentType=content_type,
                sourceFilename=source_filename,
                storageName=storage_name,
                backupRelativePath=str(relative_path).replace("\\", "/"),
                backupAbsolutePath=str(absolute_path),
                bytes=total,
                sha256=digest.hexdigest(),
                reusedExistingFile=False,
            )
    except urllib.error.HTTPError as error:
        update_entry_status(entry, downloadStatus="error", error=f"http-{error.code}")
    except urllib.error.URLError as error:
        update_entry_status(entry, downloadStatus="error", error=f"url-{error.reason}")
    except TimeoutError:
        update_entry_status(entry, downloadStatus="error", error="timeout")
    except OSError as error:
        update_entry_status(entry, downloadStatus="error", error=str(error))


def load_secret(path: Path) -> dict[str, Any]:
    if not path.is_file():
        raise FileNotFoundError(f"Secret file not found: {path}")
    return load_json(path)


def ensure_host_resolves(host: str) -> None:
    socket.getaddrinfo(host, None)


def ftp_ensure_dirs(ftp: FTP, parts: list[str]) -> None:
    root = ftp.pwd()
    try:
        for part in parts:
            try:
                ftp.cwd(part)
            except ftp_errors:
                ftp.mkd(part)
                ftp.cwd(part)
    finally:
        ftp.cwd(root)


def ftp_store_file(ftp: FTP, local_path: Path, remote_dir: str, remote_name: str) -> None:
    parts = [part for part in remote_dir.split("/") if part]
    root = ftp.pwd()
    ftp_ensure_dirs(ftp, parts)
    try:
        for part in parts:
            ftp.cwd(part)
        with local_path.open("rb") as handle:
            ftp.storbinary(f"STOR {remote_name}", handle, blocksize=1024 * 256)
    finally:
        ftp.cwd(root)


def upload_manifest_entries(manifest: dict[str, Any], secret: dict[str, Any]) -> None:
    domain = str(secret.get("domain", "")).strip()
    username = str(secret.get("username", "")).strip()
    password = str(secret.get("password", "")).strip()
    if not domain or not username or not password:
        raise RuntimeError("Download-host credentials are incomplete.")

    ensure_host_resolves(domain)
    ftp = FTP()
    ftp.connect(domain, 21, timeout=60)
    ftp.login(username, password)
    ftp.set_pasv(True)
    try:
        for entry in manifest["entries"]:
            if entry.get("downloadStatus") != "downloaded":
                if entry.get("downloadStatus") == "skipped":
                    entry["uploadStatus"] = "skipped"
                continue
            local_path = Path(str(entry.get("backupAbsolutePath", "")).strip())
            storage_name = str(entry.get("storageName", "")).strip()
            relative_dir = str(entry.get("relativeDir", "")).strip()
            if not local_path.is_file() or not storage_name or not relative_dir:
                entry["uploadStatus"] = "error"
                entry["error"] = "missing-local-file"
                continue

            ftp_store_file(ftp, local_path, relative_dir, storage_name)
            public_url = "https://" + domain.rstrip("/") + "/" + relative_dir.strip("/") + "/" + urllib.parse.quote(storage_name)
            update_entry_status(
                entry,
                uploadStatus="uploaded",
                uploadedUrl=public_url,
                uploadedAt=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
            )
    finally:
        try:
            ftp.quit()
        except ftp_errors:
            ftp.close()


def request_json(
    opener: urllib.request.OpenerDirector,
    url: str,
    data: dict[str, Any] | None = None,
) -> dict[str, Any]:
    payload = None
    headers = {
        "Accept": "application/json",
    }
    if data is not None:
        payload = urllib.parse.urlencode({key: str(value) for key, value in data.items()}).encode("utf-8")
        headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8"
    request = urllib.request.Request(url, data=payload, headers=headers)
    with opener.open(request, timeout=60) as response:
        raw = response.read().decode("utf-8", errors="replace")
        return json.loads(raw)


def update_live_links(manifest: dict[str, Any], live_base_url: str, owner_login: dict[str, str]) -> None:
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(),
    )
    opener.addheaders = [("User-Agent", "Dentistry1402TUMS-ResourceMigrator/1.0")]

    login_url = live_base_url.rstrip("/") + "/api/auth_api.php?action=login"
    login_response = request_json(opener, login_url, owner_login)
    if not login_response.get("success"):
        raise RuntimeError("Live owner login failed.")

    notes_url = live_base_url.rstrip("/") + "/api/notes_api.php?action=editItem"
    for entry in manifest["entries"]:
        if entry.get("uploadStatus") != "uploaded":
            continue
        uploaded_url = str(entry.get("uploadedUrl", "")).strip()
        if not uploaded_url:
            continue
        if uploaded_url == str(entry.get("oldUrl", "")).strip():
            entry["liveUpdateStatus"] = "skipped"
            continue

        payload = {
            "cohort": entry["cohort"],
            "term": entry["apiTerm"],
            "itemId": entry["itemId"],
            "badge": entry["badge"],
            "title": entry["title"],
            "description": entry["description"],
            "buttonLabel": entry["buttonLabel"],
            "buttonUrl": uploaded_url,
        }
        response = request_json(opener, notes_url, payload)
        if not response.get("success"):
            entry["liveUpdateStatus"] = "error"
            entry["error"] = response.get("error", "live-update-failed")
            continue

        update_entry_status(
            entry,
            liveUpdateStatus="updated",
            liveUpdatedAt=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        )


def print_summary(manifest: dict[str, Any]) -> None:
    entries = manifest["entries"]
    counts: dict[str, int] = {}
    for entry in entries:
        status = str(entry.get("downloadStatus", "pending"))
        counts[status] = counts.get(status, 0) + 1
    print(f"Entries: {len(entries)}")
    print("Download status:", counts)
    upload_counts: dict[str, int] = {}
    for entry in entries:
        status = str(entry.get("uploadStatus", "pending"))
        upload_counts[status] = upload_counts.get(status, 0) + 1
    print("Upload status:", upload_counts)
    live_counts: dict[str, int] = {}
    for entry in entries:
        status = str(entry.get("liveUpdateStatus", "pending"))
        live_counts[status] = live_counts.get(status, 0) + 1
    print("Live update status:", live_counts)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Backup, upload, and relink notes/resources assets.")
    parser.add_argument(
        "action",
        choices=["inventory", "download", "upload", "update-live"],
        help="Operation to run.",
    )
    parser.add_argument(
        "--backup-root",
        default=str(DEFAULT_BACKUP_ROOT),
        help="Absolute backup root for downloaded resource files.",
    )
    parser.add_argument(
        "--secret-path",
        default=str(DEFAULT_SECRET_PATH),
        help="Local ignored JSON file containing download-host credentials.",
    )
    parser.add_argument(
        "--live-base-url",
        default=DEFAULT_LIVE_BASE_URL,
        help="Live site base URL for notes API updates.",
    )
    parser.add_argument(
        "--refresh-manifest",
        action="store_true",
        help="Rebuild the manifest from storage before running the action.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    backup_root = Path(args.backup_root)
    backup_root.mkdir(parents=True, exist_ok=True)
    manifest, manifest_file = load_or_build_manifest(backup_root, refresh=args.refresh_manifest)

    if args.action == "inventory":
        save_manifest(backup_root, manifest)
        print(f"Manifest: {manifest_file}")
        print_summary(manifest)
        return 0

    if args.action == "download":
        opener = create_no_proxy_opener()
        for entry in manifest["entries"]:
            if entry.get("downloadStatus") == "downloaded":
                continue
            download_entry(opener, backup_root, entry)
            save_manifest(backup_root, manifest)
        print(f"Manifest: {manifest_file}")
        print_summary(manifest)
        return 0

    if args.action == "upload":
        secret = load_secret(Path(args.secret_path))
        upload_manifest_entries(manifest, secret)
        save_manifest(backup_root, manifest)
        print(f"Manifest: {manifest_file}")
        print_summary(manifest)
        return 0

    if args.action == "update-live":
        update_live_links(manifest, args.live_base_url, DEFAULT_OWNER_LOGIN)
        save_manifest(backup_root, manifest)
        print(f"Manifest: {manifest_file}")
        print_summary(manifest)
        return 0

    raise RuntimeError(f"Unsupported action: {args.action}")


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"ERROR: {error}", file=sys.stderr)
        raise
