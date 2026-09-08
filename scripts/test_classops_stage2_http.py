#!/usr/bin/env python3
from __future__ import annotations

import contextlib
import http.cookiejar
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import urllib.request

from test_classops_api_http import ROOT, form_request, free_port, request


def login(base: str, identities: dict, role: str):
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(jar))
    status, payload = form_request(opener, base + "/api/auth_api.php", {
        "action": "login",
        "studentNumber": identities[role],
        "password": "classops-test-password",
    })
    assert status == 200 and payload.get("loggedIn") is True, (status, payload)
    status, sessions = request(opener, base + "/api/auth_api.php?action=authSessions")
    assert status == 200 and sessions.get("csrfToken"), (status, sessions)
    return opener, str(sessions["csrfToken"])


def audience_spec() -> dict:
    return {
        "version": "classops-audience-v1",
        "resolutionMode": "snapshot",
        "expression": {"op": "whole_cohort"},
        "includeStudentNumbers": [],
        "excludeStudentNumbers": [],
    }


def public_preview_item(item: dict, patch: dict | None = None) -> dict:
    merged = dict(item)
    merged.update(patch or {})
    out = {
        "cohortKey": merged["cohortKey"],
        "type": merged["type"],
        "title": merged["title"],
        "description": merged.get("description", ""),
        "course": merged.get("course"),
        "timing": merged.get("timing") or {"startsAt": None, "endsAt": None, "dueAt": None, "timezone": "Asia/Tehran"},
        "location": merged.get("location", ""),
        "importance": merged.get("importance", "normal"),
        "requireAck": bool(merged.get("requireAck", False)),
        "status": merged.get("status", "draft"),
    }
    if isinstance(merged.get("source"), dict):
        out["source"] = merged["source"]
    if isinstance(merged.get("metadata"), dict):
        out["metadata"] = merged["metadata"]
    extensions = dict(merged.get("extensions") or {})
    extensions.pop("classops_stage2_v1", None)
    if extensions:
        out["extensions"] = extensions
    return out


def binding(item: dict) -> dict:
    value = (item.get("extensions") or {}).get("classops_stage2_v1")
    assert isinstance(value, dict) and value.get("contractVersion") == "classops-stage2-binding-v1", item
    return value


def preview_create(owner, csrf: str, base: str, item: dict, *, destinations=None, service_ref=None) -> dict:
    body = {
        "item": item,
        "audienceSpec": audience_spec(),
        "destinations": destinations or ["private_users"],
    }
    if service_ref is not None:
        body["serviceRef"] = service_ref
    status, preview = request(owner, base + "/api/classops_api.php?action=preview", method="POST", csrf=csrf, fields=body)
    assert status == 200 and preview.get("success") is True and preview.get("mutationPerformed") is False, (status, preview)
    assert preview["confirmation"]["required"] is True
    assert len(preview["confirmation"]["audienceHash"]) == 64
    return preview


def confirm_create(owner, csrf: str, base: str, item: dict, preview: dict, idem: str, *, destinations=None, service_ref=None) -> dict:
    body = {
        "mode": "create",
        "item": item,
        "audienceSpec": audience_spec(),
        "expectedAudienceHash": preview["confirmation"]["audienceHash"],
        "destinations": destinations or ["private_users"],
        "idempotencyKey": idem,
        "reason": "Stage2 HTTP acceptance create",
    }
    if service_ref is not None:
        body["serviceRef"] = service_ref
    status, result = request(owner, base + "/api/classops_api.php?action=confirm", method="POST", csrf=csrf, fields=body)
    assert status == 200 and result.get("success") is True, (status, result)
    return result


def preview_update(owner, csrf: str, base: str, item: dict, patch: dict) -> tuple[dict, dict]:
    current_binding = binding(item)
    body = {
        "item": public_preview_item(item, patch),
        "audienceSpec": current_binding["audienceSpec"],
        "destinations": current_binding["destinations"],
    }
    if current_binding.get("reminderPolicy") is not None:
        body["reminderPolicy"] = current_binding["reminderPolicy"]
    if current_binding.get("serviceRef") is not None:
        body["serviceRef"] = current_binding["serviceRef"]
    status, preview = request(owner, base + "/api/classops_api.php?action=preview", method="POST", csrf=csrf, fields=body)
    assert status == 200 and preview.get("mutationPerformed") is False, (status, preview)
    return preview, current_binding


