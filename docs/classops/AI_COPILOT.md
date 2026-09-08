# ClassOps AI Copilot — preview-only structured draft

Status: **feature module, not centrally wired**. This branch implements a dormant ClassOps AI producer and candidate contract only. It does not change `classops-v1`, `classops_api.php`, `classops_store.php`, bot runtime routing, notification delivery, or production configuration.

## Boundary

The copilot converts owner-authored operational text, optionally with forwarded text quoted as untrusted data, into `classops-structured-draft-v1` for owner preview. It has no authority to:

- create/update/archive a ClassOps item;
- resolve student identity or audience membership;
- resolve a course reference;
- normalize a human date/time into canonical UTC;
- enqueue a notification or send Telegram/Bale messages;
- access payment, Term 7, booklet, bot-link, or runtime state.

The frozen boundary in `contracts/shared-boundaries-v1.json` remains unchanged. The more concrete JSON schema in `contracts/candidates/classops-ai-draft-v1.json` is an **integration candidate**, not a silent modification of the frozen shared contract.

## Files

- `public_html/api/classops_modules/ai/errors.php` — isolated error type/codes.
- `public_html/api/classops_modules/ai/contract.php` — deterministic structured-draft validator and preview/provenance checks.
- `public_html/api/classops_modules/ai/provider.php` — AvalAI/DeepSeek client plus injectable transport; production cURL transport is present but unused until integration wires the module.
- `public_html/api/classops_modules/ai/copilot.php` — create/edit orchestration, draft versioning, field-level provenance, aggregate telemetry sanitation.
- `contracts/candidates/classops-ai-draft-v1.json` — strict candidate schema.
- `scripts/test_classops_ai_copilot.php` and `scripts/test_classops_ai_contract.py` — offline deterministic tests; no provider network required.

## Provider contract

AvalAI exposes an OpenAI-compatible API at `https://api.avalai.ir/v1`; this module uses the compatible `POST /v1/chat/completions` route and requests a JSON object response. The endpoint is fixed in source to avoid arbitrary credential-bearing redirects/SSRF. The model is deliberately not hard-coded: integration must pin a reviewed DeepSeek model ID through runtime configuration.

Required runtime expectations:

- `DENT_CLASSOPS_AI_AVALAI_API_KEY` — dedicated ClassOps AI credential.
- `DENT_CLASSOPS_AI_MODEL` — explicitly selected/pinned DeepSeek model.

Optional runtime expectations:

- `DENT_CLASSOPS_AI_TIMEOUT_MS` — default `12000`, accepted range 1000–30000 ms.
- `DENT_CLASSOPS_AI_INPUT_USD_PER_MILLION` — optional current input-token rate used only for an estimate.
- `DENT_CLASSOPS_AI_OUTPUT_USD_PER_MILLION` — optional current output-token rate used only for an estimate.

The module intentionally ignores generic `AVALAI_API_KEY`, VoiceMatn/STT, Eboo, speech, and other product credentials. There is no credential fallback. No secret is committed and `.env.example` is an integration-only hotspot, so it is not changed here.

## Authority separation and prompt injection

The provider request has two layers:

1. a fixed system policy defining the schema and zero mutation/resolution/send authority;
2. one JSON data object carrying owner instruction, forwarded operational text, or an edit request.

Forwarded text is explicitly classified as quoted, untrusted data. Instructions inside forwarded text cannot elevate authority. The provider output is never trusted merely because the prompt asked for JSON: `contract.php` validates every field after the model response.

Deterministic rejection includes:

- extra top-level or nested fields;
- unsupported type/importance/destination enums;
- student numbers or any non-empty audience refs;
- resolved audience mode;
- resolved course `ref`;
- normalized `startsAt`, `endsAt`, `dueAt`, or timezone emitted by the AI;
- oversized/control-character text;
- invalid/missing preview and provenance fields.

Human clues can remain as `course.rawText`, `timing.rawText`, `audience.rawText`, or `reminderHint`. Deterministic integration components may resolve them later. Unknown/uncertain scalar values remain `null`.

## Draft/edit model

A final preview draft contains:

- `contractVersion = classops-structured-draft-v1`;
- monotonic `draftVersion`;
- operation (`create` or `edit`);
- trusted, non-AI context such as a canonical `cohortKey` when supplied by the owner workflow;
- the strict `fields` object;
- `changedFields` and `unresolved` lists;
- provider/model/parser/prompt provenance;
- `fieldOrigins`, recording the draft version that last explicitly changed each top-level field;
- a fixed preview block: `required=true`, `confirmed=false`, `mutationAuthority=none`, `directSend=false`.

For natural-language edits, the model must return the complete fields object plus only the fields the owner explicitly asked to change. The deterministic validator compares all undeclared fields with the previous draft and rejects any hidden modification. The copilot then increments `draftVersion`, records `parentDraftVersion`, and updates only changed field origins.

The AI module does not implement a `confirm()` function. Confirmation belongs to a future owner UI/bot management integration, which must visibly preview the validated draft and perform a separate owner action before invoking canonical ClassOps mutation APIs.

## Failure behavior

The client fails closed on:

- missing dedicated credential or model configuration;
- transport failure or timeout;
- HTTP 429;
- any non-2xx provider response;
- malformed provider JSON;
- missing/invalid completion shape;
- a completion whose `finish_reason` is not `stop`;
- code-fenced, malformed, list, extra-field, oversized, or schema-invalid model output;
- invalid usage telemetry.

No retry loop is implemented in this feature module. Retry/backoff policy belongs to integration so it can share owner-request idempotency/cancellation semantics and avoid multiplying provider cost.

## Telemetry and retention

The copilot returns only this aggregate telemetry object to its caller:

- provider;
- model;
- latency in milliseconds;
- prompt/completion/total token counts when reported;
- optional cost estimate if current token rates were supplied through runtime configuration.

No raw prompt, forwarded text, draft text, identity, phone, national code, model response body, or provider credential is placed in telemetry or logs by this module. The transport emits no application logs. Integration must preserve that retention rule if it records aggregate metrics.

## Tests

Offline focused suite:

```bash
php scripts/test_classops_ai_copilot.php
python scripts/test_classops_ai_contract.py
```

Coverage includes valid drafts, null-on-unknown behavior, identity/audience/date/course resolution rejection, enum/extra-field rejection, malformed/partial JSON, timeout/rate limit, forwarded prompt injection isolation, edit preservation, preview-only enforcement, zero mutation/send dependencies, dedicated credential/no fallback, non-sensitive telemetry, and deterministic validation without a model.

The existing Foundation and shared-contract tests remain integration regression gates and are not modified by this branch.
