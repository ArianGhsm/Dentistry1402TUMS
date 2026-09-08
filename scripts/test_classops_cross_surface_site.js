'use strict';
const assert = require('assert');
const ops = require('../public_html/assets/classops_ops/classops_ops.js');

assert.strictEqual(ops.CONTRACT_VERSION, 'classops-surface-v1');
assert.strictEqual(ops.ACTIONS['draft.create'].confirmation, true);
assert.strictEqual(ops.ACTIONS['item.archive'].revision, true);
assert.strictEqual(ops.FOUNDATION_CAPABILITIES['audience.resolve'], false);
assert.strictEqual(ops.FOUNDATION_CAPABILITIES['summary.weekly'], false);

const student = ops.studentViewModel({'exam.view': true, 'summary.tomorrow': true}, ['exam.view']);
assert.strictEqual(student.find((entry) => entry.action === 'exam.view').enabled, true);
assert.strictEqual(student.find((entry) => entry.action === 'summary.tomorrow').enabled, false);
assert.strictEqual(student.some((entry) => entry.action === 'draft.create'), false);

const intent = ops.buildIntent('item.archive', 'owner', {
    itemId:'item_1', expectedRevision:5, payload:{reasonCode:'cleanup'}, nonce:'stable-retry-nonce-001'
});
assert.strictEqual(intent.confirmationRequired, true);
assert.strictEqual(intent.confirmed, false);
assert.strictEqual(intent.expectedRevision, 5);
assert.match(intent.idempotencyKey, /^surface_[a-f0-9]{32}$/);
const confirmed = ops.confirmIntent(intent);
assert.strictEqual(confirmed.confirmed, true);
assert.strictEqual(confirmed.idempotencyKey, intent.idempotencyKey);

assert.throws(() => ops.buildIntent('item.cancel', 'student', {itemId:'x', expectedRevision:1, nonce:'student-nonce-001'}));
assert.throws(() => ops.buildIntent('ai.draft_request', 'owner', {payload:{chat_id:123}}));
assert.throws(() => ops.buildIntent('item.edit', 'owner', {itemId:'x', nonce:'missing-revision'}));

assert.deepStrictEqual(ops.diffItem({title:'الف', description:'قدیم'}, {description:'جدید'}), [
    {field:'description', before:'قدیم', after:'جدید'}
]);

const calls = [];
const fakeFetch = async (url, init) => {
    calls.push({url, init});
    if (String(url).includes('authSessions')) {
        return {ok:true, status:200, json:async () => ({success:true, csrfToken:'csrf-test-token-12345678901234567890'})};
    }
    return {ok:true, status:200, json:async () => ({success:true, item:{id:'x', status:'draft'}})};
};
const client = new ops.ClassOpsClient({fetchImpl:fakeFetch, endpoint:'https://example.test/api/classops_api.php'});
const createIntent = ops.confirmIntent(ops.buildIntent('draft.create', 'owner', {payload:{cohortKey:'dentistry-1402'}, nonce:'create-nonce-001'}));
(async () => {
    await client.createDraft(createIntent, {cohortKey:'dentistry-1402', type:'announcement', title:'نمونه', status:'active'}, 'test');
    assert.strictEqual(calls.length, 2);
    const body = JSON.parse(calls[1].init.body);
    assert.strictEqual(body.item.status, 'draft');
    assert.strictEqual(calls[1].init.headers['X-CSRF-Token'], 'csrf-test-token-12345678901234567890');
    assert.strictEqual(typeof client.audiencePreview, 'undefined');
    console.log('OK: classops website surface intents, role gates, confirmation, CSRF reuse and no-fake-capability behavior are consistent.');
})().catch((error) => { console.error(error); process.exit(1); });
