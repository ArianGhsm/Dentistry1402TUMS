#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import hmac
import json
import os
import secrets
import sys
import time
import urllib.error
import urllib.request


def request(endpoint: str, secret: bytes, payload: dict, *, nonce: str | None = None, method: str = "POST") -> dict:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    timestamp = str(int(time.time()))
    nonce = nonce or secrets.token_urlsafe(24)
    canonical = f"{timestamp}\n{nonce}\n{hashlib.sha256(body).hexdigest()}".encode("ascii")
    signature = hmac.new(secret, canonical, hashlib.sha256).hexdigest()
    req = urllib.request.Request(
        endpoint,
        data=body,
        method=method,
        headers={
            "Content-Type": "application/json",
            "X-Dent-Timestamp": timestamp,
            "X-Dent-Nonce": nonce,
            "X-Dent-Signature": signature,
        },
    )
    try:
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        with opener.open(req, timeout=10) as response:
            status = response.status
            raw = response.read()
    except urllib.error.HTTPError as error:
        status = error.code
        raw = error.read()
    return {"status": status, "payload": json.loads(raw.decode("utf-8"))}


def main() -> int:
    endpoint = os.environ.get("DENT_TEST_BOT_API_URL", "").strip()
    secret_hex = os.environ.get("DENT_TEST_BOT_API_SECRET_HEX", "").strip()
    storage_file = os.environ.get("DENT_TEST_BOT_STORE_PATH", "").strip()
    if not endpoint or len(secret_hex) != 64 or not storage_file:
        raise RuntimeError("Isolated bot API test environment is incomplete")
    secret = bytes.fromhex(secret_hex)
    identity = 123456
    started = request(endpoint, secret, {"action": "startLink", "platform": "telegram", "platformUserId": str(identity)})
    assert started["status"] == 200 and started["payload"].get("alreadyLinked") is False, started
    assert started["payload"]["linkUrl"].startswith("https://example.test/account/bot-link/?token=")

    account = request(endpoint, secret, {"action": "account", "platform": "telegram", "platformUserId": str(identity)})
    assert account["status"] == 200 and account["payload"]["linked"] is False, account
    assert account["payload"]["identity"] == {"recognized": False, "candidateStatus": "", "claimStatus": ""}

    nonce = secrets.token_urlsafe(24)
    first = request(endpoint, secret, {"action": "account", "platform": "telegram", "platformUserId": str(identity)}, nonce=nonce)
    second = request(endpoint, secret, {"action": "account", "platform": "telegram", "platformUserId": str(identity)}, nonce=nonce)
    assert first["status"] == 200
    assert second["status"] == 409

    put_request = request(
        endpoint,
        secret,
        {"action": "account", "platform": "telegram", "platformUserId": str(identity)},
        method="PUT",
    )
    assert put_request["status"] == 200 and put_request["payload"]["linked"] is False, put_request

    stored = open(storage_file, "r", encoding="utf-8").read()
    assert str(identity) not in stored
    linked_identity = 654321
    removed_catalog = request(endpoint, secret, {
        "action": "paymentCatalog", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert removed_catalog["status"] == 404, removed_catalog

    payment_payload = {
        "action": "createBotPayment",
        "platform": "telegram",
        "platformUserId": str(linked_identity),
        "offerRef": "bot_offer_abcdefghijklmnop",
        "title": "Synthetic bot offer",
        "description": "Created independently in the bot",
        "amountRials": 300000,
        "requestId": "integration-payment-request-0001",
    }
    created = request(endpoint, secret, payment_payload)
    assert created["status"] == 200 and created["payload"]["amountRials"] == 300000, created
    assert created["payload"]["alreadyCreated"] is False
    assert created["payload"]["redirectUrl"].startswith("https://example.test/api/payments_api.php?action=mockGateway")
    duplicate = request(endpoint, secret, payment_payload)
    assert duplicate["status"] == 200 and duplicate["payload"]["alreadyCreated"] is True, duplicate
    assert duplicate["payload"]["orderToken"] == created["payload"]["orderToken"]
    status = request(endpoint, secret, {
        "action": "paymentStatus",
        "platform": "telegram",
        "platformUserId": str(linked_identity),
        "orderToken": created["payload"]["orderToken"],
    })
    assert status["status"] == 200 and status["payload"]["status"] == "pending", status

    unlinked_notifications = request(endpoint, secret, {
        "action": "notifications", "platform": "telegram", "platformUserId": str(identity),
    })
    assert unlinked_notifications["status"] == 403, unlinked_notifications

    regular_identity = 777001
    feed = request(endpoint, secret, {
        "action": "notifications", "platform": "telegram", "platformUserId": str(regular_identity), "limit": 20,
    })
    assert feed["status"] == 200 and feed["payload"]["data"]["items"], feed

    missing_contract = request(endpoint, secret, {
        "action": "studentAssistantSummaryV1", "platform": "telegram", "platformUserId": str(regular_identity),
    })
    assert missing_contract["status"] == 409 and missing_contract["payload"].get("code") == "STUDENT_ASSISTANT_CONTRACT_MISMATCH", missing_contract
    assistant = request(endpoint, secret, {
        "action": "studentAssistantSummaryV1", "contractVersion": "student-assistant-v1",
        "platform": "telegram", "platformUserId": str(regular_identity),
    })
    assert assistant["status"] == 200 and assistant["payload"]["contractVersion"] == "student-assistant-v1", assistant
    food_action = next(item for item in assistant["payload"]["view"]["actions"] if "تغذیه" in item["label"])
    assert 12 <= len(food_action["ref"]) <= 20
    start_payload = {
        "action": "performIntegrationActionV1", "contractVersion": "student-assistant-v1",
        "platform": "telegram", "platformUserId": str(regular_identity),
        "actionRef": food_action["ref"], "requestId": hashlib.sha256(b"assistant-start-1").hexdigest(),
    }
    started_assistant = request(endpoint, secret, start_payload)
    assert started_assistant["status"] == 200 and started_assistant["payload"]["status"] == "challenge", started_assistant
    assert started_assistant["payload"]["challenge"]["imageDataUri"].startswith("data:image/png;base64,")
    repeated_start = request(endpoint, secret, start_payload)
    assert repeated_start["status"] == 200, repeated_start
    assert repeated_start["payload"]["jobRef"] == started_assistant["payload"]["jobRef"]
    assert repeated_start["payload"]["challenge"]["ref"] == started_assistant["payload"]["challenge"]["ref"]

    challenge = started_assistant["payload"]["challenge"]
    cross_platform_answer = request(endpoint, secret, {
        "action": "integrationChallengeAnswerV1", "contractVersion": "student-assistant-v1",
        "platform": "bale", "platformUserId": "777002",
        "challengeRef": challenge["ref"], "jobRef": started_assistant["payload"]["jobRef"],
        "answer": "A7K2P", "requestId": hashlib.sha256(b"assistant-cross-platform").hexdigest(),
    })
    assert cross_platform_answer["status"] == 409 and cross_platform_answer["payload"].get("code") == "INTEGRATION_CHALLENGE_NOT_ACTIVE", cross_platform_answer
    cross_user_answer = request(endpoint, secret, {
        "action": "integrationChallengeAnswerV1", "contractVersion": "student-assistant-v1",
        "platform": "telegram", "platformUserId": str(linked_identity),
        "challengeRef": challenge["ref"], "jobRef": started_assistant["payload"]["jobRef"],
        "answer": "A7K2P", "requestId": hashlib.sha256(b"assistant-cross-user").hexdigest(),
    })
    assert cross_user_answer["status"] == 409 and cross_user_answer["payload"].get("code") == "INTEGRATION_CHALLENGE_NOT_ACTIVE", cross_user_answer

    wrong_payload = {
        "action": "integrationChallengeAnswerV1", "contractVersion": "student-assistant-v1",
        "platform": "telegram", "platformUserId": str(regular_identity),
        "challengeRef": challenge["ref"], "jobRef": started_assistant["payload"]["jobRef"],
        "answer": "WRONG7", "requestId": hashlib.sha256(b"assistant-wrong-1").hexdigest(),
    }
    wrong = request(endpoint, secret, wrong_payload)
    assert wrong["status"] == 200 and wrong["payload"]["status"] == "challenge", wrong
    assert wrong["payload"]["challenge"]["ref"] != challenge["ref"]
    wrong_retry = request(endpoint, secret, wrong_payload)
    assert wrong_retry["status"] == 200 and wrong_retry["payload"]["challenge"]["ref"] == wrong["payload"]["challenge"]["ref"], wrong_retry
    replay_old = request(endpoint, secret, {
        **wrong_payload,
        "answer": "A7K2P", "requestId": hashlib.sha256(b"assistant-old-replay").hexdigest(),
    })
    assert replay_old["status"] == 409 and replay_old["payload"].get("code") == "INTEGRATION_CHALLENGE_NOT_ACTIVE", replay_old

    correct_payload = {
        "action": "integrationChallengeAnswerV1", "contractVersion": "student-assistant-v1",
        "platform": "telegram", "platformUserId": str(regular_identity),
        "challengeRef": wrong["payload"]["challenge"]["ref"], "jobRef": wrong["payload"]["jobRef"],
        "answer": "A7K2P", "requestId": hashlib.sha256(b"assistant-correct-1").hexdigest(),
    }
    preview = request(endpoint, secret, correct_payload)
    assert preview["status"] == 200 and preview["payload"]["status"] == "preview", preview
    assert "هنوز هیچ" in preview["payload"]["view"]["description"]
    preview_retry = request(endpoint, secret, correct_payload)
    assert preview_retry["status"] == 200 and preview_retry["payload"] == preview["payload"], preview_retry
    confirm_action = preview["payload"]["view"]["actions"][0]["ref"]
    confirmed = request(endpoint, secret, {
        "action": "performIntegrationActionV1", "contractVersion": "student-assistant-v1",
        "platform": "telegram", "platformUserId": str(regular_identity),
        "actionRef": confirm_action, "requestId": hashlib.sha256(b"assistant-confirm-1").hexdigest(),
    })
    assert confirmed["status"] == 200 and confirmed["payload"]["status"] == "verified", confirmed
    assistant_store = open(os.path.join(os.path.dirname(storage_file), "student_assistant.json"), "r", encoding="utf-8").read()
    assert "data:image" not in assistant_store
    assert "A7K2P" not in assistant_store and "WRONG7" not in assistant_store

    notification = next(item for item in feed["payload"]["data"]["items"] if item["title"] == "Synthetic notification")
    assert notification["unread"] is True and notification["ctaHref"] == "/exams/"

    deploy_event = {
        "event_id": "website-test-started",
        "service": "website",
        "status": "started",
        "environment": "production",
        "version": "test-v1",
        "summary": "Synthetic deploy lifecycle check",
        "actor": "contract-test",
        "created_at": "2026-08-25T00:00:00+00:00",
    }
    deploy_created = request(endpoint, secret, {
        "action": "createDeployNotification", "platform": "telegram", "platformUserId": str(linked_identity),
        "event": deploy_event,
    })
    assert deploy_created["status"] == 200 and deploy_created["payload"]["eventId"] == deploy_event["event_id"], deploy_created
    deploy_duplicate = request(endpoint, secret, {
        "action": "createDeployNotification", "platform": "telegram", "platformUserId": str(linked_identity),
        "event": deploy_event,
    })
    assert deploy_duplicate["status"] == 200, deploy_duplicate
    assert deploy_duplicate["payload"]["notificationId"] == deploy_created["payload"]["notificationId"]
    deploy_forbidden = request(endpoint, secret, {
        "action": "createDeployNotification", "platform": "telegram", "platformUserId": str(regular_identity),
        "event": deploy_event,
    })
    assert deploy_forbidden["status"] == 403, deploy_forbidden

    owner_feed = request(endpoint, secret, {
        "action": "notifications", "platform": "telegram", "platformUserId": str(linked_identity), "limit": 20,
    })
    deploy_items = [item for item in owner_feed["payload"]["data"]["items"] if item["id"] == deploy_created["payload"]["notificationId"]]
    assert len(deploy_items) == 1, owner_feed
    assert deploy_items[0]["meta"]["eventId"] == deploy_event["event_id"]
    assert deploy_items[0]["meta"]["deployStatus"] == "started"
    assert deploy_items[0]["meta"]["service"] == "website"

    claimed = request(endpoint, secret, {
        "action": "claimNotificationDeliveries", "platform": "telegram", "platformUserId": str(linked_identity), "limit": 10,
    })
    assert claimed["status"] == 200, claimed
    delivery = next(item for item in claimed["payload"]["deliveries"] if item["chatId"] == str(regular_identity))
    assert delivery["notification"]["id"] == notification["id"]
    assert delivery["notification"]["ctaUrl"] == "https://example.test/exams/"
    assert all(item["notification"]["id"] != deploy_created["payload"]["notificationId"] for item in claimed["payload"]["deliveries"])
    leased_again = request(endpoint, secret, {
        "action": "claimNotificationDeliveries", "platform": "telegram", "platformUserId": str(linked_identity), "limit": 10,
    })
    assert all(item["deliveryId"] != delivery["deliveryId"] for item in leased_again["payload"]["deliveries"])

    marked = request(endpoint, secret, {
        "action": "markNotificationRead", "platform": "telegram", "platformUserId": str(regular_identity),
        "notificationId": notification["id"],
    })
    assert marked["status"] == 200, marked
    audience = request(endpoint, secret, {
        "action": "notificationAudience", "platform": "telegram", "platformUserId": str(linked_identity),
        "notificationId": notification["id"],
    })
    assert audience["status"] == 200 and audience["payload"]["data"]["summary"]["viewedCount"] == 1, audience
    non_owner_audience = request(endpoint, secret, {
        "action": "notificationAudience", "platform": "telegram", "platformUserId": str(regular_identity),
        "notificationId": notification["id"],
    })
    assert non_owner_audience["status"] == 403, non_owner_audience

    imported_identity = 888001
    imported = request(endpoint, secret, {
        "action": "importIdentityCandidates", "platform": "telegram", "platformUserId": str(linked_identity),
        "candidates": [{
            "platformUserId": str(imported_identity), "studentNumber": "402000004",
            "matchMethod": "exact_unique_name", "ownerApproved": True,
        }],
    })
    assert imported["status"] == 200 and imported["payload"]["linked"] == 1, imported
    imported_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": str(imported_identity),
    })
    assert imported_account["status"] == 200 and imported_account["payload"]["linked"] is True, imported_account
    duplicate_claim = request(endpoint, secret, {
        "action": "submitIdentityClaim", "platform": "telegram", "platformUserId": str(imported_identity),
        "name": "Imported Student", "telegramProfile": {"displayName": "Duplicate Claim"},
    })
    assert duplicate_claim["status"] == 200 and duplicate_claim["payload"]["status"] == "already-linked", duplicate_claim

    claim_identity = 888002
    claimed = request(endpoint, secret, {
        "action": "submitIdentityClaim", "platform": "telegram", "platformUserId": str(claim_identity),
        "name": "Claim Student",
        "telegramProfile": {
            "displayName": "Claim Student TG", "username": "claim_student",
            "languageCode": "fa", "isPremium": True,
        },
    })
    assert claimed["status"] == 200 and claimed["payload"]["status"] == "pending", claimed
    stored_after_claim = open(storage_file, "r", encoding="utf-8").read()
    assert str(claim_identity) not in stored_after_claim
    assert "claim_student" not in stored_after_claim
    assert "Claim Student TG" not in stored_after_claim
    pending = request(endpoint, secret, {
        "action": "identityClaims", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert pending["status"] == 200 and len(pending["payload"]["claims"]) == 1, pending
    assert pending["payload"]["claims"][0]["claimedName"] == "Claim Student"
    assert pending["payload"]["claims"][0]["platformUserId"] == str(claim_identity)
    assert pending["payload"]["claims"][0]["telegramProfile"]["username"] == "claim_student"
    claim_ref = pending["payload"]["claims"][0]["ref"]
    approved = request(endpoint, secret, {
        "action": "resolveIdentityClaim", "platform": "telegram", "platformUserId": str(linked_identity),
        "claimRef": claim_ref, "decision": "approve",
    })
    assert approved["status"] == 200 and approved["payload"]["status"] == "approved", approved
    claimed_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": str(claim_identity),
    })
    assert claimed_account["status"] == 200 and claimed_account["payload"]["linked"] is True, claimed_account

    conflicting_claim_identity = 888003
    conflicting = request(endpoint, secret, {
        "action": "submitIdentityClaim", "platform": "telegram", "platformUserId": str(conflicting_claim_identity),
        "name": "Synthetic Student", "telegramProfile": {"displayName": "Conflicting Student"},
    })
    assert conflicting["status"] == 200, conflicting
    conflict_list = request(endpoint, secret, {
        "action": "identityClaims", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    conflict_ref = next(item["ref"] for item in conflict_list["payload"]["claims"] if item["platformUserId"] == str(conflicting_claim_identity))
    conflict_approval = request(endpoint, secret, {
        "action": "resolveIdentityClaim", "platform": "telegram", "platformUserId": str(linked_identity),
        "claimRef": conflict_ref, "decision": "approve",
    })
    assert conflict_approval["status"] == 409, conflict_approval

    mappings = request(endpoint, secret, {
        "action": "identityMappings", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert mappings["status"] == 200 and any(item["platformUserId"] == str(claim_identity) for item in mappings["payload"]["mappings"]), mappings
    owner_changed = request(endpoint, secret, {
        "action": "setIdentityMapping", "platform": "telegram", "platformUserId": str(linked_identity),
        "studentNumber": "402000002", "targetPlatformUserId": "999002",
        "reason": "Owner approved account replacement in isolated test",
    })
    assert owner_changed["status"] == 200, owner_changed
    old_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": str(claim_identity),
    })
    new_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": "999002",
    })
    assert old_account["status"] == 200 and old_account["payload"]["linked"] is False, old_account
    assert new_account["status"] == 200 and new_account["payload"]["linked"] is True, new_account
    mappings_after = request(endpoint, secret, {
        "action": "identityMappings", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    mapping_ref = next(item["ref"] for item in mappings_after["payload"]["mappings"] if item["platformUserId"] == "999002")
    deleted = request(endpoint, secret, {
        "action": "deleteIdentityMapping", "platform": "telegram", "platformUserId": str(linked_identity),
        "mappingRef": mapping_ref, "reason": "Owner requested unlink in isolated test",
    })
    assert deleted["status"] == 200 and deleted["payload"]["status"] == "deleted", deleted
    acknowledged = request(endpoint, secret, {
        "action": "ackNotificationDelivery", "platform": "telegram", "platformUserId": str(linked_identity),
        "deliveryId": delivery["deliveryId"], "delivered": True,
    })
    assert acknowledged["status"] == 200 and acknowledged["payload"]["delivered"] is True, acknowledged
    print("OK: HTTP HMAC, account linking, student-assistant CAPTCHA binding, bot checkout, notifications and owner controls passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
