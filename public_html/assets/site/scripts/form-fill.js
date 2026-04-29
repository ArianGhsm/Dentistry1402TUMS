(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var params = new URLSearchParams(window.location.search);
    var formId = String(params.get("form") || params.get("formId") || "").trim();

    var boot = $("fill-boot");
    var login = $("fill-login");
    var notFound = $("fill-not-found");
    var stage = $("fill-stage");
    var loginLink = $("fill-login-link");
    var refreshBtn = $("fill-refresh");
    var topTitle = $("fill-top-title");
    var topCopy = $("fill-top-copy");
    var kindChip = $("fill-kind");
    var titleEl = $("fill-title");
    var statusChip = $("fill-status");
    var descriptionEl = $("fill-description");
    var formEl = $("fill-form");
    var guestBox = $("fill-guest-box");
    var guestNameInput = $("fill-guest-name");
    var guestPhoneInput = $("fill-guest-phone");
    var fieldsRoot = $("fill-fields");
    var feedback = $("fill-feedback");
    var submitBtn = $("fill-submit");
    var resultsCard = $("fill-results-card");
    var resultsTotal = $("fill-results-total");
    var resultsNote = $("fill-results-note");
    var resultsList = $("fill-results-list");
    var copyLinkBtn = $("fill-copy-link");
    var shareLinkInput = $("fill-share-link");
    var toastEl = $("fill-toast");

    var state = {
        viewer: null,
        form: null,
        toastTimer: 0,
        loading: false
    };

    function parseApiResponse(response) {
        return response.text().then(function (text) {
            var payload = null;
            if (text) {
                try {
                    payload = JSON.parse(text);
                } catch (_error) {
                    payload = null;
                }
            }
            if (!payload || typeof payload !== "object") {
                payload = {
                    success: false,
                    error: response.status >= 500 ? "خطای داخلی سرور رخ داد." : "پاسخ نامعتبر از سرور دریافت شد."
                };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function apiGet(action, paramsObj) {
        var query = new URLSearchParams(Object.assign({ action: action }, paramsObj || {}));
        return fetch("/api/forms_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/forms_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: body
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function showStage(name) {
        boot.hidden = name !== "boot";
        login.hidden = name !== "login";
        notFound.hidden = name !== "not-found";
        stage.hidden = name !== "stage";
    }

    function showToast(text) {
        if (!toastEl || !text) return;
        toastEl.textContent = text;
        toastEl.classList.add("is-show");
        window.clearTimeout(state.toastTimer);
        state.toastTimer = window.setTimeout(function () {
            toastEl.classList.remove("is-show");
        }, 2200);
    }

    function setFeedback(text, kind) {
        feedback.textContent = text || "";
        feedback.className = "forms-feedback" + (kind ? " is-" + kind : "");
    }

    function normalizeDigits(value) {
        return String(value || "").replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
            var code = char.charCodeAt(0);
            if (code >= 0x06F0 && code <= 0x06F9) return String(code - 0x06F0);
            return String(code - 0x0660);
        });
    }

    function guestKey() {
        var key = "";
        try {
            key = localStorage.getItem("dent1402_forms_guest_key") || "";
            if (!key) {
                key = "guest-" + Date.now().toString(36) + "-" + Math.floor(Math.random() * 1000000).toString(36);
                localStorage.setItem("dent1402_forms_guest_key", key);
            }
        } catch (_error) {
            key = "guest-" + Date.now().toString(36) + "-" + Math.floor(Math.random() * 1000000).toString(36);
        }
        return key;
    }

    function copyText(value) {
        if (!value) return Promise.reject(new Error("empty"));
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }
        return new Promise(function (resolve, reject) {
            try {
                var helper = document.createElement("textarea");
                helper.value = value;
                helper.setAttribute("readonly", "");
                helper.style.position = "fixed";
                helper.style.opacity = "0";
                document.body.appendChild(helper);
                helper.select();
                document.execCommand("copy");
                helper.remove();
                resolve();
            } catch (error) {
                reject(error);
            }
        });
    }

    function optionText(field, optionId) {
        var options = Array.isArray(field.options) ? field.options : [];
        for (var i = 0; i < options.length; i++) {
            if (String(options[i].id) === String(optionId)) {
                return String(options[i].text || optionId);
            }
        }
        return String(optionId);
    }

    function syncChoiceStyles(card) {
        Array.prototype.forEach.call(card.querySelectorAll(".forms-choice"), function (label) {
            var input = label.querySelector("input");
            label.classList.toggle("is-selected", !!(input && input.checked));
        });
    }

    function renderQuestion(field) {
        var card = document.createElement("section");
        card.className = "forms-card forms-fill-question";
        card.dataset.fieldId = String(field.id || "");
        card.dataset.fieldType = String(field.type || "short_text");

        var head = document.createElement("div");
        head.className = "forms-card-head";
        var copy = document.createElement("div");
        var title = document.createElement("h2");
        title.textContent = String(field.label || "پرسش") + (field.required ? " *" : "");
        copy.appendChild(title);
        if (field.help) {
            var help = document.createElement("p");
            help.className = "forms-muted";
            help.textContent = String(field.help);
            copy.appendChild(help);
        }
        head.appendChild(copy);
        card.appendChild(head);

        var type = String(field.type || "short_text");
        if (["single_choice", "multiple_choice", "linear_scale"].indexOf(type) !== -1) {
            var inputType = type === "multiple_choice" ? "checkbox" : "radio";
            (Array.isArray(field.options) ? field.options : []).forEach(function (option) {
                var label = document.createElement("label");
                label.className = "forms-choice";
                var input = document.createElement("input");
                input.type = inputType;
                input.name = "answer-" + String(field.id || "");
                input.value = String(option.id || "");
                input.addEventListener("change", function () {
                    syncChoiceStyles(card);
                });
                label.appendChild(input);
                var text = document.createElement("span");
                text.textContent = String(option.text || option.id || "");
                label.appendChild(text);
                card.appendChild(label);
            });
            if (type === "linear_scale" && field.scale) {
                var scaleHelp = document.createElement("p");
                scaleHelp.className = "forms-muted";
                scaleHelp.textContent = [field.scale.minLabel || "", field.scale.maxLabel || ""].filter(Boolean).join(" / ");
                if (scaleHelp.textContent) {
                    card.appendChild(scaleHelp);
                }
            }
            return card;
        }

        if (type === "dropdown") {
            var selectWrap = document.createElement("label");
            selectWrap.className = "forms-field";
            var select = document.createElement("select");
            select.dataset.answerInput = "1";
            var empty = document.createElement("option");
            empty.value = "";
            empty.textContent = "انتخاب کنید";
            select.appendChild(empty);
            (Array.isArray(field.options) ? field.options : []).forEach(function (option) {
                var opt = document.createElement("option");
                opt.value = String(option.id || "");
                opt.textContent = String(option.text || option.id || "");
                select.appendChild(opt);
            });
            selectWrap.appendChild(select);
            card.appendChild(selectWrap);
            return card;
        }

        var labelWrap = document.createElement("label");
        labelWrap.className = "forms-field";
        var input;
        if (type === "paragraph") {
            input = document.createElement("textarea");
            input.rows = 5;
        } else {
            input = document.createElement("input");
            input.type = type === "email" ? "email"
                : type === "phone" ? "tel"
                    : type === "number" ? "number"
                        : type === "date" ? "date"
                            : type === "time" ? "time"
                                : "text";
            if (type === "phone" || type === "number") {
                input.dir = "ltr";
                input.setAttribute("data-latin-digits", "true");
            }
        }
        input.dataset.answerInput = "1";
        input.maxLength = type === "paragraph" ? 4000 : 700;
        labelWrap.appendChild(input);
        card.appendChild(labelWrap);
        return card;
    }

    function renderResults(form) {
        var results = form && form.results ? form.results : null;
        if (!form || form.kind !== "poll" || !results) {
            resultsCard.hidden = true;
            return;
        }

        resultsCard.hidden = false;
        resultsList.innerHTML = "";
        if (!results.visible) {
            resultsTotal.textContent = "مخفی";
            resultsNote.textContent = String(results.hiddenReason || "نتایج فعلاً مخفی است.");
            return;
        }

        var total = Number(results.totalResponses || 0);
        resultsTotal.textContent = total.toLocaleString("fa-IR") + " پاسخ";
        resultsNote.textContent = "";
        (Array.isArray(results.options) ? results.options : []).forEach(function (option) {
            var item = document.createElement("div");
            item.className = "forms-results-item";
            var head = document.createElement("div");
            head.className = "forms-results-item__head";
            var title = document.createElement("strong");
            title.textContent = String(option.text || "");
            head.appendChild(title);
            var meta = document.createElement("span");
            meta.textContent = Number(option.count || 0).toLocaleString("fa-IR") + " • " + Number(option.percent || 0).toLocaleString("fa-IR") + "%";
            head.appendChild(meta);
            item.appendChild(head);
            var bar = document.createElement("div");
            bar.className = "forms-result-bar";
            var fill = document.createElement("i");
            fill.style.width = Math.max(0, Math.min(100, Number(option.percent || 0))) + "%";
            bar.appendChild(fill);
            item.appendChild(bar);
            resultsList.appendChild(item);
        });
    }

    function renderForm(payload) {
        var form = payload.form || null;
        state.form = form;
        state.viewer = payload.viewer || null;
        if (!form) return;

        topTitle.textContent = String(form.title || "فرم");
        topCopy.textContent = String(form.kindLabel || "فرم") + " • " + String(form.statusLabel || "");
        kindChip.textContent = String(form.kindLabel || "فرم");
        titleEl.textContent = String(form.title || "فرم");
        statusChip.textContent = String(form.statusLabel || "—");
        descriptionEl.textContent = String(form.description || "");
        descriptionEl.hidden = !String(form.description || "").trim();
        shareLinkInput.value = String(form.shareUrl || window.location.href);

        var isGuest = !state.viewer;
        var settings = form.settings || {};
        guestBox.hidden = !isGuest;
        guestNameInput.required = isGuest && settings.collectGuestName !== false;
        guestPhoneInput.required = isGuest && !!settings.collectGuestPhone;
        guestNameInput.closest(".forms-field").hidden = !(isGuest && settings.collectGuestName !== false);
        guestPhoneInput.closest(".forms-field").hidden = !(isGuest && !!settings.collectGuestPhone);

        fieldsRoot.innerHTML = "";
        (Array.isArray(form.fields) ? form.fields : []).forEach(function (field) {
            fieldsRoot.appendChild(renderQuestion(field));
        });

        submitBtn.disabled = !(form.permissions && form.permissions.canSubmit);
        if (submitBtn.disabled) {
            setFeedback("ثبت پاسخ برای این فرم فعال نیست.", "");
        } else {
            setFeedback("", "");
        }
        renderResults(form);
        showStage("stage");
    }

    function collectAnswers() {
        var answers = {};
        if (!state.form) return answers;
        (Array.isArray(state.form.fields) ? state.form.fields : []).forEach(function (field) {
            var fieldId = String(field.id || "");
            var type = String(field.type || "short_text");
            var card = fieldsRoot.querySelector('[data-field-id="' + fieldId.replace(/"/g, "") + '"]');
            if (!card) return;
            if (type === "multiple_choice") {
                answers[fieldId] = Array.prototype.slice.call(card.querySelectorAll("input:checked")).map(function (input) {
                    return String(input.value || "");
                });
                return;
            }
            if (["single_choice", "linear_scale"].indexOf(type) !== -1) {
                var selected = card.querySelector("input:checked");
                answers[fieldId] = selected ? String(selected.value || "") : "";
                return;
            }
            if (type === "dropdown") {
                var select = card.querySelector("select[data-answer-input]");
                answers[fieldId] = select ? String(select.value || "") : "";
                return;
            }
            var input = card.querySelector("[data-answer-input]");
            var value = input ? String(input.value || "") : "";
            if (type === "phone" || type === "number") {
                value = normalizeDigits(value);
            }
            answers[fieldId] = value;
        });
        return answers;
    }

    async function loadForm() {
        if (!formId) {
            showStage("not-found");
            return;
        }
        if (state.loading) return;
        state.loading = true;
        refreshBtn.disabled = true;
        try {
            var response = await apiGet("get", { form: formId });
            if (response && response.httpStatus === 401) {
                loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
                showStage("login");
                return;
            }
            if (response && response.httpStatus === 404) {
                showStage("not-found");
                return;
            }
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "بارگذاری فرم انجام نشد.");
            }
            renderForm(response);
        } catch (error) {
            setFeedback(error && error.message ? error.message : "بارگذاری انجام نشد.", "error");
            showStage("stage");
        } finally {
            state.loading = false;
            refreshBtn.disabled = false;
        }
    }

    async function submitForm(event) {
        event.preventDefault();
        if (!state.form || submitBtn.disabled) return;
        submitBtn.disabled = true;
        setFeedback("در حال ثبت پاسخ...", "");
        try {
            var payload = {
                formId: String(state.form.id || formId),
                answers: JSON.stringify(collectAnswers()),
                guestKey: guestKey(),
                guestName: String(guestNameInput.value || "").trim(),
                guestPhone: normalizeDigits(guestPhoneInput.value || "")
            };
            var response = await apiPost("submit", payload);
            if (response && response.httpStatus === 401) {
                loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
                showStage("login");
                return;
            }
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "ثبت پاسخ انجام نشد.");
            }
            renderForm({ form: response.form, viewer: state.viewer });
            setFeedback(String(response.message || "پاسخ ثبت شد."), "success");
            showToast("پاسخ ثبت شد.");
        } catch (error) {
            setFeedback(error && error.message ? error.message : "ثبت پاسخ انجام نشد.", "error");
        } finally {
            if (state.form && state.form.permissions && state.form.permissions.canSubmit) {
                submitBtn.disabled = false;
            }
        }
    }

    copyLinkBtn.addEventListener("click", function () {
        copyText(String(shareLinkInput.value || "")).then(function () {
            showToast("لینک کپی شد.");
        }).catch(function () {
            showToast("کپی لینک انجام نشد.");
        });
    });

    refreshBtn.addEventListener("click", loadForm);
    formEl.addEventListener("submit", submitForm);

    showStage("boot");
    window.Dent1402Auth.onChange(function (detail) {
        if (detail && (detail.status === "session-restoring" || detail.status === "logging-out")) {
            showStage("boot");
            return;
        }
        loadForm();
    });
})();
