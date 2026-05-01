(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    var root = document.getElementById("payment-collection-root");
    var params = new URLSearchParams(window.location.search);
    var token = String(params.get("token") || "").trim();
    var paymentOrderToken = String(params.get("paymentOrderToken") || "").trim();
    var state = {
        viewer: null,
        collection: null,
        result: null,
        loading: false
    };

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case "\"": return "&quot;";
                default: return char;
            }
        });
    }

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
                payload = { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function apiGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse);
    }

    function apiPost(action, payload) {
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(parseApiResponse);
    }

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function normalizeDigits(value) {
        return String(value || "").replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
            var code = char.charCodeAt(0);
            if (code >= 0x06F0 && code <= 0x06F9) return String(code - 0x06F0);
            return String(code - 0x0660);
        });
    }

    function userDisplayName() {
        var user = state.viewer || {};
        return String(user.name || "").trim();
    }

    function renderLogin() {
        var loginUrl = window.Dent1402Auth.loginUrl
            ? window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search)
            : "/account/";
        root.innerHTML = [
            '<section class="buy-auth-required">',
            '  <span class="buy-kicker">ورود لازم است</span>',
            "  <h2>برای مشاهده و پرداخت این هزینه وارد حساب خود شوید</h2>",
            "  <p>پس از ورود، وضعیت پرداخت با حساب شما ثبت می‌شود.</p>",
            '  <a class="buy-primary-btn" href="' + escapeHtml(loginUrl) + '">ورود به حساب</a>',
            "</section>"
        ].join("");
    }

    function gatewayOptions(collection) {
        var gateways = collection && collection.paymentGateways && Array.isArray(collection.paymentGateways.gateways)
            ? collection.paymentGateways.gateways
            : [];
        var enabled = gateways.filter(function (gateway) {
            return !!gateway.isEnabled;
        });
        if (!enabled.length) {
            return '<div class="buy-alert buy-alert--error">درگاه فعالی برای این پرداخت وجود ندارد.</div>';
        }
        return enabled.map(function (gateway, index) {
            var checked = gateway.isDefault || index === 0;
            return [
                '<label class="buy-gateway-option">',
                '  <input type="radio" name="collection-gateway" value="' + escapeHtml(gateway.key || "") + '"' + (checked ? " checked" : "") + ">",
                '  <span><strong>' + escapeHtml(gateway.label || "پرداخت آنلاین") + "</strong><small>" + escapeHtml(gateway.provider || "") + "</small></span>",
                "</label>"
            ].join("");
        }).join("");
    }

    function renderCollection() {
        var collection = state.collection;
        if (!collection) {
            root.innerHTML = '<div class="buy-empty">لینک پرداخت پیدا نشد.</div>';
            return;
        }
        var result = state.result;
        var resultHtml = "";
        if (collection.paid) {
            resultHtml = '<div class="buy-alert buy-alert--success">' + escapeHtml(collection.successMessage || "پرداخت شما تایید شده است.") + "</div>";
        } else if (result && result.status && result.status !== "success") {
            resultHtml = '<div class="buy-alert buy-alert--error">' + escapeHtml(result.message || collection.failureMessage || "پرداخت تایید نشد.") + "</div>";
        }
        var disabled = String(collection.status || "") !== "active" || !!collection.paid;
        root.innerHTML = [
            '<section class="payment-collection-card">',
            '  <div class="payment-collection-card__head">',
            '    <span class="buy-kicker">پرداخت هزینه</span>',
            '    <h2>' + escapeHtml(collection.title || "پرداخت هزینه") + "</h2>",
            '    <p>' + escapeHtml(collection.description || "") + "</p>",
            "  </div>",
            '  <div class="payment-collection-amount">' + escapeHtml(money(collection.amount || 0)) + "</div>",
            resultHtml,
            disabled && !collection.paid ? '<div class="buy-alert buy-alert--error">این لینک پرداخت در حال حاضر فعال نیست.</div>' : "",
            '  <form id="payment-collection-form" class="payment-collection-form" novalidate>',
            '    <label class="payments-field"><span>نام پرداخت‌کننده</span><input id="payment-collection-name" type="text" maxlength="120" value="' + escapeHtml(userDisplayName()) + '" required></label>',
            '    <label class="payments-field"><span>شماره موبایل</span><input id="payment-collection-phone" type="tel" inputmode="tel" dir="ltr" data-latin-digits="true" maxlength="14" required></label>',
            '    <div class="payment-collection-gateways">' + gatewayOptions(collection) + "</div>",
            '    <button class="buy-primary-btn" type="submit"' + (disabled ? " disabled" : "") + '>' + (collection.paid ? "پرداخت شده" : "پرداخت") + "</button>",
            '    <div id="payment-collection-feedback" class="account-feedback account-feedback--inline" aria-live="polite"></div>',
            "  </form>",
            "</section>"
        ].join("");
    }

    function setFeedback(message, kind) {
        var node = document.getElementById("payment-collection-feedback");
        if (!node) return;
        node.textContent = message || "";
        node.className = "account-feedback account-feedback--inline" + (kind ? " " + kind : "");
    }

    async function loadCollection() {
        if (!token) {
            root.innerHTML = '<div class="buy-empty">شناسه لینک پرداخت در آدرس وجود ندارد.</div>';
            return;
        }
        if (state.loading) return;
        state.loading = true;
        root.innerHTML = '<div class="buy-empty">در حال دریافت اطلاعات پرداخت...</div>';
        try {
            var response = await apiGet("publicCollection", { token: token });
            if (response && response.httpStatus === 401) {
                renderLogin();
                return;
            }
            if (!response || !response.success || !response.collection) {
                throw new Error((response && response.error) || "بارگذاری لینک پرداخت انجام نشد.");
            }
            state.collection = response.collection;
            if (paymentOrderToken) {
                var resultResponse = await apiGet("publicOrderResult", { orderToken: paymentOrderToken });
                if (resultResponse && resultResponse.success && resultResponse.order) {
                    state.result = resultResponse.order;
                }
            }
            renderCollection();
        } catch (error) {
            root.innerHTML = '<div class="buy-empty">' + escapeHtml(error && error.message ? error.message : "بارگذاری انجام نشد.") + "</div>";
        } finally {
            state.loading = false;
        }
    }

    async function submitPayment(event) {
        event.preventDefault();
        var form = event.target;
        var button = form.querySelector("button[type='submit']");
        var selectedGateway = form.querySelector("input[name='collection-gateway']:checked");
        button.disabled = true;
        setFeedback("در حال انتقال به درگاه پرداخت...", "");
        try {
            var response = await apiPost("createCollectionOrder", {
                token: token,
                payerName: String(document.getElementById("payment-collection-name").value || "").trim(),
                payerPhone: normalizeDigits(document.getElementById("payment-collection-phone").value || ""),
                gateway: selectedGateway ? selectedGateway.value : ""
            });
            if (response && response.httpStatus === 401) {
                renderLogin();
                return;
            }
            if (!response || !response.success || !response.redirectUrl) {
                throw new Error((response && response.error) || "ایجاد پرداخت انجام نشد.");
            }
            window.location.href = response.redirectUrl;
        } catch (error) {
            button.disabled = false;
            setFeedback(error && error.message ? error.message : "ایجاد پرداخت انجام نشد.", "error");
        }
    }

    root.addEventListener("submit", function (event) {
        if (event.target && event.target.id === "payment-collection-form") {
            submitPayment(event);
        }
    });

    window.Dent1402Auth.onChange(function (detail) {
        if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
            root.innerHTML = '<div class="buy-empty">در حال بررسی ورود...</div>';
            return;
        }
        if (!detail.loggedIn) {
            renderLogin();
            return;
        }
        state.viewer = detail.user || null;
        loadCollection();
    });
})();
