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
        var amount = Math.max(0, Number(value) || 0);
        return amount.toLocaleString("fa-IR") + " ریال";
    }

    function statusClass(stateKey) {
        switch (String(stateKey || "")) {
            case "active":
                return "is-active";
            case "full":
                return "is-full";
            case "expired":
                return "is-expired";
            case "inactive":
                return "is-inactive";
            case "success":
                return "is-success";
            case "failed":
                return "is-failed";
            case "canceled":
                return "is-canceled";
            default:
                return "";
        }
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

    function setFeedback(node, message, kind) {
        if (!node) return;
        node.className = "buy-feedback" + (kind ? (" " + kind) : "");
        node.textContent = message || "";
    }

    function readSlugFromLocation() {
        var params = new URLSearchParams(window.location.search);
        var slug = String(params.get("slug") || "").trim();
        if (slug) {
            return slug;
        }

        var parts = String(window.location.pathname || "").split("/").filter(Boolean);
        var buyIndex = parts.indexOf("item");
        if (buyIndex >= 0 && parts.length > buyIndex + 1) {
            return parts[buyIndex + 1];
        }
        return "";
    }

    function renderList(items) {
        var root = $("buy-list-root");
        if (!root) return;

        if (!Array.isArray(items) || !items.length) {
            root.innerHTML = '<div class="buy-empty">فعلا آیتم فعالی برای پرداخت وجود ندارد.</div>';
            return;
        }

        root.innerHTML = items.map(function (item) {
            var hero = String(item.heroImage || "").trim();
            var state = item.state || {};
            var statusKey = String(state.key || item.status || "");
            var heroHtml = hero
                ? '<img src="' + text(hero) + '" alt="' + text(item.title || "تصویر آیتم پرداخت") + '">'
                : '<span>بدون تصویر</span>';
            return [
                '<article class="buy-item-card">',
                '  <div class="buy-item-card__hero">' + heroHtml + "</div>",
                '  <div class="buy-item-card__head">',
                '    <div>',
                '      <h3 class="buy-item-card__title">' + text(item.title || "بدون عنوان") + "</h3>",
                '      <span class="buy-status ' + statusClass(statusKey) + '">' + text(state.label || "نامشخص") + "</span>",
                "    </div>",
                '    <strong class="buy-item-card__price">' + text(money(item.price || 0)) + "</strong>",
                "  </div>",
                '  <p class="buy-muted">' + text(item.shortDescription || "—") + "</p>",
                '  <a class="shell-action-btn shell-action-btn-primary" href="/buy/item/?slug=' + encodeURIComponent(String(item.slug || "")) + '">مشاهده و پرداخت</a>',
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
                '<label>',
                "  <span>" + text(label) + (required ? " *" : "") + "</span>",
                "  " + control,
                "</label>"
            ].join("");
        }).join("");
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
        var heroNode = $("buy-item-hero");
        var galleryNode = $("buy-item-gallery");
        var priceNode = $("buy-item-price");
        var stateNode = $("buy-item-state");
        var specsNode = $("buy-item-specs");
        var form = $("buy-order-form");
        var submit = $("buy-order-submit");
        var feedback = $("buy-order-feedback");

        if (!slug) {
            if (titleNode) titleNode.textContent = "آیتم پرداخت پیدا نشد";
            if (shortNode) shortNode.textContent = "لینک معتبر نیست.";
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
            var stateKey = String(state.key || item.status || "");
            var payable = !!state.isPayable;

            if (titleNode) titleNode.textContent = item.title || "آیتم پرداخت";
            if (shortNode) shortNode.textContent = item.shortDescription || "—";
            if (fullNode) fullNode.textContent = item.fullDescription || "—";
            if (priceNode) priceNode.textContent = money(item.price || 0);
            if (stateNode) {
                stateNode.textContent = state.label || "نامشخص";
                stateNode.className = "buy-status " + statusClass(stateKey);
            }

            if (heroNode) {
                if (item.heroImage) {
                    heroNode.innerHTML = '<img src="' + text(item.heroImage) + '" alt="' + text(item.title || "تصویر آیتم") + '">';
                } else {
                    heroNode.innerHTML = "";
                }
            }

            if (galleryNode) {
                var gallery = Array.isArray(item.gallery) ? item.gallery : [];
                if (!gallery.length) {
                    galleryNode.innerHTML = "";
                } else {
                    galleryNode.innerHTML = gallery.map(function (url) {
                        return '<div class="buy-gallery__item"><img src="' + text(url) + '" alt="گالری"></div>';
                    }).join("");
                }
            }

            if (specsNode) {
                var specs = Array.isArray(item.specifications) ? item.specifications : [];
                if (!specs.length) {
                    specsNode.innerHTML = '<div class="buy-empty">جزئیات تکمیلی ثبت نشده است.</div>';
                } else {
                    specsNode.innerHTML = specs.map(function (spec) {
                        return '<div class="buy-spec-row"><span>' + text(spec.label || "عنوان") + '</span><strong>' + text(spec.value || "—") + "</strong></div>";
                    }).join("");
                }
            }

            renderRequiredFields(item.requiredFields || []);
            if (submit) {
                submit.disabled = !payable;
                submit.textContent = payable ? "پرداخت آنلاین" : "در حال حاضر قابل پرداخت نیست";
            }
            if (!payable) {
                setFeedback(feedback, payload.item.statusMessage || "این آیتم در حال حاضر قابل پرداخت نیست.", "is-error");
            }

            if (!form) {
                return;
            }

            form.addEventListener("submit", function (event) {
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
                var fields = Array.prototype.slice.call(form.querySelectorAll("[data-extra-field='true']"));
                fields.forEach(function (field) {
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

                if (submit) submit.disabled = true;
                setFeedback(feedback, "در حال انتقال به درگاه پرداخت...", "");

                apiPost("createOrder", {
                    slug: item.slug,
                    payerName: payerName,
                    payerPhone: payerPhone,
                    payerStudentNumber: payerStudentNumber,
                    extraFormData: JSON.stringify(extraData)
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
            }, { once: true });
        }).catch(function () {
            if (titleNode) titleNode.textContent = "دریافت آیتم پرداخت انجام نشد";
            if (shortNode) shortNode.textContent = "ارتباط با سرور برقرار نشد.";
            if (form) form.hidden = true;
        });
    }

    var page = document.body && document.body.dataset ? String(document.body.dataset.buyPage || "") : "";
    if (page === "list") {
        initListPage();
        return;
    }

    if (page === "item") {
        initItemPage();
    }
})();
