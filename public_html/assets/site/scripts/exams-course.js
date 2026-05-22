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
        feedbackKind: "",
        query: "",
        filter: "all"
    };

    var FILTERS = [
        { key: "all", label: "همه" },
        { key: "completed", label: "تکمیل‌شده" },
        { key: "in-progress", label: "ادامه" },
        { key: "not-started", label: "شروع‌نشده" },
        { key: "flagged", label: "نشان‌دار" }
    ];

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

    function withCohort(payload) {
        var next = Object.assign({}, payload || {});
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            next.cohort = cohort;
        }
        return next;
    }

    function appendCohortPath(path) {
        var target = String(path || "").trim();
        var cohort = String(params.get("cohort") || "").trim();
        if (!target || !cohort || /(?:\?|&)cohort=/.test(target)) {
            return target;
        }
        return target + (target.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(cohort);
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

    function formatValue(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR");
    }

    function formatCount(value, noun) {
        return formatValue(value) + " " + noun;
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

    function formatPercent(value) {
        var numeric = Math.max(0, Number(value) || 0);
        var hasFraction = Math.abs(numeric - Math.round(numeric)) > 0.001;
        return numeric.toLocaleString("fa-IR", {
            minimumFractionDigits: hasFraction ? 1 : 0,
            maximumFractionDigits: 1
        }) + "٪";
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function searchIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="M16 16L20 20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    function layersIcon() {
        return '<span class="exams-course-hero__art" aria-hidden="true"><span></span><span></span><span></span></span>';
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
                label: course.access && course.access.requiresLogin ? "نیاز به ورود" : (course.amountLabel || "پولی"),
                className: "exams-status-pill exams-status-pill--paid"
            };
        }
        return {
            label: "رایگان",
            className: "exams-status-pill exams-status-pill--free"
        };
    }

    function courseItemNoun(course) {
        if (course && course.supportsDirectAttemptableExams) {
            return "جلسه";
        }
        return "بخش";
    }

    function sessionLastAttempt(progress) {
        var record = progress && progress.assessmentReport ? progress.assessmentReport : null;
        return String((progress && progress.lastAttemptAt) || (record && (record.updatedAt || record.submittedAt)) || "").trim();
    }

    function sessionStatus(session, course) {
        var access = course && course.access ? course.access : {};
        var progress = session && session.viewerProgress ? session.viewerProgress : {};
        var hasReport = !!(progress && progress.hasAssessmentReport && progress.assessmentReport);
        var hasActivity = !!(progress && (progress.hasActivity || progress.hasFlags));

        if (session && session.isLocked) {
            if (access.requiresLogin) {
                return {
                    key: "locked",
                    label: "نیاز به ورود",
                    actionLabel: "ورود",
                    actionHref: loginHref(),
                    hint: "برای دیدن سوال‌ها ابتدا باید وارد حساب کاربری شوی.",
                    className: "exam-session-status exam-session-status--locked"
                };
            }
            return {
                key: "locked",
                label: "نیاز به پرداخت",
                actionLabel: access.canPurchase ? "فعال‌سازی" : "بازگشت",
                actionHref: access.canPurchase ? appendCohortPath(course.paymentPath || session.href || course.path || "/exams/") : appendCohortPath(course.path || "/exams/"),
                hint: access.canPurchase
                    ? "بعد از فعال‌سازی، همه جلسه‌های همین درس از همین‌جا باز می‌شوند."
                    : "فعلاً دسترسی این درس از سمت مدیریت غیرفعال است.",
                className: "exam-session-status exam-session-status--locked"
            };
        }

        if (!session || !session.attemptable) {
            return {
                key: "section",
                label: "بخش",
                actionLabel: "مشاهده بخش",
                actionHref: appendCohortPath((session && (session.path || session.href)) || course.path || "/exams/"),
                hint: "این ردیف یک بخش چندقسمتی است و شروع آزمون از صفحه بعد انجام می‌شود.",
                className: "exam-session-status exam-session-status--section"
            };
        }

        if (hasReport) {
            return {
                key: "completed",
                label: "تکمیل شده",
                actionLabel: "مشاهده آزمون",
                actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
                hint: "کارنامه این جلسه روی حساب شما ذخیره شده است.",
                className: "exam-session-status exam-session-status--completed"
            };
        }

        if (hasActivity) {
            return {
                key: "in-progress",
                label: "ادامه",
                actionLabel: "ادامه آزمون",
                actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
                hint: "آخرین فعالیت شما برای این جلسه ذخیره شده است.",
                className: "exam-session-status exam-session-status--progress"
            };
        }

        return {
            key: "not-started",
            label: "شروع",
            actionLabel: "ورود به آزمون",
            actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
            hint: "انتخاب حالت آزمون داخل صفحه همین جلسه انجام می‌شود.",
            className: "exam-session-status exam-session-status--fresh"
        };
    }

    function createSessionModel(session, course) {
        var progress = session && session.viewerProgress ? session.viewerProgress : null;
        var report = progress && progress.assessmentReport ? progress.assessmentReport : null;
        var status = sessionStatus(session, course);
        var flagsCount = Math.max(0, Number(progress && progress.flagsCount || 0));
        var lastAttemptAt = sessionLastAttempt(progress);
        var title = String(session && session.title || "").trim() || String(session && session.label || "").trim() || "جلسه";

        return {
            raw: session,
            title: title,
            label: String(session && session.label || "").trim(),
            attemptable: !!(session && session.attemptable),
            questionCount: Math.max(0, Number(session && session.questionCount || 0)),
            flagsCount: flagsCount,
            report: report,
            progress: progress,
            status: status,
            lastAttemptAt: lastAttemptAt,
            searchable: [title, String(session && session.label || ""), String(session && session.slug || "")].join(" ").toLowerCase()
        };
    }

    function filterCounts(items) {
        var counts = {
            all: items.length,
            completed: 0,
            "in-progress": 0,
            "not-started": 0,
            flagged: 0
        };

        items.forEach(function (item) {
            if (counts[item.status.key] !== undefined) {
                counts[item.status.key] += 1;
            }
            if (item.flagsCount > 0) {
                counts.flagged += 1;
            }
        });

        return counts;
    }

    function matchesQuery(item) {
        var query = String(state.query || "").trim().toLowerCase();
        if (!query) {
            return true;
        }
        return item.searchable.indexOf(query) !== -1;
    }

    function matchesFilter(item) {
        if (state.filter === "all") {
            return true;
        }
        if (state.filter === "flagged") {
            return item.flagsCount > 0;
        }
        return item.status.key === state.filter;
    }

    function latestAttemptLabel(items) {
        var latestRaw = "";
        var latestTime = 0;

        items.forEach(function (item) {
            var raw = String(item.lastAttemptAt || "").trim();
            if (!raw) {
                return;
            }
            var parsed = new Date(raw);
            var timestamp = Number.isFinite(parsed.getTime()) ? parsed.getTime() : 0;
            if (timestamp >= latestTime) {
                latestTime = timestamp;
                latestRaw = raw;
            }
        });

        return latestRaw ? formatDateTime(latestRaw, "—") : "";
    }

    function summaryStat(label, value, accentClass) {
        return [
            '<div class="exams-summary-stat' + (accentClass ? " " + accentClass : "") + '">',
            '  <span class="exams-summary-stat__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exams-summary-stat__value">' + escapeHtml(value) + "</strong>",
            "</div>"
        ].join("");
    }

    function summaryHtml(course, items) {
        var access = statusMeta(course);
        var itemNoun = courseItemNoun(course);
        var averageValue = course.stats && course.stats.viewerAveragePercent !== null && course.stats.viewerAveragePercent !== undefined
            ? formatPercent(course.stats.viewerAveragePercent)
            : "—";
        var latestAttempt = latestAttemptLabel(items);
        var countValue = formatCount(course.stats && course.stats.examCount || 0, itemNoun);

        return [
            '<section class="exams-card exams-course-hero">',
            '  <div class="exams-course-hero__lead">',
                 layersIcon(),
            '    <div class="exams-course-hero__copy">',
            '      <div class="exams-course-hero__topline">',
            '        <span class="exams-kicker">انتخاب ' + escapeHtml(itemNoun) + "</span>",
            '        <span class="' + escapeHtml(access.className) + '">' + escapeHtml(access.label) + "</span>",
            "      </div>",
            '      <h2 class="exams-course-title">' + escapeHtml(course.heroTitle || course.title || "") + "</h2>",
            '      <p class="exams-course-description">' + escapeHtml(course.heroDescription || "همه جلسه‌های این درس در همین صفحه فهرست شده‌اند و انتخاب حالت آزمون داخل صفحه هر جلسه انجام می‌شود.") + "</p>",
            "    </div>",
            '    <div class="exams-course-hero__count">',
            '      <span class="exams-course-hero__count-label">کل ' + escapeHtml(itemNoun) + "</span>",
            '      <strong>' + escapeHtml(formatValue(course.stats && course.stats.examCount || 0)) + "</strong>",
            '      <small>' + escapeHtml(itemNoun) + "</small>",
            "    </div>",
            "  </div>",
            '  <div class="exams-course-hero__stats">',
                 summaryStat("تعداد کل سوالات", formatValue(course.stats && course.stats.questionCount || 0)),
                 summaryStat("کارنامه‌های ثبت‌شده", formatValue(course.stats && course.stats.completedAssessmentCount || 0)),
                 summaryStat("میانگین تو", averageValue, averageValue !== "—" ? "is-accent" : ""),
                 summaryStat("نشان‌دارها", formatValue(course.stats && course.stats.flaggedQuestionsCount || 0)),
            "  </div>",
            '  <div class="exams-course-hero__footer">',
            '    <span class="exams-session-meta">' + escapeHtml(course.badge || countValue) + "</span>",
            latestAttempt
                ? '<span class="exams-session-meta">آخرین شرکت: ' + escapeHtml(latestAttempt) + "</span>"
                : '<span class="exams-session-meta">انتخاب حالت آزمون داخل صفحه هر جلسه انجام می‌شود.</span>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function toolbarHtml(items) {
        var counts = filterCounts(items);

        return [
            '<section class="exams-card exams-toolbar-card">',
            '  <label class="exams-search-field" for="exams-session-search">',
            '    <span class="exams-search-field__icon">' + searchIcon() + "</span>",
            '    <input id="exams-session-search" type="search" inputmode="search" autocomplete="off" placeholder="جستجو در عنوان جلسه..." value="' + escapeHtml(state.query) + '">',
            "  </label>",
            '  <div class="exams-filter-row" role="tablist" aria-label="فیلتر جلسه‌ها">',
            FILTERS.map(function (filter) {
                var isActive = state.filter === filter.key;
                return [
                    '<button class="exams-filter-chip' + (isActive ? " is-active" : "") + '" type="button" data-session-filter="' + escapeHtml(filter.key) + '" role="tab" aria-selected="' + (isActive ? "true" : "false") + '">',
                    '  <span>' + escapeHtml(filter.label) + "</span>",
                    '  <strong>' + escapeHtml(formatValue(counts[filter.key] || 0)) + "</strong>",
                    "</button>"
                ].join("");
            }).join(""),
            "  </div>",
            "</section>"
        ].join("");
    }

    function paywallHtml(course) {
        if (!course || course.paymentMode !== "paid" || (course.access && course.access.hasAccess)) {
            return "";
        }

        var access = course.access || {};
        var actionHref = access.requiresLogin
            ? loginHref()
            : appendCohortPath(course.paymentPath || course.path || "/exams/");
        var actionLabel = access.requiresLogin
            ? "ورود برای ادامه"
            : (access.canPurchase ? "فعال‌سازی همه جلسه‌ها" : "بازگشت");
        var note = access.requiresLogin
            ? "برای ذخیره کارنامه و شروع جلسه‌ها ابتدا باید وارد حساب کاربری خودت شوی."
            : (course.paymentDescription || "با یک بار پرداخت، دسترسی همه جلسه‌های این درس برای همین حساب فعال می‌شود.");

        return [
            '<aside class="exams-card exams-access-card">',
            '  <div class="exams-access-card__head">',
            '    <div>',
            '      <span class="exams-kicker">دسترسی به درس</span>',
            '      <h3 class="exams-panel-title">' + escapeHtml(course.amountLabel || "فعال‌سازی درس") + "</h3>",
            "    </div>",
            '    <span class="' + escapeHtml(statusMeta(course).className) + '">' + escapeHtml(statusMeta(course).label) + "</span>",
            "  </div>",
            '  <p class="exams-inline-note">' + escapeHtml(note) + "</p>",
            '  <div class="exams-card-actions">',
            '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(actionHref) + '">' + escapeHtml(actionLabel) + "</a>",
            course.stats && course.stats.totalOrders
                ? '<span class="exams-session-meta">' + escapeHtml(formatValue(course.stats.totalOrders)) + " سفارش</span>"
                : "",
            "  </div>",
            "</aside>"
        ].join("");
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
        var openAttr = state.feedback || state.saving ? " open" : "";

        return [
            '<details class="exams-card exams-owner-shell"' + openAttr + ">",
            '  <summary class="exams-owner-shell__summary">',
            '    <span class="exams-owner-chip">فقط برای مالک</span>',
            '    <span class="exams-owner-shell__title">تنظیم دسترسی و مبلغ این درس</span>',
            '    <span class="exams-owner-shell__meta">به‌روزرسانی: ' + escapeHtml(formatDateTime(course.ownerSettings.updatedAt, "—")) + "</span>",
            "  </summary>",
            '  <div class="exams-owner-shell__body">',
            '    <p class="exams-inline-note">پرداخت این درس یک‌باره است و بعد از تایید، همه جلسه‌های همین درس برای همان حساب باز می‌شود.</p>',
            '    <form id="exams-owner-form" class="exams-owner-form" novalidate>',
            '      <div class="exams-owner-row">',
            '        <div class="exams-owner-modes">',
            '          <label class="exams-owner-mode"><input type="radio" name="paymentMode" value="free"' + (!isPaid ? " checked" : "") + '> <span>رایگان</span></label>',
            '          <label class="exams-owner-mode"><input type="radio" name="paymentMode" value="paid"' + (isPaid ? " checked" : "") + '> <span>پولی</span></label>',
            "        </div>",
            "      </div>",
            '      <label class="exams-owner-label">',
            "        <span>هزینه این درس</span>",
            '        <input class="exams-owner-input" id="exams-owner-amount" name="amount" type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(String(course.amount || "")) + '" placeholder="مثلاً 300000">',
            "      </label>",
            '      <div class="exams-owner-actions">',
            '        <button class="exam-btn exam-btn--primary" type="submit"' + (state.saving ? " disabled" : "") + '>' + (state.saving ? "در حال ذخیره..." : "ذخیره تنظیمات") + "</button>",
            "      </div>",
                     feedbackHtml(),
            "    </form>",
            "  </div>",
            "</details>"
        ].join("");
    }

    function sessionCardHtml(item) {
        var resultLabel = item.report ? formatPercent(item.report.percent || 0) : "—";
        var lastAttemptLabel = item.lastAttemptAt
            ? formatDateTime(item.lastAttemptAt, "—")
            : (item.status.key === "not-started" ? "هنوز ثبت نشده" : "—");

        return [
            '<article class="exams-card exam-session-card is-' + escapeHtml(item.status.key) + '">',
            '  <a class="exam-session-card__link" href="' + escapeHtml(item.status.actionHref) + '">',
            '    <span class="exam-session-card__icon" aria-hidden="true"></span>',
            '    <div class="exam-session-card__body">',
            '      <div class="exam-session-card__head">',
            '        <div class="exam-session-card__title-wrap">',
            '          <span class="exam-session-card__eyebrow">' + escapeHtml(item.label || "جلسه") + "</span>",
            '          <h3 class="exam-session-title">' + escapeHtml(item.title) + "</h3>",
            "        </div>",
            '        <span class="' + escapeHtml(item.status.className) + '">' + escapeHtml(item.status.label) + "</span>",
            "      </div>",
            '      <div class="exam-session-card__stats">',
            '        <div class="exam-session-stat"><span>تعداد سوال</span><strong>' + escapeHtml(formatValue(item.questionCount)) + "</strong></div>",
            '        <div class="exam-session-stat"><span>نتیجه</span><strong>' + escapeHtml(resultLabel) + "</strong></div>",
            '        <div class="exam-session-stat"><span>نشان‌دار</span><strong>' + escapeHtml(formatValue(item.flagsCount)) + "</strong></div>",
            '        <div class="exam-session-stat exam-session-stat--wide"><span>آخرین شرکت</span><strong>' + escapeHtml(lastAttemptLabel) + "</strong></div>",
            "      </div>",
            '      <div class="exam-session-card__footer">',
            '        <p class="exam-session-card__hint">' + escapeHtml(item.status.hint) + "</p>",
            '        <span class="exam-session-card__action">' + escapeHtml(item.status.actionLabel) + "</span>",
            "      </div>",
            "    </div>",
            "  </a>",
            "</article>"
        ].join("");
    }

    function emptyStateHtml(message) {
        return '<div class="exams-card exams-empty">' + escapeHtml(message) + "</div>";
    }

    function renderCourse() {
        var course = state.course;
        if (!course) {
            root.innerHTML = emptyStateHtml("اطلاعات این درس در دسترس نیست.");
            return;
        }

        var rawSessions = Array.isArray(course.exams) ? course.exams : [];
        var items = rawSessions.map(function (session) {
            return createSessionModel(session, course);
        });
        var filteredItems = items.filter(function (item) {
            return matchesQuery(item) && matchesFilter(item);
        });

        root.innerHTML = [
            '<section class="exams-course-shell">',
            '  <div class="exams-course-head">',
                     summaryHtml(course, items),
            "  </div>",
            '  <div class="exams-course-scroll">',
                     toolbarHtml(items),
                     paywallHtml(course),
                     ownerPanelHtml(course),
            filteredItems.length
                ? '<section class="exams-session-list">' + filteredItems.map(sessionCardHtml).join("") + "</section>"
                : emptyStateHtml("برای این جستجو یا فیلتر، جلسه‌ای پیدا نشد."),
            "  </div>",
            "</section>"
        ].join("");
    }

    function setLoading() {
        root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری این درس...</div>';
    }

    function setError(message) {
        root.innerHTML = emptyStateHtml(message || "بارگذاری انجام نشد.");
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

    root.addEventListener("click", function (event) {
        var filterButton = event.target.closest("[data-session-filter]");
        if (!filterButton) {
            return;
        }
        state.filter = String(filterButton.getAttribute("data-session-filter") || "all");
        renderCourse();
    });

    root.addEventListener("input", function (event) {
        var target = event.target;
        if (!target || target.id !== "exams-session-search") {
            return;
        }
        state.query = String(target.value || "");
        renderCourse();
    });

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
