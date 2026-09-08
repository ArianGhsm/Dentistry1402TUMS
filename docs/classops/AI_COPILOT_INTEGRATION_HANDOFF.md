# Integration handoff — ClassOps AI Copilot

Branch scope stops before central wiring. The AI module is intentionally dormant until integration explicitly connects it.

## Candidate contract requiring integration decision

Review and either promote/version or reject:

- `contracts/candidates/classops-ai-draft-v1.json`

It refines the already-frozen `classops-structured-draft-v1` boundary without editing `contracts/shared-boundaries-v1.json`. Promotion should keep these invariants:

- unknown/uncertain values are `null`;
- canonical student identity and audience refs never originate from AI;
- course refs and normalized dates never originate from AI;
- `preview.required=true` and `preview.confirmed=false` for AI output;
- direct mutation and direct send remain impossible;
- parser/provider provenance remains auditable;
- telemetry remains aggregate and non-sensitive.

## Required central wiring — not performed here

### Website owner surface

Integration may add an owner-only AI draft/edit action at the existing ClassOps management surface. Any central route change belongs to integration-owned files such as `public_html/api/classops_api.php` and the appropriate owner UI.

Recommended flow:

1. existing owner authentication/CSRF gate succeeds;
2. owner input is passed to `DentClassOpsAiCopilot`;
3. the returned draft is rendered as escaped text in a preview UI;
4. deterministic resolvers separately resolve canonical cohort/course/date/audience/destination data as their contracts become available;
5. owner sees the fully resolved mutation payload and explicitly confirms;
6. only then the existing canonical ClassOps mutation API performs `create`/`update` with its existing idempotency/revision semantics.

Do not make the AI endpoint itself call `classops_create_item`, `classops_update_item`, notification enqueue, or delivery code.

### Telegram/Bale owner management

If owner bot management is added later, keep both platforms as thin adapters. The bot runtime should call a signed website-side owner service action using the existing runtime-site service authentication boundary; it should not receive or store the AvalAI credential and should not implement a second AI parser.

Hotspots intentionally untouched by this branch include:

- `public_html/api/bot_api.php`;
- `bot_runtime/dent_bot/site_api.py`;
- `bot_runtime/dent_bot/app.py`;
- `bot_runtime/dent_bot/runtime.py`;
- `bot_runtime/dent_bot/state.py`;
- central scheduler/router and service units.

Before exposing a bot AI action, integration must verify current owner identity/authorization on every request and must keep forwarded chat content as untrusted data. No raw Telegram/Bale chat ID belongs in the structured draft.

## Deterministic resolvers required before mutation

The AI module intentionally leaves these unresolved:

- `context.cohortKey` unless supplied as trusted canonical owner-workflow context;
- `course.ref`;
- normalized timing fields and timezone;
- `audience.mode` and all `audience.refs`;
- reminder offsets/scheduler decisions.

Integration should consume approved resolver contracts from the audience/delivery workstreams and canonical website sources. Do not implement display-name matching or copy `academic_term7.php` data into ClassOps. Term 7 remains canonical and ClassOps may only represent operational overlays/exceptions.

## Runtime configuration

Integration must provide, outside Git:

- `DENT_CLASSOPS_AI_AVALAI_API_KEY`;
- `DENT_CLASSOPS_AI_MODEL`.

Optionally provide timeout and current per-million-token rates documented in `AI_COPILOT.md`. Do not reuse VoiceMatn/STT/Eboo credentials and do not introduce fallback. If the dedicated key is absent, the capability must report unavailable rather than pretend AI is enabled.

`.env.example`, service units, deploy scripts, and dependency manifests are integration-only and were not changed.

## Test commands for integration

Run at minimum:

```bash
php scripts/test_classops_ai_copilot.php
python scripts/test_classops_ai_contract.py
php scripts/test_classops_foundation.php
python scripts/test_shared_contracts.py
python scripts/test_classops_api_http.py
```

Then run the repository-wide deterministic suite through the existing integration-owned static-check runner. Provider/live-network smoke belongs to the later runtime verification stage and must use the dedicated ClassOps AI credential.

## Security review checklist

- [ ] candidate contract promoted/versioned explicitly; frozen contract not silently altered;
- [ ] owner auth + CSRF enforced before any AI request from website UI;
- [ ] bot service action owner authorization enforced server-side;
- [ ] preview HTML escapes all model text;
- [ ] no raw prompt/forwarded text stored in logs, metrics, audit, or error payloads;
- [ ] provider body/error messages are not logged verbatim;
- [ ] ClassOps AI key is distinct from Voice/STT and inaccessible to bot adapters;
- [ ] no mutation occurs until separate explicit owner confirmation;
- [ ] canonical ClassOps validator runs again on the final resolved mutation payload;
- [ ] existing ClassOps idempotency and optimistic revision checks remain unchanged;
- [ ] notification/delivery remains canonical and separate;
- [ ] no raw platform chat IDs enter ClassOps domain persistence.
