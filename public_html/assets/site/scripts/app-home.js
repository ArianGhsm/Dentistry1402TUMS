(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var panel = $("home-identity-panel");
    if (!panel) {
        return;
    }

    var status = $("home-identity-status");
    var title = $("home-identity-title");
    var desc = $("home-identity-desc");
    var meta = $("home-identity-meta");
    var primaryAction = $("home-identity-primary");
    var secondaryAction = $("home-identity-secondary");
    var ownerBadge = $("home-owner-badge");

    function setLoggedOutUi() {
        panel.dataset.authState = "logged-out";
        status.textContent = "ورود لازم است";
        title.textContent = "حساب سراسری‌ات را فعال کن.";
        desc.textContent = "با همان شماره دانشجویی و رمز از پیش‌تعریف‌شده وارد شو تا نمرات، چت و حساب کاربری‌ات در کل سایت یکپارچه شوند.";
        meta.textContent = "بعد از ورود، نشست روی همین دستگاه نگه داشته می‌شود.";
        ownerBadge.hidden = true;
        primaryAction.textContent = "ورود به حساب";
        primaryAction.href = window.Dent1402Auth.loginUrl("/app/");
        secondaryAction.textContent = "مدیریت حساب";
        secondaryAction.href = "/account/";
    }

    function setBootUi(message) {
        panel.dataset.authState = "restoring";
        status.textContent = "در حال بازیابی";
        title.textContent = "نشست حساب در حال آماده‌سازی است.";
        desc.textContent = message || "اگر قبلاً وارد شده باشی، هویتت روی همین دستگاه برمی‌گردد.";
        meta.textContent = "چند لحظه صبر کن.";
        ownerBadge.hidden = true;
        primaryAction.textContent = "در حال بررسی...";
        primaryAction.href = "/account/";
        secondaryAction.textContent = "حساب کاربری";
        secondaryAction.href = "/account/";
    }

    function setLoggedInUi(user) {
        var isOwner = !!(user && user.isOwner);
        panel.dataset.authState = isOwner ? "logged-in-admin" : "logged-in";
        status.textContent = isOwner ? "مالک سامانه" : (user.roleLabel || "حساب فعال");
        title.textContent = (user.name || "دانشجو") + "، خوش برگشتی.";
        desc.textContent = isOwner
            ? "دسترسی مدیریتی فعال است و از همین‌جا می‌توانی چت، نماینده‌ها و حساب‌ها را مدیریت کنی."
            : "هویتت در چت، نمرات و حساب کاربری همگام است و لازم نیست هر صفحه جداگانه وارد شوی.";
        meta.textContent = "شماره دانشجویی: " + (user.studentNumber || "-");
        ownerBadge.hidden = !isOwner;
        primaryAction.textContent = isOwner ? "پنل حساب و مدیریت" : "حساب کاربری";
        primaryAction.href = "/account/";
        secondaryAction.textContent = "نمرات من";
        secondaryAction.href = "/grades/";
    }

    function sync(detail) {
        if (detail.status === "restoring" || detail.status === "logging-out") {
            setBootUi(detail.status === "logging-out" ? "در حال بستن نشست فعلی..." : "");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            setLoggedOutUi();
            return;
        }

        setLoggedInUi(detail.user);
    }

    window.Dent1402Auth.onChange(sync);
})();
