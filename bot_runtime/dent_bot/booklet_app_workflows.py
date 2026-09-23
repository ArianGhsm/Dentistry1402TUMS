from __future__ import annotations

import logging

from .ai_booklets import (
    AI_BOOKLET_CONTENT_KIND,
    AI_BOOKLET_PRICE_RIALS,
    ai_booklet_offer_ref,
    ai_booklet_request_id,
)
from .api import BotApiError
from .booklets import (
    RESOURCE_LABELS,
    ai_booklet_purchase_screen,
    bale_unavailable_screen,
    course_by_key,
    courses_screen as booklet_courses_screen,
    resources_screen as booklet_resources_screen,
    session_by_number,
    sessions_screen as booklet_sessions_screen,
    source_records_from_channel_post,
)
from .message_frames import frame_error
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .subscriptions import policy_is_effective, subscription_identity_from_account
from .ui import Screen, button, keyboard, payment_created_screen, term_subscription_screen


class BookletAppWorkflows:
    """Shared-syllabus Booklets flow, isolated from the central bot router."""

    @staticmethod
    def _is_booklet_action(name: str) -> bool:
        return name == "notes" or name.startswith(
            (
                "booklet-course:",
                "booklet-session:",
                "booklet-resource:",
                "booklet-ai-buy:",
                "booklet-ai-get:",
            )
        )

    def _booklet_catalog(self, user_id: int) -> dict:
        if self.site_api is None:
            raise SiteApiError("منبع مشترک طرح درس فعلاً در دسترس نیست.", code="SITE_UNAVAILABLE")
        catalog = dict(self.site_api.booklet_catalog(user_id) or {})
        if (
            str(catalog.get("contractVersion") or "") != "term7-booklet-catalog-v1"
            or int(catalog.get("term") or 0) != 7
            or not isinstance(catalog.get("courses"), list)
        ):
            raise SiteApiError("نسخهٔ طرح درس جزوات معتبر نیست.", code="INVALID_BOOKLET_CATALOG")
        return catalog

    def _booklet_access_screen(self, user_id: int) -> tuple[bool, Screen | None]:
        if user_id == self.owner_id:
            return True, None
        policy = self.state.term_access_policy(7) or {}
        account = self.site_api.account(user_id) if self.site_api is not None else {}
        identity = subscription_identity_from_account(account)
        booklet_profile = (
            dict(account.get("bookletProfile") or {})
            if isinstance(account.get("bookletProfile"), dict)
            else {}
        )
        free_system_access = identity is not None and booklet_profile.get("freeSubscriptionEligible") is True
        if free_system_access:
            self.state.ensure_automatic_booklet_entitlement(
                student_number=identity.student_number,
                display_name=identity.display_name,
                term=7,
            )
            return True, None
        if identity is not None:
            self.state.revoke_automatic_booklet_entitlement(
                student_number=identity.student_number,
                term=7,
            )
        decision = self.state.term_access_decision(identity.subject_key if identity else "", 7)
        decision["freeSubscriptionEligible"] = booklet_profile.get("freeSubscriptionEligible") is True
        if policy_is_effective(policy) and identity is None:
            return False, self._unlinked_access_screen(user_id, account)
        if decision.get("allowed"):
            return True, None
        return False, term_subscription_screen(policy, decision, term=7)

    def _render_booklet_screen(self, chat_id: int, message_id: int, screen: Screen) -> None:
        if message_id > 0:
            try:
                self.api.edit(chat_id, message_id, screen.text, screen.keyboard)
                return
            except BotApiError as error:
                lowered = str(error).lower()
                if "message is not modified" in lowered:
                    return
                if not any(
                    marker in lowered
                    for marker in (
                        "message to edit not found",
                        "message can't be edited",
                        "message can not be edited",
                    )
                ):
                    raise
        self.api.send(chat_id, screen.text, screen.keyboard)

    def _handle_booklet_source_post(self, message: dict) -> None:
        if self.platform != "telegram" or self.booklet_source_channel_id >= 0:
            return
        chat = dict(message.get("chat") or {})
        if int(chat.get("id") or 0) != self.booklet_source_channel_id:
            return
        message_id = int(message.get("message_id") or 0)
        if message_id <= 0:
            return
        try:
            catalog = self._booklet_catalog(self.owner_id)
        except SiteApiError as error:
            logging.warning("booklet source catalog refresh skipped code=%s", error.code)
            return
        records = source_records_from_channel_post(message, catalog)
        count = self.state.replace_protected_media_message(
            self.booklet_source_channel_id,
            message_id,
            records,
        )
        logging.info("booklet source catalog updated routes=%s", count)

    def booklet_access_allowed(self, user_id: int, source: dict) -> bool:
        """Fresh entitlement check for every protected-media delivery."""
        if self.platform != "telegram":
            return False
        term = int(source.get("term") or 0)
        course_tag = str(source.get("courseTag") or "").strip()
        course_code = str(source.get("courseCode") or "").strip()
        content_kind = str(source.get("contentKind") or "").strip()
        if term != 7 or not course_tag:
            return False

        if content_kind == AI_BOOKLET_CONTENT_KIND:
            if not course_code or self.site_api is None:
                return False
            try:
                account = self.site_api.account(user_id)
                identity = subscription_identity_from_account(account)
                if identity is None:
                    return False
                if self.required_channel_username:
                    if not bool(self.api.is_chat_member(f"@{self.required_channel_username}", user_id)):
                        return False
                return self.state.has_ai_booklet_access(
                    identity.subject_key,
                    term=term,
                    course_code=course_code,
                    session_no=int(source.get("sessionNo") or 0),
                )
            except (SiteApiError, BotApiError, AttributeError, TypeError, ValueError):
                return False

        if user_id == self.owner_id:
            return True
        if self.site_api is None:
            return False
        try:
            account = self.site_api.account(user_id)
            authorized = bool(
                account.get("linked") is True and account.get("authComplete") is True
            ) or bool(
                isinstance(account.get("onboardingProfile"), dict)
                and str(account["onboardingProfile"].get("verifiedAt") or "").strip()
                and not account["onboardingProfile"].get("isClassMember")
            )
            if not authorized:
                return False
            if self.required_channel_username:
                if not bool(self.api.is_chat_member(f"@{self.required_channel_username}", user_id)):
                    return False
            identity = subscription_identity_from_account(account)
            booklet_profile = (
                dict(account.get("bookletProfile") or {})
                if isinstance(account.get("bookletProfile"), dict)
                else {}
            )
            if identity is not None and booklet_profile.get("freeSubscriptionEligible") is True:
                self.state.ensure_automatic_booklet_entitlement(
                    student_number=identity.student_number,
                    display_name=identity.display_name,
                    term=term,
                )
                return True
            if identity is not None:
                self.state.revoke_automatic_booklet_entitlement(
                    student_number=identity.student_number,
                    term=term,
                )
            decision = self.state.term_access_decision(identity.subject_key if identity else "", term)
            return bool(decision.get("allowed"))
        except (SiteApiError, BotApiError, AttributeError, TypeError, ValueError):
            return False

    def _handle_booklet_action(
        self,
        *,
        chat_id: int,
        user_id: int,
        name: str,
        message_id: int,
    ) -> None:
        legacy_dialog = self.state.dialog(user_id)
        if legacy_dialog is not None and str(legacy_dialog.get("kind") or "") == "booklets-v1":
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(chat_id)

        if self.platform != "telegram":
            self._render_booklet_screen(chat_id, message_id, bale_unavailable_screen())
            return

        try:
            catalog = self._booklet_catalog(user_id)
            regular_resource = (
                name.startswith("booklet-resource:")
                and not name.endswith(f":{AI_BOOKLET_CONTENT_KIND}")
            )
            if regular_resource:
                allowed, blocked_screen = self._booklet_access_screen(user_id)
                if not allowed:
                    assert blocked_screen is not None
                    self._render_booklet_screen(chat_id, message_id, blocked_screen)
                    return
            screen = self._booklet_screen_for_action(name, user_id, catalog)
        except SiteApiError as error:
            screen = Screen(
                frame_error(str(error)),
                keyboard(
                    [button("↻ تلاش دوباره", action="notes")],
                    [button("🏠 منوی اصلی", action="home")],
                ),
            )
        self._render_booklet_screen(chat_id, message_id, screen)

    def _booklet_screen_for_action(self, name: str, user_id: int, catalog: dict) -> Screen:
        if name == "notes":
            self.state.touch_user(user_id, "notes")
            return booklet_courses_screen(catalog)

        if name.startswith("booklet-course:"):
            course_key = name.removeprefix("booklet-course:")
            if course_by_key(catalog, course_key) is None:
                return Screen(
                    frame_error("این درس دیگر در طرح درس مرجع وجود ندارد."),
                    keyboard(
                        [button("↩️ فهرست درس‌ها", action="notes")],
                        [button("🏠 منوی اصلی", action="home")],
                    ),
                )
            return booklet_sessions_screen(catalog, course_key)

        if name.startswith("booklet-session:"):
            parts = name.split(":")
            if len(parts) != 3 or not parts[2].isdigit():
                return Screen(
                    frame_error("شناسهٔ جلسه معتبر نیست."),
                    keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
                )
            course_key = parts[1]
            session_no = int(parts[2])
            course = course_by_key(catalog, course_key)
            if course is None or session_by_number(course, session_no) is None:
                return Screen(
                    frame_error("این جلسه در طرح درس مرجع پیدا نشد."),
                    keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
                )
            return booklet_resources_screen(catalog, course_key, session_no)

        if name.startswith("booklet-ai-buy:"):
            return self._buy_ai_booklet_screen(name, user_id, catalog)

        if name.startswith("booklet-ai-get:"):
            parts = name.split(":")
            if len(parts) != 3 or not parts[2].isdigit():
                return Screen(
                    frame_error("مسیر دریافت جزوه هوش مصنوعی معتبر نیست."),
                    keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
                )
            return self._booklet_resource_screen(
                f"booklet-resource:{parts[1]}:{int(parts[2])}:{AI_BOOKLET_CONTENT_KIND}",
                user_id,
                catalog,
            )

        return self._booklet_resource_screen(name, user_id, catalog)

    def _buy_ai_booklet_screen(self, name: str, user_id: int, catalog: dict) -> Screen:
        parts = name.split(":")
        if len(parts) != 3 or not parts[2].isdigit() or self.site_api is None:
            return Screen(
                frame_error("مسیر خرید جزوه هوش مصنوعی معتبر نیست."),
                keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
            )
        course_key = parts[1]
        session_no = int(parts[2])
        course = course_by_key(catalog, course_key)
        session = session_by_number(course or {}, session_no) if course is not None else None
        if course is None or session is None:
            return Screen(
                frame_error("این جلسه در طرح درس مرجع پیدا نشد."),
                keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
            )

        term = int(catalog.get("term") or 7)
        course_code = str(course.get("courseKey") or "").strip()
        course_tag = str(course.get("bookletTag") or "").strip()
        sources = self.state.protected_media_for_tag(
            course_tag=course_tag,
            term=term,
            session_no=session_no,
            content_kind=AI_BOOKLET_CONTENT_KIND,
        )
        if not sources:
            base_screen = booklet_resources_screen(catalog, course_key, session_no)
            return Screen(
                "🤖 جزوه هوش مصنوعی این جلسه هنوز منتشر نشده است.\n\n" + base_screen.text,
                base_screen.keyboard,
            )

        account = self.site_api.account(user_id)
        identity = subscription_identity_from_account(account)
        if identity is None:
            return self._unlinked_access_screen(user_id, account)

        if self.state.has_ai_booklet_access(
            identity.subject_key,
            term=term,
            course_code=course_code,
            session_no=session_no,
        ):
            return self._booklet_resource_screen(
                f"booklet-resource:{course_key}:{session_no}:{AI_BOOKLET_CONTENT_KIND}",
                user_id,
                catalog,
            )

        offer_ref = ai_booklet_offer_ref(term, course_code, session_no)
        request_id = ai_booklet_request_id(
            platform=self.platform,
            platform_user_id=user_id,
            subject_key=identity.subject_key,
            term=term,
            course_code=course_code,
            session_no=session_no,
        )
        self.state.begin_ai_booklet_checkout(
            request_id=request_id,
            platform=self.platform,
            platform_user_id=user_id,
            subject_key=identity.subject_key,
            student_number=identity.student_number,
            display_name=identity.display_name,
            term=term,
            course_code=course_code,
            course_tag=course_tag,
            session_no=session_no,
            amount_rials=AI_BOOKLET_PRICE_RIALS,
            offer_ref=offer_ref,
            offer_version=1,
        )
        result = self.site_api.create_bot_payment(
            user_id,
            offer_ref=offer_ref,
            title=f"جزوه هوش مصنوعی {str(course.get('courseTitle') or 'درس')} · جلسه {to_persian_digits(session_no)}",
            description=f"{str(session.get('title') or 'جلسه')} · دسترسی مستقل به جزوه هوش مصنوعی همین جلسه",
            amount_rials=AI_BOOKLET_PRICE_RIALS,
            request_id=request_id,
            product_version=1,
            available_from="",
            expires_at="",
            capacity=0,
            max_per_user=1,
            fulfillment={"text": "پس از تأیید درگاه، جزوه هوش مصنوعی همین جلسه در ربات فعال می‌شود."},
        )
        self.state.bind_ai_booklet_order(request_id, str(result.get("orderToken") or ""))
        return payment_created_screen(
            result,
            platform=self.platform,
            return_to_bot_enabled=self.payment_return_v1_enabled,
        )

    def _booklet_resource_screen(self, name: str, user_id: int, catalog: dict) -> Screen:
        parts = name.split(":")
        if len(parts) != 4 or not parts[2].isdigit() or parts[3] not in RESOURCE_LABELS:
            return Screen(
                frame_error("مسیر دریافت فایل معتبر نیست."),
                keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
            )

        course_key = parts[1]
        session_no = int(parts[2])
        content_kind = parts[3]
        course = course_by_key(catalog, course_key)
        session = session_by_number(course or {}, session_no) if course is not None else None
        if course is None or session is None:
            return Screen(
                frame_error("این جلسه در طرح درس مرجع پیدا نشد."),
                keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
            )

        if content_kind == AI_BOOKLET_CONTENT_KIND:
            base_screen = booklet_resources_screen(catalog, course_key, session_no)
            ai_sources = self.state.protected_media_for_tag(
                course_tag=str(course.get("bookletTag") or ""),
                term=int(catalog.get("term") or 7),
                session_no=session_no,
                content_kind=AI_BOOKLET_CONTENT_KIND,
            )
            if not ai_sources:
                return Screen(
                    "🤖 جزوه هوش مصنوعی این جلسه هنوز منتشر نشده است.\n\n" + base_screen.text,
                    base_screen.keyboard,
                )
            if self.site_api is None:
                return Screen(
                    frame_error("بررسی دسترسی جزوه هوش مصنوعی فعلاً در دسترس نیست."),
                    base_screen.keyboard,
                )
            account = self.site_api.account(user_id)
            identity = subscription_identity_from_account(account)
            if identity is None:
                return self._unlinked_access_screen(user_id, account)
            if not self.state.has_ai_booklet_access(
                identity.subject_key,
                term=int(catalog.get("term") or 7),
                course_code=str(course.get("courseKey") or ""),
                session_no=session_no,
            ):
                return ai_booklet_purchase_screen(catalog, course_key, session_no)

        base_screen = booklet_resources_screen(catalog, course_key, session_no)
        sources = self.state.protected_media_for_tag(
            course_tag=str(course.get("bookletTag") or ""),
            term=7,
            session_no=session_no,
            content_kind=content_kind,
        )
        if not sources:
            message_text = "برای این جلسه و این نوع محتوا هنوز فایل معتبری ثبت نشده است."
        elif self.media_dispatcher is None:
            message_text = "صف ارسال امن فعلاً در دسترس نیست."
        else:
            statuses = [self.media_dispatcher.enqueue(user_id, int(source["id"])) for source in sources]
            message_text = self._booklet_enqueue_message(statuses)
        return Screen(f"{message_text}\n\n{base_screen.text}", base_screen.keyboard)

    @staticmethod
    def _booklet_enqueue_message(statuses: list[str]) -> str:
        if all(status == "full" for status in statuses):
            return "صف ارسال پر است؛ کمی بعد دوباره تلاش کن."
        if all(status in {"rate-limited", "cooldown"} for status in statuses):
            return "⏳ درخواست‌ها خیلی سریع تکرار شدند؛ کمی بعد دوباره امتحان کن."
        if all(status in {"denied", "missing"} for status in statuses):
            return "⚠️ مجوز یا فایل معتبر پیدا نشد؛ از فهرست جزوات دوباره وارد شو."
        if all(status == "duplicate" for status in statuses):
            return "همین فایل هم‌اکنون در صف ارسال توست."
        queued = sum(status == "queued" for status in statuses)
        if queued:
            return (
                f"✅ {to_persian_digits(queued)} فایل در صف امن قرار گرفت و "
                "پس از بررسی دوبارهٔ مجوز ارسال می‌شود."
            )
        if any(status == "duplicate" for status in statuses):
            return "همین فایل هم‌اکنون در صف ارسال توست."
        if any(status in {"rate-limited", "cooldown"} for status in statuses):
            return "⏳ درخواست‌ها خیلی سریع تکرار شدند؛ کمی بعد دوباره امتحان کن."
        return "⚠️ فایل قابل ارسال پیدا نشد."
