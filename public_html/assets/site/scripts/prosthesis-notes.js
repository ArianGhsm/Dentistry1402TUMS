(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var list = $("prosthesis-term-list");
    var empty = $("prosthesis-term-empty");
    var manage = $("prosthesis-term-manage");
    var form = $("prosthesis-term-form");
    var feedback = $("prosthesis-term-feedback");
    var submit = $("prosthesis-term-submit");

    if (!list || !empty) {
        return;
    }

    var state = {
        terms: [],
        canManage: false,
        loading: false,
        saving: false,
        deletingTermId: 0,
        editingTermId: 0
    };

    function parseJsonResponse(response) {
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };
        var data = Object.assign({ cohort: "prosthesis-1402" }, payload || {});
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);

        if (method === "GET") {
            Object.keys(data).forEach(function (key) {
                if (String(data[key] || "") !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(data[key]));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, data));
        }

        return fetch(url, options).then(parseJsonResponse);
    }

    function setFeedback(text, kind) {
        if (!feedback) return;
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
    }

    function inputs() {
        return {
            title: $("prosthesis-term-title"),
            kicker: $("prosthesis-term-kicker"),
            description: $("prosthesis-term-description"),
            emptyMessage: $("prosthesis-term-empty-message")
        };
    }

    function clearForm() {
        var nodes = inputs();
        Object.keys(nodes).forEach(function (key) {
            if (nodes[key]) nodes[key].value = "";
        });
    }

    function readPayload() {
        var nodes = inputs();
        return {
            title: nodes.title ? nodes.title.value.trim() : "",
            kicker: nodes.kicker ? nodes.kicker.value.trim() : "",
            description: nodes.description ? nodes.description.value.trim() : "",
            emptyMessage: nodes.emptyMessage ? nodes.emptyMessage.value.trim() : ""
        };
    }

    function findTerm(termId) {
        return state.terms.filter(function (term) {
            return Number(term.id || 0) === Number(termId || 0);
        })[0] || null;
    }

    function fillForm(term) {
        var nodes = inputs();
        if (nodes.title) nodes.title.value = term.title || "";
        if (nodes.kicker) nodes.kicker.value = term.kicker || "";
        if (nodes.description) nodes.description.value = term.description || "";
        if (nodes.emptyMessage) nodes.emptyMessage.value = term.emptyMessage || "";
    }

    function setEditing(term) {
        state.editingTermId = term ? Number(term.id || 0) : 0;
        if (term) {
            fillForm(term);
            setFeedback("ترم برای ویرایش آماده شد.", "success");
            if (manage && typeof manage.scrollIntoView === "function") {
                manage.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        } else {
            clearForm();
            setFeedback("", "");
        }
        render();
    }

    function termUrl(term) {
        return "/prosthesis-1402/term/?term=" + encodeURIComponent(String(term.id || ""));
    }

    function buildCard(term) {
        var termId = Number(term.id || 0);
        var card = document.createElement("article");
        card.className = "action-card";

        var content = document.createElement("div");
        content.className = "card-content";

        var header = document.createElement("div");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = term.kicker || "پروتز ۱۴۰۲";

        var title = document.createElement("h3");
        title.className = "card-title";
        title.textContent = term.title || "ترم بدون عنوان";

        var desc = document.createElement("p");
        desc.className = "card-desc";
        desc.textContent = term.description || "";

        header.appendChild(badge);
        header.appendChild(title);
        content.appendChild(header);
        content.appendChild(desc);

        var actions = document.createElement("div");
        actions.className = "notes-card-actions";

        var open = document.createElement("a");
        open.className = "card-btn";
        open.href = termUrl(term);
        open.textContent = "ورود به صفحه";
        actions.appendChild(open);

        if (state.canManage) {
            var edit = document.createElement("button");
            edit.type = "button";
            edit.className = "notes-card-edit";
            edit.dataset.termEdit = "true";
            edit.dataset.termId = String(termId);
            edit.textContent = state.editingTermId === termId ? "در حال ویرایش" : "ویرایش";
            edit.disabled = state.saving || state.deletingTermId > 0;
            actions.appendChild(edit);

            var remove = document.createElement("button");
            remove.type = "button";
            remove.className = "notes-card-delete";
            remove.dataset.termDelete = "true";
            remove.dataset.termId = String(termId);
            remove.textContent = state.deletingTermId === termId ? "در حال حذف..." : "حذف";
            remove.disabled = state.saving || state.deletingTermId === termId;
            actions.appendChild(remove);
        }

        card.appendChild(content);
        card.appendChild(actions);
        return card;
    }

    function render() {
        list.innerHTML = "";
        if (manage) manage.hidden = !state.canManage;
        if (submit) {
            submit.disabled = state.saving;
            submit.textContent = state.saving
                ? "در حال ذخیره..."
                : (state.editingTermId ? "ذخیره تغییرات ترم" : "افزودن ترم");
        }

        if (!state.terms.length) {
            empty.hidden = false;
            empty.textContent = state.loading ? "در حال دریافت ترم‌های پروتز..." : "هنوز ترمی برای پروتز ۱۴۰۲ ثبت نشده است.";
            return;
        }

        empty.hidden = true;
        state.terms.forEach(function (term) {
            list.appendChild(buildCard(term));
        });
    }

    function loadTerms() {
        state.loading = true;
        render();
        return request("terms", "GET", {}).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "دریافت ترم‌ها ناموفق بود.");
            }
            state.terms = Array.isArray(payload.terms) ? payload.terms : [];
            state.canManage = !!payload.canManage;
        }).catch(function (error) {
            empty.hidden = false;
            empty.textContent = error && error.message ? error.message : "دریافت ترم‌ها با خطا مواجه شد.";
            state.canManage = false;
        }).finally(function () {
            state.loading = false;
            render();
        });
    }

    if (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            if (state.saving) return;

            var payload = readPayload();
            if (!payload.title) {
                setFeedback("عنوان ترم را وارد کنید.", "error");
                return;
            }
            if (state.editingTermId) {
                payload.term = String(state.editingTermId);
            }

            state.saving = true;
            setFeedback("", "");
            render();
            request(state.editingTermId ? "editTerm" : "addTerm", "POST", payload).then(function (response) {
                if (!response || !response.success || !response.term) {
                    throw new Error((response && response.error) || "ذخیره ترم انجام نشد.");
                }
                var saved = response.term;
                var replaced = false;
                state.terms = state.terms.map(function (term) {
                    if (Number(term.id || 0) === Number(saved.id || 0)) {
                        replaced = true;
                        return saved;
                    }
                    return term;
                });
                if (!replaced) {
                    state.terms.push(saved);
                }
                state.terms.sort(function (left, right) {
                    return Number(left.id || 0) - Number(right.id || 0);
                });
                state.editingTermId = 0;
                clearForm();
                setFeedback(response.message || "ترم ذخیره شد.", "success");
            }).catch(function (error) {
                setFeedback(error && error.message ? error.message : "ذخیره ترم با خطا مواجه شد.", "error");
            }).finally(function () {
                state.saving = false;
                render();
            });
        });
    }

    list.addEventListener("click", function (event) {
        var edit = event.target && event.target.closest ? event.target.closest("[data-term-edit='true']") : null;
        var remove = event.target && event.target.closest ? event.target.closest("[data-term-delete='true']") : null;
        if (edit) {
            setEditing(findTerm(edit.dataset.termId));
            return;
        }
        if (!remove || state.deletingTermId) return;

        var termId = Number(remove.dataset.termId || 0);
        if (!termId || !window.confirm("این ترم و همه کارت‌های داخل آن حذف شود؟")) {
            return;
        }

        state.deletingTermId = termId;
        render();
        request("deleteTerm", "POST", { term: String(termId) }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف ترم انجام نشد.");
            }
            state.terms = state.terms.filter(function (term) {
                return Number(term.id || 0) !== termId;
            });
            if (state.editingTermId === termId) {
                setEditing(null);
            }
            setFeedback(response.message || "ترم حذف شد.", "success");
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "حذف ترم با خطا مواجه شد.", "error");
        }).finally(function () {
            state.deletingTermId = 0;
            render();
        });
    });

    loadTerms();
})();
