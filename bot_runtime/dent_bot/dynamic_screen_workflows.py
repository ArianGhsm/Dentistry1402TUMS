from __future__ import annotations

import hashlib
import html
import re

from .academic_term7_rich import decorate_academic_notification_screen
from .api import BotApiError
from .app_shell_screens import home
from .message_frames import frame_error
from .onboarding import class_auth_screen
from .payments import identity_from_account
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .student_assistant import send_private_challenge
from .subscriptions import (
    billing_period_for, policy_is_effective, subscription_identity_from_account, utc_iso,
)
from .ui import (
    Screen, account_screen, bot_start_url, button, complimentary_access_list_screen,
    exam_screen, format_rials, grades_screen, identity_mapping_remove_confirmation,
    identity_mapping_remove_screen, integration_challenge_waiting_screen, keyboard,
    navid_screen, notification_audience_screen, notification_detail_screen,
    notification_list_screen, owner_grade_screen, owner_identity_mappings_screen,
    owner_payment_offers_screen, payment_confirm_screen, payment_control_center_screen,
    payment_created_screen, payment_offer_admin_detail_screen,
    payment_offer_delete_confirmation, payment_offer_preview_screen,
    payment_offer_saved_screen, payment_offer_wizard_screen, payment_offers_screen,
    payment_people_screen, payment_product_report_screen, payment_status_screen,
    payment_transaction_detail_screen, payment_transaction_filters_screen,
    payment_transactions_screen, profile_edit_fields_screen, profile_edit_prompt_screen,
    profile_edit_requests_screen, student_assistant_screen, term_access_policies_screen,
    term_subscription_admin_screen, term_subscription_info_screen, term_subscription_screen,
    term_subscription_settings_screen,
)


