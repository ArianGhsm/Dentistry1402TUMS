/*
 * Home 2026 (Navy & Brass): greeting, continue-exam card, reference-exam shelf
 * and upcoming class items. Everything here is progressive: a failed request
 * only keeps its section hidden; app-home.js still owns quick actions,
 * service flags and the new-exams feed.
 */
(function () {
    "use strict";

    var root = document.body;
    if (!root || !root.classList.contains("home-2026")) {
        return;
    }

    var LAST_EXAM_KEY = "dent1402-last-exam-v1";
    var LAST_EXAM_MAX_AGE_MS = 21 * 24 * 60 * 60 * 1000;
    var COVER_TONES = 6;

    function $(selector) {
        return document.querySelector(selector);
    }

    function fa(value) {
        return String(value).replace(/\d/g, function (d) {
            return "۰۱۲۳۴۵۶۷۸۹".charAt(Number(d));
        });
    }

    function faNumber(value) {
        try {
            return new Intl.NumberFormat("fa-IR").format(value);
        } catch (_error) {
            return fa(value);
        }
    }

    function auth() {
        return window.Dent1402Auth || null;
    }

    function authState() {
        var api = auth();
        return api && typeof api.getState === "function" ? api.getState() || {} : {};
    }

    function activeCohortKey(state) {
        var user = state && state.loggedIn && state.user ? state.user : null;
        if (user && user.cohort && user.cohort.key) {
            return String(user.cohort.key);
        }
        if (user && user.isProsthesisStudent) {
            return "prosthesis-1402";
        }
        var param = new URLSearchParams(window.location.search).get("cohort");
        return param ? String(param) : "";
    }

    function cohortUrl(path, cohortKey) {
        if (!cohortKey) {
            return path;
        }
        return path + (path.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(cohortKey);
    }

    function getJson(url) {
        return fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } }).then(function (response) {
            if (!response.ok) {
                throw new Error("HTTP " + response.status);
            }
            return response.json();
        });
    }

    /* ---------- greeting ---------- */

    function renderToday() {
        var node = $("[data-h26-today]");
        if (!node) {
            return;
        }
        try {
            var parts = {};
            new Intl.DateTimeFormat("fa-IR-u-ca-persian", { weekday: "long", day: "numeric", month: "long", year: "numeric" }).formatToParts(new Date()).forEach(function (part) {
                parts[part.type] = part.value;
            });
            node.textContent = [parts.weekday, parts.day, parts.month, parts.year].filter(Boolean).join(" ");
        } catch (_error) {
            node.textContent = "ورودی ۱۴۰۲";
        }
    }

    function renderHello(state) {
        var node = $("[data-h26-hello]");
        if (!node) {
            return;
        }
        var name = state && state.loggedIn && state.user && state.user.name ? String(state.user.name).trim().split(/\s+/)[0] : "";
        node.textContent = name ? "سلام " + name + "،" : "سلام،";
    }

    function bindSearch() {
        var button = $("[data-h26-search]");
        if (!button) {
            return;
        }
        button.addEventListener("click", function () {
            var trigger = document.querySelector(".shell-search-trigger");
            if (trigger && !trigger.hidden && trigger.offsetParent !== null) {
                trigger.click();
            } else {
                window.location.href = "/exams/";
            }
        });
    }

    /* ---------- continue exam (device-local pointer from exam-quiz.js) ---------- */

    function relativeTime(iso) {
        var then = Date.parse(iso);
        if (!then) {
            return "";
        }
        var minutes = Math.max(1, Math.round((Date.now() - then) / 60000));
        if (minutes < 60) {
            return faNumber(minutes) + " دقیقه پیش";
        }
        var hours = Math.round(minutes / 60);
        if (hours < 24) {
            return faNumber(hours) + " ساعت پیش";
        }
        return faNumber(Math.round(hours / 24)) + " روز پیش";
    }

    function readLastExam() {
        try {
            var data = JSON.parse(window.localStorage.getItem(LAST_EXAM_KEY) || "null");
            if (!data || !data.href || data.finished || !data.total) {
                return null;
            }
            if (Date.now() - Date.parse(data.updatedAt || 0) > LAST_EXAM_MAX_AGE_MS) {
                return null;
            }
            if (String(data.href).charAt(0) !== "/" || String(data.href).indexOf("//") === 0) {
                return null;
            }
            /* Exam restoration is tab-scoped (sessionStorage). Only advertise a
               position that following the link will actually restore. */
            var normalizeCohort = function (key) {
                key = String(key || "").trim();
                return key === "dentistry-1402" ? "" : key;
            };
            if (normalizeCohort(data.cohort) !== normalizeCohort(activeCohortKey(authState()))) {
                return null;
            }
            var draft = data.draftKey ? JSON.parse(window.sessionStorage.getItem(String(data.draftKey)) || "null") : null;
            if (!draft || !draft.started) {
                return null;
            }
            return data;
        } catch (_error) {
            return null;
        }
    }

    function renderContinue() {
        var card = $("[data-h26-continue]");
        var start = $("[data-h26-start]");
        var data = readLastExam();
        if (!card) {
            return;
        }
        if (!data) {
            card.hidden = true;
            if (start) {
                start.hidden = false;
            }
            return;
        }
        var total = Math.max(1, Number(data.total) || 1);
        var current = Math.min(total, Math.max(1, (Number(data.index) || 0) + 1));
        var answered = Math.min(total, Math.max(0, Number(data.answered) || 0));
        var flagged = Math.max(0, Number(data.flagged) || 0);
        var heading = data.courseTitle || data.title || "آزمون";
        var sub = data.courseTitle && data.title && data.title !== data.courseTitle ? data.title : "";
        var modeLabel = data.mode === "learning" ? "حالت یادگیری" : "حالت آزمون";

        card.href = data.href;
        $("[data-h26-continue-title]").textContent = heading;
        $("[data-h26-continue-meta]").textContent = [sub, modeLabel, data.updatedAt ? relativeTime(data.updatedAt) : ""].filter(Boolean).join("، ");
        $("[data-h26-continue-cta]").textContent = "ادامه از سؤال " + faNumber(current);
        $("[data-h26-continue-count]").textContent = faNumber(answered) + " از " + faNumber(total) + " پاسخ‌داده" + (flagged ? "، " + faNumber(flagged) + " نشان‌دار" : "");
        var tape = $("[data-h26-tape]");
        if (tape) {
            /* indicator tape: one cell per question, grouped past 60 */
            var cells = Math.min(total, 60);
            var doneCells = Math.round((answered / total) * cells);
            tape.style.setProperty("--cells", String(cells));
            tape.innerHTML = new Array(cells + 1).join("<i></i>");
            Array.prototype.slice.call(tape.children, 0, doneCells).forEach(function (cell) {
                cell.className = "is-done";
            });
        }
        card.hidden = false;
        if (start) {
            start.hidden = true;
        }
    }

    /* ---------- reference shelf (exams catalog) ---------- */

    function collectCourses(node, out) {
        if (!node || typeof node !== "object") {
            return out;
        }
        if (Array.isArray(node)) {
            node.forEach(function (child) {
                collectCourses(child, out);
            });
            return out;
        }
        if (node.access && node.path && node.title) {
            out.push(node);
            return out;
        }
        Object.keys(node).forEach(function (key) {
            collectCourses(node[key], out);
        });
        return out;
    }

    function priceLabel(course) {
        var text = String(course.amountLabel || "");
        var digits = text.replace(/[^\d۰-۹]/g, "").replace(/[۰-۹]/g, function (d) {
            return String("۰۱۲۳۴۵۶۷۸۹".indexOf(d));
        });
        var amount = Number(digits);
        if (!amount) {
            return "";
        }
        if (text.indexOf("ریال") !== -1) {
            amount = Math.round(amount / 10);
        }
        return faNumber(amount) + " تومان";
    }

    function coverNode(course, tone) {
        var parts = String(course.title).split("|").map(function (part) {
            return part.trim();
        });
        var words = function (text) {
            return text ? text.split(/\s+/).length : 99;
        };
        /* Titles are "reference | subject" or "course | reference": the
           reference is the shorter part. */
        var swap = parts.length > 1 && words(parts[1]) < words(parts[0]);
        var name = swap ? parts[1] : parts[0];
        var subject = (swap ? parts[0] : parts[1]) || "";
        var link = document.createElement("a");
        link.className = "h26-cover";
        link.dataset.tone = String(tone);
        link.href = course.path;

        var small = document.createElement("small");
        small.textContent = subject || "آزمون مرجع";
        var title = document.createElement("h4");
        title.textContent = name;
        if (/[A-Za-z]/.test(name)) {
            title.className = "is-latin";
            title.dir = "ltr";
        }
        var foot = document.createElement("span");
        foot.className = "h26-cover__foot";
        var badge = document.createElement("span");
        badge.textContent = course.badge || "";
        var status = document.createElement("b");
        var access = course.access || {};
        if (access.hasAccess) {
            status.className = "h26-cover__owned";
            status.textContent = "فعال";
        } else {
            status.textContent = priceLabel(course) || access.unlockLabel || "";
        }
        foot.appendChild(badge);
        foot.appendChild(status);
        link.appendChild(small);
        link.appendChild(title);
        link.appendChild(foot);
        return link;
    }

    var shelfLoadedFor = null;
    /* ---------- sample-question sets (the site's main product) ---------- */

    var SAMPLE_PATTERN = /نمونه|سوالات|سؤالات/;

    /* «آزمون نمونه سوالات پایان‌ترم فوریت پزشکی» → { name: «فوریت پزشکی», kind: «پایان‌ترم» }.
       Inside a sample-question list the shared prefix is noise; the course name is the point. */
    function splitSampleTitle(raw) {
        var text = String(raw || "").replace(/\s*\|\s*/g, "، ").replace(/\u200c/g, "\u200c").trim();
        var rest = text.replace(/^آزمون\s+/, "").replace(/^نمونه[\s\u200c]*(سؤالات|سوالات|سؤال|سوال)\s*/, "");
        var kind = "";
        var match = rest.match(/^(میان[\s\u200c]*ترم|پایان[\s\u200c]*ترم)\s*/);
        if (match) {
            kind = /میان/.test(match[1]) ? "میان‌ترم" : "پایان‌ترم";
            rest = rest.slice(match[0].length);
        }
        rest = rest.trim();
        return { name: rest || text, kind: kind };
    }

    function sampleRow(course, isFresh) {
        /* one row per set: what it is, then a direct way to buy (or start) */
        var row = document.createElement("div");
        row.className = "h26-sample";
        var copy = document.createElement("a");
        copy.className = "h26-sample__copy";
        copy.href = course.path;
        var split = splitSampleTitle(course.title);
        var title = document.createElement("strong");
        title.textContent = split.name;
        copy.title = String(course.title || "");
        if (isFresh) {
            var fresh = document.createElement("span");
            fresh.className = "h26-sample__fresh";
            fresh.textContent = "تازه";
            title.appendChild(fresh);
        }
        var meta = document.createElement("small");
        var curriculum = course.curriculum && typeof course.curriculum === "object" ? course.curriculum : {};
        var stats = course.stats || {};
        meta.textContent = [
            split.kind,
            curriculum.termLabel || "",
            Number(stats.questionCount) ? faNumber(stats.questionCount) + " سؤال" : (course.badge || ""),
            course.access && course.access.hasAccess ? "" : priceLabel(course)
        ].filter(Boolean).join("، ");
        copy.appendChild(title);
        copy.appendChild(meta);
        var access = course.access || {};
        var action = document.createElement("a");
        if (access.hasAccess) {
            action.className = "h26-sample__go is-owned";
            action.href = course.path;
            action.textContent = "شروع";
        } else {
            action.className = "h26-sample__go";
            action.href = course.paymentPath || course.path;
            action.textContent = "خرید";
            action.setAttribute("aria-label", "خرید " + title.textContent);
        }
        row.appendChild(copy);
        row.appendChild(action);
        return row;
    }

    function renderSamples(catalog) {
        var box = $("[data-h26-samples]");
        var list = $("[data-h26-samples-list]");
        if (!box || !list) {
            return;
        }
        var seen = {};
        var courses = (catalog && Array.isArray(catalog.courses) ? catalog.courses : []).filter(function (course) {
            var key = String(course && (course.slug || course.path) || "");
            if (!key || seen[key] || !course.path || !SAMPLE_PATTERN.test(String(course.title || ""))) {
                return false;
            }
            seen[key] = true;
            return true;
        });
        /* the catalog API already lists courses newest first */
        var freshSlugs = {};
        (catalog && Array.isArray(catalog.homeActiveCourses) ? catalog.homeActiveCourses : []).forEach(function (course) {
            if (course && course.slug) freshSlugs[course.slug] = true;
        });
        renderFact(courses);
        /* the first screen offers one thing: continue an exam, else the newest set */
        var continuing = !!($("[data-h26-continue]") && !$("[data-h26-continue]").hidden);
        var featured = continuing ? null : courses[0] || null;
        renderFeature(featured);
        var rest = featured ? courses.slice(1) : courses;
        list.textContent = "";
        rest.slice(0, 6).forEach(function (course, index) {
            list.appendChild(sampleRow(course, !!freshSlugs[course.slug] || (!featured && index === 0)));
        });
        box.hidden = rest.length === 0;
    }

    function renderFact(courses) {
        var fact = $("[data-h26-fact]");
        if (!fact || !courses.length) {
            return;
        }
        /* real prices only: «each» when every set costs the same, else «from» the lowest */
        var amounts = [];
        courses.forEach(function (course) {
            var label = priceLabel(course);
            var value = Number(String(label).replace(/[^\d۰-۹]/g, "").replace(/[۰-۹]/g, function (d) { return String("۰۱۲۳۴۵۶۷۸۹".indexOf(d)); }));
            if (value) amounts.push({ value: value, label: label });
        });
        var priceText = "";
        if (amounts.length) {
            var lowest = amounts.reduce(function (min, item) { return item.value < min.value ? item : min; }, amounts[0]);
            var uniform = amounts.length === courses.length && amounts.every(function (item) { return item.value === lowest.value; });
            priceText = uniform ? "، هر کدام " + lowest.label + " و برای همیشه." : "، از " + lowest.label + "، با یک بار پرداخت برای همیشه.";
        }
        fact.textContent = faNumber(courses.length) + " مجموعه نمونه‌سؤال میان‌ترم و پایان‌ترم"
            + (priceText || "، با یک بار پرداخت برای همیشه.");
    }

    function renderFeature(course) {
        var card = $("[data-h26-feature]");
        if (!card) {
            return;
        }
        if (!course) {
            card.hidden = true;
            card.textContent = "";
            return;
        }
        var access = course.access || {};
        var stats = course.stats || {};
        var curriculum = course.curriculum && typeof course.curriculum === "object" ? course.curriculum : {};
        var questions = Number(stats.questionCount) || 0;
        card.textContent = "";

        var title = document.createElement("h2");
        title.className = "h26-feature__title";
        var titleLink = document.createElement("a");
        titleLink.href = course.path;
        var split = splitSampleTitle(course.title);
        titleLink.textContent = split.name;
        titleLink.title = String(course.title || "");
        title.appendChild(titleLink);
        var fresh = document.createElement("span");
        fresh.className = "h26-sample__fresh";
        fresh.textContent = "تازه";
        title.appendChild(fresh);

        var meta = document.createElement("p");
        meta.className = "h26-feature__meta";
        meta.textContent = ["نمونه‌سؤال " + (split.kind || "امتحان"), curriculum.termLabel || "", questions ? faNumber(questions) + " سؤال با پاسخ تشریحی" : (course.badge || "")]
            .filter(Boolean).join("، ");

        /* the set's size as indicator tape: one raw cell per question (grouped past 60) */
        var tape = document.createElement("div");
        tape.className = "h26-tape h26-tape--feature";
        tape.setAttribute("aria-hidden", "true");
        var cells = Math.max(1, Math.min(questions || 20, 60));
        tape.style.setProperty("--cells", String(cells));
        tape.innerHTML = new Array(cells + 1).join("<i></i>");

        var actions = document.createElement("div");
        actions.className = "h26-feature__actions";
        var primary = document.createElement("a");
        primary.className = "h26-feature__buy";
        if (access.hasAccess) {
            primary.href = course.path;
            primary.textContent = "شروع آزمون";
        } else {
            primary.href = course.paymentPath || course.path;
            primary.textContent = "خرید " + (priceLabel(course) || "مجموعه");
        }
        actions.appendChild(primary);
        var more = document.createElement("a");
        more.className = "h26-feature__more";
        more.href = course.path;
        more.textContent = "دیدن جلسه‌ها و سؤال‌ها";
        actions.appendChild(more);

        card.appendChild(title);
        card.appendChild(meta);
        card.appendChild(tape);
        card.appendChild(actions);
        card.hidden = false;
    }

    function unitCourses(unit) {
        var list = Array.isArray(unit.collections) ? unit.collections : [];
        return list.filter(function (course) { return course && course.path; });
    }

    function termsWithCourses(catalog) {
        var terms = catalog && catalog.curriculum && Array.isArray(catalog.curriculum.terms) ? catalog.curriculum.terms : [];
        var seen = {};
        return terms.map(function (term) {
            var units = [];
            (Array.isArray(term.categories) ? term.categories : []).forEach(function (category) {
                (Array.isArray(category.units) ? category.units : []).forEach(function (unit) {
                    if (!unit || !unit.stats || !unit.stats.examCount || !unitCourses(unit).length) {
                        return;
                    }
                    var key = String(term.number) + ":" + String(unit.title || unit.key);
                    if (seen[key]) {
                        return;
                    }
                    seen[key] = true;
                    units.push(unit);
                });
            });
            return { number: Number(term.number) || 0, label: String(term.label || ""), units: units };
        }).filter(function (term) { return term.units.length; });
    }

    function preferredTermNumber(catalog, terms) {
        /* open on the term of the newest sample-question set when there is one */
        var courses = catalog && Array.isArray(catalog.courses) ? catalog.courses.slice() : [];
        courses.sort(function (a, b) { return String(b.addedAt || "").localeCompare(String(a.addedAt || "")); });
        for (var i = 0; i < courses.length; i++) {
            if (!SAMPLE_PATTERN.test(String(courses[i].title || ""))) continue;
            for (var t = 0; t < terms.length; t++) {
                for (var u = 0; u < terms[t].units.length; u++) {
                    if (unitCourses(terms[t].units[u]).some(function (course) { return course.slug === courses[i].slug; })) {
                        return terms[t].number;
                    }
                }
            }
        }
        return terms[0].number;
    }

    function unitRow(unit) {
        var courses = unitCourses(unit);
        var href = unit.entryMode === "direct" && unit.entryHref ? unit.entryHref : courses[0].path;
        if (courses.length > 1) {
            href = "/exams/?mode=term&term=" + encodeURIComponent(String(unit.termNumber || "")) + "&unit=" + encodeURIComponent(String(unit.key || ""));
        }
        var owned = courses.every(function (course) { return course.access && course.access.hasAccess; });
        var price = courses.length === 1 ? priceLabel(courses[0]) : "";
        var row = document.createElement("a");
        row.className = "h26-unit";
        row.href = href;
        var copy = document.createElement("span");
        copy.className = "h26-unit__copy";
        var title = document.createElement("strong");
        title.textContent = String(unit.title || "");
        var meta = document.createElement("small");
        meta.textContent = [
            faNumber(unit.stats.questionCount) + " سؤال",
            courses.length > 1 ? faNumber(courses.length) + " مجموعه" : ""
        ].filter(Boolean).join("، ");
        copy.appendChild(title);
        copy.appendChild(meta);
        var end = document.createElement("span");
        end.className = "h26-unit__end" + (owned ? " is-owned" : "");
        end.textContent = owned ? "فعال" : (price || "مشاهده");
        row.appendChild(copy);
        row.appendChild(end);
        return row;
    }

    function renderTermTabs(terms, activeNumber, holder) {
        var tabs = $("[data-h26-term-tabs]");
        var show = function (number) {
            var term = terms.filter(function (item) { return item.number === number; })[0] || terms[0];
            holder.textContent = "";
            term.units.forEach(function (unit) { holder.appendChild(unitRow(unit)); });
            if (tabs) {
                Array.prototype.forEach.call(tabs.children, function (button) {
                    var on = Number(button.getAttribute("data-term")) === term.number;
                    button.setAttribute("aria-selected", on ? "true" : "false");
                    button.classList.toggle("is-on", on);
                });
            }
        };
        if (tabs) {
            tabs.textContent = "";
            terms.forEach(function (term) {
                var button = document.createElement("button");
                button.type = "button";
                button.setAttribute("role", "tab");
                button.setAttribute("data-term", String(term.number));
                button.textContent = term.label;
                button.addEventListener("click", function () { show(term.number); });
                tabs.appendChild(button);
            });
        }
        show(activeNumber);
    }

    function renderShelf(state) {
        var shelf = $("[data-h26-shelf]");
        var holder = $("[data-h26-covers]");
        if (!shelf || !holder) {
            return;
        }
        var status = state && state.status ? String(state.status) : "";
        if (!state || (!state.loggedIn && /restor|logging|pending|loading|boot/.test(status))) {
            return;
        }
        var cohortKey = activeCohortKey(state);
        if (shelfLoadedFor === cohortKey) {
            return;
        }
        shelfLoadedFor = cohortKey;
        getJson(cohortUrl("/api/exams_api.php?action=catalog", cohortKey)).then(function (payload) {
            if (shelfLoadedFor !== cohortKey) {
                return;
            }
            var catalog = payload && payload.catalog ? payload.catalog : null;
            renderSamples(catalog);
            /* Reference-by-reference shelves are retired from home: courses are
               listed term by term, the way the faculty teaches them. */
            var terms = termsWithCourses(catalog);
            if (!terms.length) {
                return;
            }
            var preferred = preferredTermNumber(catalog, terms);
            renderTermTabs(terms, preferred, holder);
            shelf.hidden = false;
        }).catch(function () {
            if (shelfLoadedFor === cohortKey) {
                shelf.hidden = true;
            }
        });
    }

    /* ---------- class plan: weekly ClassOps digest (same source as the bots) ---------- */

    var DEADLINE_TYPES = { task: true, requirement: true, deadline: true };

    function digestMoment(item) {
        /* Deadlines show their due time; events show their start. */
        var timing = item && item.timing ? item.timing : {};
        return DEADLINE_TYPES[item && item.itemType]
            ? (timing.dueAtUtc || timing.startsAtUtc || "")
            : (timing.startsAtUtc || timing.dueAtUtc || "");
    }

    function digestTime(item) {
        var timing = item && item.timing ? item.timing : {};
        if (timing.localDate) {
            return Date.parse(timing.localDate + "T00:00:00+03:30") || 0;
        }
        return Date.parse(digestMoment(item)) || 0;
    }

    function dayLabel(time) {
        if (!time) {
            return "";
        }
        try {
            var parts = {};
            new Intl.DateTimeFormat("fa-IR-u-ca-persian", { weekday: "long", day: "numeric", month: "long", timeZone: "Asia/Tehran" })
                .formatToParts(new Date(time)).forEach(function (part) {
                    parts[part.type] = part.value;
                });
            return [parts.weekday, parts.day, parts.month].filter(Boolean).join(" ");
        } catch (_error) {
            return "";
        }
    }

    function timeLabel(item) {
        var timing = item && item.timing ? item.timing : {};
        var raw = digestMoment(item);
        if (!raw || timing.allDay) {
            return "";
        }
        try {
            return new Intl.DateTimeFormat("fa-IR", { hour: "2-digit", minute: "2-digit", timeZone: "Asia/Tehran" }).format(new Date(raw));
        } catch (_error) {
            return "";
        }
    }

    function planGroup(title, items, render) {
        var group = document.createElement("div");
        group.className = "h26-plan__group";
        var heading = document.createElement("h4");
        heading.textContent = title;
        group.appendChild(heading);
        items.forEach(function (item) {
            group.appendChild(render(item));
        });
        return group;
    }

    function planRow(item, withDay) {
        var row = document.createElement("div");
        row.className = "h26-plan__row";
        var title = document.createElement("strong");
        title.textContent = item.title || "";
        var meta = document.createElement("small");
        meta.textContent = [withDay ? dayLabel(digestTime(item)) : "", timeLabel(item), item.location || ""].filter(Boolean).join("، ");
        row.appendChild(title);
        if (meta.textContent) {
            row.appendChild(meta);
        }
        return row;
    }

    function scheduleRow(item) {
        var row = document.createElement("div");
        row.className = "h26-plan__row h26-plan__row--schedule";
        var day = document.createElement("strong");
        day.textContent = dayLabel(digestTime(item)) || (item.title || "");
        var body = document.createElement("p");
        body.textContent = String(item.description || "").trim();
        row.appendChild(day);
        if (body.textContent) {
            row.appendChild(body);
        }
        return row;
    }

    var planLoadedFor = "";
    function renderPlan(state) {
        var box = $("[data-h26-week]");
        var holder = $("[data-h26-plan]");
        if (!box || !holder) {
            return;
        }
        var user = state && state.loggedIn && state.user ? state.user : null;
        var key = user ? String(user.id || user.studentNumber || user.name || "user") : "";
        if (!user || user.isExternalExamUser) {
            box.hidden = true;
            planLoadedFor = "";
            return;
        }
        if (planLoadedFor === key) {
            return;
        }
        planLoadedFor = key;
        getJson("/api/classops_api.php?action=weekly-digest").then(function (payload) {
            var digest = payload && payload.digest ? payload.digest : null;
            var sections = digest && Array.isArray(digest.sections) ? digest.sections : [];
            var byKey = {};
            sections.forEach(function (section) {
                byKey[section.key] = Array.isArray(section.items) ? section.items : [];
            });
            var schedule = (byKey.schedule || []).filter(function (item) {
                return item.itemType === "schedule_ref" && String(item.description || "").trim();
            });
            var events = (byKey.schedule || []).filter(function (item) {
                return item.itemType !== "schedule_ref";
            });
            var exams = byKey.exams || [];
            var tasks = (byKey.deadlines || []).concat(byKey.outstanding_tasks || [], byKey.critical_ack || []);
            holder.textContent = "";
            if (schedule.length) {
                var scheduleGroup = planGroup("برنامهٔ کلاس‌ها", schedule.slice(0, 1), scheduleRow);
                if (schedule.length > 1) {
                    /* The rest of the week stays one tap away, never discarded. */
                    var more = document.createElement("details");
                    more.className = "h26-plan__more";
                    var summary = document.createElement("summary");
                    summary.textContent = "برنامهٔ " + faNumber(schedule.length - 1) + " روز بعد";
                    more.appendChild(summary);
                    schedule.slice(1).forEach(function (item) {
                        more.appendChild(scheduleRow(item));
                    });
                    scheduleGroup.appendChild(more);
                }
                holder.appendChild(scheduleGroup);
            }
            if (exams.length || events.length) {
                holder.appendChild(planGroup("امتحان‌ها و رویدادها", exams.concat(events).slice(0, 4), function (item) {
                    return planRow(item, true);
                }));
            }
            if (tasks.length) {
                holder.appendChild(planGroup("تکلیف‌ها و مهلت‌ها", tasks.slice(0, 4), function (item) {
                    return planRow(item, true);
                }));
            }
            box.hidden = holder.childElementCount === 0;
        }).catch(function () {
            box.hidden = true;
        });
    }

    function syncAuth(state) {
        renderHello(state);
        renderContinue();
        renderShelf(state);
        renderPlan(state);
    }

    function init() {
        renderToday();
        bindSearch();
        renderContinue();
        syncAuth(authState());
        var api = auth();
        if (api && typeof api.onChange === "function") {
            api.onChange(function (state) {
                syncAuth(state || authState());
            });
        }
        window.addEventListener("pageshow", renderContinue);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
