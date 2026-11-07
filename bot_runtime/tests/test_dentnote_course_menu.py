from __future__ import annotations

import unittest

from dent_bot.booklet_app_workflows import BookletAppWorkflows
from dent_bot.booklets import resources_screen, sessions_screen


CATALOG = {
    "term": 7,
    "courses": [
        {
            "courseKey": "pathology-practical-1",
            "courseTitle": "آسیب‌شناسی عملی ۱",
            "bookletTag": "آسیب_شناسی_عملی۱",
            "sessions": [
                {"sessionNumber": 1, "title": "مقدمه"},
                {"sessionNumber": 2, "title": "مبحث دوم"},
            ],
        },
        {
            "courseKey": "oral-health-practical-2",
            "courseTitle": "سلامت دهان عملی ۲",
            "bookletTag": "سلامت_دهان_عملی۲",
            "sessions": [
                {"sessionNumber": 1, "title": "جلسه توجیهی"},
                {"sessionNumber": 2, "title": "کارگاه"},
            ],
        },
        {
            "courseKey": "without-notes",
            "courseTitle": "درس بدون دنت‌نوت",
            "bookletTag": "بدون_دنت_نوت",
            "sessions": [{"sessionNumber": 1, "title": "جلسه اول"}],
        },
    ],
}


class FakeMediaState:
    def __init__(self):
        self.media = {
            ("آسیب_شناسی_عملی۱", 1): ["path"],
            ("آسیب_شناسی_عملی۱", 2): ["path"],
            ("سلامت_دهان_عملی۲", 1): ["oral"],
        }
        self.offers = {
            "path": {"ref": "path-offer", "title": "دنت‌نوت پاتولوژی", "fulfillment": {"kind": "paid_file"}},
            "oral": {"ref": "oral-offer", "title": "دنت‌نوت سلامت دهان", "fulfillment": {"kind": "paid_file"}},
        }

    def protected_media_session_counts_for_tag(self, *, course_tag, term, content_kind):
        assert term == 7
        if content_kind == "booklet":
            return {}
        assert content_kind == "dentnote"
        return {session: len(assets) for (tag, session), assets in self.media.items() if tag == course_tag}

    def protected_media_for_tag(self, *, course_tag, term, session_no, content_kind):
        assert term == 7 and content_kind == "dentnote"
        return [{"asset": asset} for asset in self.media.get((course_tag, session_no), [])]

    def paid_file_asset_for_source(self, source):
        return {"assetRef": source["asset"]}

    def paid_file_offer_refs(self, asset_ref):
        return [asset_ref]

    def payment_offer(self, ref):
        return self.offers.get(ref)


def actions(screen):
    return [
        item.get("callback_data", "")
        for row in screen.keyboard.get("inline_keyboard", [])
        for item in row
    ]


class DentnoteCourseMenuTests(unittest.TestCase):
    def setUp(self):
        self.app = object.__new__(BookletAppWorkflows)
        self.app.state = FakeMediaState()
        self.app._ai_bulk_context = lambda _user_id, _catalog, _course_key: {
            "published": set(), "owned": set(), "eligible": set(),
        }

    def test_pathology_and_oral_health_render_dentnote_as_first_button(self):
        for course_key in ("pathology-practical-1", "oral-health-practical-2"):
            with self.subTest(course_key=course_key):
                screen = self.app._booklet_sessions_screen(123, CATALOG, course_key)
                self.assertTrue(screen.keyboard["inline_keyboard"][0][0]["text"].startswith("📕 دنت‌نوت"))
                self.assertIn(
                    f"v1:booklet-resource:{course_key}:1:dentnote",
                    actions(screen)[0],
                )
                self.assertIn(f"v1:booklet-session:{course_key}:1", actions(screen))

    def test_one_multi_session_file_yields_only_one_course_purchase(self):
        entries = self.app._course_dentnote_entries(CATALOG, "pathology-practical-1")
        self.assertEqual(len(entries), 1)
        self.assertEqual(entries[0][0]["ref"], "path-offer")

    def test_no_unbacked_or_inactive_product_advertised(self):
        empty = self.app._booklet_sessions_screen(123, CATALOG, "without-notes")
        self.assertFalse(any("dentnote" in a for a in actions(empty)))
        self.app.state.offers.pop("oral")
        oral = self.app._booklet_sessions_screen(123, CATALOG, "oral-health-practical-2")
        self.assertFalse(any("dentnote" in a for a in actions(oral)))

    def test_dentnote_is_not_a_session_level_button_or_status(self):
        screen = resources_screen(
            CATALOG, "oral-health-practical-2", 1,
            content_counts={"dentnote": 1, "voice": 1, "flashcard": 1},
        )
        self.assertNotIn("دنت‌نوت", str(screen.text))
        self.assertFalse(any("dentnote" in a for a in actions(screen)))
        self.assertTrue(any("flashcard" in a for a in actions(screen)))

    def test_first_button_stays_first_when_other_course_offers_exist(self):
        screen = sessions_screen(
            CATALOG, "oral-health-practical-2",
            dentnote_action="booklet-resource:oral-health-practical-2:1:dentnote",
            ai_published_sessions={1, 2}, ai_owned_sessions=set(),
            highlight_study_packs=True,
        )
        self.assertIn("dentnote", actions(screen)[0])
        self.assertIn("study-packs", actions(screen)[1])


if __name__ == "__main__":
    unittest.main()
