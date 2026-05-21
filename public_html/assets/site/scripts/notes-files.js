(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var guard = $("ndh-guard");
    var app = $("ndh-app");
    var rootList = $("ndh-root-list");
    var currentPathLabel = $("ndh-current-path-label");
    var createFolderButton = $("ndh-create-folder");
    var refreshButton = $("ndh-refresh");
    var pickFilesButton = $("ndh-pick-files");
    var uploadInput = $("ndh-upload-input");
    var uploadQueue = $("ndh-upload-queue");
    var uploadSubmit = $("ndh-upload-submit");
    var breadcrumbs = $("ndh-breadcrumbs");
    var searchInput = $("ndh-search-input");
    var feedback = $("ndh-feedback");
    var entriesRoot = $("ndh-entries");
    var empty = $("ndh-empty");

    if (!guard || !app || !rootList || !entriesRoot || !empty) {
        return;
    }

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;
    var params = new URLSearchParams(window.location.search || "");
    var requestedPath = String(params.get("path") || "").trim();
    var requestedCohort = String(params.get("cohort") || "").trim();
    if (requestedCohort === "main" || requestedCohort === "dentistry-1402") {
        requestedCohort = "1402";
    } else if (requestedCohort === "dentistry-1403") {
        requestedCohort = "1403";
    }

    var state = {
        authKey: "",
        loading: false,
        busy: false,
        currentPath: "",
        breadcrumbs: [],
        entries: [],
        filteredEntries: [],
        roots: ["1402", "1403", "prosthesis-1402"],
        uploadItems: [],
        searchQuery: "",
        scopeCohort: requestedCohort,
        downloadHost: null
    };

    function authSnapshotKey() {
        if (!authApi || typeof authApi.getState !== "function") {
            return "anon";
        }
        var snapshot = authApi.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber
            ? String(snapshot.user.studentNumber)
            : "";
        return String(snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function loginUrl() {
        if (authApi && typeof authApi.loginUrl === "function") {
            return authApi.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function parseJsonResponse(response) {
        if (siteApi && typeof siteApi.parseJsonResponse === "function") {
            return siteApi.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function request(action, method, payload) {
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        var options = {
            method: method,
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        };
        var data = Object.assign({}, payload || {});
        if (state.scopeCohort) {
            data.cohort = state.scopeCohort;
        }

        if (method === "GET") {
            Object.keys(data).forEach(function (key) {
                var value = data[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, data));
        }

        return fetch(url, options).then(parseJsonResponse);
    }

    function requestFormData(action, formData) {
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        if (state.scopeCohort) {
            formData.set("cohort", state.scopeCohort);
        }
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            },
            body: formData
        }).then(parseJsonResponse);
    }

    function setFeedback(text, kind) {
        if (!feedback) {
            return;
        }
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
    }

    function setGuard(kind, title, copy, actionHref, actionLabel) {
        app.hidden = true;
        guard.hidden = false;
        guard.innerHTML = [
            '<div class="notes-manage-panel__head">',
            '  <h4>' + escapeHtml(title || "") + '</h4>',
            '  <p>' + escapeHtml(copy || "") + '</p>',
            '</div>',
            actionHref && actionLabel
                ? '<a class="card-btn" href="' + escapeHtml(actionHref) + '">' + escapeHtml(actionLabel) + '</a>'
                : ""
        ].join("");
        guard.dataset.kind = kind || "";
    }

    function showApp() {
        guard.hidden = true;
        app.hidden = false;
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatDate(value) {
        if (!value) {
            return "بدون تاریخ";
        }
        try {
            return new Intl.DateTimeFormat("fa-IR", {
                dateStyle: "short",
                timeStyle: "short"
            }).format(new Date(value));
        } catch (_error) {
            return value;
        }
    }

    function currentScopeRoot() {
        return state.downloadHost && state.downloadHost.scopeRoot
            ? String(state.downloadHost.scopeRoot)
            : "";
    }

    function availableRoots() {
        var scopeRoot = currentScopeRoot();
        var canManageAll = !!(state.downloadHost && state.downloadHost.canManageAllRoots);
        if (scopeRoot && !canManageAll) {
            return [scopeRoot];
        }
        return state.roots.slice();
    }

    function currentRoot() {
        var path = String(state.currentPath || "");
        if (!path) {
            return "";
        }
        return path.split("/")[0] || "";
    }

    function filterEntries() {
        var query = String(state.searchQuery || "").trim().toLowerCase();
        if (!query) {
            state.filteredEntries = state.entries.slice();
            return;
        }
        state.filteredEntries = state.entries.filter(function (entry) {
            return String(entry.name || "").toLowerCase().indexOf(query) !== -1;
        });
    }

    function updateUrl() {
        var next = new URL(window.location.href);
        if (state.currentPath) {
            next.searchParams.set("path", state.currentPath);
        } else {
            next.searchParams.delete("path");
        }
        if (state.scopeCohort) {
            next.searchParams.set("cohort", state.scopeCohort);
        } else {
            next.searchParams.delete("cohort");
        }
        window.history.replaceState({}, "", next.pathname + next.search);
    }

    function renderRoots() {
        rootList.innerHTML = "";
        availableRoots().forEach(function (root) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "ndh-root-btn" + (currentRoot() === root ? " is-active" : "");
            button.setAttribute("data-root-path", root);
            button.innerHTML = "<span><strong>" + escapeHtml(root) + "</strong><small>مسیر ریشه منابع</small></span>";
            rootList.appendChild(button);
        });
    }

    function renderBreadcrumbs() {
        breadcrumbs.innerHTML = "";
        var items = Array.isArray(state.breadcrumbs) ? state.breadcrumbs : [];
        items.forEach(function (item, index) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = index === items.length - 1 ? "is-active" : "";
            button.setAttribute("data-browse-path", String(item.path || ""));
            button.textContent = String(item.label || "");
            breadcrumbs.appendChild(button);
        });
    }

    function uploadMeta(item) {
        if (item.status === "uploading") {
            return "در حال انتقال";
        }
        if (item.status === "done") {
            return "تکمیل شد";
        }
        if (item.status === "error") {
            return item.error || "با خطا مواجه شد";
        }
        return "آماده ارسال";
    }

    function renderUploadQueue() {
        uploadQueue.innerHTML = "";
        if (!state.uploadItems.length) {
            uploadQueue.innerHTML = '<p class="archive-empty">هنوز فایلی برای آپلود انتخاب نشده است.</p>';
            return;
        }

        state.uploadItems.forEach(function (item) {
            var article = document.createElement("article");
            article.className = "ndh-upload-item";
            article.innerHTML = [
                "<strong>" + escapeHtml(item.file.name) + "</strong>",
                "<small>" + escapeHtml(uploadMeta(item)) + "</small>",
                '<div class="ndh-upload-progress"><span style="width:' + String(Math.max(4, item.progress || 0)) + '%"></span></div>'
            ].join("");
            uploadQueue.appendChild(article);
        });
    }

    function entryMeta(entry) {
        var parts = [];
        parts.push(entry.type === "dir" ? "پوشه" : (entry.sizeLabel || "فایل"));
        if (entry.modifiedAt) {
            parts.push(formatDate(entry.modifiedAt));
        }
        return parts.join(" • ");
    }

    function iconLabel(entry) {
        if (entry.type === "dir") {
            return "DIR";
        }
        var name = String(entry.name || "");
        var match = /\.([^.]+)$/.exec(name);
        return match && match[1] ? match[1].slice(0, 3).toUpperCase() : "FILE";
    }

    function renderEntries() {
        filterEntries();
        renderRoots();
        renderBreadcrumbs();
        entriesRoot.innerHTML = "";
        currentPathLabel.textContent = state.currentPath || "ریشه منابع";

        if (!state.filteredEntries.length) {
            empty.hidden = false;
            empty.textContent = state.loading
                ? "در حال دریافت فایل‌ها..."
                : "در این پوشه هنوز فایل یا پوشه‌ای وجود ندارد.";
            return;
        }

        empty.hidden = true;
        state.filteredEntries.forEach(function (entry) {
            var article = document.createElement("article");
            article.className = "ndh-entry";
            article.dataset.type = String(entry.type || "file");
            article.dataset.path = String(entry.relativePath || "");

            var actions = [];
            if (entry.type === "dir") {
                actions.push('<button class="is-open" type="button" data-browse-path="' + escapeHtml(entry.relativePath || "") + '">باز کردن</button>');
            } else if (entry.publicUrl) {
                actions.push('<a class="is-open" href="' + escapeHtml(entry.publicUrl) + '" target="_blank" rel="noopener noreferrer">لینک مستقیم</a>');
                actions.push('<button type="button" data-copy-link="' + escapeHtml(entry.publicUrl) + '">کپی لینک</button>');
            }
            actions.push('<button type="button" data-rename-path="' + escapeHtml(entry.relativePath || "") + '">تغییر نام</button>');
            actions.push('<button class="is-danger" type="button" data-delete-path="' + escapeHtml(entry.relativePath || "") + '" data-delete-type="' + escapeHtml(entry.type || "file") + '">حذف</button>');

            article.innerHTML = [
                '<div class="ndh-entry-main">',
                '  <div class="ndh-entry-icon">' + escapeHtml(iconLabel(entry)) + '</div>',
                '  <div class="ndh-entry-copy">',
                '    <strong>' + escapeHtml(entry.name || "") + '</strong>',
                '    <small>' + escapeHtml(entryMeta(entry)) + '</small>',
                '  </div>',
                '</div>',
                '<div class="ndh-entry-actions">' + actions.join("") + '</div>'
            ].join("");
            entriesRoot.appendChild(article);
        });
    }

    function loadBrowse(path, options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        renderEntries();
        if (!(options && options.silent)) {
            setFeedback("", "");
        }

        var nextPath = String(path == null ? "" : path).trim();
        if (!nextPath && currentScopeRoot() && !(state.downloadHost && state.downloadHost.canManageAllRoots)) {
            nextPath = currentScopeRoot();
        }

        return request("downloadHostBrowse", "GET", {
            path: nextPath
        }).then(function (payload) {
            if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
                setGuard("login", "نیاز به ورود", "برای استفاده از فایل‌منیجر منابع باید وارد حساب مجاز شوید.", loginUrl(), "ورود");
                return;
            }
            if (payload && payload.httpStatus === 403) {
                setGuard("forbidden", "دسترسی مجاز نیست", (payload && payload.error) || "این بخش فقط برای حساب‌های مجاز فعال است.");
                return;
            }
            if (!payload || !payload.success || !payload.browse) {
                throw new Error((payload && payload.error) || "دریافت فایل‌های هاست دانلود ناموفق بود.");
            }

            state.downloadHost = payload.downloadHost || null;
            state.currentPath = String(payload.browse.currentPath || "");
            state.breadcrumbs = Array.isArray(payload.browse.breadcrumbs) ? payload.browse.breadcrumbs : [];
            state.entries = Array.isArray(payload.browse.entries) ? payload.browse.entries : [];
            showApp();
            updateUrl();
            renderEntries();
        }).catch(function (error) {
            setGuard("error", "خطا در ارتباط با هاست دانلود", error && error.message ? error.message : "فایل‌منیجر با خطا مواجه شد.");
        }).finally(function () {
            state.loading = false;
            renderEntries();
        });
    }

    function addFilesToQueue(fileList) {
        Array.prototype.forEach.call(fileList || [], function (file) {
            state.uploadItems.push({
                id: Math.random().toString(36).slice(2),
                file: file,
                status: "queued",
                progress: 6,
                error: ""
            });
        });
        renderUploadQueue();
    }

    function uploadPendingFiles() {
        if (state.busy) {
            return;
        }
        if (!state.currentPath) {
            setFeedback("ابتدا یکی از پوشه‌های منابع را باز کنید و بعد آپلود را شروع کنید.", "error");
            return;
        }
        var pending = state.uploadItems.filter(function (item) {
            return item.status === "queued" || item.status === "error";
        });
        if (!pending.length) {
            setFeedback("فایل جدیدی برای آپلود در صف وجود ندارد.", "error");
            return;
        }

        state.busy = true;
        uploadSubmit.disabled = true;
        setFeedback("آپلود فایل‌ها به هاست دانلود شروع شد.", "");

        var chain = Promise.resolve();
        pending.forEach(function (item) {
            chain = chain.then(function () {
                item.status = "uploading";
                item.progress = 18;
                item.error = "";
                renderUploadQueue();

                var formData = new FormData();
                formData.append("path", state.currentPath);
                formData.append("file", item.file);
                return requestFormData("downloadHostUpload", formData).then(function (response) {
                    if (!response || !response.success || !response.file) {
                        throw new Error((response && response.error) || "آپلود فایل کامل نشد.");
                    }
                    item.status = "done";
                    item.progress = 100;
                    renderUploadQueue();
                    setFeedback("فایل‌ها روی هاست دانلود به‌روزرسانی شدند.", "success");
                    return loadBrowse(state.currentPath, { silent: true });
                }).catch(function (error) {
                    item.status = "error";
                    item.progress = 100;
                    item.error = error && error.message ? error.message : "آپلود فایل با خطا مواجه شد.";
                    renderUploadQueue();
                    setFeedback(item.error, "error");
                });
            });
        });

        chain.finally(function () {
            state.busy = false;
            uploadSubmit.disabled = false;
        });
    }

    function createFolder() {
        if (state.busy || state.loading) {
            return;
        }
        if (!state.currentPath) {
            setFeedback("برای ساخت پوشه جدید ابتدا یکی از ریشه‌های منابع را باز کنید.", "error");
            return;
        }
        var name = window.prompt("نام پوشه جدید را وارد کنید:");
        if (!name) {
            return;
        }

        state.busy = true;
        request("downloadHostCreateDir", "POST", {
            path: state.currentPath,
            name: name
        }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ساخت پوشه انجام نشد.");
            }
            setFeedback(response.message || "پوشه جدید ساخته شد.", "success");
            return loadBrowse(state.currentPath, { silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "ساخت پوشه با خطا مواجه شد.", "error");
        }).finally(function () {
            state.busy = false;
        });
    }

    function renameEntry(path) {
        if (!path || state.busy) {
            return;
        }
        var currentName = String(path).split("/").pop() || "";
        var nextName = window.prompt("نام جدید را وارد کنید:", currentName);
        if (!nextName || nextName === currentName) {
            return;
        }

        state.busy = true;
        request("downloadHostRenameEntry", "POST", {
            path: path,
            name: nextName
        }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "تغییر نام انجام نشد.");
            }
            setFeedback(response.message || "نام فایل یا پوشه تغییر کرد.", "success");
            return loadBrowse(state.currentPath, { silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "تغییر نام با خطا مواجه شد.", "error");
        }).finally(function () {
            state.busy = false;
        });
    }

    function deleteEntry(path, type) {
        if (!path || state.busy) {
            return;
        }
        var confirmed = window.confirm("این " + (type === "dir" ? "پوشه" : "فایل") + " حذف شود؟");
        if (!confirmed) {
            return;
        }

        state.busy = true;
        request("downloadHostDeleteEntry", "POST", {
            path: path,
            entryType: type
        }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف انجام نشد.");
            }
            setFeedback(response.message || "آیتم انتخابی حذف شد.", "success");
            return loadBrowse(state.currentPath, { silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "حذف با خطا مواجه شد.", "error");
        }).finally(function () {
            state.busy = false;
        });
    }

    function copyLink(url) {
        if (!url) {
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(url).then(function () {
                setFeedback("لینک مستقیم فایل کپی شد.", "success");
            }).catch(function () {
                setFeedback("کپی خودکار لینک ممکن نشد.", "error");
            });
            return;
        }
        window.prompt("لینک مستقیم فایل:", url);
    }

    function bindUi() {
        if (pickFilesButton && uploadInput) {
            pickFilesButton.addEventListener("click", function () {
                if (!state.busy) {
                    uploadInput.click();
                }
            });
            uploadInput.addEventListener("change", function () {
                addFilesToQueue(uploadInput.files);
                uploadInput.value = "";
            });
        }

        if (uploadSubmit) {
            uploadSubmit.addEventListener("click", uploadPendingFiles);
        }

        if (createFolderButton) {
            createFolderButton.addEventListener("click", createFolder);
        }

        if (refreshButton) {
            refreshButton.addEventListener("click", function () {
                loadBrowse(state.currentPath, { silent: true });
            });
        }

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                state.searchQuery = String(searchInput.value || "");
                renderEntries();
            });
        }

        rootList.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-root-path]") : null;
            if (!button) {
                return;
            }
            loadBrowse(button.getAttribute("data-root-path") || "", { silent: false });
        });

        breadcrumbs.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-browse-path]") : null;
            if (!button) {
                return;
            }
            loadBrowse(button.getAttribute("data-browse-path") || "", { silent: true });
        });

        entriesRoot.addEventListener("click", function (event) {
            var browseButton = event.target && event.target.closest ? event.target.closest("[data-browse-path]") : null;
            var renameButton = event.target && event.target.closest ? event.target.closest("[data-rename-path]") : null;
            var deleteButton = event.target && event.target.closest ? event.target.closest("[data-delete-path]") : null;
            var copyButton = event.target && event.target.closest ? event.target.closest("[data-copy-link]") : null;

            if (browseButton) {
                loadBrowse(browseButton.getAttribute("data-browse-path") || "", { silent: true });
                return;
            }
            if (renameButton) {
                renameEntry(renameButton.getAttribute("data-rename-path") || "");
                return;
            }
            if (deleteButton) {
                deleteEntry(
                    deleteButton.getAttribute("data-delete-path") || "",
                    deleteButton.getAttribute("data-delete-type") || "file"
                );
                return;
            }
            if (copyButton) {
                copyLink(copyButton.getAttribute("data-copy-link") || "");
            }
        });
    }

    function watchAuthChanges() {
        if (!authApi || typeof authApi.onChange !== "function") {
            return;
        }
        authApi.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }
            state.authKey = nextKey;
            loadBrowse(state.currentPath || requestedPath, { silent: false });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindUi();
        renderUploadQueue();
        watchAuthChanges();
        loadBrowse(requestedPath, { silent: false });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
