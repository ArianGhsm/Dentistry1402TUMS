(function () {
    "use strict";

    var STATUS = {
        LOGGED_OUT: "logged-out",
        LOGGING_IN: "logging-in",
        LOGIN_ERROR: "login-error",
        SESSION_RESTORING: "session-restoring",
        LOGGED_IN: "logged-in",
        LOGGING_OUT: "logging-out",
        UNAUTHORIZED: "unauthorized"
    };

    var LEGACY_STATUS_MAP = {
        restoring: STATUS.SESSION_RESTORING,
        "logged-in-admin": STATUS.LOGGED_IN,
        "logged-in": STATUS.LOGGED_IN,
        "logged-out": STATUS.LOGGED_OUT,
        "logging-in": STATUS.LOGGING_IN,
        "login-error": STATUS.LOGIN_ERROR,
        "logging-out": STATUS.LOGGING_OUT,
        unauthorized: STATUS.UNAUTHORIZED
    };

    var listeners = [];
    var readyResolved = false;
    var readyResolve = null;
    var bootPromise = null;
    var state = {
        status: STATUS.SESSION_RESTORING,
        loggedIn: false,
        user: null,
        error: ""
    };

    var readyPromise = new Promise(function (resolve) {
        readyResolve = resolve;
    });

    function clone(value) {
        return value ? JSON.parse(JSON.stringify(value)) : value;
    }

    function snapshot() {
        return {
            status: state.status,
            loggedIn: state.loggedIn,
            user: clone(state.user),
            error: state.error || ""
        };
    }

    function normalizeStatus(status, fallback) {
        if (!status || typeof status !== "string") {
            return fallback || STATUS.LOGGED_OUT;
        }

        if (status === STATUS.LOGGED_OUT ||
            status === STATUS.LOGGING_IN ||
            status === STATUS.LOGIN_ERROR ||
            status === STATUS.SESSION_RESTORING ||
            status === STATUS.LOGGED_IN ||
            status === STATUS.LOGGING_OUT ||
            status === STATUS.UNAUTHORIZED) {
            return status;
        }

        return LEGACY_STATUS_MAP[status] || fallback || STATUS.LOGGED_OUT;
    }

    function resolveReady() {
        if (readyResolved) {
            return;
        }

        readyResolved = true;
        readyResolve(snapshot());
    }

    function emit() {
        var detail = snapshot();
        window.dispatchEvent(new CustomEvent("dent1402:auth-change", { detail: detail }));
        listeners.slice().forEach(function (listener) {
            listener(detail);
        });
    }

    function setState(nextState) {
        var status = normalizeStatus(nextState.status, state.status);
        var user = nextState.user ? clone(nextState.user) : null;
        var loggedIn = !!nextState.loggedIn && !!user;

        state = {
            status: status,
            loggedIn: loggedIn,
            user: user,
            error: nextState.error || ""
        };

        emit();
    }

    function currentReturnTo() {
        return window.location.pathname + window.location.search + window.location.hash;
    }

    function loginUrl(returnTo) {
        var target = returnTo || currentReturnTo();
        return "/account/?returnTo=" + encodeURIComponent(target);
    }

    async function request(action, method, payload) {
        var requestMethod = method || "GET";
        var url = "/api/auth_api.php";
        var options = {
            method: requestMethod,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        };

        if (requestMethod === "GET") {
            url += "?action=" + encodeURIComponent(action);
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        }

        var response = await fetch(url, options);
        var data = {};

        try {
            data = await response.json();
        } catch (error) {
            data = {
                success: false,
                error: "Invalid server response."
            };
        }

        data.httpStatus = response.status;
        return data;
    }

    function applyAuthenticatedState(response) {
        var user = response && response.user ? response.user : null;

        setState({
            status: STATUS.LOGGED_IN,
            loggedIn: !!user,
            user: user,
            error: ""
        });
    }

    function applyLoggedOutState(nextStatus, errorText) {
        var status = normalizeStatus(nextStatus || STATUS.LOGGED_OUT, STATUS.LOGGED_OUT);
        if (status === STATUS.LOGGED_IN) {
            status = STATUS.LOGGED_OUT;
        }

        setState({
            status: status,
            loggedIn: false,
            user: null,
            error: errorText || ""
        });
    }

    function patchCurrentUser(user, nextStatus) {
        if (!user) {
            applyLoggedOutState(STATUS.LOGGED_OUT, "");
            return snapshot();
        }

        setState({
            status: normalizeStatus(nextStatus || STATUS.LOGGED_IN, STATUS.LOGGED_IN),
            loggedIn: true,
            user: user,
            error: ""
        });

        resolveReady();
        return snapshot();
    }

    async function bootstrap(force) {
        if (bootPromise && !force) {
            return bootPromise;
        }

        setState({
            status: STATUS.SESSION_RESTORING,
            loggedIn: state.loggedIn,
            user: state.user,
            error: ""
        });

        bootPromise = request("me", "GET").then(function (response) {
            if (response && response.loggedIn && response.user) {
                applyAuthenticatedState(response);
            } else {
                applyLoggedOutState(STATUS.LOGGED_OUT, "");
            }

            resolveReady();
            return snapshot();
        }).catch(function () {
            applyLoggedOutState(STATUS.LOGGED_OUT, "Session restore failed.");
            resolveReady();
            return snapshot();
        }).finally(function () {
            bootPromise = null;
        });

        return bootPromise;
    }

    async function login(studentNumber, password) {
        setState({
            status: STATUS.LOGGING_IN,
            loggedIn: false,
            user: null,
            error: ""
        });

        var response = await request("login", "POST", {
            studentNumber: studentNumber,
            password: password
        });

        if (response && response.success && response.loggedIn && response.user) {
            applyAuthenticatedState(response);
            resolveReady();
            return snapshot();
        }

        setState({
            status: STATUS.LOGIN_ERROR,
            loggedIn: false,
            user: null,
            error: (response && response.error) || "Login failed."
        });

        resolveReady();
        return snapshot();
    }

    async function requestLoginOtp(phoneNumber) {
        return request("requestLoginOtp", "POST", {
            phoneNumber: phoneNumber
        });
    }

    async function loginWithOtp(phoneNumber, otpCode) {
        setState({
            status: STATUS.LOGGING_IN,
            loggedIn: false,
            user: null,
            error: ""
        });

        var response = await request("verifyLoginOtp", "POST", {
            phoneNumber: phoneNumber,
            otpCode: otpCode
        });

        if (response && response.success && response.loggedIn && response.user) {
            applyAuthenticatedState(response);
            resolveReady();
            return snapshot();
        }

        setState({
            status: STATUS.LOGIN_ERROR,
            loggedIn: false,
            user: null,
            error: (response && response.error) || "OTP login failed."
        });

        resolveReady();
        return snapshot();
    }

    async function requestPhoneEnrollOtp(phoneNumber) {
        return request("requestPhoneEnrollOtp", "POST", {
            phoneNumber: phoneNumber
        });
    }

    async function verifyPhoneEnrollOtp(phoneNumber, otpCode) {
        var response = await request("verifyPhoneEnrollOtp", "POST", {
            phoneNumber: phoneNumber,
            otpCode: otpCode
        });
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function setPhoneLoginEnabled(enabled) {
        var response = await request("setPhoneLoginEnabled", "POST", {
            enabled: enabled ? "1" : "0"
        });
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function dismissPhoneNudge() {
        var response = await request("dismissPhoneNudge", "POST", {});
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function smsStatus() {
        return request("smsStatus", "GET");
    }

    async function saveSmsConfig(payload) {
        return request("saveSmsConfig", "POST", payload || {});
    }

    async function smsHealthCheck(phoneNumber) {
        var payload = {};
        if (typeof phoneNumber === "string" && phoneNumber.trim()) {
            payload.phoneNumber = phoneNumber.trim();
        }
        return request("smsHealthCheck", "POST", payload);
    }

    async function logout() {
        setState({
            status: STATUS.LOGGING_OUT,
            loggedIn: state.loggedIn,
            user: state.user,
            error: ""
        });

        try {
            await request("logout", "POST", {});
        } finally {
            applyLoggedOutState(STATUS.LOGGED_OUT, "");
        }

        return snapshot();
    }

    function markUnauthorized(errorText) {
        applyLoggedOutState(STATUS.UNAUTHORIZED, errorText || "Authentication required.");
        resolveReady();
        return snapshot();
    }

    function isUnauthorizedPayload(payload) {
        if (!payload || typeof payload !== "object") {
            return false;
        }

        return !!payload.loggedOut || payload.httpStatus === 401;
    }

    function handleUnauthorizedPayload(payload, fallbackError) {
        if (!isUnauthorizedPayload(payload)) {
            return false;
        }

        markUnauthorized((payload && payload.error) || fallbackError || "Authentication required.");
        return true;
    }

    function onChange(listener) {
        if (typeof listener !== "function") {
            return function () {};
        }

        listeners.push(listener);
        listener(snapshot());

        return function () {
            listeners = listeners.filter(function (item) {
                return item !== listener;
            });
        };
    }

    window.Dent1402Auth = {
        STATUS: STATUS,
        bootstrap: bootstrap,
        ready: function () {
            return readyPromise;
        },
        login: login,
        requestLoginOtp: requestLoginOtp,
        loginWithOtp: loginWithOtp,
        logout: logout,
        requestPhoneEnrollOtp: requestPhoneEnrollOtp,
        verifyPhoneEnrollOtp: verifyPhoneEnrollOtp,
        setPhoneLoginEnabled: setPhoneLoginEnabled,
        dismissPhoneNudge: dismissPhoneNudge,
        smsStatus: smsStatus,
        saveSmsConfig: saveSmsConfig,
        smsHealthCheck: smsHealthCheck,
        markUnauthorized: markUnauthorized,
        isUnauthorizedPayload: isUnauthorizedPayload,
        handleUnauthorizedPayload: handleUnauthorizedPayload,
        getState: snapshot,
        getCurrentUser: function () {
            return clone(state.user);
        },
        onChange: onChange,
        patchCurrentUser: patchCurrentUser,
        loginUrl: loginUrl,
        currentReturnTo: currentReturnTo
    };

    emit();
    bootstrap(false);
})();
