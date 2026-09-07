#!/usr/bin/env python3
"""Compare a deploy manifest with public_html without modifying either."""
import argparse
import hashlib
import json
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--public-html", default=str(Path(__file__).resolve().parents[1] / "public_html"))
    args = parser.parse_args()
    manifest_path = Path(args.manifest).resolve()
    root = Path(args.public_html).resolve()
    raw = manifest_path.read_bytes()
    payload = json.loads(raw.decode("utf-8-sig"))
    files = payload.get("Files")
    if not isinstance(files, dict) or not files:
        raise SystemExit("manifest has no file map")
    mismatches = []
    for rel, expected in files.items():
        path = root.joinpath(*rel.split("/"))
        if not path.is_file():
            mismatches.append({"path": rel, "reason": "missing"})
            continue
        data = path.read_bytes()
        actual_hash = hashlib.sha256(data).hexdigest()
        if actual_hash.lower() != str(expected.get("Hash", "")).lower() or len(data) != int(expected.get("Length", -1)):
            mismatches.append({"path": rel, "reason": "hash-or-size"})
    result = {
        "ok": not mismatches,
        "manifestSha256": hashlib.sha256(raw).hexdigest(),
        "manifestSourceHead": payload.get("SourceHead", ""),
        "fileCount": len(files),
        "mismatchCount": len(mismatches),
        "mismatches": mismatches[:25],
    }
    print(json.dumps(result, separators=(",", ":")))
    return 0 if result["ok"] else 2


if __name__ == "__main__":
    raise SystemExit(main())
