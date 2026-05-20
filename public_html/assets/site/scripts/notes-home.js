(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
    }

    function siteApi() {
        return window.Dent1402Site && typeof window.Dent1402Site === "object"
            ? window.Dent1402Site
            : null;
    }

    function resolveCohort() {
        var auth = authApi();
        var cohort = auth && typeof auth.resolvePageCohort === "function"
            ? auth.resolvePageCohort("notesCohort")
            : String(document.body && document.body.dataset ? document.body.dataset.notesCohort || "" : "").trim();

        if (!cohort || cohort === "main" || cohort === "dentistry-1402") {
            return "1402";
        }
        return cohort;
    }

    var pageCohort = resolveCohort();
    if (pageCohort !== "1402" && pageCohort !== "prosthesis-1402") {
        return;
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
        authKey: "",
        canManage: false,
        deletingTermId: 0,
        editingTermId: 0,
        loadError: "",
        loading: false,
        saving: false,
        terms: []
    };

    function manageSupported() {
        return pageCohort === "prosthesis-1402";
    }

    function homeCanManage() {
        return manageSupported() && state.canManage;
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function parseJsonResponse(response) {
        var site = siteApi();
        if (site && typeof site.parseJsonResponse === "function") {
            return site.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function consumeUnauthorized(payload, fallbackText) {
        var site = siteApi();
        if (site && typeof site.consumeUnauthorized === "function") {
            return !!site.consumeUnauthorized(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        var auth = authApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            return !!auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };
        var data = Object.assign({ cohort: pageCohort }, payload || {});
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);

        if (method === "GET") {
            Object.keys(data).forEach(function (key) {
                var value = data[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, data));
        }

        return fetch(url, options).then(parseJsonResponse);
    }

    function authSnapshotKey() {
        var auth = authApi();
        if (!auth || typeof auth.getState !== "function") {
            return "anon";
        }
        var snapshot = auth.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber
            ? String(snapshot.user.studentNumber)
            : "";
        return String(snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function applyPageCopy() {
        var isProsthesis = pageCohort === "prosthesis-1402";
        if (heading) {
            heading.textContent = isProsthesis ? "آرشیو منابع پروتز ۱۴۰۲" : "آرشیو منابع ورودی ۱۴۰۲";
        }
        if (subheading) {
            subheading.textContent = isProsthesis ? "هر ترم در صفحه جداگانه" : "ترم‌ها از storage مشترک بارگذاری می‌شوند";
        }
        if (backLink) {
            backLink.href = "/app/";
            backLink.textContent = isProsthesis ? "بازگشت به خانه پروتز" : "بازگشت به خانه";
        }
        if (kicker) {
            kicker.textContent = isProsthesis ? "آرشیو پروتز ۱۴۰۲" : "آرشیو ۱۴۰۲";
        }
        if (title) {
            title.textContent = isProsthesis
                ? "ترم موردنظر را برای دیدن جزوات و منابع انتخاب کن."
                : "ترم موردنظر را برای دیدن آرشیو منابع انتخاب کن.";
        }
        if (sectionKicker) {
            sectionKicker.textContent = "ترم‌ها";
        }
        if (sectionTitle) {
            sectionTitle.textContent = isProsthesis ? "صفحات مستقل ترمی پروتز ۱۴۰۲" : "صفحات مستقل ترمی ۱۴۰۲";
        }
        if (sectionCopy) {
            sectionCopy.textContent = isProsthesis
                ? "این فهرست از storage پروتز خوانده می‌شود و مدیریت آن در همین صفحه انجام می‌شود."
                : "این فهرست مستقیم از storage مشترک خوانده می‌شود و دیگر به کارت‌های ثابت داخل HTML وابسته نیست.";
        }
        if (footer) {
            footer.textContent = isProsthesis ? "ورودی ۱۴۰۲ پروتز تهران" : "ورودی ۱۴۰۲ دندانپزشکی تهران";
        }
        document.title = isProsthesis
            ? "آرشیو جزوات پروتز ۱۴۰۲ | انتخاب ترم"
            : "آرشیو منابع ۱۴۰۲ | انتخاب ترم";
    }

    function termId(term) {
        return Number(term && (term.id || term.term) || 0);
    }

    function termUrl(term) {
        var id = termId(term);
        var site = siteApi();
        var auth = authApi();
        var base = site && typeof site.buildUrl === "function"
            ? site.buildUrl("/notes/term/", { term: String(id) })
            : "/notes/term/?term=" + encodeURIComponent(String(id));

        if (pageCohort !== "1402") {
            if (auth && typeof auth.appendCohortQuery === "function") {
                return auth.appendCohortQuery(base, pageCohort);
            }
            return base + "&cohort=" + encodeURIComponent(pageCohort);
        }

        return base;
    }

    function setFeedback(text, kind) {
        if (!feedback) {
            return;
        }
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
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
            if (nodes[key]) {
                nodes[key].value = "";
            }
        });
    }

    function fillForm(term) {
        var nodes = inputs();
        if (nodes.title) nodes.title.value = term.title || "";
        if (nodes.kicker) nodes.kicker.value = term.kicker || "";
        if (nodes.description) nodes.description.value = term.description || "";
        if (nodes.emptyMessage) nodes.emptyMessage.value = term.emptyMessage || "";
    }

    function readPayload() {
        var nodes = inputs();
        return {
            title: nodes.title ? String(nodes.title.value || "").trim() : "",
            kicker: nodes.kicker ? String(nodes.kicker.value || "").trim() : "",
            description: nodes.description ? String(nodes.description.value || "").trim() : "",
            emptyMessage: nodes.emptyMessage ? String(nodes.emptyMessage.value || "").trim() : ""
        };
    }

    function findTerm(termIdValue) {
        return state.terms.filter(function (term) {
            return termId(term) === Number(termIdValue || 0);
        })[0] || null;
    }

    function setEditing(term) {
        state.editingTermId = term ? termId(term) : 0;
        if (!term) {
            clearForm();
            setFeedback("", "");
            render();
            return;
        }

        fillForm(term);
        setFeedback("ترم برای ویرایش آماده شد.", "success");
        render();
        if (manage && typeof manage.scrollIntoView === "function") {
            manage.scrollIntoView({ behavior: "smooth", block: "start" });
        }
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
        badge.textContent = term.kicker || "ترم";

        var titleNode = document.createElement("span");
        titleNode.className = "card-title";
        titleNode.textContent = term.title || "ترم بدون عنوان";

        var desc = document.createElement("span");
        desc.className = "card-desc";
        desc.textContent = term.description || "";

        var visual = document.createElement("span");
        visual.className = "action-card__visual";
        visual.setAttribute("aria-hidden", "true");
        visual.innerHTML = "<strong>" + toFaDigits(termId(term) || "?") + "</strong>";

        header.appendChild(badge);
        header.appendChild(titleNode);
        content.appendChild(header);
        content.appendChild(desc);

        link.appendChild(createChevron());
        link.appendChild(content);
        link.appendChild(visual);
        return link;
    }

    function buildCard(term) {
        if (!homeCanManage()) {
            var publicCard = createPrimaryLink(term);
            publicCard.classList.add("action-card", "action-card--link");
            return publicCard;
        }

        var id = termId(term);
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
        edit.dataset.termId = String(id);
        edit.textContent = state.editingTermId === id ? "در حال ویرایش" : "ویرایش";
        edit.disabled = state.saving || state.deletingTermId > 0;
        actions.appendChild(edit);

        var remove = document.createElement("button");
        remove.type = "button";
        remove.className = "notes-card-delete";
        remove.dataset.termDelete = "true";
        remove.dataset.termId = String(id);
        remove.textContent = state.deletingTermId === id ? "در حال حذف..." : "حذف";
        remove.disabled = state.saving || state.deletingTermId === id;
        actions.appendChild(remove);

        card.appendChild(actions);
        return card;
    }

    function render() {
        applyPageCopy();
        list.innerHTML = "";

        if (manage) {
            manage.hidden = !homeCanManage();
        }
        if (submit) {
            submit.disabled = state.saving;
            submit.textContent = state.saving
                ? "در حال ذخیره..."
                : (state.editingTermId ? "ذخیره تغییرات ترم" : "افزودن ترم");
        }

        if (!state.terms.length) {
            empty.hidden = false;
            if (state.loading) {
                empty.textContent = "در حال دریافت ترم‌ها...";
                return;
            }
            if (state.loadError) {
                empty.textContent = state.loadError;
                return;
            }
            empty.textContent = pageCohort === "prosthesis-1402"
                ? "هنوز ترمی برای پروتز ۱۴۰۲ ثبت نشده است."
                : "هنوز ترمی برای این آرشیو ثبت نشده است.";
            return;
        }

        empty.hidden = true;
        state.terms.forEach(function (term) {
            list.appendChild(buildCard(term));
        });
    }

    function loadTerms(options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        state.loadError = "";
        if (!(options && options.silent)) {
            render();
        }

        return request("terms", "GET", {}).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                state.canManage = false;
                render();
                return;
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "دریافت ترم‌ها ناموفق بود.");
            }

            state.terms = Array.isArray(payload.terms) ? payload.terms : [];
            state.canManage = manageSupported() && !!payload.canManage;
            if (state.editingTermId && !findTerm(state.editingTermId)) {
                state.editingTermId = 0;
                clearForm();
            }
            render();
        }).catch(function (error) {
            state.loadError = error && error.message ? error.message : "دریافت ترم‌ها با خطا مواجه شد.";
            state.canManage = false;
            if (manage) {
                manage.hidden = true;
            }
        }).finally(function () {
            state.loading = false;
            render();
        });
    }

    function saveTerm(event) {
        event.preventDefault();
        if (!homeCanManage() || state.saving) {
            return;
        }

        var payload = readPayload();
        if (!payload.title) {
            setFeedback("عنوان ترم را وارد کن.", "error");
            return;
        }

        state.saving = true;
        render();

        var editingId = state.editingTermId;
        var action = editingId ? "editTerm" : "addTerm";
        if (editingId) {
            payload.term = String(editingId);
        }

        request(action, "POST", payload).then(function (response) {
            if (consumeUnauthorized(response, "برای مدیریت این آرشیو باید وارد حساب مجاز شوید.")) {
                throw new Error("برای مدیریت این آرشیو باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ذخیره ترم انجام نشد.");
            }

            state.editingTermId = 0;
            clearForm();
            setFeedback(editingId ? "ترم ویرایش شد." : "ترم جدید اضافه شد.", "success");
            return loadTerms({ silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "ذخیره ترم انجام نشد.", "error");
        }).finally(function () {
            state.saving = false;
            render();
        });
    }

    function deleteTerm(termValue) {
        if (!homeCanManage() || state.deletingTermId > 0) {
            return;
        }

        var id = Number(termValue || 0);
        var term = findTerm(id);
        if (!term) {
            return;
        }
        if (!window.confirm("این ترم حذف شود؟")) {
            return;
        }

        state.deletingTermId = id;
        render();

        request("deleteTerm", "POST", { term: String(id) }).then(function (response) {
            if (consumeUnauthorized(response, "برای مدیریت این آرشیو باید وارد حساب مجاز شوید.")) {
                throw new Error("برای مدیریت این آرشیو باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف ترم انجام نشد.");
            }

            if (state.editingTermId === id) {
                state.editingTermId = 0;
                clearForm();
            }
            setFeedback("ترم حذف شد.", "success");
            return loadTerms({ silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "حذف ترم انجام نشد.", "error");
        }).finally(function () {
            state.deletingTermId = 0;
            render();
        });
    }

    function bindForm() {
        if (!form) {
            return;
        }
        form.addEventListener("submit", saveTerm);
    }

    function bindList() {
        list.addEventListener("click", function (event) {
            var editButton = event.target.closest("[data-term-edit]");
            if (editButton) {
                event.preventDefault();
                if (!homeCanManage()) {
                    return;
                }
                setEditing(findTerm(Number(editButton.getAttribute("data-term-id") || 0)));
                return;
            }

            var deleteButton = event.target.closest("[data-term-delete]");
            if (!deleteButton) {
                return;
            }

            event.preventDefault();
            if (!homeCanManage()) {
                return;
            }
            deleteTerm(Number(deleteButton.getAttribute("data-term-id") || 0));
        });
    }

    function watchAuthChanges() {
        var auth = authApi();
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }

        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }
            state.authKey = nextKey;
            loadTerms({ silent: true });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindForm();
        bindList();
        watchAuthChanges();
        loadTerms({ silent: false });
    }

    boot();
})();
