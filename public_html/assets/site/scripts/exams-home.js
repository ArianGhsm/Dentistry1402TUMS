(function () {
    "use strict";

    var root = document.getElementById("exams-home-root");
    if (!root) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var state = {
        loading: false,
        catalog: null,
        viewer: null
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

    function apiGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            query.set("cohort", cohort);
        }
        query.set("_t", String(Date.now()));
        return fetch("/api/exams_api.php?" + query.toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson);
    }

    function formatQuestions(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " سوال";
    }

    function statusMeta(course) {
        var access = course && course.access ? course.access : {};
        if (course && course.paymentMode === "paid" && access.hasAccess) {
            return {
                label: access.unlockLabel || "باز شده",
                className: "exams-status-pill exams-status-pill--unlocked"
            };
        }
        if (course && course.paymentMode === "paid") {
            return {
                label: course.amountLabel || "پولی",
                className: "exams-status-pill exams-status-pill--paid"
            };
        }
        return {
            label: "رایگان",
            className: "exams-status-pill exams-status-pill--free"
        };
    }

    function renderCatalog() {
        var catalog = state.catalog;
        if (!catalog || !Array.isArray(catalog.courses) || !catalog.courses.length) {
            root.innerHTML = '<div class="exams-card exams-empty">هنوز درسی برای این بخش ثبت نشده است.</div>';
            return;
        }

        root.innerHTML = catalog.courses.map(function (course) {
            var status = statusMeta(course);
            return [
                '<article class="exams-card exam-card">',
                '  <div class="exam-card__top">',
                '    <div>',
                '      <span class="exams-kicker">' + escapeHtml(course.badge || "") + "</span>",
                '      <h3 class="exam-card__title">' + escapeHtml(course.title || "") + "</h3>",
                "    </div>",
                '    <span class="' + escapeHtml(status.className) + '">' + escapeHtml(status.label) + "</span>",
                "  </div>",
                '  <p class="exam-card__desc">' + escapeHtml(course.cardDescription || course.heroDescription || "") + "</p>",
                '  <div class="exams-card-actions">',
                '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(course.path || "/exams/") + '">ورود به صفحه درس</a>',
                '    <span class="exams-session-meta">' + escapeHtml((Math.max(0, Number(course.stats && course.stats.examCount || 0))).toLocaleString("fa-IR") + " آزمون") + "</span>",
                '    <span class="exams-session-meta">' + escapeHtml(formatQuestions(course.stats && course.stats.questionCount || 0)) + "</span>",
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function setLoading() {
        root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری آزمون‌ها...</div>';
    }

    function setError(message) {
        root.innerHTML = '<div class="exams-card exams-empty">' + escapeHtml(message || "بارگذاری انجام نشد.") + "</div>";
    }

    function load() {
        if (state.loading) {
            return;
        }
        state.loading = true;
        setLoading();
        apiGet("catalog").then(function (payload) {
            if (!payload || !payload.success || !payload.catalog) {
                throw new Error((payload && payload.error) || "بارگذاری آزمون‌ها انجام نشد.");
            }
            state.catalog = payload.catalog;
            state.viewer = payload.viewer || null;
            renderCatalog();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
        window.Dent1402Auth.onChange(function (detail) {
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                setLoading();
                return;
            }
            load();
        });
    } else {
        load();
    }
})();
