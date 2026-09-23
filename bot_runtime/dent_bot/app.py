from __future__ import annotations

import hashlib
import html
import re
import threading
import time
import logging
from datetime import datetime, timezone
from urllib.parse import urlsplit

from .api import BotApiError, TelegramBotApi
from .keyboard_invariant import KeyboardInvariantApi
from .state import BotState
from .payments import identity_from_account
from .payment_app_workflows import PaymentAppWorkflows
from .app_shell_screens import home, section
from .booklet_app_workflows import BookletAppWorkflows
from .dialog_app_workflows import DialogAppWorkflows
from .dynamic_screen_workflows import DynamicScreenWorkflows
from .message_frames import frame_error
from .site_api import SiteApiClient, SiteApiError
from .persian_datetime import format_jalali_datetime, to_persian_digits
from .ui import (
    Screen,
    bot_start_url,
    button,
    keyboard,
    account_screen,
    exam_screen,
    grades_screen,
    identity_mapping_remove_confirmation,
    identity_mapping_remove_screen,
    notification_audience_screen,
    notification_detail_screen,
    notification_list_screen,
    navid_screen,
    owner_identity_mappings_screen,
    profile_edit_fields_screen,
    profile_edit_prompt_screen,
    profile_edit_requests_screen,
    owner_grade_screen,
    owner_payment_offers_screen,
    payment_control_center_screen,
    payment_offers_screen,
    payment_offer_saved_screen,
    payment_offer_wizard_screen,
    payment_offer_preview_screen,
    payment_offer_admin_detail_screen,
    payment_offer_delete_confirmation,
    payment_confirm_screen,
    payment_created_screen,
    payment_status_screen,
    payment_product_report_screen,
    payment_transactions_screen,
    payment_transaction_filters_screen,
    payment_transaction_detail_screen,
    payment_people_screen,
    complimentary_access_list_screen,
    complimentary_search_results_screen,
    term_subscription_admin_screen,
    term_access_policies_screen,
    term_subscription_settings_screen,
    format_rials,
    required_channel_membership_screen,
    student_assistant_screen,
    integration_challenge_waiting_screen,
    section as base_section,
)
from .navid import local_now, send_daily_challenge
from .student_assistant import challenge_expired, clean_captcha_answer, send_private_challenge
from .academic_term7_rich import decorate_academic_notification_screen
from .classops_shell import canonical_home_screen, owner_management_screen
from .feature_router import decorate_feature_screen, route_feature_callback, route_feature_message
from .onboarding import (
    BACK_STEP,
    CANCEL,
    CLASS_OTP,
    CLASS_SITE,
    CHANGE_PHONE,
    CONFIRM_PROFILE,
    NEXT_PAGE,
    PREVIOUS_PAGE,
    RESEND_OTP,
    RESTART_PROFILE,
    SKIP_STUDENT_NUMBER,
    START_CLASS,
    START_GENERIC,
    gateway_screen,
    class_auth_screen,
    class_student_number_screen,
    class_otp_screen,
    prompt_screen as onboarding_prompt_screen,
    success_screen as onboarding_success_screen,
)






