from __future__ import annotations

import json
import sqlite3
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from dent_bot.api import BotApiError, TelegramBotApi
from dent_bot.protected_media import ProtectedMediaDispatcher
from dent_bot.state import BotState


class BookletDeliveryRecoveryTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.state = BotState(Path(self.temp.name) / "state.sqlite3")
        with self.state._lock:
            self.state.connection.execute(
                "INSERT INTO protected_media_sources (source_chat_id,source_message_id,"
                "course_code,course_name,course_tag,term,session_no,content_kind,telegram_method,file_id)"
                " VALUES (-100,1,'endo','اندو','اندو',7,7,'ai_booklet','sendDocument','test-file')"
            )
            self.source_id = int(
                self.state.connection.execute(
                    "SELECT id FROM protected_media_sources WHERE source_message_id=1"
                ).fetchone()[0]
            )
            self.state.connection.commit()

    def tearDown(self) -> None:
        self.state.close()
        self.temp.cleanup()

    def _record_old(self, user: int, status: str) -> None:
        self.state.record_protected_media_delivery(user, self.source_id, status)
        with self.state._lock:
            self.state.connection.execute(
                "UPDATE protected_media_deliveries SET created_at=datetime('now','-10 minutes')"
                " WHERE id=(SELECT max(id) FROM protected_media_deliveries)"
            )
            self.state.connection.commit()

    def test_failed_entitled_delivery_eligible_after_backoff(self) -> None:
        self._record_old(101, "failed")
        self.assertEqual(
            self.state.failed_protected_media_candidates(),
            [(101, self.source_id)],
        )

    def test_recent_failure_is_not_replayed_immediately(self) -> None:
        self.state.record_protected_media_delivery(102, self.source_id, "failed")
        self.assertEqual(self.state.failed_protected_media_candidates(), [])

    def test_prior_success_prevents_duplicate_delivery(self) -> None:
        self._record_old(103, "sent")
        self._record_old(103, "failed")
        self.assertEqual(self.state.failed_protected_media_candidates(), [])

    def test_recovery_attempts_are_bounded(self) -> None:
        for _ in range(12):
            self._record_old(104, "failed")
        self.assertEqual(self.state.failed_protected_media_candidates(), [])

    def test_recovery_queues_only_due_requests(self) -> None:
        self._record_old(105, "failed")
        dispatcher = object.__new__(ProtectedMediaDispatcher)
        dispatcher.state = self.state
        queued = []
        dispatcher.enqueue = lambda uid, sid: queued.append((uid, sid)) or "queued"
        result = dispatcher.retry_failed_deliveries()
        self.assertEqual(result["queued"], 1)
        self.assertEqual(queued, [(105, self.source_id)])


class TelegramHttp2UploadTests(unittest.TestCase):
    def test_http2_upload_keeps_bot_token_off_process_arguments(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "personalized.pdf"
            path.write_bytes(b"%PDF-1.4\nTEST")
            api = TelegramBotApi("123:TEST_SECRET", proxy_url="http://127.0.0.1:11082")
            success = {"ok": True, "result": {"message_id": 55, "document": {"file_id": "cached"}}}
            try:
                with patch("dent_bot.api.subprocess.run") as call:
                    call.return_value = subprocess.CompletedProcess([], 0, json.dumps(success).encode(), b"")
                    result = api.send_protected_document_curl(101, path, caption="<b>جزوه</b>")
                self.assertEqual(result["message_id"], 55)
                cmd = call.call_args.args[0]
                self.assertIn("--http2", cmd)
                self.assertIn("protect_content=true", cmd)
                self.assertTrue(any("filename=dent1402-personalized.pdf" in arg for arg in cmd))
                self.assertNotIn("TEST_SECRET", " ".join(cmd))
                self.assertIn(b"123:TEST_SECRET", call.call_args.kwargs["input"])
                self.assertIn(b"11082", call.call_args.kwargs["input"])
            finally:
                api.close()

    def test_failed_http2_upload_is_not_recorded_as_success(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "test.pdf"
            path.write_bytes(b"%PDF-1.4")
            api = TelegramBotApi("123:TEST_SECRET")
            try:
                with patch("dent_bot.api.subprocess.run") as call:
                    call.return_value = subprocess.CompletedProcess([], 56, b"", b"")
                    with self.assertRaises(BotApiError) as raised:
                        api.send_protected_document_curl(101, path)
                self.assertTrue(raised.exception.transient)
            finally:
                api.close()


if __name__ == "__main__":
    unittest.main()
