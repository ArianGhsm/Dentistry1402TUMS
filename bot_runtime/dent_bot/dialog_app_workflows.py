from __future__ import annotations

import html
import logging
import re
from datetime import datetime, timezone
from urllib.parse import urlsplit

from .api import BotApiError
from .booklets import (
    BOOKLET_BACK, BOOKLET_CANCEL, BOOKLET_HOME, COURSE_BY_CODE, RESOURCE_LABELS,
    course_from_button, courses_screen as booklet_courses_screen,
    resources_screen as booklet_resources_screen, session_from_button,
    sessions_screen as booklet_sessions_screen,
)
from .message_frames import frame_error
from .navid import local_now
from .onboarding import (
    BACK_STEP, CANCEL, CLASS_OTP, CLASS_SITE, CHANGE_PHONE, CONFIRM_PROFILE,
    NEXT_PAGE, PREVIOUS_PAGE, RESEND_OTP, RESTART_PROFILE, SKIP_STUDENT_NUMBER,
    class_auth_screen, class_otp_screen, class_student_number_screen, gateway_screen,
    prompt_screen as onboarding_prompt_screen, success_screen as onboarding_success_screen,
)
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .subscriptions import parse_jalali_date
from .ui import (
    Screen, account_screen, button, complimentary_access_list_screen,
    complimentary_search_results_screen, identity_mapping_remove_confirmation,
    identity_mapping_remove_screen, keyboard, payment_control_center_screen,
    payment_offer_admin_detail_screen, payment_offer_preview_screen,
    payment_offer_wizard_screen, payment_transaction_detail_screen,
    payment_transactions_screen, profile_edit_prompt_screen, profile_edit_requests_screen,
    term_access_policies_screen, term_subscription_admin_screen,
    term_subscription_settings_screen,
)


