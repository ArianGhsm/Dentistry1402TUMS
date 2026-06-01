(function () {
    "use strict";

    var appRoot = document.querySelector("[data-exam-app]");
    var body = document.body;
    var courseSlug = body && body.dataset ? String(body.dataset.examsCourse || "").trim() : "";
    var examSlug = body && body.dataset ? String(body.dataset.examsExam || "").trim() : "";
    var assetVersionQuery = (function () {
        var src = "";
        var currentScript = document.currentScript;
        if (currentScript && typeof currentScript.src === "string" && currentScript.src) {
            src = currentScript.src;
        }
        if (!src) {
            var bootstrapScripts = document.querySelectorAll('script[src*="/assets/site/scripts/exam-bootstrap.js"]');
            if (bootstrapScripts.length > 0) {
                src = bootstrapScripts[bootstrapScripts.length - 1].src || "";
            }
        }
        if (!src) {
            return "";
        }
        try {
            var url = new URL(src, window.location.href);
            var version = String(url.searchParams.get("v") || "").trim();
            return version ? "?v=" + encodeURIComponent(version) : "";
        } catch (_error) {
            return "";
        }
    }());
    if (!appRoot || !courseSlug || !examSlug) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var started = false;
    var BOOTSTRAP_TIMEOUT_MS = 14000;

    appRoot.addEventListener("click", function (event) {
        var retryButton = event.target.closest("[data-exam-bootstrap-action='retry']");
        if (!retryButton) {
            return;
        }
        event.preventDefault();
        started = false;
        loadExam();
    });

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

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function appendCohortPath(path) {
        var target = String(path || "").trim();
        var cohort = String(params.get("cohort") || "").trim();
        if (!target || !cohort || /(?:\?|&)cohort=/.test(target)) {
            return target;
        }
        return target + (target.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(cohort);
    }

    function defaultBackHref() {
        var path = String(window.location.pathname || "").trim();
        if (!path) {
            return appendCohortPath("/exams/");
        }

        var normalized = path.endsWith("/") ? path.slice(0, -1) : path;
        var separatorIndex = normalized.lastIndexOf("/");
        if (separatorIndex <= 0) {
            return appendCohortPath("/exams/");
        }

        return appendCohortPath(normalized.slice(0, separatorIndex + 1));
    }

    function renderActions(primaryLabel, primaryHref, secondaryLabel, secondaryHref) {
        var parts = [];
        if (primaryLabel && primaryHref) {
            parts.push('<a class="exam-btn exam-btn--primary" href="' + escapeHtml(primaryHref) + '">' + escapeHtml(primaryLabel) + "</a>");
        }
        if (secondaryLabel && secondaryHref) {
            parts.push('<a class="exam-btn exam-btn--ghost" href="' + escapeHtml(secondaryHref) + '">' + escapeHtml(secondaryLabel) + "</a>");
        }
        if (!parts.length) {
            return "";
        }
        return '<div class="exam-side-section exam-side-section--actions">' + parts.join("") + "</div>";
    }

    function queryWithCohort() {
        var query = new URLSearchParams({
            action: "exam",
            course: courseSlug,
            exam: examSlug
        });
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            query.set("cohort", cohort);
        }
        query.set("_t", String(Date.now()));
        return query;
    }

    function renderShell(options) {
        var config = options || {};
        var title = String(config.title || "").trim();
        var copy = String(config.copy || "").trim();
        var eyebrow = String(config.eyebrow || "").trim();
        var extraHtml = String(config.extraHtml || "").trim();
        var backHref = String(config.backHref || "").trim();
        var backLabel = String(config.backLabel || "بازگشت").trim();

        document.body.classList.add("quiz-stage-active");
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <main class="exam-main">',
            '    <section class="exam-stage-shell">',
            '      <div class="exam-stage-scaler">',
            '        <div class="exam-stage-canvas">',
            '          <section class="exam-panel exam-stage exam-stage--message">',
            backHref
                ? '            <a class="back-btn exam-back-link" href="' + escapeHtml(backHref) + '" aria-label="' + escapeHtml(backLabel) + '"><span class="back-icon" aria-hidden="true">←</span><span>' + escapeHtml(backLabel) + "</span></a>"
                : "",
            '            <div class="exam-message-card">',
            eyebrow ? '              <span class="exam-kicker">' + escapeHtml(eyebrow) + "</span>" : "",
            '              <h1>' + escapeHtml(title) + "</h1>",
            '              <p>' + escapeHtml(copy) + "</p>",
            "            </div>",
            extraHtml ? '            <div class="exam-empty-card">' + extraHtml + "</div>" : "",
            "          </section>",
            "        </div>",
            "      </div>",
            "    </section>",
            "  </main>",
            "</div>"
        ].join("");
    }

    function renderLoading() {
        renderShell({
            title: "در حال بارگذاری آزمون",
            copy: "دسترسی و داده‌های آزمون در حال بررسی است.",
            eyebrow: "در حال همگام‌سازی",
            extraHtml: [
                '<div class="exam-busy-card exam-busy-card--bootstrap">',
                '  <span class="exam-kicker">آماده‌سازی</span>',
                '  <strong class="exam-busy-card__title">در حال واکشی سوال‌ها و وضعیت آزمون...</strong>',
                '  <div class="exam-busy-card__skeleton">',
                '    <span class="exam-skeleton exam-skeleton--line"></span>',
                '    <span class="exam-skeleton exam-skeleton--line is-short"></span>',
                '    <div class="exam-skeleton-grid">',
                '      <span class="exam-skeleton exam-skeleton--tile"></span>',
                '      <span class="exam-skeleton exam-skeleton--tile"></span>',
                '      <span class="exam-skeleton exam-skeleton--tile"></span>',
                "    </div>",
                "  </div>",
                "</div>"
            ].join("")
        });
    }

    function renderFailure(message) {
        var hint = !navigator.onLine
            ? "اتصال اینترنت قطع یا بسیار ضعیف است. بعد از پایدارشدن شبکه دوباره تلاش کن."
            : message;
        renderShell({
            title: "بارگذاری آزمون انجام نشد",
            copy: hint || "این آزمون فعلا در دسترس نیست.",
            eyebrow: "خطا",
            backHref: defaultBackHref(),
            backLabel: "بازگشت",
            extraHtml: [
                '<div class="exam-side-section exam-side-section--actions">',
                '  <button class="exam-btn exam-btn--primary" type="button" data-exam-bootstrap-action="retry">تلاش دوباره</button>',
                '  <a class="exam-btn exam-btn--ghost" href="' + escapeHtml(appendCohortPath("/exams/")) + '">بازگشت به آزمون‌ها</a>',
                '  <a class="exam-btn exam-btn--ghost" href="' + escapeHtml(defaultBackHref()) + '">بازگشت به بخش قبلی</a>',
                "</div>"
            ].join("")
        });
    }

    function renderLogin() {
        renderShell({
            title: "نیاز به ورود",
            copy: "برای مشاهده سوال‌های این آزمون باید ابتدا وارد حساب کاربری خود شوید.",
            eyebrow: "ورود لازم است",
            backHref: defaultBackHref(),
            backLabel: "بازگشت",
            extraHtml: renderActions("ورود به حساب", loginHref(), "بازگشت به بخش قبلی", defaultBackHref())
        });
    }

    function renderPaywall(course) {
        var title = course && course.title ? "این درس نیاز به فعال‌سازی دارد" : "نیاز به پرداخت";
        var copy = course && course.paymentDescription
            ? course.paymentDescription
            : "برای مشاهده سوال‌های این درس، ابتدا باید دسترسی آن را فعال کنید.";
        var paymentHref = course && course.paymentPath
            ? appendCohortPath(course.paymentPath)
            : appendCohortPath("/exams/");
        renderShell({
            title: title,
            copy: copy,
            eyebrow: "دسترسی این درس",
            backHref: defaultBackHref(),
            backLabel: "بازگشت",
            extraHtml: renderActions(
                course && course.paymentPath ? "ورود به صفحه پرداخت این درس" : "بازگشت به آزمون‌ها",
                paymentHref,
                "بازگشت به بخش قبلی",
                defaultBackHref()
            )
        });
    }

    function mountExam(exam) {
        var existing = document.getElementById("exam-data");
        if (existing) {
            existing.remove();
        }

        var node = document.createElement("script");
        node.id = "exam-data";
        node.type = "application/json";
        node.textContent = JSON.stringify(exam || {});
        document.body.appendChild(node);

        var script = document.createElement("script");
        script.src = "/assets/site/scripts/exam-quiz.js" + assetVersionQuery;
        document.body.appendChild(script);
    }

    function fetchWithTimeout(url, options) {
        if (typeof AbortController !== "function") {
            return fetch(url, options).then(parseJson);
        }

        var controller = new AbortController();
        var timer = window.setTimeout(function () {
            controller.abort();
        }, BOOTSTRAP_TIMEOUT_MS);
        var requestOptions = Object.assign({}, options || {}, { signal: controller.signal });

        return fetch(url, requestOptions).then(parseJson).catch(function (error) {
            if (error && error.name === "AbortError") {
                throw new Error("بارگذاری آزمون بیشتر از حد انتظار طول کشید. دوباره تلاش کن.");
            }
            if ((typeof navigator !== "undefined" && navigator.onLine === false) || (error && error.name === "TypeError")) {
                throw new Error("ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کن و دوباره تلاش کن.");
            }
            throw error;
        }).finally(function () {
            window.clearTimeout(timer);
        });
    }

    function loadExam() {
        if (started) {
            return;
        }
        started = true;
        renderLoading();
        fetchWithTimeout("/api/exams_api.php?" + queryWithCohort().toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(function (payload) {
            if (payload && payload.success && payload.exam) {
                mountExam(payload.exam);
                return;
            }
            if (payload && payload.httpStatus === 401) {
                renderLogin();
                return;
            }
            if (payload && payload.httpStatus === 403 && payload.course) {
                renderPaywall(payload.course);
                return;
            }
            throw new Error((payload && payload.error) || "بارگذاری آزمون انجام نشد.");
        }).catch(function (error) {
            renderFailure(error && error.message ? error.message : "بارگذاری آزمون انجام نشد.");
        });
    }

    function boot() {
        renderLoading();
        if (window.Dent1402Auth && typeof window.Dent1402Auth.ready === "function") {
            window.Dent1402Auth.ready().catch(function () {
                return null;
            }).finally(loadExam);
            return;
        }
        loadExam();
    }

    boot();
})();
