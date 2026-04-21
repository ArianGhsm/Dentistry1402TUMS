(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    if (!window.Dent1402Auth) {
        return;
    }

    var stageBoot = $("account-boot");
    var stageLogin = $("account-login");
    var stagePanel = $("account-panel");
    var bootText = $("account-boot-text");

    var loginForm = $("login-form");
    var loginSubmit = $("login-submit");
    var loginFeedback = $("login-feedback");

    var profileForm = $("profile-form");
    var profileSubmit = $("profile-submit");
    var profileFeedback = $("profile-feedback");

    var securityForm = $("security-form");
    var securitySubmit = $("security-submit");
    var securityFeedback = $("security-feedback");

    var logoutSubmit = $("logout-submit");

    var ownerPanel = $("owner-panel");
    var ownerSearch = $("owner-search");
    var ownerSummary = $("owner-summary");
    var ownerFeedback = $("owner-feedback");
    var representativeList = $("representative-list");
    var ownerUserList = $("owner-user-list");

    var redirectedAfterLogin = false;
    var pendingReturnTo = safeReturnTo(new URLSearchParams(window.location.search).get("returnTo"));
    var ownerState = {
        loading: false,
        savingStudentNumber: "",
        users: []
    };

    function safeReturnTo(value) {
        if (!value || typeof value !== "string") {
            return "";
        }

        if (!value.startsWith("/") || value.startsWith("//")) {
            return "";
        }

        return value;
    }

    function showStage(name) {
        stageBoot.hidden = name !== "boot";
        stageLogin.hidden = name !== "login";
        stagePanel.hidden = name !== "panel";
    }

    function setBootText(text) {
        bootText.textContent = text;
    }

    function setFeedback(node, text, kind, loading) {
        node.className = "account-feedback" + (kind ? " " + kind : "");

        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        node.textContent = text || "";
    }

    function ownerFeedbackMessage(text, kind) {
        ownerFeedback.className = "account-feedback account-feedback--inline" + (kind ? " " + kind : "");
        ownerFeedback.textContent = text || "";
    }

    function request(action, payload) {
        return fetch("/api/auth_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "???? ???? ??????? ???."
                };
            });
        });
    }

    function requestUsers() {
        return fetch("/api/auth_api.php?action=users", {
            method: "GET",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "???? ???? ??????? ???."
                };
            });
        });
    }

    function renderIdentity(user) {
        $("account-role-eyebrow").textContent = user.roleLabel || "??????";
        $("account-name").textContent = user.name || "??????";
        $("account-student-number").textContent = "????? ????????: " + (user.studentNumber || "-");
        $("account-role-badge").textContent = user.roleLabel || "??????";
        $("account-session-badge").textContent = user.isOwner ? "?????? ???? ????" : (user.canModerateChat ? "?????? ??????? ????" : "???? ????");

        $("profile-name").value = user.name || "";
        $("profile-student-number").value = user.studentNumber || "";
        $("profile-focus-area").value = (user.profile && user.profile.focusArea) || "";
        $("profile-contact-handle").value = (user.profile && user.profile.contactHandle) || "";
        $("profile-bio").value = (user.profile && user.profile.bio) || "";
    }

    function renderOwnerSummary(users) {
        var totalUsers = users.length;
        var representatives = users.filter(function (user) {
            return user.role === "representative";
        }).length;
        var withGrades = users.filter(function (user) {
            return user.hasGrades;
        }).length;

        ownerSummary.innerHTML = [
            summaryCard("?????", totalUsers.toLocaleString("fa-IR"), "?? ???????? ?????????"),
            summaryCard("???????", representatives.toLocaleString("fa-IR"), "????? ????? ?????? ???????"),
            summaryCard("????? ????", withGrades.toLocaleString("fa-IR"), "????????? ?? ?? ???? ????? ????? ?????")
        ].join("");
    }

    function summaryCard(label, value, meta) {
        return [
            '<article class="owner-summary-card">',
            '  <span>' + label + "</span>",
            '  <strong>' + value + "</strong>",
            '  <small>' + meta + "</small>",
            "</article>"
        ].join("");
    }

    function userMatchesQuery(user, query) {
        if (!query) {
            return true;
        }

        var normalized = query.trim().toLowerCase();
        if (!normalized) {
            return true;
        }

        return String(user.name || "").toLowerCase().indexOf(normalized) !== -1 ||
            String(user.studentNumber || "").indexOf(normalized) !== -1;
    }

    function renderRepresentatives(users) {
        var items = users.filter(function (user) {
            return user.role === "representative";
        });

        if (!items.length) {
            representativeList.innerHTML = '<div class="owner-empty">????? ?????????? ????? ???? ???.</div>';
            return;
        }

        representativeList.innerHTML = "";
        items.forEach(function (user) {
            var article = document.createElement("article");
            article.className = "representative-chip";
            article.innerHTML = [
                "<strong>" + user.name + "</strong>",
                "<span>" + user.studentNumber + "</span>"
            ].join("");
            representativeList.appendChild(article);
        });
    }

    function toggleButtonLabel(user) {
        if (user.role === "owner") {
            return "???? ????";
        }

        return user.role === "representative" ? "??? ???????" : "??? ???????? ???????";
    }

    function userMeta(user) {
        var parts = [user.roleLabel || "??????"];
        if (user.hasGrades) {
            parts.push("????? ?????");
        }
        return parts.join(" � ");
    }

    function renderUsers(users) {
        var query = ownerSearch.value || "";
        var visibleUsers = users.filter(function (user) {
            return userMatchesQuery(user, query);
        });

        if (!visibleUsers.length) {
            ownerUserList.innerHTML = '<div class="owner-empty">?????? ?? ??? ??????? ???? ???.</div>';
            return;
        }

        ownerUserList.innerHTML = "";
        visibleUsers.forEach(function (user) {
            var article = document.createElement("article");
            article.className = "owner-user";

            var actionDisabled = user.role === "owner" || ownerState.savingStudentNumber === user.studentNumber;
            var button = '<button class="shell-action-btn' + (user.role === "representative" ? " shell-action-btn-primary" : "") + '" type="button" data-student-number="' + user.studentNumber + '" ' + (actionDisabled ? "disabled" : "") + ">" + toggleButtonLabel(user) + "</button>";

            article.innerHTML = [
                '<div class="owner-user__copy">',
                "  <strong>" + user.name + "</strong>",
                "  <span>" + user.studentNumber + "</span>",
                "  <small>" + userMeta(user) + "</small>",
                "</div>",
                '<div class="owner-user__actions">' + button + "</div>"
            ].join("");

            ownerUserList.appendChild(article);
        });
    }

    function renderOwnerPanel() {
        renderOwnerSummary(ownerState.users);
        renderRepresentatives(ownerState.users);
        renderUsers(ownerState.users);
    }

    async function loadOwnerUsers() {
        ownerState.loading = true;
        ownerFeedbackMessage("?? ??? ????? ????? ???????...", "");
        ownerSummary.innerHTML = summaryCard("?????", "�", "?? ??? ???????? ???????? ???????");
        representativeList.innerHTML = '<div class="owner-empty">?? ??? ?????? ??????????...</div>';
        ownerUserList.innerHTML = '<div class="owner-empty">?? ??? ?????? ????? ???????...</div>';

        var response = await requestUsers();
        ownerState.loading = false;

        if (!response || !response.success) {
            ownerFeedbackMessage((response && response.error) || "????? ??????? ????? ???.", "error");
            return;
        }

        ownerState.users = Array.isArray(response.users) ? response.users : [];
        ownerFeedbackMessage("", "");
        renderOwnerPanel();
    }

    async function setRepresentative(studentNumber, representative) {
        ownerState.savingStudentNumber = studentNumber;
        ownerFeedbackMessage(representative ? "?? ??? ??? ???????..." : "?? ??? ??? ??? ???????...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("setRepresentative", {
                studentNumber: studentNumber,
                representative: representative ? "1" : "0"
            });

            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "????? ??????? ????? ???.", "error");
                return;
            }

            ownerState.users = ownerState.users.map(function (user) {
                return user.studentNumber === response.user.studentNumber ? Object.assign({}, user, response.user) : user;
            });

            ownerFeedbackMessage(response.message || "??????? ????? ??.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    function maybeRedirectAfterLogin(user) {
        if (!pendingReturnTo || redirectedAfterLogin) {
            return;
        }

        if (!user) {
            return;
        }

        redirectedAfterLogin = true;
        setBootText("???? ????? ??? ?? ??? ??????...");
        showStage("boot");

        window.setTimeout(function () {
            window.location.href = pendingReturnTo;
        }, 520);
    }

    function handleAuthState(detail) {
        if (detail.status === "restoring") {
            setBootText("?? ??? ??????? ????...");
            showStage("boot");
            return;
        }

        if (detail.status === "logging-in") {
            setFeedback(loginFeedback, "?? ??? ????...", "", true);
            showStage("login");
            return;
        }

        if (detail.status === "logging-out") {
            setBootText("?? ??? ???? ?? ????...");
            showStage("boot");
            return;
        }

        if (!detail.loggedIn) {
            showStage("login");
            if (detail.status === "login-error") {
                setFeedback(loginFeedback, detail.error || "???? ????? ???.", "error");
            } else {
                setFeedback(loginFeedback, "", "");
            }
            return;
        }

        renderIdentity(detail.user);
        showStage("panel");
        setFeedback(profileFeedback, "", "");
        setFeedback(securityFeedback, "", "");

        if (detail.user.isOwner) {
            ownerPanel.hidden = false;
            if (!ownerState.users.length && !ownerState.loading) {
                loadOwnerUsers();
            } else {
                renderOwnerPanel();
            }
        } else {
            ownerPanel.hidden = true;
        }

        maybeRedirectAfterLogin(detail.user);
    }

    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();

        var studentNumber = $("login-student-number").value.trim();
        var password = $("login-password").value.trim();

        if (!studentNumber || !password) {
            setFeedback(loginFeedback, "????? ???????? ? ??? ???? ?? ???? ???? ??.", "error");
            return;
        }

        loginSubmit.disabled = true;
        window.Dent1402Auth.login(studentNumber, password).finally(function () {
            loginSubmit.disabled = false;
        });
    });

    profileForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        profileSubmit.disabled = true;
        setFeedback(profileFeedback, "?? ??? ????? ???????...", "", true);

        try {
            var response = await request("updateProfile", {
                bio: $("profile-bio").value.trim(),
                contactHandle: $("profile-contact-handle").value.trim(),
                focusArea: $("profile-focus-area").value.trim()
            });

            if (!response || !response.success || !response.user) {
                setFeedback(profileFeedback, (response && response.error) || "????? ??????? ????? ???.", "error");
                return;
            }

            if (typeof window.Dent1402Auth.patchCurrentUser === "function") {
                window.Dent1402Auth.patchCurrentUser(response.user);
            } else {
                window.Dent1402Auth.bootstrap(true);
            }
            setFeedback(profileFeedback, response.message || "??????? ????? ??.", "success");
        } finally {
            profileSubmit.disabled = false;
        }
    });

    securityForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        var currentPassword = $("security-current-password").value.trim();
        var newPassword = $("security-new-password").value.trim();
        var confirmPassword = $("security-confirm-password").value.trim();

        if (!currentPassword || !newPassword || !confirmPassword) {
            setFeedback(securityFeedback, "??? ??????? ??? ?? ???? ??.", "error");
            return;
        }

        if (newPassword !== confirmPassword) {
            setFeedback(securityFeedback, "??? ???? ? ?????? ????? ????.", "error");
            return;
        }

        securitySubmit.disabled = true;
        setFeedback(securityFeedback, "?? ??? ????? ???...", "", true);

        try {
            var response = await request("changePassword", {
                currentPassword: currentPassword,
                newPassword: newPassword
            });

            if (!response || !response.success) {
                setFeedback(securityFeedback, (response && response.error) || "????? ??? ????? ???.", "error");
                return;
            }

            $("security-current-password").value = "";
            $("security-new-password").value = "";
            $("security-confirm-password").value = "";
            setFeedback(securityFeedback, response.message || "??? ???? ????? ???.", "success");
        } finally {
            securitySubmit.disabled = false;
        }
    });

    logoutSubmit.addEventListener("click", function () {
        logoutSubmit.disabled = true;
        window.Dent1402Auth.logout().finally(function () {
            logoutSubmit.disabled = false;
        });
    });

    ownerSearch.addEventListener("input", function () {
        renderUsers(ownerState.users);
    });

    ownerUserList.addEventListener("click", function (event) {
        var button = event.target.closest("button[data-student-number]");
        if (!button) {
            return;
        }

        var studentNumber = button.dataset.studentNumber;
        var user = ownerState.users.find(function (item) {
            return item.studentNumber === studentNumber;
        });

        if (!user || user.role === "owner") {
            return;
        }

        setRepresentative(studentNumber, user.role !== "representative");
    });

    window.Dent1402Auth.onChange(handleAuthState);
})();
