from __future__ import annotations

import unittest

from dent_bot.site_api import SiteApiClient
from dent_bot.ui import (
    account_screen,
    payment_phone_required_screen,
    profile_edit_fields_screen,
)


class CaptureSiteApi(SiteApiClient):
    def __init__(self) -> None:
        self.calls = []

    def request(self, action: str, user_id: int, **payload):
        self.calls.append((action, user_id, payload))
        return {"success": True, "phoneMasked": "۰۹۱۲•••••••"}


class PhoneEnrollmentRegressionTests(unittest.TestCase):
    def test_profile_edit_exposes_verified_phone_flow(self) -> None:
        screen = profile_edit_fields_screen()
        actions = [
            str(item.get("callback_data") or "")
            for row in screen.keyboard["inline_keyboard"]
            for item in row
        ]
        self.assertIn("v1:phone-enroll", actions)
        self.assertIn("📱 شماره موبایل", str(screen.keyboard))

    def test_payment_phone_error_has_direct_recovery_action(self) -> None:
        screen = payment_phone_required_screen()
        self.assertIn("شماره موبایل برای پرداخت لازم است", screen.text)
        self.assertIn("v1:phone-enroll", str(screen.keyboard))

    def test_account_shows_phone_action_and_canonical_mask(self) -> None:
        screen = account_screen(
            "https://dentistry1402.ir",
            platform="telegram",
            linked_user={
                "name": "کاربر تست",
                "roleLabel": "دانشجو",
                "phoneMasked": "۰۹۱۲•••••••",
            },
            onboarding_profile={"firstName": "کاربر", "lastName": "تست"},
        )
        self.assertIn("۰۹۱۲•••••••", screen.text)
        self.assertIn("v1:phone-enroll", str(screen.keyboard))

    def test_site_api_phone_enrollment_uses_canonical_service_actions(self) -> None:
        api = CaptureSiteApi()
        api.request_phone_enrollment(20, phone_number="09121234567")
        api.verify_phone_enrollment(
            20,
            phone_number="09121234567",
            code="123456",
        )
        self.assertEqual(api.calls[0][0], "requestPhoneEnrollmentV1")
        self.assertEqual(api.calls[0][2]["phoneNumber"], "09121234567")
        self.assertEqual(api.calls[1][0], "verifyPhoneEnrollmentV1")
        self.assertEqual(api.calls[1][2]["code"], "123456")


if __name__ == "__main__":
    unittest.main()
