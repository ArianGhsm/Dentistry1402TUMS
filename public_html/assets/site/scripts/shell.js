(function () {
    "use strict";

    var shellDisabled = !!(document.body && document.body.dataset.shell === "off");
    var shellHeaderDisabled = !!(document.body && document.body.dataset.shellHeader === "off");
    var modal = null;
    var modalBackdrop = null;
    var pendingExternal = null;
    var navInner = null;
    var navSignature = "";
    var authLinkSeeded = false;
    var pollNavState = {
        pending: false,
        count: 0,
        lastUserKey: "",
        lastFetchedAt: 0
    };
    var navBadgeState = {
        pending: false,
        chatCount: 0,
        notificationCount: 0,
        lastUserKey: "",
        lastFetchedAt: 0
    };
    var notificationBanner = {
        root: null,
        eyebrow: null,
        title: null,
        body: null,
        primary: null,
        dismiss: null
    };
    var notificationBannerState = {
        userKey: "",
        preview: null
    };
    var POLL_COUNT_TTL_MS = 45000;
    var NAV_BADGE_TTL_MS = 45000;
    var NOTIFICATION_BANNER_DISMISS_KEY = "dent1402-shell-notification-banner-dismissed";

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
    }

    function readSessionValue(key) {
        try {
            return window.sessionStorage ? String(window.sessionStorage.getItem(key) || "") : "";
        } catch (_error) {
            return "";
        }
    }

    function writeSessionValue(key, value) {
        try {
            if (!window.sessionStorage) {
                return;
            }
            if (value) {
                window.sessionStorage.setItem(key, String(value));
            } else {
                window.sessionStorage.removeItem(key);
            }
        } catch (_error) {
            // Ignore storage failures.
        }
    }

    function scopedPath(path, cohortKey) {
        var auth = authApi();
        if (auth && typeof auth.appendCohortQuery === "function") {
            return auth.appendCohortQuery(path, cohortKey);
        }
        return path;
    }

    function icon(name) {
        var icons = {
            home: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5L12 4L20 10.5V19A1 1 0 0 1 19 20H5A1 1 0 0 1 4 19V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 20V13.5H14.5V20" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            chat: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 18.5L3.8 20L4.7 16.6C3.6 15.3 3 13.7 3 12C3 7.58 7.03 4 12 4C16.97 4 21 7.58 21 12C21 16.42 16.97 20 12 20C10.2 20 8.53 19.53 7 18.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            forms: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 9H15.5M8.5 12.3H15.5M8.5 15.6H12.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            exam: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4.5H17A2 2 0 0 1 19 6.5V19.5L12 16.5L5 19.5V6.5A2 2 0 0 1 7 4.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 12.5H13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            grades: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 18.5V13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M19 18.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M3.5 19.5H20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            buy: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7.2A2.2 2.2 0 0 1 6.2 5h11.6A2.2 2.2 0 0 1 20 7.2v9.6a2.2 2.2 0 0 1-2.2 2.2H6.2A2.2 2.2 0 0 1 4 16.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4 9.4h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 14.2h3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14.7 14.2h1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            polls: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4.5V12L18.5 15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 20A8 8 0 1 1 20 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            account: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12.25A3.75 3.75 0 1 0 12 4.75A3.75 3.75 0 0 0 12 12.25Z" stroke="currentColor" stroke-width="1.8"/><path d="M5 19.25C5.93 16.74 8.48 15 12 15C15.52 15 18.07 16.74 19 19.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
        };

        return icons[name] || "";
    }

    function currentPath() {
        var path = window.location.pathname || "/";
        return path.endsWith("/") ? path : path + "/";
    }

    function isActive(item) {
        var path = currentPath();
        return item.active.some(function (prefix) {
            if (item.exact) {
                return path === prefix;
            }
            return path === prefix || path.indexOf(prefix) === 0;
        });
    }

    function useDynamicBranding() {
        return true;
    }

    function authState() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.getState === "function") {
            return window.Dent1402Auth.getState();
        }
        return {
            status: "logged-out",
            loggedIn: false,
            user: null,
            error: ""
        };
    }

    function authStatus(state) {
        return state && state.status ? state.status : "logged-out";
    }

    function isAuthTransitioning(status) {
        return status === "session-restoring" || status === "logging-in" || status === "logging-out";
    }

    function authLinkHref(isLoggedIn) {
        if (isLoggedIn) {
            return "/account/";
        }

        var returnTo = window.location.pathname + window.location.search + window.location.hash;
        return "/account/?returnTo=" + encodeURIComponent(returnTo);
    }

    function userKey(state) {
        return state && state.loggedIn && state.user && state.user.studentNumber
            ? String(state.user.studentNumber)
            : "";
    }

    function isProsthesisState(state) {
        return !!(state && state.user && state.user.isProsthesisStudent);
    }

    function brandName(state) {
        return isProsthesisState(state) ? "ورودی ۱۴۰۲ پروتز تهران" : "ورودی ۱۴۰۲ دندانپزشکی تهران";
    }

    function prosthesisRedirectTarget(path) {
        if (path === "/chat/") {
            return scopedPath("/chat/", "prosthesis-1402");
        }
        if (path === "/forms/") {
            return scopedPath("/forms/", "prosthesis-1402");
        }
        if (path === "/forms/fill/") {
            return scopedPath("/forms/fill/", "prosthesis-1402");
        }
        if (path === "/grades/") {
            return scopedPath("/grades/", "prosthesis-1402");
        }
        if (path === "/exams/") {
            return scopedPath("/exams/", "prosthesis-1402");
        }
        if (path === "/notes/") {
            return scopedPath("/notes/", "prosthesis-1402");
        }

        return "";
    }

    function navItems(state) {
        var status = authStatus(state);
        var isPending = isAuthTransitioning(status);
        var accountHref = isPending ? "/account/" : authLinkHref(state.loggedIn);
        var isProsthesis = isProsthesisState(state);
        var chatBadgeCount = state.loggedIn ? Math.max(0, Number(navBadgeState.chatCount || 0)) : 0;
        var accountBadgeCount = state.loggedIn ? Math.max(0, Number(navBadgeState.notificationCount || 0)) : 0;
        var items = [{
            href: "/app/",
            label: "خانه",
            icon: "home",
            active: ["/app/"],
            exact: true
        }];
        if (!isProsthesis) {
            items.push({
                href: "/chat/",
                label: "چت",
                icon: "chat",
                active: ["/chat/"],
                badgeCount: chatBadgeCount,
                badgeAriaLabel: "پیام خوانده‌نشده"
            });
            items.push({ href: "/exams/", label: "آزمون‌ها", icon: "exam", active: ["/exams/"] });
        } else {
            items.push({
                href: scopedPath("/chat/", "prosthesis-1402"),
                label: "چت",
                icon: "chat",
                active: ["/chat/"],
                badgeCount: chatBadgeCount,
                badgeAriaLabel: "پیام خوانده‌نشده"
            });
            items.push({ href: scopedPath("/exams/", "prosthesis-1402"), label: "آزمون‌ها", icon: "exam", active: ["/exams/"] });
        }
        items.push(
            {
                href: accountHref,
                label: state.loggedIn ? "حساب" : "ورود",
                icon: "account",
                active: ["/account/"],
                pending: isPending,
                badgeCount: accountBadgeCount,
                badgeAriaLabel: "اعلان خوانده‌نشده"
            }
        );
        return items;
    }

    function applyBranding(state) {
        if (!useDynamicBranding()) {
            return;
        }
        var brand = brandName(state);
        var pageTitle = document.title || "";
        if (pageTitle.indexOf("ورودی ۱۴۰۲ دندانپزشکی تهران") !== -1) {
            document.title = pageTitle.replace(/ورودی ۱۴۰۲ دندانپزشکی تهران/g, brand);
        } else if (pageTitle.indexOf("ورودی ۱۴۰۲ دندانپزشکی") !== -1) {
            document.title = pageTitle.replace(/ورودی ۱۴۰۲ دندانپزشکی/g, isProsthesisState(state) ? "ورودی ۱۴۰۲ پروتز" : "ورودی ۱۴۰۲ دندانپزشکی");
        }

        document.querySelectorAll(".site-header .site-info h1, .site-footer p").forEach(function (node) {
            if (node) {
                node.textContent = brand;
            }
        });
    }

    function maybeRedirectProsthesis(state) {
        if (!isProsthesisState(state)) {
            return false;
        }

        var path = currentPath();
        var target = prosthesisRedirectTarget(path);
        var current = path + (window.location.search || "") + (window.location.hash || "");
        if (!target || target === current) {
            return false;
        }

        window.location.replace(target + (window.location.hash || ""));
        return true;
    }

    function ensureBottomNav() {
        if (shellDisabled || navInner) {
            return;
        }

        var nav = document.createElement("nav");
        nav.className = "shell-bottom-nav";
        nav.setAttribute("aria-label", "ناوبری پایین");

        navInner = document.createElement("div");
        navInner.className = "shell-bottom-nav__inner";

        nav.appendChild(navInner);
        document.body.appendChild(nav);
    }

    function renderBottomNav(state) {
        if (shellDisabled) {
            return;
        }

        ensureBottomNav();
        var items = navItems(state);
        var signature = JSON.stringify({
            status: authStatus(state),
            path: currentPath(),
            items: items.map(function (item) {
                return {
                    href: item.href,
                    label: item.label,
                    icon: item.icon,
                    active: !!isActive(item),
                    pending: !!item.pending,
                    badgeCount: Number(item.badgeCount || 0)
                };
            })
        });
        if (signature === navSignature) {
            return;
        }
        navSignature = signature;
        navInner.textContent = "";
        navInner.style.setProperty("--nav-count", String(items.length));
        navInner.dataset.authStatus = authStatus(state);

        var fragment = document.createDocumentFragment();

        items.forEach(function (item) {
            var link = document.createElement("a");
            link.className = "shell-bottom-nav__link";
            if (item.pending) {
                link.classList.add("is-pending");
            }
            if (isActive(item)) {
                link.classList.add("is-active");
                link.setAttribute("aria-current", "page");
            }
            link.href = item.href;

            var iconHtml = '<span class="shell-bottom-nav__icon" aria-hidden="true">' + icon(item.icon);
            if (item.badgeCount) {
                var countText = item.badgeCount > 9 ? "۹+" : String(item.badgeCount).replace(/\d/g, function (digit) {
                    return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
                });
                var badgeLabel = item.badgeAriaLabel || (item.label + " جدید");
                iconHtml += '<span class="shell-bottom-nav__badge" aria-label="' + badgeLabel + '">' + countText + "</span>";
            }
            iconHtml += "</span>";

            link.innerHTML = iconHtml + '<span class="shell-bottom-nav__label">' + item.label + "</span>";
            fragment.appendChild(link);
        });
        navInner.appendChild(fragment);
    }

    function themeIconMarkup(targetTheme) {
        if (targetTheme === "dark") {
            return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A7.5 7.5 0 0 1 9.5 4A8.5 8.5 0 1 0 20 14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }

        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.5 12H18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5.5 12H3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 5.99L16.59 7.41" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 16.59L5.99 18.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 18.01L16.59 16.59" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 7.41L5.99 5.99" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3.6" stroke="currentColor" stroke-width="1.8"/></svg>';
    }

    function syncShellThemeButton(button) {
        if (!button || !window.Dent1402Theme || typeof window.Dent1402Theme.getState !== "function") {
            return;
        }

        var theme = window.Dent1402Theme.getState().theme;
        var nextTheme = theme === "dark" ? "light" : "dark";
        var label = nextTheme === "dark" ? "تم تیره" : "تم روشن";
        button.innerHTML = '<span class="theme-toggle-btn__icon">' + themeIconMarkup(nextTheme) + "</span>";
        button.dataset.themeTarget = nextTheme;
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
    }

    function ensureMinimalThemeButton(actions) {
        if (!actions || !window.Dent1402Theme || typeof window.Dent1402Theme.toggle !== "function") {
            return;
        }

        var button = actions.querySelector("[data-theme-toggle]");
        if (button) {
            return;
        }

        button = document.createElement("button");
        button.type = "button";
        button.className = "theme-toggle-btn";
        button.dataset.themeToggle = "true";
        button.addEventListener("click", function () {
            window.Dent1402Theme.toggle();
        });
        window.addEventListener("dent1402:theme-change", function () {
            syncShellThemeButton(button);
        });
        actions.appendChild(button);
        syncShellThemeButton(button);
    }

    function createMinimalSiteHeader() {
        if (document.querySelector(".site-header") || shellDisabled || shellHeaderDisabled) {
            return;
        }

        if (document.body.classList.contains("chat-page")) {
            return;
        }

        var header = document.createElement("header");
        header.className = "site-header";
        header.innerHTML = [
            '<div class="logo-area">',
            '  <div class="site-info"><h1>ورودی ۱۴۰۲ دندانپزشکی تهران</h1></div>',
            "</div>",
            '<div class="header-actions"></div>'
        ].join("");

        var overlay = document.querySelector(".background-overlay");
        if (overlay && overlay.parentNode === document.body && overlay.nextSibling) {
            document.body.insertBefore(header, overlay.nextSibling);
            return;
        }

        document.body.insertBefore(header, document.body.firstChild);
    }

    function normalizeSiteHeader() {
        if (shellHeaderDisabled) {
            return;
        }
        createMinimalSiteHeader();

        document.querySelectorAll(".site-header").forEach(function (header) {
            header.classList.add("site-header--minimal");

            var logoArea = header.querySelector(".logo-area");
            if (!logoArea) {
                logoArea = document.createElement("div");
                logoArea.className = "logo-area";
                header.insertBefore(logoArea, header.firstChild);
            }

            logoArea.querySelectorAll(".logo-circle, .badge-unofficial").forEach(function (node) {
                node.remove();
            });

            var siteInfo = logoArea.querySelector(".site-info");
            if (!siteInfo) {
                siteInfo = document.createElement("div");
                siteInfo.className = "site-info";
                logoArea.appendChild(siteInfo);
            }

            var title = siteInfo.querySelector("h1");
            if (!title) {
                title = document.createElement("h1");
                siteInfo.insertBefore(title, siteInfo.firstChild);
            }
            if (useDynamicBranding()) {
                title.textContent = brandName(authState());
            }

            siteInfo.querySelectorAll("p").forEach(function (node) {
                node.remove();
            });

            header.querySelectorAll(".site-top-nav, .header-link, [data-auth-link], .badge-unofficial").forEach(function (node) {
                node.remove();
            });

            var actions = header.querySelector(".header-actions");
            if (!actions) {
                actions = document.createElement("div");
                actions.className = "header-actions";
                header.appendChild(actions);
            }

            Array.prototype.slice.call(actions.children).forEach(function (node) {
                if (!node.matches("[data-theme-toggle]")) {
                    node.remove();
                }
            });

            ensureMinimalThemeButton(actions);
        });
    }

    function normalizePageTopbars() {
        document.querySelectorAll(".forms-topbar").forEach(function (topbar) {
            topbar.querySelectorAll('a[href="/app/"], a[href="/account/"]').forEach(function (node) {
                node.remove();
            });

            if (!topbar.querySelector(".forms-icon-btn")) {
                topbar.classList.add("forms-topbar--plain");
            }
        });
    }

    function ensureHeaderAuthLink() {
        authLinkSeeded = true;
    }

    function syncAuthLinks(state) {
        ensureHeaderAuthLink();
        document.querySelectorAll("[data-auth-link]").forEach(function (link) {
            var outLabel = link.dataset.authOutLabel || "ورود";
            var inLabel = link.dataset.authInLabel || "حساب کاربری";
            var useName = link.dataset.authUseName === "true";
            var text = state.loggedIn
                ? (useName && state.user && state.user.name ? state.user.name : inLabel)
                : outLabel;
            var status = authStatus(state);
            var pending = isAuthTransitioning(status);
            link.href = pending ? "/account/" : (state.loggedIn ? "/account/" : authLinkHref(false));
            link.textContent = text;
            link.setAttribute("title", text);
            link.setAttribute("aria-label", text);
            link.classList.toggle("is-authenticated", !!state.loggedIn);
            link.classList.toggle("is-auth-pending", pending);
        });
    }

    function parseJsonResponse(response) {
        return response.json().catch(function () {
            return {
                success: false,
                error: "پاسخ نامعتبر از سرور دریافت شد."
            };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function notificationPreviewKey(key, preview) {
        var id = preview && preview.id ? String(preview.id) : "";
        return key && id ? (key + ":" + id) : "";
    }

    function notificationBannerDismissedKey() {
        return readSessionValue(NOTIFICATION_BANNER_DISMISS_KEY);
    }

    function notificationBannerDefaultHref(preview) {
        if (preview && preview.kind === "navid-assignment") {
            return "/navid/";
        }
        return "/account/#notifications";
    }

    function notificationBannerDefaultLabel(preview) {
        if (preview && preview.kind === "navid-assignment") {
            return "مشاهده تکالیف";
        }
        return "مشاهده اعلان";
    }

    function notificationBannerKindLabel(preview) {
        return preview && preview.kind === "navid-assignment" ? "تکلیف جدید نوید" : "اعلان جدید";
    }

    function ensureNotificationBanner() {
        if (notificationBanner.root || !document.body) {
            return notificationBanner.root;
        }

        var banner = document.createElement("section");
        banner.className = "shell-notification-banner";
        banner.hidden = true;
        banner.setAttribute("aria-live", "polite");
        banner.innerHTML = [
            '<div class="shell-notification-banner__copy">',
            '  <span class="shell-notification-banner__eyebrow"></span>',
            '  <strong class="shell-notification-banner__title"></strong>',
            '  <p class="shell-notification-banner__body"></p>',
            "</div>",
            '<div class="shell-notification-banner__actions">',
            '  <button type="button" class="shell-action-btn shell-notification-banner__dismiss">بعداً</button>',
            '  <a class="shell-action-btn shell-action-btn-primary shell-notification-banner__primary" href="/account/#notifications">مشاهده اعلان</a>',
            "</div>"
        ].join("");

        document.body.appendChild(banner);
        notificationBanner.root = banner;
        notificationBanner.eyebrow = banner.querySelector(".shell-notification-banner__eyebrow");
        notificationBanner.title = banner.querySelector(".shell-notification-banner__title");
        notificationBanner.body = banner.querySelector(".shell-notification-banner__body");
        notificationBanner.primary = banner.querySelector(".shell-notification-banner__primary");
        notificationBanner.dismiss = banner.querySelector(".shell-notification-banner__dismiss");

        if (notificationBanner.dismiss) {
            notificationBanner.dismiss.addEventListener("click", function () {
                var key = notificationPreviewKey(notificationBannerState.userKey, notificationBannerState.preview);
                writeSessionValue(NOTIFICATION_BANNER_DISMISS_KEY, key);
                renderNotificationBanner(authState());
            });
        }

        if (notificationBanner.primary) {
            notificationBanner.primary.addEventListener("click", function (event) {
                var href = notificationBanner.primary.getAttribute("href") || "/account/#notifications";
                var notificationId = notificationBanner.primary.dataset.notificationId || "";
                var dismissKey = notificationPreviewKey(notificationBannerState.userKey, notificationBannerState.preview);
                event.preventDefault();
                writeSessionValue(NOTIFICATION_BANNER_DISMISS_KEY, dismissKey);
                markNotificationReadFromShell(notificationId).finally(function () {
                    window.location.href = href;
                });
            });
        }

        return notificationBanner.root;
    }

    function renderNotificationBanner(state) {
        var banner = ensureNotificationBanner();
        if (!banner) {
            return;
        }

        var preview = notificationBannerState.preview;
        var key = notificationPreviewKey(notificationBannerState.userKey || userKey(state), preview);
        var dismissedKey = notificationBannerDismissedKey();
        var isNotificationsSurfaceOpen = currentPath() === "/account/" && (window.location.hash || "") === "#notifications";
        var visible = !!(state && state.loggedIn && preview && key && dismissedKey !== key && !isNotificationsSurfaceOpen);

        banner.hidden = !visible;
        banner.classList.toggle("is-visible", visible);
        if (!visible) {
            return;
        }

        if (notificationBanner.eyebrow) {
            notificationBanner.eyebrow.textContent = notificationBannerKindLabel(preview);
        }
        if (notificationBanner.title) {
            notificationBanner.title.textContent = String(preview.title || (preview.kind === "navid-assignment" ? "تکلیف جدید نوید" : "اعلان جدید"));
        }
        if (notificationBanner.body) {
            notificationBanner.body.textContent = String(preview.body || (preview.kind === "navid-assignment"
                ? "برای دیدن جزئیات، بخش تکالیف نوید را باز کن."
                : "برای دیدن جزئیات، اعلان را باز کن."));
        }
        if (notificationBanner.primary) {
            notificationBanner.primary.textContent = String(preview.ctaLabel || notificationBannerDefaultLabel(preview));
            notificationBanner.primary.href = String(preview.ctaHref || notificationBannerDefaultHref(preview));
            notificationBanner.primary.dataset.notificationId = String(preview.id || "");
        }
    }

    function setNotificationBannerPreview(preview, state) {
        notificationBannerState.userKey = userKey(state);
        notificationBannerState.preview = preview && typeof preview === "object" ? preview : null;
        renderNotificationBanner(state);
    }

    function markNotificationReadFromShell(id) {
        var notificationId = String(id || "").trim();
        if (!notificationId) {
            return Promise.resolve(null);
        }

        return window.fetch("/api/notifications_api.php?action=markRead", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams({
                idsJson: JSON.stringify([notificationId])
            })
        }).then(parseJsonResponse).then(function (payload) {
            var state = authState();
            if (consumeUnauthorized(payload, "نشست شما برای خواندن اعلان‌ها منقضی شده است.")) {
                resetNavBadgeState();
                setNotificationBannerPreview(null, state);
                renderBottomNav(state);
                return null;
            }
            if (!payload || payload.success !== true) {
                return null;
            }
            updateNavBadgeState(navBadgeState.chatCount, payload.summary && payload.summary.unreadCount, userKey(state));
            setNotificationBannerPreview(payload.preview || null, state);
            renderBottomNav(state);
            return payload;
        }).catch(function () {
            return null;
        });
    }

    function consumeUnauthorized(payload, fallbackText) {
        var auth = window.Dent1402Auth;
        var message = fallbackText || "نشست شما منقضی شده است.";
        if (!auth || typeof auth !== "object") {
            return false;
        }

        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(payload, message);
            }
        } catch (_error) {
            // Ignore and continue fallback.
        }

        if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((payload && payload.error) || message);
            }
            return true;
        }

        return false;
    }

    function shouldRefetchPollCount(state) {
        if (!state.loggedIn) {
            return false;
        }

        var key = userKey(state);
        var now = Date.now();
        if (key !== pollNavState.lastUserKey) {
            return true;
        }
        return (now - pollNavState.lastFetchedAt) > POLL_COUNT_TTL_MS;
    }

    function updatePollCountState(count, key) {
        pollNavState.count = Math.max(0, Number(count) || 0);
        pollNavState.lastFetchedAt = Date.now();
        pollNavState.lastUserKey = key || "";
    }

    function resetPollCountState() {
        pollNavState.pending = false;
        pollNavState.count = 0;
        pollNavState.lastFetchedAt = 0;
        pollNavState.lastUserKey = "";
    }

    function shouldRefetchNavBadges(state) {
        if (!state.loggedIn) {
            return false;
        }

        var key = userKey(state);
        var now = Date.now();
        if (key !== navBadgeState.lastUserKey) {
            return true;
        }
        return (now - navBadgeState.lastFetchedAt) > NAV_BADGE_TTL_MS;
    }

    function updateNavBadgeState(chatCount, notificationCount, key) {
        navBadgeState.chatCount = Math.max(0, Number(chatCount) || 0);
        navBadgeState.notificationCount = Math.max(0, Number(notificationCount) || 0);
        navBadgeState.lastFetchedAt = Date.now();
        navBadgeState.lastUserKey = key || "";
    }

    function resetNavBadgeState() {
        navBadgeState.pending = false;
        navBadgeState.chatCount = 0;
        navBadgeState.notificationCount = 0;
        navBadgeState.lastFetchedAt = 0;
        navBadgeState.lastUserKey = "";
        notificationBannerState.userKey = "";
        notificationBannerState.preview = null;
    }

    function applyNavBadgeEvent(kind, count) {
        var state = authState();
        if (!state.loggedIn) {
            resetNavBadgeState();
            renderBottomNav(state);
            renderNotificationBanner(state);
            return;
        }

        var key = userKey(state);
        if (key !== navBadgeState.lastUserKey) {
            navBadgeState.chatCount = 0;
            navBadgeState.notificationCount = 0;
        }
        navBadgeState.lastUserKey = key;
        navBadgeState.lastFetchedAt = Date.now();
        if (kind === "chat") {
            navBadgeState.chatCount = Math.max(0, Number(count) || 0);
        } else if (kind === "notifications") {
            navBadgeState.notificationCount = Math.max(0, Number(count) || 0);
        }
        renderBottomNav(state);
        renderNotificationBanner(state);
    }

    function fetchNavBadgeSummary(state) {
        if (!state.loggedIn || navBadgeState.pending) {
            return;
        }

        var key = userKey(state);
        if (key !== navBadgeState.lastUserKey) {
            navBadgeState.chatCount = 0;
            navBadgeState.notificationCount = 0;
            navBadgeState.lastUserKey = key;
            setNotificationBannerPreview(null, state);
            renderBottomNav(state);
        }

        navBadgeState.pending = true;
        Promise.all([
            window.fetch("/api/notifications_api.php?action=summary", {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            }).then(parseJsonResponse),
            window.fetch("/chat/chat_api.php?action=navSummary", {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            }).then(parseJsonResponse)
        ]).then(function (results) {
            var notificationsPayload = results[0] || {};
            var chatPayload = results[1] || {};
            if (
                consumeUnauthorized(notificationsPayload, "نشست شما برای خواندن اعلان‌ها منقضی شده است.")
                || consumeUnauthorized(chatPayload, "نشست شما برای خواندن پیام‌ها منقضی شده است.")
            ) {
                resetNavBadgeState();
                renderBottomNav(authState());
                renderNotificationBanner(authState());
                return;
            }

            if (!notificationsPayload.success || !chatPayload.success) {
                return;
            }

            updateNavBadgeState(
                chatPayload.summary && chatPayload.summary.unreadCount,
                notificationsPayload.summary && notificationsPayload.summary.unreadCount,
                key
            );
            setNotificationBannerPreview(notificationsPayload.preview || null, authState());
            renderBottomNav(authState());
        }).catch(function () {
            // Keep the last known counts on transient failures.
        }).finally(function () {
            navBadgeState.pending = false;
        });
    }

    function syncPollEntry(state) {
        resetPollCountState();
        if (!state.loggedIn) {
            resetNavBadgeState();
            if (!shellDisabled) {
                renderBottomNav(state);
            }
            renderNotificationBanner(state);
            return;
        }
        if (!shellDisabled) {
            renderBottomNav(state);
        }
        renderNotificationBanner(state);
        if (shouldRefetchNavBadges(state)) {
            fetchNavBadgeSummary(state);
        }
    }

    function createModal() {
        if (modal && modalBackdrop) {
            return;
        }

        modalBackdrop = document.createElement("div");
        modalBackdrop.className = "shell-modal-backdrop";
        modalBackdrop.style.position = "fixed";
        modalBackdrop.style.inset = "0";
        modalBackdrop.style.zIndex = "340";
        modalBackdrop.style.opacity = "0";
        modalBackdrop.style.pointerEvents = "none";
        modalBackdrop.addEventListener("click", closeModal);

        modal = document.createElement("div");
        modal.className = "shell-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-hidden", "true");
        modal.style.position = "fixed";
        modal.style.top = "50%";
        modal.style.left = "50%";
        modal.style.right = "auto";
        modal.style.bottom = "auto";
        modal.style.zIndex = "350";
        modal.style.width = "min(420px, calc(100vw - 2rem))";
        modal.style.maxHeight = "calc(100dvh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 2rem)";
        modal.style.transform = "translate(-50%, calc(-50% + 16px)) scale(0.98)";
        modal.style.opacity = "0";
        modal.style.pointerEvents = "none";
        modal.style.overflowY = "auto";
        modal.style.webkitOverflowScrolling = "touch";
        modal.innerHTML = [
            '<h2 class="shell-modal__title">خروج از سایت</h2>',
            '<p class="shell-modal__desc">این لینک خارج از سایت باز می‌شود.</p>',
            '<div class="shell-modal__host"></div>',
            '<div class="shell-modal__actions">',
            '  <button type="button" class="shell-action-btn" data-shell-cancel>لغو</button>',
            '  <button type="button" class="shell-action-btn shell-action-btn-primary" data-shell-continue>ادامه</button>',
            "</div>"
        ].join("");

        modal.querySelector("[data-shell-cancel]").addEventListener("click", closeModal);
        modal.querySelector("[data-shell-continue]").addEventListener("click", continueExternal);

        document.body.appendChild(modalBackdrop);
        document.body.appendChild(modal);
    }

    function openExternalModal(anchor) {
        createModal();
        pendingExternal = {
            href: anchor.href,
            target: anchor.target
        };

        modal.querySelector(".shell-modal__host").textContent = new URL(anchor.href).host;
        modal.classList.add("is-open");
        modalBackdrop.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        modal.style.opacity = "1";
        modal.style.pointerEvents = "auto";
        modal.style.transform = "translate(-50%, -50%) scale(1)";
        modalBackdrop.style.opacity = "1";
        modalBackdrop.style.pointerEvents = "auto";
    }

    function closeModal() {
        pendingExternal = null;
        if (!modal || !modalBackdrop) {
            return;
        }

        modal.classList.remove("is-open");
        modalBackdrop.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        modal.style.opacity = "0";
        modal.style.pointerEvents = "none";
        modal.style.transform = "translate(-50%, calc(-50% + 16px)) scale(0.98)";
        modalBackdrop.style.opacity = "0";
        modalBackdrop.style.pointerEvents = "none";
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
        var installPromptDismissed = false;

        function currentPwaState() {
            if (window.Dent1402PWA && typeof window.Dent1402PWA.getState === "function") {
                return window.Dent1402PWA.getState();
            }
            return {
                installed: false,
                canInstall: false,
                isIOS: false
            };
        }

        function shouldShowInstallCard(detail) {
            return !!detail && !detail.installed && !installPromptDismissed && (!!detail.canInstall || !!detail.isIOS);
        }

        function updateInstallCards(detail) {
            var visible = shouldShowInstallCard(detail);
            document.querySelectorAll("[data-install-card]").forEach(function (card) {
                card.hidden = !visible;
                card.classList.toggle("is-visible", visible);
            });
        }

        function updateButtons(detail) {
            detail = detail || currentPwaState();
            updateInstallCards(detail);

            var buttons = document.querySelectorAll("[data-install-app]");
            buttons.forEach(function (button) {
                if (detail.installed) {
                    button.hidden = true;
                    return;
                }

                button.hidden = false;
                button.disabled = !detail.canInstall && !detail.isIOS;
                button.textContent = detail.canInstall ? "نصب روی گوشی" : (detail.isIOS ? "راهنمای نصب iOS" : "مرورگر پشتیبانی نمی‌کند");
            });

            var hints = document.querySelectorAll("[data-install-hint]");
            hints.forEach(function (hint) {
                if (detail.installed) {
                    hint.textContent = "نسخه نصب‌شده روی دستگاه فعال است.";
                } else if (detail.canInstall) {
                    hint.textContent = "برای نصب سریع، روی دکمه نصب بزن.";
                } else if (detail.isIOS) {
                    hint.textContent = "در Safari از گزینه اشتراک‌گذاری «Add to Home Screen» استفاده کن.";
                } else {
                    hint.textContent = "این مرورگر در حال حاضر نصب وب‌اپ را پشتیبانی نمی‌کند.";
                }
            });
        }

        document.addEventListener("click", function (event) {
            var dismissButton = event.target.closest("[data-install-dismiss]");
            if (dismissButton) {
                installPromptDismissed = true;
                updateButtons(currentPwaState());
                return;
            }

            var button = event.target.closest("[data-install-app]");
            if (!button || !window.Dent1402PWA) {
                return;
            }

            var detail = currentPwaState();
            window.Dent1402PWA.promptInstall().then(function () {
                if (detail.canInstall) {
                    installPromptDismissed = true;
                    updateButtons(currentPwaState());
                }
            });
        });

        if (window.Dent1402PWA) {
            window.Dent1402PWA.onChange(updateButtons);
        }

        window.addEventListener("dent1402:pwa-state", function (event) {
            updateButtons(event.detail);
        });
    }

    function syncAuthUi(state) {
        if (maybeRedirectProsthesis(state)) {
            return;
        }
        applyBranding(state);
        syncAuthLinks(state);
        syncPollEntry(state);
    }

    function init() {
        document.body.classList.add("has-app-shell");
        if (shellDisabled) {
            document.body.classList.add("app-shell-hidden");
        }

        bindExternalLinks();
        bindInstallButtons();
        normalizeSiteHeader();
        normalizePageTopbars();
        syncAuthUi(authState());

        if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
            window.Dent1402Auth.onChange(syncAuthUi);
        }

        window.addEventListener("dent1402:notifications-change", function (event) {
            var detail = event && event.detail ? event.detail : {};
            applyNavBadgeEvent("notifications", detail.unreadCount);
            if (Object.prototype.hasOwnProperty.call(detail, "preview")) {
                setNotificationBannerPreview(detail.preview || null, authState());
            }
        });
        window.addEventListener("dent1402:chat-unread-change", function (event) {
            var detail = event && event.detail ? event.detail : {};
            applyNavBadgeEvent("chat", detail.unreadCount);
        });
        window.addEventListener("focus", function () {
            syncPollEntry(authState());
        });
        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) {
                syncPollEntry(authState());
            }
        });
        window.setInterval(function () {
            syncPollEntry(authState());
        }, NAV_BADGE_TTL_MS);

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
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
