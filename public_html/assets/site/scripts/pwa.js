(function () {
    if (window.Dent1402PWA) {
        return;
    }

    var state = {
        installed: window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true,
        canInstall: false,
        deferredPrompt: null,
        isIOS: /iphone|ipad|ipod/i.test(window.navigator.userAgent),
        isOffline: !window.navigator.onLine
    };

    var listeners = [];

    function snapshot() {
        return {
            installed: state.installed,
            canInstall: state.canInstall,
            isIOS: state.isIOS,
            isOffline: state.isOffline
        };
    }

    function notify() {
        var detail = snapshot();
        window.dispatchEvent(new CustomEvent("dent1402:pwa-state", { detail: detail }));
        listeners.forEach(function (listener) {
            listener(detail);
        });
    }

    function updateOnlineState() {
        state.isOffline = !window.navigator.onLine;
        notify();
    }

    async function promptInstall() {
        if (state.deferredPrompt) {
            state.deferredPrompt.prompt();
            var choice = await state.deferredPrompt.userChoice;
            if (choice && choice.outcome === "accepted") {
                state.canInstall = false;
                state.deferredPrompt = null;
                notify();
            }
            return choice;
        }

        return { outcome: "unavailable" };
    }

    window.Dent1402PWA = {
        getState: snapshot,
        promptInstall: promptInstall,
        onChange: function (listener) {
            if (typeof listener === "function") {
                listeners.push(listener);
                listener(snapshot());
            }
        }
    };

    window.addEventListener("beforeinstallprompt", function (event) {
        event.preventDefault();
        state.deferredPrompt = event;
        state.canInstall = true;
        notify();
    });

    window.addEventListener("appinstalled", function () {
        state.installed = true;
        state.canInstall = false;
        state.deferredPrompt = null;
        notify();
    });

    window.addEventListener("online", updateOnlineState);
    window.addEventListener("offline", updateOnlineState);

    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function () {
            navigator.serviceWorker.register("/sw.js?v=20260422-cache4").then(function (registration) {
                registration.update().catch(function () {
                    // Ignore update failures.
                });
            }).catch(function () {
                // Keep the registration failure silent in production.
            });
        });
    }

    notify();
})();
