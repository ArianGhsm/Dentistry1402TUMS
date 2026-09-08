#!/usr/bin/env python3
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

registry = json.loads(read('contracts/classops-domain-contracts-v1.json'))
assert registry['compatibility']['historicalClassopsV1Readable'] is True
assert registry['compatibility']['audiencePlaceholderReadable'] is True
assert registry['compatibility']['deliveryPlaceholderReadable'] is True
assert registry['compatibility']['reminderPlaceholderReadable'] is True
assert registry['sourceOfTruth']['term7'] == 'academic_term7.php'
assert registry['sourceOfTruth']['notifications'] == 'existing-notification-subsystem'
assert registry['sourceOfTruth']['payments'] == 'existing-payment-subsystem'

foundation_schema = json.loads(read('contracts/classops-v1.schema.json'))
assert foundation_schema['properties']['contractVersion']['const'] == 'classops-v1'
assert foundation_schema['$defs']['audience']['properties']['version']['const'] == 'classops-audience-placeholder-v1'
assert foundation_schema['$defs']['delivery']['properties']['version']['const'] == 'classops-delivery-placeholder-v1'
assert foundation_schema['$defs']['reminder']['properties']['version']['const'] == 'classops-reminder-placeholder-v1'

facade = read('public_html/api/classops_modules/domain_facade.php')
for domain in ['audience', 'delivery', 'ai', 'tasks', 'exams', 'ack', 'scheduler', 'digests']:
    assert f'/{domain}/' in facade, f'{domain} is missing from unified domain facade'
assert "'directSend'=>false" in facade
assert "'directAiMutation'=>false" in facade

ai = read('public_html/api/classops_modules/ai/copilot.php').lower()
provider = read('public_html/api/classops_modules/ai/provider.php').lower()
assert "'confirmed' => false" in ai
assert "'mutationauthority' => 'none'" in ai
assert "'directsend' => false" in ai
for bad in ['voicematn', 'stt_api_key', 'voice_api_key']:
    assert bad not in provider, f'AI provider must not reuse voice/STT credential: {bad}'

registry_source = read('public_html/api/classops_modules/delivery/destination_registry.php').lower()
delivery = read('public_html/api/classops_modules/delivery/delivery_planner.php').lower()
for forbidden in ['chat_id', 'telegram_id', 'bale_id', 'bot_token']:
    assert forbidden in registry_source, f'destination registry must explicitly reject {forbidden}'
assert 'sendmessage(' not in delivery
assert 'notifications_store.php' not in delivery
assert "['completed','cancelled','archived']" in delivery.replace(' ', '')
assert 'supersedesintentid' in delivery

ack = read('public_html/api/classops_modules/ack/critical_ack.php').lower()
assert 'transport delivery/read receipt is not an acknowledgement' in ack
assert 'noticeversion' in ack or "['revision']" in ack
exam_test = read('scripts/test_classops_exam_ack_domain.php')
assert 'Revised notice invalidates prior revision satisfaction.' in exam_test
assert 'Platform read receipt cannot become ACK.' in exam_test

scheduler = read('public_html/api/classops_modules/scheduler/classops_reminder_planner.php').lower()
assert 'single_active_leader' in scheduler
assert 'planner side' not in scheduler or 'sideeffectowner' in scheduler
assert 'classops_reminder_assert_no_credentials' in scheduler
for forbidden in ['saba_password', 'saba_username', 'saba_token', 'saba_session']:
    assert forbidden not in scheduler

scheduler_test = read('scripts/test_classops_scheduler.php').lower()
for marker in ['saba', 'supersed', 'credential']:
    assert marker in scheduler_test, f'scheduler tests must cover {marker}'

 tasks = read('public_html/api/classops_modules/tasks/task_domain.php')
