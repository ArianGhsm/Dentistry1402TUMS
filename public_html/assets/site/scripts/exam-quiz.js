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
    var activityTrackedMode = "";
    var layoutFrame = 0;
    var delayedLayoutTimer = 0;
    var BIDI_LTR_RUN_RE = /[\p{Script=Latin}0-9][\p{Script=Latin}0-9/%&+_.:=,\-]*(?:\s+[\p{Script=Latin}0-9][\p{Script=Latin}0-9/%&+_.:=,\-]*)*/gu;

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
    window.addEventListener("resize", scheduleLayoutSync);
    window.addEventListener("orientationchange", scheduleLayoutSync);
    window.addEventListener("load", scheduleLayoutSync);

    render();

    window.setTimeout(scheduleLayoutSync, 40);
    window.setTimeout(scheduleLayoutSync, 180);
    window.setTimeout(scheduleLayoutSync, 520);

    function renderFailure(message) {
        document.body.classList.add("quiz-stage-active");
        var fallbackBackHref = exam && exam.backHref ? exam.backHref : "/exams/";
        var fallbackBackLabel = exam && exam.backLabel ? exam.backLabel : "بازگشت";
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <main class="exam-main">',
            '    <section class="exam-stage-shell">',
            '      <div class="exam-stage-scaler">',
            '        <div class="exam-stage-canvas">',
            '          <section class="exam-panel exam-stage exam-stage--message">',
            '            <a class="back-btn exam-back-link" href="' + escapeHtml(fallbackBackHref) + '">',
            '              <span class="back-icon" aria-hidden="true">←</span>',
            '              <span>' + escapeHtml(fallbackBackLabel) + "</span>",
            "            </a>",
            '            <div class="exam-message-card">',
            "              <h1>خطا در بارگذاری آزمون</h1>",
            "              <p>" + escapeHtml(message) + "</p>",
            "            </div>",
            "          </section>",
            "        </div>",
            "      </div>",
            "    </section>",
            "  </main>",
            "</div>"
        ].join("");
        scheduleLayoutSync();
    }

    function render() {
        document.body.classList.add("quiz-stage-active");
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <main class="exam-main">',
            '    <section class="exam-stage-shell">',
            '      <div class="exam-stage-scaler">',
            '        <div class="exam-stage-canvas">',
            !state.mode || !isModeStarted(state.mode) ? renderLaunchStage() : renderActiveStage(),
            "        </div>",
            "      </div>",
            "    </section>",
            "  </main>",
            "</div>"
        ].join("");

        scheduleLayoutSync();
    }

    function renderLaunchStage() {
        var selected = state.mode ? modeDefinition(state.mode) : null;
        var report = state.assessment.report;
        var learningStats = learningTotals();
        var assessmentStats = report ? reportTotals(report) : assessmentDraftTotals();
        var launchMeta = [
            renderMetaChip(formatValue(exam.questions.length) + " سوال", "neutral"),
            state.flags.size ? renderMetaChip(formatValue(state.flags.size) + " نشان‌دار", "flagged") : "",
            report ? renderMetaChip("کارنامه ذخیره‌شده", "success") : ""
        ].filter(Boolean);
        var previewTitle = selected ? selected.title : "اول حالت آزمون را انتخاب کن";
        var previewCopy = selected
            ? selected.description
            : "برای همین جلسه دو مسیر جدا در دسترس است: آموزشی برای پاسخ فوری و سنجشی برای ثبت کارنامه.";
        var previewStats = [];

        if (state.mode === "assessment") {
            if (report) {
                previewStats.push(renderMiniStat("درصد", formatPercent(report.percent)));
                previewStats.push(renderMiniStat("صحیح", formatValue(report.correct)));
                previewStats.push(renderMiniStat("غلط", formatValue(report.wrong)));
            } else {
                previewStats.push(renderMiniStat("پاسخ‌داده‌شده", formatValue(assessmentStats.answered)));
                previewStats.push(renderMiniStat("بی‌پاسخ", formatValue(assessmentStats.unanswered)));
                previewStats.push(renderMiniStat("کارنامه", exam.viewerState.canPersist ? "فعال" : "نیاز به ورود"));
            }
        } else if (state.mode === "learning") {
            previewStats.push(renderMiniStat("حل‌شده", formatValue(learningStats.answered)));
            previewStats.push(renderMiniStat("باقی‌مانده", formatValue(learningStats.unanswered)));
            previewStats.push(renderMiniStat("پاسخ فوری", "فعال"));
        }

        return [
            '<section class="exam-panel exam-stage exam-stage--launch">',
            renderLaunchHeader(launchMeta),
            '  <div class="exam-launch-grid">',
            '    <section class="exam-launch-copy">',
            '      <span class="exam-kicker">شروع آزمون</span>',
            '      <h1 class="exam-stage-title">' + escapeHtml(exam.title) + "</h1>",
            '      <p class="exam-stage-subtitle">' + escapeHtml(exam.subtitle) + "</p>",
            "    </section>",
            '    <section class="exam-launch-panel">',
            '      <div class="exam-launch-panel__head">',
            '        <div>',
            '          <span class="exam-launch-panel__eyebrow">انتخاب حالت</span>',
            '          <h2 class="exam-launch-panel__title">' + escapeHtml(previewTitle) + "</h2>",
            "        </div>",
            '        <div class="exam-mode-pills">',
            renderModePill("assessment", "سنجشی", !state.mode),
            renderModePill("learning", "آموزشی", !state.mode),
            "        </div>",
            "      </div>",
            '      <p class="exam-launch-panel__copy">' + escapeHtml(previewCopy) + "</p>",
            previewStats.length ? '<div class="exam-mini-stats">' + previewStats.join("") + "</div>" : "",
            state.layout.chooserHintExpanded
                ? '<div class="exam-note-card">در حالت سنجشی همه سوال‌ها با کارنامه، رتبه و ذخیره نتیجه اجرا می‌شود. در حالت آموزشی پس از هر پاسخ، جواب درست و توضیح همان سوال را می‌بینی.</div>'
                : "",
            renderLaunchActions(),
            "    </section>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderLaunchHeader(launchMeta) {
        return [
            '  <div class="exam-stage-head">',
            '    <div class="exam-stage-head__main">',
            '      <a class="back-btn exam-back-link" href="' + escapeHtml(exam.backHref) + '">',
            '        <span class="back-icon" aria-hidden="true">←</span>',
            "        " + renderResponsiveLabel(exam.backLabel, "\u0628\u0627\u0632\u06af\u0634\u062a"),
            "      </a>",
            '      <div class="exam-stage-head__copy">',
            '        <span class="exam-kicker">' + escapeHtml(exam.courseTitle || exam.eyebrow) + "</span>",
            '        <div class="exam-meta-strip">' + launchMeta.join("") + "</div>",
            "      </div>",
            "    </div>",
            state.feedback.text ? '<div class="exam-feedback exam-feedback--' + escapeHtml(state.feedback.kind || "neutral") + '">' + escapeHtml(state.feedback.text) + "</div>" : "",
            "  </div>"
        ].join("");
    }

    function renderLaunchActions() {
        var currentMode = state.mode;
        var report = state.assessment.report;
        var assessmentStats = report ? reportTotals(report) : assessmentDraftTotals();
        var learningStats = learningTotals();

        if (!currentMode) {
            return [
                '  <div class="exam-launch-actions">',
                '    <button class="exam-btn exam-btn--primary" type="button" disabled>ابتدا حالت آزمون را انتخاب کن</button>',
                '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-chooser-hint">تفاوت دو حالت</button>',
                "  </div>"
            ].join("");
        }

        if (currentMode === "assessment" && !exam.viewerState.canPersist) {
            return [
                '  <div class="exam-launch-actions">',
                '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(loginHref()) + '">ورود برای حالت سنجشی</a>',
                '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به حالت آموزشی</button>',
                "  </div>"
            ].join("");
        }

        var primaryLabel = "";
        if (currentMode === "assessment") {
            if (report) {
                primaryLabel = "مشاهده کارنامه سنجشی";
            } else if (assessmentStats.answered > 0) {
                primaryLabel = "ادامه آزمون سنجشی";
            } else {
                primaryLabel = "شروع آزمون سنجشی";
            }
        } else if (learningStats.answered > 0) {
            primaryLabel = "ادامه آزمون آموزشی";
        } else {
            primaryLabel = "شروع آزمون آموزشی";
        }

        return [
            '  <div class="exam-launch-actions">',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="start-mode"' + (currentMode ? ' data-mode="' + escapeHtml(currentMode) + '"' : "") + ">" + escapeHtml(primaryLabel) + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-chooser-hint">' + escapeHtml(state.layout.chooserHintExpanded ? "بستن توضیح" : "تفاوت دو حالت") + "</button>",
            "  </div>"
        ].join("");
    }

    function renderModePill(mode, label, ghostWhenEmpty) {
        return [
            '<button class="exam-mode-pill' + (state.mode === mode ? " is-active" : "") + (ghostWhenEmpty ? " is-neutral" : "") + '" type="button" data-action="set-mode" data-mode="' + escapeHtml(mode) + '">',
            escapeHtml(label),
            "</button>"
        ].join("");
    }

    function renderActiveStage() {
        if (state.mode === "assessment") {
            if (!exam.viewerState.canPersist) {
                return renderLaunchStage();
            }
            return state.assessment.report ? renderAssessmentReportStage() : renderAssessmentDraftStage();
        }

        if (state.mode === "learning") {
            return renderLearningStage();
        }

        return renderLaunchStage();
    }

    function renderAssessmentDraftStage() {
        var totals = assessmentDraftTotals();
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = ensureAssessmentIndex(visibleIndexes);

        return [
            '<section class="exam-panel exam-stage exam-stage--session exam-stage--assessment">',
            renderSessionHeader({
                mode: "assessment",
                title: "آزمون سنجشی",
                subtitle: "همه سوال‌ها در همین حالت ذخیره می‌شوند و بعد از ثبت، کارنامه جداگانه نمایش داده می‌شود.",
                chips: [
                    renderMetaChip("سوال " + formatValue(currentIndex + 1) + " از " + formatValue(exam.questions.length), "neutral"),
                    renderMetaChip("پاسخ‌داده‌شده " + formatValue(totals.answered), "neutral"),
                    renderMetaChip("بی‌پاسخ " + formatValue(totals.unanswered), totals.unanswered ? "warning" : "success"),
                    state.flags.size ? renderMetaChip(formatValue(state.flags.size) + " نشان‌دار", "flagged") : ""
                ],
                statusText: state.flagsSync.saving ? "در حال ذخیره نشان‌دارها..." : state.feedback.text,
                statusKind: state.flagsSync.saving ? "neutral" : state.feedback.kind
            }),
            renderQuestionRail({
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState,
                emptyLabel: "نمایش خالی شده است."
            }),
            renderAssessmentDraftCompactPanel(totals, visibleIndexes.length),
            visibleIndexes.length ? [
                '<div class="exam-stage-body">',
                renderAssessmentDraftQuestionCard(currentIndex),
                renderAssessmentDraftSidePanel(totals, visibleIndexes.length),
                "</div>"
            ].join("") : renderStageEmptyState("هیچ سوالی با این فیلتر پیدا نشد."),
            "</section>"
        ].join("");
    }

    function renderAssessmentReportStage() {
        var report = state.assessment.report;
        var totals = reportTotals(report);
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = ensureAssessmentIndex(visibleIndexes);

        return [
            '<section class="exam-panel exam-stage exam-stage--session exam-stage--report">',
            renderSessionHeader({
                mode: "assessment",
                title: "کارنامه سنجشی",
                subtitle: "نتیجه این آزمون ذخیره شده است. از نوار بالایی سوال‌ها را جابه‌جا کن و پاسخ‌ها را مرور کن.",
                chips: [
                    renderMetaChip("درصد " + formatPercent(report.percent), "success"),
                    renderMetaChip("صحیح " + formatValue(totals.correct), "success"),
                    renderMetaChip("غلط " + formatValue(totals.wrong), totals.wrong ? "danger" : "neutral"),
                    renderMetaChip("بی‌پاسخ " + formatValue(totals.unanswered), totals.unanswered ? "warning" : "neutral")
                ],
                statusText: state.feedback.text,
                statusKind: state.feedback.kind
            }),
            renderQuestionRail({
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState,
                emptyLabel: "در این فیلتر سوالی باقی نمانده است."
            }),
            renderAssessmentReportCompactPanel(report),
            visibleIndexes.length ? [
                '<div class="exam-stage-body exam-stage-body--report">',
                renderAssessmentReportQuestionCard(currentIndex, report),
                renderAssessmentReportSidePanel(report),
                "</div>"
            ].join("") : renderStageEmptyState("در این فیلتر سوالی برای مرور باقی نمانده است."),
            "</section>"
        ].join("");
    }

    function renderLearningStage() {
        var stats = learningTotals();
        var visibleIndexes = learningVisibleIndexes();
        var currentIndex = ensureLearningIndex(visibleIndexes);

        return [
            '<section class="exam-panel exam-stage exam-stage--session exam-stage--learning">',
            renderSessionHeader({
                mode: "learning",
                title: "آزمون آموزشی",
                subtitle: "بعد از هر پاسخ، جواب درست و توضیح همان سوال بدون خروج از همین صفحه نمایش داده می‌شود.",
                chips: [
                    renderMetaChip("سوال " + formatValue(currentIndex + 1) + " از " + formatValue(exam.questions.length), "neutral"),
                    renderMetaChip("حل‌شده " + formatValue(stats.answered), "success"),
                    renderMetaChip("باقی‌مانده " + formatValue(stats.unanswered), stats.unanswered ? "warning" : "success"),
                    state.flags.size ? renderMetaChip(formatValue(state.flags.size) + " نشان‌دار", "flagged") : ""
                ],
                statusText: state.feedback.text,
                statusKind: state.feedback.kind
            }),
            renderQuestionRail({
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "learning-prev",
                nextAction: "learning-next",
                jumpAction: "learning-goto",
                stateResolver: learningNavState,
                emptyLabel: "هنوز سوال نشان‌داری برای این نما وجود ندارد."
            }),
            renderLearningCompactPanel(stats, currentIndex),
            visibleIndexes.length ? [
                '<div class="exam-stage-body">',
                renderLearningQuestionCard(currentIndex),
                renderLearningSidePanel(stats, currentIndex, visibleIndexes.length),
                "</div>"
            ].join("") : renderStageEmptyState("هنوز سوالی در این فیلتر باقی نمانده است."),
            "</section>"
        ].join("");
    }

    function renderSessionHeader(config) {
        return [
            '  <div class="exam-stage-head exam-stage-head--session">',
            '    <div class="exam-stage-head__main">',
            '      <a class="back-btn exam-back-link" href="' + escapeHtml(exam.backHref) + '">',
            '        <span class="back-icon" aria-hidden="true">←</span>',
            "        " + renderResponsiveLabel(exam.backLabel, "\u0628\u0627\u0632\u06af\u0634\u062a"),
            "      </a>",
            '      <div class="exam-stage-head__copy">',
            '        <span class="exam-kicker">' + escapeHtml(exam.eyebrow) + "</span>",
            '        <h2 class="exam-stage-title exam-stage-title--compact">' + escapeHtml(exam.title) + "</h2>",
            '        <p class="exam-stage-subtitle exam-stage-subtitle--compact">' + escapeHtml(config.subtitle) + "</p>",
            "      </div>",
            "    </div>",
            '    <div class="exam-stage-head__aside">',
            '      <div class="exam-mode-pills exam-mode-pills--compact">',
            renderModePill("assessment", "سنجشی", false),
            renderModePill("learning", "آموزشی", false),
            "      </div>",
            config.statusText ? '<div class="exam-feedback exam-feedback--' + escapeHtml(config.statusKind || "neutral") + '">' + escapeHtml(config.statusText) + "</div>" : "",
            "    </div>",
            "  </div>",
            '  <div class="exam-meta-strip exam-meta-strip--session">' + (config.chips || []).join("") + "</div>"
        ].join("");
    }

    function renderQuestionRail(config) {
        var indexes = config.visibleIndexes || [];
        var railItems = buildRailItems(indexes, config.currentIndex, railWindowSize());
        var currentPosition = indexes.indexOf(config.currentIndex);
        var stateFn = typeof config.stateResolver === "function" ? config.stateResolver : function () {
            return "neutral";
        };
        var trackHtml = railItems.map(function (item) {
            if (item.type === "ellipsis") {
                return '<span class="exam-rail__ellipsis" aria-hidden="true">…</span>';
            }

            return [
                '<button class="exam-rail__pill' + (item.index === config.currentIndex ? " is-active" : "") + (isFlagged(item.index) ? " is-flagged" : "") + '" type="button" data-action="' + escapeHtml(config.jumpAction) + '" data-question-index="' + escapeHtml(String(item.index)) + '" data-state="' + escapeHtml(stateFn(item.index)) + '">',
                escapeHtml(formatValue(item.index + 1)),
                "</button>"
            ].join("");
        }).join("");

        return [
            '  <div class="exam-question-rail">',
            '    <div class="exam-question-rail__meta">',
            '      <strong>' + escapeHtml("شماره سوال") + "</strong>",
            '      <span>' + escapeHtml(indexes.length ? ("نمایش " + formatValue(currentPosition + 1) + " از " + formatValue(indexes.length)) : config.emptyLabel) + "</span>",
            "    </div>",
            '    <div class="exam-question-rail__bar">',
            '      <button class="exam-rail__nav" type="button" data-action="' + escapeHtml(config.prevAction) + '"' + (indexes.length < 2 || currentPosition <= 0 ? " disabled" : "") + ">قبلی</button>",
            '      <div class="exam-question-rail__track">' + trackHtml + "</div>",
            '      <button class="exam-rail__nav" type="button" data-action="' + escapeHtml(config.nextAction) + '"' + (indexes.length < 2 || currentPosition >= indexes.length - 1 ? " disabled" : "") + ">بعدی</button>",
            "    </div>",
            "  </div>"
        ].join("");
    }

    function renderAssessmentDraftQuestionCard(questionIndex) {
        var question = exam.questions[questionIndex];
        var selectedIndex = state.assessment.answers[questionIndex];
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentPosition = visibleIndexes.indexOf(questionIndex);

        return [
            '<article class="exam-session-card exam-session-card--question" data-card-state="' + escapeHtml(assessmentDraftState(selectedIndex)) + '">',
            renderQuestionCardHead(questionIndex, assessmentStateLabel(assessmentDraftState(selectedIndex))),
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
            question.options.map(function (option, optionIndex) {
                return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, false);
            }).join(""),
            "  </div>",
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + renderResponsiveLabel(isFlagged(questionIndex) ? "\u062d\u0630\u0641 \u0646\u0634\u0627\u0646" : "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631 \u06a9\u0646", isFlagged(questionIndex) ? "\u062d\u0630\u0641" : "\u0646\u0634\u0627\u0646") + "</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="assessment-next"' + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0628\u0639\u062f\u06cc", "\u0628\u0639\u062f\u06cc") + "</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderAssessmentReportQuestionCard(questionIndex, report) {
        var question = exam.questions[questionIndex];
        var selectedIndex = report.answers[questionIndex];
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentPosition = visibleIndexes.indexOf(questionIndex);
        var stateName = assessmentReviewState(questionIndex, selectedIndex);

        return [
            '<article class="exam-session-card exam-session-card--question" data-card-state="' + escapeHtml(stateName) + '">',
            renderQuestionCardHead(questionIndex, assessmentStateLabel(stateName)),
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
            question.options.map(function (option, optionIndex) {
                return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, true);
            }).join(""),
            "  </div>",
            question.explanation ? '<div class="exam-answer-card"><span class="exam-answer-card__label">پاسخ تشریحی</span><div class="exam-answer-card__copy">' + richTextHtml(question.explanation) + "</div></div>" : "",
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + renderResponsiveLabel(isFlagged(questionIndex) ? "\u062d\u0630\u0641 \u0646\u0634\u0627\u0646" : "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631 \u06a9\u0646", isFlagged(questionIndex) ? "\u062d\u0630\u0641" : "\u0646\u0634\u0627\u0646") + "</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="assessment-next"' + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0628\u0639\u062f\u06cc", "\u0628\u0639\u062f\u06cc") + "</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderLearningQuestionCard(questionIndex) {
        var question = exam.questions[questionIndex];
        var selectedIndex = state.learning.answers[questionIndex];
        var revealed = state.learning.revealed[questionIndex];
        var visibleIndexes = learningVisibleIndexes();
        var currentPosition = visibleIndexes.indexOf(questionIndex);

        return [
            '<article class="exam-session-card exam-session-card--question" data-card-state="' + escapeHtml(learningNavState(questionIndex)) + '">',
            renderQuestionCardHead(questionIndex, revealed ? learningAnswerLabel(question, selectedIndex) : "در انتظار پاسخ"),
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
            question.options.map(function (option, optionIndex) {
                return renderLearningOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, revealed);
            }).join(""),
            "  </div>",
            revealed ? renderLearningFeedback(question, selectedIndex, questionIndex) : '<div class="exam-note-card">یکی از گزینه‌ها را انتخاب کن تا پاسخ صحیح و توضیح همان سوال نمایش داده شود.</div>',
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + renderResponsiveLabel(isFlagged(questionIndex) ? "\u062d\u0630\u0641 \u0646\u0634\u0627\u0646" : "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631 \u06a9\u0646", isFlagged(questionIndex) ? "\u062d\u0630\u0641" : "\u0646\u0634\u0627\u0646") + "</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="learning-next"' + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0628\u0639\u062f\u06cc", "\u0628\u0639\u062f\u06cc") + "</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderQuestionCardHead(questionIndex, stateLabel) {
        return [
            '  <div class="exam-question-card__head">',
            '    <div class="exam-question-card__title-wrap">',
            '      <span class="exam-question-number">سوال ' + escapeHtml(formatValue(questionIndex + 1)) + "</span>",
            '      <span class="exam-question-state">' + escapeHtml(stateLabel) + "</span>",
            "    </div>",
            '    <span class="exam-question-flag' + (isFlagged(questionIndex) ? " is-active" : "") + '">' + escapeHtml(isFlagged(questionIndex) ? "نشان‌دار" : "عادی") + "</span>",
            "  </div>"
        ].join("");
    }

    function renderAssessmentDraftSidePanel(totals, visibleCount) {
        return [
            '<aside class="exam-session-card exam-session-card--side">',
            '  <div class="exam-side-section">',
            '    <span class="exam-kicker">وضعیت سنجشی</span>',
            '    <div class="exam-stat-grid">',
            renderStatCard("کل", formatValue(exam.questions.length)),
            renderStatCard("پاسخ‌داده", formatValue(totals.answered)),
            renderStatCard("بی‌پاسخ", formatValue(totals.unanswered)),
            renderStatCard("نشان‌دار", formatValue(state.flags.size)),
            "    </div>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر نمایش</span>',
            '    <div class="exam-filter-pills">' + renderAssessmentFilterButtons(false) + "</div>",
            '    <p class="exam-side-copy">در حال نمایش ' + escapeHtml(formatValue(visibleCount)) + ' سوال از این نما هستی.</p>',
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-first-unanswered"' + (totals.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-assessment-draft">پاک‌کردن پاسخ‌ها</button>',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="submit-assessment"' + (state.assessment.submitting ? " disabled" : "") + ">" + escapeHtml(state.assessment.submitting ? "در حال ثبت..." : "ثبت آزمون") + "</button>",
            "  </div>",
            "</aside>"
        ].join("");
    }

    function renderAssessmentReportSidePanel(report) {
        var extraStats = [];
        if (report.showRank) {
            extraStats.push(renderStatCard("رتبه", formatValue(report.rank) + " از " + formatValue(report.participantCount)));
        }
        if (report.overallAveragePercent !== null && report.overallAveragePercent !== undefined) {
            extraStats.push(renderStatCard("میانگین کل", formatPercent(report.overallAveragePercent)));
        }

        return [
            '<aside class="exam-session-card exam-session-card--side exam-session-card--report-side">',
            '  <div class="exam-report-score">',
            '    <span class="exam-report-score__label">درصد این آزمون</span>',
            '    <strong class="exam-report-score__value">' + escapeHtml(formatPercent(report.percent)) + "</strong>",
            '    <span class="exam-report-score__meta">ثبت شده در ' + escapeHtml(formatDateTime(report.submittedAt)) + "</span>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <div class="exam-stat-grid">',
            renderStatCard("صحیح", formatValue(report.correct)),
            renderStatCard("غلط", formatValue(report.wrong)),
            renderStatCard("بی‌پاسخ", formatValue(report.unanswered)),
            renderStatCard("نشان‌دار", formatValue(state.flags.size)),
            extraStats.join(""),
            "    </div>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر مرور</span>',
            '    <div class="exam-filter-pills">' + renderAssessmentFilterButtons(true) + "</div>",
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به آموزشی</button>',
            '    <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report">ریست کارنامه</button>',
            "  </div>",
            "</aside>"
        ].join("");
    }

    function renderLearningSidePanel(stats, currentIndex, visibleCount) {
        return [
            '<aside class="exam-session-card exam-session-card--side">',
            '  <div class="exam-side-section">',
            '    <span class="exam-kicker">مرور آموزشی</span>',
            '    <div class="exam-stat-grid">',
            renderStatCard("حل‌شده", formatValue(stats.answered)),
            renderStatCard("باقی‌مانده", formatValue(stats.unanswered)),
            renderStatCard("نشان‌دار", formatValue(state.flags.size)),
            renderStatCard("جاری", formatValue(currentIndex + 1)),
            "    </div>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر نمایش</span>',
            '    <div class="exam-filter-pills">',
            renderLearningFilterButton("all", "\u0647\u0645\u0647", exam.questions.length),
            renderLearningFilterButton("flagged", "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631", state.flags.size),
            "    </div>",
            '    <p class="exam-side-copy">در این نما ' + escapeHtml(formatValue(visibleCount)) + ' سوال قابل جابه‌جایی است.</p>',
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-jump-unanswered"' + (stats.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-learning-progress">شروع دوباره آموزشی</button>',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="set-mode" data-mode="assessment">\u0631\u0641\u062a\u0646 \u0628\u0647 \u0633\u0646\u062c\u0634\u06cc</button>',
            "  </div>",
            "</aside>"
        ].join("");
    }

    function renderAssessmentDraftCompactPanel(totals, visibleCount) {
        return [
            '<section class="exam-compact-panel exam-compact-panel--assessment" aria-label="' + escapeHtml("\u062e\u0644\u0627\u0635\u0647 \u062d\u0627\u0644\u062a \u0633\u0646\u062c\u0634\u06cc") + '">',
            '  <div class="exam-compact-toolbar">',
            '    <div class="exam-compact-summary">',
            renderCompactMetric("\u0628\u0627\u0642\u06cc", formatValue(totals.unanswered), totals.unanswered ? "warning" : "success"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "    </div>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="submit-assessment"' + (state.assessment.submitting ? " disabled" : "") + ">" + escapeHtml(state.assessment.submitting ? "\u062f\u0631 \u062d\u0627\u0644 \u062b\u0628\u062a..." : "\u062b\u0628\u062a \u0633\u0646\u062c\u0634\u06cc") + "</button>",
            '    <details class="exam-compact-tools">',
            '      <summary class="exam-compact-tools__summary">\u0627\u0628\u0632\u0627\u0631\u0647\u0627</summary>',
            '      <div class="exam-compact-tools__body">',
            '        <div class="exam-compact-stats">',
            renderCompactMetric("\u06a9\u0644", formatValue(exam.questions.length), "accent"),
            renderCompactMetric("\u067e\u0627\u0633\u062e", formatValue(totals.answered), totals.answered ? "success" : "neutral"),
            renderCompactMetric("\u0628\u06cc\u200c\u067e\u0627\u0633\u062e", formatValue(totals.unanswered), totals.unanswered ? "warning" : "success"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "        </div>",
            '        <div class="exam-compact-filter-row"><div class="exam-filter-pills">' + renderAssessmentFilterButtons(false) + "</div></div>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-first-unanswered"' + (totals.unanswered <= 0 ? " disabled" : "") + ">\u0627\u0648\u0644\u06cc\u0646 \u0628\u06cc\u200c\u067e\u0627\u0633\u062e</button>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-assessment-draft">\u067e\u0627\u06a9 \u06a9\u0631\u062f\u0646 \u067e\u0627\u0633\u062e\u200c\u0647\u0627</button>',
            '        <span class="exam-compact-note">\u062f\u0631 \u0627\u06cc\u0646 \u0646\u0645\u0627 ' + escapeHtml(formatValue(visibleCount)) + ' \u0633\u0648\u0627\u0644 \u0642\u0627\u0628\u0644 \u0645\u0631\u0648\u0631 \u0627\u0633\u062a.</span>',
            "      </div>",
            "    </details>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentReportCompactPanel(report) {
        return [
            '<section class="exam-compact-panel exam-compact-panel--report" aria-label="' + escapeHtml("\u062e\u0644\u0627\u0635\u0647 \u06a9\u0627\u0631\u0646\u0627\u0645\u0647") + '">',
            '  <div class="exam-compact-toolbar exam-compact-toolbar--report">',
            '    <div class="exam-compact-summary">',
            renderCompactMetric("\u062f\u0631\u0635\u062f", formatPercent(report.percent), "accent"),
            renderCompactMetric("\u0635\u062d\u06cc\u062d", formatValue(report.correct), "success"),
            "    </div>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">\u0622\u0645\u0648\u0632\u0634\u06cc</button>',
            "  </div>",
            '  <div class="exam-compact-tools__body exam-compact-tools__body--inline exam-compact-tools__body--report">',
            '    <div class="exam-compact-report-hero">',
            '      <div class="exam-compact-report-hero__copy">',
            '        <span class="exam-compact-report-hero__label">\u06a9\u0627\u0631\u0646\u0627\u0645\u0647 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647</span>',
            '        <span class="exam-compact-report-hero__meta">' + escapeHtml(formatDateTime(report.submittedAt)) + "</span>",
            "      </div>",
            '      <strong class="exam-compact-report-hero__value">' + escapeHtml(formatPercent(report.percent)) + "</strong>",
            "    </div>",
            '    <div class="exam-compact-stats">',
            renderCompactMetric("\u0635\u062d\u06cc\u062d", formatValue(report.correct), "success"),
            renderCompactMetric("\u063a\u0644\u0637", formatValue(report.wrong), report.wrong ? "danger" : "neutral"),
            renderCompactMetric("\u0628\u06cc\u200c\u067e\u0627\u0633\u062e", formatValue(report.unanswered), report.unanswered ? "warning" : "neutral"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "    </div>",
            '    <div class="exam-compact-filter-row"><div class="exam-filter-pills">' + renderAssessmentFilterButtons(true) + "</div></div>",
            '    <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report">\u0631\u06cc\u0633\u062a \u06a9\u0627\u0631\u0646\u0627\u0645\u0647</button>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderLearningCompactPanel(stats, currentIndex) {
        return [
            '<section class="exam-compact-panel exam-compact-panel--learning" aria-label="' + escapeHtml("\u062e\u0644\u0627\u0635\u0647 \u062d\u0627\u0644\u062a \u0622\u0645\u0648\u0632\u0634\u06cc") + '">',
            '  <div class="exam-compact-toolbar">',
            '    <div class="exam-compact-summary">',
            renderCompactMetric("\u062c\u0627\u0631\u06cc", formatValue(currentIndex + 1), "accent"),
            renderCompactMetric("\u062d\u0644", formatValue(stats.answered), stats.answered ? "success" : "neutral"),
            "    </div>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="set-mode" data-mode="assessment">\u0633\u0646\u062c\u0634\u06cc</button>',
            '    <details class="exam-compact-tools">',
            '      <summary class="exam-compact-tools__summary">\u0627\u0628\u0632\u0627\u0631\u0647\u0627</summary>',
            '      <div class="exam-compact-tools__body">',
            '        <div class="exam-compact-stats">',
            renderCompactMetric("\u062d\u0644\u200c\u0634\u062f\u0647", formatValue(stats.answered), stats.answered ? "success" : "neutral"),
            renderCompactMetric("\u0628\u0627\u0642\u06cc", formatValue(stats.unanswered), stats.unanswered ? "warning" : "success"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            renderCompactMetric("\u062c\u0627\u0631\u06cc", formatValue(currentIndex + 1), "accent"),
            "        </div>",
            '        <div class="exam-compact-filter-row"><div class="exam-filter-pills">',
            renderLearningFilterButton("all", "\u0647\u0645\u0647", exam.questions.length),
            renderLearningFilterButton("flagged", "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631", state.flags.size),
            "        </div></div>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-jump-unanswered"' + (stats.unanswered <= 0 ? " disabled" : "") + ">\u0627\u0648\u0644\u06cc\u0646 \u0628\u06cc\u200c\u067e\u0627\u0633\u062e</button>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-learning-progress">\u0634\u0631\u0648\u0639 \u062f\u0648\u0628\u0627\u0631\u0647</button>',
            "      </div>",
            "    </details>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderCompactMetric(label, value, tone) {
        return [
            '<article class="exam-compact-metric' + (tone ? " is-" + escapeHtml(tone) : "") + '">',
            '  <span class="exam-compact-metric__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-compact-metric__value">' + escapeHtml(value) + "</strong>",
            "</article>"
        ].join("");
    }

    function renderResponsiveLabel(fullLabel, compactLabel) {
        return [
            '<span class="exam-label exam-label--full">' + escapeHtml(fullLabel) + "</span>",
            '<span class="exam-label exam-label--compact">' + escapeHtml(compactLabel) + "</span>"
        ].join("");
    }

    function renderAssessmentFilterButtons(reviewMode) {
        var filters = reviewMode
            ? [
                { key: "all", label: "همه" },
                { key: "correct", label: "صحیح" },
                { key: "wrong", label: "غلط" },
                { key: "unanswered", label: "بی‌پاسخ" },
                { key: "flagged", label: "نشان‌دار" }
            ]
            : [
                { key: "all", label: "همه" },
                { key: "answered", label: "پاسخ‌داده" },
                { key: "unanswered", label: "بی‌پاسخ" },
                { key: "flagged", label: "نشان‌دار" }
            ];

        return filters.map(function (item) {
            return '<button class="exam-filter-pill' + (state.assessment.filter === item.key ? " is-active" : "") + '" type="button" data-action="assessment-filter" data-filter="' + escapeHtml(item.key) + '">' + escapeHtml(item.label) + "</button>";
        }).join("");
    }

    function renderLearningFilterButton(filter, label, count) {
        return '<button class="exam-filter-pill' + (state.learning.filter === filter ? " is-active" : "") + '" type="button" data-action="learning-filter" data-filter="' + escapeHtml(filter) + '">' + escapeHtml(label) + ' <span>' + escapeHtml(formatValue(count)) + "</span></button>";
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
        var briefCopy = '  <p class="exam-answer-card__copy">' + escapeHtml("پاسخ صحیح این سوال گزینه " + optionLetter(question.correctIndex) + " است.") + "</p>";

        return [
            '<div class="exam-answer-card' + (isCorrect ? " is-correct" : " is-warning") + '">',
            '  <span class="exam-answer-card__label">' + escapeHtml(isCorrect ? "پاسخ تو درست بود" : "پاسخ صحیح مشخص شد") + "</span>",
            question.explanation ? '<div class="exam-answer-card__body">' + richTextHtml(question.explanation) + "</div>" : briefCopy,
            isFlagged(questionIndex) ? '<span class="exam-answer-card__hint">این سوال نشان‌دار شده و بعداً سریع پیدایش می‌کنی.</span>' : "",
            "</div>"
        ].join("");
    }

    function renderStageEmptyState(copy) {
        return [
            '<section class="exam-empty-card">',
            '  <h3>نمایش خالی شد</h3>',
            '  <p>' + escapeHtml(copy) + "</p>",
            '  <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-filters">حذف فیلترها</button>',
            "</section>"
        ].join("");
    }

    function renderMetaChip(text, kind) {
        return '<span class="exam-meta-chip' + (kind ? " is-" + escapeHtml(kind) : "") + '">' + escapeHtml(text) + "</span>";
    }

    function renderMiniStat(label, value) {
        return '<span class="exam-mini-stat"><strong>' + escapeHtml(label) + "</strong><span>" + escapeHtml(value) + "</span></span>";
    }

    function renderStatCard(label, value) {
        return [
            '<article class="exam-stat-card">',
            '  <span class="exam-stat-card__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-stat-card__value">' + escapeHtml(value) + "</strong>",
            "</article>"
        ].join("");
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
        if (action === "start-mode") {
            startMode(normalizeMode(actionNode.getAttribute("data-mode")) || state.mode);
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
            ensureAssessmentIndex(assessmentVisibleIndexes(state.assessment.filter));
            persistAssessmentState();
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
        if (action === "assessment-prev") {
            moveAssessment(-1);
            return;
        }
        if (action === "assessment-next") {
            moveAssessment(1);
            return;
        }
        if (action === "assessment-first-unanswered") {
            jumpAssessmentToFirstUnanswered();
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
        if (action === "reset-learning-progress") {
            resetLearningProgress();
            return;
        }
        if (action === "clear-filters") {
            state.assessment.filter = "all";
            state.learning.filter = "all";
            persistAssessmentState();
            persistLearningState();
            render();
        }
    }

    function handleChange(_event) {
        return;
    }

    function setMode(mode) {
        state.mode = mode;
        clearFeedback();
        syncModeInUrl();
        render();
    }

    function startMode(mode) {
        var normalizedMode = normalizeMode(mode);
        if (!normalizedMode) {
            return;
        }

        state.mode = normalizedMode;
        clearFeedback();
        if (normalizedMode === "assessment") {
            state.assessment.started = true;
            persistAssessmentState();
        } else {
            state.learning.started = true;
            persistLearningState();
        }
        syncModeInUrl();
        touchExamActivity(normalizedMode);
        render();
    }

    function isModeStarted(mode) {
        if (mode === "assessment") {
            return !!state.assessment.started;
        }
        if (mode === "learning") {
            return !!state.learning.started;
        }
        return false;
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
        if (state.assessment.report || !isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.assessment.answers[questionIndex] = optionIndex;
        state.assessment.currentQuestionIndex = questionIndex;
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
        state.assessment.currentQuestionIndex = 0;
        state.assessment.started = true;
        clearFeedback();
        persistAssessmentState();
        render();
    }

    function submitAssessment() {
        if (!exam.viewerState.canPersist || state.assessment.submitting || state.assessment.report) {
            return;
        }

        var totals = assessmentDraftTotals();
        if (totals.unanswered > 0) {
            var confirmed = window.confirm("هنوز " + formatValue(totals.unanswered) + " سوال بی‌پاسخ مانده است. آزمون با همین وضعیت ثبت شود؟");
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
            state.assessment.currentQuestionIndex = 0;
            state.assessment.started = true;
            setFeedback("success", payload.message || "کارنامه این آزمون ذخیره شد.");
            persistAssessmentState();
            render();
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
            state.assessment.currentQuestionIndex = 0;
            state.assessment.started = true;
            setFeedback("success", payload.message || "کارنامه این آزمون ریست شد.");
            persistAssessmentState();
            render();
        }).catch(function (error) {
            setFeedback("error", error && error.message ? error.message : "ریست کارنامه انجام نشد.");
            render();
        });
    }

    function resetLearningProgress() {
        if (!window.confirm("پیشرفت حالت آموزشی از اول شروع شود؟")) {
            return;
        }

        state.learning.answers = createNullArray(exam.questions.length);
        state.learning.revealed = createFalseArray(exam.questions.length);
        state.learning.currentQuestionIndex = 0;
        state.learning.filter = "all";
        state.learning.started = true;
        clearFeedback();
        persistLearningState();
        render();
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

    function ensureAssessmentIndex(visibleIndexes) {
        var indexes = visibleIndexes && visibleIndexes.length ? visibleIndexes : assessmentVisibleIndexes(state.assessment.filter);
        if (!indexes.length) {
            return 0;
        }

        if (indexes.indexOf(state.assessment.currentQuestionIndex) === -1) {
            state.assessment.currentQuestionIndex = indexes[0];
            persistAssessmentState();
        }
        return state.assessment.currentQuestionIndex;
    }

    function moveAssessment(step) {
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = ensureAssessmentIndex(visibleIndexes);
        var currentPosition = visibleIndexes.indexOf(currentIndex);
        if (currentPosition === -1) {
            currentPosition = 0;
        }

        var nextPosition = currentPosition + step;
        if (nextPosition < 0 || nextPosition >= visibleIndexes.length) {
            return;
        }

        state.assessment.currentQuestionIndex = visibleIndexes[nextPosition];
        persistAssessmentState();
        render();
    }

    function jumpAssessmentToFirstUnanswered() {
        var visibleIndexes = assessmentVisibleIndexes("unanswered");
        if (!visibleIndexes.length) {
            return;
        }

        state.assessment.filter = "unanswered";
        state.assessment.currentQuestionIndex = visibleIndexes[0];
        persistAssessmentState();
        render();
    }

    function jumpToAssessmentQuestion(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.assessment.currentQuestionIndex = questionIndex;
        persistAssessmentState();
        render();
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
    }

    function jumpToLearningQuestion(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.learning.currentQuestionIndex = questionIndex;
        persistLearningState();
        render();
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
            mode: state.mode || "view",
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
        try {
            window.sessionStorage.setItem(assessmentDraftKey, JSON.stringify({
                answers: state.assessment.report ? state.assessment.report.answers : state.assessment.answers,
                startedAt: state.assessment.startedAt,
                filter: state.assessment.filter,
                currentQuestionIndex: state.assessment.currentQuestionIndex,
                started: state.assessment.started
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
                filter: state.learning.filter,
                started: state.learning.started
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

    function scheduleLayoutSync() {
        if (layoutFrame) {
            window.cancelAnimationFrame(layoutFrame);
        }
        if (delayedLayoutTimer) {
            window.clearTimeout(delayedLayoutTimer);
        }

        layoutFrame = window.requestAnimationFrame(function () {
            layoutFrame = 0;
            syncShellMetrics();
            syncStageScale();
        });

        delayedLayoutTimer = window.setTimeout(function () {
            delayedLayoutTimer = 0;
            syncShellMetrics();
            syncStageScale();
        }, 90);
    }

    function syncShellMetrics() {
        var header = document.querySelector(".site-header");
        var bottomNav = document.querySelector(".shell-bottom-nav");
        var headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
        var bottomHeight = bottomNav ? Math.ceil(bottomNav.getBoundingClientRect().height) : 0;
        var usableHeight = Math.max(360, window.innerHeight - headerHeight - bottomHeight);

        document.body.style.setProperty("--exam-shell-top-offset", headerHeight + "px");
        document.body.style.setProperty("--exam-shell-bottom-offset", bottomHeight + "px");
        document.body.style.setProperty("--exam-shell-usable-height", usableHeight + "px");
    }

    function syncStageScale() {
        var shell = appRoot.querySelector(".exam-stage-shell");
        var scaler = appRoot.querySelector(".exam-stage-scaler");
        var canvas = appRoot.querySelector(".exam-stage-canvas");
        if (!shell || !scaler || !canvas) {
            return;
        }

        scaler.style.setProperty("--exam-stage-scale", "1");

        var availableWidth = shell.clientWidth;
        var availableHeight = shell.clientHeight;
        var canvasWidth = canvas.scrollWidth;
        var canvasHeight = canvas.scrollHeight;

        if (!availableWidth || !availableHeight || !canvasWidth || !canvasHeight) {
            return;
        }

        var scale = Math.min(1, availableWidth / canvasWidth, availableHeight / canvasHeight);
        scaler.style.setProperty("--exam-stage-scale", String(scale));
    }

    function railWindowSize() {
        if (window.innerWidth <= 520) {
            return 5;
        }
        if (window.innerWidth <= 880) {
            return 7;
        }
        return 9;
    }

    function buildRailItems(indexes, currentIndex, windowSize) {
        if (!indexes.length) {
            return [];
        }

        var currentPosition = indexes.indexOf(currentIndex);
        if (currentPosition < 0) {
            currentPosition = 0;
        }

        var start = Math.max(0, currentPosition - Math.floor(windowSize / 2));
        var end = start + windowSize - 1;
        if (end >= indexes.length) {
            end = indexes.length - 1;
            start = Math.max(0, end - windowSize + 1);
        }

        var items = [];
        if (start > 0) {
            items.push({ type: "index", index: indexes[0] });
            if (start > 1) {
                items.push({ type: "ellipsis" });
            }
        }

        for (var cursor = start; cursor <= end; cursor++) {
            items.push({ type: "index", index: indexes[cursor] });
        }

        if (end < indexes.length - 1) {
            if (end < indexes.length - 2) {
                items.push({ type: "ellipsis" });
            }
            items.push({ type: "index", index: indexes[indexes.length - 1] });
        }

        return items;
    }

    function setFeedback(kind, text) {
        state.feedback.kind = kind || "neutral";
        state.feedback.text = text || "";
    }

    function clearFeedback() {
        setFeedback("", "");
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

    function touchExamActivity(mode) {
        var normalizedMode = normalizeMode(mode) || "view";
        if (!exam.viewerState.canPersist || !exam.courseSlug || !exam.slug) {
            return;
        }
        if (normalizedMode === activityTrackedMode) {
            return;
        }

        activityTrackedMode = normalizedMode;
        apiPost("touchExamActivity", {
            course: exam.courseSlug,
            exam: exam.slug,
            mode: normalizedMode
        }).catch(function () {
            return null;
        });
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
            answers: report ? report.answers.slice() : createNullArray(totalQuestions),
            startedAt: new Date().toISOString(),
            filter: "all",
            report: report,
            submitting: false,
            currentQuestionIndex: 0,
            started: false
        };

        try {
            var saved = JSON.parse(window.sessionStorage.getItem(key) || "null");
            if (!isObject(saved)) {
                return empty;
            }

            if (!report) {
                empty.answers = clampAnswers(Array.isArray(saved.answers) ? saved.answers : empty.answers, exam.questions);
            }
            empty.startedAt = normalizeText(saved.startedAt) || empty.startedAt;
            empty.filter = normalizeAssessmentFilter(saved.filter);
            empty.currentQuestionIndex = clampIndex(saved.currentQuestionIndex, totalQuestions);
            empty.started = Boolean(saved.started);
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
            filter: "all",
            started: false
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
            empty.started = Boolean(saved.started);
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

    function bidiAwareHtml(text) {
        var source = String(text || "");
        var htmlParts = [];
        var lastIndex = 0;
        var match;

        while ((match = BIDI_LTR_RUN_RE.exec(source)) !== null) {
            var offset = match.index;
            htmlParts.push(escapeHtml(source.slice(lastIndex, offset)).replace(/\n/g, "<br>"));
            htmlParts.push('<bdi dir="ltr" class="exam-bidi-ltr">' + escapeHtml(match[0]) + "</bdi>");
            lastIndex = offset + match[0].length;
        }

        htmlParts.push(escapeHtml(source.slice(lastIndex)).replace(/\n/g, "<br>"));
        BIDI_LTR_RUN_RE.lastIndex = 0;
        return htmlParts.join("");
    }

    function richTextHtml(text) {
        return String(text || "")
            .split(/(\*\*[^*]+\*\*)/g)
            .map(function (part) {
                if (!part) {
                    return "";
                }
                if (part.indexOf("**") === 0 && part.lastIndexOf("**") === part.length - 2) {
                    return "<strong>" + bidiAwareHtml(part.slice(2, -2)) + "</strong>";
                }
                return bidiAwareHtml(part);
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
