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

    var countAssignments = $("navid-count-assignments");
    var countUpdates = $("navid-count-updates");
    var lastCheck = $("navid-last-check");
    var sessionState = $("navid-session-state");

    var ownerBox = $("navid-owner-box");
    var ownerCards = $("navid-owner-status-cards");

    var updatesList = $("navid-updates-list");
    var assignmentsList = $("navid-assignments-list");

    var currentUser = null;
    var currentUserKey = "";
    var loadingFeed = false;
    var feedTicket = 0;

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
            // Ignore stale auth surface mismatch and continue fallback.
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
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case "\"":
                    return "&quot;";
                default:
                    return char;
            }
        });
    }

    function snippet(value, maxLength) {
        var clean = String(value || "").replace(/\s+/g, " ").trim();
        if (!clean) {
            return "";
        }
        if (clean.length <= maxLength) {
            return clean;
        }
        return clean.slice(0, maxLength - 1).trim() + "\u2026";
    }

    function formatDate(value, fallback) {
        var raw = String(value || "").trim();
        if (!raw) {
            return fallback || "\u2014";
        }

        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return raw;
        }

        return parsed.toLocaleString("fa-IR", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    function resultLabel(result) {
        switch (result) {
            case "ok":
                return "\u067e\u0627\u06cc\u062f\u0627\u0631";
            case "partial":
                return "\u0646\u0627\u0642\u0635";
            case "running":
                return "\u062f\u0631 \u062d\u0627\u0644 \u0627\u062c\u0631\u0627";
            case "config-updated":
                return "\u062a\u0646\u0638\u06cc\u0645\u0627\u062a \u0628\u0647\u200c\u0631\u0648\u0632 \u0634\u062f";
            case "credentials-missing":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u062b\u0628\u062a \u0646\u0634\u062f\u0647";
            case "credentials-invalid":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0627\u0645\u0639\u062a\u0628\u0631";
            case "reconnect-required":
                return "\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f";
            case "login-failed":
                return "\u062e\u0637\u0627\u06cc \u0648\u0631\u0648\u062f";
            case "dashboard-failed":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u0634\u0628\u0648\u0631\u062f";
            case "exception":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u062e\u0644\u06cc";
            case "skipped":
                return "\u0628\u062f\u0648\u0646 \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "already-running":
                return "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u062f\u0631 \u062d\u0627\u0644 \u0627\u0646\u062c\u0627\u0645";
            case "lock-failed":
                return "\u062e\u0637\u0627\u06cc \u0642\u0641\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0646\u0627\u0645\u0634\u062e\u0635";
        }
    }

    function actionRequiredLabel(action) {
        switch (String(action || "")) {
            case "save-credentials":
                return "\u062b\u0628\u062a \u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0648\u06cc\u062f";
            case "update-credentials":
                return "\u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0648\u06cc\u062f";
            case "manual-reconnect":
                return "\u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0628\u0627 \u06a9\u067e\u0686\u0627";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0647\u06cc\u0686";
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

    function renderOwnerStatus(ownerStatus) {
        var isOwner = !!(currentUser && currentUser.isOwner);
        if (!ownerBox || !ownerCards) {
            return;
        }

        if (!isOwner || !ownerStatus || typeof ownerStatus !== "object") {
            ownerBox.hidden = true;
            ownerCards.innerHTML = "";
            return;
        }

        ownerBox.hidden = false;
        var state = ownerStatus.state || {};
        var config = ownerStatus.config || {};
        var session = ownerStatus.session || {};
        var actionRequired = state.actionRequired || "none";
        var snapshotCounts = ownerStatus.snapshotCounts || {};
        var failedCourses = Math.max(0, Math.floor(Number(state.lastFailedCourses != null ? state.lastFailedCourses : snapshotCounts.failedCourses) || 0));

        var cards = [
            {
                label: "\u0646\u062a\u06cc\u062c\u0647 \u0622\u062e\u0631",
                value: resultLabel(state.lastResult || "")
            },
            {
                label: "\u0622\u062e\u0631\u06cc\u0646 \u0645\u0648\u0641\u0642",
                value: formatDate(state.lastSuccessAt, "\u2014")
            },
            {
                label: "\u062e\u0637\u0627\u06cc \u0627\u062e\u06cc\u0631",
                value: state.lastError ? snippet(state.lastError, 90) : "\u0628\u062f\u0648\u0646 \u062e\u0637\u0627"
            },
            {
                label: "\u0648\u0636\u0639\u06cc\u062a \u0646\u0634\u0633\u062a",
                value: session.status || "\u2014"
            },
            {
                label: "\u0627\u0642\u062f\u0627\u0645 \u0644\u0627\u0632\u0645",
                value: actionRequiredLabel(actionRequired)
            },
            {
                label: "\u062f\u0631\u0648\u0633 \u0646\u0627\u0645\u0648\u0641\u0642",
                value: failedCourses.toLocaleString("fa-IR")
            },
            {
                label: "\u062d\u0633\u0627\u0628 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647",
                value: config.hasCredentials ? (config.usernameMasked || "\u062b\u0628\u062a \u0634\u062f\u0647") : "\u062b\u0628\u062a \u0646\u0634\u062f\u0647"
            }
        ];

        ownerCards.innerHTML = cards.map(function (card) {
            return [
                '<article class="navid-owner-card">',
                "  <span>" + safeText(card.label) + "</span>",
                "  <strong>" + safeText(card.value) + "</strong>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderUpdates(updates) {
        if (!updatesList) {
            return;
        }

        var list = Array.isArray(updates) ? updates.slice(0, 24) : [];
        if (!list.length) {
            renderEmpty(updatesList, "\u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u062c\u062f\u06cc\u062f\u06cc \u0628\u0631\u0627\u06cc \u062a\u06a9\u0627\u0644\u06cc\u0641 \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.");
            return;
        }

        updatesList.innerHTML = list.map(function (item) {
            var eventType = String(item.eventType || "").toLowerCase() === "updated"
                ? "\u0648\u06cc\u0631\u0627\u06cc\u0634 \u062a\u06a9\u0644\u06cc\u0641"
                : "\u062a\u06a9\u0644\u06cc\u0641 \u062c\u062f\u06cc\u062f";
            var titleText = snippet(item.title, 140) || "\u0628\u062f\u0648\u0646 \u0639\u0646\u0648\u0627\u0646";
            var course = snippet(item.courseTitle, 90) || "\u062f\u0631\u0633 \u0646\u0627\u0645\u0634\u062e\u0635";
            var detectedAt = formatDate(item.detectedAt, "\u0632\u0645\u0627\u0646 \u062a\u0634\u062e\u06cc\u0635 \u0646\u0627\u0645\u0634\u062e\u0635");
            return [
                '<article class="navid-item">',
                '  <h4 class="navid-item__title">' + safeText(eventType + ": " + titleText) + "</h4>",
                '  <p class="navid-item__meta">' + safeText(course + " • " + detectedAt) + "</p>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderAssignments(assignments) {
        if (!assignmentsList) {
            return;
        }

        var list = Array.isArray(assignments) ? assignments.slice(0, 120) : [];
        if (!list.length) {
            renderEmpty(assignmentsList, "\u062a\u06a9\u0644\u06cc\u0641 \u0641\u0639\u0627\u0644\u06cc \u062f\u0631 \u0646\u0648\u06cc\u062f \u067e\u06cc\u062f\u0627 \u0646\u0634\u062f.");
            return;
        }

        assignmentsList.innerHTML = list.map(function (item) {
            var titleText = snippet(item.title, 140) || "\u0628\u062f\u0648\u0646 \u0639\u0646\u0648\u0627\u0646";
            var course = snippet(item.courseTitle, 90) || "\u062f\u0631\u0633 \u0646\u0627\u0645\u0634\u062e\u0635";
            var deadline = item.endDateShamsi || formatDate(item.endDateIso, "\u0645\u0647\u0644\u062a \u0646\u0627\u0645\u0634\u062e\u0635");
            var description = snippet(item.descriptionText, 440) || "\u0628\u0631\u0627\u06cc \u0627\u06cc\u0646 \u062a\u06a9\u0644\u06cc\u0641 \u062a\u0648\u0636\u06cc\u062d\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.";
            var filesCount = Array.isArray(item.files) ? item.files.length : 0;
            return [
                '<article class="navid-item">',
                '  <h4 class="navid-item__title">' + safeText(titleText) + "</h4>",
                '  <p class="navid-item__meta">' + safeText(course + " • مهلت: " + deadline + " • فایل پیوست: " + filesCount) + "</p>",
                '  <p class="navid-item__desc">' + safeText(description) + "</p>",
                "</article>"
            ].join("");
        }).join("");
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

        if (countAssignments) {
            countAssignments.textContent = assignments.length.toLocaleString("fa-IR");
        }
        if (countUpdates) {
            countUpdates.textContent = updates.length.toLocaleString("fa-IR");
        }
        if (lastCheck) {
            lastCheck.textContent = formatDate(publicStatus.lastSuccessAt || publicStatus.lastSyncAt, "\u2014");
        }

        if (!enabled) {
            if (statusTitle) {
                statusTitle.textContent = "\u06cc\u06a9\u067e\u0627\u0631\u0686\u0647\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f \u063a\u06cc\u0631\u0641\u0639\u0627\u0644 \u0627\u0633\u062a";
            }
            if (statusDesc) {
                statusDesc.textContent = "\u0641\u0639\u0627\u0644\u200c\u0633\u0627\u0632\u06cc \u0648 \u062a\u0646\u0638\u06cc\u0645 \u0627\u062a\u0635\u0627\u0644 \u0631\u0627 \u0627\u0632 \u067e\u0646\u0644 \u062d\u0633\u0627\u0628 \u0627\u0646\u062c\u0627\u0645 \u0628\u062f\u0647.";
            }
            if (sessionState) {
                sessionState.textContent = "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            }
            setDashboardFeedback("\u062e\u0631\u0648\u062c\u06cc \u0646\u0648\u06cc\u062f \u067e\u0633 \u0627\u0632 \u0641\u0639\u0627\u0644\u200c\u0633\u0627\u0632\u06cc \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0645\u06cc\u200c\u0634\u0648\u062f.", "");
            renderOwnerStatus(ownerStatus);
            renderUpdates([]);
            renderAssignments([]);
            return;
        }

        var result = resultLabel(publicStatus.lastResult || "");
        if (statusTitle) {
            statusTitle.textContent = "\u0648\u0636\u0639\u06cc\u062a \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc: " + result;
        }
        if (statusDesc) {
            if (actionRequired === "save-credentials" || publicStatus.credentialsMissing) {
                statusDesc.textContent = currentUser && currentUser.isOwner
                    ? "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0648 \u0631\u0645\u0632 \u0646\u0648\u06cc\u062f \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a. \u0627\u0632 \u067e\u0646\u0644 \u062d\u0633\u0627\u0628 \u0627\u0639\u062a\u0628\u0627\u0631 \u0631\u0627 \u0630\u062e\u06cc\u0631\u0647 \u06a9\u0646."
                    : "\u0627\u062a\u0635\u0627\u0644 \u0646\u0648\u06cc\u062f \u0647\u0646\u0648\u0632 \u062a\u06a9\u0645\u06cc\u0644 \u0646\u0634\u062f\u0647 \u0627\u0633\u062a \u0648 \u0646\u06cc\u0627\u0632 \u0628\u0647 \u062b\u0628\u062a \u0627\u0639\u062a\u0628\u0627\u0631 \u062a\u0648\u0633\u0637 \u0645\u062f\u06cc\u0631 \u062f\u0627\u0631\u062f.";
            } else if (actionRequired === "update-credentials" || publicStatus.credentialsInvalid) {
                statusDesc.textContent = currentUser && currentUser.isOwner
                    ? "\u0627\u0639\u062a\u0628\u0627\u0631 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0633\u062a. \u062f\u0631 \u067e\u0646\u0644 \u062d\u0633\u0627\u0628 \u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc/\u0631\u0645\u0632 \u0631\u0627 \u0628\u0647\u200c\u0631\u0648\u0632 \u06a9\u0646."
                    : "\u0627\u062a\u0635\u0627\u0644 \u0646\u0648\u06cc\u062f \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u0627\u0639\u062a\u0628\u0627\u0631 \u062a\u0648\u0633\u0637 \u0645\u062f\u06cc\u0631 \u062f\u0627\u0631\u062f.";
            } else if (actionRequired === "manual-reconnect" || publicStatus.requiresReconnect) {
                statusDesc.textContent = currentUser && currentUser.isOwner
                    ? "\u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0628\u0627 \u06a9\u067e\u0686\u0627 \u062f\u0627\u0631\u062f."
                    : "\u0628\u0631\u0627\u06cc \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u062e\u0631\u0648\u062c\u06cc \u0646\u0648\u06cc\u062f\u060c \u0645\u062f\u06cc\u0631 \u0628\u0627\u06cc\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0627\u0646\u062c\u0627\u0645 \u062f\u0647\u062f.";
            } else if (publicStatus.lastResult === "partial") {
                statusDesc.textContent = failedCourses > 0
                    ? ("\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f\u061b " + failedCourses.toLocaleString("fa-IR") + " \u062f\u0631\u0633 \u062f\u0631\u06cc\u0627\u0641\u062a \u0646\u0634\u062f.")
                    : "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f \u0648 \u062e\u0631\u0648\u062c\u06cc \u062a\u0627\u06cc\u06cc\u062f \u0646\u0634\u062f.";
            } else if (publicLastError) {
                statusDesc.textContent = publicLastError;
            } else {
                statusDesc.textContent = "\u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0648 \u0622\u062e\u0631\u06cc\u0646 \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627 \u0628\u0627 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0633\u0631\u0648\u0631 \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0645\u06cc\u200c\u0634\u0648\u062f.";
            }
        }

        if (sessionState) {
            if (ownerStatus && ownerStatus.session) {
                sessionState.textContent = ownerStatus.session.status || "\u2014";
            } else if (actionRequired === "save-credentials" || publicStatus.credentialsMissing) {
                sessionState.textContent = "\u0628\u062f\u0648\u0646 \u0627\u0639\u062a\u0628\u0627\u0631";
            } else if (actionRequired === "update-credentials" || publicStatus.credentialsInvalid) {
                sessionState.textContent = "\u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0627\u0645\u0639\u062a\u0628\u0631";
            } else if (publicStatus.lastResult === "partial") {
                sessionState.textContent = "\u0646\u0627\u0642\u0635";
            } else {
                sessionState.textContent = publicStatus.requiresReconnect ? "\u0646\u06cc\u0627\u0632 \u0628\u0647 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f" : "\u0641\u0639\u0627\u0644";
            }
        }

        if (actionRequired === "save-credentials" || publicStatus.credentialsMissing) {
            setDashboardFeedback("\u0627\u0639\u062a\u0628\u0627\u0631 \u0648\u0631\u0648\u062f \u0646\u0648\u06cc\u062f \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.", "error");
        } else if (actionRequired === "update-credentials" || publicStatus.credentialsInvalid) {
            setDashboardFeedback("\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u06cc\u0627 \u0631\u0645\u0632 \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0633\u062a.", "error");
        } else if (actionRequired === "manual-reconnect" || publicStatus.requiresReconnect) {
            setDashboardFeedback("\u0627\u062a\u0635\u0627\u0644 \u0646\u0648\u06cc\u062f \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0628\u0627\u0632\u0627\u062a\u0635\u0627\u0644 \u062f\u0633\u062a\u06cc \u0648 \u06a9\u067e\u0686\u0627 \u062f\u0627\u0631\u062f.", "error");
        } else if (publicStatus.lastResult === "partial") {
            setDashboardFeedback(
                failedCourses > 0
                    ? ("\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f\u061b " + failedCourses.toLocaleString("fa-IR") + " \u062f\u0631\u0633 \u062f\u0631\u06cc\u0627\u0641\u062a \u0646\u0634\u062f.")
                    : "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0627\u0633\u062a.",
                "error"
            );
        } else if (publicStatus.lastResult === "ok" || publicStatus.lastResult === "skipped" || publicStatus.lastResult === "already-running") {
            setDashboardFeedback("\u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0628\u0647\u200c\u0631\u0648\u0632 \u0634\u062f.", "success");
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
        setFlowState("loading");
        setDashboardFeedback("", "");

        try {
            var response = await apiGet("feed");
            if (ticket !== feedTicket) {
                return;
            }

            if (consumeUnauthorized(response, "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f.")) {
                currentUser = null;
                currentUserKey = "";
                setFlowState("unauthorized");
                setAuthFeedback("\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f. \u062f\u0648\u0628\u0627\u0631\u0647 \u0648\u0627\u0631\u062f \u0634\u0648.", "error");
                return;
            }

            if (!response || !response.success || !response.data) {
                setFlowState("ready");
                setDashboardFeedback((response && response.error) || "\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0627\u0646\u062c\u0627\u0645 \u0646\u0634\u062f.", "error");
                renderUpdates([]);
                renderAssignments([]);
                return;
            }

            renderFeed(response.data);
            setFlowState("ready");
        } catch (error) {
            if (ticket !== feedTicket) {
                return;
            }
            setFlowState("ready");
            setDashboardFeedback((error && error.message) || "\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0628\u0627 \u062e\u0637\u0627 \u0645\u062a\u0648\u0642\u0641 \u0634\u062f.", "error");
            renderUpdates([]);
            renderAssignments([]);
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
        setDashboardFeedback("\u062f\u0631 \u062d\u0627\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0641\u0648\u0631\u06cc \u0646\u0648\u06cc\u062f...", "");

        try {
            var response = await apiPost("syncNow", {});
            if (consumeUnauthorized(response, "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f.")) {
                currentUser = null;
                currentUserKey = "";
                setFlowState("unauthorized");
                setAuthFeedback("\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f. \u062f\u0648\u0628\u0627\u0631\u0647 \u0648\u0627\u0631\u062f \u0634\u0648.", "error");
                return;
            }

            if (!response || !response.success) {
                setDashboardFeedback((response && response.message) || (response && response.error) || "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0641\u0648\u0631\u06cc \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.", "error");
            } else {
                setDashboardFeedback(response.message || "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0641\u0648\u0631\u06cc \u0627\u0646\u062c\u0627\u0645 \u0634\u062f.", "success");
            }
        } finally {
            syncNowButton.disabled = false;
            await loadFeed();
        }
    }

    function handleAuth(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            currentUser = null;
            currentUserKey = "";
            setFlowState("restoring");
            setAuthFeedback("", "");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            currentUser = null;
            currentUserKey = "";
            setFlowState(detail.status === "unauthorized" ? "unauthorized" : "signed-out");
            setAuthFeedback(
                detail.status === "unauthorized"
                    ? (detail.error || "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f.")
                    : "\u0628\u0631\u0627\u06cc \u062f\u0633\u062a\u0631\u0633\u06cc \u0628\u0647 \u062e\u0631\u0648\u062c\u06cc \u0646\u0648\u06cc\u062f \u0648\u0627\u0631\u062f \u062d\u0633\u0627\u0628 \u0634\u0648.",
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

    window.Dent1402Auth.onChange(handleAuth);
})();
