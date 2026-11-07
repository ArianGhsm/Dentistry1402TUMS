from __future__ import annotations

import unittest
from unittest.mock import Mock

from dent_bot.booklet_app_workflows import BookletAppWorkflows
from dent_bot.booklets import sessions_screen
from dent_bot.ui import Screen


CATALOG = {
    "term": 7,
    "courses": [{
        "courseKey": "partial-basics-theory",
        "courseTitle": "مبانی پروتز پارسیل",
        "bookletTag": "پارسیل",
        "sessions": [
            {"sessionNumber": 1, "title": "مقدمه"},
            {"sessionNumber": 2, "title": "تکیه‌گاه"},
            {"sessionNumber": 3, "title": "رکوردهای طولانی"},
        ],
    }],
}


def source(identifier, session_no, *, kind="booklet", unique=None, **kwargs):
    item = {
        "id": identifier,
        "term": 7,
        "courseCode": "partial-basics-theory",
        "courseTag": "پارسیل",
        "sessionNo": session_no,
        "contentKind": kind,
        "sourceChatId": -1001,
        "sourceMessageId": identifier,
        "telegramMethod": "sendDocument",
        "fileId": f"file-{identifier}",
        "fileUniqueId": str(unique or identifier),
        "mimeType": "application/pdf",
        "fileName": "source.pdf",
    }
    item.update(kwargs)
    return item


class SourceState:
    def __init__(self, entries):
        self.entries = entries

    def protected_media_session_counts_for_tag(self, *, course_tag, term, content_kind):
        return {
            session_no: len([s for s in records if s.get("contentKind") == content_kind])
            for session_no, records in self.entries.items()
            if any(s.get("contentKind") == content_kind for s in records)
        }

    def protected_media_for_tag(self, *, course_tag, term, session_no, content_kind):
        # Deliberately return mixed source kinds as a defensive regression.
        return list(self.entries.get(session_no, []))


def actions(screen):
    return [
        b["callback_data"]
        for row in screen.keyboard["inline_keyboard"]
        for b in row
    ]


