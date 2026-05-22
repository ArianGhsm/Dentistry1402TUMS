(function () {
    "use strict";

    var appRoot = document.querySelector("[data-exam-app]");
    var dataNode = document.getElementById("exam-data");

    if (!appRoot || !dataNode) {
        return;
    }

    var parsedData;
    try {
        parsedData = JSON.parse(dataNode.textContent || "{}");
    } catch (_error) {
        renderFailure("داده‌های آزمون قابل خواندن نیست.");
        return;
    }

    var exam = normalizeExamData(parsedData);
    if (!exam.questions.length) {
        renderFailure("برای این آزمون هنوز سوالی ثبت نشده است.");
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var cohortKey = String(params.get("cohort") || "").trim();
    var storageBase = "dent1402:exam:" + window.location.pathname + ":" + cohortKey;
    var assessmentDraftKey = storageBase + ":assessment";
    var learningDraftKey = storageBase + ":learning";
    var guestFlagsKey = storageBase + ":flags";
    var selectedMode = normalizeMode(params.get("mode"));
    var initialFlags = exam.viewerState.canPersist
        ? exam.viewerState.flaggedQuestionIndexes.slice()
        : restoreFlagIndexes(guestFlagsKey);
    var initialReport = exam.viewerState.assessmentReport;
    var state = {
        feedback: { kind: "", text: "" },
        mode: selectedMode,
        flags: new Set(initialFlags),
        flagsSync: { saving: false, queued: false, timer: 0 },
        assessment: restoreAssessmentState(assessmentDraftKey, exam.questions.length, initialReport),
        learning: restoreLearningState(learningDraftKey, exam.questions.length),
        layout: { chooserHintExpanded: false }
    };

    state.assessment.answers = clampAnswers(state.assessment.answers, exam.questions);
    state.learning.answers = clampAnswers(state.learning.answers, exam.questions);
    state.learning.revealed = clampRevealed(state.learning.revealed, exam.questions.length);
    if (!selectedMode) {
        state.mode = null;
    }
    syncModeInUrl();

    appRoot.addEventListener("click", handleClick);
    appRoot.addEventListener("change", handleChange);
    window.addEventListener("beforeunload", flushFlagSync);

    render();

    function renderFailure(message) {
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<main class="exam-main">',
            '  <section class="exam-panel exam-empty-state">',
            "    <h1>خطا در بارگذاری آزمون</h1>",
            "    <p>" + escapeHtml(message) + "</p>",
            '    <a class="back-btn" href="' + escapeHtml(exam.backHref || "/exams/") + '">بازگشت</a>',
            "  </section>",
            "</main>"
        ].join("");
    }

    function render() {
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <main class="exam-main">',
                 renderHero(),
                 renderModeRail(),
                 !state.mode ? renderChooser() : renderActiveMode(),
            "  </main>",
            '  <footer class="exam-footer">',
            '    <p class="exam-footer-text">' + escapeHtml(footerText()) + "</p>",
            "  </footer>",
            "</div>"
        ].join("");
    }

    function renderHero() {
        var report = state.assessment.report;
        return [
            '<section class="exam-panel exam-hero">',
            '  <div class="exam-hero-top">',
            '    <a class="back-btn exam-back-link" href="' + escapeHtml(exam.backHref) + '">',
            '      <span class="back-icon" aria-hidden="true">←</span>',
            '      <span>' + escapeHtml(exam.backLabel) + "</span>",
            "    </a>",
            '    <div class="exam-hero-badges">',
            '      <span class="exam-chip">' + escapeHtml(exam.eyebrow) + "</span>",
            '      <span class="exam-chip">' + escapeHtml(formatValue(exam.questions.length)) + " سوال</span>",
            state.flags.size ? '<span class="exam-chip exam-chip--flagged">' + escapeHtml(formatValue(state.flags.size)) + " نشان‌دار</span>" : "",
            report ? '<span class="exam-chip exam-chip--accent">کارنامه ذخیره‌شده</span>' : "",
            "    </div>",
            "  </div>",
            '  <div class="exam-hero-copy">',
            '    <h1 class="exam-title">' + escapeHtml(exam.title) + "</h1>",
            '    <p class="exam-subtitle">' + escapeHtml(exam.subtitle) + "</p>",
            '    <div class="exam-hero-meta">',
            '      <span class="exam-hero-meta__item">' + escapeHtml(exam.courseTitle || exam.eyebrow) + "</span>",
            state.feedback.text ? '<span class="exam-feedback exam-feedback--' + escapeHtml(state.feedback.kind || "neutral") + '">' + escapeHtml(state.feedback.text) + "</span>" : "",
            state.flagsSync.saving ? '<span class="exam-feedback exam-feedback--neutral">در حال ذخیره نشان‌دارها...</span>' : "",
            "    </div>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderModeRail() {
        var isCompact = !!state.mode;
        return [
            '<section class="exam-panel exam-mode-rail' + (isCompact ? " is-compact" : "") + '">',
            '  <div class="exam-mode-rail__copy">',
            '    <span class="exam-kicker">نحوه شرکت در آزمون</span>',
            isCompact ? "" : '    <h2 class="exam-section-title">قبل از شروع، حالت مناسب را انتخاب کن.</h2>',
            isCompact
                ? '    <p class="exam-section-copy">حالت فعال را همین‌جا عوض کن.</p>'
                : '    <p class="exam-section-copy">دو مسیر مستقل برای همین آزمون فعال است: سنجشی برای کارنامه و رتبه، آموزشی برای پاسخ فوری و مرور قدم‌به‌قدم.</p>',
            "  </div>",
            '  <div class="exam-mode-switch">',
            renderModeRailButton(null, "انتخاب حالت"),
            renderModeRailButton("assessment", "سنجشی"),
            renderModeRailButton("learning", "آموزشی"),
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderModeRailButton(mode, label) {
        var isActive = state.mode === mode || (!state.mode && mode === null);
        return [
            '<button class="exam-mode-switch__btn' + (isActive ? " is-active" : "") + '" type="button" data-action="set-mode"' + (mode ? ' data-mode="' + escapeHtml(mode) + '"' : "") + ">",
            escapeHtml(label),
            "</button>"
        ].join("");
    }

    function renderChooser() {
        var assessmentReport = state.assessment.report;
        return [
            '<section class="exam-panel exam-mode-chooser">',
            '  <div class="exam-mode-chooser__head">',
            '    <div>',
            '      <span class="exam-kicker">دو حالت آزمون</span>',
            '      <h2 class="exam-section-title">همین جلسه را چطور می‌خواهی شروع کنی؟</h2>',
            '      <p class="exam-section-copy">این انتخاب فقط روی نحوه اجرا اثر می‌گذارد. سوال‌ها مشترک‌اند اما تجربه کاربری و ثبت نتیجه متفاوت است.</p>',
            "    </div>",
            '    <button class="exam-inline-link" type="button" data-action="toggle-chooser-hint">' + escapeHtml(state.layout.chooserHintExpanded ? "بستن توضیح" : "تفاوت دو حالت") + "</button>",
            "  </div>",
            state.layout.chooserHintExpanded ? (
                '<div class="exam-inline-note">در حالت سنجشی همه سوالات یکجا نمایش داده می‌شود و بعد از ثبت آزمون، کارنامه، رتبه و میانگین کلی همان لحظه ذخیره و نمایش داده می‌شود. در حالت آموزشی سوال‌ها یکی‌یکی پیش می‌روند و پس از هر پاسخ، جواب درست و پاسخ تشریحی همان سوال دیده می‌شود.</div>'
            ) : "",
            '  <div class="exam-mode-grid">',
                 renderModeCard("assessment", assessmentReport),
                 renderModeCard("learning", null),
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderModeCard(mode, assessmentReport) {
        var definition = modeDefinition(mode);
        var isAssessment = mode === "assessment";
        var requiresLogin = isAssessment && !exam.viewerState.canPersist;
        var buttonLabel = requiresLogin
            ? "ورود برای کارنامه"
            : (isAssessment && assessmentReport ? "مشاهده کارنامه" : "شروع " + definition.title);
        var actionHtml = requiresLogin
            ? '<a class="exam-btn exam-btn--primary" href="' + escapeHtml(loginHref()) + '">ورود برای کارنامه</a>'
            : '<button class="exam-btn exam-btn--primary" type="button" data-action="set-mode" data-mode="' + escapeHtml(mode) + '">' + escapeHtml(buttonLabel) + "</button>";
        var meta = [];
        if (isAssessment && assessmentReport) {
            meta.push('<span class="exam-mini-stat">درصد: ' + escapeHtml(formatPercent(assessmentReport.percent)) + "</span>");
            meta.push('<span class="exam-mini-stat">صحیح: ' + escapeHtml(formatValue(assessmentReport.correct)) + "</span>");
        } else if (isAssessment) {
            meta.push('<span class="exam-mini-stat">کارنامه ذخیره می‌شود</span>');
            meta.push('<span class="exam-mini-stat">رتبه با حداقل ۱۰ شرکت‌کننده</span>');
        } else {
            meta.push('<span class="exam-mini-stat">پاسخ فوری بعد از هر سوال</span>');
            meta.push('<span class="exam-mini-stat">بدون صدور کارنامه</span>');
        }

        return [
            '<article class="exam-mode-card' + (isAssessment ? " exam-mode-card--accent" : "") + '">',
            '  <div class="exam-mode-card__head">',
            '    <div>',
            '      <span class="exam-mode-card__eyebrow">' + escapeHtml(definition.tagline) + "</span>",
            '      <h3 class="exam-mode-card__title">' + escapeHtml(definition.title) + "</h3>",
            "    </div>",
            isAssessment && assessmentReport ? '<span class="exam-status-pill exam-status-pill--success">ذخیره‌شده</span>' : "",
            "  </div>",
            '  <p class="exam-mode-card__copy">' + escapeHtml(definition.description) + "</p>",
            requiresLogin ? '<p class="exam-mode-card__hint">برای ذخیره کارنامه و رتبه باید اول وارد حساب کاربری شوی.</p>' : "",
            '  <div class="exam-mini-stats">' + meta.join("") + "</div>",
            '  <div class="exam-mode-card__actions">' + actionHtml + "</div>",
            "</article>"
        ].join("");
    }

    function renderActiveMode() {
        if (state.mode === "assessment") {
            if (!exam.viewerState.canPersist) {
                return renderAssessmentLoginGate();
            }
            return renderAssessmentMode();
        }

        if (state.mode === "learning") {
            return renderLearningMode();
        }

        return renderChooser();
    }

    function renderAssessmentLoginGate() {
        return [
            '<section class="exam-panel exam-empty-state">',
            '  <h2 class="exam-section-title">برای حالت سنجشی باید وارد حساب شوی.</h2>',
            '  <p class="exam-section-copy">کارنامه، رتبه و میانگین کلی فقط روی حساب کاربری ثبت می‌شود. بعد از ورود، همین آزمون را در حالت سنجشی شروع کن.</p>',
            '  <div class="exam-inline-actions">',
            '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(loginHref()) + '">ورود به حساب</a>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به حالت آموزشی</button>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentMode() {
        var report = state.assessment.report;
        var totals = report ? reportTotals(report) : assessmentDraftTotals();
        var filter = state.assessment.filter;
        var visibleIndexes = assessmentVisibleIndexes(filter);
        return [
            '<section class="exam-layout exam-layout--assessment">',
            '  <div class="exam-primary-stack">',
                 renderAssessmentSummary(report, totals),
                 renderAssessmentToolbar(report, totals, visibleIndexes.length),
                 visibleIndexes.length ? renderAssessmentQuestionList(visibleIndexes, report) : renderFilteredEmptyState("هیچ سوالی با این فیلتر پیدا نشد."),
            "  </div>",
            '  <aside class="exam-panel exam-sidebar">',
                 renderAssessmentSidebar(report, totals),
            "  </aside>",
            "</section>"
        ].join("");
    }

    function renderAssessmentSummary(report, totals) {
        var cards = [
            renderSummaryCard("کل سوال‌ها", formatValue(exam.questions.length)),
            renderSummaryCard(report ? "پاسخ صحیح" : "پاسخ داده‌شده", formatValue(report ? totals.correct : totals.answered)),
            renderSummaryCard(report ? "پاسخ غلط" : "بی‌پاسخ", formatValue(report ? totals.wrong : totals.unanswered)),
            renderSummaryCard("نشان‌دار", formatValue(state.flags.size))
        ];
        if (report) {
            cards.unshift(renderSummaryCard("درصد", formatPercent(report.percent), "is-accent"));
            if (report.showRank) {
                cards.push(renderSummaryCard("رتبه", formatValue(report.rank) + " از " + formatValue(report.participantCount)));
            }
            if (report.overallAveragePercent !== null && report.overallAveragePercent !== undefined) {
                cards.push(renderSummaryCard("میانگین همه آزمون‌ها", formatPercent(report.overallAveragePercent)));
            }
        }

        return [
            '<section class="exam-panel exam-summary-panel exam-summary-panel--compact">',
            '  <div class="exam-summary-panel__head">',
            '    <div>',
            '      <span class="exam-kicker">' + escapeHtml(report ? "کارنامه ذخیره‌شده" : "آماده ثبت") + "</span>",
            '      <h2 class="exam-section-title">' + escapeHtml(report ? "نتیجه این آزمون روی حساب شما ثبت شده است." : "حالت سنجشی: همه سوالات یکجا در اختیار توست.") + "</h2>",
            '      <p class="exam-section-copy">' + escapeHtml(report ? "بعد از ریست کارنامه می‌توانی دوباره این آزمون را از اول بدهی." : "می‌توانی بین سوال‌ها جابه‌جا شوی، سوال‌ها را نشان‌دار کنی و هر زمان خواستی آزمون را ثبت کنی.") + "</p>",
            "    </div>",
            report ? '<span class="exam-summary-timestamp">ثبت: ' + escapeHtml(formatDateTime(report.submittedAt)) + "</span>" : "",
            "  </div>",
            '  <div class="exam-summary-grid">' + cards.join("") + "</div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentToolbar(report, totals, visibleCount) {
        return [
            '<section class="exam-panel exam-toolbar-panel">',
            '  <div class="exam-toolbar">',
            '    <div class="exam-toolbar__copy">',
            '      <span class="exam-toolbar__title">' + escapeHtml(report ? "فیلتر مرور پاسخ‌ها" : "فیلتر سوال‌ها") + "</span>",
            '      <span class="exam-toolbar__subtitle">' + escapeHtml("در حال نمایش " + formatValue(visibleCount) + " سوال") + "</span>",
            "    </div>",
            '    <div class="exam-filter-row">' + renderAssessmentFilterButtons(report, totals) + "</div>",
            "  </div>",
            report ? (
                '<div class="exam-inline-actions">' +
                '<button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به حالت آموزشی</button>' +
                '<button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report">ریست کارنامه و شروع دوباره</button>' +
                "</div>"
            ) : (
                '<div class="exam-inline-actions">' +
                '<button class="exam-btn exam-btn--ghost" type="button" data-action="reset-assessment-draft">پاک‌کردن پاسخ‌های سنجشی</button>' +
                '<button class="exam-btn exam-btn--primary" type="button" data-action="submit-assessment"' + (state.assessment.submitting ? " disabled" : "") + ">" + escapeHtml(state.assessment.submitting ? "در حال ثبت..." : "ثبت آزمون") + "</button>" +
                "</div>"
            ),
            "</section>"
        ].join("");
    }

    function renderAssessmentFilterButtons(report, totals) {
        var filters = report
            ? [
                { key: "all", label: "همه", count: exam.questions.length },
                { key: "correct", label: "صحیح", count: totals.correct },
                { key: "wrong", label: "غلط", count: totals.wrong },
                { key: "unanswered", label: "بی‌پاسخ", count: totals.unanswered },
                { key: "flagged", label: "نشان‌دار", count: state.flags.size }
            ]
            : [
                { key: "all", label: "همه", count: exam.questions.length },
                { key: "answered", label: "پاسخ‌داده‌شده", count: totals.answered },
                { key: "unanswered", label: "بی‌پاسخ", count: totals.unanswered },
                { key: "flagged", label: "نشان‌دار", count: state.flags.size }
            ];

        return filters.map(function (item) {
            return '<button class="exam-filter-chip' + (state.assessment.filter === item.key ? " is-active" : "") + '" type="button" data-action="assessment-filter" data-filter="' + escapeHtml(item.key) + '">' +
                '<span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(formatValue(item.count)) + "</strong></button>";
        }).join("");
    }

    function renderAssessmentQuestionList(indexes, report) {
        return [
            '<section class="exam-question-list">',
            indexes.map(function (questionIndex) {
                return renderAssessmentQuestionCard(questionIndex, report);
            }).join(""),
            "</section>"
        ].join("");
    }

    function renderAssessmentQuestionCard(questionIndex, report) {
        var question = exam.questions[questionIndex];
        var selectedIndex = report
            ? report.answers[questionIndex]
            : state.assessment.answers[questionIndex];
        var cardState = report
            ? assessmentReviewState(questionIndex, selectedIndex)
            : assessmentDraftState(selectedIndex);

        return [
            '<article class="exam-panel exam-question-card" data-card-state="' + escapeHtml(cardState) + '" data-question-anchor="' + escapeHtml(String(questionIndex)) + '">',
            '  <div class="exam-question-card__head">',
            '    <div class="exam-question-card__title-wrap">',
            '      <span class="exam-question-number">سوال ' + escapeHtml(formatValue(questionIndex + 1)) + "</span>",
            '      <span class="exam-question-state">' + escapeHtml(assessmentStateLabel(cardState)) + "</span>",
            "    </div>",
            '    <button class="exam-flag-btn' + (isFlagged(questionIndex) ? " is-active" : "") + '" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + escapeHtml(isFlagged(questionIndex) ? "نشان‌دار شده" : "نشان‌دار کن") + "</button>",
            "  </div>",
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
                 question.options.map(function (option, optionIndex) {
                    return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, !!report);
                 }).join(""),
            "  </div>",
            report && question.explanation ? '<div class="exam-explanation-block"><span class="exam-explanation-block__label">پاسخ تشریحی</span><div class="exam-explanation-block__copy">' + richTextHtml(question.explanation) + "</div></div>" : "",
            "</article>"
        ].join("");
    }

    function renderAssessmentOption(questionIndex, optionIndex, optionText, selectedIndex, correctIndex, reviewMode) {
        var stateName = "neutral";
        var tag = "";
        if (reviewMode) {
            if (optionIndex === correctIndex && selectedIndex === optionIndex) {
                stateName = "user-correct";
                tag = "پاسخ درست تو";
            } else if (optionIndex === correctIndex) {
                stateName = "correct";
                tag = "پاسخ درست";
            } else if (selectedIndex === optionIndex) {
                stateName = "user-wrong";
                tag = "انتخاب تو";
            }
        } else if (selectedIndex === optionIndex) {
            stateName = "selected";
        }

        return [
            '<button class="exam-option' + (selectedIndex === optionIndex ? " is-selected" : "") + (reviewMode ? " is-results-mode" : "") + '" type="button" data-action="' + (reviewMode ? "noop" : "assessment-answer") + '" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-option-index="' + escapeHtml(String(optionIndex)) + '" data-state="' + escapeHtml(stateName) + '"' + (reviewMode ? " disabled" : "") + ">",
            '  <span class="exam-option-marker">' + escapeHtml(optionLetter(optionIndex)) + "</span>",
            '  <span class="exam-option-copy">' + richTextHtml(optionText) + "</span>",
            tag ? '  <span class="exam-option-tag">' + escapeHtml(tag) + "</span>" : "",
            "</button>"
        ].join("");
    }

    function renderAssessmentSidebar(report, totals) {
        return [
            '<div class="exam-sidebar-section">',
            '  <div class="exam-sidebar-head">',
            '    <h3 class="exam-sidebar-title">وضعیت سنجشی</h3>',
            '    <span class="exam-sidebar-note">' + escapeHtml(report ? "کارنامه روی حساب شما ذخیره شده است." : "تا قبل از ثبت، پاسخ‌ها فقط روی همین دستگاه نگه‌داری می‌شوند.") + "</span>",
            "  </div>",
            '  <div class="exam-stat-grid">',
            renderSidebarStat("کل", exam.questions.length),
            renderSidebarStat(report ? "صحیح" : "پاسخ", report ? totals.correct : totals.answered),
            renderSidebarStat(report ? "غلط" : "بی‌پاسخ", report ? totals.wrong : totals.unanswered),
            renderSidebarStat("نشان‌دار", state.flags.size),
            "  </div>",
            "</div>",
            '<details class="exam-sidebar-section exam-sidebar-details">',
            '  <summary>جهش سریع به سوال‌ها</summary>',
            '  <span class="exam-sidebar-note">برای رفتن مستقیم به هر سوال روی شماره آن بزن.</span>',
            '  <div class="exam-nav-grid">' + renderJumpButtons(exam.questions.length) + "</div>",
            "</details>"
        ].join("");
    }

    function renderLearningMode() {
        var stats = learningTotals();
        var visibleIndexes = learningVisibleIndexes();
        var currentIndex = ensureLearningIndex(visibleIndexes);
        return [
            '<section class="exam-layout exam-layout--learning">',
            '  <div class="exam-primary-stack">',
                 renderLearningSummary(stats, currentIndex),
                 visibleIndexes.length ? renderLearningQuestion(currentIndex, stats) : renderFilteredEmptyState("هنوز سوال نشان‌دار نشده است."),
            "  </div>",
            '  <aside class="exam-panel exam-sidebar">',
                 renderLearningSidebar(stats, visibleIndexes, currentIndex),
            "  </aside>",
            "</section>"
        ].join("");
    }

    function renderLearningSummary(stats, currentIndex) {
        var question = exam.questions[currentIndex];
        return [
            '<section class="exam-panel exam-summary-panel exam-summary-panel--compact">',
            '  <div class="exam-summary-panel__head">',
            '    <div>',
            '      <span class="exam-kicker">حالت آموزشی</span>',
            '      <h2 class="exam-section-title">سوال‌ها را قدم‌به‌قدم حل کن و همان‌جا پاسخ درست را ببین.</h2>',
            '      <p class="exam-section-copy">در این حالت کارنامه صادر نمی‌شود. هر سوال را می‌توانی نشان‌دار کنی و بعداً دوباره سراغش برگردی.</p>',
            "    </div>",
            '    <span class="exam-summary-timestamp">سوال ' + escapeHtml(formatValue(currentIndex + 1)) + " از " + escapeHtml(formatValue(exam.questions.length)) + "</span>",
            "  </div>",
            '  <div class="exam-summary-grid">',
                 renderSummaryCard("حل‌شده", formatValue(stats.answered)),
                 renderSummaryCard("باقی‌مانده", formatValue(stats.unanswered)),
                 renderSummaryCard("نشان‌دار", formatValue(state.flags.size)),
                 renderSummaryCard("فیلتر فعال", state.learning.filter === "flagged" ? "نشان‌دارها" : "همه"),
            "  </div>",
            question && state.learning.answers[currentIndex] !== null ? '<div class="exam-inline-note">بعد از انتخاب پاسخ، گزینه صحیح و پاسخ تشریحی همان سوال بلافاصله نمایش داده می‌شود.</div>' : "",
            "</section>"
        ].join("");
    }

    function renderLearningQuestion(currentIndex, stats) {
        var question = exam.questions[currentIndex];
        var selectedIndex = state.learning.answers[currentIndex];
        var revealed = state.learning.revealed[currentIndex];
        return [
            '<article class="exam-panel exam-learning-card">',
            '  <div class="exam-question-card__head">',
            '    <div class="exam-question-card__title-wrap">',
            '      <span class="exam-question-number">سوال ' + escapeHtml(formatValue(currentIndex + 1)) + "</span>",
            '      <span class="exam-question-state">' + escapeHtml(revealed ? learningAnswerLabel(question, selectedIndex) : "در انتظار پاسخ") + "</span>",
            "    </div>",
            '    <button class="exam-flag-btn' + (isFlagged(currentIndex) ? " is-active" : "") + '" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(currentIndex)) + '">' + escapeHtml(isFlagged(currentIndex) ? "نشان‌دار شده" : "نشان‌دار کن") + "</button>",
            "  </div>",
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
                 question.options.map(function (option, optionIndex) {
                    return renderLearningOption(currentIndex, optionIndex, option, selectedIndex, question.correctIndex, revealed);
                 }).join(""),
            "  </div>",
            revealed ? renderLearningFeedback(question, selectedIndex, currentIndex) : '<div class="exam-inline-note">پاسخ خودت را انتخاب کن تا جواب صحیح و توضیح تشریحی نمایش داده شود.</div>',
            '  <div class="exam-inline-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-prev"' + (currentIndex <= 0 ? " disabled" : "") + ">سوال قبلی</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-jump-unanswered"' + (stats.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="learning-next"' + (currentIndex >= exam.questions.length - 1 ? " disabled" : "") + ">سوال بعدی</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderLearningOption(questionIndex, optionIndex, optionText, selectedIndex, correctIndex, revealed) {
        var stateName = "neutral";
        var tag = "";
        if (revealed) {
            if (optionIndex === correctIndex && selectedIndex === optionIndex) {
                stateName = "user-correct";
                tag = "پاسخ درست تو";
            } else if (optionIndex === correctIndex) {
                stateName = "correct";
                tag = "پاسخ درست";
            } else if (selectedIndex === optionIndex) {
                stateName = "user-wrong";
                tag = "انتخاب تو";
            }
        } else if (selectedIndex === optionIndex) {
            stateName = "selected";
        }

        return [
            '<button class="exam-option' + (selectedIndex === optionIndex ? " is-selected" : "") + (revealed ? " is-results-mode" : "") + '" type="button" data-action="' + (revealed ? "noop" : "learning-answer") + '" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-option-index="' + escapeHtml(String(optionIndex)) + '" data-state="' + escapeHtml(stateName) + '"' + (revealed ? " disabled" : "") + ">",
            '  <span class="exam-option-marker">' + escapeHtml(optionLetter(optionIndex)) + "</span>",
            '  <span class="exam-option-copy">' + richTextHtml(optionText) + "</span>",
            tag ? '  <span class="exam-option-tag">' + escapeHtml(tag) + "</span>" : "",
            "</button>"
        ].join("");
    }

    function renderLearningFeedback(question, selectedIndex, questionIndex) {
        var isCorrect = selectedIndex === question.correctIndex;
        return [
            '<div class="exam-learning-feedback' + (isCorrect ? " is-correct" : " is-wrong") + '">',
            '  <span class="exam-learning-feedback__title">' + escapeHtml(isCorrect ? "پاسخ تو درست بود." : "پاسخ صحیح مشخص شد.") + "</span>",
            '  <p class="exam-learning-feedback__copy">پاسخ صحیح این سوال گزینه ' + escapeHtml(optionLetter(question.correctIndex)) + ' است.</p>',
            question.explanation ? '<div class="exam-explanation-block"><span class="exam-explanation-block__label">پاسخ تشریحی</span><div class="exam-explanation-block__copy">' + richTextHtml(question.explanation) + "</div></div>" : "",
            isFlagged(questionIndex) ? '<div class="exam-inline-note">این سوال نشان‌دار شده و بعداً از فیلتر نشان‌دارها سریع پیدایش می‌کنی.</div>' : "",
            "</div>"
        ].join("");
    }

    function renderLearningSidebar(stats, visibleIndexes, currentIndex) {
        return [
            '<div class="exam-sidebar-section">',
            '  <div class="exam-sidebar-head">',
            '    <h3 class="exam-sidebar-title">مرور آموزشی</h3>',
            '    <span class="exam-sidebar-note">با فیلتر نشان‌دارها می‌توانی فقط سوال‌های علامت‌خورده را دوباره ببینی.</span>',
            "  </div>",
            '  <div class="exam-filter-row">',
            renderLearningFilterButton("all", "همه", exam.questions.length),
            renderLearningFilterButton("flagged", "نشان‌دار", state.flags.size),
            "  </div>",
            '  <div class="exam-stat-grid">',
            renderSidebarStat("حل‌شده", stats.answered),
            renderSidebarStat("بی‌پاسخ", stats.unanswered),
            renderSidebarStat("نشان‌دار", state.flags.size),
            renderSidebarStat("جاری", currentIndex + 1),
            "  </div>",
            "</div>",
            '<details class="exam-sidebar-section exam-sidebar-details">',
            '  <summary>جهش سریع به سوال‌ها</summary>',
            '  <span class="exam-sidebar-note">شماره‌ها وضعیت پاسخ‌دهی و نشان‌دار بودن را نشان می‌دهند.</span>',
            '  <div class="exam-nav-grid">' + renderLearningJumpButtons(visibleIndexes, currentIndex) + "</div>",
            "</details>"
        ].join("");
    }

    function renderLearningFilterButton(filter, label, count) {
        return '<button class="exam-filter-chip' + (state.learning.filter === filter ? " is-active" : "") + '" type="button" data-action="learning-filter" data-filter="' + escapeHtml(filter) + '">' +
            '<span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(formatValue(count)) + "</strong></button>";
    }

    function renderFilteredEmptyState(copy) {
        return [
            '<section class="exam-panel exam-empty-state">',
            '  <h2 class="exam-section-title">نمایش خالی شد.</h2>',
            '  <p class="exam-section-copy">' + escapeHtml(copy) + "</p>",
            '  <div class="exam-inline-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-filters">حذف فیلترها</button>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderSummaryCard(label, value, extraClass) {
        return [
            '<article class="exam-summary-card' + (extraClass ? " " + extraClass : "") + '">',
            '  <span class="exam-summary-card__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-summary-card__value">' + escapeHtml(String(value)) + "</strong>",
            "</article>"
        ].join("");
    }

    function renderSidebarStat(label, value) {
        return [
            '<div class="exam-stat-card">',
            '  <span class="exam-stat-label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-stat-value">' + escapeHtml(formatValue(value)) + "</strong>",
            "</div>"
        ].join("");
    }

    function renderJumpButtons(totalQuestions) {
        var buttons = [];
        for (var index = 0; index < totalQuestions; index++) {
            buttons.push(
                '<button class="exam-nav-btn' + (isFlagged(index) ? " is-flagged" : "") + '" type="button" data-action="jump-to-question" data-question-index="' + escapeHtml(String(index)) + '" data-state="' + escapeHtml(assessmentNavState(index)) + '">' +
                escapeHtml(formatValue(index + 1)) +
                "</button>"
            );
        }
        return buttons.join("");
    }

    function renderLearningJumpButtons(visibleIndexes, currentIndex) {
        return visibleIndexes.map(function (index) {
            return '<button class="exam-nav-btn' + (index === currentIndex ? " is-active" : "") + (isFlagged(index) ? " is-flagged" : "") + '" type="button" data-action="learning-goto" data-question-index="' + escapeHtml(String(index)) + '" data-state="' + escapeHtml(learningNavState(index)) + '">' +
                escapeHtml(formatValue(index + 1)) +
                "</button>";
        }).join("");
    }

    function handleClick(event) {
        var actionNode = event.target.closest("[data-action]");
        if (!actionNode) {
            return;
        }

        var action = String(actionNode.getAttribute("data-action") || "").trim();
        if (!action || action === "noop") {
            return;
        }

        if (action === "set-mode") {
            setMode(normalizeMode(actionNode.getAttribute("data-mode")));
            return;
        }
        if (action === "toggle-chooser-hint") {
            state.layout.chooserHintExpanded = !state.layout.chooserHintExpanded;
            render();
            return;
        }
        if (action === "toggle-flag") {
            toggleFlag(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "assessment-filter") {
            state.assessment.filter = String(actionNode.getAttribute("data-filter") || "all");
            render();
            return;
        }
        if (action === "learning-filter") {
            state.learning.filter = String(actionNode.getAttribute("data-filter") || "all");
            ensureLearningIndex(learningVisibleIndexes());
            persistLearningState();
            render();
            return;
        }
        if (action === "assessment-answer") {
            selectAssessmentAnswer(
                parseIndex(actionNode.getAttribute("data-question-index")),
                parseIndex(actionNode.getAttribute("data-option-index"))
            );
            return;
        }
        if (action === "learning-answer") {
            selectLearningAnswer(
                parseIndex(actionNode.getAttribute("data-question-index")),
                parseIndex(actionNode.getAttribute("data-option-index"))
            );
            return;
        }
        if (action === "reset-assessment-draft") {
            resetAssessmentDraft();
            return;
        }
        if (action === "submit-assessment") {
            submitAssessment();
            return;
        }
        if (action === "reset-assessment-report") {
            resetAssessmentReport();
            return;
        }
        if (action === "jump-to-question") {
            jumpToAssessmentQuestion(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "learning-goto") {
            jumpToLearningQuestion(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "learning-next") {
            moveLearning(1);
            return;
        }
        if (action === "learning-prev") {
            moveLearning(-1);
            return;
        }
        if (action === "learning-jump-unanswered") {
            jumpLearningToFirstUnanswered();
            return;
        }
        if (action === "clear-filters") {
            state.assessment.filter = "all";
            state.learning.filter = "all";
            render();
        }
    }

    function handleChange(event) {
        var target = event.target;
        if (!target || target.nodeType !== 1) {
            return;
        }
    }

    function setMode(mode) {
        state.mode = mode;
        clearFeedback();
        syncModeInUrl();
        render();
    }

    function syncModeInUrl() {
        var nextParams = new URLSearchParams(window.location.search);
        if (state.mode) {
            nextParams.set("mode", state.mode);
        } else {
            nextParams.delete("mode");
        }

        var nextSearch = nextParams.toString();
        var nextUrl = window.location.pathname + (nextSearch ? "?" + nextSearch : "");
        window.history.replaceState(null, "", nextUrl);
    }

    function selectAssessmentAnswer(questionIndex, optionIndex) {
        if (state.assessment.report) {
            return;
        }
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.assessment.answers[questionIndex] = optionIndex;
        persistAssessmentState();
        render();
    }

    function selectLearningAnswer(questionIndex, optionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.learning.answers[questionIndex] = optionIndex;
        state.learning.revealed[questionIndex] = true;
        state.learning.currentQuestionIndex = questionIndex;
        persistLearningState();
        render();
    }

    function resetAssessmentDraft() {
        if (state.assessment.report) {
            return;
        }

        if (!window.confirm("تمام پاسخ‌های سنجشی این آزمون پاک شود؟")) {
            return;
        }

        state.assessment.answers = createNullArray(exam.questions.length);
        state.assessment.startedAt = new Date().toISOString();
        state.assessment.filter = "all";
        clearFeedback();
        persistAssessmentState();
        render();
    }

    function submitAssessment() {
        if (!exam.viewerState.canPersist || state.assessment.submitting || state.assessment.report) {
            return;
        }

        var totals = assessmentDraftTotals();
        var unanswered = totals.unanswered;
        if (unanswered > 0) {
            var confirmed = window.confirm("هنوز " + formatValue(unanswered) + " سوال بی‌پاسخ مانده است. آزمون با همین وضعیت ثبت شود؟");
            if (!confirmed) {
                return;
            }
        }

        state.assessment.submitting = true;
        clearFeedback();
        render();

        apiPost("submitAssessment", {
            course: exam.courseSlug,
            exam: exam.slug,
            answers: JSON.stringify(state.assessment.answers),
            startedAt: state.assessment.startedAt
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.report) {
                throw new Error((payload && payload.error) || "ثبت آزمون انجام نشد.");
            }

            state.assessment.report = normalizeReport(payload.report, exam.questions);
            state.assessment.answers = state.assessment.report.answers.slice();
            state.assessment.filter = "all";
            state.assessment.submitting = false;
            window.sessionStorage.removeItem(assessmentDraftKey);
            setFeedback("success", payload.message || "کارنامه این آزمون ذخیره شد.");
            render();
            window.scrollTo({ top: 0, behavior: "smooth" });
        }).catch(function (error) {
            state.assessment.submitting = false;
            setFeedback("error", error && error.message ? error.message : "ثبت آزمون انجام نشد.");
            render();
        });
    }

    function resetAssessmentReport() {
        if (!state.assessment.report) {
            return;
        }

        if (!window.confirm("کارنامه این آزمون پاک شود تا دوباره از اول آزمون بدهی؟")) {
            return;
        }

        apiPost("resetAssessment", {
            course: exam.courseSlug,
            exam: exam.slug
        }).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "ریست کارنامه انجام نشد.");
            }

            state.assessment.report = null;
            state.assessment.answers = createNullArray(exam.questions.length);
            state.assessment.startedAt = new Date().toISOString();
            state.assessment.filter = "all";
            persistAssessmentState();
            setFeedback("success", payload.message || "کارنامه این آزمون ریست شد.");
            render();
            window.scrollTo({ top: 0, behavior: "smooth" });
        }).catch(function (error) {
            setFeedback("error", error && error.message ? error.message : "ریست کارنامه انجام نشد.");
            render();
        });
    }

    function jumpToAssessmentQuestion(questionIndex) {
        var target = appRoot.querySelector('[data-question-anchor="' + String(questionIndex) + '"]');
        if (target) {
            target.scrollIntoView({ behavior: "smooth", block: "start" });
            return;
        }

        var cards = appRoot.querySelectorAll(".exam-question-card");
        var card = cards[questionIndex] || null;
        if (card && typeof card.scrollIntoView === "function") {
            card.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    function jumpToLearningQuestion(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.learning.currentQuestionIndex = questionIndex;
        persistLearningState();
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function moveLearning(step) {
        var visibleIndexes = learningVisibleIndexes();
        var currentIndex = ensureLearningIndex(visibleIndexes);
        var currentPosition = visibleIndexes.indexOf(currentIndex);
        if (currentPosition === -1) {
            currentPosition = 0;
        }

        var nextPosition = currentPosition + step;
        if (nextPosition < 0 || nextPosition >= visibleIndexes.length) {
            return;
        }

        state.learning.currentQuestionIndex = visibleIndexes[nextPosition];
        persistLearningState();
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function jumpLearningToFirstUnanswered() {
        var index = state.learning.answers.findIndex(function (answer) {
            return answer === null;
        });
        if (index < 0) {
            return;
        }

        state.learning.currentQuestionIndex = index;
        state.learning.filter = "all";
        persistLearningState();
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function toggleFlag(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        if (state.flags.has(questionIndex)) {
            state.flags.delete(questionIndex);
        } else {
            state.flags.add(questionIndex);
        }

        if (exam.viewerState.canPersist) {
            scheduleFlagSync();
        } else {
            persistGuestFlags();
        }
        clearFeedback();
        render();
    }

    function scheduleFlagSync() {
        if (state.flagsSync.timer) {
            window.clearTimeout(state.flagsSync.timer);
        }

        state.flagsSync.queued = true;
        state.flagsSync.timer = window.setTimeout(function () {
            state.flagsSync.timer = 0;
            flushFlagSync();
        }, 280);
    }

    function flushFlagSync() {
        if (!exam.viewerState.canPersist || !state.flagsSync.queued || state.flagsSync.saving) {
            return;
        }

        state.flagsSync.saving = true;
        state.flagsSync.queued = false;
        render();

        apiPost("saveFlags", {
            course: exam.courseSlug,
            exam: exam.slug,
            flaggedQuestionIndexes: JSON.stringify(Array.from(state.flags).sort(function (left, right) {
                return left - right;
            }))
        }).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "ذخیره نشان‌دارها انجام نشد.");
            }

            state.flagsSync.saving = false;
            render();
        }).catch(function (error) {
            state.flagsSync.saving = false;
            setFeedback("error", error && error.message ? error.message : "ذخیره نشان‌دارها انجام نشد.");
            render();
        });
    }

    function persistGuestFlags() {
        try {
            window.localStorage.setItem(guestFlagsKey, JSON.stringify(Array.from(state.flags)));
        } catch (_error) {
            return;
        }
    }

    function persistAssessmentState() {
        if (state.assessment.report) {
            try {
                window.sessionStorage.removeItem(assessmentDraftKey);
            } catch (_error) {
                return;
            }
            return;
        }

        try {
            window.sessionStorage.setItem(assessmentDraftKey, JSON.stringify({
                answers: state.assessment.answers,
                startedAt: state.assessment.startedAt,
                filter: state.assessment.filter
            }));
        } catch (_error) {
            return;
        }
    }

    function persistLearningState() {
        try {
            window.sessionStorage.setItem(learningDraftKey, JSON.stringify({
                answers: state.learning.answers,
                revealed: state.learning.revealed,
                currentQuestionIndex: state.learning.currentQuestionIndex,
                filter: state.learning.filter
            }));
        } catch (_error) {
            return;
        }
    }

    function assessmentDraftTotals() {
        var answered = 0;
        state.assessment.answers.forEach(function (answer) {
            if (answer !== null) {
                answered++;
            }
        });

        return {
            answered: answered,
            unanswered: exam.questions.length - answered
        };
    }

    function reportTotals(report) {
        return {
            correct: Number(report.correct || 0),
            wrong: Number(report.wrong || 0),
            unanswered: Number(report.unanswered || 0)
        };
    }

    function learningTotals() {
        var answered = 0;
        state.learning.answers.forEach(function (answer) {
            if (answer !== null) {
                answered++;
            }
        });

        return {
            answered: answered,
            unanswered: exam.questions.length - answered
        };
    }

    function assessmentVisibleIndexes(filter) {
        var indexes = [];
        for (var index = 0; index < exam.questions.length; index++) {
            if (assessmentMatchesFilter(index, filter)) {
                indexes.push(index);
            }
        }
        return indexes;
    }

    function assessmentMatchesFilter(questionIndex, filter) {
        var report = state.assessment.report;
        var selectedIndex = report ? report.answers[questionIndex] : state.assessment.answers[questionIndex];
        var stateName = report
            ? assessmentReviewState(questionIndex, selectedIndex)
            : assessmentDraftState(selectedIndex);
        if (!filter || filter === "all") {
            return true;
        }
        if (filter === "flagged") {
            return isFlagged(questionIndex);
        }
        if (filter === "answered") {
            return selectedIndex !== null;
        }
        if (filter === "unanswered") {
            return stateName === "unanswered";
        }
        if (filter === "correct") {
            return stateName === "correct";
        }
        if (filter === "wrong") {
            return stateName === "wrong";
        }
        return true;
    }

    function learningVisibleIndexes() {
        var indexes = [];
        for (var index = 0; index < exam.questions.length; index++) {
            if (state.learning.filter === "flagged" && !isFlagged(index)) {
                continue;
            }
            indexes.push(index);
        }
        return indexes;
    }

    function ensureLearningIndex(visibleIndexes) {
        var indexes = visibleIndexes && visibleIndexes.length ? visibleIndexes : learningVisibleIndexes();
        if (!indexes.length) {
            return 0;
        }

        if (indexes.indexOf(state.learning.currentQuestionIndex) === -1) {
            state.learning.currentQuestionIndex = indexes[0];
            persistLearningState();
        }
        return state.learning.currentQuestionIndex;
    }

    function assessmentDraftState(selectedIndex) {
        return selectedIndex === null ? "unanswered" : "answered";
    }

    function assessmentReviewState(questionIndex, selectedIndex) {
        if (selectedIndex === null) {
            return "unanswered";
        }

        return selectedIndex === exam.questions[questionIndex].correctIndex ? "correct" : "wrong";
    }

    function assessmentNavState(questionIndex) {
        var report = state.assessment.report;
        var selectedIndex = report ? report.answers[questionIndex] : state.assessment.answers[questionIndex];
        return report
            ? assessmentReviewState(questionIndex, selectedIndex)
            : assessmentDraftState(selectedIndex);
    }

    function learningNavState(questionIndex) {
        var selectedIndex = state.learning.answers[questionIndex];
        if (selectedIndex === null) {
            return "unanswered";
        }

        if (!state.learning.revealed[questionIndex]) {
            return "answered";
        }

        return selectedIndex === exam.questions[questionIndex].correctIndex ? "correct" : "wrong";
    }

    function assessmentStateLabel(stateName) {
        if (stateName === "correct") {
            return "صحیح";
        }
        if (stateName === "wrong") {
            return "غلط";
        }
        if (stateName === "answered") {
            return "پاسخ داده شده";
        }
        return "بی‌پاسخ";
    }

    function learningAnswerLabel(question, selectedIndex) {
        if (selectedIndex === null) {
            return "در انتظار پاسخ";
        }
        return selectedIndex === question.correctIndex ? "پاسخ درست" : "نیاز به مرور";
    }

    function setFeedback(kind, text) {
        state.feedback.kind = kind || "neutral";
        state.feedback.text = text || "";
    }

    function clearFeedback() {
        setFeedback("", "");
    }

    function footerText() {
        if (state.mode === "assessment" && state.assessment.report) {
            return "کارنامه این آزمون برای حساب شما ذخیره شده است و بعد از ریست می‌توانی دوباره شرکت کنی.";
        }
        if (state.mode === "learning") {
            return "در حالت آموزشی بعد از هر پاسخ، جواب درست و توضیح تشریحی همان سوال نمایش داده می‌شود.";
        }
        return exam.footerText;
    }

    function modeDefinition(mode) {
        var items = exam.modes || [];
        for (var index = 0; index < items.length; index++) {
            if (items[index].key === mode) {
                return items[index];
            }
        }
        return {
            key: mode || "",
            title: mode === "learning" ? "آزمون آموزشی" : "آزمون سنجشی",
            tagline: "",
            description: ""
        };
    }

    function isFlagged(questionIndex) {
        return state.flags.has(questionIndex);
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        return fetch("/api/exams_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: body.toString()
        }).then(parseJson);
    }

    function withCohort(payload) {
        var next = Object.assign({}, payload || {});
        if (cohortKey) {
            next.cohort = cohortKey;
        }
        return next;
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

    function normalizeExamData(data) {
        var viewerState = isObject(data.viewerState) ? data.viewerState : {};
        var normalizedQuestions = (Array.isArray(data.questions) ? data.questions : []).map(normalizeQuestion).filter(function (question) {
            return question.question && question.options.length;
        });
        var report = normalizeReport(viewerState.assessmentReport, normalizedQuestions);

        return {
            slug: normalizeText(data.slug),
            courseSlug: normalizeText(data.courseSlug || data.course || (document.body && document.body.dataset ? document.body.dataset.examsCourse : "")),
            courseTitle: normalizeText(data.courseTitle),
            footerText: normalizeText(data.footerText) || "طراحی شده برای مرور، سنجش و یادگیری مرحله‌ای.",
            backHref: normalizeText(data.backHref) || "/exams/",
            backLabel: normalizeText(data.backLabel) || "بازگشت به آزمون‌ها",
            eyebrow: normalizeText(data.eyebrow) || "آزمون",
            title: normalizeText(data.title) || "آزمون",
            subtitle: normalizeText(data.subtitle) || "پیش از شروع، حالت دلخواهت را انتخاب کن.",
            modes: Array.isArray(data.modes) ? data.modes : [],
            questions: normalizedQuestions,
            viewerState: {
                canPersist: Boolean(viewerState.canPersist),
                flaggedQuestionIndexes: normalizeFlagIndexes(viewerState.flaggedQuestionIndexes, normalizedQuestions.length),
                assessmentReport: report
            }
        };
    }

    function normalizeQuestion(item) {
        var rawOptions = Array.isArray(item.options) ? item.options.slice(0, 8) : [];
        var options = rawOptions.map(function (option) {
            return normalizeText(option);
        });

        return {
            question: stripQuestionNumber(normalizeText(item.question || item.text || "")),
            options: options,
            correctIndex: clampCorrectIndex(item.correctIndex, options.length),
            explanation: normalizeText(item.explanation || ""),
            useCompactOptions: shouldUseCompactOptions(options)
        };
    }

    function normalizeReport(rawReport, questions) {
        if (!isObject(rawReport)) {
            return null;
        }

        var totalQuestions = Array.isArray(questions) ? questions.length : 0;
        return {
            answers: clampAnswers(Array.isArray(rawReport.answers) ? rawReport.answers : createNullArray(totalQuestions), Array.isArray(questions) ? questions : []),
            totalQuestions: maxNumber(rawReport.totalQuestions, totalQuestions),
            correct: maxNumber(rawReport.correct, 0),
            wrong: maxNumber(rawReport.wrong, 0),
            unanswered: maxNumber(rawReport.unanswered, 0),
            percent: clampPercent(rawReport.percent),
            startedAt: normalizeText(rawReport.startedAt),
            submittedAt: normalizeText(rawReport.submittedAt),
            updatedAt: normalizeText(rawReport.updatedAt),
            rank: rawReport.rank === null || rawReport.rank === undefined ? null : maxNumber(rawReport.rank, 0),
            participantCount: maxNumber(rawReport.participantCount, 0),
            showRank: Boolean(rawReport.showRank),
            overallCompletedExams: maxNumber(rawReport.overallCompletedExams, 0),
            overallAveragePercent: rawReport.overallAveragePercent === null || rawReport.overallAveragePercent === undefined
                ? null
                : clampPercent(rawReport.overallAveragePercent)
        };
    }

    function restoreAssessmentState(key, totalQuestions, report) {
        var empty = {
            answers: createNullArray(totalQuestions),
            startedAt: new Date().toISOString(),
            filter: "all",
            report: report,
            submitting: false
        };

        if (report) {
            empty.answers = report.answers.slice();
            return empty;
        }

        try {
            var saved = JSON.parse(window.sessionStorage.getItem(key) || "null");
            if (!isObject(saved)) {
                return empty;
            }
            empty.answers = clampAnswers(Array.isArray(saved.answers) ? saved.answers : empty.answers, exam.questions);
            empty.startedAt = normalizeText(saved.startedAt) || empty.startedAt;
            empty.filter = normalizeAssessmentFilter(saved.filter);
        } catch (_error) {
            return empty;
        }

        return empty;
    }

    function restoreLearningState(key, totalQuestions) {
        var empty = {
            answers: createNullArray(totalQuestions),
            revealed: createFalseArray(totalQuestions),
            currentQuestionIndex: 0,
            filter: "all"
        };

        try {
            var saved = JSON.parse(window.sessionStorage.getItem(key) || "null");
            if (!isObject(saved)) {
                return empty;
            }

            empty.answers = clampAnswers(Array.isArray(saved.answers) ? saved.answers : empty.answers, exam.questions);
            empty.revealed = clampRevealed(Array.isArray(saved.revealed) ? saved.revealed : empty.revealed, totalQuestions);
            empty.currentQuestionIndex = clampIndex(saved.currentQuestionIndex, totalQuestions);
            empty.filter = normalizeLearningFilter(saved.filter);
        } catch (_error) {
            return empty;
        }

        return empty;
    }

    function restoreFlagIndexes(key) {
        try {
            return normalizeFlagIndexes(JSON.parse(window.localStorage.getItem(key) || "[]"), exam.questions.length);
        } catch (_error) {
            return [];
        }
    }

    function normalizeFlagIndexes(value, totalQuestions) {
        if (!Array.isArray(value)) {
            return [];
        }

        var result = [];
        value.forEach(function (item) {
            var index = parseIndex(item);
            if (index >= 0 && index < totalQuestions && result.indexOf(index) === -1) {
                result.push(index);
            }
        });
        result.sort(function (left, right) {
            return left - right;
        });
        return result;
    }

    function clampAnswers(answers, questions) {
        return createNullArray(questions.length).map(function (_item, index) {
            var answer = Array.isArray(answers) ? answers[index] : null;
            var optionCount = questions[index] ? questions[index].options.length : 0;
            return Number.isInteger(answer) && answer >= 0 && answer < optionCount ? answer : null;
        });
    }

    function clampRevealed(revealed, totalQuestions) {
        return createFalseArray(totalQuestions).map(function (_item, index) {
            return Array.isArray(revealed) ? Boolean(revealed[index]) : false;
        });
    }

    function clampIndex(value, totalQuestions) {
        var index = parseIndex(value);
        if (index < 0) {
            return 0;
        }
        if (index >= totalQuestions) {
            return Math.max(0, totalQuestions - 1);
        }
        return index;
    }

    function normalizeAssessmentFilter(value) {
        var filter = String(value || "all");
        return ["all", "answered", "unanswered", "flagged", "correct", "wrong"].indexOf(filter) >= 0 ? filter : "all";
    }

    function normalizeLearningFilter(value) {
        return String(value || "all") === "flagged" ? "flagged" : "all";
    }

    function normalizeMode(value) {
        var mode = String(value || "").trim().toLowerCase();
        return mode === "assessment" || mode === "learning" ? mode : null;
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function richTextHtml(text) {
        return String(text || "")
            .split(/(\*\*[^*]+\*\*)/g)
            .map(function (part) {
                if (!part) {
                    return "";
                }
                if (part.indexOf("**") === 0 && part.lastIndexOf("**") === part.length - 2) {
                    return "<strong>" + escapeHtml(part.slice(2, -2)).replace(/\n/g, "<br>") + "</strong>";
                }
                return escapeHtml(part).replace(/\n/g, "<br>");
            })
            .join("");
    }

    function shouldUseCompactOptions(options) {
        if (options.length < 2 || options.length > 4) {
            return false;
        }

        return options.every(function (option) {
            return option.length <= 95 && option.indexOf("\n") === -1;
        });
    }

    function normalizeText(value) {
        return String(value || "")
            .replace(/\r\n?/g, "\n")
            .replace(/\u00a0/g, " ")
            .trim();
    }

    function stripQuestionNumber(value) {
        return value.replace(/^\s*[0-9۰-۹]+\s*[\)\-–]\s*/, "");
    }

    function clampCorrectIndex(value, optionCount) {
        var parsed = Number(value);
        if (!Number.isInteger(parsed) || parsed < 0 || parsed >= optionCount) {
            return 0;
        }
        return parsed;
    }

    function createNullArray(length) {
        return new Array(length).fill(null);
    }

    function createFalseArray(length) {
        return new Array(length).fill(false);
    }

    function optionLetter(index) {
        return ["الف", "ب", "ج", "د", "هـ", "و", "ز", "ح"][index] || formatValue(index + 1);
    }

    function formatValue(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatPercent(value) {
        var numeric = clampPercent(value);
        var hasFraction = Math.abs(numeric - Math.round(numeric)) > 0.001;
        return numeric.toLocaleString("fa-IR", {
            minimumFractionDigits: hasFraction ? 1 : 0,
            maximumFractionDigits: 1
        }) + "٪";
    }

    function formatDateTime(value) {
        var raw = normalizeText(value);
        if (!raw) {
            return "—";
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

    function clampPercent(value) {
        var numeric = Number(value || 0);
        if (!Number.isFinite(numeric)) {
            return 0;
        }
        if (numeric < 0) {
            return 0;
        }
        if (numeric > 100) {
            return 100;
        }
        return Math.round(numeric * 10) / 10;
    }

    function maxNumber(value, fallback) {
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return fallback;
        }
        return Math.max(fallback, numeric);
    }

    function parseIndex(value) {
        var parsed = Number(value);
        return Number.isInteger(parsed) ? parsed : -1;
    }

    function isValidQuestionIndex(index) {
        return Number.isInteger(index) && index >= 0 && index < exam.questions.length;
    }

    function isObject(value) {
        return !!value && typeof value === "object" && !Array.isArray(value);
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case '"':
                    return "&quot;";
                default:
                    return char;
            }
        });
    }
})();
