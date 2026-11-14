from __future__ import annotations

import queue
import tempfile
import threading
import time
import unittest
from pathlib import Path
from unittest.mock import Mock

from dent_bot.api import BotApiError, TelegramBotApi
from dent_bot.protected_media import DeliveryJob, ProtectedMediaDispatcher
from dent_bot.state import BotState


CHANNEL = -1003706539157


def pdf_source(kind, message_id=101, unique_id='unique-pdf'):
    return dict(contentKind=kind, sourcePlatform='telegram',
                sourceChatId=CHANNEL, sourceMessageId=message_id,
                telegramMethod='sendDocument', mimeType='application/pdf',
                fileName='جزوه.pdf', fileId='original-file-id',
                fileUniqueId=unique_id, active=True)


class UnrestrictedStudyPdfTests(unittest.TestCase):
    def test_transport_rejects_untrusted_or_wrong_source(self):
        api = object.__new__(TelegramBotApi)
        api.call = Mock(return_value={'message_id': 45})
        for kind in ('booklet', 'ai_booklet', 'paid_file'):
            src = pdf_source(kind)
            api.send_original_booklet_pdf(
                123, src, expected_source_chat_id=CHANNEL,
                allowed_content_kind=kind)
            method, payload = api.call.call_args.args
            self.assertEqual(method, 'sendDocument')
            self.assertIs(payload['protect_content'], False)
            self.assertEqual(payload['document'], src['fileId'])
        for change in (
            {'sourceChatId': -999}, {'mimeType': 'image/jpeg'},
            {'telegramMethod': 'sendAudio'}, {'fileId': ''},
            {'contentKind': 'reference'},
        ):
            with self.assertRaises(BotApiError):
                api.send_original_booklet_pdf(
                    123, {**pdf_source('ai_booklet'), **change},
                    expected_source_chat_id=CHANNEL,
                    allowed_content_kind='ai_booklet')
        with self.assertRaises(BotApiError):
            api.send_original_booklet_pdf(
                123, pdf_source('paid_file'),
                expected_source_chat_id=CHANNEL)

    def test_dentnote_provenance_requires_exact_active_record(self):
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / 'state.sqlite3')
            try:
                row = dict(**pdf_source('dentnote'),
                           courseCode='test-course', courseName='درس تست',
                           courseTag='درس_تست', term=7, sessionNo=1,
                           caption='دنت‌نوت')
                state.replace_protected_media_message(CHANNEL, 101, [row])
                asset = pdf_source('paid_file')
                self.assertTrue(state.is_active_dentnote_asset(
                    asset, expected_source_chat_id=CHANNEL))
                for change in (
                    {'sourceMessageId': 102}, {'fileUniqueId': 'wrong'},
                    {'sourceChatId': -100}, {'contentKind': 'booklet'},
                    {'active': False}, {'fileId': ''},
                    {'mimeType': 'image/jpeg'},
                ):
                    self.assertFalse(state.is_active_dentnote_asset(
                        {**asset, **change}, expected_source_chat_id=CHANNEL))
                state.replace_protected_media_message(CHANNEL, 101, [])
                self.assertFalse(state.is_active_dentnote_asset(
                    asset, expected_source_chat_id=CHANNEL))
            finally:
                state.close()

    def dispatch(self, kind, is_dentnote=False, ordinary_original=True, authorized=True):
        events = []
        source = pdf_source(kind)

        class State:
            def protected_media_source(self, _id):
                return source
            def paid_file_asset_by_id(self, _id):
                return source
            def is_active_dentnote_asset(self, asset, *, expected_source_chat_id):
                events.append(('lookup', expected_source_chat_id))
                return is_dentnote
            def record_protected_media_delivery(self, _user, _id, status, *, message_id=0):
                events.append(('delivery', status, message_id))

        class Api:
            def send_original_booklet_pdf(self, _user, _source, **kwargs):
                events.append(('original', kwargs['allowed_content_kind']))
                return {'message_id': 88}
            def send(self, _user, _text, _keyboard):
                events.append(('notice',))
                return {'message_id': 89}

        dispatcher = object.__new__(ProtectedMediaDispatcher)
        dispatcher.api = Api()
        dispatcher.state = State()
        dispatcher.original_booklet_delivery = ordinary_original
        dispatcher.original_booklet_source_chat_id = CHANNEL
        dispatcher.authorize = lambda _user, _source: authorized
        dispatcher._send_pdf = lambda _job, _source: (
            events.append(('watermarked',)) or {'message_id': 91})
        dispatcher._lock = threading.Lock()
        dispatcher._pending = set()
        dispatcher.queue = queue.Queue()
        dispatcher.queue.put(DeliveryJob(
            321, 55, 'doc', time.monotonic(),
            source_type='paid_file' if kind == 'paid_file' else 'booklet'))
        dispatcher.queue.put(None)
        dispatcher._run()
        return events

    def test_ai_booklet_original_irrespective_of_ordinary_flag(self):
        events = self.dispatch('ai_booklet', ordinary_original=False)
        self.assertIn(('original', 'ai_booklet'), events)
        self.assertNotIn(('watermarked',), events)
        self.assertIn(('delivery', 'sent', 88), events)

    def test_only_linked_paid_dentnote_is_unlocked(self):
        events = self.dispatch('paid_file', is_dentnote=True)
        self.assertIn(('original', 'paid_file'), events)
        self.assertNotIn(('watermarked',), events)
        events = self.dispatch('paid_file', is_dentnote=False)
        self.assertIn(('watermarked',), events)
        self.assertNotIn(('original', 'paid_file'), events)

    def test_ordinary_and_denied_access_remain_protected(self):
        self.assertIn(('watermarked',),
                      self.dispatch('booklet', ordinary_original=False))
        self.assertIn(('original', 'booklet'),
                      self.dispatch('booklet', ordinary_original=True))
        events = self.dispatch('ai_booklet', authorized=False)
        self.assertNotIn(('original', 'ai_booklet'), events)
        self.assertNotIn(('watermarked',), events)
        self.assertIn(('delivery', 'denied', 0), events)


    def test_paid_dentnote_checkout_does_not_require_watermark_identity(self):
        from dent_bot.payment_app_workflows import PaymentAppWorkflows

        class State:
            def paid_file_asset(self, _ref):
                return pdf_source('paid_file')
            def is_active_dentnote_asset(self, _asset, *, expected_source_chat_id):
                self.checked_channel = expected_source_chat_id
                return self.is_dentnote

        class SiteApi:
            def __init__(self):
                self.called = 0
            def booklet_watermark_identity(self, _user):
                self.called += 1

        app = PaymentAppWorkflows()
        app.platform = 'telegram'
        app.state = State()
        app.booklet_source_channel_id = CHANNEL
        app.site_api = SiteApi()
        offer = {'fulfillment': {'kind': 'paid_file', 'assetRef': 'a'}}
        app.state.is_dentnote = True
        self.assertIsNone(app._paid_file_purchase_blocker(333, offer))
        self.assertEqual(app.site_api.called, 0)
        self.assertEqual(app.state.checked_channel, CHANNEL)

        app.state.is_dentnote = False
        self.assertIsNone(app._paid_file_purchase_blocker(333, offer))
        self.assertEqual(app.site_api.called, 1)


if __name__ == '__main__':
    unittest.main()