class DentBotApp(BookletAppWorkflows, DialogAppWorkflows, DynamicScreenWorkflows, PaymentAppWorkflows):
    def __init__(
        self,
        api: TelegramBotApi,
        state: BotState,
        *,
        owner_id: int,
        site_url: str,
        site_api: SiteApiClient | None = None,
        platform: str = "telegram",
        bot_username: str = "Dent1402Bot",
        exams_v1_enabled: bool = False,
        payment_return_v1_enabled: bool = False,
        student_assistant_v1_enabled: bool = False,
        required_channel_username: str = "",
        booklet_source_channel_id: int = 0,
        media_dispatcher=None,
    ) -> None:
        self.api = KeyboardInvariantApi(api, state)
        self.state = state
        self.owner_id = owner_id
        self.site_url = site_url
        self.site_api = site_api
        self.platform = platform
        self.bot_username = bot_username
        self.exams_v1_enabled = exams_v1_enabled
        self.payment_return_v1_enabled = payment_return_v1_enabled
        self.student_assistant_v1_enabled = student_assistant_v1_enabled
        self.required_channel_username = required_channel_username.strip().lstrip("@")
        self.booklet_source_channel_id = int(booklet_source_channel_id)
        self.media_dispatcher = media_dispatcher
        self._interaction_lock = threading.Lock()
        self._interaction_versions: dict[int, int] = {}
        # Telegram/Bale may deliver two taps from one user close together while
        # different executor threads are active. Fixed stripes keep each user's
        # state transitions ordered without an unbounded per-user lock map.
        self._update_locks = tuple(threading.RLock() for _ in range(64))
        self._onboarding_catalog_cache: dict = {}
        self._onboarding_catalog_cached_at = 0.0
        self._account_cache: dict[int, tuple[float, dict]] = {}
        self._product_state_cache: dict[int, tuple[float, tuple[str, ...], dict]] = {}
        self._payment_filters: dict[int, dict] = {}





















    def _screen(self, name: str, user_id: int) -> Screen:
        is_owner = user_id == self.owner_id
        has_products = False
        if name == "home" and not is_owner:
            try:
                has_products = bool(self._eligible_products(user_id))
            except SiteApiError:
                has_products = False
        return home(
            self.site_url,
            is_owner=is_owner,
            student_assistant_enabled=self.student_assistant_v1_enabled,
            has_products=has_products,
        ) if name == "home" else section(name, self.site_url, is_owner=is_owner)

    def _private_access_gate(self, user_id: int) -> Screen | None:
        """Return a blocking screen unless the canonical website link is live."""
        if self.site_api is None:
            return Screen(
                frame_error("بررسی امن اتصال حساب فعلاً در دسترس نیست؛ دسترسی خصوصی باز نشد."),
                gateway_screen().keyboard,
            )
        if not hasattr(self.site_api, "account"):
            return Screen(
                frame_error("بررسی امن اتصال حساب در دسترس نیست؛ دسترسی خصوصی باز نشد."),
                gateway_screen().keyboard,
            )
        try:
            account = self._account_snapshot(user_id)
        except (SiteApiError, AttributeError) as error:
            return Screen(frame_error(str(error)), gateway_screen().keyboard)
        if account.get("linked") is True and account.get("authComplete") is True:
            return None
        return self._unlinked_access_screen(user_id, account)

    def _unlinked_access_screen(self, user_id: int, account: dict) -> Screen:
        profile = (
            dict(account.get("onboardingProfile") or {})
            if isinstance(account.get("onboardingProfile"), dict)
            else None
        )
        if profile and profile.get("isClassMember"):
            dialog = self.state.dialog(user_id)
            if not dialog or str(dialog.get("kind") or "") != "class-auth-v1":
                self.state.start_dialog(user_id, "class-auth-v1", "method", {})
            return Screen(
                frame_error("اتصال حساب کلاس کامل نیست یا از سایت قطع شده است؛ دوباره با یکی از دو مسیر امن وارد شو."),
                class_auth_screen().keyboard,
            )
        if profile and str(profile.get("verifiedAt") or "").strip():
            return account_screen(
                self.site_url,
                platform=self.platform,
                identity_state=dict(account.get("identity") or {}),
                onboarding_profile=profile,
                booklet_profile=dict(account.get("bookletProfile") or {}) if isinstance(account.get("bookletProfile"), dict) else None,
            )
        return gateway_screen()

    def _bot_entry_gate(self, user_id: int) -> Screen | None:
        """Block every bot option until one approved intake route is complete."""
        if self.site_api is None or not hasattr(self.site_api, "account"):
            return Screen(
                frame_error("بررسی امن وضعیت ثبت‌نام در دسترس نیست؛ هیچ بخشی باز نشد."),
                gateway_screen().keyboard,
            )
        try:
            account = self._account_snapshot(user_id)
        except (SiteApiError, AttributeError) as error:
            return Screen(frame_error(str(error)), gateway_screen().keyboard)
        if account.get("linked") is True and account.get("authComplete") is True:
            return None
        profile = account.get("onboardingProfile")
        if (
            isinstance(profile, dict)
            and str(profile.get("verifiedAt") or "").strip()
            and not profile.get("isClassMember")
        ):
            return None
        return self._unlinked_access_screen(user_id, account)

    @staticmethod
    def _requires_canonical_link(name: str) -> bool:
        exact = {
            "admin", "system-status", "admin-grades", "admin-payments", "navid", "navid-check",
            "identity-mappings", "identity-mapping-remove-cancel", "identity-mapping-remove-confirm",
            "profile-edit", "profile-edit-cancel", "profile-edit-requests", "grades", "notifications",
            "student-assistant", "exam-owner", "payment-offer-new", "payment-offer-cancel",
            "payment-offer-publish", "payment-offer-no-description", "payment-offer-custom-amount",
            "payment-products", "payment-stats", "payment-transactions", "payment-search",
            "payment-audiences", "payment-export", "payment-reminders", "payment-settings",
            "booklet-sales", "booklet-sales-ai", "booklet-sales-subscriptions",
            "payment-audience-new", "payment-transaction-filters", "payment-tx-clear", "payment-tx-product",
            "payment-audience-search", "payment-audience-search-more", "payment-audience-selection-save",
            "term-subscription", "term-access-policies", "term-access-policy-add",
        }
        prefixes = (
            "profile-edit-field:", "profile-edit-approve:", "profile-edit-reject:",
            "identity-mapping-remove:", "payment-offer-status:", "payment-offer-amount:",
            "payment-offer-delete", "payment-offer:",
            "payment-products-page:", "payment-offer-audience:",
            "payment-offer-edit:", "payment-offer-schedule:", "payment-offer-advanced:",
            "payment-offer-stats:", "payment-offer-payers:", "payment-offer-unpaid:",
            "payment-offer-export:", "payment-offer-duplicate:", "payment-offer-rotate:",
            "payment-export-file:", "payment-export-text:", "payment-reminder-preview:",
            "prs:", "payment-product-copy:", "payment-product-share-preview:", "pps:",
            "payment-audience-select:",
            "payment-transactions-page:", "payment-tx-status:", "payment-tx-platform:",
            "payment-tx-gateway:", "payment-tx-period:", "payment-tx-product:",
            "payment-transaction:", "payment-transaction-status:",
            "notification-action:", "notification-read:", "notification:",
            "notification-audience:", "assistant-action:", "exam-action:",
            "term-subscription:", "term-subscription-buy:", "term-subscription-info:",
            "term-subscription-admin:", "term-subscription-settings:", "term-subscription-price:",
            "term-subscription-start:",
            "term-subscription-toggle:", "term-subscription-mode:", "term-subscription-reminders:",
            "term-subscription-grant:", "term-subscription-grant-select:", "term-subscription-free:",
            "term-subscription-revoke:", "term-subscription-export:",
        )
        return name in exact or name.startswith(prefixes)

    def _active_auth_dialog_screen(self, user_id: int) -> Screen | None:
        dialog = self.state.dialog(user_id)
        if not dialog:
            return None
        kind = str(dialog.get("kind") or "")
        step = str(dialog.get("step") or "")
        payload = dict(dialog.get("payload") or {})
        if kind == "class-auth-v1":
            if step == "student-number":
                return class_student_number_screen()
            if step == "otp":
                return class_otp_screen(str(payload.get("phoneMasked") or ""))
            return class_auth_screen()
        if kind == "onboarding-v1":
            try:
                return onboarding_prompt_screen(step, payload, self._onboarding_catalog(user_id))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), gateway_screen().keyboard)
        return None

    def handle(self, update: dict) -> None:
        inline_query = update.get("inline_query")
        if isinstance(inline_query, dict):
            self._handle_inline_query(dict(inline_query))
            return
        channel_post = update.get("channel_post") or update.get("edited_channel_post")
        if isinstance(channel_post, dict):
            self._handle_booklet_source_post(dict(channel_post))
            return
        callback = dict(update.get("callback_query") or {})
        message = dict(callback.get("message") or update.get("message") or {})
        sender = dict(callback.get("from") or message.get("from") or {})
        user_id = sender.get("id")
        if isinstance(user_id, int):
            interaction_version = self._claim_interaction(user_id) if callback else None
            with self._update_locks[user_id % len(self._update_locks)]:
                self._handle_serialized(update, interaction_version=interaction_version)
            return
        self._handle_serialized(update)

    def _handle_inline_query(self, query: dict) -> None:
        if self.platform != "telegram" or not hasattr(self.api, "answer_inline_query"):
            return
        query_id = str(query.get("id") or "")
        sender = dict(query.get("from") or {})
        user_id = sender.get("id")
        if not query_id or not isinstance(user_id, int) or user_id != self.owner_id:
            if query_id:
                self.api.answer_inline_query(query_id, [])
            return
        try:
            if self.required_channel_username and not self.api.is_chat_member(f"@{self.required_channel_username}", user_id):
                self.api.answer_inline_query(query_id, [])
                return
            if self._private_access_gate(user_id) is not None:
                self.api.answer_inline_query(query_id, [])
                return
        except (BotApiError, SiteApiError):
            self.api.answer_inline_query(query_id, [])
            return
        needle = " ".join(str(query.get("query") or "").lower().split())
        results: list[dict] = []
        for item in self._ordinary_payment_offers():
            if str(item.get("effectiveStatus") or "") != "active":
                continue
            title = str(item.get("title") or "محصول")
            if needle and needle not in title.lower() and needle not in str(item.get("description") or "").lower():
                continue
            share_url = f"https://t.me/{self.bot_username.lstrip('@')}?start=product_{item.get('shareToken', '')}"
            amount = to_persian_digits(format_rials(item.get("amountRials")))
            deadline = format_jalali_datetime(item.get("expiresAt"))
            body = f"<b>🛍 {html.escape(title)}</b>\n\nمبلغ: <code>{html.escape(amount)}</code>"
            if deadline:
                body += f"\nمهلت: {html.escape(deadline)}"
            description = str(item.get("description") or "").strip()
            if description:
                body += f"\n\n{html.escape(description[:300])}"
            results.append({
                "type": "article",
                "id": hashlib.sha256(str(item.get("shareToken") or "").encode()).hexdigest()[:32],
                "title": title[:80],
                "description": f"{amount}" + (f" · {deadline}" if deadline else ""),
                "input_message_content": {"message_text": body, "parse_mode": "HTML", "disable_web_page_preview": True},
                "reply_markup": {"inline_keyboard": [[{"text": "مشاهده / پرداخت", "url": share_url}]]},
            })
        self.api.answer_inline_query(query_id, results, cache_time=3)

    def _handle_serialized(self, update: dict, *, interaction_version: int | None = None) -> None:
        started = time.monotonic()
        kind = "callback" if isinstance(update.get("callback_query"), dict) else "message"
        if self._block_without_required_channel(update):
            return
        if isinstance(update.get("callback_query"), dict):
            self._callback(dict(update["callback_query"]), interaction_version=interaction_version)
        elif isinstance(update.get("message"), dict):
            self._message(dict(update["message"]))
        elapsed = time.monotonic() - started
        if elapsed >= 1.0:
            logging.warning("slow interactive update kind=%s elapsed_ms=%s", kind, int(elapsed * 1000))

    def _block_without_required_channel(self, update: dict) -> bool:
        """Fail closed before every Telegram private interaction."""
        if self.platform != "telegram" or not self.required_channel_username:
            return False
        callback = dict(update.get("callback_query") or {})
        message = dict(callback.get("message") or update.get("message") or {})
        sender = dict(callback.get("from") or message.get("from") or {})
        chat = dict(message.get("chat") or {})
        if chat.get("type") != "private" or not isinstance(sender.get("id"), int):
            return False
        user_id = int(sender["id"])
        callback_name = str(callback.get("data") or "")
        if callback_name.startswith("v1:"):
            callback_name = callback_name[3:]
        if user_id == self.owner_id:
            if callback and self._is_booklet_action(callback_name):
                return False
            owner_dialog = self.state.dialog(user_id)
            if not callback and owner_dialog is not None and str(owner_dialog.get("kind") or "") == "booklets-v1":
                return False
        check_unavailable = False
        try:
            member = bool(self.api.is_chat_member(f"@{self.required_channel_username}", user_id))
        except (BotApiError, AttributeError, TypeError, ValueError):
            member = False
            check_unavailable = True
        if member:
            if callback and str(callback.get("data") or "") == "v1:membership-check":
                callback_id = str(callback.get("id") or "")
                if callback_id:
                    self.api.answer_callback(callback_id, "عضویت تأیید شد؛ ربات باز شد.")
                    callback["_membership_answered"] = True
                callback["data"] = "v1:home"
                update["callback_query"] = callback
            return False
        screen = required_channel_membership_screen(
            self.required_channel_username,
            check_unavailable=check_unavailable,
        )
        if callback:
            callback_id = str(callback.get("id") or "")
            if callback_id:
                acknowledged = self.api.answer_callback(
                    callback_id,
                    "بررسی عضویت فعلاً ممکن نیست." if check_unavailable else "هنوز عضو کانال نیستی.",
                    show_alert=True,
                )
                if acknowledged is False:
                    self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            try:
                self.api.edit(int(chat["id"]), int(message["message_id"]), screen.text, screen.keyboard)
            except BotApiError as error:
                if "message is not modified" not in str(error).lower():
                    raise
        else:
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
        return True

    def _message(self, message: dict) -> None:
        if route_feature_message(self, message):
            return
        self._message_core(message)

    def _message_core(self, message: dict) -> None:
        chat = dict(message.get("chat") or {})
        sender = dict(message.get("from") or {})
        if chat.get("type") != "private" or not isinstance(sender.get("id"), int):
            return
        user_id = int(sender["id"])
        text = str(message.get("text") or "").strip()
        command, _, command_argument = text.partition(" ")
        command = command.split("@", 1)[0]
        if command == "/start":
            self._account_cache.pop(user_id, None)
            self._product_state_cache.pop(user_id, None)
        if command == "/start" and command_argument.startswith("product_"):
            blocked = self._bot_entry_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
            token = command_argument[8:]
            account = self._account_snapshot(user_id)
            item = self.state.payment_offer_by_share_token(token)
            allowed = None
            if item is not None:
                allowed = self.state.payment_offer_for_user(
                    str(item.get("ref") or ""), identity_from_account(account), via_link=True
                )
            if allowed is None:
                screen = Screen(
                    frame_error("این لینک در دسترس این حساب نیست یا اعتبارش پایان یافته است."),
                    self._screen("home", user_id).keyboard,
                )
            else:
                states = self._payment_product_states(user_id, [allowed])
                screen = payment_confirm_screen(
                    allowed,
                    action_ref=f"payment-create-link:{token}",
                    state=dict(states.get(str(allowed.get('ref') or '')) or {}),
                )
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        if command == "/start" and command_argument.startswith("pay_"):
            blocked = self._bot_entry_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
            account = self._account_snapshot(user_id)
            offer = self.state.payment_offer_for_user(command_argument[4:], identity_from_account(account))
            screen = payment_confirm_screen(offer) if offer else Screen(frame_error("این محصول فعال نیست."), gateway_screen().keyboard)
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        if command == "/start" and command_argument.startswith("receipt_"):
            blocked = self._bot_entry_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
            order_token = command_argument[8:]
            if self.site_api is None or re.fullmatch(r"[A-Za-z0-9_-]{20,46}", order_token) is None:
                screen = Screen(
                    frame_error("شناسهٔ بازگشت پرداخت معتبر نیست."),
                    home(self.site_url, is_owner=user_id == self.owner_id).keyboard,
                )
            else:
                try:
                    status_payload = self.site_api.payment_status(user_id, order_token)
                    status_payload = self._activate_subscription_from_payment_status(
                        user_id, order_token, dict(status_payload)
                    )
                    screen = payment_status_screen(
                        status_payload,
                        platform=self.platform,
                        order_token=order_token,
                        return_to_bot_enabled=True,
                    )
                except SiteApiError as error:
                    screen = (
                        account_screen(self.site_url, platform=self.platform)
                        if error.code == "ACCOUNT_LINK_REQUIRED"
                        else Screen(
                            frame_error(str(error)),
                            home(self.site_url, is_owner=user_id == self.owner_id).keyboard,
                        )
                    )
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        if command == "/start" and not command_argument:
            self._start_onboarding_gateway(int(chat["id"]), user_id)
            return
        if text == START_GENERIC and self.state.dialog(user_id) is None:
            self._begin_entry_route(int(chat["id"]), user_id, requested="generic")
            return
        if text == START_CLASS and self.state.dialog(user_id) is None:
            self._begin_entry_route(int(chat["id"]), user_id, requested="class")
            return
        auth_dialog = self._active_auth_dialog_screen(user_id)
        if auth_dialog is not None:
            if not text.startswith("/") and self._handle_dialog_message(
                int(chat["id"]), user_id, text, sender=sender, message=message
            ):
                return
            self.api.send(int(chat["id"]), auth_dialog.text, auth_dialog.keyboard)
            return
        entry_dialog = self.state.dialog(user_id)
        owner_legacy_booklet = (
            user_id == self.owner_id
            and entry_dialog is not None
            and str(entry_dialog.get("kind") or "") == "booklets-v1"
        )
        blocked = None if owner_legacy_booklet else self._bot_entry_gate(user_id)
        if blocked is not None:
            dialog = entry_dialog
            if dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(int(chat["id"]))
            self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
            return
        if command == "/menu":
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(int(chat["id"]))
            screen = self._dynamic_screen("home", user_id)
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        pending_dialog = self.state.dialog(user_id)
        private_message = (
            (self.student_assistant_v1_enabled and self._is_integration_captcha_reply(user_id, message))
            or (user_id == self.owner_id and text.split(maxsplit=1)[0].split("@", 1)[0] == "/navid")
            or (user_id == self.owner_id and self._is_navid_captcha_reply(message))
            or text.startswith("/setgrade")
            or command in {"/product", "/payform"}
            or (
                pending_dialog is not None
                and str(pending_dialog.get("kind") or "")
                not in {"onboarding-v1", "class-auth-v1", "booklets-v1"}
            )
        )
        if private_message:
            blocked = self._private_access_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
        if self.student_assistant_v1_enabled and self._is_integration_captcha_reply(user_id, message):
            self._complete_integration_captcha(int(chat["id"]), user_id, message, text)
            return
        if user_id == self.owner_id and text.split(maxsplit=1)[0].split("@", 1)[0] == "/navid":
            self._send_navid_challenge(int(chat["id"]), refresh=True)
            return
        if user_id == self.owner_id and self._is_navid_captcha_reply(message):
            self._complete_navid_captcha(int(chat["id"]), text)
            return
        if text.startswith("/setgrade"):
            self._set_grade_message(int(chat["id"]), user_id, text)
            return
        if command in {"/product", "/payform"}:
            self._start_payment_offer_wizard(int(chat["id"]), user_id, command_argument)
            return
        if self._handle_dialog_message(int(chat["id"]), user_id, text, sender=sender, message=message):
            return
        command = text.split(maxsplit=1)[0].split("@", 1)[0]
        target = (
            "help" if command == "/help"
            else "account" if command in {"/verify", "/account"}
            else "student-assistant" if command == "/assistant" and self.student_assistant_v1_enabled
            else "home"
        )
        self.state.touch_user(user_id, target)
        screen = self._dynamic_screen(target, user_id)
        self.api.send(int(chat["id"]), screen.text, screen.keyboard)

    def _is_integration_captcha_reply(self, user_id: int, message: dict) -> bool:
        pending = self.state.integration_challenge(user_id)
        if not pending:
            return False
        reply = dict(message.get("reply_to_message") or {})
        return int(reply.get("message_id") or 0) == int(pending["message_id"])

    def _complete_integration_captcha(self, chat_id: int, user_id: int, message: dict, text: str) -> None:
        pending = self.state.integration_challenge(user_id)
        reply = dict(message.get("reply_to_message") or {})
        reply_message_id = int(reply.get("message_id") or 0)
        answer = clean_captcha_answer(text)
        if not pending or reply_message_id != int(pending["message_id"]):
            return
        if not answer:
            self.api.send(
                chat_id,
                frame_error("کد تصویر باید ۴ تا ۱۲ حرف یا عدد باشد و با Reply به همان تصویر ارسال شود."),
                self._screen("student-assistant", user_id).keyboard,
            )
            return
        if challenge_expired(str(pending.get("expires_at") or "")):
            self.state.clear_integration_challenge(user_id)
            self.api.send(
                chat_id,
                frame_error("اعتبار این تصویر تمام شده است؛ از دستیار دانشجو عملیات را دوباره باز کن."),
                self._screen("student-assistant", user_id).keyboard,
            )
            return
        consumed = self.state.take_integration_challenge(user_id, message_id=reply_message_id)
        if not consumed or self.site_api is None:
            return
        stable_request_id = hashlib.sha256(
            f"{self.platform}:{user_id}:{int(message.get('message_id') or 0)}".encode("ascii")
        ).hexdigest()
        try:
            result = self.site_api.integration_challenge_answer(
                user_id,
                challenge_ref=str(consumed["challenge_ref"]),
                job_ref=str(consumed["job_ref"]),
                answer=answer,
                request_id=stable_request_id,
            )
            if str(result.get("status") or "") == "challenge":
                send_private_challenge(api=self.api, state=self.state, user_id=user_id, payload=result)
                screen = integration_challenge_waiting_screen(result)
            else:
                screen = student_assistant_screen(result, is_owner=user_id == self.owner_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
        except SiteApiError as error:
            # The one-time local binding was consumed before the network call and
            # the answer is never retained. Restarting the job yields a fresh challenge.
            self.api.send(
                chat_id,
                frame_error(f"{str(error)} برای ادامه، عملیات را از دستیار دانشجو دوباره باز کن."),
                self._screen("student-assistant", user_id).keyboard,
            )
        except BotApiError:
            self.api.send(
                chat_id,
                frame_error("ارسال تصویر تازه انجام نشد؛ عملیات را از دستیار دانشجو دوباره باز کن."),
                self._screen("student-assistant", user_id).keyboard,
            )

    def _is_navid_captcha_reply(self, message: dict) -> bool:
        pending = self.state.navid_challenge()
        if not pending:
            return False
        reply = dict(message.get("reply_to_message") or {})
        return int(reply.get("message_id") or 0) == int(pending["message_id"])

    def _send_navid_challenge(self, chat_id: int, *, refresh: bool) -> None:
        if self.site_api is None:
            self.api.send(chat_id, frame_error("اتصال امن نوید در دسترس نیست."), home(self.site_url, is_owner=True).keyboard)
            return
        daily_date = local_now("Asia/Tehran").strftime("%Y-%m-%d")
        try:
            result = send_daily_challenge(
                api=self.api,
                state=self.state,
                site_api=self.site_api,
                owner_id=self.owner_id,
                daily_date=daily_date,
                refresh=refresh,
            )
            if str(result.get("status") or "") == "already-completed":
                self.api.send(
                    chat_id,
                    "<b>✅ بررسی امروز نوید انجام شده است</b>\n\nتکلیف جدیدی که شناسایی شده باشد در اعلان‌های سایت ثبت شده است.",
                    home(self.site_url, is_owner=True).keyboard,
                )
        except (SiteApiError, BotApiError) as error:
            self.api.send(chat_id, frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)

    def _complete_navid_captcha(self, chat_id: int, text: str) -> None:
        pending = self.state.navid_challenge()
        code = re.sub(r"[^A-Za-z0-9]", "", text).upper()
        if not pending or len(code) < 4 or len(code) > 10 or self.site_api is None:
            self.api.send(chat_id, frame_error("کد کپچا معتبر نیست؛ با /navid تصویر تازه بگیر."), home(self.site_url, is_owner=True).keyboard)
            return
        try:
            result = self.site_api.navid_daily_complete(
                self.owner_id,
                date=str(pending["date"]),
                captcha_code=code,
            )
            summary = dict(result.get("summary") or {})
            new_events = max(0, int(summary.get("newEvents") or 0))
            self.state.clear_navid_challenge()
            self.state.set_runtime_value("navid_completed_date", str(pending["date"]))
            self.api.send(
                chat_id,
                "<b>✅ بررسی روزانه نوید انجام شد</b>\n\n"
                f"تکلیف جدید: <b>{new_events}</b>\n"
                "<blockquote>موارد جدید از مسیر اعلان‌های سایت برای اعضای متصل به ربات‌ها توزیع می‌شوند.</blockquote>",
                home(self.site_url, is_owner=True).keyboard,
            )
        except SiteApiError as error:
            self.state.clear_navid_challenge()
            self.api.send(
                chat_id,
                frame_error(f"{str(error)} برای دریافت تصویر تازه /navid را بفرست."),
                home(self.site_url, is_owner=True).keyboard,
            )

    def _set_grade_message(self, chat_id: int, user_id: int, text: str) -> None:
        if user_id != self.owner_id or self.site_api is None:
            self.api.send(chat_id, frame_error("اجازه ثبت نمره را نداری."), home(self.site_url, is_owner=False).keyboard)
            return
        raw = text.split(maxsplit=1)[1] if len(text.split(maxsplit=1)) > 1 else ""
        parts = [item.strip() for item in raw.split("|")]
        if len(parts) != 4 or not all(parts):
            screen = owner_grade_screen(self.site_url)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        try:
            result = self.site_api.set_grade(
                user_id,
                student_number=parts[0],
                course_label=parts[1],
                max_score=parts[2],
                score=parts[3],
            )
            course = dict(result.get("course") or {})
            label = str(course.get("label") or parts[1])
            text_result = f"<b>🟢 نمره ذخیره شد</b>\n\n{label}\n<blockquote>{parts[3]} از {parts[2]}</blockquote>"
            self.api.send(chat_id, text_result, owner_grade_screen(self.site_url).keyboard)
        except SiteApiError as error:
            self.api.send(chat_id, frame_error(str(error)), owner_grade_screen(self.site_url).keyboard)

    def _start_payment_offer_wizard(self, chat_id: int, user_id: int, quick: str = "") -> None:
        if user_id != self.owner_id:
            self.api.send(chat_id, frame_error("اجازه ساخت محصول پرداختی را نداری."), home(self.site_url, is_owner=False).keyboard)
            return
        quick = " ".join(str(quick).split())
        match = re.fullmatch(r"([۰-۹٠-٩0-9][۰-۹٠-٩0-9,٬]*)\s+(.{3,160})", quick) if quick else None
        if match:
            amount_tomans = self._parse_tomans(match.group(1))
            if 1000 <= amount_tomans <= 10000000000:
                payload = {"title": match.group(2).strip(), "amountRials": amount_tomans * 10, "description": ""}
                dialog = self.state.start_dialog(user_id, "payment-offer", "audience", payload)
                screen = payment_offer_wizard_screen("audience", dialog["payload"])
            else:
                screen = Screen(frame_error("مبلغ دستور سریع معتبر نیست."), payment_offer_wizard_screen("title", {}).keyboard)
        else:
            dialog = self.state.start_dialog(user_id, "payment-offer", "title")
            screen = payment_offer_wizard_screen("title", dialog["payload"])
        self.api.send(chat_id, screen.text, screen.keyboard)

    @staticmethod
    def _parse_tomans(text: str) -> int:
        normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
        digits = re.sub(r"[^0-9]", "", normalized)
        return int(digits or "0")

    @staticmethod
    def _platform_profile(sender: dict) -> dict:
        first_name = " ".join(str(sender.get("first_name") or "").split())[:64]
        last_name = " ".join(str(sender.get("last_name") or "").split())[:64]
        return {
            "displayName": " ".join(value for value in (first_name, last_name) if value)[:128],
            "username": str(sender.get("username") or "").lstrip("@")[:32],
            "languageCode": str(sender.get("language_code") or "")[:16],
            "isPremium": bool(sender.get("is_premium")),
            "platformUserId": str(sender.get("id") or ""),
        }

    def _onboarding_catalog(self, user_id: int, *, refresh: bool = False) -> dict:
        if self.site_api is None:
            raise SiteApiError("اتصال امن سایت در دسترس نیست.", code="SITE_UNAVAILABLE")
        now = time.monotonic()
        if not refresh and self._onboarding_catalog_cache and now - self._onboarding_catalog_cached_at < 21600:
            return dict(self._onboarding_catalog_cache)
        catalog = self.site_api.onboarding_catalog(user_id)
        if str(catalog.get("contractVersion") or "") != "bot-onboarding-v1":
            raise SiteApiError("نسخه فهرست ثبت مشخصات معتبر نیست.", code="INVALID_ONBOARDING_CONTRACT")
        self._onboarding_catalog_cache = dict(catalog)
        self._onboarding_catalog_cached_at = now
        return dict(catalog)

    def _start_onboarding_gateway(self, chat_id: int, user_id: int) -> None:
        screen = self._bot_entry_gate(user_id)
        dialog = self.state.dialog(user_id)
        if screen is None:
            # A canonical completion supersedes any stale local intake state.
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(chat_id)
            screen = self._screen("home", user_id)
        else:
            # Repeated /start or the client's Start button resumes the exact
            # saved step. Only explicit cancel/restart may discard progress.
            active = self._active_auth_dialog_screen(user_id)
            if active is not None:
                screen = active
            elif dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(chat_id)
        self.api.send(chat_id, screen.text, screen.keyboard)

    def _remove_reply_keyboard(self, chat_id: int) -> None:
        """Best-effort transport cleanup when an auth reply flow ends."""
        remove = getattr(self.api, "remove_reply_keyboard", None)
        if not callable(remove):
            return
        try:
            remove(chat_id)
        except BotApiError:
            # Authentication has already completed. A transient presentation
            # cleanup failure must not roll back or duplicate that mutation.
            logging.warning("reply keyboard cleanup failed platform=%s", self.platform)

    def _begin_generic_onboarding(self, chat_id: int, user_id: int) -> None:
        try:
            catalog = self._onboarding_catalog(user_id)
            self.state.start_dialog(user_id, "onboarding-v1", "first-name", {})
            screen = onboarding_prompt_screen("first-name", {}, catalog)
        except SiteApiError as error:
            screen = Screen(frame_error(str(error)), gateway_screen().keyboard)
        self.api.send(chat_id, screen.text, screen.keyboard)

    def _begin_entry_route(self, chat_id: int, user_id: int, *, requested: str) -> None:
        if self.site_api is None or not hasattr(self.site_api, "account"):
            screen = Screen(frame_error("بررسی امن وضعیت ثبت‌نام در دسترس نیست؛ هیچ مسیری باز نشد."), gateway_screen().keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        try:
            account = self.site_api.account(user_id)
        except (SiteApiError, AttributeError) as error:
            self.api.send(chat_id, frame_error(str(error)), gateway_screen().keyboard)
            return
        profile = account.get("onboardingProfile")
        if account.get("linked") is True and account.get("authComplete") is True:
            screen = self._screen("home", user_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        if (
            isinstance(profile, dict)
            and str(profile.get("verifiedAt") or "").strip()
            and not profile.get("isClassMember")
        ):
            screen = self._screen("home", user_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        if requested == "class" or (isinstance(profile, dict) and profile.get("isClassMember")):
            self._begin_class_onboarding(chat_id, user_id)
            return
        self._begin_generic_onboarding(chat_id, user_id)

    def _begin_class_onboarding(self, chat_id: int, user_id: int) -> None:
        self.state.start_dialog(user_id, "class-auth-v1", "method", {})
        screen = class_auth_screen()
        self.api.send(chat_id, screen.text, screen.keyboard)

    @staticmethod
    def _institution_system(catalog: dict, province: str, institution: str) -> str:
        for item in catalog.get("institutions", []):
            if not isinstance(item, dict):
                continue
            if str(item.get("province") or "") == province and str(item.get("name") or "") == institution:
                return str(item.get("system") or "public")
        return "public"

    @staticmethod
    def _canonical_course_type(value: str) -> str:
        return {
            "روزانه": "روزانه یا تعهدی",
            "تعهدی": "روزانه یا تعهدی",
            "روزانه یا تعهدی": "روزانه یا تعهدی",
            "شهریه‌پرداز": "شهریه پرداز",
            "شهریه پرداز": "شهریه پرداز",
            "بین‌الملل": "بین الملل",
            "بین الملل": "بین الملل",
        }.get(str(value), str(value))

    def _back_onboarding(self, step: str, payload: dict) -> tuple[str, dict]:
        if step == "first-name":
            return "gateway", payload
        if step == "last-name":
            return "first-name", payload
        if step == "major":
            return "last-name", payload
        if step == "province":
            return "major", payload
        if step == "institution":
            return "province", payload
        if step == "entry-year":
            return "institution", payload
        if step == "entry-term":
            return "entry-year", payload
        if step == "course-type":
            return "entry-term", payload
        if step == "student-number":
            return (
                "entry-term" if str(payload.get("institutionSystem") or "") == "azad" else "course-type",
                payload,
            )
        if step == "review":
            return "student-number", payload
        if step == "contact":
            return "review", payload
        if step == "otp":
            return "contact", dict(payload.get("profile") or {})
        return "first-name", payload

    @staticmethod
    def _clean_person_name(value: str) -> str:
        value = str(value).translate(str.maketrans({"ي": "ی", "ى": "ی", "ك": "ک"}))
        value = re.sub(r"[^\w\s\u0600-\u06FF]", " ", value, flags=re.UNICODE)
        return " ".join(value.split())[:64]

    @staticmethod
    def _recover_onboarding_step(payload: dict, catalog: dict) -> str:
        """Find the first incomplete step without discarding submitted values."""
        nested_profile = payload.get("profile")
        if isinstance(nested_profile, dict):
            return "otp" if str(payload.get("challengeRef") or "") else "contact"
        if not str(payload.get("firstName") or "").strip():
            return "first-name"
        if not str(payload.get("lastName") or "").strip():
            return "last-name"
        if str(payload.get("major") or "") not in [str(item) for item in catalog.get("majors", [])]:
            return "major"
        province = str(payload.get("province") or "")
        if province not in [str(item) for item in catalog.get("provinces", [])]:
            return "province"
        institution = str(payload.get("institution") or "")
        allowed_institutions = [
            str(item.get("name") or "")
            for item in catalog.get("institutions", [])
            if isinstance(item, dict) and str(item.get("province") or "") == province
        ]
        if institution not in allowed_institutions:
            return "institution"
        if str(payload.get("entryYear") or "") not in [str(item) for item in catalog.get("entryYears", [])]:
            return "entry-year"
        if str(payload.get("entryTerm") or "") not in [str(item) for item in catalog.get("entryTerms", [])]:
            return "entry-term"
        if str(payload.get("institutionSystem") or "") != "azad" and not str(payload.get("courseType") or ""):
            return "course-type"
        if "studentNumber" not in payload:
            return "student-number"
        return "review"




    def _claim_interaction(self, user_id: int) -> int:
        with self._interaction_lock:
            version = self._interaction_versions.get(user_id, 0) + 1
            self._interaction_versions[user_id] = version
            return version

    def _interaction_is_current(self, user_id: int, version: int) -> bool:
        with self._interaction_lock:
            return self._interaction_versions.get(user_id) == version

    def _callback(self, callback: dict, *, interaction_version: int | None = None) -> None:
        if route_feature_callback(self, callback, interaction_version=interaction_version):
            return
        self._callback_core(callback, interaction_version=interaction_version)

    def _callback_core(self, callback: dict, *, interaction_version: int | None = None) -> None:
        callback_id = str(callback.get("id") or "")
        sender = dict(callback.get("from") or {})
        message = dict(callback.get("message") or {})
        chat = dict(message.get("chat") or {})
        data = str(callback.get("data") or "")
        if not callback_id or not isinstance(sender.get("id"), int) or chat.get("type") != "private":
            return
        user_id = int(sender["id"])
        if interaction_version is None:
            interaction_version = self._claim_interaction(user_id)
        name = data[3:] if data.startswith("v1:") else "home"
        if name == "submit-note":
            name = "notes"
        owner_action = name in {
            "admin", "system-status", "admin-grades", "admin-payments", "navid", "navid-check",
            "identity-mappings", "identity-mapping-remove-cancel", "identity-mapping-remove-confirm",
            "profile-edit-requests", "payment-offer-new", "payment-offer-cancel", "payment-offer-publish",
            "payment-offer-no-description", "payment-offer-custom-amount", "payment-offer-description",
            "payment-products", "payment-stats", "payment-transactions", "payment-search", "payment-audiences",
            "booklet-sales", "booklet-sales-ai", "booklet-sales-subscriptions",
            "payment-export", "payment-reminders", "payment-settings", "payment-audience-new",
            "payment-audience-search", "payment-audience-search-more", "payment-audience-selection-save",
            "payment-transaction-filters", "payment-tx-clear", "payment-tx-product",
            "term-subscription-admin", "term-subscription-settings", "term-subscription-price",
            "term-subscription-start",
            "term-subscription-toggle", "term-subscription-mode", "term-subscription-reminders",
            "term-subscription-grant", "term-subscription-free", "term-subscription-revoke",
            "term-subscription-export", "term-access-policies", "term-access-policy-add",
        } or name.startswith((
            "identity-approve:", "identity-reject:", "identity-mapping-remove:", "profile-edit-approve:",
            "profile-edit-reject:", "payment-offer-status:", "payment-offer-amount:", "payment-offer-delete",
            "payment-offer:", "payment-offer-", "payment-products-page:", "payment-export-",
            "payment-reminder-preview:",
            "prs:", "payment-product-copy:", "payment-product-share-preview:", "pps:",
            "payment-audience-select:",
            "payment-transactions-page:", "payment-tx-status:", "payment-tx-platform:",
            "payment-tx-gateway:", "payment-tx-period:", "payment-tx-product:",
            "payment-transaction:", "payment-transaction-status:",
            "term-subscription-admin:", "term-subscription-settings:", "term-subscription-price:",
            "term-subscription-start:",
            "term-subscription-toggle:", "term-subscription-mode:", "term-subscription-reminders:",
            "term-subscription-grant:", "term-subscription-grant-select:", "term-subscription-free:",
            "term-subscription-revoke:", "term-subscription-export:",
        ))
        if owner_action and user_id != self.owner_id:
            name = "home"
        if not callback.get("_membership_answered"):
            self.api.answer_callback(callback_id)
        time.sleep(0.08)
        if not self._interaction_is_current(user_id, interaction_version):
            return
        booklet_owner_bypass = user_id == self.owner_id and self._is_booklet_action(name)
        auth_dialog = None if booklet_owner_bypass else self._active_auth_dialog_screen(user_id)
        blocked = None if booklet_owner_bypass else (
            auth_dialog or (
                self._private_access_gate(user_id)
                if self._requires_canonical_link(name)
                else self._bot_entry_gate(user_id)
            )
        )
        if blocked is not None:
            dialog = self.state.dialog(user_id)
            if dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(int(chat["id"]))
            try:
                self.api.edit(int(chat["id"]), int(message["message_id"]), blocked.text, blocked.keyboard)
            except BotApiError as error:
                if "message is not modified" not in str(error).lower():
                    raise
            return
        if name.startswith("payment-export-file:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3 and re.fullmatch(r"(?:all|filtered|[A-Za-z0-9_-]{16,80})", parts[1]) and parts[2] in {"csv", "paid", "unpaid", "transactions"}:
                try:
                    self._send_payment_export(int(chat["id"]), user_id, parts[1], "transactions" if parts[2] == "csv" else parts[2])
                except (SiteApiError, BotApiError) as error:
                    self.api.send(int(chat["id"]), frame_error(str(error)), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            return
        if name.startswith("term-subscription-export:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3 and parts[1].isdigit() and parts[2] in {"csv", "txt"}:
                try:
                    self._send_term_subscription_export(int(chat["id"]), user_id, int(parts[1]), parts[2])
                except BotApiError as error:
                    self.api.send(
                        int(chat["id"]), frame_error(str(error)),
                        term_subscription_admin_screen(
                            self.state.term_access_policy(int(parts[1])) or {},
                            self._term_subscription_report(user_id, int(parts[1])),
                        ).keyboard,
                    )
            return
        if name.startswith("prs:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3:
                try:
                    self._send_payment_reminder_batch(int(chat["id"]), user_id, parts[1], parts[2])
                except SiteApiError as error:
                    self.api.send(int(chat["id"]), frame_error(str(error)), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            return
        if name.startswith("pps:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3:
                try:
                    self._send_payment_product_batch(int(chat["id"]), user_id, parts[1], parts[2])
                except SiteApiError as error:
                    self.api.send(int(chat["id"]), frame_error(str(error)), payment_control_center_screen([]).keyboard)
            return
        if name == "navid-check" and user_id == self.owner_id:
            self._send_navid_challenge(int(chat["id"]), refresh=True)
            return
        if self._is_booklet_action(name):
            self._handle_booklet_action(
                chat_id=int(chat["id"]),
                user_id=user_id,
                name=name,
                message_id=int(message.get("message_id") or 0),
            )
            return
        dialog = self.state.dialog(user_id)
        if dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(int(chat["id"]))
        self.state.touch_user(user_id, name)
        screen = self._dynamic_screen(name, user_id, request_id=callback_id, sender=sender)
        if not self._interaction_is_current(user_id, interaction_version):
            return
        # Telegram clients do not consistently repaint a regular text message
        # when editMessageText changes its content type to a native rich message.
        # Enter a structured report with sendRichMessage, then keep using edits
        # only when the callback already came from a rich message.
        is_native_rich = bool(getattr(screen.text, "rich_html", ""))
        source_is_native_rich = isinstance(message.get("rich_message"), dict)
        if self.platform == "telegram" and is_native_rich and not source_is_native_rich:
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        try:
            self.api.edit(int(chat["id"]), int(message["message_id"]), screen.text, screen.keyboard)
        except BotApiError as error:
            if "message is not modified" not in str(error).lower():
                raise

    def _dynamic_screen(
        self,
        name: str,
        user_id: int,
        *,
        request_id: str = "",
        sender: dict | None = None,
    ) -> Screen:
        screen = self._dynamic_screen_core(name, user_id, request_id=request_id, sender=sender)
        return decorate_feature_screen(self, name, user_id, screen)
