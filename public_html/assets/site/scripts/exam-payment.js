(function () {
    "use strict";

    var root = document.getElementById("exam-payment-root");
    if (!root) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var courseSlug = String(params.get("course") || "").trim();
    var paymentOrderToken = String(params.get("paymentOrderToken") || "").trim();
    var state = {
        loading: false,
        paying: false,
        course: null,
        result: null,
        viewer: null,
        auth: null,
        feedback: "",
        feedbackKind: ""
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

    function parseJson(response) {
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

    function withCohort(payload) {
        var next = Object.assign({}, payload || {});
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            next.cohort = cohort;
        }
        return next;
    }

    function examsGet(action, payload) {
        var query = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        query.set("_t", String(Date.now()));
        return fetch("/api/exams_api.php?" + query.toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson);
    }

    function paymentsGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson);
    }

    function paymentsPost(action, payload) {
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(parseJson);
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

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function returnPath() {
        var url = new URL(window.location.pathname, window.location.origin);
        url.searchParams.set("course", courseSlug);
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            url.searchParams.set("cohort", cohort);
        }
        return url.pathname + url.search;
    }

    function resultBoxHtml() {
        if (!state.result) {
            return "";
        }
        var success = String(state.result.status || "") === "success";
        return [
            '<section class="exam-payment-result ' + (success ? "is-success" : "is-error") + '">',
            '  <h3>' + escapeHtml(success ? "نتیجه پرداخت" : "وضعیت پرداخت") + "</h3>",
            '  <p>' + escapeHtml(state.result.message || "") + "</p>",
            '  <p>وضعیت: ' + escapeHtml(state.result.statusLabel || "—") + "</p>",
            '  <p>زمان ثبت: ' + escapeHtml(formatDateTime(state.result.createdAt, "—")) + "</p>",
            "  " + (state.result.refId ? '<p>کد مرجع: <span dir="ltr">' + escapeHtml(state.result.refId) + "</span></p>" : ""),
            "</section>"
        ].join("");
    }

    function loginGuardHtml() {
        if (!window.Dent1402Auth || typeof window.Dent1402Auth.renderLoginRequiredGuard !== "function") {
            return '<a class="exam-payment-link" href="' + escapeHtml(loginHref()) + '">ورود به حساب</a>';
        }

        return window.Dent1402Auth.renderLoginRequiredGuard({
            loginHref: loginHref(),
            fallbackHref: "/exams/",
            primaryClass: "exam-payment-btn",
            secondaryClass: "exam-payment-link"
        });
    }

    function renderPayment() {
        var course = state.course;
        if (!course) {
            root.innerHTML = '<div class="exam-payment-panel">اطلاعات این درس در دسترس نیست.</div>';
            return;
        }

        var access = course.access || {};
        var features = Array.isArray(course.paymentHighlights) ? course.paymentHighlights : [];
        var canPay = course.paymentMode === "paid" && !access.hasAccess && access.canPurchase && !access.requiresLogin;
        var headlineChip = course.paymentMode === "paid"
            ? '<span class="exam-payment-chip">دسترسی یک‌باره برای کل درس</span>'
            : '<span class="exam-payment-chip">این درس رایگان است</span>';
        var actionHtml = "";

        if (course.paymentMode !== "paid") {
            actionHtml = '<div class="exam-payment-action"><a class="exam-payment-btn" href="' + escapeHtml(course.path || "/exams/") + '">ورود به صفحه درس</a></div>';
        } else if (access.hasAccess) {
            actionHtml = '<div class="exam-payment-action"><a class="exam-payment-btn" href="' + escapeHtml(course.path || "/exams/") + '">ورود به آزمون‌های درس</a></div>';
        } else if (access.requiresLogin) {
            actionHtml = '<div class="exam-payment-login">' + loginGuardHtml() + "</div>";
        } else if (access.canPurchase) {
            actionHtml = '<div class="exam-payment-action"><button id="exam-payment-submit" class="exam-payment-btn" type="button"' + (state.paying ? " disabled" : "") + '>' + (state.paying ? "در حال انتقال به درگاه..." : "پرداخت و فعال‌سازی دسترسی") + "</button></div>";
        } else {
            actionHtml = '<div class="exam-payment-action"><a class="exam-payment-btn" href="' + escapeHtml(course.path || "/exams/") + '">بازگشت به درس</a></div>';
        }

        root.innerHTML = [
            '<section class="exam-payment-shell">',
            '  <article class="exam-payment-card">',
            '    <div class="exam-payment-card__inner">',
            '      <div class="exam-payment-head">',
            '        <span class="exam-payment-kicker">فعال‌سازی آزمون‌های درس</span>',
            '        <h2 class="exam-payment-title">' + escapeHtml(course.paymentTitle || course.title || "") + "</h2>",
            '        <p class="exam-payment-copy">' + escapeHtml(course.paymentDescription || "") + "</p>",
            '        <div class="exam-payment-price"><strong>' + escapeHtml(course.amountLabel || "۰ ریال") + '</strong><span>یک بار برای تمام آزمون‌های این درس</span></div>',
            '        <div class="exam-payment-headline">' + headlineChip + (state.viewer && state.viewer.name ? '<span class="exam-payment-user">' + escapeHtml(state.viewer.name) + "</span>" : "") + "</div>",
            "      </div>",
                     actionHtml,
            '      <div class="exam-payment-feedback' + (state.feedbackKind ? " is-" + escapeHtml(state.feedbackKind) : "") + '">' + escapeHtml(state.feedback || "") + "</div>",
                     resultBoxHtml(),
            '      <div class="exam-payment-subactions"><a class="exam-payment-link" href="' + escapeHtml(course.path || "/exams/") + '">بازگشت به صفحه درس</a></div>',
            "    </div>",
            "  </article>",
            '  <aside class="exam-payment-side">',
            '    <section class="exam-payment-panel">',
            '      <h3 class="exam-payment-panel__title">چه چیزی فعال می‌شود؟</h3>',
            '      <p class="exam-payment-panel__copy">بعد از تایید پرداخت، دسترسی همین حساب به همه آزمون‌های این درس باز می‌شود و لازم نیست برای هر جلسه جداگانه پرداخت کنید.</p>',
            '      <div class="exam-payment-features">' + features.map(function (item, index) {
                return [
                    '<article class="exam-payment-feature">',
                    '  <span class="exam-payment-feature__index">' + escapeHtml((index + 1).toLocaleString("fa-IR")) + "</span>",
                    '  <p>' + escapeHtml(item || "") + "</p>",
                    "</article>"
                ].join("");
            }).join("") + "</div>",
            "    </section>",
            '    <section class="exam-payment-panel">',
            '      <h3 class="exam-payment-panel__title">وضعیت فعلی شما</h3>',
            '      <p class="exam-payment-panel__copy">' + escapeHtml(access.hasAccess ? "دسترسی این درس برای حساب شما فعال است." : (access.requiresLogin ? "برای پرداخت باید وارد حساب خود شوید." : (access.canPurchase ? "هنوز دسترسی این درس فعال نشده است." : "در حال حاضر امکان فعال‌سازی این درس برای این حساب وجود ندارد."))) + "</p>",
            '      <div class="exam-payment-headline"><span class="exam-payment-chip">' + escapeHtml(access.unlockLabel || "—") + "</span></div>",
            "    </section>",
            "  </aside>",
            "</section>"
        ].join("");
    }

    function setError(message) {
        root.innerHTML = '<section class="exam-payment-panel"><p class="exam-payment-panel__copy">' + escapeHtml(message || "بارگذاری انجام نشد.") + "</p></section>";
    }

    function loadCourse() {
        if (!courseSlug || state.loading) {
            return Promise.resolve();
        }
        state.loading = true;
        root.innerHTML = '<section class="exam-payment-panel"><p class="exam-payment-panel__copy">در حال بارگذاری اطلاعات پرداخت...</p></section>';
        return examsGet("course", { course: courseSlug }).then(function (payload) {
            if (!payload || !payload.success || !payload.course) {
                throw new Error((payload && payload.error) || "بارگذاری اطلاعات پرداخت انجام نشد.");
            }
            state.course = payload.course;
            state.viewer = payload.viewer || null;
            renderPayment();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    function loadOrderResult() {
        if (!paymentOrderToken) {
            return Promise.resolve();
        }
        return paymentsGet("publicOrderResult", { orderToken: paymentOrderToken }).then(function (payload) {
            if (payload && payload.success && payload.order) {
                state.result = payload.order;
                state.feedback = "";
                state.feedbackKind = "";
            }
            if (state.result && String(state.result.status || "") === "success") {
                return loadCourse();
            }
            renderPayment();
            return null;
        }).catch(function () {
            renderPayment();
        });
    }

    function submitPayment() {
        if (!state.course || state.paying) {
            return;
        }
        state.paying = true;
        state.feedback = "در حال انتقال به درگاه پرداخت...";
        state.feedbackKind = "";
        renderPayment();

        paymentsPost("createCollectionOrder", {
            token: state.course.collectionToken || "",
            returnPath: returnPath()
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.redirectUrl) {
                throw new Error((payload && payload.error) || "ایجاد پرداخت انجام نشد.");
            }
            window.location.href = payload.redirectUrl;
        }).catch(function (error) {
            state.feedback = error && error.message ? error.message : "ایجاد پرداخت انجام نشد.";
            state.feedbackKind = "error";
            state.paying = false;
            renderPayment();
        });
    }

    root.addEventListener("click", function (event) {
        if (event.target && event.target.id === "exam-payment-submit") {
            submitPayment();
        }
    });

    function boot(detail) {
        state.auth = detail || null;
        loadCourse().then(loadOrderResult);
    }

    if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
        window.Dent1402Auth.onChange(function (detail) {
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                root.innerHTML = '<section class="exam-payment-panel"><p class="exam-payment-panel__copy">در حال بررسی وضعیت ورود...</p></section>';
                return;
            }
            boot(detail);
        });
    } else {
        boot(null);
    }
})();
