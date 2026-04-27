(function () {
    "use strict";

    var STORAGE_KEY = "dent1402-theme";
    var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
    var listeners = [];
    var manualTheme = "";
    var digitObserver = null;
    var LAUNCH_SPLASH_CLASS = "dent-launch-splash";
    var LAUNCH_SPLASH_LEAVING_CLASS = "dent-launch-splash--leaving";
    var LAUNCH_SPLASH_STYLE_ID = "dent1402-launch-splash-style";
    var LAUNCH_SPLASH_MIN_VISIBLE_MS = 1000;
    var LAUNCH_SPLASH_FADE_MS = 150;
    var LAUNCH_SPLASH_LOGO_URL = "/assets/images/logo.png?v=20260422-brand1";
    var launchSplashMounted = false;
    var persianDigits = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];

    try {
        var stored = window.localStorage.getItem(STORAGE_KEY);
        if (stored === "light" || stored === "dark") {
            manualTheme = stored;
        }
    } catch (error) {
        manualTheme = "";
    }

    function resolvedTheme() {
        if (manualTheme) {
            return manualTheme;
        }

        if (media && media.matches) {
            return "dark";
        }

        return "light";
    }

    function themeColor(theme) {
        return theme === "dark" ? "#0d1420" : "#eef2f7";
    }

    function parseVersionFromUrl(rawUrl) {
        if (!rawUrl) {
            return "";
        }

        try {
            var parsed = new URL(rawUrl, window.location.origin);
            var queryVersion = (parsed.searchParams.get("v") || "").trim();
            if (queryVersion) {
                return queryVersion;
            }
        } catch (error) {
            // Ignore invalid URLs and keep fallback parsing.
        }

        var fallbackMatch = String(rawUrl).match(/[?&]v=([^&#]+)/i);
        return fallbackMatch && fallbackMatch[1] ? decodeURIComponent(fallbackMatch[1]) : "";
    }

    function resolveLaunchBuildLabel() {
        var manifestTag = document.querySelector('link[rel="manifest"]');
        var fromManifest = parseVersionFromUrl(manifestTag && manifestTag.getAttribute("href"));
        if (fromManifest) {
            return fromManifest;
        }

        var pwaScript = document.querySelector('script[src*="/assets/site/scripts/pwa.js"]');
        var fromPwaScript = parseVersionFromUrl(pwaScript && pwaScript.getAttribute("src"));
        if (fromPwaScript) {
            return fromPwaScript;
        }

        var versionMeta = document.querySelector('meta[name="app-version"]');
        if (versionMeta && versionMeta.content) {
            return String(versionMeta.content).trim();
        }

        return "";
    }

    function navigationType() {
        if (window.performance && typeof window.performance.getEntriesByType === "function") {
            var entries = window.performance.getEntriesByType("navigation");
            if (entries && entries.length && entries[0] && entries[0].type) {
                return entries[0].type;
            }
        }

        if (window.performance && window.performance.navigation) {
            if (window.performance.navigation.type === 1) {
                return "reload";
            }

            if (window.performance.navigation.type === 2) {
                return "back_forward";
            }
        }

        return "navigate";
    }

    function isStandaloneDisplayMode() {
        return !!(
            window.navigator.standalone === true ||
            (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches)
        );
    }

    function isSameOriginReferrer() {
        if (!document.referrer) {
            return false;
        }

        try {
            return new URL(document.referrer, window.location.origin).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function shouldShowLaunchSplash() {
        var navType = navigationType();
        if (navType === "reload") {
            return true;
        }

        if (navType === "back_forward") {
            return false;
        }

        if (!document.referrer) {
            return true;
        }

        if (isStandaloneDisplayMode() && !isSameOriginReferrer()) {
            return true;
        }

        return !isSameOriginReferrer();
    }

    function ensureLaunchSplashStyle() {
        if (document.getElementById(LAUNCH_SPLASH_STYLE_ID)) {
            return;
        }

        var style = document.createElement("style");
        style.id = LAUNCH_SPLASH_STYLE_ID;
        style.textContent = [
            "html." + LAUNCH_SPLASH_CLASS + "::before,html." + LAUNCH_SPLASH_CLASS + "::after{position:fixed;inset:0;pointer-events:none;opacity:1;transition:opacity 0.15s ease;}",
            "html." + LAUNCH_SPLASH_CLASS + "::before{content:\"\";z-index:10020;background-image:url(\"" + LAUNCH_SPLASH_LOGO_URL + "\"),radial-gradient(circle at 20% 12%,rgba(255,255,255,0.22),transparent 44%),linear-gradient(160deg,var(--accent-color,#2b6df3) 0%,var(--accent-strong,#1f56d6) 100%);background-repeat:no-repeat,no-repeat,no-repeat;background-size:clamp(124px,32vw,190px) auto,cover,cover;background-position:center calc(50% - 10px),center,center;}",
            "html[data-theme=\"dark\"]." + LAUNCH_SPLASH_CLASS + "::before{background-image:url(\"" + LAUNCH_SPLASH_LOGO_URL + "\"),radial-gradient(circle at 18% 14%,rgba(154,195,255,0.18),transparent 44%),linear-gradient(160deg,var(--bg-body,#0d1420) 0%,var(--accent-color,#72a9ff) 100%);}",
            "html." + LAUNCH_SPLASH_CLASS + "::after{content:attr(data-launch-build);z-index:10021;display:flex;align-items:flex-end;justify-content:center;padding:0 1.1rem calc(env(safe-area-inset-bottom,0px) + 0.72rem);color:rgba(255,255,255,0.82);font-family:var(--font-main,system-ui,sans-serif);font-size:0.66rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;}",
            "html[data-theme=\"dark\"]." + LAUNCH_SPLASH_CLASS + "::after{color:rgba(241,247,255,0.76);}",
            "html." + LAUNCH_SPLASH_CLASS + "." + LAUNCH_SPLASH_LEAVING_CLASS + "::before,html." + LAUNCH_SPLASH_CLASS + "." + LAUNCH_SPLASH_LEAVING_CLASS + "::after{opacity:0;}",
            "@media (prefers-reduced-motion: reduce){html." + LAUNCH_SPLASH_CLASS + "::before,html." + LAUNCH_SPLASH_CLASS + "::after{transition-duration:0.01ms;}}"
        ].join("");

        (document.head || document.documentElement).appendChild(style);
    }

    function primeLaunchLogo() {
        var img = new Image();
        img.decoding = "async";
        img.loading = "eager";
        img.src = LAUNCH_SPLASH_LOGO_URL;
    }

    function mountLaunchSplash() {
        if (launchSplashMounted || !shouldShowLaunchSplash()) {
            return;
        }
        launchSplashMounted = true;

        ensureLaunchSplashStyle();
        primeLaunchLogo();

        var root = document.documentElement;
        var buildLabel = resolveLaunchBuildLabel();
        root.setAttribute("data-launch-build", buildLabel ? "Build " + buildLabel : "");
        root.classList.add(LAUNCH_SPLASH_CLASS);

        var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        var fadeDuration = reducedMotion ? 1 : LAUNCH_SPLASH_FADE_MS;
        var isHiding = false;

        function cleanupSplash() {
            root.classList.remove(LAUNCH_SPLASH_CLASS);
            root.classList.remove(LAUNCH_SPLASH_LEAVING_CLASS);
            root.removeAttribute("data-launch-build");
        }

        function beginHide() {
            if (isHiding) {
                return;
            }

            isHiding = true;
            root.classList.add(LAUNCH_SPLASH_LEAVING_CLASS);
            window.setTimeout(cleanupSplash, fadeDuration + 24);
        }

        window.setTimeout(beginHide, LAUNCH_SPLASH_MIN_VISIBLE_MS);
        window.addEventListener("pagehide", cleanupSplash, { once: true });
    }

    function syncMeta(theme) {
        var themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta) {
            themeMeta.setAttribute("content", themeColor(theme));
        }
    }

    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;
        syncMeta(theme);
    }

    function snapshot() {
        return {
            theme: resolvedTheme(),
            manual: manualTheme || null
        };
    }

    function notify() {
        var detail = snapshot();
        window.dispatchEvent(new CustomEvent("dent1402:theme-change", { detail: detail }));
        listeners.forEach(function (listener) {
            listener(detail);
        });
    }

    function syncButton(button) {
        if (!button) {
            return;
        }

        var theme = resolvedTheme();
        var nextTheme = theme === "dark" ? "light" : "dark";
        var label = nextTheme === "dark" ? "تم تیره" : "تم روشن";
        var iconMarkup = nextTheme === "dark"
            ? '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A7.5 7.5 0 0 1 9.5 4A8.5 8.5 0 1 0 20 14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.5 12H18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5.5 12H3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 5.99L16.59 7.41" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 16.59L5.99 18.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 18.01L16.59 16.59" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 7.41L5.99 5.99" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3.6" stroke="currentColor" stroke-width="1.8"/></svg>';

        button.innerHTML =
            '<span class="theme-toggle-btn__icon">' + iconMarkup + '</span>' +
            '<span class="theme-toggle-btn__label">' + label + '</span>';
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
        button.dataset.themeTarget = nextTheme;
    }

    function syncButtons() {
        document.querySelectorAll("[data-theme-toggle]").forEach(syncButton);
    }

    function toPersianDigits(value) {
        var text = value == null ? "" : String(value);
        if (!/[0-9٠-٩]/.test(text)) {
            return text;
        }

        return text
            .replace(/[0-9]/g, function (digit) {
                return persianDigits[digit.charCodeAt(0) - 48];
            })
            .replace(/[٠-٩]/g, function (digit) {
                return persianDigits[digit.charCodeAt(0) - 1632];
            });
    }

    function shouldSkipDigitLocalization(element) {
        // Messenger preserves user-entered text (chat titles/messages) as-is.
        // We keep chat counters localized in chat.js instead of mutating all text nodes here.
        if (document.body && document.body.classList.contains("chat-page")) {
            return true;
        }

        if (!element) {
            return true;
        }

        if (element.closest("[data-digit-locale='latin'], [data-latin-digits='true']")) {
            return true;
        }

        if (element.closest("script, style, textarea, code, pre, kbd, samp")) {
            return true;
        }

        if (element.isContentEditable || element.closest("[contenteditable='true']")) {
            return true;
        }

        return false;
    }

    function localizeTextNode(node) {
        if (!node || node.nodeType !== Node.TEXT_NODE) {
            return;
        }

        var parent = node.parentElement;
        if (shouldSkipDigitLocalization(parent)) {
            return;
        }

        var nextValue = toPersianDigits(node.nodeValue || "");
        if (nextValue !== node.nodeValue) {
            node.nodeValue = nextValue;
        }
    }

    function localizeAttribute(element, name) {
        if (!element || !element.hasAttribute(name) || shouldSkipDigitLocalization(element)) {
            return;
        }

        var value = element.getAttribute(name);
        var localized = toPersianDigits(value);
        if (localized !== value) {
            element.setAttribute(name, localized);
        }
    }

    function localizeDigits(root) {
        if (!root) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            localizeTextNode(root);
            return;
        }

        if (root.nodeType === Node.ELEMENT_NODE) {
            localizeAttribute(root, "placeholder");
            localizeAttribute(root, "title");
            localizeAttribute(root, "aria-label");
        }

        var base = root.nodeType === Node.ELEMENT_NODE ? root : document.body;
        if (!base) {
            return;
        }

        var walker = document.createTreeWalker(base, NodeFilter.SHOW_TEXT, null);
        var current = walker.nextNode();
        while (current) {
            localizeTextNode(current);
            current = walker.nextNode();
        }

        if (typeof base.querySelectorAll === "function") {
            base.querySelectorAll("[placeholder], [title], [aria-label]").forEach(function (element) {
                localizeAttribute(element, "placeholder");
                localizeAttribute(element, "title");
                localizeAttribute(element, "aria-label");
            });
        }
    }

    function startDigitLocalization() {
        if (!document.body) {
            return;
        }

        localizeDigits(document.body);

        if (!window.MutationObserver || digitObserver) {
            return;
        }

        digitObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === "characterData") {
                    localizeTextNode(mutation.target);
                    return;
                }

                if (mutation.type === "attributes" && mutation.target && mutation.target.nodeType === Node.ELEMENT_NODE) {
                    localizeAttribute(mutation.target, mutation.attributeName || "");
                    return;
                }

                if (mutation.type === "childList") {
                    mutation.addedNodes.forEach(function (node) {
                        localizeDigits(node);
                    });
                }
            });
        });

        digitObserver.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ["placeholder", "title", "aria-label"]
        });
    }

    function persistTheme() {
        try {
            if (manualTheme) {
                window.localStorage.setItem(STORAGE_KEY, manualTheme);
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (error) {
            // Ignore storage failures.
        }
    }

    function setTheme(value) {
        manualTheme = value === "light" || value === "dark" ? value : "";
        persistTheme();
        applyTheme(resolvedTheme());
        syncButtons();
        notify();
    }

    function toggleTheme() {
        setTheme(resolvedTheme() === "dark" ? "light" : "dark");
    }

    function ensureButton(container, compact) {
        if (!container || container.querySelector("[data-theme-toggle]")) {
            return;
        }

        var button = document.createElement("button");
        button.type = "button";
        button.className = "theme-toggle-btn" + (compact ? " theme-toggle-btn--compact" : "");
        button.dataset.themeToggle = "true";
        button.addEventListener("click", toggleTheme);
        container.appendChild(button);
        syncButton(button);
    }

    function injectButtons() {
        var chatActions = document.querySelector(".chat-app__actions");
        if (chatActions) {
            ensureButton(chatActions, true);
            return;
        }

        var headerActions = document.querySelector(".header-actions");
        if (headerActions) {
            ensureButton(headerActions, false);
            return;
        }

        var header = document.querySelector(".site-header");
        if (!header) {
            return;
        }

        var fallback = document.createElement("div");
        fallback.className = "header-actions";
        header.appendChild(fallback);
        ensureButton(fallback, false);
    }

    function handleSystemThemeChange() {
        if (manualTheme) {
            return;
        }

        applyTheme(resolvedTheme());
        syncButtons();
        notify();
    }

    if (media) {
        if (typeof media.addEventListener === "function") {
            media.addEventListener("change", handleSystemThemeChange);
        } else if (typeof media.addListener === "function") {
            media.addListener(handleSystemThemeChange);
        }
    }

    window.Dent1402Theme = {
        getState: snapshot,
        setTheme: setTheme,
        toggle: toggleTheme,
        onChange: function (listener) {
            if (typeof listener === "function") {
                listeners.push(listener);
                listener(snapshot());
            }
        }
    };

    window.Dent1402Locale = {
        toPersianDigits: toPersianDigits,
        localizeDigits: function (root) {
            localizeDigits(root || document.body);
        }
    };

    applyTheme(resolvedTheme());
    mountLaunchSplash();

    function boot() {
        injectButtons();
        syncButtons();
        startDigitLocalization();
        notify();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
