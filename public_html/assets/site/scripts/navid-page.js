(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var flow = $("navid-flow");
    if (!flow) {
        return;
    }

    var authStage = $("navid-auth-stage");
    var loadingStage = $("navid-loading");
    var dashboard = $("navid-dashboard");

    var loginLink = $("navid-login-link");
    var authFeedback = $("navid-auth-feedback");

    var statusTitle = $("navid-status-title");
    var statusDesc = $("navid-status-desc");
    var feedback = $("navid-feedback");
    var syncNowButton = $("navid-sync-now");

    var countAssignmentsHero = $("navid-count-assignments");
    var countAssignmentsStatus = $("navid-count-assignments-status");
    var countUpdates = $("navid-count-updates");
    var lastCheck = $("navid-last-check");
    var sessionState = $("navid-session-state");
    var countCoursesHero = $("navid-count-courses");
    var countCoursesStatus = $("navid-count-courses-status");
    var nextDeadlineHero = $("navid-next-deadline");
    var nextDeadlineStatus = $("navid-next-deadline-status");

    var ownerBox = $("navid-owner-box");
    var ownerCards = $("navid-owner-status-cards");
    var ownerToggleButton = $("navid-owner-toggle");
    var assignmentsPanel = $("navid-assignments-panel");
    var updatesPanel = $("navid-updates-panel");
    var assignmentsMoreButton = $("navid-assignments-more");
    var updatesMoreButton = $("navid-updates-more");
    var viewAssignmentsCount = $("navid-view-assignments-count");
    var viewUpdatesCount = $("navid-view-updates-count");
    var contentViewButtons = Array.prototype.slice.call(document.querySelectorAll("[data-navid-view]"));

    var updatesList = $("navid-updates-list");
    var assignmentsList = $("navid-assignments-list");

    var currentUser = null;
    var currentUserKey = "";
    var loadingFeed = false;
    var feedTicket = 0;
    var hasLoadedFeed = false;
    var currentContentView = "assignments";
    var currentAssignments = [];
    var currentUpdates = [];
    var assignmentsExpanded = false;
    var updatesExpanded = false;
    var ownerExpanded = false;
    var ASSIGNMENTS_PREVIEW_COUNT = 1;
    var UPDATES_PREVIEW_COUNT = 3;

    function consumeUnauthorized(response, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;

        if (!auth) {
            return false;
        }

        var message = fallbackText || "نشست شما منقضی شده است.";
        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(response, message);
            }
        } catch (_error) {
            // Ignore auth surface mismatches and continue fallback.
        }

        if (response && (response.loggedOut || response.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((response && response.error) || message);
            }
            return true;
        }

        return false;
    }

    function safeText(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case "\"":
                    return "&quot;";
                case "'":
                    return "&#39;";
                default:
                    return char;
            }
        });
    }

    function safeAttr(value) {
        return safeText(value);
    }

    function normalizeInlineText(value) {
        return String(value || "")
            .replace(/\u00a0/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function normalizeMultilineText(value) {
        return String(value || "")
            .replace(/\u00a0/g, " ")
            .replace(/\r\n?/g, "\n")
            .replace(/[ \t]+\n/g, "\n")
            .replace(/\n{3,}/g, "\n\n")
            .trim();
    }

    function safeMultilineHtml(value) {
        var text = normalizeMultilineText(value);
        if (!text) {
            return "";
        }
        return safeText(text).replace(/\n/g, "<br>");
    }

    function snippet(value, maxLength) {
        var clean = normalizeInlineText(value);
        if (!clean) {
            return "";
        }
        if (clean.length <= maxLength) {
            return clean;
        }
        return clean.slice(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    function formatDate(value, fallback) {
        var raw = String(value || "").trim();
        if (!raw) {
            return fallback || "—";
        }

        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return raw;
        }

        return parsed.toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function formatDateOrLabel(primary, fallbackDate, fallbackText) {
        var label = normalizeInlineText(primary);
        if (label) {
            return label;
        }
        return formatDate(fallbackDate, fallbackText || "—");
    }

    function parseDate(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return null;
        }

        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return null;
        }
        return parsed;
    }

    function deadlineInfo(item) {
        var parsed = parseDate(item && item.endDateIso);
        var label = formatDateOrLabel(item && item.endDateShamsi, item && item.endDateIso, "نامشخص");
        var now = Date.now();
        var tone = "accent";
        var stateLabel = "در جریان";

        if (!parsed) {
            return {
                label: label,
                tone: "muted",
                stateLabel: "بدون مهلت دقیق",
                date: null,
                time: Number.POSITIVE_INFINITY
            };
        }

        var diffMs = parsed.getTime() - now;
        if (diffMs < 0) {
            tone = "danger";
            stateLabel = "مهلت گذشته";
        } else if (diffMs <= 24 * 60 * 60 * 1000) {
            tone = "danger";
            stateLabel = "مهلت امروز";
        } else if (diffMs <= 3 * 24 * 60 * 60 * 1000) {
            tone = "warning";
            stateLabel = "نزدیک به ددلاین";
        } else {
            tone = "success";
            stateLabel = "زمان کافی";
        }

        return {
            label: label,
            tone: tone,
            stateLabel: stateLabel,
            date: parsed,
            time: parsed.getTime()
        };
    }

    function resultLabel(result) {
        switch (result) {
            case "ok":
                return "پایدار";
            case "partial":
                return "ناقص";
            case "running":
                return "در حال اجرا";
            case "config-updated":
                return "تنظیمات به‌روز شد";
            case "credentials-missing":
                return "اعتبار ثبت نشده";
            case "credentials-invalid":
                return "اعتبار نامعتبر";
            case "reconnect-required":
                return "نیازمند اتصال مجدد";
            case "login-failed":
                return "خطای ورود";
            case "dashboard-failed":
                return "خطای داشبورد";
            case "exception":
                return "خطای داخلی";
            case "skipped":
                return "به‌روز";
            case "already-running":
                return "همگام‌سازی در حال انجام";
            case "lock-failed":
                return "خطای قفل";
            case "disabled":
                return "غیرفعال";
            default:
                return "نامشخص";
        }
    }

    function actionRequiredLabel(action) {
        switch (String(action || "")) {
            case "save-credentials":
                return "ثبت اعتبار نوید";
            case "update-credentials":
                return "به‌روزرسانی اعتبار";
            case "manual-reconnect":
                return "اتصال مجدد با کپچا";
            case "disabled":
                return "غیرفعال";
            default:
                return "هیچ";
        }
    }

    function setFlowState(state) {
        flow.dataset.authState = state;
        authStage.hidden = state !== "signed-out" && state !== "unauthorized";
        loadingStage.hidden = state !== "restoring" && state !== "loading";
        dashboard.hidden = state === "signed-out" || state === "unauthorized" || state === "restoring" || state === "loading";
    }

    function setAuthFeedback(text, kind) {
        if (!authFeedback) {
            return;
        }
        authFeedback.className = "navid-feedback" + (kind ? " " + kind : "");
        authFeedback.textContent = text || "";
    }

    function setDashboardFeedback(text, kind) {
        if (!feedback) {
            return;
        }
        feedback.className = "navid-feedback navid-feedback--inline" + (kind ? " " + kind : "");
        feedback.textContent = text || "";
    }

    function renderEmpty(container, message) {
        if (!container) {
            return;
        }
        container.innerHTML = '<div class="navid-empty">' + safeText(message) + "</div>";
    }

    function setText(node, value) {
        if (!node) {
            return;
        }
        node.textContent = value;
    }

    function setMirroredText(nodes, value) {
        nodes.forEach(function (node) {
            setText(node, value);
        });
    }

    function normalizeContentView(value) {
        return String(value || "").toLowerCase() === "updates" ? "updates" : "assignments";
    }

    function syncContentView() {
        currentContentView = normalizeContentView(currentContentView);
        flow.dataset.contentView = currentContentView;

        if (assignmentsPanel) {
            assignmentsPanel.hidden = currentContentView !== "assignments";
        }
        if (updatesPanel) {
            updatesPanel.hidden = currentContentView !== "updates";
        }

        contentViewButtons.forEach(function (button) {
            var active = normalizeContentView(button.getAttribute("data-navid-view")) === currentContentView;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-pressed", active ? "true" : "false");
        });
    }

    function setContentView(nextView) {
        currentContentView = normalizeContentView(nextView);
        syncContentView();
    }

    function uniqueCourseCount(assignments) {
        var seen = Object.create(null);
        var count = 0;
        (Array.isArray(assignments) ? assignments : []).forEach(function (item) {
            var key = String(item && (item.courseTemplateId || item.courseTitle || ""));
            if (!key || seen[key]) {
                return;
            }
            seen[key] = true;
            count += 1;
        });
        return count;
    }

    function findNearestDeadline(assignments) {
        var nearest = null;
        (Array.isArray(assignments) ? assignments : []).forEach(function (item) {
            var info = deadlineInfo(item);
            if (!info.date) {
                return;
            }
            if (!nearest || info.time < nearest.info.time) {
                nearest = {
                    item: item,
                    info: info
                };
            }
        });
        return nearest;
    }

    function ownerCard(label, value) {
        return [
            '<article class="navid-owner-card">',
            '  <span>' + safeText(label) + "</span>",
            '  <strong>' + safeText(value) + "</strong>",
            "</article>"
        ].join("");
    }

    function renderOwnerStatus(ownerStatus) {
        var isOwner = !!(currentUser && currentUser.isOwner);
        if (!ownerBox || !ownerCards) {
            return;
        }

        if (!isOwner || !ownerStatus || typeof ownerStatus !== "object") {
            ownerBox.hidden = true;
            ownerCards.innerHTML = "";
            ownerExpanded = false;
            return;
        }

        ownerBox.hidden = false;
        var state = ownerStatus.state || {};
        var config = ownerStatus.config || {};
        var session = ownerStatus.session || {};
        var actionRequired = state.actionRequired || "none";
        var snapshotCounts = ownerStatus.snapshotCounts || {};
        var failedCourses = Math.max(0, Math.floor(Number(
            state.lastFailedCourses != null ? state.lastFailedCourses : snapshotCounts.failedCourses
        ) || 0));

        ownerCards.innerHTML = [
            ownerCard("نتیجه آخر", resultLabel(state.lastResult || "")),
            ownerCard("آخرین موفق", formatDate(state.lastSuccessAt, "—")),
            ownerCard("وضعیت نشست", session.status || "—"),
            ownerCard("اقدام لازم", actionRequiredLabel(actionRequired)),
            ownerCard("درس‌های ناموفق", failedCourses.toLocaleString("fa-IR")),
            ownerCard(
                "اعتبار ذخیره‌شده",
                config.hasCredentials ? (config.usernameMasked || "ثبت شده") : "ثبت نشده"
            )
        ].join("");
        syncOwnerPanel();
    }

    function syncOwnerPanel() {
        if (!ownerBox || !ownerCards) {
            return;
        }
        ownerBox.dataset.collapsed = ownerExpanded ? "false" : "true";
        ownerCards.hidden = !ownerExpanded;
        if (ownerToggleButton) {
            ownerToggleButton.textContent = ownerExpanded ? "بستن جزئیات مالک" : "باز کردن جزئیات مالک";
            ownerToggleButton.setAttribute("aria-expanded", ownerExpanded ? "true" : "false");
            if (!ownerExpanded) {
                ownerToggleButton.textContent = "\u062C\u0632\u0626\u06CC\u0627\u062A \u0645\u0627\u0644\u06A9";
            }
        }
    }

    function buildUpdateCard(item) {
        var eventType = String(item && item.eventType || "").toLowerCase() === "updated"
            ? "ویرایش تکلیف"
            : "تکلیف جدید";
        var updateClass = String(item && item.eventType || "").toLowerCase() === "updated"
            ? " navid-update--updated"
            : "";
        var course = snippet(item && item.courseTitle, 120) || "درس نامشخص";
        var detectedAt = formatDate(item && item.detectedAt, "زمان تشخیص نامشخص");
        var deadline = formatDateOrLabel(item && item.endDateShamsi, item && item.endDateIso, "نامشخص");
        var createdAt = formatDateOrLabel(item && item.proposeDate, item && item.sourceUpdatedAt, "نامشخص");
        var description = snippet(item && item.descriptionText, 260) || "برای این تغییر توضیح متنی ثبت نشده است.";

        return [
            '<article class="navid-update' + updateClass + '">',
            '  <div class="navid-update__head">',
            '    <span class="navid-badge">' + safeText(eventType) + "</span>",
            '    <span class="navid-update__time">' + safeText(detectedAt) + "</span>",
            "  </div>",
            '  <h4 class="navid-update__title">' + safeText(snippet(item && item.title, 160) || "بدون عنوان") + "</h4>",
            '  <div class="navid-update__meta">',
            '    <div><span class="navid-update__meta-label">درس</span><div class="navid-update__meta-value">' + safeText(course) + "</div></div>",
            '    <div><span class="navid-update__meta-label">تاریخ ایجاد</span><div class="navid-update__meta-value">' + safeText(createdAt) + "</div></div>",
            '    <div><span class="navid-update__meta-label">مهلت ارسال</span><div class="navid-update__meta-value">' + safeText(deadline) + "</div></div>",
            "  </div>",
            '  <p class="navid-update__desc">' + safeText(description) + "</p>",
            "</article>"
        ].join("");
    }

    function buildFileChip(file) {
        if (!file || typeof file !== "object") {
            return "";
        }

        var name = normalizeInlineText(file.name) || "فایل بدون نام";
        var metaParts = [];
        if (file.extension) {
            metaParts.push(String(file.extension).toUpperCase());
        }
        if (Number(file.size) > 0) {
            metaParts.push(Number(file.size).toLocaleString("fa-IR") + " بایت");
        }
        var meta = metaParts.length ? metaParts.join(" • ") : "پیوست نوید";
        var url = String(file.url || "").trim();

        if (!url) {
            return [
                '<div class="navid-file-chip">',
                '  <span class="navid-file-chip__name">' + safeText(name) + "</span>",
                '  <span class="navid-file-chip__meta">' + safeText(meta) + "</span>",
                "</div>"
            ].join("");
        }

        return [
            '<a class="navid-file-chip" href="' + safeAttr(url) + '" target="_blank" rel="noopener noreferrer">',
            '  <span class="navid-file-chip__name">' + safeText(name) + "</span>",
            '  <span class="navid-file-chip__meta">' + safeText(meta) + "</span>",
            "</a>"
        ].join("");
    }

    function buildAssignmentCard(item) {
        var info = deadlineInfo(item);
        var toneClass = info.tone === "danger"
            ? " navid-assignment--danger"
            : info.tone === "warning"
                ? " navid-assignment--warning"
                : info.tone === "success"
                    ? " navid-assignment--success"
                    : "";
        var course = snippet(item && item.courseTitle, 120) || "درس نامشخص";
        var title = snippet(item && item.title, 220) || "بدون عنوان";
        var createdAt = formatDateOrLabel(item && item.proposeDate, item && item.sourceUpdatedAt, "نامشخص");
        var deadline = info.label;
        var descriptionText = normalizeMultilineText(item && item.descriptionText);
        var description = safeMultilineHtml(descriptionText) || "برای این تکلیف توضیحی ثبت نشده است.";
        var hasLongDescription = descriptionText.length > 140;
        var files = Array.isArray(item && item.files) ? item.files : [];
        var fileCountLabel = files.length
            ? (files.length.toLocaleString("fa-IR") + " فایل")
            : "بدون پیوست";
        var attachmentsHtml = files.length
            ? [
                '<section class="navid-assignment__section">',
                '  <div class="navid-assignment__section-head">',
                '    <span class="navid-assignment__section-label">پیوست‌های تکلیف</span>',
                '    <span class="navid-badge navid-badge--muted">' + safeText(fileCountLabel) + "</span>",
                "  </div>",
                '  <div class="navid-file-list" data-files-list hidden>' + files.map(buildFileChip).join("") + "</div>",
                '  <button class="navid-inline-toggle" type="button" data-files-toggle aria-expanded="false">نمایش فایل‌ها</button>',
                "</section>"
            ].join("")
            : "";

        return [
            '<article class="navid-assignment' + toneClass + '">',
            '  <div class="navid-assignment__head">',
            '    <div class="navid-assignment__badges">',
            '      <span class="navid-badge">' + safeText(course) + "</span>",
            '      <span class="navid-badge navid-badge--' + safeText(info.tone) + '">' + safeText(info.stateLabel) + "</span>",
            "    </div>",
            "  </div>",
            '  <h4 class="navid-assignment__title">' + safeText(title) + "</h4>",
            '  <div class="navid-assignment__meta">',
            '    <div class="navid-field"><span class="navid-field__label">تاریخ ایجاد تکلیف</span><strong class="navid-field__value">' + safeText(createdAt) + "</strong></div>",
            '    <div class="navid-field"><span class="navid-field__label">مهلت ارسال تکلیف</span><strong class="navid-field__value">' + safeText(deadline) + "</strong></div>",
            "  </div>",
            '  <section class="navid-assignment__section">',
            '    <div class="navid-assignment__section-head">',
            '      <span class="navid-assignment__section-label">متن تکلیف</span>',
            '      <span class="navid-badge navid-badge--muted">' + safeText(fileCountLabel) + "</span>",
            "    </div>",
            '    <div class="navid-assignment__description">',
            '      <div class="navid-assignment__description-copy' + (hasLongDescription ? ' is-collapsed' : '') + '">' + description + "</div>",
            hasLongDescription
                ? '      <button class="navid-inline-toggle" type="button" data-desc-toggle aria-expanded="false">نمایش کامل</button>'
                : "",
            "    </div>",
            "  </section>",
            attachmentsHtml,
            "</article>"
        ].join("");
    }

    function syncAssignmentsPreview() {
        if (!assignmentsList) {
            return;
        }

        var list = currentAssignments.slice();
        if (!list.length) {
            renderEmpty(assignmentsList, "فعلاً تکلیف فعالی در خروجی نوید پیدا نشد.");
            if (assignmentsMoreButton) {
                assignmentsMoreButton.hidden = true;
            }
            return;
        }

        var visible = assignmentsExpanded ? list : list.slice(0, ASSIGNMENTS_PREVIEW_COUNT);
        assignmentsList.innerHTML = visible.map(buildAssignmentCard).join("");

        if (assignmentsMoreButton) {
            var hiddenCount = Math.max(0, list.length - visible.length);
            assignmentsMoreButton.hidden = list.length <= ASSIGNMENTS_PREVIEW_COUNT;
            assignmentsMoreButton.textContent = assignmentsExpanded
                ? "جمع‌کردن فهرست تکالیف"
                : ("نمایش " + hiddenCount.toLocaleString("fa-IR") + " تکلیف دیگر");
            assignmentsMoreButton.setAttribute("aria-expanded", assignmentsExpanded ? "true" : "false");
        }
    }

    function syncUpdatesPreview() {
        if (!updatesList) {
            return;
        }

        var list = currentUpdates.slice();
        if (!list.length) {
            renderEmpty(updatesList, "هنوز تغییر تازه‌ای برای تکالیف در نوید ثبت نشده است.");
            if (updatesMoreButton) {
                updatesMoreButton.hidden = true;
            }
            return;
        }

        var visible = updatesExpanded ? list : list.slice(0, UPDATES_PREVIEW_COUNT);
        updatesList.innerHTML = visible.map(buildUpdateCard).join("");

        if (updatesMoreButton) {
            var hiddenCount = Math.max(0, list.length - visible.length);
            updatesMoreButton.hidden = list.length <= UPDATES_PREVIEW_COUNT;
            updatesMoreButton.textContent = updatesExpanded
                ? "جمع‌کردن آپدیت‌ها"
                : ("نمایش " + hiddenCount.toLocaleString("fa-IR") + " آپدیت دیگر");
            updatesMoreButton.setAttribute("aria-expanded", updatesExpanded ? "true" : "false");
        }
    }

    function renderUpdates(updates) {
        currentUpdates = Array.isArray(updates) ? updates.slice(0, 12) : [];
        updatesExpanded = false;
        syncUpdatesPreview();
    }

    function renderAssignments(assignments) {
        currentAssignments = Array.isArray(assignments) ? assignments.slice(0, 120) : [];
        assignmentsExpanded = false;
        syncAssignmentsPreview();
    }

    function renderFeed(data) {
        var payload = data && typeof data === "object" ? data : {};
        var publicStatus = payload.publicStatus || {};
        var updates = Array.isArray(payload.updates) ? payload.updates : [];
        var assignments = Array.isArray(payload.currentAssignments) ? payload.currentAssignments : [];
        var ownerStatus = payload.ownerStatus || null;
        var enabled = !!publicStatus.enabled;
        var actionRequired = String(publicStatus.actionRequired || "");
        var publicLastError = snippet(publicStatus.lastError, 180);
        var failedCourses = Math.max(0, Math.floor(Number(publicStatus.lastFailedCourses) || 0));
        var assignmentCountText = assignments.length.toLocaleString("fa-IR");
        var courseCountText = uniqueCourseCount(assignments).toLocaleString("fa-IR");
        var nearestDeadline = findNearestDeadline(assignments);
        var nearestDeadlineText = nearestDeadline
            ? (snippet(nearestDeadline.item.courseTitle, 48) + " • " + nearestDeadline.info.label)
            : "فعلاً مهلت فعالی نیست";
        var updateCountText = updates.length.toLocaleString("fa-IR");

        setMirroredText([countAssignmentsHero, countAssignmentsStatus], assignmentCountText);
        setMirroredText([countCoursesHero, countCoursesStatus], courseCountText);
        setMirroredText([nextDeadlineHero, nextDeadlineStatus], nearestDeadlineText);
        setText(countUpdates, updateCountText);
        setText(lastCheck, formatDate(publicStatus.lastSuccessAt || publicStatus.lastSyncAt, "—"));
        setText(viewAssignmentsCount, assignmentCountText);
        setText(viewUpdatesCount, updateCountText);

        if (!assignments.length && updates.length && currentContentView !== "updates") {
            setContentView("updates");
        } else if (assignments.length && currentContentView !== "assignments") {
            syncContentView();
        }

        if (!enabled) {
            setText(statusTitle, "یکپارچه‌سازی نوید غیرفعال است");
            setText(statusDesc, "فعالسازی و ثبت تنظیمات اتصال را از پنل حساب انجام بده.");
            setText(sessionState, "غیرفعال");
            setDashboardFeedback("خروجی نوید بعد از فعال‌سازی و ثبت اعتبار نمایش داده می‌شود.", "");
            renderOwnerStatus(ownerStatus);
            renderUpdates([]);
            renderAssignments([]);
            return;
        }

        setText(statusTitle, "وضعیت همگام‌سازی: " + resultLabel(publicStatus.lastResult || ""));

        if (actionRequired === "save-credentials" || publicStatus.credentialsMissing) {
            setText(
                statusDesc,
                currentUser && currentUser.isOwner
                    ? "نام کاربری و رمز نوید هنوز ثبت نشده است. آن را از پنل حساب ذخیره کن."
                    : "اتصال نوید هنوز توسط مدیر کامل نشده و نیاز به ثبت اعتبار دارد."
            );
        } else if (actionRequired === "update-credentials" || publicStatus.credentialsInvalid) {
            setText(
                statusDesc,
                currentUser && currentUser.isOwner
                    ? "اعتبار ذخیره‌شده‌ی نوید نامعتبر شده است و باید از پنل حساب به‌روزرسانی شود."
                    : "اتصال نوید نیاز به به‌روزرسانی اعتبار توسط مدیر دارد."
            );
        } else if (actionRequired === "manual-reconnect" || publicStatus.requiresReconnect) {
            setText(
                statusDesc,
                currentUser && currentUser.isOwner
                    ? "نشست نوید نیاز به reconnect دستی دارد تا همگام‌سازی دوباره پایدار شود."
                    : "برای تازه شدن خروجی نوید، مدیر باید اتصال را دوباره برقرار کند."
            );
        } else if (publicStatus.lastResult === "partial") {
            setText(
                statusDesc,
                failedCourses > 0
                    ? ("همگام‌سازی ناقص بود و " + failedCourses.toLocaleString("fa-IR") + " درس کامل دریافت نشد.")
                    : "همگام‌سازی ناقص بود و بخشی از خروجی تایید نشد."
            );
        } else if (publicLastError) {
            setText(statusDesc, publicLastError);
        } else {
            setText(statusDesc, "تکالیف فعال، توضیحات آن‌ها و آخرین تغییرات از آخرین sync موفق نوید نمایش داده می‌شود.");
        }

        if (ownerStatus && ownerStatus.session) {
            setText(sessionState, ownerStatus.session.status || "—");
        } else if (actionRequired === "save-credentials" || publicStatus.credentialsMissing) {
            setText(sessionState, "بدون اعتبار");
        } else if (actionRequired === "update-credentials" || publicStatus.credentialsInvalid) {
            setText(sessionState, "اعتبار نامعتبر");
        } else if (actionRequired === "manual-reconnect" || publicStatus.requiresReconnect) {
            setText(sessionState, "نیازمند reconnect");
        } else if (publicStatus.lastResult === "partial") {
            setText(sessionState, "ناقص");
        } else {
            setText(sessionState, "فعال");
        }

        if (actionRequired === "save-credentials" || publicStatus.credentialsMissing) {
            setDashboardFeedback("اعتبار ورود نوید هنوز ثبت نشده است.", "error");
        } else if (actionRequired === "update-credentials" || publicStatus.credentialsInvalid) {
            setDashboardFeedback("نام کاربری یا رمز نوید نامعتبر است و باید اصلاح شود.", "error");
        } else if (actionRequired === "manual-reconnect" || publicStatus.requiresReconnect) {
            setDashboardFeedback("اتصال نوید نیاز به reconnect دستی و عبور از کپچا دارد.", "error");
        } else if (publicStatus.lastResult === "partial") {
            setDashboardFeedback(
                failedCourses > 0
                    ? ("همگام‌سازی ناقص بود؛ " + failedCourses.toLocaleString("fa-IR") + " درس کامل خوانده نشد.")
                    : "همگام‌سازی ناقص است.",
                "error"
            );
        } else if (publicStatus.lastResult === "ok" || publicStatus.lastResult === "skipped" || publicStatus.lastResult === "already-running") {
            setDashboardFeedback("اطلاعات نوید با آخرین sync موفق نمایش داده می‌شود.", "success");
        } else if (publicLastError) {
            setDashboardFeedback(publicLastError, "error");
        } else {
            setDashboardFeedback("", "");
        }

        renderOwnerStatus(ownerStatus);
        renderUpdates(updates);
        renderAssignments(assignments);
    }

    function apiGet(action) {
        return fetch("/api/navid_api.php?action=" + encodeURIComponent(action), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function apiPost(action, payload) {
        return fetch("/api/navid_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    async function loadFeed() {
        if (loadingFeed || !currentUser) {
            return;
        }

        loadingFeed = true;
        var ticket = ++feedTicket;
        var inlineRefresh = hasLoadedFeed && dashboard && !dashboard.hidden;
        if (inlineRefresh) {
            setDashboardFeedback("در حال به‌روزرسانی خروجی نوید...", "");
        } else {
            setFlowState("loading");
            setDashboardFeedback("", "");
        }

        try {
            var response = await apiGet("feed");
            if (ticket !== feedTicket) {
                return;
            }

            if (consumeUnauthorized(response, "نشست شما منقضی شد.")) {
                currentUser = null;
                currentUserKey = "";
                hasLoadedFeed = false;
                setFlowState("unauthorized");
                setAuthFeedback("نشست شما منقضی شد. دوباره وارد شو.", "error");
                return;
            }

            if (!response || !response.success || !response.data) {
                setFlowState("ready");
                setDashboardFeedback((response && response.error) || "دریافت اطلاعات نوید انجام نشد.", "error");
                if (!inlineRefresh) {
                    renderUpdates([]);
                    renderAssignments([]);
                }
                return;
            }

            renderFeed(response.data);
            hasLoadedFeed = true;
            setFlowState("ready");
        } catch (error) {
            if (ticket !== feedTicket) {
                return;
            }

            setFlowState("ready");
            setDashboardFeedback((error && error.message) || "دریافت اطلاعات نوید با خطا متوقف شد.", "error");
            if (!inlineRefresh) {
                renderUpdates([]);
                renderAssignments([]);
            }
        } finally {
            if (ticket === feedTicket) {
                loadingFeed = false;
            }
        }
    }

    async function syncNow() {
        if (!currentUser || !currentUser.isOwner || !syncNowButton) {
            return;
        }

        syncNowButton.disabled = true;
        setDashboardFeedback("در حال همگام‌سازی فوری نوید...", "");

        try {
            var response = await apiPost("syncNow", {});
            if (consumeUnauthorized(response, "نشست شما منقضی شد.")) {
                currentUser = null;
                currentUserKey = "";
                setFlowState("unauthorized");
                setAuthFeedback("نشست شما منقضی شد. دوباره وارد شو.", "error");
                return;
            }

            if (!response || !response.success) {
                setDashboardFeedback(
                    (response && response.message) || (response && response.error) || "همگام‌سازی فوری ناموفق بود.",
                    "error"
                );
            } else {
                setDashboardFeedback(response.message || "همگام‌سازی فوری انجام شد.", "success");
            }
        } finally {
            syncNowButton.disabled = false;
            await loadFeed();
        }
    }

    function handleAssignmentListClick(event) {
        if (!assignmentsList) {
            return;
        }
        var button = event.target && event.target.closest ? event.target.closest("[data-desc-toggle]") : null;
        if (!button) {
            return;
        }
        var wrapper = button.closest(".navid-assignment__description");
        if (!wrapper) {
            return;
        }
        var copy = wrapper.querySelector(".navid-assignment__description-copy");
        if (!copy) {
            return;
        }
        var expanded = button.getAttribute("aria-expanded") === "true";
        if (expanded) {
            copy.classList.add("is-collapsed");
            button.setAttribute("aria-expanded", "false");
            button.textContent = "نمایش کامل";
        } else {
            copy.classList.remove("is-collapsed");
            button.setAttribute("aria-expanded", "true");
            button.textContent = "جمع کردن متن";
        }
    }

    function handleAssignmentFilesToggle(event) {
        if (!assignmentsList) {
            return;
        }
        var button = event.target && event.target.closest ? event.target.closest("[data-files-toggle]") : null;
        if (!button) {
            return;
        }
        var section = button.closest(".navid-assignment__section");
        if (!section) {
            return;
        }
        var filesList = section.querySelector("[data-files-list]");
        if (!filesList) {
            return;
        }
        var expanded = button.getAttribute("aria-expanded") === "true";
        if (expanded) {
            filesList.hidden = true;
            button.setAttribute("aria-expanded", "false");
            button.textContent = "نمایش فایل‌ها";
        } else {
            filesList.hidden = false;
            button.setAttribute("aria-expanded", "true");
            button.textContent = "بستن فایل‌ها";
        }
    }

    function handleContentViewClick(event) {
        var button = event.target && event.target.closest ? event.target.closest("[data-navid-view]") : null;
        if (!button) {
            return;
        }
        setContentView(button.getAttribute("data-navid-view"));
    }

    function handleMoreAssignments() {
        assignmentsExpanded = !assignmentsExpanded;
        syncAssignmentsPreview();
    }

    function handleMoreUpdates() {
        updatesExpanded = !updatesExpanded;
        syncUpdatesPreview();
    }

    function handleOwnerToggle() {
        ownerExpanded = !ownerExpanded;
        syncOwnerPanel();
    }

    function handleAuth(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            currentUser = null;
            currentUserKey = "";
            hasLoadedFeed = false;
            setFlowState("restoring");
            setAuthFeedback("", "");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            currentUser = null;
            currentUserKey = "";
            hasLoadedFeed = false;
            setFlowState(detail.status === "unauthorized" ? "unauthorized" : "signed-out");
            setAuthFeedback(
                detail.status === "unauthorized"
                    ? (detail.error || "نشست شما منقضی شد.")
                    : "برای دسترسی به خروجی نوید وارد حساب شو.",
                detail.status === "unauthorized" ? "error" : ""
            );
            if (loginLink) {
                loginLink.href = window.Dent1402Auth.loginUrl("/navid/");
            }
            return;
        }

        currentUser = detail.user;
        var nextKey = String(detail.user.studentNumber || "_logged");
        var changed = nextKey !== currentUserKey;
        currentUserKey = nextKey;

        if (syncNowButton) {
            syncNowButton.hidden = !detail.user.isOwner;
        }

        if (changed || !dashboard || dashboard.hidden) {
            loadFeed();
        }
    }

    if (syncNowButton) {
        syncNowButton.addEventListener("click", syncNow);
    }

    if (assignmentsList) {
        assignmentsList.addEventListener("click", handleAssignmentListClick);
        assignmentsList.addEventListener("click", handleAssignmentFilesToggle);
    }

    contentViewButtons.forEach(function (button) {
        button.addEventListener("click", handleContentViewClick);
    });

    if (assignmentsMoreButton) {
        assignmentsMoreButton.addEventListener("click", handleMoreAssignments);
    }

    if (updatesMoreButton) {
        updatesMoreButton.addEventListener("click", handleMoreUpdates);
    }

    if (ownerToggleButton) {
        ownerToggleButton.addEventListener("click", handleOwnerToggle);
    }

    syncContentView();

    window.Dent1402Auth.onChange(handleAuth);
})();
