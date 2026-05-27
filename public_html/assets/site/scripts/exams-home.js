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

    function joinMetaParts(parts) {
        return parts.filter(function (part) {
            return !!String(part || "").trim();
        }).join(" • ");
    }

    function simpleHeroHtml(options) {
        var config = options || {};
        return [
            '<section class="catalog-simple-hero">',
            config.actionsHtml
                ? '  <div class="catalog-simple-hero__actions">' + config.actionsHtml + "</div>"
                : "",
            '  <div class="catalog-simple-hero__copy">',
            config.eyebrow
                ? '    <span class="catalog-simple-hero__eyebrow">' + escapeHtml(config.eyebrow) + "</span>"
                : "",
            '    <h2 class="catalog-simple-hero__title">' + escapeHtml(config.title || "") + "</h2>",
            config.meta
                ? '    <p class="catalog-simple-hero__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            config.secondaryHtml
                ? '    <div class="catalog-simple-hero__secondary">' + config.secondaryHtml + "</div>"
                : "",
            "  </div>",
            "</section>"
        ].join("");
    }

    function simpleRowHtml(options) {
        var config = options || {};
        var interactiveOpen = "";
        var interactiveClose = "";
        var showChevron = true;

        if (config.type === "button") {
            interactiveOpen = '<button class="catalog-simple-row__button" type="button"' + (config.attrs || "") + ">";
            interactiveClose = "</button>";
        } else if (config.type === "static") {
            interactiveOpen = '<div class="catalog-simple-row__static">';
            interactiveClose = "</div>";
            showChevron = false;
        } else {
            interactiveOpen = '<a class="catalog-simple-row__link" href="' + escapeHtml(config.href || "#") + '">';
            interactiveClose = "</a>";
        }

        return [
            '<article class="catalog-simple-row">',
            interactiveOpen,
            '  <div class="catalog-simple-row__body">',
            (config.eyebrow || config.status)
                ? '    <div class="catalog-simple-row__topline">'
                    + (config.eyebrow ? '<span class="catalog-simple-row__eyebrow">' + escapeHtml(config.eyebrow) + "</span>" : "")
                    + (config.status ? '<span class="catalog-simple-row__status' + (config.statusMuted ? " is-muted" : "") + '">' + escapeHtml(config.status) + "</span>" : "")
                    + "</div>"
                : "",
            '    <h3 class="catalog-simple-row__title">' + escapeHtml(config.title || "") + "</h3>",
            config.meta
                ? '    <p class="catalog-simple-row__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            "  </div>",
            '  <div class="catalog-simple-row__tail">',
            config.actionLabel
                ? '<span class="catalog-simple-row__action' + (showChevron ? "" : " is-muted") + '">' + escapeHtml(config.actionLabel) + "</span>"
                : "",
            showChevron ? '<span class="catalog-simple-row__chevron">‹</span>' : "",
            "  </div>",
            interactiveClose,
            "</article>"
        ].join("");
    }

    function simpleGroupHtml(options, rowsHtml) {
        var config = options || {};
        return [
            '<section class="catalog-simple-group">',
            '  <div class="catalog-simple-group__head">',
            config.eyebrow
                ? '    <span class="catalog-simple-group__eyebrow">' + escapeHtml(config.eyebrow) + "</span>"
                : "",
            '    <h3 class="catalog-simple-group__title">' + escapeHtml(config.title || "") + "</h3>",
            config.meta
                ? '    <p class="catalog-simple-group__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            "  </div>",
            '  <div class="catalog-simple-stack">' + (rowsHtml || "") + "</div>",
            "</section>"
        ].join("");
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
        return simpleHeroHtml({
            eyebrow: "آزمون‌ها",
            title: (state.catalog && state.catalog.title) || "آزمون‌ها",
            meta: joinMetaParts([
                formatValue(summary.termCount || curriculumTerms().length) + " ترم",
                formatValue(summary.availableUnitCount || 0) + " واحد فعال",
                formatValue(summary.courseCount || 0) + " مجموعه",
                description
            ])
        });
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
        var meta = joinMetaParts([
            formatValue(stats.unitCount || 0) + " واحد",
            formatValue(stats.courseCount || 0) + " مجموعه",
            formatValue(stats.examCount || 0) + " جلسه",
            preview.length ? preview.join(" | ") : ""
        ]);

        return simpleRowHtml({
            type: "button",
            attrs: ' data-open-term="' + escapeHtml(term.number) + '"',
            eyebrow: "ترم",
            status: availableCount > 0 ? "دارای آزمون" : "بدون آزمون",
            statusMuted: availableCount <= 0,
            title: term.label || "",
            meta: meta,
            actionLabel: "ورود"
        });
    }

    function termGridHtml() {
        var terms = curriculumTerms();
        if (!terms.length) {
            return '<div class="exams-card exams-empty">ساختار ترم‌ها هنوز برای این بخش ثبت نشده است.</div>';
        }

        return [
            homeHeroHtml(),
            '<section class="catalog-simple-stack">',
            terms.map(function (term, index) {
                return termCardHtml(term, index);
            }).join(""),
            "</section>"
        ].join("");
    }

    function sectionHeroHtml(term, unit) {
        var stats = unit && unit.stats ? unit.stats : (term && term.stats ? term.stats : {});
        var title = unit ? unit.title : (term && term.label) || "آزمون‌ها";
        var description = unit
            ? compactText(unit.description, "یکی از مجموعه‌های همین واحد را باز کن تا جلسه‌ها را ببینی.", 80)
            : ((term && term.stats && Number(term.stats.availableUnitCount || 0) > 0)
                ? "واحد موردنظرت را از بین ردیف‌های همین ترم انتخاب کن."
                : "ساختار این ترم ثبت شده اما هنوز آزمونی به آن وصل نشده است.");

        return simpleHeroHtml({
            eyebrow: unit ? (unit.categoryTitle || "مجموعه آزمون‌ها") : "واحدهای همین ترم",
            title: title,
            meta: joinMetaParts([
                unit
                    ? formatValue(stats.courseCount || 0) + " مجموعه"
                    : formatValue(stats.unitCount || 0) + " واحد",
                formatValue(stats.examCount || 0) + " جلسه",
                formatValue(stats.questionCount || 0) + " سوال",
                description
            ]),
            actionsHtml: [
                '<button class="exam-btn exam-btn--ghost" type="button" data-go-home="true">بازگشت به ترم‌ها</button>',
                unit
                    ? '<button class="exam-btn exam-btn--ghost" type="button" data-back-term="' + escapeHtml(term && term.number) + '">بازگشت به ' + escapeHtml(term && term.label || "") + "</button>"
                    : ""
            ].filter(Boolean).join(""),
            secondaryHtml: unit && unit.collectionTitles && unit.collectionTitles.length
                ? '<span class="exams-session-meta">' + escapeHtml(unit.collectionTitles.join(" | ")) + "</span>"
                : ""
        });
    }

    function unitActionConfig(unit) {
        var entryMode = cleanUnitKey(unit && unit.entryMode);
        if (entryMode === "direct") {
            return {
                type: "link",
                href: appendCohortPath(unit.entryHref || "/exams/"),
                actionLabel: unit.entryLabel || "ورود"
            };
        }
        if (entryMode === "collections") {
            return {
                type: "button",
                attrs: ' data-open-unit="' + escapeHtml(unit.key || "") + '"',
                actionLabel: unit.entryLabel || "ورود"
            };
        }
        return {
            type: "static",
            actionLabel: "بدون آزمون"
        };
    }

    function unitCardHtml(unit, index) {
        var stats = unit && unit.stats ? unit.stats : {};
        var note = unit && unit.collectionTitles && unit.collectionTitles.length > 1
            ? unit.collectionTitles.join(" | ")
            : "";
        var action = unitActionConfig(unit);
        var meta = joinMetaParts([
            formatValue(stats.courseCount || 0) + " مجموعه",
            formatValue(stats.examCount || 0) + " جلسه",
            formatValue(stats.questionCount || 0) + " سوال",
            note || compactText(unit.description, "", 64)
        ]);

        return simpleRowHtml({
            type: action.type,
            attrs: action.attrs || "",
            href: action.href || "",
            eyebrow: unit.categoryTitle || "واحد",
            status: unit.statusLabel || "",
            statusMuted: cleanUnitKey(unit && unit.statusKey) === "empty",
            title: unit.title || "",
            meta: meta,
            actionLabel: action.actionLabel
        });
    }

    function categoryCardHtml(category) {
        var units = Array.isArray(category && category.units) ? category.units : [];
        if (!units.length) {
            return simpleGroupHtml({
                eyebrow: "دسته",
                title: category.title || "",
                meta: "هنوز واحدی برای این دسته ثبت نشده است."
            }, "");
        }

        return simpleGroupHtml({
            eyebrow: "دسته",
            title: category.title || "",
            meta: joinMetaParts([
                formatValue(category.stats && category.stats.availableUnitCount || 0) + " واحد فعال",
                formatValue(units.length) + " ردیف"
            ])
        }, units.map(function (unit, index) {
            return unitCardHtml(unit, index);
        }).join(""));
    }

    function termDetailHtml(term) {
        var categories = Array.isArray(term && term.categories) ? term.categories : [];
        return [
            sectionHeroHtml(term, null),
            '<section class="catalog-simple-stack">',
            categories.map(function (category) {
                return categoryCardHtml(category);
            }).join(""),
            "</section>"
        ].join("");
    }

    function courseCardHtml(course, index) {
        var status = statusMeta(course);
        var action = courseAction(course);
        var title = cleanCourseTitle(course.title || "") || String(course.title || "").trim();
        var meta = joinMetaParts([
            formatValue(course.stats && course.stats.examCount || 0) + " جلسه",
            formatValue(course.stats && course.stats.questionCount || 0) + " سوال",
            formatValue(course.stats && course.stats.completedAssessmentCount || 0) + " کارنامه",
            compactText(course.cardDescription || course.heroDescription, "", 64)
        ]);

        return simpleRowHtml({
            type: "link",
            href: action.href,
            eyebrow: course.badge || "مجموعه",
            status: status.label,
            title: title,
            meta: meta,
            actionLabel: action.label
        });
    }

    function unitCollectionsHtml(term, unit) {
        var collections = Array.isArray(unit && unit.collections) ? unit.collections : [];
        return [
            sectionHeroHtml(term, unit),
            collections.length
                ? '<section class="catalog-simple-stack">' + collections.map(function (course, index) {
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
            '<section class="catalog-simple-stack">',
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
