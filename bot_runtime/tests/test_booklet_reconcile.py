from __future__ import annotations

import unittest

from dent_bot.booklet_reconcile import (
    RETRY_UNROUTED_SECONDS,
    album_caption_overrides,
    blocking_private_unrouted,
    reconciliation_record,
    should_prune_missing_sources,
    should_reconcile,
    source_fingerprint,
    source_history_complete,
    source_media_field,
    source_requires_reusable_file_id,
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


    def test_unrouted_private_source_retries_each_reconcile_minute(self) -> None:
        self.assertEqual(RETRY_UNROUTED_SECONDS, 60)
        record = reconciliation_record("fingerprint", status="unrouted", now=1000)
        self.assertEqual(record["nextRetryAt"], 1060)
        self.assertFalse(should_reconcile(record, "fingerprint", now=1059))
        self.assertTrue(should_reconcile(record, "fingerprint", now=1060))

    def test_missing_source_pruning_requires_complete_private_history(self) -> None:
        self.assertTrue(source_history_complete(76, 200))
        self.assertFalse(source_history_complete(200, 200))
        self.assertTrue(should_prune_missing_sources("private", 76, 200))
        self.assertFalse(should_prune_missing_sources("private", 200, 200))
        self.assertFalse(should_prune_missing_sources("power", 76, 200))

    def test_private_unrouted_is_operationally_blocking(self) -> None:
        self.assertEqual(blocking_private_unrouted([
            {"role": "private", "unrouted": 2},
            {"role": "power", "unrouted": 9},
        ]), 2)
        self.assertEqual(blocking_private_unrouted([
            {"role": "private", "unrouted": 0},
            {"role": "power", "unrouted": 9},
        ]), 0)

    def test_source_media_filter_rejects_photo_false_positive(self) -> None:
        class PhotoMessage:
            voice = None
            audio = None
            document = None
            photo = object()

        class DocumentMessage:
            voice = None
            audio = None
            document = object()
            photo = None

        self.assertEqual(source_media_field(PhotoMessage()), "")
        self.assertEqual(source_media_field(DocumentMessage()), "document")
        self.assertFalse(source_requires_reusable_file_id("power"))
        self.assertTrue(source_requires_reusable_file_id("private"))

    def test_reconciler_never_uses_user_facing_sync_forward(self) -> None:
        root = __import__("pathlib").Path(__file__).resolve().parents[1]
        worker = (root / "scripts" / "reconcile-booklet-source-channel.py").read_text(encoding="utf-8")
        hook = (root / "scripts" / "dent1402-booklet-post-write-hook.sh").read_text(encoding="utf-8")
        self.assertIn("register-metadata", worker)
        self.assertIn("deactivate-missing", worker)
        self.assertIn("privateUnrouted", worker)
        self.assertIn("DENT_BOT_POWER_SOURCE_CHANNEL_ID", worker)
        self.assertIn("--source-channel-id", worker)
        self.assertNotIn("sync-existing", worker)
        self.assertNotIn("forwardMessage", worker)
        self.assertIn("integrated-dent-booklet-source-reconcile.service", hook)
        self.assertIn("DENT_BOT_POWER_SOURCE_CHANNEL_ID", hook)
        self.assertNotIn("sync-existing", hook)

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
