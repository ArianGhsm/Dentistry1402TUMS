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

    async function readData(action) {
        const response = await fetch("/api/hamiad_bridge_api.php?action=" + action, {
            credentials: "same-origin", cache: "no-store",
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

    async function loadCourses() {
        courseButton.disabled = true;
        courseFeedback.textContent = "در حال بررسی درس‌های ثبت‌شده…";
        courseList.replaceChildren();
        courseDetails.replaceChildren();
        try {
            const result = await readData("courses");
            const list = Array.isArray(result.courses) ? result.courses : [];
            courseFeedback.textContent = list.length
                ? "برای نمایش محتوای هر درس، نام درس را انتخاب کن."
                : "هنوز درسی از همیاد شناسایی نشده است.";
            for (const course of list) {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "bot-link-secondary hamiad-course-button";
                button.textContent = String(course.title || "درس بدون عنوان");
                button.addEventListener("click", async () => {
                    courseDetails.replaceChildren();
                    courseFeedback.textContent = "در حال دریافت محتوای درس…";
                    try {
                        const data = await readData("course&id=" + encodeURIComponent(String(course.id)));
                        const heading = document.createElement("strong");
                        heading.textContent = String(course.title || "");
                        courseDetails.appendChild(heading);
                        const items = Array.isArray(data.items) ? data.items : [];
                        for (const item of items) {
                            const line = document.createElement("div");
                            line.className = "hamiad-resource-row";
                            const type = document.createElement("span");
                            type.textContent = String(item.type || "محتوا");
                            const title = document.createElement("strong");
                            title.textContent = String(item.title || "بدون عنوان");
                            line.append(type, title);
                            courseDetails.appendChild(line);
                        }
                        courseFeedback.textContent = items.length
                            ? "محتوای شناسایی‌شده در همیاد:"
                            : "محتوای قابل نمایشی در این درس پیدا نشد.";
                    } catch (error) {
                        courseFeedback.textContent = error.message;
                    }
                });
                courseList.appendChild(button);
            }
        } catch (error) {
            courseFeedback.textContent = error.message;
        } finally {
            courseButton.disabled = false;
        }
    }

    courseButton.addEventListener("click", loadCourses);
    document.getElementById("hamiad-reconnect").addEventListener("click", () => {
        connectPanel.hidden = true;
        loginPanel.hidden = false;
        newCaptcha();
    });

    readData("status").then((result) => {
        if (result.status?.connected) {
            showConnected();
            courseFeedback.textContent = "نشست قبلی همیاد روی سرور ذخیره شده است.";
        } else {
            newCaptcha();
        }
    }).catch(() => newCaptcha());

})();
