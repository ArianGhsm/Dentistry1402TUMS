#!/usr/bin/env python3
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
registry = json.loads((root / 'contracts/classops-domain-contracts-v1.json').read_text(encoding='utf-8'))
assert registry['contractVersion'] == 'classops-domain-contracts-v1'
assert registry['stage'] == 'integration-stage2'
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

def git_blob_sha(data: bytes) -> str:
    return hashlib.sha1(b'blob ' + str(len(data)).encode('ascii') + b'\0' + data).hexdigest()

for key, version in expected.items():
    entry = registry['domains'][key]
    assert entry['version'] == version
    assert entry['promotion'] == 'stage1-approved-domain'
    schema = root / entry['schema']
    assert schema.is_file(), f'missing canonical contract for {key}: {schema}'
    assert '/candidates/' not in schema.as_posix(), f'{key} was not promoted to canonical contracts/'
    json.loads(schema.read_text(encoding='utf-8'))

    origin = entry['candidateOrigin']
    candidate = root / origin['path']
    assert candidate.is_file(), f'missing recorded candidate origin for {key}'
    canonical_bytes = schema.read_bytes()
    candidate_bytes = candidate.read_bytes()
    assert canonical_bytes == candidate_bytes, f'{key} canonical promotion drifted from locked candidate origin'
    assert git_blob_sha(candidate_bytes) == origin['blobSha'], f'{key} candidate origin blob SHA drifted'

surface = registry['surface']
assert surface['version'] == 'classops-surface-v1'
assert surface['promotion'] == 'stage2-approved-surface'
assert surface['reconciledFromCandidate'] is True
surface_schema = root / surface['schema']
assert surface_schema.is_file(), 'Stage2 canonical surface contract is missing'
surface_doc = json.loads(surface_schema.read_text(encoding='utf-8'))
assert surface_doc['contractVersion'] == 'classops-surface-v1'
assert surface_doc['status'] == 'stage2-approved'
assert surface_doc['principles']['ownerPreviewConfirmRequired'] is True
assert surface_doc['principles']['rawPlatformIdsInDomain'] is False
assert surface_doc['principles']['criticalAckAuthority'] == 'explicit-application-state-only'
for action in [
    'draft.validate', 'ai.draft_create', 'ai.draft_edit', 'item.preview',
    'item.confirm_create', 'item.confirm_update', 'student.task_transition',
    'student.critical_ack', 'summary.tomorrow', 'summary.weekly',
    'runtime.delivery_claim', 'runtime.delivery_ack', 'runtime.scheduler_tick',
]:
    assert action in surface_doc['definitions']['actions'], f'missing Stage2 surface action: {action}'

candidate = root / surface['candidateOrigin']['path']
assert candidate.is_file(), 'surface candidate origin is missing'
assert git_blob_sha(candidate.read_bytes()) == surface['candidateOrigin']['blobSha'], 'surface candidate origin blob SHA drifted'

# In Stage2 the previously forbidden UX is required, but only when the real
# application/API/runtime wiring exists. This replaces the Stage1-only
# "surface must not exist" assertion instead of disabling it.
assert (root / 'public_html/classops/index.html').is_file(), 'Stage2 website ClassOps surface is missing'
assert (root / 'public_html/assets/classops_ops/classops_ops.js').is_file(), 'Stage2 website application asset is missing'
assert (root / 'public_html/api/classops_stage2_store.php').is_file(), 'Stage2 canonical side-state store is missing'
assert (root / 'public_html/api/classops_stage2/owner_workflow.php').is_file(), 'Stage2 owner workflow is missing'
assert (root / 'public_html/api/classops_stage2/student_workflow.php').is_file(), 'Stage2 student workflow is missing'
assert (root / 'public_html/api/classops_stage2_scheduler.php').is_file(), 'Stage2 scheduler coordinator is missing'
assert (root / 'bot_runtime/dent_bot/classops_surface/model.py').is_file(), 'Stage2 bot semantic surface is missing'
assert (root / 'public_html/api/classops_modules/tasks/task_domain.php').is_file(), 'tasks gap not closed'

source_map = registry['sourceOfTruth']
assert source_map['identity'] == 'auth_store.php'
assert source_map['term7'] == 'academic_term7.php'
assert source_map['notifications'] == 'existing-notification-subsystem'
assert source_map['payments'] == 'existing-payment-subsystem'
assert source_map['classops'] == 'canonical-classops-storage-family'

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

print('ClassOps Stage2 domain/surface contract graph tests passed')
