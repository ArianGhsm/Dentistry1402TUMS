from __future__ import annotations

import re
from datetime import datetime, timedelta, timezone

from .ai_booklets import AI_BOOKLET_CONTENT_KIND, AI_BOOKLET_PRICE_RIALS, ai_booklet_offer_ref
from .booklets import course_by_key, session_by_number
from .cart import (
    cart_checkout_request_id,
    cart_item_key,
    cart_media_requests,
    commerce_identity_from_account,
)
from .cart_ui import (
    cart_added_screen,
    cart_discount_prompt_screen,
    cart_screen,
    discount_codes_screen,
    discount_new_kind_screen,
)
from .message_frames import frame_error
from .payments import identity_from_account
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .subscriptions import (
    billing_period_for,
    jalali_midnight_utc,
    parse_jalali_date,
    policy_is_effective,
    subscription_identity_from_account,
    utc_iso,
)
from .ui import Screen, keyboard, button, payment_created_screen, payment_status_screen


class CartAppWorkflows:
    """Canonical cart domain + routing shared by Telegram and Bale."""

    def _cart_identity(self, user_id: int, *, refresh: bool = False):
        if self.site_api is None:
            return None, {}
        account = self._account_snapshot(user_id, refresh=refresh)
        return commerce_identity_from_account(account), account

    def _cart_mutation_blocker(self, user_id: int, subject_key: str) -> Screen | None:
        checkout = self.state.latest_bound_commerce_cart_checkout(subject_key)
        if checkout is None or self.site_api is None:
            return None
        order_token = str(checkout.get("orderToken") or "")
        if not order_token:
            return None
        try:
            status_payload = dict(self.site_api.payment_status(user_id, order_token))
        except SiteApiError:
            return Screen(
                frame_error(
                    "یک پرداخت قبلی برای این سبد هنوز تعیین تکلیف نشده است؛ "
                    "برای جلوگیری از ساخت سفارش تکراری فعلاً سبد تغییر نمی‌کند."
                ),
                keyboard(
                    [button("بررسی وضعیت پرداخت", action=f"payment-status:{order_token}", style="primary")],
                    [button("🏠 خانه", action="home")],
                ),
            )

        status = str(status_payload.get("status") or "pending")
        if status == "success":
            status_payload = self._activate_subscription_from_payment_status(
                user_id,
                order_token,
                status_payload,
            )
            return payment_status_screen(
                status_payload,
                platform=self.platform,
                order_token=order_token,
                return_to_bot_enabled=self.payment_return_v1_enabled,
            )
        if status != "pending":
            return None

        created_at = str(checkout.get("createdAt") or "").strip()
        if created_at:
            try:
                created = datetime.fromisoformat(created_at.replace("Z", "+00:00"))
                if created.tzinfo is None:
                    created = created.replace(tzinfo=timezone.utc)
                if (datetime.now(timezone.utc) - created.astimezone(timezone.utc)).total_seconds() >= 3600:
                    return None
            except ValueError:
                pass

        return Screen(
            "<b>🟡 یک پرداخت برای این سبد در جریان است</b>\n\n"
            "تا مشخص‌شدن نتیجهٔ این پرداخت، افزودن/حذف محصول و تغییر کد تخفیف قفل است "
            "تا دو سفارش قابل‌پرداخت برای یک سبد ساخته نشود.",
            keyboard(
                [button("بررسی وضعیت پرداخت", action=f"payment-status:{order_token}", style="primary")],
                [button("↩️ سبد خرید", action="cart"), button("🏠 خانه", action="home")],
            ),
        )

    def _cart_offer_snapshot(self, offer: dict, *, item_key: str, kind: str = "offer") -> dict:
        return {
            "itemKey": item_key,
            "kind": kind,
            "offerRef": str(offer.get("ref") or ""),
            "title": str(offer.get("title") or "محصول"),
            "description": str(offer.get("description") or ""),
            "amountRials": int(offer.get("amountRials") or 0),
            "productVersion": int(offer.get("version") or 1),
            "availableFrom": str(offer.get("availableFrom") or ""),
            "expiresAt": str(offer.get("expiresAt") or ""),
            "capacity": int(offer.get("capacity") or 0),
            "maxPurchasesPerUser": int(offer.get("maxPurchasesPerUser") or 0),
            "fulfillment": dict(offer.get("fulfillment") or {}),
            "available": True,
            "reason": "",
        }

    def _cart_resolve_descriptor(
        self,
        user_id: int,
        descriptor: dict,
        *,
        account: dict,
        commerce_identity,
    ) -> dict:
        kind = str(descriptor.get("kind") or "")
        key = cart_item_key(descriptor)

        if kind == "offer":
            offer = self.state.payment_offer_for_user(
                str(descriptor.get("offerRef") or ""),
                identity_from_account(account),
            )
            if offer is None:
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(descriptor.get("offerRef") or ""),
                    "title": "محصول ناموجود",
                    "amountRials": 0,
                    "available": False,
                    "reason": "این محصول دیگر قابل خرید نیست.",
                }
            item = self._cart_offer_snapshot(offer, item_key=key)
            if self._is_paid_file_offer(offer):
                fulfillment = dict(offer.get("fulfillment") or {})
                if self.platform != "telegram":
                    item["available"] = False
                    item["reason"] = "تحویل این فایل فقط در تلگرام انجام می‌شود."
                elif self.state.paid_file_asset(str(fulfillment.get("assetRef") or "")) is None:
                    item["available"] = False
                    item["reason"] = "فایل محصول در دسترس نیست."
            return item

        if kind == "ai_booklet":
            if self.platform != "telegram":
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(descriptor.get("offerRef") or ""),
                    "title": "جزوه هوش مصنوعی",
                    "amountRials": AI_BOOKLET_PRICE_RIALS,
                    "available": False,
                    "reason": "تحویل جزوه هوش مصنوعی فقط در تلگرام انجام می‌شود.",
                }
            catalog = self._booklet_catalog(user_id)
            term = int(descriptor.get("term") or catalog.get("term") or 7)
            course_key = str(descriptor.get("courseCode") or "")
            session_no = int(descriptor.get("sessionNo") or 0)
            course = course_by_key(catalog, course_key)
            session = session_by_number(course or {}, session_no) if course is not None else None
            identity = subscription_identity_from_account(account)
            if course is None or session is None or identity is None:
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(descriptor.get("offerRef") or ""),
                    "title": "جزوه هوش مصنوعی",
                    "amountRials": AI_BOOKLET_PRICE_RIALS,
                    "available": False,
                    "reason": "جلسه یا هویت خرید دیگر معتبر نیست.",
                }
            course_tag = str(course.get("bookletTag") or "")
            sources = self.state.protected_media_for_tag(
                course_tag=course_tag,
                term=term,
                session_no=session_no,
                content_kind=AI_BOOKLET_CONTENT_KIND,
            )
            title = (
                f"جزوه هوش مصنوعی {str(course.get('courseTitle') or 'درس')} · "
                f"جلسه {to_persian_digits(session_no)}"
            )
            if not sources:
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(descriptor.get("offerRef") or ""),
                    "title": title,
                    "amountRials": AI_BOOKLET_PRICE_RIALS,
                    "available": False,
                    "reason": "جزوه این جلسه دیگر منتشر نیست.",
                }
            if self.state.has_ai_booklet_access(
                identity.subject_key,
                term=term,
                course_code=course_key,
                session_no=session_no,
            ):
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(descriptor.get("offerRef") or ""),
                    "title": title,
                    "amountRials": AI_BOOKLET_PRICE_RIALS,
                    "available": False,
                    "reason": "این جزوه قبلاً برای حساب شما فعال شده است.",
                }
            return {
                "itemKey": key,
                "kind": kind,
                "offerRef": ai_booklet_offer_ref(term, course_key, session_no),
                "title": title,
                "description": (
                    f"{str(session.get('title') or 'جلسه')} · "
                    "دسترسی مستقل به جزوه هوش مصنوعی همین جلسه"
                ),
                "amountRials": AI_BOOKLET_PRICE_RIALS,
                "productVersion": 1,
                "availableFrom": "",
                "expiresAt": "",
                "capacity": 0,
                "maxPurchasesPerUser": 1,
                "fulfillment": {
                    "text": "پس از تأیید درگاه، جزوه هوش مصنوعی همین جلسه در ربات فعال می‌شود."
                },
                "term": term,
                "courseCode": course_key,
                "courseTag": course_tag,
                "sessionNo": session_no,
                "available": True,
                "reason": "",
            }

        if kind == "term_subscription":
            term = int(descriptor.get("term") or 0)
            policy = self.state.term_access_policy(term)
            identity = subscription_identity_from_account(account)
            current_period = billing_period_for(term)
            title = f"اشتراک جزوات ترم {to_persian_digits(term)} · {current_period.month_label}"
            if policy is None or identity is None or not policy_is_effective(policy):
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(descriptor.get("offerRef") or ""),
                    "title": title,
                    "amountRials": 0,
                    "available": False,
                    "reason": "فروش اشتراک این ترم فعال نیست.",
                }
            if str(descriptor.get("billingPeriod") or "") != current_period.key:
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(policy.get("offerRef") or ""),
                    "title": title,
                    "amountRials": int(policy.get("monthlyPriceRials") or 0),
                    "available": False,
                    "reason": "ماه اشتراک تغییر کرده؛ این مورد را حذف و دوباره اضافه کن.",
                }
            decision = self.state.term_access_decision(identity.subject_key, term)
            if decision.get("allowed"):
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(policy.get("offerRef") or ""),
                    "title": title,
                    "amountRials": int(policy.get("monthlyPriceRials") or 0),
                    "available": False,
                    "reason": "اشتراک این ماه برای حساب شما فعال است.",
                }
            offer = self.state.payment_offer(str(policy.get("offerRef") or ""), require_active=True)
            if offer is None:
                return {
                    "itemKey": key,
                    "kind": kind,
                    "offerRef": str(policy.get("offerRef") or ""),
                    "title": title,
                    "amountRials": int(policy.get("monthlyPriceRials") or 0),
                    "available": False,
                    "reason": "فروش اشتراک فعلاً متوقف است.",
                }
            return {
                "itemKey": key,
                "kind": kind,
                "offerRef": str(policy.get("offerRef") or ""),
                "title": title,
                "description": "اشتراک کامل ماه شمسی جاری؛ اعتبار فقط تا پایان همین ماه است.",
                "amountRials": int(policy.get("monthlyPriceRials") or 0),
                "productVersion": int(policy.get("version") or offer.get("version") or 1),
                "availableFrom": "",
                "expiresAt": utc_iso(current_period.expires_at),
                "capacity": 0,
                "maxPurchasesPerUser": 0,
                "fulfillment": {
                    "kind": "term_subscription",
                    "term": term,
                    "billingPeriod": current_period.key,
                    "text": "پس از تأیید درگاه، اشتراک همین ماه در ربات فعال می‌شود.",
                },
                "term": term,
                "billingPeriod": current_period.key,
                "available": True,
                "reason": "",
            }

        return {
            "itemKey": key,
            "kind": kind,
            "offerRef": str(descriptor.get("offerRef") or ""),
            "title": "محصول نامعتبر",
            "amountRials": 0,
            "available": False,
            "reason": "نوع محصول پشتیبانی نمی‌شود.",
        }

    def _cart_snapshot(self, user_id: int, *, refresh_account: bool = False) -> dict:
        commerce_identity, account = self._cart_identity(user_id, refresh=refresh_account)
        if commerce_identity is None:
            return {
                "identityMissing": True,
                "items": [],
                "version": 0,
                "subtotalRials": 0,
                "discountCode": "",
                "discountAmountRials": 0,
                "amountRials": 0,
            }

        cart = self.state.commerce_cart(commerce_identity.subject_key)
        items = [
            self._cart_resolve_descriptor(
                user_id,
                dict(descriptor),
                account=account,
                commerce_identity=commerce_identity,
            )
            for descriptor in cart.get("items", [])
            if isinstance(descriptor, dict)
        ]

        refs = [str(item.get("offerRef") or "") for item in items if item.get("offerRef")]
        states: dict = {}
        if refs and self.site_api is not None:
            try:
                states = dict(self.site_api.payment_product_states(user_id, refs).get("states") or {})
            except SiteApiError:
                states = {}
        for item in items:
            if not item.get("available"):
                continue
            state = dict(states.get(str(item.get("offerRef") or "")) or {})
            max_per_user = int(item.get("maxPurchasesPerUser") or 0)
            capacity = int(item.get("capacity") or 0)
            if max_per_user > 0 and int(state.get("successCount") or 0) >= max_per_user:
                item["available"] = False
                item["reason"] = "سقف خرید این محصول برای حساب شما تکمیل شده است."
            elif max_per_user > 0 and int(state.get("pendingCount") or 0) >= max_per_user:
                item["available"] = False
                item["reason"] = "یک پرداخت قبلی برای این محصول هنوز در انتظار نتیجه است."
            elif capacity > 0 and int(state.get("reservedCount") or 0) >= capacity:
                item["available"] = False
                item["reason"] = "ظرفیت این محصول تکمیل شده است."

        subtotal = sum(int(item.get("amountRials") or 0) for item in items if item.get("available"))
        discount_code = str(cart.get("discountCode") or "")
        discount_amount = 0
        discount_valid = True
        discount_reason = ""
        discount = self.state.commerce_discount_code(discount_code) if discount_code else None
        if discount_code:
            if discount is None:
                discount_valid = False
                discount_reason = "کد تخفیف معتبر یا فعال نیست."
            elif subtotal < int(discount.get("minSubtotalRials") or 0):
                discount_valid = False
                discount_reason = "جمع سبد به حداقل مبلغ این کد تخفیف نرسیده است."
            elif str(discount.get("kind") or "") == "percent":
                discount_amount = subtotal * int(discount.get("amount") or 0) // 100
            else:
                discount_amount = min(subtotal, int(discount.get("amount") or 0))

        return {
            "identityMissing": False,
            "identity": commerce_identity,
            "account": account,
            "items": items,
            "version": int(cart.get("version") or 0),
            "discountCode": discount_code,
            "discount": discount,
            "discountValid": discount_valid,
            "discountReason": discount_reason,
            "subtotalRials": subtotal,
            "discountAmountRials": discount_amount,
            "amountRials": max(0, subtotal - discount_amount),
        }

    def _cart_add_descriptor(self, user_id: int, descriptor: dict, *, title: str) -> Screen:
        identity, account = self._cart_identity(user_id, refresh=True)
        if identity is None:
            return self._unlinked_access_screen(user_id, account)
        blocked = self._cart_mutation_blocker(user_id, identity.subject_key)
        if blocked is not None:
            return blocked
        resolved = self._cart_resolve_descriptor(
            user_id,
            descriptor,
            account=account,
            commerce_identity=identity,
        )
        if not resolved.get("available"):
            return Screen(
                frame_error(str(resolved.get("reason") or "این محصول قابل افزودن نیست.")),
                keyboard([button("↩️ سبد خرید", action="cart"), button("🏠 خانه", action="home")]),
            )
        if str(descriptor.get("kind") or "") == "offer":
            offer = self.state.payment_offer(str(descriptor.get("offerRef") or ""), require_active=False)
            if offer is not None:
                blocked = self._paid_file_purchase_blocker(user_id, offer)
                if blocked is not None:
                    return blocked
        self.state.commerce_cart_add(
            subject_key=identity.subject_key,
            student_number=identity.student_number,
            display_name=identity.display_name,
            item=descriptor,
        )
        return cart_added_screen(self._cart_snapshot(user_id), title=title)

    def _cart_checkout_screen(self, user_id: int) -> Screen:
        if self.site_api is None:
            return Screen(frame_error("درگاه پرداخت در دسترس نیست."), self._screen("home", user_id).keyboard)
        snapshot = self._cart_snapshot(user_id, refresh_account=True)
        identity = snapshot.get("identity")
        items = [dict(item) for item in snapshot.get("items", []) if isinstance(item, dict)]
        if identity is None:
            return self._unlinked_access_screen(user_id, dict(snapshot.get("account") or {}))
        if not items:
            return cart_screen(snapshot)
        if any(not item.get("available") for item in items):
            return cart_screen(snapshot, notice="پیش از پرداخت، موردهای نامعتبر را از سبد حذف کن.")
        if not snapshot.get("discountValid", True):
            return cart_screen(snapshot, notice=str(snapshot.get("discountReason") or "کد تخفیف معتبر نیست."))

        # Recheck delivery prerequisites immediately before payment. A PDF may
        # have been added earlier when identity data was complete; checkout must
        # fail closed if the current canonical watermark identity is no longer
        # sufficient.
        for descriptor in self.state.commerce_cart(identity.subject_key).get("items", []):
            if not isinstance(descriptor, dict) or str(descriptor.get("kind") or "") != "offer":
                continue
            offer = self.state.payment_offer(str(descriptor.get("offerRef") or ""), require_active=False)
            if offer is None or not self._is_paid_file_offer(offer):
                continue
            blocked = self._paid_file_purchase_blocker(user_id, offer)
            if blocked is not None:
                return blocked

        checkout_items = [
            {
                key: value
                for key, value in item.items()
                if key not in {"available", "reason"}
            }
            for item in items
        ]
        # Canonical across Telegram/Bale: the same identity + cart revision must
        # never create two payable orders merely because checkout was tapped
        # from two linked transports at nearly the same time.
        request_id = cart_checkout_request_id(
            identity.subject_key,
            version=int(snapshot.get("version") or 0),
            items=checkout_items,
            discount_code=str(snapshot.get("discountCode") or ""),
        )
        discount = dict(snapshot.get("discount") or {})
        try:
            result = self.site_api.create_bot_cart_payment(
                user_id,
                items=checkout_items,
                request_id=request_id,
                discount=discount,
            )
            self.state.record_commerce_cart_checkout(
                request_id=request_id,
                order_token=str(result.get("orderToken") or ""),
                platform=self.platform,
                platform_user_id=user_id,
                subject_key=identity.subject_key,
                student_number=identity.student_number,
                display_name=identity.display_name,
                items=(
                    [dict(item) for item in result.get("cartItems", []) if isinstance(item, dict)]
                    or checkout_items
                ),
                subtotal_rials=int(result.get("subtotalRials") or snapshot.get("subtotalRials") or 0),
                discount_code=str(result.get("discountCode") or ""),
                discount_amount_rials=int(result.get("discountAmountRials") or 0),
                amount_rials=int(result.get("amountRials") or 0),
            )
            return payment_created_screen(
                result,
                platform=self.platform,
                return_to_bot_enabled=self.payment_return_v1_enabled,
            )
        except SiteApiError as error:
            if error.code == "PAYMENT_PHONE_REQUIRED":
                from .ui import payment_phone_required_screen
                return payment_phone_required_screen()
            return cart_screen(snapshot, notice=str(error))
        except (TypeError, ValueError) as error:
            return cart_screen(snapshot, notice=str(error))

    def _handle_cart_dialog_message(
        self,
        chat_id: int,
        user_id: int,
        text: str,
        dialog: dict,
    ) -> bool:
        kind = str(dialog.get("kind") or "")
        if kind == "cart-discount-user":
            from .cart_ui import cart_discount_prompt_screen, cart_screen

            payload = dict(dialog.get("payload") or {})
            identity, account = self._cart_identity(user_id, refresh=True)
            subject_key = str(payload.get("subjectKey") or "")
            if identity is None or identity.subject_key != subject_key:
                self.state.clear_dialog(user_id)
                screen = self._unlinked_access_screen(user_id, account)
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            blocked = self._cart_mutation_blocker(user_id, subject_key)
            if blocked is not None:
                self.state.clear_dialog(user_id)
                self.api.send(chat_id, blocked.text, blocked.keyboard)
                return True
            code = re.sub(r"\s+", "", text.upper())
            discount = self.state.commerce_discount_code(code)
            if discount is None:
                current = self.state.commerce_cart(subject_key)
                screen = Screen(
                    frame_error("کد تخفیف معتبر یا فعال نیست."),
                    cart_discount_prompt_screen(
                        current_code=str(current.get("discountCode") or "")
                    ).keyboard,
                )
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            self.state.commerce_cart_set_discount(subject_key, str(discount["code"]))
            self.state.clear_dialog(user_id)
            screen = cart_screen(
                self._cart_snapshot(user_id),
                notice="کد تخفیف روی سبد ثبت شد.",
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True

        if kind != "commerce-discount-new":
            return False

        from .cart_ui import discount_created_screen, discount_new_prompt_screen

        if user_id != self.owner_id:
            self.state.clear_dialog(user_id)
            screen = Screen(
                frame_error("این بخش فقط برای مالک در دسترس است."),
                self._screen("home", user_id).keyboard,
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True

        step = str(dialog.get("step") or "amount")
        payload = dict(dialog.get("payload") or {})
        normalized = text.translate(
            str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
        )
        digits = re.sub(r"[^0-9]", "", normalized)

        if step == "amount":
            value = int(digits or 0)
            kind_value = str(payload.get("kind") or "")
            if kind_value == "percent":
                if not 1 <= value <= 90:
                    screen = Screen(
                        frame_error("درصد تخفیف باید بین ۱ تا ۹۰ باشد."),
                        discount_new_prompt_screen(step, payload).keyboard,
                    )
                    self.api.send(chat_id, screen.text, screen.keyboard)
                    return True
                payload["amount"] = value
            else:
                if value < 1000:
                    screen = Screen(
                        frame_error("مبلغ ثابت را به تومان و حداقل ۱٬۰۰۰ تومان بفرست."),
                        discount_new_prompt_screen(step, payload).keyboard,
                    )
                    self.api.send(chat_id, screen.text, screen.keyboard)
                    return True
                payload["amount"] = value * 10
            self.state.update_dialog(user_id, step="minimum", payload=payload)
            screen = discount_new_prompt_screen("minimum", payload)

        elif step == "minimum":
            payload["minSubtotalRials"] = int(digits or 0) * 10
            self.state.update_dialog(user_id, step="uses", payload=payload)
            screen = discount_new_prompt_screen("uses", payload)

        elif step == "uses":
            value = int(digits or 0)
            if value > 1_000_000:
                screen = Screen(
                    frame_error("سقف استفاده بیش از حد بزرگ است."),
                    discount_new_prompt_screen(step, payload).keyboard,
                )
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            payload["maxUses"] = value
            self.state.update_dialog(user_id, step="expiry", payload=payload)
            screen = discount_new_prompt_screen("expiry", payload)

        elif step == "expiry":
            expires_at = ""
            if text.strip() != "-":
                try:
                    year, month, day = parse_jalali_date(text)
                    expires_at = utc_iso(
                        jalali_midnight_utc(year, month, day) + timedelta(days=1)
                    )
                except (TypeError, ValueError):
                    screen = Screen(
                        frame_error(
                            "تاریخ را مثل ۱۴۰۵/۰۸/۳۰ بفرست یا «-» را برای بدون انقضا ارسال کن."
                        ),
                        discount_new_prompt_screen(step, payload).keyboard,
                    )
                    self.api.send(chat_id, screen.text, screen.keyboard)
                    return True
            try:
                item = self.state.create_commerce_discount_code(
                    kind=str(payload.get("kind") or ""),
                    amount=int(payload.get("amount") or 0),
                    min_subtotal_rials=int(payload.get("minSubtotalRials") or 0),
                    max_uses=int(payload.get("maxUses") or 0),
                    expires_at=expires_at,
                    actor_user_id=user_id,
                    actor_platform=self.platform,
                )
            except (TypeError, ValueError, RuntimeError) as error:
                screen = Screen(
                    frame_error(str(error)),
                    discount_new_prompt_screen(step, payload).keyboard,
                )
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            self.state.clear_dialog(user_id)
            screen = discount_created_screen(item)

        else:
            self.state.clear_dialog(user_id)
            screen = self._screen("home", user_id)

        self.api.send(chat_id, screen.text, screen.keyboard)
        return True

    def _cart_delivery_screen(self, user_id: int, order_token: str) -> Screen:
        checkout = self.state.commerce_cart_checkout_by_order(order_token)
        if (
            checkout is None
            or str(checkout.get("status") or "") != "activated"
            or str(checkout.get("platform") or "") != self.platform
            or int(checkout.get("platformUserId") or 0) != int(user_id)
        ):
            return Screen(
                frame_error("خرید تأییدشده‌ای برای این سبد پیدا نشد."),
                keyboard([button("🛒 سبد خرید", action="cart"), button("🏠 خانه", action="home")]),
            )
        if self.platform != "telegram":
            return Screen(
                "✅ دسترسی‌های سبد فعال شده‌اند. فایل‌های محافظت‌شده فقط از تلگرام ارسال می‌شوند.",
                keyboard([button("🏠 خانه", action="home")]),
            )
        if self.media_dispatcher is None:
            return Screen(
                frame_error("صف ارسال محافظت‌شده فعلاً در دسترس نیست."),
                keyboard([button("بررسی دوباره", action=f"cart-deliver:{order_token}"), button("🏠 خانه", action="home")]),
            )

        jobs = cart_media_requests(
            self.state,
            [dict(item) for item in checkout.get("items", []) if isinstance(item, dict)],
        )
        if not jobs:
            return Screen(
                "✅ همهٔ دسترسی‌های این سبد فعال شده‌اند و فایل جداگانه‌ای برای ارسال ندارد.",
                keyboard(
                    [button("جزئیات پرداخت", action=f"payment-status:{order_token}")],
                    [button("🏠 خانه", action="home")],
                ),
            )

        statuses = self.media_dispatcher.enqueue_batch(
            user_id,
            [(source_type, source_id) for _item_key, source_type, source_id in jobs],
        )
        grouped: dict[str, list[str]] = {}
        for (item_key, _source_type, _source_id), status in zip(jobs, statuses):
            grouped.setdefault(item_key, []).append(status)
        for item_key, item_statuses in grouped.items():
            self.state.mark_commerce_cart_fulfillment(
                order_token,
                item_key,
                kind="media",
                status="queued" if any(status in {"queued", "duplicate"} for status in item_statuses) else "activated",
                detail={
                    "queued": sum(status == "queued" for status in item_statuses),
                    "duplicate": sum(status == "duplicate" for status in item_statuses),
                    "full": sum(status == "full" for status in item_statuses),
                },
            )

        queued = sum(status == "queued" for status in statuses)
        duplicate = sum(status == "duplicate" for status in statuses)
        deferred = sum(status in {"full", "rate-limited", "cooldown"} for status in statuses)
        failed = sum(status in {"missing", "denied"} for status in statuses)
        lines = [
            "<b>📥 ارسال خریدهای سبد</b>",
            "",
            f"در صف امن: <b>{to_persian_digits(queued)}</b>",
        ]
        if duplicate:
            lines.append(f"از قبل در صف: <b>{to_persian_digits(duplicate)}</b>")
        if deferred:
            lines.append(f"نیازمند تلاش دوباره: <b>{to_persian_digits(deferred)}</b>")
        if failed:
            lines.append(f"نیازمند بررسی: <b>{to_persian_digits(failed)}</b>")
        lines.extend(
            (
                "",
                "<blockquote>فایل‌ها به همان ترتیب سبد، با بررسی دوبارهٔ مجوز و بدون ارسال موازیِ تکراری وارد صف می‌شوند.</blockquote>",
            )
        )
        rows = []
        if deferred or failed:
            rows.append([button("↻ تلاش برای باقی‌مانده‌ها", action=f"cart-deliver:{order_token}", style="primary")])
        rows.append([button("جزئیات پرداخت", action=f"payment-status:{order_token}")])
        rows.append([button("🏠 خانه", action="home")])
        return Screen("\n".join(lines), keyboard(*rows))

    def _cart_route(
        self,
        name: str,
        user_id: int,
        *,
        request_id: str = "",
    ) -> Screen | None:
        del request_id
        if name == "cart":
            snapshot = self._cart_snapshot(user_id, refresh_account=True)
            if snapshot.get("identityMissing"):
                identity, account = self._cart_identity(user_id, refresh=True)
                if identity is None:
                    return self._unlinked_access_screen(user_id, account)
            return cart_screen(snapshot)

        if name.startswith("cart-add:"):
            offer_ref = name.split(":", 1)[1]
            offer = self.state.payment_offer(offer_ref, require_active=True)
            if offer is None:
                return Screen(frame_error("این محصول دیگر قابل خرید نیست."), self._screen("home", user_id).keyboard)
            return self._cart_add_descriptor(
                user_id,
                {"kind": "offer", "offerRef": offer_ref},
                title=str(offer.get("title") or "محصول"),
            )

        if name.startswith("cart-add-ai:"):
            parts = name.split(":")
            if len(parts) != 4 or not parts[3].isdigit():
                return Screen(frame_error("مسیر افزودن جزوه معتبر نیست."), self._screen("home", user_id).keyboard)
            course_key = parts[2]
            session_no = int(parts[3])
            catalog = self._booklet_catalog(user_id)
            course = course_by_key(catalog, course_key)
            session = session_by_number(course or {}, session_no) if course is not None else None
            if course is None or session is None:
                return Screen(frame_error("جلسه پیدا نشد."), self._screen("home", user_id).keyboard)
            term = int(catalog.get("term") or 7)
            descriptor = {
                "kind": "ai_booklet",
                "offerRef": ai_booklet_offer_ref(term, course_key, session_no),
                "term": term,
                "courseCode": course_key,
                "courseTag": str(course.get("bookletTag") or ""),
                "sessionNo": session_no,
            }
            return self._cart_add_descriptor(
                user_id,
                descriptor,
                title=f"جزوه هوش مصنوعی {str(course.get('courseTitle') or 'درس')} · جلسه {to_persian_digits(session_no)}",
            )

        if name.startswith("cart-add-subscription:"):
            term_text = name.rsplit(":", 1)[1]
            if not term_text.isdigit():
                return Screen(frame_error("مسیر افزودن اشتراک معتبر نیست."), self._screen("home", user_id).keyboard)
            term = int(term_text)
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("اشتراک این ترم پیدا نشد."), self._screen("home", user_id).keyboard)
            period = billing_period_for(term)
            descriptor = {
                "kind": "term_subscription",
                "offerRef": str(policy.get("offerRef") or ""),
                "term": term,
                "billingPeriod": period.key,
            }
            return self._cart_add_descriptor(
                user_id,
                descriptor,
                title=f"اشتراک جزوات ترم {to_persian_digits(term)} · {period.month_label}",
            )

        if name.startswith("cart-remove:"):
            parts = name.split(":")
            if len(parts) != 3 or not parts[1].isdigit() or not parts[2].isdigit():
                return Screen(frame_error("درخواست حذف معتبر نیست."), self._screen("home", user_id).keyboard)
            identity, account = self._cart_identity(user_id, refresh=True)
            if identity is None:
                return self._unlinked_access_screen(user_id, account)
            blocked = self._cart_mutation_blocker(user_id, identity.subject_key)
            if blocked is not None:
                return blocked
            cart = self.state.commerce_cart(identity.subject_key)
            if int(cart.get("version") or 0) != int(parts[2]):
                return cart_screen(self._cart_snapshot(user_id), notice="سبد تغییر کرده بود؛ نسخهٔ تازه نمایش داده شد.")
            index = int(parts[1])
            descriptors = [item for item in cart.get("items", []) if isinstance(item, dict)]
            if not 0 <= index < len(descriptors):
                return cart_screen(self._cart_snapshot(user_id), notice="این مورد دیگر در سبد نیست.")
            self.state.commerce_cart_remove(identity.subject_key, cart_item_key(descriptors[index]))
            return cart_screen(self._cart_snapshot(user_id), notice="محصول از سبد حذف شد.")

        if name.startswith("cart-clear:"):
            version_text = name.rsplit(":", 1)[1]
            identity, account = self._cart_identity(user_id, refresh=True)
            if identity is None:
                return self._unlinked_access_screen(user_id, account)
            blocked = self._cart_mutation_blocker(user_id, identity.subject_key)
            if blocked is not None:
                return blocked
            cart = self.state.commerce_cart(identity.subject_key)
            if not version_text.isdigit() or int(cart.get("version") or 0) != int(version_text):
                return cart_screen(self._cart_snapshot(user_id), notice="سبد تغییر کرده بود؛ نسخهٔ تازه نمایش داده شد.")
            self.state.commerce_cart_clear(identity.subject_key)
            return cart_screen(self._cart_snapshot(user_id), notice="سبد خرید خالی شد.")

        if name == "cart-discount":
            snapshot = self._cart_snapshot(user_id, refresh_account=True)
            identity = snapshot.get("identity")
            if identity is None:
                return self._unlinked_access_screen(user_id, dict(snapshot.get("account") or {}))
            blocked = self._cart_mutation_blocker(user_id, identity.subject_key)
            if blocked is not None:
                return blocked
            self.state.start_dialog(
                user_id,
                "cart-discount-user",
                "code",
                {"subjectKey": identity.subject_key},
            )
            return cart_discount_prompt_screen(current_code=str(snapshot.get("discountCode") or ""))

        if name == "cart-discount-remove":
            identity, account = self._cart_identity(user_id, refresh=True)
            if identity is None:
                return self._unlinked_access_screen(user_id, account)
            blocked = self._cart_mutation_blocker(user_id, identity.subject_key)
            if blocked is not None:
                return blocked
            self.state.commerce_cart_set_discount(identity.subject_key, "")
            self.state.clear_dialog(user_id)
            return cart_screen(self._cart_snapshot(user_id), notice="کد تخفیف از سبد حذف شد.")

        if name == "cart-checkout":
            return self._cart_checkout_screen(user_id)

        if name.startswith("cart-deliver:"):
            return self._cart_delivery_screen(user_id, name.split(":", 1)[1])

        if name == "discount-codes" and user_id == self.owner_id:
            return discount_codes_screen(self.state.commerce_discount_codes())
        if name == "discount-new" and user_id == self.owner_id:
            return discount_new_kind_screen()
        if name.startswith("discount-new-kind:") and user_id == self.owner_id:
            kind = name.rsplit(":", 1)[1]
            if kind not in {"percent", "fixed"}:
                return discount_new_kind_screen()
            self.state.start_dialog(user_id, "commerce-discount-new", "amount", {"kind": kind})
            from .cart_ui import discount_new_prompt_screen
            return discount_new_prompt_screen("amount", {"kind": kind})
        if name.startswith("discount-toggle:") and user_id == self.owner_id:
            code = name.split(":", 1)[1]
            item = self.state.commerce_discount_code(code, require_active=False)
            if item is None:
                return discount_codes_screen(self.state.commerce_discount_codes())
            self.state.set_commerce_discount_code_active(code, not bool(item.get("configuredActive")))
            return discount_codes_screen(self.state.commerce_discount_codes())

        return None
