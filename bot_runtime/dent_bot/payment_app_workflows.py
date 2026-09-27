from __future__ import annotations

import csv
import hashlib
import html
import tempfile
import time
from datetime import timedelta, timezone
from pathlib import Path

from .api import BotApiError
from .message_frames import frame_error
from .navid import local_now
from .payments import identity_from_account
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .ui import (
    Screen,
    bot_start_url,
    button,
    format_rials,
    keyboard,
    payment_control_center_screen,
)


class PaymentAppWorkflows:
    """Payment/report workflows mixed into DentBotApp without owning app state."""

    def _account_snapshot(self, user_id: int, *, refresh: bool = False) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "account"):
            raise SiteApiError("بررسی امن اتصال حساب در دسترس نیست.", code="SITE_UNAVAILABLE")
        now = time.monotonic()
        cached = self._account_cache.get(int(user_id))
        if not refresh and cached is not None and now - cached[0] < 15:
            return dict(cached[1])
        account = self.site_api.account(user_id)
        self._account_cache[int(user_id)] = (now, dict(account))
        return dict(account)

    def _eligible_products(self, user_id: int, *, via_link: bool = False) -> list[dict]:
        account = self._account_snapshot(user_id)
        identity = identity_from_account(account)
        offers = [
            item for item in self.state.eligible_payment_offers(identity, via_link=via_link)
            if not self._is_term_subscription_offer(item)
        ]
        if not offers or self.site_api is None or not hasattr(self.site_api, "payment_product_states"):
            return offers
        states = self._payment_product_states(user_id, offers)
        result: list[dict] = []
        for offer in offers:
            state = dict(states.get(str(offer.get("ref") or "")) or {})
            max_per_user = max(0, int(offer.get("maxPurchasesPerUser") or 0))
            if max_per_user and int(state.get("successCount") or 0) >= max_per_user:
                # A paid one-time product remains visible in the product page so
                # the student can retrieve the canonical receipt.
                result.append(offer)
                continue
            capacity = max(0, int(offer.get("capacity") or 0))
            if capacity and int(state.get("reservedCount") or 0) >= capacity:
                continue
            result.append(offer)
        return result

    @staticmethod
    def _is_term_subscription_offer(offer: dict | None) -> bool:
        fulfillment = dict((offer or {}).get("fulfillment") or {})
        return str(fulfillment.get("kind") or "") == "term_subscription"

    def _ordinary_payment_offers(self, *, include_inactive: bool = True) -> list[dict]:
        return [
            item for item in self.state.payment_offers(include_inactive=include_inactive)
            if not self._is_term_subscription_offer(item)
        ]

    def _ordinary_payment_offer(self, offer_ref: str) -> dict | None:
        item = self.state.payment_offer(offer_ref, require_active=False)
        return None if self._is_term_subscription_offer(item) else item

    def _payment_product_states(self, user_id: int, offers: list[dict]) -> dict:
        refs = tuple(sorted(str(item.get("ref") or "") for item in offers if item.get("ref")))
        if not refs or self.site_api is None or not hasattr(self.site_api, "payment_product_states"):
            return {}
        now = time.monotonic()
        cached = self._product_state_cache.get(int(user_id))
        if cached is not None and now - cached[0] < 12 and cached[1] == refs:
            return dict(cached[2])
        payload = self.site_api.payment_product_states(user_id, list(refs))
        states = dict(payload.get("states") or {})
        self._product_state_cache[int(user_id)] = (now, refs, states)
        return states

    def _activate_subscription_from_payment_status(
        self, user_id: int, order_token: str, payload: dict
    ) -> dict:
        """Activate only from canonical provider-verified website state."""
        if str(payload.get("status") or "") != "success":
            return payload

        ai_checkout = self.state.ai_booklet_checkout_by_order(order_token)
        if ai_checkout is not None:
            if (
                str(ai_checkout.get("platform") or "") != self.platform
                or int(ai_checkout.get("platformUserId") or 0) != int(user_id)
                or not str(payload.get("verifiedAt") or "").strip()
                or int(payload.get("amountRials") or 0) != int(ai_checkout.get("amountRials") or -1)
            ):
                return payload
            proof_id = "status-" + hashlib.sha256(order_token.encode("ascii")).hexdigest()[:48]
            try:
                entitlement = self.state.activate_paid_ai_booklet(
                    order_token=order_token,
                    delivery_id=proof_id,
                    platform=self.platform,
                    platform_user_id=user_id,
                    amount_rials=int(payload["amountRials"]),
                    verified_at=str(payload["verifiedAt"]),
                    payment_order_ref=str(payload.get("orderId") or payload.get("trackingRef") or ""),
                )
            except (TypeError, ValueError, RuntimeError):
                return payload
            result = dict(payload)
            result["fulfillment"] = {
                "text": "دسترسی جزوه هوش مصنوعی همین جلسه فعال شد.",
                "action": (
                    f"booklet-ai-get:{entitlement.get('courseCode')}:"
                    f"{int(entitlement.get('sessionNo') or 0)}"
                ),
            }
            return result

        checkout = self.state.term_subscription_checkout_by_order(order_token)
        if checkout is None:
            return payload
        if (
            str(checkout.get("platform") or "") != self.platform
            or int(checkout.get("platformUserId") or 0) != int(user_id)
            or not str(payload.get("verifiedAt") or "").strip()
            or int(payload.get("amountRials") or 0) != int(checkout.get("amountRials") or -1)
        ):
            return payload
        proof_id = "status-" + hashlib.sha256(order_token.encode("ascii")).hexdigest()[:48]
        try:
            entitlement = self.state.activate_paid_term_subscription(
                order_token=order_token, delivery_id=proof_id, platform=self.platform,
                platform_user_id=user_id, amount_rials=int(payload["amountRials"]),
                verified_at=str(payload["verifiedAt"]),
                payment_order_ref=str(payload.get("orderId") or payload.get("trackingRef") or ""),
            )
        except (TypeError, ValueError, RuntimeError):
            return payload
        result = dict(payload)
        result["fulfillment"] = {
            "text": (
                f"اشتراک {entitlement.get('billingPeriod') or ''} فعال شد؛ "
                "اکنون از بخش جزوات ادامه بده."
            )
        }
        return result

    def _owner_payment_summary(self, user_id: int) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "payment_owner_dashboard"):
            return {}
        try:
            return self.site_api.payment_owner_dashboard(user_id)
        except SiteApiError:
            return {}

    def _term_subscription_report(self, user_id: int, term: int) -> dict:
        report = self.state.term_subscription_report(term)
        if self.site_api is None or not hasattr(self.site_api, "payment_directory"):
            return report
        try:
            directory = [
                item for item in self.site_api.payment_directory(user_id, limit=500).get("items", [])
                if isinstance(item, dict) and str(item.get("studentNumber") or "").isdigit()
            ]
            total = len({str(item.get("studentNumber")) for item in directory})
            report["directoryCount"] = total
            report["withoutSubscription"] = max(0, total - int(report.get("totalActiveAccess") or 0))
        except SiteApiError:
            pass
        return report

    def _payment_people_report(
        self, user_id: int, item: dict, *, date_from: str = "", date_to: str = ""
    ) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "payment_product_report"):
            raise SiteApiError("گزارش پرداخت در دسترس نیست.", code="PAYMENT_REPORT_UNAVAILABLE")
        report = dict(self.site_api.payment_product_report(
            user_id, offer_ref=str(item.get("ref") or ""), date_from=date_from, date_to=date_to
        ))
        audience = dict(item.get("audience") or {})
        mode = str(audience.get("mode") or "all")
        targets: list[dict] = []
        if mode in {"cohorts", "users", "lists"} and hasattr(self.site_api, "payment_directory"):
            directory = [entry for entry in self.site_api.payment_directory(user_id, limit=500).get("items", []) if isinstance(entry, dict)]
            student_numbers = set(str(value) for value in audience.get("studentNumbers", []))
            if mode == "lists":
                student_numbers = set(self.state.saved_audience_members(list(audience.get("listRefs") or [])))
            cohorts = set(str(value) for value in audience.get("cohorts", []))
            for entry in directory:
                student = str(entry.get("studentNumber") or "")
                if (mode == "cohorts" and str(entry.get("cohortKey") or "") in cohorts) or (mode != "cohorts" and student in student_numbers):
                    targets.append(entry)
        payer_numbers = {str(entry.get("studentNumber") or "") for entry in report.get("payers", []) if isinstance(entry, dict)}
        report["unpaid"] = [entry for entry in targets if str(entry.get("studentNumber") or "") not in payer_numbers]
        report["targets"] = targets
        report["targetCount"] = len(targets) if mode in {"cohorts", "users", "lists"} else None
        report["unpaidCount"] = len(report["unpaid"]) if report["targetCount"] is not None else None
        return report

    @staticmethod
    def _payment_period_bounds(period: str) -> tuple[str, str]:
        now = local_now("Asia/Tehran")
        if period == "today":
            start = now.replace(hour=0, minute=0, second=0, microsecond=0)
        elif period == "7":
            start = now - timedelta(days=7)
        elif period == "30":
            start = now - timedelta(days=30)
        else:
            return "", ""
        return start.astimezone(timezone.utc).isoformat(), now.astimezone(timezone.utc).isoformat()

    def _payment_transaction_filter_state(self, user_id: int) -> dict:
        return dict(self._payment_filters.get(int(user_id)) or {})

    def _payment_transactions_payload(self, user_id: int, *, page: int = 0, limit: int = 10) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "payment_transactions"):
            raise SiteApiError("گزارش تراکنش در دسترس نیست.", code="PAYMENT_REPORT_UNAVAILABLE")
        filters = self._payment_transaction_filter_state(user_id)
        return self.site_api.payment_transactions(
            user_id,
            query=str(filters.get("query") or ""),
            offer_ref=str(filters.get("offerRef") or ""),
            status=str(filters.get("status") or ""),
            platform=str(filters.get("platform") or ""),
            gateway=str(filters.get("gateway") or ""),
            date_from=str(filters.get("dateFrom") or ""),
            date_to=str(filters.get("dateTo") or ""),
            page=max(0, int(page)), limit=max(1, min(100, int(limit))),
        )

    def _all_payment_transactions(self, user_id: int, *, filtered: bool, offer_ref: str = "") -> list[dict]:
        if self.site_api is None or not hasattr(self.site_api, "payment_transactions"):
            raise SiteApiError("خروجی تراکنش در دسترس نیست.", code="PAYMENT_EXPORT_UNAVAILABLE")
        filters = self._payment_transaction_filter_state(user_id) if filtered else {}
        if offer_ref:
            filters["offerRef"] = offer_ref
        rows: list[dict] = []
        for page in range(100):
            payload = self.site_api.payment_transactions(
                user_id,
                query=str(filters.get("query") or ""), offer_ref=str(filters.get("offerRef") or ""),
                status=str(filters.get("status") or ""), platform=str(filters.get("platform") or ""),
                gateway=str(filters.get("gateway") or ""), date_from=str(filters.get("dateFrom") or ""),
                date_to=str(filters.get("dateTo") or ""), page=page, limit=100,
            )
            chunk = [item for item in payload.get("items", []) if isinstance(item, dict)]
            rows.extend(chunk)
            total = max(0, int(payload.get("total") or len(rows)))
            if not chunk or len(rows) >= total:
                break
        return rows

    def _payment_audience_search_results(self, payload: dict) -> Screen:
        selected = {str(value) for value in payload.get("selected", [])}
        candidates = [item for item in payload.get("candidates", []) if isinstance(item, dict)][:12]
        lines = ["<b>👥 انتخاب مخاطبان</b>", "", f"انتخاب‌شده: <b>{to_persian_digits(len(selected))}</b> نفر"]
        rows: list[list[dict]] = []
        for item in candidates:
            student = str(item.get("studentNumber") or "")
            if not student.isdigit():
                continue
            marker = "✅" if student in selected else "◻️"
            name = str(item.get("name") or student)
            rows.append([button(f"{marker} {name[:24]} · {to_persian_digits(student)}", action=f"payment-audience-select:{student}")])
        if not candidates:
            lines.append("نتیجه‌ای پیدا نشد.")
        rows.extend((
            [button("🔎 جستجوی بیشتر", action="payment-audience-search-more")],
            [button("💾 ذخیره فهرست", action="payment-audience-selection-save", style="success")],
            [button("انصراف", action="payment-audiences")],
        ))
        return Screen("\n".join(lines), keyboard(*rows))

    def _payment_export_rows(self, user_id: int, scope: str, kind: str) -> list[dict]:
        if scope in {"all", "filtered"}:
            return self._all_payment_transactions(user_id, filtered=scope == "filtered")
        item = self._ordinary_payment_offer(scope)
        if item is None:
            raise SiteApiError("محصول پیدا نشد.", code="PRODUCT_NOT_FOUND")
        if kind in {"paid", "unpaid"}:
            report = self._payment_people_report(user_id, item)
            people = report.get("payers" if kind == "paid" else "unpaid", [])
            return [
                {
                    "payerName": str(person.get("name") or ""),
                    "studentNumber": str(person.get("studentNumber") or ""),
                    "title": str(item.get("title") or ""),
                    "amountRials": int(person.get("amountRials") or item.get("amountRials") or 0) if kind == "paid" else 0,
                    "status": "success" if kind == "paid" else "unpaid",
                    "createdAt": str(person.get("paidAt") or ""),
                    "trackingRef": "",
                    "originPlatform": self.platform,
                    "gateway": "",
                }
                for person in people if isinstance(person, dict)
            ]
        return self._all_payment_transactions(user_id, filtered=False, offer_ref=scope)

    def _send_payment_export(self, chat_id: int, user_id: int, scope: str, kind: str) -> None:
        rows = self._payment_export_rows(user_id, scope, kind)
        fields = [
            ("نام", "payerName"), ("شماره دانشجویی", "studentNumber"), ("محصول", "title"),
            ("مبلغ ریال", "amountRials"), ("وضعیت", "status"), ("ایجاد", "createdAt"),
            ("پرداخت", "paidAt"), ("تأیید", "verifiedAt"), ("کد پیگیری", "trackingRef"),
            ("پلتفرم", "originPlatform"), ("درگاه", "gateway"),
        ]
        with tempfile.TemporaryDirectory(prefix="dent-payment-export-") as temp_dir:
            output = Path(temp_dir) / f"dent-payments-{kind}-{int(time.time())}.csv"
            with output.open("w", encoding="utf-8-sig", newline="") as stream:
                writer = csv.writer(stream)
                writer.writerow([label for label, _key in fields])
                for row in rows:
                    writer.writerow([row.get(key, "") for _label, key in fields])
            self.api.send_document_path(
                chat_id, output, caption=f"<b>📤 خروجی پرداخت‌ها</b>\n\n{to_persian_digits(len(rows))} ردیف · CSV سازگار با Excel",
                filename=output.name, content_type="text/csv; charset=utf-8",
            )
        self.state.record_payment_action(
            "payment-exported", actor_user_id=user_id, actor_platform=self.platform,
            offer_ref="" if scope in {"all", "filtered"} else scope, note=f"{scope}:{kind}:{len(rows)}",
        )

    def _send_term_subscription_export(self, chat_id: int, user_id: int, term: int, kind: str) -> None:
        rows = self.state.term_access_export_rows(term)
        with tempfile.TemporaryDirectory(prefix="dent-subscription-export-") as temp_dir:
            suffix = "csv" if kind == "csv" else "txt"
            output = Path(temp_dir) / f"term-{term}-subscription-{int(time.time())}.{suffix}"
            if kind == "csv":
                fields = (
                    ("نام", "displayName"), ("شماره دانشجویی", "studentNumber"),
                    ("نوع دسترسی", "accessType"), ("دوره", "billingPeriod"),
                    ("وضعیت", "status"), ("اعطا", "grantedAt"), ("انقضا", "expiresAt"),
                    ("یادداشت", "note"),
                )
                with output.open("w", encoding="utf-8-sig", newline="") as stream:
                    writer = csv.writer(stream)
                    writer.writerow([label for label, _key in fields])
                    for row in rows:
                        writer.writerow([row.get(key, "") for _label, key in fields])
            else:
                lines = [f"اشتراک جزوات ترم {term}", ""]
                for index, row in enumerate(rows, 1):
                    lines.append(
                        f"{index}. {row.get('displayName') or '—'} | {row.get('studentNumber') or '—'} | "
                        f"{row.get('accessType') or '—'} | {row.get('billingPeriod') or 'دسترسی رایگان'} | "
                        f"{row.get('status') or '—'}"
                    )
                output.write_text("\n".join(lines), encoding="utf-8")
            self.api.send_document_path(
                chat_id, output,
                caption=f"<b>📤 خروجی اشتراک جزوات ترم {to_persian_digits(term)}</b>\n\n{to_persian_digits(len(rows))} ردیف",
                filename=output.name,
                content_type="text/csv; charset=utf-8" if kind == "csv" else "text/plain; charset=utf-8",
            )

    def _send_payment_reminder_batch(self, chat_id: int, user_id: int, offer_ref: str, batch_ref: str) -> None:
        batch = self.state.payment_reminder(batch_ref)
        item = self._ordinary_payment_offer(offer_ref)
        if not batch or batch.get("status") != "preview" or batch.get("offerRef") != offer_ref or item is None:
            self.api.send(chat_id, frame_error("پیش‌نمایش یادآوری منقضی یا قبلاً استفاده شده است."), payment_control_center_screen([]).keyboard)
            return
        report = self._payment_people_report(user_id, item)
        recipients = [person for person in report.get("unpaid", []) if str(person.get("platformUserId") or "").isdigit()][:50]
        self.state.finish_payment_reminder(batch_ref, status="sending", sent_count=0)
        sent = 0
        for person in recipients:
            try:
                self.api.send(
                    int(person["platformUserId"]),
                    f"<b>🔔 یادآوری پرداخت</b>\n\n<b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code>\n"
                    "این پیام فقط برای مخاطبان همین محصول و با تأیید مالک ارسال شده است.",
                    keyboard([button("مشاهده محصول", action=f"payment-confirm:{offer_ref}", style="success")]),
                )
                sent += 1
                time.sleep(0.05)
            except BotApiError:
                continue
        self.state.finish_payment_reminder(batch_ref, status="sent" if sent == len(recipients) else "failed", sent_count=sent)
        self.state.record_payment_action(
            "payment-reminder-sent", actor_user_id=user_id, actor_platform=self.platform,
            offer_ref=offer_ref, note=f"{sent}/{len(recipients)}",
        )
        self.api.send(
            chat_id,
            f"<b>🔔 یادآوری‌ها پردازش شد</b>\n\nارسال موفق: <b>{to_persian_digits(sent)}</b> از <b>{to_persian_digits(len(recipients))}</b>",
            keyboard([button("بازگشت به آمار", action=f"payment-offer-stats:{offer_ref}")]),
        )

    def _send_payment_product_batch(self, chat_id: int, user_id: int, offer_ref: str, batch_ref: str) -> None:
        batch = self.state.payment_reminder(batch_ref)
        item = self._ordinary_payment_offer(offer_ref)
        if not batch or batch.get("status") != "preview" or batch.get("offerRef") != offer_ref or item is None:
            self.api.send(chat_id, frame_error("پیش‌نمایش ارسال منقضی یا قبلاً استفاده شده است."), payment_control_center_screen([]).keyboard)
            return
        report = self._payment_people_report(user_id, item)
        recipients = [person for person in report.get("targets", []) if str(person.get("platformUserId") or "").isdigit()][:50]
        share_url = bot_start_url(self.bot_username, f"product_{str(item.get('shareToken') or '')}", platform=self.platform)
        if not share_url:
            self.state.finish_payment_reminder(batch_ref, status="failed", sent_count=0)
            self.api.send(chat_id, frame_error("لینک امن محصول ساخته نشد."), payment_control_center_screen([]).keyboard)
            return
        self.state.finish_payment_reminder(batch_ref, status="sending", sent_count=0)
        sent = 0
        for person in recipients:
            try:
                self.api.send(
                    int(person["platformUserId"]),
                    f"<b>🛍 محصول جدید</b>\n\n<b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code>\n"
                    "این پیام فقط برای مخاطبان همین محصول و پس از تأیید مالک ارسال شده است.",
                    keyboard([button("مشاهده محصول", url=share_url, style="success")]),
                )
                sent += 1
                time.sleep(0.05)
            except BotApiError:
                continue
        self.state.finish_payment_reminder(batch_ref, status="sent" if sent == len(recipients) else "failed", sent_count=sent)
        self.state.record_payment_action(
            "payment-product-shared", actor_user_id=user_id, actor_platform=self.platform,
            offer_ref=offer_ref, note=f"{sent}/{len(recipients)}",
        )
        self.api.send(
            chat_id,
            f"<b>📤 ارسال محصول پردازش شد</b>\n\nارسال موفق: <b>{to_persian_digits(sent)}</b> از <b>{to_persian_digits(len(recipients))}</b>",
            keyboard([button("بازگشت به محصول", action=f"payment-offer:{offer_ref}")]),
        )
