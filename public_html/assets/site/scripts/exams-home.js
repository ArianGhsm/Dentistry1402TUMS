(function () {
    "use strict";

    var root = document.getElementById("exams-home-root");
    if (!root) {
        return;
    }

    var state = {
        loading: false,
        catalog: null,
        viewer: null,
        termNumber: 0,
        unitKey: ""
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

    function currentParams() {
        return new URLSearchParams(window.location.search);
    }

    function readTermNumber(value) {
        var numeric = Number(String(value || "").trim() || "0");
        if (!Number.isFinite(numeric) || numeric <= 0) {
            return 0;
        }
        return Math.round(numeric);
    }

    function cleanUnitKey(value) {
        return String(value || "").trim().toLowerCase();
    }

    function readViewFromLocation() {
        var params = currentParams();
        return {
            termNumber: readTermNumber(params.get("term")),
            unitKey: cleanUnitKey(params.get("unit"))
        };
    }

    function applyLocationView() {
        var nextView = readViewFromLocation();
        state.termNumber = nextView.termNumber;
        state.unitKey = nextView.unitKey;
    }

    function buildViewSearch(termNumber, unitKey) {
        var params = currentParams();
        if (termNumber > 0) {
            params.set("term", String(termNumber));
        } else {
            params.delete("term");
        }
        if (unitKey) {
            params.set("unit", unitKey);
        } else {
            params.delete("unit");
        }
        return params.toString();
    }

    function commitView(termNumber, unitKey, replace) {
        var nextTerm = readTermNumber(termNumber);
        var nextUnitKey = cleanUnitKey(unitKey);
        if (nextTerm <= 0) {
            nextUnitKey = "";
        }

        state.termNumber = nextTerm;
        state.unitKey = nextUnitKey;

        var search = buildViewSearch(nextTerm, nextUnitKey);
        var target = window.location.pathname + (search ? "?" + search : "");
        if (replace) {
            window.history.replaceState({}, "", target);
        } else {
            window.history.pushState({}, "", target);
        }

        render();
    }

    function appendCohortPath(path) {
        var target = String(path || "").trim();
        var cohort = String(currentParams().get("cohort") || "").trim();
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
        var cohort = String(currentParams().get("cohort") || "").trim();
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

    function compactText(value, fallback, maxLength) {
        var text = String(value || "").replace(/\s+/g, " ").trim();
        if (!text) {
            text = String(fallback || "").trim();
        }
        if (!text || !maxLength || text.length <= maxLength) {
            return text;
        }

        var sentence = text.split(/[.!؟]/)[0].trim();
        if (sentence && sentence.length <= maxLength) {
            return sentence;
        }

        return text.slice(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    function cleanCourseTitle(value) {
        return String(value || "")
            .replace(/^آزمون[\s‌]*های[\s‌]+/u, "")
            .replace(/^آزمون[\s‌]+/u, "")
            .trim();
    }

    function accentClassName(index) {
        var accents = ["is-accent-a", "is-accent-b", "is-accent-c", "is-accent-d"];
        return accents[Math.abs(Number(index) || 0) % accents.length];
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

    function curriculum() {
        return state.catalog && state.catalog.curriculum && typeof state.catalog.curriculum === "object"
            ? state.catalog.curriculum
            : null;
    }

    function curriculumTerms() {
        var payload = curriculum();
        return payload && Array.isArray(payload.terms) ? payload.terms : [];
    }

    function findTerm(termNumber) {
        var cleanNumber = readTermNumber(termNumber);
        var terms = curriculumTerms();
        for (var index = 0; index < terms.length; index += 1) {
            if (readTermNumber(terms[index] && terms[index].number) === cleanNumber) {
                return terms[index];
            }
        }
        return null;
    }

    function findUnitInTerm(term, unitKey) {
        var cleanKey = cleanUnitKey(unitKey);
        if (!term || !cleanKey) {
            return null;
        }

        var categories = Array.isArray(term.categories) ? term.categories : [];
        for (var categoryIndex = 0; categoryIndex < categories.length; categoryIndex += 1) {
            var units = Array.isArray(categories[categoryIndex].units) ? categories[categoryIndex].units : [];
            for (var unitIndex = 0; unitIndex < units.length; unitIndex += 1) {
                if (cleanUnitKey(units[unitIndex] && units[unitIndex].key) === cleanKey) {
                    return units[unitIndex];
                }
            }
        }

        return null;
    }

    function selectedTerm() {
        return findTerm(state.termNumber);
    }

    function selectedUnit() {
        var term = selectedTerm();
        if (!term || !state.unitKey) {
            return null;
        }
        return findUnitInTerm(term, state.unitKey);
    }

    function normalizeViewState(replaceHistory) {
        var terms = curriculumTerms();
        if (!terms.length) {
            state.termNumber = 0;
            state.unitKey = "";
            return;
        }

        var term = selectedTerm();
        if (!term) {
            if (state.termNumber > 0 || state.unitKey) {
                state.termNumber = 0;
                state.unitKey = "";
                if (replaceHistory) {
                    commitView(0, "", true);
                }
            }
            return;
        }

        if (!state.unitKey) {
            return;
        }

        var unit = selectedUnit();
        if (!unit || cleanUnitKey(unit.entryMode) !== "collections") {
            state.unitKey = "";
            if (replaceHistory) {
                commitView(state.termNumber, "", true);
            }
        }
    }

    function homeHeroHtml() {
        var payload = curriculum();
        var summary = payload && payload.stats ? payload.stats : catalogStats(state.catalog);
        var description = compactText(
            payload && payload.description,
            "اول ترم را انتخاب کن، بعد از داخل دسته واحدها وارد آزمون‌های هر درس شو.",
            96
        );

        return [
            '<section class="exams-card exams-home-hero">',
            '  <div class="exams-home-hero__copy">',
            '    <span class="exams-kicker">ترم‌بندی آزمون‌ها</span>',
            '    <h2 class="exams-title">' + escapeHtml((state.catalog && state.catalog.title) || "آزمون‌ها") + "</h2>",
            '    <p class="exams-description">' + escapeHtml(description) + "</p>",
            '    <div class="exams-home-hero__meta">',
            '      <span class="exams-session-meta">چینش صفحه بر اساس ترم و ساختار واقعی دانشکده انجام شده است.</span>',
            "    </div>",
            "  </div>",
            '  <div class="exams-home-hero__stats">',
                 heroStat("ترم", formatValue(summary.termCount || curriculumTerms().length)),
                 heroStat("واحد فعال", formatValue(summary.availableUnitCount || 0)),
                 heroStat("مجموعه", formatValue(summary.courseCount || 0)),
                 heroStat("سوال", formatValue(summary.questionCount || 0)),
            "  </div>",
            "</section>"
        ].join("");
    }

    function termPreview(term) {
        var titles = [];
        var categories = Array.isArray(term && term.categories) ? term.categories : [];

        categories.forEach(function (category) {
            (Array.isArray(category && category.units) ? category.units : []).forEach(function (unit) {
                if (unit && unit.statusKey !== "empty") {
                    titles.push(String(unit.title || "").trim());
                }
            });
        });

        if (!titles.length) {
            categories.forEach(function (category) {
                (Array.isArray(category && category.units) ? category.units : []).forEach(function (unit) {
                    if (titles.length < 3) {
                        titles.push(String(unit.title || "").trim());
                    }
                });
            });
        }

        return titles.slice(0, 3);
    }

    function termCardHtml(term, index) {
        var stats = term && term.stats ? term.stats : {};
        var availableCount = Math.max(0, Number(stats.availableUnitCount || 0));
        var preview = termPreview(term);
        var description = availableCount > 0
            ? "از " + formatValue(stats.unitCount || 0) + " واحد این ترم، " + formatValue(availableCount) + " واحد الان آزمون دارد."
            : "ساختار این ترم آماده است ولی هنوز آزمونی برای آن ثبت نشده.";

        return [
            '<article class="exams-card exams-term-card ' + accentClassName(index) + '">',
            '  <button class="exams-term-card__link" type="button" data-open-term="' + escapeHtml(term.number) + '">',
            '    <div class="exams-term-card__head">',
            '      <div class="exams-term-card__copy">',
            '        <span class="exams-kicker">' + escapeHtml(term.label || "") + "</span>",
            '        <h3 class="exam-course-card__title">' + escapeHtml(term.label || "") + "</h3>",
            '        <p class="exam-course-card__desc">' + escapeHtml(description) + "</p>",
            "      </div>",
            '      <span class="exams-term-card__badge">' + escapeHtml(availableCount > 0 ? "دارای آزمون" : "بدون آزمون") + "</span>",
            "    </div>",
            preview.length
                ? '    <div class="exams-term-card__preview">' + preview.map(function (title) {
                    return '<span>' + escapeHtml(title) + "</span>";
                }).join("") + "</div>"
                : "",
            '    <div class="exams-term-card__stats">',
            '      <div class="exam-course-stat"><span>واحد</span><strong>' + escapeHtml(formatValue(stats.unitCount || 0)) + "</strong></div>",
            '      <div class="exam-course-stat"><span>مجموعه</span><strong>' + escapeHtml(formatValue(stats.courseCount || 0)) + "</strong></div>",
            '      <div class="exam-course-stat"><span>جلسه</span><strong>' + escapeHtml(formatValue(stats.examCount || 0)) + "</strong></div>",
            '      <div class="exam-course-stat"><span>سوال</span><strong>' + escapeHtml(formatValue(stats.questionCount || 0)) + "</strong></div>",
            "    </div>",
            '    <div class="exams-term-card__footer">',
            '      <span class="exam-btn exam-btn--ghost">ورود به ترم</span>',
            "    </div>",
            "  </button>",
            "</article>"
        ].join("");
    }

    function termGridHtml() {
        var terms = curriculumTerms();
        if (!terms.length) {
            return '<div class="exams-card exams-empty">ساختار ترم‌ها هنوز برای این بخش ثبت نشده است.</div>';
        }

        return [
            homeHeroHtml(),
            '<section class="exams-term-grid">',
            terms.map(function (term, index) {
                return termCardHtml(term, index);
            }).join(""),
            "</section>"
        ].join("");
    }

    function sectionHeroHtml(term, unit) {
        var stats = unit && unit.stats ? unit.stats : (term && term.stats ? term.stats : {});
        var title = unit ? unit.title : (term && term.label) || "آزمون‌ها";
        var kicker = unit ? (unit.categoryTitle || "مجموعه آزمون‌ها") : "واحدهای همین ترم";
        var description = unit
            ? compactText(unit.description, "یکی از مجموعه‌های همین واحد را باز کن تا جلسه‌ها را ببینی.", 88)
            : ((term && term.stats && Number(term.stats.availableUnitCount || 0) > 0)
                ? "واحد موردنظرت را از بین دسته‌های همین ترم انتخاب کن."
                : "ساختار این ترم کامل شده اما هنوز آزمونی به واحدهای آن وصل نشده است.");

        return [
            '<section class="exams-card exams-home-hero exams-home-hero--section">',
            '  <div class="exams-home-hero__copy">',
            '    <div class="exams-home-hero__actions">',
            '      <button class="exam-btn exam-btn--ghost" type="button" data-go-home="true">بازگشت به ترم‌ها</button>',
            unit
                ? '<button class="exam-btn exam-btn--ghost" type="button" data-back-term="' + escapeHtml(term && term.number) + '">بازگشت به ' + escapeHtml(term && term.label || "") + "</button>"
                : "",
            "    </div>",
            '    <span class="exams-kicker">' + escapeHtml(kicker) + "</span>",
            '    <h2 class="exams-title">' + escapeHtml(title) + "</h2>",
            '    <p class="exams-description">' + escapeHtml(description) + "</p>",
            unit && unit.collectionTitles && unit.collectionTitles.length
                ? '    <div class="exams-home-hero__meta"><span class="exams-session-meta">' + escapeHtml(unit.collectionTitles.join(" | ")) + "</span></div>"
                : "",
            "  </div>",
            '  <div class="exams-home-hero__stats">',
            unit
                ? heroStat("مجموعه", formatValue(stats.courseCount || 0))
                : heroStat("واحد", formatValue(stats.unitCount || 0)),
            '    ' + heroStat("جلسه", formatValue(stats.examCount || 0)),
            '    ' + heroStat("سوال", formatValue(stats.questionCount || 0)),
            '    ' + heroStat("فعال", formatValue(unit ? stats.courseCount || 0 : stats.availableUnitCount || 0)),
            "  </div>",
            "</section>"
        ].join("");
    }

    function unitActionHtml(unit) {
        var entryMode = cleanUnitKey(unit && unit.entryMode);
        if (entryMode === "direct") {
            return '<a class="exam-btn exam-btn--primary" href="' + escapeHtml(appendCohortPath(unit.entryHref || "/exams/")) + '">' + escapeHtml(unit.entryLabel || "مشاهده آزمون‌ها") + "</a>";
        }
        if (entryMode === "collections") {
            return '<button class="exam-btn exam-btn--primary" type="button" data-open-unit="' + escapeHtml(unit.key || "") + '">' + escapeHtml(unit.entryLabel || "مشاهده مجموعه‌ها") + "</button>";
        }
        return '<span class="exam-btn exam-btn--muted" aria-disabled="true">هنوز آزمونی ندارد</span>';
    }

    function unitCardHtml(unit, index) {
        var stats = unit && unit.stats ? unit.stats : {};
        var statusClass = unit && unit.statusKey === "empty"
            ? "exams-status-pill exams-status-pill--paid"
            : (unit && unit.statusKey === "multi"
                ? "exams-status-pill exams-status-pill--unlocked"
                : "exams-status-pill exams-status-pill--free");
        var note = unit && unit.collectionTitles && unit.collectionTitles.length > 1
            ? unit.collectionTitles.join(" | ")
            : "";

        return [
            '<article class="exams-card exams-unit-card exam-course-card ' + accentClassName(index) + '">',
            '  <div class="exams-unit-card__head">',
            '    <div class="exams-unit-card__copy">',
            '      <div class="exam-course-card__eyebrow-row">',
            '        <span class="exams-kicker">' + escapeHtml(unit.categoryTitle || "") + "</span>",
            '        <span class="' + escapeHtml(statusClass) + '">' + escapeHtml(unit.statusLabel || "") + "</span>",
            "      </div>",
            '      <h3 class="exam-course-card__title">' + escapeHtml(unit.title || "") + "</h3>",
            '      <p class="exam-course-card__desc">' + escapeHtml(unit.description || "") + "</p>",
            note
                ? '      <p class="exams-unit-card__note">' + escapeHtml(note) + "</p>"
                : "",
            "    </div>",
            '    <div class="exams-unit-card__action">' + unitActionHtml(unit) + "</div>",
            "  </div>",
            '  <div class="exam-course-card__stats">',
            '    <div class="exam-course-stat"><span>مجموعه</span><strong>' + escapeHtml(formatValue(stats.courseCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>جلسه</span><strong>' + escapeHtml(formatValue(stats.examCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>سوال</span><strong>' + escapeHtml(formatValue(stats.questionCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>کارنامه</span><strong>' + escapeHtml(formatValue(stats.completedAssessmentCount || 0)) + "</strong></div>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function categoryCardHtml(category) {
        var units = Array.isArray(category && category.units) ? category.units : [];
        return [
            '<section class="exams-card exams-group-card">',
            '  <div class="exams-group-card__head">',
            '    <div>',
            '      <span class="exams-kicker">' + escapeHtml(category.title || "") + "</span>",
            '      <h3 class="exams-panel-title">' + escapeHtml(category.title || "") + "</h3>",
            "    </div>",
            '    <span class="exams-session-meta">' + escapeHtml(formatValue(category.stats && category.stats.availableUnitCount || 0)) + " واحد فعال</span>",
            "  </div>",
            units.length
                ? '<div class="exams-unit-list">' + units.map(function (unit, index) {
                    return unitCardHtml(unit, index);
                }).join("") + "</div>"
                : '<div class="exams-empty">هنوز واحدی برای این دسته ثبت نشده است.</div>',
            "</section>"
        ].join("");
    }

    function termDetailHtml(term) {
        var categories = Array.isArray(term && term.categories) ? term.categories : [];
        return [
            sectionHeroHtml(term, null),
            categories.map(function (category) {
                return categoryCardHtml(category);
            }).join("")
        ].join("");
    }

    function courseCardHtml(course, index) {
        var status = statusMeta(course);
        var action = courseAction(course);
        var accentClass = accentClassName(index);
        var title = cleanCourseTitle(course.title || "") || String(course.title || "").trim();
        var description = compactText(
            course.cardDescription || course.heroDescription,
            "ورود به این مجموعه، جلسه‌ها و گزارش عملکردت را باز می‌کند.",
            76
        );

        return [
            '<article class="exams-card exam-course-card ' + accentClass + '">',
            '  <div class="exam-course-card__top">',
            '    <div class="exam-course-card__copy">',
            '      <div class="exam-course-card__eyebrow-row">',
            '        <span class="exams-kicker">' + escapeHtml(course.badge || "") + "</span>",
            '        <span class="' + escapeHtml(status.className) + '">' + escapeHtml(status.label) + "</span>",
            "      </div>",
            '      <h3 class="exam-course-card__title">' + escapeHtml(title) + "</h3>",
            '      <p class="exam-course-card__desc">' + escapeHtml(description) + "</p>",
            "    </div>",
            '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(action.href) + '">' + escapeHtml(action.label) + "</a>",
            "  </div>",
            '  <div class="exam-course-card__stats">',
            '    <div class="exam-course-stat"><span>جلسه</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.examCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>سوال</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.questionCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>کارنامه</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.completedAssessmentCount || 0)) + "</strong></div>",
            '    <div class="exam-course-stat"><span>نشان‌دار</span><strong>' + escapeHtml(formatValue(course.stats && course.stats.flaggedQuestionsCount || 0)) + "</strong></div>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function unitCollectionsHtml(term, unit) {
        var collections = Array.isArray(unit && unit.collections) ? unit.collections : [];
        return [
            sectionHeroHtml(term, unit),
            collections.length
                ? '<section class="exams-catalog-list">' + collections.map(function (course, index) {
                    return courseCardHtml(course, index);
                }).join("") + "</section>"
                : '<div class="exams-card exams-empty">برای این واحد هنوز مجموعه‌ای ثبت نشده است.</div>'
        ].join("");
    }

    function renderLegacyCatalog() {
        var catalog = state.catalog;
        var courses = Array.isArray(catalog && catalog.courses) ? catalog.courses : [];
        if (!courses.length) {
            root.innerHTML = '<div class="exams-card exams-empty">هنوز آزمونی برای این بخش ثبت نشده است.</div>';
            return;
        }

        root.innerHTML = [
            homeHeroHtml(),
            '<section class="exams-catalog-list">',
            courses.map(function (course, index) {
                return courseCardHtml(course, index);
            }).join(""),
            "</section>"
        ].join("");
    }

    function render() {
        if (!state.catalog) {
            root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری آزمون‌ها...</div>';
            return;
        }

        var terms = curriculumTerms();
        if (!terms.length) {
            renderLegacyCatalog();
            return;
        }

        normalizeViewState(false);

        if (!state.termNumber) {
            root.innerHTML = termGridHtml();
            return;
        }

        var term = selectedTerm();
        if (!term) {
            root.innerHTML = termGridHtml();
            return;
        }

        if (state.unitKey) {
            var unit = selectedUnit();
            if (unit && cleanUnitKey(unit.entryMode) === "collections") {
                root.innerHTML = unitCollectionsHtml(term, unit);
                return;
            }
        }

        root.innerHTML = termDetailHtml(term);
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
            normalizeViewState(true);
            render();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    root.addEventListener("click", function (event) {
        var homeButton = event.target.closest("[data-go-home]");
        if (homeButton) {
            event.preventDefault();
            commitView(0, "", false);
            return;
        }

        var termButton = event.target.closest("[data-open-term]");
        if (termButton) {
            event.preventDefault();
            commitView(termButton.getAttribute("data-open-term"), "", false);
            return;
        }

        var backTermButton = event.target.closest("[data-back-term]");
        if (backTermButton) {
            event.preventDefault();
            commitView(backTermButton.getAttribute("data-back-term"), "", false);
            return;
        }

        var unitButton = event.target.closest("[data-open-unit]");
        if (unitButton) {
            event.preventDefault();
            commitView(state.termNumber, unitButton.getAttribute("data-open-unit"), false);
        }
    });

    window.addEventListener("popstate", function () {
        applyLocationView();
        render();
    });

    if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
        window.Dent1402Auth.onChange(function (detail) {
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                setLoading();
                return;
            }
            applyLocationView();
            load();
        });
    } else {
        applyLocationView();
        load();
    }
})();
