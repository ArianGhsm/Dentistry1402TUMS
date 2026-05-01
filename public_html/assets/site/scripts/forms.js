(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var boot = $("forms-boot");
    var login = $("forms-login");
    var app = $("forms-app");
    var loginLink = $("forms-login-link");
    var viewerCopy = $("forms-viewer-copy");
    var formsCount = $("forms-count");
    var formsOpenCount = $("forms-open-count");
    var formsResponseCount = $("forms-response-count");
    var tabs = Array.prototype.slice.call(document.querySelectorAll("[data-forms-tab]"));
    var panels = {
        builder: $("forms-builder-panel"),
        list: $("forms-list-panel"),
        responses: $("forms-responses-panel")
    };

    var builder = $("forms-builder");
    var builderTitle = $("forms-builder-title");
    var resetBuilderBtn = $("forms-reset-builder");
    var kindInput = $("forms-kind");
    var templateInput = $("forms-template");
    var titleInput = $("forms-title-input");
    var descriptionInput = $("forms-description-input");
    var statusInput = $("forms-status");
    var audienceInput = $("forms-audience");
    var resultVisibilityInput = $("forms-result-visibility");
    var startAtInput = $("forms-start-at");
    var endAtInput = $("forms-end-at");
    var allowedStudentsInput = $("forms-allowed-students");
    var managerStudentsInput = $("forms-manager-students");
    var allowRepresentativeManageInput = $("forms-allow-representative-manage");
    var allowGuestInput = $("forms-allow-guest");
    var collectGuestNameInput = $("forms-collect-guest-name");
    var collectGuestPhoneInput = $("forms-collect-guest-phone");
    var limitOneInput = $("forms-limit-one");
    var allowEditResponseInput = $("forms-allow-edit-response");
    var allowCreatorSubmitInput = $("forms-allow-creator-submit");
    var anonymousResponsesInput = $("forms-anonymous-responses");
    var exportColumnsRoot = $("forms-export-columns");
    var exportAllFieldsInput = $("forms-export-all-fields");
    var exportFieldIdsInput = $("forms-export-field-ids");
    var fieldsEditor = $("forms-fields-editor");
    var addFieldBtn = $("forms-add-field");
    var saveBtn = $("forms-save");
    var submitTitle = $("forms-submit-title");
    var feedback = $("forms-feedback");

    var reloadBtn = $("forms-reload");
    var formsEmpty = $("forms-empty");
    var formsList = $("forms-list");
    var responsesTitle = $("forms-responses-title");
    var responsesEmpty = $("forms-responses-empty");
    var responsesList = $("forms-responses-list");
    var exportLink = $("forms-export-link");
    var closeResponsesBtn = $("forms-close-responses");
    var toastEl = $("forms-toast");

    var state = {
        viewer: null,
        canCreate: false,
        forms: [],
        editingId: "",
        fields: [],
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

    function apiGet(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch("/api/forms_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams();
        body.append("action", action);
        Object.keys(payload || {}).forEach(function (key) {
            body.append(key, payload[key]);
        });
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

    function safeAuthApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    }

    function consumeUnauthorized(payload) {
        var auth = safeAuthApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            try {
                if (auth.handleUnauthorizedPayload(payload, "نشست شما منقضی شده است.")) {
                    return true;
                }
            } catch (_error) {
                // Continue fallback.
            }
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
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
        if (!feedback) return;
        feedback.textContent = text || "";
        feedback.className = "forms-feedback" + (kind ? " is-" + kind : "");
    }

    function showStage(name) {
        boot.hidden = name !== "boot";
        login.hidden = name !== "login";
        app.hidden = name !== "app";
    }

    function setTab(name) {
        Object.keys(panels).forEach(function (key) {
            if (panels[key]) {
                panels[key].hidden = key !== name;
            }
        });
        tabs.forEach(function (tab) {
            tab.classList.toggle("is-active", tab.getAttribute("data-forms-tab") === name);
        });
    }

    function formatDateTime(ts) {
        var value = Number(ts || 0);
        if (!value) return "—";
        return new Date(value * 1000).toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function toDatetimeLocal(ts) {
        var value = Number(ts || 0);
        if (!value) return "";
        var date = new Date(value * 1000);
        var pad = function (item) {
            return String(item).padStart(2, "0");
        };
        return [
            date.getFullYear(),
            "-",
            pad(date.getMonth() + 1),
            "-",
            pad(date.getDate()),
            "T",
            pad(date.getHours()),
            ":",
            pad(date.getMinutes())
        ].join("");
    }

    function toIsoValue(value) {
        var text = String(value || "").trim();
        if (!text) return "";
        var date = new Date(text);
        return Number.isFinite(date.getTime()) ? date.toISOString() : "";
    }

    function normalizeDigits(value) {
        return String(value || "").replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
            var code = char.charCodeAt(0);
            if (code >= 0x06F0 && code <= 0x06F9) return String(code - 0x06F0);
            return String(code - 0x0660);
        });
    }

    function selectedExportColumns() {
        if (!exportColumnsRoot) return [];
        return Array.prototype.slice.call(exportColumnsRoot.querySelectorAll("input[type='checkbox']:checked")).map(function (input) {
            return String(input.value || "");
        }).filter(Boolean);
    }

    function setExportSettings(exportSettings) {
        exportSettings = exportSettings || {};
        var columns = Array.isArray(exportSettings.identityColumns) ? exportSettings.identityColumns : [];
        if (!columns.length) {
            columns = ["index", "responseId", "submittedAt", "participantKind", "name", "studentNumber", "roleLabel", "phone"];
        }
        if (exportColumnsRoot) {
            Array.prototype.forEach.call(exportColumnsRoot.querySelectorAll("input[type='checkbox']"), function (input) {
                input.checked = columns.indexOf(String(input.value || "")) !== -1;
            });
        }
        if (exportAllFieldsInput) {
            exportAllFieldsInput.checked = exportSettings.includeAllFields !== false;
        }
        if (exportFieldIdsInput) {
            exportFieldIdsInput.value = Array.isArray(exportSettings.fieldIds) ? exportSettings.fieldIds.join("\n") : "";
        }
    }

    function collectExportSettings() {
        return {
            identityColumns: selectedExportColumns(),
            includeAllFields: exportAllFieldsInput ? !!exportAllFieldsInput.checked : true,
            fieldIds: String(exportFieldIdsInput && exportFieldIdsInput.value || "").split(/[\s,،;]+/).filter(Boolean)
        };
    }

    function uniqueFieldId() {
        return "q-" + Date.now().toString(36) + "-" + Math.floor(Math.random() * 1000).toString(36);
    }

    function option(text, index) {
        return {
            id: "opt-" + String(index || 1),
            text: text || "گزینه"
        };
    }

    function newField(type, label) {
        var field = {
            id: uniqueFieldId(),
            type: type || "short_text",
            label: label || "پرسش",
            help: "",
            required: true,
            options: [],
            rows: [],
            scale: null,
            payment: null
        };
        if (["single_choice", "multiple_choice", "dropdown"].indexOf(field.type) !== -1) {
            field.options = [option("گزینه اول", 1), option("گزینه دوم", 2)];
        }
        if (["multiple_choice_grid", "checkbox_grid"].indexOf(field.type) !== -1) {
            field.rows = [option("ردیف اول", 1)];
            field.options = [option("ستون اول", 1), option("ستون دوم", 2)];
        }
        if (field.type === "linear_scale") {
            field.scale = { min: 1, max: 5, minLabel: "", maxLabel: "" };
            field.options = [1, 2, 3, 4, 5].map(function (value) {
                return { id: String(value), text: String(value) };
            });
        }
        if (field.type === "payment") {
            field.payment = { amount: "", gateway: "" };
            field.required = true;
        }
        return field;
    }

    function blankTemplate() {
        return {
            kind: "form",
            title: "",
            description: "",
            fields: [newField("short_text", "نام و نام خانوادگی")]
        };
    }

    function pollTemplate() {
        return {
            kind: "poll",
            title: "نظرسنجی جدید",
            description: "",
            fields: [newField("single_choice", "سؤال نظرسنجی")]
        };
    }

    function disTemplate() {
        return {
            kind: "form",
            title: "فرم درخواست ایجاد کاربری در DIS",
            description: "قالب عمومی فرم DIS با خروجی Excel و لینک قابل اشتراک.",
            fields: [
                newField("short_text", "نام"),
                newField("short_text", "نام خانوادگی"),
                newField("number", "کد ملی"),
                newField("phone", "شماره تلفن"),
                Object.assign(newField("number", "شماره نظام پزشکی"), { required: false }),
                newField("number", "شماره پرسنلی / شماره دانشجویی"),
                Object.assign(newField("single_choice", "سمت"), {
                    options: ["هیئت علمی", "دندانپزشک", "دانشجو", "کارمند", "رسمی", "قراردادی", "شرکتی"].map(function (text, index) { return option(text, index + 1); })
                }),
                Object.assign(newField("single_choice", "نرم‌افزار"), {
                    options: ["رادیولوژی", "DIS", "پاتولوژی", "داروخانه"].map(function (text, index) { return option(text, index + 1); })
                }),
                Object.assign(newField("multiple_choice", "سمت و سطح دسترسی"), {
                    options: ["مدیر سیستم", "مدیریت درمان", "مدیریت کلینیک", "دندانپزشکان", "مددکاری", "انبار داروخانه", "صندوق", "پذیرش", "جهادگر", "رزیدنت", "دانشجو", "حسابداری", "آموزش", "درآمد", "کارشناس DIS", "سایر"].map(function (text, index) { return option(text, index + 1); })
                }),
                Object.assign(newField("paragraph", "توضیحات"), { required: false })
            ]
        };
    }

    function cloneField(field) {
        return {
            id: String(field.id || uniqueFieldId()),
            type: String(field.type || "short_text"),
            label: String(field.label || "پرسش"),
            help: String(field.help || ""),
            required: !!field.required,
            options: Array.isArray(field.options) ? field.options.map(function (item, index) {
                return {
                    id: String(item.id || ("opt-" + (index + 1))),
                    text: String(item.text || "")
                };
            }) : [],
            rows: Array.isArray(field.rows) ? field.rows.map(function (item, index) {
                return {
                    id: String(item.id || ("row-" + (index + 1))),
                    text: String(item.text || "")
                };
            }) : [],
            scale: field.scale && typeof field.scale === "object"
                ? {
                    min: Number(field.scale.min || 1),
                    max: Number(field.scale.max || 5),
                    minLabel: String(field.scale.minLabel || ""),
                    maxLabel: String(field.scale.maxLabel || "")
                }
                : null,
            payment: field.payment && typeof field.payment === "object"
                ? {
                    amount: String(field.payment.amount || ""),
                    gateway: String(field.payment.gateway || "")
                }
                : null
        };
    }

    function fieldNeedsOptions(type) {
        return ["single_choice", "multiple_choice", "dropdown", "multiple_choice_grid", "checkbox_grid"].indexOf(type) !== -1;
    }

    function applyKindRestrictions() {
        if (kindInput.value === "poll") {
            if (!state.fields.length) {
                state.fields = [newField("single_choice", "سؤال نظرسنجی")];
            }
            state.fields = [state.fields[0]];
            if (["single_choice", "multiple_choice"].indexOf(state.fields[0].type) === -1) {
                state.fields[0].type = "single_choice";
            }
            state.fields[0].required = true;
            if (!state.fields[0].options || state.fields[0].options.length < 2) {
                state.fields[0].options = [option("گزینه اول", 1), option("گزینه دوم", 2)];
            }
        }
    }

    function renderFieldEditor() {
        applyKindRestrictions();
        fieldsEditor.innerHTML = "";
        state.fields.forEach(function (field, index) {
            var card = document.createElement("article");
            card.className = "forms-field-card";

            var top = document.createElement("div");
            top.className = "forms-field-card__top";

            var labelWrap = document.createElement("label");
            labelWrap.className = "forms-field";
            labelWrap.innerHTML = "<span>عنوان پرسش</span>";
            var labelInput = document.createElement("input");
            labelInput.type = "text";
            labelInput.maxLength = 180;
            labelInput.value = field.label || "";
            labelInput.addEventListener("input", function () {
                field.label = labelInput.value;
            });
            labelWrap.appendChild(labelInput);
            top.appendChild(labelWrap);

            var typeWrap = document.createElement("label");
            typeWrap.className = "forms-field";
            typeWrap.innerHTML = "<span>نوع پاسخ</span>";
            var typeSelect = document.createElement("select");
            [
                ["short_text", "متن کوتاه"],
                ["paragraph", "پاراگراف"],
                ["single_choice", "چندگزینه‌ای تک‌انتخاب"],
                ["multiple_choice", "چندگزینه‌ای چندانتخاب"],
                ["dropdown", "فهرست کشویی"],
                ["linear_scale", "مقیاس خطی"],
                ["multiple_choice_grid", "جدول تک‌انتخابی"],
                ["checkbox_grid", "جدول چندانتخابی"],
                ["date", "تاریخ"],
                ["time", "زمان"],
                ["number", "عدد"],
                ["email", "ایمیل"],
                ["phone", "شماره تماس"],
                ["url", "لینک"],
                ["payment", "پرداخت"]
            ].forEach(function (item) {
                if (kindInput.value === "poll" && ["single_choice", "multiple_choice"].indexOf(item[0]) === -1) return;
                var optionEl = document.createElement("option");
                optionEl.value = item[0];
                optionEl.textContent = item[1];
                typeSelect.appendChild(optionEl);
            });
            typeSelect.value = field.type || "short_text";
            typeSelect.addEventListener("change", function () {
                field.type = typeSelect.value;
                if (fieldNeedsOptions(field.type) && (!field.options || field.options.length < 2)) {
                    field.options = [option("گزینه اول", 1), option("گزینه دوم", 2)];
                }
                if (["multiple_choice_grid", "checkbox_grid"].indexOf(field.type) !== -1 && (!field.rows || field.rows.length < 1)) {
                    field.rows = [option("ردیف اول", 1)];
                }
                if (field.type === "linear_scale") {
                    field.scale = field.scale || { min: 1, max: 5, minLabel: "", maxLabel: "" };
                }
                if (field.type === "payment") {
                    field.payment = field.payment || { amount: "", gateway: "" };
                    field.required = true;
                }
                renderFieldEditor();
            });
            typeWrap.appendChild(typeSelect);
            top.appendChild(typeWrap);

            var removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "forms-btn forms-btn--danger";
            removeBtn.textContent = "حذف";
            removeBtn.hidden = kindInput.value === "poll" || state.fields.length <= 1;
            removeBtn.addEventListener("click", function () {
                state.fields.splice(index, 1);
                renderFieldEditor();
            });
            top.appendChild(removeBtn);
            card.appendChild(top);

            var helpWrap = document.createElement("label");
            helpWrap.className = "forms-field";
            helpWrap.innerHTML = "<span>راهنمای کوتاه</span>";
            var helpInput = document.createElement("input");
            helpInput.type = "text";
            helpInput.maxLength = 500;
            helpInput.value = field.help || "";
            helpInput.addEventListener("input", function () {
                field.help = helpInput.value;
            });
            helpWrap.appendChild(helpInput);
            card.appendChild(helpWrap);

            var requiredLabel = document.createElement("label");
            requiredLabel.className = "forms-toggle";
            var requiredInput = document.createElement("input");
            requiredInput.type = "checkbox";
            requiredInput.checked = !!field.required;
            requiredInput.disabled = kindInput.value === "poll";
            requiredInput.addEventListener("change", function () {
                field.required = requiredInput.checked;
            });
            requiredLabel.appendChild(requiredInput);
            var requiredText = document.createElement("span");
            requiredText.textContent = "پاسخ به این پرسش الزامی باشد";
            requiredLabel.appendChild(requiredText);
            card.appendChild(requiredLabel);

            if (fieldNeedsOptions(field.type)) {
                card.appendChild(renderOptionsEditor(field));
            }
            if (["multiple_choice_grid", "checkbox_grid"].indexOf(field.type) !== -1) {
                card.appendChild(renderRowsEditor(field));
            }
            if (field.type === "linear_scale") {
                card.appendChild(renderScaleEditor(field));
            }
            if (field.type === "payment") {
                card.appendChild(renderPaymentEditor(field));
            }

            fieldsEditor.appendChild(card);
        });
    }

    function renderOptionsEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-options-box";
        field.options = Array.isArray(field.options) ? field.options : [];
        field.options.forEach(function (item, index) {
            var row = document.createElement("div");
            row.className = "forms-option-line";
            var input = document.createElement("input");
            input.type = "text";
            input.maxLength = 160;
            input.value = item.text || "";
            input.addEventListener("input", function () {
                item.text = input.value;
            });
            row.appendChild(input);
            var remove = document.createElement("button");
            remove.type = "button";
            remove.className = "forms-btn forms-btn--danger";
            remove.textContent = "×";
            remove.addEventListener("click", function () {
                if (field.options.length <= 2) {
                    showToast("حداقل دو گزینه لازم است.");
                    return;
                }
                field.options.splice(index, 1);
                renderFieldEditor();
            });
            row.appendChild(remove);
            wrap.appendChild(row);
        });

        var add = document.createElement("button");
        add.type = "button";
        add.className = "forms-btn";
        add.textContent = "افزودن گزینه";
        add.addEventListener("click", function () {
            if (field.options.length >= 50) {
                showToast("حداکثر ۵۰ گزینه مجاز است.");
                return;
            }
            field.options.push(option("گزینه جدید", field.options.length + 1));
            renderFieldEditor();
        });
        wrap.appendChild(add);
        return wrap;
    }

    function renderRowsEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-options-box";
        var title = document.createElement("small");
        title.className = "forms-muted";
        title.textContent = "ردیف‌های جدول";
        wrap.appendChild(title);
        field.rows = Array.isArray(field.rows) ? field.rows : [];
        field.rows.forEach(function (item, index) {
            var row = document.createElement("div");
            row.className = "forms-option-line";
            var input = document.createElement("input");
            input.type = "text";
            input.maxLength = 160;
            input.value = item.text || "";
            input.addEventListener("input", function () {
                item.text = input.value;
            });
            row.appendChild(input);
            var remove = document.createElement("button");
            remove.type = "button";
            remove.className = "forms-btn forms-btn--danger";
            remove.textContent = "×";
            remove.addEventListener("click", function () {
                if (field.rows.length <= 1) {
                    showToast("حداقل یک ردیف لازم است.");
                    return;
                }
                field.rows.splice(index, 1);
                renderFieldEditor();
            });
            row.appendChild(remove);
            wrap.appendChild(row);
        });
        var add = document.createElement("button");
        add.type = "button";
        add.className = "forms-btn";
        add.textContent = "افزودن ردیف";
        add.addEventListener("click", function () {
            field.rows.push(option("ردیف جدید", field.rows.length + 1));
            renderFieldEditor();
        });
        wrap.appendChild(add);
        return wrap;
    }

    function renderScaleEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-scale-grid";
        field.scale = field.scale || { min: 1, max: 5, minLabel: "", maxLabel: "" };
        [
            ["min", "حداقل", "number"],
            ["max", "حداکثر", "number"],
            ["minLabel", "برچسب حداقل", "text"],
            ["maxLabel", "برچسب حداکثر", "text"]
        ].forEach(function (item) {
            var label = document.createElement("label");
            label.className = "forms-field";
            label.innerHTML = "<span>" + item[1] + "</span>";
            var input = document.createElement("input");
            input.type = item[2];
            input.value = field.scale[item[0]] == null ? "" : String(field.scale[item[0]]);
            if (item[2] === "number") {
                input.min = "0";
                input.max = "10";
            }
            input.addEventListener("input", function () {
                field.scale[item[0]] = item[2] === "number" ? Number(input.value || "0") : input.value;
            });
            label.appendChild(input);
            wrap.appendChild(label);
        });
        return wrap;
    }

    function renderPaymentEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-payment-editor";
        field.payment = field.payment || { amount: "", gateway: "" };

        var amountWrap = document.createElement("label");
        amountWrap.className = "forms-field";
        amountWrap.innerHTML = "<span>مبلغ پرداخت (ریال)</span>";
        var amountInput = document.createElement("input");
        amountInput.type = "text";
        amountInput.inputMode = "numeric";
        amountInput.dir = "ltr";
        amountInput.setAttribute("data-latin-digits", "true");
        amountInput.maxLength = 14;
        amountInput.value = field.payment.amount || "";
        amountInput.addEventListener("input", function () {
            field.payment.amount = normalizeDigits(amountInput.value).replace(/\D+/g, "");
            amountInput.value = field.payment.amount;
        });
        amountWrap.appendChild(amountInput);
        wrap.appendChild(amountWrap);

        var gatewayWrap = document.createElement("label");
        gatewayWrap.className = "forms-field";
        gatewayWrap.innerHTML = "<span>درگاه پیش‌فرض سوال</span>";
        var gatewayInput = document.createElement("input");
        gatewayInput.type = "text";
        gatewayInput.maxLength = 60;
        gatewayInput.dir = "ltr";
        gatewayInput.setAttribute("data-latin-digits", "true");
        gatewayInput.placeholder = "اختیاری؛ اگر خالی باشد کاربر درگاه را انتخاب می‌کند";
        gatewayInput.value = field.payment.gateway || "";
        gatewayInput.addEventListener("input", function () {
            field.payment.gateway = gatewayInput.value.trim();
        });
        gatewayWrap.appendChild(gatewayInput);
        wrap.appendChild(gatewayWrap);

        var note = document.createElement("small");
        note.className = "forms-muted";
        note.textContent = "پاسخ این سوال فقط بعد از پرداخت تاییدشده همراه پاسخ فرم ثبت می‌شود.";
        wrap.appendChild(note);
        return wrap;
    }

    function applyTemplate(name) {
        var tpl = name === "dis" ? disTemplate() : (name === "poll" ? pollTemplate() : blankTemplate());
        state.editingId = "";
        kindInput.value = tpl.kind;
        titleInput.value = tpl.title;
        descriptionInput.value = tpl.description;
        statusInput.value = "open";
        audienceInput.value = "link";
        resultVisibilityInput.value = tpl.kind === "poll" ? "after-submit" : "manager-only";
        startAtInput.value = "";
        endAtInput.value = "";
        allowedStudentsInput.value = "";
        managerStudentsInput.value = "";
        allowRepresentativeManageInput.checked = false;
        allowGuestInput.checked = false;
        collectGuestNameInput.checked = true;
        collectGuestPhoneInput.checked = false;
        limitOneInput.checked = true;
        allowEditResponseInput.checked = false;
        allowCreatorSubmitInput.checked = true;
        anonymousResponsesInput.checked = false;
        setExportSettings({ identityColumns: ["index", "responseId", "submittedAt", "participantKind", "name", "studentNumber", "roleLabel", "phone"], includeAllFields: true, fieldIds: [] });
        state.fields = tpl.fields.map(cloneField);
        builderTitle.textContent = "ساخت فرم جدید";
        submitTitle.textContent = "ذخیره فرم";
        saveBtn.textContent = "ذخیره";
        renderFieldEditor();
        setFeedback("", "");
    }

    function populateBuilder(form) {
        state.editingId = String(form.id || "");
        kindInput.value = String(form.kind || "form");
        templateInput.value = "blank";
        titleInput.value = String(form.title || "");
        descriptionInput.value = String(form.description || "");
        statusInput.value = ["draft", "open", "closed"].indexOf(String(form.status || "")) !== -1 ? String(form.status) : "open";
        var settings = form.settings || {};
        audienceInput.value = String(settings.audience || "link");
        resultVisibilityInput.value = String(settings.resultVisibility || "after-submit");
        startAtInput.value = toDatetimeLocal(settings.startAt);
        endAtInput.value = toDatetimeLocal(settings.endAt);
        allowedStudentsInput.value = Array.isArray(settings.allowedStudents) ? settings.allowedStudents.join("\n") : "";
        managerStudentsInput.value = Array.isArray(settings.managerStudentNumbers) ? settings.managerStudentNumbers.join("\n") : "";
        allowRepresentativeManageInput.checked = !!settings.allowRepresentativeManage;
        allowGuestInput.checked = !!settings.allowGuest;
        collectGuestNameInput.checked = settings.collectGuestName !== false;
        collectGuestPhoneInput.checked = !!settings.collectGuestPhone;
        limitOneInput.checked = settings.limitOneResponse !== false;
        allowEditResponseInput.checked = !!settings.allowEditResponse;
        allowCreatorSubmitInput.checked = settings.allowCreatorSubmit !== false;
        anonymousResponsesInput.checked = !!settings.anonymousResponses;
        setExportSettings(settings.export || {});
        state.fields = Array.isArray(form.fields) ? form.fields.map(cloneField) : [newField()];
        builderTitle.textContent = "ویرایش " + String(form.kindLabel || "فرم");
        submitTitle.textContent = "ذخیره تغییرات";
        saveBtn.textContent = "ذخیره تغییرات";
        renderFieldEditor();
        setFeedback("می‌توانید شرکت‌کنندگان مجاز و تنظیمات را بعد از ساخت هم ویرایش کنید.", "");
        setTab("builder");
    }

    function collectPayload() {
        var title = String(titleInput.value || "").trim();
        if (!title) {
            throw new Error("عنوان را وارد کنید.");
        }
        var fields = state.fields.map(function (field) {
            var next = cloneField(field);
            next.label = String(next.label || "").trim();
            next.help = String(next.help || "").trim();
            if (fieldNeedsOptions(next.type)) {
                next.options = next.options.map(function (item, index) {
                    return {
                        id: String(item.id || ("opt-" + (index + 1))),
                        text: String(item.text || "").trim()
                    };
                }).filter(function (item) {
                    return item.text;
                });
            }
            if (["multiple_choice_grid", "checkbox_grid"].indexOf(next.type) !== -1) {
                next.rows = next.rows.map(function (item, index) {
                    return {
                        id: String(item.id || ("row-" + (index + 1))),
                        text: String(item.text || "").trim()
                    };
                }).filter(function (item) {
                    return item.text;
                });
                if (next.rows.length < 1) {
                    throw new Error("برای جدول حداقل یک ردیف لازم است.");
                }
            }
            if (fieldNeedsOptions(next.type) && next.options.length < 2) {
                throw new Error("برای پرسش‌های گزینه‌ای حداقل دو گزینه لازم است.");
            }
            if (next.type === "payment") {
                next.payment = next.payment || { amount: "", gateway: "" };
                next.payment.amount = normalizeDigits(String(next.payment.amount || "")).replace(/\D+/g, "");
                next.payment.gateway = String(next.payment.gateway || "").trim();
                next.required = !!next.required;
                if (!next.payment.amount || Number(next.payment.amount) <= 0) {
                    throw new Error("برای سوال پرداخت باید مبلغ بیشتر از صفر وارد شود.");
                }
            }
            return next;
        }).filter(function (field) {
            return field.label;
        });
        if (!fields.length) {
            throw new Error("حداقل یک پرسش معتبر لازم است.");
        }
        return {
            kind: String(kindInput.value || "form"),
            title: title,
            description: String(descriptionInput.value || "").trim(),
            status: String(statusInput.value || "open"),
            settings: {
                audience: String(audienceInput.value || "link"),
                allowedStudents: normalizeDigits(String(allowedStudentsInput.value || "")).split(/[\s,،;]+/).filter(Boolean),
                allowRepresentativeManage: !!allowRepresentativeManageInput.checked,
                managerStudentNumbers: normalizeDigits(String(managerStudentsInput.value || "")).split(/[\s,،;]+/).filter(Boolean),
                allowGuest: !!allowGuestInput.checked,
                collectGuestName: !!collectGuestNameInput.checked,
                collectGuestPhone: !!collectGuestPhoneInput.checked,
                limitOneResponse: !!limitOneInput.checked,
                allowEditResponse: !!allowEditResponseInput.checked,
                allowCreatorSubmit: !!allowCreatorSubmitInput.checked,
                anonymousResponses: !!anonymousResponsesInput.checked,
                export: collectExportSettings(),
                resultVisibility: String(resultVisibilityInput.value || "after-submit"),
                startAt: toIsoValue(startAtInput.value),
                endAt: toIsoValue(endAtInput.value)
            },
            fields: fields
        };
    }

    function updateSummary() {
        var total = state.forms.length;
        var open = 0;
        var responses = 0;
        state.forms.forEach(function (form) {
            if (form.status === "open") open++;
            responses += Number(form.responseCount || 0);
        });
        formsCount.textContent = total.toLocaleString("fa-IR");
        formsOpenCount.textContent = open.toLocaleString("fa-IR");
        formsResponseCount.textContent = responses.toLocaleString("fa-IR");
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

    function renderFormsList() {
        updateSummary();
        formsList.innerHTML = "";
        formsEmpty.hidden = state.forms.length > 0;
        state.forms.forEach(function (form) {
            var item = document.createElement("article");
            item.className = "forms-item";

            var head = document.createElement("div");
            head.className = "forms-item__head";
            var copy = document.createElement("div");
            var title = document.createElement("h3");
            title.textContent = String(form.title || "فرم");
            copy.appendChild(title);
            var meta = document.createElement("p");
            meta.className = "forms-item__meta";
            meta.textContent = [
                String(form.kindLabel || "فرم"),
                String(form.statusLabel || "نامشخص"),
                "پاسخ‌ها: " + Number(form.responseCount || 0).toLocaleString("fa-IR"),
                "دسترسی: " + String(form.settings && form.settings.audienceLabel ? form.settings.audienceLabel : "")
            ].filter(Boolean).join(" • ");
            copy.appendChild(meta);
            head.appendChild(copy);
            var chip = document.createElement("span");
            chip.className = "forms-chip" + (form.status === "open" ? "" : " forms-chip--soft");
            chip.textContent = String(form.statusLabel || "—");
            head.appendChild(chip);
            item.appendChild(head);

            var actions = document.createElement("div");
            actions.className = "forms-actions";

            var open = document.createElement("a");
            open.className = "forms-btn forms-btn--primary";
            open.href = String(form.sharePath || "#");
            open.target = "_blank";
            open.rel = "noopener";
            open.textContent = "باز کردن";
            actions.appendChild(open);

            var copyBtn = document.createElement("button");
            copyBtn.className = "forms-btn";
            copyBtn.type = "button";
            copyBtn.textContent = "کپی لینک";
            copyBtn.addEventListener("click", function () {
                copyText(String(form.shareUrl || "")).then(function () {
                    showToast("لینک کپی شد.");
                }).catch(function () {
                    showToast("کپی لینک انجام نشد.");
                });
            });
            actions.appendChild(copyBtn);

            if (form.permissions && form.permissions.canManage) {
                var edit = document.createElement("button");
                edit.className = "forms-btn";
                edit.type = "button";
                edit.textContent = "ویرایش";
                edit.addEventListener("click", function () {
                    populateBuilder(form);
                });
                actions.appendChild(edit);

                var responses = document.createElement("button");
                responses.className = "forms-btn";
                responses.type = "button";
                responses.textContent = "پاسخ‌ها";
                responses.addEventListener("click", function () {
                    loadResponses(form.id);
                });
                actions.appendChild(responses);

                var statusBtn = document.createElement("button");
                statusBtn.className = form.status === "open" ? "forms-btn forms-btn--danger" : "forms-btn";
                statusBtn.type = "button";
                statusBtn.textContent = form.status === "open" ? "بستن" : "فعال‌سازی";
                statusBtn.addEventListener("click", function () {
                    setFormStatus(form.id, form.status === "open" ? "closed" : "open");
                });
                actions.appendChild(statusBtn);

                var exportA = document.createElement("a");
                exportA.className = "forms-btn";
                exportA.href = "/api/forms_api.php?action=export&mode=responses&formId=" + encodeURIComponent(String(form.id || ""));
                exportA.textContent = "Excel پاسخ‌ها";
                actions.appendChild(exportA);

                var summaryA = document.createElement("a");
                summaryA.className = "forms-btn";
                summaryA.href = "/api/forms_api.php?action=export&mode=summary&formId=" + encodeURIComponent(String(form.id || ""));
                summaryA.textContent = "خلاصه Excel";
                actions.appendChild(summaryA);

                var officialA = document.createElement("a");
                officialA.className = "forms-btn";
                officialA.href = "/api/forms_api.php?action=export&mode=official&formId=" + encodeURIComponent(String(form.id || ""));
                officialA.textContent = "خروجی رسمی";
                actions.appendChild(officialA);

                if (form.permissions && form.permissions.canDelete) {
                    var deleteBtn = document.createElement("button");
                    deleteBtn.className = "forms-btn forms-btn--danger";
                    deleteBtn.type = "button";
                    deleteBtn.textContent = "حذف کامل";
                    deleteBtn.addEventListener("click", function () {
                        deleteForm(form.id);
                    });
                    actions.appendChild(deleteBtn);
                }
            }

            item.appendChild(actions);
            formsList.appendChild(item);
        });
    }

    async function loadForms() {
        if (state.loading) return;
        state.loading = true;
        try {
            var response = await apiGet("list");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "بارگذاری فرم‌ها انجام نشد.");
            }
            state.forms = Array.isArray(response.forms) ? response.forms : [];
            state.canCreate = !!response.canCreate;
            renderFormsList();
        } catch (error) {
            showToast(error && error.message ? error.message : "بارگذاری انجام نشد.");
        } finally {
            state.loading = false;
        }
    }

    async function saveForm(event) {
        event.preventDefault();
        if (!state.canCreate && !state.editingId) return;
        var payload;
        try {
            payload = collectPayload();
        } catch (error) {
            setFeedback(error.message, "error");
            return;
        }
        saveBtn.disabled = true;
        setFeedback("در حال ذخیره...", "");
        try {
            var postPayload = {
                payload: JSON.stringify(payload)
            };
            var action = state.editingId ? "update" : "create";
            if (state.editingId) {
                postPayload.formId = state.editingId;
            }
            var response = await apiPost(action, postPayload);
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "ذخیره فرم انجام نشد.");
            }
            state.editingId = String(response.form.id || "");
            setFeedback((response && response.message) || "ذخیره شد.", "success");
            showToast("فرم ذخیره شد.");
            await loadForms();
            populateBuilder(response.form);
        } catch (error) {
            setFeedback(error && error.message ? error.message : "ذخیره انجام نشد.", "error");
        } finally {
            saveBtn.disabled = false;
        }
    }

    async function setFormStatus(formId, status) {
        try {
            var response = await apiPost("setStatus", {
                formId: String(formId || ""),
                status: String(status || "open")
            });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "تغییر وضعیت انجام نشد.");
            }
            showToast(status === "open" ? "فرم فعال شد." : "فرم بسته شد.");
            await loadForms();
        } catch (error) {
            showToast(error && error.message ? error.message : "تغییر وضعیت انجام نشد.");
        }
    }

    async function deleteForm(formId) {
        if (!formId || !window.confirm("این فرم و همه پاسخ‌های آن حذف شود؟ این کار فقط برای مالک مجاز است و برگشت‌پذیر نیست.")) {
            return;
        }
        try {
            var response = await apiPost("delete", {
                formId: String(formId || "")
            });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف فرم انجام نشد.");
            }
            if (state.editingId === String(formId || "")) {
                applyTemplate("blank");
            }
            responsesList.innerHTML = "";
            responsesEmpty.hidden = false;
            exportLink.hidden = true;
            exportLink.removeAttribute("href");
            showToast("فرم حذف شد.");
            await loadForms();
        } catch (error) {
            showToast(error && error.message ? error.message : "حذف فرم انجام نشد.");
        }
    }

    async function loadResponses(formId) {
        try {
            var response = await apiGet("responses", { formId: String(formId || "") });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "دریافت پاسخ‌ها انجام نشد.");
            }
            renderResponses(response.form || null, response.responses || []);
            setTab("responses");
        } catch (error) {
            showToast(error && error.message ? error.message : "دریافت پاسخ‌ها انجام نشد.");
        }
    }

    function renderResponses(form, responses) {
        responsesList.innerHTML = "";
        responses = Array.isArray(responses) ? responses : [];
        responsesEmpty.hidden = responses.length > 0;
        responsesTitle.textContent = form && form.title ? "پاسخ‌ها: " + form.title : "پاسخ‌ها";
        if (form && form.id) {
            exportLink.hidden = false;
            exportLink.href = "/api/forms_api.php?action=export&mode=responses&formId=" + encodeURIComponent(String(form.id));
        } else {
            exportLink.hidden = true;
            exportLink.removeAttribute("href");
        }
        responses.forEach(function (response) {
            var card = document.createElement("article");
            card.className = "forms-response-card";
            var identity = response.identity || {};
            var title = document.createElement("h3");
            title.textContent = String(identity.name || identity.studentNumber || "شرکت‌کننده") + " • " + formatDateTime(response.submittedAt);
            card.appendChild(title);
            var grid = document.createElement("div");
            grid.className = "forms-answer-grid";
            (Array.isArray(response.answers) ? response.answers : []).forEach(function (answer) {
                var item = document.createElement("div");
                item.className = "forms-answer";
                var label = document.createElement("span");
                label.textContent = String(answer.label || "");
                item.appendChild(label);
                var value = document.createElement("strong");
                value.textContent = String(answer.displayValue || "—");
                item.appendChild(value);
                grid.appendChild(item);
            });
            card.appendChild(grid);
            responsesList.appendChild(card);
        });
    }

    async function loadSession() {
        var response = await apiGet("session");
        if (!response || !response.success) {
            throw new Error((response && response.error) || "آماده‌سازی انجام نشد.");
        }
        state.viewer = response.viewer || null;
        state.canCreate = !!response.canCreate;
        if (viewerCopy) {
            viewerCopy.textContent = state.viewer
                ? ((state.canCreate ? "مدیریت فعال برای " : "فرم‌های فعال برای ") + String(state.viewer.name || "کاربر"))
                : "برای مدیریت فرم‌ها وارد شوید.";
        }
        if (!state.viewer) {
            if (loginLink) {
                loginLink.href = window.Dent1402Auth.loginUrl("/forms/");
            }
            showStage("login");
            return;
        }
        showStage("app");
        if (!state.canCreate) {
            panels.builder.hidden = true;
            tabs.forEach(function (tab) {
                if (tab.getAttribute("data-forms-tab") === "builder") {
                    tab.hidden = true;
                }
            });
            setTab("list");
        } else {
            tabs.forEach(function (tab) { tab.hidden = false; });
            panels.builder.hidden = false;
            setTab((new URLSearchParams(window.location.search).get("tab")) || "builder");
        }
        await loadForms();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            setTab(tab.getAttribute("data-forms-tab") || "list");
        });
    });

    templateInput.addEventListener("change", function () {
        applyTemplate(templateInput.value);
    });

    kindInput.addEventListener("change", function () {
        if (kindInput.value === "poll" && state.fields.length > 1) {
            state.fields = [state.fields[0]];
        }
        if (kindInput.value === "poll") {
            resultVisibilityInput.value = "after-submit";
        }
        renderFieldEditor();
    });

    addFieldBtn.addEventListener("click", function () {
        if (kindInput.value === "poll") {
            showToast("نظرسنجی سریع فقط یک پرسش دارد.");
            return;
        }
        state.fields.push(newField("short_text", "پرسش جدید"));
        renderFieldEditor();
    });

    resetBuilderBtn.addEventListener("click", function () {
        templateInput.value = "blank";
        applyTemplate("blank");
    });

    builder.addEventListener("submit", saveForm);
    reloadBtn.addEventListener("click", loadForms);
    closeResponsesBtn.addEventListener("click", function () {
        setTab("list");
    });

    applyTemplate("blank");
    showStage("boot");
    window.Dent1402Auth.onChange(function (detail) {
        if (detail && (detail.status === "session-restoring" || detail.status === "logging-out")) {
            showStage("boot");
            return;
        }
        loadSession().catch(function (error) {
            showStage("login");
            showToast(error && error.message ? error.message : "آماده‌سازی انجام نشد.");
        });
    });
})();
