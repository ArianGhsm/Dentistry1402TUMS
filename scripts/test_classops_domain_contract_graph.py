#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
registry = json.loads((root / 'contracts/classops-domain-contracts-v1.json').read_text(encoding='utf-8'))
assert registry['contractVersion'] == 'classops-domain-contracts-v1'
assert registry['baseContract'] == 'classops-v1'
assert registry['compatibility']['historicalClassopsV1Readable'] is True
assert registry['compatibility']['productionDataMigrationPerformed'] is False

expected = {
    'audience': 'classops-audience-v1',
    'delivery': 'classops-delivery-v1',
    'aiDraft': 'classops-ai-draft-v1',
    'tasksRequirements': 'classops-tasks-v1',
    'examAck': 'classops-exam-ack-v1',
    'reminder': 'classops-reminder-v1',
    'digest': 'classops-digest-v1',
}
for key, version in expected.items():
    entry = registry['domains'][key]
    assert entry['version'] == version
    assert entry['promotion'] == 'stage1-approved-domain'
    schema = root / entry['schema']
    assert schema.is_file(), f'missing schema for {key}: {schema}'
    json.loads(schema.read_text(encoding='utf-8'))

assert registry['futureSurface']['promotion'] == 'stage2-only-not-merged'
assert not (root / 'public_html/classops/index.html').exists(), 'cross-surface UX must remain Stage2-only'
assert (root / 'public_html/api/classops_modules/tasks/task_domain.php').is_file(), 'tasks gap not closed'

source_map = registry['sourceOfTruth']
assert source_map['identity'] == 'auth_store.php'
assert source_map['term7'] == 'academic_term7.php'
assert source_map['notifications'] == 'existing-notification-subsystem'
assert source_map['payments'] == 'existing-payment-subsystem'

for path in [
    root / 'public_html/api/classops_modules/delivery/delivery_planner.php',
    root / 'public_html/api/classops_modules/scheduler/classops_reminder_planner.php',
]:
    text = path.read_text(encoding='utf-8').lower()
    assert 'sendmessage(' not in text
    assert 'notifications_store' not in text

reminder_text = (root / 'public_html/api/classops_modules/scheduler/classops_reminder_planner.php').read_text(encoding='utf-8').lower()
for forbidden in ['saba_password', 'saba_username', 'saba_token']:
    assert forbidden not in reminder_text

print('ClassOps domain contract graph tests passed')
