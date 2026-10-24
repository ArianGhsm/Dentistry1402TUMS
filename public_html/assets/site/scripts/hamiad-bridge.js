(() => {
    "use strict";

    const csrf = document.getElementById("hamiad-config")?.dataset.csrf || "";
    const form = document.getElementById("hamiad-form");
    const user = document.getElementById("hamiad-username");
    const password = document.getElementById("hamiad-password");
    const captcha = document.getElementById("hamiad-captcha");
    const image = document.getElementById("hamiad-image");
    const loading = document.getElementById("hamiad-image-loading");
    const submit = document.getElementById("hamiad-submit");
    const refresh = document.getElementById("hamiad-refresh");
    const feedback = document.getElementById("hamiad-feedback");
    let busy = false;
    let imageReady = false;

    function status(message, failed = false) {
        feedback.textContent = message;
        feedback.classList.toggle("is-error", failed);
    }

    async function request(action, body = "") {
        const response = await fetch("/api/hamiad_bridge_api.php?action=" + action, {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            redirect: "error",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "X-CSRF-Token": csrf,
            },
            body,
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || !result || result.success !== true) {
            throw new Error(result?.error || "ارتباط با سامانه برقرار نشد.");
        }
        return result;
    }

    async function newCaptcha() {
        if (busy) return;
        busy = true;
        imageReady = false;
        submit.disabled = true;
        refresh.disabled = true;
        image.hidden = true;
        image.removeAttribute("src");
        loading.hidden = false;
        loading.textContent = "در حال دریافت تصویر امنیتی…";
        captcha.value = "";
        status("");
        try {
            const data = await request("challenge");
            if (!String(data.image || "").startsWith("data:image/png;base64,")) {
                throw new Error("تصویر دریافتی معتبر نیست.");
            }
            image.src = data.image;
            image.hidden = false;
            loading.hidden = true;
            imageReady = true;
        } catch (error) {
            loading.textContent = "تصویر در دسترس نیست.";
            status(error.message + " برای تلاش مجدد «تصویر جدید» را بزن.", true);
        } finally {
            busy = false;
            refresh.disabled = false;
            submit.disabled = !imageReady;
        }
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (busy || !imageReady || !form.reportValidity()) return;
        busy = true;
        submit.disabled = true;
        refresh.disabled = true;
        submit.textContent = "در حال بررسی ورود…";
        status("");
        const params = new URLSearchParams({
            username: user.value.trim(),
            password: password.value,
            captcha: captcha.value.trim().replace(/[۰-۹]/g, digit => "۰۱۲۳۴۵۶۷۸۹".indexOf(digit)),
        });
        try {
            const result = await request("login", params.toString());
            password.value = "";
            captcha.value = "";
            document.getElementById("hamiad-form-panel").hidden = true;
            document.getElementById("hamiad-success-panel").hidden = false;
            status(result.message || "");
            loadDashboard();
        } catch (error) {
            password.value = "";
            status(error.message + " برای تلاش بعدی تصویر تازه دریافت کن.", true);
            imageReady = false;
            image.hidden = true;
            loading.hidden = false;
            loading.textContent = "کپچای قبلی مصرف شد.";
        } finally {
            busy = false;
            refresh.disabled = false;
            submit.disabled = !imageReady;
            submit.textContent = "ورود به همیاد";
        }
    });

    refresh.addEventListener("click", newCaptcha);

    const connectPanel = document.getElementById("hamiad-success-panel");
    const loginPanel = document.getElementById("hamiad-form-panel");
    const courseButton = document.getElementById("hamiad-load-courses");
    const courseList = document.getElementById("hamiad-course-list");
    const courseDetails = document.getElementById("hamiad-course-details");
    const courseFeedback = document.getElementById("hamiad-course-feedback");
    const overview = document.getElementById("hamiad-overview");
    const taskList = document.getElementById("hamiad-task-list");
    const coursesTab = document.getElementById("hamiad-view-courses");
    const tasksTab = document.getElementById("hamiad-view-tasks");
    let dashboard = null;
    let activeView = "courses";
    let loadSequence = 0;

    const persianDigits = (value) => String(value ?? "").replace(/[0-9]/g, digit => "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]);
    const normalizedCourse = (value) => persianDigits(String(value || "درس").replace(/\s*-\s*ترم\s*[۰-۹0-9]+\s*-\s*کدگروه\s*[۰-۹0-9]+\s*$/u, ""));
    const create = (tag, cls, text) => {
        const element = document.createElement(tag);
        if (cls) element.className = cls;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    async function readData(action) {
        const response = await fetch("/api/hamiad_bridge_api.php?action=" + action, {
            credentials: "same-origin", cache: "no-store", redirect: "error",
        });
        const value = await response.json().catch(() => null);
        if (!response.ok || !value || value.success !== true) {
            throw new Error(value?.error || "دریافت اطلاعات از سامانه انجام نشد.");
        }
        return value;
    }

    function showConnected() {
        loginPanel.hidden = true;
        connectPanel.hidden = false;
    }

    function statusMessage(text, isError = false) {
        courseFeedback.textContent = text;
        courseFeedback.classList.toggle("is-error", isError);
    }

    function makeMetric(icon, number, label) {
        const item = create("div", "hamiad-metric");
        item.append(create("span", "hamiad-metric-icon", icon),
                    create("strong", "hamiad-metric-count", persianDigits(number)),
                    create("span", "hamiad-metric-label", label));
        return item;
    }

    function renderDashboardSummary(data) {
        overview.replaceChildren(
            makeMetric("📚", data.courses?.length || 0, "درس"),
            makeMetric("📎", data.resources || 0, "منبع آموزشی"),
            makeMetric("⏰", data.assignments?.length || 0, "تکلیف")
        );
    }

    function showView(view) {
        activeView = view;
        courseList.hidden = view !== "courses";
        taskList.hidden = view !== "tasks";
        courseDetails.hidden = true;
        coursesTab.classList.toggle("is-active", view === "courses");
        tasksTab.classList.toggle("is-active", view === "tasks");
        if (!dashboard) return;
        if (view === "tasks") renderTasks(dashboard.assignments || []);
        else renderCourses(dashboard.courses || []);
    }

    function renderCourses(courses) {
        courseList.replaceChildren();
        if (!courses.length) {
            courseList.append(create("p", "hamiad-empty", "در نیمسال جاری درسی در همیاد شناسایی نشده است."));
            return;
        }
        for (const course of courses) {
            const row = create("article", "hamiad-course-card");
            const content = create("div", "hamiad-course-content");
            content.append(
                create("strong", "hamiad-course-name", normalizedCourse(course.title)),
                create("span", "hamiad-course-meta",
                    "📎 " + persianDigits(course.resourceCount || 0) + " منبع · ⏰ " +
                    persianDigits(course.assignmentCount || 0) + " تکلیف")
            );
            const button = create("button", "bot-link-secondary hamiad-open-course", "مشاهده ←");
            button.type = "button";
            button.addEventListener("click", () => renderCourseDetail(course));
            row.append(content, button);
            courseList.append(row);
        }
        statusMessage("درس‌های نیمسال جاری بر اساس حساب متصل نمایش داده می‌شوند.");
    }

    function renderTasks(tasks) {
        taskList.replaceChildren();
        if (!tasks.length) {
            const empty = create("div", "hamiad-empty");
            empty.append(
                create("strong", "", "⏰ تکلیفی ثبت نشده است"),
                create("p", "", "در حال حاضر در دروس ترم ۷ این حساب تکلیفی در سامانه همیاد شناسایی نشده است.")
            );
            taskList.append(empty);
            statusMessage("با تازه‌سازی، تکالیف احتمالی جدید مستقیماً بررسی می‌شوند.");
            return;
        }
        for (const task of tasks) {
            const card = create("article", "hamiad-course-card");
            const info = create("div", "hamiad-course-content");
            const dueText = task.dueText || "زمان تحویل در سامانه مشخص نیست";
            const delivery = task.submissionStatus || "وضعیت ارسال نامشخص";
            info.append(
                create("strong", "hamiad-course-name", persianDigits(task.title || "تکلیف")),
                create("span", "hamiad-course-meta", "📚 " + normalizedCourse(task.courseTitle)),
                create("span", "hamiad-course-meta", "⏳ " + persianDigits(dueText)),
                create("span", "hamiad-course-meta", "📤 " + persianDigits(delivery))
            );
            const action = create("button", "bot-link-secondary", "جزئیات");
            action.type = "button";
            action.addEventListener("click", () => renderAssignmentDetail(task.courseId, task.id));
            card.append(info, action);
            taskList.append(card);
        }
        statusMessage("عنوان و اطلاعات هر تکلیف از صفحهٔ اصلی همان درس دریافت شده‌اند.");
    }

    async function renderCourseDetail(course) {
        courseList.hidden = true;
        taskList.hidden = true;
        courseDetails.hidden = false;
        coursesTab.classList.add("is-active");
        tasksTab.classList.remove("is-active");
        courseDetails.replaceChildren(create("p", "hamiad-empty", "در حال دریافت منابع و تکالیف درس…"));
        statusMessage("");
        try {
            const result = await readData("termCourse&id=" + encodeURIComponent(course.id));
            const items = Array.isArray(result.detail?.items) ? result.detail.items : [];
            courseDetails.replaceChildren();
            const heading = create("div", "hamiad-detail-head");
            const back = create("button", "bot-link-secondary", "↩ بازگشت به درس‌ها");
            back.type = "button";
            back.addEventListener("click", () => showView("courses"));
            heading.append(create("strong", "", normalizedCourse(course.title)), back);
            courseDetails.append(heading);
            if (!items.length) {
                courseDetails.append(create("p", "hamiad-empty", "هنوز منبع یا تکلیفی برای این درس ثبت نشده است."));
            }
            for (const item of items) {
                const row = create("article", "hamiad-resource-row");
                const icon = item.kind === "assign" ? "⏰" : (item.kind === "folder" ? "📂" : "📎");
                row.append(create("span", "hamiad-resource-type", icon + " " + (item.type || "محتوا")),
                           create("strong", "", persianDigits(item.title || "بدون عنوان")));
                if (item.kind === "assign") {
                    const action = create("button", "bot-link-secondary", "مشاهدهٔ تکلیف");
                    action.addEventListener("click", () => renderAssignmentDetail(course.id, item.id));
                    row.append(action);
                } else if (Number.isSafeInteger(Number(item.id)) && Number(item.id) > 0
                    && ["resource", "folder", "page"].includes(item.kind)) {
                    const link = create("a", "bot-link-secondary", "باز کردن در همیاد");
                    link.href = "https://lms.tums.ac.ir/mod/" + item.kind + "/view.php?id=" + Number(item.id);
                    link.target = "_blank";
                    link.rel = "noopener noreferrer";
                    row.append(link);
                }
                courseDetails.append(row);
            }
        } catch (error) {
            courseDetails.replaceChildren(create("p", "hamiad-empty", error.message));
            statusMessage(error.message, true);
        }
    }

    async function renderAssignmentDetail(courseId, activityId) {
        courseDetails.hidden = false;
        courseList.hidden = true;
        taskList.hidden = true;
        courseDetails.replaceChildren(create("p", "hamiad-empty", "در حال دریافت جزئیات تکلیف…"));
        try {
            const result = await readData("assignment&courseId=" + encodeURIComponent(courseId)
                + "&activityId=" + encodeURIComponent(activityId));
            const item = result.assignment || {};
            courseDetails.replaceChildren();
            const back = create("button", "bot-link-secondary", "↩ بازگشت به تکالیف");
            back.addEventListener("click", () => showView("tasks"));
            courseDetails.append(back,
                create("strong", "hamiad-course-name", persianDigits(item.title || "تکلیف")),
                create("p", "hamiad-course-meta", "⏳ زمان باقی‌مانده: " + persianDigits(item.dueText || "در سامانه درج نشده")),
                create("p", "hamiad-course-meta", "📤 وضعیت ارسال: " + persianDigits(item.submissionStatus || "نامشخص")),
                create("p", "hamiad-course-meta", "📋 وضعیت تصحیح: " + persianDigits(item.gradingStatus || "نامشخص")),
                create("p", "", persianDigits(item.description || "توضیحی برای این تکلیف ثبت نشده است.")));
        } catch (error) {
            courseDetails.replaceChildren(create("p", "hamiad-empty", error.message));
        }
    }

    async function loadDashboard() {
        const sequence = ++loadSequence;
        courseButton.disabled = true;
        statusMessage("در حال بررسی درس‌ها و تکالیف واقعی ترم ۷…");
        try {
            const result = await readData("dashboard");
            if (sequence !== loadSequence) return;
            dashboard = result.dashboard || null;
            if (!dashboard?.connected) {
                statusMessage("نشست همیاد متصل نیست؛ ورود دوباره لازم است.", true);
                return;
            }
            renderDashboardSummary(dashboard);
            showView(activeView);
        } catch (error) {
            if (sequence !== loadSequence) return;
            dashboard = null;
            statusMessage(error.message, true);
        } finally {
            if (sequence === loadSequence) courseButton.disabled = false;
        }
    }

    courseButton.addEventListener("click", loadDashboard);
    coursesTab.addEventListener("click", () => showView("courses"));
    tasksTab.addEventListener("click", () => showView("tasks"));
    document.getElementById("hamiad-reconnect").addEventListener("click", () => {
        dashboard = null;
        connectPanel.hidden = true;
        loginPanel.hidden = false;
        newCaptcha();
    });

    readData("status").then((result) => {
        if (result.status?.connected) {
            showConnected();
            loadDashboard();
        } else {
            newCaptcha();
        }
    }).catch(() => newCaptcha());

})();
