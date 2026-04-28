(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
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

    function apiGet(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse);
    }

    function apiPost(action, payload) {
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(parseJsonResponse);
    }

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function normalizeDigits(value) {
        return String(value || "")
            .replace(/[\u06F0-\u06F9]/g, function (ch) {
                return String("\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".indexOf(ch));
            })
            .replace(/[\u0660-\u0669]/g, function (ch) {
                return String("\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669".indexOf(ch));
            });
    }

    function normalizePhone(value) {
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) return "";
        if (digits.indexOf("0098") === 0) {
            digits = digits.slice(4);
        } else if (digits.indexOf("98") === 0) {
            digits = digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === "9") {
            digits = "0" + digits;
        }
        return digits;
    }

    function text(value) {
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

    function formatDateTime(value, fallback) {
        var raw = String(value || "").trim();
        if (!raw) {
            return fallback || "—";
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

    function statusClass(stateKey) {
        switch (String(stateKey || "")) {
            case "active":
            case "success":
                return "is-active";
            case "upcoming":
            case "pending":
            case "full":
                return "is-warn";
            case "failed":
            case "canceled":
            case "expired":
                return "is-error";
            default:
                return "is-muted";
        }
    }

    function setFeedback(node, message, kind) {
        if (!node) return;
        node.className = "buy-feedback" + (kind ? (" " + kind) : "");
        node.textContent = message || "";
    }

    function readParam(name) {
        var params = new URLSearchParams(window.location.search);
        return String(params.get(name) || "").trim();
    }

    function readSlugFromLocation() {
        var slug = readParam("slug");
        if (slug) {
            return slug;
        }
        var parts = String(window.location.pathname || "").split("/").filter(Boolean);
        var itemIndex = parts.indexOf("item");
        if (itemIndex >= 0 && parts.length > itemIndex + 1) {
            return parts[itemIndex + 1];
        }
        return "";
    }

    function gatewayProviderFallback(key) {
        var clean = String(key || "").trim().toLowerCase();
        if (clean === "zibal") {
            return "درگاه زیبال";
        }
        if (clean === "zarinpal") {
            return "درگاه زرین‌پال";
        }
        if (clean === "mock") {
            return "درگاه آزمایشی";
        }
        return "درگاه آنلاین";
    }

    function normalizeGatewayBundle(raw) {
        var source = raw && typeof raw === "object" ? raw : {};
        var rows = Array.isArray(source.gateways) ? source.gateways : [];
        var defaultKey = String(source.defaultKey || "").trim().toLowerCase();
        var gateways = [];
        rows.forEach(function (row) {
            if (!row || typeof row !== "object") {
                return;
            }
            var key = String(row.key || "").trim().toLowerCase();
            if (!key) {
                return;
            }
            gateways.push({
                key: key,
                label: String(row.label || "پرداخت آنلاین"),
                provider: String(row.provider || gatewayProviderFallback(key)),
                icon: String(row.icon || "").trim(),
                isEnabled: !!row.isEnabled,
                isDefault: !!row.isDefault
            });
        });

        if (!defaultKey) {
            var explicit = gateways.find(function (entry) { return entry.isEnabled && entry.isDefault; });
            if (explicit) {
                defaultKey = explicit.key;
            }
        }
        if (!defaultKey) {
            var firstEnabled = gateways.find(function (entry) { return entry.isEnabled; });
            if (firstEnabled) {
                defaultKey = firstEnabled.key;
            }
        }

        return {
            gateways: gateways,
            defaultKey: defaultKey
        };
    }

    function renderGatewayOptions(raw, form, submit) {
        var section = $("buy-gateway-section");
        var root = $("buy-gateway-options");
        if (!section || !root || !form) {
            return {
                hasEnabled: true,
                getSelected: function () { return ""; },
                refreshSubmitText: function () {}
            };
        }

        var bundle = normalizeGatewayBundle(raw);
        var options = bundle.gateways;
        var enabledCount = options.filter(function (entry) { return entry.isEnabled; }).length;

        if (!options.length) {
            section.hidden = true;
            root.innerHTML = "";
            return {
                hasEnabled: true,
                getSelected: function () { return ""; },
                refreshSubmitText: function () {}
            };
        }

        section.hidden = false;
        var selectedOnce = false;
        root.innerHTML = options.map(function (entry, index) {
            var inputId = "buy-gateway-" + entry.key + "-" + String(index);
            var checked = false;
            if (entry.isEnabled && !selectedOnce && bundle.defaultKey && entry.key === bundle.defaultKey) {
                checked = true;
                selectedOnce = true;
            } else if (entry.isEnabled && !selectedOnce && !bundle.defaultKey) {
                checked = true;
                selectedOnce = true;
            }
            var disabled = !entry.isEnabled;
            var subtitle = entry.provider || gatewayProviderFallback(entry.key);
            if (disabled) {
                subtitle += " (غیرفعال)";
            }

            return [
                '<label class="buy-gateway-option' + (disabled ? " is-disabled" : "") + '" for="' + text(inputId) + '">',
                '  <input id="' + text(inputId) + '" type="radio" name="buy_gateway" value="' + text(entry.key) + '"' + (checked ? " checked" : "") + (disabled ? " disabled" : "") + ">",
                '  <span class="buy-gateway-option__radio" aria-hidden="true"></span>',
                '  <span class="buy-gateway-option__copy">',
                '    <strong class="buy-gateway-option__title">' + text(entry.label || "پرداخت آنلاین") + "</strong>",
                '    <small class="buy-gateway-option__meta">' + text(subtitle) + "</small>",
                "  </span>",
                '  <span class="buy-gateway-option__badge">' + text(entry.icon || entry.key.toUpperCase()) + "</span>",
                "</label>"
            ].join("");
        }).join("");

        function readSelectedInput() {
            return form.querySelector("input[name='buy_gateway']:checked");
        }

        function selectedTitle() {
            var input = readSelectedInput();
            if (!input) {
                return "پرداخت آنلاین";
            }
            var optionNode = input.closest(".buy-gateway-option");
            if (!optionNode) {
                return "پرداخت آنلاین";
            }
            var titleNode = optionNode.querySelector(".buy-gateway-option__title");
            return titleNode ? String(titleNode.textContent || "").trim() || "پرداخت آنلاین" : "پرداخت آنلاین";
        }

        function refreshSubmitText() {
            if (!submit) {
                return;
            }
            if (enabledCount <= 0) {
                submit.textContent = "درگاه فعالی موجود نیست";
                submit.disabled = true;
                return;
            }
            submit.textContent = selectedTitle();
        }

        root.addEventListener("change", function () {
            refreshSubmitText();
        });
        refreshSubmitText();

        return {
            hasEnabled: enabledCount > 0,
            getSelected: function () {
                var input = readSelectedInput();
                return input ? String(input.value || "").trim().toLowerCase() : "";
            },
            refreshSubmitText: refreshSubmitText
        };
    }

    function renderList(items) {
        var root = $("buy-list-root");
        if (!root) return;

        if (!Array.isArray(items) || !items.length) {
            root.innerHTML = '<div class="buy-empty">فعلا آیتم فعالی برای پرداخت وجود ندارد.</div>';
            return;
        }

        root.innerHTML = items.map(function (item) {
            var state = item.state || {};
            var hero = String(item.heroImage || "").trim();
            var meta = [];
            if (item.remainingCapacity != null) {
                meta.push("باقی‌مانده " + String(Number(item.remainingCapacity || 0).toLocaleString("fa-IR")));
            }
            if (item.expiresAt) {
                meta.push("مهلت " + formatDateTime(item.expiresAt, "—"));
            }
            return [
                '<article class="buy-item-card">',
                '  <a class="buy-item-card__hero" href="/buy/item/?slug=' + encodeURIComponent(String(item.slug || "")) + '">',
                hero
                    ? '    <img src="' + text(hero) + '" alt="' + text(item.title || "تصویر آیتم پرداخت") + '">'
                    : '    <span>بدون تصویر</span>',
                "  </a>",
                '  <div class="buy-item-card__body">',
                '    <div class="buy-item-card__head">',
                '      <div class="buy-item-card__copy">',
                '        <span class="buy-status ' + statusClass(state.key || item.status) + '">' + text(state.label || "نامشخص") + "</span>",
                '        <h3 class="buy-item-card__title">' + text(item.title || "بدون عنوان") + "</h3>",
                "      </div>",
                '      <strong class="buy-item-card__price">' + text(money(item.price || 0)) + "</strong>",
                "    </div>",
                '    <p class="buy-item-card__desc">' + text(item.shortDescription || "—") + "</p>",
                meta.length ? '    <p class="buy-item-card__meta">' + text(meta.join(" • ")) + "</p>" : "",
                '    <a class="shell-action-btn shell-action-btn-primary" href="/buy/item/?slug=' + encodeURIComponent(String(item.slug || "")) + '">مشاهده و پرداخت</a>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderRequiredFields(schema) {
        var root = $("buy-extra-fields");
        if (!root) return;

        if (!Array.isArray(schema) || !schema.length) {
            root.innerHTML = "";
            return;
        }

        root.innerHTML = schema.map(function (field) {
            var name = String(field.name || "").trim();
            if (!name) {
                return "";
            }

            var type = String(field.type || "text");
            var label = String(field.label || name);
            var placeholder = String(field.placeholder || "");
            var required = !!field.required;
            var maxLength = Number(field.maxLength || 120);
            var options = Array.isArray(field.options) ? field.options : [];
            var attrs = [
                'data-extra-field="true"',
                'data-field-name="' + text(name) + '"',
                'data-field-label="' + text(label) + '"',
                'data-field-required="' + (required ? "1" : "0") + '"'
            ];

            var control = "";
            if (type === "textarea") {
                control = '<textarea ' + attrs.join(" ") + ' maxlength="' + String(Math.max(10, Math.min(1000, maxLength))) + '" placeholder="' + text(placeholder) + '"' + (required ? " required" : "") + "></textarea>";
            } else if (type === "select") {
                var optionsHtml = ['<option value="">انتخاب کنید</option>'];
                options.forEach(function (option) {
                    optionsHtml.push('<option value="' + text(option) + '">' + text(option) + "</option>");
                });
                control = '<select ' + attrs.join(" ") + (required ? " required" : "") + ">" + optionsHtml.join("") + "</select>";
            } else {
                var inputType = type === "tel" ? "tel" : (type === "number" ? "number" : "text");
                control = '<input ' + attrs.join(" ") + ' type="' + inputType + '" maxlength="' + String(Math.max(10, Math.min(1000, maxLength))) + '" placeholder="' + text(placeholder) + '"' + (required ? " required" : "") + ">";
            }

            return [
                '<label class="buy-form__field">',
                '  <span>' + text(label) + (required ? " *" : "") + "</span>",
                control,
                "</label>"
            ].join("");
        }).join("");
    }

    function renderSpecifications(specifications) {
        var root = $("buy-item-specs");
        if (!root) return;

        var specs = Array.isArray(specifications) ? specifications : [];
        if (!specs.length) {
            root.innerHTML = '<div class="buy-empty">جزئیات تکمیلی برای این مورد ثبت نشده است.</div>';
            return;
        }

        root.innerHTML = specs.map(function (spec) {
            return [
                '<div class="buy-spec-row">',
                '  <span>' + text(spec.label || "عنوان") + "</span>",
                '  <strong>' + text(spec.value || "—") + "</strong>",
                "</div>"
            ].join("");
        }).join("");
    }

    function renderGallery(item) {
        var heroNode = $("buy-item-hero");
        var galleryNode = $("buy-item-gallery");
        var hero = String(item.heroImage || "").trim();
        var gallery = Array.isArray(item.gallery) ? item.gallery.slice() : [];

        if (hero && gallery.indexOf(hero) < 0) {
            gallery.unshift(hero);
        }

        if (heroNode) {
            heroNode.innerHTML = hero
                ? '<img src="' + text(hero) + '" alt="' + text(item.title || "تصویر آیتم") + '">'
                : '<div class="buy-image-placeholder">تصویری ثبت نشده است</div>';
        }

        if (!galleryNode) {
            return;
        }
        if (!gallery.length) {
            galleryNode.innerHTML = "";
            return;
        }

        galleryNode.innerHTML = gallery.map(function (url, index) {
            return [
                '<button class="buy-gallery__item' + (index === 0 ? " is-active" : "") + '" type="button" data-buy-gallery-item="' + text(url) + '">',
                '  <img src="' + text(url) + '" alt="تصویر گالری">',
                "</button>"
            ].join("");
        }).join("");

        galleryNode.onclick = function (event) {
            var button = event.target.closest("[data-buy-gallery-item]");
            if (!button || !heroNode) {
                return;
            }
            var nextUrl = String(button.getAttribute("data-buy-gallery-item") || "").trim();
            if (!nextUrl) {
                return;
            }
            heroNode.innerHTML = '<img src="' + text(nextUrl) + '" alt="' + text(item.title || "تصویر آیتم") + '">';
            Array.prototype.slice.call(galleryNode.querySelectorAll("[data-buy-gallery-item]")).forEach(function (node) {
                node.classList.toggle("is-active", node === button);
            });
        };
    }

    function initListPage() {
        apiGet("listPublicItems", {}).then(function (payload) {
            if (!payload || !payload.success) {
                renderList([]);
                return;
            }
            renderList(Array.isArray(payload.items) ? payload.items : []);
        }).catch(function () {
            renderList([]);
        });
    }

    function initItemPage() {
        var slug = readSlugFromLocation();
        var titleNode = $("buy-item-title");
        var shortNode = $("buy-item-short");
        var fullNode = $("buy-item-full");
        var priceNode = $("buy-item-price");
        var stateNode = $("buy-item-state");
        var capacityNode = $("buy-item-capacity");
        var timeNode = $("buy-item-time");
        var form = $("buy-order-form");
        var submit = $("buy-order-submit");
        var feedback = $("buy-order-feedback");
        var gatewaySelection = null;

        if (!slug) {
            if (titleNode) titleNode.textContent = "آیتم پرداخت پیدا نشد";
            if (shortNode) shortNode.textContent = "لینک پرداخت معتبر نیست.";
            if (form) form.hidden = true;
            return;
        }

        apiGet("publicItem", { slug: slug }).then(function (payload) {
            if (!payload || !payload.success || !payload.item) {
                if (titleNode) titleNode.textContent = "آیتم پرداخت پیدا نشد";
                if (shortNode) shortNode.textContent = (payload && payload.error) || "لینک پرداخت معتبر نیست.";
                if (form) form.hidden = true;
                return;
            }

            var item = payload.item;
            var state = item.state || {};
            var payable = !!state.isPayable;

            if (titleNode) titleNode.textContent = item.title || "آیتم پرداخت";
            if (shortNode) shortNode.textContent = item.shortDescription || "—";
            if (fullNode) fullNode.textContent = item.fullDescription || "—";
            if (priceNode) priceNode.textContent = money(item.price || 0);
            if (stateNode) {
                stateNode.textContent = state.label || "نامشخص";
                stateNode.className = "buy-status " + statusClass(state.key || item.status);
            }
            if (capacityNode) {
                capacityNode.textContent = item.capacity == null
                    ? "بدون محدودیت ظرفیت"
                    : ("کل " + String(Number(item.capacity || 0).toLocaleString("fa-IR")) + " • باقی‌مانده " + String(Number(item.remainingCapacity || 0).toLocaleString("fa-IR")));
            }
            if (timeNode) {
                var timeMeta = [];
                if (item.startsAt) {
                    timeMeta.push("شروع " + formatDateTime(item.startsAt, "—"));
                }
                if (item.expiresAt) {
                    timeMeta.push("پایان " + formatDateTime(item.expiresAt, "—"));
                }
                timeNode.textContent = timeMeta.length ? timeMeta.join(" • ") : "برای این آیتم بازه زمانی ثبت نشده است";
            }

            renderGallery(item);
            renderSpecifications(item.specifications || []);
            renderRequiredFields(item.requiredFields || []);
            gatewaySelection = renderGatewayOptions(item.paymentGateways || {}, form, submit);

            if (submit) {
                var hasGateway = !!(gatewaySelection && gatewaySelection.hasEnabled);
                submit.disabled = !payable || !hasGateway;
                if (!payable) {
                    submit.textContent = "در حال حاضر قابل پرداخت نیست";
                } else if (!hasGateway) {
                    submit.textContent = "درگاه فعالی موجود نیست";
                } else if (gatewaySelection && typeof gatewaySelection.refreshSubmitText === "function") {
                    gatewaySelection.refreshSubmitText();
                } else {
                    submit.textContent = "پرداخت آنلاین";
                }
            }
            setFeedback(feedback, payable ? "" : (item.statusMessage || "این آیتم در حال حاضر قابل پرداخت نیست."), payable ? "" : "is-error");

            if (!form) {
                return;
            }

            form.onsubmit = function (event) {
                event.preventDefault();
                if (!payable) {
                    return;
                }

                var payerName = String(($("buy-payer-name") && $("buy-payer-name").value) || "").trim();
                var payerPhone = normalizePhone(String(($("buy-payer-phone") && $("buy-payer-phone").value) || ""));
                var payerStudentNumber = normalizeDigits(String(($("buy-payer-student-number") && $("buy-payer-student-number").value) || "")).replace(/\D+/g, "");

                if (!payerName) {
                    setFeedback(feedback, "نام پرداخت‌کننده الزامی است.", "is-error");
                    return;
                }
                if (!payerPhone || payerPhone.length < 10) {
                    setFeedback(feedback, "شماره موبایل معتبر وارد کنید.", "is-error");
                    return;
                }

                var extraData = {};
                var valid = true;
                Array.prototype.slice.call(form.querySelectorAll("[data-extra-field='true']")).forEach(function (field) {
                    var key = String(field.dataset.fieldName || "").trim();
                    var required = String(field.dataset.fieldRequired || "0") === "1";
                    var label = String(field.dataset.fieldLabel || key || "فیلد");
                    if (!key) {
                        return;
                    }
                    var value = String(field.value || "").trim();
                    if (required && !value && valid) {
                        setFeedback(feedback, "فیلد «" + label + "» الزامی است.", "is-error");
                        valid = false;
                        return;
                    }
                    extraData[key] = value;
                });

                if (!valid) {
                    return;
                }

                var selectedGateway = gatewaySelection && typeof gatewaySelection.getSelected === "function"
                    ? gatewaySelection.getSelected()
                    : "";
                if (!selectedGateway) {
                    setFeedback(feedback, "لطفا روش پرداخت را انتخاب کنید.", "is-error");
                    return;
                }

                if (submit) submit.disabled = true;
                setFeedback(feedback, "در حال انتقال به درگاه پرداخت...", "");

                apiPost("createOrder", {
                    slug: item.slug,
                    payerName: payerName,
                    payerPhone: payerPhone,
                    payerStudentNumber: payerStudentNumber,
                    extraFormData: JSON.stringify(extraData),
                    gateway: selectedGateway
                }).then(function (response) {
                    if (!response || !response.success || !response.redirectUrl) {
                        if (submit) submit.disabled = false;
                        setFeedback(feedback, (response && response.error) || "ایجاد درخواست پرداخت انجام نشد.", "is-error");
                        return;
                    }
                    window.location.href = response.redirectUrl;
                }).catch(function () {
                    if (submit) submit.disabled = false;
                    setFeedback(feedback, "ارتباط با سرور برقرار نشد.", "is-error");
                });
            };
        }).catch(function () {
            if (titleNode) titleNode.textContent = "دریافت آیتم پرداخت انجام نشد";
            if (shortNode) shortNode.textContent = "ارتباط با سرور برقرار نشد.";
            if (form) form.hidden = true;
        });
    }

    function initResultPage() {
        var orderToken = readParam("orderToken");
        var statusNode = $("buy-result-status");
        var titleNode = $("buy-result-title");
        var messageNode = $("buy-result-message");
        var summaryNode = $("buy-result-summary");
        var actionsNode = $("buy-result-actions");

        if (!orderToken) {
            if (titleNode) titleNode.textContent = "اطلاعات پرداخت پیدا نشد";
            if (messageNode) messageNode.textContent = "شناسه سفارش معتبر نیست.";
            return;
        }

        apiGet("publicOrderResult", { orderToken: orderToken }).then(function (payload) {
            if (!payload || !payload.success || !payload.order) {
                if (titleNode) titleNode.textContent = "نتیجه پرداخت در دسترس نیست";
                if (messageNode) messageNode.textContent = (payload && payload.error) || "امکان دریافت وضعیت پرداخت وجود ندارد.";
                return;
            }

            var order = payload.order;
            var item = order.item || null;
            if (statusNode) {
                statusNode.textContent = order.statusLabel || "در انتظار";
                statusNode.className = "buy-status " + statusClass(order.status);
            }
            if (titleNode) {
                titleNode.textContent = order.status === "success"
                    ? "پرداخت شما ثبت و تایید شد"
                    : (order.status === "pending" ? "پرداخت در حال بررسی است" : "پرداخت کامل نشد");
            }
            if (messageNode) {
                messageNode.textContent = order.message || "وضعیت پرداخت به‌روزرسانی شد.";
            }
            if (summaryNode) {
                var rows = [
                    { label: "مبلغ", value: money(order.amount || 0) },
                    { label: "پرداخت‌کننده", value: order.payerName || "—" },
                    { label: "موبایل", value: order.payerPhone || "—" },
                    { label: "زمان ثبت", value: formatDateTime(order.createdAt, "—") }
                ];
                if (item && item.title) {
                    rows.unshift({ label: "آیتم", value: item.title });
                }
                if (order.refId) {
                    rows.push({ label: "کد رهگیری", value: order.refId });
                } else if (order.authority) {
                    rows.push({ label: "authority", value: order.authority });
                }
                if (order.verifiedAt) {
                    rows.push({ label: "زمان تایید", value: formatDateTime(order.verifiedAt, "—") });
                }

                summaryNode.innerHTML = rows.map(function (row) {
                    return [
                        '<div class="buy-result-row">',
                        '  <span>' + text(row.label) + "</span>",
                        '  <strong>' + text(row.value) + "</strong>",
                        "</div>"
                    ].join("");
                }).join("");
            }
            if (actionsNode) {
                var itemHref = item && item.slug ? "/buy/item/?slug=" + encodeURIComponent(String(item.slug)) : "/buy/";
                actionsNode.innerHTML = [
                    '<a class="shell-action-btn shell-action-btn-primary" href="' + itemHref + '">بازگشت به صفحه پرداخت</a>',
                    '<a class="shell-action-btn" href="/buy/">مشاهده سایر پرداخت‌ها</a>'
                ].join("");
            }
        }).catch(function () {
            if (titleNode) titleNode.textContent = "نتیجه پرداخت دریافت نشد";
            if (messageNode) messageNode.textContent = "ارتباط با سرور برقرار نشد.";
        });
    }

    var page = document.body && document.body.dataset ? String(document.body.dataset.buyPage || "") : "";
    if (page === "list") {
        initListPage();
        return;
    }
    if (page === "item") {
        initItemPage();
        return;
    }
    if (page === "result") {
        initResultPage();
    }
})();