def confirm_update(owner, csrf: str, base: str, item: dict, patch: dict, preview: dict, current_binding: dict, idem: str) -> dict:
    body = {
        "mode": "update",
        "id": item["id"],
        "expectedRevision": item["revision"],
        "item": patch,
        "audienceSpec": current_binding["audienceSpec"],
        "expectedAudienceHash": preview["confirmation"]["audienceHash"],
        "destinations": current_binding["destinations"],
        "idempotencyKey": idem,
        "reason": "Stage2 HTTP acceptance update",
    }
    if current_binding.get("reminderPolicy") is not None:
        body["reminderPolicy"] = current_binding["reminderPolicy"]
    if current_binding.get("serviceRef") is not None:
        body["serviceRef"] = current_binding["serviceRef"]
    status, result = request(owner, base + "/api/classops_api.php?action=confirm", method="POST", csrf=csrf, fields=body)
    assert status == 200 and result.get("success") is True, (status, result)
    return result


def main() -> int:
    temp = Path(tempfile.mkdtemp(prefix="dent-classops-stage2-http-"))
    storage = temp / "storage"
    sessions = temp / "sessions"
    sessions.mkdir(parents=True)
    env = os.environ.copy()
    env.update({
        "DENT_APP_ENV": "test",
        "DENT_STORAGE_ROOT": str(storage),
        "DENT_SERVER_ONLY_ROOT": str(temp),
        "DENT_SESSION_SAVE_PATH": str(sessions),
        "DENT_CLASSOPS_TELEGRAM_ENABLED": "false",
        "DENT_CLASSOPS_BALE_ENABLED": "false",
    })
    php = os.environ.get("PHP_BIN", "php")
    fixture = subprocess.run(
        [php, str(ROOT / "scripts" / "setup_classops_api_fixture.php")],
        cwd=ROOT, env=env, check=True, capture_output=True, text=True, encoding="utf-8",
    )
    identities = json.loads(fixture.stdout)
    port = free_port()
    server = subprocess.Popen(
        [php, "-S", f"127.0.0.1:{port}", "-t", str(ROOT / "public_html")],
        cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    base = f"http://127.0.0.1:{port}"
    plain = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    try:
        for _ in range(50):
            try:
                status, _ = request(plain, base + "/api/classops_api.php?action=capabilities")
                if status == 200:
                    break
            except OSError:
                pass
            time.sleep(0.1)
        else:
            raise RuntimeError("ClassOps Stage2 fixture server did not start")

        owner, owner_csrf = login(base, identities, "owner")
        student, student_csrf = login(base, identities, "student")

        # A. Preview is a zero-write management operation.
        task_input = {
            "cohortKey": "dentistry-1402",
            "type": "task",
            "title": "Stage2 task fixture",
            "description": "HTTP acceptance",
            "timing": {"startsAt": None, "endsAt": None, "dueAt": "2026-09-20T20:00:00+03:30", "timezone": "Asia/Tehran"},
            "location": "",
            "importance": "important",
            "requireAck": False,
            "status": "draft",
        }
        preview = preview_create(owner, owner_csrf, base, task_input)
        assert not (storage / "classops" / "store.json").exists(), "preview must not initialize Foundation storage"
        assert not (storage / "classops" / "domain-state.json").exists(), "preview must not initialize Stage2 state"

        # B. Owner confirm creates canonical revision + audience/task state.
        created = confirm_create(owner, owner_csrf, base, task_input, preview, "stage2-http-task-create-0001")
        task = created["item"]
        assert task["status"] == "scheduled" and task["revision"] == 2, task
        assert created["notificationCount"] >= 1
        assert created["directDeliveryIntentCount"] == 0  # both transports explicitly unavailable in fixture
        assert (storage / "classops" / "store.json").exists()
        assert (storage / "classops" / "domain-state.json").exists()

        # C. Canonical student sees only the audience projection and can mutate its own task state.
        status, student_list = request(student, base + "/api/classops_api.php?action=student-list&type=task")
        assert status == 200 and student_list["data"]["count"] == 1, (status, student_list)
        projection = student_list["data"]["items"][0]
        assert projection["id"] == task["id"] and projection["task"]["state"] == "pending", projection
        state_revision = projection["task"]["stateRevision"]
        status, transitioned = request(student, base + "/api/classops_api.php?action=student-task-transition", method="POST", csrf=student_csrf, fields={
            "id": task["id"],
            "expectedStateRevision": state_revision,
            "target": "submitted",
            "commandId": "stage2-student-submit-0001",
            "reason": "fixture submission",
        })
        assert status == 200 and transitioned["state"]["state"] == "submitted", (status, transitioned)

        # D. Stage2 revisions carry eligible task state and stale writes still use expectedRevision.
        active_preview, active_binding = preview_update(owner, owner_csrf, base, task, {"status": "active"})
        active_result = confirm_update(owner, owner_csrf, base, task, {"status": "active"}, active_preview, active_binding, "stage2-http-task-active-0001")
        task = active_result["item"]
        assert task["status"] == "active"
        status, task_projection = request(student, base + f"/api/classops_api.php?action=student-get&id={task['id']}")
        assert status == 200 and task_projection["item"]["task"]["state"] == "submitted", (status, task_projection)

        # E. Completing a bound item is a terminal lifecycle revision, not a fresh delivery event.
        completed_preview, completed_binding = preview_update(owner, owner_csrf, base, task, {"status": "completed"})
        completed_result = confirm_update(owner, owner_csrf, base, task, {"status": "completed"}, completed_preview, completed_binding, "stage2-http-task-complete-0001")
        task = completed_result["item"]
        assert task["status"] == "completed"
        assert completed_result["notificationCount"] == 0, completed_result
        assert completed_result["directDeliveryIntentCount"] == 0, completed_result

        # F. Critical ACK is explicit and revision-bound; an item revision invalidates prior satisfaction.
        notice_input = {
            "cohortKey": "dentistry-1402",
            "type": "critical_notice",
            "title": "Stage2 critical fixture",
            "description": "Please acknowledge",
            "timing": {"startsAt": None, "endsAt": None, "dueAt": None, "timezone": "Asia/Tehran"},
            "location": "",
            "importance": "critical",
            "requireAck": True,
            "status": "draft",
        }
        notice_preview = preview_create(owner, owner_csrf, base, notice_input)
        notice_result = confirm_create(owner, owner_csrf, base, notice_input, notice_preview, "stage2-http-ack-create-0001")
        notice = notice_result["item"]
        status, acked = request(student, base + "/api/classops_api.php?action=student-ack", method="POST", csrf=student_csrf, fields={
            "id": notice["id"],
            "expectedRevision": notice["revision"],
            "idempotencyKey": "stage2-http-ack-student-0001",
        })
        assert status == 200, (status, acked)
        ack_record = acked["ack"]["ack"]
        assert ack_record["itemId"] == notice["id"] and ack_record["revision"] == notice["revision"], acked
        assert ack_record["intent"] == "explicit_user_ack" and acked["ack"]["stateChanged"] is True, acked
        status, notice_projection = request(student, base + f"/api/classops_api.php?action=student-get&id={notice['id']}")
        assert status == 200 and notice_projection["item"]["ack"]["acked"] is True

        revised_preview, revised_binding = preview_update(owner, owner_csrf, base, notice, {"description": "Changed revision"})
        revised_result = confirm_update(owner, owner_csrf, base, notice, {"description": "Changed revision"}, revised_preview, revised_binding, "stage2-http-ack-revise-0001")
        notice = revised_result["item"]
        status, revised_projection = request(student, base + f"/api/classops_api.php?action=student-get&id={notice['id']}")
        assert status == 200 and revised_projection["item"]["ack"]["acked"] is False, (status, revised_projection)

        # G. Saba remains a local reminder state only; it never claims external verification.
        service_input = {
            "cohortKey": "dentistry-1402",
            "type": "service_reminder",
            "title": "Saba reminder fixture",
            "description": "Local reminder only",
            "timing": {"startsAt": None, "endsAt": None, "dueAt": "2026-09-21T20:00:00+03:30", "timezone": "Asia/Tehran"},
            "location": "",
            "importance": "normal",
            "requireAck": False,
            "status": "draft",
        }
        service_preview = preview_create(owner, owner_csrf, base, service_input, service_ref="saba")
        service_result = confirm_create(owner, owner_csrf, base, service_input, service_preview, "stage2-http-saba-create-0001", service_ref="saba")
        service = service_result["item"]
        status, service_projection = request(student, base + f"/api/classops_api.php?action=student-get&id={service['id']}")
        assert status == 200, (status, service_projection)
        service_state = service_projection["item"]["service"]["state"]
        assert service_projection["item"]["service"]["externallyVerified"] is False
        status, service_transition = request(student, base + "/api/classops_api.php?action=student-service-transition", method="POST", csrf=student_csrf, fields={
            "id": service["id"],
            "expectedStateRevision": service_state["stateRevision"],
            "target": "completed",
            "commandId": "stage2-saba-complete-0001",
        })
        assert status == 200 and service_transition["externallyVerified"] is False, (status, service_transition)
        assert service_transition["state"]["state"] == "completed", service_transition

        print(json.dumps({
            "status": "ok",
            "previewZeroWrite": True,
            "confirmAudienceBound": True,
            "studentTaskState": True,
            "terminalNoFreshDelivery": True,
            "criticalAckRevisionBound": True,
            "sabaReminderOnly": True,
        }, ensure_ascii=False))
        return 0
    finally:
        server.terminate()
        with contextlib.suppress(subprocess.TimeoutExpired):
            server.wait(timeout=5)
        if server.poll() is None:
            server.kill()
        shutil.rmtree(temp, ignore_errors=True)


if __name__ == "__main__":
    raise SystemExit(main())
