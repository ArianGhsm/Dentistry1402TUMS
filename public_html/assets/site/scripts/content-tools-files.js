(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
        ? window.Dent1402Auth
        : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object"
        ? window.Dent1402Site
        : null;

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

    function formatNumber(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatDecimal(value, digits) {
        var number = Number(value);
        if (!Number.isFinite(number)) return "\u2014";
        return number.toLocaleString("fa-IR", {
            minimumFractionDigits: 0,
            maximumFractionDigits: typeof digits === "number" ? digits : 1
        });
    }

    function formatBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes <= 0) return "۰ بایت";
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
        if (!Number.isFinite(bps) || bps <= 0) return "—";
        return formatBytes(bps) + "/ث";
    }

    function formatEta(seconds) {
        var value = Number(seconds);
        if (!Number.isFinite(value) || value < 0) return "—";
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

    function formatDate(value, fallback) {
        if (siteApi && typeof siteApi.formatDateTime === "function") {
            return siteApi.formatDateTime(value, fallback);
        }
        var raw = String(value || "").trim();
        if (!raw) return fallback || "—";
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) return raw;
        return parsed.toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function stateLabel(state) {
        switch (String(state || "")) {
            case "active": return "فعال";
            case "hidden": return "مخفی";
            case "expired": return "منقضی";
            case "deleted": return "حذف‌شده";
            default: return "نامشخص";
        }
    }

    function fileKind(file) {
        var mime = String(file && file.mimeType || "").toLowerCase();
        var ext = String(file && file.extension || "").toUpperCase();
        if (mime.indexOf("image/") === 0) return "IMG";
        if (mime.indexOf("video/") === 0) return "VID";
        if (mime.indexOf("audio/") === 0) return "AUD";
        if (mime === "application/pdf") return "PDF";
        if (mime.indexOf("text/") === 0 || mime === "application/json") return "TXT";
        return ext ? ext.slice(0, 4) : "FILE";
    }

    function folderName(path) {
        var value = String(path || "").trim().replace(/\/+$/, "");
        if (!value) return "ریشه آپلودسنتر";
        var parts = value.split("/");
        return parts[parts.length - 1] || value;
    }

    function summaryCardMarkup(label, valueId, smallId, copy) {
        return [
            '<article class="ctf-summary-card">',
            '  <span>' + escapeHtml(label) + '</span>',
            '  <strong id="' + escapeHtml(valueId) + '">\u2014</strong>',
            '  <small id="' + escapeHtml(smallId) + '">' + escapeHtml(copy) + '</small>',
            '</article>'
        ].join("");
    }

    function typeMatches(file, type) {
        var selected = String(type || "all");
        if (selected === "all") return true;
        var mime = String(file && file.mimeType || "").toLowerCase();
        if (selected === "image") return mime.indexOf("image/") === 0;
        if (selected === "video") return mime.indexOf("video/") === 0;
        if (selected === "audio") return mime.indexOf("audio/") === 0;
        if (selected === "pdf") return mime === "application/pdf";
        if (selected === "text") return mime.indexOf("text/") === 0 || mime === "application/json";
        if (selected === "other") {
            return mime.indexOf("image/") !== 0
                && mime.indexOf("video/") !== 0
                && mime.indexOf("audio/") !== 0
                && mime !== "application/pdf"
                && mime.indexOf("text/") !== 0
                && mime !== "application/json";
        }
        return true;
    }

    function request(action, payload, method) {
        var verb = String(method || "GET").toUpperCase();
        var url = "/api/content_tools_api.php";
        var options = {
            method: verb,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };
        if (verb === "GET") {
            url += "?" + new URLSearchParams(Object.assign({ action: action }, payload || {})).toString();
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        }
        return fetch(url, options).then(function (response) {
            if (siteApi && typeof siteApi.parseJsonResponse === "function") {
                return siteApi.parseJsonResponse(response);
            }
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function copyText(value, onDone) {
        var text = String(value || "").trim();
        if (!text) {
            if (onDone) onDone(false);
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(text).then(function () {
                if (onDone) onDone(true);
            }).catch(function () {
                fallbackCopy(text, onDone);
            });
            return;
        }
        fallbackCopy(text, onDone);
    }

    function fallbackCopy(text, onDone) {
        var area = document.createElement("textarea");
        area.value = text;
        area.setAttribute("readonly", "readonly");
        area.style.position = "fixed";
        area.style.insetInlineStart = "-9999px";
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try {
            ok = document.execCommand("copy");
        } catch (_error) {
            ok = false;
        }
        area.remove();
        if (onDone) onDone(ok);
    }

    function setFeedback(node, text, kind) {
        if (!node) return;
        node.className = "ct-feedback" + (kind ? (" is-" + kind) : "");
        node.textContent = text || "";
    }

    function consumeUnauthorized(payload, fallbackText) {
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        if (!authApi || !payload) return false;
        if (typeof authApi.handleUnauthorizedPayload === "function") {
            return !!authApi.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return false;
    }

    function initOwnerGuard(onReady) {
        var guard = $("ct-auth-guard");
        var app = $("ct-owner-app");
        if (!authApi || !guard || !app) return;

        authApi.onChange(function (detail) {
            var loginUrl = authApi.loginUrl ? authApi.loginUrl(window.location.pathname + window.location.search) : "/account/";
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = "<h2>در حال بررسی حساب</h2><p>وضعیت نشست مشترک سایت خوانده می‌شود.</p>";
                return;
            }
            if (!detail.loggedIn) {
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = authApi.renderLoginRequiredGuard({
                    loginHref: loginUrl,
                    fallbackHref: "/app/",
                    actionsClass: "ct-auth-guard__actions",
                    primaryClass: "ct-btn ct-btn--primary",
                    secondaryClass: "ct-btn"
                });
                authApi.enhanceLoginGuards(guard);
                return;
            }
            if (!detail.user || !detail.user.isOwner) {
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = [
                    "<h2>این بخش مخصوص مالک سایت است</h2>",
                    "<p>مدیریت آپلودسنتر و فایل‌های عمومی فقط برای مالک در دسترس است.</p>",
                    '<a class="ct-btn" href="/app/">بازگشت به خانه</a>'
                ].join("");
                return;
            }
            guard.hidden = true;
            guard.innerHTML = "";
            app.hidden = false;
            onReady(detail.user);
        });
    }

    function initFilesCenter() {
        var feedback = $("ct-feedback");
        var uploadInput = $("ct-upload-input");
        var pickButton = $("ct-upload-pick");
        var dropzone = $("ct-dropzone");
        var clearQueueButton = $("ct-upload-clear");
        var submitButton = $("ct-upload-submit");
        var queueNode = $("ct-upload-queue");
        var browserEntries = $("ctf-browser-entries");
        var browserEmpty = $("ctf-browser-empty");
        var browserQuery = $("ctf-browser-query");
        var browserRefresh = $("ctf-browser-refresh");
        var browserCreateFolder = $("ctf-create-folder");
        var breadcrumbs = $("ctf-breadcrumbs");
        var summaryGrid = $("ctf-summary-grid");
        var currentPathLabel = $("ctf-current-path");
        var currentFolderLabel = $("ctf-current-folder");
        var destinationLabel = $("ctf-upload-destination");
        var linksList = $("ct-files-list");
        var linksPager = $("ct-files-pager");

        var state = {
            summary: {},
            downloadHost: {},
            browserPath: "",
            browserEntries: [],
            browserQuery: "",
            browserLoading: false,
            queue: [],
            uploadBusy: false,
            queueCounter: 1,
            links: [],
            linkStatus: "available",
            linkType: "all",
            linkSort: "newest",
            linkQuery: "",
            linkPage: 1,
            linkPerPage: 20,
            linkSelected: {},
            pageInfo: { page: 1, pages: 1, total: 0 }
        };

        function currentUploadPath() {
            return String(state.browserPath || "");
        }

        function currentUploadFolderMeta() {
            var manual = $("ct-upload-folder-input");
            var raw = manual ? String(manual.value || "").trim() : "";
            if (raw) return raw.slice(0, 80);
            var path = currentUploadPath();
            return path ? path.slice(0, 80) : "ریشه";
        }

        function uploadMetaPayload() {
            return {
                title: $("ct-upload-title-input") ? $("ct-upload-title-input").value : "",
                description: $("ct-upload-description-input") ? $("ct-upload-description-input").value : "",
                tags: $("ct-upload-tags-input") ? $("ct-upload-tags-input").value : "",
                folder: currentUploadFolderMeta(),
                expiresAt: $("ct-upload-expire-input") ? $("ct-upload-expire-input").value : "",
                downloadLimit: $("ct-upload-limit-input") ? $("ct-upload-limit-input").value : "",
                password: $("ct-upload-password-input") ? $("ct-upload-password-input").value : "",
                status: $("ct-upload-status-input") ? $("ct-upload-status-input").value : "active",
                targetPath: currentUploadPath()
            };
        }

        function resetUploadMeta() {
            ["ct-upload-title-input", "ct-upload-description-input", "ct-upload-tags-input", "ct-upload-folder-input", "ct-upload-expire-input", "ct-upload-limit-input", "ct-upload-password-input"].forEach(function (id) {
                var node = $(id);
                if (node) node.value = "";
            });
            var status = $("ct-upload-status-input");
            if (status) status.value = "active";
        }

        function ensureSummaryCards() {
            if (!summaryGrid || summaryGrid.dataset.ready === "true") return;
            summaryGrid.innerHTML = [
                '<div class="ctf-summary-primary">',
                summaryCardMarkup("حجم باقی‌مانده کل هاست", "ctf-summary-host-free", "ctf-summary-host-free-meta", "فضای آزاد برای آپلودهای بعدی"),
                summaryCardMarkup("لینک‌ها و فایل‌های ثبت‌شده", "ctf-summary-files", "ctf-summary-files-meta", "تفکیک فایل‌های ریموت، لوکال و لینک‌های فعال"),
                summaryCardMarkup("پایه هاست دانلود", "ctf-summary-host", "ctf-summary-host-meta", "ریشه‌ی انتشار و مقصد اصلی فایل‌های جدید"),
                "</div>",
                '<details class="ctf-summary-details">',
                "<summary>جزئیات آمار هاست و storage</summary>",
                '<div class="ctf-summary-detail-grid">',
                summaryCardMarkup("حجم مصرف‌شده کل هاست", "ctf-summary-host-used", "ctf-summary-host-used-meta", "از کل سهم فضای هاست"),
                summaryCardMarkup("تعداد کل فایل‌های هاست", "ctf-summary-host-files", "ctf-summary-host-files-meta", "در ریشه و زیرپوشه‌های Upload Center"),
                summaryCardMarkup("تعداد کل پوشه‌های هاست", "ctf-summary-host-folders", "ctf-summary-host-folders-meta", "همه پوشه‌های قابل مرور و مدیریت"),
                summaryCardMarkup("حجم فایل‌های ثبت‌شده", "ctf-summary-size", "ctf-summary-size-meta", "جمع فایل‌های شناخته‌شده در استور"),
                "</div>",
                "</details>"
            ].join("");
            summaryGrid.dataset.ready = "true";
        }

        function updateSummary(summary) {
            ensureSummaryCards();
            state.summary = Object.assign({}, state.summary || {}, summary || {});
            var hostUsage = state.summary.hostUsage && typeof state.summary.hostUsage === "object"
                ? state.summary.hostUsage
                : {};
            var hostAvailable = hostUsage.available === true;
            var totalFiles = $("ctf-summary-files");
            var totalFilesMeta = $("ctf-summary-files-meta");
            var totalSize = $("ctf-summary-size");
            var totalSizeMeta = $("ctf-summary-size-meta");
            var emptyMetric = "\u2014";
            var hostBase = $("ctf-summary-host");
            var hostBaseMeta = $("ctf-summary-host-meta");
            var hostUsed = $("ctf-summary-host-used");
            var hostUsedMeta = $("ctf-summary-host-used-meta");
            var hostFree = $("ctf-summary-host-free");
            var hostFreeMeta = $("ctf-summary-host-free-meta");
            var hostFiles = $("ctf-summary-host-files");
            var hostFilesMeta = $("ctf-summary-host-files-meta");
            var hostFolders = $("ctf-summary-host-folders");
            var hostFoldersMeta = $("ctf-summary-host-folders-meta");
            if (hostUsed) {
                hostUsed.textContent = hostAvailable && hostUsage.usedBytes != null ? formatBytes(hostUsage.usedBytes) : emptyMetric;
            }
            if (hostUsedMeta) {
                hostUsedMeta.textContent = hostAvailable && hostUsage.limitBytes != null
                    ? ("\u0627\u0632 " + formatBytes(hostUsage.limitBytes) + " \u06a9\u0644 \u0641\u0636\u0627" + (hostUsage.usagePercent != null ? (" \u2022 " + formatDecimal(hostUsage.usagePercent, 1) + "\u066a \u0645\u0635\u0631\u0641") : ""))
                    : "\u0622\u0645\u0627\u0631 \u0644\u062d\u0638\u0647\u200c\u0627\u06cc \u0647\u0627\u0633\u062a \u062f\u0631 \u0627\u06cc\u0646 \u0644\u062d\u0638\u0647 \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a";
            }
            if (hostFree) {
                hostFree.textContent = hostAvailable && hostUsage.remainingBytes != null ? formatBytes(hostUsage.remainingBytes) : emptyMetric;
            }
            if (hostFreeMeta) {
                hostFreeMeta.textContent = hostAvailable && hostUsage.uploadRemainingBytes != null
                    ? ("\u062d\u062f\u0627\u06a9\u062b\u0631 \u0622\u067e\u0644\u0648\u062f \u0628\u0627\u0642\u06cc\u200c\u0645\u0627\u0646\u062f\u0647: " + formatBytes(hostUsage.uploadRemainingBytes))
                    : "\u0645\u0627\u0646\u062f\u0647 \u0627\u0632 quota \u06a9\u0644 \u0647\u0627\u0633\u062a";
            }
            if (hostFiles) {
                hostFiles.textContent = hostAvailable ? formatNumber(hostUsage.fileCount || 0) : emptyMetric;
            }
            if (hostFilesMeta) {
                hostFilesMeta.textContent = hostAvailable
                    ? (formatBytes(hostUsage.managedBytes || 0) + " \u062f\u0631 \u062f\u0631\u062e\u062a \u0641\u0627\u06cc\u0644\u06cc \u0631\u06cc\u0634\u0647")
                    : "\u0634\u0645\u0627\u0631\u0634 \u0641\u0627\u06cc\u0644\u200c\u0647\u0627 \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a";
            }
            if (hostFolders) {
                hostFolders.textContent = hostAvailable ? formatNumber(hostUsage.directoryCount || 0) : emptyMetric;
            }
            if (hostFoldersMeta) {
                hostFoldersMeta.textContent = hostAvailable
                    ? (formatNumber(hostUsage.entryCount || 0) + " \u0648\u0631\u0648\u062f\u06cc \u062f\u0631 \u0645\u062c\u0645\u0648\u0639 \u2022 " + formatNumber(hostUsage.scannedDirectories || 0) + " \u0645\u0633\u06cc\u0631 \u0627\u0633\u06a9\u0646 \u0634\u062f")
                    : "\u0622\u0645\u0627\u0631 \u067e\u0648\u0634\u0647\u200c\u0647\u0627 \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a";
            }
            if (totalFiles) {
                totalFiles.textContent = formatNumber(state.summary.totalFiles || 0);
            }
            if (totalFilesMeta) {
                totalFilesMeta.textContent = formatNumber(state.summary.remoteFiles || 0) + " ریموت / "
                    + formatNumber(state.summary.localFiles || 0) + " لوکال • "
                    + formatNumber(state.summary.activeFiles || 0) + " فعال";
            }
            if (totalSize) {
                totalSize.textContent = formatBytes(state.summary.totalBytes || 0);
            }
            if (totalSizeMeta) {
                totalSizeMeta.textContent = formatNumber(state.summary.downloadCount || 0) + " دانلود ثبت‌شده";
            }
            if (hostBase) {
                hostBase.textContent = String(state.summary.storageRoot || state.downloadHost.baseUrl || emptyMetric);
            }
            if (hostBaseMeta) {
                hostBaseMeta.textContent = hostAvailable && hostUsage.generatedAt
                    ? ((hostUsage.stale ? "آخرین اسکن کش‌شده: " : "آخرین اسکن: ") + formatDate(hostUsage.generatedAt, "اکنون"))
                    : "ریشه‌ی انتشار و مقصد اصلی فایل‌های جدید";
            }
            var notice = $("ctf-summary-notice");
            if (notice) {
                notice.textContent = String(state.summary.notice || "");
            }
        }

        function updateDestinationUi() {
            var path = currentUploadPath();
            if (currentPathLabel) currentPathLabel.textContent = path || "ریشه آپلودسنتر";
            if (currentFolderLabel) currentFolderLabel.textContent = folderName(path);
            if (destinationLabel) destinationLabel.value = path || "/";
        }

        function renderBreadcrumbs(items) {
            if (!breadcrumbs) return;
            breadcrumbs.innerHTML = "";
            (items || []).forEach(function (item, index, all) {
                var button = document.createElement("button");
                button.type = "button";
                button.dataset.path = String(item.path || "");
                if (index === all.length - 1) button.className = "is-active";
                button.textContent = String(item.label || "");
                breadcrumbs.appendChild(button);
            });
        }

        function filterBrowserEntries() {
            var query = String(state.browserQuery || "").trim().toLowerCase();
            if (!query) return state.browserEntries.slice();
            return state.browserEntries.filter(function (entry) {
                var name = String(entry && entry.name || "").toLowerCase();
                var path = String(entry && entry.relativePath || "").toLowerCase();
                return name.indexOf(query) !== -1 || path.indexOf(query) !== -1;
            });
        }

        function browserEntryActions(entry) {
            var actions = [];
            if (entry.type === "dir") {
                actions.push('<button class="ct-btn" type="button" data-open-path="' + escapeHtml(entry.relativePath || "") + '">باز کردن</button>');
            } else if (entry.publicUrl) {
                actions.push('<a class="ct-btn" href="' + escapeHtml(entry.publicUrl) + '" target="_blank" rel="noopener">لینک مستقیم</a>');
                actions.push('<button class="ct-btn" type="button" data-copy="' + escapeHtml(entry.publicUrl) + '">کپی لینک</button>');
            }
            actions.push('<button class="ct-btn" type="button" data-rename-path="' + escapeHtml(entry.relativePath || "") + '" data-entry-type="' + escapeHtml(entry.type || "file") + '">تغییر نام</button>');
            actions.push('<button class="ct-btn ct-btn--danger" type="button" data-delete-path="' + escapeHtml(entry.relativePath || "") + '" data-entry-type="' + escapeHtml(entry.type || "file") + '">حذف</button>');
            return actions.join("");
        }

        function renderBrowser() {
            if (!browserEntries || !browserEmpty) return;
            var items = filterBrowserEntries();
            browserEntries.innerHTML = "";
            updateDestinationUi();
            if (!items.length) {
                browserEmpty.hidden = false;
                browserEmpty.textContent = state.browserLoading
                    ? "در حال دریافت محتویات این پوشه..."
                    : "در این مسیر هنوز فایل یا پوشه‌ای وجود ندارد.";
                return;
            }
            browserEmpty.hidden = true;
            items.forEach(function (entry) {
                var meta = [];
                meta.push(entry.type === "dir" ? "پوشه" : (entry.sizeLabel || formatBytes(entry.sizeBytes || 0)));
                if (entry.modifiedAt) meta.push(formatDate(entry.modifiedAt));
                var article = document.createElement("article");
                article.className = "ctf-entry";
                article.dataset.type = String(entry.type || "file");
                article.innerHTML = [
                    '<div class="ctf-entry-main">',
                    '  <div class="ctf-entry-icon">' + escapeHtml(entry.type === "dir" ? "DIR" : fileKind({ mimeType: entry.mimeType || "", extension: entry.name || "" })) + '</div>',
                    '  <div class="ctf-entry-copy">',
                    '    <strong>' + escapeHtml(entry.name || "") + '</strong>',
                    '    <small>' + escapeHtml(meta.join(" • ")) + '</small>',
                    '    <small>' + escapeHtml(String(entry.relativePath || "")) + '</small>',
                    '  </div>',
                    '</div>',
                    '<div class="ctf-entry-actions">' + browserEntryActions(entry) + '</div>'
                ].join("");
                browserEntries.appendChild(article);
            });
        }

        async function loadHostSummary(forceRefresh, silent) {
            var response = await request("ownerDownloadHostSummary", {
                refresh: forceRefresh ? "1" : ""
            }, "GET");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                if (!silent) {
                    setFeedback(feedback, (response && response.error) || "آمار کامل هاست خوانده نشد.", "error");
                }
                return;
            }
            state.downloadHost = response.downloadHost || state.downloadHost || {};
            updateSummary(response.summary || {});
        }

        async function loadBrowser(path, silent) {
            state.browserLoading = true;
            renderBrowser();
            var response = await request("ownerDownloadHostBrowse", { path: path || "" }, "GET");
            state.browserLoading = false;
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                renderBrowser();
                if (!silent) setFeedback(feedback, (response && response.error) || "فهرست فایل‌ها خوانده نشد.", "error");
                return;
            }
            state.downloadHost = response.downloadHost || {};
            updateSummary(response.summary || {});
            state.browserPath = String(response.browser && response.browser.currentPath || "");
            state.browserEntries = Array.isArray(response.browser && response.browser.entries) ? response.browser.entries : [];
            renderBreadcrumbs(Array.isArray(response.browser && response.browser.breadcrumbs) ? response.browser.breadcrumbs : []);
            renderBrowser();
            if (!silent) setFeedback(feedback, "");
        }

        function queueStateLabel(item) {
            switch (item.status) {
                case "uploading": return "در حال انتقال";
                case "finalizing": return "ثبت روی هاست";
                case "done": return "تکمیل شد";
                case "error": return "با خطا مواجه شد";
                default: return "آماده آپلود";
            }
        }

        function queueProgressPercent(item) {
            var value = Number(item.progress || 0);
            return Math.max(0, Math.min(100, value));
        }

        function uploadStats() {
            var total = state.queue.length;
            var done = state.queue.filter(function (item) { return item.status === "done"; }).length;
            var uploading = state.queue.filter(function (item) { return item.status === "uploading" || item.status === "finalizing"; }).length;
            var totalBytes = state.queue.reduce(function (sum, item) { return sum + Number(item.size || 0); }, 0);
            var uploadedBytes = state.queue.reduce(function (sum, item) { return sum + (Number(item.size || 0) * queueProgressPercent(item) / 100); }, 0);
            return {
                total: total,
                done: done,
                uploading: uploading,
                totalBytes: totalBytes,
                uploadedBytes: uploadedBytes
            };
        }

        function updateQueueSummary() {
            var stats = uploadStats();
            var files = $("ctf-queue-files");
            var transferred = $("ctf-queue-transferred");
            var active = $("ctf-queue-active");
            if (files) files.textContent = formatNumber(stats.total);
            if (transferred) transferred.textContent = formatBytes(stats.uploadedBytes) + " / " + formatBytes(stats.totalBytes);
            if (active) active.textContent = stats.uploading ? (formatNumber(stats.uploading) + " در حال انتقال") : (stats.done ? (formatNumber(stats.done) + " تکمیل‌شده") : "آماده");
        }

        function renderQueue() {
            if (!queueNode) return;
            updateQueueSummary();
            if (!state.queue.length) {
                queueNode.innerHTML = '<div class="ct-empty">هنوز فایلی به صف آپلود اضافه نشده است.</div>';
                return;
            }
            queueNode.innerHTML = state.queue.map(function (item) {
                var stats = [
                    '<span>حجم: ' + escapeHtml(formatBytes(item.size)) + '</span>',
                    '<span>پیشرفت: ' + escapeHtml(queueProgressPercent(item).toFixed(0).toLocaleString("fa-IR")) + '%</span>',
                    '<span>سرعت: ' + escapeHtml(formatSpeed(item.speedBps)) + '</span>',
                    '<span>زمان باقی‌مانده: ' + escapeHtml(formatEta(item.etaSeconds)) + '</span>'
                ];
                if (item.status === "done") {
                    stats[2] = '<span>مسیر: ' + escapeHtml(item.remotePath || currentUploadPath() || "/") + '</span>';
                    stats[3] = '<span>اتمام: ' + escapeHtml(formatDate(item.completedAt || "", "اکنون")) + '</span>';
                }
                if (item.status === "error") {
                    stats[2] = '<span>خطا: ' + escapeHtml(item.error || "خطای نامشخص") + '</span>';
                    stats[3] = "";
                }
                var actions = [];
                if (item.publicUrl) {
                    actions.push('<button class="ct-btn" type="button" data-copy="' + escapeHtml(item.publicUrl) + '">کپی لینک عمومی</button>');
                }
                if (item.directUrl) {
                    actions.push('<a class="ct-btn" href="' + escapeHtml(item.directUrl) + '" target="_blank" rel="noopener">لینک مستقیم</a>');
                }
                if (item.status === "uploading" || item.status === "finalizing") {
                    actions.push('<button class="ct-btn ct-btn--danger" type="button" data-remove-queue="' + escapeHtml(item.id) + '">لغو آپلود</button>');
                }
                if (item.status === "queued" || item.status === "error" || item.status === "done") {
                    actions.push('<button class="ct-btn ct-btn--danger" type="button" data-remove-queue="' + escapeHtml(item.id) + '">حذف از صف</button>');
                }
                return [
                    '<article class="ctf-queue-item" data-state="' + escapeHtml(item.status || "queued") + '">',
                    '  <div class="ctf-queue-top">',
                    '    <strong class="ctf-queue-name">' + escapeHtml(item.name) + '</strong>',
                    '    <span class="ctf-queue-state">' + escapeHtml(queueStateLabel(item)) + '</span>',
                    '  </div>',
                    '  <div class="ctf-queue-meter"><span style="width:' + queueProgressPercent(item).toFixed(1) + '%"></span></div>',
                    '  <div class="ctf-queue-stats">' + stats.join("") + '</div>',
                    (actions.length ? '<div class="ctf-queue-result">' + actions.join("") + '</div>' : ""),
                    '</article>'
                ].join("");
            }).join("");
        }

        function addFiles(files) {
            Array.prototype.forEach.call(files || [], function (file) {
                if (!file) return;
                state.queue.push({
                    id: "q-" + String(state.queueCounter++),
                    file: file,
                    name: file.name || "file",
                    size: Number(file.size || 0),
                    status: "queued",
                    progress: 0,
                    speedBps: 0,
                    etaSeconds: NaN,
                    publicUrl: "",
                    directUrl: "",
                    remotePath: "",
                    error: "",
                    xhr: null,
                    canceled: false
                });
            });
            renderQueue();
        }

        function removeQueueItem(id) {
            var active = state.queue.filter(function (item) {
                return item.id === id && (item.status === "uploading" || item.status === "finalizing");
            })[0] || null;
            if (active && active.xhr) {
                active.canceled = true;
                active.xhr.abort();
                return;
            }
            state.queue = state.queue.filter(function (item) { return item.id !== id; });
            renderQueue();
        }

        function clearQueue() {
            if (state.uploadBusy) return;
            state.queue = state.queue.filter(function (item) { return item.status === "uploading" || item.status === "finalizing"; });
            renderQueue();
        }

        function uploadItem(item) {
            return new Promise(function (resolve, reject) {
                var meta = uploadMetaPayload();
                var body = new FormData();
                body.append("action", "ownerDownloadHostUpload");
                body.append("file", item.file, item.name);
                Object.keys(meta).forEach(function (key) {
                    body.append(key, meta[key] == null ? "" : meta[key]);
                });

                item.status = "uploading";
                item.progress = 0;
                item.speedBps = 0;
                item.etaSeconds = NaN;
                item.error = "";
                item.canceled = false;
                renderQueue();

                var startedAt = Date.now();
                var xhr = new XMLHttpRequest();
                item.xhr = xhr;
                xhr.open("POST", "/api/content_tools_api.php", true);
                xhr.withCredentials = true;
                xhr.setRequestHeader("Accept", "application/json");

                xhr.upload.onprogress = function (event) {
                    if (!event.lengthComputable) return;
                    var loaded = Number(event.loaded || 0);
                    var total = Number(event.total || item.size || 0);
                    var elapsed = Math.max(0.25, (Date.now() - startedAt) / 1000);
                    var speed = loaded / elapsed;
                    item.progress = total > 0 ? (loaded / total) * 100 : item.progress;
                    item.speedBps = speed;
                    item.etaSeconds = speed > 0 && total > loaded ? (total - loaded) / speed : 0;
                    if (item.progress >= 99.9) {
                        item.status = "finalizing";
                        item.etaSeconds = 0;
                    }
                    renderQueue();
                };

                xhr.upload.onload = function () {
                    item.progress = 100;
                    item.status = "finalizing";
                    item.etaSeconds = 0;
                    renderQueue();
                };

                xhr.onload = function () {
                    var response = {};
                    try {
                        response = JSON.parse(xhr.responseText || "{}");
                    } catch (_error) {
                        response = { success: false, error: "پاسخ آپلود معتبر نبود." };
                    }
                    item.xhr = null;
                    response.httpStatus = xhr.status;
                    if (consumeUnauthorized(response)) {
                        reject(new Error("unauthorized"));
                        return;
                    }
                    if (!response.success) {
                        item.status = "error";
                        item.error = response.error || "آپلود انجام نشد.";
                        item.speedBps = 0;
                        item.etaSeconds = NaN;
                        renderQueue();
                        reject(new Error(item.error));
                        return;
                    }
                    var uploaded = Array.isArray(response.files) && response.files[0] ? response.files[0] : null;
                    item.status = "done";
                    item.progress = 100;
                    item.completedAt = new Date().toISOString();
                    item.speedBps = 0;
                    item.etaSeconds = 0;
                    item.publicUrl = uploaded && uploaded.publicUrl ? String(uploaded.publicUrl) : "";
                    item.directUrl = uploaded && uploaded.directUrl ? String(uploaded.directUrl) : "";
                    item.remotePath = uploaded && uploaded.remoteRelativePath ? String(uploaded.remoteRelativePath) : currentUploadPath();
                    renderQueue();
                    resolve(response);
                };

                xhr.onerror = function () {
                    item.xhr = null;
                    item.status = "error";
                    item.error = "ارتباط آپلود قطع شد.";
                    item.speedBps = 0;
                    item.etaSeconds = NaN;
                    renderQueue();
                    reject(new Error(item.error));
                };

                xhr.onabort = function () {
                    item.xhr = null;
                    item.status = "error";
                    item.error = item.canceled ? "آپلود توسط کاربر لغو شد." : "آپلود توسط مرورگر متوقف شد.";
                    item.speedBps = 0;
                    item.etaSeconds = NaN;
                    renderQueue();
                    reject(new Error(item.error));
                };

                xhr.send(body);
            });
        }

        async function uploadQueue() {
            if (state.uploadBusy) return;
            var pending = state.queue.filter(function (item) { return item.status === "queued"; });
            if (!pending.length) {
                setFeedback(feedback, "فایلی برای شروع آپلود در صف نیست.", "error");
                return;
            }
            state.uploadBusy = true;
            submitButton.disabled = true;
            setFeedback(feedback, "آپلود روی هاست دانلود شروع شد...", "");
            var successCount = 0;
            var canceledCount = 0;
            for (var i = 0; i < pending.length; i += 1) {
                try {
                    await uploadItem(pending[i]);
                    successCount += 1;
                } catch (error) {
                    if (error && /لغو/.test(String(error.message || ""))) {
                        canceledCount += 1;
                    }
                }
            }
            state.uploadBusy = false;
            submitButton.disabled = false;
            await loadBrowser(currentUploadPath(), true);
            await loadHostSummary(true, true);
            await loadLinks(true);
            if (successCount > 0) {
                setFeedback(feedback, successCount.toLocaleString("fa-IR") + " فایل با موفقیت روی هاست دانلود ثبت شد.", "success");
            } else if (canceledCount > 0) {
                setFeedback(feedback, "آپلود فایل از طرف کاربر لغو شد.", "");
            } else {
                setFeedback(feedback, "هیچ فایلی با موفقیت آپلود نشد.", "error");
            }
        }

        function linkParams() {
            return {
                query: state.linkQuery,
                status: state.linkStatus,
                type: state.linkType,
                sort: state.linkSort,
                page: String(state.linkPage),
                perPage: String(state.linkPerPage)
            };
        }

        async function loadLinks(silent) {
            var response = await request("ownerFiles", linkParams(), "GET");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                if (!silent) setFeedback(feedback, (response && response.error) || "فهرست لینک‌ها خوانده نشد.", "error");
                return;
            }
            updateSummary(response.summary || state.summary);
            state.links = Array.isArray(response.page && response.page.items) ? response.page.items : [];
            state.pageInfo = response.page || { page: 1, pages: 1, total: 0 };
            renderLinks();
        }

        function selectedLinkIds() {
            return Object.keys(state.linkSelected).filter(function (id) { return state.linkSelected[id]; });
        }

        function renderSelectedCount() {
            var node = $("ct-files-selected");
            if (node) node.textContent = selectedLinkIds().length.toLocaleString("fa-IR") + " انتخاب";
        }

        function renderLinks() {
            renderSelectedCount();
            if (!linksList || !linksPager) return;
            if (!state.links.length) {
                linksList.innerHTML = '<div class="ct-empty">لینکی با این فیلتر پیدا نشد.</div>';
                linksPager.innerHTML = "";
                return;
            }
            linksList.innerHTML = state.links.map(function (file) {
                var status = file.publicState || file.status || "";
                var meta = [
                    "<span>" + escapeHtml(file.originalName || "") + "</span>",
                    "<span>" + escapeHtml(formatBytes(file.size || 0)) + "</span>",
                    "<span>" + escapeHtml(formatDate(file.createdAt)) + "</span>",
                    "<span>" + escapeHtml(formatNumber(file.downloadCount || 0)) + " دانلود</span>",
                    '<span class="ct-status ct-status--' + escapeHtml(status) + '">' + escapeHtml(stateLabel(status)) + "</span>",
                    file.storageDriver === "download-host" ? "<span>هاست دانلود</span>" : "<span>لوکال</span>",
                    file.hasPassword ? "<span>رمزد‌ار</span>" : ""
                ].join("");
                var actions = [
                    '<button class="ct-btn" type="button" data-copy="' + escapeHtml(file.publicUrl) + '">کپی لینک</button>',
                    file.directUrl ? ('<button class="ct-btn" type="button" data-copy="' + escapeHtml(file.directUrl) + '">کپی مستقیم</button>') : "",
                    '<a class="ct-btn" href="' + escapeHtml(file.publicUrl) + '" target="_blank" rel="noopener">صفحه فایل</a>',
                    file.directUrl ? ('<a class="ct-btn" href="' + escapeHtml(file.directUrl) + '" target="_blank" rel="noopener">دانلود مستقیم</a>') : "",
                    '<button class="ct-btn" type="button" data-edit-file="' + escapeHtml(file.id) + '">ویرایش</button>',
                    '<button class="ct-btn ct-btn--danger" type="button" data-single-file-delete="' + escapeHtml(file.id) + '">حذف</button>'
                ].join("");
                return [
                    '<article class="ctf-link-row">',
                    '  <input class="ct-file-select" type="checkbox" data-file-check="' + escapeHtml(file.id) + '"' + (state.linkSelected[file.id] ? " checked" : "") + '>',
                    '  <div class="ctf-link-meta">',
                    '    <strong>' + escapeHtml(file.title || file.originalName || "فایل") + '</strong>',
                    '    <div class="ctf-link-meta-line">' + meta + '</div>',
                    '    <small>' + escapeHtml(file.publicUrl || "") + '</small>',
                    '  </div>',
                    '  <div class="ctf-link-actions">' + actions + '</div>',
                    '</article>'
                ].join("");
            }).join("");

            var pages = Number(state.pageInfo.pages || 1);
            if (pages <= 1) {
                linksPager.innerHTML = "";
                return;
            }
            var buttons = [];
            for (var page = 1; page <= pages; page += 1) {
                buttons.push('<button class="ct-btn' + (page === Number(state.pageInfo.page || 1) ? " is-active" : "") + '" type="button" data-links-page="' + page + '">' + page.toLocaleString("fa-IR") + '</button>');
            }
            linksPager.innerHTML = buttons.join("");
        }

        async function bulkFiles(operation, ids) {
            var targets = ids || selectedLinkIds();
            if (!targets.length) {
                setFeedback(feedback, "حداقل یک فایل را انتخاب کنید.", "error");
                return;
            }
            var dangerous = operation === "delete" || operation === "purge";
            var message = operation === "purge"
                ? "فایل اصلی از هاست دانلود یا storage حذف می‌شود. ادامه می‌دهید؟"
                : "عملیات روی " + targets.length.toLocaleString("fa-IR") + " فایل انجام شود؟";
            if (dangerous && !window.confirm(message)) return;
            var response = await request("ownerBulkFiles", {
                operation: operation,
                ids: JSON.stringify(targets)
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "عملیات انجام نشد.", "error");
                return;
            }
            state.linkSelected = {};
            setFeedback(feedback, response.message || "عملیات انجام شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            if (operation === "delete" || operation === "purge") {
                await loadHostSummary(true, true);
            }
            await loadLinks(true);
        }

        async function editFile(id) {
            var file = state.links.find(function (item) { return item.id === id; });
            if (!file) return;
            var title = window.prompt("عنوان فایل", file.title || file.originalName || "");
            if (title === null) return;
            var description = window.prompt("توضیح فایل", file.description || "");
            if (description === null) return;
            var folder = window.prompt("برچسب پوشه/دسته", file.folder || "");
            if (folder === null) return;
            var status = window.prompt("وضعیت لینک: active یا hidden", file.status || "active");
            if (status === null) return;
            var response = await request("ownerUpdateFile", {
                id: file.id,
                title: title,
                description: description,
                folder: folder,
                status: status,
                tags: Array.isArray(file.tags) ? file.tags.join(", ") : ""
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "ویرایش فایل انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "فایل به‌روزرسانی شد.", "success");
            await loadLinks(true);
        }

        async function createFolder() {
            var name = window.prompt("نام پوشه جدید", "");
            if (name === null) return;
            name = String(name || "").trim();
            if (!name) {
                setFeedback(feedback, "نام پوشه نمی‌تواند خالی باشد.", "error");
                return;
            }
            var response = await request("ownerDownloadHostCreateDir", {
                parentPath: currentUploadPath(),
                name: name
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "ساخت پوشه انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "پوشه جدید ساخته شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            await loadHostSummary(true, true);
        }

        async function renameEntry(path, type) {
            var currentName = folderName(path);
            var newName = window.prompt("نام جدید", currentName);
            if (newName === null) return;
            newName = String(newName || "").trim();
            if (!newName) {
                setFeedback(feedback, "نام جدید معتبر نیست.", "error");
                return;
            }
            var response = await request("ownerDownloadHostRenameEntry", {
                path: path,
                type: type,
                newName: newName
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "تغییر نام انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "نام فایل یا پوشه به‌روزرسانی شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            await loadLinks(true);
        }

        async function deleteEntry(path, type) {
            var label = type === "dir" ? "این پوشه و محتویاتش" : "این فایل";
            if (!window.confirm(label + " حذف شود؟")) return;
            var response = await request("ownerDownloadHostDeleteEntry", {
                path: path,
                type: type
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "حذف انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "ورودی حذف شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            await loadHostSummary(true, true);
            await loadLinks(true);
        }

        if (pickButton && uploadInput) {
            pickButton.addEventListener("click", function () {
                uploadInput.click();
            });
            uploadInput.addEventListener("change", function () {
                addFiles(uploadInput.files || []);
                uploadInput.value = "";
            });
        }

        if (dropzone && uploadInput) {
            dropzone.addEventListener("click", function () {
                uploadInput.click();
            });
            dropzone.addEventListener("keydown", function (event) {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    uploadInput.click();
                }
            });
            ["dragenter", "dragover"].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.add("is-dragover");
                });
            });
            ["dragleave", "dragend", "drop"].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove("is-dragover");
                });
            });
            dropzone.addEventListener("drop", function (event) {
                addFiles(event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : []);
            });
        }

        if (clearQueueButton) {
            clearQueueButton.addEventListener("click", function () {
                clearQueue();
                resetUploadMeta();
            });
        }
        if (submitButton) {
            submitButton.addEventListener("click", function (event) {
                event.preventDefault();
                uploadQueue();
            });
        }
        if (queueNode) {
            queueNode.addEventListener("click", function (event) {
                var copyNode = event.target.closest("[data-copy]");
                if (copyNode) {
                    copyText(copyNode.getAttribute("data-copy"), function (ok) {
                        setFeedback(feedback, ok ? "لینک کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                    });
                    return;
                }
                var removeNode = event.target.closest("[data-remove-queue]");
                if (removeNode) {
                    removeQueueItem(removeNode.getAttribute("data-remove-queue") || "");
                }
            });
        }

        if (browserQuery) {
            browserQuery.addEventListener("input", function () {
                state.browserQuery = browserQuery.value || "";
                renderBrowser();
            });
        }
        if (browserRefresh) {
            browserRefresh.addEventListener("click", function () {
                loadBrowser(currentUploadPath(), true);
            });
        }
        if (browserCreateFolder) {
            browserCreateFolder.addEventListener("click", function () {
                createFolder();
            });
        }
        if (breadcrumbs) {
            breadcrumbs.addEventListener("click", function (event) {
                var button = event.target.closest("[data-path]");
                if (!button) return;
                loadBrowser(button.getAttribute("data-path") || "", true);
            });
        }
        if (browserEntries) {
            browserEntries.addEventListener("click", function (event) {
                var copyNode = event.target.closest("[data-copy]");
                if (copyNode) {
                    copyText(copyNode.getAttribute("data-copy"), function (ok) {
                        setFeedback(feedback, ok ? "لینک مستقیم کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                    });
                    return;
                }
                var openNode = event.target.closest("[data-open-path]");
                if (openNode) {
                    loadBrowser(openNode.getAttribute("data-open-path") || "", true);
                    return;
                }
                var renameNode = event.target.closest("[data-rename-path]");
                if (renameNode) {
                    renameEntry(renameNode.getAttribute("data-rename-path") || "", renameNode.getAttribute("data-entry-type") || "file");
                    return;
                }
                var deleteNode = event.target.closest("[data-delete-path]");
                if (deleteNode) {
                    deleteEntry(deleteNode.getAttribute("data-delete-path") || "", deleteNode.getAttribute("data-entry-type") || "file");
                }
            });
        }

        var filesRefresh = $("ct-files-refresh");
        if (filesRefresh) {
            filesRefresh.addEventListener("click", function () {
                loadLinks(true);
            });
        }
        var filesQuery = $("ct-files-query");
        if (filesQuery) {
            filesQuery.addEventListener("input", function () {
                state.linkQuery = filesQuery.value || "";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        var filesStatus = $("ct-files-status");
        if (filesStatus) {
            filesStatus.addEventListener("change", function () {
                state.linkStatus = filesStatus.value || "available";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        var filesType = $("ct-files-type");
        if (filesType) {
            filesType.addEventListener("change", function () {
                state.linkType = filesType.value || "all";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        var filesSort = $("ct-files-sort");
        if (filesSort) {
            filesSort.addEventListener("change", function () {
                state.linkSort = filesSort.value || "newest";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        document.querySelectorAll("[data-ct-bulk-files]").forEach(function (button) {
            button.addEventListener("click", function () {
                bulkFiles(button.getAttribute("data-ct-bulk-files") || "");
            });
        });
        if (linksList) {
            linksList.addEventListener("change", function (event) {
                var checkbox = event.target.closest("[data-file-check]");
                if (!checkbox) return;
                state.linkSelected[checkbox.getAttribute("data-file-check") || ""] = !!checkbox.checked;
                renderSelectedCount();
            });
            linksList.addEventListener("click", function (event) {
                var copyNode = event.target.closest("[data-copy]");
                if (copyNode) {
                    copyText(copyNode.getAttribute("data-copy"), function (ok) {
                        setFeedback(feedback, ok ? "لینک کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                    });
                    return;
                }
                var editNode = event.target.closest("[data-edit-file]");
                if (editNode) {
                    editFile(editNode.getAttribute("data-edit-file") || "");
                    return;
                }
                var deleteNode = event.target.closest("[data-single-file-delete]");
                if (deleteNode) {
                    bulkFiles("delete", [deleteNode.getAttribute("data-single-file-delete") || ""]);
                }
            });
        }
        if (linksPager) {
            linksPager.addEventListener("click", function (event) {
                var button = event.target.closest("[data-links-page]");
                if (!button) return;
                state.linkPage = Math.max(1, Number(button.getAttribute("data-links-page") || "1"));
                loadLinks(true);
            });
        }

        renderQueue();
        updateDestinationUi();
        loadHostSummary(false, true);
        loadBrowser("", true);
        loadLinks(true);
    }

    initOwnerGuard(initFilesCenter);
}());
