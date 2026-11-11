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
        $("[data-h26-continue-kicker]").textContent = "ادامهٔ آزمون" + (data.updatedAt ? " · " + relativeTime(data.updatedAt) : "");
        $("[data-h26-continue-title]").textContent = heading;
        $("[data-h26-continue-meta]").textContent = (sub ? sub + " · " : "") + modeLabel;
        $("[data-h26-ring-num]").textContent = faNumber(current);
        $("[data-h26-ring-of]").textContent = "از " + faNumber(total) + " سؤال";
        $("[data-h26-stat-answered]").textContent = faNumber(answered);
        $("[data-h26-stat-flagged]").textContent = faNumber(flagged);
        $("[data-h26-stat-left]").textContent = faNumber(total - answered);
        $("[data-h26-continue-cta]").textContent = "ادامه از سؤال " + faNumber(current);
        var ring = $("[data-h26-ring]");
        if (ring) {
            var length = 2 * Math.PI * 51;
            ring.style.strokeDasharray = String(length);
            ring.style.strokeDashoffset = String(length * (1 - answered / total));
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

    function sampleRow(course) {
        var link = document.createElement("a");
        link.className = "h26-sample";
        link.href = course.path;
        var copy = document.createElement("span");
        copy.className = "h26-sample__copy";
        var title = document.createElement("strong");
        title.textContent = String(course.title || "").replace(/\s*\|\s*/g, " · ");
        var meta = document.createElement("small");
        var curriculum = course.curriculum && typeof course.curriculum === "object" ? course.curriculum : {};
        var stats = course.stats || {};
        meta.textContent = [
            curriculum.termLabel || "",
            Number(stats.questionCount) ? faNumber(stats.questionCount) + " سؤال" : (course.badge || "")
        ].filter(Boolean).join(" · ");
        copy.appendChild(title);
        copy.appendChild(meta);
        var status = document.createElement("b");
        var access = course.access || {};
        if (access.hasAccess) {
            status.className = "h26-sample__owned";
            status.textContent = "فعال";
        } else {
            status.textContent = priceLabel(course) || access.unlockLabel || "";
        }
        link.appendChild(copy);
        link.appendChild(status);
        return link;
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
        courses.sort(function (a, b) {
            return String(b.addedAt || "").localeCompare(String(a.addedAt || ""));
        });
        list.textContent = "";
        courses.slice(0, 6).forEach(function (course) {
            list.appendChild(sampleRow(course));
        });
        box.hidden = courses.length === 0;
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
            var courses = collectCourses(catalog && catalog.referenceCatalog, []).filter(function (course) {
                return String(course.title).indexOf("|") !== -1;
            });
            if (!courses.length) {
                return;
            }
            courses.sort(function (a, b) {
                return (b.access && b.access.hasAccess ? 1 : 0) - (a.access && a.access.hasAccess ? 1 : 0);
            });
            var fragment = document.createDocumentFragment();
            courses.slice(0, 6).forEach(function (course, index) {
                fragment.appendChild(coverNode(course, index % COVER_TONES));
            });
            holder.textContent = "";
            holder.appendChild(fragment);
            shelf.hidden = false;
        }).catch(function () {
            if (shelfLoadedFor === cohortKey) {
                shelf.hidden = true;
            }
        });
    }

    /* ---------- class plan: weekly ClassOps digest (same source as the bots) ---------- */

    function digestTime(item) {
        var timing = item && item.timing ? item.timing : {};
        if (timing.localDate) {
            return Date.parse(timing.localDate + "T00:00:00+03:30") || 0;
        }
        return Date.parse(timing.startsAtUtc || timing.dueAtUtc || "") || 0;
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
        var raw = timing.startsAtUtc || timing.dueAtUtc;
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
        meta.textContent = [withDay ? dayLabel(digestTime(item)) : "", timeLabel(item), item.location || ""].filter(Boolean).join(" · ");
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
            }).slice(0, 1);
            var events = (byKey.schedule || []).filter(function (item) {
                return item.itemType !== "schedule_ref";
            });
            var exams = byKey.exams || [];
            var tasks = (byKey.deadlines || []).concat(byKey.outstanding_tasks || [], byKey.critical_ack || []);
            holder.textContent = "";
            if (schedule.length) {
                holder.appendChild(planGroup("برنامهٔ کلاس‌ها", schedule, scheduleRow));
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
