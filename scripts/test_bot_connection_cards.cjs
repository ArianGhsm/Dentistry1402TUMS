const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const source = fs.readFileSync(path.join(__dirname, "../public_html/assets/site/scripts/account.js"), "utf8");
const from = source.indexOf("    function botPlatformMeta(");
const until = source.indexOf("    function renderBotConnections()", from);
assert(from !== -1 && until !== -1, "Missing account connection renderers");
const state = { disconnectingPlatform: "" };
const escapeHtml = value => String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
const toPersianDigits = value => String(value).replace(/[0-9]/g, d => "۰۱۲۳۴۵۶۷۸۹"[Number(d)]);
const formatJalaliDateTime = value => value || "—";
const render = new Function("escapeHtml", "toPersianDigits", "formatJalaliDateTime", "botConnectionsState",
    source.slice(from, until) + "\nreturn botConnectionCard;")(escapeHtml, toPersianDigits, formatJalaliDateTime, state);

const oldAccount = { platformUserId: "111111111", platformDisplayName: "", platformUsername: "",
    linkedAt: "۱۴۰۵/۰۸/۰۱" };
const second = { platformUserId: "222222222", platformDisplayName: "Mohamadreza",
    platformUsername: "life_ofme04", linkedAt: "۱۴۰۵/۰۸/۱۸" };
const two = render({ connected: true, websiteName: "دانشجو", accounts: [oldAccount, second] }, "telegram");
assert(two.includes("۲ اکانت متصل"), "Two-account summary should use Persian digits");
assert(two.includes("اکانت ۱") && two.includes("اکانت ۲"), "Two distinct account blocks");
assert(two.includes("@life_ofme04") && two.includes("اتصال دوم"), "Secondary username and position shown");
assert.equal((two.match(/data-disconnect-bot="telegram"/g) || []).length, 2, "Distinct disconnect buttons");
assert(two.includes('data-platform-user-id="111111111"') && two.includes('data-platform-user-id="222222222"'), "Scoped disconnect identities");
assert(!two.includes("undefined") && !two.includes("Term 7") && !two.includes("Status"), "No leaked raw UI labels");
const one = render({connected: true, ...oldAccount, accounts: [oldAccount]}, "telegram");
assert(one.includes("۱ اکانت متصل") && !one.includes("اکانت ۲"), "Single account remains simple");
const empty = render({ connected: false, accounts: [] }, "bale");
assert(empty.includes("متصل نیست") && !empty.includes("data-disconnect-bot"), "Empty Bale state");
state.disconnectingPlatform = "telegram:222222222";
const busy = render({connected: true, accounts: [oldAccount, second]}, "telegram");
assert(busy.includes("در حال قطع اتصال…") && (busy.match(/disabled/g) || []).length === 2, "Busy state locks both controls");
state.disconnectingPlatform = "";
const unsafe = render({connected: true, accounts: [{...second, platformDisplayName: '<script>test</script>'}]}, "telegram");
assert(!unsafe.includes("<script>") && unsafe.includes("&lt;script&gt;"), "Profile text is escaped");
console.log("PASS: dual Telegram account UI — single, dual, empty, busy, incomplete and hostile profile states");
