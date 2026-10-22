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
    newCaptcha();
})();
