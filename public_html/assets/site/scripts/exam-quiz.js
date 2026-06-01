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
        layout: {
            chooserHintExpanded: false,
            activeSheet: "",
            sheetMode: "",
            navigatorOffset: { assessment: 0, learning: 0 }
        },
        ui: {
            busyAction: "",
            busyLabel: "",
            confirmDialog: null,
            toast: null
        },
        interaction: {
            lastLearningRevealIndex: -1,
            swipeStart: null
        }
    };
    var activityTrackedMode = "";
    var layoutFrame = 0;
    var delayedLayoutTimer = 0;
    var toastTimer = 0;
    var assessmentPersistTimer = 0;
    var learningPersistTimer = 0;
    var assessmentDerivedCache = null;
    var learningDerivedCache = null;
    var STORAGE_PERSIST_DEBOUNCE_MS = 96;
    var NETWORK_TIMEOUT_MS = 14000;
    var FLAG_SYNC_DEBOUNCE_MS = 480;
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
    appRoot.addEventListener("touchstart", handleTouchStart, { passive: true });
    appRoot.addEventListener("touchend", handleTouchEnd, { passive: true });
    appRoot.addEventListener("touchcancel", clearSwipeState, { passive: true });
    window.addEventListener("beforeunload", handleBeforeUnload);
    window.addEventListener("keydown", handleKeydown);
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
            '            <a class="back-btn exam-back-link" href="' + escapeHtml(fallbackBackHref) + '" aria-label="' + escapeHtml(fallbackBackLabel) + '">',
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
            renderActiveSheet(),
            renderConfirmDialog(),
            renderBusyOverlay(),
            renderToastRegion(),
            "        </div>",
            "      </div>",
            "    </section>",
            "  </main>",
            "</div>"
        ].join("");

        decorateDynamicContent();
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
            "      </div>",
            '      <p class="exam-launch-panel__copy">' + escapeHtml(previewCopy) + "</p>",
            previewStats.length ? '<div class="exam-mini-stats">' + previewStats.join("") + "</div>" : "",
            report ? renderLaunchSubmissionNotice(report) : "",
            '      <div class="exam-mode-card-grid">' + renderModeCards() + "</div>",
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
            '    <div class="exam-session-topbar">',
            '      <a class="back-btn exam-back-link exam-back-link--session" href="' + escapeHtml(exam.backHref) + '" aria-label="' + escapeHtml(exam.backLabel || "بازگشت") + '">',
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

    function renderLaunchSubmissionNotice(report) {
        var submittedAt = report && (report.submittedAt || report.updatedAt);
        return [
            '<section class="exam-launch-status-card" aria-label="' + escapeHtml("آخرین وضعیت کارنامه") + '">',
            '  <div class="exam-launch-status-card__chips">',
            renderMetaChip("وضعیت قبلی: ثبت‌شده", "success"),
            submittedAt ? renderMetaChip("تاریخ ثبت: " + formatDateTime(submittedAt), "neutral") : "",
            "  </div>",
            '  <p class="exam-launch-status-card__copy">این آزمون قبلاً ثبت شده است. می‌توانی مستقیم کارنامه را مرور کنی یا بعد از تایید، دوباره در سنجشی شرکت کنی.</p>',
            "</section>"
        ].join("");
    }

    function renderLaunchActions() {
        return [
            '  <div class="exam-launch-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-chooser-hint">' + escapeHtml(state.layout.chooserHintExpanded ? "بستن توضیح" : "تفاوت دو حالت") + "</button>",
            state.mode
                ? '    <button class="exam-btn exam-btn--primary" type="button" data-action="start-mode" data-mode="' + escapeHtml(state.mode) + '">' + escapeHtml(modePrimaryActionLabel(state.mode)) + "</button>"
                : '    <button class="exam-btn exam-btn--primary" type="button" disabled>یکی از کارت‌ها را انتخاب کن</button>',
            "  </div>"
        ].join("");
    }

    function renderModeCards() {
        return ["assessment", "learning"].map(function (mode) {
            var definition = modeDefinition(mode);
            var summary = modeSummary(mode);
            var selected = state.mode === mode;
            var locked = mode === "assessment" && !exam.viewerState.canPersist;

            return [
                '<article class="exam-mode-card' + (selected ? " is-selected" : "") + (locked ? " is-locked" : "") + '">',
                '  <div class="exam-mode-card__top">',
                '    <div class="exam-mode-card__copy">',
                '      <span class="exam-mode-card__eyebrow">' + escapeHtml(definition.tagline || "حالت آزمون") + "</span>",
                '      <h3 class="exam-mode-card__title">' + escapeHtml(definition.title) + "</h3>",
                '      <p class="exam-mode-card__description">' + escapeHtml(definition.description || "") + "</p>",
                "    </div>",
                '    <span class="exam-mode-card__badge' + (selected ? " is-active" : "") + '">' + escapeHtml(selected ? "انتخاب‌شده" : locked ? "نیاز به ورود" : "آماده") + "</span>",
                "  </div>",
                '  <div class="exam-mode-card__stats">',
                renderModeSummaryStat("وضعیت", summary.statusLabel),
                renderModeSummaryStat("پیشرفت", summary.progressLabel),
                renderModeSummaryStat("آخرین سوال", summary.positionLabel),
                "  </div>",
                summary.savedLabel ? '  <div class="exam-mode-card__footer-note">' + escapeHtml(summary.savedLabel) + "</div>" : "",
                '  <div class="exam-mode-card__actions">',
                locked
                    ? '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(loginHref()) + '">ورود برای سنجشی</a>'
                    : '    <button class="exam-btn exam-btn--primary" type="button" data-action="start-mode" data-mode="' + escapeHtml(mode) + '">' + escapeHtml(modePrimaryActionLabel(mode)) + "</button>",
                '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="' + escapeHtml(mode) + '">' + escapeHtml(selected ? "در حال نمایش" : "انتخاب این حالت") + "</button>",
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function renderModeSummaryStat(label, value) {
        return [
            '<span class="exam-mode-card__stat">',
            '  <strong>' + escapeHtml(value) + "</strong>",
            '  <span>' + escapeHtml(label) + "</span>",
            "</span>"
        ].join("");
    }

    function modePrimaryActionLabel(mode) {
        var summary = modeSummary(mode);
        if (mode === "assessment" && summary.hasReport) {
            return "مشاهده کارنامه";
        }
        return summary.canResume ? "ادامه از آخرین وضعیت" : "شروع از ابتدا";
    }

    function modeSummary(mode) {
        var progress = mode === "assessment" ? state.assessment : state.learning;
        var totals = mode === "assessment"
            ? (progress.report ? reportTotals(progress.report) : assessmentDraftTotals())
            : learningTotals();
        var answered = mode === "assessment" && progress.report
            ? exam.questions.length - totals.unanswered
            : totals.answered;
        var report = mode === "assessment" ? progress.report : null;
        var currentIndex = Math.min(exam.questions.length, Math.max(0, (progress.currentQuestionIndex || 0) + 1));
        var canResume = Boolean(progress.started && (answered > 0 || currentIndex > 1 || state.flags.size > 0 || report));
        return {
            canResume: canResume,
            hasReport: Boolean(report),
            statusLabel: report ? "کارنامه ثبت‌شده" : (canResume ? "در حال انجام" : "شروع‌نشده"),
            progressLabel: formatValue(answered) + " از " + formatValue(exam.questions.length),
            positionLabel: formatValue(currentIndex || 1),
            savedLabel: report
                ? "ثبت نهایی: " + formatDateTime(report.submittedAt || report.updatedAt)
                : lastSavedLabel(mode)
        };
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
            renderSessionProgress({
                mode: "assessment",
                answered: totals.answered,
                unanswered: totals.unanswered,
                flagged: state.flags.size,
                currentIndex: currentIndex,
                total: exam.questions.length,
                filterLabel: assessmentFilterLabel(state.assessment.filter)
            }),
            renderQuestionRail({
                mode: "assessment",
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
                renderAssessmentDraftSidePanel(totals, visibleIndexes.length, currentIndex, visibleIndexes),
                "</div>"
            ].join("") : renderStageEmptyState("هیچ سوالی با این فیلتر پیدا نشد."),
            renderMobileQuestionDock({
                mode: "assessment",
                questionIndex: currentIndex,
                visibleIndexes: visibleIndexes,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                clearAction: "assessment-clear-answer",
                openNavigatorMode: "assessment"
            }),
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
            renderSessionProgress({
                mode: "assessment-report",
                answered: exam.questions.length - totals.unanswered,
                unanswered: totals.unanswered,
                flagged: state.flags.size,
                currentIndex: currentIndex,
                total: exam.questions.length,
                filterLabel: assessmentFilterLabel(state.assessment.filter),
                percent: report.percent,
                correct: totals.correct,
                wrong: totals.wrong
            }),
            renderQuestionRail({
                mode: "assessment",
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState,
                emptyLabel: "در این فیلتر سوالی باقی نمانده است."
            }),
            renderAssessmentReportDashboard(report),
            visibleIndexes.length ? [
                '<div class="exam-stage-body exam-stage-body--report">',
                renderAssessmentReportQuestionCard(currentIndex, report),
                renderAssessmentReportSidePanel(report, currentIndex, visibleIndexes),
                "</div>"
            ].join("") : renderStageEmptyState(reportFilterEmptyCopy(state.assessment.filter), reportFilterEmptyTitle(state.assessment.filter)),
            renderMobileQuestionDock({
                mode: "assessment-report",
                questionIndex: currentIndex,
                visibleIndexes: visibleIndexes,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                clearAction: "",
                openNavigatorMode: "assessment"
            }),
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
            renderSessionProgress({
                mode: "learning",
                answered: stats.answered,
                unanswered: stats.unanswered,
                flagged: state.flags.size,
                currentIndex: currentIndex,
                total: exam.questions.length,
                filterLabel: learningFilterLabel(state.learning.filter)
            }),
            renderQuestionRail({
                mode: "learning",
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
                renderLearningSidePanel(stats, currentIndex, visibleIndexes.length, visibleIndexes),
                "</div>"
            ].join("") : renderStageEmptyState("هنوز سوالی در این فیلتر باقی نمانده است."),
            renderMobileQuestionDock({
                mode: "learning",
                questionIndex: currentIndex,
                visibleIndexes: visibleIndexes,
                prevAction: "learning-prev",
                nextAction: "learning-next",
                clearAction: "learning-clear-answer",
                openNavigatorMode: "learning"
            }),
            "</section>"
        ].join("");
    }

    function renderSessionProgress(config) {
        var total = Math.max(1, Number(config.total || exam.questions.length || 1));
        var answered = Math.max(0, Number(config.answered || 0));
        var percent = config.mode === "assessment-report"
            ? Math.max(0, Math.min(100, Number(config.percent || 0)))
            : Math.round((answered / total) * 100);
        var legend = [
            renderProgressLegendItem("پاسخ‌داده", formatValue(answered), "success"),
            renderProgressLegendItem("بی‌پاسخ", formatValue(config.unanswered || 0), config.unanswered ? "warning" : "neutral"),
            renderProgressLegendItem("نشان‌دار", formatValue(config.flagged || 0), config.flagged ? "flagged" : "neutral")
        ];

        if (config.mode === "assessment-report") {
            legend.unshift(renderProgressLegendItem("صحیح", formatValue(config.correct || 0), "success"));
            legend.splice(2, 0, renderProgressLegendItem("غلط", formatValue(config.wrong || 0), config.wrong ? "danger" : "neutral"));
        }

        return [
            '<section class="exam-progress-card" aria-label="' + escapeHtml("وضعیت فعلی آزمون") + '">',
            '  <div class="exam-progress-card__top">',
            '    <div class="exam-progress-card__copy">',
            '      <strong>' + escapeHtml(config.mode === "assessment-report" ? "خلاصه نتیجه" : "پیشرفت فعلی") + "</strong>",
            '      <span>' + escapeHtml("سوال " + formatValue((config.currentIndex || 0) + 1) + " از " + formatValue(total) + " • " + (config.filterLabel || "همه سوال‌ها")) + "</span>",
            "    </div>",
            '    <div class="exam-progress-card__aside">',
            '      <span class="exam-progress-card__percent">' + escapeHtml(formatPercent(percent)) + "</span>",
            (state.mode === "assessment" || state.mode === "learning") && lastSavedLabel(state.mode)
                ? '      <span class="exam-progress-card__saved">' + escapeHtml(lastSavedLabel(state.mode)) + "</span>"
                : "",
            "    </div>",
            "  </div>",
            '  <div class="exam-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(String(percent)) + '" aria-label="' + escapeHtml("پیشرفت آزمون") + '">',
            '    <span class="exam-progress-track__fill" style="width:' + escapeHtml(String(percent)) + '%"></span>',
            "  </div>",
            '  <div class="exam-progress-legend">' + legend.join("") + "</div>",
            "</section>"
        ].join("");
    }

    function renderProgressLegendItem(label, value, tone) {
        return [
            '<span class="exam-progress-chip' + (tone ? " is-" + escapeHtml(tone) : "") + '">',
            '  <strong>' + escapeHtml(value) + "</strong>",
            '  <span>' + escapeHtml(label) + "</span>",
            "</span>"
        ].join("");
    }

    function renderSessionHeader(config) {
        return [
            '  <div class="exam-stage-head exam-stage-head--session">',
            '    <div class="exam-session-topbar">',
            '      <a class="back-btn exam-back-link exam-back-link--session" href="' + escapeHtml(exam.backHref) + '" aria-label="' + escapeHtml(exam.backLabel || "بازگشت") + '">',
            '        <span class="back-icon" aria-hidden="true">←</span>',
            "        " + renderResponsiveLabel(exam.backLabel, "\u0628\u0627\u0632\u06af\u0634\u062a"),
            "      </a>",
            '      <div class="exam-session-topbar__aside">',
            '        <div class="exam-mode-pills exam-mode-pills--compact">',
            renderModePill("assessment", "سنجشی", false),
            renderModePill("learning", "آموزشی", false),
            "        </div>",
            config.statusText ? '        <div class="exam-feedback exam-feedback--' + escapeHtml(config.statusKind || "neutral") + '">' + escapeHtml(config.statusText) + "</div>" : "",
            "      </div>",
            "    </div>",
            '    <div class="exam-session-hero">',
            '      <span class="exam-kicker">' + escapeHtml(exam.eyebrow) + "</span>",
            '      <h2 class="exam-stage-title exam-stage-title--compact">' + escapeHtml(exam.title) + "</h2>",
            '      <p class="exam-stage-subtitle exam-stage-subtitle--compact">' + escapeHtml(config.subtitle) + "</p>",
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
            '      <button class="exam-rail__browse" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="' + escapeHtml(config.mode || state.mode || "assessment") + '">فهرست سوال‌ها</button>',
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
            '<article class="exam-session-card exam-session-card--question" data-swipe-area="assessment" data-card-state="' + escapeHtml(assessmentDraftState(selectedIndex)) + '">',
            renderQuestionCardHead(questionIndex, assessmentStateLabel(assessmentDraftState(selectedIndex))),
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
            question.options.map(function (option, optionIndex) {
                return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, false);
            }).join(""),
            "  </div>",
            renderQuestionStatusBar({
                mode: "assessment",
                questionIndex: questionIndex,
                selectedIndex: selectedIndex,
                currentPosition: currentPosition,
                visibleIndexes: visibleIndexes
            }),
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-clear-answer" data-question-index="' + escapeHtml(String(questionIndex)) + '"' + (selectedIndex === null ? " disabled" : "") + ">\u062d\u0630\u0641 \u067e\u0627\u0633\u062e</button>",
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
            '<article class="exam-session-card exam-session-card--question" data-swipe-area="assessment" data-card-state="' + escapeHtml(stateName) + '">',
            renderQuestionCardHead(questionIndex, assessmentStateLabel(stateName)),
            '  <h3 class="exam-question-text">' + richTextHtml(question.question) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '">',
            question.options.map(function (option, optionIndex) {
                return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, true);
            }).join(""),
            "  </div>",
            renderQuestionStatusBar({
                mode: "assessment-report",
                questionIndex: questionIndex,
                selectedIndex: selectedIndex,
                currentPosition: currentPosition,
                visibleIndexes: visibleIndexes
            }),
            renderAssessmentReviewFeedback(questionIndex, question, selectedIndex),
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
            '<article class="exam-session-card exam-session-card--question' + (revealed && state.interaction.lastLearningRevealIndex === questionIndex ? " is-feedback-animated" : "") + '" data-swipe-area="learning" data-card-state="' + escapeHtml(learningNavState(questionIndex)) + '">',
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
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-clear-answer" data-question-index="' + escapeHtml(String(questionIndex)) + '"' + (selectedIndex === null ? " disabled" : "") + ">\u062d\u0630\u0641 \u067e\u0627\u0633\u062e</button>",
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

    function renderAssessmentReviewFeedback(questionIndex, question, selectedIndex) {
        var isUnanswered = selectedIndex === null;
        var isCorrect = !isUnanswered && selectedIndex === question.correctIndex;
        var chosenLabel = isUnanswered ? "بدون پاسخ" : ("گزینه " + optionLetter(selectedIndex));
        var summaryCopy = isUnanswered
            ? "برای این سوال پاسخی ثبت نشده است. پاسخ صحیح گزینه " + optionLetter(question.correctIndex) + " بود."
            : (isCorrect
                ? "پاسخ ثبت‌شده تو " + chosenLabel + " است و با جواب صحیح این سوال یکسان بود."
                : "پاسخ ثبت‌شده تو " + chosenLabel + " بود و جواب صحیح این سوال گزینه " + optionLetter(question.correctIndex) + " است.");

        return [
            '<div class="exam-answer-card' + (isCorrect ? " is-correct" : " is-warning") + '">',
            '  <span class="exam-answer-card__label">' + escapeHtml(isUnanswered ? "بی‌پاسخ مانده" : (isCorrect ? "پاسخ این سوال درست بود" : "نیاز به مرور")) + "</span>",
            '  <p class="exam-answer-card__copy">' + escapeHtml(summaryCopy) + "</p>",
            renderExplanationDisclosure(question.explanation, {
                label: "پاسخ تشریحی",
                open: false,
                tone: isCorrect ? "success" : "warning"
            }),
            isFlagged(questionIndex) ? '<span class="exam-answer-card__hint">این سوال نشان‌دار است و از تب «نشان‌دار» هم در دسترس می‌ماند.</span>' : "",
            "</div>"
        ].join("");
    }

    function renderAssessmentDraftSidePanel(totals, visibleCount, currentIndex, visibleIndexes) {
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
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="save-progress" data-mode="assessment">ذخیره موقت</button>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="assessment">فهرست سوال‌ها</button>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-first-unanswered"' + (totals.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-assessment-draft">پاک‌کردن پاسخ‌ها</button>',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="submit-assessment"' + (state.assessment.submitting ? " disabled" : "") + ">" + escapeHtml(state.assessment.submitting ? "در حال ثبت..." : "ثبت آزمون") + "</button>",
            "  </div>",
            renderSidebarNavigator({
                mode: "assessment",
                currentIndex: currentIndex,
                visibleIndexes: visibleIndexes,
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState
            }),
            "</aside>"
        ].join("");
    }

    function renderAssessmentReportSidePanel(report, currentIndex, visibleIndexes) {
        var extraStats = [];
        if (canShowAssessmentComparisons(report) && report.showRank) {
            extraStats.push(renderStatCard("رتبه", formatValue(report.rank) + " از " + formatValue(report.participantCount)));
        }
        if (canShowAssessmentComparisons(report) && report.overallAveragePercent !== null && report.overallAveragePercent !== undefined) {
            extraStats.push(renderStatCard("میانگین قبلی", formatPercent(report.overallAveragePercent)));
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
            !canShowAssessmentComparisons(report) ? '<p class="exam-side-copy">رتبه و میانگین بعد از رسیدن این آزمون به حداقل ۱۰ شرکت‌کننده نمایش داده می‌شود.</p>' : "",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر مرور</span>',
            '    <div class="exam-filter-pills">' + renderAssessmentFilterButtons(true) + "</div>",
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="review-filter-focus" data-filter="wrong"' + (report.wrong <= 0 ? " disabled" : "") + ">مرور غلط‌ها</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="review-filter-focus" data-filter="flagged"' + (state.flags.size <= 0 ? " disabled" : "") + ">مرور نشان‌دارها</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="assessment">فهرست سوال‌ها</button>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به آموزشی</button>',
            '    <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report"' + (isBusyAction("reset-assessment-report") ? " disabled" : "") + '>' + escapeHtml(isBusyAction("reset-assessment-report") ? "در حال آماده‌سازی..." : "شرکت مجدد") + "</button>",
            "  </div>",
            renderSidebarNavigator({
                mode: "assessment",
                currentIndex: currentIndex,
                visibleIndexes: visibleIndexes,
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState
            }),
            "</aside>"
        ].join("");
    }

    function renderLearningSidePanel(stats, currentIndex, visibleCount, visibleIndexes) {
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
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="save-progress" data-mode="learning">ذخیره موقت</button>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="learning">فهرست سوال‌ها</button>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-jump-unanswered"' + (stats.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-learning-progress">شروع دوباره آموزشی</button>',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="set-mode" data-mode="assessment">\u0631\u0641\u062a\u0646 \u0628\u0647 \u0633\u0646\u062c\u0634\u06cc</button>',
            "  </div>",
            renderSidebarNavigator({
                mode: "learning",
                currentIndex: currentIndex,
                visibleIndexes: visibleIndexes,
                jumpAction: "learning-goto",
                stateResolver: learningNavState
            }),
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
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="save-progress" data-mode="assessment">ذخیره موقت</button>',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="filters" data-mode="assessment">فیلترها</button>',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="assessment">فهرست سوال‌ها</button>',
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
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="filters" data-mode="assessment">فیلترها</button>',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="assessment">فهرست سوال‌ها</button>',
            '    <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report">\u0631\u06cc\u0633\u062a \u06a9\u0627\u0631\u0646\u0627\u0645\u0647</button>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentReportDashboard(report) {
        var canShowComparisons = canShowAssessmentComparisons(report);
        return [
            '<section class="exam-compact-panel exam-compact-panel--report exam-report-dashboard-panel" aria-label="' + escapeHtml("خلاصه کارنامه") + '">',
            '  <div class="exam-report-dashboard">',
            '    <section class="exam-report-summary-card">',
            '      <div class="exam-report-summary-card__head">',
            '        <div class="exam-report-summary-card__copy">',
            '          <span class="exam-report-summary-card__kicker">کارنامه ثبت‌شده</span>',
            '          <strong class="exam-report-summary-card__title">خلاصه عملکرد این آزمون</strong>',
            '          <p class="exam-report-summary-card__meta">آخرین ثبت: ' + escapeHtml(formatDateTime(report.submittedAt)) + "</p>",
            "        </div>",
            '        <strong class="exam-report-summary-card__score">' + escapeHtml(formatPercent(report.percent)) + "</strong>",
            "      </div>",
            renderAssessmentPerformanceChart(report),
            '      <div class="exam-report-summary-card__stats">',
            renderCompactMetric("صحیح", formatValue(report.correct), "success"),
            renderCompactMetric("غلط", formatValue(report.wrong), report.wrong ? "danger" : "neutral"),
            renderCompactMetric("بی‌پاسخ", formatValue(report.unanswered), report.unanswered ? "warning" : "neutral"),
            renderCompactMetric("نشان‌دار", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "      </div>",
            "    </section>",
            '    <section class="exam-report-breakdown">',
            '      <div class="exam-report-breakdown__head">',
            '        <span class="exam-side-title">Breakdown سوال‌ها</span>',
            '        <button class="exam-btn exam-btn--ghost exam-btn--inline" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="assessment">فهرست کامل</button>',
            "      </div>",
            '      <div class="exam-report-breakdown__grid">',
            renderReportBreakdownButton("all", "همه", assessmentFilterCount("all", true), "accent"),
            renderReportBreakdownButton("correct", "صحیح", assessmentFilterCount("correct", true), "success"),
            renderReportBreakdownButton("wrong", "غلط", assessmentFilterCount("wrong", true), "danger"),
            renderReportBreakdownButton("unanswered", "بی‌پاسخ", assessmentFilterCount("unanswered", true), "warning"),
            renderReportBreakdownButton("flagged", "نشان‌دار", assessmentFilterCount("flagged", true), "flagged"),
            "      </div>",
            "    </section>",
            '    <section class="exam-report-insights">',
            canShowComparisons && report.showRank ? renderReportInsightCard("رتبه", formatValue(report.rank) + " از " + formatValue(report.participantCount), "success") : "",
            canShowComparisons && report.overallAveragePercent !== null && report.overallAveragePercent !== undefined
                ? renderReportInsightCard("میانگین آزمون‌های قبلی", formatPercent(report.overallAveragePercent), "accent")
                : "",
            canShowComparisons
                ? renderReportInsightCard("شرکت‌کننده", formatValue(report.participantCount) + " نفر", "neutral")
                : '<div class="exam-report-insight exam-report-insight--note">رتبه و میانگین از زمانی نمایش داده می‌شود که این آزمون حداقل ۱۰ شرکت‌کننده داشته باشد.</div>',
            "    </section>",
            '    <section class="exam-report-actions">',
            '      <div class="exam-compact-filter-row"><div class="exam-filter-pills">' + renderAssessmentFilterButtons(true) + "</div></div>",
            '      <div class="exam-report-actions__grid">',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="review-filter-focus" data-filter="wrong"' + (report.wrong <= 0 ? " disabled" : "") + ">مرور غلط‌ها</button>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="review-filter-focus" data-filter="flagged"' + (state.flags.size <= 0 ? " disabled" : "") + ">مرور نشان‌دارها</button>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="review-filter-focus" data-filter="all">همه سوال‌ها</button>',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">مرور آموزشی</button>',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="filters" data-mode="assessment">فیلترها</button>',
            '        <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report"' + (isBusyAction("reset-assessment-report") ? " disabled" : "") + '>' + escapeHtml(isBusyAction("reset-assessment-report") ? "در حال آماده‌سازی..." : "شرکت مجدد") + "</button>",
            "      </div>",
            "    </section>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentPerformanceChart(report) {
        var totalQuestions = Math.max(1, Number(report.totalQuestions || exam.questions.length || 1));
        var correctWidth = Math.max(0, Math.min(100, Math.round((Number(report.correct || 0) / totalQuestions) * 1000) / 10));
        var wrongWidth = Math.max(0, Math.min(100, Math.round((Number(report.wrong || 0) / totalQuestions) * 1000) / 10));
        var unansweredWidth = Math.max(0, Math.min(100, Math.round((Number(report.unanswered || 0) / totalQuestions) * 1000) / 10));

        return [
            '<div class="exam-report-chart" aria-hidden="true">',
            '  <span class="exam-report-chart__segment is-success" style="width:' + escapeHtml(String(correctWidth)) + '%"></span>',
            '  <span class="exam-report-chart__segment is-danger" style="width:' + escapeHtml(String(wrongWidth)) + '%"></span>',
            '  <span class="exam-report-chart__segment is-warning" style="width:' + escapeHtml(String(unansweredWidth)) + '%"></span>',
            "</div>",
            '  <div class="exam-report-chart__legend">',
            renderProgressLegendItem("صحیح", formatValue(report.correct), "success"),
            renderProgressLegendItem("غلط", formatValue(report.wrong), report.wrong ? "danger" : "neutral"),
            renderProgressLegendItem("بی‌پاسخ", formatValue(report.unanswered), report.unanswered ? "warning" : "neutral"),
            "  </div>"
        ].join("");
    }

    function renderReportBreakdownButton(filter, label, count, tone) {
        return [
            '<button class="exam-report-breakdown__item' + (state.assessment.filter === filter ? " is-active" : "") + (tone ? " is-" + escapeHtml(tone) : "") + '" type="button" data-action="review-filter-focus" data-filter="' + escapeHtml(filter) + '"' + (filter !== "all" && count <= 0 ? " disabled" : "") + ">",
            '  <strong>' + escapeHtml(formatValue(count)) + "</strong>",
            '  <span>' + escapeHtml(label) + "</span>",
            "</button>"
        ].join("");
    }

    function renderReportInsightCard(label, value, tone) {
        return [
            '<article class="exam-report-insight' + (tone ? " is-" + escapeHtml(tone) : "") + '">',
            '  <span class="exam-report-insight__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-report-insight__value">' + escapeHtml(value) + "</strong>",
            "</article>"
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
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="save-progress" data-mode="learning">ذخیره موقت</button>',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="filters" data-mode="learning">فیلترها</button>',
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="learning">فهرست سوال‌ها</button>',
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
            return '<button class="exam-filter-pill' + (state.assessment.filter === item.key ? " is-active" : "") + '" type="button" data-action="assessment-filter" data-filter="' + escapeHtml(item.key) + '">' + escapeHtml(item.label) + ' <span>' + escapeHtml(formatValue(assessmentFilterCount(item.key, reviewMode))) + "</span></button>";
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
            question.explanation ? renderExplanationDisclosure(question.explanation, {
                label: "پاسخ تشریحی",
                open: true,
                tone: isCorrect ? "success" : "warning"
            }) : briefCopy,
            isFlagged(questionIndex) ? '<span class="exam-answer-card__hint">این سوال نشان‌دار شده و بعداً سریع پیدایش می‌کنی.</span>' : "",
            "</div>"
        ].join("");
    }

    function renderStageEmptyState(copy, title) {
        return [
            '<section class="exam-empty-card">',
            '  <h3>' + escapeHtml(title || "نمایش خالی شد") + "</h3>",
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

    function canShowAssessmentComparisons(report) {
        return Boolean(report) && Number(report.participantCount || 0) >= 10;
    }

    function assessmentFilterCount(filter, reviewMode) {
        var derived = getAssessmentDerived();
        if (reviewMode) {
            if (!derived.reviewMode) {
                return filter === "all" ? exam.questions.length : 0;
            }
            return Number(derived.counts[normalizeAssessmentFilter(filter)] || (filter === "all" ? exam.questions.length : 0));
        }

        if (filter === "answered") {
            return Number(derived.counts.answered || 0);
        }
        if (filter === "unanswered") {
            return Number(derived.counts.unanswered || 0);
        }
        if (filter === "flagged") {
            return Number(derived.counts.flagged || 0);
        }
        return exam.questions.length;
    }

    function reportFilterEmptyTitle(filter) {
        if (filter === "wrong") {
            return "غلطی برای مرور نداری";
        }
        if (filter === "flagged") {
            return "سوال نشان‌دار پیدا نشد";
        }
        if (filter === "correct") {
            return "سوال صحیحی برای این نما نیست";
        }
        if (filter === "unanswered") {
            return "بی‌پاسخ‌ها تمام شد";
        }
        return "نمایش خالی شد";
    }

    function reportFilterEmptyCopy(filter) {
        if (filter === "wrong") {
            return "در این آزمون فعلاً سوال غلطی برای مرور باقی نمانده است.";
        }
        if (filter === "flagged") {
            return "هنوز سوال نشان‌داری برای مرور انتخاب نکرده‌ای.";
        }
        if (filter === "correct") {
            return "در این نما سوال صحیحی باقی نمانده است.";
        }
        if (filter === "unanswered") {
            return "در این آزمون همه سوال‌های این نما پاسخ گرفته‌اند.";
        }
        return "در این فیلتر سوالی برای مرور باقی نمانده است.";
    }

    function renderQuestionStatusBar(config) {
        var totalVisible = Array.isArray(config.visibleIndexes) ? config.visibleIndexes.length : exam.questions.length;
        var stateLabel = "\u0628\u06cc\u200c\u067e\u0627\u0633\u062e";
        var tone = "warning";

        if (config.selectedIndex !== null) {
            if (config.mode === "assessment") {
                stateLabel = "\u0627\u0646\u062a\u062e\u0627\u0628 \u0634\u062f";
                tone = "accent";
            } else {
                stateLabel = config.selectedIndex === exam.questions[config.questionIndex].correctIndex ? "\u062f\u0631\u0633\u062a" : "\u063a\u0644\u0637";
                tone = config.selectedIndex === exam.questions[config.questionIndex].correctIndex ? "success" : "danger";
            }
        }

        return [
            '<div class="exam-question-statusbar">',
            '  <span class="exam-meta-chip is-' + escapeHtml(tone) + '">' + escapeHtml(stateLabel) + "</span>",
            '  <span class="exam-question-statusbar__meta">' + escapeHtml("\u0645\u0648\u0642\u0639\u06cc\u062a " + formatValue((config.currentPosition || 0) + 1) + " \u0627\u0632 " + formatValue(totalVisible)) + "</span>",
            isFlagged(config.questionIndex) ? '  <span class="exam-meta-chip is-flagged">\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631</span>' : "",
            "</div>"
        ].join("");
    }

    function renderExplanationDisclosure(explanation, options) {
        var config = options || {};
        var text = normalizeText(explanation);
        if (!text) {
            return "";
        }
        return [
            '<details class="exam-explanation' + (config.tone ? " is-" + escapeHtml(config.tone) : "") + '"' + (config.open ? " open" : "") + '>',
            '  <summary class="exam-explanation__summary">',
            '    <span class="exam-explanation__label">' + escapeHtml(config.label || "\u067e\u0627\u0633\u062e \u062a\u0634\u0631\u06cc\u062d\u06cc") + "</span>",
            '    <span class="exam-explanation__toggle">\u0645\u0634\u0627\u0647\u062f\u0647</span>',
            "  </summary>",
            '  <div class="exam-explanation__body">' + richTextHtml(text) + "</div>",
            "</details>"
        ].join("");
    }

    function renderSidebarNavigator(config) {
        var visibleIndexes = Array.isArray(config.visibleIndexes) ? config.visibleIndexes : [];
        var currentPosition = visibleIndexes.indexOf(config.currentIndex);
        var pageSize = sidebarNavigatorWindowSize();
        var start = alignNavigatorOffset(config.mode, currentPosition < 0 ? 0 : currentPosition, visibleIndexes.length, pageSize);
        var chunk = visibleIndexes.slice(start, start + pageSize);

        return [
            '<div class="exam-side-section exam-side-section--navigator">',
            '  <div class="exam-side-section__head"><span class="exam-side-title">\u0646\u0627\u0648\u0628\u0631 \u0633\u0648\u0627\u0644\u200c\u0647\u0627</span><button class="exam-btn exam-btn--ghost exam-btn--inline" type="button" data-action="open-sheet" data-sheet="navigator" data-mode="' + escapeHtml(config.mode) + '">\u0646\u0645\u0627\u06cc\u0634 \u06a9\u0627\u0645\u0644</button></div>',
            '  <div class="exam-sidebar-nav">' + chunk.map(function (index) {
                var resolvedState = config.stateResolver(index);
                return [
                    '<button class="exam-sidebar-nav__item' + (index === config.currentIndex ? " is-active" : "") + (isFlagged(index) ? " is-flagged" : "") + '" type="button" data-action="' + escapeHtml(config.jumpAction) + '" data-question-index="' + escapeHtml(String(index)) + '" data-state="' + escapeHtml(resolvedState) + '">',
                    '  <strong>' + escapeHtml(formatValue(index + 1)) + "</strong>",
                    '  <span>' + escapeHtml(assessmentStateLabel(resolvedState)) + "</span>",
                    "</button>"
                ].join("");
            }).join("") + "</div>",
            visibleIndexes.length > chunk.length ? '  <p class="exam-side-copy">نمایش ' + escapeHtml(formatValue(start + 1)) + ' تا ' + escapeHtml(formatValue(Math.min(visibleIndexes.length, start + chunk.length))) + ' از ' + escapeHtml(formatValue(visibleIndexes.length)) + ' سوال در سایدبار. برای فهرست کامل از «نمایش کامل» استفاده کن.</p>' : "",
            "</div>"
        ].join("");
    }

    function renderMobileQuestionDock(config) {
        var visibleIndexes = config.visibleIndexes || [];
        var currentPosition = visibleIndexes.indexOf(config.questionIndex);
        if (currentPosition < 0) {
            return "";
        }
        var statusText = config.mode === "assessment-report"
            ? assessmentStateLabel(assessmentNavState(config.questionIndex))
            : (config.mode === "learning"
                ? assessmentStateLabel(learningNavState(config.questionIndex))
                : assessmentStateLabel(assessmentDraftState(state.assessment.answers[config.questionIndex])));
        var canClear = config.clearAction && (
            config.mode === "learning"
                ? state.learning.answers[config.questionIndex] !== null
                : state.assessment.answers[config.questionIndex] !== null
        );

        return [
            '<nav class="exam-mobile-dock" aria-label="' + escapeHtml("\u0646\u0627\u0648\u0628\u0631 \u0633\u0631\u06cc\u0639 \u0633\u0648\u0627\u0644") + '">',
            ("  <button class=\"exam-mobile-dock__btn\" type=\"button\" data-action=\"" + escapeHtml(config.prevAction) + "\"" + (currentPosition <= 0 ? " disabled" : "") + ">\u0642\u0628\u0644\u06cc</button>"),
            ("  <button class=\"exam-mobile-dock__btn" + (isFlagged(config.questionIndex) ? " is-flagged" : "") + "\" type=\"button\" data-action=\"toggle-flag\" data-question-index=\"" + escapeHtml(String(config.questionIndex)) + "\">" + escapeHtml(isFlagged(config.questionIndex) ? "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631" : "\u0646\u0634\u0627\u0646") + "</button>"),
            config.clearAction
                ? ("  <button class=\"exam-mobile-dock__btn\" type=\"button\" data-action=\"" + escapeHtml(config.clearAction) + "\" data-question-index=\"" + escapeHtml(String(config.questionIndex)) + "\"" + (canClear ? "" : " disabled") + ">\u062d\u0630\u0641</button>")
                : ('  <span class="exam-mobile-dock__status">' + escapeHtml(statusText) + "</span>"),
            ("  <button class=\"exam-mobile-dock__btn exam-mobile-dock__btn--count\" type=\"button\" data-action=\"open-sheet\" data-sheet=\"navigator\" data-mode=\"" + escapeHtml(config.openNavigatorMode) + "\">" + escapeHtml(formatValue(config.questionIndex + 1)) + "/" + escapeHtml(formatValue(exam.questions.length)) + "</button>"),
            ("  <button class=\"exam-mobile-dock__btn exam-mobile-dock__btn--primary\" type=\"button\" data-action=\"" + escapeHtml(config.nextAction) + "\"" + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">\u0628\u0639\u062f\u06cc</button>"),
            "</nav>"
        ].join("");
    }

    function decorateDynamicContent() {
        var cards = appRoot.querySelectorAll(".exam-answer-card");
        cards.forEach(function (card) {
            if (card.hasAttribute("data-explanation-ready")) {
                return;
            }
            card.setAttribute("data-explanation-ready", "true");

            var body = card.querySelector(".exam-answer-card__body");
            if (body) {
                var details = document.createElement("details");
                details.className = "exam-explanation is-dynamic";
                details.open = true;
                details.innerHTML = '<summary class="exam-explanation__summary"><span class="exam-explanation__label">پاسخ تشریحی</span><span class="exam-explanation__toggle">مشاهده</span></summary><div class="exam-explanation__body">' + body.innerHTML + "</div>";
                body.replaceWith(details);
                return;
            }

            var label = card.querySelector(".exam-answer-card__label");
            var copy = card.querySelector(".exam-answer-card__copy");
            if (!label || !copy) {
                return;
            }
            if (String(label.textContent || "").indexOf("تشریح") === -1) {
                return;
            }

            var reportDetails = document.createElement("details");
            reportDetails.className = "exam-explanation is-dynamic";
            reportDetails.open = false;
            reportDetails.innerHTML = '<summary class="exam-explanation__summary"><span class="exam-explanation__label">' + escapeHtml(label.textContent || "پاسخ تشریحی") + '</span><span class="exam-explanation__toggle">مشاهده</span></summary><div class="exam-explanation__body">' + copy.innerHTML + "</div>";
            label.replaceWith(reportDetails);
            copy.remove();
        });
    }

    function renderConfirmDialog() {
        var dialog = state.ui.confirmDialog;
        if (!dialog) {
            return "";
        }

        return [
            '<div class="exam-confirm-backdrop" data-action="close-confirm-dialog"></div>',
            '<section class="exam-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="exam-confirm-title">',
            '  <div class="exam-confirm-dialog__copy">',
            '    <span class="exam-kicker">' + escapeHtml(dialog.kicker || "تایید اقدام") + "</span>",
            '    <h3 class="exam-confirm-dialog__title" id="exam-confirm-title">' + escapeHtml(dialog.title || "ادامه بده؟") + "</h3>",
            '    <p class="exam-confirm-dialog__text">' + escapeHtml(dialog.text || "") + "</p>",
            "  </div>",
            '  <div class="exam-confirm-dialog__actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="close-confirm-dialog">انصراف</button>',
            '    <button class="exam-btn ' + escapeHtml(dialog.confirmKind === "danger" ? "exam-btn--danger" : "exam-btn--primary") + '" type="button" data-action="confirm-dialog-action">' + escapeHtml(dialog.confirmLabel || "تایید") + "</button>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderBusyOverlay() {
        if (!state.ui.busyAction) {
            return "";
        }

        return [
            '<div class="exam-busy-overlay" role="status" aria-live="polite" aria-busy="true">',
            '  <section class="exam-busy-card">',
            '    <span class="exam-kicker">در حال پردازش</span>',
            '    <strong class="exam-busy-card__title">' + escapeHtml(state.ui.busyLabel || "لطفاً کمی صبر کن...") + "</strong>",
            '    <div class="exam-busy-card__skeleton">',
            '      <span class="exam-skeleton exam-skeleton--line"></span>',
            '      <span class="exam-skeleton exam-skeleton--line is-short"></span>',
            '      <div class="exam-skeleton-grid">',
            '        <span class="exam-skeleton exam-skeleton--tile"></span>',
            '        <span class="exam-skeleton exam-skeleton--tile"></span>',
            '        <span class="exam-skeleton exam-skeleton--tile"></span>',
            "      </div>",
            "    </div>",
            "  </section>",
            "</div>"
        ].join("");
    }

    function renderToastRegion() {
        if (!state.ui.toast || !state.ui.toast.text) {
            return "";
        }

        return [
            '<div class="exam-toast-stack" aria-live="polite" aria-atomic="true">',
            '  <div class="exam-toast exam-toast--' + escapeHtml(state.ui.toast.kind || "neutral") + '">',
            '    <span class="exam-toast__copy">' + escapeHtml(state.ui.toast.text) + "</span>",
            '    <button class="exam-toast__close" type="button" data-action="close-toast" aria-label="' + escapeHtml("بستن اعلان") + '">×</button>',
            "  </div>",
            "</div>"
        ].join("");
    }

    function renderActiveSheet() {
        if (!state.layout.activeSheet) {
            return "";
        }
        if (state.layout.activeSheet === "filters") {
            return renderFilterSheet(state.layout.sheetMode || state.mode || "assessment");
        }
        if (state.layout.activeSheet === "navigator") {
            return renderNavigatorSheet(state.layout.sheetMode || state.mode || "assessment");
        }
        return "";
    }

    function renderFilterSheet(mode) {
        var isLearning = mode === "learning";
        return [
            '<div class="exam-sheet-backdrop" data-action="close-sheet"></div>',
            '<section class="exam-sheet exam-sheet--filters" role="dialog" aria-modal="true" aria-label="' + escapeHtml("فیلتر سوال‌ها") + '">',
            '  <div class="exam-sheet__handle" aria-hidden="true"></div>',
            '  <div class="exam-sheet__head">',
            '    <div><strong>فیلتر سوال‌ها</strong><span>' + escapeHtml(isLearning ? "نمایش سریع سوال‌های آموزشی" : "صحیح، غلط، بی‌پاسخ و نشان‌دار") + "</span></div>",
            '    <button class="exam-sheet__close" type="button" data-action="close-sheet">بازگشت</button>',
            "  </div>",
            '  <div class="exam-sheet__body">',
            '    <div class="exam-sheet__chips">' + (
                isLearning
                    ? [
                        renderLearningFilterButton("all", "همه", exam.questions.length),
                        renderLearningFilterButton("flagged", "نشان‌دار", state.flags.size)
                    ].join("")
                    : renderAssessmentFilterButtons(Boolean(state.assessment.report))
            ) + "</div>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderNavigatorSheet(mode) {
        var isLearning = mode === "learning";
        var visibleIndexes = isLearning ? learningVisibleIndexes() : assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = isLearning ? ensureLearningIndex(visibleIndexes) : ensureAssessmentIndex(visibleIndexes);
        var currentPosition = Math.max(0, visibleIndexes.indexOf(currentIndex));
        var pageSize = navigatorChunkSize();
        var start = alignNavigatorOffset(mode, currentPosition, visibleIndexes.length, pageSize);
        var chunk = visibleIndexes.slice(start, start + pageSize);
        var stateFn = isLearning ? learningNavState : assessmentNavState;
        var jumpAction = isLearning ? "learning-goto" : "jump-to-question";

        return [
            '<div class="exam-sheet-backdrop" data-action="close-sheet"></div>',
            '<section class="exam-sheet exam-sheet--navigator" role="dialog" aria-modal="true" aria-label="' + escapeHtml("فهرست سوال‌ها") + '">',
            '  <div class="exam-sheet__handle" aria-hidden="true"></div>',
            '  <div class="exam-sheet__head">',
            '    <div><strong>فهرست سوال‌ها</strong><span>' + escapeHtml("نمایش سبک " + formatValue(chunk.length) + " سوال از " + formatValue(visibleIndexes.length)) + "</span></div>",
            '    <button class="exam-sheet__close" type="button" data-action="close-sheet">بازگشت</button>',
            "  </div>",
            '  <div class="exam-sheet__body">',
            '    <div class="exam-sheet__summary">',
            renderProgressLegendItem("جاری", formatValue(currentIndex + 1), "accent"),
            renderProgressLegendItem("فیلتر", isLearning ? learningFilterLabel(state.learning.filter) : assessmentFilterLabel(state.assessment.filter), "neutral"),
            renderProgressLegendItem("نشان", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "    </div>",
            '    <div class="exam-sheet__grid">' + chunk.map(function (index) {
                return [
                    '<button class="exam-sheet__pill' + (index === currentIndex ? " is-active" : "") + (isFlagged(index) ? " is-flagged" : "") + '" type="button" data-action="' + escapeHtml(jumpAction) + '" data-question-index="' + escapeHtml(String(index)) + '" data-state="' + escapeHtml(stateFn(index)) + '">',
                    '  <strong>' + escapeHtml(formatValue(index + 1)) + "</strong>",
                    '  <span>' + escapeHtml(assessmentStateLabel(stateFn(index))) + "</span>",
                    "</button>"
                ].join("");
            }).join("") + "</div>",
            '    <div class="exam-sheet__footer">',
            "      <button class=\"exam-btn exam-btn--ghost\" type=\"button\" data-action=\"navigator-prev-chunk\" data-mode=\"" + escapeHtml(mode) + "\"" + (start <= 0 ? " disabled" : "") + ">بخش قبلی</button>",
            '      <span class="exam-sheet__footer-copy">' + escapeHtml("بخش " + formatValue(Math.floor(start / pageSize) + 1) + " از " + formatValue(Math.max(1, Math.ceil(visibleIndexes.length / pageSize)))) + "</span>",
            "      <button class=\"exam-btn exam-btn--ghost\" type=\"button\" data-action=\"navigator-next-chunk\" data-mode=\"" + escapeHtml(mode) + "\"" + (start + pageSize >= visibleIndexes.length ? " disabled" : "") + ">بخش بعدی</button>",
            "    </div>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function handleClick(event) {
        var linkNode = event.target.closest("a.exam-back-link");
        if (linkNode && !linkNode.hasAttribute("data-action")) {
            if (!confirmExitIfNeeded()) {
                event.preventDefault();
                return;
            }
            flushPendingStatePersistence();
            flushFlagSync();
            return;
        }

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
        if (action === "close-confirm-dialog") {
            closeConfirmDialog();
            return;
        }
        if (action === "confirm-dialog-action") {
            confirmDialogAction();
            return;
        }
        if (action === "close-toast") {
            clearToast();
            return;
        }
        if (action === "open-sheet") {
            openSheet(String(actionNode.getAttribute("data-sheet") || ""), normalizeMode(actionNode.getAttribute("data-mode")) || state.mode || "assessment");
            return;
        }
        if (action === "close-sheet") {
            closeSheet();
            render();
            return;
        }
        if (action === "navigator-prev-chunk") {
            shiftNavigatorChunk(normalizeMode(actionNode.getAttribute("data-mode")) || state.mode || "assessment", -1);
            return;
        }
        if (action === "navigator-next-chunk") {
            shiftNavigatorChunk(normalizeMode(actionNode.getAttribute("data-mode")) || state.mode || "assessment", 1);
            return;
        }
        if (action === "toggle-flag") {
            toggleFlag(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "save-progress") {
            saveTemporaryProgress(normalizeMode(actionNode.getAttribute("data-mode")) || state.mode);
            return;
        }
        if (action === "assessment-filter") {
            state.assessment.filter = String(actionNode.getAttribute("data-filter") || "all");
            ensureAssessmentIndex(assessmentVisibleIndexes(state.assessment.filter));
            persistAssessmentState();
            if (window.innerWidth <= 680 && state.layout.activeSheet === "filters") {
                closeSheet();
            }
            render();
            return;
        }
        if (action === "review-filter-focus") {
            focusAssessmentFilter(String(actionNode.getAttribute("data-filter") || "all"));
            return;
        }
        if (action === "learning-filter") {
            state.learning.filter = String(actionNode.getAttribute("data-filter") || "all");
            ensureLearningIndex(learningVisibleIndexes());
            persistLearningState();
            if (window.innerWidth <= 680 && state.layout.activeSheet === "filters") {
                closeSheet();
            }
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
        if (action === "assessment-clear-answer") {
            clearAssessmentAnswer(parseIndex(actionNode.getAttribute("data-question-index")));
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
            closeSheet();
            return;
        }
        if (action === "learning-goto") {
            jumpToLearningQuestion(parseIndex(actionNode.getAttribute("data-question-index")));
            closeSheet();
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
        if (action === "learning-clear-answer") {
            clearLearningAnswer(parseIndex(actionNode.getAttribute("data-question-index")));
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

    function handleKeydown(event) {
        if (event.defaultPrevented) {
            return;
        }
        var target = event.target;
        if (target && /input|textarea|select/i.test(target.tagName || "")) {
            return;
        }
        if (state.ui.confirmDialog && event.key === "Escape") {
            closeConfirmDialog();
            return;
        }
        if (state.layout.activeSheet && event.key === "Escape") {
            closeSheet();
            render();
            return;
        }
        if (!state.mode || !isModeStarted(state.mode)) {
            return;
        }

        if (event.key === "ArrowLeft" || event.key === "PageDown") {
            event.preventDefault();
            navigateCurrentMode(1);
            return;
        }
        if (event.key === "ArrowRight" || event.key === "PageUp") {
            event.preventDefault();
            navigateCurrentMode(-1);
            return;
        }
        if (event.key === "Delete" || event.key === "Backspace") {
            event.preventDefault();
            clearCurrentAnswer();
            return;
        }
        if (String(event.key || "").toLowerCase() === "b") {
            event.preventDefault();
            toggleFlag(currentQuestionIndex());
        }
    }

    function handleTouchStart(event) {
        if (state.layout.activeSheet) {
            return;
        }
        var touch = event.changedTouches && event.changedTouches[0];
        var target = event.target;
        if (!touch || !target || !target.closest || !target.closest(".exam-session-card--question") || target.closest("button, a, summary")) {
            clearSwipeState();
            return;
        }
        state.interaction.swipeStart = {
            x: touch.clientX,
            y: touch.clientY,
            time: Date.now()
        };
    }

    function handleTouchEnd(event) {
        var start = state.interaction.swipeStart;
        clearSwipeState();
        if (!start || state.layout.activeSheet) {
            return;
        }
        var touch = event.changedTouches && event.changedTouches[0];
        if (!touch) {
            return;
        }
        var dx = touch.clientX - start.x;
        var dy = touch.clientY - start.y;
        var elapsed = Date.now() - start.time;
        if (elapsed > 900 || Math.abs(dx) < 54 || Math.abs(dx) < Math.abs(dy) * 1.2) {
            return;
        }
        navigateCurrentMode(dx < 0 ? 1 : -1);
    }

    function clearSwipeState() {
        state.interaction.swipeStart = null;
    }

    function setMode(mode) {
        state.mode = mode;
        closeSheet();
        state.ui.confirmDialog = null;
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
        closeSheet();
        state.ui.confirmDialog = null;
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
        state.interaction.lastLearningRevealIndex = -1;
        invalidateAssessmentDerived();
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
        state.interaction.lastLearningRevealIndex = questionIndex;
        invalidateLearningDerived();
        persistLearningState();
        render();
    }

    function clearAssessmentAnswer(questionIndex) {
        if (state.assessment.report) {
            return;
        }
        var targetIndex = isValidQuestionIndex(questionIndex) ? questionIndex : state.assessment.currentQuestionIndex;
        if (!isValidQuestionIndex(targetIndex)) {
            return;
        }
        state.assessment.answers[targetIndex] = null;
        state.assessment.currentQuestionIndex = targetIndex;
        invalidateAssessmentDerived();
        persistAssessmentState();
        render();
    }

    function clearLearningAnswer(questionIndex) {
        var targetIndex = isValidQuestionIndex(questionIndex) ? questionIndex : state.learning.currentQuestionIndex;
        if (!isValidQuestionIndex(targetIndex)) {
            return;
        }
        state.learning.answers[targetIndex] = null;
        state.learning.revealed[targetIndex] = false;
        state.learning.currentQuestionIndex = targetIndex;
        state.interaction.lastLearningRevealIndex = -1;
        invalidateLearningDerived();
        persistLearningState();
        render();
    }

    function resetAssessmentDraft() {
        if (state.assessment.report) {
            return;
        }
        openConfirmDialog({
            intent: "reset-assessment-draft",
            kicker: "پاک‌کردن پاسخ‌ها",
            title: "پاسخ‌های سنجشی پاک شوند؟",
            text: "همه انتخاب‌های فعلی این آزمون حذف می‌شود و سنجشی از اول ادامه پیدا می‌کند.",
            confirmLabel: "پاک کن",
            confirmKind: "danger"
        });
    }

    function submitAssessment() {
        if (!exam.viewerState.canPersist || state.assessment.submitting || state.assessment.report) {
            return;
        }

        var totals = assessmentDraftTotals();
        if (totals.unanswered > 0) {
            openConfirmDialog({
                intent: "submit-assessment",
                kicker: "ثبت نهایی آزمون",
                title: "آزمون با سوال‌های بی‌پاسخ ثبت شود؟",
                text: "هنوز " + formatValue(totals.unanswered) + " سوال بی‌پاسخ مانده است. اگر ادامه بدهی، همین وضعیت به‌عنوان کارنامه نهایی ثبت می‌شود.",
                confirmLabel: "ثبت نهایی",
                confirmKind: "primary"
            });
            return;
        }
        performSubmitAssessment();
    }

    function resetAssessmentReport() {
        if (!state.assessment.report) {
            return;
        }
        openConfirmDialog({
            intent: "reset-assessment-report",
            kicker: "شرکت مجدد",
            title: "کارنامه پاک شود و دوباره شرکت کنی؟",
            text: "کارنامه ثبت‌شده حذف می‌شود و سنجشی از ابتدا برایت باز می‌شود.",
            confirmLabel: "شرکت مجدد",
            confirmKind: "danger"
        });
    }

    function resetLearningProgress() {
        openConfirmDialog({
            intent: "reset-learning-progress",
            kicker: "شروع دوباره آموزشی",
            title: "پیشرفت آموزشی از اول شروع شود؟",
            text: "پاسخ‌ها و مرورهای آموزشی این جلسه پاک می‌شود و از سوال اول برمی‌گردی.",
            confirmLabel: "شروع دوباره",
            confirmKind: "danger"
        });
    }

    function performResetAssessmentDraft() {
        state.assessment.answers = createNullArray(exam.questions.length);
        state.assessment.startedAt = new Date().toISOString();
        state.assessment.filter = "all";
        state.assessment.currentQuestionIndex = 0;
        state.assessment.started = true;
        state.assessment.savedAt = "";
        invalidateAssessmentDerived();
        clearFeedback();
        persistAssessmentState();
        render();
    }

    function performSubmitAssessment() {
        state.assessment.submitting = true;
        setBusy("submit-assessment", "در حال ثبت کارنامه و آماده‌سازی مرور...");
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
            state.assessment.savedAt = payload.report.submittedAt || new Date().toISOString();
            invalidateAssessmentDerived();
            clearBusy();
            setFeedback("success", payload.message || "کارنامه این آزمون ذخیره شد.");
            persistAssessmentState(true);
            render();
        }).catch(function (error) {
            state.assessment.submitting = false;
            clearBusy();
            setFeedback("error", error && error.message ? error.message : "ثبت آزمون انجام نشد.");
            render();
        });
    }

    function performResetAssessmentReport() {
        setBusy("reset-assessment-report", "در حال پاک‌کردن کارنامه و باز کردن سنجشی...");
        clearFeedback();
        render();

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
            state.assessment.savedAt = "";
            invalidateAssessmentDerived();
            clearBusy();
            setFeedback("success", payload.message || "کارنامه این آزمون ریست شد.");
            persistAssessmentState(true);
            render();
        }).catch(function (error) {
            clearBusy();
            setFeedback("error", error && error.message ? error.message : "ریست کارنامه انجام نشد.");
            render();
        });
    }

    function performResetLearningProgress() {
        state.learning.answers = createNullArray(exam.questions.length);
        state.learning.revealed = createFalseArray(exam.questions.length);
        state.learning.currentQuestionIndex = 0;
        state.learning.filter = "all";
        state.learning.started = true;
        state.learning.savedAt = "";
        state.interaction.lastLearningRevealIndex = -1;
        invalidateLearningDerived();
        clearFeedback();
        persistLearningState();
        render();
    }

    function openConfirmDialog(config) {
        state.ui.confirmDialog = {
            intent: normalizeText(config && config.intent),
            kicker: normalizeText(config && config.kicker),
            title: normalizeText(config && config.title),
            text: normalizeText(config && config.text),
            confirmLabel: normalizeText(config && config.confirmLabel) || "تایید",
            confirmKind: normalizeText(config && config.confirmKind) || "primary"
        };
        closeSheet();
        render();
    }

    function closeConfirmDialog(skipRender) {
        state.ui.confirmDialog = null;
        if (!skipRender) {
            render();
        }
    }

    function confirmDialogAction() {
        if (!state.ui.confirmDialog) {
            return;
        }
        var intent = state.ui.confirmDialog.intent;
        closeConfirmDialog(true);
        if (intent === "reset-assessment-draft") {
            performResetAssessmentDraft();
            return;
        }
        if (intent === "submit-assessment") {
            performSubmitAssessment();
            return;
        }
        if (intent === "reset-assessment-report") {
            performResetAssessmentReport();
            return;
        }
        if (intent === "reset-learning-progress") {
            performResetLearningProgress();
            return;
        }
        render();
    }

    function focusAssessmentFilter(filter) {
        state.assessment.filter = normalizeAssessmentFilter(filter);
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        state.assessment.currentQuestionIndex = visibleIndexes.length ? visibleIndexes[0] : 0;
        persistAssessmentState();
        if (window.innerWidth <= 680 && state.layout.activeSheet === "filters") {
            closeSheet();
        }
        render();
    }

    function setBusy(action, label) {
        state.ui.busyAction = normalizeText(action);
        state.ui.busyLabel = normalizeText(label);
    }

    function clearBusy() {
        state.ui.busyAction = "";
        state.ui.busyLabel = "";
    }

    function isBusyAction(action) {
        return state.ui.busyAction === action;
    }

    function showToast(kind, text) {
        if (toastTimer) {
            window.clearTimeout(toastTimer);
            toastTimer = 0;
        }
        state.ui.toast = { kind: kind || "neutral", text: text || "" };
        if (!state.ui.toast.text) {
            return;
        }
        toastTimer = window.setTimeout(function () {
            toastTimer = 0;
            clearToast();
        }, 3600);
    }

    function clearToast(skipRender) {
        if (toastTimer) {
            window.clearTimeout(toastTimer);
            toastTimer = 0;
        }
        state.ui.toast = null;
        if (!skipRender) {
            render();
        }
    }

    function invalidateAssessmentDerived() {
        assessmentDerivedCache = null;
    }

    function invalidateLearningDerived() {
        learningDerivedCache = null;
    }

    function invalidateDerivedCollections() {
        invalidateAssessmentDerived();
        invalidateLearningDerived();
    }

    function getAssessmentDerived() {
        if (assessmentDerivedCache) {
            return assessmentDerivedCache;
        }

        var reviewMode = Boolean(state.assessment.report);
        var answers = reviewMode ? state.assessment.report.answers : state.assessment.answers;
        var visibleByFilter = {
            all: [],
            answered: [],
            unanswered: [],
            flagged: [],
            correct: [],
            wrong: []
        };
        var counts = {
            all: exam.questions.length,
            answered: 0,
            unanswered: 0,
            flagged: 0,
            correct: 0,
            wrong: 0
        };
        var states = new Array(exam.questions.length);

        for (var index = 0; index < exam.questions.length; index++) {
            var selectedIndex = answers[index];
            var stateName = reviewMode
                ? assessmentReviewState(index, selectedIndex)
                : assessmentDraftState(selectedIndex);

            states[index] = stateName;
            visibleByFilter.all.push(index);

            if (selectedIndex !== null) {
                counts.answered++;
                visibleByFilter.answered.push(index);
            }
            if (stateName === "unanswered") {
                counts.unanswered++;
                visibleByFilter.unanswered.push(index);
            }
            if (stateName === "correct") {
                counts.correct++;
                visibleByFilter.correct.push(index);
            }
            if (stateName === "wrong") {
                counts.wrong++;
                visibleByFilter.wrong.push(index);
            }
            if (state.flags.has(index)) {
                counts.flagged++;
                visibleByFilter.flagged.push(index);
            }
        }

        assessmentDerivedCache = {
            reviewMode: reviewMode,
            states: states,
            visibleByFilter: visibleByFilter,
            counts: counts,
            draftTotals: {
                answered: counts.answered,
                unanswered: counts.unanswered
            }
        };
        return assessmentDerivedCache;
    }

    function getLearningDerived() {
        if (learningDerivedCache) {
            return learningDerivedCache;
        }

        var visibleAll = [];
        var visibleFlagged = [];
        var states = new Array(exam.questions.length);
        var answered = 0;

        for (var index = 0; index < exam.questions.length; index++) {
            var selectedIndex = state.learning.answers[index];
            var stateName = "unanswered";
            if (selectedIndex !== null) {
                answered++;
                stateName = state.learning.revealed[index]
                    ? (selectedIndex === exam.questions[index].correctIndex ? "correct" : "wrong")
                    : "answered";
            }
            states[index] = stateName;
            visibleAll.push(index);
            if (state.flags.has(index)) {
                visibleFlagged.push(index);
            }
        }

        learningDerivedCache = {
            states: states,
            visibleByFilter: {
                all: visibleAll,
                flagged: visibleFlagged
            },
            totals: {
                answered: answered,
                unanswered: exam.questions.length - answered
            }
        };
        return learningDerivedCache;
    }

    function assessmentVisibleIndexes(filter) {
        var normalizedFilter = normalizeAssessmentFilter(filter);
        var visible = getAssessmentDerived().visibleByFilter[normalizedFilter];
        return Array.isArray(visible) ? visible : getAssessmentDerived().visibleByFilter.all;
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
        var normalizedFilter = normalizeLearningFilter(state.learning.filter);
        return getLearningDerived().visibleByFilter[normalizedFilter];
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

    function navigateCurrentMode(step) {
        if (state.mode === "learning") {
            moveLearning(step);
            return;
        }
        if (state.mode === "assessment") {
            moveAssessment(step);
        }
    }

    function currentQuestionIndex() {
        return state.mode === "learning" ? state.learning.currentQuestionIndex : state.assessment.currentQuestionIndex;
    }

    function clearCurrentAnswer() {
        if (state.mode === "learning") {
            clearLearningAnswer(state.learning.currentQuestionIndex);
            return;
        }
        if (state.mode === "assessment" && !state.assessment.report) {
            clearAssessmentAnswer(state.assessment.currentQuestionIndex);
        }
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

        invalidateDerivedCollections();
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
        }, FLAG_SYNC_DEBOUNCE_MS);
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
        }, {
            keepalive: true,
            timeoutMs: 4000
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

    function saveTemporaryProgress(mode) {
        var normalizedMode = normalizeMode(mode);
        if (!normalizedMode) {
            return;
        }

        var savedAt = new Date().toISOString();
        if (normalizedMode === "assessment") {
            state.assessment.started = true;
            state.assessment.savedAt = savedAt;
            persistAssessmentState(true);
        } else {
            state.learning.started = true;
            state.learning.savedAt = savedAt;
            persistLearningState(true);
        }

        if (state.flagsSync.queued && exam.viewerState.canPersist) {
            flushFlagSync();
        }

        setFeedback("success", "آخرین وضعیت این آزمون موقتاً ذخیره شد.");
        render();
    }

    function writeAssessmentState() {
        try {
            window.sessionStorage.setItem(assessmentDraftKey, JSON.stringify({
                answers: state.assessment.report ? state.assessment.report.answers : state.assessment.answers,
                startedAt: state.assessment.startedAt,
                filter: state.assessment.filter,
                currentQuestionIndex: state.assessment.currentQuestionIndex,
                started: state.assessment.started,
                savedAt: state.assessment.savedAt || ""
            }));
        } catch (_error) {
            return;
        }
    }

    function writeLearningState() {
        try {
            window.sessionStorage.setItem(learningDraftKey, JSON.stringify({
                answers: state.learning.answers,
                revealed: state.learning.revealed,
                currentQuestionIndex: state.learning.currentQuestionIndex,
                filter: state.learning.filter,
                started: state.learning.started,
                savedAt: state.learning.savedAt || ""
            }));
        } catch (_error) {
            return;
        }
    }

    function persistAssessmentState(force) {
        if (force) {
            if (assessmentPersistTimer) {
                window.clearTimeout(assessmentPersistTimer);
                assessmentPersistTimer = 0;
            }
            writeAssessmentState();
            return;
        }

        if (assessmentPersistTimer) {
            window.clearTimeout(assessmentPersistTimer);
        }
        assessmentPersistTimer = window.setTimeout(function () {
            assessmentPersistTimer = 0;
            writeAssessmentState();
        }, STORAGE_PERSIST_DEBOUNCE_MS);
    }

    function persistLearningState(force) {
        if (force) {
            if (learningPersistTimer) {
                window.clearTimeout(learningPersistTimer);
                learningPersistTimer = 0;
            }
            writeLearningState();
            return;
        }

        if (learningPersistTimer) {
            window.clearTimeout(learningPersistTimer);
        }
        learningPersistTimer = window.setTimeout(function () {
            learningPersistTimer = 0;
            writeLearningState();
        }, STORAGE_PERSIST_DEBOUNCE_MS);
    }

    function flushPendingStatePersistence() {
        if (assessmentPersistTimer) {
            window.clearTimeout(assessmentPersistTimer);
            assessmentPersistTimer = 0;
            writeAssessmentState();
        }
        if (learningPersistTimer) {
            window.clearTimeout(learningPersistTimer);
            learningPersistTimer = 0;
            writeLearningState();
        }
    }

    function assessmentDraftTotals() {
        var totals = getAssessmentDerived().draftTotals;
        return {
            answered: Number(totals.answered || 0),
            unanswered: Number(totals.unanswered || 0)
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
        var totals = getLearningDerived().totals;
        return {
            answered: Number(totals.answered || 0),
            unanswered: Number(totals.unanswered || 0)
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
        return getAssessmentDerived().states[questionIndex] || "unanswered";
    }

    function learningNavState(questionIndex) {
        return getLearningDerived().states[questionIndex] || "unanswered";
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
        var scaler = appRoot.querySelector(".exam-stage-scaler");
        if (!scaler) {
            return;
        }

        scaler.style.setProperty("--exam-stage-scale", "1");
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

    function sidebarNavigatorWindowSize() {
        if (window.innerWidth <= 860) {
            return 10;
        }
        if (window.innerWidth <= 1180) {
            return 14;
        }
        return 18;
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

    function setFeedback(kind, text, options) {
        var config = options || {};
        state.feedback.kind = kind || "neutral";
        state.feedback.text = text || "";
        if (!text) {
            return;
        }
        if (config.toast !== false) {
            showToast(state.feedback.kind, state.feedback.text);
        }
    }

    function clearFeedback() {
        state.feedback.kind = "";
        state.feedback.text = "";
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

    function apiPost(action, payload, options) {
        var config = options || {};
        var body = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        return fetchWithTimeout("/api/exams_api.php", {
            method: "POST",
            credentials: "same-origin",
            keepalive: Boolean(config.keepalive),
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: body.toString()
        }, config.timeoutMs);
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
        }, {
            keepalive: true,
            timeoutMs: 4000
        }).catch(function () {
            return null;
        });
    }

    function handleBeforeUnload(event) {
        flushPendingStatePersistence();
        flushFlagSync();
        if (!shouldWarnBeforeExit()) {
            return;
        }

        event.preventDefault();
        event.returnValue = "";
    }

    function shouldWarnBeforeExit() {
        var assessmentSummary = modeSummary("assessment");
        var learningSummary = modeSummary("learning");
        var hasAssessmentDraft = !state.assessment.report && assessmentSummary.canResume;
        var hasLearningDraft = learningSummary.canResume;
        return hasAssessmentDraft || hasLearningDraft || state.flagsSync.queued;
    }

    function confirmExitIfNeeded() {
        if (!shouldWarnBeforeExit()) {
            return true;
        }
        return window.confirm("آخرین وضعیت این آزمون هنوز باز است. اگر خارج شوی، ادامه را بعداً از همین‌جا برمی‌داری. مطمئنی می‌خواهی خارج شوی؟");
    }

    function openSheet(sheetName, mode) {
        var normalizedMode = normalizeMode(mode) || state.mode || "assessment";
        if (sheetName !== "filters" && sheetName !== "navigator") {
            return;
        }

        state.layout.activeSheet = sheetName;
        state.layout.sheetMode = normalizedMode;
        if (sheetName === "navigator") {
            alignNavigatorOffsetToCurrent(normalizedMode);
        }
        render();
    }

    function closeSheet() {
        state.layout.activeSheet = "";
        state.layout.sheetMode = "";
    }

    function navigatorChunkSize() {
        if (window.innerWidth <= 520) {
            return 12;
        }
        if (window.innerWidth <= 820) {
            return 15;
        }
        return 24;
    }

    function alignNavigatorOffset(mode, currentPosition, totalLength, pageSize) {
        var normalizedMode = normalizeMode(mode) || "assessment";
        var maxStart = Math.max(0, totalLength - pageSize);
        var stored = clampNavigatorOffset(state.layout.navigatorOffset[normalizedMode], maxStart);
        var current = Math.max(0, Number(currentPosition || 0));

        if (current < stored || current >= stored + pageSize) {
            stored = Math.floor(current / pageSize) * pageSize;
        }

        stored = clampNavigatorOffset(stored, maxStart);
        state.layout.navigatorOffset[normalizedMode] = stored;
        return stored;
    }

    function alignNavigatorOffsetToCurrent(mode) {
        var normalizedMode = normalizeMode(mode) || "assessment";
        var visibleIndexes = normalizedMode === "learning"
            ? learningVisibleIndexes()
            : assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = normalizedMode === "learning"
            ? ensureLearningIndex(visibleIndexes)
            : ensureAssessmentIndex(visibleIndexes);
        var currentPosition = Math.max(0, visibleIndexes.indexOf(currentIndex));
        alignNavigatorOffset(normalizedMode, currentPosition, visibleIndexes.length, navigatorChunkSize());
    }

    function shiftNavigatorChunk(mode, step) {
        var normalizedMode = normalizeMode(mode) || "assessment";
        var visibleIndexes = normalizedMode === "learning"
            ? learningVisibleIndexes()
            : assessmentVisibleIndexes(state.assessment.filter);
        var pageSize = navigatorChunkSize();
        var maxStart = Math.max(0, visibleIndexes.length - pageSize);
        var nextOffset = clampNavigatorOffset(
            Number(state.layout.navigatorOffset[normalizedMode] || 0) + (Number(step || 0) * pageSize),
            maxStart
        );
        state.layout.navigatorOffset[normalizedMode] = nextOffset;
        render();
    }

    function clampNavigatorOffset(value, maxStart) {
        var numeric = Number(value || 0);
        if (!Number.isFinite(numeric) || numeric < 0) {
            return 0;
        }
        if (numeric > maxStart) {
            return maxStart;
        }
        return numeric;
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

    function fetchWithTimeout(url, options, timeoutMs) {
        var waitMs = Number(timeoutMs || NETWORK_TIMEOUT_MS);
        if (waitMs <= 0 || typeof AbortController !== "function") {
            return fetch(url, options).then(parseJson);
        }

        var controller = new AbortController();
        var timer = window.setTimeout(function () {
            controller.abort();
        }, waitMs);
        var requestOptions = Object.assign({}, options || {}, { signal: controller.signal });

        return fetch(url, requestOptions).then(parseJson).catch(function (error) {
            if (error && error.name === "AbortError") {
                throw new Error("ارتباط با سرور بیشتر از حد انتظار طول کشید. دوباره تلاش کن.");
            }
            if ((typeof navigator !== "undefined" && navigator.onLine === false) || (error && error.name === "TypeError")) {
                throw new Error("ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کن و دوباره تلاش کن.");
            }
            throw error;
        }).finally(function () {
            window.clearTimeout(timer);
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
        var participantCount = maxNumber(rawReport.participantCount, 0);
        var canShowComparisons = participantCount >= 10;
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
            participantCount: participantCount,
            showRank: Boolean(rawReport.showRank) && canShowComparisons,
            overallCompletedExams: maxNumber(rawReport.overallCompletedExams, 0),
            overallAveragePercent: !canShowComparisons || rawReport.overallAveragePercent === null || rawReport.overallAveragePercent === undefined
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
            started: false,
            savedAt: ""
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
            empty.savedAt = normalizeText(saved.savedAt);
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
            started: false,
            savedAt: ""
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
            empty.savedAt = normalizeText(saved.savedAt);
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

    function assessmentFilterLabel(filter) {
        switch (normalizeAssessmentFilter(filter)) {
            case "answered":
                return "پاسخ‌داده";
            case "unanswered":
                return "بی‌پاسخ";
            case "flagged":
                return "نشان‌دار";
            case "correct":
                return "صحیح";
            case "wrong":
                return "غلط";
            default:
                return "همه سؤال‌ها";
        }
    }

    function learningFilterLabel(filter) {
        return normalizeLearningFilter(filter) === "flagged" ? "نشان‌دار" : "همه سؤال‌ها";
    }

    function lastSavedLabel(mode) {
        var normalizedMode = normalizeMode(mode);
        if (normalizedMode === "assessment" && state.assessment.savedAt) {
            return "ذخیره موقت: " + formatDateTime(state.assessment.savedAt);
        }
        if (normalizedMode === "learning" && state.learning.savedAt) {
            return "ذخیره موقت: " + formatDateTime(state.learning.savedAt);
        }
        return "";
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
