(function () {
    "use strict";

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
            switch (char) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case "\"": return "&quot;";
                case "'": return "&#39;";
                default: return char;
            }
        });
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return "۰۱۲۳۴۵۶۷۸۹"[Number(digit)] || digit;
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
        var fixed = bytes >= 10 || index === 0 ? Math.round(bytes) : Math.round(bytes * 10) / 10;
        return String(fixed).replace(/\B(?=(\d{3})+(?!\d))/g, ",").replace(/\d/g, function (digit) {
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

    function createPickerSignal(code, message) {
        var error = new Error(message || code || "picker");
        error.code = code || "picker";
        return error;
    }

    function normalizePath(value) {
        return String(value == null ? "" : value)
            .trim()
            .replace(/\\/g, "/")
            .replace(/\/+/g, "/")
            .replace(/^\/+|\/+$/g, "");
    }

    function mergeContextPayload(config, payload) {
        var data = Object.assign({}, payload || {});
        if (typeof config.getTerm === "function") {
            var term = Number(config.getTerm() || 0);
            if (Number.isFinite(term) && term > 0 && data.term === undefined) {
                data.term = String(term);
            }
        }
        if (typeof config.getContext === "function") {
            var context = config.getContext() || null;
            var unitKey = context && context.unitKey ? String(context.unitKey).trim().toLowerCase() : "";
            if (unitKey) {
                if (data.unit === undefined) {
                    data.unit = unitKey;
                }
                if (data.unitKey === undefined) {
                    data.unitKey = unitKey;
                }
            }
            if (context && context.title && data.termTitle === undefined) {
                data.termTitle = String(context.title || "");
            }
        }
        if (typeof config.getCohort === "function" && data.cohort === undefined) {
            var cohort = String(config.getCohort() || "").trim();
            if (cohort) {
                data.cohort = cohort;
            }
        }
        return data;
    }

    function create(config) {
        if (!config || !config.manageForm || typeof config.request !== "function") {
            return {
                sync: function () {},
                focusPrimary: function () {}
            };
        }

        var prefix = String(config.prefix || "notes-host").trim() || "notes-host";
        var manageForm = config.manageForm;
        var insertBeforeNode = config.insertBeforeNode || null;
        var linkInput = config.linkInput || null;
        var buttonLabelInput = config.buttonLabelInput || null;
        var shell = null;

        var state = {
            canManage: false,
            info: null,
            path: "",
            pathTouched: false,
            manualLink: false,
            manualLinkTouched: false,
            browserMode: "folder",
            browserOpen: false,
            browserPath: "",
            browserEntries: [],
            browserBreadcrumbs: [],
            browserMissingDirectory: false,
            browserBusy: false,
            ensureBusy: false,
            ensuredPath: "",
            uploadBusy: false,
            uploadRetryPending: false,
            uploadRetryTimer: 0,
            uploadRetryCount: 0,
            uploadTask: null,
            uploadXhr: null,
            uploadCancelRequested: false,
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

        function id(name) {
            return prefix + "-" + name;
        }

        function node(name) {
            return document.getElementById(id(name));
        }

        function setStatus(text, kind, previewUrl) {
            var status = node("status");
            var preview = node("preview");
            if (status) {
                status.textContent = text || "";
                status.dataset.kind = kind || "";
                status.hidden = !text;
            }
            if (preview) {
                preview.hidden = !previewUrl;
                if (previewUrl) {
                    preview.href = previewUrl;
                    preview.textContent = "باز کردن لینک مستقیم فایل";
                } else {
                    preview.removeAttribute("href");
                    preview.textContent = "";
                }
            }
        }

        function currentDefaultPath() {
            var info = state.info || {};
            return normalizePath(info.defaultRelativeDir || "");
        }

        function currentScopeRoot() {
            var info = state.info || {};
            return normalizePath(info.scopeRoot || "");
        }

        function currentPublicBaseUrl() {
            var info = state.info || {};
            return String(info.publicBaseUrl || "").trim();
        }

        function isManagedLinkValue(value) {
            var normalized = String(value || "").trim();
            if (!normalized) {
                return true;
            }
            var publicBaseUrl = currentPublicBaseUrl();
            return !!(publicBaseUrl && normalized.indexOf(publicBaseUrl) === 0);
        }

        function syncManualLinkFromInput() {
            if (!linkInput || state.manualLinkTouched) {
                return;
            }
            state.manualLink = !isManagedLinkValue(linkInput.value);
        }

        function selectedFile() {
            var input = node("file-input");
            return input && input.files && input.files[0] ? input.files[0] : null;
        }

        function syncFileMeta() {
            var meta = node("file-meta");
            if (!meta) {
                return;
            }
            var file = selectedFile();
            meta.textContent = file ? (file.name + " • " + formatBytes(file.size || 0)) : "هنوز فایلی برای آپلود انتخاب نشده است.";
        }

        function clearRetryTimer() {
            if (!state.uploadRetryTimer) {
                return;
            }
            window.clearTimeout(state.uploadRetryTimer);
            state.uploadRetryTimer = 0;
        }

        function hasPendingRetry() {
            return !!(state.uploadRetryPending && state.uploadTask && state.uploadTask.file);
        }

        function retryDelayMs() {
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

        function waitingMessage() {
            if (isOffline()) {
                return "اتصال اینترنت قطع شده است. فایل در صف می‌ماند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شود.";
            }
            if (Number(state.uploadProgress && state.uploadProgress.progress || 0) >= 99) {
                return "ارتباط در مرحله نهایی قطع شد. به محض پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
            }
            return "ارتباط آپلود دچار اختلال شد. بعد از پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
        }

        function resetRetryState() {
            clearRetryTimer();
            state.uploadRetryPending = false;
            state.uploadRetryCount = 0;
            state.uploadTask = null;
        }

        function scheduleRetry(delayMs) {
            clearRetryTimer();
            if (!hasPendingRetry()) {
                return;
            }
            state.uploadRetryTimer = window.setTimeout(function () {
                state.uploadRetryTimer = 0;
                if (state.uploadBusy || !hasPendingRetry()) {
                    return;
                }
                uploadSelectedFile(true);
            }, Math.max(1200, Number(delayMs || 0)));
        }

        function progressLabel() {
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

        function renderProgress() {
            var progress = node("progress");
            var progressLabelNode = node("progress-label");
            var progressPercent = node("progress-percent");
            var progressBar = node("progress-bar");
            var progressSize = node("progress-size");
            var progressSpeed = node("progress-speed");
            var progressEta = node("progress-eta");
            if (!progress) {
                return;
            }

            var snapshot = state.uploadProgress || {};
            progress.hidden = !snapshot.visible;
            progress.dataset.phase = snapshot.phase || "idle";
            if (progress.hidden) {
                return;
            }

            var percent = Math.max(0, Math.min(100, Number(snapshot.progress || 0)));
            if (progressLabelNode) {
                progressLabelNode.textContent = progressLabel();
            }
            if (progressPercent) {
                progressPercent.textContent = formatPercent(percent) + "%";
            }
            if (progressBar) {
                progressBar.style.width = percent.toFixed(1) + "%";
            }
            if (progressSize) {
                progressSize.textContent = "انتقال: " + formatBytes(snapshot.transferredBytes || 0) + " / " + formatBytes(snapshot.totalBytes || 0);
            }
            if (progressSpeed) {
                progressSpeed.textContent = snapshot.phase === "done"
                    ? ("سرعت نهایی: " + formatSpeed(snapshot.speedBps))
                    : ("سرعت: " + formatSpeed(snapshot.speedBps));
            }
            if (progressEta) {
                progressEta.textContent = snapshot.phase === "done"
                    ? ("اتمام: " + (snapshot.completedAt ? new Intl.DateTimeFormat("fa-IR", {
                        dateStyle: "short",
                        timeStyle: "short"
                    }).format(new Date(snapshot.completedAt)) : "اکنون"))
                    : (snapshot.phase === "waiting"
                        ? "تلاش دوباره: بعد از پایدار شدن اتصال، آپلود خودکار تکرار می‌شود."
                        : "زمان باقی‌مانده: " + formatEta(snapshot.etaSeconds));
            }
        }

        function requestPayload(payload) {
            return mergeContextPayload(config, payload);
        }

        function createShell() {
            if (shell) {
                return;
            }

            shell = document.createElement("section");
            shell.className = "notes-host-tools";
            shell.id = id("shell");
            shell.innerHTML = [
                '<div class="notes-host-tools__head">',
                '  <div>',
                '    <span class="notes-host-tools__eyebrow">هاست دانلود</span>',
                '    <strong>انتخاب پوشه و فایل بدون تایپ دستی مسیر</strong>',
                '    <p>مسیر پیش‌فرض بر اساس همین ترم و همین واحد تنظیم می‌شود. اگر بخواهی جای دیگری بگذاری، پوشه را از داخل مرورگر فولدری انتخاب کن و لازم نیست path را دستی بنویسی.</p>',
                '  </div>',
                '  <a id="' + id("manager-link") + '" class="notes-host-tools__manager" href="/notes/files/">فایل‌منیجر فولدری</a>',
                '</div>',
                '<div class="notes-host-tools__mode">',
                '  <div class="notes-host-tools__mode-copy">',
                '    <strong>نحوه تنظیم لینک کارت</strong>',
                '    <small id="' + id("mode-hint") + '">به‌صورت پیش‌فرض، لینک کارت از فایل همین بخش پر می‌شود و لازم نیست path را دستی بنویسی.</small>',
                '  </div>',
                '  <button id="' + id("toggle-manual-link") + '" class="notes-card-edit" type="button">استفاده از لینک بیرونی</button>',
                '</div>',
                '<div class="notes-host-tools__grid">',
                '  <label class="notes-manage-panel__field notes-manage-panel__field--full">',
                '    <span>پوشه مقصد روی هاست دانلود</span>',
                '    <div class="notes-host-tools__pathbar">',
                '      <input id="' + id("path-display") + '" type="text" class="notes-host-tools__path-readonly" readonly value="/">',
                '      <div class="notes-host-tools__path-actions">',
                '        <button id="' + id("browse-folder") + '" class="notes-card-edit" type="button">انتخاب پوشه</button>',
                '        <button id="' + id("reset-path") + '" class="notes-card-edit" type="button">پوشه پیش‌فرض</button>',
                '      </div>',
                '    </div>',
                '    <small id="' + id("path-hint") + '" class="notes-host-tools__hint">پوشه مقصد همین ترم و واحد به‌صورت خودکار انتخاب می‌شود.</small>',
                '  </label>',
                '  <label class="notes-manage-panel__field">',
                '    <span>نام فایل نهایی (اختیاری)</span>',
                '    <input id="' + id("file-name") + '" type="text" maxlength="200" placeholder="مثلاً جزوه اندو ۱.pdf">',
                '  </label>',
                '  <div class="notes-host-tools__picker">',
                '    <span>فایل</span>',
                '    <input id="' + id("file-input") + '" type="file" hidden>',
                '    <button id="' + id("pick-file") + '" class="notes-card-edit" type="button">انتخاب فایل</button>',
                '    <small id="' + id("file-meta") + '">هنوز فایلی برای آپلود انتخاب نشده است.</small>',
                '  </div>',
                '</div>',
                '<div class="notes-host-tools__actions">',
                '  <button id="' + id("upload") + '" class="card-btn" type="button">آپلود به هاست دانلود</button>',
                '  <button id="' + id("browse-file") + '" class="notes-card-edit" type="button">انتخاب فایل موجود</button>',
                '  <button id="' + id("cancel-upload") + '" class="notes-card-delete" type="button" hidden>لغو آپلود</button>',
                '</div>',
                '<section id="' + id("browser") + '" class="notes-host-browser" hidden>',
                '  <div class="notes-host-browser__head">',
                '    <div>',
                '      <strong id="' + id("browser-title") + '">انتخاب پوشه مقصد</strong>',
                '      <p id="' + id("browser-copy") + '">پوشه فعلی را از بین فولدرها و ساب‌فولدرها انتخاب کن.</p>',
                '    </div>',
                '    <div class="notes-host-browser__actions">',
                '      <button id="' + id("browser-choose-current") + '" class="card-btn" type="button">انتخاب همین پوشه</button>',
                '      <button id="' + id("browser-create-folder") + '" class="notes-card-edit" type="button">پوشه جدید</button>',
                '      <button id="' + id("browser-close") + '" class="notes-card-delete" type="button">بستن</button>',
                '    </div>',
                '  </div>',
                '  <div id="' + id("browser-breadcrumbs") + '" class="notes-host-browser__breadcrumbs"></div>',
                '  <p id="' + id("browser-feedback") + '" class="notes-manage-feedback" hidden></p>',
                '  <p id="' + id("browser-empty") + '" class="notes-host-browser__empty" hidden></p>',
                '  <div id="' + id("browser-entries") + '" class="notes-host-browser__entries"></div>',
                '</section>',
                '<section id="' + id("progress") + '" class="notes-host-progress" hidden>',
                '  <div class="notes-host-progress__head">',
                '    <strong id="' + id("progress-label") + '">آماده برای آپلود</strong>',
                '    <span id="' + id("progress-percent") + '">۰%</span>',
                '  </div>',
                '  <div class="notes-host-progress__track"><span id="' + id("progress-bar") + '"></span></div>',
                '  <div class="notes-host-progress__stats">',
                '    <span id="' + id("progress-size") + '">انتقال: ۰ بایت / ۰ بایت</span>',
                '    <span id="' + id("progress-speed") + '">سرعت: —</span>',
                '    <span id="' + id("progress-eta") + '">زمان باقی‌مانده: —</span>',
                '  </div>',
                '</section>',
                '<p id="' + id("status") + '" class="notes-manage-feedback" hidden></p>',
                '<a id="' + id("preview") + '" class="notes-host-tools__preview" href="#" target="_blank" rel="noopener noreferrer" hidden></a>'
            ].join("");

            if (insertBeforeNode && insertBeforeNode.parentNode === manageForm) {
                manageForm.insertBefore(shell, insertBeforeNode);
            } else if (insertBeforeNode && insertBeforeNode.parentNode) {
                insertBeforeNode.parentNode.insertBefore(shell, insertBeforeNode);
            } else {
                manageForm.appendChild(shell);
            }

            bindShell();
        }

        function setBrowserFeedback(text, kind) {
            var feedback = node("browser-feedback");
            if (!feedback) {
                return;
            }
            feedback.textContent = text || "";
            feedback.dataset.kind = kind || "";
            feedback.hidden = !text;
        }

        function browserEntryMeta(entry) {
            if (!entry || typeof entry !== "object") {
                return "";
            }
            if (entry.type === "dir") {
                return entry.missing ? "پوشه آماده‌سازی‌نشده" : "پوشه";
            }
            var parts = [String(entry.sizeLabel || "").trim()];
            if (entry.modifiedAt) {
                try {
                    parts.push(new Intl.DateTimeFormat("fa-IR", {
                        dateStyle: "short",
                        timeStyle: "short"
                    }).format(new Date(entry.modifiedAt)));
                } catch (_error) {
                }
            }
            return parts.filter(Boolean).join(" • ");
        }

        function renderBrowser() {
            createShell();
            var browser = node("browser");
            var titleNode = node("browser-title");
            var copyNode = node("browser-copy");
            var breadcrumbs = node("browser-breadcrumbs");
            var entries = node("browser-entries");
            var empty = node("browser-empty");
            var chooseCurrent = node("browser-choose-current");
            var createFolderButton = node("browser-create-folder");
            if (!browser || !breadcrumbs || !entries || !empty) {
                return;
            }

            browser.hidden = !state.browserOpen || !state.canManage;
            if (browser.hidden) {
                return;
            }

            if (titleNode) {
                titleNode.textContent = state.browserMode === "file" ? "انتخاب فایل موجود" : "انتخاب پوشه مقصد";
            }
            if (copyNode) {
                copyNode.textContent = state.browserMode === "file"
                    ? "داخل فولدرها جلو برو و فایل موردنظر را مستقیم انتخاب کن."
                    : "پوشه فعلی را از بین فولدرها و ساب‌فولدرها انتخاب کن.";
            }
            if (chooseCurrent) {
                chooseCurrent.hidden = state.browserMode !== "folder";
                chooseCurrent.disabled = state.browserBusy;
            }
            if (createFolderButton) {
                createFolderButton.disabled = state.browserBusy;
            }

            breadcrumbs.innerHTML = "";
            (Array.isArray(state.browserBreadcrumbs) ? state.browserBreadcrumbs : []).forEach(function (crumb, index, array) {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "notes-host-browser__crumb" + (index === array.length - 1 ? " is-active" : "");
                button.textContent = String(crumb && crumb.label || "ریشه");
                button.setAttribute("data-browse-path", normalizePath(crumb && crumb.path || ""));
                breadcrumbs.appendChild(button);
            });

            entries.innerHTML = "";
            var items = Array.isArray(state.browserEntries) ? state.browserEntries : [];
            if (!items.length) {
                empty.hidden = false;
                empty.textContent = state.browserMissingDirectory
                    ? "این پوشه هنوز روی هاست دانلود ساخته نشده است. با انتخاب همین پوشه یا اولین آپلود، مسیر آماده می‌شود."
                    : (state.browserBusy ? "در حال دریافت فایل‌ها..." : "در این مسیر هنوز فایل یا پوشه‌ای وجود ندارد.");
                return;
            }

            empty.hidden = true;
            var fragment = document.createDocumentFragment();
            items.forEach(function (entry) {
                var article = document.createElement("article");
                article.className = "notes-host-browser__entry";
                article.dataset.entryType = String(entry.type || "file");

                var entryActions = [];
                if (entry.type === "dir") {
                    entryActions.push('<button type="button" class="notes-card-edit" data-browse-path="' + escapeHtml(entry.relativePath || "") + '">ورود</button>');
                    if (state.browserMode === "folder") {
                        entryActions.push('<button type="button" class="card-btn" data-select-folder="' + escapeHtml(entry.relativePath || "") + '">انتخاب</button>');
                    }
                } else {
                    entryActions.push('<a class="notes-card-edit" href="' + escapeHtml(entry.publicUrl || "#") + '" target="_blank" rel="noopener noreferrer">بازکردن</a>');
                    if (state.browserMode === "file") {
                        entryActions.push('<button type="button" class="card-btn" data-select-file="' + escapeHtml(entry.publicUrl || "") + '">انتخاب فایل</button>');
                    }
                }

                article.innerHTML = [
                    '<div class="notes-host-browser__entry-main">',
                    '  <strong>' + escapeHtml(entry.name || "") + '</strong>',
                    '  <small>' + escapeHtml(browserEntryMeta(entry)) + '</small>',
                    '</div>',
                    '<div class="notes-host-browser__entry-actions">' + entryActions.join("") + '</div>'
                ].join("");
                fragment.appendChild(article);
            });
            entries.appendChild(fragment);
        }

        function openBrowser(mode) {
            if (!state.canManage) {
                return;
            }
            createShell();
            state.browserMode = mode === "file" ? "file" : "folder";
            state.browserOpen = true;
            setBrowserFeedback("", "");
            var nextPath = normalizePath(state.path || currentDefaultPath() || currentScopeRoot() || "");
            loadBrowser(nextPath);
        }

        function closeBrowser() {
            state.browserOpen = false;
            renderBrowser();
        }

        function selectCurrentFolder(path) {
            var nextPath = normalizePath(path || state.browserPath || state.path || currentDefaultPath());
            if (!nextPath) {
                setBrowserFeedback("ابتدا یکی از پوشه‌های مجاز را باز کن.", "error");
                return;
            }
            state.path = nextPath;
            state.pathTouched = true;
            closeBrowser();
            syncUi();
            setStatus("پوشه مقصد از داخل فایل‌منیجر همین صفحه انتخاب شد.", "success");
        }

        function chooseExistingFile(url) {
            if (!linkInput) {
                return;
            }
            var normalized = String(url || "").trim();
            if (!normalized) {
                return;
            }
            state.manualLink = false;
            state.manualLinkTouched = false;
            linkInput.value = normalized;
            if (buttonLabelInput && !String(buttonLabelInput.value || "").trim()) {
                buttonLabelInput.value = "دانلود";
            }
            closeBrowser();
            syncUi();
            setStatus("لینک مستقیم فایل روی کارت قرار گرفت.", "success", normalized);
        }

        function loadBrowser(path) {
            if (!state.canManage) {
                return Promise.resolve();
            }
            state.browserBusy = true;
            state.browserPath = normalizePath(path || currentScopeRoot());
            renderBrowser();
            setBrowserFeedback("", "");

            return config.request("downloadHostBrowse", "GET", requestPayload({
                path: state.browserPath
            })).then(function (payload) {
                if (config.handleUnauthorized && config.handleUnauthorized(payload, "برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.")) {
                    throw new Error("برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.");
                }
                if (!payload || !payload.success || !payload.browse) {
                    throw new Error((payload && payload.error) || "دریافت فایل‌های هاست دانلود ناموفق بود.");
                }
                state.browserPath = normalizePath(payload.browse.currentPath || "");
                state.browserEntries = Array.isArray(payload.browse.entries) ? payload.browse.entries : [];
                state.browserBreadcrumbs = Array.isArray(payload.browse.breadcrumbs) ? payload.browse.breadcrumbs : [];
                state.browserMissingDirectory = !!payload.browse.missingDirectory;
                renderBrowser();
            }).catch(function (error) {
                state.browserEntries = [];
                state.browserBreadcrumbs = [];
                state.browserMissingDirectory = false;
                renderBrowser();
                setBrowserFeedback(error && error.message ? error.message : "مرور پوشه‌ها با خطا مواجه شد.", "error");
            }).finally(function () {
                state.browserBusy = false;
                renderBrowser();
            });
        }

        function ensureDefaultDirectory() {
            var info = state.info || {};
            var targetPath = normalizePath(info.defaultRelativeDir || "");
            if (!state.canManage || !info.enabled || !info.canUpload || !targetPath || state.ensureBusy || state.ensuredPath === targetPath) {
                return;
            }
            state.ensureBusy = true;
            config.request("downloadHostEnsureDir", "POST", requestPayload({
                path: targetPath
            })).then(function (payload) {
                if (config.handleUnauthorized && config.handleUnauthorized(payload, "برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.")) {
                    throw new Error("برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.");
                }
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.error) || "آماده‌سازی پوشه مقصد انجام نشد.");
                }
                state.ensuredPath = normalizePath(payload.path || targetPath);
                syncUi();
            }).catch(function (_error) {
            }).finally(function () {
                state.ensureBusy = false;
            });
        }

        function syncUi() {
            createShell();

            var info = state.info || {};
            var managerLink = node("manager-link");
            var pathDisplay = node("path-display");
            var pathHint = node("path-hint");
            var browseFolder = node("browse-folder");
            var resetPath = node("reset-path");
            var browseFile = node("browse-file");
            var pickFile = node("pick-file");
            var fileInput = node("file-input");
            var fileNameInput = node("file-name");
            var toggleManualLink = node("toggle-manual-link");
            var modeHint = node("mode-hint");
            var uploadButton = node("upload");
            var cancelUpload = node("cancel-upload");

            shell.hidden = !state.canManage;
            if (shell.hidden) {
                return;
            }

            if (!state.pathTouched && currentDefaultPath()) {
                state.path = currentDefaultPath();
            }
            syncManualLinkFromInput();
            if (managerLink) {
                managerLink.href = info.managerUrl || "/notes/files/";
            }
            if (pathDisplay) {
                pathDisplay.value = state.path || "/";
            }
            if (pathHint) {
                pathHint.textContent = state.path === currentDefaultPath()
                    ? "پوشه پیشنهادی همین بخش فعال است."
                    : "مسیر فعلی را از داخل فایل‌منیجر همین صفحه انتخاب کرده‌ای.";
            }
            if (toggleManualLink) {
                toggleManualLink.textContent = state.manualLink ? "بازگشت به فایل همین بخش" : "استفاده از لینک بیرونی";
                toggleManualLink.disabled = state.uploadBusy || state.uploadRetryPending;
                toggleManualLink.dataset.mode = state.manualLink ? "external" : "managed";
            }
            if (modeHint) {
                modeHint.textContent = state.manualLink
                    ? "حالا می‌توانی لینک بیرونی یا مسیر دلخواهت را دستی وارد کنی. اگر دوباره بخواهی از فایل همین بخش استفاده کنی، به حالت قبلی برگرد."
                    : "لینک کارت به‌صورت پیش‌فرض از فایل همین بخش پر می‌شود و لازم نیست path را دستی بنویسی.";
            }
            if (linkInput) {
                linkInput.readOnly = !state.manualLink;
                linkInput.dataset.linkMode = state.manualLink ? "external" : "managed";
                linkInput.placeholder = state.manualLink
                    ? "https://example.com/file.pdf"
                    : "با انتخاب فایل موجود یا آپلود، این فیلد خودکار پر می‌شود";
                if (state.manualLink) {
                    linkInput.removeAttribute("aria-readonly");
                    linkInput.title = "لینک بیرونی یا مسیر دلخواه را اینجا وارد کن.";
                } else {
                    linkInput.setAttribute("aria-readonly", "true");
                    linkInput.title = "این فیلد از فایل‌منیجر یا آپلود مستقیم پر می‌شود. برای لینک بیرونی، دکمه بالای این بخش را بزن.";
                }
            }

            var disabled = state.uploadBusy || state.uploadRetryPending || !info.enabled || !info.canUpload;
            if (browseFolder) browseFolder.disabled = state.browserBusy || !info.enabled || !info.canUpload;
            if (resetPath) resetPath.disabled = !currentDefaultPath() || disabled;
            if (browseFile) browseFile.disabled = state.browserBusy || !info.enabled;
            if (pickFile) pickFile.disabled = disabled;
            if (fileInput) fileInput.disabled = disabled;
            if (fileNameInput) fileNameInput.disabled = disabled;
            if (uploadButton) {
                uploadButton.disabled = disabled;
                uploadButton.textContent = state.uploadRetryPending
                    ? "در انتظار تلاش دوباره..."
                    : (state.uploadBusy ? "در حال آپلود..." : "آپلود به هاست دانلود");
            }
            if (cancelUpload) {
                cancelUpload.hidden = !(state.uploadBusy || state.uploadRetryPending);
                cancelUpload.disabled = !(state.uploadXhr || state.uploadRetryPending);
            }
            renderProgress();
            syncFileMeta();

            if (!info.enabled) {
                setStatus("تنظیمات هاست دانلود روی این سرور هنوز کامل نشده است.", "error");
                return;
            }
            if (!info.canUpload) {
                setStatus("مدیریت فایل این بخش برای حساب فعلی فعال نیست.", "error");
                return;
            }
            if (!node("status") || node("status").hidden || !String(node("status").textContent || "").trim()) {
                setStatus("همین‌جا می‌توانی پوشه مقصد را انتخاب کنی، فایل تازه آپلود کنی یا فایل موجود را روی کارت بنشانی.", "");
            }
            ensureDefaultDirectory();
        }

        function buildUploadUrl(pathValue, fileName) {
            var params = requestPayload({
                path: pathValue
            });
            if (fileName) {
                params.fileName = fileName;
            }
            var search = new URLSearchParams();
            search.set("action", "downloadHostUpload");
            Object.keys(params).forEach(function (key) {
                var value = params[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    search.set(key, String(value));
                }
            });
            return "/api/notes_api.php?" + search.toString();
        }

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
            renderProgress();
            syncUi();
            setStatus(message || waitingMessage(), "");
            scheduleRetry(isOffline() ? 2500 : retryDelayMs());
        }

        function uploadSelectedFile(resumeOnly) {
            var info = state.info || {};
            if (!info.enabled || !info.canUpload) {
                if (resumeOnly) {
                    state.uploadBusy = false;
                    resetRetryState();
                    syncUi();
                }
                setStatus("آپلود مستقیم برای این بخش فعال نیست.", "error");
                return;
            }

            var task = resumeOnly ? state.uploadTask : null;
            if (!task) {
                var file = selectedFile();
                var pathValue = normalizePath(state.path || currentDefaultPath());
                var fileNameInput = node("file-name");
                var fileName = fileNameInput ? String(fileNameInput.value || "").trim() : "";
                if (!file) {
                    setStatus("ابتدا فایل موردنظر را انتخاب کن.", "error");
                    return;
                }
                if (!pathValue) {
                    setStatus("ابتدا پوشه مقصد را انتخاب کن.", "error");
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
                setStatus("فایل انتخاب‌شده برای ادامه آپلود در دسترس نیست.", "error");
                state.uploadBusy = false;
                resetRetryState();
                syncUi();
                return;
            }

            clearRetryTimer();
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
            syncUi();
            setStatus(resumeOnly ? "اتصال برگشت و آپلود دوباره تلاش شد..." : "فایل در حال انتقال به هاست دانلود است...", "");
            renderProgress();

            var startedAt = Date.now();
            var xhr = new XMLHttpRequest();
            state.uploadCancelRequested = false;
            state.uploadXhr = xhr;
            xhr.open("POST", buildUploadUrl(task.pathValue, task.fileName), true);
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
                renderProgress();
            };

            xhr.upload.onload = function () {
                state.uploadProgress.visible = true;
                state.uploadProgress.phase = "finalizing";
                state.uploadProgress.progress = 100;
                state.uploadProgress.transferredBytes = Number(task.file.size || state.uploadProgress.transferredBytes || 0);
                state.uploadProgress.totalBytes = Number(task.file.size || state.uploadProgress.totalBytes || 0);
                state.uploadProgress.speedBps = 0;
                state.uploadProgress.etaSeconds = 0;
                renderProgress();
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

                if (config.handleUnauthorized && config.handleUnauthorized(response, "برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.")) {
                    resetRetryState();
                    state.uploadProgress.phase = "error";
                    state.uploadProgress.etaSeconds = NaN;
                    renderProgress();
                    setStatus("برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.", "error");
                    state.uploadBusy = false;
                    syncUi();
                    return;
                }
                if (!response || !response.success || !response.file) {
                    resetRetryState();
                    state.uploadProgress.phase = "error";
                    state.uploadProgress.speedBps = 0;
                    state.uploadProgress.etaSeconds = NaN;
                    renderProgress();
                    state.uploadBusy = false;
                    syncUi();
                    setStatus((response && response.error) || "آپلود فایل روی هاست دانلود انجام نشد.", "error");
                    return;
                }

                if (linkInput) {
                    state.manualLink = false;
                    state.manualLinkTouched = false;
                    linkInput.value = response.file.publicUrl || "";
                }
                if (buttonLabelInput && !String(buttonLabelInput.value || "").trim()) {
                    buttonLabelInput.value = "دانلود";
                }
                if (response.file.relativeDir) {
                    state.path = normalizePath(response.file.relativeDir);
                    state.pathTouched = true;
                }
                var fileInput = node("file-input");
                var fileNameInput = node("file-name");
                if (fileInput) {
                    fileInput.value = "";
                }
                if (fileNameInput) {
                    fileNameInput.value = "";
                }

                state.uploadProgress.visible = true;
                state.uploadProgress.phase = "done";
                state.uploadProgress.progress = 100;
                state.uploadProgress.transferredBytes = Number(task.file.size || state.uploadProgress.transferredBytes || 0);
                state.uploadProgress.totalBytes = Number(task.file.size || state.uploadProgress.totalBytes || 0);
                state.uploadProgress.speedBps = 0;
                state.uploadProgress.etaSeconds = 0;
                state.uploadProgress.completedAt = new Date().toISOString();
                renderProgress();
                syncFileMeta();
                syncUi();
                setStatus(response.message || "فایل روی هاست دانلود ذخیره شد و لینک مستقیم آن آماده است.", "success", response.file.publicUrl || "");
                state.uploadBusy = false;
                resetRetryState();
                syncUi();
            };

            xhr.onerror = function () {
                moveUploadToWaiting(waitingMessage());
            };

            xhr.onabort = function () {
                var canceledByUser = state.uploadCancelRequested;
                state.uploadXhr = null;
                state.uploadCancelRequested = false;
                if (canceledByUser) {
                    state.uploadBusy = false;
                    resetRetryState();
                    state.uploadProgress.phase = "error";
                    state.uploadProgress.speedBps = 0;
                    state.uploadProgress.etaSeconds = NaN;
                    renderProgress();
                    syncUi();
                    setStatus("آپلود فایل از طرف کاربر لغو شد.", "");
                    return;
                }
                moveUploadToWaiting(waitingMessage());
            };

            xhr.send(task.file);
        }

        function bindShell() {
            var browseFolder = node("browse-folder");
            var resetPath = node("reset-path");
            var browseFile = node("browse-file");
            var pickFile = node("pick-file");
            var fileInput = node("file-input");
            var toggleManualLink = node("toggle-manual-link");
            var uploadButton = node("upload");
            var cancelUpload = node("cancel-upload");
            var closeButton = node("browser-close");
            var chooseCurrent = node("browser-choose-current");
            var createFolderButton = node("browser-create-folder");
            var breadcrumbs = node("browser-breadcrumbs");
            var entries = node("browser-entries");

            if (browseFolder) {
                browseFolder.addEventListener("click", function () {
                    openBrowser("folder");
                });
            }
            if (resetPath) {
                resetPath.addEventListener("click", function () {
                    state.pathTouched = false;
                    state.path = currentDefaultPath();
                    syncUi();
                    ensureDefaultDirectory();
                    setStatus("پوشه پیش‌فرض همین بخش دوباره فعال شد.", "success");
                });
            }
            if (browseFile) {
                browseFile.addEventListener("click", function () {
                    openBrowser("file");
                });
            }
            if (pickFile && fileInput) {
                pickFile.addEventListener("click", function () {
                    if (!state.uploadBusy) {
                        fileInput.click();
                    }
                });
                fileInput.addEventListener("change", function () {
                    syncFileMeta();
                    setStatus("", "");
                    state.uploadProgress = {
                        visible: !!selectedFile(),
                        phase: selectedFile() ? "queued" : "idle",
                        progress: 0,
                        transferredBytes: 0,
                        totalBytes: Number(selectedFile() && selectedFile().size || 0),
                        speedBps: 0,
                        etaSeconds: NaN,
                        completedAt: ""
                    };
                    renderProgress();
                });
            }
            if (toggleManualLink) {
                toggleManualLink.addEventListener("click", function () {
                    state.manualLink = !state.manualLink;
                    state.manualLinkTouched = true;
                    syncUi();
                    if (state.manualLink && linkInput && typeof linkInput.focus === "function") {
                        linkInput.focus();
                    }
                });
            }
            if (uploadButton) {
                uploadButton.addEventListener("click", function () {
                    uploadSelectedFile(false);
                });
            }
            if (cancelUpload) {
                cancelUpload.addEventListener("click", function () {
                    if (state.uploadXhr) {
                        state.uploadCancelRequested = true;
                        state.uploadXhr.abort();
                        return;
                    }
                    if (state.uploadRetryPending) {
                        resetRetryState();
                        state.uploadProgress.phase = "error";
                        state.uploadProgress.speedBps = 0;
                        state.uploadProgress.etaSeconds = NaN;
                        renderProgress();
                        syncUi();
                        setStatus("آپلود فایل از طرف کاربر لغو شد.", "");
                    }
                });
            }
            if (closeButton) {
                closeButton.addEventListener("click", closeBrowser);
            }
            if (chooseCurrent) {
                chooseCurrent.addEventListener("click", function () {
                    selectCurrentFolder(state.browserPath);
                });
            }
            if (createFolderButton) {
                createFolderButton.addEventListener("click", function () {
                    if (state.browserBusy) {
                        return;
                    }
                    var name = window.prompt("نام پوشه جدید را وارد کن:");
                    if (!name) {
                        return;
                    }
                    state.browserBusy = true;
                    renderBrowser();
                    config.request("downloadHostCreateDir", "POST", requestPayload({
                        path: normalizePath(state.browserPath || state.path || currentDefaultPath() || currentScopeRoot()),
                        name: name
                    })).then(function (payload) {
                        if (config.handleUnauthorized && config.handleUnauthorized(payload, "برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.")) {
                            throw new Error("برای مدیریت فایل‌های منابع باید وارد حساب مجاز شوید.");
                        }
                        if (!payload || !payload.success) {
                            throw new Error((payload && payload.error) || "ساخت پوشه انجام نشد.");
                        }
                        setBrowserFeedback(payload.message || "پوشه جدید ساخته شد.", "success");
                        return loadBrowser(state.browserPath || state.path || currentDefaultPath());
                    }).catch(function (error) {
                        setBrowserFeedback(error && error.message ? error.message : "ساخت پوشه با خطا مواجه شد.", "error");
                    }).finally(function () {
                        state.browserBusy = false;
                        renderBrowser();
                    });
                });
            }
            if (breadcrumbs) {
                breadcrumbs.addEventListener("click", function (event) {
                    var button = event.target && event.target.closest ? event.target.closest("[data-browse-path]") : null;
                    if (!button) {
                        return;
                    }
                    loadBrowser(button.getAttribute("data-browse-path") || "");
                });
            }
            if (entries) {
                entries.addEventListener("click", function (event) {
                    var browseButton = event.target && event.target.closest ? event.target.closest("[data-browse-path]") : null;
                    var selectFolderButton = event.target && event.target.closest ? event.target.closest("[data-select-folder]") : null;
                    var selectFileButton = event.target && event.target.closest ? event.target.closest("[data-select-file]") : null;
                    if (browseButton) {
                        loadBrowser(browseButton.getAttribute("data-browse-path") || "");
                        return;
                    }
                    if (selectFolderButton) {
                        selectCurrentFolder(selectFolderButton.getAttribute("data-select-folder") || "");
                        return;
                    }
                    if (selectFileButton) {
                        chooseExistingFile(selectFileButton.getAttribute("data-select-file") || "");
                    }
                });
            }

            if (!state._networkBound) {
                window.addEventListener("offline", function () {
                    if (!(state.uploadBusy || state.uploadRetryPending)) {
                        return;
                    }
                    setStatus("اتصال اینترنت قطع شد. فایل در صف می‌ماند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شود.", "");
                });
                window.addEventListener("online", function () {
                    if (!hasPendingRetry()) {
                        return;
                    }
                    setStatus("اتصال برگشت. آپلود فایل خودکار دوباره تلاش می‌شود.", "");
                    scheduleRetry(900);
                });
                state._networkBound = true;
            }
        }

        return {
            sync: function (nextState) {
                var payload = nextState || {};
                state.canManage = !!payload.canManage;
                state.info = payload.info || null;
                if (payload.forceResetPath) {
                    state.pathTouched = false;
                }
                if (!state.canManage) {
                    state.manualLink = false;
                    state.manualLinkTouched = false;
                }
                createShell();
                syncUi();
            },
            focusPrimary: function () {
                createShell();
                var button = node("browse-folder");
                if (button && typeof button.focus === "function") {
                    button.focus();
                }
            },
            resetLinkMode: function () {
                state.manualLink = false;
                state.manualLinkTouched = false;
                syncUi();
            },
            syncFromInput: function () {
                state.manualLinkTouched = false;
                syncManualLinkFromInput();
                syncUi();
            }
        };
    }

    window.Dent1402NotesHostPicker = {
        create: create
    };
})();
