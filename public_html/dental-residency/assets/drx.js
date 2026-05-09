(function () {
    "use strict";

    var DATA = window.DRX_DEMO_DATA || { subjects: [], questions: [] };
    var app = document.getElementById("drx-app");
    var view = document.body ? document.body.dataset.drxView : "home";
    var timerHandle = null;
    var storagePrefix = "drx:";

    var routes = [
        { view: "home", href: "/dental-residency/", label: "نمای کلی" },
        { view: "qbank", href: "/dental-residency/qbank/", label: "بانک سوالات" },
        { view: "practice", href: "/dental-residency/practice/", label: "تمرین" },
        { view: "dashboard", href: "/dental-residency/dashboard/", label: "تحلیل" },
        { view: "bookmarks", href: "/dental-residency/bookmarks/", label: "نشان‌شده" },
        { view: "wrongAnswers", href: "/dental-residency/wrong-answers/", label: "پاسخ‌های غلط" }
    ];

    function storageKey(key) {
        return storagePrefix + key;
    }

    function readJson(key, fallback) {
        try {
            var raw = window.localStorage.getItem(storageKey(key));
            return raw ? JSON.parse(raw) : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            window.localStorage.setItem(storageKey(key), JSON.stringify(value));
        } catch (error) {
            showToast("ذخیره محلی مرورگر در دسترس نیست.");
        }
    }

    function toFa(value) {
        return String(value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function formatPercent(value) {
        return toFa(Math.round(value)) + "٪";
    }

    function formatTime(seconds) {
        seconds = Math.max(0, Math.floor(Number(seconds) || 0));
        var minutes = Math.floor(seconds / 60);
        var rest = seconds % 60;
        return toFa(String(minutes).padStart(2, "0") + ":" + String(rest).padStart(2, "0"));
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function subjectById(id) {
        return DATA.subjects.find(function (subject) {
            return subject.id === id;
        }) || { id: id, title: "نامشخص", shortTitle: "نامشخص", color: "#5b4df5" };
    }

    function questionById(id) {
        return DATA.questions.find(function (question) {
            return question.id === id;
        }) || null;
    }

    function difficultyLabel(value) {
        if (value === "easy") {
            return "آسان";
        }
        if (value === "hard") {
            return "چالشی";
        }
        return "متوسط";
    }

    function statusLabel(value) {
        if (value === "reviewed") {
            return "مرور شده";
        }
        if (value === "weak") {
            return "نیازمند مرور";
        }
        return "جدید";
    }

    function navMarkup(activeView) {
        return routes.map(function (item) {
            return '<a class="drx-nav__link' + (item.view === activeView ? " is-active" : "") + '" href="' + item.href + '">' + item.label + "</a>";
        }).join("");
    }

    function layout(activeView, content) {
        app.innerHTML = [
            '<header class="drx-topbar">',
            '  <a class="drx-brand" href="/dental-residency/">',
            '    <span class="drx-brand__mark" aria-hidden="true">DR</span>',
            '    <span class="drx-brand__copy">',
            '      <span class="drx-brand__title">رزیدنتی دندانپزشکی</span>',
            '      <span class="drx-brand__subtitle">MVP آزمایشی با سوالات نمونه</span>',
            '    </span>',
            "  </a>",
            '  <nav class="drx-nav" aria-label="ناوبری رزیدنتی دندانپزشکی">' + navMarkup(activeView) + "</nav>",
            "</header>",
            '<main class="drx-main">' + content + "</main>",
            '<p class="drx-footer-note">همه سوالات این نسخه ساختگی و فقط برای نمایش قابلیت‌ها هستند.</p>'
        ].join("");
    }

    function statCard(label, value, hint) {
        return [
            '<article class="drx-card drx-stat">',
            '  <span class="drx-muted">' + label + "</span>",
            '  <strong class="drx-stat__value">' + value + "</strong>",
            '  <span class="drx-muted">' + hint + "</span>",
            "</article>"
        ].join("");
    }

    function showToast(message) {
        var previous = document.querySelector(".drx-toast");
        if (previous) {
            previous.remove();
        }
        var toast = document.createElement("div");
        toast.className = "drx-toast";
        toast.setAttribute("role", "status");
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(function () {
            toast.remove();
        }, 2600);
    }

    function bookmarks() {
        return readJson("bookmarks", []);
    }

    function setBookmarks(ids) {
        writeJson("bookmarks", Array.from(new Set(ids)));
    }

    function isBookmarked(id) {
        return bookmarks().indexOf(id) !== -1;
    }

    function toggleBookmark(id) {
        var ids = bookmarks();
        if (ids.indexOf(id) === -1) {
            ids.push(id);
            setBookmarks(ids);
            showToast("سوال به فهرست نشان‌شده اضافه شد.");
            return true;
        }
        setBookmarks(ids.filter(function (item) {
            return item !== id;
        }));
        showToast("سوال از فهرست نشان‌شده حذف شد.");
        return false;
    }

    function wrongNotebook() {
        return readJson("wrongAnswers", {});
    }

    function writeWrongNotebook(value) {
        writeJson("wrongAnswers", value);
    }

    function addWrongQuestion(id) {
        var notebook = wrongNotebook();
        var current = notebook[id] || { questionId: id, count: 0, status: "open" };
        current.count = Number(current.count || 0) + 1;
        current.lastAt = new Date().toISOString();
        current.status = "open";
        notebook[id] = current;
        writeWrongNotebook(notebook);
        showToast("به دفترچه پاسخ‌های غلط اضافه شد.");
    }

    function sessions() {
        return readJson("sessions", []);
    }

    function writeSessions(value) {
        writeJson("sessions", value.slice(0, 20));
    }

    function activeSession() {
        return readJson("activeSession", null);
    }

    function writeActiveSession(session) {
        writeJson("activeSession", session);
    }

    function latestCompletedSession() {
        return readJson("latestCompletedSession", null);
    }

    function answeredCount(session) {
        return Object.keys(session.answers || {}).filter(function (id) {
            return session.answers[id] !== null && session.answers[id] !== undefined;
        }).length;
    }

    function computeResult(session) {
        var correct = 0;
        var wrong = 0;
        var unanswered = 0;
        (session.questionIds || []).forEach(function (id) {
            var question = questionById(id);
            var answer = session.answers ? session.answers[id] : undefined;
            if (!question || answer === undefined || answer === null) {
                unanswered += 1;
            } else if (Number(answer) === question.correctIndex) {
                correct += 1;
            } else {
                wrong += 1;
            }
        });
        return {
            total: (session.questionIds || []).length,
            correct: correct,
            wrong: wrong,
            unanswered: unanswered,
            accuracy: session.questionIds && session.questionIds.length ? (correct / session.questionIds.length) * 100 : 0,
            spentSeconds: Math.max(1, Math.floor(((session.finishedAt || Date.now()) - session.startedAt) / 1000))
        };
    }

    function subjectStats() {
        var completed = sessions();
        return DATA.subjects.map(function (subject) {
            var subjectQuestions = DATA.questions.filter(function (question) {
                return question.subject === subject.id;
            });
            var seen = 0;
            var correct = 0;
            completed.forEach(function (session) {
                (session.questionIds || []).forEach(function (id) {
                    var question = questionById(id);
                    if (!question || question.subject !== subject.id) {
                        return;
                    }
                    var answer = session.answers ? session.answers[id] : undefined;
                    if (answer !== undefined && answer !== null) {
                        seen += 1;
                        if (Number(answer) === question.correctIndex) {
                            correct += 1;
                        }
                    }
                });
            });
            var progress = subjectQuestions.length ? Math.min(100, (seen / subjectQuestions.length) * 100) : 0;
            var accuracy = seen ? (correct / seen) * 100 : 0;
            return {
                subject: subject,
                total: subjectQuestions.length,
                seen: seen,
                correct: correct,
                progress: progress,
                accuracy: accuracy
            };
        });
    }

    function subjectCard(stat, actionHref) {
        var subject = stat.subject || subjectById(stat.id);
        return [
            '<article class="drx-card drx-subject-card" style="--subject-color:' + subject.color + '">',
            '  <div class="drx-subject-card__top">',
            '    <div>',
            '      <span class="drx-chip drx-chip--cyan">' + toFa(stat.total || 0) + " سوال نمونه</span>",
            '      <h3>' + subject.title + "</h3>",
            "    </div>",
            '    <span class="drx-subject-mark" aria-hidden="true"></span>',
            "  </div>",
            '  <div class="drx-progress" aria-label="پیشرفت"><span style="--drx-progress-value:' + Math.round(stat.progress || 0) + '%"></span></div>',
            '  <p class="drx-muted">پیشرفت: ' + formatPercent(stat.progress || 0) + " · دقت: " + formatPercent(stat.accuracy || 0) + "</p>",
            actionHref ? '<a class="drx-btn drx-btn--ghost" href="' + actionHref + '">مشاهده سوالات</a>' : "",
            "</article>"
        ].join("");
    }

    function questionCard(question, includeAction) {
        var subject = subjectById(question.subject);
        var marked = isBookmarked(question.id);
        return [
            '<article class="drx-card" data-question-card="' + question.id + '">',
            '  <div class="drx-card__head">',
            '    <div>',
            '      <span class="drx-chip">' + subject.shortTitle + "</span> ",
            '      <span class="drx-chip drx-chip--cyan">' + difficultyLabel(question.difficulty) + "</span>",
            "      <h3>" + question.topic + "</h3>",
            "    </div>",
            '    <button class="drx-btn drx-btn--ghost" type="button" data-action="toggle-bookmark" data-id="' + question.id + '">' + (marked ? "حذف نشان" : "نشان کردن") + "</button>",
            "  </div>",
            '  <p class="drx-muted">' + question.yearLabel + " · " + statusLabel(question.status) + "</p>",
            "  <p>" + escapeHtml(question.stem) + "</p>",
            '  <div class="drx-filter-row">' + question.tags.map(function (tag) {
                return '<span class="drx-chip">' + escapeHtml(tag) + "</span>";
            }).join("") + "</div>",
            includeAction ? '<div class="drx-actions"><button class="drx-btn drx-btn--secondary" type="button" data-action="start-single" data-id="' + question.id + '">تمرین همین سوال</button></div>' : "",
            "</article>"
        ].join("");
    }

    function renderHome() {
        var stats = subjectStats();
        var completed = sessions();
        var latest = completed[0];
        var latestText = latest ? "آخرین جلسه: " + toFa(computeResult(latest).correct) + " پاسخ درست از " + toFa(latest.questionIds.length) : "هنوز جلسه‌ای ثبت نشده است.";
        layout("home", [
            '<section class="drx-hero">',
            '  <div class="drx-hero__copy">',
            '    <span class="drx-kicker">نسخه آزمایشی مستقل</span>',
            "    <h1>بانک سوالات رزیدنتی دندانپزشکی برای تمرین هدفمند</h1>",
            "    <p>یک محیط جدا از سایت اصلی برای مرور درس‌ها، ساخت آزمون شبیه‌ساز، ذخیره سوالات و تحلیل عملکرد. محتوای فعلی فقط نمونه ساختگی است.</p>",
            '    <div class="drx-actions">',
            '      <a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">شروع تمرین</a>',
            '      <button class="drx-btn drx-btn--secondary" type="button" data-action="create-mock">ساخت آزمون شبیه‌ساز</button>',
            '      <a class="drx-btn drx-btn--ghost" href="/dental-residency/qbank/">مشاهده بانک سوالات</a>',
            "    </div>",
            "  </div>",
            '  <aside class="drx-panel">',
            "    <h2>تمرکز امروز</h2>",
            '    <p class="drx-muted">از درس‌های ضعیف شروع کن و بعد یک آزمون کوتاه بساز.</p>',
            '    <div class="drx-grid">',
            '      <span class="drx-chip drx-chip--danger">دفترچه غلط‌ها: ' + toFa(Object.keys(wrongNotebook()).length) + "</span>",
            '      <span class="drx-chip drx-chip--cyan">سوالات نشان‌شده: ' + toFa(bookmarks().length) + "</span>",
            '      <span class="drx-chip drx-chip--success">آمادگی شبیه‌ساز: ' + formatPercent(completed.length ? 58 + Math.min(32, completed.length * 6) : 42) + "</span>",
            "    </div>",
            "  </aside>",
            "</section>",
            '<section class="drx-grid drx-grid--stats" aria-label="آمار سریع">',
            statCard("سوالات نمونه", toFa(DATA.questions.length), "همه ساختگی و demo"),
            statCard("درس‌ها", toFa(DATA.subjects.length), "قابل توسعه برای محتوای مجاز"),
            statCard("جلسه‌های ثبت‌شده", toFa(completed.length), "ذخیره محلی مرورگر"),
            statCard("دقت کلی", completed.length ? formatPercent(computeResult(latest).accuracy) : "۰٪", latestText),
            "</section>",
            '<section class="drx-section">',
            '  <div class="drx-section-head"><h2 class="drx-section-title">پیشرفت درس‌ها</h2><a class="drx-btn drx-btn--ghost" href="/dental-residency/dashboard/">تحلیل کامل</a></div>',
            '  <div class="drx-grid drx-grid--subjects">' + stats.map(function (stat) {
                return subjectCard(stat, "/dental-residency/qbank/?subject=" + stat.subject.id);
            }).join("") + "</div>",
            "</section>",
            '<section class="drx-section drx-panel"><h2>آخرین فعالیت</h2><p class="drx-muted">' + latestText + "</p></section>"
        ].join(""));
        bindCommonActions();
    }

    function renderQbank() {
        var subjectOptions = DATA.subjects.map(function (subject) {
            return '<option value="' + subject.id + '">' + subject.title + "</option>";
        }).join("");
        layout("qbank", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">بانک سوالات</span>',
            "  <h1 class=\"drx-section-title\">مرور سوالات نمونه رزیدنتی دندانپزشکی</h1>",
            '  <p class="drx-muted">فیلترها روی دیتاست ساختگی اجرا می‌شوند و برای import محتوای مجاز در آینده آماده‌اند.</p>',
            '  <div class="drx-toolbar">',
            '    <input class="drx-field" id="drx-search" type="search" placeholder="جستجو در متن سوال، مبحث یا برچسب">',
            '    <a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">ساخت جلسه تمرین</a>',
            "  </div>",
            '  <div class="drx-form-grid">',
            '    <label class="drx-form-group"><span>درس</span><select class="drx-select" id="drx-subject-filter"><option value="">همه درس‌ها</option>' + subjectOptions + "</select></label>",
            '    <label class="drx-form-group"><span>سختی</span><select class="drx-select" id="drx-difficulty-filter"><option value="">همه سطح‌ها</option><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">چالشی</option></select></label>',
            '    <label class="drx-form-group"><span>سال/منبع</span><select class="drx-select" id="drx-year-filter"><option value="">همه سال‌ها</option></select></label>',
            '    <label class="drx-form-group"><span>وضعیت</span><select class="drx-select" id="drx-status-filter"><option value="">همه وضعیت‌ها</option><option value="new">جدید</option><option value="reviewed">مرور شده</option><option value="weak">نیازمند مرور</option></select></label>',
            "  </div>",
            "</section>",
            '<section class="drx-section">',
            '  <div class="drx-section-head"><h2 class="drx-section-title">درس‌ها و دسته‌ها</h2><span class="drx-muted">' + toFa(DATA.subjects.length) + " درس</span></div>",
            '  <div class="drx-grid drx-grid--subjects">' + subjectStats().map(function (stat) {
                return subjectCard(stat, "/dental-residency/qbank/?subject=" + stat.subject.id);
            }).join("") + "</div>",
            "</section>",
            '<section class="drx-section">',
            '  <div class="drx-section-head"><h2 class="drx-section-title">سوالات</h2><span id="drx-result-count" class="drx-chip"></span></div>',
            '  <div id="drx-question-list" class="drx-grid drx-grid--cards"></div>',
            "</section>"
        ].join(""));
        bindQbank();
        bindCommonActions();
    }

    function bindQbank() {
        var params = new URLSearchParams(window.location.search);
        var years = Array.from(new Set(DATA.questions.map(function (question) {
            return question.yearLabel;
        })));
        var yearFilter = document.getElementById("drx-year-filter");
        yearFilter.innerHTML += years.map(function (year) {
            return '<option value="' + escapeHtml(year) + '">' + escapeHtml(year) + "</option>";
        }).join("");

        var subjectFilter = document.getElementById("drx-subject-filter");
        subjectFilter.value = params.get("subject") || "";

        ["drx-search", "drx-subject-filter", "drx-difficulty-filter", "drx-year-filter", "drx-status-filter"].forEach(function (id) {
            document.getElementById(id).addEventListener("input", renderQuestionList);
        });
        renderQuestionList();
    }

    function renderQuestionList() {
        var search = document.getElementById("drx-search").value.trim().toLowerCase();
        var subject = document.getElementById("drx-subject-filter").value;
        var difficulty = document.getElementById("drx-difficulty-filter").value;
        var year = document.getElementById("drx-year-filter").value;
        var status = document.getElementById("drx-status-filter").value;
        var list = DATA.questions.filter(function (question) {
            var haystack = [question.stem, question.topic, question.yearLabel, question.tags.join(" "), subjectById(question.subject).title].join(" ").toLowerCase();
            return (!search || haystack.indexOf(search) !== -1) &&
                (!subject || question.subject === subject) &&
                (!difficulty || question.difficulty === difficulty) &&
                (!year || question.yearLabel === year) &&
                (!status || question.status === status);
        });
        document.getElementById("drx-result-count").textContent = toFa(list.length) + " سوال";
        document.getElementById("drx-question-list").innerHTML = list.length
            ? list.map(function (question) { return questionCard(question, true); }).join("")
            : '<div class="drx-empty"><div><h3>سوالی با این فیلتر پیدا نشد.</h3><p class="drx-muted">فیلترها را سبک‌تر کن یا یک جلسه تمرین عمومی بساز.</p></div></div>';
    }

    function renderPractice() {
        layout("practice", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">ساخت جلسه</span>',
            '  <h1 class="drx-section-title">تمرین یا آزمون شبیه‌ساز بساز</h1>',
            '  <p class="drx-muted">برای MVP همه چیز محلی است. بعداً همین ساختار می‌تواند به محتوای مجاز و API مستقل وصل شود.</p>',
            '  <form id="drx-practice-form" class="drx-form-grid">',
            '    <fieldset class="drx-form-group drx-form-group--wide"><legend class="drx-label">درس‌ها</legend><div class="drx-check-grid">' + DATA.subjects.map(function (subject) {
                return '<label class="drx-check"><input type="checkbox" name="subject" value="' + subject.id + '" checked><span>' + subject.title + "</span></label>";
            }).join("") + "</div></fieldset>",
            '    <label class="drx-form-group"><span>تعداد سوال</span><select class="drx-select" name="count"><option value="5">۵ سوال</option><option value="8">۸ سوال</option><option value="3">۳ سوال سریع</option></select></label>',
            '    <label class="drx-form-group"><span>چیدمان</span><select class="drx-select" name="order"><option value="balanced">متعادل بین درس‌ها</option><option value="weak">اولویت با سوالات چالشی</option><option value="new">اولویت با سوالات جدید</option></select></label>',
            '    <fieldset class="drx-form-group drx-form-group--wide"><legend class="drx-label">حالت جلسه</legend><div class="drx-mode-grid">',
            '      <label class="drx-mode"><input type="radio" name="mode" value="learning" checked><span><strong>یادگیری</strong><small class="drx-muted">بعد از پاسخ، امکان مرور توضیح داری.</small></span></label>',
            '      <label class="drx-mode"><input type="radio" name="mode" value="exam"><span><strong>آزمون زمان‌دار</strong><small class="drx-muted">برای هر سوال زمان محدود در نظر گرفته می‌شود.</small></span></label>',
            '      <label class="drx-mode"><input type="radio" name="mode" value="review"><span><strong>مرور هدفمند</strong><small class="drx-muted">برای مرور سوالات سخت و نشان‌شده مناسب است.</small></span></label>',
            "    </div></fieldset>",
            '    <div class="drx-actions drx-form-group--wide"><button class="drx-btn drx-btn--primary" type="submit">شروع جلسه</button><button class="drx-btn drx-btn--secondary" type="button" data-action="create-mock">ساخت آزمون شبیه‌ساز</button></div>',
            "  </form>",
            "</section>"
        ].join(""));
        document.getElementById("drx-practice-form").addEventListener("submit", function (event) {
            event.preventDefault();
            var form = event.currentTarget;
            var selectedSubjects = Array.from(form.querySelectorAll('input[name="subject"]:checked')).map(function (input) {
                return input.value;
            });
            if (!selectedSubjects.length) {
                showToast("حداقل یک درس را انتخاب کن.");
                return;
            }
            var mode = form.querySelector('input[name="mode"]:checked').value;
            var count = Number(form.elements.count.value);
            var order = form.elements.order.value;
            startSession({
                subjects: selectedSubjects,
                count: count,
                mode: mode,
                order: order
            });
        });
        bindCommonActions();
    }

    function pickQuestions(options) {
        var pool = DATA.questions.filter(function (question) {
            return !options.subjects || options.subjects.indexOf(question.subject) !== -1;
        });
        if (options.order === "weak") {
            pool.sort(function (a, b) {
                return (b.difficulty === "hard" ? 1 : 0) - (a.difficulty === "hard" ? 1 : 0);
            });
        } else if (options.order === "new") {
            pool.sort(function (a, b) {
                return (b.status === "new" ? 1 : 0) - (a.status === "new" ? 1 : 0);
            });
        }
        return pool.slice(0, Math.min(options.count || 5, pool.length)).map(function (question) {
            return question.id;
        });
    }

    function startSession(options) {
        var ids = options.questionIds || pickQuestions(options);
        if (!ids.length) {
            showToast("برای این انتخاب سوالی وجود ندارد.");
            return;
        }
        var now = Date.now();
        writeActiveSession({
            id: "drx-session-" + now,
            mode: options.mode || "learning",
            order: options.order || "balanced",
            questionIds: ids,
            answers: {},
            currentIndex: 0,
            startedAt: now,
            durationSeconds: (options.mode === "exam" ? ids.length * 75 : 0)
        });
        window.location.href = "/dental-residency/session/";
    }

    function renderSession() {
        var session = activeSession();
        if (!session || !Array.isArray(session.questionIds) || !session.questionIds.length) {
            startSession({ subjects: DATA.subjects.map(function (subject) { return subject.id; }), count: 5, mode: "learning", order: "balanced" });
            return;
        }
        var index = Math.max(0, Math.min(session.currentIndex || 0, session.questionIds.length - 1));
        session.currentIndex = index;
        var question = questionById(session.questionIds[index]);
        if (!question) {
            showToast("سوال فعال پیدا نشد.");
            return;
        }
        var answer = session.answers ? session.answers[question.id] : undefined;
        var progress = ((index + 1) / session.questionIds.length) * 100;
        var subject = subjectById(question.subject);
        var canShowLearningExplanation = session.mode !== "exam" && answer !== undefined;
        layout("practice", [
            '<section class="drx-session-layout">',
            '  <article class="drx-question">',
            '    <div class="drx-question__meta">',
            '      <div><span class="drx-chip">' + subject.title + '</span> <span class="drx-chip drx-chip--cyan">' + difficultyLabel(question.difficulty) + "</span></div>",
            '      <button class="drx-btn drx-btn--ghost" type="button" data-action="toggle-bookmark-session" data-id="' + question.id + '">' + (isBookmarked(question.id) ? "نشان‌شده" : "نشان کردن") + "</button>",
            "    </div>",
            '    <div class="drx-progress" aria-label="پیشرفت جلسه"><span style="--drx-progress-value:' + progress + '%"></span></div>',
            '    <p class="drx-muted">سوال ' + toFa(index + 1) + " از " + toFa(session.questionIds.length) + " · " + question.yearLabel + "</p>",
            '    <h1 class="drx-question__stem">' + escapeHtml(question.stem) + "</h1>",
            '    <div class="drx-options">' + question.options.map(function (option, optionIndex) {
                var classes = "drx-option";
                if (answer !== undefined && Number(answer) === optionIndex) {
                    classes += " is-selected";
                }
                if (canShowLearningExplanation && optionIndex === question.correctIndex) {
                    classes += " is-correct";
                }
                if (canShowLearningExplanation && Number(answer) === optionIndex && optionIndex !== question.correctIndex) {
                    classes += " is-wrong";
                }
                return '<button class="' + classes + '" type="button" data-action="answer" data-index="' + optionIndex + '"><span class="drx-option__key">' + toFa(optionIndex + 1) + '</span><span>' + escapeHtml(option) + "</span></button>";
            }).join("") + "</div>",
            canShowLearningExplanation ? '<div class="drx-explanation"><strong>توضیح نمونه:</strong><br>' + escapeHtml(question.explanation) + "</div>" : "",
            '    <div class="drx-session-actions">',
            '      <button class="drx-btn drx-btn--ghost" type="button" data-action="prev-question"' + (index === 0 ? " disabled" : "") + ">قبلی</button>",
            '      <button class="drx-btn drx-btn--secondary" type="button" data-action="next-question"' + (index >= session.questionIds.length - 1 ? " disabled" : "") + ">بعدی</button>",
            '      <button class="drx-btn drx-btn--primary" type="button" data-action="finish-session">پایان و مرور</button>',
            "    </div>",
            "  </article>",
            '  <aside class="drx-session-rail">',
            '    <h2>وضعیت جلسه</h2>',
            '    <span class="drx-chip drx-chip--success">پاسخ‌داده: ' + toFa(answeredCount(session)) + "</span>",
            '    <span class="drx-chip drx-chip--cyan">حالت: ' + (session.mode === "exam" ? "آزمون زمان‌دار" : session.mode === "review" ? "مرور هدفمند" : "یادگیری") + "</span>",
            '    <span class="drx-chip drx-chip--warning" id="drx-timer">زمان: --:--</span>',
            '    <div class="drx-step-list">' + session.questionIds.map(function (id, stepIndex) {
                var classes = "drx-step";
                if (stepIndex === index) {
                    classes += " is-current";
                } else if (session.answers && session.answers[id] !== undefined) {
                    classes += " is-answered";
                }
                return '<button class="' + classes + '" type="button" data-action="jump-question" data-index="' + stepIndex + '">' + toFa(stepIndex + 1) + "</button>";
            }).join("") + "</div>",
            "  </aside>",
            "</section>"
        ].join(""));
        bindSessionActions();
        startTimer();
    }

    function bindSessionActions() {
        app.addEventListener("click", function (event) {
            var target = event.target.closest("[data-action]");
            if (!target) {
                return;
            }
            var session = activeSession();
            if (!session) {
                return;
            }
            var action = target.dataset.action;
            if (action === "answer") {
                var qid = session.questionIds[session.currentIndex || 0];
                session.answers = session.answers || {};
                session.answers[qid] = Number(target.dataset.index);
                writeActiveSession(session);
                renderSession();
            }
            if (action === "prev-question" || action === "next-question" || action === "jump-question") {
                if (action === "prev-question") {
                    session.currentIndex = Math.max(0, (session.currentIndex || 0) - 1);
                } else if (action === "next-question") {
                    session.currentIndex = Math.min(session.questionIds.length - 1, (session.currentIndex || 0) + 1);
                } else {
                    session.currentIndex = Number(target.dataset.index) || 0;
                }
                writeActiveSession(session);
                renderSession();
            }
            if (action === "toggle-bookmark-session") {
                toggleBookmark(target.dataset.id);
                renderSession();
            }
            if (action === "finish-session") {
                finishSession();
            }
        }, { once: true });
    }

    function startTimer() {
        if (timerHandle) {
            window.clearInterval(timerHandle);
        }
        function update() {
            var session = activeSession();
            var timer = document.getElementById("drx-timer");
            if (!session || !timer) {
                return;
            }
            var elapsed = Math.floor((Date.now() - session.startedAt) / 1000);
            if (session.mode === "exam") {
                var remaining = Math.max(0, Number(session.durationSeconds || 0) - elapsed);
                timer.textContent = "باقی‌مانده: " + formatTime(remaining);
                if (remaining <= 0) {
                    finishSession(true);
                }
            } else {
                timer.textContent = "زمان سپری‌شده: " + formatTime(elapsed);
            }
        }
        update();
        timerHandle = window.setInterval(update, 1000);
    }

    function finishSession(force) {
        var session = activeSession();
        if (!session) {
            return;
        }
        if (!force && answeredCount(session) < session.questionIds.length && !window.confirm("برخی سوالات بی‌پاسخ هستند. جلسه پایان یابد؟")) {
            return;
        }
        session.finishedAt = Date.now();
        session.result = computeResult(session);
        var allSessions = sessions();
        allSessions.unshift(session);
        writeSessions(allSessions);
        writeJson("latestCompletedSession", session);
        writeActiveSession(null);
        window.location.href = "/dental-residency/review/";
    }

    function renderReview() {
        var session = latestCompletedSession();
        if (!session) {
            layout("practice", '<div class="drx-empty"><div><h2>هنوز نتیجه‌ای برای مرور وجود ندارد.</h2><p class="drx-muted">یک جلسه تمرین بساز و بعد به این صفحه برگرد.</p><a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">شروع تمرین</a></div></div>');
            return;
        }
        var result = computeResult(session);
        layout("practice", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">مرور نتیجه</span>',
            '  <h1 class="drx-section-title">خلاصه جلسه</h1>',
            '  <div class="drx-grid drx-grid--stats">',
            statCard("درست", toFa(result.correct), "از " + toFa(result.total) + " سوال"),
            statCard("غلط", toFa(result.wrong), "قابل افزودن به دفترچه"),
            statCard("بی‌پاسخ", toFa(result.unanswered), "برای مرور بعدی"),
            statCard("زمان", formatTime(result.spentSeconds), "دقت " + formatPercent(result.accuracy)),
            "  </div>",
            '  <div class="drx-actions"><a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">جلسه جدید</a><a class="drx-btn drx-btn--secondary" href="/dental-residency/dashboard/">تحلیل عملکرد</a></div>',
            "</section>",
            '<section class="drx-section"><div class="drx-section-head"><h2 class="drx-section-title">مرور سوال به سوال</h2><span class="drx-chip">توضیح‌ها نمونه‌اند</span></div><div class="drx-review-list">',
            session.questionIds.map(function (id, index) {
                var question = questionById(id);
                if (!question) {
                    return "";
                }
                var answer = session.answers ? session.answers[id] : undefined;
                var isCorrect = answer !== undefined && Number(answer) === question.correctIndex;
                var stateChip = answer === undefined ? '<span class="drx-chip drx-chip--warning">بی‌پاسخ</span>' : isCorrect ? '<span class="drx-chip drx-chip--success">درست</span>' : '<span class="drx-chip drx-chip--danger">غلط</span>';
                return [
                    '<article class="drx-review-row">',
                    '  <div class="drx-review-row__head"><h3>سوال ' + toFa(index + 1) + " · " + subjectById(question.subject).shortTitle + "</h3>" + stateChip + "</div>",
                    "  <p>" + escapeHtml(question.stem) + "</p>",
                    '  <div class="drx-options">' + question.options.map(function (option, optionIndex) {
                        var classes = "drx-option";
                        if (optionIndex === question.correctIndex) {
                            classes += " is-correct";
                        }
                        if (answer !== undefined && Number(answer) === optionIndex && optionIndex !== question.correctIndex) {
                            classes += " is-wrong";
                        }
                        return '<div class="' + classes + '"><span class="drx-option__key">' + toFa(optionIndex + 1) + '</span><span>' + escapeHtml(option) + "</span></div>";
                    }).join("") + "</div>",
                    '  <div class="drx-explanation"><strong>توضیح نمونه:</strong><br>' + escapeHtml(question.explanation) + "</div>",
                    !isCorrect ? '<div class="drx-actions"><button class="drx-btn drx-btn--secondary" type="button" data-action="add-wrong" data-id="' + id + '">افزودن به دفترچه پاسخ‌های غلط</button></div>' : "",
                    "</article>"
                ].join("");
            }).join(""),
            "</div></section>"
        ].join(""));
        bindCommonActions();
    }

    function renderDashboard() {
        var completed = sessions();
        var latest = completed[0];
        var aggregate = latest ? computeResult(latest) : { accuracy: 0, correct: 0, wrong: 0, unanswered: 0, spentSeconds: 0 };
        var stats = subjectStats();
        var weak = stats.filter(function (stat) {
            return stat.seen === 0 || stat.accuracy < 60;
        }).slice(0, 3);
        layout("dashboard", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">تحلیل عملکرد</span>',
            '  <h1 class="drx-section-title">داشبورد آمادگی رزیدنتی</h1>',
            '  <p class="drx-muted">این داشبورد فعلاً بر پایه sessionهای ذخیره‌شده در مرورگر کار می‌کند.</p>',
            '  <div class="drx-grid drx-grid--stats">',
            statCard("جلسه‌ها", toFa(completed.length), "تمرین‌های محلی"),
            statCard("دقت آخرین جلسه", formatPercent(aggregate.accuracy), "شاخص فوری"),
            statCard("دفترچه غلط‌ها", toFa(Object.keys(wrongNotebook()).length), "برای retry"),
            statCard("آمادگی شبیه‌ساز", formatPercent(completed.length ? 58 + Math.min(32, completed.length * 6) : 42), "placeholder تحلیلی"),
            "  </div>",
            "</section>",
            '<section class="drx-section"><div class="drx-section-head"><h2 class="drx-section-title">پیشرفت درس به درس</h2><a class="drx-btn drx-btn--ghost" href="/dental-residency/qbank/">بانک سوالات</a></div><div class="drx-grid drx-grid--subjects">' + stats.map(function (stat) {
                return subjectCard(stat, "/dental-residency/qbank/?subject=" + stat.subject.id);
            }).join("") + "</div></section>",
            '<section class="drx-section drx-grid drx-grid--cards">',
            '  <article class="drx-panel"><h2>درس‌های ضعیف</h2>' + (weak.length ? weak.map(function (stat) {
                return '<p><strong>' + stat.subject.title + '</strong><br><span class="drx-muted">دقت فعلی: ' + formatPercent(stat.accuracy) + "</span></p>";
            }).join("") : '<p class="drx-muted">هنوز داده کافی برای تشخیص ضعف وجود ندارد.</p>') + "</article>",
            '  <article class="drx-panel"><h2>جلسه‌های اخیر</h2>' + (completed.length ? completed.slice(0, 5).map(function (session) {
                var result = computeResult(session);
                return '<p><strong>' + (session.mode === "exam" ? "آزمون زمان‌دار" : "تمرین") + '</strong><br><span class="drx-muted">' + toFa(result.correct) + " درست از " + toFa(result.total) + " · " + formatTime(result.spentSeconds) + "</span></p>";
            }).join("") : '<p class="drx-muted">هنوز جلسه‌ای ثبت نشده است.</p>') + "</article>",
            '  <article class="drx-panel"><h2>روند پاسخ‌های غلط</h2><p class="drx-muted">نمودار روند در نسخه بعدی با داده‌های بیشتر فعال می‌شود.</p><div class="drx-progress"><span style="--drx-progress-value:42%"></span></div></article>',
            "</section>"
        ].join(""));
    }

    function renderBookmarks() {
        var ids = bookmarks();
        var list = ids.map(questionById).filter(Boolean);
        layout("bookmarks", [
            '<section class="drx-panel"><span class="drx-kicker">سوالات نشان‌شده</span><h1 class="drx-section-title">مرور سریع سوالات ذخیره‌شده</h1><p class="drx-muted">این فهرست به‌صورت محلی در مرورگر نگه‌داری می‌شود.</p></section>',
            '<section class="drx-section"><div class="drx-grid drx-grid--cards">' + (list.length ? list.map(function (question) {
                return questionCard(question, true);
            }).join("") : '<div class="drx-empty"><div><h2>هنوز سوالی نشان نشده است.</h2><p class="drx-muted">از بانک سوالات یا جلسه تمرین، سوال‌های مهم را نشان کن.</p><a class="drx-btn drx-btn--primary" href="/dental-residency/qbank/">رفتن به بانک سوالات</a></div></div>') + "</div></section>"
        ].join(""));
        bindCommonActions();
    }

    function renderWrongAnswers() {
        var notebook = wrongNotebook();
        var rows = Object.keys(notebook).map(function (id) {
            return { meta: notebook[id], question: questionById(id) };
        }).filter(function (row) {
            return row.question;
        });
        layout("wrongAnswers", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">دفترچه پاسخ‌های غلط</span>',
            '  <h1 class="drx-section-title">مرور پاسخ‌های غلط و retry</h1>',
            '  <div class="drx-toolbar"><input class="drx-field" id="drx-wrong-search" type="search" placeholder="فیلتر بر اساس درس، مبحث یا برچسب"><button class="drx-btn drx-btn--primary" type="button" data-action="retry-wrong"' + (rows.length ? "" : " disabled") + ">تمرین دوباره غلط‌ها</button></div>",
            '  <div class="drx-filter-row"><button class="drx-filter is-active" type="button">همه</button><button class="drx-filter" type="button">باز</button><button class="drx-filter" type="button">مرور شده</button></div>',
            "</section>",
            '<section class="drx-section"><div id="drx-wrong-list" class="drx-grid drx-grid--cards">' + wrongRowsMarkup(rows) + "</div></section>"
        ].join(""));
        var search = document.getElementById("drx-wrong-search");
        search.addEventListener("input", function () {
            var value = search.value.trim().toLowerCase();
            var filtered = rows.filter(function (row) {
                var question = row.question;
                var subject = subjectById(question.subject);
                return [question.stem, question.topic, subject.title, question.tags.join(" ")].join(" ").toLowerCase().indexOf(value) !== -1;
            });
            document.getElementById("drx-wrong-list").innerHTML = wrongRowsMarkup(filtered);
        });
        bindCommonActions();
    }

    function wrongRowsMarkup(rows) {
        if (!rows.length) {
            return '<div class="drx-empty"><div><h2>دفترچه پاسخ‌های غلط خالی است.</h2><p class="drx-muted">در صفحه مرور نتیجه، سوالات غلط را به این دفترچه اضافه کن.</p><a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">شروع تمرین</a></div></div>';
        }
        return rows.map(function (row) {
            var question = row.question;
            var subject = subjectById(question.subject);
            return [
                '<article class="drx-card">',
                '  <div class="drx-card__head"><div><span class="drx-chip drx-chip--danger">تکرار خطا: ' + toFa(row.meta.count || 1) + '</span><h3>' + question.topic + "</h3></div><span class=\"drx-chip\">" + subject.shortTitle + "</span></div>",
                '  <p class="drx-muted">آخرین ثبت: ' + (row.meta.lastAt ? new Date(row.meta.lastAt).toLocaleDateString("fa-IR") : "نامشخص") + "</p>",
                "  <p>" + escapeHtml(question.stem) + "</p>",
                '  <div class="drx-actions"><button class="drx-btn drx-btn--secondary" type="button" data-action="start-single" data-id="' + question.id + '">تمرین این سوال</button></div>',
                "</article>"
            ].join("");
        }).join("");
    }

    function bindCommonActions() {
        app.addEventListener("click", function (event) {
            var target = event.target.closest("[data-action]");
            if (!target) {
                return;
            }
            var action = target.dataset.action;
            if (action === "toggle-bookmark") {
                toggleBookmark(target.dataset.id);
                var active = view;
                if (active === "qbank") {
                    renderQuestionList();
                } else if (active === "bookmarks") {
                    renderBookmarks();
                }
            }
            if (action === "start-single") {
                startSession({ questionIds: [target.dataset.id], mode: "learning" });
            }
            if (action === "create-mock") {
                startSession({
                    subjects: DATA.subjects.map(function (subject) { return subject.id; }),
                    count: Math.min(8, DATA.questions.length),
                    mode: "exam",
                    order: "balanced"
                });
            }
            if (action === "add-wrong") {
                addWrongQuestion(target.dataset.id);
            }
            if (action === "retry-wrong") {
                var ids = Object.keys(wrongNotebook()).filter(function (id) {
                    return questionById(id);
                });
                startSession({ questionIds: ids, mode: "review" });
            }
        });
    }

    function init() {
        if (!app) {
            return;
        }
        if (view === "qbank") {
            renderQbank();
        } else if (view === "practice") {
            renderPractice();
        } else if (view === "session") {
            renderSession();
        } else if (view === "review") {
            renderReview();
        } else if (view === "dashboard") {
            renderDashboard();
        } else if (view === "bookmarks") {
            renderBookmarks();
        } else if (view === "wrongAnswers") {
            renderWrongAnswers();
        } else {
            renderHome();
        }
    }

    var drxAuth = { checked: false, loading: false, loggedIn: false, user: null };
    var drxApiUrl = "/dental-residency/api/auth.php";

    routes = [
        { view: "home", href: "/dental-residency/", label: "منوی اصلی" },
        { view: "qbank", href: "/dental-residency/qbank/", label: "بانک تست" },
        { view: "practice", href: "/dental-residency/practice/", label: "آزمون‌ساز" },
        { view: "dashboard", href: "/dental-residency/dashboard/", label: "داشبورد" },
        { view: "examHistory", href: "/dental-residency/exam-history/", label: "سوابق" },
        { view: "schedule", href: "/dental-residency/schedule/", label: "برنامه" },
        { view: "search", href: "/dental-residency/search/", label: "جستجو" }
    ];

    function drxMetrics() {
        var completed = sessions();
        var correct = completed.reduce(function (sum, session) {
            return sum + computeResult(session).correct;
        }, 0);
        return {
            points: correct * 12,
            coins: Math.floor(correct / 12) + (completed.length ? 1 : 0),
            dailyGoal: Math.min(100, Math.round((correct * 12 / 500) * 100)),
            sessions: completed.length,
            bookmarks: bookmarks().length,
            wrong: Object.keys(wrongNotebook()).length
        };
    }

    function drxServiceItems() {
        return [
            { title: "بانک تست کشوری", href: "/dental-residency/qbank/?source=national", icon: "KT", text: "مرور درس‌محور و سال‌محور سوالات رزیدنتی دندانپزشکی." },
            { title: "بانک تست تالیفی", href: "/dental-residency/qbank/?source=custom", icon: "TT", text: "سوالات تمرینی مجاز برای تثبیت مباحث پرتکرار." },
            { title: "میکرودرسنامه", href: "/dental-residency/notes/", icon: "MD", text: "یادداشت‌ها و خلاصه‌های کوتاه کنار بانک سوال." },
            { title: "مدومایند", href: "/dental-residency/highlights/", icon: "MM", text: "هایلایت‌ها و نقشه مرور از نکات ذخیره‌شده." },
            { title: "آزمون‌ساز هوشمند", href: "/dental-residency/practice/", icon: "EX", text: "ساخت آزمون درس‌محور، مروری یا زمان‌دار." },
            { title: "آزمون‌های تالیفی", href: "/dental-residency/exam-history/", icon: "AZ", text: "سوابق آزمون‌ها و نقطه شروع آزمون‌های جدید." },
            { title: "دستیار مرور", href: "/dental-residency/wrong-answers/", icon: "RV", text: "مرور فاصله‌دار غلط‌ها، سوالات سخت و نشان‌شده‌ها." },
            { title: "گزارشات", href: "/dental-residency/dashboard/", icon: "RP", text: "تحلیل دقت، پیشرفت درس‌ها، امتیاز و هدف روزانه." },
            { title: "برنامه روزانه", href: "/dental-residency/schedule/", icon: "PL", text: "چک‌لیست مطالعه و تست برای آمادگی رزیدنتی." },
            { title: "جستجو", href: "/dental-residency/search/", icon: "SR", text: "جستجو در سوال، درس، تگ و توضیح نمونه." }
        ];
    }

    function drxAccountPill() {
        var metrics = drxMetrics();
        if (drxAuth.loggedIn && drxAuth.user) {
            return [
                '<a class="drx-account-pill is-logged-in" href="/dental-residency/account/">',
                '  <span class="drx-account-pill__title">' + escapeHtml(drxAuth.user.phoneMasked || "کاربر رزیدنتی") + "</span>",
                '  <span class="drx-account-pill__meta">' + escapeHtml(drxAuth.user.roleLabel || "ورود مستقل") + " · " + toFa(metrics.coins) + " کوین</span>",
                "</a>"
            ].join("");
        }
        return '<a class="drx-account-pill" href="/dental-residency/account/"><span class="drx-account-pill__title">ورود مستقل رزیدنتی</span><span class="drx-account-pill__meta">جدا از حساب Dentistry1402TUMS</span></a>';
    }

    function drxApi(action, payload) {
        var body = new FormData();
        body.append("action", action);
        Object.keys(payload || {}).forEach(function (key) {
            body.append(key, payload[key]);
        });
        return fetch(drxApiUrl, {
            method: "POST",
            body: body,
            credentials: "same-origin"
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: "پاسخ سرور قابل خواندن نیست." };
            }).then(function (json) {
                if (!response.ok || !json.success) {
                    throw new Error(json.error || "درخواست انجام نشد.");
                }
                return json;
            });
        });
    }

    function drxLoadAuth() {
        drxAuth.loading = true;
        return fetch(drxApiUrl + "?action=me", { credentials: "same-origin" })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                drxAuth.checked = true;
                drxAuth.loggedIn = !!(json && json.loggedIn);
                drxAuth.user = json && json.user ? json.user : null;
            }).catch(function () {
                drxAuth.checked = true;
                drxAuth.loggedIn = false;
                drxAuth.user = null;
            }).finally(function () {
                drxAuth.loading = false;
            });
    }

    layout = function (activeView, content) {
        var metrics = drxMetrics();
        app.innerHTML = [
            '<header class="drx-topbar">',
            '  <a class="drx-brand" href="/dental-residency/">',
            '    <span class="drx-brand__mark" aria-hidden="true">DR</span>',
            '    <span class="drx-brand__copy">',
            '      <span class="drx-brand__title">Dental Residency</span>',
            '      <span class="drx-brand__subtitle">بخش مستقل رزیدنتی دندانپزشکی</span>',
            '    </span>',
            "  </a>",
            '  <nav class="drx-nav" aria-label="ناوبری رزیدنتی دندانپزشکی">' + navMarkup(activeView) + "</nav>",
            '  <div class="drx-top-actions">',
            '    <button class="drx-icon-btn" type="button" data-action="toggle-theme" title="تغییر تم" aria-label="تغییر تم">☾</button>',
            '    <span class="drx-score-pill"><strong>' + toFa(metrics.points) + '</strong><small>امتیاز</small></span>',
            '    <span class="drx-score-pill"><strong>' + toFa(metrics.coins) + '</strong><small>کوین</small></span>',
            drxAccountPill(),
            "  </div>",
            "</header>",
            '<main class="drx-main">' + content + "</main>",
            '<p class="drx-footer-note">این بخش از نظر login، session، داده و تنظیمات از Dentistry1402TUMS جداست. محتوای سوالات فعلی نمونه آموزشی و غیررسمی است.</p>'
        ].join("");
    };

    renderHome = function () {
        var stats = subjectStats();
        var metrics = drxMetrics();
        var completed = sessions();
        var latest = completed[0];
        var latestText = latest ? "آخرین جلسه: " + toFa(computeResult(latest).correct) + " پاسخ درست از " + toFa(latest.questionIds.length) : "هنوز جلسه‌ای ثبت نشده است.";
        layout("home", [
            '<section class="drx-hero drx-hero--menu">',
            '  <div class="drx-hero__copy">',
            '    <span class="drx-kicker">منوی اصلی رزیدنتی دندانپزشکی</span>',
            "    <h1>محیط تمرین رزیدنتی دندانپزشکی، جدا از سایت آموزشی ۱۴۰۲</h1>",
            "    <p>ساختار این بخش از الگوی مدوفست الهام گرفته شده: بانک تست، آزمون‌ساز، مرور، گزارش، برنامه روزانه، یادداشت و جستجو؛ اما هویت و state آن کاملاً ایزوله است.</p>",
            '    <div class="drx-actions">',
            '      <a class="drx-btn drx-btn--primary" href="/dental-residency/qbank/">ورود به بانک تست</a>',
            '      <a class="drx-btn drx-btn--secondary" href="/dental-residency/practice/">ساخت آزمون</a>',
            '      <a class="drx-btn drx-btn--ghost" href="/dental-residency/account/">ورود پیامکی مستقل</a>',
            "    </div>",
            "  </div>",
            '  <aside class="drx-panel drx-daily-panel">',
            "    <h2>هدف امروز</h2>",
            '    <p class="drx-muted">با پاسخ درست امتیاز بگیر، غلط‌ها را وارد مرور کن و تا هدف روزانه پیش برو.</p>',
            '    <div class="drx-progress drx-progress--thick"><span style="--drx-progress-value:' + metrics.dailyGoal + '%"></span></div>',
            '    <div class="drx-mini-stats"><span>جلسه ' + toFa(metrics.sessions) + '</span><span>نشان‌شده ' + toFa(metrics.bookmarks) + '</span><span>غلط ' + toFa(metrics.wrong) + "</span></div>",
            "  </aside>",
            "</section>",
            '<section class="drx-service-grid" aria-label="قابلیت‌های رزیدنتی">' + drxServiceItems().map(function (item) {
                return [
                    '<a class="drx-service-card" href="' + item.href + '">',
                    '  <span class="drx-service-card__icon" aria-hidden="true">' + item.icon + "</span>",
                    '  <strong>' + item.title + "</strong>",
                    '  <small>' + item.text + "</small>",
                    "</a>"
                ].join("");
            }).join("") + "</section>",
            '<section class="drx-grid drx-grid--stats" aria-label="آمار سریع">',
            statCard("سوالات نمونه", toFa(DATA.questions.length), "دیتاست غیررسمی برای تست محصول"),
            statCard("درس‌ها", toFa(DATA.subjects.length), "طبقه‌بندی رزیدنتی دندانپزشکی"),
            statCard("جلسه‌ها", toFa(completed.length), "ذخیره در فضای ایزوله مرورگر"),
            statCard("دقت کلی", completed.length ? formatPercent(computeResult(latest).accuracy) : "۰٪", latestText),
            "</section>",
            '<section class="drx-section"><div class="drx-section-head"><h2 class="drx-section-title">پیشرفت درس‌ها</h2><a class="drx-btn drx-btn--ghost" href="/dental-residency/dashboard/">تحلیل کامل</a></div><div class="drx-grid drx-grid--subjects">' + stats.map(function (stat) {
                return subjectCard(stat, "/dental-residency/qbank/?subject=" + stat.subject.id);
            }).join("") + "</div></section>"
        ].join(""));
        bindCommonActions();
    };

    renderDashboard = function () {
        var completed = sessions();
        var latest = completed[0];
        var aggregate = latest ? computeResult(latest) : { accuracy: 0, correct: 0, wrong: 0, unanswered: 0, spentSeconds: 0 };
        var stats = subjectStats();
        var weak = stats.filter(function (stat) { return stat.seen === 0 || stat.accuracy < 60; }).slice(0, 4);
        var metrics = drxMetrics();
        layout("dashboard", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">داشبورد رزیدنتی</span>',
            '  <h1 class="drx-section-title">خلاصه عملکرد، امتیاز و مسیر مرور</h1>',
            '  <div class="drx-grid drx-grid--stats">',
            statCard("امتیاز امروز", toFa(metrics.points), "هدف روزانه ۵۰۰ امتیاز"),
            statCard("کوین", toFa(metrics.coins), "پاداش تمرین‌های کامل"),
            statCard("دقت آخرین جلسه", formatPercent(aggregate.accuracy), "شاخص فوری آمادگی"),
            statCard("دفترچه غلط‌ها", toFa(metrics.wrong), "برای مرور فاصله‌دار"),
            "  </div>",
            "</section>",
            '<section class="drx-section drx-grid drx-grid--cards">',
            '  <article class="drx-panel"><h2>درس‌های اولویت‌دار</h2>' + (weak.length ? weak.map(function (stat) {
                return '<p><strong>' + stat.subject.title + '</strong><br><span class="drx-muted">دقت فعلی: ' + formatPercent(stat.accuracy) + " · پیشروی: " + formatPercent(stat.progress) + "</span></p>";
            }).join("") : '<p class="drx-muted">هنوز ضعف مشخصی ثبت نشده است.</p>') + "</article>",
            '  <article class="drx-panel"><h2>جلسه‌های اخیر</h2>' + (completed.length ? completed.slice(0, 5).map(function (session) {
                var result = computeResult(session);
                return '<p><strong>' + (session.mode === "exam" ? "آزمون زمان‌دار" : "تمرین") + '</strong><br><span class="drx-muted">' + toFa(result.correct) + " درست از " + toFa(result.total) + " · " + formatTime(result.spentSeconds) + "</span></p>";
            }).join("") : '<p class="drx-muted">هنوز جلسه‌ای ثبت نشده است.</p>') + '<a class="drx-btn drx-btn--ghost" href="/dental-residency/exam-history/">سوابق آزمون</a></article>',
            '  <article class="drx-panel"><h2>هدف روزانه</h2><p class="drx-muted">پیشروی تا هدف امتیازی امروز.</p><div class="drx-progress drx-progress--thick"><span style="--drx-progress-value:' + metrics.dailyGoal + '%"></span></div><div class="drx-actions"><a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">ادامه تمرین</a></div></article>',
            "</section>",
            '<section class="drx-section"><div class="drx-section-head"><h2 class="drx-section-title">پیشرفت درس به درس</h2><a class="drx-btn drx-btn--ghost" href="/dental-residency/qbank/">بانک تست</a></div><div class="drx-grid drx-grid--subjects">' + stats.map(function (stat) {
                return subjectCard(stat, "/dental-residency/qbank/?subject=" + stat.subject.id);
            }).join("") + "</div></section>"
        ].join(""));
        bindCommonActions();
    };

    function drxLocalNotes() {
        return readJson("notes", []);
    }

    function drxWriteNotes(notes) {
        writeJson("notes", notes.slice(0, 100));
    }

    function renderAccount() {
        layout("account", [
            '<section class="drx-panel">',
            '  <span class="drx-kicker">ورود مستقل</span>',
            '  <h1 class="drx-section-title">حساب رزیدنتی دندانپزشکی</h1>',
            '  <p class="drx-muted">این ورود، session و user store جدا از حساب اصلی Dentistry1402TUMS دارد.</p>',
            drxAuth.loggedIn && drxAuth.user ? [
                '<div class="drx-account-summary">',
                '<strong>' + escapeHtml(drxAuth.user.phoneMasked || "") + '</strong>',
                '<span class="drx-muted">' + escapeHtml(drxAuth.user.roleLabel || "") + '</span>',
                '<button class="drx-btn drx-btn--danger" type="button" data-action="drx-logout">خروج از رزیدنتی</button>',
                "</div>"
            ].join("") : [
                '<form id="drx-login-form" class="drx-form-grid">',
                '<label class="drx-form-group"><span>شماره موبایل</span><input class="drx-field" name="phoneNumber" inputmode="tel" autocomplete="tel" data-digit-locale="latin" placeholder="09000000000"></label>',
                '<div class="drx-actions"><button class="drx-btn drx-btn--primary" type="submit">ارسال کد</button></div>',
                '</form>',
                '<form id="drx-verify-form" class="drx-form-grid" hidden>',
                '<label class="drx-form-group"><span>کد تایید</span><input class="drx-field drx-otp-input" name="otpCode" inputmode="numeric" autocomplete="one-time-code" data-digit-locale="latin" maxlength="6"></label>',
                '<input type="hidden" name="phoneNumber">',
                '<div class="drx-actions"><button class="drx-btn drx-btn--primary" type="submit">تایید و ورود</button><button class="drx-btn drx-btn--ghost" type="button" data-action="drx-change-phone">تغییر شماره</button></div>',
                '</form>',
                '<p id="drx-auth-feedback" class="drx-feedback" role="status"></p>'
            ].join(""),
            "</section>"
        ].join(""));
        bindCommonActions();
        bindAccountForms();
    }

    function bindAccountForms() {
        var loginForm = document.getElementById("drx-login-form");
        var verifyForm = document.getElementById("drx-verify-form");
        var feedback = document.getElementById("drx-auth-feedback");
        if (!loginForm || !verifyForm || !feedback) {
            return;
        }
        loginForm.addEventListener("submit", function (event) {
            event.preventDefault();
            var phone = loginForm.elements.phoneNumber.value.trim();
            feedback.textContent = "در حال ارسال کد...";
            drxApi("requestOtp", { phoneNumber: phone }).then(function (json) {
                feedback.textContent = json.message || "کد ارسال شد.";
                verifyForm.elements.phoneNumber.value = phone;
                loginForm.hidden = true;
                verifyForm.hidden = false;
                verifyForm.elements.otpCode.focus();
            }).catch(function (error) {
                feedback.textContent = error.message || "ارسال کد انجام نشد.";
            });
        });
        verifyForm.addEventListener("submit", function (event) {
            event.preventDefault();
            feedback.textContent = "در حال تایید کد...";
            drxApi("verifyOtp", {
                phoneNumber: verifyForm.elements.phoneNumber.value,
                otpCode: verifyForm.elements.otpCode.value
            }).then(function (json) {
                drxAuth.loggedIn = true;
                drxAuth.user = json.user || null;
                showToast(json.message || "ورود انجام شد.");
                renderAccount();
            }).catch(function (error) {
                feedback.textContent = error.message || "تایید کد انجام نشد.";
            });
        });
    }

    function renderExamHistory() {
        var completed = sessions();
        layout("examHistory", [
            '<section class="drx-panel"><span class="drx-kicker">سوابق آزمون</span><h1 class="drx-section-title">آزمون‌های فعال و پایان‌یافته</h1><div class="drx-actions"><a class="drx-btn drx-btn--primary" href="/dental-residency/practice/">ساخت آزمون جدید</a></div></section>',
            '<section class="drx-section drx-grid drx-grid--cards">' + (completed.length ? completed.map(function (session) {
                var result = computeResult(session);
                return '<article class="drx-card"><div class="drx-card__head"><h3>' + (session.mode === "exam" ? "آزمون زمان‌دار" : "تمرین") + '</h3><span class="drx-chip">' + formatPercent(result.accuracy) + '</span></div><p class="drx-muted">' + toFa(result.correct) + " درست · " + toFa(result.wrong) + " غلط · " + formatTime(result.spentSeconds) + '</p><a class="drx-btn drx-btn--ghost" href="/dental-residency/review/">مشاهده آخرین کارنامه</a></article>';
            }).join("") : '<div class="drx-empty"><div><h2>سوابق آزمون خالی است.</h2><p class="drx-muted">یک آزمون‌ساز بساز تا کارنامه اینجا دیده شود.</p></div></div>') + "</section>"
        ].join(""));
        bindCommonActions();
    }

    function renderSchedule() {
        var rows = [
            ["صبح", "مرور سریع پاتولوژی، رادیولوژی و درمان ریشه", "۳۰ تست"],
            ["ظهر", "تحلیل غلط‌ها و ثبت نکات در یادداشت‌ها", "۲۰ دقیقه"],
            ["عصر", "آزمون زمان‌دار ترکیبی", "۴۰ تست"],
            ["شب", "مرور فاصله‌دار سوالات نشان‌شده", "۱۵ دقیقه"]
        ];
        layout("schedule", [
            '<section class="drx-panel"><span class="drx-kicker">برنامه روزانه</span><h1 class="drx-section-title">چک‌لیست مطالعه رزیدنتی دندانپزشکی</h1><p class="drx-muted">برنامه نمونه محلی است و بعداً می‌تواند به برنامه‌های قابل مدیریت وصل شود.</p></section>',
            '<section class="drx-section drx-grid drx-grid--cards">' + rows.map(function (row, index) {
                return '<article class="drx-card"><span class="drx-chip drx-chip--cyan">' + row[0] + '</span><h3>' + row[1] + '</h3><p class="drx-muted">' + row[2] + '</p><button class="drx-btn drx-btn--ghost" type="button" data-action="schedule-done" data-index="' + index + '">ثبت انجام شد</button></article>';
            }).join("") + "</section>"
        ].join(""));
        bindCommonActions();
    }

    function renderSearch() {
        layout("search", [
            '<section class="drx-panel"><span class="drx-kicker">جستجوی محتوا</span><h1 class="drx-section-title">جستجو در سوال، درس، تگ و توضیح</h1><div class="drx-toolbar"><input class="drx-field" id="drx-global-search" type="search" placeholder="کلمه یا عبارت مورد نظر"><a class="drx-btn drx-btn--ghost" href="/dental-residency/qbank/">بانک تست</a></div></section>',
            '<section class="drx-section"><div id="drx-global-results" class="drx-grid drx-grid--cards"></div></section>'
        ].join(""));
        var input = document.getElementById("drx-global-search");
        function paint() {
            var q = input.value.trim().toLowerCase();
            var list = DATA.questions.filter(function (question) {
                var subject = subjectById(question.subject);
                return !q || [question.stem, question.topic, question.explanation, subject.title, question.tags.join(" ")].join(" ").toLowerCase().indexOf(q) !== -1;
            });
            document.getElementById("drx-global-results").innerHTML = list.length ? list.map(function (question) {
                return questionCard(question, true);
            }).join("") : '<div class="drx-empty"><div><h2>نتیجه‌ای پیدا نشد.</h2><p class="drx-muted">عبارت کوتاه‌تر یا درس دیگری را امتحان کن.</p></div></div>';
        }
        input.addEventListener("input", paint);
        paint();
        bindCommonActions();
    }

    function renderNotes() {
        var notes = drxLocalNotes();
        layout("notes", [
            '<section class="drx-panel"><span class="drx-kicker">یادداشت‌ها</span><h1 class="drx-section-title">یادداشت‌های شخصی رزیدنتی</h1><form id="drx-note-form" class="drx-toolbar"><input class="drx-field" name="text" placeholder="نکته کوتاه مطالعه"><button class="drx-btn drx-btn--primary" type="submit">افزودن</button></form></section>',
            '<section class="drx-section drx-grid drx-grid--cards">' + (notes.length ? notes.map(function (note) {
                return '<article class="drx-card"><p>' + escapeHtml(note.text) + '</p><span class="drx-muted">' + new Date(note.createdAt).toLocaleDateString("fa-IR") + '</span></article>';
            }).join("") : '<div class="drx-empty"><div><h2>هنوز یادداشتی ثبت نشده است.</h2><p class="drx-muted">نکته‌های مهم را اینجا جدا از سایت اصلی نگه دار.</p></div></div>') + "</section>"
        ].join(""));
        document.getElementById("drx-note-form").addEventListener("submit", function (event) {
            event.preventDefault();
            var value = event.currentTarget.elements.text.value.trim();
            if (!value) {
                return;
            }
            notes.unshift({ text: value, createdAt: new Date().toISOString() });
            drxWriteNotes(notes);
            showToast("یادداشت ذخیره شد.");
            renderNotes();
        });
        bindCommonActions();
    }

    function renderHighlights() {
        var marked = bookmarks().map(questionById).filter(Boolean);
        layout("highlights", [
            '<section class="drx-panel"><span class="drx-kicker">هایلایت‌ها</span><h1 class="drx-section-title">نکات برجسته و نقشه مرور</h1><p class="drx-muted">در این نسخه، هایلایت‌ها از سوالات نشان‌شده و توضیح‌های نمونه ساخته می‌شوند.</p></section>',
            '<section class="drx-section drx-grid drx-grid--cards">' + (marked.length ? marked.map(function (question) {
                return '<article class="drx-card"><span class="drx-chip">' + subjectById(question.subject).shortTitle + '</span><h3>' + question.topic + '</h3><p>' + escapeHtml(question.explanation) + '</p></article>';
            }).join("") : '<div class="drx-empty"><div><h2>هایلایتی وجود ندارد.</h2><p class="drx-muted">از بانک تست، سوالات مهم را نشان کن.</p></div></div>') + "</section>"
        ].join(""));
        bindCommonActions();
    }

    function renderSettings() {
        layout("settings", [
            '<section class="drx-panel"><span class="drx-kicker">تنظیمات شخصی</span><h1 class="drx-section-title">تنظیمات و پاکسازی state رزیدنتی</h1><p class="drx-muted">این عملیات فقط روی localStorage بخش رزیدنتی اثر دارد و به داده‌های سایت اصلی دست نمی‌زند.</p><div class="drx-actions"><button class="drx-btn drx-btn--danger" type="button" data-action="reset-drx-local">پاکسازی داده محلی رزیدنتی</button></div></section>'
        ].join(""));
        bindCommonActions();
    }

    bindCommonActions = function () {
        if (app.dataset.drxCommonBound === "1") {
            return;
        }
        app.dataset.drxCommonBound = "1";
        app.addEventListener("click", function (event) {
            var target = event.target.closest("[data-action]");
            if (!target) {
                return;
            }
            var action = target.dataset.action;
            if (action === "toggle-theme") {
                document.body.classList.toggle("is-drx-dark");
                writeJson("theme", document.body.classList.contains("is-drx-dark") ? "dark" : "light");
            }
            if (action === "drx-change-phone") {
                renderAccount();
            }
            if (action === "drx-logout") {
                drxApi("logout", {}).then(function (json) {
                    drxAuth.loggedIn = false;
                    drxAuth.user = null;
                    showToast(json.message || "خارج شدی.");
                    renderAccount();
                }).catch(function (error) { showToast(error.message); });
            }
            if (action === "reset-drx-local" && window.confirm("همه داده‌های محلی رزیدنتی پاک شود؟")) {
                ["bookmarks", "wrongAnswers", "sessions", "activeSession", "latestCompletedSession", "notes"].forEach(function (key) {
                    window.localStorage.removeItem(storageKey(key));
                });
                showToast("داده محلی رزیدنتی پاک شد.");
                renderSettings();
            }
            if (action === "schedule-done") {
                showToast("در برنامه امروز ثبت شد.");
            }
            if (action === "toggle-bookmark") {
                toggleBookmark(target.dataset.id);
                if (view === "qbank") {
                    renderQuestionList();
                } else if (view === "bookmarks") {
                    renderBookmarks();
                }
            }
            if (action === "start-single") {
                startSession({ questionIds: [target.dataset.id], mode: "learning" });
            }
            if (action === "create-mock") {
                startSession({
                    subjects: DATA.subjects.map(function (subject) { return subject.id; }),
                    count: Math.min(8, DATA.questions.length),
                    mode: "exam",
                    order: "balanced"
                });
            }
            if (action === "add-wrong") {
                addWrongQuestion(target.dataset.id);
            }
            if (action === "retry-wrong") {
                var ids = Object.keys(wrongNotebook()).filter(function (id) { return questionById(id); });
                startSession({ questionIds: ids, mode: "review" });
            }
        });
    };

    function renderCurrentView() {
        if (view === "qbank") {
            renderQbank();
        } else if (view === "practice") {
            renderPractice();
        } else if (view === "session") {
            renderSession();
        } else if (view === "review") {
            renderReview();
        } else if (view === "dashboard") {
            renderDashboard();
        } else if (view === "bookmarks") {
            renderBookmarks();
        } else if (view === "wrongAnswers") {
            renderWrongAnswers();
        } else if (view === "account") {
            renderAccount();
        } else if (view === "examHistory") {
            renderExamHistory();
        } else if (view === "schedule") {
            renderSchedule();
        } else if (view === "search") {
            renderSearch();
        } else if (view === "notes") {
            renderNotes();
        } else if (view === "highlights") {
            renderHighlights();
        } else if (view === "settings") {
            renderSettings();
        } else {
            renderHome();
        }
    }

    init = function () {
        if (!app) {
            return;
        }
        if (readJson("theme", "light") === "dark") {
            document.body.classList.add("is-drx-dark");
        }
        renderCurrentView();
        drxLoadAuth().then(function () {
            if (view !== "session") {
                renderCurrentView();
            }
        });
    };

    init();
})();
