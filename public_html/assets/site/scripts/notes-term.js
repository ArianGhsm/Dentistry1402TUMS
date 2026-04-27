(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var cardsContainer = $("notes-term-cards");
    var emptyBox = $("notes-term-empty");
    var termTitle = $("notes-term-title");
    var termDescription = $("notes-term-description");
    var managePanel = $("notes-term-manage");
    var manageForm = $("notes-term-form");
    var manageFeedback = $("notes-term-feedback");
    var addSubmit = $("notes-term-submit");

    if (!cardsContainer || !emptyBox) {
        return;
    }

    var term = Number(document.body.dataset.termNumber || "0");
    if (!Number.isFinite(term) || term < 5 || term > 12) {
        return;
    }

    var state = {
        termData: null,
        canManage: false,
        loading: false,
        saving: false,
        authKey: ""
    };

    function authSnapshotKey() {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.getState !== "function") {
            return "anon";
        }

        var snapshot = auth.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber ? String(snapshot.user.studentNumber) : "";
        return (snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function parseJsonResponse(response) {
        return response.json().catch(function () {
            return {
                success: false,
                error: "پاسخ نامعتبر از سرور دریافت شد."
            };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        };

        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        if (method === "GET") {
            if (payload && payload.term) {
                url += "&term=" + encodeURIComponent(String(payload.term));
            }
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        }

        return fetch(url, options).then(parseJsonResponse);
    }

    function setFeedback(text, kind) {
        if (!manageFeedback) {
            return;
        }

        manageFeedback.textContent = text || "";
        manageFeedback.dataset.kind = kind || "";
        manageFeedback.hidden = !text;
    }

    function clearCards() {
        while (cardsContainer.firstChild) {
            cardsContainer.removeChild(cardsContainer.firstChild);
        }
    }

    function buildCard(item) {
        var card = document.createElement("article");
        card.className = "action-card";

        var content = document.createElement("div");
        content.className = "card-content";

        var header = document.createElement("div");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = item.badge || "منبع";

        var title = document.createElement("h3");
        title.className = "card-title";
        title.textContent = item.title || "بدون عنوان";

        var desc = document.createElement("p");
        desc.className = "card-desc";
        desc.textContent = item.description || "";

        header.appendChild(badge);
        header.appendChild(title);
        content.appendChild(header);
        content.appendChild(desc);

        var button = document.createElement("a");
        button.className = "card-btn";
        button.href = item.buttonUrl || "#";
        button.textContent = item.buttonLabel || "باز کردن";
        if (item.isExternal) {
            button.dataset.externalLink = "true";
            button.target = "_blank";
            button.rel = "noopener noreferrer";
        }

        card.appendChild(content);
        card.appendChild(button);
        return card;
    }

    function renderTerm() {
        var termData = state.termData;
        clearCards();

        if (!termData) {
            emptyBox.hidden = false;
            emptyBox.textContent = "داده‌ای برای این ترم دریافت نشد.";
            return;
        }

        if (termTitle) {
            termTitle.textContent = termData.title || termTitle.textContent;
        }
        if (termDescription) {
            termDescription.textContent = termData.description || termDescription.textContent;
        }

        var items = Array.isArray(termData.items) ? termData.items : [];
        if (!items.length) {
            emptyBox.hidden = false;
            emptyBox.textContent = termData.emptyMessage || "برای این ترم هنوز منبعی ثبت نشده است.";
        } else {
            emptyBox.hidden = true;
            items.forEach(function (item) {
                cardsContainer.appendChild(buildCard(item));
            });
        }

        if (managePanel) {
            managePanel.hidden = !state.canManage;
        }
    }

    function setSaving(saving) {
        state.saving = !!saving;
        if (addSubmit) {
            addSubmit.disabled = state.saving;
            addSubmit.textContent = state.saving ? "در حال ثبت..." : "افزودن کارت";
        }
    }

    function handleUnauthorized(payload) {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.handleUnauthorizedPayload !== "function") {
            return false;
        }
        return auth.handleUnauthorizedPayload(payload, "برای مدیریت منابع باید وارد حساب مالک شوید.");
    }

    function loadTerm(options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        var silent = options && options.silent;
        if (!silent) {
            emptyBox.hidden = false;
            emptyBox.textContent = "در حال دریافت منابع این ترم...";
        }

        return request("term", "GET", { term: term }).then(function (payload) {
            if (handleUnauthorized(payload)) {
                state.canManage = false;
                state.termData = payload.term || state.termData;
                renderTerm();
                return;
            }

            if (!payload || !payload.success || !payload.term) {
                throw new Error((payload && payload.error) || "دریافت منابع ترم ناموفق بود.");
            }

            state.termData = payload.term;
            state.canManage = !!payload.canManage;
            renderTerm();
        }).catch(function (error) {
            emptyBox.hidden = false;
            emptyBox.textContent = error && error.message ? error.message : "دریافت منابع با خطا مواجه شد.";
            state.canManage = false;
            if (managePanel) {
                managePanel.hidden = true;
            }
        }).finally(function () {
            state.loading = false;
        });
    }

    function bindManageForm() {
        if (!manageForm) {
            return;
        }

        manageForm.addEventListener("submit", function (event) {
            event.preventDefault();
            if (state.saving) {
                return;
            }

            var badgeInput = $("notes-term-badge");
            var titleInput = $("notes-term-card-title");
            var descInput = $("notes-term-card-description");
            var buttonLabelInput = $("notes-term-button-label");
            var buttonUrlInput = $("notes-term-button-url");

            var payload = {
                term: String(term),
                badge: badgeInput ? badgeInput.value : "",
                title: titleInput ? titleInput.value : "",
                description: descInput ? descInput.value : "",
                buttonLabel: buttonLabelInput ? buttonLabelInput.value : "",
                buttonUrl: buttonUrlInput ? buttonUrlInput.value : ""
            };

            if (!payload.badge.trim() || !payload.title.trim() || !payload.description.trim() || !payload.buttonLabel.trim() || !payload.buttonUrl.trim()) {
                setFeedback("همه فیلدها را کامل وارد کنید.", "error");
                return;
            }

            setFeedback("", "");
            setSaving(true);

            request("addItem", "POST", payload).then(function (response) {
                if (handleUnauthorized(response)) {
                    throw new Error("برای مدیریت منابع باید وارد حساب مالک شوید.");
                }

                if (!response || !response.success || !response.item) {
                    throw new Error((response && response.error) || "ثبت کارت منبع انجام نشد.");
                }

                if (!state.termData) {
                    state.termData = {
                        term: term,
                        title: "",
                        description: "",
                        emptyMessage: "",
                        items: []
                    };
                }

                if (!Array.isArray(state.termData.items)) {
                    state.termData.items = [];
                }

                state.termData.items.unshift(response.item);
                renderTerm();

                if (titleInput) {
                    titleInput.value = "";
                }
                if (badgeInput) {
                    badgeInput.value = "";
                }
                if (descInput) {
                    descInput.value = "";
                }
                if (buttonLabelInput) {
                    buttonLabelInput.value = "";
                }
                if (buttonUrlInput) {
                    buttonUrlInput.value = "";
                }

                setFeedback(response.message || "کارت منبع ثبت شد.", "success");
            }).catch(function (error) {
                setFeedback(error && error.message ? error.message : "ثبت کارت منبع با خطا مواجه شد.", "error");
            }).finally(function () {
                setSaving(false);
            });
        });
    }

    function watchAuthChanges() {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }

        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }

            state.authKey = nextKey;
            loadTerm({ silent: true });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindManageForm();
        watchAuthChanges();
        loadTerm({ silent: false });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
