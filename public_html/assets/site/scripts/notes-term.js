(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var cardsContainer = $("notes-term-cards");
    var emptyBox = $("notes-term-empty");
    var termTitle = $("notes-term-title");
    var termDescription = $("notes-term-description");
    var managePanel = $("notes-term-manage");
    var manageForm = $("notes-term-form");
    var manageFeedback = $("notes-term-feedback");
    var addSubmit = $("notes-term-submit");
    var backLink = $("notes-term-back-link");
    var heroKicker = document.querySelector(".hero-content .greeting-pill");
    var sectionLead = document.querySelector("#notes-term-section .archive-term__head p");
    var manageHeading = managePanel ? managePanel.querySelector(".notes-manage-panel__head h4") : null;
    var manageLead = managePanel ? managePanel.querySelector(".notes-manage-panel__head p") : null;

    if (!cardsContainer || !emptyBox) {
        return;
    }

    var searchParams = new URLSearchParams(window.location.search || "");
    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;

    function normalizeNotesCohort(value) {
        var clean = String(value == null ? "" : value).trim();
        if (!clean || clean === "main" || clean === "1402" || clean === "dentistry-1402") {
            return "1402";
        }
        if (clean === "1403" || clean === "dentistry-1403") {
            return "1403";
        }
        if (clean === "1404" || clean === "dentistry-1404") {
            return "1404";
        }
        if (clean === "prosthesis-1402") {
            return "prosthesis-1402";
        }
        return clean;
    }

    function isValidTermForCohort(cohortKey, value) {
        if (!Number.isFinite(value)) {
            return false;
        }
        if (cohortKey === "1402") {
            return value >= 4 && value <= 12;
        }
        if (cohortKey === "1403") {
            return value >= 3 && value <= 12;
        }
        if (cohortKey === "1404") {
            return value >= 1 && value <= 12;
        }
        if (cohortKey === "prosthesis-1402") {
            return value > 0;
        }
        return false;
    }

    var cohort = authApi && typeof authApi.resolvePageCohort === "function"
        ? authApi.resolvePageCohort("notesCohort")
        : String(document.body.dataset.notesCohort || searchParams.get("cohort") || "1402");
    cohort = normalizeNotesCohort(cohort);
    var rawTerm = String(document.body.dataset.termNumber || searchParams.get("term") || "");
    var term = Number(rawTerm || "0");
    var requestedUnitKey = String(searchParams.get("unit") || "").trim().toLowerCase();
    if (["1402", "1403", "1404", "prosthesis-1402"].indexOf(cohort) === -1) {
        return;
    }
    if (!isValidTermForCohort(cohort, term)) {
        return;
    }

    var state = {
        termData: null,
        canManage: false,
        loadError: "",
        loading: false,
        manageExpanded: false,
        saving: false,
        deletingItemId: 0,
        editingItemId: 0,
        authKey: "",
        downloadHost: null,
        uploadBusy: false,
        uploadRetryPending: false,
        uploadRetryTimer: 0,
        uploadRetryCount: 0,
        uploadTask: null,
        uploadNetworkBound: false,
        uploadXhr: null,
        uploadCancelRequested: false,
        downloadHostPathTouched: false,
        uploadProgress: {
            visible: false,
            phase: "idle",
            progress: 0,
            transferredBytes: 0,
            totalBytes: 0,
            speedBps: 0,
            etaSeconds: NaN,
            completedAt: ""
        }
    };

    function isCurriculumCohort() {
        return cohort === "1402" || cohort === "1403" || cohort === "1404";
    }

    function isUnitMode() {
        return isCurriculumCohort() && requestedUnitKey !== "";
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function cohortHomePath() {
        if (cohort === "1403") {
            return "/notes/1403/";
        }
        if (cohort === "1404") {
            return "/notes/1404/";
        }
        return "/notes/";
    }

    function buildContextUrl(pathname, termValue, unitKey) {
        var url = new URL(pathname, window.location.origin);
        if (Number(termValue || 0) > 0) {
            url.searchParams.set("term", String(termValue));
        }
        if (unitKey) {
            url.searchParams.set("unit", unitKey);
        }
        if (cohort !== "1402") {
            url.searchParams.set("cohort", cohort);
        }
        return url.pathname + (url.search || "");
    }

    function homeUrl(termValue, unitKey) {
        return buildContextUrl(cohortHomePath(), termValue, unitKey);
    }

    function resolveItemStorageTerm(item) {
        var value = Number(item && item.storageTerm ? item.storageTerm : term);
        if (!Number.isFinite(value) || value <= 0) {
            return term;
        }
        return value;
    }

    function applyPagePresentation(termData) {
        var data = termData && typeof termData === "object" ? termData : {};
        var mode = String(data.mode || "");
        var displayTerm = Number(data.termNumber || data.term || term || 0);
        var displayTermLabel = data.termLabel || ("ترم " + toFaDigits(displayTerm));
        var isCurriculumUnit = mode === "curriculum-unit" || isUnitMode();
        var isLegacyTerm = isCurriculumCohort() && !isCurriculumUnit && displayTerm > 0 && displayTerm < 4;

        if (document.body) {
            if (isCurriculumCohort()) {
                document.body.classList.add("notes-curriculum-page");
            }
            document.body.dataset.notesView = isCurriculumUnit ? "unit" : (isLegacyTerm ? "legacy-term" : "term");
        }

        if (heroKicker) {
            heroKicker.textContent = isCurriculumUnit
                ? (data.categoryTitle || displayTermLabel)
                : (data.kicker || displayTermLabel);
        }

        if (sectionLead) {
            if (isCurriculumUnit) {
                sectionLead.textContent = "منابع این واحد در همین صفحه نمایش داده می‌شوند و اگر کارت قدیمی‌تری هم به این درس تعلق داشته باشد، باز هم اینجا دیده می‌شود.";
            } else if (isLegacyTerm) {
                sectionLead.textContent = "این آرشیو هنوز خارج از ساختار اصلی ترم‌های ۴ تا ۱۲ نگه‌داری می‌شود تا هیچ منبع فعلی از دسترس خارج نشود.";
            } else if (isCurriculumCohort()) {
                sectionLead.textContent = "منابع این ترم از دل ساختار دانشکده نمایش داده می‌شوند و از همین صفحه هم قابل مدیریت هستند.";
            } else {
                sectionLead.textContent = "کارت‌های این ترم از سرور بارگذاری می‌شوند و مدیریت آن‌ها فقط از طریق پنل همین صفحه انجام می‌شود.";
            }
        }

        if (manageHeading) {
            manageHeading.textContent = isCurriculumUnit
                ? "مدیریت منابع این واحد"
                : "مدیریت کارت‌های این ترم";
        }
        if (manageLead) {
            manageLead.textContent = isCurriculumUnit
                ? "کارت جدیدی که اینجا ثبت شود، به همین واحد وصل می‌شود و در صفحه عمومی همین درس نمایش داده خواهد شد."
                : "مالک یا مدیر مجاز می‌تواند کارت جدید اضافه کند، کارت‌های موجود را ویرایش کند یا حذف کند.";
        }

        if (backLink) {
            if (isCurriculumCohort()) {
                backLink.href = isCurriculumUnit ? homeUrl(displayTerm, "") : homeUrl(0, "");
                backLink.textContent = isCurriculumUnit
                    ? ("بازگشت به " + displayTermLabel)
                    : "بازگشت به همه ترم‌ها";
            } else if (cohort !== "1402") {
                if (authApi && typeof authApi.appendCohortQuery === "function") {
                    backLink.href = authApi.appendCohortQuery("/notes/", cohort);
                } else {
                    backLink.href = "/notes/?cohort=" + encodeURIComponent(cohort);
                }
            }
        }

        document.title = (data.title || displayTermLabel) + " | آرشیو منابع";
    }

    function authSnapshotKey() {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.getState !== "function") {
            return "anon";
        }

        var snapshot = auth.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber ? String(snapshot.user.studentNumber) : "";
        return (snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function currentViewer() {
        if (!authApi || typeof authApi.getState !== "function") {
            return null;
        }
        var snapshot = authApi.getState();
        return snapshot && snapshot.user ? snapshot.user : null;
    }

    function parseJsonResponse(response) {
        if (siteApi && typeof siteApi.parseJsonResponse === "function") {
            return siteApi.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return {
                success: false,
                error: "پاسخ نامعتبر از سرور دریافت شد."
            };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function formatBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return "۰ بایت";
        }

        var units = ["بایت", "KB", "MB", "GB", "TB"];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes = bytes / 1024;
            index += 1;
        }

        var fixed = bytes >= 10 || index === 0
            ? Math.round(bytes)
            : Math.round(bytes * 10) / 10;
        return String(fixed)
            .replace(/\B(?=(\d{3})+(?!\d))/g, ",")
            .replace(/\d/g, function (digit) {
                return "۰۱۲۳۴۵۶۷۸۹"[digit];
            }) + " " + units[index];
    }

    function formatSpeed(value) {
        var bps = Number(value || 0);
        if (!Number.isFinite(bps) || bps <= 0) {
            return "—";
        }
        return formatBytes(bps) + "/ث";
    }

    function formatEta(seconds) {
        var value = Number(seconds);
        if (!Number.isFinite(value) || value < 0) {
            return "—";
        }
        if (value < 60) {
            return Math.max(1, Math.round(value)).toLocaleString("fa-IR") + " ثانیه";
        }

        var minutes = Math.floor(value / 60);
        var remain = Math.round(value % 60);
        if (minutes < 60) {
            return minutes.toLocaleString("fa-IR") + " دقیقه" + (remain ? " و " + remain.toLocaleString("fa-IR") + " ثانیه" : "");
        }

        var hours = Math.floor(minutes / 60);
        minutes = minutes % 60;
        return hours.toLocaleString("fa-IR") + " ساعت" + (minutes ? " و " + minutes.toLocaleString("fa-IR") + " دقیقه" : "");
    }

    function formatPercent(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            number = 0;
        }
        return toFaDigits(Math.max(0, Math.min(100, Math.round(number))));
    }

    function isOffline() {
        return typeof navigator !== "undefined" && navigator && navigator.onLine === false;
    }

    function withContextPayload(payload) {
        var next = Object.assign({ cohort: cohort }, payload || {});
        if (cohort === "1402" || cohort === "1403" || cohort === "1404" || cohort === "prosthesis-1402") {
            var payloadTerm = Number(next.term || term);
            next.term = String(Number.isFinite(payloadTerm) && payloadTerm > 0 ? payloadTerm : term);
        }
        if (isCurriculumCohort() && requestedUnitKey) {
            if (next.unit === undefined) {
                next.unit = requestedUnitKey;
            }
            if (next.unitKey === undefined) {
                next.unitKey = requestedUnitKey;
            }
        }
        return next;
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        };
        var requestPayload = withContextPayload(payload);
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);

        if (method === "GET") {
            Object.keys(requestPayload).forEach(function (key) {
                var value = requestPayload[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, requestPayload));
        }

        return fetch(url, options).then(parseJsonResponse);
    }

    function requestFormData(action, formData) {
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            body: formData,
            headers: {
                Accept: "application/json"
            }
        }).then(parseJsonResponse);
    }

    function setFeedback(text, kind) {
        if (!manageFeedback) {
            return;
        }

        manageFeedback.textContent = text || "";
        manageFeedback.dataset.kind = kind || "";
        manageFeedback.hidden = !text;
    }

    function ensureManageToggle() {
        if (!managePanel || !managePanel.firstElementChild) {
            return null;
        }
        var existing = $("notes-term-manage-toggle");
        if (existing) {
            return existing;
        }
        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.id = "notes-term-manage-toggle";
        toggle.className = "notes-manage-panel__toggle";
        toggle.addEventListener("click", function () {
            state.manageExpanded = !state.manageExpanded;
            renderTerm();
        });
        managePanel.firstElementChild.appendChild(toggle);
        return toggle;
    }

    function syncManagePanel() {
        if (!managePanel) {
            return;
        }
        if (managePanel.hidden) {
            if (manageForm) {
                manageForm.hidden = true;
            }
            return;
        }
        var toggle = ensureManageToggle();
        var expanded = !!state.manageExpanded;
        managePanel.dataset.collapsed = expanded ? "false" : "true";
        if (toggle) {
            toggle.textContent = expanded ? "بستن مدیریت منابع" : "باز کردن مدیریت منابع";
            toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
            toggle.setAttribute("aria-controls", "notes-term-form");
        }
        if (manageForm) {
            manageForm.hidden = !expanded;
        }
    }

    function clearCards() {
        while (cardsContainer.firstChild) {
            cardsContainer.removeChild(cardsContainer.firstChild);
        }
    }

    function findItem(itemId) {
        var items = state.termData && Array.isArray(state.termData.items) ? state.termData.items : [];
        for (var index = 0; index < items.length; index += 1) {
            if (Number(items[index].id || 0) === Number(itemId || 0)) {
                return items[index];
            }
        }
        return null;
    }

    function createChevron() {
        var chevron = document.createElement("span");
        chevron.className = "action-card__chevron";
        chevron.setAttribute("aria-hidden", "true");
        return chevron;
    }

    function createVisual(kind) {
        var visual = document.createElement("span");
        visual.className = "action-card__visual";
        visual.setAttribute("aria-hidden", "true");
        if (kind === "link") {
            visual.innerHTML = '<svg viewBox="0 0 24 24" fill="none"><path d="M9.4 14.6L14.6 9.4M10.4 7.2H16.8V13.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6H7.4A2.4 2.4 0 0 0 5 8.4V16.6A2.4 2.4 0 0 0 7.4 19H15.6A2.4 2.4 0 0 0 18 16.6V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            return visual;
        }
        visual.innerHTML = '<svg viewBox="0 0 24 24" fill="none"><path d="M7 4.8H13.1L17.5 9.1V18A2.2 2.2 0 0 1 15.3 20.2H8.7A2.2 2.2 0 0 1 6.5 18V7A2.2 2.2 0 0 1 8.7 4.8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13 4.8V9.2H17.4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.4 12.4H14.8M9.4 15.6H13.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        return visual;
    }

    function createPrimaryLink(item) {
        var link = document.createElement("a");
        link.className = "action-card__primary";
        link.href = item.buttonUrl || "#";
        if (item.isExternal) {
            link.dataset.externalLink = "true";
            link.target = "_blank";
            link.rel = "noopener noreferrer";
        }

        var content = document.createElement("span");
        content.className = "card-content";

        var header = document.createElement("span");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = item.badge || "منبع";

        var title = document.createElement("span");
        title.className = "card-title";
        title.textContent = item.title || "بدون عنوان";

        var desc = document.createElement("span");
        desc.className = "card-desc";
        desc.textContent = item.description || "";

        header.appendChild(badge);
        header.appendChild(title);
        content.appendChild(header);
        content.appendChild(desc);

        link.appendChild(createChevron());
        link.appendChild(content);
        link.appendChild(createVisual(item.isExternal ? "link" : "document"));
        return link;
    }

    function buildCard(item) {
        var itemId = Number(item.id || 0);
        if (!state.canManage) {
            var publicCard = createPrimaryLink(item);
            publicCard.classList.add("action-card", "action-card--link");
            publicCard.dataset.itemId = String(item.id || "");
            return publicCard;
        }

        var card = document.createElement("article");
        card.className = "action-card";
        card.dataset.itemId = String(item.id || "");
        card.appendChild(createPrimaryLink(item));

        var actions = document.createElement("div");
        actions.className = "notes-card-actions";
        var button = document.createElement("a");
        button.className = "card-btn";
        button.href = item.buttonUrl || "#";
        button.textContent = item.buttonLabel || "باز کردن";
        if (item.isExternal) {
            button.dataset.externalLink = "true";
            button.target = "_blank";
            button.rel = "noopener noreferrer";
        }
        actions.appendChild(button);

        var editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "notes-card-edit";
        editButton.setAttribute("data-notes-edit", "true");
        editButton.setAttribute("data-item-id", String(item.id || ""));
        editButton.textContent = state.editingItemId === itemId ? "در حال ویرایش" : "ویرایش";
        editButton.disabled = state.saving || state.deletingItemId > 0;
        actions.appendChild(editButton);

        var deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "notes-card-delete";
        deleteButton.setAttribute("data-notes-delete", "true");
        deleteButton.setAttribute("data-item-id", String(item.id || ""));
        deleteButton.textContent = state.deletingItemId === itemId ? "در حال حذف..." : "حذف";
        deleteButton.disabled = state.deletingItemId === itemId || state.saving || state.editingItemId === itemId;
        actions.appendChild(deleteButton);

        card.appendChild(actions);
        return card;
    }

    function syncEditUi() {
        if (addSubmit) {
            if (state.saving) {
                addSubmit.textContent = state.editingItemId ? "در حال ذخیره..." : "در حال ثبت...";
            } else {
                addSubmit.textContent = state.editingItemId ? "ذخیره تغییرات" : "افزودن کارت";
            }
            addSubmit.disabled = state.saving;
        }

        var cancelButton = $("notes-term-cancel-edit");
        if (cancelButton) {
            cancelButton.hidden = !state.editingItemId;
            cancelButton.disabled = state.saving;
        }
    }

    function formInputs() {
        return {
            badge: $("notes-term-badge"),
            title: $("notes-term-card-title"),
            description: $("notes-term-card-description"),
            buttonLabel: $("notes-term-button-label"),
            buttonUrl: $("notes-term-button-url")
        };
    }

    function clearForm() {
        var inputs = formInputs();
        Object.keys(inputs).forEach(function (key) {
            if (inputs[key]) {
                inputs[key].value = "";
            }
        });
    }

    function fillForm(item) {
        var inputs = formInputs();
        if (inputs.badge) inputs.badge.value = item.badge || "";
        if (inputs.title) inputs.title.value = item.title || "";
        if (inputs.description) inputs.description.value = item.description || "";
        if (inputs.buttonLabel) inputs.buttonLabel.value = item.buttonLabel || "";
        if (inputs.buttonUrl) inputs.buttonUrl.value = item.buttonUrl || "";
    }

    function resetEditMode(keepValues) {
        state.editingItemId = 0;
        if (!keepValues) {
            clearForm();
        }
        setFeedback("", "");
        renderTerm();
    }

    function startEditing(item) {
        if (!item) {
            return;
        }

        state.editingItemId = Number(item.id || 0);
        state.manageExpanded = true;
        fillForm(item);
        setFeedback("کارت برای ویرایش آماده شد.", "success");
        renderTerm();

        var inputs = formInputs();
        if (inputs.title && typeof inputs.title.focus === "function") {
            inputs.title.focus();
        }
        if (managePanel && typeof managePanel.scrollIntoView === "function") {
            managePanel.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    function ensureCancelEditButton() {
        if (!manageForm || !addSubmit || $("notes-term-cancel-edit")) {
            return;
        }

        var cancelButton = document.createElement("button");
        cancelButton.type = "button";
        cancelButton.id = "notes-term-cancel-edit";
        cancelButton.className = "notes-manage-panel__cancel";
        cancelButton.textContent = "انصراف از ویرایش";
        cancelButton.hidden = true;
        cancelButton.addEventListener("click", function () {
            resetEditMode(false);
        });
        addSubmit.insertAdjacentElement("afterend", cancelButton);
    }

    function ensureTermData() {
        if (!state.termData) {
            state.termData = {
                cohort: cohort,
                term: term,
                title: "",
                description: "",
                emptyMessage: "",
                items: []
            };
        }
        if (!Array.isArray(state.termData.items)) {
            state.termData.items = [];
        }
    }

    function applySavedItem(item, isEdit) {
        ensureTermData();
        if (!isEdit) {
            state.termData.items.unshift(item);
            return;
        }

        var replaced = false;
        state.termData.items = state.termData.items.map(function (current) {
            if (Number(current.id || 0) === Number(item.id || 0)) {
                replaced = true;
                return item;
            }
            return current;
        });
        if (!replaced) {
            state.termData.items.unshift(item);
        }
    }

    function handleUnauthorized(payload) {
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(payload, "برای مدیریت منابع باید وارد حساب مجاز شوید.");
        }
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.handleUnauthorizedPayload !== "function") {
            return false;
        }
        return auth.handleUnauthorizedPayload(payload, "برای مدیریت منابع باید وارد حساب مجاز شوید.");
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function hostUi() {
        return {
            shell: $("notes-host-tools"),
            managerLink: $("notes-host-manager-link"),
            pathInput: $("notes-host-path"),
            nameInput: $("notes-host-name"),
            fileInput: $("notes-host-file"),
            pickButton: $("notes-host-pick"),
            uploadButton: $("notes-host-upload"),
            cancelButton: $("notes-host-cancel"),
            fileMeta: $("notes-host-file-meta"),
            progress: $("notes-host-progress"),
            progressLabel: $("notes-host-progress-label"),
            progressPercent: $("notes-host-progress-percent"),
            progressBar: $("notes-host-progress-bar"),
            progressSize: $("notes-host-progress-size"),
            progressSpeed: $("notes-host-progress-speed"),
            progressEta: $("notes-host-progress-eta"),
            status: $("notes-host-status"),
            preview: $("notes-host-preview")
        };
    }

    function clearHostUploadRetryTimer() {
        if (!state.uploadRetryTimer) {
            return;
        }
        window.clearTimeout(state.uploadRetryTimer);
        state.uploadRetryTimer = 0;
    }

    function hasPendingHostUploadRetry() {
        return !!(state.uploadRetryPending && state.uploadTask && state.uploadTask.file);
    }

    function hostUploadRetryDelayMs() {
        var attempts = Math.max(1, Number(state.uploadRetryCount || 0));
        if (attempts <= 1) {
            return 4000;
        }
        if (attempts === 2) {
            return 7000;
        }
        if (attempts === 3) {
            return 12000;
        }
        return 20000;
    }

    function hostUploadWaitingMessage() {
        if (isOffline()) {
            return "اتصال اینترنت قطع شده است. فایل در صف می‌ماند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شود.";
        }
        if (Number(state.uploadProgress && state.uploadProgress.progress || 0) >= 99) {
            return "ارتباط در مرحله نهایی‌سازی قطع شد. به محض پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
        }
        return "ارتباط آپلود دچار اختلال شد. بعد از پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
    }

    function resetHostUploadRetryState() {
        clearHostUploadRetryTimer();
        state.uploadRetryPending = false;
        state.uploadRetryCount = 0;
        state.uploadTask = null;
    }

    function scheduleHostUploadResume(delayMs) {
        clearHostUploadRetryTimer();
        if (!hasPendingHostUploadRetry()) {
            return;
        }
        state.uploadRetryTimer = window.setTimeout(function () {
            state.uploadRetryTimer = 0;
            if (state.uploadBusy || !hasPendingHostUploadRetry()) {
                return;
            }
            uploadSelectedHostFile(true);
        }, Math.max(1200, Number(delayMs || 0)));
    }

    function hostProgressLabel() {
        switch (state.uploadProgress.phase) {
            case "queued":
                return "آماده برای آپلود";
            case "uploading":
                return "در حال انتقال";
            case "finalizing":
                return "در حال ثبت روی هاست";
            case "waiting":
                return "در انتظار تلاش خودکار";
            case "done":
                return "آپلود کامل شد";
            case "error":
                return "آپلود با خطا متوقف شد";
            default:
                return "وضعیت آپلود";
        }
    }

    function resetHostProgress(visible) {
        resetHostUploadRetryState();
        state.uploadProgress = {
            visible: !!visible,
            phase: visible ? "queued" : "idle",
            progress: 0,
            transferredBytes: 0,
            totalBytes: 0,
            speedBps: 0,
            etaSeconds: NaN,
            completedAt: ""
        };
    }

    function primeHostProgress(file) {
        clearHostUploadRetryTimer();
        state.uploadRetryPending = false;
        state.uploadRetryCount = 0;
        state.uploadTask = null;
        state.uploadProgress = {
            visible: !!file,
            phase: file ? "queued" : "idle",
            progress: 0,
            transferredBytes: 0,
            totalBytes: Number(file && file.size || 0),
            speedBps: 0,
            etaSeconds: NaN,
            completedAt: ""
        };
    }

    function renderHostProgress() {
        var ui = hostUi();
        if (!ui.progress) {
            return;
        }

        var snapshot = state.uploadProgress || {};
        ui.progress.hidden = !snapshot.visible;
        ui.progress.dataset.phase = snapshot.phase || "idle";
        if (ui.progress.hidden) {
            return;
        }

        var progress = Math.max(0, Math.min(100, Number(snapshot.progress || 0)));
        if (ui.progressLabel) {
            ui.progressLabel.textContent = hostProgressLabel();
        }
        if (ui.progressPercent) {
            ui.progressPercent.textContent = formatPercent(progress) + "%";
        }
        if (ui.progressBar) {
            ui.progressBar.style.width = progress.toFixed(1) + "%";
        }
        if (ui.progressSize) {
            ui.progressSize.textContent = "انتقال: " + formatBytes(snapshot.transferredBytes || 0) + " / " + formatBytes(snapshot.totalBytes || 0);
        }
        if (ui.progressSpeed) {
            ui.progressSpeed.textContent = snapshot.phase === "done"
                ? "سرعت نهایی: " + formatSpeed(snapshot.speedBps)
                : "سرعت: " + formatSpeed(snapshot.speedBps);
        }
        if (ui.progressEta) {
            ui.progressEta.textContent = snapshot.phase === "done"
                ? ("اتمام: " + (snapshot.completedAt ? new Intl.DateTimeFormat("fa-IR", {
                    dateStyle: "short",
                    timeStyle: "short"
                }).format(new Date(snapshot.completedAt)) : "اکنون"))
                : (snapshot.phase === "waiting"
                    ? "تلاش دوباره: به‌محض برگشت اتصال، آپلود خودکار دوباره انجام می‌شود."
                    : "زمان باقی‌مانده: " + formatEta(snapshot.etaSeconds));
        }
    }

    function setHostStatus(text, kind, previewUrl) {
        var ui = hostUi();
        if (ui.status) {
            ui.status.textContent = text || "";
            ui.status.dataset.kind = kind || "";
            ui.status.hidden = !text;
        }
        if (ui.preview) {
            ui.preview.hidden = !previewUrl;
            if (previewUrl) {
                ui.preview.href = previewUrl;
                ui.preview.textContent = "باز کردن لینک مستقیم فایل";
            } else {
                ui.preview.removeAttribute("href");
                ui.preview.textContent = "";
            }
        }
    }

    function selectedHostFile() {
        var ui = hostUi();
        return ui.fileInput && ui.fileInput.files && ui.fileInput.files[0] ? ui.fileInput.files[0] : null;
    }

    function syncSelectedHostFileMeta() {
        var ui = hostUi();
        if (!ui.fileMeta) {
            return;
        }
        var file = selectedHostFile();
        if (!file) {
            ui.fileMeta.textContent = "هنوز فایلی برای آپلود انتخاب نشده است.";
            return;
        }
        ui.fileMeta.textContent = file.name + " • " + formatBytes(file.size || 0);
    }

    function ensureDownloadHostUi() {
        if (!manageForm || $("notes-host-tools")) {
            return;
        }

        var shell = document.createElement("section");
        shell.className = "notes-host-tools";
        shell.id = "notes-host-tools";
        shell.innerHTML = [
            '<div class="notes-host-tools__head">',
            '  <div>',
            '    <span class="notes-host-tools__eyebrow">هاست دانلود</span>',
            '    <strong>آپلود مستقیم فایل روی هاست دانلود منابع</strong>',
            '    <p>اگر فایل را همین‌جا آپلود کنی، لینک دکمه به‌صورت خودکار در فیلد لینک بالا قرار می‌گیرد و همان فایل داخل ساختار پوشه‌ای منابع ذخیره می‌شود.</p>',
            '  </div>',
            '  <a id="notes-host-manager-link" class="notes-host-tools__manager" href="/notes/files/">فایل‌منیجر فولدری</a>',
            '</div>',
            '<div class="notes-host-tools__grid">',
            '  <label class="notes-manage-panel__field notes-manage-panel__field--full">',
            '    <span>پوشه مقصد روی هاست دانلود</span>',
            '    <input id="notes-host-path" type="text" maxlength="240" placeholder="مثلاً 1402/term-06">',
            '  </label>',
            '  <label class="notes-manage-panel__field">',
            '    <span>نام فایل نهایی (اختیاری)</span>',
            '    <input id="notes-host-name" type="text" maxlength="200" placeholder="مثلاً جزوه اندو ۱.pdf">',
            '  </label>',
            '  <div class="notes-host-tools__picker">',
            '    <span>فایل</span>',
            '    <input id="notes-host-file" type="file" hidden>',
            '    <button id="notes-host-pick" class="notes-card-edit" type="button">انتخاب فایل</button>',
            '    <small id="notes-host-file-meta">هنوز فایلی برای آپلود انتخاب نشده است.</small>',
            '  </div>',
            '</div>',
            '<div class="notes-host-tools__actions">',
            '  <button id="notes-host-upload" class="card-btn" type="button">آپلود به هاست دانلود</button>',
            '</div>',
            '<section id="notes-host-progress" class="notes-host-progress" hidden>',
            '  <div class="notes-host-progress__head">',
            '    <strong id="notes-host-progress-label">آماده برای آپلود</strong>',
            '    <span id="notes-host-progress-percent">۰٪</span>',
            '  </div>',
            '  <div class="notes-host-progress__track"><span id="notes-host-progress-bar"></span></div>',
            '  <div class="notes-host-progress__stats">',
            '    <span id="notes-host-progress-size">انتقال: ۰ بایت / ۰ بایت</span>',
            '    <span id="notes-host-progress-speed">سرعت: —</span>',
            '    <span id="notes-host-progress-eta">زمان باقی‌مانده: —</span>',
            '  </div>',
            '</section>',
            '<p id="notes-host-status" class="notes-manage-feedback" hidden></p>',
            '<a id="notes-host-preview" class="notes-host-tools__preview" href="#" target="_blank" rel="noopener noreferrer" hidden></a>'
        ].join("");

        if (addSubmit) {
            addSubmit.insertAdjacentElement("beforebegin", shell);
        } else {
            manageForm.appendChild(shell);
        }

        var ui = hostUi();
        if (ui.uploadButton && !ui.cancelButton) {
            var cancelButton = document.createElement("button");
            cancelButton.id = "notes-host-cancel";
            cancelButton.type = "button";
            cancelButton.className = "notes-card-delete";
            cancelButton.hidden = true;
            cancelButton.textContent = "لغو آپلود";
            ui.uploadButton.insertAdjacentElement("afterend", cancelButton);
            ui = hostUi();
        }
        if (ui.pickButton && ui.fileInput) {
            ui.pickButton.addEventListener("click", function () {
                if (!state.uploadBusy) {
                    ui.fileInput.click();
                }
            });
            ui.fileInput.addEventListener("change", function () {
                syncSelectedHostFileMeta();
                primeHostProgress(selectedHostFile());
                renderHostProgress();
                setHostStatus("", "");
            });
        }
        if (ui.pathInput) {
            ui.pathInput.addEventListener("input", function () {
                state.downloadHostPathTouched = true;
            });
        }
        if (ui.uploadButton) {
            ui.uploadButton.addEventListener("click", function () {
                uploadSelectedHostFile(false);
            });
        }
        if (ui.cancelButton) {
            ui.cancelButton.addEventListener("click", function () {
                if (state.uploadXhr) {
                    state.uploadCancelRequested = true;
                    state.uploadXhr.abort();
                    return;
                }
                if (state.uploadRetryPending) {
                    resetHostUploadRetryState();
                    state.uploadProgress.phase = "error";
                    state.uploadProgress.speedBps = 0;
                    state.uploadProgress.etaSeconds = NaN;
                    renderHostProgress();
                    syncDownloadHostUi();
                    setHostStatus("آپلود فایل از طرف کاربر لغو شد.", "");
                }
            });
        }
        if (!state.uploadNetworkBound) {
            window.addEventListener("offline", function () {
                if (!(state.uploadBusy || state.uploadRetryPending)) {
                    return;
                }
                setHostStatus("اتصال اینترنت قطع شد. فایل در صف می‌ماند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شود.", "");
            });
            window.addEventListener("online", function () {
                if (!hasPendingHostUploadRetry()) {
                    return;
                }
                setHostStatus("اتصال برگشت. آپلود فایل خودکار دوباره تلاش می‌شود.", "");
                scheduleHostUploadResume(900);
            });
            state.uploadNetworkBound = true;
        }
        renderHostProgress();
    }

    function syncDownloadHostUi() {
        ensureDownloadHostUi();
        var ui = hostUi();
        if (!ui.shell) {
            return;
        }

        ui.shell.hidden = !state.canManage;
        if (!state.canManage) {
            return;
        }

        var info = state.downloadHost || {};
        if (ui.managerLink) {
            ui.managerLink.href = info.managerUrl || "/notes/files/";
        }
        if (ui.pathInput && !state.downloadHostPathTouched && info.defaultRelativeDir) {
            ui.pathInput.value = info.defaultRelativeDir;
        }
        if (ui.uploadButton) {
            ui.uploadButton.disabled = state.uploadBusy || state.uploadRetryPending || !info.enabled || !info.canUpload;
            ui.uploadButton.textContent = state.uploadRetryPending
                ? "در انتظار تلاش دوباره..."
                : (state.uploadBusy ? "در حال آپلود..." : "آپلود به هاست دانلود");
        }
        if (ui.cancelButton) {
            ui.cancelButton.hidden = !(state.uploadBusy || state.uploadRetryPending);
            ui.cancelButton.disabled = !(state.uploadXhr || state.uploadRetryPending);
            ui.cancelButton.textContent = state.uploadRetryPending ? "لغو انتظار" : "لغو آپلود";
        }
        if (ui.pickButton) {
            ui.pickButton.disabled = state.uploadBusy || state.uploadRetryPending || !info.enabled || !info.canUpload;
        }
        if (ui.nameInput) {
            ui.nameInput.disabled = state.uploadBusy || state.uploadRetryPending || !info.enabled || !info.canUpload;
        }
        if (ui.pathInput) {
            ui.pathInput.disabled = state.uploadBusy || state.uploadRetryPending || !info.enabled || !info.canUpload;
        }
        renderHostProgress();

        if (!info.enabled) {
            setHostStatus("تنظیمات هاست دانلود روی این سرور هنوز کامل نشده است.", "error");
            return;
        }
        if (!info.canUpload) {
            setHostStatus("آپلود مستقیم برای این حساب در این صفحه فعال نیست.", "error");
            return;
        }
        if (!state.uploadBusy && (!ui.status || ui.status.hidden || !ui.status.textContent)) {
            setHostStatus("آپلود در همین بخش انجام می‌شود و لینک مستقیم فایل به‌صورت خودکار روی کارت قرار می‌گیرد.", "");
        }
    }

    function uploadSelectedHostFile(resumeOnly) {
        var info = state.downloadHost || {};
        var ui = hostUi();
        if (!info.enabled || !info.canUpload) {
            if (resumeOnly) {
                state.uploadBusy = false;
                resetHostUploadRetryState();
                syncDownloadHostUi();
            }
            setHostStatus("آپلود مستقیم برای این صفحه فعال نیست.", "error");
            return;
        }

        var task = resumeOnly ? state.uploadTask : null;
        if (!task) {
            var file = selectedHostFile();
            var pathValue = ui.pathInput ? String(ui.pathInput.value || "").trim() : "";
            var fileName = ui.nameInput ? String(ui.nameInput.value || "").trim() : "";
            if (!file) {
                setHostStatus("ابتدا فایل موردنظر را انتخاب کنید.", "error");
                return;
            }
            if (!pathValue) {
                setHostStatus("پوشه مقصد روی هاست دانلود را مشخص کنید.", "error");
                return;
            }
            task = {
                file: file,
                pathValue: pathValue,
                fileName: fileName
            };
            state.uploadTask = task;
            state.uploadRetryCount = 0;
        }
        if (!task || !task.file) {
            setHostStatus("فایل انتخاب‌شده برای ادامه آپلود در دسترس نیست.", "error");
            state.uploadBusy = false;
            resetHostUploadRetryState();
            syncDownloadHostUi();
            return;
        }

        clearHostUploadRetryTimer();
        state.uploadRetryPending = false;
        state.uploadBusy = true;
        state.uploadProgress = {
            visible: true,
            phase: "uploading",
            progress: 0,
            transferredBytes: 0,
            totalBytes: Number(task.file.size || 0),
            speedBps: 0,
            etaSeconds: NaN,
            completedAt: ""
        };
        syncDownloadHostUi();
        setHostStatus(resumeOnly ? "اتصال برگشت و آپلود دوباره تلاش شد..." : "فایل در حال انتقال به هاست دانلود است...", "");
        renderHostProgress();

        function moveUploadToWaiting(message) {
            state.uploadXhr = null;
            state.uploadCancelRequested = false;
            state.uploadBusy = false;
            state.uploadRetryPending = true;
            state.uploadRetryCount = Math.max(0, Number(state.uploadRetryCount || 0)) + 1;
            state.uploadProgress.visible = true;
            state.uploadProgress.phase = "waiting";
            state.uploadProgress.speedBps = 0;
            state.uploadProgress.etaSeconds = NaN;
            renderHostProgress();
            syncDownloadHostUi();
            setHostStatus(message || hostUploadWaitingMessage(), "");
            scheduleHostUploadResume(isOffline() ? 2500 : hostUploadRetryDelayMs());
        }

        var startedAt = Date.now();
        var xhr = new XMLHttpRequest();
        state.uploadCancelRequested = false;
        state.uploadXhr = xhr;
        var requestUrl = "/api/notes_api.php?action=downloadHostUpload"
            + "&cohort=" + encodeURIComponent(cohort)
            + "&term=" + encodeURIComponent(String(term))
            + "&path=" + encodeURIComponent(task.pathValue);
        if (task.fileName) {
            requestUrl += "&fileName=" + encodeURIComponent(task.fileName);
        }
        xhr.open("POST", requestUrl, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader("Accept", "application/json");
        xhr.setRequestHeader("Content-Type", task.file && task.file.type ? task.file.type : "application/octet-stream");
        xhr.setRequestHeader("X-Dent-Upload-Name", encodeURIComponent(task.fileName || task.file.name || "file"));

        xhr.upload.onprogress = function (event) {
            if (!event.lengthComputable) {
                return;
            }

            var loaded = Number(event.loaded || 0);
            var total = Number(event.total || task.file.size || 0);
            var elapsed = Math.max(0.25, (Date.now() - startedAt) / 1000);
            var speed = loaded / elapsed;
            state.uploadProgress.visible = true;
            state.uploadProgress.phase = "uploading";
            state.uploadProgress.transferredBytes = loaded;
            state.uploadProgress.totalBytes = total;
            state.uploadProgress.progress = total > 0 ? (loaded / total) * 100 : state.uploadProgress.progress;
            state.uploadProgress.speedBps = speed;
            state.uploadProgress.etaSeconds = speed > 0 && total > loaded ? (total - loaded) / speed : 0;
            if (state.uploadProgress.progress >= 99.9) {
                state.uploadProgress.phase = "finalizing";
                state.uploadProgress.etaSeconds = 0;
            }
            renderHostProgress();
        };

        xhr.upload.onload = function () {
            state.uploadProgress.visible = true;
            state.uploadProgress.phase = "finalizing";
            state.uploadProgress.progress = 100;
            state.uploadProgress.transferredBytes = Number(task.file.size || state.uploadProgress.transferredBytes || 0);
            state.uploadProgress.totalBytes = Number(task.file.size || state.uploadProgress.totalBytes || 0);
            state.uploadProgress.speedBps = 0;
            state.uploadProgress.etaSeconds = 0;
            renderHostProgress();
        };

        xhr.onload = function () {
            var response = {};
            try {
                response = JSON.parse(xhr.responseText || "{}");
            } catch (_error) {
                response = { success: false, error: "پاسخ آپلود معتبر نبود." };
            }
            state.uploadXhr = null;
            state.uploadCancelRequested = false;
            response.httpStatus = xhr.status;

            if (handleUnauthorized(response)) {
                resetHostUploadRetryState();
                state.uploadProgress.phase = "error";
                state.uploadProgress.etaSeconds = NaN;
                renderHostProgress();
                setHostStatus("برای مدیریت منابع باید وارد حساب مجاز شوید.", "error");
                state.uploadBusy = false;
                syncDownloadHostUi();
                return;
            }
            if (!response || !response.success || !response.file) {
                resetHostUploadRetryState();
                state.uploadProgress.phase = "error";
                state.uploadProgress.speedBps = 0;
                state.uploadProgress.etaSeconds = NaN;
                renderHostProgress();
                state.uploadBusy = false;
                syncDownloadHostUi();
                setHostStatus((response && response.error) || "آپلود فایل روی هاست دانلود انجام نشد.", "error");
                return;
            }

            var inputs = formInputs();
            if (inputs.buttonUrl) {
                inputs.buttonUrl.value = response.file.publicUrl || "";
            }
            if (inputs.buttonLabel && !String(inputs.buttonLabel.value || "").trim()) {
                inputs.buttonLabel.value = "دانلود";
            }
            if (ui.pathInput) {
                ui.pathInput.value = response.file.relativeDir || task.pathValue;
            }
            if (ui.fileInput) {
                ui.fileInput.value = "";
            }
            if (ui.nameInput) {
                ui.nameInput.value = "";
            }

            state.uploadProgress.visible = true;
            state.uploadProgress.phase = "done";
            state.uploadProgress.progress = 100;
            state.uploadProgress.transferredBytes = Number(task.file.size || state.uploadProgress.transferredBytes || 0);
            state.uploadProgress.totalBytes = Number(task.file.size || state.uploadProgress.totalBytes || 0);
            state.uploadProgress.speedBps = 0;
            state.uploadProgress.etaSeconds = 0;
            state.uploadProgress.completedAt = new Date().toISOString();
            syncSelectedHostFileMeta();
            renderHostProgress();
            setHostStatus(response.message || "فایل روی هاست دانلود ذخیره شد و لینک مستقیم آن روی کارت قرار گرفت.", "success", response.file.publicUrl || "");
            state.uploadBusy = false;
            resetHostUploadRetryState();
            syncDownloadHostUi();
        };

        xhr.onerror = function () {
            moveUploadToWaiting(hostUploadWaitingMessage());
        };

        xhr.onabort = function () {
            var canceledByUser = state.uploadCancelRequested;
            state.uploadXhr = null;
            state.uploadCancelRequested = false;
            if (canceledByUser) {
                state.uploadBusy = false;
                resetHostUploadRetryState();
                state.uploadProgress.phase = "error";
                state.uploadProgress.speedBps = 0;
                state.uploadProgress.etaSeconds = NaN;
                renderHostProgress();
                syncDownloadHostUi();
                setHostStatus("آپلود فایل از طرف کاربر لغو شد.", "");
                return;
            }
            moveUploadToWaiting(hostUploadWaitingMessage());
        };

        xhr.send(task.file);
    }

    function renderTerm() {
        var termData = state.termData;
        clearCards();
        var fragment = document.createDocumentFragment();
        applyPagePresentation(termData || {
            termNumber: term,
            termLabel: "ترم " + toFaDigits(term),
            mode: isUnitMode() ? "curriculum-unit" : ""
        });

        if (!termData) {
            emptyBox.hidden = false;
            emptyBox.textContent = state.loadError || "داده‌ای برای این ترم دریافت نشد.";
            syncEditUi();
            syncManagePanel();
            syncDownloadHostUi();
            return;
        }

        if (termTitle) {
            termTitle.textContent = termData.title || termTitle.textContent;
        }
        if (termDescription) {
            termDescription.textContent = termData.description || termDescription.textContent;
        }

        var items = Array.isArray(termData.items) ? termData.items : [];
        if (!items.length) {
            emptyBox.hidden = false;
            emptyBox.textContent = termData.emptyMessage || "هنوز منبعی ثبت نشده است.";
        } else {
            emptyBox.hidden = true;
            items.forEach(function (item) {
                fragment.appendChild(buildCard(item));
            });
            cardsContainer.appendChild(fragment);
        }

        if (managePanel) {
            managePanel.hidden = !state.canManage;
        }
        syncEditUi();
        syncManagePanel();
        syncDownloadHostUi();
    }

    function setSaving(saving) {
        state.saving = !!saving;
        syncEditUi();
        renderTerm();
    }

    function setDeletingItemId(itemId) {
        state.deletingItemId = Number(itemId) || 0;
        renderTerm();
    }

    function loadTerm(options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        var silent = options && options.silent;
        if (!silent) {
            emptyBox.hidden = false;
            emptyBox.textContent = "در حال دریافت منابع این ترم...";
        }
        state.loadError = "";

        return request("term", "GET", {}).then(function (payload) {
            if (handleUnauthorized(payload)) {
                state.canManage = false;
                state.termData = payload.term || state.termData;
                state.downloadHost = payload.downloadHost || null;
                renderTerm();
                return;
            }

            if (!payload || !payload.success || !payload.term) {
                throw new Error((payload && payload.error) || "دریافت منابع ناموفق بود.");
            }

            state.termData = payload.term;
            state.canManage = !!payload.canManage;
            state.loadError = "";
            state.downloadHost = payload.downloadHost || null;
            if (state.editingItemId && !findItem(state.editingItemId)) {
                state.editingItemId = 0;
            }
            renderTerm();
        }).catch(function (error) {
            state.termData = null;
            state.loadError = error && error.message ? error.message : "دریافت منابع با خطا مواجه شد.";
            state.canManage = false;
            state.downloadHost = null;
            if (managePanel) {
                managePanel.hidden = true;
            }
            renderTerm();
        }).finally(function () {
            state.loading = false;
        });
    }

    function readFormPayload() {
        var inputs = formInputs();
        return {
            badge: inputs.badge ? inputs.badge.value : "",
            title: inputs.title ? inputs.title.value : "",
            description: inputs.description ? inputs.description.value : "",
            buttonLabel: inputs.buttonLabel ? inputs.buttonLabel.value : "",
            buttonUrl: inputs.buttonUrl ? inputs.buttonUrl.value : ""
        };
    }

    function bindManageForm() {
        if (!manageForm) {
            return;
        }

        ensureCancelEditButton();
        ensureDownloadHostUi();

        manageForm.addEventListener("submit", function (event) {
            event.preventDefault();
            if (state.saving) {
                return;
            }

            var payload = readFormPayload();
            if (!payload.badge.trim() || !payload.title.trim() || !payload.description.trim() || !payload.buttonLabel.trim() || !payload.buttonUrl.trim()) {
                state.manageExpanded = true;
                syncManagePanel();
                setFeedback("همه فیلدهای کارت را کامل وارد کنید.", "error");
                return;
            }

            var isEdit = state.editingItemId > 0;
            var action = isEdit ? "editItem" : "addItem";
            if (isEdit) {
                var editingItem = findItem(state.editingItemId);
                payload.itemId = String(state.editingItemId);
                payload.term = String(resolveItemStorageTerm(editingItem));
            }

            state.manageExpanded = true;
            setFeedback("", "");
            setSaving(true);

            request(action, "POST", payload).then(function (response) {
                if (handleUnauthorized(response)) {
                    throw new Error("برای مدیریت منابع باید وارد حساب مجاز شوید.");
                }

                if (!response || !response.success || !response.item) {
                    throw new Error((response && response.error) || "ذخیره کارت منبع انجام نشد.");
                }

                applySavedItem(response.item, isEdit);
                state.editingItemId = 0;
                state.manageExpanded = true;
                clearForm();
                renderTerm();
                setFeedback(response.message || "کارت منبع ذخیره شد.", "success");
            }).catch(function (error) {
                state.manageExpanded = true;
                setFeedback(error && error.message ? error.message : "ذخیره کارت منبع با خطا مواجه شد.", "error");
            }).finally(function () {
                setSaving(false);
            });
        });
    }

    function bindCardActions() {
        cardsContainer.addEventListener("click", function (event) {
            var editButton = event.target && event.target.closest ? event.target.closest("[data-notes-edit='true']") : null;
            var deleteButton = event.target && event.target.closest ? event.target.closest("[data-notes-delete='true']") : null;

            if (editButton) {
                if (!state.canManage || state.saving || state.deletingItemId) {
                    return;
                }

                var editItemId = Number(editButton.getAttribute("data-item-id") || "0");
                if (!Number.isFinite(editItemId) || editItemId <= 0) {
                    return;
                }

                startEditing(findItem(editItemId));
                return;
            }

            if (!deleteButton) {
                return;
            }

            if (!state.canManage || state.saving || state.deletingItemId) {
                return;
            }

            var itemId = Number(deleteButton.getAttribute("data-item-id") || "0");
            if (!Number.isFinite(itemId) || itemId <= 0) {
                return;
            }

            var confirmed = window.confirm("این کارت منبع حذف شود؟");
            if (!confirmed) {
                return;
            }

            state.manageExpanded = true;
            setFeedback("", "");
            setDeletingItemId(itemId);

            request("deleteItem", "POST", {
                itemId: String(itemId),
                term: String(resolveItemStorageTerm(findItem(itemId)))
            }).then(function (response) {
                if (handleUnauthorized(response)) {
                    throw new Error("برای مدیریت منابع باید وارد حساب مجاز شوید.");
                }

                if (!response || !response.success || !response.item) {
                    throw new Error((response && response.error) || "حذف کارت انجام نشد.");
                }

                if (state.termData && Array.isArray(state.termData.items)) {
                    state.termData.items = state.termData.items.filter(function (item) {
                        return Number(item.id || 0) !== itemId;
                    });
                }
                if (state.editingItemId === itemId) {
                    state.editingItemId = 0;
                    clearForm();
                }
                state.manageExpanded = true;
                renderTerm();
                setFeedback(response.message || "کارت منبع حذف شد.", "success");
            }).catch(function (error) {
                state.manageExpanded = true;
                setFeedback(error && error.message ? error.message : "حذف کارت با خطا مواجه شد.", "error");
            }).finally(function () {
                setDeletingItemId(0);
            });
        });
    }

    function watchAuthChanges() {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }

        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }

            state.authKey = nextKey;
            loadTerm({ silent: true });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindManageForm();
        bindCardActions();
        watchAuthChanges();
        loadTerm({ silent: false });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
