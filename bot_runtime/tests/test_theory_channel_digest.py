from datetime import datetime
from types import SimpleNamespace
from unittest.mock import Mock
from zoneinfo import ZoneInfo

import unittest
from unittest.mock import patch

from dent_bot.theory_channel_digest import dispatch_theory_channel_digest

TZ = ZoneInfo("Asia/Tehran")


def make_setup():
    settings = SimpleNamespace(platform="telegram", owner_id=12)
    api, state, site = Mock(), Mock(), Mock()
    state.has_notification_delivery.return_value = False
    api.call.return_value = {"type": "channel"}
    api.send.return_value = {"message_id": 7}
    site.request.return_value = {
        "success": True, "date": "2026-11-25", "inSchedule": True,
        "title": "📅 برنامه کلاس‌های نظری فردا | چهارشنبه ۱۴۰۵/۰۹/۰۴",
        "rows": [{"kind": "theory", "title": "پارسیل نظری", "start": "07:00",
                  "end": "08:00", "instructor": "دکتر عطری", "location": "مجازی"}],
    }
    return settings, api, state, site


def run(setup, hour=16, minute=0):
    settings, api, state, site = setup
    return dispatch_theory_channel_digest(settings=settings, api=api, state=state,
        site_api=site, now=datetime(2026, 11, 24, hour, minute, tzinfo=TZ))


class TheoryChannelDigestTests(unittest.TestCase):
    def setUp(self):
        import os
        self.environ = os.environ
        patcher = patch.dict(os.environ, {"DENT_CLASSOPS_TELEGRAM_INFORMATION_CHANNEL_ID": "-1001234567890"})
        patcher.start()
        self.addCleanup(patcher.stop)

    def test_due_and_receipt(self):
        setup = make_setup()
        _, api, state, site = setup
        assert run(setup, 15, 59) == "not-due"
        site.request.assert_not_called()
        assert run(setup) == "sent"
        chat, message, keyboard = api.send.call_args.args
        assert chat == -1001234567890
        assert "۰۷:۰۰" in str(message)
        assert "دکتر عطری" in message.rich_html
        assert "مجازی" in message.rich_html
        assert "برنامه شخصی" not in message.rich_html
        assert "<table bordered striped compact>" in message.rich_html
        assert keyboard == {"inline_keyboard": []}
        state.mark_notification_delivery.assert_called_once_with("term7-theory-channel:2026-11-25")
        state.has_notification_delivery.return_value = True
        assert run(setup, 21) == "already-sent"
        api.send.assert_called_once()


    def test_empty_and_catchup(self):
        setup = make_setup()
        _, api, _, site = setup
        site.request.return_value["rows"] = []
        assert run(setup, 21) == "sent"
        assert "فردا کلاس نظری ثبت نشده است." in str(api.send.call_args.args[1])


    def test_wrong_rows_or_date_fail_closed(self):
        setup = make_setup()
        _, api, state, site = setup
        site.request.return_value["rows"][0]["kind"] = "practical"
        with self.assertRaises(ValueError):
            run(setup)
        site.request.return_value["date"] = "2026-11-26"
        with self.assertRaises(ValueError):
            run(setup)
        api.send.assert_not_called()
        state.mark_notification_delivery.assert_not_called()


    def test_missing_binding_and_other_platform(self):
        setup = make_setup()
        settings, api, _, site = setup
        self.environ.pop("DENT_CLASSOPS_TELEGRAM_INFORMATION_CHANNEL_ID")
        assert run(setup) == "unconfigured"
        settings.platform = "bale"
        assert run(setup) == "disabled"
        api.send.assert_not_called()
        site.request.assert_not_called()


    def test_invalid_destination_and_failed_send(self):
        setup = make_setup()
        _, api, state, _ = setup
        api.call.return_value = {"type": "supergroup"}
        with self.assertRaises(ValueError):
            run(setup)
        api.send.assert_not_called()
        api.call.return_value = {"type": "channel"}
        api.send.return_value = {}
        with self.assertRaises(ValueError):
            run(setup)
        state.mark_notification_delivery.assert_not_called()
