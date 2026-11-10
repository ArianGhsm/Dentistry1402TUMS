from __future__ import annotations
import unittest
from dent_bot.app import DentBotApp
from dent_bot.cart import commerce_identity_from_account
from dent_bot.subscriptions import subscription_identity_from_account


def account(verified=True, class_member=False, number="40011272030"):
    return {"linked": False, "authComplete": False, "onboardingProfileRef": "verifiedProfileReference1234",
            "onboardingProfile": {"verifiedAt": "2026-11-10T20:05:57+03:30" if verified else "",
                                  "isClassMember": class_member, "studentNumber": number,
                                  "firstName": "کاربر", "lastName": "آزاد"}}


class SubscriptionExternalIdentityTests(unittest.TestCase):
    def test_verified_nonclass_onboarding_uses_same_identity_as_commerce(self):
        external = account()
        subscription = subscription_identity_from_account(external)
        commerce = commerce_identity_from_account(external)
        self.assertIsNotNone(subscription)
        self.assertEqual(subscription.subject_key, commerce.subject_key)
        self.assertEqual(subscription.student_number, "40011272030")
        self.assertIsNone(subscription_identity_from_account(account(verified=False)))
        self.assertIsNone(subscription_identity_from_account(account(class_member=True)))
        self.assertIsNotNone(subscription_identity_from_account(account(number="")))

    def test_subscription_deep_link_is_a_registered_start_route(self):
        from pathlib import Path
        body = Path(__file__).resolve().parents[1].joinpath("dent_bot/feature_router.py").read_text()
        self.assertIn('== "/start subscription_7"', body)
        self.assertIn('app._dynamic_screen("term-subscription", user_id)', body)

    def test_buy_and_info_do_not_demand_a_class_link(self):
        for action in ("term-subscription", "term-subscription:7",
                       "term-subscription-buy:7", "term-subscription-info:7",
                       "cart", "cart-checkout", "cart-add-subscription:7"):
            self.assertFalse(DentBotApp._requires_canonical_link(action), action)
        for action in ("grades", "profile-edit", "term-subscription-admin:7"):
            self.assertTrue(DentBotApp._requires_canonical_link(action), action)

import json
from types import SimpleNamespace
from dent_bot.subscription_booklet_delivery import REQUEST_PREFIX, dispatch_verified_subscription_booklets


class DeliveryState:
    def __init__(self, paid=False, complete=True):
        self.paid, self.complete = paid, complete
        self.entries = {}

    def runtime_values_with_prefix(self, prefix):
        return {k: v for k, v in self.entries.items() if k.startswith(prefix)}

    def set_runtime_value(self, key, value):
        self.entries[key] = value

    def term_access_decision(self, _subject, _term):
        return {"paidValid": self.paid}

    def protected_media_session_counts_for_tag(self, **_kwargs):
        return {n: 1 for n in range(1, 8 if self.complete else 7)}

    def protected_media_for_tag(self, *, session_no, **_kwargs):
        return [{"id": 500+session_no, "sessionNo": session_no,
                 "contentKind": "booklet", "term": 7,
                 "courseTag": "مبانی_پروتز_پارسیل", "fileUniqueId": f"unique-{session_no}",
                 "sourceChatId": -100100, "sourceMessageId": 100+session_no,
                 "telegramMethod": "sendDocument", "mimeType": "application/pdf",
                 "fileName": f"partial-{session_no}.pdf", "fileId": f"file-{session_no}"}]


class DeliverySite:
    def __init__(self, profile):
        self.profile = profile

    def account(self, _id):
        return self.profile

    def booklet_catalog(self, _id):
        return {"term": 7, "courses": [{"courseKey": "partial-basics-theory",
            "courseTitle": "مبانی پارسیل نظری", "bookletTag": "مبانی_پروتز_پارسیل",
            "sessions": [{"sessionNumber": n} for n in range(1, 16)]}]}


class DeliveryMedia:
    def __init__(self, status="queued"):
        self.status, self.calls = status, []

    def enqueue_batch(self, uid, requests):
        self.calls.append((uid, requests))
        return [self.status] * len(requests)


class DeliveryApi:
    def __init__(self):
        self.sent=[]

    def send(self, *args):
        self.sent.append(args)


class SubscriptionDeliveryTests(unittest.TestCase):
    def run_job(self, paid=False, complete=True, media_status="queued", profile=None, state=None):
        state = state or DeliveryState(paid, complete)
        key = f"{REQUEST_PREFIX}telegram:99"
        if key not in state.entries:
            identity = subscription_identity_from_account(account())
            state.entries[key] = json.dumps({"status":"pending", "platform":"telegram",
                "userId":99, "subjectKey":identity.subject_key, "term":7,
                "courseKey":"partial-basics-theory", "maxSession":7})
        api, media = DeliveryApi(), DeliveryMedia(media_status)
        counts = dispatch_verified_subscription_booklets(
            settings=SimpleNamespace(platform="telegram"), api=api,
            state=state, site_api=DeliverySite(profile or account()), media_dispatcher=media)
        return counts,state,media,api,key

    def test_payment_pending_never_triggers_booklets(self):
        result,state,media,api,key=self.run_job()
        self.assertEqual(result["queued"],0)
        self.assertFalse(media.calls)
        self.assertFalse(api.sent)
        self.assertEqual(json.loads(state.entries[key])["status"],"pending")

    def test_paid_queues_seven_ordinary_files_once(self):
        result,state,media,api,key=self.run_job(paid=True)
        self.assertEqual(result["queued"],7)
        self.assertEqual(media.calls,[(99,[("booklet",i) for i in range(501,508)])])
        self.assertEqual(len(api.sent),1)
        self.assertIn("۷ جزوه",api.sent[0][1])
        self.assertEqual(json.loads(state.entries[key])["status"],"queued")
        again,_,retry_media,_,_=self.run_job(state=state)
        self.assertEqual(again["queued"],0)
        self.assertEqual(retry_media.calls,[])

    def test_changed_identity_missing_session_and_full_queue_fail_closed(self):
        for options in ({"paid":True,"profile":account(number="40011272031")},
                        {"paid":True,"complete":False},
                        {"paid":True,"media_status":"full"}):
            result,state,media,_,key=self.run_job(**options)
            self.assertEqual(result["queued"],0)
            self.assertEqual(json.loads(state.entries[key])["status"],"pending")
            if options.get("media_status")!="full": self.assertFalse(media.calls)
