(function () {
  "use strict";

  var BLANK_AVATAR_DATA_URL = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn0K1sAAAAASUVORK5CYII=";

  function asObject(value) {
    return value && typeof value === "object" && !Array.isArray(value) ? value : null;
  }

  function toText(value) {
    return String(value == null ? "" : value);
  }

  function escapeHtml(value) {
    return toText(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatNavBadgeCount(value) {
    var count = Math.max(0, Math.floor(toNumber(value, 0)));
    if (count <= 0) return "";
    if (count > 9) return "۹+";
    return count.toLocaleString("fa-IR");
  }

  function normalizeSpace(value) {
    return toText(value).replace(/\s+/g, " ").trim();
  }

  function normalizeDigits(value) {
    return toText(value).replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
      var code = char.charCodeAt(0);
      if (code >= 0x06F0 && code <= 0x06F9) {
        return String(code - 0x06F0);
      }
      if (code >= 0x0660 && code <= 0x0669) {
        return String(code - 0x0660);
      }
      return char;
    });
  }

  function normalizeStudentNumber(value) {
    var normalized = normalizeSpace(normalizeDigits(value));
    if (!normalized) return "";
    var digitsOnly = normalized.replace(/\D+/g, "");
    return digitsOnly || normalized;
  }

  function avatarLabel(value) {
    var clean = normalizeSpace(value);
    if (!clean) return "?";
    var parts = clean.split(" ").filter(Boolean);
    if (parts.length === 1) {
      return parts[0].slice(0, 1).toUpperCase();
    }
    return (parts[0].slice(0, 1) + parts[1].slice(0, 1)).toUpperCase();
  }

  function snippet(value, maxLen) {
    var clean = normalizeSpace(value);
    if (clean.length <= maxLen) return clean;
    return clean.slice(0, maxLen - 1) + "…";
  }

  function clamp(number, min, max) {
    return Math.max(min, Math.min(max, number));
  }

  function toNumber(value, fallback) {
    var num = Number(value);
    return Number.isFinite(num) ? num : fallback;
  }

  function formatTime(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      var date = new Date(n * 1000);
      var now = new Date();
      var sameDay = date.getFullYear() === now.getFullYear()
        && date.getMonth() === now.getMonth()
        && date.getDate() === now.getDate();
      if (sameDay) {
        return date.toLocaleTimeString("fa-IR-u-ca-persian", { hour: "2-digit", minute: "2-digit", hour12: false });
      }

      var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
      var isYesterday = date.getFullYear() === yesterday.getFullYear()
        && date.getMonth() === yesterday.getMonth()
        && date.getDate() === yesterday.getDate();
      if (isYesterday) {
        return "دیروز";
      }

      if (date.getFullYear() === now.getFullYear()) {
        return date.toLocaleDateString("fa-IR-u-ca-persian", { month: "short", day: "numeric" });
      }

      return date.toLocaleDateString("fa-IR-u-ca-persian", { year: "numeric", month: "short", day: "numeric" });
    } catch (error) {
      return "";
    }
  }

  function formatClock(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      return new Date(n * 1000).toLocaleTimeString("fa-IR-u-ca-persian", { hour: "2-digit", minute: "2-digit", hour12: false });
    } catch (error) {
      return "";
    }
  }

  function formatDate(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      return new Date(n * 1000).toLocaleDateString("fa-IR-u-ca-persian", { month: "short", day: "numeric" });
    } catch (error) {
      return "";
    }
  }

  function formatDateTime(ts) {
    var date = formatDate(ts);
    var clock = formatClock(ts);
    return [date, clock].filter(Boolean).join(" ");
  }

  function formatLastSeenLabel(ts) {
    var n = Math.max(0, Math.floor(toNumber(ts, 0)));
    if (!n) return "";
    var now = Math.floor(Date.now() / 1000);
    var delta = Math.max(0, now - n);
    if (delta < 60) return "لحظاتی پیش";
    if (delta < 3600) return Math.max(1, Math.floor(delta / 60)).toLocaleString("fa-IR") + " دقیقه پیش";
    if (delta < 21600) return formatClock(n);
    return formatDateTime(n);
  }

  function dayKeyFromTimestamp(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    var date = new Date(n * 1000);
    if (Number.isNaN(date.getTime())) return "";
    return [
      String(date.getFullYear()),
      String(date.getMonth() + 1).padStart(2, "0"),
      String(date.getDate()).padStart(2, "0")
    ].join("-");
  }

  function formatFileSize(bytes) {
    var size = Math.max(0, Math.floor(toNumber(bytes, 0)));
    if (!size) return "0 B";
    if (size < 1024) return size + " B";
    if (size < (1024 * 1024)) return (size / 1024).toFixed(1) + " KB";
    if (size < (1024 * 1024 * 1024)) return (size / (1024 * 1024)).toFixed(1) + " MB";
    return (size / (1024 * 1024 * 1024)).toFixed(1) + " GB";
  }

  function formatDuration(seconds) {
    var total = Math.max(0, Math.floor(toNumber(seconds, 0)));
    var mins = Math.floor(total / 60);
    var secs = total % 60;
    return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
  }

  function voiceSpeedLabel(rate) {
    var normalized = Math.max(1, toNumber(rate, 1));
    return (Math.round(normalized * 10) / 10).toLocaleString("fa-IR") + "x";
  }

  function stableHashSeed(value) {
    var text = toText(value);
    var hash = 0;
    for (var i = 0; i < text.length; i += 1) {
      hash = ((hash << 5) - hash) + text.charCodeAt(i);
      hash |= 0;
    }
    return Math.abs(hash) || 1;
  }

  function seededWaveformSamples(seed, count) {
    var total = Math.max(6, Math.floor(toNumber(count, 0)));
    var stateSeed = stableHashSeed(seed);
    var samples = [];
    for (var i = 0; i < total; i += 1) {
      stateSeed = (stateSeed * 1664525 + 1013904223) >>> 0;
      var noise = (stateSeed / 4294967295);
      var swing = 0.28 + (Math.sin((i + 1) * 0.62 + noise * 2.8) * 0.22);
      samples.push(clamp(0.22 + noise * 0.56 + swing, 0.2, 0.96));
    }
    return samples;
  }

  function normalizeAttachmentCategory(value) {
    var category = normalizeSpace(value).toLowerCase();
    if (
      category === "image" ||
      category === "video" ||
      category === "audio" ||
      category === "voice" ||
      category === "document" ||
      category === "pdf" ||
      category === "office" ||
      category === "archive"
    ) {
      return category;
    }
    return "file";
  }

  function attachmentCategoryLabel(category) {
    switch (normalizeAttachmentCategory(category)) {
      case "image":
        return "تصویر";
      case "video":
        return "ویدیو";
      case "audio":
        return "فایل صوتی";
      case "voice":
        return "پیام صوتی";
      case "document":
        return "سند";
      case "pdf":
        return "PDF";
      case "office":
        return "Office";
      case "archive":
        return "آرشیو";
      default:
        return "فایل";
    }
  }

  function isGeneratedMessagePlaceholder(message, text) {
    var normalizedText = normalizeSpace(text);
    if (!normalizedText) return false;
    var hasAttachments = !!(message && Array.isArray(message.attachments) && message.attachments.length);
    var kind = normalizeSpace(message && message.kind || "text");
    if (hasAttachments && normalizedText.toLowerCase() === "attachment") {
      return true;
    }
    if ((kind === "poll" || !!(message && message.poll)) && normalizedText.toLowerCase() === "poll") {
      return true;
    }
    if (message && message.meta && (normalizedText === "RouteCard" || normalizedText === "TaskReminder")) {
      return true;
    }
    return false;
  }

  function meaningfulMessageText(message) {
    if (!message) return "";
    var text = toText(message.text);
    if (!text) return "";
    return isGeneratedMessagePlaceholder(message, text) ? "" : normalizeSpace(text);
  }

  function normalizeAvatarUrl(value) {
    var clean = toText(value).trim();
    if (!clean) return "";
    if (clean === BLANK_AVATAR_DATA_URL) return "";
    if (clean.indexOf("data:image/") === 0) return clean;
    if (clean.charAt(0) === "/") return clean;
    if (/^https?:\/\//i.test(clean)) return clean;
    return "";
  }

  window.Dent1402ChatUtils = Object.freeze({
    BLANK_AVATAR_DATA_URL: BLANK_AVATAR_DATA_URL,
    asObject: asObject,
    toText: toText,
    escapeHtml: escapeHtml,
    formatNavBadgeCount: formatNavBadgeCount,
    normalizeSpace: normalizeSpace,
    normalizeDigits: normalizeDigits,
    normalizeStudentNumber: normalizeStudentNumber,
    avatarLabel: avatarLabel,
    snippet: snippet,
    clamp: clamp,
    toNumber: toNumber,
    formatTime: formatTime,
    formatClock: formatClock,
    formatDate: formatDate,
    formatDateTime: formatDateTime,
    formatLastSeenLabel: formatLastSeenLabel,
    dayKeyFromTimestamp: dayKeyFromTimestamp,
    formatFileSize: formatFileSize,
    formatDuration: formatDuration,
    voiceSpeedLabel: voiceSpeedLabel,
    stableHashSeed: stableHashSeed,
    seededWaveformSamples: seededWaveformSamples,
    normalizeAttachmentCategory: normalizeAttachmentCategory,
    attachmentCategoryLabel: attachmentCategoryLabel,
    isGeneratedMessagePlaceholder: isGeneratedMessagePlaceholder,
    meaningfulMessageText: meaningfulMessageText,
    normalizeAvatarUrl: normalizeAvatarUrl
  });
}());
