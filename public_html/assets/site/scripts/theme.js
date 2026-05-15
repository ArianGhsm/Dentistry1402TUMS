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
    var LAUNCH_SPLASH_NODE_ID = "dent1402-launch-splash";
    var LAUNCH_SPLASH_MIN_VISIBLE_MS = 280;
    var LAUNCH_SPLASH_MAX_VISIBLE_MS = 560;
    var LAUNCH_SPLASH_FADE_MS = 140;
    var LAUNCH_SPLASH_LOGO_URL = "/assets/images/logo.png?v=20260422-brand1";
    var LAUNCH_SPLASH_COLOR_LIGHT = "#f2f3f5";
    var LAUNCH_SPLASH_COLOR_DARK = "#101827";
    var launchSplashMounted = false;
    var launchSplashNode = null;
    var inputViewportTimer = null;
    var persianDigits = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
    var CRITICAL_ACCENT_FONTS = [
        "/fonts/AbarHigh-Regular.woff2",
        "/fonts/AbarHigh-Bold.woff2",
        "/fonts/AbarHigh-ExtraBold.woff2",
        "/fonts/AbarHigh-Black.woff2"
    ];

    function ensureCriticalAccentFontPreloads() {
        var head = document.head || document.documentElement;
        if (!head) {
            return;
        }

        CRITICAL_ACCENT_FONTS.forEach(function (fontUrl) {
            if (head.querySelector('link[rel="preload"][href="' + fontUrl + '"]')) {
                return;
            }

            var link = document.createElement("link");
            link.rel = "preload";
            link.as = "font";
            link.type = "font/woff2";
            link.href = fontUrl;
            link.crossOrigin = "anonymous";
            link.setAttribute("fetchpriority", "high");
            head.appendChild(link);
        });
    }

    ensureCriticalAccentFontPreloads();

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

    function launchSplashColor(theme) {
        return theme === "dark" ? LAUNCH_SPLASH_COLOR_DARK : LAUNCH_SPLASH_COLOR_LIGHT;
    }

    function ensureViewportScaleLock() {
        var meta = document.querySelector('meta[name="viewport"]');
        if (!meta) {
            meta = document.createElement("meta");
            meta.name = "viewport";
            (document.head || document.documentElement).appendChild(meta);
        }

        var content = String(meta.getAttribute("content") || "width=device-width, initial-scale=1.0");
        var parts = content.split(",").map(function (part) {
            return part.trim();
        }).filter(function (part) {
            return part && !/^(maximum-scale|user-scalable)\s*=/i.test(part);
        });

        if (!parts.some(function (part) { return /^width\s*=/i.test(part); })) {
            parts.unshift("width=device-width");
        }
        if (!parts.some(function (part) { return /^initial-scale\s*=/i.test(part); })) {
            parts.push("initial-scale=1.0");
        }

        parts.push("maximum-scale=1.0");
        parts.push("user-scalable=no");
        meta.setAttribute("content", parts.join(", "));
        meta.dataset.globalScaleLock = "1";
    }

    function isTextInputElement(node) {
        if (!node || !node.matches) {
            return false;
        }

        return node.matches("textarea, select, [contenteditable='true'], [contenteditable=''], input:not([type='checkbox']):not([type='radio']):not([type='range']):not([type='file']):not([type='color']):not([type='button']):not([type='submit']):not([type='reset']):not([type='hidden'])");
    }

    function isTextInputFocused() {
        return isTextInputElement(document.activeElement);
    }

    function isSoftKeyboardOpen() {
        if (!isTextInputFocused()) {
            return false;
        }

        var compactViewport = window.matchMedia && window.matchMedia("(max-width: 980px)").matches;
        var touchPoints = Number(window.navigator.maxTouchPoints || 0);
        if (!compactViewport && touchPoints < 1) {
            return false;
        }

        if (!window.visualViewport) {
            return compactViewport && touchPoints > 0;
        }

        var vv = window.visualViewport;
        var viewportHeight = Math.max(0, Number(vv.height || 0));
        var viewportOffsetTop = Math.max(0, Number(vv.offsetTop || 0));
        var layoutHeight = Math.max(0, Number(window.innerHeight || document.documentElement.clientHeight || 0));
        var hiddenHeight = layoutHeight - (viewportHeight + viewportOffsetTop);
        return hiddenHeight > 92;
    }

    function syncInputViewportState() {
        ensureViewportScaleLock();
        if (!document.body) {
            return;
        }
        var focused = isTextInputFocused();
        document.body.classList.toggle("site-input-focus", focused);
        document.body.classList.toggle("site-keyboard-open", isSoftKeyboardOpen());
    }

    function queueInputViewportSync() {
        ensureViewportScaleLock();
        if (inputViewportTimer) {
            window.clearTimeout(inputViewportTimer);
        }
        inputViewportTimer = window.setTimeout(syncInputViewportState, 34);
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
            "html." + LAUNCH_SPLASH_CLASS + ",html." + LAUNCH_SPLASH_CLASS + " body{background:#f2f3f5!important;}",
            "html[data-theme=\"dark\"]." + LAUNCH_SPLASH_CLASS + ",html[data-theme=\"dark\"]." + LAUNCH_SPLASH_CLASS + " body{background:#101827!important;}",
            "#" + LAUNCH_SPLASH_NODE_ID + "{position:fixed;inset:0;z-index:10020;display:grid;grid-template-rows:1fr auto auto;justify-items:center;align-items:center;padding:clamp(2rem,6vh,4rem) 1.4rem calc(1.8rem + env(safe-area-inset-bottom,0px));background:#f2f3f5;color:#111827;opacity:1;pointer-events:none;transition:opacity 0.16s ease;}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + "{background:#101827;color:#edf3ff;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__center{align-self:center;display:grid;justify-items:center;gap:1.1rem;transform:translateY(8vh);transition:transform 0.22s cubic-bezier(0.2,0.8,0.2,1),opacity 0.16s ease;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark{width:clamp(92px,22vw,124px);height:clamp(92px,22vw,124px);display:grid;place-items:center;border-radius:28px;background:rgba(255,255,255,0.72);border:1px solid rgba(127,145,165,0.12);box-shadow:0 22px 54px -38px rgba(17,30,54,0.38);overflow:hidden;}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark{background:rgba(245,249,255,0.9);border-color:rgba(124,145,168,0.16);box-shadow:0 24px 60px -38px rgba(0,0,0,0.68);}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark img{width:82%;height:82%;object-fit:contain;border-radius:20px;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress{width:min(280px,54vw);height:5px;border-radius:999px;background:rgba(22,34,53,0.14);overflow:hidden;}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress{background:rgba(237,243,255,0.16);}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress span{display:block;width:100%;height:100%;border-radius:inherit;background:#2b6df3;transform-origin:right center;animation:dentLaunchProgress 0.42s cubic-bezier(0.2,0.8,0.2,1) both;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__brand{margin-top:min(11vh,7rem);font-family:var(--font-accent,var(--font-main));font-size:clamp(1rem,4vw,1.35rem);font-weight:900;color:rgba(17,24,39,0.78);}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__brand{color:rgba(237,243,255,0.8);}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__build{display:none;}",
            "html." + LAUNCH_SPLASH_CLASS + "." + LAUNCH_SPLASH_LEAVING_CLASS + " #" + LAUNCH_SPLASH_NODE_ID + "{opacity:0;}",
            "html." + LAUNCH_SPLASH_CLASS + "." + LAUNCH_SPLASH_LEAVING_CLASS + " #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__center{opacity:0;transform:translateY(7vh) scale(0.985);}",
            "@keyframes dentLaunchProgress{0%{transform:scaleX(0.08);opacity:0.7;}100%{transform:scaleX(1);opacity:1;}}",
            "@media (prefers-reduced-motion: reduce){#" + LAUNCH_SPLASH_NODE_ID + ",#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__center{transition-duration:0.01ms;}#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress span{animation-duration:0.01ms;}}"
        ].join("");

        (document.head || document.documentElement).appendChild(style);
    }

    function primeLaunchLogo() {
        var img = new Image();
        img.decoding = "async";
        img.loading = "eager";
        img.src = LAUNCH_SPLASH_LOGO_URL;
    }

    function createLaunchSplashNode(buildLabel) {
        if (launchSplashNode) {
            return launchSplashNode;
        }

        var splash = document.createElement("div");
        splash.id = LAUNCH_SPLASH_NODE_ID;
        splash.dir = "rtl";
        splash.setAttribute("aria-hidden", "true");
        splash.innerHTML = [
            '<div class="dent-launch-splash__center">',
            '  <div class="dent-launch-splash__mark"><img src="' + LAUNCH_SPLASH_LOGO_URL + '" alt=""></div>',
            '  <div class="dent-launch-splash__progress"><span></span></div>',
            "</div>",
            '<div class="dent-launch-splash__brand">ورودی ۱۴۰۲ دندانپزشکی تهران</div>',
            '<div class="dent-launch-splash__build" data-digit-locale="latin">' + (buildLabel || "") + "</div>"
        ].join("");

        document.documentElement.appendChild(splash);
        launchSplashNode = splash;
        return splash;
    }

    function mountLaunchSplash() {
        if (launchSplashMounted || !shouldShowLaunchSplash()) {
            return;
        }
        launchSplashMounted = true;

        ensureLaunchSplashStyle();
        primeLaunchLogo();

        var root = document.documentElement;
        var splashTheme = resolvedTheme();
        var buildLabel = resolveLaunchBuildLabel();
        createLaunchSplashNode(buildLabel ? "Build " + buildLabel : "");
        root.setAttribute("data-launch-build", buildLabel ? "Build " + buildLabel : "");
        root.classList.add(LAUNCH_SPLASH_CLASS);
        forceLaunchMeta(splashTheme);

        var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        var fadeDuration = reducedMotion ? 1 : LAUNCH_SPLASH_FADE_MS;
        var isHiding = false;
        var minElapsed = false;
        var domReady = document.readyState !== "loading";

        function cleanupSplash() {
            root.classList.remove(LAUNCH_SPLASH_CLASS);
            root.classList.remove(LAUNCH_SPLASH_LEAVING_CLASS);
            root.removeAttribute("data-launch-build");
            if (launchSplashNode && launchSplashNode.parentNode) {
                launchSplashNode.parentNode.removeChild(launchSplashNode);
            }
            launchSplashNode = null;
            syncMeta(resolvedTheme());
        }

        function beginHide() {
            if (isHiding) {
                return;
            }

            isHiding = true;
            root.classList.add(LAUNCH_SPLASH_LEAVING_CLASS);
            window.setTimeout(cleanupSplash, fadeDuration + 24);
        }

        function maybeHide() {
            if (minElapsed && domReady) {
                beginHide();
            }
        }

        window.setTimeout(function () {
            minElapsed = true;
            maybeHide();
        }, reducedMotion ? 1 : LAUNCH_SPLASH_MIN_VISIBLE_MS);

        if (!domReady) {
            document.addEventListener("DOMContentLoaded", function () {
                window.requestAnimationFrame(function () {
                    domReady = true;
                    maybeHide();
                });
            }, { once: true });
        } else {
            maybeHide();
        }

        window.setTimeout(beginHide, reducedMotion ? 1 : LAUNCH_SPLASH_MAX_VISIBLE_MS);
        window.addEventListener("pagehide", cleanupSplash, { once: true });
    }

    function syncMeta(theme) {
        var themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta) {
            themeMeta.setAttribute("content", themeColor(theme));
        }

        var appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
        if (appleStatusBarMeta) {
            // Full-bleed status bar in iOS standalone mode prevents white top strips during splash.
            appleStatusBarMeta.setAttribute("content", isStandaloneDisplayMode() ? "black-translucent" : "default");
        }
    }

    function forceLaunchMeta(theme) {
        var color = launchSplashColor(theme);
        var themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta) {
            themeMeta.setAttribute("content", color);
        }

        var appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
        if (appleStatusBarMeta) {
            appleStatusBarMeta.setAttribute("content", "black-translucent");
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

        button.innerHTML = '<span class="theme-toggle-btn__icon">' + iconMarkup + "</span>";
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
        document.querySelectorAll("[data-theme-toggle-slot]").forEach(function (slot) {
            ensureButton(slot, true);
        });

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

    ensureViewportScaleLock();
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

    document.addEventListener("focusin", queueInputViewportSync, true);
    document.addEventListener("focusout", queueInputViewportSync, true);
    window.addEventListener("resize", queueInputViewportSync, { passive: true });
    window.addEventListener("orientationchange", queueInputViewportSync, { passive: true });
    window.addEventListener("pageshow", queueInputViewportSync, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", queueInputViewportSync, { passive: true });
        window.visualViewport.addEventListener("scroll", queueInputViewportSync, { passive: true });
    }
})();
