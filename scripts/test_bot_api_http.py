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
import urllib.parse
import urllib.request
import http.cookiejar


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


def website_request(opener, url: str, *, method: str = "GET", fields: dict | None = None, csrf: str = "") -> dict:
    body = None
    headers = {"Accept": "application/json"}
    if fields is not None:
        body = urllib.parse.urlencode(fields).encode("utf-8")
        headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8"
    if csrf:
        headers["X-CSRF-Token"] = csrf
    req = urllib.request.Request(url, data=body, method=method, headers=headers)
    try:
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
    linked_identity = 654321
    started = request(endpoint, secret, {
        "action": "startLink", "platform": "telegram", "platformUserId": str(identity),
        "authVersion": "bot-canonical-auth-v1",
    })
    assert started["status"] == 200 and started["payload"].get("alreadyLinked") is False, started
    assert started["payload"]["linkUrl"].startswith("https://example.test/account/bot-link/?token=")

    account = request(endpoint, secret, {"action": "account", "platform": "telegram", "platformUserId": str(identity)})
    assert account["status"] == 200 and account["payload"]["linked"] is False, account
    assert account["payload"]["identity"] == {"recognized": False, "candidateStatus": "", "claimStatus": ""}

    generic_identity = "888001"
    generic_states = request(endpoint, secret, {
        "action": "paymentProductStatesV2",
        "platform": "telegram",
        "platformUserId": generic_identity,
        "contractVersion": "bot-commerce-v2",
        "offerRefs": [],
    })
    assert generic_states["status"] == 200, generic_states
    assert generic_states["payload"] == {
        "success": True,
        "contractVersion": "bot-commerce-v2",
        "states": [],
    }, generic_states
    generic_create_reaches_payment_validation = request(endpoint, secret, {
        "action": "createBotPayment",
        "platform": "telegram",
        "platformUserId": generic_identity,
        "contractVersion": "bot-commerce-v2",
        "offerRef": "bad",
        "title": "Invalid test offer",
        "amountRials": 10000,
        "requestId": "request-test-123456",
    })
    assert generic_create_reaches_payment_validation["status"] == 422, generic_create_reaches_payment_validation
    assert generic_create_reaches_payment_validation["payload"].get("code") == "BOT_PAYMENT_OFFER_INVALID", generic_create_reaches_payment_validation
    generic_private_action = request(endpoint, secret, {
        "action": "grades",
        "platform": "telegram",
        "platformUserId": generic_identity,
    })
    assert generic_private_action["status"] == 403, generic_private_action
    assert generic_private_action["payload"].get("code") == "ACCOUNT_LINK_REQUIRED", generic_private_action
    stored_after_generic = json.loads(open(storage_file, "r", encoding="utf-8").read())
    generic_routes = stored_after_generic.get("onboardingIdentityRoutes", {})
    assert len(generic_routes) == 1, generic_routes
    generic_route = next(iter(generic_routes.values()))
    assert generic_route.get("platform") == "telegram", generic_route
    assert "888001" not in json.dumps(generic_route, ensure_ascii=False), generic_route
    assert isinstance(generic_route.get("platformUserIdEncrypted"), dict), generic_route

    disconnect_claim = request(endpoint, secret, {
        "action": "claimAccountDisconnectDeliveriesV1",
        "platform": "telegram",
        "platformUserId": "654321",
        "limit": 10,
    })
    assert disconnect_claim["status"] == 200, disconnect_claim
    disconnect_deliveries = disconnect_claim["payload"].get("deliveries", [])
    assert len(disconnect_deliveries) == 1, disconnect_claim
    disconnect_delivery = disconnect_deliveries[0]
    assert disconnect_delivery["chatId"] == "777005", disconnect_delivery
    assert disconnect_delivery["platform"] == "telegram", disconnect_delivery
    assert str(disconnect_delivery["deliveryId"]).startswith("bd-"), disconnect_delivery
    disconnect_ack = request(endpoint, secret, {
        "action": "ackAccountDisconnectDeliveryV1",
        "platform": "telegram",
        "platformUserId": "654321",
        "deliveryId": disconnect_delivery["deliveryId"],
        "delivered": True,
    })
    assert disconnect_ack["status"] == 200 and disconnect_ack["payload"]["delivered"] is True, disconnect_ack

    site_origin = endpoint.split("/api/bot_api.php", 1)[0]
    cookie_jar = http.cookiejar.CookieJar()
    website_opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(cookie_jar),
    )
    website_login = website_request(
        website_opener,
        site_origin + "/api/auth_api.php",
        method="POST",
        fields={
            "action": "login",
            "studentNumber": "402000006",
            "password": "test-password-not-used",
        },
    )
    assert website_login["status"] == 200 and website_login["payload"].get("loggedIn") is True, website_login
    website_status = website_request(
        website_opener,
        site_origin + "/api/bot_api.php?action=accountConnections",
    )
    assert website_status["status"] == 200, website_status
    assert website_status["payload"]["contractVersion"] == "bot-account-connections-v1", website_status
    assert website_status["payload"]["connections"]["telegram"]["platformUserId"] == "777006", website_status
    assert website_status["payload"]["connections"]["telegram"]["platformDisplayName"] == "Website Disconnect Account", website_status
    csrf_token = website_status["payload"].get("csrfToken", "")
    missing_csrf_disconnect = website_request(
        website_opener,
        site_origin + "/api/bot_api.php",
        method="POST",
        fields={"action": "disconnectAccount", "platform": "telegram"},
    )
    assert missing_csrf_disconnect["status"] == 403, missing_csrf_disconnect
    website_disconnect = website_request(
        website_opener,
        site_origin + "/api/bot_api.php",
        method="POST",
        fields={"action": "disconnectAccount", "platform": "telegram"},
        csrf=csrf_token,
    )
    assert website_disconnect["status"] == 200 and website_disconnect["payload"]["deliveryQueued"] is True, website_disconnect
    website_status_after = website_request(
        website_opener,
        site_origin + "/api/bot_api.php?action=accountConnections",
    )
    assert website_status_after["payload"]["connections"]["telegram"]["connected"] is False, website_status_after
    website_disconnect_claim = request(endpoint, secret, {
        "action": "claimAccountDisconnectDeliveriesV1",
        "platform": "telegram",
        "platformUserId": "654321",
        "limit": 10,
    })
    website_disconnect_deliveries = website_disconnect_claim["payload"].get("deliveries", [])
    assert len(website_disconnect_deliveries) == 1 and website_disconnect_deliveries[0]["chatId"] == "777006", website_disconnect_claim
    website_disconnect_ack = request(endpoint, secret, {
        "action": "ackAccountDisconnectDeliveryV1",
        "platform": "telegram",
        "platformUserId": "654321",
        "deliveryId": website_disconnect_deliveries[0]["deliveryId"],
        "delivered": True,
    })
    assert website_disconnect_ack["status"] == 200, website_disconnect_ack

    catalog = request(endpoint, secret, {
        "action": "onboardingCatalogV1", "platform": "telegram", "platformUserId": str(identity),
        "contractVersion": "bot-onboarding-v1",
    })
    assert catalog["status"] == 200, catalog
    institutions = catalog["payload"]["institutions"]
    assert len(institutions) == 106, len(institutions)
    assert sum(item.get("system") == "azad" for item in institutions) == 31
    assert catalog["payload"]["entryYears"] == ["۱۳۹۹", "۱۴۰۰", "۱۴۰۱", "۱۴۰۲", "۱۴۰۳", "۱۴۰۴", "۱۴۰۵"]
    assert catalog["payload"]["azadAdmissionTypes"] == ["نیمسال اول", "نیمسال دوم"]

    watermark_identity = request(endpoint, secret, {
        "action": "bookletWatermarkIdentityV1",
        "platform": "telegram",
        "platformUserId": str(linked_identity),
        "contractVersion": "booklet-watermark-identity-v1",
    })
    assert watermark_identity["status"] == 200, watermark_identity
    assert watermark_identity["payload"]["identity"] == {
        "fullName": "Test Owner",
        "nationalCode": "0013546789",
        "phoneNumber": "+989120000000",
    }, watermark_identity

    azad_profile_reaches_phone_validation = request(endpoint, secret, {
        "action": "requestOnboardingOtpV1", "platform": "telegram", "platformUserId": str(identity),
        "contractVersion": "bot-onboarding-v1", "phoneNumber": "",
        "profile": {
            "firstName": "Test", "lastName": "Student", "major": "دندانپزشکی", "province": "تهران",
            "institution": "دانشگاه علوم پزشکی آزاد اسلامی تهران",
            "entryYear": "۱۴۰۲",
            "entryTerm": "نیمسال دوم", "courseType": "", "admissionType": "نیمسال دوم", "studentNumber": "",
        },
    })
    assert azad_profile_reaches_phone_validation["status"] == 422, azad_profile_reaches_phone_validation
    assert "شماره موبایل" in azad_profile_reaches_phone_validation["payload"].get("error", "")

    legacy_public_alias_reaches_phone_validation = request(endpoint, secret, {
        "action": "requestOnboardingOtpV1", "platform": "bale", "platformUserId": "123457",
        "contractVersion": "bot-onboarding-v1", "phoneNumber": "",
        "profile": {
            "firstName": "Test", "lastName": "Student", "major": "پزشکی", "province": "تهران",
            "institution": "دانشگاه علوم پزشکی تهران",
            "entryYear": "1402",
            "entryTerm": "نیمسال اول", "courseType": "روزانه", "admissionType": "نیمسال اول (روزانه)",
            "studentNumber": "",
        },
    })
    assert legacy_public_alias_reaches_phone_validation["status"] == 422, legacy_public_alias_reaches_phone_validation
    assert "شماره موبایل" in legacy_public_alias_reaches_phone_validation["payload"].get("error", "")

    invalid_azad_course_type = request(endpoint, secret, {
        "action": "requestOnboardingOtpV1", "platform": "telegram", "platformUserId": "123458",
        "contractVersion": "bot-onboarding-v1", "phoneNumber": "",
        "profile": {
            "firstName": "Test", "lastName": "Student", "major": "داروسازی", "province": "تهران",
            "institution": "دانشگاه علوم پزشکی آزاد اسلامی تهران",
            "entryYear": "۱۴۰۲",
            "entryTerm": "نیمسال اول", "courseType": "شهریه پرداز",
            "admissionType": "نیمسال اول (شهریه پرداز)", "studentNumber": "",
        },
    })
    assert invalid_azad_course_type["status"] == 422, invalid_azad_course_type
    assert invalid_azad_course_type["payload"].get("code") == "INVALID_ADMISSION_TYPE"

    invalid_entry_year = request(endpoint, secret, {
        "action": "requestOnboardingOtpV1", "platform": "telegram", "platformUserId": "123459",
        "contractVersion": "bot-onboarding-v1", "phoneNumber": "",
        "profile": {
            "firstName": "Test", "lastName": "Student", "major": "دندانپزشکی", "province": "تهران",
            "institution": "دانشگاه علوم پزشکی تهران", "entryYear": "۱۳۹۸",
            "entryTerm": "نیمسال اول", "courseType": "روزانه یا تعهدی",
            "admissionType": "نیمسال اول (روزانه یا تعهدی)", "studentNumber": "",
        },
    })
    assert invalid_entry_year["status"] == 422, invalid_entry_year
    assert invalid_entry_year["payload"].get("code") == "INVALID_ENTRY_YEAR"

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
    removed_catalog = request(endpoint, secret, {
        "action": "paymentCatalog", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert removed_catalog["status"] == 404, removed_catalog

    payment_payload = {
        "action": "createBotPayment",
        "platform": "telegram",
        "platformUserId": str(linked_identity),
        "contractVersion": "bot-commerce-v2",
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
    class_otp_not_ready = request(endpoint, secret, {
        "action": "classAuthOtpStartV1", "platform": "telegram", "platformUserId": "888004",
        "contractVersion": "bot-onboarding-v1", "studentNumber": "402000001",
    })
    assert class_otp_not_ready["status"] == 409 and class_otp_not_ready["payload"]["code"] == "CLASS_PHONE_OTP_NOT_READY", class_otp_not_ready
    regular_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": str(regular_identity),
    })
    assert regular_account["status"] == 200 and regular_account["payload"]["onboardingProfile"]["isClassMember"] is True, regular_account
    assert regular_account["payload"]["onboardingProfile"]["entryYear"] == "۱۴۰۲", regular_account
    assert regular_account["payload"]["onboardingProfile"]["admissionType"] == "نیمسال اول (روزانه یا تعهدی)", regular_account
    edit_requested = request(endpoint, secret, {
        "action": "requestProfileEditV1", "platform": "telegram", "platformUserId": str(regular_identity),
        "contractVersion": "bot-onboarding-v1", "field": "lastName", "value": "Student Updated",
    })
    assert edit_requested["status"] == 200 and edit_requested["payload"]["status"] == "pending", edit_requested
    edit_list = request(endpoint, secret, {
        "action": "profileEditRequestsV1", "platform": "telegram", "platformUserId": str(linked_identity),
        "contractVersion": "bot-onboarding-v1",
    })
    assert edit_list["status"] == 200 and len(edit_list["payload"]["requests"]) == 1, edit_list
    assert edit_list["payload"]["requests"][0]["previousValue"] == regular_account["payload"]["onboardingProfile"]["lastName"], edit_list
    assert edit_list["payload"]["requests"][0]["value"] == "Student Updated", edit_list
    edit_ref = edit_list["payload"]["requests"][0]["ref"]
    edit_approved = request(endpoint, secret, {
        "action": "resolveProfileEditV1", "platform": "telegram", "platformUserId": str(linked_identity),
        "contractVersion": "bot-onboarding-v1", "requestRef": edit_ref, "decision": "approve",
    })
    assert edit_approved["status"] == 200 and edit_approved["payload"]["status"] == "approved", edit_approved
    regular_account_after_edit = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": str(regular_identity),
    })
    assert regular_account_after_edit["payload"]["onboardingProfile"]["lastName"] == "Student Updated", regular_account_after_edit
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
    assert imported["status"] == 410 and imported["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", imported
    duplicate_claim = request(endpoint, secret, {
        "action": "submitIdentityClaim", "platform": "telegram", "platformUserId": str(imported_identity),
        "name": "Imported Student", "telegramProfile": {"displayName": "Duplicate Claim"},
    })
    assert duplicate_claim["status"] == 410 and duplicate_claim["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", duplicate_claim

    claim_identity = 888002
    claimed = request(endpoint, secret, {
        "action": "submitIdentityClaim", "platform": "telegram", "platformUserId": str(claim_identity),
        "name": "Claim Student",
        "telegramProfile": {
            "displayName": "Claim Student TG", "username": "claim_student",
            "languageCode": "fa", "isPremium": True,
        },
    })
    assert claimed["status"] == 410 and claimed["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", claimed
    owner_linked_claim_identity = request(endpoint, secret, {
        "action": "setIdentityMapping", "platform": "telegram", "platformUserId": str(linked_identity),
        "studentNumber": "402000002", "targetPlatformUserId": str(claim_identity),
        "reason": "Owner fixture mapping for unified auth test",
    })
    assert owner_linked_claim_identity["status"] == 410 and owner_linked_claim_identity["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", owner_linked_claim_identity

    normalized = request(endpoint, secret, {
        "action": "normalizeIdentityAuthV2", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert normalized["status"] == 200, normalized
    assert normalized["payload"]["result"]["approvedMigrated"] >= 1, normalized
    assert normalized["payload"]["result"]["pendingRejected"] >= 1, normalized
    auth_v2_status = request(endpoint, secret, {
        "action": "identityAuthV2Status", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert auth_v2_status["status"] == 200, auth_v2_status
    assert auth_v2_status["payload"]["contractVersion"] == "identity-auth-v2", auth_v2_status
    assert auth_v2_status["payload"]["pendingManualClaims"] == 0, auth_v2_status
    claimed_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": str(claim_identity),
    })
    assert claimed_account["status"] == 200 and claimed_account["payload"]["linked"] is True, claimed_account
    assert claimed_account["payload"]["authComplete"] is True, claimed_account
    mapped_private = request(endpoint, secret, {
        "action": "notifications", "platform": "telegram", "platformUserId": str(claim_identity), "limit": 1,
    })
    assert mapped_private["status"] == 200 and mapped_private["payload"].get("success") is True, mapped_private
    mapped_relink = request(endpoint, secret, {
        "action": "startLink", "platform": "telegram", "platformUserId": str(claim_identity),
        "authVersion": "bot-canonical-auth-v1",
    })
    assert mapped_relink["status"] == 200 and mapped_relink["payload"].get("alreadyLinked") is True, mapped_relink
    assert mapped_relink["payload"].get("authComplete") is True, mapped_relink

    retired_pending_account = request(endpoint, secret, {
        "action": "account", "platform": "telegram", "platformUserId": "888003",
    })
    assert retired_pending_account["status"] == 200 and retired_pending_account["payload"]["linked"] is False, retired_pending_account

    claims_disabled = request(endpoint, secret, {
        "action": "identityClaims", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert claims_disabled["status"] == 410 and claims_disabled["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", claims_disabled
    resolve_disabled = request(endpoint, secret, {
        "action": "resolveIdentityClaim", "platform": "telegram", "platformUserId": str(linked_identity),
        "claimRef": "abcdefghijkl", "decision": "approve",
    })
    assert resolve_disabled["status"] == 410 and resolve_disabled["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", resolve_disabled

    mappings = request(endpoint, secret, {
        "action": "identityMappings", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    assert mappings["status"] == 200 and any(item["platformUserId"] == str(claim_identity) for item in mappings["payload"]["mappings"]), mappings
    owner_changed = request(endpoint, secret, {
        "action": "setIdentityMapping", "platform": "telegram", "platformUserId": str(linked_identity),
        "studentNumber": "402000002", "targetPlatformUserId": "999002",
        "reason": "Owner approved account replacement in isolated test",
    })
    assert owner_changed["status"] == 410 and owner_changed["payload"]["code"] == "MANUAL_IDENTITY_DISABLED", owner_changed
    mappings_after = request(endpoint, secret, {
        "action": "identityMappings", "platform": "telegram", "platformUserId": str(linked_identity),
    })
    mapping_ref = next(item["ref"] for item in mappings_after["payload"]["mappings"] if item["platformUserId"] == str(claim_identity))
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
