(function () {
    "use strict";

    var listeners = [];
    var readyResolved = false;
    var readyResolve = null;
    var bootPromise = null;
    var state = {
        status: "restoring",
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
        state = {
            status: nextState.status || state.status,
            loggedIn: !!nextState.loggedIn,
            user: nextState.user ? clone(nextState.user) : null,
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
                error: "???? ???? ??????? ???."
            };
        }

        data.httpStatus = response.status;
        return data;
    }

    function applyAuthenticatedState(response) {
        var user = response && response.user ? response.user : null;
        var nextStatus = response && response.status ? response.status : (user && user.isOwner ? "logged-in-admin" : "logged-in");

        setState({
            status: nextStatus,
            loggedIn: !!user,
            user: user,
            error: ""
        });
    }

    function applyLoggedOutState(nextStatus, errorText) {
        setState({
            status: nextStatus || "logged-out",
            loggedIn: false,
            user: null,
            error: errorText || ""
        });
    }

    function patchCurrentUser(user, nextStatus) {
        if (!user) {
            applyLoggedOutState("logged-out", "");
            return snapshot();
        }

        setState({
            status: nextStatus || determineStatusFromUser(user),
            loggedIn: true,
            user: user,
            error: ""
        });

        resolveReady();
        return snapshot();
    }

    function determineStatusFromUser(user) {
        if (user && user.isOwner) {
            return "logged-in-admin";
        }

        return "logged-in";
    }

    async function bootstrap(force) {
        if (bootPromise && !force) {
            return bootPromise;
        }

        setState({
            status: "restoring",
            loggedIn: state.loggedIn,
            user: state.user,
            error: ""
        });

        bootPromise = request("me", "GET").then(function (response) {
            if (response && response.loggedIn && response.user) {
                applyAuthenticatedState(response);
            } else {
                applyLoggedOutState("logged-out", "");
            }

            resolveReady();
            return snapshot();
        }).catch(function () {
            applyLoggedOutState("logged-out", "??????? ???? ????? ???.");
            resolveReady();
            return snapshot();
        });

        return bootPromise;
    }

    async function login(studentNumber, password) {
        setState({
            status: "logging-in",
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
            status: "login-error",
            loggedIn: false,
            user: null,
            error: (response && response.error) || "???? ????? ???."
        });

        resolveReady();
        return snapshot();
    }

    async function logout() {
        setState({
            status: "logging-out",
            loggedIn: state.loggedIn,
            user: state.user,
            error: ""
        });

        try {
            await request("logout", "POST", {});
        } finally {
            applyLoggedOutState("logged-out", "");
        }

        return snapshot();
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
        bootstrap: bootstrap,
        ready: function () {
            return readyPromise;
        },
        login: login,
        logout: logout,
        getState: snapshot,
        onChange: onChange,
        patchCurrentUser: patchCurrentUser,
        loginUrl: loginUrl,
        currentReturnTo: currentReturnTo
    };

    emit();
    bootstrap(false);
})();
