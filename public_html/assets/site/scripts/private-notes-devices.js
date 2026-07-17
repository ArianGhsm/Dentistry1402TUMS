(function () {
    "use strict";

    var API = "/api/private_notes_api.php";
    var state = {
        csrfToken: "",
        isOwner: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    var els = {
        status: $("devices-status"),
        deviceLimit: $("device-limit"),
        myDevices: $("my-devices-list"),
        sessions: $("viewing-sessions-list"),
        signOutAll: $("sign-out-all-devices"),
        ownerPanel: $("owner-devices-panel"),
        ownerDevices: $("owner-devices-list"),
        refreshOwner: $("refresh-owner-devices")
    };

    if (!els.status || !els.myDevices) {
        return;
    }

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    }

    function parseJsonResponse(response) {
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

    function request(action, method, data) {
        var options = {
            method: method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { Accept: "application/json" }
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
            if (state.csrfToken) {
                options.headers["X-CSRF-Token"] = state.csrfToken;
            }
            options.body = new URLSearchParams(payload);
        }
        return fetch(url, options).then(parseJsonResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function setStatus(message, mode) {
        els.status.textContent = message || "";
        els.status.classList.toggle("is-error", mode === "error");
    }

    function formatDate(value) {
        if (!value) {
            return "ثبت نشده";
        }
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }
        try {
            return new Intl.DateTimeFormat("fa-IR", {
                dateStyle: "medium",
                timeStyle: "short"
            }).format(date);
        } catch (_error) {
            return date.toLocaleString();
        }
    }

    function faText(value, fallback) {
        var text = String(value || "").trim();
        return text || fallback || "—";
    }

    function emptyNode(text) {
        var node = document.createElement("div");
        node.className = "private-devices-empty";
        node.textContent = text;
        return node;
    }

    function badge(text, extraClass) {
        var node = document.createElement("span");
        node.className = "private-device-badge" + (extraClass ? " " + extraClass : "");
        node.textContent = text;
        return node;
    }

    function deviceType(device) {
        var summary = String(device.userAgentSummary || device.label || "").toLowerCase();
        var os = String(device.operatingSystem || "").toLowerCase();
        if (os.indexOf("ios") !== -1 || summary.indexOf("iphone") !== -1) {
            return "آیفون";
        }
        if (summary.indexOf("ipad") !== -1) {
            return "آیپد";
        }
        if (os.indexOf("android") !== -1) {
            return "اندروید";
        }
        if (os.indexOf("windows") !== -1 || os.indexOf("mac") !== -1 || os.indexOf("linux") !== -1) {
            return "کامپیوتر";
        }
        return "دستگاه";
    }

    function statusText(status) {
        switch (String(status || "")) {
            case "active":
                return "فعال";
            case "revoked":
                return "لغوشده";
            case "expired":
                return "منقضی";
            case "closed":
                return "بسته‌شده";
            default:
                return status ? String(status) : "نامشخص";
        }
    }

    function verificationText(status, method) {
        var value = String(status || "");
        if (value === "verified") {
            return method ? "تاییدشده با " + method : "تاییدشده";
        }
        if (value === "development-fallback") {
            return "ثبت توسعه‌ای";
        }
        if (value === "pending") {
            return "در انتظار تایید";
        }
        return value || "نامشخص";
    }

    function appendDetail(grid, label, value, extraClass) {
        var item = document.createElement("div");
        item.className = "private-device-detail" + (extraClass ? " " + extraClass : "");
        var key = document.createElement("span");
        key.textContent = label;
        var val = document.createElement("strong");
        val.textContent = value;
        item.appendChild(key);
        item.appendChild(val);
        grid.appendChild(item);
    }

    function deviceTitle(device) {
        return device.label || device.userAgentSummary || "دستگاه ثبت‌شده";
    }

    function renderDevice(container, device, options) {
        var item = document.createElement("article");
        item.className = "private-device-item";
        item.dataset.status = String(device.status || "");

        var top = document.createElement("div");
        top.className = "private-device-item__top";

        var identity = document.createElement("div");
        identity.className = "private-device-item__identity";
        var icon = document.createElement("span");
        icon.className = "private-device-icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = deviceType(device).slice(0, 1);

        var copy = document.createElement("div");
        var title = document.createElement("strong");
        title.textContent = deviceTitle(device);
        var detail = document.createElement("span");
        detail.textContent = [
            deviceType(device),
            device.browser || "",
            device.operatingSystem || "",
            device.userKey ? "کاربر " + device.userKey : ""
        ].filter(Boolean).join(" | ");
        copy.appendChild(title);
        copy.appendChild(detail);
        identity.appendChild(icon);
        identity.appendChild(copy);

        var meta = document.createElement("div");
        meta.className = "private-device-item__meta";
        meta.appendChild(badge(statusText(device.status), device.status === "active" ? "" : "is-revoked"));
        if (device.isCurrent) {
            meta.appendChild(badge("همین دستگاه", "is-current"));
        }
        top.appendChild(identity);
        top.appendChild(meta);
        item.appendChild(top);

        var details = document.createElement("div");
        details.className = "private-device-details";
        appendDetail(details, "نوع دستگاه", deviceType(device));
        appendDetail(details, "مرورگر", faText(device.browser, "نامشخص"));
        appendDetail(details, "سیستم‌عامل", faText(device.operatingSystem, "نامشخص"));
        appendDetail(details, "وضعیت تایید", verificationText(device.verificationStatus, device.verificationMethod));
        appendDetail(details, "اولین ورود", formatDate(device.firstSeenAt));
        appendDetail(details, "آخرین فعالیت", formatDate(device.lastSeenAt));
        if (device.revokedAt) {
            appendDetail(details, "زمان لغو", formatDate(device.revokedAt), "is-danger");
        }
        if (device.ipMeta && device.ipMeta.network) {
            appendDetail(details, "IP تقریبی", String(device.ipMeta.network));
        }
        if (device.userAgentSummary) {
            appendDetail(details, "خلاصه دستگاه", String(device.userAgentSummary));
        }
        item.appendChild(details);

        if (device.status === "active" && options && typeof options.onRevoke === "function") {
            var actions = document.createElement("div");
            actions.className = "private-device-actions";
            var button = document.createElement("button");
            button.type = "button";
            button.className = "shell-action-btn";
            button.textContent = "لغو دستگاه";
            button.addEventListener("click", function () {
                options.onRevoke(device.id, button);
            });
            actions.appendChild(button);
            item.appendChild(actions);
        }
        container.appendChild(item);
    }

    function renderSessions(sessions) {
        els.sessions.textContent = "";
        if (!sessions.length) {
            els.sessions.appendChild(emptyNode("نشست نمایشی ثبت نشده است."));
            return;
        }
        sessions.forEach(function (session) {
            var item = document.createElement("article");
            item.className = "private-device-item";
            var title = document.createElement("strong");
            title.textContent = session.status === "active" ? "نشست فعال نمایش" : "نشست " + statusText(session.status);
            var meta = document.createElement("span");
            meta.textContent = "سند: " + (session.documentId || "-") + " | trace: " + (session.traceCode || "-");
            var details = document.createElement("div");
            details.className = "private-device-details";
            appendDetail(details, "شروع نشست", formatDate(session.startedAt));
            appendDetail(details, "آخرین فعالیت", formatDate(session.lastSeenAt));
            appendDetail(details, "شناسه trace", faText(session.traceCode, "-"));
            appendDetail(details, "سند", faText(session.documentId, "-"));
            if (session.closedAt) {
                appendDetail(details, "زمان پایان", formatDate(session.closedAt));
            }
            if (session.closedReason) {
                appendDetail(details, "علت پایان", String(session.closedReason));
            }
            item.appendChild(title);
            item.appendChild(meta);
            item.appendChild(details);
            els.sessions.appendChild(item);
        });
    }

    function renderDevices(payload) {
        els.myDevices.textContent = "";
        var limits = payload.limits || {};
        els.deviceLimit.textContent = "سقف دستگاه فعال: " + (limits.maxRegisteredDevices || 2) + " | سقف نشست فعال نمایش: " + (limits.activeViewingSessionsPerUser || 1);
        var devices = Array.isArray(payload.devices) ? payload.devices : [];
        if (devices.length === 0) {
            els.myDevices.appendChild(emptyNode("هنوز دستگاهی برای جزوه خصوصی ثبت نشده است."));
        } else {
            devices.forEach(function (device) {
                renderDevice(els.myDevices, device, { onRevoke: revokeMyDevice });
            });
        }
        els.signOutAll.disabled = devices.filter(function (device) { return device.status === "active"; }).length === 0;
        renderSessions(Array.isArray(payload.viewingSessions) ? payload.viewingSessions : []);
    }

    function loadCsrf() {
        return request("csrfToken", "GET", {}).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.error || "csrf");
            }
            state.csrfToken = String(payload.csrfToken || "");
            return state.csrfToken;
        });
    }

    function loadDevices() {
        return request("myDevices", "GET", {}).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.error || "devices");
            }
            renderDevices(payload);
            return request("status", "GET", {});
        }).then(function (statusPayload) {
            state.isOwner = !!(statusPayload && statusPayload.isOwner);
            els.ownerPanel.hidden = !state.isOwner;
            if (state.isOwner) {
                return loadOwnerDevices();
            }
            return null;
        });
    }

    function loadOwnerDevices() {
        return request("ownerDevices", "GET", {}).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.error || "ownerDevices");
            }
            els.ownerDevices.textContent = "";
            var devices = Array.isArray(payload.devices) ? payload.devices : [];
            if (devices.length === 0) {
                els.ownerDevices.appendChild(emptyNode("دستگاهی برای کاربران ثبت نشده است."));
            } else {
                devices.forEach(function (device) {
                    renderDevice(els.ownerDevices, device, { onRevoke: ownerRevokeDevice });
                });
            }
        });
    }

    function revokeMyDevice(deviceId, button) {
        button.disabled = true;
        request("revokeMyDevice", "POST", { deviceId: deviceId }).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.error || "revoke");
            }
            setStatus("دستگاه لغو شد.");
            return loadDevices();
        }).catch(function (error) {
            setStatus(error.message || "لغو دستگاه انجام نشد.", "error");
            button.disabled = false;
        });
    }

    function ownerRevokeDevice(deviceId, button) {
        button.disabled = true;
        request("ownerRevokeDevice", "POST", { deviceId: deviceId }).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.error || "owner revoke");
            }
            setStatus("دستگاه کاربر لغو شد.");
            return loadDevices();
        }).catch(function (error) {
            setStatus(error.message || "لغو دستگاه کاربر انجام نشد.", "error");
            button.disabled = false;
        });
    }

    function signOutAll() {
        els.signOutAll.disabled = true;
        request("signOutPrivateNoteDevices", "POST", {}).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.error || "sign out");
            }
            setStatus("همه دستگاه‌های جزوه خصوصی لغو شدند.");
            return loadDevices();
        }).catch(function (error) {
            setStatus(error.message || "خروج از همه دستگاه‌ها انجام نشد.", "error");
            els.signOutAll.disabled = false;
        });
    }

    function boot() {
        var auth = authApi();
        var ready = auth && typeof auth.ready === "function" ? auth.ready() : Promise.resolve();
        ready.then(function () {
            return loadCsrf();
        }).then(function () {
            return loadDevices();
        }).then(function () {
            setStatus("آماده");
        }).catch(function (error) {
            setStatus(error.message || "بارگذاری دستگاه‌ها انجام نشد.", "error");
        });
    }

    els.signOutAll.addEventListener("click", signOutAll);
    if (els.refreshOwner) {
        els.refreshOwner.addEventListener("click", function () {
            loadOwnerDevices().catch(function (error) {
                setStatus(error.message || "به‌روزرسانی دستگاه‌ها انجام نشد.", "error");
            });
        });
    }
    boot();
})();
