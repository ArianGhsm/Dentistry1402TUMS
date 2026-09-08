#!/usr/bin/env python3
import json, pathlib, re
root=pathlib.Path(__file__).resolve().parents[1]
contract=json.loads((root/'contracts/candidates/classops-delivery-v1.json').read_text())
assert contract['contractVersion']=='classops-delivery-v1'
assert contract['status']=='candidate'
assert contract['boundaries']['planningOnly'] is True
assert contract['boundaries']['directSend'] is False
assert contract['boundaries']['notificationStoreWrite'] is False
assert contract['boundaries']['parallelFeedAllowed'] is False
assert contract['boundaries']['rawPlatformIdentifiersInDomain'] is False
assert contract['retry']=={'maxAttempts':5,'baseBackoffSeconds':30,'maxBackoffSeconds':900}
assert contract['capabilityPolicy']['unknown']=='fail_closed'
php='\n'.join(p.read_text() for p in (root/'public_html/api/classops_modules/delivery').glob('*.php'))
for forbidden in ['notifications_enqueue_', 'notifications_with_store_lock', 'sendMessage', 'copyMessage', 'curl_exec']:
    assert forbidden not in php, forbidden
assert 'raw_platform_identifier_forbidden' in php
assert "'unknown'" in php and 'classops-delivery-adapter-v1' in php
assert not re.search(r"bindingRef\s*['\"]?\s*=>\s*['\"]-?\d{5,}", php)
print('OK: delivery candidate contract and boundary scan passed')
