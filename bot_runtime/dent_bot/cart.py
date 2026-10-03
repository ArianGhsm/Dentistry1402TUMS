from __future__ import annotations

from dataclasses import dataclass
import hashlib
import json
import re

from .payments import normalize_student_number


CART_MAX_ITEMS = 20
CHECKOUT_MAX_ITEMS = 50
AI_BULK_DISCOUNT_RULES = ((16, 30), (11, 20))
CART_ITEM_KINDS = {"offer", "ai_booklet", "term_subscription"}
DISCOUNT_KINDS = {"percent", "fixed"}
DISCOUNT_CODE_PATTERN = re.compile(r"^[A-Z0-9][A-Z0-9_-]{3,23}$")


@dataclass(frozen=True)
class CommerceIdentity:
    subject_key: str
    student_number: str
    display_name: str


def commerce_identity_from_account(account: object) -> CommerceIdentity | None:
    source = dict(account) if isinstance(account, dict) else {}
    user = dict(source.get("user") or {}) if isinstance(source.get("user"), dict) else {}
    profile = (
        dict(source.get("onboardingProfile") or {})
        if isinstance(source.get("onboardingProfile"), dict)
        else {}
    )
    linked = source.get("linked") is True and source.get("authComplete") is True
    external_verified = bool(
        str(profile.get("verifiedAt") or "").strip()
        and not bool(profile.get("isClassMember"))
    )
    if not linked and not external_verified:
        return None

    student_number = normalize_student_number(
        user.get("studentNumber") or profile.get("studentNumber")
    )
    display_name = " ".join(str(user.get("name") or "").split())
    if not display_name:
        display_name = " ".join(
            part
            for part in (
                " ".join(str(profile.get("firstName") or "").split()),
                " ".join(str(profile.get("lastName") or "").split()),
            )
            if part
        )
    if student_number:
        subject = "student:" + hashlib.sha256(student_number.encode("ascii")).hexdigest()
        return CommerceIdentity(subject, student_number, display_name[:160])

    # Non-class onboarding may intentionally omit a student number. The website
    # still exposes a verified profile reference; using that opaque canonical
    # reference keeps cart identity independent from Telegram/Bale IDs.
    profile_ref = str(
        profile.get("profileRef")
        or source.get("onboardingProfileRef")
        or source.get("profileRef")
        or ""
    ).strip()
    if re.fullmatch(r"[A-Za-z0-9_-]{12,120}", profile_ref):
        subject = "profile:" + hashlib.sha256(profile_ref.encode("utf-8")).hexdigest()
        return CommerceIdentity(subject, "", display_name[:160])
    return None


def normalize_cart_item(value: object) -> dict:
    source = dict(value) if isinstance(value, dict) else {}
    kind = str(source.get("kind") or "").strip().lower()
    if kind not in CART_ITEM_KINDS:
        raise ValueError("Unsupported cart item kind")
    offer_ref = str(source.get("offerRef") or "").strip()
    if re.fullmatch(r"[A-Za-z0-9_-]{16,80}", offer_ref) is None:
        raise ValueError("Invalid cart offer reference")

    result: dict = {"kind": kind, "offerRef": offer_ref}
    if kind == "ai_booklet":
        term = int(source.get("term") or 0)
        session_no = int(source.get("sessionNo") or 0)
        course_code = " ".join(str(source.get("courseCode") or "").split())[:80]
        course_tag = " ".join(str(source.get("courseTag") or "").split())[:80]
        if not 1 <= term <= 12 or not 1 <= session_no <= 40 or not course_code:
            raise ValueError("Invalid AI booklet cart item")
        result.update(
            {
                "term": term,
                "courseCode": course_code,
                "courseTag": course_tag,
                "sessionNo": session_no,
            }
        )
    elif kind == "term_subscription":
        term = int(source.get("term") or 0)
        billing_period = str(source.get("billingPeriod") or "").strip()
        if not 1 <= term <= 12 or re.fullmatch(
            r"term(?:[1-9]|1[0-2])-1[34][0-9]{2}-(?:0[1-9]|1[0-2])",
            billing_period,
        ) is None:
            raise ValueError("Invalid subscription cart item")
        result.update({"term": term, "billingPeriod": billing_period})
    return result


