(function () {
    "use strict";

    function create(context) {
        var getCurrentUser = context.getCurrentUser;
        var accountNavidAlertBody = context.accountNavidAlertBody;
        var accountNavidAlertCard = context.accountNavidAlertCard;
        var accountNavidAlertLink = context.accountNavidAlertLink;
        var accountNavidAlertMarkRead = context.accountNavidAlertMarkRead;
        var accountNavidAlertTime = context.accountNavidAlertTime;
        var accountNavidAlertTitle = context.accountNavidAlertTitle;
        var accountNotificationAlertKind = context.accountNotificationAlertKind;
        var accountRowNotificationsMeta = context.accountRowNotificationsMeta;
        var accountUserKey = context.accountUserKey;
        var consumeUnauthorized = context.consumeUnauthorized;
        var escapeHtml = context.escapeHtml;
        var formatJalaliDateTime = context.formatJalaliDateTime;
        var networkErrorResponse = context.networkErrorResponse;
        var notificationsBodyInput = context.notificationsBodyInput;
        var notificationsBroadcastForm = context.notificationsBroadcastForm;
        var notificationsBroadcastSubmit = context.notificationsBroadcastSubmit;
        var notificationsComposeShell = context.notificationsComposeShell;
        var notificationsCtaHrefInput = context.notificationsCtaHrefInput;
        var notificationsCtaLabelInput = context.notificationsCtaLabelInput;
        var notificationsDigestEnabledToggle = context.notificationsDigestEnabledToggle;
        var notificationsDigestHourInput = context.notificationsDigestHourInput;
        var notificationsEmpty = context.notificationsEmpty;
        var notificationsFeedback = context.notificationsFeedback;
        var notificationsFilters = context.notificationsFilters;
        var notificationsFormRemindersToggle = context.notificationsFormRemindersToggle;
        var notificationsList = context.notificationsList;
        var notificationsManagerCard = context.notificationsManagerCard;
        var notificationsManagerFeedback = context.notificationsManagerFeedback;
        var notificationsMarkAllButton = context.notificationsMarkAllButton;
        var notificationsNavidAlertsToggle = context.notificationsNavidAlertsToggle;
        var notificationsNavidRow = context.notificationsNavidRow;
        var notificationsPaymentRemindersToggle = context.notificationsPaymentRemindersToggle;
        var notificationsPrefsCard = context.notificationsPrefsCard;
        var notificationsPrefsHint = context.notificationsPrefsHint;
        var notificationsPrefsSaveButton = context.notificationsPrefsSaveButton;
        var notificationsRefreshButton = context.notificationsRefreshButton;
        var notificationsScheduleInput = context.notificationsScheduleInput;
        var notificationsSendSmsInput = context.notificationsSendSmsInput;
        var notificationsState = context.notificationsState;
        var notificationsSummary = context.notificationsSummary;
        var notificationsTargetSelect = context.notificationsTargetSelect;
        var notificationsTitleInput = context.notificationsTitleInput;
        var setFeedback = context.setFeedback;
        var setInlineFeedback = context.setInlineFeedback;
        var toNumber = context.toNumber;
        var toPersianDigits = context.toPersianDigits;

    function notificationsUserKey() {
        return accountUserKey(getCurrentUser());
    }

    function notificationsGet(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch("/api/notifications_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
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
        }).catch(networkErrorResponse);
    }

    function notificationsPost(action, payload) {
        return fetch("/api/notifications_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
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
        }).catch(networkErrorResponse);
    }

    function notificationsResetState() {
        notificationsState.loading = false;
        notificationsState.requestToken = 0;
        notificationsState.loadedForUserKey = "";
        notificationsState.summary = null;
        notificationsState.preview = null;
        notificationsState.preferences = null;
        notificationsState.draftPreferences = null;
        notificationsState.manager = null;
        notificationsState.items = [];
        notificationsState.activeFilter = "all";
        notificationsState.savingPrefs = false;
        notificationsState.markingAll = false;
        notificationsState.broadcasting = false;
        notificationsState.markingIds = {};
        notificationsState.snoozingIds = {};
        notificationsState.audienceById = {};
        notificationsState.audienceLoadingIds = {};
        notificationsState.expandedAudienceId = "";
        notificationsState.deletingId = "";
    }

    function notificationsDispatchSummary(summary) {
        var unreadCount = Math.max(0, Math.floor(toNumber(summary && summary.unreadCount, 0)));
        window.dispatchEvent(new CustomEvent("dent1402:notifications-change", {
            detail: {
                unreadCount: unreadCount,
                preview: notificationsState.preview || null
            }
        }));
    }

    function notificationsNormalizeDigestHourValue(value, fallback) {
        var normalized = Math.floor(toNumber(value, fallback));
        if (!Number.isFinite(normalized)) {
            normalized = Math.floor(toNumber(fallback, 8));
        }
        return Math.max(0, Math.min(23, normalized));
    }

    function notificationsPreferenceSnapshotFromInputs() {
        var base = notificationsState.preferences && typeof notificationsState.preferences === "object"
            ? notificationsState.preferences
            : {};
        return {
            navidAssignmentAlerts: notificationsNavidAlertsToggle
                ? !!notificationsNavidAlertsToggle.checked
                : !!base.navidAssignmentAlerts,
            formReminders: notificationsFormRemindersToggle
                ? !!notificationsFormRemindersToggle.checked
                : !!base.formReminders,
            paymentReminders: notificationsPaymentRemindersToggle
                ? !!notificationsPaymentRemindersToggle.checked
                : !!base.paymentReminders,
            dailyDigestEnabled: notificationsDigestEnabledToggle
                ? !!notificationsDigestEnabledToggle.checked
                : !!base.dailyDigestEnabled,
            dailyDigestHour: notificationsNormalizeDigestHourValue(
                notificationsDigestHourInput ? notificationsDigestHourInput.value : (base.dailyDigestHour || 8),
                base.dailyDigestHour || 8
            )
        };
    }

    function notificationsCurrentPreferenceView() {
        var base = notificationsState.preferences && typeof notificationsState.preferences === "object"
            ? notificationsState.preferences
            : {};
        var draft = notificationsState.draftPreferences && typeof notificationsState.draftPreferences === "object"
            ? notificationsState.draftPreferences
            : null;
        return draft ? Object.assign({}, base, draft) : base;
    }

    function notificationsUpdatePreferenceDraft(shouldRender) {
        notificationsState.draftPreferences = notificationsPreferenceSnapshotFromInputs();
        if (shouldRender) {
            renderNotificationsSurface();
        }
    }

    function notificationsItemIsImportant(item) {
        return !!(item && item.important);
    }

    function notificationsLatestUnreadItemFromItems() {
        return notificationsState.items.find(function (item) {
            return !!(item && item.unread && notificationsItemIsImportant(item));
        }) || notificationsState.items.find(function (item) {
            return !!(item && item.unread);
        }) || null;
    }

    function notificationsLatestUnreadItem() {
        return notificationsLatestUnreadItemFromItems() || (
            notificationsState.preview && notificationsState.preview.unread !== false
                ? notificationsState.preview
                : null
        );
    }

    function notificationsSyncPreview() {
        var previewFromItems = notificationsLatestUnreadItemFromItems();
        if (previewFromItems) {
            notificationsState.preview = previewFromItems;
            return;
        }
        if (!notificationsState.preview || notificationsState.preview.unread === false) {
            notificationsState.preview = null;
        }
    }

    function notificationsApplyResponseMeta(response) {
        if (!response || typeof response !== "object") {
            return;
        }
        notificationsState.summary = response.summary || notificationsState.summary;
        if (response.preferences) {
            notificationsState.preferences = response.preferences;
        }
        if (response.manager) {
            notificationsState.manager = response.manager;
        }
        if (Object.prototype.hasOwnProperty.call(response, "preview")) {
            notificationsState.preview = response.preview && typeof response.preview === "object"
                ? response.preview
                : null;
        }
    }

    function notificationsCompactText(value, fallback, maxLength) {
        var text = String(value || "").replace(/\s+/g, " ").trim();
        if (!text) {
            text = String(fallback || "").trim();
        }
        if (!text || !maxLength || text.length <= maxLength) {
            return text;
        }
        return text.slice(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    function notificationsItemIsManaged(item) {
        var managerMeta = item && item.manager && typeof item.manager === "object" ? item.manager : {};
        return !!(managerMeta.canInspectAudience || managerMeta.canDelete);
    }

    function notificationsMatchesFilter(item, filterKey) {
        var key = String(filterKey || "all");
        if (key === "unread") {
            return !!(item && item.unread && !item.scheduled);
        }
        if (key === "important") {
            return !!(item && item.unread && !item.scheduled && notificationsItemIsImportant(item));
        }
        if (key === "navid") {
            return !!(item && item.kind === "navid-assignment");
        }
        if (key === "scheduled") {
            return !!(item && item.scheduled);
        }
        if (key === "manager") {
            return notificationsItemIsManaged(item);
        }
        return true;
    }

    function notificationsFilterConfigs(items) {
        var list = Array.isArray(items) ? items : [];
        var configs = [
            { key: "all", label: "همه", count: list.length },
            { key: "unread", label: "خوانده‌نشده", count: list.filter(function (item) { return notificationsMatchesFilter(item, "unread"); }).length },
            { key: "important", label: "مهم", count: list.filter(function (item) { return notificationsMatchesFilter(item, "important"); }).length },
            { key: "navid", label: "نوید", count: list.filter(function (item) { return notificationsMatchesFilter(item, "navid"); }).length },
            { key: "manager", label: "مدیریتی", count: list.filter(function (item) { return notificationsMatchesFilter(item, "manager"); }).length },
            { key: "scheduled", label: "زمان‌بندی", count: list.filter(function (item) { return notificationsMatchesFilter(item, "scheduled"); }).length }
        ];

        return configs.filter(function (config) {
            return config.key === "all" || config.key === "important" || config.count > 0;
        });
    }

    function notificationsNormalizeActiveFilter(items) {
        var available = notificationsFilterConfigs(items).map(function (config) {
            return config.key;
        });
        if (available.indexOf(notificationsState.activeFilter) === -1) {
            notificationsState.activeFilter = "all";
        }
        return notificationsState.activeFilter;
    }

    function notificationsFilteredItems(items) {
        var list = Array.isArray(items) ? items : [];
        var filterKey = notificationsNormalizeActiveFilter(list);
        return list.filter(function (item) {
            return notificationsMatchesFilter(item, filterKey);
        });
    }

    function notificationsBodyHtml(item) {
        var deployBody = notificationsDeployBodyHtml(item);
        if (deployBody) {
            return deployBody;
        }

        var text = String(item && item.body || "").trim();
        if (!text) {
            return "";
        }

        var bodyHtml = escapeHtml(text).replace(/\r?\n/g, "<br>");
        var isLong = text.length > 220 || text.indexOf("\n") >= 0;
        if (!isLong) {
            return '<p class="account-notification-item__body">' + bodyHtml + "</p>";
        }

        return [
            '<details class="account-notification-item__body-shell">',
            '  <summary class="account-notification-item__body-preview">' + escapeHtml(notificationsCompactText(text, "", 180)) + "</summary>",
            '  <p class="account-notification-item__body">' + bodyHtml + "</p>",
            "</details>"
        ].join("");
    }

    function notificationsValidateBroadcastPayload(payload) {
        var data = payload && typeof payload === "object" ? payload : {};
        if (!String(data.targetKey || "").trim()) {
            return "مقصد اعلان را انتخاب کن.";
        }
        if (!String(data.title || "").trim() && !String(data.body || "").trim()) {
            return "عنوان یا متن اعلان را وارد کن.";
        }
        if (String(data.ctaLabel || "").trim() && !String(data.ctaHref || "").trim()) {
            return "وقتی متن دکمه را وارد می‌کنی، مسیر آن را هم مشخص کن.";
        }
        var href = String(data.ctaHref || "").trim();
        if (href && (!/^\/(?!\/)/.test(href))) {
            return "مسیر دکمه باید با / شروع شود.";
        }
        var scheduleAt = String(data.scheduleAt || "").trim();
        if (scheduleAt && Number.isNaN(new Date(scheduleAt).getTime())) {
            return "زمان انتشار معتبر نیست.";
        }
        return "";
    }

    function notificationsDeployMeta(item) {
        if (!item || item.source !== "deploy") {
            return null;
        }

        var meta = item.meta && typeof item.meta === "object" ? item.meta : {};
        var body = String(item.body || "");
        var title = String(item.title || "");
        var version = String(meta.version || "").trim();
        var deployedAt = String(meta.deployedAt || item.effectiveAt || item.createdAt || "").trim();
        var branch = String(meta.branch || "").trim();
        var deployHead = String(meta.deployHead || "").trim();

        if (!version) {
            var titleMatch = title.match(/\b(\d{8}-\d{6})\b/);
            if (titleMatch && titleMatch[1]) {
                version = titleMatch[1];
            }
        }
        if (!version) {
            var bodyVersionMatch = body.match(/^نسخه(?: منتشرشده| فعال)?:\s*(.+)$/m);
            if (bodyVersionMatch && bodyVersionMatch[1]) {
                version = bodyVersionMatch[1].trim();
            }
        }
        if (!branch) {
            var branchMatch = body.match(/^شاخه(?: استقرار)?:\s*(.+)$/m);
            if (branchMatch && branchMatch[1]) {
                branch = branchMatch[1].trim();
            }
        }
        if (!deployHead) {
            var headMatch = body.match(/^(?:HEAD|کد استقرار):\s*(.+)$/mi);
            if (headMatch && headMatch[1]) {
                deployHead = headMatch[1].trim();
            }
        }
        if (!deployedAt) {
            var timeMatch = body.match(/^زمان(?: دقیق)?(?: deploy| استقرار)?(?: \(ایران\))?:\s*(.+)$/m);
            if (timeMatch && timeMatch[1]) {
                deployedAt = timeMatch[1].trim();
            }
        }

        return {
            version: version,
            deployedAt: deployedAt,
            branch: branch,
            deployHead: deployHead
        };
    }

    function notificationsDeploySummaryRows(item) {
        var meta = notificationsDeployMeta(item);
        if (!meta) {
            return [];
        }

        var rows = [];
        if (meta.version) {
            rows.push({
                label: "نسخه",
                value: toPersianDigits(meta.version),
                latin: false
            });
        }
        if (meta.deployedAt) {
            rows.push({
                label: "زمان استقرار",
                value: formatJalaliDateTime(meta.deployedAt, "—", true),
                latin: false
            });
        }
        if (meta.branch) {
            rows.push({
                label: "شاخه",
                value: meta.branch,
                latin: true
            });
        }
        if (meta.deployHead) {
            rows.push({
                label: "کد استقرار",
                value: String(meta.deployHead).slice(0, 12),
                latin: true
            });
        }
        return rows;
    }

    function notificationsDeployPreviewText(item) {
        var meta = notificationsDeployMeta(item);
        if (!meta) {
            return "";
        }

        var parts = [];
        if (meta.version) {
            parts.push("نسخه " + toPersianDigits(meta.version));
        }
        if (meta.deployedAt) {
            parts.push("در " + formatJalaliDateTime(meta.deployedAt, "—", true));
        }
        if (!parts.length) {
            return "گزارش استقرار جدید سایت ثبت شد.";
        }
        return parts.join(" ") + " روی سایت منتشر شد.";
    }

    function notificationsDeployBodyHtml(item) {
        var rows = notificationsDeploySummaryRows(item);
        if (!item || item.source !== "deploy" || !rows.length) {
            return "";
        }

        return [
            '<div class="account-notification-item__deploy-summary">',
            '  <p class="account-notification-item__body">استقرار جدید سایت با موفقیت ثبت شد.</p>',
            '  <div class="account-notification-item__deploy-grid">',
            rows.map(function (row) {
                return [
                    '    <div class="account-notification-item__deploy-row">',
                    '      <span class="account-notification-item__deploy-label">' + escapeHtml(String(row.label || "")) + "</span>",
                    '      <strong class="account-notification-item__deploy-value"' + (row.latin ? ' dir="ltr" data-latin-digits="true"' : "") + ">" + escapeHtml(String(row.value || "")) + "</strong>",
                    "    </div>"
                ].join("");
            }).join(""),
            "  </div>",
            "</div>"
        ].join("");
    }

    function notificationsKindLabel(item) {
        if (item && item.kind === "navid-assignment") {
            return "نوید";
        }
        var source = String(item && item.source || "");
        if (source === "forms") {
            return "فرم";
        }
        if (source === "payments") {
            return "پرداخت";
        }
        if (source === "digest") {
            return "خلاصه روزانه";
        }
        if (item && item.source === "deploy") {
            return "استقرار";
        }
        return "اعلان";
    }

    function notificationsItemDisplayAt(item) {
        if (!item || typeof item !== "object") {
            return "—";
        }
        return formatJalaliDateTime(
            item.scheduled ? (item.publishAt || item.effectiveAt || item.createdAt) : (item.effectiveAt || item.createdAt),
            "—"
        );
    }

    function notificationsPrimaryState(item) {
        if (item && item.scheduled) {
            return {
                text: "زمان‌بندی",
                className: " is-scheduled"
            };
        }
        if (item && item.unread) {
            return {
                text: "جدید",
                className: " is-unread"
            };
        }
        return {
            text: "",
            className: ""
        };
    }

    function notificationsSmsStatusLabel(sms) {
        var status = String(sms && sms.status || "");
        if (!sms || !sms.requested) {
            return "";
        }
        if (status === "pending") return "SMS در صف";
        if (status === "sending") return "در حال SMS";
        if (status === "sent") return "SMS ارسال شد";
        if (status === "partial") return "SMS ناقص";
        if (status === "failed") return "SMS ناموفق";
        return "SMS فعال";
    }

    function notificationsAudienceSummaryText(summary) {
        var total = Math.max(0, Math.floor(toNumber(summary && summary.recipientCount, 0)));
        var viewed = Math.max(0, Math.floor(toNumber(summary && summary.viewedCount, 0)));
        if (total <= 0) {
            return "";
        }
        return viewed.toLocaleString("fa-IR") + " از " + total.toLocaleString("fa-IR") + " دیده‌اند";
    }

    function notificationsRowMetaText(item) {
        if (item && item.source === "deploy") {
            return "گزارش خودکار استقرار سایت";
        }

        var parts = [];
        var senderLabel = String(item && item.senderLabel || "").trim();
        var targetLabel = String(item && item.targetLabel || "").trim();
        if (senderLabel) {
            parts.push(senderLabel);
        }
        if (targetLabel && targetLabel !== "همه ورودی‌ها") {
            parts.push(targetLabel);
        }
        return parts.join(" • ");
    }

    function notificationsFooterNoteText(item, audienceText) {
        var parts = [];
        if (item && item.scheduled) {
            parts.push("انتشار: " + notificationsItemDisplayAt(item));
        }
        if (audienceText) {
            parts.push(audienceText);
        }
        return parts.join(" • ");
    }

    function notificationsComposeContainer() {
        if (notificationsComposeShell) {
            return notificationsComposeShell;
        }
        if (notificationsManagerCard && String(notificationsManagerCard.tagName || "").toUpperCase() === "DETAILS") {
            return notificationsManagerCard;
        }
        return null;
    }

    function notificationsSetComposeOpen(nextOpen) {
        var shell = notificationsComposeContainer();
        if (!shell || typeof shell.open !== "boolean") {
            return;
        }
        shell.open = !!nextOpen;
    }

    function notificationsOverviewPillHtml(label, value, tone) {
        return [
            '<span class="account-notifications-overview__pill"' + (tone ? ' data-tone="' + escapeHtml(tone) + '"' : "") + '>',
            '  <strong>' + escapeHtml(String(value || "—")) + "</strong>",
            '  <span>' + escapeHtml(String(label || "")) + "</span>",
            "</span>"
        ].join("");
    }

    function notificationsOverviewHtml(summary, manager) {
        var unreadCount = Math.max(0, Math.floor(toNumber(summary && summary.unreadCount, 0)));
        var importantUnreadCount = Math.max(0, Math.floor(toNumber(summary && summary.importantUnreadCount, 0)));
        var reminderCount = Math.max(0, Math.floor(toNumber(summary && summary.reminderCount, 0)));
        var visibleCount = Math.max(0, Math.floor(toNumber(summary && summary.visibleCount, 0)));
        var navidCount = Math.max(0, Math.floor(toNumber(summary && summary.navidCount, 0)));
        var scheduledCount = Math.max(0, Math.floor(toNumber(summary && summary.scheduledCount, 0)));
        var latestTitle = String(summary && summary.latestTitle || "").trim();
        var pills = [
            notificationsOverviewPillHtml("جدید", unreadCount.toLocaleString("fa-IR"), unreadCount > 0 ? "warn" : "ok"),
            notificationsOverviewPillHtml("مهم", importantUnreadCount.toLocaleString("fa-IR"), importantUnreadCount > 0 ? "danger" : ""),
            notificationsOverviewPillHtml("یادآور", reminderCount.toLocaleString("fa-IR"), reminderCount > 0 ? "warn" : ""),
            notificationsOverviewPillHtml(manager && manager.canBroadcast ? "در فید" : "قابل‌نمایش", visibleCount.toLocaleString("fa-IR"), ""),
            notificationsOverviewPillHtml("نوید", navidCount.toLocaleString("fa-IR"), "")
        ];
        if (manager && manager.canBroadcast) {
            pills.splice(2, 0, notificationsOverviewPillHtml("در صف", scheduledCount.toLocaleString("fa-IR"), scheduledCount > 0 ? "warn" : ""));
        }

        return [
            '<p class="account-notifications-overview__lead">' + escapeHtml(
                latestTitle
                    ? ("آخرین مورد: " + latestTitle)
                    : (importantUnreadCount > 0
                        ? "اعلان‌های مهم خوانده‌نشده و یادآورها از همین‌جا پیگیری می‌شوند."
                        : (unreadCount > 0 ? "اعلان‌های جدید شما اینجا جمع می‌شوند." : "فید اعلان‌ها جمع‌وجور شد و جزئیات هر مورد فقط هنگام نیاز باز می‌شود."))
            ) + "</p>",
            '<div class="account-notifications-overview__pills">' + pills.join("") + "</div>"
        ].join("");
    }

    function notificationsSmsDetailText(sms) {
        if (!sms || !sms.requested) {
            return "برای این اعلان، ارسال پیامک فعال نبود.";
        }

        var eligible = Math.max(0, Math.floor(toNumber(sms.eligibleCount, 0)));
        var sent = Math.max(0, Math.floor(toNumber(sms.sentCount, 0)));
        var skipped = Math.max(0, Math.floor(toNumber(sms.skippedCount, 0)));
        var statusLabel = notificationsSmsStatusLabel(sms) || "SMS";
        var parts = [statusLabel];
        if (eligible > 0) {
            parts.push("دارای شماره تاییدشده: " + eligible.toLocaleString("fa-IR"));
        }
        if (sent > 0) {
            parts.push("ثبت‌شده: " + sent.toLocaleString("fa-IR"));
        }
        if (skipped > 0) {
            parts.push("بدون شماره تاییدشده: " + skipped.toLocaleString("fa-IR"));
        }
        if (sms.lastMessage) {
            parts.push(String(sms.lastMessage));
        }
        return parts.join(" • ");
    }

    function notificationsAudienceEntryHtml(entry, includeReadAt) {
        var meta = [
            "شماره دانشجویی: " + escapeHtml(String(entry && entry.studentNumber || "—")),
            escapeHtml(String(entry && entry.roleLabel || "دانشجو")),
            escapeHtml(String(entry && entry.cohortLabel || "—"))
        ];
        if (entry && entry.hasVerifiedPhone && entry.phoneMasked) {
            meta.push("شماره تاییدشده: " + escapeHtml(String(entry.phoneMasked)));
        } else {
            meta.push("شماره تاییدشده ندارد");
        }
        if (includeReadAt && entry && entry.readAt) {
            meta.unshift("خوانده در " + escapeHtml(formatJalaliDateTime(entry.readAt, "—")));
        }

        return [
            '<li class="account-notification-audience__item">',
            '  <strong>' + escapeHtml(String(entry && entry.name || "کاربر")) + "</strong>",
            '  <span>' + meta.join(" • ") + "</span>",
            "</li>"
        ].join("");
    }

    function notificationsAudienceColumnHtml(title, items, emptyText, includeReadAt) {
        var list = Array.isArray(items) ? items : [];
        return [
            '<section class="account-notification-audience__column">',
            '  <div class="account-notification-audience__column-head">',
            '    <strong>' + escapeHtml(title) + "</strong>",
            '    <span>' + list.length.toLocaleString("fa-IR") + "</span>",
            "  </div>",
            list.length
                ? ('  <ul class="account-notification-audience__list">' + list.map(function (entry) {
                    return notificationsAudienceEntryHtml(entry, includeReadAt);
                }).join("") + "</ul>")
                : ('  <p class="account-notification-audience__empty">' + escapeHtml(emptyText) + "</p>"),
            "</section>"
        ].join("");
    }

    function notificationsAudiencePanelHtml(item) {
        var id = String(item && item.id || "");
        var panelId = String(item && item.audiencePanelId || ("notification-audience-" + id));
        var managerMeta = item && item.manager && typeof item.manager === "object" ? item.manager : {};
        if (!managerMeta.canInspectAudience) {
            return "";
        }

        var open = notificationsState.expandedAudienceId === id;
        var loading = !!notificationsState.audienceLoadingIds[id];
        var payload = notificationsState.audienceById[id] && typeof notificationsState.audienceById[id] === "object"
            ? notificationsState.audienceById[id]
            : null;
        var summary = payload && payload.summary ? payload.summary : (managerMeta.audienceSummary || {});
        var sms = payload && payload.sms ? payload.sms : (item && item.sms ? item.sms : {});
        var viewed = payload && Array.isArray(payload.viewed) ? payload.viewed : [];
        var pending = payload && Array.isArray(payload.pending) ? payload.pending : [];
        var recipientCount = Math.max(0, Math.floor(toNumber(summary && summary.recipientCount, 0)));
        var viewedCount = Math.max(0, Math.floor(toNumber(summary && summary.viewedCount, 0)));
        var pendingCount = Math.max(0, Math.floor(toNumber(summary && summary.pendingCount, 0)));
        var verifiedPhoneCount = Math.max(0, Math.floor(toNumber(summary && summary.verifiedPhoneCount, 0)));

        return [
            '<section id="' + escapeHtml(panelId) + '" class="account-notification-audience"' + (open ? "" : " hidden") + ' data-notification-audience-panel="' + escapeHtml(id) + '">',
            '  <div class="account-notification-audience__stats">',
            '    <div class="account-notification-audience__stat"><strong>' + recipientCount.toLocaleString("fa-IR") + '</strong><span>مخاطب</span></div>',
            '    <div class="account-notification-audience__stat"><strong>' + viewedCount.toLocaleString("fa-IR") + '</strong><span>دیده‌اند</span></div>',
            '    <div class="account-notification-audience__stat"><strong>' + pendingCount.toLocaleString("fa-IR") + '</strong><span>ندیده‌اند</span></div>',
            '    <div class="account-notification-audience__stat"><strong>' + verifiedPhoneCount.toLocaleString("fa-IR") + '</strong><span>شماره تاییدشده</span></div>',
            "  </div>",
            '  <p class="account-notification-audience__sms">' + escapeHtml(notificationsSmsDetailText(sms)) + "</p>",
            loading
                ? '  <p class="account-notification-audience__hint">در حال دریافت وضعیت مشاهده‌کنندگان...</p>'
                : payload
                    ? ('  <div class="account-notification-audience__columns">'
                        + notificationsAudienceColumnHtml("دیده‌اند", viewed, "هنوز کسی این اعلان را نخوانده است.", true)
                        + notificationsAudienceColumnHtml("ندیده‌اند", pending, "همه مخاطبان این اعلان را دیده‌اند.", false)
                        + "</div>")
                    : '  <p class="account-notification-audience__hint">برای دریافت لیست کامل، دکمه وضعیت مشاهده را باز کن.</p>',
            "</section>"
        ].join("");
    }

    function notificationsHubMetaText() {
        var summary = notificationsState.summary || {};
        var manager = notificationsState.manager || {};
        var unreadCount = Math.max(0, Math.floor(toNumber(summary.unreadCount, 0)));
        var importantUnreadCount = Math.max(0, Math.floor(toNumber(summary.importantUnreadCount, 0)));
        var scheduledCount = Math.max(0, Math.floor(toNumber(summary.scheduledCount, 0)));
        if (notificationsState.loading) {
            return "در حال دریافت اعلان‌ها...";
        }
        if (unreadCount > 0) {
            var latestTitle = String(summary.latestTitle || "").trim();
            var meta = unreadCount.toLocaleString("fa-IR") + " اعلان جدید";
            if (importantUnreadCount > 0) {
                meta += " • " + importantUnreadCount.toLocaleString("fa-IR") + " مورد مهم";
            }
            return meta + (latestTitle ? (" • آخرین مورد: " + latestTitle) : "");
        }

        if (notificationsState.loadedForUserKey) {
            if (manager.canBroadcast && scheduledCount > 0) {
                return scheduledCount.toLocaleString("fa-IR") + " اعلان زمان‌بندی‌شده در صف انتشار است.";
            }
            return "فعلاً اعلان خوانده‌نشده‌ای برای این حساب ثبت نشده است.";
        }

        return "آخرین اعلان‌های این حساب در همین بخش نمایش داده می‌شوند.";
    }

    function renderNotificationsHub() {
        if (accountRowNotificationsMeta) {
            accountRowNotificationsMeta.textContent = notificationsHubMetaText();
        }

        if (!accountNavidAlertCard) {
            return;
        }

        var preview = notificationsLatestUnreadItem();
        accountNavidAlertCard.hidden = !preview;
        if (!preview) {
            return;
        }

        if (accountNotificationAlertKind) {
            accountNotificationAlertKind.textContent = notificationsKindLabel(preview);
        }
        if (accountNavidAlertTime) {
            accountNavidAlertTime.textContent = formatJalaliDateTime(preview.effectiveAt || preview.createdAt, "—");
        }
        if (accountNavidAlertTitle) {
            accountNavidAlertTitle.textContent = preview.title || (preview.kind === "navid-assignment" ? "تکلیف جدید نوید" : "اعلان جدید");
        }
        if (accountNavidAlertBody) {
            accountNavidAlertBody.textContent = preview.source === "deploy"
                ? (notificationsDeployPreviewText(preview) || "گزارش استقرار جدید سایت ثبت شد.")
                : (preview.body || (preview.kind === "navid-assignment"
                ? "برای دیدن جزئیات، بخش تکالیف نوید را باز کن."
                : "برای دیدن جزئیات، اعلان را باز کن."));
        }
        if (accountNavidAlertLink) {
            accountNavidAlertLink.href = String(preview.ctaHref || "/account/#notifications");
            accountNavidAlertLink.dataset.notificationId = String(preview.id || "");
            accountNavidAlertLink.textContent = String(preview.ctaLabel || (preview.kind === "navid-assignment" ? "مشاهده تکالیف" : "مشاهده اعلان"));
        }
        if (accountNavidAlertMarkRead) {
            var previewId = String(preview.id || "").trim();
            var isMarking = !!notificationsState.markingIds[previewId];
            accountNavidAlertMarkRead.hidden = !previewId || preview.unread === false;
            accountNavidAlertMarkRead.disabled = isMarking;
            accountNavidAlertMarkRead.dataset.notificationMark = previewId;
            accountNavidAlertMarkRead.textContent = isMarking ? "در حال ثبت..." : "علامت زده به عنوان خوانده شده";
        }
    }

    function renderNotificationsSurface() {
        var summary = notificationsState.summary || {};
        var storedPreferences = notificationsState.preferences || {};
        var preferences = notificationsCurrentPreferenceView();
        var manager = notificationsState.manager || {};
        var items = Array.isArray(notificationsState.items) ? notificationsState.items : [];
        var filterConfigs = notificationsFilterConfigs(items);
        var filteredItems = notificationsFilteredItems(items);
        var activeFilter = notificationsState.activeFilter;

        if (notificationsSummary) {
            notificationsSummary.innerHTML = notificationsOverviewHtml(summary, manager);
        }

        if (notificationsPrefsCard) {
            var canToggle = !!storedPreferences.canToggleNavidAssignmentAlerts;
            var digestEnabled = !!preferences.dailyDigestEnabled;
            var digestHour = notificationsNormalizeDigestHourValue(preferences.dailyDigestHour, 8);
            notificationsPrefsCard.hidden = false;
            if (notificationsNavidRow) {
                notificationsNavidRow.hidden = !canToggle;
            }
            if (notificationsNavidAlertsToggle) {
                notificationsNavidAlertsToggle.checked = !!preferences.navidAssignmentAlerts;
                notificationsNavidAlertsToggle.disabled = notificationsState.savingPrefs || !canToggle;
            }
            if (notificationsFormRemindersToggle) {
                notificationsFormRemindersToggle.checked = !!preferences.formReminders;
                notificationsFormRemindersToggle.disabled = notificationsState.savingPrefs;
            }
            if (notificationsPaymentRemindersToggle) {
                notificationsPaymentRemindersToggle.checked = !!preferences.paymentReminders;
                notificationsPaymentRemindersToggle.disabled = notificationsState.savingPrefs;
            }
            if (notificationsDigestEnabledToggle) {
                notificationsDigestEnabledToggle.checked = digestEnabled;
                notificationsDigestEnabledToggle.disabled = notificationsState.savingPrefs;
            }
            if (notificationsDigestHourInput) {
                notificationsDigestHourInput.value = String(digestHour);
                notificationsDigestHourInput.disabled = notificationsState.savingPrefs || !digestEnabled;
            }
            if (notificationsPrefsSaveButton) {
                notificationsPrefsSaveButton.disabled = notificationsState.savingPrefs;
            }
            if (notificationsPrefsHint) {
                notificationsPrefsHint.textContent = canToggle
                    ? "وقتی تکلیف جدیدی در نوید بیاید، در حساب کاربری به شما اطلاع داده می‌شود."
                    : "";
            }
        }

        if (notificationsManagerCard) {
            var canBroadcast = !!manager.canBroadcast;
            notificationsManagerCard.hidden = !canBroadcast;
            if (notificationsTargetSelect && canBroadcast) {
                var currentTarget = String(notificationsTargetSelect.value || "");
                var options = Array.isArray(manager.targets) ? manager.targets : [];
                notificationsTargetSelect.innerHTML = options.map(function (target) {
                    var key = String(target && target.key || "");
                    return '<option value="' + escapeHtml(key) + '">' + escapeHtml(String(target && target.label || key || "مقصد")) + "</option>";
                }).join("");
                notificationsTargetSelect.value = options.some(function (target) {
                    return String(target && target.key || "") === currentTarget;
                }) ? currentTarget : String(manager.defaultTargetKey || (options[0] && options[0].key) || "");
                notificationsTargetSelect.disabled = notificationsState.broadcasting;
            }
            if (notificationsBroadcastSubmit) {
                notificationsBroadcastSubmit.disabled = notificationsState.broadcasting;
            }
            var composeContainer = notificationsComposeContainer();
            if (composeContainer) {
                composeContainer.classList.toggle("is-busy", notificationsState.broadcasting);
            }
            if (notificationsTitleInput) notificationsTitleInput.disabled = notificationsState.broadcasting;
            if (notificationsBodyInput) notificationsBodyInput.disabled = notificationsState.broadcasting;
            if (notificationsCtaLabelInput) notificationsCtaLabelInput.disabled = notificationsState.broadcasting;
            if (notificationsCtaHrefInput) notificationsCtaHrefInput.disabled = notificationsState.broadcasting;
            if (notificationsScheduleInput) notificationsScheduleInput.disabled = notificationsState.broadcasting;
            if (notificationsSendSmsInput) notificationsSendSmsInput.disabled = notificationsState.broadcasting;
        }

        if (notificationsRefreshButton) {
            notificationsRefreshButton.disabled = notificationsState.loading;
        }
        if (notificationsMarkAllButton) {
            notificationsMarkAllButton.disabled = notificationsState.markingAll || Math.max(0, Math.floor(toNumber(summary.unreadCount, 0))) <= 0;
        }
        if (notificationsFilters) {
            notificationsFilters.hidden = filterConfigs.length <= 1;
            notificationsFilters.innerHTML = filterConfigs.map(function (config) {
                var key = String(config && config.key || "all");
                return '<button class="account-notifications-filter' + (key === activeFilter ? " is-active" : "") + '" type="button" data-notification-filter="' + escapeHtml(key) + '">' + escapeHtml(String(config && config.label || key)) + ' <span>(' + Math.max(0, Math.floor(toNumber(config && config.count, 0))).toLocaleString("fa-IR") + ")</span></button>";
            }).join("");
        }
        if (notificationsEmpty) {
            notificationsEmpty.hidden = filteredItems.length > 0 || notificationsState.loading;
            notificationsEmpty.textContent = activeFilter === "all"
                ? "اعلانی برای این حساب پیدا نشد."
                : (activeFilter === "important"
                    ? "اعلان مهم خوانده‌نشده‌ای پیدا نشد."
                    : "برای این فیلتر، اعلانی پیدا نشد.");
        }
        if (!notificationsList) {
            return;
        }

        notificationsList.innerHTML = filteredItems.map(function (item) {
            var id = String(item && item.id || "");
            var displayAt = notificationsItemDisplayAt(item);
            var unread = !!(item && item.unread);
            var marking = !!notificationsState.markingIds[id];
            var snoozing = !!notificationsState.snoozingIds[id];
            var ctaHref = String(item && item.ctaHref || "");
            var ctaLabel = String(item && item.ctaLabel || "مشاهده");
            var deleting = notificationsState.deletingId === id;
            var managerMeta = item && item.manager && typeof item.manager === "object" ? item.manager : {};
            var audienceSummary = managerMeta.audienceSummary || {};
            var stateBadge = notificationsPrimaryState(item);
            var sms = item && item.sms ? item.sms : {};
            var smsLabel = notificationsSmsStatusLabel(sms);
            var important = notificationsItemIsImportant(item);
            var canSnooze = !!(item && item.canSnooze && unread && !item.scheduled);
            var canInspect = !!managerMeta.canInspectAudience;
            var canDelete = !!managerMeta.canDelete;
            var audienceOpen = notificationsState.expandedAudienceId === id;
            var audienceLoading = !!notificationsState.audienceLoadingIds[id];
            var audiencePanelId = "notification-audience-" + id;
            var audienceBadgeText = notificationsAudienceSummaryText(audienceSummary);
            var metaText = notificationsRowMetaText(item);
            var footerNote = notificationsFooterNoteText(item, audienceBadgeText);
            var signals = [];
            var actions = [];

            if (stateBadge.text) {
                signals.push('<span class="account-notification-item__badge' + stateBadge.className + '">' + escapeHtml(stateBadge.text) + "</span>");
            }
            if (smsLabel) {
                signals.push('<span class="account-notification-item__badge">' + escapeHtml(smsLabel) + "</span>");
            }
            if (important) {
                signals.push('<span class="account-notification-item__badge is-important">مهم</span>');
            }

            if (ctaHref) {
                actions.push('<a class="shell-action-btn shell-action-btn-primary" href="' + escapeHtml(ctaHref) + '" data-notification-cta="true" data-notification-id="' + escapeHtml(id) + '">' + escapeHtml(ctaLabel) + "</a>");
            }
            if (!item.scheduled && unread) {
                actions.push('<button class="shell-action-btn" type="button" data-notification-mark="' + escapeHtml(id) + '"' + (marking ? " disabled" : "") + ">" + (marking ? "در حال ثبت..." : "خواندم") + "</button>");
            }
            if (canSnooze) {
                actions.push('<button class="shell-action-btn" type="button" data-notification-snooze="24" data-notification-id="' + escapeHtml(id) + '"' + (snoozing ? " disabled" : "") + ">" + (snoozing ? "در حال تعویق..." : "یادآوری فردا") + "</button>");
            }
            if (canInspect) {
                actions.push('<button class="shell-action-btn" type="button" data-notification-audience-toggle="' + escapeHtml(id) + '" aria-expanded="' + (audienceOpen ? "true" : "false") + '" aria-controls="' + escapeHtml(audiencePanelId) + '"' + (audienceLoading ? " disabled" : "") + ">" + (audienceOpen ? "بستن مخاطب‌ها" : "مخاطب‌ها") + "</button>");
            }
            if (canDelete) {
                actions.push('<button class="shell-action-btn shell-action-btn-danger" type="button" data-notification-delete="' + escapeHtml(id) + '"' + (deleting ? " disabled" : "") + ">" + (deleting ? "در حال حذف..." : "حذف") + "</button>");
            }

            return [
                '<article class="account-notification-item' + (unread ? " is-unread" : "") + (item && item.scheduled ? " is-scheduled" : "") + '" data-tone="' + escapeHtml(String(item && item.tone || "accent")) + '" data-notification-id="' + escapeHtml(id) + '">',
                '  <div class="account-notification-item__topline">',
                '    <span class="account-notification-item__eyebrow">' + escapeHtml(notificationsKindLabel(item)) + "</span>",
                signals.length
                    ? ('    <div class="account-notification-item__signals">' + signals.join("") + "</div>")
                    : "",
                "  </div>",
                '  <div class="account-notification-item__head">',
                '    <div class="account-notification-item__copy">',
                '      <h4 class="account-notification-item__title">' + escapeHtml(String(item && item.title || "بدون عنوان")) + "</h4>",
                metaText
                    ? ('      <p class="account-notification-item__meta-line">' + escapeHtml(metaText) + "</p>")
                    : "",
                "    </div>",
                '    <span class="account-notification-item__time">' + escapeHtml(displayAt) + "</span>",
                "  </div>",
                notificationsBodyHtml(item),
                footerNote
                    ? ('  <p class="account-notification-item__note">' + escapeHtml(footerNote) + "</p>")
                    : "",
                actions.length
                    ? ('  <div class="account-notification-item__actions">' + actions.join("") + "</div>")
                    : "",
                notificationsAudiencePanelHtml(Object.assign({}, item, { audiencePanelId: audiencePanelId })),
                "</article>"
            ].join("");
        }).join("");
    }

    function renderNotificationsUi() {
        renderNotificationsHub();
        renderNotificationsSurface();
    }

    function applyNotificationsPayload(payload) {
        var data = payload && typeof payload === "object" ? payload : {};
        notificationsState.summary = data.summary && typeof data.summary === "object" ? data.summary : null;
        notificationsState.preview = data.preview && typeof data.preview === "object" ? data.preview : null;
        notificationsState.preferences = data.preferences && typeof data.preferences === "object" ? data.preferences : null;
        notificationsState.draftPreferences = null;
        notificationsState.manager = data.manager && typeof data.manager === "object" ? data.manager : null;
        notificationsState.items = Array.isArray(data.items) ? data.items : [];
        var validIds = {};
        notificationsState.items.forEach(function (item) {
            var id = String(item && item.id || "").trim();
            if (id) {
                validIds[id] = true;
            }
        });
        Object.keys(notificationsState.audienceById).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.audienceById[id];
            }
        });
        Object.keys(notificationsState.audienceLoadingIds).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.audienceLoadingIds[id];
            }
        });
        Object.keys(notificationsState.markingIds).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.markingIds[id];
            }
        });
        Object.keys(notificationsState.snoozingIds).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.snoozingIds[id];
            }
        });
        if (notificationsState.expandedAudienceId && !notificationsState.items.some(function (item) {
            return String(item && item.id || "") === notificationsState.expandedAudienceId;
        })) {
            notificationsState.expandedAudienceId = "";
        }
        notificationsSyncPreview();
        renderNotificationsUi();
        notificationsDispatchSummary(notificationsState.summary);
    }

    function loadNotifications(force) {
        var userKey = notificationsUserKey();
        if (!userKey) {
            notificationsResetState();
            renderNotificationsUi();
            return Promise.resolve(null);
        }

        if (notificationsState.loading && !force) {
            return Promise.resolve(null);
        }
        if (!force && notificationsState.loadedForUserKey === userKey && notificationsState.items.length) {
            renderNotificationsUi();
            return Promise.resolve(null);
        }

        notificationsState.loading = true;
        notificationsState.loadedForUserKey = userKey;
        var requestToken = ++notificationsState.requestToken;
        setInlineFeedback(notificationsFeedback, "در حال دریافت اعلان‌ها...", "", true);
        renderNotificationsUi();
        return notificationsGet("list", { limit: 60 }).then(function (response) {
            if (requestToken !== notificationsState.requestToken) {
                return null;
            }
            notificationsState.loading = false;
            if (consumeUnauthorized(response, "نشست شما برای خواندن اعلان‌ها منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true || !response.data) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "خواندن اعلان‌ها انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            setInlineFeedback(notificationsFeedback, "", "");
            applyNotificationsPayload(response.data);
            return response.data;
        }).catch(function () {
            if (requestToken !== notificationsState.requestToken) {
                return null;
            }
            notificationsState.loading = false;
            setInlineFeedback(notificationsFeedback, "اتصال برای دریافت اعلان‌ها برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function updateNotificationsReadState(ids, unread) {
        var idSet = {};
        (Array.isArray(ids) ? ids : []).forEach(function (id) {
            var key = String(id || "").trim();
            if (key) {
                idSet[key] = true;
            }
        });

        notificationsState.items = notificationsState.items.map(function (item) {
            var itemId = String(item && item.id || "");
            if (!idSet[itemId]) {
                return item;
            }
            return Object.assign({}, item, { unread: !!unread });
        });
        notificationsSyncPreview();
    }

    function markNotificationsRead(ids) {
        var cleanIds = (Array.isArray(ids) ? ids : []).map(function (id) {
            return String(id || "").trim();
        }).filter(Boolean);
        if (!cleanIds.length) {
            return Promise.resolve(null);
        }

        cleanIds.forEach(function (id) {
            notificationsState.markingIds[id] = true;
        });
        renderNotificationsUi();

        return notificationsPost("markRead", { idsJson: JSON.stringify(cleanIds) }).then(function (response) {
            cleanIds.forEach(function (id) {
                delete notificationsState.markingIds[id];
            });
            if (consumeUnauthorized(response, "نشست شما برای ثبت خواندن اعلان‌ها منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "ثبت خواندن اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            updateNotificationsReadState(cleanIds, false);
            setInlineFeedback(notificationsFeedback, "", "");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            cleanIds.forEach(function (id) {
                delete notificationsState.markingIds[id];
            });
            setInlineFeedback(notificationsFeedback, "اتصال برای ثبت خواندن اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function markAllNotificationsRead() {
        if (notificationsState.markingAll) {
            return Promise.resolve(null);
        }

        notificationsState.markingAll = true;
        setInlineFeedback(notificationsFeedback, "در حال ثبت خواندن همه اعلان‌ها...", "", true);
        renderNotificationsUi();
        return notificationsPost("markAllRead", {}).then(function (response) {
            notificationsState.markingAll = false;
            if (consumeUnauthorized(response, "نشست شما برای ثبت خواندن اعلان‌ها منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "ثبت خواندن همه اعلان‌ها انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            notificationsState.items = notificationsState.items.map(function (item) {
                return Object.assign({}, item, { unread: false });
            });
            notificationsSyncPreview();
            setInlineFeedback(notificationsFeedback, "همه اعلان‌ها خوانده‌شده ثبت شدند.", "success");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            notificationsState.markingAll = false;
            setInlineFeedback(notificationsFeedback, "اتصال برای ثبت خواندن همه اعلان‌ها برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function saveNotificationsPreferences() {
        if (notificationsState.savingPrefs) {
            return Promise.resolve(null);
        }

        var draftPreferences = notificationsPreferenceSnapshotFromInputs();
        notificationsState.draftPreferences = draftPreferences;
        notificationsState.savingPrefs = true;
        setInlineFeedback(notificationsFeedback, "در حال ذخیره تنظیمات اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("savePrefs", {
            navidAssignmentAlerts: draftPreferences.navidAssignmentAlerts ? "1" : "0",
            formReminders: draftPreferences.formReminders ? "1" : "0",
            paymentReminders: draftPreferences.paymentReminders ? "1" : "0",
            dailyDigestEnabled: draftPreferences.dailyDigestEnabled ? "1" : "0",
            dailyDigestHour: String(draftPreferences.dailyDigestHour)
        }).then(function (response) {
            notificationsState.savingPrefs = false;
            if (consumeUnauthorized(response, "نشست شما برای ذخیره تنظیمات اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "ذخیره تنظیمات اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            setInlineFeedback(notificationsFeedback, "تنظیمات اعلان ذخیره شد.", "success");
            loadNotifications(true);
            return response;
        }).catch(function () {
            notificationsState.savingPrefs = false;
            setInlineFeedback(notificationsFeedback, "اتصال برای ذخیره تنظیمات اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function snoozeNotification(id, hours) {
        var notificationId = String(id || "").trim();
        var snoozeHours = Math.max(1, Math.floor(toNumber(hours, 24)));
        if (!notificationId || notificationsState.snoozingIds[notificationId]) {
            return Promise.resolve(null);
        }

        notificationsState.snoozingIds[notificationId] = true;
        setInlineFeedback(notificationsFeedback, "در حال ثبت تعویق اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("snooze", {
            id: notificationId,
            hours: String(snoozeHours)
        }).then(function (response) {
            delete notificationsState.snoozingIds[notificationId];
            if (consumeUnauthorized(response, "نشست شما برای تعویق اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "تعویق اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            setInlineFeedback(notificationsFeedback, response.message || "اعلان برای بعداً کنار گذاشته شد.", "success");
            loadNotifications(true);
            return response;
        }).catch(function () {
            delete notificationsState.snoozingIds[notificationId];
            setInlineFeedback(notificationsFeedback, "اتصال برای تعویق اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function loadNotificationAudience(id, force) {
        var notificationId = String(id || "").trim();
        if (!notificationId) {
            return Promise.resolve(null);
        }
        if (notificationsState.audienceLoadingIds[notificationId]) {
            return Promise.resolve(null);
        }
        if (!force && notificationsState.audienceById[notificationId]) {
            notificationsState.expandedAudienceId = notificationId;
            renderNotificationsUi();
            return Promise.resolve(notificationsState.audienceById[notificationId]);
        }

        notificationsState.expandedAudienceId = notificationId;
        notificationsState.audienceLoadingIds[notificationId] = true;
        renderNotificationsUi();
        return notificationsGet("audience", { id: notificationId }).then(function (response) {
            delete notificationsState.audienceLoadingIds[notificationId];
            if (consumeUnauthorized(response, "نشست شما برای خواندن وضعیت اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true || !response.data) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "خواندن وضعیت این اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsState.audienceById[notificationId] = response.data;
            notificationsState.items = notificationsState.items.map(function (item) {
                if (String(item && item.id || "") !== notificationId) {
                    return item;
                }
                var nextManager = Object.assign({}, item && item.manager || {});
                if (response.data.summary) {
                    nextManager.audienceSummary = response.data.summary;
                }
                return Object.assign({}, item, { manager: nextManager });
            });
            setInlineFeedback(notificationsFeedback, "", "");
            renderNotificationsUi();
            return response.data;
        }).catch(function () {
            delete notificationsState.audienceLoadingIds[notificationId];
            setInlineFeedback(notificationsFeedback, "اتصال برای خواندن وضعیت مشاهده‌کنندگان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function toggleNotificationAudience(id) {
        var notificationId = String(id || "").trim();
        if (!notificationId) {
            return;
        }
        if (notificationsState.expandedAudienceId === notificationId) {
            notificationsState.expandedAudienceId = "";
            renderNotificationsUi();
            return;
        }
        loadNotificationAudience(notificationId, false);
    }

    function deleteNotification(id) {
        var notificationId = String(id || "").trim();
        if (!notificationId || notificationsState.deletingId === notificationId) {
            return Promise.resolve(null);
        }

        var item = notificationsState.items.find(function (candidate) {
            return String(candidate && candidate.id || "") === notificationId;
        }) || null;
        var title = String(item && item.title || "این اعلان");
        if (!window.confirm("اعلان \"" + title + "\" حذف شود؟ این کار برای همه مخاطبان همان اعلان اعمال می‌شود.")) {
            return Promise.resolve(null);
        }

        notificationsState.deletingId = notificationId;
        setInlineFeedback(notificationsFeedback, "در حال حذف اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("delete", { id: notificationId }).then(function (response) {
            notificationsState.deletingId = "";
            if (consumeUnauthorized(response, "نشست شما برای حذف اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "حذف اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            var deletedId = String(response.deletedId || notificationId);
            notificationsState.items = notificationsState.items.filter(function (entry) {
                return String(entry && entry.id || "") !== deletedId;
            });
            delete notificationsState.audienceById[deletedId];
            delete notificationsState.audienceLoadingIds[deletedId];
            if (notificationsState.expandedAudienceId === deletedId) {
                notificationsState.expandedAudienceId = "";
            }
            notificationsApplyResponseMeta(response);
            notificationsSyncPreview();
            setInlineFeedback(notificationsFeedback, response.message || "اعلان حذف شد.", "success");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            notificationsState.deletingId = "";
            setInlineFeedback(notificationsFeedback, "اتصال برای حذف اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function submitNotificationsBroadcast() {
        if (!notificationsBroadcastForm || notificationsState.broadcasting) {
            return Promise.resolve(null);
        }

        var payload = {
            targetKey: (notificationsTargetSelect && notificationsTargetSelect.value.trim()) || "",
            title: (notificationsTitleInput && notificationsTitleInput.value.trim()) || "",
            body: (notificationsBodyInput && notificationsBodyInput.value.trim()) || "",
            ctaLabel: (notificationsCtaLabelInput && notificationsCtaLabelInput.value.trim()) || "",
            ctaHref: (notificationsCtaHrefInput && notificationsCtaHrefInput.value.trim()) || "",
            scheduleAt: (notificationsScheduleInput && notificationsScheduleInput.value.trim()) || "",
            sendSms: notificationsSendSmsInput && notificationsSendSmsInput.checked ? "1" : "0"
        };
        var validationError = notificationsValidateBroadcastPayload(payload);
        if (validationError) {
            notificationsSetComposeOpen(true);
            setFeedback(notificationsManagerFeedback, validationError, "error");
            return Promise.resolve(null);
        }

        notificationsState.broadcasting = true;
        notificationsSetComposeOpen(true);
        setFeedback(notificationsManagerFeedback, payload.scheduleAt ? "در حال زمان‌بندی اعلان..." : "در حال ارسال اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("broadcast", payload).then(function (response) {
            notificationsState.broadcasting = false;
            if (consumeUnauthorized(response, "نشست شما برای ارسال اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setFeedback(notificationsManagerFeedback, (response && response.error) || "ارسال اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            if (response.notification) {
                notificationsState.items = [response.notification].concat(notificationsState.items.filter(function (item) {
                    return String(item && item.id || "") !== String(response.notification.id || "");
                })).slice(0, 60);
                notificationsState.activeFilter = response.notification.scheduled ? "scheduled" : "all";
            }
            notificationsSyncPreview();
            if (notificationsTitleInput) notificationsTitleInput.value = "";
            if (notificationsBodyInput) notificationsBodyInput.value = "";
            if (notificationsCtaLabelInput) notificationsCtaLabelInput.value = "";
            if (notificationsCtaHrefInput) notificationsCtaHrefInput.value = "";
            if (notificationsScheduleInput) notificationsScheduleInput.value = "";
            if (notificationsSendSmsInput) notificationsSendSmsInput.checked = false;
            notificationsSetComposeOpen(false);
            setFeedback(notificationsManagerFeedback, "", "");
            setInlineFeedback(notificationsFeedback, response.message || "اعلان ثبت شد.", "success");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            notificationsState.broadcasting = false;
            notificationsSetComposeOpen(true);
            setFeedback(notificationsManagerFeedback, "اتصال برای ارسال اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function handleNotificationCtaNavigation(event, notificationId, href) {
        var targetHref = String(href || "").trim();
        if (!targetHref) {
            return;
        }
        if (event && event.preventDefault) {
            event.preventDefault();
        }
        markNotificationsRead([notificationId]).finally(function () {
            window.location.href = targetHref;
        });
    }

        return {
            notificationsUserKey: notificationsUserKey,
            notificationsGet: notificationsGet,
            notificationsPost: notificationsPost,
            notificationsResetState: notificationsResetState,
            notificationsDispatchSummary: notificationsDispatchSummary,
            notificationsNormalizeDigestHourValue: notificationsNormalizeDigestHourValue,
            notificationsPreferenceSnapshotFromInputs: notificationsPreferenceSnapshotFromInputs,
            notificationsCurrentPreferenceView: notificationsCurrentPreferenceView,
            notificationsUpdatePreferenceDraft: notificationsUpdatePreferenceDraft,
            notificationsItemIsImportant: notificationsItemIsImportant,
            notificationsLatestUnreadItemFromItems: notificationsLatestUnreadItemFromItems,
            notificationsLatestUnreadItem: notificationsLatestUnreadItem,
            notificationsSyncPreview: notificationsSyncPreview,
            notificationsApplyResponseMeta: notificationsApplyResponseMeta,
            notificationsCompactText: notificationsCompactText,
            notificationsItemIsManaged: notificationsItemIsManaged,
            notificationsMatchesFilter: notificationsMatchesFilter,
            notificationsFilterConfigs: notificationsFilterConfigs,
            notificationsNormalizeActiveFilter: notificationsNormalizeActiveFilter,
            notificationsFilteredItems: notificationsFilteredItems,
            notificationsBodyHtml: notificationsBodyHtml,
            notificationsValidateBroadcastPayload: notificationsValidateBroadcastPayload,
            notificationsDeployMeta: notificationsDeployMeta,
            notificationsDeploySummaryRows: notificationsDeploySummaryRows,
            notificationsDeployPreviewText: notificationsDeployPreviewText,
            notificationsDeployBodyHtml: notificationsDeployBodyHtml,
            notificationsKindLabel: notificationsKindLabel,
            notificationsItemDisplayAt: notificationsItemDisplayAt,
            notificationsPrimaryState: notificationsPrimaryState,
            notificationsSmsStatusLabel: notificationsSmsStatusLabel,
            notificationsAudienceSummaryText: notificationsAudienceSummaryText,
            notificationsRowMetaText: notificationsRowMetaText,
            notificationsFooterNoteText: notificationsFooterNoteText,
            notificationsComposeContainer: notificationsComposeContainer,
            notificationsSetComposeOpen: notificationsSetComposeOpen,
            notificationsOverviewPillHtml: notificationsOverviewPillHtml,
            notificationsOverviewHtml: notificationsOverviewHtml,
            notificationsSmsDetailText: notificationsSmsDetailText,
            notificationsAudienceEntryHtml: notificationsAudienceEntryHtml,
            notificationsAudienceColumnHtml: notificationsAudienceColumnHtml,
            notificationsAudiencePanelHtml: notificationsAudiencePanelHtml,
            notificationsHubMetaText: notificationsHubMetaText,
            renderNotificationsHub: renderNotificationsHub,
            renderNotificationsSurface: renderNotificationsSurface,
            renderNotificationsUi: renderNotificationsUi,
            applyNotificationsPayload: applyNotificationsPayload,
            loadNotifications: loadNotifications,
            updateNotificationsReadState: updateNotificationsReadState,
            markNotificationsRead: markNotificationsRead,
            markAllNotificationsRead: markAllNotificationsRead,
            saveNotificationsPreferences: saveNotificationsPreferences,
            snoozeNotification: snoozeNotification,
            loadNotificationAudience: loadNotificationAudience,
            toggleNotificationAudience: toggleNotificationAudience,
            deleteNotification: deleteNotification,
            submitNotificationsBroadcast: submitNotificationsBroadcast,
            handleNotificationCtaNavigation: handleNotificationCtaNavigation
        };
    }

    window.Dent1402AccountNotifications = { create: create };
})();