class DynamicScreenWorkflows:
    """Large dynamic-screen routing table mixed into DentBotApp."""

    def _dynamic_screen_core(
        self,
        name: str,
        user_id: int,
        *,
        request_id: str = "",
        sender: dict | None = None,
    ) -> Screen:
        blocked = (
            self._private_access_gate(user_id)
            if self._requires_canonical_link(name)
            else self._bot_entry_gate(user_id)
        )
        if blocked is not None:
            return blocked
        if name == "admin-grades" and user_id == self.owner_id:
            return owner_grade_screen(self.site_url)
        if name in {"term-subscription", "term-subscription:7"} or name.startswith("term-subscription-info:"):
            term = int(name.rsplit(":", 1)[1]) if ":" in name and name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("برای این ترم سیاست دسترسی تعریف نشده است."), self._screen("home", user_id).keyboard)
            account = self._account_snapshot(user_id, refresh=True)
            identity = subscription_identity_from_account(account)
            decision = self.state.term_access_decision(identity.subject_key if identity else "", term)
            return term_subscription_info_screen(policy, term=term) if name.startswith("term-subscription-info:") else term_subscription_screen(policy, decision, term=term)
        if name.startswith("term-subscription-buy:"):
            term_text = name.rsplit(":", 1)[1]
            if not term_text.isdigit() or self.site_api is None:
                return Screen(frame_error("مسیر خرید اشتراک معتبر نیست."), self._screen("home", user_id).keyboard)
            term = int(term_text)
            policy = self.state.term_access_policy(term)
            account = self._account_snapshot(user_id, refresh=True)
            identity = subscription_identity_from_account(account)
            if policy is None or identity is None:
                return self._unlinked_access_screen(user_id, account)
            decision = self.state.term_access_decision(identity.subject_key, term)
            if decision.get("allowed"):
                return term_subscription_screen(policy, decision, term=term)
            if not policy_is_effective(policy):
                return term_subscription_screen(policy, decision, term=term)
            offer = self.state.payment_offer(str(policy.get("offerRef") or ""), require_active=True)
            if offer is None:
                return Screen(frame_error("فروش اشتراک این ترم فعلاً متوقف است."), term_subscription_screen(policy, decision, term=term).keyboard)
            period = billing_period_for(term)
            stable_request_id = hashlib.sha256(
                f"term-subscription:{self.platform}:{user_id}:{identity.subject_key}:{period.key}".encode("utf-8")
            ).hexdigest()
            try:
                checkout = self.state.begin_term_subscription_checkout(
                    request_id=stable_request_id, platform=self.platform, platform_user_id=user_id,
                    subject_key=identity.subject_key, student_number=identity.student_number,
                    display_name=identity.display_name, term=term, billing_period=period.key,
                    amount_rials=int(policy["monthlyPriceRials"]), offer_ref=str(policy["offerRef"]),
                    offer_version=int(policy.get("version") or 1),
                )
                result = self.site_api.create_bot_payment(
                    user_id, offer_ref=str(policy["offerRef"]),
                    title=f"اشتراک جزوات ترم {term} · {period.month_label}",
                    description="اشتراک کامل ماه شمسی جاری؛ اعتبار فقط تا پایان همین ماه است.",
                    amount_rials=int(checkout["amountRials"]), request_id=stable_request_id,
                    product_version=int(checkout["offerVersion"]),
                    available_from="", expires_at=utc_iso(period.expires_at), capacity=0, max_per_user=0,
                    fulfillment={
                        "kind": "term_subscription", "term": term, "billingPeriod": period.key,
                        "text": "پس از تأیید درگاه، اشتراک همین ماه در ربات فعال می‌شود.",
                    },
                )
                self.state.bind_term_subscription_order(stable_request_id, str(result.get("orderToken") or ""))
                return payment_created_screen(
                    result, platform=self.platform, return_to_bot_enabled=self.payment_return_v1_enabled
                )
            except (SiteApiError, TypeError, ValueError) as error:
                return Screen(frame_error(str(error)), term_subscription_screen(policy, decision, term=term).keyboard)
        if name == "term-access-policies" and user_id == self.owner_id:
            return term_access_policies_screen(self.state.term_access_policies())
        if name == "term-access-policy-add" and user_id == self.owner_id:
            self.state.start_dialog(user_id, "term-access-policy-add", "term", {})
            return Screen(
                "<b>➕ افزودن policy ترم</b>\n\nشماره ترم را بین ۱ تا ۱۲ بفرست. policy جدید در حالت باز و غیرفعال ساخته می‌شود.",
                keyboard([button("انصراف", action="term-access-policies")]),
            )
        if name.startswith("term-subscription-admin:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("سیاست این ترم پیدا نشد."), payment_control_center_screen([]).keyboard)
            return term_subscription_admin_screen(policy, self._term_subscription_report(user_id, term))
        if name.startswith("term-subscription-settings:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            return term_subscription_settings_screen(policy) if policy else Screen(frame_error("سیاست پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("term-subscription-price:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            self.state.start_dialog(user_id, "term-subscription-price", "value", {"term": term})
            return Screen(
                f"<b>💰 مبلغ اشتراک ترم {to_persian_digits(term)}</b>\n\nمبلغ جدید را به <b>تومان</b> بفرست. سفارش‌های قبلاً ساخته‌شده تغییر نمی‌کنند.",
                keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
            )
        if name.startswith("term-subscription-start:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            self.state.start_dialog(user_id, "term-subscription-start", "value", {"term": term})
            return Screen(
                f"<b>📅 شروع سیاست ترم {to_persian_digits(term)}</b>\n\nتاریخ شمسی را به شکل <code>۱۴۰۵/۰۷/۰۱</code> بفرست. مرز اجرا نیمه‌شب تهران است.",
                keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
            )
        if name.startswith(("term-subscription-toggle:", "term-subscription-mode:", "term-subscription-reminders:")) and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("سیاست پیدا نشد."), payment_control_center_screen([]).keyboard)
            changes = (
                {"enabled": not bool(policy.get("enabled"))}
                if name.startswith("term-subscription-toggle:")
                else {"mode": "open" if str(policy.get("mode")) == "subscription" else "subscription"}
                if name.startswith("term-subscription-mode:")
                else {"renewalRemindersEnabled": not bool(policy.get("renewalRemindersEnabled"))}
            )
            updated = self.state.update_term_access_policy(
                term, changes, actor_user_id=user_id, actor_platform=self.platform, note="owner settings change"
            )
            return term_subscription_settings_screen(updated)
        if name.startswith("term-subscription-grant:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            self.state.start_dialog(user_id, "term-subscription-grant-search", "query", {"term": term})
            return Screen(
                f"<b>🔎 اعطای دسترسی رایگان ترم {to_persian_digits(term)}</b>\n\nبخشی از نام یا شماره دانشجویی را بفرست. انتخاب فقط از حساب‌های canonical سایت انجام می‌شود.",
                keyboard([button("انصراف", action=f"term-subscription-admin:{term}")]),
            )
        if name.startswith("term-subscription-grant-select:") and user_id == self.owner_id:
            parts = name.split(":")
            dialog = self.state.dialog(user_id)
            if len(parts) != 3 or not parts[1].isdigit() or not parts[2].isdigit() or not dialog or dialog.get("kind") != "term-subscription-grant-search":
                return Screen(frame_error("جلسهٔ انتخاب دانشجو منقضی شده است."), payment_control_center_screen([]).keyboard)
            term, student = int(parts[1]), parts[2]
            candidates = [item for item in dict(dialog.get("payload") or {}).get("candidates", []) if isinstance(item, dict)]
            candidate = next((item for item in candidates if str(item.get("studentNumber") or "") == student), None)
            if candidate is None:
                return Screen(frame_error("دانشجو در نتیجهٔ canonical پیدا نشد."), keyboard([button("بازگشت", action=f"term-subscription-grant:{term}")]))
            payload = {"term": term, "studentNumber": student, "displayName": str(candidate.get("name") or "")}
            self.state.start_dialog(user_id, "term-subscription-grant-note", "note", payload)
            return Screen(
                f"<b>🎁 تأیید دسترسی رایگان</b>\n\n👤 {html.escape(payload['displayName'] or 'دانشجو')}\n"
                f"🎓 <code>{to_persian_digits(student)}</code>\n\nیادداشت/دلیل را بفرست؛ اگر لازم نیست فقط <code>-</code> بفرست.",
                keyboard([button("انصراف", action=f"term-subscription-admin:{term}")]),
            )
        if name.startswith("term-subscription-free:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            return complimentary_access_list_screen(self.state.complimentary_term_access(term), term=term)
        if name.startswith("term-subscription-revoke:") and user_id == self.owner_id:
            entitlement_id = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 0
            item = self.state.complimentary_term_access_by_id(entitlement_id)
            if item is None:
                return Screen(frame_error("دسترسی رایگان فعال پیدا نشد."), complimentary_access_list_screen([], term=7).keyboard)
            term = int(item.get("term") or 7)
            self.state.start_dialog(user_id, "term-subscription-revoke-note", "note", {"term": term, "entitlementId": entitlement_id})
            return Screen(
                f"<b>❌ لغو دسترسی رایگان</b>\n\n👤 {html.escape(str(item.get('displayName') or 'دانشجو'))}\n"
                f"🎓 <code>{to_persian_digits(item.get('studentNumber') or '—')}</code>\n\nدلیل لغو را بنویس؛ اگر لازم نیست <code>-</code> بفرست.",
                keyboard([button("انصراف", action=f"term-subscription-free:{term}")]),
            )
        if name == "payments" or name.startswith("payments-page:"):
            page = int(name.rsplit(":", 1)[1]) if name.startswith("payments-page:") and name.rsplit(":", 1)[1].isdigit() else 0
            try:
                offers = self._eligible_products(user_id)
                states = self._payment_product_states(user_id, offers)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), self._screen("home", user_id).keyboard)
            return payment_offers_screen(offers, states=states, page=page)
        if name == "admin-payments" and user_id == self.owner_id:
            summary = {}
            if self.site_api is not None and hasattr(self.site_api, "payment_owner_dashboard"):
                try:
                    summary = self.site_api.payment_owner_dashboard(user_id)
                except SiteApiError:
                    summary = {}
            return payment_control_center_screen(self._ordinary_payment_offers(), summary)
        if (name == "payment-products" or name.startswith("payment-products-page:")) and user_id == self.owner_id:
            page = int(name.rsplit(":", 1)[1]) if name.startswith("payment-products-page:") and name.rsplit(":", 1)[1].isdigit() else 0
            return owner_payment_offers_screen(self._ordinary_payment_offers(), page=page)
        if name == "payment-stats" and user_id == self.owner_id:
            return payment_control_center_screen(self._ordinary_payment_offers(), self._owner_payment_summary(user_id))
        if name == "payment-export" and user_id == self.owner_id:
            return Screen(
                "<b>📤 خروجی پرداخت‌ها</b>\n\nخروجی CSV با UTF-8 BOM برای Excel و نسخه متنی کوتاه در دسترس است.",
                keyboard(
                    [button("📤 همه تراکنش‌ها CSV", action="payment-export-file:all:csv", style="success")],
                    [button("📝 خروجی متنی", action="payment-export-text:all")],
                    [button("مرکز پرداخت‌ها", action="admin-payments")],
                ),
            )
        if name == "payment-reminders" and user_id == self.owner_id:
            return Screen(
                "<b>🔔 یادآوری پرداخت</b>\n\nیادآوری فقط از صفحه آمار محصولِ دارای مخاطب محدود ساخته می‌شود: ابتدا فهرست پرداخت‌نکرده‌ها، سپس پیش‌نمایش و تأیید صریح. ارسال تکراری ۲۴ ساعته مسدود است.",
                keyboard([button("📦 انتخاب محصول", action="payment-products")], [button("مرکز پرداخت‌ها", action="admin-payments")]),
            )
        if name == "payment-settings" and user_id == self.owner_id:
            return Screen(
                "<b>⚙️ تنظیمات پرداخت</b>\n\nواحد canonical: <b>ریال</b>؛ نمایش ربات: <b>تومان</b>.\n"
                "درگاه، callback و reconciliation از تنظیم فعلی سایت استفاده می‌کنند و از ربات قابل تغییر نیستند.",
                keyboard([button("📦 مدیریت محصولات", action="payment-products")], [button("مرکز پرداخت‌ها", action="admin-payments")]),
            )
        if (name == "payment-transactions" or name.startswith("payment-transactions-page:")) and user_id == self.owner_id:
            page = int(name.rsplit(":", 1)[1]) if name.startswith("payment-transactions-page:") and name.rsplit(":", 1)[1].isdigit() else 0
            try:
                filters = self._payment_transaction_filter_state(user_id)
                return payment_transactions_screen(
                    self._payment_transactions_payload(user_id, page=page, limit=10), page=page, filters=filters
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
        if name == "payment-transaction-filters" and user_id == self.owner_id:
            return payment_transaction_filters_screen(
                self._payment_transaction_filter_state(user_id), self._ordinary_payment_offers()
            )
        if name == "payment-tx-clear" and user_id == self.owner_id:
            self._payment_filters.pop(user_id, None)
            try:
                return payment_transactions_screen(self._payment_transactions_payload(user_id), filters={})
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_control_center_screen([]).keyboard)
        if name.startswith(("payment-tx-status:", "payment-tx-platform:", "payment-tx-gateway:")) and user_id == self.owner_id:
            prefix, value = name.rsplit(":", 1)
            key = {"payment-tx-status": "status", "payment-tx-platform": "platform", "payment-tx-gateway": "gateway"}.get(prefix, "")
            allowed = {
                "status": {"success", "pending", "failed", "canceled", "expired"},
                "platform": {"telegram", "bale"},
                "gateway": {"zibal", "zarinpal", "mock"},
            }
            if not key or value not in allowed[key]:
                return Screen(frame_error("فیلتر معتبر نیست."), payment_transaction_filters_screen({}, []).keyboard)
            filters = self._payment_transaction_filter_state(user_id)
            filters[key] = value
            self._payment_filters[user_id] = filters
            return payment_transaction_filters_screen(filters, self._ordinary_payment_offers())
        if name.startswith("payment-tx-period:") and user_id == self.owner_id:
            period = name.rsplit(":", 1)[1]
            filters = self._payment_transaction_filter_state(user_id)
            if period == "custom":
                self.state.start_dialog(user_id, "payment-transaction-date", "value", filters)
                return Screen(
                    "<b>🗓 بازه سفارشی</b>\n\nتاریخ شروع و پایان را میلادی و با این قالب بفرست:\n<code>2026-08-01 | 2026-08-31</code>",
                    keyboard([button("انصراف", action="payment-transaction-filters")]),
                )
            date_from, date_to = self._payment_period_bounds(period)
            if not date_from:
                return Screen(frame_error("بازه زمانی معتبر نیست."), payment_transaction_filters_screen(filters, []).keyboard)
            filters["dateFrom"], filters["dateTo"] = date_from, date_to
            self._payment_filters[user_id] = filters
            return payment_transaction_filters_screen(filters, self._ordinary_payment_offers())
        if name == "payment-tx-product" and user_id == self.owner_id:
            return payment_transaction_filters_screen(
                self._payment_transaction_filter_state(user_id), self._ordinary_payment_offers()
            )
        if name.startswith("payment-tx-product:") and user_id == self.owner_id:
            offer_ref = name.split(":", 1)[1]
            if self._ordinary_payment_offer(offer_ref) is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_transaction_filters_screen({}, []).keyboard)
            filters = self._payment_transaction_filter_state(user_id)
            filters["offerRef"] = offer_ref
            self._payment_filters[user_id] = filters
            return payment_transaction_filters_screen(filters, self._ordinary_payment_offers())
        if name.startswith("payment-transaction-status:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3 or not parts[1].isdigit() or parts[2] not in {"pending", "failed", "canceled", "expired"}:
                return Screen(frame_error("تغییر وضعیت معتبر نیست."), payment_transactions_screen({"items": []}).keyboard)
            self.state.start_dialog(user_id, "payment-transaction-status", "note", {"orderId": int(parts[1]), "status": parts[2]})
            return Screen(
                "<b>✍️ دلیل تغییر وضعیت</b>\n\nاین تغییر دستی است و به‌عنوان تأیید درگاه ثبت نمی‌شود. دلیل کوتاه را بفرست.",
                keyboard([button("انصراف", action=f"payment-transaction:{parts[1]}")]),
            )
        if name.startswith("payment-transaction:") and user_id == self.owner_id:
            order_id = name.rsplit(":", 1)[1]
            if not order_id.isdigit() or self.site_api is None or not hasattr(self.site_api, "payment_transaction"):
                return Screen(frame_error("سفارش پیدا نشد."), payment_transactions_screen({"items": []}).keyboard)
            try:
                return payment_transaction_detail_screen(self.site_api.payment_transaction(user_id, order_id=int(order_id)))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_transactions_screen({"items": []}).keyboard)
        if name == "payment-search" and user_id == self.owner_id:
            self.state.start_dialog(user_id, "payment-search", "query", {})
            return Screen("<b>🔎 جستجوی تراکنش</b>\n\nنام، شماره دانشجویی، کد پیگیری، شناسه سفارش یا نام محصول را بفرست.", payment_transactions_screen({"items": []}).keyboard)
        if name == "payment-audiences" and user_id == self.owner_id:
            lists = self.state.saved_payment_audiences()
            lines = ["<b>👥 مخاطبان ذخیره‌شده</b>", ""]
            for entry in lists[:20]:
                lines.append(f"• <b>{html.escape(str(entry.get('name') or 'فهرست'))}</b> · {to_persian_digits(len(entry.get('studentNumbers') or []))} نفر")
            if not lists:
                lines.append("هنوز فهرستی ساخته نشده است.")
            return Screen(
                "\n".join(lines),
                keyboard(
                    [button("➕ فهرست جدید", action="payment-audience-new", style="success")],
                    [button("🔎 جستجو و انتخاب افراد", action="payment-audience-search", style="primary")],
                    [button("مرکز پرداخت‌ها", action="admin-payments")],
                ),
            )
        if name == "payment-audience-new" and user_id == self.owner_id:
            self.state.start_dialog(user_id, "payment-audience-new", "value", {})
            return Screen(
                "<b>➕ فهرست مخاطب</b>\n\nدر یک پیام بفرست:\n<code>نام فهرست | شماره۱، شماره۲، شماره۳</code>",
                keyboard([button("انصراف", action="payment-audiences")]),
            )
        if name in {"payment-audience-search", "payment-audience-search-more"} and user_id == self.owner_id:
            existing = self.state.dialog(user_id)
            payload = dict(existing.get("payload") or {}) if existing and existing.get("kind") == "payment-audience-search" else {"selected": []}
            self.state.start_dialog(user_id, "payment-audience-search", "query", payload)
            return Screen(
                "<b>🔎 جستجوی مخاطب</b>\n\nبخشی از نام یا شماره دانشجویی را بفرست. نتیجه از حساب‌های canonical سایت خوانده می‌شود.",
                keyboard([button("انصراف", action="payment-audiences")]),
            )
        if name.startswith("payment-audience-select:") and user_id == self.owner_id:
            student = name.rsplit(":", 1)[1]
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-audience-search" or not student.isdigit():
                return Screen(frame_error("جلسه انتخاب مخاطب منقضی شده است."), self._dynamic_screen("payment-audiences", user_id).keyboard)
            payload = dict(dialog.get("payload") or {})
            selected = [str(value) for value in payload.get("selected", []) if str(value).isdigit()]
            if student in selected:
                selected.remove(student)
            else:
                selected.append(student)
            payload["selected"] = selected[:500]
            self.state.update_dialog(user_id, step="results", payload=payload)
            return self._payment_audience_search_results(payload)
        if name == "payment-audience-selection-save" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            selected = list(dict(dialog.get("payload") or {}).get("selected") or []) if dialog and dialog.get("kind") == "payment-audience-search" else []
            if not selected:
                return Screen(frame_error("حداقل یک نفر را انتخاب کن."), self._dynamic_screen("payment-audiences", user_id).keyboard)
            payload = dict(dialog.get("payload") or {})
            self.state.update_dialog(user_id, step="name", payload=payload)
            return Screen("<b>💾 ذخیره فهرست</b>\n\nیک نام کوتاه برای این فهرست بفرست.", keyboard([button("انصراف", action="payment-audiences")]))
        if name.startswith("payment-export-text:") and user_id == self.owner_id:
            parts = name.split(":")
            scope = parts[1] if len(parts) > 1 else "all"
            try:
                rows = self._payment_export_rows(user_id, scope, "transactions")
                lines = ["<b>📝 خروجی متنی پرداخت‌ها</b>", ""]
                for index, row in enumerate(rows[:25], 1):
                    lines.append(
                        f"{to_persian_digits(index)}. {html.escape(str(row.get('payerName') or '—'))} · "
                        f"{html.escape(str(row.get('title') or 'محصول'))} · <code>{html.escape(format_rials(row.get('amountRials')))}</code> · "
                        f"{html.escape(str(row.get('statusLabel') or row.get('status') or ''))}"
                    )
                if len(rows) > 25:
                    lines.append("\nبرای ادامه، فایل CSV را بگیر.")
                return Screen("\n".join(lines), keyboard([button("📤 CSV", action=f"payment-export-file:{scope}:csv")], [button("مرکز پرداخت‌ها", action="admin-payments")]))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-export:") and user_id == self.owner_id:
            name = "payment-offer-stats:" + name.rsplit(":", 1)[1]
        if name.startswith("payment-product-copy:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            share_url = bot_start_url(self.bot_username, f"product_{str(item.get('shareToken') or '')}", platform=self.platform)
            return Screen(
                f"<b>📋 اطلاعات قابل کپی</b>\n\n<b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code>\n"
                f"{html.escape(str(item.get('description') or ''))}\n\n<code>{html.escape(share_url)}</code>",
                keyboard([button("بازگشت به محصول", action=f"payment-offer:{offer_ref}")]),
            )
        if name.startswith("payment-product-share-preview:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            try:
                report = self._payment_people_report(user_id, item)
                recipients = [person for person in report.get("targets", []) if str(person.get("platformUserId") or "").isdigit()][:50]
                if not recipients:
                    return Screen(
                        frame_error("برای مخاطبان این محصول، حساب متصل و قابل ارسال در همین پلتفرم پیدا نشد."),
                        payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard,
                    )
                preview = self.state.create_payment_reminder_preview(
                    offer_ref, self.platform,
                    [str(person.get("studentNumber") or "") for person in recipients], purpose="product-share",
                )
                if preview.get("duplicate"):
                    return Screen(
                        frame_error("همین محصول در ۲۴ ساعت اخیر برای همین مخاطبان ارسال شده یا در حال ارسال است."),
                        payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard,
                    )
                return Screen(
                    f"<b>📤 پیش‌نمایش ارسال محصول</b>\n\nمحصول: <b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"گیرنده قابل دسترس در {('بله' if self.platform == 'bale' else 'تلگرام')}: <b>{to_persian_digits(min(50, len(recipients)))}</b>\n\n"
                    "بدون تأیید زیر هیچ پیامی ارسال نمی‌شود؛ هر اجرا حداکثر ۵۰ گیرنده دارد.",
                    keyboard(
                        [button("تأیید و ارسال", action=f"pps:{offer_ref}:{preview['ref']}", style="danger")],
                        [button("انصراف", action=f"payment-offer:{offer_ref}")],
                    ),
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name.startswith("payment-reminder-preview:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            try:
                report = self._payment_people_report(user_id, item)
                recipients = [person for person in report.get("unpaid", []) if str(person.get("platformUserId") or "").isdigit()][:50]
                if not recipients:
                    return Screen(
                        frame_error("دانشجوی پرداخت‌نکرده با حساب متصل و قابل ارسال در همین پلتفرم پیدا نشد."),
                        payment_product_report_screen(item, report).keyboard,
                    )
                preview = self.state.create_payment_reminder_preview(offer_ref, self.platform, [str(person.get("studentNumber") or "") for person in recipients])
                if preview.get("duplicate"):
                    return Screen(frame_error("همین یادآوری در ۲۴ ساعت اخیر ارسال شده یا در حال ارسال است."), payment_product_report_screen(item, report).keyboard)
                return Screen(
                    f"<b>🔔 پیش‌نمایش یادآوری</b>\n\nمحصول: <b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"گیرنده قابل دسترس در {('بله' if self.platform == 'bale' else 'تلگرام')}: <b>{to_persian_digits(min(50, len(recipients)))}</b>\n\nبدون تأیید زیر هیچ پیامی ارسال نمی‌شود؛ هر اجرا حداکثر ۵۰ گیرنده دارد.",
                    keyboard(
                        [button("تأیید و ارسال", action=f"prs:{offer_ref}:{preview['ref']}", style="danger")],
                        [button("انصراف", action=f"payment-offer-stats:{offer_ref}")],
                    ),
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name in {"identity-claim", "identity-claim-cancel"}:
            self.state.clear_dialog(user_id)
            return Screen(
                frame_error("تأیید دستی هویت بازنشسته شده است؛ یکی از دو مسیر امن زیر را انتخاب کن."),
                class_auth_screen().keyboard,
            )
        if name == "class-auth":
            self.state.start_dialog(user_id, "class-auth-v1", "method", {})
            return class_auth_screen()
        if name == "profile-edit-cancel":
            self.state.clear_dialog(user_id)
            name = "account"
        if name == "profile-edit":
            return profile_edit_fields_screen()
        if name.startswith("profile-edit-field:"):
            field = name.split(":", 1)[1]
            labels = {
                "firstName": "نام", "lastName": "نام خانوادگی", "major": "رشته",
                "institution": "دانشگاه", "province": "استان دانشگاه",
                "entryYear": "سال ورود", "admissionType": "نیمسال و نوع پذیرش",
                "studentNumber": "شماره دانشجویی",
            }
            if field not in labels:
                return Screen(frame_error("بخش انتخاب‌شده معتبر نیست."), profile_edit_fields_screen().keyboard)
            self.state.start_dialog(user_id, "profile-edit-v1", "value", {"field": field, "fieldLabel": labels[field]})
            return profile_edit_prompt_screen(labels[field])
        if name == "identity-mapping-remove-cancel" and user_id == self.owner_id:
            self.state.clear_dialog(user_id)
            if self.site_api is None:
                return self._screen("admin", user_id)
            try:
                return owner_identity_mappings_screen(self.site_api.identity_mappings(user_id), platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name in {"identity-mapping-new", "identity-mapping-save", "identity-mapping-cancel", "identity-claims"}:
            self.state.clear_dialog(user_id)
            return Screen(
                frame_error("اتصال و تأیید دستی هویت بازنشسته شده است؛ اتصال تازه فقط با OTP یا ورود امن سایت ساخته می‌شود."),
                home(self.site_url, is_owner=user_id == self.owner_id).keyboard,
            )
        if name == "payment-offer-new" and user_id == self.owner_id:
            dialog = self.state.start_dialog(user_id, "payment-offer", "title")
            return payment_offer_wizard_screen("title", dialog["payload"])
        if name == "payment-offer-cancel" and user_id == self.owner_id:
            self.state.clear_dialog(user_id)
            return owner_payment_offers_screen(self._ordinary_payment_offers())
        if name.startswith("payment-offer-amount:") and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer" or dialog.get("step") != "amount":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            amount_tomans = self._parse_tomans(name.rsplit(":", 1)[1])
            payload = dict(dialog.get("payload") or {})
            payload["amountRials"] = amount_tomans * 10
            self.state.update_dialog(user_id, step="audience", payload=payload)
            return payment_offer_wizard_screen("audience", payload)
        if name == "payment-offer-custom-amount" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            payload = dict(dialog.get("payload") or {})
            self.state.update_dialog(user_id, step="custom-amount", payload=payload)
            return payment_offer_wizard_screen("custom-amount", payload)
        if name == "payment-offer-no-description" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            payload = dict(dialog.get("payload") or {})
            payload["description"] = ""
            self.state.update_dialog(user_id, step="preview", payload=payload)
            return payment_offer_preview_screen(payload)
        if name == "payment-offer-description" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            payload = dict(dialog.get("payload") or {})
            self.state.update_dialog(user_id, step="description", payload=payload)
            return payment_offer_wizard_screen("description", payload)
        if name.startswith("payment-offer-audience:") and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            selection = name.rsplit(":", 1)[1]
            audience = {
                "all": {"mode": "all"},
                "open": {"mode": "open"},
                "primary": {"mode": "cohorts", "cohorts": ["dentistry-1402"]},
            }.get(selection)
            if audience is None:
                return Screen(
                    "<b>👥 مخاطب پیشرفته</b>\n\nبرای انتخاب فردی و فهرست ذخیره‌شده، ابتدا از بخش «مخاطبان» فهرست بساز؛ سپس در ویرایش محصول انتخابش کن.",
                    payment_offer_wizard_screen("audience", dict(dialog.get("payload") or {})).keyboard,
                )
            payload = dict(dialog.get("payload") or {})
            payload["audience"] = audience
            payload.setdefault("description", "")
            self.state.update_dialog(user_id, step="preview", payload=payload)
            return payment_offer_preview_screen(payload)
        if name == "payment-offer-publish" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            payload = dict(dialog.get("payload") or {}) if dialog else {}
            if not dialog or dialog.get("kind") != "payment-offer" or not payload.get("title") or not payload.get("amountRials"):
                return Screen(frame_error("اطلاعات محصول کامل نیست."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            try:
                item = self.state.create_payment_offer(
                    str(payload["title"]), int(payload["amountRials"]), str(payload.get("description") or ""),
                    audience=dict(payload.get("audience") or {"mode": "all"}),
                    actor_user_id=user_id, actor_platform=self.platform,
                )
            except (TypeError, ValueError):
                return Screen(frame_error("اطلاعات محصول معتبر نیست."), payment_offer_preview_screen(payload).keyboard)
            self.state.clear_dialog(user_id)
            return payment_offer_saved_screen(item, bot_username=self.bot_username, platform=self.platform)
        if name.startswith("payment-offer-delete-confirm:") and user_id == self.owner_id:
            item = self._ordinary_payment_offer(name.rsplit(":", 1)[1])
            return payment_offer_delete_confirmation(item) if item else Screen(frame_error("محصول پیدا نشد."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
        if name.startswith("payment-offer-delete:") and user_id == self.owner_id:
            self.state.set_payment_offer_status(name.rsplit(":", 1)[1], "archived", actor_user_id=user_id, actor_platform=self.platform)
            return owner_payment_offers_screen(self._ordinary_payment_offers())
        if name.startswith("payment-offer:") and user_id == self.owner_id:
            item = self._ordinary_payment_offer(name.split(":", 1)[1])
            return payment_offer_admin_detail_screen(
                item,
                bot_username=self.bot_username,
                platform=self.platform,
            ) if item else Screen(frame_error("محصول پیدا نشد."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
        if name.startswith("payment-offer-status:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3 or parts[2] not in {"active", "paused"}:
                return Screen(frame_error("درخواست تغییر وضعیت معتبر نیست."), home(self.site_url, is_owner=True).keyboard)
            self.state.set_payment_offer_status(parts[1], parts[2], actor_user_id=user_id, actor_platform=self.platform)
            return owner_payment_offers_screen(self._ordinary_payment_offers())
        if name.startswith("payment-offer-duplicate:") and user_id == self.owner_id:
            item = self.state.duplicate_payment_offer(name.rsplit(":", 1)[1], actor_user_id=user_id, actor_platform=self.platform)
            return payment_offer_saved_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-rotate:") and user_id == self.owner_id:
            item = self.state.rotate_payment_share_token(name.rsplit(":", 1)[1], actor_user_id=user_id, actor_platform=self.platform)
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-edit:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            return Screen(
                "<b>✏️ ویرایش محصول</b>\n\nفیلد موردنظر را انتخاب کن؛ تغییر پس از ثبت، نسخه محصول را افزایش می‌دهد و سفارش‌های قبلی را عوض نمی‌کند.",
                keyboard(
                    [button("عنوان", action=f"payment-offer-field:{offer_ref}:title"), button("مبلغ", action=f"payment-offer-field:{offer_ref}:amountRials")],
                    [button("توضیح", action=f"payment-offer-field:{offer_ref}:description")],
                    [button("بازگشت", action=f"payment-offer:{offer_ref}")],
                ),
            ) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-schedule:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            return Screen(
                "<b>🗓 زمان‌بندی محصول</b>\n\nزمان را ISO همراه timezone بفرست؛ نمونه: <code>2026-09-01T12:00:00+03:30</code>",
                keyboard(
                    [button("زمان شروع", action=f"payment-offer-field:{offer_ref}:availableFrom"), button("مهلت پایان", action=f"payment-offer-field:{offer_ref}:expiresAt")],
                    [button("حذف زمان‌بندی", action=f"payment-offer-clear-window:{offer_ref}")],
                    [button("بازگشت", action=f"payment-offer:{offer_ref}")],
                ),
            )
        if name.startswith("payment-offer-advanced:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            return Screen(
                "<b>⚙️ تنظیمات پیشرفته</b>\n\nصفر برای ظرفیت یا سقف خرید یعنی نامحدود.",
                keyboard(
                    [button("ظرفیت", action=f"payment-offer-field:{offer_ref}:capacity"), button("سقف خرید هر نفر", action=f"payment-offer-field:{offer_ref}:maxPurchasesPerUser")],
                    [button("متن تحویل پس از خرید", action=f"payment-offer-field:{offer_ref}:fulfillmentText")],
                    [button("لینک تحویل HTTPS", action=f"payment-offer-field:{offer_ref}:fulfillmentUrl")],
                    [button("بازگشت", action=f"payment-offer:{offer_ref}")],
                ),
            )
        if name.startswith("payment-offer-audience-edit:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            rows = [
                [button("همه احرازشده‌ها", action=f"payment-offer-audience-set:{offer_ref}:all")],
                [button("ورودی ۱۴۰۲ تهران", action=f"payment-offer-audience-set:{offer_ref}:primary")],
                [button("فقط دارندگان لینک", action=f"payment-offer-audience-set:{offer_ref}:open")],
                [button("شماره‌های دانشجویی", action=f"payment-offer-field:{offer_ref}:audienceUsers")],
                [button("یک یا چند cohort", action=f"payment-offer-field:{offer_ref}:audienceCohorts")],
            ]
            for saved in self.state.saved_payment_audiences()[:6]:
                rows.append([button(f"فهرست · {str(saved.get('name') or '')[:24]}", action=f"payment-offer-audience-list:{offer_ref}:{saved.get('ref', '')}")])
            rows.append([button("بازگشت", action=f"payment-offer:{offer_ref}")])
            return Screen(
                "<b>👥 مخاطب محصول</b>\n\nانتخاب جدید بلافاصله روی مشاهده و خریدهای بعدی اعمال می‌شود.",
                keyboard(*rows),
            )
        if name.startswith("payment-offer-audience-list:") and user_id == self.owner_id:
            parts = name.split(":")
            item = self.state.update_payment_offer(parts[1], {"audience": {"mode": "lists", "listRefs": [parts[2]]}}, actor_user_id=user_id, actor_platform=self.platform) if len(parts) == 3 else None
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("فهرست یا محصول معتبر نیست."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-audience-set:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3:
                return Screen(frame_error("مخاطب معتبر نیست."), payment_control_center_screen([]).keyboard)
            audience = {"all": {"mode": "all"}, "primary": {"mode": "cohorts", "cohorts": ["dentistry-1402"]}, "open": {"mode": "open"}}.get(parts[2])
            item = self.state.update_payment_offer(parts[1], {"audience": audience}, actor_user_id=user_id, actor_platform=self.platform) if audience else None
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-clear-window:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self.state.update_payment_offer(offer_ref, {"availableFrom": "", "expiresAt": ""}, actor_user_id=user_id, actor_platform=self.platform)
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-field:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3 or parts[2] not in {"title", "description", "amountRials", "availableFrom", "expiresAt", "capacity", "maxPurchasesPerUser", "audienceUsers", "audienceCohorts", "fulfillmentText", "fulfillmentUrl"}:
                return Screen(frame_error("فیلد ویرایش معتبر نیست."), payment_control_center_screen([]).keyboard)
            self.state.start_dialog(user_id, "payment-offer-edit", "value", {"offerRef": parts[1], "field": parts[2]})
            return Screen("<b>✏️ مقدار جدید</b>\n\nمقدار را در یک پیام بفرست. برای خالی‌کردن توضیح یا زمان، یک خط تیره بفرست.", keyboard([button("انصراف", action=f"payment-offer:{parts[1]}")]))
        if name.startswith("payment-offer-stats:") and user_id == self.owner_id:
            parts = name.split(":")
            offer_ref = parts[1] if len(parts) >= 2 else ""
            period = parts[2] if len(parts) >= 3 and parts[2] in {"today", "7", "30", "all"} else "all"
            item = self._ordinary_payment_offer(offer_ref)
            if item is None or self.site_api is None or not hasattr(self.site_api, "payment_product_report"):
                return Screen(frame_error("گزارش این محصول در دسترس نیست."), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            try:
                date_from, date_to = self._payment_period_bounds(period)
                report = self._payment_people_report(user_id, item, date_from=date_from, date_to=date_to)
                return payment_product_report_screen(item, report, period=period)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name.startswith(("payment-offer-payers:", "payment-offer-unpaid:")) and user_id == self.owner_id:
            kind = "paid" if name.startswith("payment-offer-payers:") else "unpaid"
            parts = name.split(":")
            offer_ref = parts[1] if len(parts) >= 2 else ""
            page = int(parts[2]) if len(parts) >= 3 and parts[2].isdigit() else 0
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            try:
                report = self._payment_people_report(user_id, item)
                people = list(report.get("payers" if kind == "paid" else "unpaid") or [])
                return payment_people_screen(
                    "✅ پرداخت‌کنندگان" if kind == "paid" else "❌ پرداخت‌نکرده‌ها",
                    people, offer_ref=offer_ref, kind=kind, page=page,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name.startswith("payment-confirm:"):
            account = self._account_snapshot(user_id)
            item = self.state.payment_offer_for_user(name.split(":", 1)[1], identity_from_account(account))
            if item is None:
                return Screen(frame_error("این محصول در دسترس این حساب نیست یا اعتبارش پایان یافته است."), self._screen("home", user_id).keyboard)
            states = self._payment_product_states(user_id, [item])
            return payment_confirm_screen(item, state=dict(states.get(str(item.get("ref") or "")) or {}))
        if name == "student-assistant" and not self.student_assistant_v1_enabled:
            return self._screen("home", user_id)
        if name in {"exams", "exam-owner"} and not self.exams_v1_enabled:
            return self._screen("exams" if name == "exams" else "admin", user_id)
        if self.site_api is None:
            return self._screen(name, user_id)
        if name == "exams" and self.exams_v1_enabled:
            try:
                return exam_screen(
                    self.site_api.exam_hub(user_id),
                    site_url=self.site_url,
                    is_owner=user_id == self.owner_id,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "exam-owner" and self.exams_v1_enabled:
            if user_id != self.owner_id:
                return self._screen("home", user_id)
            try:
                return exam_screen(
                    self.site_api.exam_owner_hub(user_id),
                    site_url=self.site_url,
                    is_owner=True,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith("exam-action:") and self.exams_v1_enabled:
            action_ref = name.split(":", 1)[1]
            if not re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref):
                return Screen(frame_error("این عملیات آزمون منقضی یا نامعتبر است."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            stable_request_id = hashlib.sha256(request_id.encode("utf-8")).hexdigest()
            try:
                return exam_screen(
                    self.site_api.perform_exam_action(
                        user_id,
                        action_ref=action_ref,
                        request_id=stable_request_id,
                    ),
                    site_url=self.site_url,
                    is_owner=user_id == self.owner_id,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "student-assistant" and self.student_assistant_v1_enabled:
            try:
                return student_assistant_screen(
                    self.site_api.student_assistant_summary(user_id),
                    is_owner=user_id == self.owner_id,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), self._screen("home", user_id).keyboard)
        if name.startswith("assistant-action:") and self.student_assistant_v1_enabled:
            action_ref = name.split(":", 1)[1]
            if re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref) is None:
                return Screen(frame_error("این عملیات منقضی یا نامعتبر است."), self._screen("home", user_id).keyboard)
            stable_request_id = hashlib.sha256(request_id.encode("utf-8")).hexdigest()
            try:
                result = self.site_api.perform_integration_action(
                    user_id,
                    action_ref=action_ref,
                    request_id=stable_request_id,
                )
                if str(result.get("status") or "") == "challenge":
                    send_private_challenge(api=self.api, state=self.state, user_id=user_id, payload=result)
                    return integration_challenge_waiting_screen(result)
                return student_assistant_screen(result, is_owner=user_id == self.owner_id)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), self._screen("home", user_id).keyboard)
            except BotApiError:
                return Screen(
                    frame_error("تصویر کپچا معتبر نبود یا در پیام‌رسان ارسال نشد."),
                    self._screen("student-assistant", user_id).keyboard,
                )
        if name in {"account", "check-link", "link-required"}:
            try:
                account = self.site_api.account(user_id)
                return account_screen(
                    self.site_url,
                    platform=self.platform,
                    linked_user=(
                        dict(account.get("user") or {})
                        if account.get("linked") and account.get("authComplete")
                        else None
                    ),
                    identity_state=dict(account.get("identity") or {}),
                    onboarding_profile=dict(account.get("onboardingProfile") or {}) if isinstance(account.get("onboardingProfile"), dict) else None,
                    booklet_profile=dict(account.get("bookletProfile") or {}) if isinstance(account.get("bookletProfile"), dict) else None,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "link-account":
            try:
                result = self.site_api.start_link(
                    user_id,
                    platform_profile=self._platform_profile(dict(sender or {"id": user_id})),
                )
                return account_screen(
                    self.site_url,
                    platform=self.platform,
                    linked_user=(
                        dict(result.get("user") or {})
                        if result.get("alreadyLinked") and result.get("authComplete")
                        else None
                    ),
                    link_url=str(result.get("linkUrl") or ""),
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "grades":
            try:
                return grades_screen(self.site_url, self.site_api.grades(user_id), platform=self.platform)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "navid" and user_id == self.owner_id:
            try:
                return navid_screen(self.site_api.navid_status(user_id), platform=self.platform, site_url=self.site_url)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name == "profile-edit-requests" and user_id == self.owner_id:
            try:
                return profile_edit_requests_screen(self.site_api.profile_edit_requests(user_id))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith(("profile-edit-approve:", "profile-edit-reject:")) and user_id == self.owner_id:
            decision = "approve" if name.startswith("profile-edit-approve:") else "reject"
            request_ref = name.rsplit(":", 1)[1]
            try:
                self.site_api.resolve_profile_edit(user_id, request_ref=request_ref, decision=decision)
                payload = self.site_api.profile_edit_requests(user_id)
                status = "تأیید و اعمال" if decision == "approve" else "رد"
                screen = profile_edit_requests_screen(payload)
                return Screen(f"<b>✅ درخواست {status} شد</b>\n\n" + screen.text, screen.keyboard)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name == "identity-mappings" and user_id == self.owner_id:
            try:
                return owner_identity_mappings_screen(self.site_api.identity_mappings(user_id), platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith("identity-mapping-remove:") and user_id == self.owner_id:
            mapping_ref = name.rsplit(":", 1)[1]
            try:
                payload = self.site_api.identity_mappings(user_id)
                item = next((entry for entry in payload.get("mappings", []) if str(entry.get("ref") or "") == mapping_ref), None)
                if not isinstance(item, dict):
                    return Screen(frame_error("اتصال موردنظر پیدا نشد."), owner_identity_mappings_screen(payload, platform=self.platform).keyboard)
                self.state.start_dialog(user_id, "identity-mapping-remove", "reason", {"mappingRef": mapping_ref, "item": item})
                return identity_mapping_remove_screen(item, platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name == "identity-mapping-remove-confirm" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            payload = dict(dialog.get("payload") or {}) if dialog else {}
            if not dialog or dialog.get("kind") != "identity-mapping-remove" or dialog.get("step") != "preview":
                return Screen(frame_error("فرایند حذف اتصال منقضی شده است."), home(self.site_url, is_owner=True).keyboard)
            try:
                self.site_api.delete_identity_mapping(
                    user_id,
                    mapping_ref=str(payload.get("mappingRef") or ""),
                    reason=str(payload.get("reason") or ""),
                )
                self.state.clear_dialog(user_id)
                return owner_identity_mappings_screen(self.site_api.identity_mappings(user_id), platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), identity_mapping_remove_confirmation(payload, platform=self.platform).keyboard)
        if (name.startswith("identity-approve:") or name.startswith("identity-reject:")) and user_id == self.owner_id:
            return Screen(
                frame_error("این دکمه متعلق به سامانهٔ قدیمی است و دیگر هیچ هویتی را تأیید یا رد نمی‌کند."),
                home(self.site_url, is_owner=True).keyboard,
            )
        if name == "notifications":
            try:
                payload = self.site_api.notifications(user_id, limit=20)
                items = [item for item in dict(payload.get("data") or {}).get("items", []) if isinstance(item, dict)]
                refs = {
                    str(item.get("id") or ""): self.state.remember_notification(str(item.get("id") or ""))
                    for item in items
                    if str(item.get("id") or "")
                }
                return notification_list_screen(
                    payload,
                    refs,
                    platform=self.platform,
                    site_url=self.site_url,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("notification-action:"):
            parts = name.split(":", 2)
            if len(parts) != 3:
                return Screen(frame_error("عملیات اعلان معتبر نیست."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            ref, action_ref = parts[1], parts[2]
            notification_id = self.state.notification_id(ref)
            if not notification_id or not re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref):
                return Screen(frame_error("این عملیات دیگر در دسترس نیست."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            try:
                result = self.site_api.perform_notification_action(user_id, notification_id, action_ref)
                payload = self.site_api.notifications(user_id, limit=40)
                items = [item for item in dict(payload.get("data") or {}).get("items", []) if isinstance(item, dict)]
                item = next((entry for entry in items if str(entry.get("id") or "") == notification_id), None)
                if not isinstance(item, dict):
                    raise SiteApiError("اعلان پیدا نشد.", code="NOTIFICATION_NOT_FOUND", status=404)
                screen = notification_detail_screen(
                    item,
                    ref,
                    platform=self.platform,
                    is_owner=user_id == self.owner_id,
                    site_url=self.site_url,
                    show_mark_read=False,
                )
                screen = decorate_academic_notification_screen(screen, item)
                message = html.escape(str(result.get("message") or "انجام شد."))[:240]
                return Screen(f"<b>✅ {message}</b>\n\n{screen.text}", screen.keyboard)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("notification-read:") or name.startswith("notification:"):
            ref = name.rsplit(":", 1)[1]
            notification_id = self.state.notification_id(ref)
            if not notification_id:
                return Screen(frame_error("این اعلان دیگر در دسترس نیست."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            try:
                self.site_api.mark_notification_read(user_id, notification_id)
                payload = self.site_api.notifications(user_id, limit=40)
                items = [item for item in dict(payload.get("data") or {}).get("items", []) if isinstance(item, dict)]
                item = next((entry for entry in items if str(entry.get("id") or "") == notification_id), None)
                if not isinstance(item, dict):
                    raise SiteApiError("اعلان پیدا نشد.", code="NOTIFICATION_NOT_FOUND", status=404)
                screen = notification_detail_screen(
                    item,
                    ref,
                    platform=self.platform,
                    is_owner=user_id == self.owner_id,
                    site_url=self.site_url,
                    show_mark_read=False,
                )
                return decorate_academic_notification_screen(screen, item)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("notification-audience:"):
            if user_id != self.owner_id:
                return self._screen("home", user_id)
            ref = name.rsplit(":", 1)[1]
            notification_id = self.state.notification_id(ref)
            if not notification_id:
                return Screen(frame_error("این اعلان دیگر در دسترس نیست."), home(self.site_url, is_owner=True).keyboard)
            try:
                return notification_audience_screen(
                    self.site_api.notification_audience(user_id, notification_id),
                    ref,
                    platform=self.platform,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith(("payment-create:", "payment-create-link:")):
            via_link = name.startswith("payment-create-link:")
            account = self._account_snapshot(user_id)
            if via_link:
                raw_token = name.split(":", 1)[1]
                candidate = self.state.payment_offer_by_share_token(raw_token)
                offer = self.state.payment_offer_for_user(str(candidate.get("ref") or ""), identity_from_account(account), via_link=True) if candidate else None
            else:
                offer = self.state.payment_offer_for_user(name.split(":", 1)[1], identity_from_account(account))
            if offer is None:
                return Screen(frame_error("این محصول در دسترس این حساب نیست یا اعتبارش پایان یافته است."), self._screen("home", user_id).keyboard)
            stable_request_id = hashlib.sha256(request_id.encode("utf-8")).hexdigest()
            try:
                return payment_created_screen(
                    self.site_api.create_bot_payment(
                        user_id,
                        offer_ref=str(offer["ref"]),
                        title=str(offer["title"]),
                        description=str(offer["description"]),
                        amount_rials=int(offer["amountRials"]),
                        request_id=stable_request_id,
                        product_version=int(offer.get("version") or 1),
                        available_from=str(offer.get("availableFrom") or ""),
                        expires_at=str(offer.get("expiresAt") or ""),
                        capacity=int(offer.get("capacity") or 0),
                        max_per_user=int(offer.get("maxPurchasesPerUser") or 0),
                        fulfillment=dict(offer.get("fulfillment") or {}),
                    ),
                    platform=self.platform,
                    return_to_bot_enabled=self.payment_return_v1_enabled,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("payment-status:"):
            order_token = name.split(":", 1)[1]
            try:
                status_payload = self.site_api.payment_status(user_id, order_token)
                status_payload = self._activate_subscription_from_payment_status(
                    user_id, order_token, dict(status_payload)
                )
                return payment_status_screen(
                    status_payload,
                    platform=self.platform,
                    order_token=order_token,
                    return_to_bot_enabled=self.payment_return_v1_enabled,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        return self._screen(name, user_id)
