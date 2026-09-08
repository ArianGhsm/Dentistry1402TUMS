#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[1]
api = (root / 'public_html/api/classops_api.php').read_text(encoding='utf-8')
adapter = (root / 'public_html/api/classops_domain_store_adapter.php').read_text(encoding='utf-8')
facade = (root / 'public_html/api/classops_modules/domain_facade.php').read_text(encoding='utf-8')

assert "require_once __DIR__ . '/classops_domain_store_adapter.php';" in api
assert "require_once __DIR__ . '/classops_modules/domain_facade.php';" in api
assert "$action === 'domain-capabilities'" in api
assert 'classops_domain_capabilities()' in api
assert 'classops_domain_store_create_item(' in api
assert 'classops_domain_store_update_item(' in api

# Stage 1 API must remain mutation-thin: no delivery send, no scheduler execution,
# no notification feed write, and no direct AI provider invocation.
for forbidden in ['sendmessage(', 'notifications_store.php', 'createcandidate(', 'postjson(']:
    assert forbidden not in api.lower(), f'forbidden Stage1 side effect in API: {forbidden}'

assert 'classops_task_normalize_extension' in adapter
assert 'classops_exam_normalize_extension' in adapter
assert 'unknown historical namespaces' in adapter
assert 'classops-audience-v1' not in adapter, 'promoted audience v1 must not reinterpret frozen item placeholder in storage adapter'
assert "'directSend'=>false" in facade
assert "'directAiMutation'=>false" in facade

print('ClassOps Stage 1 API/store wiring contract passed')
