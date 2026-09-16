(function (root, factory) {
    'use strict';
    if (!root || !root.ClassOpsOps) return;
    root.ClassOpsOps.mountOperationsCenter = factory(root.ClassOpsOps);
})(typeof globalThis !== 'undefined' ? globalThis : this, function (core) {
    'use strict';

    const {ClassOpsClient, uiLabel, toPersianDigits} = core;
    const STAGE2_EXTENSION = 'classops_stage2_v1';
    const PRIMARY_COHORT = 'dentistry-1402';
    const ALLOWED_TYPES = new Set([
        'announcement', 'event', 'class_change', 'deadline', 'task',
        'requirement', 'exam', 'critical_notice', 'service_reminder'
    ]);
    const ALLOWED_IMPORTANCE = new Set(['normal', 'important', 'critical']);
    const DESTINATIONS = ['private_users', 'class_group', 'information_channel'];

    function qs(id) { return typeof document === 'undefined' ? null : document.getElementById(id); }
    function setText(node, value) { if (node) node.textContent = String(value == null ? '' : value); }
    function setHidden(node, value) { if (node) node.hidden = !!value; }
    function value(id) { const node = qs(id); return node ? String(node.value || '').trim() : ''; }
    function checked(id) { const node = qs(id); return !!(node && node.checked); }
    function label(group, raw, fallback) { return typeof uiLabel === 'function' ? uiLabel(group, raw, fallback) : String(fallback || ''); }
    function fa(raw) { return typeof toPersianDigits === 'function' ? toPersianDigits(raw) : String(raw == null ? '' : raw); }
    function itemTypeLabel(raw) { return label('itemType', raw, 'مورد کلاس'); }
    function stateLabel(raw) { return label('state', raw, raw ? 'نامشخص' : ''); }
    function safeError(error, fallback) {
        const status = Number(error && error.status || 0);
        if (status === 401 || status === 403) return 'دسترسی این عملیات برای حساب شما فعال نیست.';
        if (status === 409) return 'اطلاعات از زمان نمایش تغییر کرده است؛ دوباره بررسی کن.';
        if (status === 422) return 'اطلاعات واردشده معتبر نیست؛ فیلدها را دوباره بررسی کن.';
        if (status >= 500) return 'سرویس موقتاً در دسترس نیست؛ دوباره امتحان کن.';
        return fallback || 'عملیات انجام نشد.';
    }
    function renderFacts(node, title, rows, note, tone) {
        if (window.ClassOperationsProduct && typeof window.ClassOperationsProduct.renderFacts === 'function') {
            window.ClassOperationsProduct.renderFacts(node, title, rows, note, tone);
            return;
        }
        setText(node, note || title || '');
        setHidden(node, false);
    }

    function randomIdempotency() {
        const random = (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
        return 'classops-web-' + random;
    }

    function latinDigits(input) {
        const persian = '۰۱۲۳۴۵۶۷۸۹';
        const arabic = '٠١٢٣٤٥٦٧٨٩';
        return String(input || '').replace(/[۰-۹]/g, (d) => String(persian.indexOf(d)))
            .replace(/[٠-٩]/g, (d) => String(arabic.indexOf(d)));
    }

    function parseStudentNumbers(raw, required) {
        const parts = latinDigits(raw).split(/[\s,،;؛]+/).map((entry) => entry.trim()).filter(Boolean);
        const unique = [];
        const seen = new Set();
        for (const student of parts) {
            if (!/^\d{5,20}$/.test(student)) throw new Error('شماره دانشجویی باید فقط عدد و بین ۵ تا ۲۰ رقم باشد.');
            if (!seen.has(student)) { seen.add(student); unique.push(student); }
        }
        if (required && unique.length === 0) throw new Error('حداقل یک شماره دانشجویی لازم است.');
        if (unique.length > 500) throw new Error('حداکثر ۵۰۰ شماره دانشجویی در هر فهرست مجاز است.');
        return unique;
    }

    function tehranIso(localValue) {
        const raw = String(localValue || '').trim();
        if (!raw) return null;
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(raw)) throw new Error('زمان محلی معتبر نیست.');
        // Iran uses UTC+03:30 year-round in the supported project period. The
        // explicit offset avoids depending on the browser/device timezone.
        return raw + ':00+03:30';
    }

    function buildAudienceSpec() {
        const mode = value('classops-audience-mode') || 'whole_cohort';
        let expression;
        if (mode === 'whole_cohort') {
            expression = {op:'whole_cohort'};
        } else if (mode === 'students') {
            expression = {op:'students', studentNumbers:parseStudentNumbers(value('classops-audience-students'), true)};
        } else if (mode === 'selector') {
            const kind = value('classops-audience-selector-kind');
            const key = value('classops-audience-selector-key');
            if (!['role','group','category'].includes(kind) || !key) throw new Error('نوع و شناسه گروه مخاطبان الزامی است.');
            expression = {op:'selector', kind, key};
        } else {
            throw new Error('نوع مخاطب معتبر نیست.');
        }
        return {
            version:'classops-audience-v1',
            resolutionMode:'snapshot',
            expression,
            includeStudentNumbers:parseStudentNumbers(value('classops-audience-include'), false),
            excludeStudentNumbers:parseStudentNumbers(value('classops-audience-exclude'), false)
        };
    }

    function selectedDestinations() {
        const result = DESTINATIONS.filter((destination) => checked('classops-dest-' + destination));
        if (!result.length) throw new Error('حداقل یک مقصد ارسال را انتخاب کن.');
        return result;
    }

    function buildComposerItem() {
        const type = value('classops-type');
        const importance = value('classops-importance') || 'normal';
        const cohortKey = value('classops-cohort') || PRIMARY_COHORT;
        const title = value('classops-title');
        if (!ALLOWED_TYPES.has(type)) throw new Error('نوع آیتم معتبر نیست.');
        if (!ALLOWED_IMPORTANCE.has(importance)) throw new Error('اهمیت معتبر نیست.');
        if (!/^[a-z0-9][a-z0-9-]{0,79}$/.test(cohortKey)) throw new Error('شناسه ورودی معتبر نیست.');
        if (!title) throw new Error('عنوان الزامی است.');

        const startsAt = tehranIso(value('classops-starts-at'));
        const dueAt = tehranIso(value('classops-due-at'));
        const item = {
            cohortKey,
            type,
            title,
            description:value('classops-description'),
            course:value('classops-course-title') ? {ref:'', title:value('classops-course-title')} : null,
            timing:{startsAt, endsAt:null, dueAt, timezone:'Asia/Tehran'},
            location:value('classops-location'),
            importance,
            requireAck:type === 'critical_notice' ? checked('classops-require-ack') : false,
            status:'draft'
        };
        return item;
    }

    function stage2Binding(item) {
        const extensions = item && item.extensions && typeof item.extensions === 'object' ? item.extensions : {};
        const binding = extensions[STAGE2_EXTENSION];
        return binding && typeof binding === 'object' && !Array.isArray(binding) ? binding : null;
    }

    function publicItemForPreview(item, patch) {
        const merged = Object.assign({}, item || {}, patch || {});
        const out = {
            cohortKey:String(merged.cohortKey || ''),
            type:String(merged.type || ''),
            title:String(merged.title || ''),
            description:String(merged.description || ''),
            course:merged.course || null,
            timing:merged.timing || {startsAt:null, endsAt:null, dueAt:null, timezone:'Asia/Tehran'},
            location:String(merged.location || ''),
            importance:String(merged.importance || 'normal'),
            requireAck:!!merged.requireAck,
            status:String(merged.status || 'draft')
        };
        if (merged.source && typeof merged.source === 'object') out.source = merged.source;
        if (merged.metadata && typeof merged.metadata === 'object' && !Array.isArray(merged.metadata)) out.metadata = merged.metadata;
        if (merged.extensions && typeof merged.extensions === 'object' && !Array.isArray(merged.extensions)) {
            const extensions = Object.assign({}, merged.extensions);
            delete extensions[STAGE2_EXTENSION];
            if (Object.keys(extensions).length) out.extensions = extensions;
        }
        return out;
    }

    function configFromBinding(binding) {
        return {
            audienceSpec:binding && binding.audienceSpec ? binding.audienceSpec : null,
            destinations:binding && Array.isArray(binding.destinations) ? binding.destinations : null,
            reminderPolicy:binding && Object.prototype.hasOwnProperty.call(binding, 'reminderPolicy') ? binding.reminderPolicy : undefined,
            serviceRef:binding && Object.prototype.hasOwnProperty.call(binding, 'serviceRef') ? binding.serviceRef : undefined
        };
    }

    function deliverySummary(plans) {
        if (!plans || typeof plans !== 'object') return [['مسیر ارسال', 'برنامه‌ای برای ارسال آماده نشده است.']];
        const rows = [];
        for (const destination of Object.keys(plans)) {
            const plan = plans[destination] || {};
            const outcomes = Array.isArray(plan.outcomes) ? plan.outcomes : [];
            const destinationLabel = label('destination', destination, 'مسیر ارسال');
            if (!outcomes.length) {
                rows.push([destinationLabel, 'مسیر فعالی پیدا نشد']);
                continue;
            }
            const states = outcomes.map((outcome) => {
                const platform = label('platform', outcome.platform, 'سرویس');
                const state = label('capabilityState', outcome.state, stateLabel(outcome.state) || 'نامشخص');
                return platform + ': ' + state;
            });
            rows.push([destinationLabel, states.join(' · ')]);
        }
        return rows;
    }

    function renderSurfaceCapabilities(surface) {
        const container = qs('classops-capabilities');
        if (!container) return;
        container.replaceChildren();
        const entries = [
            ['foundation', surface && surface.foundation],
            ['audience', surface && surface.audience],
            ['delivery', surface && surface.deliveryPlanning],
            ['ai', surface && surface.ai],
            ['tasks', surface && surface.tasksRequirements],
            ['exam', surface && surface.examAck],
            ['scheduler', surface && surface.scheduler],
            ['digest', surface && surface.digest],
            ['telegram', surface && surface.telegram],
            ['bale', surface && surface.bale],
            ['website', surface && surface.website],
            ['saba', surface && surface.saba]
        ];
        entries.forEach(([key, spec]) => {
            const card = document.createElement('article');
            card.className = 'classops-capability';
            const state = spec && typeof spec === 'object' ? String(spec.state || 'unknown') : 'unknown';
            card.dataset.enabled = ['available','configured','reminder-only'].includes(state) ? 'true' : 'false';
            const strong = document.createElement('strong');
            strong.textContent = label('capability', key, 'سرویس');
            const span = document.createElement('span');
            span.textContent = label('capabilityState', state, 'نامشخص');
            card.append(strong, span);
            container.appendChild(card);
        });
    }

    function syncAudienceControls() {
        const mode = value('classops-audience-mode') || 'whole_cohort';
        setHidden(qs('classops-audience-students-wrap'), mode !== 'students');
        setHidden(qs('classops-audience-selector-wrap'), mode !== 'selector');
    }

    function syncTypeControls() {
        const type = value('classops-type');
        const ack = qs('classops-require-ack');
        if (ack) {
            ack.disabled = type !== 'critical_notice';
            if (type !== 'critical_notice') ack.checked = false;
        }
        const saba = qs('classops-saba-note');
        setHidden(saba, type !== 'service_reminder');
    }

    function setOperationState(kind, message) {
        const node = qs('classops-operation-state');
        if (!node) return;
        node.dataset.state = kind;
        node.textContent = message;
    }

    function renderPreview(preview, context) {
        const panel = qs('classops-stage2-preview-panel');
        const output = qs('classops-stage2-preview');
        const confirm = qs('classops-stage2-confirm');
        const audience = preview && preview.audience ? preview.audience : {};
        const rows = [
            ['عملیات', context.mode === 'create' ? 'ثبت مورد جدید' : 'ثبت ویرایش جدید'],
            ['مخاطبان', audience.total == null ? '—' : fa(audience.total)],
            ['نیازمند بررسی', audience.unresolved == null ? '۰' : fa(audience.unresolved)]
        ];
        if (Array.isArray(audience.warningCounts) && audience.warningCounts.length) {
            rows.push(['هشدار مخاطبان', fa(audience.warningCounts.reduce((sum, entry) => sum + Number(entry.count || 0), 0)) + ' مورد']);
        }
        deliverySummary(preview && preview.destinations).forEach((row) => rows.push(row));
        if (preview && preview.reminderPolicy && Array.isArray(preview.reminderPolicy.rules)) {
            rows.push(['یادآوری‌ها', fa(preview.reminderPolicy.rules.length) + ' قاعده']);
        }
        if (preview && preview.serviceRef === 'saba') rows.push(['صبا', 'فقط یادآوری محلی']);
        renderFacts(
            output,
            context.mode === 'create' ? 'پیش‌نمایش ثبت' : 'پیش‌نمایش ویرایش',
            rows,
            'در تأیید نهایی، فهرست مخاطبان دوباره بررسی می‌شود. پس از تأیید ممکن است اعلان یا ارسال طبق مسیرهای انتخاب‌شده ایجاد شود.',
            'preview'
        );
        setHidden(panel, false);
        if (confirm) { confirm.disabled = false; confirm.dataset.mode = context.mode; }
    }

    function renderAiDraft(draft, telemetry) {
        const output = qs('classops-ai-preview');
        const editWrap = qs('classops-ai-edit-wrap');
        const apply = qs('classops-ai-apply');
        const fields = draft && draft.fields ? draft.fields : {};
        const rows = [];
        if (fields.type) rows.push(['نوع', itemTypeLabel(fields.type)]);
        if (fields.title) rows.push(['عنوان', String(fields.title)]);
        if (fields.description) rows.push(['توضیحات', String(fields.description)]);
        if (fields.location) rows.push(['مکان', String(fields.location)]);
        if (fields.importance) rows.push(['اهمیت', label('importance', fields.importance, 'عادی')]);
        if (typeof fields.requireAck === 'boolean') rows.push(['تأیید مشاهده', fields.requireAck ? 'لازم است' : 'لازم نیست']);
        if (fields.course) rows.push(['درس', String(fields.course.title || fields.course.rawText || 'تعیین نشده')]);
        if (fields.timing) rows.push(['زمان', String(fields.timing.rawText || 'تعیین نشده')]);
        if (fields.audience) rows.push(['مخاطبان', String(fields.audience.rawText || 'تعیین نشده')]);
        if (fields.delivery && Array.isArray(fields.delivery.initialDestinations)) {
            rows.push(['مسیرهای ارسال', fields.delivery.initialDestinations.map((entry) => label('destination', entry, 'مسیر ارسال')).join('، ')]);
        }
        const unresolved = Array.isArray(draft && draft.unresolved) ? draft.unresolved.length : 0;
        if (unresolved) rows.push(['نیازمند تکمیل', fa(unresolved) + ' مورد']);
        renderFacts(output, 'پیش‌نویس پیشنهادی', rows, 'این فقط پیش‌نویس است؛ تا پیش‌نمایش و تأیید نهایی چیزی ثبت یا ارسال نمی‌شود.', 'preview');
        setHidden(editWrap, false);
        if (apply) apply.disabled = false;
    }

    function applyAiToComposer(draft) {
        const fields = draft && draft.fields ? draft.fields : {};
        const assignments = [
            ['classops-type', fields.type, ALLOWED_TYPES],
            ['classops-title', fields.title],
            ['classops-description', fields.description],
            ['classops-location', fields.location],
            ['classops-importance', fields.importance, ALLOWED_IMPORTANCE]
        ];
        assignments.forEach(([id, next, allowed]) => {
            if (next === null || next === undefined || next === '') return;
            if (allowed && !allowed.has(next)) return;
            const node = qs(id);
            if (node) node.value = String(next);
        });
        if (fields.course && fields.course.title && qs('classops-course-title')) qs('classops-course-title').value = String(fields.course.title);
        if (typeof fields.requireAck === 'boolean' && qs('classops-require-ack')) qs('classops-require-ack').checked = fields.requireAck;
        if (fields.delivery && Array.isArray(fields.delivery.initialDestinations)) {
            DESTINATIONS.forEach((destination) => {
                const node = qs('classops-dest-' + destination);
                if (node) node.checked = fields.delivery.initialDestinations.includes(destination);
            });
        }
        syncTypeControls();
        setOperationState('ready', 'فیلدهای قطعی به فرم منتقل شد. زمان و مخاطب نامشخص عمداً حدس زده نشدند.');
    }

    async function mountOperationsCenter(options) {
        if (typeof document === 'undefined') return null;
        const opts = options || {};
        const client = opts.client || new ClassOpsClient(opts.clientOptions);
        const access = qs('classops-access-state');
        const ownerCenter = qs('classops-owner-center');
        const listNode = qs('classops-item-list');
        const itemState = qs('classops-item-state');
        const conflict = qs('classops-conflict');
        const empty = qs('classops-empty');
        const dialog = qs('classops-confirm-dialog');
        const dialogText = qs('classops-confirm-text');
        let surface = null;
        let selectedItem = null;
        let currentPreview = null;
        let lastAiDraft = null;
        let destructiveAction = null;

        function setAccess(kind, message) {
            if (access) access.dataset.state = kind;
            setText(access, message);
        }

        async function refreshList() {
            setText(itemState, 'در حال دریافت...');
            setHidden(conflict, true);
            try {
                const payload = await client.list({limit:50});
                const items = payload && payload.data && Array.isArray(payload.data.items) ? payload.data.items : [];
                if (listNode) listNode.replaceChildren();
                setHidden(empty, items.length !== 0);
                for (const item of items) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'classops-item-row';
                    const title = document.createElement('strong');
                    title.textContent = item.title || 'بدون عنوان';
                    const meta = document.createElement('span');
                    meta.textContent = [itemTypeLabel(item.type), stateLabel(item.status), 'ویرایش ' + fa(item.revision || 0)].filter(Boolean).join(' · ');
                    button.append(title, meta);
                    button.addEventListener('click', async () => {
                        try {
                            const detail = await client.get(item.id);
                            selectItem(detail.item || item);
                        } catch (error) {
                            setOperationState('error', safeError(error, 'جزئیات این مورد دریافت نشد.'));
                        }
                    });
                    if (listNode) listNode.appendChild(button);
                }
                setText(itemState, items.length ? fa(items.length) + ' مورد' : 'فعلاً موردی ثبت نشده است.');
                if (selectedItem) {
                    const fresh = items.find((item) => item.id === selectedItem.id);
                    if (!fresh) { selectedItem = null; setHidden(qs('classops-selected'), true); }
                }
            } catch (error) {
                setText(itemState, safeError(error, 'فهرست موارد دریافت نشد.'));
            }
        }

        function selectItem(item) {
            selectedItem = item;
            setText(qs('classops-selected-title'), item.title || 'بدون عنوان');
            const bound = !!stage2Binding(item);
            setText(qs('classops-selected-meta'), [itemTypeLabel(item.type), stateLabel(item.status), 'ویرایش ' + fa(item.revision || 0), bound ? 'آماده انتشار' : 'نسخه قدیمی'].join(' · '));
            const editor = qs('classops-edit-description');
            if (editor) editor.value = item.description || '';
            setHidden(qs('classops-selected'), false);
            const edit = qs('classops-edit');
            const schedule = qs('classops-schedule');
            const activate = qs('classops-activate');
            const complete = qs('classops-complete');
            const cancel = qs('classops-cancel');
            const archive = qs('classops-archive');
            if (edit) edit.disabled = item.status === 'archived' || !bound;
            if (schedule) schedule.disabled = item.status !== 'draft' || !bound;
            if (activate) activate.disabled = item.status !== 'scheduled' || !bound;
            if (complete) complete.disabled = item.status !== 'active' || !bound;
            if (cancel) cancel.disabled = !['draft','scheduled','active'].includes(item.status);
            if (archive) archive.disabled = item.status === 'archived';
            setHidden(qs('classops-legacy-item-note'), bound);
            setHidden(qs('classops-ack-stats'), !(item.type === 'critical_notice' && item.requireAck));
            setHidden(qs('classops-task-tools'), !['task','requirement'].includes(item.type));
        }

        async function previewCreate() {
            try {
                setOperationState('loading', 'در حال بررسی مخاطبان و مسیرهای ارسال…');
                const item = buildComposerItem();
                const audienceSpec = buildAudienceSpec();
                const destinations = selectedDestinations();
                const body = {item, audienceSpec, destinations};
                if (item.type === 'service_reminder') body.serviceRef = 'saba';
                const preview = await client.request('preview', {method:'POST', body});
                currentPreview = {
                    mode:'create', preview, item, audienceSpec, destinations,
                    reminderPolicy:preview.reminderPolicy,
                    serviceRef:preview.serviceRef,
                    idempotencyKey:randomIdempotency(),
                    expectedRevision:null,
                    id:null,
                    patch:null
                };
                renderPreview(preview, currentPreview);
                setOperationState('ready', 'پیش‌نمایش آماده است؛ هنوز چیزی ثبت یا ارسال نشده است.');
            } catch (error) {
                currentPreview = null;
                setHidden(qs('classops-stage2-preview-panel'), true);
                setOperationState('error', safeError(error, 'پیش‌نمایش آماده نشد.'));
            }
        }

        async function previewSelectedPatch(patch, summary) {
            if (!selectedItem) return;
            const binding = stage2Binding(selectedItem);
            if (!binding) {
                setOperationState('error', 'این مورد با نسخه قدیمی ثبت شده و برای جلوگیری از انتشار ناخواسته، باید دوباره ثبت شود.');
                return;
            }
            try {
                setOperationState('loading', 'در حال آماده‌سازی پیش‌نمایش ویرایش…');
                const config = configFromBinding(binding);
                const fullItem = publicItemForPreview(selectedItem, patch);
                const previewBody = {item:fullItem};
                if (config.audienceSpec) previewBody.audienceSpec = config.audienceSpec;
                if (config.destinations) previewBody.destinations = config.destinations;
                if (config.reminderPolicy !== undefined) previewBody.reminderPolicy = config.reminderPolicy;
                if (config.serviceRef !== undefined) previewBody.serviceRef = config.serviceRef;
                const preview = await client.request('preview', {method:'POST', body:previewBody});
                currentPreview = {
                    mode:'update', preview, item:fullItem, patch,
                    audienceSpec:config.audienceSpec || preview.audienceResolution.normalizedSpec,
                    destinations:config.destinations || Object.keys(preview.destinations || {}),
                    reminderPolicy:config.reminderPolicy !== undefined ? config.reminderPolicy : preview.reminderPolicy,
                    serviceRef:config.serviceRef !== undefined ? config.serviceRef : preview.serviceRef,
                    idempotencyKey:randomIdempotency(),
                    expectedRevision:Number(selectedItem.revision || 0),
                    id:String(selectedItem.id || ''),
                    summary
                };
                renderPreview(preview, currentPreview);
                setOperationState('ready', 'پیش‌نمایش ویرایش آماده است؛ برای ثبت، تأیید نهایی لازم است.');
            } catch (error) {
                currentPreview = null;
                setHidden(qs('classops-stage2-preview-panel'), true);
                if (error.status === 409) await staleRefresh();
                else setOperationState('error', safeError(error, 'پیش‌نمایش ویرایش آماده نشد.'));
            }
        }

        async function confirmPreview() {
            if (!currentPreview) return;
            const button = qs('classops-stage2-confirm');
            if (button) button.disabled = true;
            const context = currentPreview;
            try {
                setOperationState('loading', 'در حال بازبینی مخاطبان و ثبت نهایی…');
                const body = {
                    mode:context.mode,
                    item:context.mode === 'create' ? context.item : context.patch,
                    audienceSpec:context.audienceSpec,
                    expectedAudienceHash:String(context.preview.confirmation && context.preview.confirmation.audienceHash || ''),
                    destinations:context.destinations,
                    idempotencyKey:context.idempotencyKey,
                    reason:context.mode === 'create' ? 'owner confirmed web preview' : 'owner confirmed web revision'
                };
                if (context.mode === 'update') {
                    body.id = context.id;
                    body.expectedRevision = context.expectedRevision;
                }
                if (context.reminderPolicy !== undefined) body.reminderPolicy = context.reminderPolicy;
                if (context.serviceRef !== undefined) body.serviceRef = context.serviceRef;
                const result = await client.request('confirm', {method:'POST', body});
                setOperationState('ready', 'ثبت انجام شد؛ اعلان‌ها: ' + String(result.notificationCount || 0) + '، ارسال مستقیم: ' + String(result.directDeliveryIntentCount || 0) + '.');
                currentPreview = null;
                setHidden(qs('classops-stage2-preview-panel'), true);
                await refreshList();
                if (result.item) selectItem(result.item);
            } catch (error) {
                if (error.status === 409 || error.code === 'CLASSOPS_AUDIENCE_DRIFT' || error.code === 'CLASSOPS_REVISION_CONFLICT') {
                    currentPreview = null;
                    setHidden(qs('classops-stage2-preview-panel'), true);
                    await staleRefresh('اطلاعات مخاطبان یا نسخه مورد تغییر کرده است؛ دوباره پیش‌نمایش بگیر.');
                } else {
                    setOperationState('error', safeError(error, 'ثبت انجام نشد.'));
                    if (button) button.disabled = false;
                }
            }
        }

        async function staleRefresh(message) {
            await refreshList();
            setHidden(conflict, false);
            setText(conflict, message || 'این مورد تغییر کرده است؛ فهرست تازه دریافت شد. دوباره بررسی کن.');
        }

        function openDestructive(action) {
            if (!selectedItem) return;
            destructiveAction = action;
            const label = action === 'cancel' ? 'لغو' : 'آرشیو';
            setText(dialogText, label + ' مورد «' + String(selectedItem.title || '') + '»؟\nنسخه ' + fa(selectedItem.revision || 0));
            if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
        }

        async function executeDestructive() {
            if (!destructiveAction || !selectedItem) return;
            const action = destructiveAction;
            destructiveAction = null;
            if (dialog && typeof dialog.close === 'function') dialog.close();
            try {
                const result = await client.request(action, {method:'POST', body:{
                    id:selectedItem.id,
                    expectedRevision:Number(selectedItem.revision || 0),
                    idempotencyKey:randomIdempotency(),
                    reason:'owner confirmed ' + action + ' from web center'
                }});
                setOperationState('ready', action === 'cancel' ? 'مورد لغو شد و ارسال‌های آینده متوقف شدند.' : 'مورد بایگانی شد و ارسال‌های آینده متوقف شدند.');
                await refreshList();
                if (result.item) selectItem(result.item);
            } catch (error) {
                if (error.status === 409) await staleRefresh();
                else setOperationState('error', safeError(error, 'عملیات انجام نشد.'));
            }
        }

        async function runAiCreate() {
            if (!surface || !surface.ai || surface.ai.state !== 'configured') {
                setOperationState('error', 'هوش مصنوعی این بخش فعال نیست؛ فرم دستی در دسترس است.');
                return;
            }
            const ownerText = value('classops-ai-text');
            if (!ownerText) { setOperationState('error', 'متن درخواست هوش مصنوعی خالی است.'); return; }
            try {
                setOperationState('loading', 'در حال ساخت پیش‌نویس پیشنهادی…');
                const result = await client.request('ai-draft-create', {method:'POST', body:{
                    ownerText,
                    forwardedText:value('classops-ai-forwarded') || null,
                    cohortKey:value('classops-ai-cohort') || value('classops-cohort') || PRIMARY_COHORT
                }});
                lastAiDraft = result.draft || null;
                renderAiDraft(lastAiDraft, result.telemetry || null);
                setOperationState('ready', 'پیش‌نویس آماده شد؛ برای ثبت یا ارسال هنوز پیش‌نمایش و تأیید نهایی لازم است.');
            } catch (error) {
                setOperationState('error', safeError(error, 'پیش‌نویس ساخته نشد.'));
            }
        }

        async function runAiEdit() {
            if (!lastAiDraft) return;
            const ownerEditText = value('classops-ai-edit-text');
            if (!ownerEditText) { setOperationState('error', 'درخواست اصلاح خالی است.'); return; }
            try {
                setOperationState('loading', 'در حال اصلاح پیش‌نویس…');
                const result = await client.request('ai-draft-edit', {method:'POST', body:{priorDraft:lastAiDraft, ownerEditText}});
                lastAiDraft = result.draft || null;
                renderAiDraft(lastAiDraft, result.telemetry || null);
                setOperationState('ready', 'پیش‌نویس اصلاح شد؛ هنوز چیزی ثبت نشده است.');
            } catch (error) {
                setOperationState('error', safeError(error, 'اصلاح پیش‌نویس انجام نشد.'));
            }
        }

        async function loadDigest(kind) {
            const output = qs('classops-digest-output');
            try {
                const action = kind === 'weekly' ? 'weekly-digest' : 'tomorrow-summary';
                const payload = await client.request(action);
                const digest = payload.digest || {};
                if (window.ClassOperationsProduct && typeof window.ClassOperationsProduct.renderDigest === 'function') {
                    window.ClassOperationsProduct.renderDigest(output, digest, kind);
                } else {
                    renderFacts(output, kind === 'weekly' ? 'هفته پیش رو' : 'فردا', [], digest.empty ? 'موردی ثبت نشده است.' : 'خلاصه آماده است.');
                }
            } catch (error) {
                renderFacts(output, 'خلاصه در دسترس نیست', [], safeError(error, 'خلاصه این بازه دریافت نشد.'), 'error');
            }
        }

        async function loadAckStats() {
            if (!selectedItem) return;
            const output = qs('classops-selected-output');
            try {
                const payload = await client.request('ack-stats', {query:{id:selectedItem.id}});
                const stats = payload.stats || {};
                renderFacts(output, 'وضعیت مشاهده اطلاعیه', [
                    ['کل مخاطبان', fa(stats.eligible || 0)],
                    ['تأییدشده', fa(stats.acked || 0)],
                    ['در انتظار', fa(stats.pending || 0)]
                ], 'فقط تأیید صریح دانشجو در این آمار ثبت می‌شود.');
            } catch (error) {
                renderFacts(output, 'آمار در دسترس نیست', [], safeError(error, 'آمار تأییدها دریافت نشد.'), 'error');
            }
        }

        async function loadTaskState() {
            if (!selectedItem) return;
            const studentNumber = latinDigits(value('classops-task-student'));
            if (!/^\d{5,20}$/.test(studentNumber)) { setOperationState('error', 'شماره دانشجویی معتبر نیست.'); return; }
            const output = qs('classops-selected-output');
            try {
                const payload = await client.request('task-state', {query:{id:selectedItem.id, studentNumber}});
                const task = payload.state || {};
                renderFacts(output, 'وضعیت تکلیف دانشجو', [
                    ['وضعیت', task.state ? stateLabel(task.state) : 'ثبت نشده'],
                    ['پیشرفت', fa(task.progressCount || 0)],
                    ['نسخه وضعیت', fa(task.stateRevision || 0)],
                    ['آخرین تغییر', task.updatedAt && window.ClassOperationsProduct ? window.ClassOperationsProduct.persianDate(task.updatedAt) : '—']
                ]);
            } catch (error) {
                renderFacts(output, 'وضعیت در دسترس نیست', [], safeError(error, 'وضعیت تکلیف دریافت نشد.'), 'error');
            }
        }

        const draftForm = qs('classops-draft-form');
        if (draftForm) draftForm.addEventListener('submit', (event) => { event.preventDefault(); previewCreate(); });
        const audienceMode = qs('classops-audience-mode');
        if (audienceMode) audienceMode.addEventListener('change', syncAudienceControls);
        const typeNode = qs('classops-type');
        if (typeNode) typeNode.addEventListener('change', syncTypeControls);
        const previewConfirm = qs('classops-stage2-confirm');
        if (previewConfirm) previewConfirm.addEventListener('click', confirmPreview);
        const previewDismiss = qs('classops-stage2-dismiss');
        if (previewDismiss) previewDismiss.addEventListener('click', () => { currentPreview = null; setHidden(qs('classops-stage2-preview-panel'), true); });
        const refresh = qs('classops-refresh');
        if (refresh) refresh.addEventListener('click', refreshList);

        const edit = qs('classops-edit');
        if (edit) edit.addEventListener('click', () => {
            if (!selectedItem) return;
            const description = String((qs('classops-edit-description') || {}).value || '');
            if (description === String(selectedItem.description || '')) { setOperationState('ready', 'تغییری برای ثبت وجود ندارد.'); return; }
            previewSelectedPatch({description}, 'ویرایش توضیح');
        });
        const schedule = qs('classops-schedule');
        if (schedule) schedule.addEventListener('click', () => previewSelectedPatch({status:'scheduled'}, 'زمان‌بندی'));
        const activate = qs('classops-activate');
        if (activate) activate.addEventListener('click', () => previewSelectedPatch({status:'active'}, 'فعال‌سازی'));
        const complete = qs('classops-complete');
        if (complete) complete.addEventListener('click', () => previewSelectedPatch({status:'completed'}, 'تکمیل'));
        const cancel = qs('classops-cancel');
        if (cancel) cancel.addEventListener('click', () => openDestructive('cancel'));
        const archive = qs('classops-archive');
        if (archive) archive.addEventListener('click', () => openDestructive('archive'));
        const dialogConfirm = qs('classops-confirm-yes');
        if (dialogConfirm) dialogConfirm.addEventListener('click', executeDestructive);
        const dialogCancel = qs('classops-confirm-no');
        if (dialogCancel) dialogCancel.addEventListener('click', () => { destructiveAction = null; if (dialog) dialog.close(); });

        const aiForm = qs('classops-ai-form');
        if (aiForm) aiForm.addEventListener('submit', (event) => { event.preventDefault(); runAiCreate(); });
        const aiEdit = qs('classops-ai-edit');
        if (aiEdit) aiEdit.addEventListener('click', runAiEdit);
        const aiApply = qs('classops-ai-apply');
        if (aiApply) aiApply.addEventListener('click', () => { if (lastAiDraft) applyAiToComposer(lastAiDraft); });
        const tomorrow = qs('classops-digest-tomorrow');
        if (tomorrow) tomorrow.addEventListener('click', () => loadDigest('tomorrow'));
        const weekly = qs('classops-digest-weekly');
        if (weekly) weekly.addEventListener('click', () => loadDigest('weekly'));
        const ackStats = qs('classops-ack-stats');
        if (ackStats) ackStats.addEventListener('click', loadAckStats);
        const taskState = qs('classops-task-state');
        if (taskState) taskState.addEventListener('click', loadTaskState);

        syncAudienceControls();
        syncTypeControls();
        if (qs('classops-cohort') && !qs('classops-cohort').value) qs('classops-cohort').value = PRIMARY_COHORT;
        if (qs('classops-ai-cohort') && !qs('classops-ai-cohort').value) qs('classops-ai-cohort').value = PRIMARY_COHORT;

        try {
            setAccess('loading', 'در حال بررسی دسترسی مدیریت…');
            await client.status();
            const capabilities = await client.capabilities();
            surface = capabilities && capabilities.surface ? capabilities.surface : null;
            if (!surface || !surface.foundation || surface.foundation.state !== 'available') throw new Error('وضعیت سرویس‌های امور کلاس در دسترس نیست');
            renderSurfaceCapabilities(surface);
            setHidden(ownerCenter, false);
            setAccess('ready', 'دسترسی مدیریت امور کلاس تأیید شد.');
            const aiSubmit = qs('classops-ai-submit');
            if (aiSubmit) aiSubmit.disabled = !(surface.ai && surface.ai.state === 'configured');
            const aiState = qs('classops-ai-state');
            setText(aiState, surface.ai && surface.ai.state === 'configured'
                ? 'هوش مصنوعی فعال است؛ خروجی ابتدا به‌صورت پیش‌نویس نمایش داده می‌شود.'
                : 'هوش مصنوعی فعال نیست؛ مسیر دستی کاملاً در دسترس است.');
            await refreshList();
            return {role:'owner', surface};
        } catch (error) {
            setHidden(ownerCenter, true);
            if (error.status === 401 || error.status === 403) setAccess('forbidden', 'این صفحه فقط برای مدیریت سامانه فعال است.');
            else setAccess('error', 'وضعیت سرویس‌های مدیریتی قابل تأیید نیست؛ کنترل‌های مدیریت فعال نشدند.');
            return {role:'unknown', error};
        }
    }

    return mountOperationsCenter;
});
