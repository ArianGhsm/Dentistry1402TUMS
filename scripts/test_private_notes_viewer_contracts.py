from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
JS = ROOT / "public_html" / "assets" / "site" / "scripts" / "private-notes-viewer.js"
HTML = ROOT / "public_html" / "notes" / "private-viewer" / "index.html"
SW = ROOT / "public_html" / "sw.js"


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def main() -> None:
    js = JS.read_text(encoding="utf-8")
    html = HTML.read_text(encoding="utf-8")
    sw = SW.read_text(encoding="utf-8")

    require("iframe" not in html.lower(), "viewer must not embed a PDF iframe")
    require("pdf.js" not in js.lower() and "pdfjs" not in js.lower(), "viewer must not use PDF.js")
    require("viewerManifest" in js, "viewer must request authorized document manifest")
    require("startViewingSession" in js, "viewer must start an authorized viewing session")
    require("tileToken" in js, "viewer must request short-lived tile tokens")
    require('action: "tile"' in js or 'action", "tile"' in js, "viewer must render server-provided tile images")
    require("URL.createObjectURL(blob)" in js, "viewer must use runtime blob URLs, not permanent tile URLs")
    require("cache: \"no-store\"" in js, "viewer fetches must bypass browser caches")
    require("AbortController" in js and ".abort()" in js, "viewer must cancel stale tile requests")
    require("localStorage" in js and "PAGE_KEY_PREFIX" in js, "viewer should restore last viewed page without storing images")
    require("IndexedDB" not in js and "indexedDB" not in js, "viewer must not store document images in IndexedDB")
    require("revokeAccess(" in js and "httpStatus === 401" in js and "httpStatus === 403" in js, "viewer must clear state on unauthorized/revoked access")
    require("response.status === 401 || response.status === 403" in js and "fetchTile(tile, level, (retryCount || 0) + 1)" in js, "viewer must refresh expired tile tokens safely")
    require("showTileFallback" in js and "تلاش دوباره" in js, "viewer must expose failed tile retry UI")
    require("goToPage" in js and "ArrowLeft" in js and "ArrowRight" in js, "viewer must support page navigation and keyboard navigation")
    require("setScale" in js and "fitScale" in js and "touchmove" in js, "viewer must support zoom, fit modes and touch gestures")
    require("/api/private_notes_api.php" in sw, "service worker must bypass private notes protected content")
    require("واترمارک" in html and "شناسه قابل پیگیری" in html, "viewer must show the visible watermark security notice")

    print("Private notes viewer frontend contracts OK.")


if __name__ == "__main__":
    main()
