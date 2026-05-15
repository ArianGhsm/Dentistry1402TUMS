(function () {
    "use strict";

    var shellDisabled = !!(document.body && document.body.dataset.shell === "off");
    var modal = null;
    var modalBackdrop = null;
    var pendingExternal = null;
    var navInner = null;
    var authLinkSeeded = false;
    var pollNavState = {
        pending: false,
        count: 0,
        lastUserKey: "",
        lastFetchedAt: 0
    };
    var POLL_COUNT_TTL_MS = 45000;

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
        var path = currentPath();
        return path.indexOf("/prosthesis-1402/") !== 0 && path.indexOf("/dental-residency/") !== 0;
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
        if (path.indexOf("/prosthesis-1402/") === 0 || path.indexOf("/dental-residency/") === 0) {
            return "";
        }

        if (path === "/chat/") {
            return "/prosthesis-1402/chat/";
        }
        if (path === "/forms/") {
            return "/prosthesis-1402/forms/";
        }
        if (path === "/forms/fill/") {
            return "/prosthesis-1402/forms/fill/";
        }
        if (path === "/grades/") {
            return "/prosthesis-1402/grades/";
        }
        if (path === "/exams/") {
            return "/prosthesis-1402/exams/";
        }
        if (path === "/notes/") {
            return "/prosthesis-1402/";
        }

        return "";
    }

    function navItems(state) {
        var status = authStatus(state);
        var isPending = isAuthTransitioning(status);
        var accountHref = isPending ? "/account/" : authLinkHref(state.loggedIn);
        var isProsthesis = isProsthesisState(state);
        var items = [{
            href: "/app/",
            label: "خانه",
            icon: "home",
            active: ["/app/"],
            exact: true
        }];
        if (!isProsthesis) {
            items.push({ href: "/chat/", label: "چت", icon: "chat", active: ["/chat/"] });
            items.push({ href: "/buy/", label: "خرید", icon: "buy", active: ["/buy/", "/payments/"] });
        } else {
            items.push({ href: "/prosthesis-1402/chat/", label: "چت", icon: "chat", active: ["/prosthesis-1402/chat/"] });
        }
        items.push(
            {
                href: accountHref,
                label: state.loggedIn ? "حساب" : "ورود",
                icon: "account",
                active: ["/account/"],
                pending: isPending
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
        if (!target || target === path) {
            return false;
        }

        window.location.replace(target + window.location.search + window.location.hash);
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
        navInner.innerHTML = "";
        var items = navItems(state);
        navInner.style.setProperty("--nav-count", String(items.length));
        navInner.dataset.authStatus = authStatus(state);

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
                iconHtml += '<span class="shell-bottom-nav__badge" aria-label="نظرسنجی فعال">' + countText + "</span>";
            }
            iconHtml += "</span>";

            link.innerHTML = iconHtml + '<span class="shell-bottom-nav__label">' + item.label + "</span>";
            navInner.appendChild(link);
        });
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
        if (document.querySelector(".site-header") || shellDisabled) {
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

    function syncPollEntry(state) {
        if (shellDisabled) {
            return;
        }

        resetPollCountState();
        renderBottomNav(state);
    }

    function createModal() {
        if (modal && modalBackdrop) {
            return;
        }

        modalBackdrop = document.createElement("div");
        modalBackdrop.className = "shell-modal-backdrop";
        modalBackdrop.addEventListener("click", closeModal);

        modal = document.createElement("div");
        modal.className = "shell-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-hidden", "true");
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
        if (!modal || !modalBackdrop) {
            return;
        }

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
        renderBottomNav(state);
        syncPollEntry(state);
    }

    function init() {
        document.body.classList.add("has-app-shell");
        if (shellDisabled) {
            document.body.classList.add("app-shell-hidden");
        }

        createModal();
        bindExternalLinks();
        bindInstallButtons();
        normalizeSiteHeader();
        normalizePageTopbars();
        syncAuthUi(authState());

        if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
            window.Dent1402Auth.onChange(syncAuthUi);
        }

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
