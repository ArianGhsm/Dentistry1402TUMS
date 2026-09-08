#!/usr/bin/env python3
from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
api = (root / 'public_html/api/classops_api.php').read_text(encoding='utf-8')
adapter = (root / 'public_html/api/classops_domain_store_adapter.php').read_text(encoding='utf-8')
facade = (root / 'public_html/api/classops_modules/domain_facade.php').read_text(encoding='utf-8')
registry = json.loads((root / 'contracts/classops-domain-contracts-v1.json').read_text(encoding='utf-8'))

assert "$action === 'domain-capabilities'" in api
assert 'classops_domain_capabilities()' in api
assert 'classops_capabilities()' in api, 'Foundation capability response must remain additive-compatible'

# Stage 1 Foundation storage semantics are still the mutation substrate even
# when Stage 2 routes through the integration-owned owner workflow.
assert 'classops_domain_store_create_item(' in adapter
assert 'classops_domain_store_update_item(' in adapter
assert 'classops_task_normalize_extension' in adapter
assert 'classops_exam_normalize_extension' in adapter
assert 'unknown historical namespaces' in adapter
assert 'classops-audience-v1' not in adapter, 'promoted audience v1 must not reinterpret frozen item placeholder in storage adapter'
assert "'directSend'=>false" in facade
assert "'directAiMutation'=>false" in facade

if registry['stage'] == 'integration-stage1':
    assert "require_once __DIR__ . '/classops_domain_store_adapter.php';" in api
    assert "require_once __DIR__ . '/classops_modules/domain_facade.php';" in api
    assert 'classops_domain_store_create_item(' in api
    assert 'classops_domain_store_update_item(' in api
else:
    assert registry['stage'] == 'integration-stage2'
    assert "require_once __DIR__ . '/classops_stage2/digests.php';" in api
    assert "require_once __DIR__ . '/classops_stage2_scheduler.php';" in api
    owner = (root / 'public_html/api/classops_stage2/owner_workflow.php').read_text(encoding='utf-8')
    assert 'classops_domain_store_create_item(' in owner
    assert 'classops_domain_store_update_item(' in owner
    assert 'classops_stage2_preview(' in owner
    assert 'classops_stage2_confirm(' in owner
    assert 'CLASSOPS_AUDIENCE_CONFIRMATION_HASH_REQUIRED' in owner
    assert 'classops_stage2_save_audience(' in owner

# The HTTP API itself never sends a transport message directly. Scheduler,
# delivery and AI implementations are delegated to bounded domain/application
# services, preserving the Stage 1 no-direct-side-effect boundary here.
for forbidden in ['sendmessage(', 'postjson(']:
    assert forbidden not in api.lower(), f'forbidden direct transport/provider side effect in API: {forbidden}'

print('ClassOps Foundation API/store invariants preserved through Stage 2 wiring')
