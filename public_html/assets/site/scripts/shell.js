(function () {
    "use strict";

    var shellDisabled = !!(document.body && document.body.dataset.shell === "off");
    var modal = null;
    var modalBackdrop = null;
    var pendingExternal = null;
    var navInner = null;
    var authLinkSeeded = false;

    function icon(name) {
        var icons = {
            home: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5L12 4L20 10.5V19A1 1 0 0 1 19 20H5A1 1 0 0 1 4 19V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 20V13.5H14.5V20" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            chat: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 18.5L3.8 20L4.7 16.6C3.6 15.3 3 13.7 3 12C3 7.58 7.03 4 12 4C16.97 4 21 7.58 21 12C21 16.42 16.97 20 12 20C10.2 20 8.53 19.53 7 18.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            exam: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4.5H17A2 2 0 0 1 19 6.5V19.5L12 16.5L5 19.5V6.5A2 2 0 0 1 7 4.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 12.5H13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            grades: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 18.5V13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M19 18.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M3.5 19.5H20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            resources: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5H16.5A2.5 2.5 0 0 1 19 7.5V16.5A2.5 2.5 0 0 1 16.5 19H7.5A2.5 2.5 0 0 1 5 16.5V7.5Z" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 9H15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8.5 12H15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8.5 15H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
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
            return path === prefix || path.indexOf(prefix) === 0;
        });
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

    function authLinkHref(isLoggedIn) {
        if (isLoggedIn) {
            return "/account/";
        }

        var returnTo = window.location.pathname + window.location.search + window.location.hash;
        return "/account/?returnTo=" + encodeURIComponent(returnTo);
    }

    function navItems(state) {
        return [
            { href: "/app/", label: "????", icon: "home", active: ["/app/"] },
            { href: "/chat/", label: "??", icon: "chat", active: ["/chat/"] },
            { href: "/exams/", label: "?????", icon: "exam", active: ["/exams/"] },
            { href: "/grades/", label: "?????", icon: "grades", active: ["/grades/"] },
            { href: "/resources/", label: "?????", icon: "resources", active: ["/resources/", "/notes/"] },
            {
                href: authLinkHref(state.loggedIn),
                label: state.loggedIn ? "????" : "????",
                icon: "account",
                active: ["/account/"]
            }
        ];
    }

    function ensureBottomNav() {
        if (shellDisabled || navInner) {
            return;
        }

        var nav = document.createElement("nav");
        nav.className = "shell-bottom-nav";
        nav.setAttribute("aria-label", "?????? ????");

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
        navInner.style.setProperty("--nav-count", String(navItems(state).length));

        navItems(state).forEach(function (item) {
            var link = document.createElement("a");
            link.className = "shell-bottom-nav__link";
            if (isActive(item)) {
                link.classList.add("is-active");
                link.setAttribute("aria-current", "page");
            }
            link.href = item.href;
            link.innerHTML = '<span class="shell-bottom-nav__icon" aria-hidden="true">' + icon(item.icon) + '</span><span class="shell-bottom-nav__label">' + item.label + "</span>";
            navInner.appendChild(link);
        });
    }

    function ensureHeaderAuthLink() {
        if (authLinkSeeded) {
            return;
        }

        var headerActions = document.querySelector(".header-actions");
        if (!headerActions) {
            return;
        }

        if (headerActions.querySelector("[data-auth-link]")) {
            authLinkSeeded = true;
            return;
        }

        var link = document.createElement("a");
        link.className = "header-link header-link--auth";
        link.href = "/account/";
        link.dataset.authLink = "true";
        link.dataset.authInLabel = "???? ??????";
        link.dataset.authOutLabel = "????";
        headerActions.appendChild(link);
        authLinkSeeded = true;
    }

    function syncAuthLinks(state) {
        ensureHeaderAuthLink();

        document.querySelectorAll("[data-auth-link]").forEach(function (link) {
            var outLabel = link.dataset.authOutLabel || "????";
            var inLabel = link.dataset.authInLabel || "???? ??????";
            var useName = link.dataset.authUseName === "true";
            var text = state.loggedIn
                ? (useName && state.user && state.user.name ? state.user.name : inLabel)
                : outLabel;

            link.href = state.loggedIn ? "/account/" : authLinkHref(false);
            link.textContent = text;
            link.setAttribute("title", text);
            link.setAttribute("aria-label", text);
            link.classList.toggle("is-authenticated", !!state.loggedIn);
        });
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
            '<h2 class="shell-modal__title">???? ?? ????</h2>',
            '<p class="shell-modal__desc">??? ???? ????? ?? ???? ??? ??????.</p>',
            '<div class="shell-modal__host"></div>',
            '<div class="shell-modal__actions">',
            '  <button type="button" class="shell-action-btn" data-shell-cancel>?????</button>',
            '  <button type="button" class="shell-action-btn shell-action-btn-primary" data-shell-continue>??? ????</button>',
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
        function updateButtons(detail) {
            var buttons = document.querySelectorAll("[data-install-app]");
            buttons.forEach(function (button) {
                if (detail.installed) {
                    button.hidden = true;
                    return;
                }

                button.hidden = false;
                button.disabled = !detail.canInstall && !detail.isIOS;
                button.textContent = detail.canInstall ? "??? ??? ????" : (detail.isIOS ? "?????? ?? ???? ????" : "????? ?? ????? ????");
            });

            var hints = document.querySelectorAll("[data-install-hint]");
            hints.forEach(function (hint) {
                if (detail.installed) {
                    hint.textContent = "????? ?? ???? ???? ?????? ???? ??.";
                } else if (detail.canInstall) {
                    hint.textContent = "??? ??????? ?? ???? ???? ??? ???? ???? ???.";
                } else if (detail.isIOS) {
                    hint.textContent = "?? Safari ?? ???? ????????????? �?????? ?? ???? ????� ?? ???.";
                } else {
                    hint.textContent = "??? ?????? ???????? ???? ??? ???? ???? ??????.";
                }
            });
        }

        document.addEventListener("click", function (event) {
            var button = event.target.closest("[data-install-app]");
            if (!button || !window.Dent1402PWA) {
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

    function syncAuthUi(state) {
        syncAuthLinks(state);
        renderBottomNav(state);
    }

    function init() {
        document.body.classList.add("has-app-shell");
        if (shellDisabled) {
            document.body.classList.add("app-shell-hidden");
        }

        createModal();
        bindExternalLinks();
        bindInstallButtons();
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
