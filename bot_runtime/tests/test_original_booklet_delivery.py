from __future__ import annotations

import unittest
from unittest.mock import Mock
from dent_bot.api import BotApiError, TelegramBotApi

class OriginalBookletPdfTests(unittest.TestCase):
    def test_plain_source_pdf_delivery(self):
        api = object.__new__(TelegramBotApi)
        api.call = Mock(return_value={"message_id": 42})
        source = {"contentKind":"booklet","sourceChatId":-1003706539157,"telegramMethod":"sendDocument","mimeType":"application/pdf","fileId":"file-1","caption":"جزوه اصلی"}
        response = api.send_original_booklet_pdf(17,source,expected_source_chat_id=-1003706539157)
        self.assertEqual(response["message_id"],42)
        method,payload = api.call.call_args.args
        self.assertEqual(method,"sendDocument")
        self.assertFalse(payload["protect_content"])
        self.assertEqual(payload["document"],"file-1")
    def test_other_sources_denied(self):
        api = object.__new__(TelegramBotApi)
        api.call = Mock()
        for kind in ("paid_file","dentnote"):
            source = {"contentKind":kind,"sourceChatId":-1003706539157,"telegramMethod":"sendDocument","mimeType":"application/pdf","fileId":"file-1"}
            with self.assertRaises(BotApiError):
                api.send_original_booklet_pdf(17,source,expected_source_chat_id=-1003706539157)
        api.call.assert_not_called()

if __name__=='__main__': unittest.main()
