(function () {
    "use strict";

    var STORAGE_KEY = "dent1402-theme";
    var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
    var listeners = [];
    var manualTheme = "";
    var digitObserver = null;
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
