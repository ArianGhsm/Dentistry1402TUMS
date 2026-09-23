from __future__ import annotations

import unittest

from dent_bot.booklet_reconcile import (
    RETRY_UNROUTED_SECONDS,
    album_caption_overrides,
    reconciliation_record,
    should_reconcile,
    source_fingerprint,
)


class BookletReconcileTests(unittest.TestCase):
    def test_new_or_edited_media_is_reconciled_independent_of_writer(self) -> None:
        initial = source_fingerprint(
            message_id=21,
            date="2026-09-22T13:36:55+00:00",
            edit_date="",
            text="",
            file_name="voice.m4a",
            file_size=100,
            mime_type="audio/m4a",
        )
        self.assertTrue(should_reconcile(None, initial, now=1000))

        unrouted = reconciliation_record(initial, status="unrouted", now=1000)
        self.assertFalse(
            should_reconcile(unrouted, initial, now=1000 + RETRY_UNROUTED_SECONDS - 1)
        )

        edited = source_fingerprint(
            message_id=21,
            date="2026-09-22T13:36:55+00:00",
            edit_date="2026-09-22T13:41:46+00:00",
            text="#مبانی_اندودانتیکس۲ #ترم۷ ویس جلسه دوم",
            file_name="voice.m4a",
            file_size=100,
            mime_type="audio/m4a",
        )
        self.assertTrue(
            should_reconcile(unrouted, edited, now=1001)
        )

    def test_captionless_album_member_inherits_only_unique_album_caption(self) -> None:
        caption = "🎤 ویس جلسه اول روش تحقیق ۲\n#روش_تحقیق۲ #ترم۷"
        rows = [
            {"messageId": 6, "groupedId": "album-1", "text": ""},
            {"messageId": 7, "groupedId": "album-1", "text": caption},
        ]
        self.assertEqual(album_caption_overrides(rows), {6: caption})

        ambiguous = rows + [
            {"messageId": 8, "groupedId": "album-1", "text": "کپشن متفاوت"},
        ]
        self.assertEqual(album_caption_overrides(ambiguous), {})

    def test_routed_media_is_stable_until_source_changes(self) -> None:
        fingerprint = source_fingerprint(
            message_id=20,
            date="2026-09-22T12:38:53+00:00",
            edit_date="2026-09-22T13:41:46+00:00",
            text="#سلامت_دهان_نظری۲ #ترم۷ ویس جلسه اول",
            file_name="voice.m4a",
            file_size=200,
            mime_type="audio/m4a",
        )
        routed = reconciliation_record(fingerprint, status="routed", now=2000)
        self.assertFalse(should_reconcile(routed, fingerprint, now=999999))


if __name__ == "__main__":
    unittest.main()
