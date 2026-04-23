(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    function normalizeNumeric(value) {
        var normalized = String(value == null ? "" : value).trim();
        if (!normalized) {
            return null;
        }

        normalized = normalized
            .replace(/[\u06F0-\u06F9]/g, function (char) { return String(char.charCodeAt(0) - 0x06F0); })
            .replace(/[\u0660-\u0669]/g, function (char) { return String(char.charCodeAt(0) - 0x0660); })
            .replace(/\u066B/g, ".")
            .replace(/\u066C/g, "")
            .replace(/\u060C/g, ",")
            .replace(/,/g, ".");

        var parsed = Number(normalized);
        return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
    }

    var flow = $("grades-flow");
    var authStage = $("auth-stage");
    var loadingStage = $("grades-loading");
    var dashboard = $("grades-dashboard");
    var authMessage = $("msg");
    var dashboardFeedback = $("dashboard-feedback");
    var gradesCountLabel = $("grades-count-label");
    var gradesList = $("grades-list");
    var summaryGrid = $("summary-grid");
    var studentName = $("student-name");
    var studentMeta = $("student-meta");
    var refreshBtn = $("refresh-grades-btn");
    var logoutBtn = $("reset-grades-btn");
    var accountEntryLink = $("account-entry-link");

    var currentPayload = null;
    var currentStudentNumber = "";

    function setState(state) {
        flow.dataset.authState = state;

        authStage.hidden = state !== "signed-out" && state !== "unauthorized";
        loadingStage.hidden = state !== "restoring" && state !== "loading";
        dashboard.hidden = state !== "ready" && state !== "empty";
    }

    function setAuthMessage(text, kind, loading) {
        authMessage.className = "feedback" + (kind ? " " + kind : "");

        if (loading) {
            authMessage.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        authMessage.textContent = text || "";
    }

    function showDashboardFeedback(text, kind) {
        if (!text) {
            dashboardFeedback.hidden = true;
            dashboardFeedback.textContent = "";
            dashboardFeedback.className = "grades-status-banner";
            return;
        }

        dashboardFeedback.hidden = false;
        dashboardFeedback.textContent = text;
        dashboardFeedback.className = "grades-status-banner" + (kind ? " " + kind : "");
    }

    function summaryCards(result) {
        var numericGrades = [];
        var rankedCount = 0;
        var best = null;

        (result.grades || []).forEach(function (grade) {
            var numeric = normalizeNumeric(grade.value);
            if (numeric !== null) {
                numericGrades.push(numeric);
                if (!best || numeric > best.value) {
                    best = { label: grade.label, value: numeric };
                }
            }
        });

        (result.stats || []).forEach(function (stat) {
            if (stat && stat.rank !== null && stat.rank !== undefined) {
                rankedCount += 1;
            }
        });

        var average = numericGrades.length
            ? (numericGrades.reduce(function (sum, item) { return sum + item; }, 0) / numericGrades.length).toFixed(2)
            : "—";

        return [
            {
                label: "میانگین تو",
                value: average,
                meta: numericGrades.length ? numericGrades.length.toLocaleString("fa-IR") + " نمره ثبت‌شده" : "هنوز نمره‌ای ثبت نشده"
            },
            {
                label: "بیشترین نمره",
                value: best ? best.value.toFixed(2) : "—",
                meta: best ? best.label : "فعلاً خالی"
            },
            {
                label: "رتبه‌های موجود",
                value: rankedCount ? rankedCount.toLocaleString("fa-IR") : "—",
                meta: rankedCount ? "برای بعضی درس‌ها رتبه محاسبه شده" : "رتبه‌ای ثبت نشده"
            },
            {
                label: "شناسه",
                value: currentStudentNumber || "—",
                meta: "شماره دانشجویی"
            }
        ];
    }

    function renderSummary(result) {
        summaryGrid.innerHTML = "";

        summaryCards(result).forEach(function (card) {
            var article = document.createElement("article");
            article.className = "grades-summary-card";
            article.innerHTML =
                '<span class="grades-summary-card__label">' + card.label + "</span>" +
                '<strong class="grades-summary-card__value">' + card.value + "</strong>" +
                '<span class="grades-summary-card__meta">' + card.meta + "</span>";
            summaryGrid.appendChild(article);
        });
    }

    function renderGrades(result) {
        gradesList.innerHTML = "";

        var statsMap = {};
        (result.stats || []).forEach(function (stat) {
            if (stat && stat.label) {
                statsMap[stat.label] = stat;
            }
        });

        var rows = result.grades || [];
        var availableCount = rows.filter(function (grade) {
            return normalizeNumeric(grade.value) !== null;
        }).length;

        gradesCountLabel.textContent = availableCount
            ? availableCount.toLocaleString("fa-IR") + " نمره ثبت شده"
            : "هنوز نمره‌ای ثبت نشده.";

        if (!rows.length || !availableCount) {
            var empty = document.createElement("div");
            empty.className = "grades-empty-state";
            empty.innerHTML =
                "<strong>فعلاً نمره‌ای برای نمایش نیست.</strong>" +
                "<span>اگر تازه امتحان داده‌ای، کمی بعد دوباره صفحه را تازه کن.</span>";
            gradesList.appendChild(empty);
            flow.dataset.authState = "empty";
            return;
        }

        rows.forEach(function (grade) {
            var stat = statsMap[grade.label] || null;
            var numeric = normalizeNumeric(grade.value);
            var meta = [];

            if (stat && stat.classAverage !== null && stat.classAverage !== undefined) {
                meta.push("میانگین کلاس " + Number(stat.classAverage).toFixed(2));
            }

            if (stat && stat.rank !== null && stat.rank !== undefined &&
                stat.totalWithScore !== null && stat.totalWithScore !== undefined) {
                meta.push("رتبه " + stat.rank + " از " + stat.totalWithScore);
            }

            var row = document.createElement("article");
            row.className = "grades-row" + (numeric === null ? " grades-row--empty" : "");
            row.innerHTML =
                '<div class="grades-row__main">' +
                    '<h4>' + grade.label + "</h4>" +
                    '<p>' + (meta.length ? meta.join(" • ") : "هنوز نمره‌ای برای این درس ثبت نشده است.") + "</p>" +
                "</div>" +
                '<div class="grades-row__value">' + ((grade.value === undefined || grade.value === "") ? "—" : grade.value) + "</div>";
            gradesList.appendChild(row);
        });
    }

    function renderDashboard(result) {
        currentPayload = result;
        studentName.textContent = result.name || "دانشجو";
        studentMeta.textContent = "شماره دانشجویی: " + (currentStudentNumber || "—");
        renderSummary(result);
        renderGrades(result);
    }

    function ensureSignedOutState(errorText) {
        setState(errorText ? "unauthorized" : "signed-out");
        setAuthMessage(errorText || "برای دیدن کارنامه، اول وارد حساب کاربری شو.", errorText ? "error" : "", false);
        if (accountEntryLink) {
            accountEntryLink.href = window.Dent1402Auth.loginUrl();
        }
    }

    async function fetchGrades() {
        var response = await fetch("grades_api.php?action=me", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        var payload = await response.json().catch(function () {
            return {
                error: "پاسخ نامعتبر از سرور دریافت شد."
            };
        });
        payload.httpStatus = response.status;
        return payload;
    }

    function consumeUnauthorized(response, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
        if (!auth) {
            return false;
        }

        var message = fallbackText || "نشست شما منقضی شده است. دوباره وارد شوید.";
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

    async function loadGrades() {
        setState("loading");
        showDashboardFeedback("", "");

        try {
            var result = await fetchGrades();

            if (!result || result.error) {
                if (consumeUnauthorized(result, "نشست شما منقضی شده است. دوباره وارد شوید.")) {
                    ensureSignedOutState("نشست شما منقضی شده است. دوباره وارد شوید.");
                    return;
                }

                throw new Error((result && result.error) || "گرفتن نمرات انجام نشد.");
            }

            renderDashboard(result);
            setState(flow.dataset.authState === "empty" ? "empty" : "ready");
            showDashboardFeedback("کارنامه آماده است.", "success");
        } catch (error) {
            console.error(error);
            ensureSignedOutState(error.message || "گرفتن نمرات انجام نشد.");
        }
    }

    async function refreshGrades() {
        refreshBtn.disabled = true;
        showDashboardFeedback("در حال تازه‌سازی نمرات...", "", false);

        try {
            await loadGrades();
        } finally {
            refreshBtn.disabled = false;
        }
    }

    async function logout() {
        logoutBtn.disabled = true;
        await window.Dent1402Auth.logout();
        logoutBtn.disabled = false;
    }

    function handleAuthChange(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            setState("restoring");
            setAuthMessage("در حال بازیابی نشست...", "", true);
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            currentPayload = null;
            currentStudentNumber = "";
            gradesList.innerHTML = "";
            summaryGrid.innerHTML = "";
            ensureSignedOutState(detail.status === "unauthorized" ? detail.error : "");
            return;
        }

        currentStudentNumber = detail.user.studentNumber || "";

        if (!currentPayload || currentPayload.studentNumber !== currentStudentNumber) {
            loadGrades();
        }
    }

    refreshBtn.addEventListener("click", refreshGrades);
    logoutBtn.addEventListener("click", logout);
    window.Dent1402Auth.onChange(handleAuthChange);
})();

