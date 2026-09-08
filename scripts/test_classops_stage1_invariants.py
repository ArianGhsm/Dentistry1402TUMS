#!/usr/bin/env python3
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

registry = json.loads(read('contracts/classops-domain-contracts-v1.json'))
assert registry['stage'] in {'integration-stage1', 'integration-stage2'}
assert registry['compatibility']['historicalClassopsV1Readable'] is True
assert registry['compatibility']['audiencePlaceholderReadable'] is True
assert registry['compatibility']['deliveryPlaceholderReadable'] is True
assert registry['compatibility']['reminderPlaceholderReadable'] is True
assert registry['compatibility']['productionDataMigrationPerformed'] is False
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
exam_test = read('scripts/test_classops_exam_ack_domain.php')
assert 'Revised notice invalidates prior revision satisfaction.' in exam_test
assert 'Platform read receipt cannot become ACK.' in exam_test
assert 'Exam rejects duplicate verified payment state.' in exam_test

scheduler = read('public_html/api/classops_modules/scheduler/classops_reminder_planner.php').lower()
assert 'single_active_leader' in scheduler
assert 'classops_reminder_assert_no_credentials' in scheduler
for forbidden in ['saba_password', 'saba_username', 'saba_token', 'saba_session']:
    assert forbidden not in scheduler
scheduler_test = read('scripts/test_classops_scheduler.php').lower()
for marker in ['saba', 'supersed', 'credential']:
    assert marker in scheduler_test, f'scheduler tests must cover {marker}'

tasks = read('public_html/api/classops_modules/tasks/task_domain.php').lower()
for state in ['pending', 'submitted', 'needs_revision', 'completed', 'waived']:
    assert state in tasks
assert 'classops_task_student_projection' in tasks
assert 'classops_task_is_overdue' in tasks
assert 'submissioncontent' in tasks  # forbidden-content guard

audience = read('public_html/api/classops_modules/audience/resolver.php').lower()
audience_spec = read('public_html/api/classops_modules/audience/spec.php').lower()
assert 'classops_audience_drift' in audience
assert 'display' not in audience_spec or 'displayname' not in audience_spec
assert "['role', 'group', 'category']" in audience_spec
assert "'not'" in audience_spec and "'any'" in audience_spec and "'all'" in audience_spec

digest = read('public_html/api/classops_modules/digests/digest_engine.php').lower()
assert 'classops_digest_visible' in digest
assert "record['cohortkey'] !== $viewer['cohortkey']" in digest
assert "hash_equals" in digest
assert 'academic_term7' not in digest, 'digest must consume injected schedule projection, not import Term7 store directly'

store = read('public_html/api/classops_store.php')
assert "const CLASSOPS_CONTRACT_VERSION = 'classops-v1';" in store
assert 'classops-audience-placeholder-v1' in store
assert 'classops-delivery-placeholder-v1' in store
assert 'classops-reminder-placeholder-v1' in store

# The Stage 1 invariants remain frozen, but Stage 2 is allowed to add the
# promoted cross-surface product only when the registry explicitly says so.
if registry['stage'] == 'integration-stage1':
    assert not (ROOT / 'public_html/classops/index.html').exists()
    assert registry['futureSurface']['promotion'] == 'stage2-only-not-merged'
else:
    assert registry['surface']['promotion'] == 'stage2-approved-surface'
    assert (ROOT / 'contracts/classops-surface-v1.json').is_file()
    assert (ROOT / 'public_html/classops/index.html').is_file()
    assert (ROOT / 'public_html/api/classops_stage2_store.php').is_file()
    assert (ROOT / 'public_html/api/classops_stage2/owner_workflow.php').is_file()
    assert (ROOT / 'public_html/api/classops_stage2/student_workflow.php').is_file()
    assert (ROOT / 'public_html/api/classops_stage2_scheduler.php').is_file()

print('ClassOps Stage 1 invariants preserved; Stage 2 promotion state is coherent')
