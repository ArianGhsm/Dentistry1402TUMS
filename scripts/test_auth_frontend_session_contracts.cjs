const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const ROOT = path.resolve(__dirname, "..");
const AUTH_PATH = path.join(ROOT, "public_html/assets/site/scripts/auth.js");
const SW_PATH = path.join(ROOT, "public_html/sw.js");
const HTACCESS_PATH = path.join(ROOT, "public_html/.htaccess");
const AUTH_SOURCE = fs.readFileSync(AUTH_PATH, "utf8");

function response(payload, status = 200) {
    return {
        status,
        json: async () => payload
    };
}

async function runBootstrap({ cachedState, replies }) {
    let stored = cachedState ? JSON.stringify(cachedState) : null;
    let calls = 0;
    const localStorage = {
        getItem: () => stored,
        setItem: (_key, value) => {
            stored = String(value);
        }
    };
    const document = {
        readyState: "loading",
        addEventListener() {},
        querySelectorAll() { return []; }
    };
    function CustomEvent(type, options) {
        this.type = type;
        this.detail = options && options.detail;
    }
    const fastTimeout = (callback, delay) => {
        if (Number(delay) >= 10000) {
            return 1;
        }
        queueMicrotask(callback);
        return 2;
    };
    const context = {
        AbortController,
        Array,
        Boolean,
        CustomEvent,
        Date,
        JSON,
        Math,
        Number,
        Object,
        Promise,
        RegExp,
        String,
        URL,
        URLSearchParams,
        clearTimeout() {},
        console,
        decodeURIComponent,
        document,
        encodeURIComponent,
        fetch: async () => {
            const next = replies[Math.min(calls, replies.length - 1)];
            calls += 1;
            if (next instanceof Error) {
                throw next;
            }
            return response(next.payload, next.status || 200);
        },
        localStorage,
        location: {
            hash: "",
            href: "https://dentistry1402tums.ir/app/",
            pathname: "/app/",
            search: ""
        },
        navigator: {},
        queueMicrotask,
        setTimeout: fastTimeout,
        dispatchEvent() {},
        addEventListener() {}
    };
    context.window = context;
    context.window.localStorage = localStorage;
    context.window.location = context.location;
    context.window.navigator = context.navigator;
    context.window.setTimeout = fastTimeout;

    vm.runInNewContext(AUTH_SOURCE, context, { filename: AUTH_PATH });
    await context.Dent1402Auth.ready();
    await new Promise((resolve) => setImmediate(resolve));

    return {
        calls,
        state: context.Dent1402Auth.getState(),
        stored: stored ? JSON.parse(stored) : null
    };
}

(async () => {
    const cachedUser = {
        loggedIn: true,
        user: { studentNumber: "40200000000", name: "Cached User", role: "student" },
        availableCohorts: []
    };
    const loggedOut = { payload: { success: true, loggedIn: false, status: "logged-out" } };

    const expired = await runBootstrap({ cachedState: cachedUser, replies: [loggedOut] });
    assert.strictEqual(expired.calls, 5, "expired cached sessions must be canonically rechecked");
    assert.strictEqual(expired.state.loggedIn, false, "a confirmed expired session must clear frontend auth state");
    assert.strictEqual(expired.stored.loggedIn, false, "a confirmed expired session must clear the persisted auth cache");

    const transient = await runBootstrap({
        cachedState: cachedUser,
        replies: [new Error("temporary network failure")]
    });
    assert.strictEqual(transient.state.loggedIn, true, "a transient auth check failure must preserve a cached session");
    assert.strictEqual(transient.stored.loggedIn, true, "a transient auth check failure must not erase the auth cache");

    const refreshedUser = { studentNumber: "40200000000", name: "Fresh User", role: "student" };
    const recovered = await runBootstrap({
        cachedState: cachedUser,
        replies: [
            loggedOut,
            { payload: { success: true, loggedIn: true, user: refreshedUser, availableCohorts: [] } }
        ]
    });
    assert.strictEqual(recovered.state.loggedIn, true, "a valid recheck must restore the canonical session");
    assert.strictEqual(recovered.state.user.name, "Fresh User", "a valid recheck must replace stale cached user data");

    const freshLoggedOut = await runBootstrap({ cachedState: null, replies: [loggedOut] });
    assert.strictEqual(freshLoggedOut.state.loggedIn, false, "a browser without cached auth must accept logged-out state");

    const sw = fs.readFileSync(SW_PATH, "utf8");
    assert(sw.includes('const AUTH_RUNTIME_PATH = "/assets/site/scripts/auth.js";'), "service worker must define canonical auth runtime");
    assert(sw.includes('AUTH_RUNTIME_PATH + "?v=" + APP_VERSION'), "service worker install cache must include the current auth runtime");
    assert(/CANONICAL_RUNTIME_PATHS\s*=\s*\[[\s\S]*AUTH_RUNTIME_PATH/.test(sw), "legacy auth query tokens must resolve to the canonical runtime");

    const htaccess = fs.readFileSync(HTACCESS_PATH, "utf8");
    assert(/\^\(core\|shell\|pwa\|auth\|chat\)/.test(htaccess), "auth.js must revalidate instead of remaining immutable under a stale query token");

    console.log("OK: frontend auth preserves transient sessions, clears confirmed expiry, and ships canonically.");
})().catch((error) => {
    console.error(error && error.stack ? error.stack : error);
    process.exit(1);
});
