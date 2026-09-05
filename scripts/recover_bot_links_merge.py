#!/usr/bin/env python3
"""Deterministically reconcile a pre-incident and post-incident bot store.

The tool never contacts production. It writes a candidate and a PII-minimized
report only after validating source hashes, canonical users and payment orders.
Current valid mappings win; historical records are restored only when their
platform/canonical identity namespace is unoccupied.
"""

from __future__ import annotations

import argparse
import copy
import hashlib
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable


COLLECTION_KEYS = (
    "links",
    "challenges",
    "identityCandidates",
    "identityClaims",
    "nonces",
    "audit",
    "notificationDeliveries",
    "accountDisconnectDeliveries",
    "paymentResultDeliveries",
    "onboardingProfiles",
    "onboardingIdentityProfiles",
    "onboardingIdentityRoutes",
    "onboardingChallenges",
    "onboardingEditRequests",
)
PLATFORMS = {"telegram", "bale"}


class RecoveryError(RuntimeError):
    pass


def load_object(path: Path, label: str) -> tuple[dict[str, Any], dict[str, Any]]:
    payload = path.read_bytes()
    try:
        decoded = json.loads(payload.decode("utf-8-sig"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise RecoveryError(f"{label} is not valid UTF-8 JSON") from exc
    if not isinstance(decoded, dict):
        raise RecoveryError(f"{label} must be a JSON object")
    return decoded, {"size": len(payload), "sha256": hashlib.sha256(payload).hexdigest()}


def validate_bot_store(store: dict[str, Any], label: str) -> None:
    if not isinstance(store.get("schemaVersion"), int):
        raise RecoveryError(f"{label} has no numeric schemaVersion")
    for key in COLLECTION_KEYS:
        if key in store and not isinstance(store[key], (dict, list)):
            raise RecoveryError(f"{label}.{key} has an invalid type")


def normalize_digits(value: Any) -> str:
    table = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
    return str(value or "").translate(table).strip()


def canonical_users(store: dict[str, Any]) -> tuple[dict[str, dict[str, Any]], str]:
    raw_users = store.get("users")
    users: dict[str, dict[str, Any]] = {}
    values: Iterable[Any]
    if isinstance(raw_users, dict):
        values = raw_users.values()
    elif isinstance(raw_users, list):
        values = raw_users
    else:
        raise RecoveryError("canonical users store has an invalid users collection")
    for record in values:
        if not isinstance(record, dict):
            continue
        student = normalize_digits(record.get("studentNumber"))
        if student:
            users[student] = record
    owner = normalize_digits(store.get("ownerStudentNumber"))
    if owner not in users:
        raise RecoveryError("canonical owner is absent from users store")
    return users, owner


def canonical_orders(store: dict[str, Any]) -> dict[int, dict[str, Any]]:
    orders: dict[int, dict[str, Any]] = {}
    raw = store.get("orders")
    values = raw.values() if isinstance(raw, dict) else raw if isinstance(raw, list) else []
    for record in values:
        if not isinstance(record, dict):
            continue
        try:
            order_id = int(record.get("id") or 0)
        except (TypeError, ValueError):
            continue
        if order_id > 0:
            orders[order_id] = record
    return orders


def as_map(value: Any) -> dict[str, Any]:
    if isinstance(value, dict):
        return copy.deepcopy(value)
    if isinstance(value, list) and not value:
        return {}
    if isinstance(value, list):
        result: dict[str, Any] = {}
        for index, item in enumerate(value):
            if isinstance(item, dict):
                key = str(item.get("deliveryId") or item.get("ref") or index)
                result[key] = copy.deepcopy(item)
        return result
    return {}


def stable_record_hash(record: Any) -> str:
    encoded = json.dumps(record, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(encoded).hexdigest()


def opaque_ref(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()[:16]


def link_valid(key: str, record: Any, users: dict[str, dict[str, Any]]) -> bool:
    if not isinstance(record, dict):
        return False
    identity = str(record.get("identityHash") or key)
    platform = str(record.get("platform") or "")
    student = normalize_digits(record.get("studentNumber"))
    return (
        len(key) == 64
        and all(char in "0123456789abcdef" for char in key.lower())
        and identity == key
        and platform in PLATFORMS
        and student in users
        and isinstance(record.get("platformUserIdEncrypted"), dict)
    )


def merge_keyed(
    old_value: Any,
    current_value: Any,
    collection: str,
    conflicts: list[dict[str, Any]],
) -> tuple[dict[str, Any], int, int]:
    old = as_map(old_value)
    current = as_map(current_value)
    result = copy.deepcopy(old)
    restored = 0
    preserved = 0
    for key, value in current.items():
        if key in result and stable_record_hash(result[key]) != stable_record_hash(value):
            conflicts.append({"collection": collection, "keyRef": opaque_ref(str(key)), "resolution": "current-wins"})
        if key not in old:
            preserved += 1
        result[key] = copy.deepcopy(value)
    for key in old:
        if key not in current:
            restored += 1
    return result, restored, preserved


def merge_audit(old_value: Any, current_value: Any) -> tuple[list[Any], int, int]:
    old = old_value if isinstance(old_value, list) else list(as_map(old_value).values())
    current = current_value if isinstance(current_value, list) else list(as_map(current_value).values())
    result: list[Any] = []
    seen: set[str] = set()
    old_unique = 0
    current_unique = 0
    for source, records in (("old", old), ("current", current)):
        for record in records:
            digest = stable_record_hash(record)
            if digest in seen:
                continue
            seen.add(digest)
            result.append(copy.deepcopy(record))
            if source == "old":
                old_unique += 1
            else:
                current_unique += 1
    return result, old_unique, current_unique


def platform_counts(links: dict[str, Any]) -> dict[str, int]:
    return {
        platform: sum(1 for record in links.values() if isinstance(record, dict) and record.get("platform") == platform)
        for platform in sorted(PLATFORMS)
    }


def counts(store: dict[str, Any]) -> dict[str, int]:
    return {
        "links": len(as_map(store.get("links"))),
        "onboardingProfiles": len(as_map(store.get("onboardingProfiles"))),
        "auditRecords": len(store.get("audit") if isinstance(store.get("audit"), list) else as_map(store.get("audit"))),
        "paymentDeliveries": len(as_map(store.get("paymentResultDeliveries"))),
        "notificationDeliveries": len(as_map(store.get("notificationDeliveries"))),
        "telegramLinkedUsers": platform_counts(as_map(store.get("links")))["telegram"],
        "baleLinkedUsers": platform_counts(as_map(store.get("links")))["bale"],
    }


def iso_max(*values: Any) -> str:
    clean = [str(value) for value in values if str(value or "").strip()]
    return max(clean) if clean else ""


def consolidate_current_stores(stores: list[dict[str, Any]]) -> dict[str, Any]:
    """Union valid post-incident generations; a later record wins, absence does not delete.

    A zeroed generation is evidence from the incident, not a trustworthy mass
    disconnect. Explicit removals remain represented by audit/delivery state and
    are reviewed as conflicts instead of being inferred from a missing key.
    """
    if not stores:
        raise RecoveryError("at least one current snapshot is required")
    result = copy.deepcopy(stores[0])
    keyed = (
        "links",
        "onboardingProfiles",
        "onboardingIdentityProfiles",
        "onboardingIdentityRoutes",
        "onboardingEditRequests",
        "notificationDeliveries",
        "accountDisconnectDeliveries",
        "paymentResultDeliveries",
    )
    for newer in stores[1:]:
        existing_links = as_map(result.get("links"))
        for key, record in as_map(newer.get("links")).items():
            if isinstance(record, dict):
                namespace = (str(record.get("platform") or ""), normalize_digits(record.get("studentNumber")))
                for old_key, old_record in list(existing_links.items()):
                    if old_key == key or not isinstance(old_record, dict):
                        continue
                    old_namespace = (str(old_record.get("platform") or ""), normalize_digits(old_record.get("studentNumber")))
                    if namespace == old_namespace and namespace[0] in PLATFORMS and namespace[1]:
                        del existing_links[old_key]
            existing_links[key] = copy.deepcopy(record)
        result["links"] = existing_links
        for collection in keyed[1:]:
            values = as_map(result.get(collection))
            values.update(as_map(newer.get(collection)))
            result[collection] = values
        audit, _, _ = merge_audit(result.get("audit"), newer.get("audit"))
        result["audit"] = audit
        result["notificationDispatchSince"] = iso_max(
            result.get("notificationDispatchSince"), newer.get("notificationDispatchSince")
        )
        if isinstance(newer.get("paymentResultPoll"), dict):
            result["paymentResultPoll"] = copy.deepcopy(newer["paymentResultPoll"])
        for collection in ("challenges", "identityCandidates", "identityClaims", "nonces", "onboardingChallenges"):
            if isinstance(newer.get(collection), (dict, list)):
                result[collection] = copy.deepcopy(newer[collection])
        result["schemaVersion"] = max(int(result.get("schemaVersion") or 0), int(newer.get("schemaVersion") or 0))
        result.pop("_storage", None)
    return result


def write_atomic(path: Path, payload: bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temp = path.with_name(path.name + f".tmp.{os.getpid()}")
    with temp.open("xb") as handle:
        if handle.write(payload) != len(payload):
            raise RecoveryError(f"short local write: {path.name}")
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(temp, path)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--old", type=Path, required=True)
    parser.add_argument("--current", type=Path, required=True, action="append")
    parser.add_argument("--users", type=Path, required=True)
    parser.add_argument("--payments", type=Path, required=True)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--report", type=Path, required=True)
    args = parser.parse_args()

    old, old_meta = load_object(args.old, "old snapshot")
    current_stores: list[dict[str, Any]] = []
    current_metas: list[dict[str, Any]] = []
    for index, current_path in enumerate(args.current, start=1):
        current_item, current_meta = load_object(current_path, f"current snapshot {index}")
        validate_bot_store(current_item, f"current snapshot {index}")
        current_stores.append(current_item)
        current_metas.append(current_meta)
    current = consolidate_current_stores(current_stores)
    users_store, users_meta = load_object(args.users, "canonical users")
    payment_store, payment_meta = load_object(args.payments, "canonical payments")
    validate_bot_store(old, "old snapshot")
    validate_bot_store(current, "consolidated current snapshots")
    users, owner = canonical_users(users_store)
    orders = canonical_orders(payment_store)

    conflicts: list[dict[str, Any]] = []
    old_links = as_map(old.get("links"))
    current_links = as_map(current.get("links"))
    merged_links: dict[str, Any] = {}
    current_namespaces: dict[tuple[str, str], str] = {}
    invalid_current = 0
    invalid_old = 0
    for key, record in current_links.items():
        if not link_valid(key, record, users):
            invalid_current += 1
            conflicts.append({"collection": "links", "keyRef": opaque_ref(key), "resolution": "preserved-current-invalid-review"})
            merged_links[key] = copy.deepcopy(record)
            continue
        namespace = (str(record["platform"]), normalize_digits(record["studentNumber"]))
        if namespace in current_namespaces and current_namespaces[namespace] != key:
            raise RecoveryError("current store contains a duplicate canonical platform mapping")
        current_namespaces[namespace] = key
        merged_links[key] = copy.deepcopy(record)

    restored_links = 0
    current_relinks = 0
    for key, record in old_links.items():
        if not link_valid(key, record, users):
            invalid_old += 1
            conflicts.append({"collection": "links", "keyRef": opaque_ref(key), "resolution": "skipped-invalid-old"})
            continue
        namespace = (str(record["platform"]), normalize_digits(record["studentNumber"]))
        if key in current_links:
            current_record = current_links[key]
            same_identity = (
                isinstance(current_record, dict)
                and str(current_record.get("platform")) == namespace[0]
                and normalize_digits(current_record.get("studentNumber")) == namespace[1]
            )
            if same_identity:
                if stable_record_hash(current_record) != stable_record_hash(record):
                    current_relinks += 1
                continue
            conflicts.append({"collection": "links", "keyRef": opaque_ref(key), "resolution": "current-identity-wins"})
            continue
        occupied = current_namespaces.get(namespace)
        if occupied is not None and occupied != key:
            conflicts.append({
                "collection": "links",
                "keyRef": opaque_ref(key),
                "conflictingKeyRef": opaque_ref(occupied),
                "resolution": "current-relink-wins",
            })
            current_relinks += 1
            continue
        merged_links[key] = copy.deepcopy(record)
        current_namespaces[namespace] = key
        restored_links += 1

    merged = copy.deepcopy(current)
    merged["schemaVersion"] = max(int(old.get("schemaVersion") or 0), int(current.get("schemaVersion") or 0), 6)
    merged["links"] = merged_links

    keyed_collections = (
        "onboardingProfiles",
        "onboardingIdentityProfiles",
        "onboardingIdentityRoutes",
        "onboardingEditRequests",
        "notificationDeliveries",
        "accountDisconnectDeliveries",
        "paymentResultDeliveries",
    )
    collection_stats: dict[str, dict[str, int]] = {}
    for collection in keyed_collections:
        merged_value, restored, preserved = merge_keyed(old.get(collection), current.get(collection), collection, conflicts)
        merged[collection] = merged_value
        collection_stats[collection] = {"restored": restored, "newPreserved": preserved}

    audit, old_audit_unique, current_audit_unique = merge_audit(old.get("audit"), current.get("audit"))
    merged["audit"] = audit
    merged["notificationDispatchSince"] = iso_max(old.get("notificationDispatchSince"), current.get("notificationDispatchSince"))
    # Ephemeral and retired manual-identity workflows are never resurrected.
    for collection in ("challenges", "identityCandidates", "identityClaims", "nonces", "onboardingChallenges"):
        merged[collection] = copy.deepcopy(current.get(collection) if isinstance(current.get(collection), (dict, list)) else {})

    invalid_payment_deliveries = 0
    valid_payment_deliveries: dict[str, Any] = {}
    for key, delivery in as_map(merged.get("paymentResultDeliveries")).items():
        try:
            order_id = int(delivery.get("orderId") or 0) if isinstance(delivery, dict) else 0
        except (TypeError, ValueError):
            order_id = 0
        order = orders.get(order_id)
        extra = order.get("extra_form_data") if isinstance(order, dict) else None
        if (
            not isinstance(delivery, dict)
            or not isinstance(order, dict)
            or order.get("status") != "success"
            or not isinstance(extra, dict)
            or extra.get("source") != "bot-offer"
        ):
            invalid_payment_deliveries += 1
            conflicts.append({"collection": "paymentResultDeliveries", "keyRef": opaque_ref(key), "resolution": "skipped-invalid"})
            continue
        delivery_id = str(delivery.get("deliveryId") or key)
        valid_payment_deliveries[delivery_id] = delivery
    merged["paymentResultDeliveries"] = valid_payment_deliveries

    merged.pop("_storage", None)
    merged["audit"].append(
        {
            "event": "bot-store-incident-merge-recovery",
            "identityRef": "system-recovery",
            "studentRef": "",
            "createdAt": datetime.now(timezone.utc).isoformat(),
            "reason": f"restored-links={restored_links};current-relinks={current_relinks}",
        }
    )

    owner_links = {
        platform: sum(
            1
            for record in merged_links.values()
            if isinstance(record, dict)
            and record.get("platform") == platform
            and normalize_digits(record.get("studentNumber")) == owner
        )
        for platform in sorted(PLATFORMS)
    }
    old_only_keys = sorted(set(old_links) - set(current_links))
    sample_refs = [opaque_ref(key) for key in old_only_keys[:5]]
    report = {
        "format": "dent-bot-store-recovery-report-v1",
        "generatedAt": datetime.now(timezone.utc).isoformat(),
        "sources": {
            "old": old_meta,
            "current": current_metas,
            "users": users_meta,
            "payments": payment_meta,
        },
        "before": {"old": counts(old), "current": counts(current)},
        "after": counts(merged),
        "merge": {
            "linksRestored": restored_links,
            "newLinksPreserved": len(set(current_links) - set(old_links)),
            "currentRelinksPreserved": current_relinks,
            "invalidOldLinksSkipped": invalid_old,
            "invalidCurrentLinksPreservedForReview": invalid_current,
            "oldAuditUnique": old_audit_unique,
            "currentAuditUnique": current_audit_unique,
            "invalidPaymentDeliveriesSkipped": invalid_payment_deliveries,
            "collections": collection_stats,
            "retiredIdentityClaimsRestored": 0,
        },
        "owner": {"telegram": owner_links["telegram"] == 1, "bale": owner_links["bale"] == 1},
        "sampleRestoredLinkRefs": sample_refs,
        "conflictCount": len(conflicts),
        "conflicts": conflicts,
    }
    candidate_bytes = (json.dumps(merged, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    report["candidate"] = {"size": len(candidate_bytes), "sha256": hashlib.sha256(candidate_bytes).hexdigest()}
    report_bytes = (json.dumps(report, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    write_atomic(args.candidate, candidate_bytes)
    write_atomic(args.report, report_bytes)
    print(json.dumps({"status": "dry-run-complete", **report["merge"], "after": report["after"], "owner": report["owner"], "conflictCount": report["conflictCount"]}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, RecoveryError) as exc:
        print(f"recovery dry-run failed: {exc}", file=sys.stderr)
        raise SystemExit(1)
