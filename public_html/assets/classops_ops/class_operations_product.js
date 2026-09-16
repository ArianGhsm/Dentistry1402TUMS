(function () {
    "use strict";

    function $(id) { return document.getElementById(id); }
    function text(value) { return String(value == null ? "" : value); }
    function esc(value) {
        return text(value).replace(/[&<>"']/g, function (char) {
            return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"})[char];
        });
    }
    function core() { return window.ClassOpsOps || {}; }
    function label(group, value, fallback) {
        var api = core();
        return typeof api.uiLabel === "function" ? api.uiLabel(group, value, fallback) : text(fallback || "");
    }
    function fa(value) {
        var api = core();
        return typeof api.toPersianDigits === "function" ? api.toPersianDigits(value) : text(value);
    }
    function itemLabel(value) { return label("itemType", value, "مورد کلاس"); }
    function stateLabel(value) { return label("state", value, value ? "نامشخص" : ""); }
    function commandId(prefix) {
        var value = "";
        if (globalThis.crypto && typeof globalThis.crypto.randomUUID === "function") {
            value = globalThis.crypto.randomUUID().replace(/-/g, "");
        } else {
            value = Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
        }
        return prefix + "_" + value.slice(0, 36);
    }
    function persianDate(value) {
        var raw = text(value).trim();
        if (!raw) return "";
        var date = new Date(raw);
        if (!Number.isFinite(date.getTime())) return fa(raw);
        try {
            return date.toLocaleString("fa-IR-u-ca-persian", {
                year:"numeric", month:"2-digit", day:"2-digit", hour:"2-digit", minute:"2-digit", hour12:false,
                timeZone:"Asia/Tehran"
            });
        } catch (_error) {
            return fa(raw);
        }
    }
    function persianLocalDate(value) {
        var raw = text(value).trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return fa(raw);
        var date = new Date(raw + "T12:00:00+03:30");
        if (!Number.isFinite(date.getTime())) return fa(raw);
        try {
            return date.toLocaleDateString("fa-IR-u-ca-persian", {
                weekday:"long", year:"numeric", month:"long", day:"numeric", timeZone:"Asia/Tehran"
            });
        } catch (_error) {
            return fa(raw);
        }
    }
    function markerForType(type, needsAck) {
        if (type === "critical_notice" && needsAck) return "🚨";
        if (type === "exam") return "📝";
        if (type === "task" || type === "requirement") return "✅";
        if (type === "class_change") return "🔄";
        if (type === "deadline") return "⏳";
        if (type === "service_reminder") return "🔔";
        if (type === "event" || type === "schedule_ref") return "📅";
        return "📌";
    }
    function digestItemTime(item) {
        var timing = item && item.timing || {};
        if (timing.allDay && timing.localDate) return persianLocalDate(timing.localDate) + " · تمام‌روز";
        var raw = timing.dueAtUtc || timing.dueAt || timing.startsAtUtc || timing.startsAt || item.effectiveAtUtc || "";
        return raw ? persianDate(raw) : "";
    }
    function itemCourse(item) {
        var course = item && item.course;
        if (course && typeof course === "object") return text(course.title || "").trim();
        return "";
    }
    function make(tag, className, value) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (value != null) node.textContent = text(value);
        return node;
    }
    function appendFact(container, labelText, valueText) {
        if (!valueText) return;
        var fact = make("span", "classops-readable__fact");
        var key = make("small", "", labelText);
        var value = make("b", "", valueText);
        fact.append(key, value);
        container.appendChild(fact);
    }
    function renderFacts(container, title, rows, note, tone) {
        if (!container) return;
        container.replaceChildren();
        container.className = "classops-readable" + (tone ? " is-" + tone : "");
        if (title) container.appendChild(make("h4", "classops-readable__title", title));
        var facts = make("div", "classops-readable__facts");
        (rows || []).forEach(function (row) {
            if (!row || row.length < 2 || row[1] === "" || row[1] == null) return;
            appendFact(facts, row[0], row[1]);
        });
        if (facts.children.length) container.appendChild(facts);
        if (note) container.appendChild(make("p", "classops-readable__note", note));
        container.hidden = false;
    }
    function renderDigest(container, digest, kind) {
        if (!container) return;
        var weekly = kind === "weekly" || text(digest && digest.digestKind) === "weekly";
        container.replaceChildren();
        container.className = "classops-readable classops-digest-view " + (weekly ? "is-weekly" : "is-tomorrow");

        var head = make("div", "classops-digest-view__head");
        var headingCopy = make("div", "classops-digest-view__heading");
        headingCopy.appendChild(make("span", "classops-readable__eyebrow", weekly ? "نمای هفتگی" : "برنامه فردا"));
        headingCopy.appendChild(make("h4", "classops-readable__title", weekly ? "هفته پیش رو" : "فردا"));
        var windowData = digest && digest.window || {};
        var range = "";
        if (weekly) {
            var start = persianLocalDate(windowData.localStartDate || "");
            var endExclusive = text(windowData.localEndDateExclusive || "");
            var end = "";
            if (/^\d{4}-\d{2}-\d{2}$/.test(endExclusive)) {
                var endDate = new Date(endExclusive + "T12:00:00+03:30");
                endDate.setDate(endDate.getDate() - 1);
                end = endDate.toLocaleDateString("fa-IR-u-ca-persian", {month:"long", day:"numeric", timeZone:"Asia/Tehran"});
            }
            range = start + (end ? " تا " + end : "");
        } else {
            range = persianLocalDate(windowData.localStartDate || "");
        }
        if (range) headingCopy.appendChild(make("p", "classops-readable__sub", range));
        head.appendChild(headingCopy);
        container.appendChild(head);

        var sections = Array.isArray(digest && digest.sections) ? digest.sections : [];
        var visibleSections = sections.filter(function (section) { return Array.isArray(section.items) && section.items.length; });
        if (!visibleSections.length) {
            var empty = make("div", "classops-readable__empty");
            empty.appendChild(make("strong", "", weekly ? "این هفته مورد فعالی ثبت نشده است." : "برای فردا موردی ثبت نشده است."));
            empty.appendChild(make("span", "", weekly ? "اگر برنامه، تکلیف یا تغییری ثبت شود در همین نما دیده می‌شود." : "برنامه فردای شما فعلاً خالی است."));
            container.appendChild(empty);
            container.hidden = false;
            return;
        }

        visibleSections.forEach(function (section) {
            var block = make("section", "classops-digest-section");
            var title = make("div", "classops-digest-section__head");
            title.appendChild(make("h5", "", text(section.label || "موارد")));
            var sectionTotal = Number(section.total || section.items.length || 0);
            title.appendChild(make("span", "", fa(sectionTotal) + " مورد"));
            block.appendChild(title);
            var list = make("div", "classops-digest-section__items");
            section.items.forEach(function (item) {
                var article = make("article", "classops-digest-item");
                var row = make("div", "classops-digest-item__row");
                row.appendChild(make("span", "classops-digest-item__mark", markerForType(item.itemType, item.flags && item.flags.criticalAck)));
                var copy = make("div", "classops-digest-item__copy");
                copy.appendChild(make("strong", "", text(item.title || itemLabel(item.itemType))));
                var chips = make("div", "classops-digest-item__meta");
                var when = digestItemTime(item);
                var course = itemCourse(item);
                if (when) chips.appendChild(make("span", "", "🕒 " + when));
                if (course) chips.appendChild(make("span", "", "📚 " + course));
                if (item.location) chips.appendChild(make("span", "", "📍 " + text(item.location)));
                if (item.changeLabel) chips.appendChild(make("span", "is-change", text(item.changeLabel)));
                copy.appendChild(chips);
                if (!weekly && item.description) copy.appendChild(make("p", "classops-digest-item__description", text(item.description)));
                row.appendChild(copy);
                article.appendChild(row);
                list.appendChild(article);
            });
            block.appendChild(list);
            container.appendChild(block);
        });

        var budget = digest && digest.budget || {};
        if (budget.truncated) {
            container.appendChild(make("p", "classops-readable__note", fa(budget.omittedItems || 0) + " مورد دیگر برای خوانایی این نما خلاصه شده است."));
        }
        container.hidden = false;
    }

    function installTypeAwareComposer() {
        var type = $("classops-type");
        if (!type) return;
        var course = $("classops-course-title");
        var starts = $("classops-starts-at");
        var due = $("classops-due-at");
        var location = $("classops-location");
        var ack = $("classops-require-ack");
        var saba = $("classops-saba-note");
        function parent(node) { return node && node.closest("label"); }
        function render() {
            var kind = type.value;
            var showCourse = ["event","class_change","deadline","task","requirement","exam"].indexOf(kind) >= 0;
            var showStart = ["event","class_change","exam"].indexOf(kind) >= 0;
            var showDue = ["deadline","task","requirement","exam","service_reminder"].indexOf(kind) >= 0;
            var showLocation = ["event","class_change","exam"].indexOf(kind) >= 0;
            if (parent(course)) parent(course).hidden = !showCourse;
            if (parent(starts)) parent(starts).hidden = !showStart;
            if (parent(due)) parent(due).hidden = !showDue;
            if (parent(location)) parent(location).hidden = !showLocation;
            if (ack) {
                ack.disabled = kind !== "critical_notice";
                ack.checked = kind === "critical_notice";
                if (parent(ack)) parent(ack).hidden = kind !== "critical_notice";
            }
            if (saba) saba.hidden = kind !== "service_reminder";
        }
        type.addEventListener("change", render);
        render();
    }

    function studentCard(item) {
        var kind = itemLabel(item.type);
        var status = stateLabel(item.status);
        var timing = item.timing || {};
        var when = persianDate(timing.dueAt || timing.startsAt || "");
        var task = item.task || null;
        var taskState = task ? stateLabel(task.state) : "";
        var marker = markerForType(item.type, item.ack && !item.ack.acked);
        return '<button class="class-operations-student-item" type="button" data-student-item="' + esc(item.id) + '">' +
            '<span class="class-operations-student-item__mark">' + marker + '</span>' +
            '<span class="class-operations-student-item__copy"><strong>' + esc(item.title || kind) + '</strong>' +
            '<small>' + esc(kind + (status ? " · " + status : "") + (when ? " · " + when : "") + (taskState ? " · " + taskState : "")) + '</small></span>' +
            '<span class="class-operations-student-item__chevron" aria-hidden="true">‹</span></button>';
    }

    function studentDetail(item) {
        var timing = item.timing || {};
        var bits = [];
        if (timing.startsAt) bits.push("شروع: " + persianDate(timing.startsAt));
        if (timing.dueAt) bits.push("مهلت: " + persianDate(timing.dueAt));
        if (item.location) bits.push("مکان: " + text(item.location));
        var html = '<div class="class-operations-student-detail__head"><span>' + esc(itemLabel(item.type)) + '</span><h3>' + esc(item.title || itemLabel(item.type)) + '</h3></div>';
        if (item.description) html += '<p>' + esc(item.description) + '</p>';
        if (bits.length) html += '<div class="class-operations-student-detail__meta">' + bits.map(function (bit) { return '<span>' + esc(bit) + '</span>'; }).join("") + '</div>';
        if (item.task) {
            html += '<p class="class-operations-product-note">وضعیت من: <b>' + esc(stateLabel(item.task.state) || "در انتظار") + '</b></p>';
            if (["completed","waived"].indexOf(item.task.state) < 0) {
                html += '<div class="classops-action-row"><button class="classops-button classops-button--secondary" type="button" data-student-task="submitted">ارسال شد</button><button class="classops-button classops-button--primary" type="button" data-student-task="completed">انجام شد</button></div>';
            }
        }
        if (item.ack && !item.ack.acked) {
            html += '<div class="class-operations-product-alert">این اطلاعیه نیازمند تأیید تو است.</div><button class="classops-button classops-button--primary" type="button" data-student-ack>این اطلاعیه را دیدم و تأیید می‌کنم</button>';
        } else if (item.ack && item.ack.acked) {
            html += '<p class="class-operations-product-ok">✅ تأیید این اطلاعیه ثبت شده است.</p>';
        }
        if (item.service) {
            var serviceState = item.service.state || {};
            html += '<p class="class-operations-product-note">وضعیت یادآوری: <b>' + esc(stateLabel(serviceState.state) || "در انتظار") + '</b><br><small>این وضعیت فقط در سایت ثبت می‌شود و انجام واقعی در صبا را تأیید نمی‌کند.</small></p>';
            if (["completed","waived"].indexOf(serviceState.state) < 0) {
                html += '<div class="classops-action-row"><button class="classops-button classops-button--primary" type="button" data-student-service="completed">انجام شد</button><button class="classops-button classops-button--secondary" type="button" data-student-service="waived">نیاز نیست</button></div>';
            }
        }
        return html;
    }

    async function mountStudentSurface() {
        var root = $("classops-student-center");
        var list = $("classops-student-list");
        var detail = $("classops-student-detail");
        var state = $("classops-student-state");
        if (!root || !list || !detail || !state || !window.ClassOpsOps) return false;
        var client = new window.ClassOpsOps.ClassOpsClient();
        var currentItem = null;

        async function loadList() {
            state.textContent = "در حال آماده‌سازی امور کلاس…";
            try {
                var response = await client.request("student-list");
                var data = response && response.data || {};
                var items = Array.isArray(data.items) ? data.items : [];
                root.hidden = false;
                var ownerCenter = $("classops-owner-center");
                if (ownerCenter) ownerCenter.hidden = true;
                var access = $("classops-access-state");
                if (access) access.hidden = true;
                state.textContent = items.length ? fa(items.length) + " مورد برای حساب شما" : "فعلاً موردی برای شما ثبت نشده است.";
                list.innerHTML = items.length ? items.map(studentCard).join("") : '<div class="classops-readable__empty"><strong>فعلاً چیزی برای شما ثبت نشده است.</strong><span>اطلاعیه، تکلیف و تغییرات برنامه در این بخش نمایش داده می‌شوند.</span></div>';
                return true;
            } catch (error) {
                if (Number(error && error.status) === 403) return false;
                root.hidden = false;
                state.textContent = "این بخش فعلاً در دسترس نیست. صفحه را تازه کن و دوباره امتحان کن.";
                list.innerHTML = "";
                return true;
            }
        }

        async function openItem(id) {
            detail.hidden = false;
            detail.innerHTML = '<p class="classops-state">در حال دریافت جزئیات…</p>';
            try {
                var response = await client.request("student-get", {query:{id:id}});
                currentItem = response.item || {};
                detail.innerHTML = studentDetail(currentItem);
                detail.scrollIntoView({behavior:"smooth",block:"nearest"});
            } catch (_error) {
                detail.innerHTML = '<p class="classops-state">جزئیات این مورد فعلاً در دسترس نیست.</p>';
            }
        }

        list.addEventListener("click", function (event) {
            var target = event.target.closest("[data-student-item]");
            if (target) openItem(target.getAttribute("data-student-item"));
        });

        detail.addEventListener("click", async function (event) {
            if (!currentItem || !currentItem.id) return;
            var task = event.target.closest("[data-student-task]");
            var ack = event.target.closest("[data-student-ack]");
            var service = event.target.closest("[data-student-service]");
            try {
                if (task && currentItem.task) {
                    await client.request("student-task-transition", {method:"POST", body:{
                        id:currentItem.id,
                        expectedStateRevision:Number(currentItem.task.stateRevision || 0),
                        target:task.getAttribute("data-student-task"),
                        commandId:commandId("webtask"),
                        reason:"student website action"
                    }});
                } else if (ack && currentItem.ack) {
                    await client.request("student-ack", {method:"POST", body:{
                        id:currentItem.id,
                        expectedRevision:Number(currentItem.revision || 0),
                        idempotencyKey:commandId("weback")
                    }});
                } else if (service && currentItem.service) {
                    var serviceState = currentItem.service.state || {};
                    await client.request("student-service-transition", {method:"POST", body:{
                        id:currentItem.id,
                        expectedStateRevision:Number(serviceState.stateRevision || 0),
                        target:service.getAttribute("data-student-service"),
                        commandId:commandId("webservice")
                    }});
                } else {
                    return;
                }
                await openItem(currentItem.id);
                await loadList();
            } catch (_error) {
                var notice = document.createElement("p");
                notice.className = "classops-state";
                notice.textContent = "این تغییر ثبت نشد. صفحه را تازه کن و دوباره امتحان کن.";
                detail.prepend(notice);
            }
        });

        ["tomorrow", "weekly"].forEach(function (kind) {
            var buttonNode = $("classops-student-" + kind);
            if (!buttonNode) return;
            buttonNode.addEventListener("click", async function () {
                var output = $("classops-student-digest");
                if (!output) return;
                output.hidden = false;
                renderFacts(output, kind === "weekly" ? "هفته پیش رو" : "فردا", [], "در حال آماده‌سازی…", "loading");
                try {
                    var result = await client.request(kind === "tomorrow" ? "tomorrow-summary" : "weekly-digest");
                    renderDigest(output, result && result.digest || {}, kind);
                } catch (_error) {
                    renderFacts(output, "خلاصه در دسترس نیست", [], "صفحه را تازه کن و دوباره امتحان کن.", "error");
                }
            });
        });

        return loadList();
    }

    async function mount() {
        installTypeAwareComposer();
        await mountStudentSurface();
    }

    window.ClassOperationsProduct = Object.freeze({
        mount:mount,
        renderDigest:renderDigest,
        renderFacts:renderFacts,
        persianDate:persianDate,
        stateLabel:stateLabel,
        itemLabel:itemLabel
    });
})();
