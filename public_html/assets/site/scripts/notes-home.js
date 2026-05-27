(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
    }

    function siteApi() {
        return window.Dent1402Site && typeof window.Dent1402Site === "object"
            ? window.Dent1402Site
            : null;
    }

    function normalizeNotesCohort(value) {
        var cohort = String(value == null ? "" : value).trim();
        if (!cohort || cohort === "main" || cohort === "1402" || cohort === "dentistry-1402") {
            return "1402";
        }
        if (cohort === "1403" || cohort === "dentistry-1403") {
            return "1403";
        }
        if (cohort === "1404" || cohort === "dentistry-1404") {
            return "1404";
        }
        if (cohort === "prosthesis-1402") {
            return "prosthesis-1402";
        }
        return cohort;
    }

    function resolveCohort() {
        var auth = authApi();
        var cohort = auth && typeof auth.resolvePageCohort === "function"
            ? auth.resolvePageCohort("notesCohort")
            : String(document.body && document.body.dataset ? document.body.dataset.notesCohort || "" : "").trim();
        return normalizeNotesCohort(cohort);
    }

    var pageCohort = resolveCohort();
    if (["1402", "1403", "1404", "prosthesis-1402"].indexOf(pageCohort) === -1) {
        return;
    }

    var list = $("notes-home-list");
    var empty = $("notes-home-empty");
    var manage = $("notes-home-manage");
    var form = $("notes-home-form");
    var feedback = $("notes-home-feedback");
    var submit = $("notes-home-submit");
    var heading = $("notes-home-heading");
    var subheading = $("notes-home-subheading");
    var backLink = $("notes-home-back-link");
    var kicker = $("notes-home-kicker");
    var title = $("notes-home-title");
    var sectionKicker = $("notes-home-section-kicker");
    var sectionTitle = $("notes-home-section-title");
    var sectionCopy = $("notes-home-section-copy");
    var footer = $("notes-home-footer");

    if (!list || !empty) {
        return;
    }

    var state = {
        authKey: "",
        canManage: false,
        deletingTermId: 0,
        editingTermId: 0,
        loadError: "",
        loading: false,
        manageExpanded: false,
        saving: false,
        terms: []
    };

    function manageSupported() {
        return pageCohort === "prosthesis-1402";
    }

    function homeCanManage() {
        return manageSupported() && state.canManage;
    }

    function cohortYearLabel() {
        if (pageCohort === "1403") {
            return "۱۴۰۳";
        }
        if (pageCohort === "1404") {
            return "۱۴۰۴";
        }
        return "۱۴۰۲";
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function parseJsonResponse(response) {
        var site = siteApi();
        if (site && typeof site.parseJsonResponse === "function") {
            return site.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function consumeUnauthorized(payload, fallbackText) {
        var site = siteApi();
        if (site && typeof site.consumeUnauthorized === "function") {
            return !!site.consumeUnauthorized(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        var auth = authApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            return !!auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };
        var data = Object.assign({ cohort: pageCohort }, payload || {});
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);

        if (method === "GET") {
            Object.keys(data).forEach(function (key) {
                var value = data[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, data));
        }

        return fetch(url, options).then(parseJsonResponse);
    }

    function authSnapshotKey() {
        var auth = authApi();
        if (!auth || typeof auth.getState !== "function") {
            return "anon";
        }
        var snapshot = auth.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber
            ? String(snapshot.user.studentNumber)
            : "";
        return String(snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function dentalHomeBasePath() {
        if (pageCohort === "1403") {
            return "/notes/1403/";
        }
        if (pageCohort === "1404") {
            return "/notes/1404/";
        }
        return "/notes/";
    }

    function dentalManagePath() {
        return "/notes/term/";
    }

    function dentalRequestedTerm() {
        var params = new URLSearchParams(window.location.search || "");
        var value = Number(params.get("term") || "0");
        return Number.isFinite(value) ? value : 0;
    }

    function dentalRequestedUnitKey() {
        return String(new URLSearchParams(window.location.search || "").get("unit") || "").trim().toLowerCase();
    }

    function dentalBuildUrl(pathname, termValue, unitKey) {
        var url = new URL(pathname, window.location.origin);
        if (termValue > 0) {
            url.searchParams.set("term", String(termValue));
        }
        if (unitKey) {
            url.searchParams.set("unit", unitKey);
        }
        if (pageCohort !== "1402") {
            url.searchParams.set("cohort", pageCohort);
        }
        return url.pathname + (url.search || "");
    }

    function dentalHomeUrl(termValue, unitKey) {
        return dentalBuildUrl(dentalHomeBasePath(), termValue, unitKey);
    }

    function dentalManageUrl(termValue, unitKey) {
        return dentalBuildUrl(dentalManagePath(), termValue, unitKey);
    }

    function dentalBodyMode(mode) {
        if (!document.body) {
            return;
        }
        document.body.classList.add("notes-curriculum-page");
        document.body.dataset.notesView = mode || "";
    }

    function dentalYearLabel() {
        return toFaDigits(pageCohort);
    }

    function dentalResetList() {
        list.innerHTML = "";
        empty.hidden = true;
    }

    function dentalShowEmpty(message) {
        list.innerHTML = "";
        empty.hidden = false;
        empty.textContent = message || "داده‌ای برای نمایش پیدا نشد.";
    }

    function dentalSectionText(kickerText, titleText, copyText) {
        if (sectionKicker) {
            sectionKicker.textContent = kickerText || "";
        }
        if (sectionTitle) {
            sectionTitle.textContent = titleText || "";
        }
        if (sectionCopy) {
            sectionCopy.textContent = copyText || "";
        }
    }

    function dentalApplyBaseCopy() {
        var yearLabel = dentalYearLabel();
        if (heading) {
            heading.textContent = "آرشیو منابع ورودی " + yearLabel;
        }
        if (subheading) {
            subheading.textContent = "چینش ترم، دسته و واحد";
        }
        if (backLink) {
            backLink.href = pageCohort === "1402" ? "/app/#resources" : "/app/";
            backLink.textContent = pageCohort === "1402" ? "بازگشت به منابع" : "بازگشت به خانه";
        }
        if (kicker) {
            kicker.textContent = "آرشیو " + yearLabel;
        }
        if (footer) {
            footer.textContent = "ورودی " + yearLabel + " دندانپزشکی تهران";
        }
    }

    function dentalCreate(tagName, className, text) {
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function dentalCreateStat(labelText, valueText) {
        var stat = dentalCreate("div", "notes-summary-stat");
        stat.appendChild(dentalCreate("span", "notes-summary-stat__label", labelText));
        stat.appendChild(dentalCreate("strong", "notes-summary-stat__value", valueText));
        return stat;
    }

    function dentalAppendSummary(stats) {
        var shell = dentalCreate("section", "notes-summary-shell");
        var grid = dentalCreate("div", "notes-summary-grid");
        grid.appendChild(dentalCreateStat("ترم", toFaDigits(stats.termCount || 0)));
        grid.appendChild(dentalCreateStat("واحد فعال", toFaDigits(stats.availableUnitCount || 0)));
        grid.appendChild(dentalCreateStat("منبع", toFaDigits(stats.itemCount || 0)));
        shell.appendChild(grid);
        list.appendChild(shell);
    }

    function dentalCreateChip(text, className) {
        return dentalCreate("span", className || "notes-chip", text);
    }

    function dentalCreateActionLink(label, href, muted) {
        var link = dentalCreate("a", muted ? "notes-link-btn notes-link-btn--muted" : "notes-link-btn", label);
        if (muted) {
            link.removeAttribute("href");
            link.setAttribute("aria-disabled", "true");
            link.tabIndex = -1;
        } else {
            link.href = href;
        }
        return link;
    }

    function dentalAppendTermCards(curriculum) {
        var terms = Array.isArray(curriculum && curriculum.terms) ? curriculum.terms : [];
        var grid = dentalCreate("div", "notes-term-grid");

        terms.forEach(function (term) {
            var card = dentalCreate("article", "notes-term-card");
            var link = dentalCreate("a", "notes-term-card__link");
            link.href = dentalHomeUrl(Number(term.number || 0), "");
            var availableUnitCount = Number((term.stats && term.stats.availableUnitCount) || 0);
            var itemCount = Number((term.stats && term.stats.itemCount) || 0);

            var head = dentalCreate("div", "notes-term-card__head");
            head.appendChild(dentalCreateChip(
                itemCount > 0 ? "دارای منبع" : "بدون منبع",
                itemCount > 0 ? "notes-chip notes-chip--term" : "notes-chip notes-chip--soft"
            ));
            head.appendChild(dentalCreate("h3", "notes-term-card__title", term.label || "ترم"));

            var desc = dentalCreate(
                "p",
                "notes-term-card__desc",
                availableUnitCount > 0
                    ? "برای دیدن واحدها و منابع همین ترم وارد شو."
                    : "ساختار این ترم آماده است اما هنوز منبع فعالی ندارد."
            );

            var preview = dentalCreate("div", "notes-term-card__preview");
            var previewUnits = Array.isArray(term.previewUnits) ? term.previewUnits : [];
            if (previewUnits.length) {
                previewUnits.slice(0, 4).forEach(function (titleText) {
                    preview.appendChild(dentalCreateChip(titleText, "notes-chip"));
                });
            } else {
                preview.appendChild(dentalCreateChip("بدون منبع", "notes-chip notes-chip--soft"));
            }

            var stats = dentalCreate("div", "notes-term-card__stats");
            stats.appendChild(dentalCreateStat("واحد", toFaDigits((term.stats && term.stats.unitCount) || 0)));
            stats.appendChild(dentalCreateStat("فعال", toFaDigits(availableUnitCount)));
            stats.appendChild(dentalCreateStat("منبع", toFaDigits(itemCount)));

            link.appendChild(head);
            link.appendChild(desc);
            link.appendChild(preview);
            link.appendChild(stats);
            link.appendChild(dentalCreateActionLink("ورود به ترم", link.href, false));
            card.appendChild(link);
            grid.appendChild(card);
        });

        list.appendChild(grid);
    }

    function dentalAppendLegacyTerms(curriculum) {
        var extraTerms = Array.isArray(curriculum && curriculum.extraTerms) ? curriculum.extraTerms : [];
        if (!extraTerms.length) {
            return;
        }

        var shell = dentalCreate("section", "notes-legacy-shell");
        var head = dentalCreate("div", "notes-legacy-shell__head");
        head.appendChild(dentalCreateChip("آرشیوهای دیگر", "notes-chip notes-chip--term"));
        head.appendChild(dentalCreate("h3", "notes-legacy-shell__title", "منابع خارج از ساختار ۴ تا ۱۲"));
        head.appendChild(dentalCreate("p", "notes-legacy-shell__desc", "منابع قدیمی‌تر یا عمومی که هنوز بیرون از ساختار دانشکده نگه‌داری می‌شوند."));
        shell.appendChild(head);

        var grid = dentalCreate("div", "notes-term-grid");
        extraTerms.forEach(function (termData) {
            var card = dentalCreate("article", "notes-term-card notes-term-card--legacy");
            var link = dentalCreate("a", "notes-term-card__link");
            link.href = dentalHomeUrl(Number(termData.term || 0), "");
            card.appendChild(link);

            var headRow = dentalCreate("div", "notes-term-card__head");
            headRow.appendChild(dentalCreateChip("ترم " + toFaDigits(termData.term || 0), "notes-chip notes-chip--soft"));
            headRow.appendChild(dentalCreate("h3", "notes-term-card__title", termData.title || "آرشیو"));
            link.appendChild(headRow);
            link.appendChild(dentalCreate("p", "notes-term-card__desc", termData.description || ""));

            var stats = dentalCreate("div", "notes-term-card__stats");
            stats.appendChild(dentalCreateStat("منبع", toFaDigits(termData.itemCount || 0)));
            link.appendChild(stats);
            link.appendChild(dentalCreateActionLink("ورود به آرشیو", link.href, false));
            grid.appendChild(card);
        });

        shell.appendChild(grid);
        list.appendChild(shell);
    }

    function dentalFindMainTerm(curriculum, termNumber) {
        var terms = Array.isArray(curriculum && curriculum.terms) ? curriculum.terms : [];
        for (var index = 0; index < terms.length; index += 1) {
            if (Number(terms[index].number || 0) === Number(termNumber || 0)) {
                return terms[index];
            }
        }
        return null;
    }

    function dentalFindLegacyTerm(curriculum, termNumber) {
        var terms = Array.isArray(curriculum && curriculum.extraTerms) ? curriculum.extraTerms : [];
        for (var index = 0; index < terms.length; index += 1) {
            if (Number(terms[index].term || 0) === Number(termNumber || 0)) {
                return terms[index];
            }
        }
        return null;
    }

    function dentalAppendOverviewActions(termNumber, canManage) {
        var actions = dentalCreate("div", "notes-inline-actions");
        actions.appendChild(dentalCreateActionLink("بازگشت به همه ترم‌ها", dentalHomeUrl(0, ""), false));
        if (canManage && termNumber > 0) {
            actions.appendChild(dentalCreateActionLink("مدیریت این ترم", dentalManageUrl(termNumber, ""), false));
        }
        list.appendChild(actions);
    }

    function dentalAppendTermOverview(termData) {
        var categories = Array.isArray(termData && termData.categories) ? termData.categories : [];
        categories.forEach(function (category) {
            var section = dentalCreate("section", "notes-group-card");
            var head = dentalCreate("div", "notes-group-card__head");
            head.appendChild(dentalCreateChip(category.title || "", "notes-chip notes-chip--term"));
            head.appendChild(dentalCreate("h3", "notes-group-card__title", category.title || ""));
            head.appendChild(dentalCreate("p", "notes-group-card__desc", "واحد موردنظر را از بین این دسته انتخاب کن."));
            section.appendChild(head);

            var unitList = dentalCreate("div", "notes-unit-list");
            var units = Array.isArray(category.units) ? category.units : [];
            units.forEach(function (unit) {
                var unitCard = dentalCreate("article", "notes-unit-card");
                var cardHead = dentalCreate("div", "notes-unit-card__head");
                cardHead.appendChild(dentalCreateChip(unit.statusLabel || "", unit.itemCount > 0 ? "notes-chip notes-chip--ok" : "notes-chip notes-chip--soft"));
                cardHead.appendChild(dentalCreate("h4", "notes-unit-card__title", unit.title || "واحد"));
                unitCard.appendChild(cardHead);
                unitCard.appendChild(dentalCreate("p", "notes-unit-card__desc", unit.description || ""));

                var preview = dentalCreate("div", "notes-unit-card__preview");
                var previewTitles = Array.isArray(unit.previewTitles) ? unit.previewTitles : [];
                if (previewTitles.length) {
                    previewTitles.slice(0, 3).forEach(function (titleText) {
                        preview.appendChild(dentalCreateChip(titleText, "notes-chip"));
                    });
                } else {
                    preview.appendChild(dentalCreateChip("هنوز منبعی ندارد", "notes-chip notes-chip--soft"));
                }
                unitCard.appendChild(preview);

                var stats = dentalCreate("div", "notes-unit-card__stats");
                stats.appendChild(dentalCreateStat("منبع", toFaDigits(unit.itemCount || 0)));
                unitCard.appendChild(stats);
                unitCard.appendChild(dentalCreateActionLink(
                    unit.itemCount > 0 ? "دیدن منابع" : "ورود به واحد",
                    dentalHomeUrl(Number(termData.number || 0), unit.key || ""),
                    false
                ));
                unitList.appendChild(unitCard);
            });
            section.appendChild(unitList);
            list.appendChild(section);
        });
    }

    function dentalAppendResourceActions(termData, canManage, unitKey) {
        var termNumber = Number(termData.term || termData.termNumber || 0);
        var actions = dentalCreate("div", "notes-inline-actions");
        actions.appendChild(dentalCreateActionLink("بازگشت به همه ترم‌ها", dentalHomeUrl(0, ""), false));
        if (termNumber > 0) {
            actions.appendChild(dentalCreateActionLink("بازگشت به " + (termData.termLabel || ("ترم " + toFaDigits(termNumber))), dentalHomeUrl(termNumber, ""), false));
        }
        if (canManage) {
            actions.appendChild(dentalCreateActionLink("مدیریت منابع این واحد", dentalManageUrl(termNumber, unitKey || ""), false));
        }
        list.appendChild(actions);
    }

    function dentalAppendResourceCards(termData) {
        var items = Array.isArray(termData && termData.items) ? termData.items : [];
        if (!items.length) {
            dentalShowEmpty(termData.emptyMessage || "برای این بخش هنوز منبعی ثبت نشده است.");
            return;
        }

        var wrap = dentalCreate("div", "notes-resource-list");
        items.forEach(function (item) {
            var card = dentalCreate("article", "notes-resource-card");
            var head = dentalCreate("div", "notes-resource-card__head");
            head.appendChild(dentalCreateChip(item.badge || "منبع", "notes-chip notes-chip--term"));
            head.appendChild(dentalCreate("h3", "notes-resource-card__title", item.title || "بدون عنوان"));
            card.appendChild(head);
            card.appendChild(dentalCreate("p", "notes-resource-card__desc", item.description || ""));

            var meta = dentalCreate("div", "notes-resource-card__meta");
            if (item.curriculum && item.curriculum.termNumber) {
                meta.appendChild(dentalCreateChip(item.curriculum.categoryTitle || "", "notes-chip"));
            }
            if (item.storageTerm) {
                meta.appendChild(dentalCreateChip("ذخیره در ترم " + toFaDigits(item.storageTerm), "notes-chip notes-chip--soft"));
            }
            card.appendChild(meta);

            var action = dentalCreateActionLink(item.buttonLabel || "دریافت", item.buttonUrl || "#", false);
            action.classList.add("notes-resource-card__action");
            if (item.isExternal) {
                action.target = "_blank";
                action.rel = "noopener noreferrer";
            }
            card.appendChild(action);
            wrap.appendChild(card);
        });
        list.appendChild(wrap);
    }

    function dentalRenderCurriculumHome(curriculum) {
        dentalBodyMode("terms");
        dentalApplyBaseCopy();
        if (title) {
            title.textContent = "ترم موردنظر را برای دیدن منابع انتخاب کن.";
        }
        dentalSectionText(
            "ترم‌های دانشکده",
            "چینش منابع بر اساس ترم و واحد",
            "ابتدا ترم را انتخاب کن، بعد از داخل دسته‌ها وارد واحد هر درس شو."
        );
        document.title = "آرشیو منابع " + dentalYearLabel() + " | ساختار ترم و واحد";
        dentalResetList();
        dentalAppendSummary((curriculum && curriculum.stats) || {});
        dentalAppendTermCards(curriculum);
        dentalAppendLegacyTerms(curriculum);
    }

    function dentalRenderTermOverview(curriculum, termData) {
        dentalBodyMode("term");
        dentalApplyBaseCopy();
        if (title) {
            title.textContent = termData.label || ("ترم " + toFaDigits(termData.number || 0));
        }
        dentalSectionText(
            "واحدهای همین ترم",
            termData.label || "ترم",
            "واحد موردنظرت را از بین دسته‌های همین ترم انتخاب کن."
        );
        if (backLink) {
            backLink.href = dentalHomeUrl(0, "");
            backLink.textContent = "بازگشت به همه ترم‌ها";
        }
        document.title = (termData.label || "ترم") + " | آرشیو منابع " + dentalYearLabel();
        dentalResetList();
        dentalAppendSummary({
            termCount: 1,
            availableUnitCount: (termData.stats && termData.stats.availableUnitCount) || 0,
            itemCount: (termData.stats && termData.stats.itemCount) || 0
        });
        dentalAppendOverviewActions(Number(termData.number || 0), !!dentalState.canManage);
        dentalAppendTermOverview(termData);
    }

    function dentalRenderLegacyTerm(termData) {
        dentalBodyMode("legacy-term");
        dentalApplyBaseCopy();
        if (title) {
            title.textContent = termData.title || ("ترم " + toFaDigits(termData.term || 0));
        }
        dentalSectionText(
            "آرشیو خارج از ساختار",
            termData.title || "آرشیو",
            termData.description || "این آرشیو هنوز خارج از ساختار اصلی ۴ تا ۱۲ نگه‌داری می‌شود."
        );
        if (backLink) {
            backLink.href = dentalHomeUrl(0, "");
            backLink.textContent = "بازگشت به همه ترم‌ها";
        }
        document.title = (termData.title || "آرشیو") + " | آرشیو منابع " + dentalYearLabel();
        dentalResetList();
        dentalAppendResourceActions(termData, !!dentalState.canManage, "");
        dentalAppendResourceCards(termData);
    }

    function dentalRenderUnitDetail(termData) {
        dentalBodyMode("unit");
        dentalApplyBaseCopy();
        if (title) {
            title.textContent = termData.title || "منابع واحد";
        }
        dentalSectionText(
            termData.categoryTitle || "منابع این واحد",
            termData.title || "منابع این واحد",
            termData.description || "منابع این واحد از همین بخش در دسترس هستند."
        );
        if (backLink) {
            backLink.href = dentalHomeUrl(Number(termData.term || termData.termNumber || 0), "");
            backLink.textContent = "بازگشت به " + (termData.termLabel || ("ترم " + toFaDigits(termData.term || 0)));
        }
        document.title = (termData.title || "منابع واحد") + " | آرشیو منابع " + dentalYearLabel();
        dentalResetList();
        dentalAppendResourceActions(termData, !!dentalState.canManage, termData.unitKey || "");
        dentalAppendResourceCards(termData);
    }

    function dentalRenderError(message) {
        dentalBodyMode("error");
        dentalApplyBaseCopy();
        if (title) {
            title.textContent = "منابع این بخش پیدا نشد.";
        }
        dentalSectionText("خطا", "امکان نمایش منابع وجود ندارد", message || "در دریافت داده‌ها خطایی رخ داد.");
        dentalShowEmpty(message || "در دریافت داده‌ها خطایی رخ داد.");
    }

    var dentalState = {
        authKey: "",
        canManage: false,
        curriculum: null,
        unitDetail: null,
        loading: false
    };

    function dentalRenderFromState() {
        var requestedTerm = dentalRequestedTerm();
        var requestedUnitKey = dentalRequestedUnitKey();
        if (requestedUnitKey) {
            if (dentalState.unitDetail) {
                dentalRenderUnitDetail(dentalState.unitDetail);
                return;
            }
            if (!dentalState.loading) {
                dentalRenderError("واحد انتخاب‌شده برای این ترم پیدا نشد.");
            }
            return;
        }

        if (!dentalState.curriculum) {
            if (!dentalState.loading) {
                dentalRenderError("ساختار منابع این ورودی در دسترس نیست.");
            }
            return;
        }

        if (!requestedTerm) {
            dentalRenderCurriculumHome(dentalState.curriculum);
            return;
        }

        var mainTerm = dentalFindMainTerm(dentalState.curriculum, requestedTerm);
        if (mainTerm) {
            dentalRenderTermOverview(dentalState.curriculum, mainTerm);
            return;
        }

        var legacyTerm = dentalFindLegacyTerm(dentalState.curriculum, requestedTerm);
        if (legacyTerm) {
            dentalRenderLegacyTerm(legacyTerm);
            return;
        }

        dentalRenderError("ترم انتخاب‌شده برای این ورودی پیدا نشد.");
    }

    function dentalLoadHomeData() {
        if (dentalState.loading) {
            return Promise.resolve();
        }
        dentalState.loading = true;
        dentalState.curriculum = null;
        dentalState.unitDetail = null;
        dentalShowEmpty("در حال دریافت ساختار منابع...");
        return request("terms", "GET", {}).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                dentalState.canManage = false;
            }
            if (!payload || !payload.success || !payload.curriculum) {
                throw new Error((payload && payload.error) || "دریافت ساختار منابع ناموفق بود.");
            }
            dentalState.curriculum = payload.curriculum;
            dentalState.canManage = !!payload.canManage;
            dentalRenderFromState();
        }).catch(function (error) {
            dentalRenderError(error && error.message ? error.message : "دریافت ساختار منابع با خطا مواجه شد.");
        }).finally(function () {
            dentalState.loading = false;
        });
    }

    function dentalLoadUnitDetail() {
        if (dentalState.loading) {
            return Promise.resolve();
        }
        dentalState.loading = true;
        dentalState.unitDetail = null;
        dentalShowEmpty("در حال دریافت منابع این واحد...");
        return request("term", "GET", {
            term: String(dentalRequestedTerm() || 0),
            unit: dentalRequestedUnitKey()
        }).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                dentalState.canManage = false;
            }
            if (!payload || !payload.success || !payload.term) {
                throw new Error((payload && payload.error) || "دریافت منابع این واحد ناموفق بود.");
            }
            dentalState.unitDetail = payload.term;
            dentalState.canManage = !!payload.canManage;
            dentalRenderFromState();
        }).catch(function (error) {
            dentalRenderError(error && error.message ? error.message : "دریافت منابع این واحد با خطا مواجه شد.");
        }).finally(function () {
            dentalState.loading = false;
        });
    }

    function dentalWatchAuthChanges() {
        var auth = authApi();
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }
        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === dentalState.authKey) {
                return;
            }
            dentalState.authKey = nextKey;
            if (dentalRequestedUnitKey()) {
                dentalLoadUnitDetail();
                return;
            }
            dentalLoadHomeData();
        });
    }

    function bootDentalCurriculumHome() {
        if (manage) {
            manage.hidden = true;
        }
        dentalState.authKey = authSnapshotKey();
        dentalWatchAuthChanges();
        if (dentalRequestedUnitKey()) {
            dentalLoadUnitDetail();
            return;
        }
        dentalLoadHomeData();
    }

    if (pageCohort !== "prosthesis-1402") {
        bootDentalCurriculumHome();
        return;
    }

    function applyPageCopy() {
        var isProsthesis = pageCohort === "prosthesis-1402";
        var yearLabel = cohortYearLabel();
        if (heading) {
            heading.textContent = isProsthesis ? "آرشیو منابع پروتز ۱۴۰۲" : ("آرشیو منابع ورودی " + yearLabel);
        }
        if (subheading) {
            subheading.textContent = isProsthesis ? "هر ترم در صفحه جداگانه" : "هر ترم در صفحه جداگانه";
        }
        if (backLink) {
            backLink.href = "/app/";
            backLink.textContent = isProsthesis ? "بازگشت به خانه پروتز" : "بازگشت به خانه";
        }
        if (kicker) {
            kicker.textContent = isProsthesis ? "آرشیو پروتز ۱۴۰۲" : ("آرشیو " + yearLabel);
        }
        if (title) {
            title.textContent = isProsthesis
                ? "ترم موردنظر را برای دیدن جزوات و منابع انتخاب کن."
                : "ترم موردنظر را برای دیدن آرشیو منابع انتخاب کن.";
        }
        if (sectionKicker) {
            sectionKicker.textContent = "ترم‌ها";
        }
        if (sectionTitle) {
            sectionTitle.textContent = isProsthesis
                ? "صفحات مستقل ترمی پروتز ۱۴۰۲"
                : ("صفحات مستقل ترمی " + yearLabel);
        }
        if (sectionCopy) {
            sectionCopy.textContent = isProsthesis
                ? "این فهرست از storage پروتز خوانده می‌شود و مدیریت آن در همین صفحه انجام می‌شود."
                : "این فهرست مستقیم از storage مشترک خوانده می‌شود و دیگر به کارت‌های ثابت داخل HTML وابسته نیست.";
        }
        if (footer) {
            footer.textContent = isProsthesis ? "ورودی ۱۴۰۲ پروتز تهران" : ("ورودی " + yearLabel + " دندانپزشکی تهران");
        }
        document.title = isProsthesis
            ? "آرشیو جزوات پروتز ۱۴۰۲ | انتخاب ترم"
            : ("آرشیو منابع " + yearLabel + " | انتخاب ترم");
    }

    function termId(term) {
        return Number(term && (term.id || term.term) || 0);
    }

    function termUrl(term) {
        var id = termId(term);
        var site = siteApi();
        var auth = authApi();
        var base = site && typeof site.buildUrl === "function"
            ? site.buildUrl("/notes/term/", { term: String(id) })
            : "/notes/term/?term=" + encodeURIComponent(String(id));

        if (pageCohort !== "1402") {
            if (auth && typeof auth.appendCohortQuery === "function") {
                return auth.appendCohortQuery(base, pageCohort);
            }
            return base + "&cohort=" + encodeURIComponent(pageCohort);
        }

        return base;
    }

    function setFeedback(text, kind) {
        if (!feedback) {
            return;
        }
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
    }

    function ensureManageToggle() {
        if (!manage || !manage.firstElementChild) {
            return null;
        }
        var existing = $("notes-home-manage-toggle");
        if (existing) {
            return existing;
        }
        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.id = "notes-home-manage-toggle";
        toggle.className = "notes-manage-panel__toggle";
        toggle.addEventListener("click", function () {
            state.manageExpanded = !state.manageExpanded;
            render();
        });
        manage.firstElementChild.appendChild(toggle);
        return toggle;
    }

    function syncManagePanel() {
        if (!manage) {
            return;
        }
        if (manage.hidden) {
            if (form) {
                form.hidden = true;
            }
            return;
        }
        var toggle = ensureManageToggle();
        var expanded = !!state.manageExpanded;
        manage.dataset.collapsed = expanded ? "false" : "true";
        if (toggle) {
            toggle.textContent = expanded ? "بستن مدیریت ترم‌ها" : "باز کردن مدیریت ترم‌ها";
            toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
            toggle.setAttribute("aria-controls", "notes-home-form");
        }
        if (form) {
            form.hidden = !expanded;
        }
    }

    function inputs() {
        return {
            title: $("notes-home-term-title"),
            kicker: $("notes-home-term-kicker"),
            description: $("notes-home-term-description"),
            emptyMessage: $("notes-home-term-empty-message")
        };
    }

    function clearForm() {
        var nodes = inputs();
        Object.keys(nodes).forEach(function (key) {
            if (nodes[key]) {
                nodes[key].value = "";
            }
        });
    }

    function fillForm(term) {
        var nodes = inputs();
        if (nodes.title) nodes.title.value = term.title || "";
        if (nodes.kicker) nodes.kicker.value = term.kicker || "";
        if (nodes.description) nodes.description.value = term.description || "";
        if (nodes.emptyMessage) nodes.emptyMessage.value = term.emptyMessage || "";
    }

    function readPayload() {
        var nodes = inputs();
        return {
            title: nodes.title ? String(nodes.title.value || "").trim() : "",
            kicker: nodes.kicker ? String(nodes.kicker.value || "").trim() : "",
            description: nodes.description ? String(nodes.description.value || "").trim() : "",
            emptyMessage: nodes.emptyMessage ? String(nodes.emptyMessage.value || "").trim() : ""
        };
    }

    function findTerm(termIdValue) {
        return state.terms.filter(function (term) {
            return termId(term) === Number(termIdValue || 0);
        })[0] || null;
    }

    function setEditing(term) {
        state.editingTermId = term ? termId(term) : 0;
        if (!term) {
            clearForm();
            setFeedback("", "");
            render();
            return;
        }

        fillForm(term);
        state.manageExpanded = true;
        setFeedback("ترم برای ویرایش آماده شد.", "success");
        render();
        if (manage && typeof manage.scrollIntoView === "function") {
            manage.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    function createChevron() {
        var chevron = document.createElement("span");
        chevron.className = "action-card__chevron";
        chevron.setAttribute("aria-hidden", "true");
        return chevron;
    }

    function createPrimaryLink(term) {
        var link = document.createElement("a");
        link.className = "action-card__primary";
        link.href = termUrl(term);

        var content = document.createElement("span");
        content.className = "card-content";

        var header = document.createElement("span");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = term.kicker || "ترم";

        var titleNode = document.createElement("span");
        titleNode.className = "card-title";
        titleNode.textContent = term.title || "ترم بدون عنوان";

        var desc = document.createElement("span");
        desc.className = "card-desc";
        desc.textContent = term.description || "";

        var visual = document.createElement("span");
        visual.className = "action-card__visual";
        visual.setAttribute("aria-hidden", "true");
        visual.innerHTML = "<strong>" + toFaDigits(termId(term) || "?") + "</strong>";

        header.appendChild(badge);
        header.appendChild(titleNode);
        content.appendChild(header);
        content.appendChild(desc);

        link.appendChild(createChevron());
        link.appendChild(content);
        link.appendChild(visual);
        return link;
    }

    function buildCard(term) {
        if (!homeCanManage()) {
            var publicCard = createPrimaryLink(term);
            publicCard.classList.add("action-card", "action-card--link");
            return publicCard;
        }

        var id = termId(term);
        var card = document.createElement("article");
        card.className = "action-card";
        card.appendChild(createPrimaryLink(term));

        var actions = document.createElement("div");
        actions.className = "notes-card-actions";

        var open = document.createElement("a");
        open.className = "card-btn";
        open.href = termUrl(term);
        open.textContent = "ورود به صفحه";
        actions.appendChild(open);

        var edit = document.createElement("button");
        edit.type = "button";
        edit.className = "notes-card-edit";
        edit.dataset.termEdit = "true";
        edit.dataset.termId = String(id);
        edit.textContent = state.editingTermId === id ? "در حال ویرایش" : "ویرایش";
        edit.disabled = state.saving || state.deletingTermId > 0;
        actions.appendChild(edit);

        var remove = document.createElement("button");
        remove.type = "button";
        remove.className = "notes-card-delete";
        remove.dataset.termDelete = "true";
        remove.dataset.termId = String(id);
        remove.textContent = state.deletingTermId === id ? "در حال حذف..." : "حذف";
        remove.disabled = state.saving || state.deletingTermId === id;
        actions.appendChild(remove);

        card.appendChild(actions);
        return card;
    }

    function render() {
        applyPageCopy();
        list.innerHTML = "";
        var fragment = document.createDocumentFragment();

        if (manage) {
            manage.hidden = !homeCanManage();
        }
        syncManagePanel();
        if (submit) {
            submit.disabled = state.saving;
            submit.textContent = state.saving
                ? "در حال ذخیره..."
                : (state.editingTermId ? "ذخیره تغییرات ترم" : "افزودن ترم");
        }

        if (!state.terms.length) {
            empty.hidden = false;
            if (state.loading) {
                empty.textContent = "در حال دریافت ترم‌ها...";
                return;
            }
            if (state.loadError) {
                empty.textContent = state.loadError;
                return;
            }
            empty.textContent = pageCohort === "prosthesis-1402"
                ? "هنوز ترمی برای پروتز ۱۴۰۲ ثبت نشده است."
                : "هنوز ترمی برای این آرشیو ثبت نشده است.";
            return;
        }

        empty.hidden = true;
        state.terms.forEach(function (term) {
            fragment.appendChild(buildCard(term));
        });
        list.appendChild(fragment);
    }

    function loadTerms(options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        state.loadError = "";
        if (!(options && options.silent)) {
            render();
        }

        return request("terms", "GET", {}).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                state.canManage = false;
                render();
                return;
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "دریافت ترم‌ها ناموفق بود.");
            }

            state.terms = Array.isArray(payload.terms) ? payload.terms : [];
            state.canManage = manageSupported() && !!payload.canManage;
            if (state.editingTermId && !findTerm(state.editingTermId)) {
                state.editingTermId = 0;
                clearForm();
            }
            render();
        }).catch(function (error) {
            state.loadError = error && error.message ? error.message : "دریافت ترم‌ها با خطا مواجه شد.";
            state.canManage = false;
            if (manage) {
                manage.hidden = true;
            }
        }).finally(function () {
            state.loading = false;
            render();
        });
    }

    function saveTerm(event) {
        event.preventDefault();
        if (!homeCanManage() || state.saving) {
            return;
        }

        var payload = readPayload();
        if (!payload.title) {
            state.manageExpanded = true;
            syncManagePanel();
            setFeedback("عنوان ترم را وارد کن.", "error");
            return;
        }

        state.saving = true;
        state.manageExpanded = true;
        render();

        var editingId = state.editingTermId;
        var action = editingId ? "editTerm" : "addTerm";
        if (editingId) {
            payload.term = String(editingId);
        }

        request(action, "POST", payload).then(function (response) {
            if (consumeUnauthorized(response, "برای مدیریت این آرشیو باید وارد حساب مجاز شوید.")) {
                throw new Error("برای مدیریت این آرشیو باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ذخیره ترم انجام نشد.");
            }

            state.editingTermId = 0;
            state.manageExpanded = true;
            clearForm();
            setFeedback(editingId ? "ترم ویرایش شد." : "ترم جدید اضافه شد.", "success");
            return loadTerms({ silent: true });
        }).catch(function (error) {
            state.manageExpanded = true;
            setFeedback(error && error.message ? error.message : "ذخیره ترم انجام نشد.", "error");
        }).finally(function () {
            state.saving = false;
            render();
        });
    }

    function deleteTerm(termValue) {
        if (!homeCanManage() || state.deletingTermId > 0) {
            return;
        }

        var id = Number(termValue || 0);
        var term = findTerm(id);
        if (!term) {
            return;
        }
        if (!window.confirm("این ترم حذف شود؟")) {
            return;
        }

        state.deletingTermId = id;
        state.manageExpanded = true;
        render();

        request("deleteTerm", "POST", { term: String(id) }).then(function (response) {
            if (consumeUnauthorized(response, "برای مدیریت این آرشیو باید وارد حساب مجاز شوید.")) {
                throw new Error("برای مدیریت این آرشیو باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف ترم انجام نشد.");
            }

            if (state.editingTermId === id) {
                state.editingTermId = 0;
                clearForm();
            }
            state.manageExpanded = true;
            setFeedback("ترم حذف شد.", "success");
            return loadTerms({ silent: true });
        }).catch(function (error) {
            state.manageExpanded = true;
            setFeedback(error && error.message ? error.message : "حذف ترم انجام نشد.", "error");
        }).finally(function () {
            state.deletingTermId = 0;
            render();
        });
    }

    function bindForm() {
        if (!form) {
            return;
        }
        form.addEventListener("submit", saveTerm);
    }

    function bindList() {
        list.addEventListener("click", function (event) {
            var editButton = event.target.closest("[data-term-edit]");
            if (editButton) {
                event.preventDefault();
                if (!homeCanManage()) {
                    return;
                }
                setEditing(findTerm(Number(editButton.getAttribute("data-term-id") || 0)));
                return;
            }

            var deleteButton = event.target.closest("[data-term-delete]");
            if (!deleteButton) {
                return;
            }

            event.preventDefault();
            if (!homeCanManage()) {
                return;
            }
            deleteTerm(Number(deleteButton.getAttribute("data-term-id") || 0));
        });
    }

    function watchAuthChanges() {
        var auth = authApi();
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }

        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }
            state.authKey = nextKey;
            loadTerms({ silent: true });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindForm();
        bindList();
        watchAuthChanges();
        loadTerms({ silent: false });
    }

    boot();
})();
