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

    function renderShell(title, copy, extraHtml) {
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<main class="exam-main">',
            '  <section class="exam-panel exam-empty-state">',
            '    <h1>' + escapeHtml(title) + "</h1>",
            '    <p>' + escapeHtml(copy) + "</p>",
                 extraHtml || "",
            "  </section>",
            "</main>"
        ].join("");
    }

    function renderLoading() {
        renderShell("در حال بارگذاری آزمون", "دسترسی و داده‌های آزمون در حال بررسی است.");
    }

    function renderFailure(message) {
        renderShell("بارگذاری آزمون انجام نشد", message || "این آزمون فعلا در دسترس نیست.", '<a class="back-btn" href="/exams/">بازگشت به آزمون‌ها</a>');
    }

    function renderLogin() {
        var guard = "";
        if (window.Dent1402Auth && typeof window.Dent1402Auth.renderLoginRequiredGuard === "function") {
            guard = window.Dent1402Auth.renderLoginRequiredGuard({
                loginHref: loginHref(),
                fallbackHref: "/exams/",
                primaryClass: "back-btn",
                secondaryClass: "back-btn"
            });
        } else {
            guard = '<a class="back-btn" href="' + escapeHtml(loginHref()) + '">ورود به حساب</a>';
        }
        renderShell("نیاز به ورود", "برای مشاهده سوال‌های این آزمون باید ابتدا وارد حساب کاربری خود شوید.", guard);
        if (window.Dent1402Auth && typeof window.Dent1402Auth.enhanceLoginGuards === "function") {
            window.Dent1402Auth.enhanceLoginGuards(appRoot);
        }
    }

    function renderPaywall(course) {
        var title = course && course.title ? "این درس نیاز به فعال‌سازی دارد" : "نیاز به پرداخت";
        var copy = course && course.paymentDescription
            ? course.paymentDescription
            : "برای مشاهده سوال‌های این درس، ابتدا باید دسترسی آن را فعال کنید.";
        var button = course && course.paymentPath
            ? '<a class="back-btn" href="' + escapeHtml(course.paymentPath) + '">ورود به صفحه پرداخت این درس</a>'
            : '<a class="back-btn" href="/exams/">بازگشت به آزمون‌ها</a>';
        renderShell(title, copy, button);
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

    function loadExam() {
        if (started) {
            return;
        }
        started = true;
        renderLoading();
        fetch("/api/exams_api.php?" + queryWithCohort().toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson).then(function (payload) {
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
