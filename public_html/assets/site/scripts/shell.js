(function () {
    if (document.body && document.body.dataset.shell === "off") {
        return;
    }

    var NAV_ITEMS = [
        { href: "/app/", label: "خانه", icon: "⌂", active: ["/", "/app/"] },
        { href: "/chat/", label: "چت", icon: "◉", active: ["/chat/"] },
        { href: "/exams/", label: "آزمون", icon: "◎", active: ["/exams/"] },
        { href: "/grades/", label: "نمرات", icon: "◌", active: ["/grades/"] },
        { href: "/resources/", label: "بیشتر", icon: "⋯", active: ["/resources/", "/notes/"] }
    ];

    var drawer = null;
    var drawerBackdrop = null;
    var drawerOpen = false;
    var modal = null;
    var modalBackdrop = null;
    var pendingExternal = null;
    var statusPill = null;

    function currentPath() {
        var path = window.location.pathname || "/";
        return path.endsWith("/") ? path : path + "/";
    }

    function isActive(item) {
        var path = currentPath();
        return item.active.some(function (prefix) {
            return path === prefix || path.indexOf(prefix) === 0;
        });
    }

    function injectBottomNav() {
        var nav = document.createElement("nav");
        nav.className = "shell-bottom-nav";
        nav.setAttribute("aria-label", "ناوبری اصلی");

        var inner = document.createElement("div");
        inner.className = "shell-bottom-nav__inner";

        NAV_ITEMS.forEach(function (item) {
            var link = document.createElement("a");
            link.className = "shell-bottom-nav__link";
            if (isActive(item)) {
                link.classList.add("is-active");
                link.setAttribute("aria-current", "page");
            }
            link.href = item.href;
            link.innerHTML = '<span class="shell-bottom-nav__icon" aria-hidden="true">' + item.icon + '</span><span class="shell-bottom-nav__label">' + item.label + '</span>';
            inner.appendChild(link);
        });

        nav.appendChild(inner);
        document.body.appendChild(nav);
    }

    function createStatusPill() {
        statusPill = document.createElement("div");
        statusPill.className = "shell-status-pill";
        statusPill.setAttribute("role", "status");
        statusPill.setAttribute("aria-live", "polite");
        document.body.appendChild(statusPill);
    }

    function setStatus(message, mode) {
        if (!statusPill) {
            return;
        }

        statusPill.textContent = message;
        statusPill.classList.toggle("is-offline", mode === "offline");
        statusPill.classList.add("is-visible");

        window.clearTimeout(setStatus._timer);
        if (mode !== "offline") {
            setStatus._timer = window.setTimeout(function () {
                statusPill.classList.remove("is-visible");
            }, 2600);
        }
    }

    function createDrawer() {
        drawerBackdrop = document.createElement("div");
        drawerBackdrop.className = "shell-drawer-backdrop";
        drawerBackdrop.addEventListener("click", closeDrawer);

        drawer = document.createElement("aside");
        drawer.className = "shell-drawer";
        drawer.setAttribute("aria-hidden", "true");
        drawer.innerHTML = [
            '<div class="shell-drawer__header">',
            '  <div>',
            '    <h2 class="shell-drawer__title">منو</h2>',
            '    <p class="shell-drawer__desc">هر بخشی را لازم داری از همین‌جا باز کن.</p>',
            '  </div>',
            '  <button class="shell-dismiss-btn" type="button" aria-label="بستن">×</button>',
            '</div>',
            '<div class="shell-drawer__group">',
            '  <span class="shell-drawer__label">بخش‌های اصلی</span>',
            '  <div class="shell-drawer__grid">',
            '    <a class="shell-drawer__link" href="/app/"><span>خانه</span><span class="shell-drawer__meta">شروع</span></a>',
            '    <a class="shell-drawer__link" href="/chat/"><span>چت</span><span class="shell-drawer__meta">گفت‌وگو</span></a>',
            '    <a class="shell-drawer__link" href="/exams/"><span>آزمون‌ها</span><span class="shell-drawer__meta">مرور</span></a>',
            '    <a class="shell-drawer__link" href="/grades/"><span>نمرات</span><span class="shell-drawer__meta">شخصی</span></a>',
            '    <a class="shell-drawer__link" href="/notes/"><span>جزوات ۱۴۰۲</span><span class="shell-drawer__meta">فایل‌ها</span></a>',
            '    <a class="shell-drawer__link" href="/notes/1403/"><span>جزوات ۱۴۰۳</span><span class="shell-drawer__meta">فایل‌ها</span></a>',
            '    <a class="shell-drawer__link" href="/resources/"><span>خدمات</span><span class="shell-drawer__meta">لینک‌ها</span></a>',
            '  </div>',
            '</div>',
            '<div class="shell-drawer__group">',
            '  <span class="shell-drawer__label">لینک‌های بیرونی</span>',
            '  <div class="shell-drawer__grid">',
            '    <a class="shell-drawer__link" href="https://navid.tums.ac.ir/" data-external-link="true"><span>نوید</span><span class="shell-drawer__meta">آموزشی</span></a>',
            '    <a class="shell-drawer__link" href="https://foodstu.tums.ac.ir/" data-external-link="true"><span>تغذیه</span><span class="shell-drawer__meta">رزرو</span></a>',
            '    <a class="shell-drawer__link" href="https://sipad.tums.ac.ir/" data-external-link="true"><span>سیپاد</span><span class="shell-drawer__meta">دانشگاه</span></a>',
            '    <a class="shell-drawer__link" href="https://t.me/teledentistry1402" data-external-link="true"><span>تلگرام</span><span class="shell-drawer__meta">کلاس</span></a>',
            '    <a class="shell-drawer__link" href="https://dentistry.tums.ac.ir/" data-external-link="true"><span>دانشکده</span><span class="shell-drawer__meta">سایت رسمی</span></a>',
            '  </div>',
            '</div>'
        ].join("");

        drawer.querySelector(".shell-dismiss-btn").addEventListener("click", closeDrawer);

        document.body.appendChild(drawerBackdrop);
        document.body.appendChild(drawer);
    }

    function openDrawer() {
        drawerOpen = true;
        drawerBackdrop.classList.add("is-open");
        drawer.classList.add("is-open");
        drawer.setAttribute("aria-hidden", "false");
    }

    function closeDrawer() {
        drawerOpen = false;
        drawerBackdrop.classList.remove("is-open");
        drawer.classList.remove("is-open");
        drawer.setAttribute("aria-hidden", "true");
    }

    function createMenuButton() {
        var button = document.createElement("button");
        button.type = "button";
        button.className = "shell-menu-trigger shell-action-btn";
        button.setAttribute("aria-label", "باز کردن منو");
        button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5 12H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 17H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        button.addEventListener("click", function () {
            if (drawerOpen) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
        document.body.appendChild(button);
    }

    function createModal() {
        modalBackdrop = document.createElement("div");
        modalBackdrop.className = "shell-modal-backdrop";
        modalBackdrop.addEventListener("click", closeModal);

        modal = document.createElement("div");
        modal.className = "shell-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-hidden", "true");
        modal.innerHTML = [
            '<h2 class="shell-modal__title">باز کردن لینک بیرونی</h2>',
            '<p class="shell-modal__desc">این لینک بیرون از سایت باز می‌شود.</p>',
            '<div class="shell-modal__host"></div>',
            '<div class="shell-modal__actions">',
            '  <button type="button" class="shell-action-btn" data-shell-cancel>برگشت</button>',
            '  <button type="button" class="shell-action-btn shell-action-btn-primary" data-shell-continue>باز کردن</button>',
            '</div>'
        ].join("");

        modal.querySelector("[data-shell-cancel]").addEventListener("click", closeModal);
        modal.querySelector("[data-shell-continue]").addEventListener("click", continueExternal);

        document.body.appendChild(modalBackdrop);
        document.body.appendChild(modal);
    }

    function openExternalModal(anchor) {
        pendingExternal = {
            href: anchor.href,
            target: anchor.target
        };
        modal.querySelector(".shell-modal__host").textContent = new URL(anchor.href).host;
        modal.classList.add("is-open");
        modalBackdrop.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
        pendingExternal = null;
        modal.classList.remove("is-open");
        modalBackdrop.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
    }

    function continueExternal() {
        if (!pendingExternal) {
            closeModal();
            return;
        }

        var href = pendingExternal.href;
        var target = pendingExternal.target;
        closeModal();
        if (target === "_blank") {
            window.open(href, "_blank", "noopener");
            return;
        }
        window.location.href = href;
    }

    function shouldIntercept(anchor) {
        if (!anchor || anchor.dataset.bypassExternalWarning === "true") {
            return false;
        }

        var href = anchor.getAttribute("href");
        if (!href || href.charAt(0) === "#" || href.indexOf("javascript:") === 0 || href.indexOf("mailto:") === 0 || href.indexOf("tel:") === 0) {
            return false;
        }

        var url = new URL(anchor.href, window.location.origin);
        return url.origin !== window.location.origin;
    }

    function bindExternalLinks() {
        document.addEventListener("click", function (event) {
            var anchor = event.target.closest("a[href]");
            if (!shouldIntercept(anchor)) {
                return;
            }

            event.preventDefault();
            openExternalModal(anchor);
        });
    }

    function bindInstallButtons() {
        function updateButtons(detail) {
            var buttons = document.querySelectorAll("[data-install-app]");
            buttons.forEach(function (button) {
                if (detail.installed) {
                    button.hidden = true;
                    return;
                }

                button.hidden = false;
                button.disabled = !detail.canInstall && !detail.isIOS;
                button.textContent = detail.canInstall ? "نصب روی دستگاه" : (detail.isIOS ? "افزودن به صفحه اصلی" : "فعلاً در دسترس نیست");
            });

            var hints = document.querySelectorAll("[data-install-hint]");
            hints.forEach(function (hint) {
                if (detail.installed) {
                    hint.textContent = "می‌توانی از صفحه اصلی دستگاه بازش کنی.";
                } else if (detail.canInstall) {
                    hint.textContent = "اگر خواستی، با همین دکمه به صفحه اصلی دستگاه اضافه‌اش کن.";
                } else if (detail.isIOS) {
                    hint.textContent = "در Safari از منوی اشتراک‌گذاری، «افزودن به صفحه اصلی» را بزن.";
                } else {
                    hint.textContent = "اگر مرورگر پشتیبانی کند، این دکمه بعداً فعال می‌شود.";
                }
            });

            if (detail.isOffline) {
                setStatus("اینترنت قطع است", "offline");
            } else {
                setStatus("اتصال برقرار شد", "online");
            }
        }

        document.addEventListener("click", function (event) {
            var button = event.target.closest("[data-install-app]");
            if (!button) {
                return;
            }

            if (!window.Dent1402PWA) {
                return;
            }

            window.Dent1402PWA.promptInstall();
        });

        if (window.Dent1402PWA) {
            window.Dent1402PWA.onChange(updateButtons);
        }

        window.addEventListener("dent1402:pwa-state", function (event) {
            updateButtons(event.detail);
        });
    }

    function init() {
        document.body.classList.add("has-app-shell");
        createStatusPill();
        injectBottomNav();
        createDrawer();
        createMenuButton();
        createModal();
        bindExternalLinks();
        bindInstallButtons();

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                if (drawerOpen) {
                    closeDrawer();
                }
                closeModal();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