def cart_item_key(item: object) -> str:
    value = normalize_cart_item(item)
    if value["kind"] == "ai_booklet":
        return (
            f"ai:{value['term']}:{value['courseCode']}:"
            f"{int(value['sessionNo'])}"
        )
    if value["kind"] == "term_subscription":
        return f"subscription:{value['term']}:{value['billingPeriod']}"
    return f"offer:{value['offerRef']}"


def normalize_discount_code(value: object) -> str:
    code = re.sub(r"\s+", "", str(value or "").upper())
    if not code:
        return ""
    if DISCOUNT_CODE_PATTERN.fullmatch(code) is None:
        raise ValueError("Invalid discount code")
    return code


def ai_bulk_discount_percent(ai_count: int) -> int:
    count = max(0, int(ai_count))
    for minimum, percent in AI_BULK_DISCOUNT_RULES:
        if count >= minimum:
            return percent
    return 0


def ai_bulk_discount_progress(ai_count: int) -> dict:
    count = max(0, int(ai_count))
    current = ai_bulk_discount_percent(count)
    if count < 11:
        return {"currentPercent": current, "nextCount": 11, "nextPercent": 20, "remaining": 11 - count}
    if count < 16:
        return {"currentPercent": current, "nextCount": 16, "nextPercent": 30, "remaining": 16 - count}
    return {"currentPercent": current, "nextCount": 0, "nextPercent": 0, "remaining": 0}


def ai_bulk_discount_summary(items: list[dict]) -> dict:
    payable = [
        dict(item)
        for item in items
        if isinstance(item, dict)
        and str(item.get("kind") or "") == "ai_booklet"
        and item.get("available", True) is not False
    ]
    count = len(payable)
    subtotal = sum(max(0, int(item.get("amountRials") or 0)) for item in payable)
    percent = ai_bulk_discount_percent(count)
    amount = subtotal * percent // 100
    return {
        "aiCount": count,
        "aiSubtotalRials": subtotal,
        "percent": percent,
        "amountRials": amount,
    }


def cart_media_requests(state: object, items: list[dict]) -> list[tuple[str, str, int]]:
    """Resolve purchased cart items into stable protected-media jobs."""
    requests: list[tuple[str, str, int]] = []
    seen: set[tuple[str, int]] = set()
    for raw in items:
        if not isinstance(raw, dict):
            continue
        item_key = str(raw.get("itemKey") or "").strip()
        kind = str(raw.get("kind") or "").strip()
        if not item_key:
            continue
        if kind == "ai_booklet":
            sources = state.protected_media_for_tag(
                course_tag=str(raw.get("courseTag") or ""),
                term=int(raw.get("term") or 0),
                session_no=int(raw.get("sessionNo") or 0),
                content_kind="ai_booklet",
            )
            for source in sources:
                source_id = int(source.get("id") or 0)
                dedupe = ("booklet", source_id)
                if source_id > 0 and dedupe not in seen:
                    seen.add(dedupe)
                    requests.append((item_key, "booklet", source_id))
            continue
        fulfillment = dict(raw.get("fulfillment") or {})
        if str(fulfillment.get("kind") or "") == "paid_file":
            asset = state.paid_file_asset(str(fulfillment.get("assetRef") or ""))
            asset_id = int((asset or {}).get("id") or 0)
            dedupe = ("paid_file", asset_id)
            if asset_id > 0 and dedupe not in seen:
                seen.add(dedupe)
                requests.append((item_key, "paid_file", asset_id))
    return requests


def cart_checkout_request_id(
    subject_key: str,
    *,
    version: int,
    items: list[dict],
    discount_code: str,
) -> str:
    """Stable checkout id across Telegram/Bale for the same canonical cart revision."""
    key = str(subject_key).strip()
    if not key:
        raise ValueError("Canonical commerce identity is required")
    fingerprint = json.dumps(
        {
            "version": max(0, int(version)),
            "items": [dict(item) for item in items],
            "discountCode": str(discount_code or ""),
        },
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    )
    return hashlib.sha256((f"cart:{key}:" + fingerprint).encode("utf-8")).hexdigest()
