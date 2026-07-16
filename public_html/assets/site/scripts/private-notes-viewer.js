(function () {
    "use strict";

    var API = "/api/private_notes_api.php";
    var DEVICE_KEY = "dent1402_private_notes_device_token_v1";
    var PAGE_KEY_PREFIX = "dent1402_private_notes_last_page:";
    var MIN_SCALE = 0.18;
    var MAX_SCALE = 4;
    var SCALE_STEP = 1.18;

    function $(id) {
        return document.getElementById(id);
    }

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    }

    function siteApi() {
        return window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function normalizeDigits(value) {
        var site = siteApi();
        if (site && typeof site.normalizeDigits === "function") {
            return site.normalizeDigits(value);
        }
        return String(value || "")
            .replace(/[\u06F0-\u06F9]/g, function (char) { return String(char.charCodeAt(0) - 0x06F0); })
            .replace(/[\u0660-\u0669]/g, function (char) { return String(char.charCodeAt(0) - 0x0660); });
    }

    function readStorage(key) {
        try {
            return window.localStorage ? String(window.localStorage.getItem(key) || "") : "";
        } catch (_error) {
            return "";
        }
    }

    function writeStorage(key, value) {
        try {
            if (!window.localStorage) {
                return;
            }
            if (value) {
                window.localStorage.setItem(key, String(value));
            } else {
                window.localStorage.removeItem(key);
            }
        } catch (_error) {
            // Storage is optional for viewer preferences and device continuity.
        }
    }

    function parseJsonResponse(response) {
        var site = siteApi();
        if (site && typeof site.parseJsonResponse === "function") {
            return site.parseJsonResponse(response);
        }
        return response.text().then(function (text) {
            var payload = null;
            try {
                payload = text ? JSON.parse(text) : null;
            } catch (_error) {
                payload = null;
            }
            if (!payload || typeof payload !== "object") {
                payload = { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function networkErrorResponse() {
        return {
            success: false,
            httpStatus: 0,
            error: "ارتباط با سرور برقرار نشد."
        };
    }

    function consumeUnauthorized(payload, message) {
        var site = siteApi();
        if (site && typeof site.consumeUnauthorized === "function") {
            return !!site.consumeUnauthorized(payload, message || "نشست شما منقضی شده است.");
        }
        var auth = authApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            return !!auth.handleUnauthorizedPayload(payload, message || "نشست شما منقضی شده است.");
        }
        return !!(payload && (payload.httpStatus === 401 || payload.loggedOut));
    }

    function getDocumentId() {
        var params = new URLSearchParams(window.location.search || "");
        return String(params.get("documentId") || params.get("document") || params.get("id") || "").trim();
    }

    function request(action, method, data, signal) {
        var options = {
            method: method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { Accept: "application/json" },
            signal: signal || undefined
        };
        var url = API + "?action=" + encodeURIComponent(action);
        var payload = Object.assign({}, data || {});

        if (method === "GET") {
            Object.keys(payload).forEach(function (key) {
                var value = payload[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(payload);
        }

        return fetch(url, options).then(parseJsonResponse).catch(function (error) {
            if (error && error.name === "AbortError") {
                return { success: false, aborted: true, httpStatus: 0 };
            }
            return networkErrorResponse();
        });
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function pageStorageKey(documentId, userKey) {
        return PAGE_KEY_PREFIX + String(userKey || "anon") + ":" + documentId;
    }

    var els = {
        title: $("private-viewer-title"),
        subtitle: $("private-viewer-subtitle"),
        status: $("viewer-status"),
        statusText: $("viewer-status-text"),
        frame: $("viewer-frame"),
        canvas: $("viewer-canvas"),
        page: $("viewer-page"),
        empty: $("viewer-empty"),
        emptyMessage: $("viewer-empty-message"),
        retryDocument: $("viewer-retry-document"),
        pageInput: $("viewer-page-input"),
        pageCount: $("viewer-page-count"),
        prevPage: $("viewer-prev-page"),
        nextPage: $("viewer-next-page"),
        zoomIn: $("viewer-zoom-in"),
        zoomOut: $("viewer-zoom-out"),
        zoomLabel: $("viewer-zoom-label"),
        fitWidth: $("viewer-fit-width"),
        fitPage: $("viewer-fit-page"),
        fullscreen: $("viewer-fullscreen")
    };

    if (!els.frame || !els.canvas || !els.page) {
        return;
    }

    var state = {
        documentId: getDocumentId(),
        document: null,
        userKey: "",
        sessionId: "",
        deviceToken: readStorage(DEVICE_KEY),
        traceCode: "",
        pageNumber: 1,
        scale: 1,
        fitMode: "width",
        panX: 0,
        panY: 0,
        renderedTileLevel: -1,
        activeTiles: new Map(),
        tileTokenControllers: new Map(),
        tileImageUrls: new Map(),
        loadingTiles: 0,
        bootController: null,
        destroyed: false,
        panning: false,
        pointerId: 0,
        panStartX: 0,
        panStartY: 0,
        panOriginX: 0,
        panOriginY: 0,
        pinchStartDistance: 0,
        pinchStartScale: 1
    };

    function setStatus(text, mode) {
        els.statusText.textContent = text || "";
        els.status.classList.toggle("is-idle", mode === "idle");
        els.status.classList.toggle("is-error", mode === "error");
    }

    function showEmpty(message) {
        els.emptyMessage.textContent = message || "جزوه قابل نمایش نیست.";
        els.empty.hidden = false;
    }

    function hideEmpty() {
        els.empty.hidden = true;
    }

    function clearViewer() {
        state.activeTiles.forEach(function (entry) {
            if (entry.controller) {
                entry.controller.abort();
            }
            if (entry.node && entry.node.parentNode) {
                entry.node.parentNode.removeChild(entry.node);
            }
            if (entry.fallback && entry.fallback.parentNode) {
                entry.fallback.parentNode.removeChild(entry.fallback);
            }
        });
        state.tileTokenControllers.forEach(function (controller) {
            controller.abort();
        });
        state.tileImageUrls.forEach(function (url) {
            URL.revokeObjectURL(url);
        });
        state.activeTiles.clear();
        state.tileTokenControllers.clear();
        state.tileImageUrls.clear();
        state.loadingTiles = 0;
        els.page.textContent = "";
    }

    function revokeAccess(message) {
        state.destroyed = true;
        clearViewer();
        setStatus(message || "دسترسی نمایش این سند متوقف شد.", "error");
        showEmpty(message || "دسترسی شما به این سند معتبر نیست.");
        setControlsDisabled(true);
    }

    function setControlsDisabled(disabled) {
        [els.pageInput, els.prevPage, els.nextPage, els.zoomIn, els.zoomOut, els.fitWidth, els.fitPage, els.fullscreen].forEach(function (node) {
            if (node) {
                node.disabled = !!disabled;
            }
        });
    }

    function currentPage() {
        if (!state.document || !Array.isArray(state.document.pages)) {
            return null;
        }
        return state.document.pages[state.pageNumber - 1] || null;
    }

    function chooseLevel(page) {
        if (!page || !Array.isArray(page.levels) || page.levels.length === 0) {
            return null;
        }
        var sorted = page.levels.slice().sort(function (left, right) {
            return Number(left.scale || 1) - Number(right.scale || 1);
        });
        var targetScale = state.scale;
        var selected = sorted[sorted.length - 1];
        for (var i = 0; i < sorted.length; i += 1) {
            if (Number(sorted[i].scale || 1) >= targetScale * 0.9) {
                selected = sorted[i];
                break;
            }
        }
        return selected;
    }

    function frameSize() {
        var rect = els.frame.getBoundingClientRect();
        return {
            width: Math.max(1, Math.floor(rect.width)),
            height: Math.max(1, Math.floor(rect.height))
        };
    }

    function fitScale(mode) {
        var page = currentPage();
        if (!page) {
            return 1;
        }
        var size = frameSize();
        var widthScale = (size.width - 36) / Math.max(1, Number(page.width || 1));
        var heightScale = (size.height - 52) / Math.max(1, Number(page.height || 1));
        return clamp(mode === "page" ? Math.min(widthScale, heightScale) : widthScale, MIN_SCALE, MAX_SCALE);
    }

    function centerPage() {
        var page = currentPage();
        if (!page) {
            return;
        }
        var size = frameSize();
        var scaledWidth = Number(page.width || 1) * state.scale;
        var scaledHeight = Number(page.height || 1) * state.scale;
        state.panX = Math.round((size.width - scaledWidth) / 2);
        state.panY = Math.max(18, Math.round((size.height - scaledHeight) / 2));
    }

    function clampPan() {
        var page = currentPage();
        if (!page) {
            return;
        }
        var size = frameSize();
        var scaledWidth = Number(page.width || 1) * state.scale;
        var scaledHeight = Number(page.height || 1) * state.scale;
        var margin = 80;
        if (scaledWidth <= size.width) {
            state.panX = Math.round((size.width - scaledWidth) / 2);
        } else {
            state.panX = clamp(state.panX, size.width - scaledWidth - margin, margin);
        }
        if (scaledHeight <= size.height) {
            state.panY = Math.max(18, Math.round((size.height - scaledHeight) / 2));
        } else {
            state.panY = clamp(state.panY, size.height - scaledHeight - margin, margin);
        }
    }

    function applyPageTransform() {
        var page = currentPage();
        if (!page) {
            return;
        }
        els.page.style.width = Math.round(Number(page.width || 1)) + "px";
        els.page.style.height = Math.round(Number(page.height || 1)) + "px";
        els.page.style.transform = "translate3d(" + Math.round(state.panX) + "px," + Math.round(state.panY) + "px,0) scale(" + state.scale + ")";
        els.page.style.transformOrigin = "top left";
        els.zoomLabel.textContent = toFaDigits(Math.round(state.scale * 100)) + "٪";
    }

    function updateControls() {
        var pageCount = state.document ? Number(state.document.pageCount || state.document.pages.length || 0) : 0;
        els.pageInput.min = "1";
        els.pageInput.max = String(Math.max(1, pageCount));
        els.pageInput.value = String(state.pageNumber);
        els.pageCount.textContent = "/ " + toFaDigits(pageCount || "-");
        els.prevPage.disabled = state.pageNumber <= 1;
        els.nextPage.disabled = pageCount > 0 && state.pageNumber >= pageCount;
        els.zoomOut.disabled = state.scale <= MIN_SCALE + 0.01;
        els.zoomIn.disabled = state.scale >= MAX_SCALE - 0.01;
    }

    function tileKey(tile, level) {
        return [
            state.documentId,
            state.sessionId,
            state.pageNumber,
            level.level,
            tile.x,
            tile.y
        ].join(":");
    }

    function tileIntersectsViewport(tile, level) {
        var size = frameSize();
        var levelScale = Number(level.scale || 1);
        var factor = state.scale / Math.max(0.0001, levelScale);
        var x = state.panX + Number(tile.x || 0) * factor;
        var y = state.panY + Number(tile.y || 0) * factor;
        var width = Number(tile.width || 0) * factor;
        var height = Number(tile.height || 0) * factor;
        var buffer = 180;
        return x + width >= -buffer && y + height >= -buffer && x <= size.width + buffer && y <= size.height + buffer;
    }

    function positionTileNode(node, tile, level) {
        var levelScale = Number(level.scale || 1);
        var factor = 1 / Math.max(0.0001, levelScale);
        node.style.left = Math.round(Number(tile.x || 0) * factor) + "px";
        node.style.top = Math.round(Number(tile.y || 0) * factor) + "px";
        node.style.width = Math.ceil(Number(tile.width || 0) * factor) + "px";
        node.style.height = Math.ceil(Number(tile.height || 0) * factor) + "px";
    }

    function tileTokenPayload(tile, level) {
        return {
            documentId: state.documentId,
            sessionId: state.sessionId,
            deviceToken: state.deviceToken,
            pageNumber: state.pageNumber,
            zoomLevel: Number(level.level || 0),
            tileX: Number(tile.x || 0),
            tileY: Number(tile.y || 0)
        };
    }

    function tileUrl(tokenPayload, token) {
        var params = new URLSearchParams({
            action: "tile",
            documentId: tokenPayload.documentId,
            sessionId: tokenPayload.sessionId,
            pageNumber: String(tokenPayload.pageNumber),
            zoomLevel: String(tokenPayload.zoomLevel),
            tileX: String(tokenPayload.tileX),
            tileY: String(tokenPayload.tileY),
            token: token
        });
        return API + "?" + params.toString();
    }

    function fetchTile(tile, level, retryCount) {
        var key = tileKey(tile, level);
        if (state.activeTiles.has(key) || state.destroyed) {
            return;
        }
        var img = document.createElement("img");
        img.className = "private-viewer-tile is-loading";
        img.alt = "";
        img.draggable = false;
        img.decoding = "async";
        positionTileNode(img, tile, level);
        els.page.appendChild(img);

        var controller = new AbortController();
        var entry = { node: img, controller: controller, fallback: null };
        state.activeTiles.set(key, entry);
        state.tileTokenControllers.set(key, controller);
        state.loadingTiles += 1;
        setStatus("در حال دریافت tileهای صفحه...", "");

        var tokenPayload = tileTokenPayload(tile, level);
        request("tileToken", "POST", tokenPayload, controller.signal).then(function (payload) {
            if (state.destroyed || controller.signal.aborted) {
                return;
            }
            if (!payload.success) {
                if (payload.httpStatus === 401 || payload.httpStatus === 403) {
                    revokeAccess(payload.error || "دسترسی نمایش متوقف شد.");
                    return;
                }
                throw new Error(payload.error || "token");
            }
            var token = payload.tileToken && payload.tileToken.token ? String(payload.tileToken.token) : "";
            if (!token) {
                throw new Error("token");
            }
            return fetch(tileUrl(tokenPayload, token), {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal
            });
        }).then(function (response) {
            if (!response || state.destroyed || controller.signal.aborted) {
                return null;
            }
            if (response.status === 401 || response.status === 403) {
                if ((retryCount || 0) < 1) {
                    removeTile(key);
                    fetchTile(tile, level, (retryCount || 0) + 1);
                    return null;
                }
                revokeAccess("نشست نمایش یا دسترسی سند معتبر نیست.");
                return null;
            }
            if (!response.ok) {
                throw new Error("tile");
            }
            return response.blob();
        }).then(function (blob) {
            if (!blob || state.destroyed || controller.signal.aborted) {
                return;
            }
            var url = URL.createObjectURL(blob);
            state.tileImageUrls.set(key, url);
            img.onload = function () {
                img.classList.remove("is-loading");
            };
            img.onerror = function () {
                showTileFallback(key, tile, level, retryCount || 0);
            };
            img.src = url;
        }).catch(function () {
            if (!state.destroyed && !controller.signal.aborted) {
                showTileFallback(key, tile, level, retryCount || 0);
            }
        }).finally(function () {
            state.tileTokenControllers.delete(key);
            state.loadingTiles = Math.max(0, state.loadingTiles - 1);
            if (state.loadingTiles === 0 && !state.destroyed) {
                setStatus("آماده", "idle");
            }
        });
    }

    function showTileFallback(key, tile, level, retryCount) {
        var entry = state.activeTiles.get(key);
        if (!entry) {
            return;
        }
        if (entry.node && entry.node.parentNode) {
            entry.node.parentNode.removeChild(entry.node);
        }
        var fallback = document.createElement("div");
        fallback.className = "private-viewer-tile-fallback";
        positionTileNode(fallback, tile, level);
        var button = document.createElement("button");
        button.type = "button";
        button.textContent = retryCount >= 2 ? "خطا" : "تلاش دوباره";
        button.disabled = retryCount >= 2;
        button.addEventListener("click", function () {
            removeTile(key);
            fetchTile(tile, level, retryCount + 1);
        });
        fallback.appendChild(button);
        els.page.appendChild(fallback);
        entry.fallback = fallback;
    }

    function removeTile(key) {
        var entry = state.activeTiles.get(key);
        if (!entry) {
            return;
        }
        if (entry.controller) {
            entry.controller.abort();
        }
        if (entry.node && entry.node.parentNode) {
            entry.node.parentNode.removeChild(entry.node);
        }
        if (entry.fallback && entry.fallback.parentNode) {
            entry.fallback.parentNode.removeChild(entry.fallback);
        }
        var url = state.tileImageUrls.get(key);
        if (url) {
            URL.revokeObjectURL(url);
            state.tileImageUrls.delete(key);
        }
        state.activeTiles.delete(key);
        state.tileTokenControllers.delete(key);
    }

    function renderVisibleTiles() {
        var page = currentPage();
        if (!page || state.destroyed) {
            return;
        }
        var level = chooseLevel(page);
        if (!level) {
            return;
        }
        if (state.renderedTileLevel !== Number(level.level || 0)) {
            clearViewer();
            state.renderedTileLevel = Number(level.level || 0);
        }

        var wanted = new Set();
        (Array.isArray(level.tiles) ? level.tiles : []).forEach(function (tile) {
            if (!tileIntersectsViewport(tile, level)) {
                return;
            }
            var key = tileKey(tile, level);
            wanted.add(key);
            if (!state.activeTiles.has(key)) {
                fetchTile(tile, level, 0);
            } else {
                var entry = state.activeTiles.get(key);
                if (entry && entry.node) {
                    positionTileNode(entry.node, tile, level);
                }
                if (entry && entry.fallback) {
                    positionTileNode(entry.fallback, tile, level);
                }
            }
        });

        Array.from(state.activeTiles.keys()).forEach(function (key) {
            if (!wanted.has(key)) {
                removeTile(key);
            }
        });
    }

    function renderPage(resetPan) {
        var page = currentPage();
        if (!page) {
            return;
        }
        state.renderedTileLevel = -1;
        clearViewer();
        if (resetPan) {
            if (state.fitMode === "page" || state.fitMode === "width") {
                state.scale = fitScale(state.fitMode);
            }
            centerPage();
        } else {
            clampPan();
        }
        applyPageTransform();
        updateControls();
        renderVisibleTiles();
        writeStorage(pageStorageKey(state.documentId, state.userKey), String(state.pageNumber));
    }

    function goToPage(pageNumber) {
        if (!state.document) {
            return;
        }
        var count = Number(state.document.pageCount || state.document.pages.length || 1);
        var next = clamp(Number(pageNumber) || 1, 1, Math.max(1, count));
        if (next === state.pageNumber) {
            updateControls();
            return;
        }
        state.pageNumber = next;
        renderPage(true);
    }

    function setScale(nextScale, anchorX, anchorY) {
        var oldScale = state.scale;
        nextScale = clamp(nextScale, MIN_SCALE, MAX_SCALE);
        if (Math.abs(nextScale - oldScale) < 0.001) {
            return;
        }
        var x = Number.isFinite(anchorX) ? anchorX : frameSize().width / 2;
        var y = Number.isFinite(anchorY) ? anchorY : frameSize().height / 2;
        var docX = (x - state.panX) / oldScale;
        var docY = (y - state.panY) / oldScale;
        state.scale = nextScale;
        state.panX = x - docX * nextScale;
        state.panY = y - docY * nextScale;
        state.fitMode = "custom";
        clampPan();
        applyPageTransform();
        updateControls();
        renderVisibleTiles();
    }

    function loadManifest() {
        return request("viewerManifest", "GET", { documentId: state.documentId }).then(function (payload) {
            if (!payload.success) {
                if (consumeUnauthorized(payload, "برای مشاهده جزوه وارد حساب شوید.")) {
                    revokeAccess("برای مشاهده این جزوه باید وارد حساب شوید.");
                    return false;
                }
                if (payload.httpStatus === 403 || payload.httpStatus === 409) {
                    revokeAccess(payload.error || "دسترسی شما به این جزوه فعال نیست.");
                    return false;
                }
                showEmpty(payload.error || "جزوه قابل نمایش نیست.");
                setStatus(payload.error || "خطا در دریافت اطلاعات سند.", "error");
                return false;
            }
            state.document = payload.document;
            if (!state.document || !Array.isArray(state.document.pages) || state.document.pages.length === 0) {
                showEmpty("برای این سند هنوز صفحه‌ای آماده نشده است.");
                setStatus("سند آماده نمایش نیست.", "error");
                return false;
            }
            els.title.textContent = state.document.title || "نمایش خصوصی جزوه";
            els.subtitle.textContent = "واترمارک شخصی فعال است";
            return true;
        });
    }

    function startSession(allowRetryWithoutDevice) {
        var payload = { documentId: state.documentId, deviceLabel: window.navigator.userAgent.slice(0, 80) };
        if (state.deviceToken) {
            payload.deviceToken = state.deviceToken;
        }
        return request("startViewingSession", "POST", payload).then(function (response) {
            if (!response.success && response.httpStatus === 403 && state.deviceToken && allowRetryWithoutDevice) {
                state.deviceToken = "";
                writeStorage(DEVICE_KEY, "");
                return startSession(false);
            }
            if (!response.success) {
                if (consumeUnauthorized(response, "برای مشاهده جزوه وارد حساب شوید.")) {
                    revokeAccess("برای مشاهده این جزوه باید وارد حساب شوید.");
                    return false;
                }
                revokeAccess(response.error || "نشست نمایش ایجاد نشد.");
                return false;
            }
            var session = response.viewingSession || {};
            state.sessionId = String(session.sessionId || "");
            state.traceCode = String(session.traceCode || "");
            if (session.deviceToken) {
                state.deviceToken = String(session.deviceToken);
                writeStorage(DEVICE_KEY, state.deviceToken);
            }
            return !!state.sessionId;
        });
    }

    function bootstrapViewer() {
        if (!state.documentId) {
            showEmpty("شناسه سند در آدرس صفحه وجود ندارد.");
            setStatus("شناسه سند نامعتبر است.", "error");
            setControlsDisabled(true);
            return;
        }
        setControlsDisabled(true);
        setStatus("در حال بررسی دسترسی...", "");

        var auth = authApi();
        var ready = auth && typeof auth.ready === "function" ? auth.ready() : Promise.resolve();
        ready.then(function () {
            var snapshot = auth && typeof auth.getState === "function" ? auth.getState() : {};
            state.userKey = snapshot && snapshot.user && snapshot.user.studentNumber ? String(snapshot.user.studentNumber) : "";
            return loadManifest();
        }).then(function (ok) {
            if (!ok) {
                return false;
            }
            return startSession(true);
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            hideEmpty();
            setControlsDisabled(false);
            var savedPage = Number(readStorage(pageStorageKey(state.documentId, state.userKey)) || "0");
            var maxPage = Number(state.document.pageCount || state.document.pages.length || 1);
            state.pageNumber = clamp(savedPage || 1, 1, Math.max(1, maxPage));
            state.scale = fitScale("width");
            centerPage();
            setStatus("در حال دریافت صفحه...", "");
            renderPage(false);
        });
    }

    function bindEvents() {
        els.prevPage.addEventListener("click", function () { goToPage(state.pageNumber - 1); });
        els.nextPage.addEventListener("click", function () { goToPage(state.pageNumber + 1); });
        els.pageInput.addEventListener("change", function () {
            goToPage(Number(normalizeDigits(els.pageInput.value)));
        });
        els.zoomIn.addEventListener("click", function () { setScale(state.scale * SCALE_STEP); });
        els.zoomOut.addEventListener("click", function () { setScale(state.scale / SCALE_STEP); });
        els.fitWidth.addEventListener("click", function () {
            state.fitMode = "width";
            state.scale = fitScale("width");
            centerPage();
            applyPageTransform();
            updateControls();
            renderVisibleTiles();
        });
        els.fitPage.addEventListener("click", function () {
            state.fitMode = "page";
            state.scale = fitScale("page");
            centerPage();
            applyPageTransform();
            updateControls();
            renderVisibleTiles();
        });
        els.fullscreen.addEventListener("click", function () {
            var target = els.frame;
            if (!document.fullscreenElement && target.requestFullscreen) {
                target.requestFullscreen().catch(function () {});
            } else if (document.exitFullscreen) {
                document.exitFullscreen().catch(function () {});
            }
        });
        els.retryDocument.addEventListener("click", function () {
            state.destroyed = false;
            hideEmpty();
            clearViewer();
            bootstrapViewer();
        });
        els.frame.addEventListener("contextmenu", function (event) {
            event.preventDefault();
        });
        els.frame.addEventListener("dragstart", function (event) {
            event.preventDefault();
        });
        window.addEventListener("resize", function () {
            if (!state.document || state.destroyed) {
                return;
            }
            if (state.fitMode === "width" || state.fitMode === "page") {
                state.scale = fitScale(state.fitMode);
                centerPage();
            } else {
                clampPan();
            }
            applyPageTransform();
            renderVisibleTiles();
        });
        window.addEventListener("pagehide", function () {
            state.destroyed = true;
            clearViewer();
        });
        document.addEventListener("fullscreenchange", function () {
            window.setTimeout(function () {
                if (state.document && !state.destroyed) {
                    if (state.fitMode === "width" || state.fitMode === "page") {
                        state.scale = fitScale(state.fitMode);
                        centerPage();
                    }
                    applyPageTransform();
                    renderVisibleTiles();
                }
            }, 80);
        });
        document.addEventListener("keydown", function (event) {
            if (event.target && /input|textarea|select/i.test(event.target.tagName || "")) {
                return;
            }
            if (event.key === "ArrowLeft" || event.key === "PageDown") {
                event.preventDefault();
                goToPage(state.pageNumber + 1);
            } else if (event.key === "ArrowRight" || event.key === "PageUp") {
                event.preventDefault();
                goToPage(state.pageNumber - 1);
            } else if (event.key === "+" || event.key === "=") {
                event.preventDefault();
                setScale(state.scale * SCALE_STEP);
            } else if (event.key === "-") {
                event.preventDefault();
                setScale(state.scale / SCALE_STEP);
            } else if (event.key === "0") {
                event.preventDefault();
                state.fitMode = "width";
                state.scale = fitScale("width");
                centerPage();
                applyPageTransform();
                renderVisibleTiles();
            }
        });
        els.frame.addEventListener("wheel", function (event) {
            if (!event.ctrlKey && !event.metaKey) {
                return;
            }
            event.preventDefault();
            var rect = els.frame.getBoundingClientRect();
            var direction = event.deltaY < 0 ? SCALE_STEP : 1 / SCALE_STEP;
            setScale(state.scale * direction, event.clientX - rect.left, event.clientY - rect.top);
        }, { passive: false });
        els.canvas.addEventListener("pointerdown", function (event) {
            if (event.button !== 0 && event.pointerType === "mouse") {
                return;
            }
            state.panning = true;
            state.pointerId = event.pointerId;
            state.panStartX = event.clientX;
            state.panStartY = event.clientY;
            state.panOriginX = state.panX;
            state.panOriginY = state.panY;
            els.canvas.classList.add("is-panning");
            els.canvas.setPointerCapture(event.pointerId);
        });
        els.canvas.addEventListener("pointermove", function (event) {
            if (!state.panning || state.pointerId !== event.pointerId) {
                return;
            }
            state.panX = state.panOriginX + event.clientX - state.panStartX;
            state.panY = state.panOriginY + event.clientY - state.panStartY;
            clampPan();
            applyPageTransform();
            renderVisibleTiles();
        });
        function endPointer(event) {
            if (state.pointerId === event.pointerId) {
                state.panning = false;
                state.pointerId = 0;
                els.canvas.classList.remove("is-panning");
            }
        }
        els.canvas.addEventListener("pointerup", endPointer);
        els.canvas.addEventListener("pointercancel", endPointer);
        els.frame.addEventListener("touchstart", function (event) {
            if (event.touches.length === 2) {
                var dx = event.touches[0].clientX - event.touches[1].clientX;
                var dy = event.touches[0].clientY - event.touches[1].clientY;
                state.pinchStartDistance = Math.sqrt(dx * dx + dy * dy);
                state.pinchStartScale = state.scale;
            }
        }, { passive: true });
        els.frame.addEventListener("touchmove", function (event) {
            if (event.touches.length !== 2 || !state.pinchStartDistance) {
                return;
            }
            event.preventDefault();
            var dx = event.touches[0].clientX - event.touches[1].clientX;
            var dy = event.touches[0].clientY - event.touches[1].clientY;
            var distance = Math.sqrt(dx * dx + dy * dy);
            var rect = els.frame.getBoundingClientRect();
            var anchorX = ((event.touches[0].clientX + event.touches[1].clientX) / 2) - rect.left;
            var anchorY = ((event.touches[0].clientY + event.touches[1].clientY) / 2) - rect.top;
            setScale(state.pinchStartScale * (distance / state.pinchStartDistance), anchorX, anchorY);
        }, { passive: false });
        els.frame.addEventListener("touchend", function (event) {
            if (event.touches && event.touches.length < 2) {
                state.pinchStartDistance = 0;
            }
        }, { passive: true });
    }

    bindEvents();
    bootstrapViewer();
})();