class DialogAppWorkflows:
    """Onboarding, account and booklet dialog workflows mixed into DentBotApp."""

    def _handle_onboarding_message(
        self,
        chat_id: int,
        user_id: int,
        text: str,
        dialog: dict,
        *,
        sender: dict,
        message: dict,
    ) -> bool:
        step = str(dialog.get("step") or "")
        payload = dict(dialog.get("payload") or {})
        try:
            catalog = self._onboarding_catalog(user_id)
        except SiteApiError as error:
            self.api.send(chat_id, frame_error(str(error)), onboarding_prompt_screen(step, payload, {}).keyboard)
            return True
        if text == CANCEL:
            self.state.clear_dialog(user_id)
            screen = gateway_screen()
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if text == BACK_STEP:
            step, payload = self._back_onboarding(step, payload)
            if step == "gateway":
                self.state.clear_dialog(user_id)
                screen = gateway_screen()
            else:
                self.state.update_dialog(user_id, step=step, payload=payload)
                screen = onboarding_prompt_screen(step, payload, catalog)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if step == "first-name":
            value = self._clean_person_name(text)
            if len(value) < 2:
                self.api.send(chat_id, frame_error("نام معتبر را وارد کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["firstName"] = value
            step = "last-name"
        elif step == "last-name":
            value = self._clean_person_name(text)
            if len(value) < 2:
                self.api.send(chat_id, frame_error("نام خانوادگی معتبر را وارد کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["lastName"] = value
            step = "major"
        elif step == "major":
            if text not in catalog.get("majors", []):
                self.api.send(chat_id, frame_error("رشته را با یکی از دکمه‌ها انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["major"] = text
            for key in ("province", "institution", "institutionSystem", "entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            payload["provincePage"] = 0
            step = "province"
        elif step == "province":
            provinces = [str(item) for item in catalog.get("provinces", [])]
            page_count = max(1, (len(provinces) + 9) // 10)
            page = max(0, min(int(payload.get("provincePage") or 0), page_count - 1))
            if text == NEXT_PAGE and page + 1 < page_count:
                payload["provincePage"] = page + 1
                self.state.update_dialog(user_id, step=step, payload=payload)
                screen = onboarding_prompt_screen(step, payload, catalog)
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            if text == PREVIOUS_PAGE and page > 0:
                payload["provincePage"] = page - 1
                self.state.update_dialog(user_id, step=step, payload=payload)
                screen = onboarding_prompt_screen(step, payload, catalog)
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            if text not in provinces:
                self.api.send(chat_id, frame_error("استان را با یکی از دکمه‌های فهرست انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["province"] = text
            for key in ("institution", "institutionSystem", "entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            step = "institution"
        elif step == "institution":
            allowed = [
                str(item.get("name") or "") for item in catalog.get("institutions", [])
                if isinstance(item, dict) and str(item.get("province") or "") == str(payload.get("province") or "")
            ]
            if text not in allowed:
                self.api.send(chat_id, frame_error("دانشگاه را با یکی از دکمه‌های فهرست انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["institution"] = text
            payload["institutionSystem"] = self._institution_system(
                catalog,
                str(payload.get("province") or ""),
                text,
            )
            for key in ("entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            step = "entry-year"
        elif step == "entry-year":
            if text not in catalog.get("entryYears", []):
                self.api.send(chat_id, frame_error("سال ورود را با یکی از دکمه‌ها انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["entryYear"] = text
            for key in ("entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            step = "entry-term"
        elif step == "entry-term":
            if text not in catalog.get("entryTerms", []):
                self.api.send(chat_id, frame_error("نیمسال ورودی را با دکمه انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["entryTerm"] = text
            payload.pop("courseType", None)
            payload.pop("admissionType", None)
            if str(payload.get("institutionSystem") or "") == "azad":
                payload["courseType"] = ""
                payload["admissionType"] = text
                step = "student-number"
            else:
                step = "course-type"
        elif step == "course-type":
            if text not in catalog.get("courseTypes", []):
                self.api.send(chat_id, frame_error("نوع دوره را با دکمه انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            course_type = self._canonical_course_type(text)
            payload["courseType"] = course_type
            payload["admissionType"] = f"{payload.get('entryTerm', '')} ({course_type})"
            step = "student-number"
        elif step == "student-number":
            normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
            student_number = "" if text == SKIP_STUDENT_NUMBER else re.sub(r"[^0-9]", "", normalized)[:20]
            if text != SKIP_STUDENT_NUMBER and not 5 <= len(student_number) <= 20:
                self.api.send(chat_id, frame_error("شماره دانشجویی باید بین ۵ تا ۲۰ رقم باشد؛ یا دکمه مرحله اختیاری را بزن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["studentNumber"] = student_number
            step = "review"
        elif step == "review":
            if text == RESTART_PROFILE:
                payload = {}
                step = "first-name"
            elif text == CONFIRM_PROFILE:
                step = "contact"
            else:
                self.api.send(chat_id, frame_error("اطلاعات را تأیید کن یا از اول وارد کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
        elif step == "contact":
            contact = dict(message.get("contact") or {})
            contact_user_id = contact.get("user_id")
            if not contact or not str(contact.get("phone_number") or "").strip():
                self.api.send(chat_id, frame_error("شماره را تایپ نکن؛ Contact خودت را با دکمه ارسال کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            if contact_user_id is not None and str(contact_user_id) != str(sender.get("id") or ""):
                self.api.send(chat_id, frame_error("فقط Contact متعلق به همین حساب قابل قبول است."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            if self.platform == "telegram" and contact_user_id is None:
                self.api.send(chat_id, frame_error("تلگرام این Contact را متعلق به حساب شما اعلام نکرد؛ دکمه ارسال شماره خودم را بزن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            try:
                profile = {key: payload.get(key, "") for key in (
                    "firstName", "lastName", "major", "province", "institution", "institutionSystem",
                    "entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"
                )}
                result = self.site_api.request_onboarding_otp(
                    user_id,
                    profile=profile,
                    phone_number=str(contact.get("phone_number") or ""),
                )
            except SiteApiError as error:
                message_text = str(error)
                if "مشخصات تحصیلی خارج از فهرست معتبر" in message_text:
                    message_text = "اطلاعات آموزشی با فهرست فعلی هم‌خوان نیست؛ «مرحله قبل» را بزن و نیمسال یا نوع دوره را دوباره انتخاب کن."
                self.api.send(chat_id, frame_error(message_text), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload = {
                "profile": profile,
                "challengeRef": str(result.get("challengeRef") or ""),
                "phoneMasked": str(result.get("phoneMasked") or ""),
            }
            step = "otp"
        elif step == "otp":
            if text == CHANGE_PHONE:
                payload = dict(payload.get("profile") or {})
                step = "contact"
            elif text == RESEND_OTP:
                try:
                    result = self.site_api.resend_onboarding_otp(user_id, challenge_ref=str(payload.get("challengeRef") or ""))
                    payload["phoneMasked"] = str(result.get("phoneMasked") or payload.get("phoneMasked") or "")
                    self.state.update_dialog(user_id, step=step, payload=payload)
                    screen = onboarding_prompt_screen(step, payload, catalog)
                    self.api.send(chat_id, "<b>✅ کد تازه ارسال شد</b>\n\n" + screen.text, screen.keyboard)
                except SiteApiError as error:
                    self.api.send(chat_id, frame_error(str(error)), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            else:
                normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
                code = re.sub(r"[^0-9]", "", normalized)
                if len(code) != 6:
                    self.api.send(chat_id, frame_error("کد تأیید باید ۶ رقم باشد."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                    return True
                try:
                    result = self.site_api.verify_onboarding_otp(
                        user_id,
                        challenge_ref=str(payload.get("challengeRef") or ""),
                        code=code,
                    )
                except SiteApiError as error:
                    self.api.send(chat_id, frame_error(str(error)), onboarding_prompt_screen(step, payload, catalog).keyboard)
                    return True
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(chat_id)
                screen = onboarding_success_screen(dict(result.get("profile") or {}))
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
        else:
            step = self._recover_onboarding_step(payload, catalog)
            self.state.update_dialog(user_id, step=step, payload=payload)
            screen = Screen(
                frame_error("مرحلهٔ قبلی قابل ادامه نبود؛ اطلاعاتت پاک نشد و از نزدیک‌ترین مرحلهٔ معتبر ادامه می‌دهیم."),
                onboarding_prompt_screen(step, payload, catalog).keyboard,
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        self.state.update_dialog(user_id, step=step, payload=payload)
        screen = onboarding_prompt_screen(step, payload, catalog)
        self.api.send(chat_id, screen.text, screen.keyboard)
        return True

    def _handle_dialog_message(
        self,
        chat_id: int,
        user_id: int,
        text: str,
        *,
        sender: dict | None = None,
        message: dict | None = None,
    ) -> bool:
        dialog = self.state.dialog(user_id)
        if not dialog or text.startswith("/"):
            return False
        if dialog.get("kind") == "onboarding-v1":
            return self._handle_onboarding_message(
                chat_id,
                user_id,
                text,
                dialog,
                sender=dict(sender or {"id": user_id}),
                message=dict(message or {}),
            )
        if dialog.get("kind") == "class-auth-v1":
            step = str(dialog.get("step") or "method")
            payload = dict(dialog.get("payload") or {})
            if text == CANCEL:
                self.state.clear_dialog(user_id)
                screen = gateway_screen()
            elif text == BACK_STEP:
                if step == "method":
                    self.state.clear_dialog(user_id)
                    screen = gateway_screen()
                elif step == "student-number":
                    self.state.update_dialog(user_id, step="method", payload={})
                    screen = class_auth_screen()
                else:
                    self.state.update_dialog(user_id, step="student-number", payload={})
                    screen = class_student_number_screen()
            elif step == "method" and text == CLASS_OTP:
                self.state.update_dialog(user_id, step="student-number", payload={})
                screen = class_student_number_screen()
            elif step == "method" and text == CLASS_SITE:
                try:
                    result = self.site_api.start_link(
                        user_id,
                        platform_profile=self._platform_profile(dict(sender or {"id": user_id})),
                    )
                    self.state.clear_dialog(user_id)
                    self._remove_reply_keyboard(chat_id)
                    screen = account_screen(
                        self.site_url,
                        platform=self.platform,
                        linked_user=(
                            dict(result.get("user") or {})
                            if result.get("alreadyLinked") and result.get("authComplete")
                            else None
                        ),
                        link_url=str(result.get("linkUrl") or ""),
                    )
                except (SiteApiError, AttributeError) as error:
                    screen = Screen(frame_error(str(error)), class_auth_screen().keyboard)
            elif step == "student-number":
                normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
                student_number = re.sub(r"[^0-9]", "", normalized)[:20]
                if len(student_number) < 5:
                    screen = Screen(frame_error("شماره دانشجویی معتبر را بفرست."), class_student_number_screen().keyboard)
                else:
                    try:
                        result = self.site_api.start_class_auth_otp(
                            user_id,
                            student_number=student_number,
                            platform_profile=self._platform_profile(dict(sender or {"id": user_id})),
                        )
                        if result.get("alreadyLinked") and result.get("authComplete"):
                            self.state.clear_dialog(user_id)
                            self._remove_reply_keyboard(chat_id)
                            account = self.site_api.account(user_id)
                            screen = account_screen(
                                self.site_url, platform=self.platform,
                                linked_user=dict(account.get("user") or {}),
                                onboarding_profile=dict(account.get("onboardingProfile") or {}),
                            )
                        else:
                            payload = {
                                "challengeRef": str(result.get("challengeRef") or ""),
                                "phoneMasked": str(result.get("phoneMasked") or ""),
                                "studentNumber": student_number,
                            }
                            self.state.update_dialog(user_id, step="otp", payload=payload)
                            screen = class_otp_screen(payload["phoneMasked"])
                    except (SiteApiError, AttributeError) as error:
                        screen = Screen(frame_error(str(error)), class_student_number_screen().keyboard)
            elif step == "otp":
                normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
                code = re.sub(r"[^0-9]", "", normalized)
                if len(code) != 6:
                    screen = Screen(frame_error("کد تأیید باید ۶ رقم باشد."), class_otp_screen(str(payload.get("phoneMasked") or "")).keyboard)
                else:
                    try:
                        self.site_api.verify_class_auth_otp(user_id, challenge_ref=str(payload.get("challengeRef") or ""), code=code)
                        self.state.clear_dialog(user_id)
                        self._remove_reply_keyboard(chat_id)
                        account = self.site_api.account(user_id)
                        screen = account_screen(
                            self.site_url, platform=self.platform,
                            linked_user=dict(account.get("user") or {}),
                            onboarding_profile=dict(account.get("onboardingProfile") or {}),
                        )
                    except (SiteApiError, AttributeError) as error:
                        screen = Screen(frame_error(str(error)), class_otp_screen(str(payload.get("phoneMasked") or "")).keyboard)
            else:
                screen = Screen(frame_error("یکی از دو روش احراز هویت را با دکمه انتخاب کن."), class_auth_screen().keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if dialog.get("kind") == "booklets-v1":
            return self._handle_booklet_dialog(chat_id, user_id, text, dialog)
        if dialog.get("kind") == "profile-edit-v1":
            payload = dict(dialog.get("payload") or {})
            value = " ".join(text.split())[:180]
            if not value:
                self.api.send(chat_id, frame_error("مقدار پیشنهادی خالی است."), profile_edit_prompt_screen(str(payload.get("fieldLabel") or "مشخصات")).keyboard)
                return True
            try:
                self.site_api.request_profile_edit(user_id, field=str(payload.get("field") or ""), value=value)
                self.state.clear_dialog(user_id)
                account = self.site_api.account(user_id)
                base = account_screen(
                    self.site_url, platform=self.platform,
                    linked_user=dict(account.get("user") or {}),
                    onboarding_profile=dict(account.get("onboardingProfile") or {}),
                )
                screen = Screen("<b>✅ درخواست ویرایش برای مالک ارسال شد</b>\n\nتا پیش از تأیید، مقدار فعلی بدون تغییر می‌ماند.\n\n" + base.text, base.keyboard)
                if user_id != self.owner_id:
                    try:
                        owner_screen = profile_edit_requests_screen(self.site_api.profile_edit_requests(self.owner_id))
                        self.api.send(self.owner_id, owner_screen.text, owner_screen.keyboard)
                    except (SiteApiError, BotApiError):
                        logging.warning("profile edit owner notification failed")
            except (SiteApiError, AttributeError) as error:
                screen = Screen(frame_error(str(error)), profile_edit_prompt_screen(str(payload.get("fieldLabel") or "مشخصات")).keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if dialog.get("kind") in {"identity-claim", "identity-mapping"}:
            self.state.clear_dialog(user_id)
            screen = Screen(
                frame_error("تأیید و اتصال دستی هویت غیرفعال شده است؛ فقط OTP سایت یا ورود امن سایت پذیرفته می‌شود."),
                class_auth_screen().keyboard,
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "identity-mapping-remove":
            payload = dict(dialog.get("payload") or {})
            reason = " ".join(text.split())[:240]
            if len(reason) < 5:
                self.api.send(chat_id, frame_error("دلیل حذف باید روشن و حداقل ۵ نویسه باشد."), identity_mapping_remove_screen(dict(payload.get("item") or {}), platform=self.platform).keyboard)
                return True
            payload["reason"] = reason
            self.state.update_dialog(user_id, step="preview", payload=payload)
            screen = identity_mapping_remove_confirmation(payload, platform=self.platform)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-access-policy-add":
            try:
                term = self._parse_tomans(text)
                if not 1 <= term <= 12:
                    raise ValueError("invalid term")
                if self.state.term_access_policy(term) is None:
                    self.state.update_term_access_policy(
                        term, {}, actor_user_id=user_id, actor_platform=self.platform,
                        note="owner created configurable term policy",
                    )
                self.state.clear_dialog(user_id)
                screen = term_access_policies_screen(self.state.term_access_policies())
            except (TypeError, ValueError):
                screen = Screen(
                    frame_error("شماره ترم باید بین ۱ تا ۱۲ باشد."),
                    keyboard([button("انصراف", action="term-access-policies")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-price":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            try:
                tomans = self._parse_tomans(text)
                if not 1_000 <= tomans <= 10_000_000_000:
                    raise ValueError("invalid price")
                self.state.update_term_access_policy(
                    term, {"monthlyPriceRials": tomans * 10}, actor_user_id=user_id,
                    actor_platform=self.platform, note="owner price change",
                )
                self.state.clear_dialog(user_id)
                screen = term_subscription_admin_screen(
                    self.state.term_access_policy(term) or {}, self._term_subscription_report(user_id, term)
                )
            except (TypeError, ValueError):
                screen = Screen(
                    frame_error("مبلغ را به تومان و فقط با رقم بفرست؛ نمونه: ۱۵۰۰۰۰"),
                    keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-start":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            try:
                normalized = text.strip().translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")).replace("/", "-")
                year, month, day = parse_jalali_date(normalized)
                active_from = f"{year:04d}-{month:02d}-{day:02d}"
                updated = self.state.update_term_access_policy(
                    term, {"activeFromJalali": active_from}, actor_user_id=user_id,
                    actor_platform=self.platform, note="owner activation boundary change",
                )
                self.state.clear_dialog(user_id)
                screen = term_subscription_settings_screen(updated)
            except (TypeError, ValueError):
                screen = Screen(
                    frame_error("تاریخ شمسی معتبر را به شکل ۱۴۰۵/۰۷/۰۱ بفرست."),
                    keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-grant-search":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            query = " ".join(text.split())[:120]
            if len(query) < 2 or self.site_api is None or not hasattr(self.site_api, "payment_directory"):
                screen = Screen(
                    frame_error("نام یا شماره دانشجویی را حداقل با دو نویسه بفرست."),
                    keyboard([button("انصراف", action=f"term-subscription-admin:{term}")]),
                )
            else:
                try:
                    candidates = [
                        item for item in self.site_api.payment_directory(user_id, query=query, limit=12).get("items", [])
                        if isinstance(item, dict) and str(item.get("studentNumber") or "").isdigit()
                    ]
                    for candidate in candidates:
                        candidate["termAccess"] = self.state.term_subject_entitlement_status(
                            student_number=str(candidate.get("studentNumber") or ""), term=term
                        )
                    payload["candidates"] = candidates
                    self.state.update_dialog(user_id, step="results", payload=payload)
                    screen = complimentary_search_results_screen(candidates, term=term)
                except SiteApiError as error:
                    screen = Screen(
                        frame_error(str(error)), keyboard([button("انصراف", action=f"term-subscription-admin:{term}")])
                    )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-grant-note":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            student = str(payload.get("studentNumber") or "")
            name = str(payload.get("displayName") or "")
            note = "" if text == "-" else " ".join(text.split())[:240]
            try:
                granted = self.state.grant_complimentary_term_access(
                    term=term, student_number=student, display_name=name,
                    actor_user_id=user_id, actor_platform=self.platform, note=note,
                )
                self.state.clear_dialog(user_id)
                screen = Screen(
                    f"<b>✅ دسترسی رایگان فعال شد</b>\n\n"
                    f"👤 {html.escape(str(granted.get('displayName') or 'دانشجو'))}\n"
                    f"🎓 <code>{to_persian_digits(granted.get('studentNumber') or '—')}</code>\n"
                    f"📚 ترم {to_persian_digits(term)}",
                    complimentary_access_list_screen(self.state.complimentary_term_access(term), term=term).keyboard,
                )
            except ValueError as error:
                screen = Screen(
                    frame_error(str(error)), keyboard([button("انصراف", action=f"term-subscription-admin:{term}")])
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-revoke-note":
            payload = dict(dialog.get("payload") or {})
            entitlement_id = int(payload.get("entitlementId") or 0)
            term = int(payload.get("term") or 7)
            note = "" if text == "-" else " ".join(text.split())[:240]
            revoked = self.state.revoke_complimentary_term_access(
                entitlement_id, actor_user_id=user_id, actor_platform=self.platform, note=note,
            )
            self.state.clear_dialog(user_id)
            screen = (
                complimentary_access_list_screen(self.state.complimentary_term_access(term), term=term)
                if revoked is not None
                else Screen(frame_error("دسترسی رایگان پیدا نشد."), keyboard([button("بازگشت", action=f"term-subscription-admin:{term}")]))
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-audience-search":
            step = str(dialog.get("step") or "query")
            payload = dict(dialog.get("payload") or {})
            if step == "query":
                query = " ".join(text.split())[:120]
                if len(query) < 2 or self.site_api is None or not hasattr(self.site_api, "payment_directory"):
                    screen = Screen(frame_error("نام یا شماره را حداقل با دو نویسه بفرست."), keyboard([button("انصراف", action="payment-audiences")]))
                else:
                    try:
                        payload["candidates"] = [item for item in self.site_api.payment_directory(user_id, query=query, limit=12).get("items", []) if isinstance(item, dict)]
                        payload.setdefault("selected", [])
                        self.state.update_dialog(user_id, step="results", payload=payload)
                        screen = self._payment_audience_search_results(payload)
                    except SiteApiError as error:
                        screen = Screen(frame_error(str(error)), keyboard([button("انصراف", action="payment-audiences")]))
            elif step == "name":
                try:
                    self.state.save_payment_audience(text, list(payload.get("selected") or []), actor_user_id=user_id, actor_platform=self.platform)
                    self.state.clear_dialog(user_id)
                    screen = self._dynamic_screen("payment-audiences", user_id)
                except ValueError:
                    screen = Screen(frame_error("نام فهرست معتبر نیست."), keyboard([button("انصراف", action="payment-audiences")]))
            else:
                screen = self._payment_audience_search_results(payload)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-audience-new":
            parts = [part.strip() for part in text.split("|", 1)]
            if len(parts) != 2:
                self.api.send(chat_id, frame_error("قالب باید «نام فهرست | شماره‌ها» باشد."), keyboard([button("انصراف", action="payment-audiences")]))
                return True
            numbers = [part.strip() for part in re.split(r"[,،\s]+", parts[1]) if part.strip()]
            try:
                self.state.save_payment_audience(parts[0], numbers, actor_user_id=user_id, actor_platform=self.platform)
                self.state.clear_dialog(user_id)
                screen = self._dynamic_screen("payment-audiences", user_id)
            except ValueError:
                screen = Screen(frame_error("نام یا شماره‌های فهرست معتبر نیست."), keyboard([button("انصراف", action="payment-audiences")]))
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-offer-edit":
            payload = dict(dialog.get("payload") or {})
            offer_ref = str(payload.get("offerRef") or "")
            field = str(payload.get("field") or "")
            value: object = text.strip()
            if value == "-":
                value = ""
            try:
                if field == "amountRials":
                    tomans = self._parse_tomans(str(value))
                    value = tomans * 10
                elif field in {"capacity", "maxPurchasesPerUser"}:
                    value = self._parse_tomans(str(value))
                elif field == "audienceUsers":
                    numbers = [part.strip() for part in re.split(r"[,،\s]+", str(value)) if part.strip()]
                    field, value = "audience", {"mode": "users", "studentNumbers": numbers}
                elif field == "audienceCohorts":
                    cohorts = [part.strip() for part in re.split(r"[,،\s]+", str(value)) if re.fullmatch(r"[A-Za-z0-9_-]{2,80}", part.strip())]
                    field, value = "audience", {"mode": "cohorts", "cohorts": cohorts}
                elif field in {"fulfillmentText", "fulfillmentUrl"}:
                    current = self._ordinary_payment_offer(offer_ref)
                    if current is None:
                        raise ValueError("missing product")
                    fulfillment = dict(current.get("fulfillment") or {})
                    if field == "fulfillmentText":
                        fulfillment["text"] = " ".join(str(value).split())[:600]
                    else:
                        url = str(value).strip()
                        parsed = urlsplit(url) if url else None
                        if url and (parsed is None or parsed.scheme.lower() != "https" or not parsed.netloc):
                            raise ValueError("invalid fulfillment URL")
                        fulfillment["url"] = url
                    field, value = "fulfillment", fulfillment
                item = self.state.update_payment_offer(
                    offer_ref, {field: value}, actor_user_id=user_id, actor_platform=self.platform
                )
                self.state.clear_dialog(user_id)
                screen = payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            except (TypeError, ValueError):
                screen = Screen(frame_error("مقدار واردشده معتبر نیست."), keyboard([button("انصراف", action=f"payment-offer:{offer_ref}")]))
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-search":
            query = " ".join(text.split())[:120]
            self.state.clear_dialog(user_id)
            if len(query) < 2 or self.site_api is None or not hasattr(self.site_api, "payment_transactions"):
                screen = Screen(frame_error("عبارت جستجو حداقل دو نویسه باشد."), payment_transactions_screen({"items": []}).keyboard)
            else:
                try:
                    filters = self._payment_transaction_filter_state(user_id)
                    filters["query"] = query
                    self._payment_filters[user_id] = filters
                    screen = payment_transactions_screen(
                        self._payment_transactions_payload(user_id, limit=10), filters=filters
                    )
                except SiteApiError as error:
                    screen = Screen(frame_error(str(error)), payment_transactions_screen({"items": []}).keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-transaction-date":
            parts = [part.strip() for part in text.split("|", 1)]
            try:
                if len(parts) != 2:
                    raise ValueError("invalid date range")
                local_zone = local_now("Asia/Tehran").tzinfo
                start = datetime.strptime(parts[0], "%Y-%m-%d").replace(tzinfo=local_zone)
                end = datetime.strptime(parts[1], "%Y-%m-%d").replace(hour=23, minute=59, second=59, tzinfo=local_zone)
                if start > end:
                    raise ValueError("invalid date range")
                filters = dict(dialog.get("payload") or {})
                filters["dateFrom"] = start.astimezone(timezone.utc).isoformat()
                filters["dateTo"] = end.astimezone(timezone.utc).isoformat()
                self._payment_filters[user_id] = filters
                self.state.clear_dialog(user_id)
                screen = payment_transactions_screen(self._payment_transactions_payload(user_id), filters=filters)
            except (TypeError, ValueError, SiteApiError):
                screen = Screen(
                    frame_error("بازه معتبر نیست؛ نمونه: 2026-08-01 | 2026-08-31"),
                    keyboard([button("انصراف", action="payment-transaction-filters")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-transaction-status":
            payload = dict(dialog.get("payload") or {})
            order_id = max(0, int(payload.get("orderId") or 0))
            status = str(payload.get("status") or "")
            note = " ".join(text.split())[:240]
            if len(note) < 3 or self.site_api is None or not hasattr(self.site_api, "payment_update_transaction_status"):
                screen = Screen(frame_error("دلیل تغییر وضعیت را حداقل با ۳ نویسه بنویس."), keyboard([button("انصراف", action=f"payment-transaction:{order_id}")]))
            else:
                try:
                    updated = self.site_api.payment_update_transaction_status(
                        user_id, order_id=order_id, status=status, note=note
                    )
                    self.state.record_payment_action(
                        "transaction-status-updated", actor_user_id=user_id, actor_platform=self.platform,
                        note=f"order:{order_id}:{status}",
                    )
                    self.state.clear_dialog(user_id)
                    screen = payment_transaction_detail_screen(updated)
                except SiteApiError as error:
                    screen = Screen(frame_error(str(error)), keyboard([button("بازگشت", action=f"payment-transaction:{order_id}")]))
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id != self.owner_id or dialog.get("kind") != "payment-offer":
            return False
        step = str(dialog.get("step") or "")
        payload = dict(dialog.get("payload") or {})
        if step == "title":
            title = " ".join(text.split())[:160]
            if len(title) < 3:
                self.api.send(chat_id, frame_error("عنوان باید حداقل ۳ نویسه باشد."), payment_offer_wizard_screen("title", payload).keyboard)
                return True
            payload["title"] = title
            self.state.update_dialog(user_id, step="amount", payload=payload)
            screen = payment_offer_wizard_screen("amount", payload)
        elif step == "custom-amount":
            amount_tomans = self._parse_tomans(text)
            if amount_tomans < 1000 or amount_tomans > 10000000000:
                self.api.send(chat_id, frame_error("مبلغ باید بین ۱٬۰۰۰ تا ۱۰ میلیارد تومان باشد."), payment_offer_wizard_screen(step, payload).keyboard)
                return True
            payload["amountRials"] = amount_tomans * 10
            self.state.update_dialog(user_id, step="audience", payload=payload)
            screen = payment_offer_wizard_screen("audience", payload)
        elif step == "description":
            payload["description"] = " ".join(text.split())[:360]
            self.state.update_dialog(user_id, step="preview", payload=payload)
            screen = payment_offer_preview_screen(payload)
        else:
            screen = payment_offer_wizard_screen(step, payload)
        self.api.send(chat_id, screen.text, screen.keyboard)
        return True

    def _handle_booklet_dialog(self, chat_id: int, user_id: int, text: str, dialog: dict) -> bool:
        step = str(dialog.get("step") or "course")
        payload = dict(dialog.get("payload") or {})
        if text in {BOOKLET_CANCEL, BOOKLET_HOME}:
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(chat_id)
            screen = self._screen("home", user_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if text == BOOKLET_BACK:
            if step == "course":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(chat_id)
                screen = self._screen("home", user_id)
            elif step == "session":
                self.state.update_dialog(user_id, step="course", payload={})
                screen = booklet_courses_screen()
            else:
                course_code = str(payload.get("courseCode") or "")
                self.state.update_dialog(user_id, step="session", payload={"courseCode": course_code})
                screen = booklet_sessions_screen(course_code)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if step == "course":
            course = course_from_button(text)
            if course is None:
                self.api.send(chat_id, frame_error("درس را فقط از دکمه‌های فهرست انتخاب کن."), booklet_courses_screen().keyboard)
                return True
            course_code = str(course["code"])
            self.state.update_dialog(user_id, step="session", payload={"courseCode": course_code})
            self.api.send(chat_id, booklet_sessions_screen(course_code).text, booklet_sessions_screen(course_code).keyboard)
            return True
        course_code = str(payload.get("courseCode") or "")
        if course_code not in COURSE_BY_CODE:
            self.state.update_dialog(user_id, step="course", payload={})
            screen = booklet_courses_screen()
            self.api.send(chat_id, frame_error("درس قبلی معتبر نبود؛ دوباره انتخاب کن.") + "\n\n" + screen.text, screen.keyboard)
            return True
        if step == "session":
            session = session_from_button(course_code, text)
            if session is None:
                screen = booklet_sessions_screen(course_code)
                self.api.send(chat_id, frame_error("جلسه را فقط از دکمه‌های طرح درس انتخاب کن."), screen.keyboard)
                return True
            session_no = int(session[0])
            payload = {"courseCode": course_code, "sessionNo": session_no}
            self.state.update_dialog(user_id, step="resource", payload=payload)
            screen = booklet_resources_screen(course_code, session_no)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if step == "resource":
            content_kind = next((kind for kind, label in RESOURCE_LABELS.items() if label == text), "")
            session_no = int(payload.get("sessionNo") or 0)
            screen = booklet_resources_screen(course_code, session_no)
            if not content_kind:
                self.api.send(chat_id, frame_error("نوع فایل را از چهار دکمه انتخاب کن."), screen.keyboard)
                return True
            sources = self.state.protected_media_for(
                course_code=course_code,
                term=int(COURSE_BY_CODE[course_code]["term"]),
                session_no=session_no,
                content_kind=content_kind,
            )
            if not sources:
                self.api.send(chat_id, frame_error("برای این جلسه و این نوع، هنوز فایل معتبری ثبت نشده است."), screen.keyboard)
                return True
            if self.media_dispatcher is None:
                self.api.send(chat_id, frame_error("صف ارسال امن فعلاً در دسترس نیست."), screen.keyboard)
                return True
            statuses = [self.media_dispatcher.enqueue(user_id, int(source["id"])) for source in sources]
            if all(status == "full" for status in statuses):
                message = "صف ارسال پر است؛ چند لحظه بعد دوباره تلاش کن."
            elif all(status in {"rate-limited", "cooldown"} for status in statuses):
                message = "⏳ درخواست‌ها خیلی سریع تکرار شدند؛ چند لحظه بعد دوباره امتحان کن."
            elif all(status in {"denied", "missing"} for status in statuses):
                message = "⚠️ مجوز یا فایل معتبر این بخش پیدا نشد؛ دوباره از فهرست جزوات وارد شو."
            elif all(status == "duplicate" for status in statuses):
                message = "همین فایل هم‌اکنون در صف ارسال توست."
            else:
                queued = sum(status == "queued" for status in statuses)
                if queued:
                    message = f"✅ {queued} فایل در صف امن قرار گرفت و پس از بررسی دوبارهٔ مجوز ارسال می‌شود."
                elif any(status == "duplicate" for status in statuses):
                    message = "همین فایل هم‌اکنون در صف ارسال توست."
                elif any(status in {"rate-limited", "cooldown"} for status in statuses):
                    message = "⏳ درخواست‌ها خیلی سریع تکرار شدند؛ چند لحظه بعد دوباره امتحان کن."
                else:
                    message = "⚠️ فایل قابل ارسال پیدا نشد؛ دوباره از فهرست جزوات وارد شو."
            self.api.send(chat_id, message, screen.keyboard)
            return True
        self.state.update_dialog(user_id, step="course", payload={})
        screen = booklet_courses_screen()
        self.api.send(chat_id, frame_error("مرحلهٔ جزوات معتبر نبود؛ از فهرست درس‌ها ادامه بده."), screen.keyboard)
        return True
