from __future__ import annotations

import json
import subprocess
import unittest
from pathlib import Path

from dent_bot.booklets import parse_source_caption


ROOT = Path(__file__).resolve().parents[2]


def _term7_booklet_catalog() -> dict:
    php = (
        'require "public_html/api/classops_term7_syllabus.php"; '
        'echo json_encode(classops_term7_syllabus_booklet_catalog(), '
        'JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);'
    )
    completed = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
        encoding="utf-8",
    )
    return dict(json.loads(completed.stdout))


class BookletCatalogContractTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.catalog = _term7_booklet_catalog()
        cls.courses = [
            dict(item)
            for item in cls.catalog.get("courses", [])
            if isinstance(item, dict)
        ]

    def test_every_live_catalog_alias_routes_to_its_canonical_course(self) -> None:
        self.assertGreater(len(self.courses), 0)
        for course in self.courses:
            sessions = list(course.get("sessions") or [])
            aliases = list(course.get("bookletTagAliases") or [])
            canonical = str(course.get("bookletTag") or "")
            self.assertTrue(sessions, course.get("courseKey"))
            self.assertTrue(canonical, course.get("courseKey"))
            self.assertIn(canonical, aliases)
            session_no = int(sessions[0]["sessionNumber"])
            for alias in aliases:
                with self.subTest(course=course.get("courseKey"), alias=alias):
                    caption = (
                        f"🎤 ویس جلسه {session_no}\n"
                        f"📚 {course['courseTitle']}\n"
                        f"#{alias} #ترم۷"
                    )
                    parsed = parse_source_caption(caption, self.catalog)
                    self.assertIsNotNone(parsed)
                    assert parsed is not None
                    self.assertEqual(parsed.course_code, course["courseKey"])
                    self.assertEqual(parsed.session_no, session_no)

    def test_every_unique_course_title_is_alias_independent_fallback(self) -> None:
        title_counts: dict[str, int] = {}
        for course in self.courses:
            title = str(course.get("courseTitle") or "").strip()
            title_counts[title] = title_counts.get(title, 0) + 1
        for index, course in enumerate(self.courses, start=1):
            title = str(course.get("courseTitle") or "").strip()
            if title_counts.get(title) != 1:
                continue
            session_no = int(list(course.get("sessions") or [])[0]["sessionNumber"])
            caption = (
                f"🎤 ویس جلسه {session_no}\n"
                f"📚 {title}\n"
                f"#هشتگ_ثبت_نشده_{index} #ترم۷"
            )
            parsed = parse_source_caption(caption, self.catalog)
            self.assertIsNotNone(parsed)
            assert parsed is not None
            self.assertEqual(parsed.course_code, course["courseKey"])

    def test_conflicting_course_title_and_hashtag_fail_closed(self) -> None:
        self.assertGreaterEqual(len(self.courses), 2)
        left, right = self.courses[:2]
        session_no = int(list(left.get("sessions") or [])[0]["sessionNumber"])
        caption = (
            f"🎤 ویس جلسه {session_no}\n"
            f"📚 {left['courseTitle']}\n"
            f"#{right['bookletTag']} #ترم۷"
        )
        self.assertIsNone(parse_source_caption(caption, self.catalog))


if __name__ == "__main__":
    unittest.main()
