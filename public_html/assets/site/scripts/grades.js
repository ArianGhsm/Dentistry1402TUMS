(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    function normalizeNumeric(value) {
        var normalized = String(value == null ? "" : value).replace(",", ".");
        var parsed = Number(normalized);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
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
            : "�";

        return [
            {
                label: "??????? ??",
                value: average,
                meta: numericGrades.length ? numericGrades.length.toLocaleString("fa-IR") + " ???? ???????" : "???? ??????? ??? ????"
            },
            {
                label: "??????? ????",
                value: best ? best.value.toFixed(2) : "�",
                meta: best ? best.label : "????? ????"
            },
            {
                label: "???????? ?????",
                value: rankedCount ? rankedCount.toLocaleString("fa-IR") : "�",
                meta: rankedCount ? "???? ???? ?????? ???? ?????? ???" : "??????? ??? ????"
            },
            {
                label: "?????",
                value: currentStudentNumber || "�",
                meta: "????? ????????"
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
            ? availableCount.toLocaleString("fa-IR") + " ???? ??? ???"
            : "???? ??????? ??? ????.";

        if (!rows.length || !availableCount) {
            var empty = document.createElement("div");
            empty.className = "grades-empty-state";
            empty.innerHTML =
                "<strong>????? ??????? ???? ????? ????.</strong>" +
                "<span>??? ???? ?????? ???????? ??? ??? ?????? ???? ?? ???? ??.</span>";
            gradesList.appendChild(empty);
            flow.dataset.authState = "empty";
            return;
        }

        rows.forEach(function (grade) {
            var stat = statsMap[grade.label] || null;
            var numeric = normalizeNumeric(grade.value);
            var meta = [];

            if (stat && stat.classAverage !== null && stat.classAverage !== undefined) {
                meta.push("??????? ???? " + Number(stat.classAverage).toFixed(2));
            }

            if (stat && stat.rank !== null && stat.rank !== undefined &&
                stat.totalWithScore !== null && stat.totalWithScore !== undefined) {
                meta.push("???? " + stat.rank + " ?? " + stat.totalWithScore);
            }

            var row = document.createElement("article");
            row.className = "grades-row" + (numeric === null ? " grades-row--empty" : "");
            row.innerHTML =
                '<div class="grades-row__main">' +
                    '<h4>' + grade.label + "</h4>" +
                    '<p>' + (meta.length ? meta.join(" � ") : "???? ??????? ???? ??? ??? ??? ???? ???.") + "</p>" +
                "</div>" +
                '<div class="grades-row__value">' + ((grade.value === undefined || grade.value === "") ? "�" : grade.value) + "</div>";
            gradesList.appendChild(row);
        });
    }

    function renderDashboard(result) {
        currentPayload = result;
        studentName.textContent = result.name || "??????";
        studentMeta.textContent = "????? ????????: " + (currentStudentNumber || "�");
        renderSummary(result);
        renderGrades(result);
    }

    function ensureSignedOutState(errorText) {
        setState(errorText ? "unauthorized" : "signed-out");
        setAuthMessage(errorText || "???? ???? ???????? ??? ???? ???? ?????? ??.", errorText ? "error" : "", false);
        if (accountEntryLink) {
            accountEntryLink.href = window.Dent1402Auth.loginUrl();
        }
    }

    async function fetchGrades() {
        var response = await fetch("grades_api.php?action=me", {
            method: "GET",
            headers: {
                "Accept": "application/json"
            }
        });

        return response.json();
    }

    async function loadGrades() {
        setState("loading");
        showDashboardFeedback("", "");

        try {
            var result = await fetchGrades();

            if (!result || result.error) {
                if (result && result.loggedOut) {
                    await window.Dent1402Auth.bootstrap(true);
                    ensureSignedOutState("????? ????? ??. ?????? ???? ???? ?????? ??.");
                    return;
                }

                throw new Error((result && result.error) || "????? ????? ????? ???.");
            }

            renderDashboard(result);
            setState(flow.dataset.authState === "empty" ? "empty" : "ready");
            showDashboardFeedback("??????? ????? ???.", "success");
        } catch (error) {
            console.error(error);
            ensureSignedOutState(error.message || "????? ????? ????? ???.");
        }
    }

    async function refreshGrades() {
        refreshBtn.disabled = true;
        showDashboardFeedback("?? ??? ????????? ?????...", "", false);

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
        if (detail.status === "restoring" || detail.status === "logging-out") {
            setState("restoring");
            setAuthMessage("?? ??? ??????? ????...", "", true);
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            currentPayload = null;
            currentStudentNumber = "";
            gradesList.innerHTML = "";
            summaryGrid.innerHTML = "";
            ensureSignedOutState("");
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
