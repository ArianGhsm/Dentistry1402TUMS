from __future__ import annotations

from dent_bot.app import DentBotApp
from dent_bot.message_frames import frame_error
from dent_bot.payment_app_workflows import PaymentAppWorkflows


def test_payment_workflows_are_inherited_from_scoped_mixin() -> None:
    assert issubclass(DentBotApp, PaymentAppWorkflows)
    for name in (
        "_eligible_products",
        "_payment_people_report",
        "_send_payment_export",
        "_send_payment_reminder_batch",
        "_send_payment_product_batch",
    ):
        assert name not in DentBotApp.__dict__
        assert name in PaymentAppWorkflows.__dict__


def test_shared_error_frame_keeps_existing_persian_html_contract() -> None:
    assert frame_error("نامعتبر <x>") == "<b>🔴 انجام نشد</b>\n\nنامعتبر &lt;x&gt;"
