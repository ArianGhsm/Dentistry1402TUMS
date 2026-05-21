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

    if (!cardsContainer || !emptyBox) {
        return;
    }

    var searchParams = new URLSearchParams(window.location.search || "");
    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;
    var cohort = authApi && typeof authApi.resolvePageCohort === "function"
        ? authApi.resolvePageCohort("notesCohort")
        : String(document.body.dataset.notesCohort || searchParams.get("cohort") || "1402");
    if (cohort === "main" || cohort === "dentistry-1402") {
        cohort = "1402";
    } else if (cohort === "dentistry-1403") {
        cohort = "1403";
    }
    var rawTerm = String(document.body.dataset.termNumber || searchParams.get("term") || "");
    var term = Number(rawTerm || "0");
    if (cohort !== "1402" && cohort !== "1403" && cohort !== "prosthesis-1402") {
        return;
    }
    if (cohort === "1402" && (!Number.isFinite(term) || term < 5 || term > 12)) {
        return;
    }
    if (cohort === "prosthesis-1402" && (!Number.isFinite(term) || term <= 0)) {
        return;
    }
    if (backLink && cohort !== "1402") {
        if (authApi && typeof authApi.appendCohortQuery === "function") {
            backLink.href = authApi.appendCohortQuery("/notes/", cohort);
        } else {
            backLink.href = "/notes/?cohort=" + encodeURIComponent(cohort);
        }
    }

    var state = {
        termData: null,
        canManage: false,
        loading: false,
        saving: false,
        deletingItemId: 0,
        editingItemId: 0,
        authKey: "",
        downloadHost: null,
        uploadBusy: false,
        downloadHostPathTouched: false
    };

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

    function withContextPayload(payload) {
        var next = Object.assign({ cohort: cohort }, payload || {});
        if (cohort === "1402" || cohort === "prosthesis-1402") {
            next.term = String(term);
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
                term: cohort === "1402" || cohort === "prosthesis-1402" ? term : 0,
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
            fileMeta: $("notes-host-file-meta"),
            status: $("notes-host-status"),
            preview: $("notes-host-preview")
        };
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
        var sizeLabel = Math.max(1, Math.round(Number(file.size || 0) / 1024));
        ui.fileMeta.textContent = file.name + " • " + String(sizeLabel) + " KB";
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
            '<p id="notes-host-status" class="notes-manage-feedback" hidden></p>',
            '<a id="notes-host-preview" class="notes-host-tools__preview" href="#" target="_blank" rel="noopener noreferrer" hidden></a>'
        ].join("");

        if (addSubmit) {
            addSubmit.insertAdjacentElement("beforebegin", shell);
        } else {
            manageForm.appendChild(shell);
        }

        var ui = hostUi();
        if (ui.pickButton && ui.fileInput) {
            ui.pickButton.addEventListener("click", function () {
                if (!state.uploadBusy) {
                    ui.fileInput.click();
                }
            });
            ui.fileInput.addEventListener("change", function () {
                syncSelectedHostFileMeta();
                setHostStatus("", "");
            });
        }
        if (ui.pathInput) {
            ui.pathInput.addEventListener("input", function () {
                state.downloadHostPathTouched = true;
            });
        }
        if (ui.uploadButton) {
            ui.uploadButton.addEventListener("click", uploadSelectedHostFile);
        }
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
            ui.uploadButton.disabled = state.uploadBusy || !info.enabled || !info.canUpload;
            ui.uploadButton.textContent = state.uploadBusy ? "در حال آپلود..." : "آپلود به هاست دانلود";
        }
        if (ui.pickButton) {
            ui.pickButton.disabled = state.uploadBusy || !info.enabled || !info.canUpload;
        }
        if (ui.nameInput) {
            ui.nameInput.disabled = state.uploadBusy || !info.enabled || !info.canUpload;
        }
        if (ui.pathInput) {
            ui.pathInput.disabled = state.uploadBusy || !info.enabled || !info.canUpload;
        }

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

    function uploadSelectedHostFile() {
        var info = state.downloadHost || {};
        var ui = hostUi();
        if (!info.enabled || !info.canUpload) {
            setHostStatus("آپلود مستقیم برای این صفحه فعال نیست.", "error");
            return;
        }

        var file = selectedHostFile();
        var pathValue = ui.pathInput ? String(ui.pathInput.value || "").trim() : "";
        if (!file) {
            setHostStatus("ابتدا فایل موردنظر را انتخاب کنید.", "error");
            return;
        }
        if (!pathValue) {
            setHostStatus("پوشه مقصد روی هاست دانلود را مشخص کنید.", "error");
            return;
        }

        var payload = new FormData();
        payload.append("cohort", cohort);
        if (cohort === "1402" || cohort === "prosthesis-1402") {
            payload.append("term", String(term));
        }
        payload.append("path", pathValue);
        if (ui.nameInput && String(ui.nameInput.value || "").trim()) {
            payload.append("fileName", String(ui.nameInput.value || "").trim());
        }
        payload.append("file", file);

        state.uploadBusy = true;
        syncDownloadHostUi();
        setHostStatus("فایل در حال انتقال به هاست دانلود است...", "");

        requestFormData("downloadHostUpload", payload).then(function (response) {
            if (handleUnauthorized(response)) {
                throw new Error("برای مدیریت منابع باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success || !response.file) {
                throw new Error((response && response.error) || "آپلود فایل روی هاست دانلود انجام نشد.");
            }

            var inputs = formInputs();
            if (inputs.buttonUrl) {
                inputs.buttonUrl.value = response.file.publicUrl || "";
            }
            if (inputs.buttonLabel && !String(inputs.buttonLabel.value || "").trim()) {
                inputs.buttonLabel.value = "دانلود";
            }
            if (ui.pathInput) {
                ui.pathInput.value = response.file.relativeDir || pathValue;
            }
            if (ui.fileInput) {
                ui.fileInput.value = "";
            }
            if (ui.nameInput) {
                ui.nameInput.value = "";
            }
            syncSelectedHostFileMeta();
            setHostStatus(response.message || "فایل روی هاست دانلود ذخیره شد و لینک مستقیم آن روی کارت قرار گرفت.", "success", response.file.publicUrl || "");
        }).catch(function (error) {
            setHostStatus(error && error.message ? error.message : "آپلود فایل با خطا مواجه شد.", "error");
        }).finally(function () {
            state.uploadBusy = false;
            syncDownloadHostUi();
        });
    }

    function renderTerm() {
        var termData = state.termData;
        clearCards();

        if (!termData) {
            emptyBox.hidden = false;
            emptyBox.textContent = cohort === "1403"
                ? "داده‌ای برای این آرشیو دریافت نشد."
                : "داده‌ای برای این ترم دریافت نشد.";
            syncEditUi();
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
                cardsContainer.appendChild(buildCard(item));
            });
        }

        if (managePanel) {
            managePanel.hidden = !state.canManage;
        }
        syncEditUi();
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
            emptyBox.textContent = cohort === "1403"
                ? "در حال دریافت منابع آرشیو..."
                : "در حال دریافت منابع این ترم...";
        }

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
            state.downloadHost = payload.downloadHost || null;
            if (state.editingItemId && !findItem(state.editingItemId)) {
                state.editingItemId = 0;
            }
            renderTerm();
        }).catch(function (error) {
            emptyBox.hidden = false;
            emptyBox.textContent = error && error.message ? error.message : "دریافت منابع با خطا مواجه شد.";
            state.canManage = false;
            state.downloadHost = null;
            if (managePanel) {
                managePanel.hidden = true;
            }
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
                setFeedback("همه فیلدهای کارت را کامل وارد کنید.", "error");
                return;
            }

            var isEdit = state.editingItemId > 0;
            var action = isEdit ? "editItem" : "addItem";
            if (isEdit) {
                payload.itemId = String(state.editingItemId);
            }

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
                clearForm();
                renderTerm();
                setFeedback(response.message || "کارت منبع ذخیره شد.", "success");
            }).catch(function (error) {
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

            setFeedback("", "");
            setDeletingItemId(itemId);

            request("deleteItem", "POST", {
                itemId: String(itemId)
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
                renderTerm();
                setFeedback(response.message || "کارت منبع حذف شد.", "success");
            }).catch(function (error) {
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