class BulkRegularBookletTests(unittest.TestCase):
    def setUp(self):
        self.state = SourceState({
            1: [source(101, 1), source(501, 1, kind="dentnote"),
                source(601, 1, kind="ai_booklet")],
            2: [source(102, 2), source(103, 2, unique="shared"),
                source(701, 2, kind="reference")],
            3: [source(104, 3, unique="shared"), source(105, 3, fileId=""),
                source(106, 3, telegramMethod="sendAudio", mimeType="audio/mpeg"),
                source(107, 3, courseTag="دیگر"), source(108, 3, term=6)],
        })
        self.app = object.__new__(BookletAppWorkflows)
        self.app.state = self.state
        self.app.media_dispatcher = Mock()
        self.app.booklet_access_allowed = lambda uid, src: uid == 1
        self.app._booklet_access_screen = lambda uid: (False, Screen("دسترسی ندارید", {}))
        self.app._course_dentnote_entries = lambda _cat, _key: [({}, 1)]
        self.app._ai_bulk_context = lambda *_args, **_kwargs: {
            "published": set(), "owned": set(), "eligible": set(),
        }

    def test_syllabus_sorted_unique_only_ordinary_pdfs(self):
        result = self.app._course_regular_booklets(CATALOG, "partial-basics-theory")
        self.assertEqual([s["id"] for s in result], [101, 102, 103])
        self.assertTrue(all(s["contentKind"] == "booklet" for s in result))

    def test_button_under_dentnote_before_sessions_only_when_authorized(self):
        allowed = self.app._booklet_sessions_screen(1, CATALOG, "partial-basics-theory")
        rows = allowed.keyboard["inline_keyboard"]
        self.assertEqual(rows[0][0]["callback_data"], "v1:booklet-resource:partial-basics-theory:1:dentnote")
        self.assertEqual(rows[1][0]["callback_data"], "v1:booklet-bulk:partial-basics-theory")
        self.assertIn("۳ فایل", rows[1][0]["text"])
        self.assertIn("v1:booklet-session:partial-basics-theory:1", actions(allowed))
        denied = self.app._booklet_sessions_screen(2, CATALOG, "partial-basics-theory")
        self.assertFalse(any("booklet-bulk" in name for name in actions(denied)))

    def test_click_enqueues_regular_files_exactly_once_and_stays_navigable(self):
        self.app.media_dispatcher.enqueue_batch.return_value = ["queued"] * 3
        response = self.app._booklet_screen_for_action(
            "booklet-bulk:partial-basics-theory", 1, CATALOG,
        )
        self.app.media_dispatcher.enqueue_batch.assert_called_once_with(
            1, [("booklet", 101), ("booklet", 102), ("booklet", 103)],
        )
        self.assertIn("۳", response.text)
        self.assertIn("به‌ترتیب جلسات", response.text)
        self.assertIn("v1:booklet-course:partial-basics-theory", actions(response))

    def test_denied_old_callback_never_enqueues(self):
        response = self.app._booklet_screen_for_action(
            "booklet-bulk:partial-basics-theory", 2, CATALOG,
        )
        self.assertIn("دسترسی ندارید", response.text)
        self.app.media_dispatcher.enqueue_batch.assert_not_called()

    def test_partial_queue_reports_partial_and_retry(self):
        self.app.media_dispatcher.enqueue_batch.return_value = ["queued", "full", "duplicate"]
        response = self.app._booklet_screen_for_action(
            "booklet-bulk:partial-basics-theory", 1, CATALOG,
        )
        self.assertIn("۱", response.text)
        self.assertIn("۳", response.text)
        self.assertIn("برخی فایل‌ها", response.text)
        self.assertIn("دوباره تلاش کن", response.text)
        self.assertIn("v1:booklet-bulk:partial-basics-theory", actions(response))

    def test_duplicate_queued_batch_does_not_claim_new_delivery(self):
        self.app.media_dispatcher.enqueue_batch.return_value = ["duplicate"] * 3
        response = self.app._booklet_screen_for_action(
            "booklet-bulk:partial-basics-theory", 1, CATALOG,
        )
        self.assertIn("هم‌اکنون در صف", response.text)
        self.assertNotIn("در صف ارسال قرار گرفت", response.text)

    def test_empty_missing_dispatcher_and_missing_course(self):
        self.app.media_dispatcher = None
        response = self.app._booklet_screen_for_action(
            "booklet-bulk:partial-basics-theory", 1, CATALOG,
        )
        self.assertIn("در دسترس نیست", response.text)
        self.app.media_dispatcher = Mock()
        self.app.state = SourceState({})
        response = self.app._booklet_screen_for_action(
            "booklet-bulk:partial-basics-theory", 1, CATALOG,
        )
        self.assertIn("هنوز جزوهٔ عادی", response.text)
        screen = self.app._booklet_sessions_screen(1, CATALOG, "partial-basics-theory")
        self.assertFalse(any("booklet-bulk" in name for name in actions(screen)))
        response = self.app._booklet_screen_for_action("booklet-bulk:missing", 1, CATALOG)
        self.assertIn("پیدا نشد", response.text)

    def test_renderer_has_persian_numbers_and_only_one_bulk_action(self):
        screen = sessions_screen(
            CATALOG, "partial-basics-theory",
            dentnote_action="booklet-dentnotes:partial-basics-theory",
            regular_booklet_count=11,
        )
        self.assertEqual(
            [a for a in actions(screen) if "booklet-bulk" in a],
            ["v1:booklet-bulk:partial-basics-theory"],
        )
        self.assertIn("۱۱ فایل", screen.keyboard["inline_keyboard"][1][0]["text"])
        self.assertNotIn("11", screen.keyboard["inline_keyboard"][1][0]["text"])


if __name__ == "__main__":
    unittest.main()
