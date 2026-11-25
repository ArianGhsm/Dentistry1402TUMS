from types import SimpleNamespace
from unittest import TestCase
from unittest.mock import Mock, patch

from dent_bot.academic_term7_rich import academic_notification_text
from dent_bot.classops_ui import classops_home_screen, daily_screen
from dent_bot.final_exam_schedule import final_exam_schedule_screen
from dent_bot.theory_channel_digest import dispatch_final_exam_channel

ROW = {"title": "اندو نظری ۱", "startsAt": "2026-12-09T11:00:00+00:00",
       "location": "آمفی‌تئاتر ۴۰۰ نفره", "typeLabel": "پایان‌ترم نظری"}
PAYLOAD = {"success": True, "eligible": True, "revision": "a" * 24,
           "semesterLabel": "نیمسال اول ۱۴۰۵–۱۴۰۶", "rows": [ROW]}


class FinalExamScheduleTests(TestCase):
    def test_full_screen_and_cohort(self):
        screen = final_exam_schedule_screen(PAYLOAD)
        for text in ("برنامه امتحانات نظری", "چهارشنبه ۱۴۰۵/۰۹/۱۸", "۱۴:۳۰", "آمفی‌تئاتر ۴۰۰ نفره"):
            self.assertIn(text, screen.text)
            self.assertIn(text, screen.text.rich_html)
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("تعداد امتحانات: ۱", screen.text.rich_html)
        self.assertNotIn(ROW["title"], final_exam_schedule_screen({"eligible": False, "rows": [ROW]}).text)

    def test_hub_and_daily(self):
        screen = classops_home_screen([], {"eligible": True})
        labels = [b["text"] for row in screen.keyboard["inline_keyboard"] for b in row]
        self.assertIn("📝 برنامه امتحانات", labels)
        day = {"localDate": "2026-12-01", "items": [], "upcomingFinalExams": [ROW]}
        self.assertIn("امتحان‌های پیش رو", daily_screen(day).text.rich_html)
        self.assertNotIn("امتحان‌های پیش رو", daily_screen({"localDate": "2026-11-25", "items": []}).text)

    def test_notification_and_empty_teaching(self):
        meta = {"academicFinalExamRows": [ROW]}
        reminder = academic_notification_text({"source": "academic-term7-final-exam",
            "title": "📝 یادآوری امتحان پایان‌ترم | ۱۲ ساعت قبل", "meta": meta})
        self.assertIn("۱۲ ساعت قبل", reminder.rich_html)
        self.assertIn("۱۴:۳۰", reminder.rich_html)
        daily = academic_notification_text({"source": "academic-term7",
            "title": "📅 برنامه فردا | چهارشنبه ۱۴۰۵/۰۹/۱۸", "meta": meta})
        self.assertIn("امتحان‌های پیش رو", daily.rich_html)
        self.assertIn("اندو نظری ۱", daily.rich_html)
        self.assertNotIn("برای فردا برنامه ثبت‌شده‌ای ندارید", daily)

    def test_channel_revision_receipt(self):
        settings = SimpleNamespace(platform="telegram", owner_id=1)
        api, state, site = Mock(), Mock(), Mock()
        state.has_notification_delivery.return_value = False
        site.request.return_value = PAYLOAD
        api.call.return_value = {"type": "channel"}
        api.send.return_value = {"message_id": 3}
        with patch.dict("os.environ", {"DENT_CLASSOPS_TELEGRAM_INFORMATION_CHANNEL_ID": "-100123456"}):
            args = dict(settings=settings, api=api, state=state, site_api=site)
            self.assertEqual(dispatch_final_exam_channel(**args), "sent")
            state.mark_notification_delivery.assert_called_once_with("term7-final-exam-channel:" + "a" * 24)
            self.assertIn("برنامه امتحانات پایان‌ترم", api.send.call_args.args[1].rich_html)
            self.assertEqual(api.send.call_args.args[2], {"inline_keyboard": []})
            state.has_notification_delivery.return_value = True
            self.assertEqual(dispatch_final_exam_channel(**args), "already-sent")
            api.send.assert_called_once()

    def test_midterm_and_final_separate_and_no_repeat(self):
        from datetime import datetime
        from zoneinfo import ZoneInfo
        midterm = dict(ROW, title="ارتودنسی نظری ۱", startsAt="2026-11-29T07:00:00+03:30", typeLabel="میان‌ترم نظری")
        past = dict(midterm, title="تشخیصی ۳", startsAt="2026-11-22T07:00:00+03:30")
        payload = dict(PAYLOAD, midtermRows=[past, midterm], midtermRevision="b" * 24)
        screen = final_exam_schedule_screen(payload, now=datetime(2026, 11, 25, 22, tzinfo=ZoneInfo("Asia/Tehran")))
        self.assertIn("میان‌ترم", screen.text.rich_html)
        self.assertIn("پایان‌ترم", screen.text.rich_html)
        self.assertIn("<td>گذشته</td>", screen.text.rich_html)
        self.assertIn("<td>پیش رو</td>", screen.text.rich_html)
        reminder = academic_notification_text({"source": "academic-term7-midterm-exam", "title": "📝 یادآوری امتحان میان‌ترم | ۱۲ ساعت قبل", "meta": {"academicFinalExamRows": [midterm]}})
        self.assertIn("میان‌ترم", reminder.rich_html)
        self.assertIn("۰۷:۰۰", reminder.rich_html)
        settings = SimpleNamespace(platform="telegram", owner_id=1)
        api, state, site = Mock(), Mock(), Mock()
        state.has_notification_delivery.side_effect = lambda receipt: receipt.startswith("term7-final-exam-channel:")
        site.request.return_value = payload
        api.call.return_value = {"type": "channel"}
        api.send.return_value = {"message_id": 3}
        with patch.dict("os.environ", {"DENT_CLASSOPS_TELEGRAM_INFORMATION_CHANNEL_ID": "-100123456"}):
            self.assertEqual(dispatch_final_exam_channel(settings=settings, api=api, state=state, site_api=site), "sent")
        api.send.assert_called_once()
        self.assertIn("برنامه امتحانات میان‌ترم", api.send.call_args.args[1].rich_html)
        state.mark_notification_delivery.assert_called_once_with("term7-midterm-exam-channel:" + "b" * 24)
