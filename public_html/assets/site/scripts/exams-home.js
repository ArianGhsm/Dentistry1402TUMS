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
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
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
                    return "&#39;";
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

    function appendCohortPath(path) {
        var target = String(path || "").trim();
        var cohort = String(params.get("cohort") || "").trim();
        if (!target || !cohort || /(?:\?|&)cohort=/.test(target)) {
            return target;
        }
        return target + (target.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(cohort);
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
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

    function formatValue(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR");
    }

    function formatPercent(value) {
        var numeric = Math.max(0, Number(value) || 0);
        var hasFraction = Math.abs(numeric - Math.round(numeric)) > 0.001;
        return numeric.toLocaleString("fa-IR", {
            minimumFractionDigits: hasFraction ? 1 : 0,
            maximumFractionDigits: 1
        }) + "٪";
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
                label: access.requiresLogin ? "نیاز به ورود" : (course.amountLabel || "پولی"),
                className: "exams-status-pill exams-status-pill--paid"
            };
        }
        return {
            label: "رایگان",
            className: "exams-status-pill exams-status-pill--free"
        };
    }

    function catalogStats(catalog) {
        var summary = {
            courseCount: 0,
            examCount: 0,
            questionCount: 0,
            completedCount: 0
        };

        (catalog && Array.isArray(catalog.courses) ? catalog.courses : []).forEach(function (course) {
            summary.courseCount += 1;
            summary.examCount += Math.max(0, Number(course.stats && course.stats.examCount || 0));
            summary.questionCount += Math.max(0, Number(course.stats && course.stats.questionCount || 0));
            summary.completedCount += Math.max(0, Number(course.stats && course.stats.completedAssessmentCount || 0));
        });

        return summary;
    }

    function heroStat(label, value, accentClass) {
        return [
            '<div class="exams-summary-stat' + (accentClass ? " " + accentClass : "") + '">',
            '  <span class="exams-summary-stat__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exams-summary-stat__value">' + escapeHtml(value) + "</strong>",
            "</div>"
        ].join("");
    }

    function heroHtml(catalog) {
        var summary = catalogStats(catalog);
        return [
            '<section class="exams-card exams-home-hero">',
            '  <div class="exams-home-hero__copy">',
            '    <span class="exams-kicker">لیست درس‌ها و آزمون‌ها</span>',
            '    <h2 class="exams-title">' + escapeHtml(catalog.title || "آزمون‌ها") + "</h2>",
            '    <p class="exams-description">' + escapeHtml(catalog.description || "درس موردنظر را انتخاب کن؛ انتخاب حالت آزمون داخل صفحه هر جلسه انجام می‌شود.") + "</p>",
            '    <div class="exams-home-hero__meta">',
            '      <span class="exams-session-meta">ورود به هر درس، لیست جلسه‌های همان درس را باز می‌کند.</span>',
            '      <span class="exams-session-meta">انتخاب حالت آزمون داخل صفحه هر جلسه انجام می‌شود.</span>',
            "    </div>",
            "  </div>",
            '  <div class="exams-home-hero__stats">',
                 heroStat("درس", formatValue(summary.courseCount)),
                 heroStat("جلسه / بخش", formatValue(summary.examCount)),
                 heroStat("کل سوال", formatValue(summary.questionCount)),
                 heroStat("کارنامه ثبت‌شده", formatValue(summary.completedCount), summary.completedCount ? "is-accent" : ""),
            "  </div>",
            "</section>"
        ].join("");
    }

    function courseAction(course) {
        var access = course && course.access ? course.access : {};
        var directLabel = course && course.supportsDirectAttemptableExams ? "مشاهده جلسه‌ها" : "مشاهده بخش‌ها";

        if (course && course.paymentMode === "paid" && !access.hasAccess) {
            if (access.requiresLogin) {
                return {
                    label: "ورود برای ادامه",
                    href: loginHref()
                };
            }
            return {
                label: access.canPurchase ? "فعال‌سازی و ورود" : "مشاهده درس",
                href: access.canPurchase ? appendCohortPath(course.paymentPath || course.path || "/exams/") : appendCohortPath(course.path || "/exams/")
            };
        }

        return {
            label: directLabel,
            href: appendCohortPath(course.path || "/exams/")
        };
    }

    function courseCardHtml(course) {
        var status = statusMeta(course);
        var action = courseAction(course);
        var averageValue = course.stats && course.stats.viewerAveragePercent !== null && course.stats.viewerAveragePercent !== undefined
            ? formatPercent(course.stats.viewerAveragePercent)
            : "—";
        var itemLabel = course.supportsDirectAttemptableExams ? "جلسه" : "بخش";

        return [
            '<article class="exams-card exam-course-card">',
            '  <div class="exam-course-card__top">',
            '    <div class="exam-course-card__copy">',
            '      <div class="exam-course-card__eyebrow-row">',
            '        <span class="exams-kicker">' + escapeHtml(course.badge || "") + "</span>",
            '        <span class="' + escapeHtml(status.className) + '">' + escapeHtml(status.label) + "</span>",
            "      </div>",
            '      <h3 class="exam-course-card__title">' + escapeHtml(course.title || "") + "</h3>",
            '      <p class="exam-course-card__desc">' + escapeHtml(course.cardDescription || course.heroDescription || "ورود به این درس، لیست جلسه‌ها و گزارش عملکردت را نشان می‌دهد.") + "</p>",
            "    </div>",
            '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(action.href) + '">' + escapeHtml(action.label) + "</a>",
            "  </div>",
            '  <div class="exam-course-card__stats">',
            '    <div class="exam-course-stat"><span>تعداد ' + escapeHtml(itemLabel) + '</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.examCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>کل سوالات</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.questionCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>کارنامه ثبت‌شده</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.completedAssessmentCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>میانگین تو</span><strong>' + escapeHtml(averageValue) + "</strong></div>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderCatalog() {
        var catalog = state.catalog;
        if (!catalog || !Array.isArray(catalog.courses) || !catalog.courses.length) {
            root.innerHTML = '<div class="exams-card exams-empty">هنوز درسی برای این بخش ثبت نشده است.</div>';
            return;
        }

        root.innerHTML = [
            heroHtml(catalog),
            '<section class="exams-catalog-list">',
            catalog.courses.map(courseCardHtml).join(""),
            "</section>"
        ].join("");
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
