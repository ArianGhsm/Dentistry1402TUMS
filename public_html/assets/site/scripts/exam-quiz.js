(function () {
    "use strict";

    const appRoot = document.querySelector("[data-exam-app]");
    const dataNode = document.getElementById("exam-data");

    if (!appRoot || !dataNode) {
        return;
    }

    let parsedData;

    try {
        parsedData = JSON.parse(dataNode.textContent || "{}");
    } catch (error) {
        renderFailure("داده‌های آزمون قابل خواندن نیست.");
        return;
    }

    const exam = normalizeExamData(parsedData);

    if (!exam.questions.length) {
        renderFailure("برای این آزمون هنوز سؤالی ثبت نشده است.");
        return;
    }

    const storageKey = "dent1402:exam:" + window.location.pathname;
    const state = restoreState(storageKey, exam.questions.length);

    state.answers = state.answers.map(function (answer, index) {
        const optionCount = exam.questions[index] ? exam.questions[index].options.length : 0;
        return Number.isInteger(answer) && answer >= 0 && answer < optionCount ? answer : null;
    });

    buildShell();
    hydrateStaticCopy();
    attachStaticHandlers();
    render();

    function renderFailure(message) {
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<main class="exam-main">',
            '  <section class="exam-panel exam-empty-state">',
            "    <h1>خطا در بارگذاری آزمون</h1>",
            "    <p>" + escapeHtml(message) + "</p>",
            '    <a class="back-btn" href="/exams/">بازگشت به آزمون‌ها</a>',
            "  </section>",
            "</main>"
        ].join("");
    }

    function buildShell() {
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <header class="site-header exam-site-header">',
            '    <div class="logo-area">',
            '      <div class="logo-circle">',
            '        <img src="/assets/images/logo.png?v=20260422-brand1" alt="لوگوی ورودی ۱۴۰۲">',
            "      </div>",
            '      <div class="site-info">',
            "        <h1></h1>",
            "        <p></p>",
            "      </div>",
            "    </div>",
            '    <div class="badge-unofficial"></div>',
            "  </header>",
            '  <main class="exam-main">',
            '    <section class="exam-panel exam-hero">',
            '      <div class="exam-hero-top">',
            '        <a class="back-btn exam-back-link" href="#">',
            '          <span class="back-icon" aria-hidden="true">←</span>',
            '          <span class="exam-back-label"></span>',
            "        </a>",
            '        <div class="exam-hero-badges">',
            '          <span class="info-pill exam-eyebrow"></span>',
            '          <span class="exam-chip exam-total-chip"></span>',
            "        </div>",
            "      </div>",
            '      <div class="exam-hero-copy">',
            '        <h2 class="exam-title"></h2>',
            '        <p class="exam-subtitle"></p>',
            "      </div>",
            "    </section>",
            '    <section class="exam-layout">',
            '      <div class="exam-primary-stack">',
            '        <section class="exam-panel exam-progress-panel">',
            '          <div class="exam-progress-copy">',
            '            <div class="exam-progress-copy-row">',
            '              <span class="exam-progress-current"></span>',
            '              <span class="exam-progress-answered"></span>',
            "            </div>",
            '            <div class="exam-progress-copy-row exam-progress-copy-row--secondary">',
            '              <span class="exam-progress-label"></span>',
            '              <span class="exam-progress-percent"></span>',
            "            </div>",
            "          </div>",
            '          <div class="progress-bar exam-progress-bar">',
            '            <div class="progress-fill exam-progress-fill"></div>',
            "          </div>",
            "        </section>",
            '        <section class="exam-panel exam-question-panel">',
            '          <div class="exam-question-head">',
            '            <span class="question-tag exam-question-tag"></span>',
            '            <span class="exam-question-status"></span>',
            "          </div>",
            '          <h3 class="exam-question-text"></h3>',
            '          <div class="exam-option-grid"></div>',
            '          <div class="exam-action-row">',
            '            <div class="exam-action-group exam-action-group--main">',
            '              <button class="btn-base exam-btn-clear" type="button">پاک‌کردن پاسخ این سؤال</button>',
            '              <button class="btn-base exam-btn-jump" type="button">اولین سؤال بی‌پاسخ</button>',
            "            </div>",
            '            <div class="exam-action-group exam-action-group--nav">',
            '              <button class="btn-base exam-btn-prev" type="button">سؤال قبلی</button>',
            '              <button class="btn-base exam-btn-next" type="button">سؤال بعدی</button>',
            '              <button class="btn-base btn-primary exam-btn-finish" type="button">مشاهده نتیجه</button>',
            "            </div>",
            "          </div>",
            "        </section>",
            "      </div>",
            '      <aside class="exam-panel exam-sidebar">',
            '        <section class="exam-sidebar-section">',
            '          <div class="exam-sidebar-head">',
            '            <h3 class="exam-sidebar-title">وضعیت آزمون</h3>',
            '            <span class="exam-sidebar-note">پاسخ‌ها روی همین دستگاه نگه‌داری می‌شوند.</span>',
            "          </div>",
            '          <div class="exam-stat-grid">',
            '            <div class="exam-stat-card"><span class="exam-stat-label">کل سؤال‌ها</span><strong class="exam-stat-value exam-stat-total"></strong></div>',
            '            <div class="exam-stat-card"><span class="exam-stat-label">پاسخ‌داده‌شده</span><strong class="exam-stat-value exam-stat-answered"></strong></div>',
            '            <div class="exam-stat-card"><span class="exam-stat-label exam-stat-third-label">باقی‌مانده</span><strong class="exam-stat-value exam-stat-third"></strong></div>',
            '            <div class="exam-stat-card"><span class="exam-stat-label exam-stat-fourth-label">سؤال جاری</span><strong class="exam-stat-value exam-stat-fourth"></strong></div>',
            "          </div>",
            "        </section>",
            '        <section class="exam-sidebar-section">',
            '          <div class="exam-sidebar-head">',
            '            <h3 class="exam-sidebar-title">ناوبری سؤال‌ها</h3>',
            '            <span class="exam-sidebar-note exam-nav-summary"></span>',
            "          </div>",
            '          <div class="exam-nav-grid"></div>',
            "        </section>",
            '        <section class="exam-sidebar-section exam-sidebar-actions">',
            '          <button class="btn-base btn-primary exam-btn-results" type="button">به نتیجه و مرور پاسخ‌ها برو</button>',
            '          <button class="btn-base exam-btn-reset" type="button">شروع دوباره آزمون</button>',
            "        </section>",
            "      </aside>",
            "    </section>",
            '    <section class="exam-panel exam-results" hidden>',
            '      <div class="exam-results-head">',
            '        <div class="exam-results-copy">',
            '          <span class="exam-results-kicker">جمع‌بندی آزمون</span>',
            '          <h3 class="exam-results-title">نتیجه و مرور پاسخ‌ها</h3>',
            '          <p class="exam-results-subtitle">تمام سؤال‌ها، پاسخ درست و توضیح تشریحی اینجا جمع شده است.</p>',
            "        </div>",
            '        <div class="exam-score-card">',
            '          <span class="exam-score-percent"></span>',
            '          <span class="exam-score-label"></span>',
            "        </div>",
            "      </div>",
            '      <div class="exam-results-grid">',
            '        <div class="exam-results-stat"><span class="exam-results-stat-label">پاسخ صحیح</span><strong class="exam-results-stat-value exam-results-correct"></strong></div>',
            '        <div class="exam-results-stat"><span class="exam-results-stat-label">پاسخ غلط</span><strong class="exam-results-stat-value exam-results-wrong"></strong></div>',
            '        <div class="exam-results-stat"><span class="exam-results-stat-label">بی‌پاسخ</span><strong class="exam-results-stat-value exam-results-unanswered"></strong></div>',
            "      </div>",
            '      <div class="exam-results-actions">',
            '        <button class="btn-base exam-btn-back-to-quiz" type="button">بازگشت به سؤال‌ها</button>',
            '        <button class="btn-base exam-btn-reset exam-btn-reset--results" type="button">شروع دوباره آزمون</button>',
            "      </div>",
            '      <div class="exam-review-list"></div>',
            "    </section>",
            "  </main>",
            '  <footer class="exam-footer">',
            '    <p class="exam-footer-text"></p>',
            "  </footer>",
            "</div>"
        ].join("");
    }

    function hydrateStaticCopy() {
        appRoot.querySelector(".site-info h1").textContent = exam.siteTitle;
        appRoot.querySelector(".site-info p").textContent = exam.siteSubtitle;
        appRoot.querySelector(".badge-unofficial").textContent = exam.siteBadge;
        appRoot.querySelector(".exam-back-link").setAttribute("href", exam.backHref);
        appRoot.querySelector(".exam-back-label").textContent = exam.backLabel;
        appRoot.querySelector(".exam-eyebrow").textContent = exam.eyebrow;
        appRoot.querySelector(".exam-total-chip").textContent = formatValue(exam.questions.length) + " سؤال";
        appRoot.querySelector(".exam-title").textContent = exam.title;
        appRoot.querySelector(".exam-subtitle").textContent = exam.subtitle;
        appRoot.querySelector(".exam-footer-text").textContent = exam.footerText;
    }

    function attachStaticHandlers() {
        appRoot.querySelector(".exam-btn-prev").addEventListener("click", function () {
            jumpTo(state.currentQuestionIndex - 1);
        });

        appRoot.querySelector(".exam-btn-next").addEventListener("click", function () {
            jumpTo(state.currentQuestionIndex + 1);
        });

        appRoot.querySelector(".exam-btn-clear").addEventListener("click", function () {
            state.answers[state.currentQuestionIndex] = null;
            render();
        });

        appRoot.querySelector(".exam-btn-jump").addEventListener("click", function () {
            jumpTo(firstUnansweredIndex());
        });

        appRoot.querySelector(".exam-btn-finish").addEventListener("click", function () {
            state.resultsVisible = true;
            render();
            scrollToResults();
        });

        appRoot.querySelector(".exam-btn-results").addEventListener("click", function () {
            state.resultsVisible = true;
            render();
            scrollToResults();
        });

        appRoot.querySelector(".exam-btn-back-to-quiz").addEventListener("click", function () {
            const questionPanel = appRoot.querySelector(".exam-question-panel");
            questionPanel.scrollIntoView({ behavior: "smooth", block: "start" });
        });

        appRoot.querySelectorAll(".exam-btn-reset").forEach(function (button) {
            button.addEventListener("click", function () {
                const confirmReset = window.confirm("تمام پاسخ‌های این آزمون پاک شود و از اول شروع کنی؟");

                if (!confirmReset) {
                    return;
                }

                state.answers = new Array(exam.questions.length).fill(null);
                state.currentQuestionIndex = 0;
                state.resultsVisible = false;
                persistState();
                render();
            });
        });
    }

    function render() {
        renderProgress();
        renderQuestion();
        renderStats();
        renderNavigator();
        renderResults();
        persistState();
    }

    function renderProgress() {
        const answered = answeredCount();
        const total = exam.questions.length;
        const progressPercent = total ? Math.round((answered / total) * 100) : 0;

        appRoot.querySelector(".exam-progress-current").textContent =
            "سؤال " + formatValue(state.currentQuestionIndex + 1) + " از " + formatValue(total);
        appRoot.querySelector(".exam-progress-answered").textContent =
            formatValue(answered) + " پاسخ از " + formatValue(total);
        appRoot.querySelector(".exam-progress-label").textContent =
            answered === total ? "همه سؤال‌ها پاسخ گرفته‌اند." : "برای هر سؤال می‌توانی بعداً جواب را عوض کنی.";
        appRoot.querySelector(".exam-progress-percent").textContent = formatPercent(progressPercent);
        appRoot.querySelector(".exam-progress-fill").style.width = progressPercent + "%";
    }

    function renderQuestion() {
        const question = exam.questions[state.currentQuestionIndex];
        const selectedIndex = state.answers[state.currentQuestionIndex];
        const questionText = appRoot.querySelector(".exam-question-text");
        const status = appRoot.querySelector(".exam-question-status");
        const optionGrid = appRoot.querySelector(".exam-option-grid");
        const finishButton = appRoot.querySelector(".exam-btn-finish");

        appRoot.querySelector(".exam-question-tag").textContent =
            "سؤال " + formatValue(state.currentQuestionIndex + 1);

        status.textContent = selectedIndex === null ? "وضعیت: بدون پاسخ" : "وضعیت: پاسخ ثبت شده";

        setRichText(questionText, question.question);

        optionGrid.classList.toggle("is-compact", question.useCompactOptions);
        optionGrid.replaceChildren();

        question.options.forEach(function (optionText, optionIndex) {
            const button = document.createElement("button");
            const marker = document.createElement("span");
            const copy = document.createElement("span");
            const answerState = reviewStateForOption(question, optionIndex, selectedIndex);

            button.type = "button";
            button.className = "exam-option";
            button.dataset.state = answerState;
            button.classList.toggle("is-selected", selectedIndex === optionIndex);
            button.classList.toggle("is-results-mode", state.resultsVisible);

            marker.className = "exam-option-marker";
            marker.textContent = optionLetter(optionIndex);

            copy.className = "exam-option-copy";
            setRichText(copy, optionText);

            button.append(marker, copy);
            button.addEventListener("click", function () {
                const wasUnanswered = state.answers[state.currentQuestionIndex] === null;
                const sourceIndex = state.currentQuestionIndex;

                state.answers[sourceIndex] = optionIndex;
                render();

                if (exam.autoAdvance && wasUnanswered && !state.resultsVisible && sourceIndex < exam.questions.length - 1) {
                    window.setTimeout(function () {
                        if (state.currentQuestionIndex === sourceIndex) {
                            jumpTo(sourceIndex + 1);
                        }
                    }, 120);
                }
            });

            optionGrid.appendChild(button);
        });

        appRoot.querySelector(".exam-btn-prev").disabled = state.currentQuestionIndex === 0;
        appRoot.querySelector(".exam-btn-next").disabled = state.currentQuestionIndex === exam.questions.length - 1;
        appRoot.querySelector(".exam-btn-clear").disabled = selectedIndex === null;

        const firstPending = firstUnansweredIndex();
        appRoot.querySelector(".exam-btn-jump").disabled = firstPending === -1;

        finishButton.textContent = state.resultsVisible ? "به‌روزرسانی نتیجه" : "مشاهده نتیجه";
    }

    function renderStats() {
        const totals = scoreTotals();
        const total = exam.questions.length;
        const currentValue = state.resultsVisible
            ? formatValue(totals.correct)
            : formatValue(state.currentQuestionIndex + 1);
        const thirdLabel = state.resultsVisible ? "بی‌پاسخ" : "باقی‌مانده";
        const thirdValue = state.resultsVisible ? formatValue(totals.unanswered) : formatValue(total - totals.answered);
        const fourthLabel = state.resultsVisible ? "پاسخ صحیح" : "سؤال جاری";

        appRoot.querySelector(".exam-stat-total").textContent = formatValue(total);
        appRoot.querySelector(".exam-stat-answered").textContent = formatValue(totals.answered);
        appRoot.querySelector(".exam-stat-third-label").textContent = thirdLabel;
        appRoot.querySelector(".exam-stat-third").textContent = thirdValue;
        appRoot.querySelector(".exam-stat-fourth-label").textContent = fourthLabel;
        appRoot.querySelector(".exam-stat-fourth").textContent = currentValue;
        appRoot.querySelector(".exam-nav-summary").textContent =
            state.resultsVisible
                ? "رنگ هر شماره نشان می‌دهد پاسخ آن سؤال درست بوده یا نه."
                : "می‌توانی از اینجا مستقیم به هر سؤال بروی.";
    }

    function renderNavigator() {
        const navGrid = appRoot.querySelector(".exam-nav-grid");
        const selectedAnswers = state.answers;

        navGrid.replaceChildren();

        exam.questions.forEach(function (question, index) {
            const button = document.createElement("button");
            const selectedIndex = selectedAnswers[index];
            const stateName = navigatorState(question, selectedIndex, index);

            button.type = "button";
            button.className = "exam-nav-btn";
            button.dataset.state = stateName;
            button.classList.toggle("is-active", index === state.currentQuestionIndex);
            button.textContent = formatValue(index + 1);
            button.setAttribute("aria-label", "سؤال " + formatValue(index + 1));
            button.addEventListener("click", function () {
                jumpTo(index);
            });

            navGrid.appendChild(button);
        });
    }

    function renderResults() {
        const resultsSection = appRoot.querySelector(".exam-results");
        const reviewList = appRoot.querySelector(".exam-review-list");
        const totals = scoreTotals();
        const total = exam.questions.length;
        const percent = total ? Math.round((totals.correct / total) * 100) : 0;

        resultsSection.hidden = !state.resultsVisible;

        if (!state.resultsVisible) {
            reviewList.replaceChildren();
            return;
        }

        appRoot.querySelector(".exam-score-percent").textContent = formatPercent(percent);
        appRoot.querySelector(".exam-score-label").textContent =
            formatValue(totals.correct) + " پاسخ صحیح از " + formatValue(total) + " سؤال";
        appRoot.querySelector(".exam-results-correct").textContent = formatValue(totals.correct);
        appRoot.querySelector(".exam-results-wrong").textContent = formatValue(totals.wrong);
        appRoot.querySelector(".exam-results-unanswered").textContent = formatValue(totals.unanswered);

        reviewList.replaceChildren();

        exam.questions.forEach(function (question, index) {
            const selectedIndex = state.answers[index];
            const card = document.createElement("article");
            const topRow = document.createElement("div");
            const titleWrap = document.createElement("div");
            const number = document.createElement("span");
            const title = document.createElement("h4");
            const badge = document.createElement("span");
            const optionList = document.createElement("div");
            const explanation = document.createElement("p");

            card.className = "exam-review-card";
            topRow.className = "exam-review-top";
            titleWrap.className = "exam-review-title-wrap";
            number.className = "exam-review-number";
            title.className = "exam-review-question";
            badge.className = "exam-review-badge";
            optionList.className = "exam-review-options";
            explanation.className = "exam-review-explanation";

            number.textContent = "سؤال " + formatValue(index + 1);
            setRichText(title, question.question);

            const reviewState = navigatorState(question, selectedIndex, index);
            badge.dataset.state = reviewState;
            badge.textContent = reviewBadgeText(reviewState);

            titleWrap.append(number, title);
            topRow.append(titleWrap, badge);
            card.appendChild(topRow);

            question.options.forEach(function (optionText, optionIndex) {
                const optionRow = document.createElement("div");
                const optionMain = document.createElement("div");
                const optionMarker = document.createElement("span");
                const optionCopy = document.createElement("span");
                const optionTag = document.createElement("span");
                const optionState = reviewStateForOption(question, optionIndex, selectedIndex);
                const label = reviewTagText(question, optionIndex, selectedIndex);

                optionRow.className = "exam-review-option";
                optionRow.dataset.state = optionState;
                optionMain.className = "exam-review-option-main";
                optionMarker.className = "exam-review-option-marker";
                optionCopy.className = "exam-review-option-copy";

                optionMarker.textContent = optionLetter(optionIndex);
                setRichText(optionCopy, optionText);
                optionMain.append(optionMarker, optionCopy);
                optionRow.appendChild(optionMain);

                if (label) {
                    optionTag.className = "exam-review-option-tag";
                    optionTag.textContent = label;
                    optionRow.appendChild(optionTag);
                }

                optionList.appendChild(optionRow);
            });

            card.appendChild(optionList);

            if (question.explanation) {
                setRichText(explanation, question.explanation);
                card.appendChild(explanation);
            }

            reviewList.appendChild(card);
        });
    }

    function jumpTo(index) {
        if (typeof index !== "number" || index < 0 || index >= exam.questions.length) {
            return;
        }

        state.currentQuestionIndex = index;
        render();
    }

    function scrollToResults() {
        const resultsSection = appRoot.querySelector(".exam-results");
        resultsSection.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function answeredCount() {
        return state.answers.filter(function (value) {
            return value !== null;
        }).length;
    }

    function firstUnansweredIndex() {
        return state.answers.findIndex(function (value) {
            return value === null;
        });
    }

    function scoreTotals() {
        let correct = 0;
        let wrong = 0;
        let unanswered = 0;

        exam.questions.forEach(function (question, index) {
            const selectedIndex = state.answers[index];

            if (selectedIndex === null) {
                unanswered += 1;
                return;
            }

            if (selectedIndex === question.correctIndex) {
                correct += 1;
                return;
            }

            wrong += 1;
        });

        return {
            answered: correct + wrong,
            correct: correct,
            wrong: wrong,
            unanswered: unanswered
        };
    }

    function reviewStateForOption(question, optionIndex, selectedIndex) {
        if (!state.resultsVisible) {
            return "neutral";
        }

        if (optionIndex === question.correctIndex && selectedIndex === optionIndex) {
            return "user-correct";
        }

        if (optionIndex === question.correctIndex) {
            return "correct";
        }

        if (selectedIndex === optionIndex) {
            return "user-wrong";
        }

        return "neutral";
    }

    function navigatorState(question, selectedIndex, questionIndex) {
        if (questionIndex === state.currentQuestionIndex && !state.resultsVisible) {
            return selectedIndex === null ? "active-empty" : "active-answered";
        }

        if (!state.resultsVisible) {
            return selectedIndex === null ? "empty" : "answered";
        }

        if (selectedIndex === null) {
            return "unanswered";
        }

        return selectedIndex === question.correctIndex ? "correct" : "wrong";
    }

    function reviewTagText(question, optionIndex, selectedIndex) {
        if (optionIndex === question.correctIndex && selectedIndex === optionIndex) {
            return "پاسخ درست تو";
        }

        if (optionIndex === question.correctIndex) {
            return "پاسخ درست";
        }

        if (selectedIndex === optionIndex) {
            return "انتخاب تو";
        }

        return "";
    }

    function reviewBadgeText(stateName) {
        if (stateName === "correct") {
            return "صحیح";
        }

        if (stateName === "wrong") {
            return "غلط";
        }

        if (stateName === "unanswered") {
            return "بی‌پاسخ";
        }

        return "در حال پاسخ";
    }

    function persistState() {
        try {
            window.sessionStorage.setItem(storageKey, JSON.stringify({
                currentQuestionIndex: state.currentQuestionIndex,
                resultsVisible: state.resultsVisible,
                answers: state.answers
            }));
        } catch (error) {
            return;
        }
    }

    function normalizeExamData(data) {
        const questions = Array.isArray(data.questions) ? data.questions : [];

        return {
            siteTitle: normalizeText(data.siteTitle) || "ورودی ۱۴۰۲ دندانپزشکی تهران",
            siteSubtitle: normalizeText(data.siteSubtitle) || "دانشگاه علوم پزشکی تهران",
            siteBadge: normalizeText(data.siteBadge) || "سامانه غیررسمی",
            footerText: normalizeText(data.footerText) || "طراحی شده برای ورودی ۱۴۰۲ دانشکده دندانپزشکی تهران",
            backHref: normalizeText(data.backHref) || "/exams/",
            backLabel: normalizeText(data.backLabel) || "بازگشت به آزمون‌ها",
            eyebrow: normalizeText(data.eyebrow) || "آزمون تمرینی",
            title: normalizeText(data.title) || "آزمون",
            subtitle: normalizeText(data.subtitle) || "پاسخ‌ها ذخیره می‌شوند و نتیجه در پایان نمایش داده می‌شود.",
            autoAdvance: data.autoAdvance !== false,
            questions: questions
                .map(function (item) {
                    const normalizedQuestion = normalizeQuestion(item);
                    return normalizedQuestion;
                })
                .filter(function (question) {
                    return question.question && question.options.length;
                })
        };
    }

    function normalizeQuestion(item) {
        const rawOptions = Array.isArray(item.options) ? item.options.slice(0, 8) : [];
        const options = normalizeOptions(rawOptions);
        const questionText = stripQuestionNumber(normalizeText(item.question || item.text || ""));
        const explanation = normalizeText(item.explanation || "");

        return {
            question: questionText,
            options: options,
            correctIndex: clampCorrectIndex(item.correctIndex, options.length),
            explanation: explanation,
            useCompactOptions: shouldUseCompactOptions(options)
        };
    }

    function normalizeOptions(options) {
        const normalized = options.map(function (option) {
            return normalizeText(option);
        });

        const sequentialLetters = normalized.length > 1 && normalized.every(function (option, index) {
            const expected = String.fromCharCode(65 + index);
            return new RegExp("^\\s*" + expected + "[\\)\\.\\-:]\\s+").test(option);
        });

        if (!sequentialLetters) {
            return normalized;
        }

        return normalized.map(function (option, index) {
            const expected = String.fromCharCode(65 + index);
            return option.replace(new RegExp("^\\s*" + expected + "[\\)\\.\\-:]\\s+"), "");
        });
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
        const parsed = Number(value);

        if (!Number.isInteger(parsed) || parsed < 0 || parsed >= optionCount) {
            return 0;
        }

        return parsed;
    }

    function restoreState(key, totalQuestions) {
        const empty = {
            currentQuestionIndex: 0,
            resultsVisible: false,
            answers: new Array(totalQuestions).fill(null)
        };

        try {
            const saved = JSON.parse(window.sessionStorage.getItem(key) || "null");

            if (!saved || !Array.isArray(saved.answers)) {
                return empty;
            }

            empty.currentQuestionIndex = clampIndex(saved.currentQuestionIndex, totalQuestions);
            empty.resultsVisible = Boolean(saved.resultsVisible);
            empty.answers = new Array(totalQuestions).fill(null).map(function (_, index) {
                const answer = saved.answers[index];
                return Number.isInteger(answer) ? answer : null;
            });
        } catch (error) {
            return empty;
        }

        return empty;
    }

    function clampIndex(value, totalQuestions) {
        const parsed = Number(value);

        if (!Number.isInteger(parsed)) {
            return 0;
        }

        if (parsed < 0) {
            return 0;
        }

        if (parsed >= totalQuestions) {
            return Math.max(0, totalQuestions - 1);
        }

        return parsed;
    }

    function formatValue(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatPercent(value) {
        return Number(value || 0).toLocaleString("fa-IR") + "٪";
    }

    function optionLetter(index) {
        return ["الف", "ب", "ج", "د", "هـ", "و", "ز", "ح"][index] || formatValue(index + 1);
    }

    function setRichText(element, text) {
        const fragment = document.createDocumentFragment();
        const parts = String(text || "").split(/(\*\*[^*]+\*\*)/g);

        parts.forEach(function (part) {
            if (!part) {
                return;
            }

            const isStrong = part.startsWith("**") && part.endsWith("**");
            appendRichSegment(fragment, isStrong ? part.slice(2, -2) : part, isStrong);
        });

        element.replaceChildren(fragment);
    }

    function appendRichSegment(fragment, text, strong) {
        const lines = String(text).split("\n");

        lines.forEach(function (line, lineIndex) {
            if (strong) {
                const strongNode = document.createElement("strong");
                strongNode.textContent = line;
                fragment.appendChild(strongNode);
            } else {
                fragment.appendChild(document.createTextNode(line));
            }

            if (lineIndex < lines.length - 1) {
                fragment.appendChild(document.createElement("br"));
            }
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
})();
