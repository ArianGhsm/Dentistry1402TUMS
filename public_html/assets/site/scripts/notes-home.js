(function () {
    "use strict";

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var pageCohort = authApi && typeof authApi.resolvePageCohort === "function"
        ? authApi.resolvePageCohort("notesCohort")
        : "1402";

    if (pageCohort !== "prosthesis-1402") {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var list = $("notes-home-list");
    var empty = $("notes-home-empty");
    var manage = $("notes-home-manage");
    var form = $("notes-home-form");
    var feedback = $("notes-home-feedback");
    var submit = $("notes-home-submit");
    var heading = $("notes-home-heading");
    var subheading = $("notes-home-subheading");
    var backLink = $("notes-home-back-link");
    var kicker = $("notes-home-kicker");
    var title = $("notes-home-title");
    var sectionKicker = $("notes-home-section-kicker");
    var sectionTitle = $("notes-home-section-title");
    var sectionCopy = $("notes-home-section-copy");
    var footer = $("notes-home-footer");

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

        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (result) {
                result.httpStatus = response.status;
                return result;
            });
        });
    }

    function setFeedback(text, kind) {
        if (!feedback) return;
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
    }

    function termUrl(term) {
        var base = "/notes/term/?term=" + encodeURIComponent(String(term.id || ""));
        if (authApi && typeof authApi.appendCohortQuery === "function") {
            return authApi.appendCohortQuery(base, "prosthesis-1402");
        }
        return base + "&cohort=prosthesis-1402";
    }

    function inputs() {
        return {
            title: $("notes-home-term-title"),
            kicker: $("notes-home-term-kicker"),
            description: $("notes-home-term-description"),
            emptyMessage: $("notes-home-term-empty-message")
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

    function createChevron() {
        var chevron = document.createElement("span");
        chevron.className = "action-card__chevron";
        chevron.setAttribute("aria-hidden", "true");
        return chevron;
    }

    function createPrimaryLink(term) {
        var link = document.createElement("a");
        link.className = "action-card__primary";
        link.href = termUrl(term);

        var content = document.createElement("span");
        content.className = "card-content";

        var header = document.createElement("span");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = term.kicker || "پروتز ۱۴۰۲";

        var titleEl = document.createElement("span");
        titleEl.className = "card-title";
        titleEl.textContent = term.title || "ترم بدون عنوان";

        var desc = document.createElement("span");
        desc.className = "card-desc";
        desc.textContent = term.description || "";

        var visual = document.createElement("span");
        visual.className = "action-card__visual";
        visual.setAttribute("aria-hidden", "true");
        visual.innerHTML = "<strong>" + String(term.id || "?") + "</strong>";

        header.appendChild(badge);
        header.appendChild(titleEl);
        content.appendChild(header);
        content.appendChild(desc);

        link.appendChild(createChevron());
        link.appendChild(content);
        link.appendChild(visual);
        return link;
    }

    function buildCard(term) {
        var termId = Number(term.id || 0);
        if (!state.canManage) {
            var publicCard = createPrimaryLink(term);
            publicCard.classList.add("action-card", "action-card--link");
            return publicCard;
        }

        var card = document.createElement("article");
        card.className = "action-card";
        card.appendChild(createPrimaryLink(term));

        var actions = document.createElement("div");
        actions.className = "notes-card-actions";

        var open = document.createElement("a");
        open.className = "card-btn";
        open.href = termUrl(term);
        open.textContent = "ورود به صفحه";
        actions.appendChild(open);

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

        card.appendChild(actions);
        return card;
    }

    function applyProsthesisCopy() {
        if (heading) heading.textContent = "آرشیو منابع پروتز ۱۴۰۲";
        if (subheading) subheading.textContent = "هر ترم در صفحه‌ای جداگانه";
        if (backLink) backLink.href = "/app/";
        if (backLink) backLink.textContent = "بازگشت به خانه پروتز";
        if (kicker) kicker.textContent = "آرشیو پروتز ۱۴۰۲";
        if (title) title.textContent = "ترم موردنظر را برای دیدن جزوات و منابع انتخاب کن.";
        if (sectionKicker) sectionKicker.textContent = "ترم‌ها";
        if (sectionTitle) sectionTitle.textContent = "صفحات مستقل ترمی پروتز ۱۴۰۲";
        if (sectionCopy) sectionCopy.textContent = "این لیست از storage خوانده می‌شود و به shell جداگانه وابسته نیست.";
        if (footer) footer.textContent = "پروتز ۱۴۰۲";
        document.title = "آرشیو جزوات پروتز ۱۴۰۲ | انتخاب ترم";
    }

    function render() {
        applyProsthesisCopy();
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

    function saveTerm(event) {
        event.preventDefault();
        if (state.saving) return;

        var payload = readPayload();
        var editingTermId = state.editingTermId;
        if (!payload.title) {
            setFeedback("عنوان ترم را وارد کن.", "error");
            return;
        }

        state.saving = true;
        render();
        var action = editingTermId ? "updateTerm" : "createTerm";
        if (editingTermId) {
            payload.termId = String(editingTermId);
        }

        request(action, "POST", payload).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ذخیره ترم انجام نشد.");
            }
            setEditing(null);
            setFeedback(editingTermId ? "ترم ویرایش شد." : "ترم جدید اضافه شد.", "success");
            return loadTerms();
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "ذخیره ترم انجام نشد.", "error");
        }).finally(function () {
            state.saving = false;
            render();
        });
    }

    function deleteTerm(termId) {
        var term = findTerm(termId);
        if (!term || state.deletingTermId > 0) {
            return;
        }
        if (!window.confirm("این ترم حذف شود؟")) {
            return;
        }

        state.deletingTermId = termId;
        render();
        request("deleteTerm", "POST", { termId: String(termId) }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف ترم انجام نشد.");
            }
            if (state.editingTermId === termId) {
                setEditing(null);
            }
            setFeedback("ترم حذف شد.", "success");
            return loadTerms();
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "حذف ترم انجام نشد.", "error");
        }).finally(function () {
            state.deletingTermId = 0;
            render();
        });
    }

    if (form) {
        form.addEventListener("submit", saveTerm);
    }

    list.addEventListener("click", function (event) {
        var editButton = event.target.closest("[data-term-edit]");
        if (editButton) {
            event.preventDefault();
            setEditing(findTerm(Number(editButton.getAttribute("data-term-id") || 0)));
            return;
        }
        var deleteButton = event.target.closest("[data-term-delete]");
        if (deleteButton) {
            event.preventDefault();
            deleteTerm(Number(deleteButton.getAttribute("data-term-id") || 0));
        }
    });

    loadTerms();
})();
