(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var panel = $("home-identity-panel");
    if (!panel) {
        return;
    }

    var status = $("home-identity-status");
    var title = $("home-identity-title");
    var desc = $("home-identity-desc");
    var meta = $("home-identity-meta");
    var primaryAction = $("home-identity-primary");
    var secondaryAction = $("home-identity-secondary");
    var ownerBadge = $("home-owner-badge");

    var navidPanel = $("home-navid-panel");
    var navidStateText = $("home-navid-state");
    var navidSyncText = $("home-navid-sync");
    var navidUpdates = $("home-navid-updates");
    var navidAssignments = $("home-navid-assignments");

    var navidLoadedFor = "";
    var navidLoadToken = 0;
    var navidLoading = false;

    function consumeUnauthorized(response, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
        if (!auth) {
            return false;
        }

        var message = fallbackText || "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f.";
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

        return parsed.toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function navidResultLabel(result) {
        switch (result) {
            case "ok":
                return "\u067e\u0627\u06cc\u062f\u0627\u0631";
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
                return "\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f";
            case "dashboard-failed":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u0634\u0628\u0648\u0631\u062f";
            case "exception":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u062e\u0644\u06cc";
            case "lock-failed":
                return "\u062e\u0637\u0627\u06cc \u0642\u0641\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "skipped":
                return "\u0628\u062f\u0648\u0646 \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0628\u0631\u0631\u0633\u06cc \u062c\u062f\u06cc\u062f";
            case "already-running":
                return "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u062f\u0631 \u062d\u0627\u0644 \u0627\u0646\u062c\u0627\u0645";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0646\u0627\u0645\u0634\u062e\u0635";
        }
    }

    function setIdentityLoggedOut(errorText) {
        panel.dataset.authState = errorText ? "unauthorized" : "logged-out";
        status.textContent = "\u0648\u0631\u0648\u062f \u0644\u0627\u0632\u0645 \u0627\u0633\u062a";
        title.textContent = "\u062d\u0633\u0627\u0628 \u0633\u0631\u0627\u0633\u0631\u06cc\u200c\u0627\u062a \u0631\u0627 \u0641\u0639\u0627\u0644 \u06a9\u0646.";
        desc.textContent = "\u0628\u0627 \u0647\u0645\u0627\u0646 \u0634\u0645\u0627\u0631\u0647 \u062f\u0627\u0646\u0634\u062c\u0648\u06cc\u06cc \u0648 \u0631\u0645\u0632 \u0627\u0632 \u067e\u06cc\u0634\u200c\u062a\u0639\u0631\u06cc\u0641\u200c\u0634\u062f\u0647 \u0648\u0627\u0631\u062f \u0634\u0648 \u062a\u0627 \u0646\u0645\u0631\u0627\u062a\u060c \u0686\u062a \u0648 \u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc\u200c\u0627\u062a \u062f\u0631 \u06a9\u0644 \u0633\u0627\u06cc\u062a \u06cc\u06a9\u067e\u0627\u0631\u0686\u0647 \u0634\u0648\u0646\u062f.";
        meta.textContent = errorText || "\u0646\u0634\u0633\u062a \u062d\u0633\u0627\u0628 \u0631\u0648\u06cc \u0647\u0645\u06cc\u0646 \u062f\u0633\u062a\u06af\u0627\u0647 \u0646\u06af\u0647 \u062f\u0627\u0634\u062a\u0647 \u0645\u06cc\u200c\u0634\u0648\u062f.";
        ownerBadge.hidden = true;
        primaryAction.textContent = "\u0648\u0631\u0648\u062f \u0628\u0647 \u062d\u0633\u0627\u0628";
        primaryAction.href = window.Dent1402Auth.loginUrl("/app/");
        secondaryAction.textContent = "\u0645\u062f\u06cc\u0631\u06cc\u062a \u062d\u0633\u0627\u0628";
        secondaryAction.href = "/account/";
    }

    function setIdentityBoot(message) {
        panel.dataset.authState = "session-restoring";
        status.textContent = "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0632\u06cc\u0627\u0628\u06cc";
        title.textContent = "\u0646\u0634\u0633\u062a \u062d\u0633\u0627\u0628 \u062f\u0631 \u062d\u0627\u0644 \u0622\u0645\u0627\u062f\u0647\u200c\u0633\u0627\u0632\u06cc \u0627\u0633\u062a.";
        desc.textContent = message || "\u0627\u06af\u0631 \u0642\u0628\u0644\u0627\u064b \u0648\u0627\u0631\u062f \u0634\u062f\u0647 \u0628\u0627\u0634\u06cc\u060c \u0647\u0648\u06cc\u062a\u062a \u0631\u0648\u06cc \u0647\u0645\u06cc\u0646 \u062f\u0633\u062a\u06af\u0627\u0647 \u0628\u0631\u0645\u06cc\u200c\u06af\u0631\u062f\u062f.";
        meta.textContent = "\u0686\u0646\u062f \u0644\u062d\u0638\u0647 \u0635\u0628\u0631 \u06a9\u0646.";
        ownerBadge.hidden = true;
        primaryAction.textContent = "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0631\u0631\u0633\u06cc...";
        primaryAction.href = "/account/";
        secondaryAction.textContent = "\u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc";
        secondaryAction.href = "/account/";
    }

    function setIdentityLoggedIn(user) {
        var isOwner = !!(user && user.isOwner);
        panel.dataset.authState = "logged-in";
        status.textContent = isOwner ? "\u0645\u0627\u0644\u06a9 \u0633\u0627\u0645\u0627\u0646\u0647" : (user.roleLabel || "\u062d\u0633\u0627\u0628 \u0641\u0639\u0627\u0644");
        title.textContent = (user.name || "\u062f\u0627\u0646\u0634\u062c\u0648") + "\u060c \u062e\u0648\u0634 \u0628\u0631\u06af\u0634\u062a\u06cc.";
        desc.textContent = isOwner
            ? "\u062f\u0633\u062a\u0631\u0633\u06cc \u0645\u062f\u06cc\u0631\u06cc\u062a\u06cc \u0641\u0639\u0627\u0644 \u0627\u0633\u062a \u0648 \u0627\u0632 \u0647\u0645\u06cc\u0646\u200c\u062c\u0627 \u0645\u06cc\u200c\u062a\u0648\u0627\u0646\u06cc \u0686\u062a\u060c \u0646\u0645\u0627\u06cc\u0646\u062f\u0647\u200c\u0647\u0627 \u0648 \u062d\u0633\u0627\u0628\u200c\u0647\u0627 \u0631\u0627 \u0645\u062f\u06cc\u0631\u06cc\u062a \u06a9\u0646\u06cc."
            : "\u0647\u0648\u06cc\u062a\u062a \u062f\u0631 \u0686\u062a\u060c \u0646\u0645\u0631\u0627\u062a \u0648 \u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0647\u0645\u06af\u0627\u0645 \u0627\u0633\u062a \u0648 \u0644\u0627\u0632\u0645 \u0646\u06cc\u0633\u062a \u0647\u0631 \u0635\u0641\u062d\u0647 \u062c\u062f\u0627\u06af\u0627\u0646\u0647 \u0648\u0627\u0631\u062f \u0634\u0648\u06cc.";
        meta.textContent = "\u0634\u0645\u0627\u0631\u0647 \u062f\u0627\u0646\u0634\u062c\u0648\u06cc\u06cc: " + (user.studentNumber || "-");
        ownerBadge.hidden = !isOwner;
        primaryAction.textContent = isOwner ? "\u067e\u0646\u0644 \u062d\u0633\u0627\u0628 \u0648 \u0645\u062f\u06cc\u0631\u06cc\u062a" : "\u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc";
        primaryAction.href = "/account/";
        secondaryAction.textContent = "\u0646\u0645\u0631\u0627\u062a \u0645\u0646";
        secondaryAction.href = "/grades/";
    }

    function navidSetState(state) {
        if (!navidPanel) {
            return;
        }
        navidPanel.dataset.state = state;
    }

    function navidRenderEmpty(container, message) {
        if (!container) {
            return;
        }
        container.innerHTML = '<div class="portal-navid-empty">' + safeText(message) + "</div>";
    }

    function navidSetBoot(message) {
        if (!navidPanel) {
            return;
        }
        navidSetState("restoring");
        if (navidStateText) {
            navidStateText.textContent = message || "\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u0648\u0636\u0639\u06cc\u062a \u0646\u0648\u06cc\u062f...";
        }
        if (navidSyncText) {
            navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
        }
        navidRenderEmpty(navidUpdates, "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0631\u06af\u06cc\u0631\u06cc \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627...");
        navidRenderEmpty(navidAssignments, "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0631\u06af\u06cc\u0631\u06cc \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc...");
    }

    function navidSetSignedOut(errorText) {
        if (!navidPanel) {
            return;
        }
        navidSetState(errorText ? "unauthorized" : "signed-out");
        if (navidStateText) {
            navidStateText.textContent = errorText || "\u0628\u0631\u0627\u06cc \u062f\u06cc\u062f\u0646 \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0646\u0648\u06cc\u062f\u060c \u0627\u0648\u0644 \u0648\u0627\u0631\u062f \u062d\u0633\u0627\u0628 \u0634\u0648.";
        }
        if (navidSyncText) {
            navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
        }
        navidRenderEmpty(navidUpdates, "\u0628\u062f\u0648\u0646 \u0648\u0631\u0648\u062f\u060c \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u0646\u0648\u06cc\u062f \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0646\u0645\u06cc\u200c\u0634\u0648\u062f.");
        navidRenderEmpty(navidAssignments, "\u0628\u0639\u062f \u0627\u0632 \u0648\u0631\u0648\u062f\u060c \u0644\u06cc\u0633\u062a \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0627\u06cc\u0646\u062c\u0627 \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0645\u06cc\u200c\u0634\u0648\u062f.");
    }

    function navidSetError(message) {
        if (!navidPanel) {
            return;
        }
        navidSetState("error");
        if (navidStateText) {
            navidStateText.textContent = message || "\u062f\u0631\u06cc\u0627\u0641\u062a \u062f\u0627\u062f\u0647 \u0646\u0648\u06cc\u062f \u0628\u0627 \u062e\u0637\u0627 \u0631\u0648\u0628\u0647\u200c\u0631\u0648 \u0634\u062f.";
        }
        if (navidSyncText) {
            navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
        }
        navidRenderEmpty(navidUpdates, "\u0644\u06cc\u0633\u062a \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627 \u0641\u0639\u0644\u0627\u064b \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
        navidRenderEmpty(navidAssignments, "\u0644\u06cc\u0633\u062a \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0641\u0639\u0644\u0627\u064b \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
    }

    function navidFetchFeed() {
        return fetch("/api/navid_api.php?action=feed", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "\u067e\u0627\u0633\u062e \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0632 \u0633\u0631\u0648\u0631 \u062f\u0631\u06cc\u0627\u0641\u062a \u0634\u062f."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function navidRenderUpdates(items) {
        if (!navidUpdates) {
            return;
        }

        var updates = Array.isArray(items) ? items.slice(0, 3) : [];
        if (!updates.length) {
            navidRenderEmpty(navidUpdates, "\u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u062a\u0627\u0632\u0647\u200c\u0627\u06cc \u0628\u0631\u0627\u06cc \u0646\u0648\u06cc\u062f \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.");
            return;
        }

        navidUpdates.innerHTML = updates.map(function (item) {
            var eventType = String(item.eventType || "").toLowerCase() === "updated"
                ? "\u0648\u06cc\u0631\u0627\u06cc\u0634 \u062a\u06a9\u0644\u06cc\u0641"
                : "\u062a\u06a9\u0644\u06cc\u0641 \u062c\u062f\u06cc\u062f";
            var titleText = snippet(item.title, 120) || "\u0628\u062f\u0648\u0646 \u0639\u0646\u0648\u0627\u0646";
            var course = snippet(item.courseTitle, 80) || "\u062f\u0631\u0633 \u0646\u0627\u0645\u0634\u062e\u0635";
            var detectedAt = formatDate(item.detectedAt, "\u0632\u0645\u0627\u0646 \u062a\u0634\u062e\u06cc\u0635 \u0646\u0627\u0645\u0634\u062e\u0635");
            return [
                '<article class="portal-navid-item">',
                '  <h4 class="portal-navid-item__title">' + safeText(eventType + ": " + titleText) + "</h4>",
                '  <p class="portal-navid-item__meta">' + safeText(course + " • " + detectedAt) + "</p>",
                "</article>"
            ].join("");
        }).join("");
    }

    function navidRenderAssignments(items) {
        if (!navidAssignments) {
            return;
        }

        var assignments = Array.isArray(items) ? items.slice(0, 6) : [];
        if (!assignments.length) {
            navidRenderEmpty(navidAssignments, "\u062a\u06a9\u0644\u06cc\u0641 \u0641\u0639\u0627\u0644\u06cc \u062f\u0631 \u0627\u06cc\u0646 \u0644\u062d\u0638\u0647 \u067e\u06cc\u062f\u0627 \u0646\u0634\u062f.");
            return;
        }

        navidAssignments.innerHTML = assignments.map(function (item) {
            var titleText = snippet(item.title, 120) || "\u0628\u062f\u0648\u0646 \u0639\u0646\u0648\u0627\u0646";
            var course = snippet(item.courseTitle, 80) || "\u062f\u0631\u0633 \u0646\u0627\u0645\u0634\u062e\u0635";
            var deadline = item.endDateShamsi || formatDate(item.endDateIso, "\u0645\u0647\u0644\u062a \u0646\u0627\u0645\u0634\u062e\u0635");
            var descText = snippet(item.descriptionText, 180) || "\u0628\u0631\u0627\u06cc \u0627\u06cc\u0646 \u062a\u06a9\u0644\u06cc\u0641 \u062a\u0648\u0636\u06cc\u062d\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.";
            var filesCount = Array.isArray(item.files) ? item.files.length : 0;
            return [
                '<article class="portal-navid-item">',
                '  <h4 class="portal-navid-item__title">' + safeText(titleText) + "</h4>",
                '  <p class="portal-navid-item__meta">' + safeText(course + " • مهلت: " + deadline + " • فایل: " + filesCount) + "</p>",
                '  <p class="portal-navid-item__desc">' + safeText(descText) + "</p>",
                "</article>"
            ].join("");
        }).join("");
    }

    function navidRenderPayload(payload, currentUser) {
        var data = payload && payload.data ? payload.data : null;
        if (!data || typeof data !== "object") {
            navidSetError("\u062f\u0627\u062f\u0647 \u0646\u0648\u06cc\u062f \u0642\u0627\u0628\u0644 \u062e\u0648\u0627\u0646\u062f\u0646 \u0646\u06cc\u0633\u062a.");
            return;
        }

        var publicStatus = data.publicStatus || {};
        if (!publicStatus.enabled) {
            navidSetState("disabled");
            if (navidStateText) {
                navidStateText.textContent = "\u06cc\u06a9\u067e\u0627\u0631\u0686\u0647\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f \u062f\u0631 \u062d\u0627\u0644 \u062d\u0627\u0636\u0631 \u063a\u06cc\u0631\u0641\u0639\u0627\u0644 \u0627\u0633\u062a.";
            }
            if (navidSyncText) {
                navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
            }
            navidRenderEmpty(navidUpdates, "\u067e\u0633 \u0627\u0632 \u0641\u0639\u0627\u0644\u200c\u0633\u0627\u0632\u06cc \u0627\u0632 \u067e\u0646\u0644 \u062d\u0633\u0627\u0628\u060c \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627 \u0627\u06cc\u0646\u062c\u0627 \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0645\u06cc\u200c\u0634\u0648\u0646\u062f.");
            navidRenderEmpty(navidAssignments, "\u0628\u0639\u062f \u0627\u0632 \u0641\u0639\u0627\u0644\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f\u060c \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0627\u06cc\u0646\u062c\u0627 \u0642\u0631\u0627\u0631 \u0645\u06cc\u200c\u06af\u06cc\u0631\u062f.");
            return;
        }

        var actionRequired = String(publicStatus.actionRequired || "");
        var needsCredentials = actionRequired === "save-credentials" || !!publicStatus.credentialsMissing;
        var invalidCredentials = actionRequired === "update-credentials" || !!publicStatus.credentialsInvalid;
        var needsReconnect = actionRequired === "manual-reconnect" || !!publicStatus.requiresReconnect;
        var lastErrorText = snippet(publicStatus.lastError, 160);
        var isOwner = !!(currentUser && currentUser.isOwner);

        if (needsCredentials) {
            navidSetState("needs-credentials");
        } else if (invalidCredentials) {
            navidSetState("credentials-invalid");
        } else if (needsReconnect) {
            navidSetState("reconnect");
        } else if (lastErrorText && String(publicStatus.lastResult || "").trim() !== "ok") {
            navidSetState("warning");
        } else {
            navidSetState("ready");
        }

        var statusLabel = navidResultLabel(publicStatus.lastResult || "");
        if (needsCredentials) {
            statusLabel += isOwner
                ? " - ثبت اعتبار لازم است"
                : " - منتظر ثبت اعتبار مدیر";
        } else if (invalidCredentials) {
            statusLabel += isOwner
                ? " - اعتبار ذخیره‌شده نامعتبر است"
                : " - منتظر بروزرسانی اعتبار مدیر";
        } else if (needsReconnect) {
            statusLabel += isOwner
                ? " - نیاز به اتصال مجدد (از پنل حساب)"
                : " - در انتظار اتصال مجدد مدیر";
        }

        if (navidStateText) {
            navidStateText.textContent = "وضعیت: " + statusLabel;
        }
        if (navidSyncText) {
            navidSyncText.textContent = "آخرین بررسی: " + formatDate(publicStatus.lastSuccessAt || publicStatus.lastSyncAt, "—");
        }

        if (needsCredentials) {
            navidRenderEmpty(
                navidUpdates,
                isOwner
                    ? "اعتبار نوید را از پنل حساب ذخیره کن تا پایش تکالیف فعال شود."
                    : "مدیر هنوز اعتبار نوید را ثبت نکرده است."
            );
            navidRenderEmpty(
                navidAssignments,
                "تا زمان اتصال نوید، تکلیف فعالی برای نمایش در دسترس نیست."
            );
            return;
        }

        if (invalidCredentials) {
            navidRenderEmpty(
                navidUpdates,
                isOwner
                    ? "نام کاربری یا رمز نوید نیاز به بروزرسانی دارد."
                    : "اعتبار نوید توسط مدیر نیاز به بروزرسانی دارد."
            );
            navidRenderEmpty(
                navidAssignments,
                "بعد از بروزرسانی اعتبار، تکالیف فعال اینجا نمایش داده می‌شود."
            );
            return;
        }

        if (needsReconnect) {
            navidRenderEmpty(
                navidUpdates,
                isOwner
                    ? "اتصال نوید نیاز به کپچا و اتصال مجدد دارد."
                    : "اتصال نوید نیاز به اقدام مدیر دارد."
            );
            navidRenderEmpty(
                navidAssignments,
                "بعد از اتصال مجدد، لیست تکالیف دوباره به‌روزرسانی می‌شود."
            );
            return;
        }

        navidRenderUpdates(data.updates);
        navidRenderAssignments(data.currentAssignments);
    }

    async function loadNavidFeed(currentUser) {
        if (!navidPanel || navidLoading) {
            return;
        }

        navidLoading = true;
        var ticket = ++navidLoadToken;
        navidSetBoot("\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0646\u0648\u06cc\u062f...");

        try {
            var response = await navidFetchFeed();
            if (ticket !== navidLoadToken) {
                return;
            }

            if (consumeUnauthorized(response, "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0628\u0647 \u067e\u0627\u06cc\u0627\u0646 \u0631\u0633\u06cc\u062f.")) {
                navidLoadedFor = "";
                navidSetSignedOut("\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0628\u0647 \u067e\u0627\u06cc\u0627\u0646 \u0631\u0633\u06cc\u062f.");
                return;
            }

            if (!response || !response.success) {
                navidSetError((response && response.error) || "\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.");
                return;
            }

            navidRenderPayload(response, currentUser);
            navidLoadedFor = String(currentUser && currentUser.studentNumber ? currentUser.studentNumber : "_logged");
        } catch (error) {
            if (ticket !== navidLoadToken) {
                return;
            }
            navidSetError((error && error.message) || "\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0628\u0627 \u062e\u0637\u0627 \u0645\u062a\u0648\u0642\u0641 \u0634\u062f.");
        } finally {
            if (ticket === navidLoadToken) {
                navidLoading = false;
            }
        }
    }

    function sync(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            setIdentityBoot(detail.status === "logging-out"
                ? "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0633\u062a\u0646 \u0646\u0634\u0633\u062a \u0641\u0639\u0644\u06cc..."
                : "");
            navidSetBoot("\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0632\u06cc\u0627\u0628\u06cc \u0648\u0636\u0639\u06cc\u062a \u0646\u0648\u06cc\u062f...");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            setIdentityLoggedOut(detail.status === "unauthorized" ? detail.error : "");
            navidLoadedFor = "";
            navidSetSignedOut(detail.status === "unauthorized" ? detail.error : "");
            return;
        }

        setIdentityLoggedIn(detail.user);
        var userKey = String(detail.user.studentNumber || "_logged");
        if (userKey !== navidLoadedFor) {
            loadNavidFeed(detail.user);
        }
    }

    window.Dent1402Auth.onChange(sync);
})();
