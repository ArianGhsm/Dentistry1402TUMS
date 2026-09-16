(function () {
    "use strict";

    function normalizeDigits(value) {
        var text = String(value || "").trim();
        if (!text) {
            return "";
        }
        return text
            .replace(/[\u06F0-\u06F9]/g, function (ch) {
                return String("\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".indexOf(ch));
            })
            .replace(/[\u0660-\u0669]/g, function (ch) {
                return String("\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669".indexOf(ch));
            });
    }

    function toPersianDigits(value) {
        return String(value || "").replace(/[0-9]/g, function (ch) {
            return "\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".charAt(Number(ch));
        });
    }

    function normalizedPhone(value) {
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) return "";
        if (digits.indexOf("0098") === 0 && digits.length >= 14) {
            return "0" + digits.slice(4);
        }
        if (digits.indexOf("98") === 0 && digits.length >= 12) {
            return "0" + digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === "9") {
            return "0" + digits;
        }
        return digits;
    }

    function isValidIranMobile(value) {
        return /^09\d{9}$/.test(normalizedPhone(value));
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case '"':
                    return "&quot;";
                case "'":
                    return "&#39;";
                default:
                    return char;
            }
        });
    }

    function toNumber(value, fallback) {
        var num = Number(value);
        return Number.isFinite(num) ? num : fallback;
    }

    function nowSeconds() {
        return Math.floor(Date.now() / 1000);
    }

    function secondsRemaining(targetEpoch) {
        var left = Math.max(0, Math.floor(toNumber(targetEpoch, 0) - nowSeconds()));
        return left;
    }

    function formatSeconds(seconds) {
        var total = Math.max(0, Math.floor(toNumber(seconds, 0)));
        var mins = Math.floor(total / 60);
        var secs = total % 60;
        return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
    }

    function parseTimestampLike(value) {
        var raw = String(value == null ? "" : value).trim();
        if (!raw) {
            return null;
        }

        var direct = new Date(raw);
        if (Number.isFinite(direct.getTime())) {
            return direct;
        }

        var numeric = Number(raw);
        if (!Number.isFinite(numeric)) {
            return null;
        }

        if (Math.abs(numeric) < 1000000000000) {
            numeric = numeric * 1000;
        }

        var parsed = new Date(numeric);
        return Number.isFinite(parsed.getTime()) ? parsed : null;
    }

    function formatJalaliDateTime(value, fallback, includeSeconds) {
        var raw = String(value == null ? "" : value).trim();
        if (!raw) {
            return fallback || "—";
        }

        var parsed = parseTimestampLike(raw);
        if (!parsed) {
            return raw;
        }

        return parsed.toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: includeSeconds ? "2-digit" : undefined,
            hour12: false
        });
    }

    function ltrIsolateText(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return "";
        }
        return "\u2066" + clean + "\u2069";
    }

    function ltrMaskedPhone(value, fallback) {
        var clean = String(value || "").trim();
        if (!clean) {
            return fallback || "";
        }
        return ltrIsolateText(clean);
    }

    function userDisNumber(user) {
        return String((user && user.disNumber) || "").trim();
    }

    window.Dent1402AccountUtils = Object.freeze({
        normalizeDigits: normalizeDigits,
        toPersianDigits: toPersianDigits,
        normalizedPhone: normalizedPhone,
        isValidIranMobile: isValidIranMobile,
        escapeHtml: escapeHtml,
        toNumber: toNumber,
        nowSeconds: nowSeconds,
        secondsRemaining: secondsRemaining,
        formatSeconds: formatSeconds,
        parseTimestampLike: parseTimestampLike,
        formatJalaliDateTime: formatJalaliDateTime,
        ltrIsolateText: ltrIsolateText,
        ltrMaskedPhone: ltrMaskedPhone,
        userDisNumber: userDisNumber
    });
}());
