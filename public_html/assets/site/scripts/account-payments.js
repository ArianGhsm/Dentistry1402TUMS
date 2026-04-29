(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var panel = $("account-panel");
    var rowMeta = $("account-row-payments-meta");
    var summaryRoot = $("payments-summary");
    var feedbackNode = $("payments-feedback");
    var notificationsRoot = $("payments-notifications-list");
    var itemsRoot = $("payments-items-grid");
    var ordersSummaryRoot = $("payments-orders-summary");
    var ordersRoot = $("payments-orders-list");
    var orderDetailRoot = $("payments-order-detail");
    var itemForm = $("payments-item-form");
    var itemFormFeedback = $("payments-item-form-feedback");
    var filterForm = $("payments-orders-filter-form");
    var filterItem = $("payments-filter-item");
    var filterStatus = $("payments-filter-status");
    var filterDateFrom = $("payments-filter-date-from");
    var filterDateTo = $("payments-filter-date-to");
    var filterQuery = $("payments-filter-query");

    var state = {
        currentUser: null,
        dashboardLoaded: false,
        loadingDashboard: false,
        loadingOrders: false,
        selectedOrderId: 0,
        items: [],
        notifications: [],
        orders: []
    };

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

    function request(action, payload, method) {
        var verb = String(method || "GET").toUpperCase();
        if (verb === "POST") {
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

        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse);
    }

    function consumeUnauthorized(payload, fallbackText) {
        var auth = window.Dent1402Auth;
        var message = fallbackText || "نشست شما منقضی شده است.";
        if (!auth || typeof auth !== "object") {
            return false;
        }

        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(payload, message);
            }
        } catch (_error) {
            // Continue with fallback.
        }

        if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((payload && payload.error) || message);
            }
            return true;
        }
        return false;
    }

    function escapeHtml(value) {
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

    function setFeedback(node, text, kind, loading) {
        if (!node) {
            return;
        }
        node.className = "account-feedback account-feedback--inline" + (kind ? (" " + kind) : "");
        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + escapeHtml(text || "") + "</span>",
                "</div>"
            ].join("");
            return;
        }
        node.textContent = text || "";
    }

    function summaryCard(label, value, meta, tone) {
        return [
            '<article class="owner-summary-card' + (tone ? (" owner-summary-card--" + tone) : "") + '">',
            '  <span>' + escapeHtml(label) + "</span>",
            '  <strong>' + escapeHtml(value) + "</strong>",
            '  <small>' + escapeHtml(meta) + "</small>",
            "</article>"
        ].join("");
    }

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function absoluteUrl(path) {
        try {
            return new URL(String(path || ""), window.location.origin).href;
        } catch (_error) {
            return String(path || "");
        }
    }

    function statusLabel(status) {
        switch (String(status || "")) {
            case "success":
                return "موفق";
            case "failed":
                return "ناموفق";
            case "canceled":
                return "لغو شده";
            case "expired":
                return "منقضی شده";
            default:
                return "در انتظار";
        }
    }

    function statusTone(status) {
        switch (String(status || "")) {
            case "success":
                return "ok";
            case "failed":
            case "canceled":
            case "expired":
                return "danger";
            default:
                return "warn";
        }
    }

    function itemStateLabel(item) {
        var state = item && item.state ? item.state : {};
        return String(state.label || item.status || "نامشخص");
    }

    function itemStateTone(item) {
        var key = String(item && item.state ? item.state.key || "" : "");
        if (key === "active") return "ok";
        if (key === "upcoming" || key === "full") return "warn";
        return "danger";
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

    function normalizeDigits(value) {
        return String(value || "")
            .replace(/[\u06F0-\u06F9]/g, function (ch) {
                return String("\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".indexOf(ch));
            })
            .replace(/[\u0660-\u0669]/g, function (ch) {
                return String("\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669".indexOf(ch));
            });
    }

    function slugifyLatin(value) {
        return String(value || "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .slice(0, 80);
    }

    function toDatetimeLocal(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return "";
        }
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return "";
        }
        var year = parsed.getFullYear();
        var month = String(parsed.getMonth() + 1).padStart(2, "0");
        var day = String(parsed.getDate()).padStart(2, "0");
        var hours = String(parsed.getHours()).padStart(2, "0");
        var minutes = String(parsed.getMinutes()).padStart(2, "0");
        return year + "-" + month + "-" + day + "T" + hours + ":" + minutes;
    }

    function fromDatetimeLocal(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return "";
        }
        if (raw.length === 16) {
            return raw.replace("T", " ") + ":00";
        }
        return raw.replace("T", " ");
    }

    function prettyJson(value) {
        try {
            return JSON.stringify(value == null ? {} : value, null, 2);
        } catch (_error) {
            return "{}";
        }
    }

    function isOwner() {
        return !!(state.currentUser && state.currentUser.isOwner);
    }

    function isPaymentsSurfaceOpen() {
        return !!(panel && panel.dataset && panel.dataset.surface === "payments");
    }

    function currentFilters() {
        return {
            itemId: filterItem ? String(filterItem.value || "0") : "0",
            status: filterStatus ? String(filterStatus.value || "all") : "all",
            dateFrom: filterDateFrom ? String(filterDateFrom.value || "") : "",
            dateTo: filterDateTo ? String(filterDateTo.value || "") : "",
            query: filterQuery ? String(filterQuery.value || "").trim() : ""
        };
    }

    function updateRowMeta(summary) {
        if (!rowMeta || !summary) {
            return;
        }
        rowMeta.textContent = [
            "موفق " + Number(summary.totalSuccess || 0).toLocaleString("fa-IR"),
            "دریافتی " + money(summary.totalReceived || 0)
        ].join(" • ");
    }

    function renderSummary(summary) {
        if (!summaryRoot) {
            return;
        }
        if (!summary) {
            summaryRoot.innerHTML = summaryCard("پرداخت", "—", "آمار ماژول خرید هنوز بارگذاری نشده است.");
            return;
        }
        summaryRoot.innerHTML = [
            summaryCard("آیتم فعال", String(Number(summary.activeItems || 0).toLocaleString("fa-IR")), "از " + String(Number(summary.totalItems || 0).toLocaleString("fa-IR")) + " آیتم ثبت‌شده", (summary.activeItems || 0) > 0 ? "ok" : "warn"),
            summaryCard("دریافتی کل", money(summary.totalReceived || 0), "مجموع پرداخت‌های verify شده", (summary.totalReceived || 0) > 0 ? "ok" : ""),
            summaryCard("پرداخت موفق", String(Number(summary.totalSuccess || 0).toLocaleString("fa-IR")), "تعداد تراکنش‌های تاییدشده", (summary.totalSuccess || 0) > 0 ? "ok" : ""),
            summaryCard("در انتظار", String(Number(summary.totalPending || 0).toLocaleString("fa-IR")), "سفارش‌های ساخته‌شده و در حال بررسی", (summary.totalPending || 0) > 0 ? "warn" : ""),
            summaryCard("ناموفق", String((Number(summary.totalFailed || 0) + Number(summary.totalCanceled || 0) + Number(summary.totalExpired || 0)).toLocaleString("fa-IR")), "ناموفق، لغوشده یا منقضی", ((summary.totalFailed || 0) + (summary.totalCanceled || 0) + (summary.totalExpired || 0)) > 0 ? "danger" : ""),
            summaryCard("اعلان نخوانده", String(Number(summary.unreadNotifications || 0).toLocaleString("fa-IR")), "اعلان‌های مالی جدید", (summary.unreadNotifications || 0) > 0 ? "warn" : "ok")
        ].join("");
        updateRowMeta(summary);
    }

    function renderNotifications() {
        if (!notificationsRoot) {
            return;
        }
        if (!state.notifications.length) {
            notificationsRoot.innerHTML = '<div class="owner-empty">اعلان مالی ثبت نشده است.</div>';
            return;
        }
        notificationsRoot.innerHTML = state.notifications.map(function (note) {
            var read = !!String(note.read_at || "").trim();
            return [
                '<article class="payments-note' + (read ? "" : " is-unread") + '">',
                '  <div class="payments-note__head">',
                '    <strong>' + escapeHtml(note.title || "اعلان مالی") + "</strong>",
                '    <span>' + escapeHtml(formatDateTime(note.created_at, "—")) + "</span>",
                "  </div>",
                '  <p>' + escapeHtml(note.body || "—") + "</p>",
                '  <div class="payments-note__actions">',
                note.related_order_id ? '<button class="shell-action-btn" type="button" data-payment-order-detail="' + escapeHtml(note.related_order_id) + '">جزئیات تراکنش</button>' : "",
                read ? '<span class="payments-note__state">خوانده شده</span>' : '<button class="shell-action-btn" type="button" data-payment-read-note="' + escapeHtml(note.id) + '">خواندم</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function populateFilterItems() {
        if (!filterItem) {
            return;
        }
        var current = String(filterItem.value || "0");
        var options = ['<option value="0">همه آیتم‌ها</option>'];
        state.items.forEach(function (item) {
            options.push('<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.title || "بدون عنوان") + "</option>");
        });
        filterItem.innerHTML = options.join("");
        filterItem.value = current;
    }

    function renderItems() {
        if (!itemsRoot) {
            return;
        }
        if (!state.items.length) {
            itemsRoot.innerHTML = '<div class="owner-empty">هنوز آیتم پرداختی ساخته نشده است.</div>';
            return;
        }
        itemsRoot.innerHTML = state.items.map(function (item) {
            var publicUrl = absoluteUrl(item.publicUrl || "");
            return [
                '<article class="payments-item-card">',
                '  <div class="payments-item-card__head">',
                '    <div>',
                '      <span class="payments-pill payments-pill--' + escapeHtml(itemStateTone(item)) + '">' + escapeHtml(itemStateLabel(item)) + "</span>",
                '      <h4>' + escapeHtml(item.title || "بدون عنوان") + "</h4>",
                '      <p dir="ltr">' + escapeHtml(item.slug || "") + "</p>",
                "    </div>",
                '    <strong>' + escapeHtml(money(item.price || 0)) + "</strong>",
                "  </div>",
                '  <div class="payments-item-card__meta">',
                '    <span>لینک عمومی: <a href="' + escapeHtml(publicUrl || "#") + '" target="_blank" rel="noopener">' + escapeHtml(publicUrl || "—") + "</a></span>",
                '    <span>فروخته‌شده: ' + escapeHtml(String(Number(item.soldCount || 0).toLocaleString("fa-IR"))) + "</span>",
                '    <span>مهلت: ' + escapeHtml(formatDateTime(item.expiresAt, "بدون مهلت")) + "</span>",
                "  </div>",
                '  <div class="payments-item-card__actions">',
                '    <button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-edit-item="' + escapeHtml(item.id) + '">ویرایش</button>',
                '    <button class="shell-action-btn" type="button" data-payment-copy-link="' + escapeHtml(publicUrl || "") + '">کپی لینک</button>',
                '    <button class="shell-action-btn" type="button" data-payment-toggle-item="' + escapeHtml(item.id) + '" data-payment-enabled="' + (item.status === "active" ? "0" : "1") + '">' + (item.status === "active" ? "غیرفعال‌کردن" : "فعال‌کردن") + "</button>",
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderOrdersSummary(summary) {
        if (!ordersSummaryRoot) {
            return;
        }
        if (!summary) {
            ordersSummaryRoot.innerHTML = "";
            return;
        }
        ordersSummaryRoot.innerHTML = [
            summaryCard("نمایش‌شده", String(Number(summary.totalOrders || 0).toLocaleString("fa-IR")), "تعداد تراکنش‌های خروجی فعلی"),
            summaryCard("موفق", String(Number(summary.totalSuccess || 0).toLocaleString("fa-IR")), "پرداخت تاییدشده در این فیلتر", (summary.totalSuccess || 0) > 0 ? "ok" : ""),
            summaryCard("دریافتی", money(summary.totalReceived || 0), "جمع مبالغ verify شده", (summary.totalReceived || 0) > 0 ? "ok" : ""),
            summaryCard("در انتظار", String(Number(summary.totalPending || 0).toLocaleString("fa-IR")), "نیازمند callback/verify", (summary.totalPending || 0) > 0 ? "warn" : "")
        ].join("");
    }

    function renderOrders() {
        if (!ordersRoot) {
            return;
        }
        if (!state.orders.length) {
            ordersRoot.innerHTML = '<div class="owner-empty">تراکنشی با این فیلتر پیدا نشد.</div>';
            return;
        }
        ordersRoot.innerHTML = state.orders.map(function (order) {
            return [
                '<article class="payments-order-card' + (state.selectedOrderId === Number(order.id) ? " is-active" : "") + '">',
                '  <div class="payments-order-card__head">',
                '    <div>',
                '      <span class="payments-pill payments-pill--' + escapeHtml(statusTone(order.status)) + '">' + escapeHtml(statusLabel(order.status)) + "</span>",
                '      <h4>' + escapeHtml(order.payerName || "بدون نام") + "</h4>",
                '      <p>' + escapeHtml(order.itemTitle || "آیتم نامشخص") + "</p>",
                "    </div>",
                '    <strong>' + escapeHtml(money(order.amount || 0)) + "</strong>",
                "  </div>",
                '  <div class="payments-order-card__meta">',
                '    <span>موبایل: ' + escapeHtml(order.payerPhone || "—") + "</span>",
                '    <span>authority: ' + escapeHtml(order.authority || "—") + "</span>",
                '    <span>ref id: ' + escapeHtml(order.refId || "—") + "</span>",
                '    <span>ثبت: ' + escapeHtml(formatDateTime(order.createdAt, "—")) + "</span>",
                "  </div>",
                '  <div class="payments-order-card__actions">',
                '    <button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-order-detail="' + escapeHtml(order.id) + '">جزئیات</button>',
                order.publicToken ? '<a class="shell-action-btn" href="/buy/result/?orderToken=' + encodeURIComponent(String(order.publicToken)) + '" target="_blank" rel="noopener">صفحه نتیجه</a>' : "",
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderOrderDetail(order, snapshot) {
        if (!orderDetailRoot) {
            return;
        }
        if (!order) {
            orderDetailRoot.innerHTML = '<div class="owner-empty">برای دیدن جزئیات، یکی از تراکنش‌ها را انتخاب کن.</div>';
            return;
        }
        var extras = order.extraFormData && typeof order.extraFormData === "object"
            ? Object.keys(order.extraFormData).map(function (key) {
                return '<div class="payments-detail-row"><span>' + escapeHtml(key) + '</span><strong>' + escapeHtml(order.extraFormData[key]) + "</strong></div>";
            }).join("")
            : "";
        orderDetailRoot.innerHTML = [
            '<div class="payments-detail-grid">',
            '  <div class="payments-detail-row"><span>پرداخت‌کننده</span><strong>' + escapeHtml(order.payerName || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>شماره موبایل</span><strong>' + escapeHtml(order.payerPhone || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>شماره دانشجویی</span><strong>' + escapeHtml(order.payerStudentNumber || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>وضعیت</span><strong>' + escapeHtml(statusLabel(order.status)) + "</strong></div>",
            '  <div class="payments-detail-row"><span>مبلغ</span><strong>' + escapeHtml(money(order.amount || 0)) + "</strong></div>",
            '  <div class="payments-detail-row"><span>درگاه</span><strong>' + escapeHtml(order.gateway || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>authority</span><strong>' + escapeHtml(order.authority || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>ref id</span><strong>' + escapeHtml(order.refId || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>ثبت سفارش</span><strong>' + escapeHtml(formatDateTime(order.createdAt, "—")) + "</strong></div>",
            '  <div class="payments-detail-row"><span>تایید نهایی</span><strong>' + escapeHtml(formatDateTime(order.verifiedAt, "—")) + "</strong></div>",
            extras,
            "</div>",
            '<div class="payments-code-block">',
            "  <h5>اسنپ‌شات پاسخ درگاه</h5>",
            "  <pre>" + escapeHtml(prettyJson(snapshot || {})) + "</pre>",
            "</div>"
        ].join("");
    }

    function resetItemForm() {
        if (!itemForm) {
            return;
        }
        itemForm.reset();
        $("payments-item-id").value = "";
        $("payments-item-gallery").value = "[]";
        $("payments-item-specifications").value = "[]";
        $("payments-item-required-fields").value = "[]";
        $("payments-item-success-message").value = "پرداخت شما با موفقیت ثبت شد.";
        $("payments-item-failure-message").value = "پرداخت شما ناموفق بود.";
        setFeedback(itemFormFeedback, "", "");
    }

    function fillItemForm(itemId) {
        var item = state.items.find(function (entry) {
            return Number(entry.id) === Number(itemId);
        });
        if (!item) {
            return;
        }
        $("payments-item-id").value = item.id || "";
        $("payments-item-title").value = item.title || "";
        $("payments-item-slug").value = item.slug || "";
        $("payments-item-price").value = String(item.price || "");
        $("payments-item-status").value = item.status || "inactive";
        $("payments-item-short-description").value = item.shortDescription || "";
        $("payments-item-full-description").value = item.fullDescription || "";
        $("payments-item-hero-image").value = item.heroImage || "";
        $("payments-item-gallery").value = prettyJson(item.gallery || []);
        $("payments-item-specifications").value = prettyJson(item.specifications || []);
        $("payments-item-required-fields").value = prettyJson(item.requiredFields || []);
        $("payments-item-success-message").value = item.successMessage || "پرداخت شما با موفقیت ثبت شد.";
        $("payments-item-failure-message").value = item.failureMessage || "پرداخت شما ناموفق بود.";
        $("payments-item-starts-at").value = toDatetimeLocal(item.startsAt);
        $("payments-item-expires-at").value = toDatetimeLocal(item.expiresAt);
        $("payments-item-capacity").value = item.capacity == null ? "" : String(item.capacity);
        setFeedback(itemFormFeedback, "حالت ویرایش برای «" + (item.title || "آیتم") + "» فعال شد.", "success");
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function renderDashboard() {
        renderItems();
        renderNotifications();
        populateFilterItems();
    }

    function upsertItemInState(item) {
        if (!item || typeof item !== "object") {
            return;
        }
        var itemId = Number(item.id || 0);
        if (!itemId) {
            return;
        }
        var index = state.items.findIndex(function (entry) {
            return Number(entry.id || 0) === itemId;
        });
        if (index >= 0) {
            state.items[index] = item;
        } else {
            state.items.unshift(item);
        }
        renderItems();
        populateFilterItems();
    }

    async function loadDashboard(silent) {
        if (!isOwner() || state.loadingDashboard) {
            return;
        }
        state.loadingDashboard = true;
        if (!silent) {
            setFeedback(feedbackNode, "در حال دریافت آمار پرداخت‌ها...", "", true);
            renderSummary(null);
        }

        try {
            var response = await request("ownerDashboard", {}, "GET");
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success) {
                setFeedback(feedbackNode, (response && response.error) || "دریافت آمار پرداخت انجام نشد.", "error");
                return;
            }

            state.dashboardLoaded = true;
            state.items = Array.isArray(response.items) ? response.items : [];
            state.notifications = Array.isArray(response.notifications) ? response.notifications : [];
            renderSummary(response.summary || {});
            renderDashboard();
            setFeedback(feedbackNode, "آمار و آیتم‌های پرداخت به‌روزرسانی شد.", "success");
        } finally {
            state.loadingDashboard = false;
        }
    }

    async function loadOrders(silent) {
        if (!isOwner() || state.loadingOrders) {
            return;
        }
        state.loadingOrders = true;
        if (!silent) {
            ordersRoot.innerHTML = '<div class="owner-empty">در حال دریافت تراکنش‌ها...</div>';
        }

        try {
            var response = await request("ownerOrders", currentFilters(), "GET");
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success) {
                ordersRoot.innerHTML = '<div class="owner-empty">دریافت تراکنش‌ها انجام نشد.</div>';
                setFeedback(feedbackNode, (response && response.error) || "دریافت تراکنش‌ها انجام نشد.", "error");
                return;
            }
            state.orders = Array.isArray(response.orders) ? response.orders : [];
            renderOrdersSummary(response.filteredSummary || response.summary || null);
            renderOrders();
        } finally {
            state.loadingOrders = false;
        }
    }

    async function loadOrderDetail(orderId) {
        if (!isOwner() || !orderId) {
            return;
        }
        state.selectedOrderId = Number(orderId) || 0;
        renderOrders();
        orderDetailRoot.innerHTML = '<div class="owner-empty">در حال دریافت جزئیات تراکنش...</div>';

        var response = await request("ownerOrderDetails", { id: orderId }, "GET");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success || !response.order) {
            renderOrderDetail(null, null);
            setFeedback(feedbackNode, (response && response.error) || "دریافت جزئیات تراکنش انجام نشد.", "error");
            return;
        }
        renderOrderDetail(response.order, response.gatewaySnapshot || {});
    }

    async function saveItem(event) {
        event.preventDefault();
        if (!isOwner()) {
            return;
        }

        setFeedback(itemFormFeedback, "در حال ذخیره آیتم پرداخت...", "", true);
        var payload = {
            id: $("payments-item-id").value || "",
            title: $("payments-item-title").value.trim(),
            slug: $("payments-item-slug").value.trim(),
            price: normalizeDigits($("payments-item-price").value).replace(/\D+/g, ""),
            status: $("payments-item-status").value,
            shortDescription: $("payments-item-short-description").value.trim(),
            fullDescription: $("payments-item-full-description").value.trim(),
            heroImage: $("payments-item-hero-image").value.trim(),
            gallery: $("payments-item-gallery").value.trim() || "[]",
            specifications: $("payments-item-specifications").value.trim() || "[]",
            requiredFields: $("payments-item-required-fields").value.trim() || "[]",
            successMessage: $("payments-item-success-message").value.trim(),
            failureMessage: $("payments-item-failure-message").value.trim(),
            startsAt: fromDatetimeLocal($("payments-item-starts-at").value),
            expiresAt: fromDatetimeLocal($("payments-item-expires-at").value),
            capacity: normalizeDigits($("payments-item-capacity").value).replace(/\D+/g, "")
        };

        if (!payload.slug && payload.title) {
            payload.slug = slugifyLatin(payload.title);
        }

        if (!payload.id && payload.slug) {
            var matchedItem = state.items.find(function (entry) {
                return String(entry.slug || "").trim().toLowerCase() === String(payload.slug || "").trim().toLowerCase();
            });
            if (matchedItem && Number(matchedItem.id || 0) > 0) {
                payload.id = String(matchedItem.id || "");
            }
        }

        var response = await request("ownerSaveItem", payload, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success || !response.item) {
            setFeedback(itemFormFeedback, (response && response.error) || "ذخیره آیتم انجام نشد.", "error");
            return;
        }

        upsertItemInState(response.item);
        setFeedback(itemFormFeedback, response.message || "آیتم پرداخت ذخیره شد.", "success");
        fillItemForm(response.item.id);
        await loadDashboard(true);
        await loadOrders(true);
    }

    async function toggleItem(itemId, enabled) {
        if (!isOwner()) {
            return;
        }
        setFeedback(feedbackNode, enabled ? "در حال فعال‌سازی آیتم..." : "در حال غیرفعال‌سازی آیتم...", "", true);
        var response = await request("ownerToggleItem", { id: itemId, enabled: enabled ? "1" : "0" }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "تغییر وضعیت آیتم انجام نشد.", "error");
            return;
        }
        if (response.item) {
            upsertItemInState(response.item);
        }
        setFeedback(feedbackNode, response.message || "وضعیت آیتم به‌روزرسانی شد.", "success");
        await loadDashboard(true);
        await loadOrders(true);
    }

    async function markNotificationRead(id, markAll) {
        if (!isOwner()) {
            return;
        }
        var payload = markAll ? { all: "1" } : { id: id };
        var response = await request("ownerMarkNotificationRead", payload, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "به‌روزرسانی اعلان انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        setFeedback(feedbackNode, markAll ? "همه اعلان‌ها خوانده شدند." : "اعلان به‌عنوان خوانده‌شده ثبت شد.", "success");
    }

    function copyText(value, successText) {
        var raw = String(value || "").trim();
        if (!raw) {
            setFeedback(feedbackNode, "متنی برای کپی وجود ندارد.", "error");
            return;
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(raw).then(function () {
                setFeedback(feedbackNode, successText, "success");
            }).catch(function () {
                setFeedback(feedbackNode, "کپی خودکار انجام نشد.", "error");
            });
            return;
        }

        setFeedback(feedbackNode, "مرورگر کپی خودکار را پشتیبانی نمی‌کند.", "error");
    }

    function clearForSignedOut() {
        state.dashboardLoaded = false;
        state.currentUser = null;
        state.items = [];
        state.notifications = [];
        state.orders = [];
        state.selectedOrderId = 0;
        renderSummary(null);
        renderNotifications();
        renderItems();
        renderOrdersSummary(null);
        renderOrderDetail(null, null);
        if (ordersRoot) {
            ordersRoot.innerHTML = '<div class="owner-empty">برای استفاده از این بخش باید با حساب مالک وارد شوی.</div>';
        }
        if (rowMeta) {
            rowMeta.textContent = "ساخت لینک پرداخت، گزارش تراکنش‌ها و اعلان‌های مالی";
        }
    }

    async function ensureLoaded() {
        if (!isOwner()) {
            return;
        }
        await loadDashboard(!isPaymentsSurfaceOpen());
        await loadOrders(!isPaymentsSurfaceOpen());
    }

    function handleAuthState(detail) {
        if (!detail || !detail.loggedIn || !detail.user || !detail.user.isOwner) {
            clearForSignedOut();
            return;
        }
        state.currentUser = detail.user;
        ensureLoaded();
    }

    if (itemForm) {
        itemForm.addEventListener("submit", saveItem);
    }

    if ($("payments-item-title") && $("payments-item-slug")) {
        $("payments-item-title").addEventListener("blur", function () {
            var slugInput = $("payments-item-slug");
            if (!slugInput || String(slugInput.value || "").trim() !== "") {
                return;
            }
            var candidate = slugifyLatin($("payments-item-title").value);
            if (candidate) {
                slugInput.value = candidate;
            }
        });
    }

    if (filterForm) {
        filterForm.addEventListener("submit", function (event) {
            event.preventDefault();
            loadOrders(false);
        });
    }

    if ($("payments-item-reset")) {
        $("payments-item-reset").addEventListener("click", resetItemForm);
    }

    if ($("payments-new-item")) {
        $("payments-new-item").addEventListener("click", function () {
            resetItemForm();
            setFeedback(itemFormFeedback, "فرم برای ساخت آیتم جدید آماده شد.", "success");
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    if ($("payments-reload-dashboard")) {
        $("payments-reload-dashboard").addEventListener("click", function () {
            ensureLoaded();
        });
    }

    if ($("payments-mark-notifications-read")) {
        $("payments-mark-notifications-read").addEventListener("click", function () {
            markNotificationRead(0, true);
        });
    }

    if ($("payments-filter-reset")) {
        $("payments-filter-reset").addEventListener("click", function () {
            if (filterForm) {
                filterForm.reset();
            }
            loadOrders(false);
        });
    }

    if (itemsRoot) {
        itemsRoot.addEventListener("click", function (event) {
            var editButton = event.target.closest("[data-payment-edit-item]");
            if (editButton) {
                fillItemForm(editButton.getAttribute("data-payment-edit-item"));
                return;
            }

            var copyButton = event.target.closest("[data-payment-copy-link]");
            if (copyButton) {
                copyText(copyButton.getAttribute("data-payment-copy-link"), "لینک عمومی آیتم کپی شد.");
                return;
            }

            var toggleButton = event.target.closest("[data-payment-toggle-item]");
            if (toggleButton) {
                toggleItem(toggleButton.getAttribute("data-payment-toggle-item"), String(toggleButton.getAttribute("data-payment-enabled")) === "1");
            }
        });
    }

    if (notificationsRoot) {
        notificationsRoot.addEventListener("click", function (event) {
            var detailButton = event.target.closest("[data-payment-order-detail]");
            if (detailButton) {
                loadOrderDetail(detailButton.getAttribute("data-payment-order-detail"));
                return;
            }

            var readButton = event.target.closest("[data-payment-read-note]");
            if (readButton) {
                markNotificationRead(readButton.getAttribute("data-payment-read-note"), false);
            }
        });
    }

    if (ordersRoot) {
        ordersRoot.addEventListener("click", function (event) {
            var detailButton = event.target.closest("[data-payment-order-detail]");
            if (!detailButton) {
                return;
            }
            loadOrderDetail(detailButton.getAttribute("data-payment-order-detail"));
        });
    }

    if (panel && window.MutationObserver) {
        var observer = new MutationObserver(function () {
            if (isPaymentsSurfaceOpen() && isOwner()) {
                ensureLoaded();
            }
        });
        observer.observe(panel, { attributes: true, attributeFilter: ["data-surface"] });
    }

    resetItemForm();
    clearForSignedOut();
    window.Dent1402Auth.onChange(handleAuthState);
})();
