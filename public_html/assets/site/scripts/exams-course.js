(function () {
    "use strict";

    var root = document.getElementById("exams-course-root");
    var body = document.body;
    var courseSlug = body && body.dataset ? String(body.dataset.examsCourse || "").trim() : "";
    if (!root || !courseSlug) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var state = {
        loading: false,
        saving: false,
        course: null,
        viewer: null,
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

    function apiGet(action, payload) {
        var query = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        query.set("_t", String(Date.now()));
        return fetch("/api/exams_api.php?" + query.toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson);
    }

    function apiPost(action, payload) {
        return fetch("/api/exams_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})))
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

    function statusMeta(course) {
        var access = course && course.access ? course.access : {};
        if (course && course.paymentMode === "paid" && access.hasAccess) {
            return {
                label: access.unlockLabel || "پرداخت تایید شده",
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

    function feedbackHtml() {
        if (!state.feedback) {
            return '<div class="exams-feedback"></div>';
        }
        return '<div class="exams-feedback is-' + escapeHtml(state.feedbackKind || "") + '">' + escapeHtml(state.feedback) + "</div>";
    }

    function ownerPanelHtml(course) {
        if (!course || !course.ownerSettings || !course.ownerSettings.canManage) {
            return "";
        }

        var isPaid = String(course.paymentMode || "free") === "paid";
        return [
            '<aside class="exams-card exams-owner-panel">',
            '  <div class="exams-panel-head">',
            '    <div>',
            '      <span class="exams-owner-chip">فقط برای مالک</span>',
            '      <h3 class="exams-panel-title">تنظیم دسترسی این درس</h3>',
            "    </div>",
            '    <span class="exams-session-meta">به‌روزرسانی: ' + escapeHtml(formatDateTime(course.ownerSettings.updatedAt, "—")) + "</span>",
            "  </div>",
            '  <p class="exams-inline-note">پرداخت این درس یک‌باره است و بعد از تایید، همه آزمون‌های همین درس برای همان کاربر باز می‌شود.</p>',
            '  <form id="exams-owner-form" class="exams-owner-form" novalidate>',
            '    <div class="exams-owner-row">',
            '      <div class="exams-owner-modes">',
            '        <label class="exams-owner-mode"><input type="radio" name="paymentMode" value="free"' + (!isPaid ? " checked" : "") + '> <span>رایگان</span></label>',
            '        <label class="exams-owner-mode"><input type="radio" name="paymentMode" value="paid"' + (isPaid ? " checked" : "") + '> <span>پولی</span></label>',
            "      </div>",
            "    </div>",
            '    <label class="exams-owner-label">',
            "      <span>هزینه این درس</span>",
            '      <input class="exams-owner-input" id="exams-owner-amount" name="amount" type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(String(course.amount || "")) + '" placeholder="مثلا 3500000">',
            "    </label>",
            '    <div class="exams-owner-actions">',
            '      <button class="exam-btn exam-btn--primary" type="submit"' + (state.saving ? " disabled" : "") + '>' + (state.saving ? "در حال ذخیره..." : "ذخیره تنظیمات") + "</button>",
            "    </div>",
                 feedbackHtml(),
            "  </form>",
            "</aside>"
        ].join("");
    }

    function paywallHtml(course) {
        if (!course) {
            return "";
        }

        var access = course.access || {};
        var actionHref = course.paymentPath || "/exams/";
        var actionLabel = "فعال‌سازی همه آزمون‌های این درس";
        var note = course.paymentDescription || "";

        if (course.paymentMode !== "paid") {
            note = "این درس رایگان است و همه جلسه‌ها مستقیم در دسترس‌اند.";
            actionHref = course.exams && course.exams[0] ? course.exams[0].href || course.path : course.path;
            actionLabel = "شروع آزمون‌ها";
        } else if (access.hasAccess) {
            note = "پرداخت این درس برای حساب شما تایید شده و همه جلسه‌ها باز هستند.";
            actionHref = course.exams && course.exams[0] ? course.exams[0].href || course.path : course.path;
            actionLabel = "ورود به آزمون‌ها";
        } else if (access.requiresLogin) {
            actionHref = loginHref();
            actionLabel = "ورود برای ادامه";
            note = "برای فعال‌سازی دسترسی این درس، ابتدا باید وارد حساب کاربری خود شوید.";
        } else if (!access.canPurchase) {
            actionHref = course.path || "/exams/";
            actionLabel = "بازگشت";
            note = access.unlockKey === "inactive"
                ? "پرداخت این درس فعلا از سمت مالک غیرفعال است."
                : "فعلا امکان فعال‌سازی این درس برای این حساب وجود ندارد.";
        }

        return [
            '<aside class="exams-card exams-paywall">',
            '  <div class="exams-panel-head">',
            '    <div>',
            '      <span class="exams-kicker">دسترسی درس</span>',
            '      <h3 class="exams-paywall-title">' + escapeHtml(course.paymentMode === "paid" ? (course.amountLabel || "پولی") : "رایگان") + "</h3>",
            "    </div>",
            '    <span class="' + escapeHtml(statusMeta(course).className) + '">' + escapeHtml(statusMeta(course).label) + "</span>",
            "  </div>",
            '  <p class="exams-paywall-copy">' + escapeHtml(note) + "</p>",
            '  <div class="exams-card-actions">',
            '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(actionHref) + '">' + escapeHtml(actionLabel) + "</a>",
            '    <span class="exams-session-meta">' + escapeHtml((Math.max(0, Number(course.stats && course.stats.successCount || 0))).toLocaleString("fa-IR") + " دسترسی تاییدشده") + "</span>",
            "  </div>",
            "</aside>"
        ].join("");
    }

    function sessionCardHtml(session) {
        return [
            '<article class="exams-card exam-session-card' + (session.isLocked ? " is-locked" : "") + '">',
            '  <div class="exam-session-card__top">',
            '    <div>',
            '      <span class="exams-kicker">' + escapeHtml(session.label || "") + "</span>",
            '      <h3 class="exam-session-title">' + escapeHtml(session.title || "") + "</h3>",
            '      <p class="exam-session-copy">' + escapeHtml(session.isLocked ? "برای دیدن سوال‌ها باید دسترسی این درس را فعال کنید." : "آزمون را شروع کنید و در پایان نتیجه و پاسخ تشریحی را ببینید.") + "</p>",
            "    </div>",
            '    <span class="exam-session-card__count">' + escapeHtml((Math.max(0, Number(session.questionCount || 0))).toLocaleString("fa-IR") + " سوال") + "</span>",
            "  </div>",
            '  <div class="exam-session-actions">',
            '    <a class="exam-btn ' + (session.isLocked ? "exam-btn--ghost" : "exam-btn--primary") + '" href="' + escapeHtml(session.href || session.path || "#") + '">' + escapeHtml(session.isLocked ? "پرداخت و فعال‌سازی" : "ورود به آزمون") + "</a>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderCourse() {
        var course = state.course;
        if (!course) {
            root.innerHTML = '<div class="exams-card exams-empty">اطلاعات این درس در دسترس نیست.</div>';
            return;
        }

        var status = statusMeta(course);
        var sessions = Array.isArray(course.exams) ? course.exams : [];
        root.innerHTML = [
            '<section class="exams-card exams-hero">',
            '  <span class="exams-kicker">' + escapeHtml(course.badge || "") + "</span>",
            '  <div class="exams-panel-head">',
            '    <div style="flex:1 1 320px;">',
            '      <h2 class="exams-course-title">' + escapeHtml(course.heroTitle || course.title || "") + "</h2>",
            '      <p class="exams-course-description">' + escapeHtml(course.heroDescription || "") + "</p>",
            "    </div>",
            '    <span class="' + escapeHtml(status.className) + '">' + escapeHtml(status.label) + "</span>",
            "  </div>",
            "</section>",
            '<section class="exams-summary-grid">',
            '  <article class="exams-card exams-stat"><dt>آزمون‌های فعال</dt><dd>' + escapeHtml((Math.max(0, Number(course.stats && course.stats.examCount || 0))).toLocaleString("fa-IR")) + "</dd></article>",
            '  <article class="exams-card exams-stat"><dt>مجموع سوال‌ها</dt><dd>' + escapeHtml((Math.max(0, Number(course.stats && course.stats.questionCount || 0))).toLocaleString("fa-IR")) + "</dd></article>",
            '  <article class="exams-card exams-stat"><dt>دسترسی‌های تاییدشده</dt><dd>' + escapeHtml((Math.max(0, Number(course.stats && course.stats.successCount || 0))).toLocaleString("fa-IR")) + "</dd></article>",
            "</section>",
            '<section class="exams-course-layout">',
            '  <div class="exams-course-main">',
            '    <section class="exams-stack exams-session-grid">' + sessions.map(sessionCardHtml).join("") + "</section>",
            "  </div>",
            '  <div class="exams-course-side">',
                 paywallHtml(course),
                 ownerPanelHtml(course),
            "  </div>",
            "</section>"
        ].join("");
    }

    function setLoading() {
        root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری این درس...</div>';
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
        apiGet("course", { course: courseSlug }).then(function (payload) {
            if (!payload || !payload.success || !payload.course) {
                throw new Error((payload && payload.error) || "بارگذاری این درس انجام نشد.");
            }
            state.course = payload.course;
            state.viewer = payload.viewer || null;
            renderCourse();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    root.addEventListener("submit", function (event) {
        if (!event.target || event.target.id !== "exams-owner-form") {
            return;
        }
        event.preventDefault();

        if (!state.course || state.saving) {
            return;
        }

        var form = event.target;
        var amountInput = form.querySelector("#exams-owner-amount");
        var modeInput = form.querySelector("input[name='paymentMode']:checked");
        var amountValue = String((amountInput && amountInput.value) || "").replace(/[^\d]/g, "");
        state.saving = true;
        state.feedback = "";
        state.feedbackKind = "";
        renderCourse();

        apiPost("ownerSaveCourseAccess", {
            course: courseSlug,
            paymentMode: modeInput ? modeInput.value : "free",
            amount: amountValue
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.course) {
                throw new Error((payload && payload.error) || "ذخیره تنظیمات انجام نشد.");
            }
            state.course = payload.course;
            state.feedback = payload.message || "تنظیمات ذخیره شد.";
            state.feedbackKind = "success";
            renderCourse();
        }).catch(function (error) {
            state.feedback = error && error.message ? error.message : "ذخیره تنظیمات انجام نشد.";
            state.feedbackKind = "error";
            renderCourse();
        }).finally(function () {
            state.saving = false;
            renderCourse();
        });
    });

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
