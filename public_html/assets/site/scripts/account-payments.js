(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var panel = $("account-panel");
    var ownerConsole = $("buy-owner-console");
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
    var previewImage = $("payments-preview-image");
    var previewCategory = $("payments-preview-category");
    var previewStatus = $("payments-preview-status");
    var previewTitle = $("payments-preview-title");
    var previewShort = $("payments-preview-short");
    var previewPrice = $("payments-preview-price");
    var previewMeta = $("payments-preview-meta");
    var previewSpecs = $("payments-preview-specs");
    var fillShieldSampleButton = $("payments-fill-shield-sample");
    var heroImageInput = $("payments-item-hero-image");
    var heroFileInput = $("payments-item-hero-file");
    var heroPreviewImage = $("payments-item-hero-preview");
    var heroStatus = $("payments-item-hero-status");
    var heroClearButton = $("payments-item-hero-clear");
    var galleryInput = $("payments-item-gallery");
    var galleryFileInput = $("payments-item-gallery-files");
    var galleryList = $("payments-gallery-list");
    var gatewaysRoot = $("payments-gateways-list");
    var gatewayForm = $("payments-gateway-form");
    var gatewayIdInput = $("payments-gateway-id");
    var gatewayProviderInput = $("payments-gateway-provider");
    var gatewayKeyInput = $("payments-gateway-key");
    var gatewayLabelInput = $("payments-gateway-label");
    var gatewayProviderLabelInput = $("payments-gateway-provider-label");
    var gatewayMerchantInput = $("payments-gateway-merchant");
    var gatewayRequestUrlInput = $("payments-gateway-request-url");
    var gatewayVerifyUrlInput = $("payments-gateway-verify-url");
    var gatewayStartUrlInput = $("payments-gateway-start-url");
    var gatewayEnabledInput = $("payments-gateway-enabled");
    var gatewayDefaultInput = $("payments-gateway-default");
    var tabButtons = Array.prototype.slice.call(document.querySelectorAll("[data-payment-tab]"));
    var tabPanels = Array.prototype.slice.call(document.querySelectorAll("[data-payment-tab-panel]"));
    var stepButtons = Array.prototype.slice.call(document.querySelectorAll("[data-payment-step]"));
    var stepPanels = Array.prototype.slice.call(document.querySelectorAll("[data-payment-step-panel]"));
    var stepPrevButton = $("payments-step-prev");
    var stepNextButton = $("payments-step-next");
    var stepTitle = $("payments-step-title");
    var saveButton = $("payments-item-save");
    var heroPreviewObjectUrl = "";
    var heroPreviewObjectFile = null;
    var activeStep = 1;

    var state = {
        currentUser: null,
        dashboardLoaded: false,
        loadingDashboard: false,
        loadingOrders: false,
        selectedOrderId: 0,
        items: [],
        gateways: [],
        gatewaySettings: null,
        notifications: [],
        orders: []
    };

    var SHIELD_SAMPLE = {
        title: "شیلد ابری سامان زر دندان",
        category: "consumables",
        slug: "saman-foam-face-shield",
        price: "1200000",
        status: "active",
        shortDescription: "شیلد محافظ صورت دندانپزشکی با فوم فاصله‌دهنده، مناسب تمرین‌های عملی، لابراتوار و کارهای کلینیکی دانشجویان.",
        fullDescription: "این آیتم تستی بر اساس محصول شیلد ابری سامان زر دندان در دنتی‌پارس ساخته شده است. شیلد برای محافظت صورت هنگام کار دندانپزشکی و استفاده در لابراتوار مناسب است، فوم با ضخامت مناسب برای فاصله طلق از صورت دارد، قابل استفاده مجدد و قابل شستشو است و با کش پشت سر قرار می‌گیرد. قیمت مرجع سایت منبع ۱۲۰٬۰۰۰ تومان است و برای درگاه به‌صورت ۱٬۲۰۰٬۰۰۰ ریال ثبت می‌شود.",
        heroImage: "/assets/images/buy/saman-foam-face-shield.jpg",
        expiresAt: "2026-05-15T23:59",
        gallery: [
            "/assets/images/buy/saman-foam-face-shield.jpg"
        ],
        specifications: [
            { label: "کاربرد", value: "دندانپزشکی، لابراتوار و محیط آموزشی" },
            { label: "محتوای بسته", value: "۲ عددی" },
            { label: "ویژگی", value: "قابل استفاده مجدد، قابل شستشو، دارای کش" },
            { label: "منبع قیمت", value: "دنتی‌پارس، ۱۲۰٬۰۰۰ تومان" }
        ],
        requiredFields: [
            { name: "studentNumber", label: "شماره دانشجویی", type: "text", required: true, maxLength: 14 },
            { name: "group", label: "گروه/بخش تحویل", type: "text", required: false, maxLength: 80 }
        ],
        audienceNote: "دانشجویان دندانپزشکی ورودی ۱۴۰۲",
        deliveryNote: "تحویل حضوری در محدوده دانشکده دندانپزشکی دانشگاه علوم پزشکی تهران هماهنگ می‌شود.",
        supportNote: "برای پیگیری سفارش، کد رهگیری پرداخت را برای نماینده یا مالک سایت ارسال کنید.",
        allowCancellation: false,
        maxQuantityPerOrder: "2",
        capacity: "40",
        discountCodes: [
            { code: "SHIELD10", type: "percent", amount: 10, label: "تخفیف تستی دانشجویی", isEnabled: true }
        ],
        ratingAverage: "4.8",
        ratingCount: "12",
        reviews: [
            { name: "دانشجوی ترمیمی", rating: 5, body: "سبک است و فاصله طلق از صورت برای کار طولانی بهتر از مدل ساده است." },
            { name: "دانشجوی لابراتوار", rating: 4.5, body: "برای تمرین و کارگاه مناسب است؛ بهتر است قبل از تحویل سلامت طلق چک شود." }
        ],
        successMessage: "سفارش شیلد شما با موفقیت ثبت شد و با کد رهگیری قابل پیگیری است.",
        failureMessage: "پرداخت سفارش شیلد تایید نشد؛ در صورت کسر وجه با پشتیبانی تماس بگیرید."
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

    function requestFormData(action, formData) {
        var body = formData instanceof FormData ? formData : new FormData();
        body.set("action", action);
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            },
            body: body
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

    function readField(id) {
        var node = $(id);
        return node ? String(node.value || "").trim() : "";
    }

    function writeField(id, value) {
        var node = $(id);
        if (!node) {
            return;
        }
        node.value = value == null ? "" : String(value);
    }

    function selectedText(id, fallback) {
        var node = $(id);
        if (!node || !node.options || node.selectedIndex < 0) {
            return fallback || "";
        }
        return String(node.options[node.selectedIndex].textContent || fallback || "").trim();
    }

    function parseLooseJson(raw, fallback) {
        var text = String(raw || "").trim();
        if (!text) {
            return fallback;
        }
        try {
            return JSON.parse(text);
        } catch (_error) {
            return fallback;
        }
    }

    function safeImageUrl(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return "";
        }
        if (clean.indexOf("/api/payments_api.php?action=paymentImage&name=") === 0 ||
            clean.indexOf("/assets/images/buy/") === 0) {
            return clean;
        }
        return "";
    }

    function selectedFile(input) {
        return input && input.files && input.files.length ? input.files[0] : null;
    }

    function selectedFiles(input) {
        return input && input.files ? Array.prototype.slice.call(input.files) : [];
    }

    function releaseHeroPreviewObjectUrl() {
        if (heroPreviewObjectUrl) {
            URL.revokeObjectURL(heroPreviewObjectUrl);
        }
        heroPreviewObjectUrl = "";
        heroPreviewObjectFile = null;
    }

    function heroFilePreviewUrl(file) {
        if (!file) {
            releaseHeroPreviewObjectUrl();
            return "";
        }
        if (heroPreviewObjectUrl && heroPreviewObjectFile === file) {
            return heroPreviewObjectUrl;
        }
        releaseHeroPreviewObjectUrl();
        heroPreviewObjectFile = file;
        heroPreviewObjectUrl = URL.createObjectURL(file);
        return heroPreviewObjectUrl;
    }

    function galleryValues() {
        var parsed = parseLooseJson(readField("payments-item-gallery"), []);
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.map(function (entry) {
            return safeImageUrl(entry);
        }).filter(Boolean);
    }

    function writeGalleryValues(values) {
        writeField("payments-item-gallery", prettyJson(Array.from(new Set((values || []).map(function (entry) {
            return safeImageUrl(entry);
        }).filter(Boolean)))));
    }

    function renderHeroUploader() {
        var file = selectedFile(heroFileInput);
        var stored = safeImageUrl(heroImageInput ? heroImageInput.value : "");
        var src = "";
        var status = "تصویر اصلی هنوز انتخاب نشده است.";
        if (file) {
            src = heroFilePreviewUrl(file);
            status = "تصویر انتخاب شده و با ذخیره آیتم آپلود می‌شود.";
        } else if (stored) {
            releaseHeroPreviewObjectUrl();
            src = stored;
            status = "تصویر اصلی برای این آیتم ثبت شده است.";
        } else {
            releaseHeroPreviewObjectUrl();
        }

        if (heroPreviewImage) {
            if (src) {
                heroPreviewImage.hidden = false;
                heroPreviewImage.src = src;
            } else {
                heroPreviewImage.hidden = true;
                heroPreviewImage.removeAttribute("src");
            }
        }
        if (heroStatus) {
            heroStatus.textContent = status;
        }
    }

    function renderGalleryUploader() {
        if (!galleryList) {
            return;
        }
        var uploaded = galleryValues();
        var pending = selectedFiles(galleryFileInput);
        var rows = [];
        uploaded.forEach(function (url, index) {
            rows.push([
                '<article class="payments-gallery-thumb">',
                '  <img src="' + escapeHtml(url) + '" alt="تصویر گالری">',
                '  <button type="button" data-payment-remove-gallery="' + escapeHtml(index) + '">حذف</button>',
                "</article>"
            ].join(""));
        });
        pending.forEach(function (file) {
            rows.push([
                '<article class="payments-gallery-thumb payments-gallery-thumb--pending">',
                '  <span>در انتظار آپلود</span>',
                '  <small>' + escapeHtml(file.name || "تصویر انتخاب‌شده") + "</small>",
                "</article>"
            ].join(""));
        });
        galleryList.innerHTML = rows.length ? rows.join("") : '<div class="payments-gallery-empty">تصویری برای گالری انتخاب نشده است.</div>';
    }

    function renderImageUploaders() {
        renderHeroUploader();
        renderGalleryUploader();
    }

    function firstImageFromForm() {
        var file = selectedFile(heroFileInput);
        if (file) {
            return heroFilePreviewUrl(file);
        }
        var hero = safeImageUrl(readField("payments-item-hero-image"));
        if (hero) {
            return hero;
        }
        var gallery = galleryValues();
        return Array.isArray(gallery) && gallery.length ? String(gallery[0] || "") : "";
    }

    function formDeadlineLabel() {
        var expires = readField("payments-item-expires-at");
        return expires ? formatDateTime(fromDatetimeLocal(expires), "بدون مهلت") : "بدون مهلت";
    }

    function updateItemPreview() {
        if (!previewTitle) {
            return;
        }

        var title = readField("payments-item-title") || "عنوان آیتم خرید";
        var shortDescription = readField("payments-item-short-description") || "پیش‌نمایش زنده از چیزی که دانشجو در صفحه خرید می‌بیند.";
        var price = normalizeDigits(readField("payments-item-price")).replace(/\D+/g, "");
        var image = firstImageFromForm();
        var capacity = normalizeDigits(readField("payments-item-capacity")).replace(/\D+/g, "");
        var maxQuantity = normalizeDigits(readField("payments-item-max-quantity")).replace(/\D+/g, "") || "1";
        var audience = readField("payments-item-audience-note") || "دانشجویان دندانپزشکی ورودی ۱۴۰۲";
        var specs = parseLooseJson(readField("payments-item-specifications"), []);

        if (previewImage) {
            previewImage.innerHTML = image
                ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(title) + '">'
                : "<span>بدون تصویر</span>";
        }
        if (previewCategory) {
            previewCategory.textContent = selectedText("payments-item-category", "سفارش گروهی");
        }
        if (previewStatus) {
            previewStatus.textContent = selectedText("payments-item-status", "غیرفعال");
        }
        previewTitle.textContent = title;
        if (previewShort) {
            previewShort.textContent = shortDescription;
        }
        if (previewPrice) {
            previewPrice.textContent = money(price || 0);
        }
        if (previewMeta) {
            previewMeta.innerHTML = [
                "<span><b>ظرفیت</b>" + escapeHtml(capacity ? Number(capacity).toLocaleString("fa-IR") : "نامحدود") + "</span>",
                "<span><b>حداکثر سفارش</b>" + escapeHtml(Number(maxQuantity).toLocaleString("fa-IR")) + "</span>",
                "<span><b>مهلت</b>" + escapeHtml(formDeadlineLabel()) + "</span>",
                "<span><b>مخاطب</b>" + escapeHtml(audience) + "</span>"
            ].join("");
        }
        if (previewSpecs) {
            var visibleSpecs = Array.isArray(specs) ? specs.slice(0, 4) : [];
            previewSpecs.innerHTML = visibleSpecs.map(function (entry) {
                return "<span><b>" + escapeHtml(entry && entry.label || "مشخصه") + "</b>" + escapeHtml(entry && entry.value || "—") + "</span>";
            }).join("");
        }
        renderImageUploaders();
    }

    function fillShieldSample() {
        writeField("payments-item-id", "");
        writeField("payments-item-title", SHIELD_SAMPLE.title);
        writeField("payments-item-category", SHIELD_SAMPLE.category);
        writeField("payments-item-slug", SHIELD_SAMPLE.slug);
        writeField("payments-item-price", SHIELD_SAMPLE.price);
        writeField("payments-item-status", SHIELD_SAMPLE.status);
        writeField("payments-item-short-description", SHIELD_SAMPLE.shortDescription);
        writeField("payments-item-full-description", SHIELD_SAMPLE.fullDescription);
        writeField("payments-item-hero-image", SHIELD_SAMPLE.heroImage);
        writeField("payments-item-expires-at", SHIELD_SAMPLE.expiresAt);
        writeField("payments-item-gallery", prettyJson(SHIELD_SAMPLE.gallery));
        writeField("payments-item-specifications", prettyJson(SHIELD_SAMPLE.specifications));
        writeField("payments-item-required-fields", prettyJson(SHIELD_SAMPLE.requiredFields));
        writeField("payments-item-audience-note", SHIELD_SAMPLE.audienceNote);
        writeField("payments-item-delivery-note", SHIELD_SAMPLE.deliveryNote);
        writeField("payments-item-support-note", SHIELD_SAMPLE.supportNote);
        writeField("payments-item-max-quantity", SHIELD_SAMPLE.maxQuantityPerOrder);
        writeField("payments-item-capacity", SHIELD_SAMPLE.capacity);
        writeField("payments-item-discount-codes", prettyJson(SHIELD_SAMPLE.discountCodes));
        writeField("payments-item-rating-average", SHIELD_SAMPLE.ratingAverage);
        writeField("payments-item-rating-count", SHIELD_SAMPLE.ratingCount);
        writeField("payments-item-reviews", prettyJson(SHIELD_SAMPLE.reviews));
        writeField("payments-item-success-message", SHIELD_SAMPLE.successMessage);
        writeField("payments-item-failure-message", SHIELD_SAMPLE.failureMessage);
        if (heroFileInput) {
            heroFileInput.value = "";
        }
        if (galleryFileInput) {
            galleryFileInput.value = "";
        }
        if ($("payments-item-allow-cancellation")) {
            $("payments-item-allow-cancellation").checked = !!SHIELD_SAMPLE.allowCancellation;
        }
        updateItemPreview();
        setFeedback(itemFormFeedback, "نمونه شیلد از منبع دنتی‌پارس داخل فرم آماده شد؛ بعد از ذخیره در کاتالوگ عمومی نمایش داده می‌شود.", "success");
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

    function itemCategoryLabel(item) {
        return String(item && (item.categoryLabel || item.category) || "سفارش گروهی");
    }

    function itemStateTone(item) {
        var key = String(item && item.state ? item.state.key || "" : "");
        if (key === "active") return "ok";
        if (key === "upcoming" || key === "full") return "warn";
        return "danger";
    }

    function gatewayProviderLabel(provider) {
        switch (String(provider || "").trim().toLowerCase()) {
            case "zibal":
                return "درگاه زیبال";
            case "zarinpal":
                return "درگاه زرین‌پال";
            case "mock":
                return "درگاه آزمایشی";
            default:
                return "درگاه آنلاین";
        }
    }

    function gatewayPublicLabel(provider) {
        switch (String(provider || "").trim().toLowerCase()) {
            case "mock":
                return "پرداخت آزمایشی";
            case "zibal":
            case "zarinpal":
            default:
                return "پرداخت آنلاین";
        }
    }

    function gatewayById(id) {
        var gatewayId = Number(id || 0);
        if (!gatewayId) {
            return null;
        }
        return state.gateways.find(function (gateway) {
            return Number(gateway && gateway.id || 0) === gatewayId;
        }) || null;
    }

    function syncGatewayPayload(payload) {
        var bundle = null;
        if (payload && payload.gateways && !Array.isArray(payload.gateways) && typeof payload.gateways === "object" && Array.isArray(payload.gateways.gateways)) {
            bundle = payload.gateways;
        } else if (payload && Array.isArray(payload.gateways)) {
            bundle = payload;
        }
        if (!bundle) {
            return;
        }
        state.gatewaySettings = bundle;
        state.gateways = Array.isArray(bundle.gateways) ? bundle.gateways : [];
        renderGateways();
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
        if (ownerConsole) {
            return !ownerConsole.hidden;
        }
        return !!(panel && panel.dataset && panel.dataset.surface === "payments");
    }

    function setOwnerConsoleVisible(visible) {
        if (ownerConsole) {
            ownerConsole.hidden = !visible;
        }
    }

    function openPaymentTab(name) {
        var target = String(name || "overview").trim() || "overview";
        var matched = false;
        tabPanels.forEach(function (node) {
            var active = String(node.dataset.paymentTabPanel || "") === target;
            node.hidden = !active;
            matched = matched || active;
        });
        if (!matched) {
            target = "overview";
            tabPanels.forEach(function (node) {
                node.hidden = String(node.dataset.paymentTabPanel || "") !== target;
            });
        }
        tabButtons.forEach(function (button) {
            var active = String(button.dataset.paymentTab || "") === target;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });
        if (target === "orders" && isOwner()) {
            loadOrders(true);
        }
    }

    function setPaymentStep(step) {
        var next = Math.max(1, Math.min(3, Number(step) || 1));
        activeStep = next;
        stepPanels.forEach(function (node) {
            node.hidden = Number(node.dataset.paymentStepPanel || 0) !== activeStep;
        });
        stepButtons.forEach(function (button) {
            var active = Number(button.dataset.paymentStep || 0) === activeStep;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });
        if (stepPrevButton) {
            stepPrevButton.hidden = activeStep <= 1;
        }
        if (stepNextButton) {
            stepNextButton.hidden = activeStep >= 3;
        }
        if (saveButton) {
            saveButton.hidden = activeStep < 3;
        }
        if (stepTitle) {
            stepTitle.textContent = activeStep === 1
                ? "مرحله ۱: عنوان، توضیح و تصویر"
                : (activeStep === 2 ? "مرحله ۲: قیمت، دسته، ظرفیت و زمان" : "مرحله ۳: تحویل، لینک و گزینه‌های تکمیلی");
        }
    }

    function validateCurrentStep() {
        if (activeStep === 1 && !$("payments-item-title").value.trim()) {
            setFeedback(itemFormFeedback, "برای رفتن به مرحله بعد، عنوان آیتم را وارد کن.", "error");
            $("payments-item-title").focus();
            return false;
        }
        if (activeStep === 2) {
            var priceValue = normalizeDigits($("payments-item-price").value).replace(/\D+/g, "");
            if (!priceValue || Number(priceValue) <= 0) {
                setFeedback(itemFormFeedback, "برای رفتن به مرحله بعد، قیمت معتبر وارد کن.", "error");
                $("payments-item-price").focus();
                return false;
            }
        }
        setFeedback(itemFormFeedback, "", "");
        return true;
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
            itemsRoot.innerHTML = '<div class="owner-empty">هنوز آیتمی برای کاتالوگ خرید یا ثبت‌نام ساخته نشده است.</div>';
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
                '      <p>' + escapeHtml(itemCategoryLabel(item)) + ' • <span dir="ltr">' + escapeHtml(item.slug || "") + "</span></p>",
                "    </div>",
                '    <strong>' + escapeHtml(money(item.price || 0)) + "</strong>",
                "  </div>",
                '  <div class="payments-item-card__meta">',
                '    <span>لینک عمومی: <a href="' + escapeHtml(publicUrl || "#") + '" target="_blank" rel="noopener">' + escapeHtml(publicUrl || "—") + "</a></span>",
                '    <span>فروخته‌شده: ' + escapeHtml(String(Number(item.soldCount || 0).toLocaleString("fa-IR"))) + "</span>",
                '    <span>مهلت: ' + escapeHtml(formatDateTime(item.expiresAt, "بدون مهلت")) + "</span>",
                '    <span>حداکثر تعداد هر سفارش: ' + escapeHtml(String(Number(item.maxQuantityPerOrder || 1).toLocaleString("fa-IR"))) + "</span>",
                '    <span>لغو توسط خریدار: ' + (item.allowCancellation ? "فعال" : "غیرفعال") + "</span>",
                "  </div>",
                '  <div class="payments-item-card__actions">',
                '    <button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-edit-item="' + escapeHtml(item.id) + '">ویرایش</button>',
                '    <button class="shell-action-btn" type="button" data-payment-copy-link="' + escapeHtml(publicUrl || "") + '">کپی لینک</button>',
                '    <button class="shell-action-btn" type="button" data-payment-toggle-item="' + escapeHtml(item.id) + '" data-payment-enabled="' + (item.status === "active" ? "0" : "1") + '">' + (item.status === "active" ? "غیرفعال‌کردن" : "فعال‌کردن") + "</button>",
                '    <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-item="' + escapeHtml(item.id) + '">حذف</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderGateways() {
        if (!gatewaysRoot) {
            return;
        }
        if (!state.gateways.length) {
            gatewaysRoot.innerHTML = '<div class="owner-empty">هنوز درگاه پرداختی اضافه نشده است. اولین درگاه را از فرم بالا ثبت کن.</div>';
            return;
        }
        gatewaysRoot.innerHTML = state.gateways.map(function (gateway) {
            var configured = !!gateway.isConfigured;
            var enabled = !!gateway.isEnabled;
            var provider = String(gateway.provider || "");
            var statusText = !enabled ? "غیرفعال" : (configured ? "فعال" : "نیازمند کلید");
            var statusTone = !enabled ? "warn" : (configured ? "ok" : "danger");
            return [
                '<article class="payments-item-card payments-gateway-card">',
                '  <div class="payments-item-card__head">',
                '    <div>',
                '      <span class="payments-pill payments-pill--' + escapeHtml(statusTone) + '">' + escapeHtml(statusText) + "</span>",
                '      <h4>' + escapeHtml(gateway.label || "پرداخت آنلاین") + "</h4>",
                '      <p>' + escapeHtml(gateway.providerLabel || gatewayProviderLabel(provider)) + ' • <span dir="ltr">' + escapeHtml(gateway.key || "") + "</span></p>",
                "    </div>",
                gateway.isDefault ? '<strong>پیش‌فرض</strong>' : '<strong>درگاه</strong>',
                "  </div>",
                '  <div class="payments-item-card__meta">',
                '    <span>نوع: ' + escapeHtml(gatewayProviderLabel(provider)) + "</span>",
                '    <span>وضعیت کلید: ' + (configured ? "ثبت شده" : "ثبت نشده") + "</span>",
                '    <span>ایجاد: ' + escapeHtml(formatDateTime(gateway.createdAt, "—")) + "</span>",
                '    <span>آخرین ویرایش: ' + escapeHtml(formatDateTime(gateway.updatedAt, "—")) + "</span>",
                "  </div>",
                '  <div class="payments-item-card__actions">',
                '    <button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-edit-gateway="' + escapeHtml(gateway.id) + '">ویرایش</button>',
                '    <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-gateway="' + escapeHtml(gateway.id) + '">حذف درگاه</button>',
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
            '  <div class="payments-detail-row"><span>تعداد</span><strong>' + escapeHtml(Number(order.quantity || 1).toLocaleString("fa-IR")) + "</strong></div>",
            '  <div class="payments-detail-row"><span>جمع قبل از تخفیف</span><strong>' + escapeHtml(money(order.subtotal || order.amount || 0)) + "</strong></div>",
            '  <div class="payments-detail-row"><span>کد/مبلغ تخفیف</span><strong>' + escapeHtml((order.discountCode || "—") + " / " + money(order.discountAmount || 0)) + "</strong></div>",
            '  <div class="payments-detail-row"><span>مبلغ نهایی</span><strong>' + escapeHtml(money(order.amount || 0)) + "</strong></div>",
            '  <div class="payments-detail-row"><span>درگاه</span><strong>' + escapeHtml(order.gateway || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>authority</span><strong>' + escapeHtml(order.authority || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>ref id</span><strong>' + escapeHtml(order.refId || "—") + "</strong></div>",
            '  <div class="payments-detail-row"><span>ثبت سفارش</span><strong>' + escapeHtml(formatDateTime(order.createdAt, "—")) + "</strong></div>",
            '  <div class="payments-detail-row"><span>تایید نهایی</span><strong>' + escapeHtml(formatDateTime(order.verifiedAt, "—")) + "</strong></div>",
            extras,
            "</div>",
            '<div class="payments-item-card__actions payments-order-admin-actions">',
            order.status === "pending" ? '<button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-status-update="' + escapeHtml(order.id) + '" data-payment-next-status="canceled">لغو سفارش</button>' : "",
            order.status !== "pending" && order.status !== "success" ? '<button class="shell-action-btn" type="button" data-payment-status-update="' + escapeHtml(order.id) + '" data-payment-next-status="pending">بازگردانی به در انتظار</button>' : "",
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
        $("payments-item-hero-image").value = "";
        $("payments-item-category").value = "group_order";
        $("payments-item-max-quantity").value = "1";
        $("payments-item-audience-note").value = "دانشجویان دندانپزشکی ورودی ۱۴۰۲";
        $("payments-item-delivery-note").value = "تحویل یا استفاده در محدوده دانشگاه علوم پزشکی تهران هماهنگ می‌شود.";
        $("payments-item-support-note").value = "برای پیگیری سفارش با نماینده یا مالک سایت تماس بگیرید.";
        $("payments-item-allow-cancellation").checked = false;
        $("payments-item-gallery").value = "[]";
        $("payments-item-specifications").value = "[]";
        $("payments-item-required-fields").value = "[]";
        $("payments-item-discount-codes").value = "[]";
        $("payments-item-rating-average").value = "";
        $("payments-item-rating-count").value = "";
        $("payments-item-reviews").value = "[]";
        $("payments-item-success-message").value = "پرداخت شما با موفقیت ثبت شد.";
        $("payments-item-failure-message").value = "پرداخت شما ناموفق بود.";
        if (heroFileInput) {
            heroFileInput.value = "";
        }
        if (galleryFileInput) {
            galleryFileInput.value = "";
        }
        setFeedback(itemFormFeedback, "", "");
        setPaymentStep(1);
        updateItemPreview();
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
        $("payments-item-category").value = item.category || "group_order";
        $("payments-item-slug").value = item.slug || "";
        $("payments-item-price").value = String(item.price || "");
        $("payments-item-status").value = item.status || "inactive";
        $("payments-item-short-description").value = item.shortDescription || "";
        $("payments-item-full-description").value = item.fullDescription || "";
        $("payments-item-hero-image").value = item.heroImage || "";
        $("payments-item-gallery").value = prettyJson(item.gallery || []);
        $("payments-item-specifications").value = prettyJson(item.specifications || []);
        $("payments-item-required-fields").value = prettyJson(item.requiredFields || []);
        $("payments-item-audience-note").value = item.audienceNote || "دانشجویان دندانپزشکی ورودی ۱۴۰۲";
        $("payments-item-delivery-note").value = item.deliveryNote || "تحویل یا استفاده در محدوده دانشگاه علوم پزشکی تهران هماهنگ می‌شود.";
        $("payments-item-support-note").value = item.supportNote || "برای پیگیری سفارش با نماینده یا مالک سایت تماس بگیرید.";
        $("payments-item-allow-cancellation").checked = !!item.allowCancellation;
        $("payments-item-max-quantity").value = String(item.maxQuantityPerOrder || 1);
        $("payments-item-discount-codes").value = prettyJson(item.discountCodes || []);
        $("payments-item-rating-average").value = item.ratingAverage ? String(item.ratingAverage) : "";
        $("payments-item-rating-count").value = item.ratingCount ? String(item.ratingCount) : "";
        $("payments-item-reviews").value = prettyJson(item.reviews || []);
        $("payments-item-success-message").value = item.successMessage || "پرداخت شما با موفقیت ثبت شد.";
        $("payments-item-failure-message").value = item.failureMessage || "پرداخت شما ناموفق بود.";
        $("payments-item-starts-at").value = toDatetimeLocal(item.startsAt);
        $("payments-item-expires-at").value = toDatetimeLocal(item.expiresAt);
        $("payments-item-capacity").value = item.capacity == null ? "" : String(item.capacity);
        if (heroFileInput) {
            heroFileInput.value = "";
        }
        if (galleryFileInput) {
            galleryFileInput.value = "";
        }
        setFeedback(itemFormFeedback, "حالت ویرایش برای «" + (item.title || "آیتم") + "» فعال شد.", "success");
        openPaymentTab("item");
        setPaymentStep(1);
        updateItemPreview();
        if (ownerConsole) {
            ownerConsole.scrollIntoView({ behavior: "smooth", block: "start" });
        } else {
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    }

    function renderDashboard() {
        renderItems();
        renderGateways();
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

    async function uploadPaymentImage(file) {
        if (!file) {
            return null;
        }
        if (!/^image\/(jpeg|png|webp)$/i.test(String(file.type || ""))) {
            throw new Error("فرمت تصویر باید jpg، png یا webp باشد.");
        }
        if (Number(file.size || 0) > 5 * 1024 * 1024) {
            throw new Error("حجم تصویر باید کمتر از ۵ مگابایت باشد.");
        }

        var body = new FormData();
        body.append("image", file);
        var response = await requestFormData("ownerUploadImage", body);
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            throw new Error("نشست شما منقضی شده است.");
        }
        if (!response || !response.success || !response.image || !response.image.url) {
            throw new Error((response && response.error) || "آپلود تصویر انجام نشد.");
        }
        return response.image;
    }

    async function uploadSelectedImagesBeforeSave() {
        var heroImage = safeImageUrl(readField("payments-item-hero-image"));
        var gallery = galleryValues();
        var heroFile = selectedFile(heroFileInput);
        var galleryFiles = selectedFiles(galleryFileInput);

        if (heroFile) {
            setFeedback(itemFormFeedback, "در حال آپلود تصویر اصلی کالا...", "", true);
            var storedHero = await uploadPaymentImage(heroFile);
            heroImage = storedHero.url;
            writeField("payments-item-hero-image", heroImage);
            if (heroFileInput) {
                heroFileInput.value = "";
            }
        }

        if (galleryFiles.length) {
            setFeedback(itemFormFeedback, "در حال آپلود تصاویر گالری...", "", true);
            for (var index = 0; index < galleryFiles.length; index += 1) {
                var storedGalleryImage = await uploadPaymentImage(galleryFiles[index]);
                gallery.push(storedGalleryImage.url);
            }
            if (galleryFileInput) {
                galleryFileInput.value = "";
            }
        }

        gallery = Array.from(new Set(gallery.filter(Boolean)));
        writeGalleryValues(gallery);
        if (!heroImage && gallery.length) {
            heroImage = gallery[0];
            writeField("payments-item-hero-image", heroImage);
        }
        renderImageUploaders();
        updateItemPreview();

        return {
            heroImage: heroImage,
            gallery: gallery
        };
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
            syncGatewayPayload(response);
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

        var titleValue = $("payments-item-title").value.trim();
        var priceValue = normalizeDigits($("payments-item-price").value).replace(/\D+/g, "");
        if (!titleValue) {
            setFeedback(itemFormFeedback, "عنوان آیتم الزامی است.", "error");
            $("payments-item-title").focus();
            return;
        }
        if (!priceValue || Number(priceValue) <= 0) {
            setFeedback(itemFormFeedback, "قیمت برای درگاه باید بیشتر از صفر باشد.", "error");
            $("payments-item-price").focus();
            return;
        }

        var uploadedImages;
        try {
            uploadedImages = await uploadSelectedImagesBeforeSave();
        } catch (error) {
            setFeedback(itemFormFeedback, (error && error.message) || "آپلود تصویر انجام نشد.", "error");
            return;
        }
        if (!uploadedImages || !uploadedImages.heroImage) {
            setFeedback(itemFormFeedback, "تصویر اصلی کالا الزامی است. تصویر را از دستگاه آپلود کن.", "error");
            if (heroFileInput) {
                heroFileInput.focus();
            }
            return;
        }

        setFeedback(itemFormFeedback, "در حال ذخیره آیتم کاتالوگ...", "", true);
        var payload = {
            id: $("payments-item-id").value || "",
            title: titleValue,
            category: $("payments-item-category").value,
            slug: $("payments-item-slug").value.trim(),
            price: priceValue,
            status: $("payments-item-status").value,
            shortDescription: $("payments-item-short-description").value.trim(),
            fullDescription: $("payments-item-full-description").value.trim(),
            heroImage: uploadedImages.heroImage,
            gallery: prettyJson(uploadedImages.gallery || []),
            specifications: $("payments-item-specifications").value.trim() || "[]",
            requiredFields: $("payments-item-required-fields").value.trim() || "[]",
            audienceNote: $("payments-item-audience-note").value.trim(),
            deliveryNote: $("payments-item-delivery-note").value.trim(),
            supportNote: $("payments-item-support-note").value.trim(),
            allowCancellation: $("payments-item-allow-cancellation").checked ? "1" : "0",
            maxQuantityPerOrder: normalizeDigits($("payments-item-max-quantity").value).replace(/\D+/g, "") || "1",
            discountCodes: $("payments-item-discount-codes").value.trim() || "[]",
            ratingAverage: normalizeDigits($("payments-item-rating-average").value).replace(/[^0-9.]+/g, ""),
            ratingCount: normalizeDigits($("payments-item-rating-count").value).replace(/\D+/g, ""),
            reviews: $("payments-item-reviews").value.trim() || "[]",
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
        setFeedback(itemFormFeedback, response.message || "آیتم کاتالوگ ذخیره شد.", "success");
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

    async function deleteItem(itemId) {
        if (!isOwner() || !itemId) {
            return;
        }
        var item = state.items.find(function (entry) {
            return Number(entry && entry.id || 0) === Number(itemId);
        });
        var title = item && item.title ? item.title : "این آیتم";
        if (!window.confirm("آیتم «" + title + "» از کاتالوگ خرید حذف شود؟ سوابق سفارش‌های قبلی حفظ می‌شود.")) {
            return;
        }
        setFeedback(feedbackNode, "در حال حذف آیتم از کاتالوگ...", "", true);
        var response = await request("ownerDeleteItem", { id: itemId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "حذف آیتم انجام نشد.", "error");
            return;
        }
        state.items = state.items.filter(function (entry) {
            return Number(entry && entry.id || 0) !== Number(itemId);
        });
        if ($("payments-item-id") && Number($("payments-item-id").value || 0) === Number(itemId)) {
            resetItemForm();
        }
        renderItems();
        populateFilterItems();
        setFeedback(feedbackNode, response.message || "آیتم حذف شد.", "success");
        await loadDashboard(true);
        await loadOrders(true);
    }

    function resetGatewayForm() {
        if (!gatewayForm) {
            return;
        }
        gatewayForm.reset();
        if (gatewayIdInput) gatewayIdInput.value = "";
        if (gatewayProviderInput) gatewayProviderInput.value = "zibal";
        if (gatewayKeyInput) gatewayKeyInput.value = "";
        if (gatewayLabelInput) gatewayLabelInput.value = gatewayPublicLabel("zibal");
        if (gatewayProviderLabelInput) gatewayProviderLabelInput.value = gatewayProviderLabel("zibal");
        if (gatewayMerchantInput) gatewayMerchantInput.value = "";
        if (gatewayRequestUrlInput) gatewayRequestUrlInput.value = "";
        if (gatewayVerifyUrlInput) gatewayVerifyUrlInput.value = "";
        if (gatewayStartUrlInput) gatewayStartUrlInput.value = "";
        if (gatewayEnabledInput) gatewayEnabledInput.checked = true;
        if (gatewayDefaultInput) gatewayDefaultInput.checked = state.gateways.filter(function (entry) { return !!(entry && entry.isEnabled); }).length === 0;
    }

    function fillGatewayForm(gatewayId) {
        var gateway = gatewayById(gatewayId);
        if (!gateway || !gatewayForm) {
            return;
        }
        if (gatewayIdInput) gatewayIdInput.value = String(gateway.id || "");
        if (gatewayProviderInput) gatewayProviderInput.value = String(gateway.provider || "zibal");
        if (gatewayKeyInput) gatewayKeyInput.value = String(gateway.key || "");
        if (gatewayLabelInput) gatewayLabelInput.value = String(gateway.label || gatewayPublicLabel(gateway.provider));
        if (gatewayProviderLabelInput) gatewayProviderLabelInput.value = String(gateway.providerLabel || gatewayProviderLabel(gateway.provider));
        if (gatewayMerchantInput) gatewayMerchantInput.value = String(gateway.merchantId || gateway.apiKey || "");
        if (gatewayRequestUrlInput) gatewayRequestUrlInput.value = String(gateway.requestUrl || "");
        if (gatewayVerifyUrlInput) gatewayVerifyUrlInput.value = String(gateway.verifyUrl || "");
        if (gatewayStartUrlInput) gatewayStartUrlInput.value = String(gateway.startUrl || "");
        if (gatewayEnabledInput) gatewayEnabledInput.checked = !!gateway.isEnabled;
        if (gatewayDefaultInput) gatewayDefaultInput.checked = !!gateway.isDefault;
        setFeedback(feedbackNode, "حالت ویرایش برای درگاه «" + (gateway.label || gateway.key || "پرداخت") + "» فعال شد.", "success");
        openPaymentTab("gateways");
        gatewayForm.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    async function saveGateway(event) {
        event.preventDefault();
        if (!isOwner() || !gatewayForm) {
            return;
        }
        var provider = String(gatewayProviderInput && gatewayProviderInput.value || "zibal").trim();
        var credential = String(gatewayMerchantInput && gatewayMerchantInput.value || "").trim();
        var enabled = gatewayEnabledInput ? gatewayEnabledInput.checked : true;
        if (enabled && provider !== "mock" && !credential) {
            setFeedback(feedbackNode, "برای فعال‌سازی این درگاه، Merchant یا API Key را وارد کن.", "error");
            if (gatewayMerchantInput) gatewayMerchantInput.focus();
            return;
        }

        setFeedback(feedbackNode, "در حال ذخیره درگاه پرداخت...", "", true);
        var response = await request("ownerSaveGateway", {
            id: gatewayIdInput ? gatewayIdInput.value : "",
            provider: provider,
            key: gatewayKeyInput ? gatewayKeyInput.value.trim() : "",
            label: gatewayLabelInput ? gatewayLabelInput.value.trim() : "",
            providerLabel: gatewayProviderLabelInput ? gatewayProviderLabelInput.value.trim() : "",
            merchantId: credential,
            apiKey: provider === "mock" ? "" : credential,
            requestUrl: gatewayRequestUrlInput ? gatewayRequestUrlInput.value.trim() : "",
            verifyUrl: gatewayVerifyUrlInput ? gatewayVerifyUrlInput.value.trim() : "",
            startUrl: gatewayStartUrlInput ? gatewayStartUrlInput.value.trim() : "",
            isEnabled: enabled ? "1" : "0",
            isDefault: gatewayDefaultInput && gatewayDefaultInput.checked ? "1" : "0"
        }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "ذخیره درگاه پرداخت انجام نشد.", "error");
            return;
        }
        syncGatewayPayload(response);
        if (response.gateway && gatewayIdInput) {
            gatewayIdInput.value = String(response.gateway.id || "");
        }
        setFeedback(feedbackNode, response.message || "درگاه پرداخت ذخیره شد.", "success");
    }

    async function deleteGateway(gatewayId) {
        if (!isOwner() || !gatewayId) {
            return;
        }
        var gateway = gatewayById(gatewayId);
        var title = gateway && (gateway.label || gateway.key) ? (gateway.label || gateway.key) : "این درگاه";
        if (!window.confirm("درگاه «" + title + "» حذف شود؟ اگر سفارش در انتظار با این درگاه وجود داشته باشد، حذف انجام نمی‌شود.")) {
            return;
        }
        setFeedback(feedbackNode, "در حال حذف درگاه پرداخت...", "", true);
        var response = await request("ownerDeleteGateway", { id: gatewayId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "حذف درگاه پرداخت انجام نشد.", "error");
            return;
        }
        syncGatewayPayload(response);
        if (gatewayIdInput && Number(gatewayIdInput.value || 0) === Number(gatewayId)) {
            resetGatewayForm();
        }
        setFeedback(feedbackNode, response.message || "درگاه پرداخت حذف شد.", "success");
    }

    async function updateOrderStatus(orderId, status) {
        if (!isOwner() || !orderId || !status) {
            return;
        }
        setFeedback(feedbackNode, "در حال به‌روزرسانی وضعیت سفارش...", "", true);
        var response = await request("ownerUpdateOrderStatus", { id: orderId, status: status }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "به‌روزرسانی وضعیت سفارش انجام نشد.", "error");
            return;
        }
        setFeedback(feedbackNode, response.message || "وضعیت سفارش به‌روزرسانی شد.", "success");
        await loadOrders(true);
        await loadOrderDetail(orderId);
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
        state.gateways = [];
        state.gatewaySettings = null;
        state.notifications = [];
        state.orders = [];
        state.selectedOrderId = 0;
        renderSummary(null);
        renderNotifications();
        renderItems();
        renderGateways();
        renderOrdersSummary(null);
        renderOrderDetail(null, null);
        if (ordersRoot) {
            ordersRoot.innerHTML = '<div class="owner-empty">برای استفاده از این بخش باید با حساب مالک وارد شوی.</div>';
        }
        setOwnerConsoleVisible(false);
        if (rowMeta) {
            rowMeta.textContent = "تعریف آیتم مشخص، کد تخفیف، سفارش‌ها و اعلان‌های مالی";
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
        setOwnerConsoleVisible(true);
        ensureLoaded();
    }

    tabButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            openPaymentTab(button.dataset.paymentTab || "overview");
        });
    });

    stepButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            var target = Number(button.dataset.paymentStep || 1);
            if (target > activeStep && !validateCurrentStep()) {
                return;
            }
            setPaymentStep(target);
        });
    });

    if (stepPrevButton) {
        stepPrevButton.addEventListener("click", function () {
            setPaymentStep(activeStep - 1);
        });
    }

    if (stepNextButton) {
        stepNextButton.addEventListener("click", function () {
            if (!validateCurrentStep()) {
                return;
            }
            setPaymentStep(activeStep + 1);
        });
    }

    if (itemForm) {
        itemForm.addEventListener("submit", saveItem);
        itemForm.addEventListener("input", updateItemPreview);
        itemForm.addEventListener("change", updateItemPreview);
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
                updateItemPreview();
            }
        });
    }

    if (fillShieldSampleButton) {
        fillShieldSampleButton.addEventListener("click", fillShieldSample);
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

    if ($("payments-item-reset-top")) {
        $("payments-item-reset-top").addEventListener("click", resetItemForm);
    }

    if (heroFileInput) {
        heroFileInput.addEventListener("change", function () {
            renderImageUploaders();
            updateItemPreview();
        });
    }

    if (heroClearButton) {
        heroClearButton.addEventListener("click", function () {
            if (heroFileInput) {
                heroFileInput.value = "";
            }
            writeField("payments-item-hero-image", "");
            renderImageUploaders();
            updateItemPreview();
        });
    }

    if (galleryFileInput) {
        galleryFileInput.addEventListener("change", function () {
            renderGalleryUploader();
        });
    }

    if (galleryList) {
        galleryList.addEventListener("click", function (event) {
            var removeButton = event.target.closest("[data-payment-remove-gallery]");
            if (!removeButton) {
                return;
            }
            var index = Number(removeButton.getAttribute("data-payment-remove-gallery"));
            var values = galleryValues();
            if (Number.isFinite(index) && index >= 0) {
                values.splice(index, 1);
                writeGalleryValues(values);
                renderGalleryUploader();
                updateItemPreview();
            }
        });
    }

    if ($("payments-new-item")) {
        $("payments-new-item").addEventListener("click", function () {
            resetItemForm();
            openPaymentTab("item");
            setPaymentStep(1);
            setFeedback(itemFormFeedback, "فرم برای افزودن آیتم جدید آماده شد.", "success");
            if (ownerConsole) {
                ownerConsole.scrollIntoView({ behavior: "smooth", block: "start" });
            } else {
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
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
                return;
            }

            var deleteButton = event.target.closest("[data-payment-delete-item]");
            if (deleteButton) {
                deleteItem(deleteButton.getAttribute("data-payment-delete-item"));
            }
        });
    }

    if (gatewayForm) {
        gatewayForm.addEventListener("submit", saveGateway);
    }

    if ($("payments-gateway-reset")) {
        $("payments-gateway-reset").addEventListener("click", function () {
            resetGatewayForm();
            setFeedback(feedbackNode, "فرم برای افزودن درگاه جدید آماده شد.", "success");
        });
    }

    if (gatewayProviderInput) {
        gatewayProviderInput.addEventListener("change", function () {
            var provider = String(gatewayProviderInput.value || "zibal");
            if (gatewayLabelInput && !String(gatewayLabelInput.value || "").trim()) {
                gatewayLabelInput.value = gatewayPublicLabel(provider);
            }
            if (gatewayProviderLabelInput && !String(gatewayProviderLabelInput.value || "").trim()) {
                gatewayProviderLabelInput.value = gatewayProviderLabel(provider);
            }
            if (gatewayMerchantInput) {
                gatewayMerchantInput.disabled = provider === "mock";
                gatewayMerchantInput.placeholder = provider === "mock" ? "برای درگاه آزمایشی لازم نیست" : "Merchant یا API Key";
            }
        });
    }

    if (gatewaysRoot) {
        gatewaysRoot.addEventListener("click", function (event) {
            var editGatewayButton = event.target.closest("[data-payment-edit-gateway]");
            if (editGatewayButton) {
                fillGatewayForm(editGatewayButton.getAttribute("data-payment-edit-gateway"));
                return;
            }

            var deleteGatewayButton = event.target.closest("[data-payment-delete-gateway]");
            if (deleteGatewayButton) {
                deleteGateway(deleteGatewayButton.getAttribute("data-payment-delete-gateway"));
            }
        });
    }

    if (notificationsRoot) {
        notificationsRoot.addEventListener("click", function (event) {
            var detailButton = event.target.closest("[data-payment-order-detail]");
            if (detailButton) {
                openPaymentTab("orders");
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
            openPaymentTab("orders");
            loadOrderDetail(detailButton.getAttribute("data-payment-order-detail"));
        });
    }

    if (orderDetailRoot) {
        orderDetailRoot.addEventListener("click", function (event) {
            var statusButton = event.target.closest("[data-payment-status-update]");
            if (!statusButton) {
                return;
            }
            updateOrderStatus(
                statusButton.getAttribute("data-payment-status-update"),
                statusButton.getAttribute("data-payment-next-status")
            );
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
    resetGatewayForm();
    openPaymentTab("overview");
    setPaymentStep(1);
    clearForSignedOut();
    window.Dent1402Auth.onChange(handleAuthState);
})();
