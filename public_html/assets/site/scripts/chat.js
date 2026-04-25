(function () {
  "use strict";

  if (!window.Dent1402Auth) {
    return;
  }

  var QUICK_REACTIONS = [
    "\u{1F44D}", "\u2764\uFE0F", "\u{1F602}", "\u{1F525}", "\u{1F44F}",
    "\u{1F62E}", "\u{1F44E}", "\u{1F60D}", "\u{1F914}", "\u{1F389}",
    "\u{1F44C}", "\u{1F64F}"
  ];
  var REACTION_LIBRARY = [
    "\u{1F44D}", "\u{1F44E}", "\u2764\uFE0F", "\u{1F9E1}", "\u{1F49A}", "\u{1F499}",
    "\u{1F49C}", "\u{1F5A4}", "\u{1F4AF}", "\u{1F525}", "\u{1F389}", "\u{1F4A5}",
    "\u{1F44F}", "\u{1F64F}", "\u{1F64C}", "\u{1F44C}", "\u{1F91D}", "\u{1F4AA}",
    "\u{1F680}", "\u{1F6A8}", "\u{1F600}", "\u{1F603}", "\u{1F604}", "\u{1F601}",
    "\u{1F606}", "\u{1F605}", "\u{1F923}", "\u{1F602}", "\u{1F642}", "\u{1F60D}",
    "\u{1F970}", "\u{1F60E}", "\u{1F914}", "\u{1F609}", "\u{1F62E}", "\u{1F632}",
    "\u{1F621}", "\u{1F622}", "\u{1F62D}", "\u{1F97A}", "\u{1F44A}", "\u{1F44B}",
    "\u{1F31F}", "\u{1F308}", "\u{1F3AF}", "\u{1F3C6}", "\u{1F91F}", "\u{270C}\uFE0F",
    "\u{1F91E}", "\u{1F90C}", "\u{1F937}", "\u{1F90D}", "\u{1F607}", "\u{1F915}",
    "\u{1F973}", "\u{1F92F}", "\u{1FAE1}", "\u{1FAE0}", "\u{1FAE2}", "\u{1F90D}",
    "\u{1F618}", "\u{1F61A}", "\u{1F61C}", "\u{1F61D}", "\u{1F910}", "\u{1F917}",
    "\u{1F92D}", "\u{1F976}", "\u{1F975}", "\u{1F637}", "\u{1F631}", "\u{1F62F}",
    "\u{1F633}", "\u{1F62B}", "\u{1F62A}", "\u{1F644}", "\u{1F643}", "\u{1F60F}",
    "\u{1F611}", "\u{1F610}", "\u{1F612}", "\u{1F914}", "\u{1F90F}", "\u{1F919}",
    "\u{1F596}", "\u{1F450}", "\u{1FAF6}", "\u{1F90C}", "\u{1F927}", "\u{1F974}",
    "\u{1F436}", "\u{1F431}", "\u{1F43C}", "\u{1F98A}", "\u{1F981}", "\u{1F984}",
    "\u{1F42F}", "\u{1F437}", "\u{1F98B}", "\u{1F355}", "\u{1F354}", "\u{1F356}",
    "\u{1F35F}", "\u{1F370}", "\u{1F369}", "\u{1F366}", "\u{1F37F}", "\u{1F95E}",
    "\u{1F964}", "\u{2615}", "\u{1F37A}", "\u{1F9CB}", "\u{1F3C1}", "\u{1F3C5}",
    "\u{1F3C6}", "\u{1F947}", "\u{1F948}", "\u{1F949}", "\u{1F451}", "\u{1F4A1}",
    "\u{1F4AA}", "\u{26A1}", "\u{2600}\uFE0F", "\u{1F319}", "\u{2744}\uFE0F", "\u{2B50}",
    "\u{1F680}", "\u{1F6F8}", "\u{1F6E1}\uFE0F", "\u{1F4E2}", "\u{1F4CC}", "\u{1F4A3}"
  ];
  var REACTIONS = Array.from(new Set(QUICK_REACTIONS.concat(REACTION_LIBRARY)));
  var REACTION_GROUPS = [
    { id: "recent", label: "اخیر", emojis: [] },
    { id: "popular", label: "پرکاربرد", emojis: QUICK_REACTIONS.slice() },
    {
      id: "smileys",
      label: "حالت‌ها",
      emojis: ["\u{1F600}", "\u{1F603}", "\u{1F604}", "\u{1F601}", "\u{1F606}", "\u{1F602}", "\u{1F923}", "\u{1F642}", "\u{1F60D}", "\u{1F609}", "\u{1F914}", "\u{1F92F}", "\u{1F62E}", "\u{1F622}", "\u{1F62D}"]
    },
    {
      id: "gestures",
      label: "ژست‌ها",
      emojis: ["\u{1F44D}", "\u{1F44E}", "\u{1F44F}", "\u{1F64F}", "\u{1F64C}", "\u{1F44C}", "\u{1F91D}", "\u{1F4AA}", "\u{270C}\uFE0F", "\u{1F91F}", "\u{1F90C}", "\u{1F44A}", "\u{1F44B}"]
    },
    {
      id: "hearts",
      label: "قلب",
      emojis: ["\u2764\uFE0F", "\u{1F9E1}", "\u{1F49A}", "\u{1F499}", "\u{1F49C}", "\u{1F5A4}", "\u{1F970}", "\u{1F90D}"]
    },
    {
      id: "symbols",
      label: "نمادها",
      emojis: ["\u{1F4AF}", "\u{1F525}", "\u{1F389}", "\u{1F4A5}", "\u{1F680}", "\u{1F31F}", "\u{1F308}", "\u{1F3AF}", "\u{1F3C6}", "\u{1F607}", "\u{1F973}"]
    },
    {
      id: "nature",
      label: "طبیعت",
      emojis: ["\u{2600}\uFE0F", "\u{1F319}", "\u{2B50}", "\u{1F308}", "\u{1F525}", "\u{2744}\uFE0F", "\u{1F436}", "\u{1F431}", "\u{1F43C}", "\u{1F98A}"]
    },
    {
      id: "food",
      label: "خوراکی",
      emojis: ["\u{1F355}", "\u{1F354}", "\u{1F35F}", "\u{1F95E}", "\u{1F37F}", "\u{1F370}", "\u{1F369}", "\u{1F366}", "\u{2615}", "\u{1F37A}"]
    },
    {
      id: "activity",
      label: "فعالیت",
      emojis: ["\u{1F3AF}", "\u{1F3C6}", "\u{1F3C1}", "\u{1F3C5}", "\u{1F947}", "\u{1F948}", "\u{1F949}", "\u{1F4AA}", "\u{1F389}", "\u{1F38A}"]
    }
  ];
  var REACTION_SEARCH_ALIASES = {
    "\u{1F44D}": ["thumbs up", "like", "good", "ok", "عالی", "خوبه", "تایید"],
    "\u{1F44E}": ["thumbs down", "dislike", "bad", "not ok", "بد", "نپسندیدن"],
    "\u2764\uFE0F": ["heart", "love", "care", "عشق", "دوستت دارم", "قلب"],
    "\u{1F602}": ["laugh", "lol", "funny", "خنده", "باحال", "خنده‌دار"],
    "\u{1F62D}": ["cry", "sad", "tears", "غم", "ناراحت", "گریه"],
    "\u{1F60D}": ["love eyes", "adore", "wow", "عاشق", "عالیه", "واو"],
    "\u{1F914}": ["thinking", "hmm", "consider", "فکر", "نامطمئن", "مردد"],
    "\u{1F62E}": ["surprised", "wow", "shock", "تعجب", "شوکه", "وااا"],
    "\u{1F525}": ["fire", "lit", "hot", "آتیش", "خفن", "داغ"],
    "\u{1F389}": ["party", "celebrate", "congrats", "تبریک", "جشن", "آفرین"],
    "\u{1F4AF}": ["hundred", "perfect", "full", "صد", "کامل", "درسته"],
    "\u{1F64F}": ["pray", "please", "thanks", "دعا", "ممنون", "سپاس"],
    "\u{1F44F}": ["clap", "applause", "bravo", "دست", "تشویق", "آفرین"],
    "\u{1F44C}": ["ok hand", "perfect", "nice", "اوکی", "خوبه", "اوک"],
    "\u{1F90C}": ["pinched fingers", "wait", "چی", "صبر", "یعنی چی", "حرف حساب"],
    "\u{1F44A}": ["punch", "fight", "power", "مشت", "قدرت", "بزن بریم"],
    "\u{1F44B}": ["wave", "hello", "bye", "سلام", "خداحافظ", "دست تکان"],
    "\u{1F31F}": ["glow", "star", "shine", "درخشان", "ستاره", "نور"],
    "\u{1F308}": ["rainbow", "color", "hope", "رنگی", "رنگین‌کمان", "امید"],
    "\u{1F3AF}": ["target", "focus", "goal", "هدف", "مستقیم", "درست"],
    "\u{1F3C6}": ["trophy", "win", "champion", "برد", "قهرمان", "جام"],
    "\u{1F9E1}": ["orange heart", "warm", "care", "قلب نارنجی", "محبت", "دوستی"],
    "\u{1F49A}": ["green heart", "peace", "nature", "قلب سبز", "آرامش", "طبیعت"],
    "\u{1F499}": ["blue heart", "trust", "calm", "قلب آبی", "اعتماد", "آرام"],
    "\u{1F49C}": ["purple heart", "support", "kind", "قلب بنفش", "حمایت", "مهربانی"],
    "\u{1F5A4}": ["black heart", "dark", "bold", "قلب سیاه", "خاص", "سنگین"],
    "\u{1F970}": ["smiling heart", "affection", "sweet", "مهربان", "لطیف", "محبت"],
    "\u{1F90D}": ["white heart", "pure", "clean", "قلب سفید", "پاک", "بی‌ریا"],
    "\u{1F4AA}": ["muscle", "strong", "power", "قدرت", "قوی", "توان"],
    "\u{1F680}": ["rocket", "launch", "fast", "موشک", "شروع", "سریع"],
    "\u{1F6A8}": ["alarm", "warning", "alert", "هشدار", "اخطار", "توجه"],
    "\u{1F637}": ["mask", "sick", "health", "ماسک", "بیماری", "سلامت"],
    "\u{1F631}": ["scream", "panic", "shock", "وحشت", "ترس", "واکنش شدید"],
    "\u{1F973}": ["party face", "celebrate", "yay", "جشن", "خوشحالی", "تبریک"],
    "\u{1F97A}": ["teary", "emotional", "moved", "احساسی", "اشک شوق", "تحت تاثیر"],
    "\u{1F355}": ["pizza", "food", "snack", "پیتزا", "غذا", "خوراکی"],
    "\u{1F354}": ["burger", "food", "meal", "برگر", "ساندویچ", "غذا"],
    "\u{2615}": ["coffee", "tea", "break", "قهوه", "چای", "استراحت"],
    "\u{1F37A}": ["drink", "cheers", "party", "نوشیدنی", "به سلامتی", "جشن"],
    "\u{1F436}": ["dog", "pet", "animal", "سگ", "حیوان", "پت"],
    "\u{1F431}": ["cat", "pet", "animal", "گربه", "حیوان", "پت"],
    "\u{1F43C}": ["panda", "cute", "animal", "پاندا", "بامزه", "حیوان"],
    "\u{1F98A}": ["fox", "smart", "animal", "روباه", "باهوش", "حیوان"],
    "\u{2600}\uFE0F": ["sun", "bright", "day", "آفتاب", "روشن", "روز"],
    "\u{1F319}": ["moon", "night", "sleep", "ماه", "شب", "خواب"],
    "\u{2744}\uFE0F": ["snow", "cold", "winter", "برف", "سرد", "زمستان"],
    "\u{2B50}": ["star", "favorite", "special", "ستاره", "موردعلاقه", "خاص"]
  };
  var MAX_MESSAGE_SIZE = 2000;
  var MAX_MEDIA_BYTES = 25 * 1024 * 1024;
  var MIN_POLL_MS = 1000;
  var MAX_POLL_MS = 8000;
  var REACTION_RECENTS_LIMIT = 24;
  var REACTION_USAGE_LIMIT = 120;
  var VOICE_MIME_CANDIDATES = [
    "audio/webm;codecs=opus",
    "audio/ogg;codecs=opus",
    "audio/mp4"
  ];

  function $(id) {
    return document.getElementById(id);
  }

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

  function reactionStorageKey(kind) {
    var safeKind = normalizeSpace(kind || "recent") || "recent";
    var safeUser = normalizeStudentNumber(state && state.me && state.me.studentNumber) || "guest";
    return "dent1402-chat-reaction-" + safeKind + "-" + safeUser;
  }

  function saveReactionPreferences() {
    try {
      if (!window.localStorage) return;
      var recentPayload = JSON.stringify(state.recentReactions.slice(0, REACTION_RECENTS_LIMIT));
      window.localStorage.setItem(reactionStorageKey("recent"), recentPayload);

      var usagePayload = Array.from(state.reactionUsage.entries())
        .filter(function (pair) {
          return isLikelyEmoji(pair[0]) && toNumber(pair[1], 0) > 0;
        })
        .sort(function (left, right) {
          return toNumber(right[1], 0) - toNumber(left[1], 0);
        })
        .slice(0, REACTION_USAGE_LIMIT);
      window.localStorage.setItem(reactionStorageKey("usage"), JSON.stringify(usagePayload));
    } catch (error) {
      // Ignore storage failures and keep runtime-only behavior.
    }
  }

  function loadReactionPreferences() {
    state.recentReactions = [];
    state.reactionUsage = new Map();

    try {
      if (!window.localStorage) return;

      var recentRaw = window.localStorage.getItem(reactionStorageKey("recent"));
      if (recentRaw) {
        var parsedRecent = JSON.parse(recentRaw);
        if (Array.isArray(parsedRecent)) {
          state.recentReactions = parsedRecent
            .map(function (item) { return normalizeSpace(item); })
            .filter(function (emoji) { return isLikelyEmoji(emoji); })
            .slice(0, REACTION_RECENTS_LIMIT);
        }
      }

      var usageRaw = window.localStorage.getItem(reactionStorageKey("usage"));
      if (usageRaw) {
        var parsedUsage = JSON.parse(usageRaw);
        if (Array.isArray(parsedUsage)) {
          parsedUsage.forEach(function (entry) {
            if (!Array.isArray(entry) || entry.length < 2) return;
            var emoji = normalizeSpace(entry[0]);
            var score = Math.max(0, Math.floor(toNumber(entry[1], 0)));
            if (!isLikelyEmoji(emoji) || score <= 0) return;
            state.reactionUsage.set(emoji, score);
          });
        }
      }
    } catch (error) {
      state.recentReactions = [];
      state.reactionUsage = new Map();
    }
  }

  function isLikelyEmoji(value) {
    var clean = normalizeSpace(value);
    if (!clean || clean.length > 18) return false;
    return /[\p{Extended_Pictographic}\u2600-\u27BF]/u.test(clean);
  }

  function reactionKeywords(emoji) {
    var key = normalizeSpace(emoji);
    var list = REACTION_SEARCH_ALIASES[key];
    return Array.isArray(list) ? list : [];
  }

  function reactionMatchesQuery(emoji, query) {
    var normalizedEmoji = normalizeSpace(emoji);
    var normalizedQuery = normalizeSpace(query).toLowerCase();
    if (!normalizedQuery) return true;
    if (normalizedEmoji.indexOf(query) !== -1 || normalizedEmoji.indexOf(normalizedQuery) !== -1) {
      return true;
    }
    return reactionKeywords(normalizedEmoji).some(function (keyword) {
      return normalizeSpace(keyword).toLowerCase().indexOf(normalizedQuery) !== -1;
    });
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
      return new Date(n * 1000).toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit" });
    } catch (error) {
      return "";
    }
  }

  function formatDate(ts) {
    var n = toNumber(ts, 0);
    if (!n) return "";
    try {
      return new Date(n * 1000).toLocaleDateString("fa-IR", { month: "short", day: "numeric" });
    } catch (error) {
      return "";
    }
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

  function messagePreviewText(message) {
    if (!message) return "";
    var text = normalizeSpace(message.text);
    if (text) return text;
    if (Array.isArray(message.attachments) && message.attachments.length) {
      if (message.attachments.length === 1) {
        return attachmentCategoryLabel(message.attachments[0].category || "file");
      }
      return message.attachments.length.toLocaleString("fa-IR") + " فایل";
    }
    return "";
  }

  function normalizeAvatarUrl(value) {
    var clean = toText(value).trim();
    if (!clean) return "";
    if (clean.indexOf("data:image/") === 0) return clean;
    if (clean.charAt(0) === "/") return clean;
    if (/^https?:\/\//i.test(clean)) return clean;
    return "";
  }

  function renderAvatar(container, imageNode, fallbackNode, avatarUrl, labelText) {
    if (!container || !imageNode || !fallbackNode) return;
    var safeUrl = normalizeAvatarUrl(avatarUrl);
    fallbackNode.textContent = avatarLabel(labelText);
    if (!safeUrl) {
      container.dataset.hasAvatar = "0";
      imageNode.hidden = true;
      imageNode.removeAttribute("src");
      imageNode.alt = "";
      return;
    }

    container.dataset.hasAvatar = "1";
    imageNode.hidden = false;
    imageNode.alt = labelText ? "تصویر " + labelText : "تصویر کاربر";
    imageNode.onerror = function () {
      container.dataset.hasAvatar = "0";
      imageNode.hidden = true;
      imageNode.removeAttribute("src");
    };
    imageNode.src = safeUrl;
  }

  function setHidden(node, hidden) {
    if (!node) return;
    node.hidden = !!hidden;
  }

  function callIf(fn) {
    if (typeof fn === "function") {
      return fn;
    }
    return null;
  }

  var bootBox = $("boot-box");
  var bootText = $("boot-text");
  var loginBox = $("login-box");
  var chatBox = $("chat-box");
  var msgBox = $("msg");

  var chatApp = $("chat-app");
  var conversationPane = $("conversation-pane");
  var threadPane = $("thread-pane");
  var threadPlaceholder = $("thread-placeholder");
  var threadShell = $("thread-shell");

  var connectionBadge = $("connection-badge");
  var accountBtn = $("account-btn");
  var accountBtnAvatarImage = $("account-btn-avatar-image");
  var accountBtnAvatarFallback = $("account-btn-avatar-fallback");
  var logoutBtn = $("logout-btn");
  var refreshBtn = $("refresh-btn");

  var conversationMeta = $("conversation-meta");
  var conversationSearch = $("conversation-search");
  var conversationFilterTabs = $("conversation-filter-tabs");
  var conversationManageBtn = $("conversation-manage-btn");
  var conversationManageBar = $("conversation-manage-bar");
  var conversationSelectionCount = $("conversation-selection-count");
  var conversationSelectionToggleAll = $("conversation-selection-toggle-all");
  var conversationSelectionClear = $("conversation-selection-clear");
  var conversationBatchRead = $("conversation-batch-read");
  var conversationBatchUnread = $("conversation-batch-unread");
  var conversationBatchPin = $("conversation-batch-pin");
  var conversationBatchUnpin = $("conversation-batch-unpin");
  var conversationBatchArchive = $("conversation-batch-archive");
  var conversationBatchUnarchive = $("conversation-batch-unarchive");
  var conversationBatchMute = $("conversation-batch-mute");
  var conversationBatchUnmute = $("conversation-batch-unmute");
  var conversationBatchDelete = $("conversation-batch-delete");
  var conversationList = $("conversation-list");
  var conversationEmpty = $("conversation-empty");
  var newDmBtn = $("new-dm-btn");
  var newGroupBtn = $("new-group-btn");
  var newPollLink = $("new-poll-link");
  var mobileOpenListBtn = $("mobile-open-list");
  var mobileCloseListBtn = $("mobile-close-list");
  var mobileNewChatFab = $("mobile-new-chat-fab");
  var chatMobileNav = $("chat-mobile-nav");
  var chatNavList = $("chat-nav-list");
  var chatNavCompose = $("chat-nav-compose");
  var chatNavGroup = $("chat-nav-group");
  var chatNavPolls = $("chat-nav-polls");
  var chatNavHome = $("chat-nav-home");
  var chatNavSettings = $("chat-nav-settings");

  var threadInfoTrigger = $("thread-info-trigger");
  var threadAvatar = $("thread-avatar");
  var threadAvatarImage = $("thread-avatar-image");
  var threadAvatarFallback = $("thread-avatar-fallback");
  var threadTitle = $("thread-title");
  var threadSubtitle = $("thread-subtitle");

  var muteBadge = $("mute-badge");
  var pinnedWrap = $("pinned-wrap");
  var pinnedText = $("pinned-text");
  var pinnedActionLabel = $("pinned-action-label");
  var streamStateEl = $("stream-state");
  var messagesEl = $("messages");
  var chatTextEl = $("chat-text");
  var sendBtn = $("send-btn");
  var attachBtn = $("attach-btn");
  var voiceBtn = $("voice-btn");
  var attachmentInput = $("attachment-input");
  var composerUploadSheet = $("composer-upload-sheet");
  var composerUploads = $("composer-uploads");
  var composerVoice = $("composer-voice");
  var composerVoiceTimer = $("composer-voice-timer");
  var voiceCancelBtn = $("voice-cancel-btn");
  var voiceStopBtn = $("voice-stop-btn");
  var voiceSendBtn = $("voice-send-btn");
  var composerStatus = $("composer-status");
  var replyBar = $("reply-bar");
  var replyToName = $("reply-to-name");
  var replyToSnippet = $("reply-to-snippet");
  var replyCancel = $("reply-cancel");

  var contextBackdrop = $("chat-context-backdrop");
  var contextMenu = $("chat-context-menu");
  var reactionBar = $("chat-reaction-bar");
  var contextActions = $("chat-context-actions");
  var listContextBackdrop = $("chat-list-context-backdrop");
  var listContextMenu = $("chat-list-context-menu");
  var listContextTitle = $("chat-list-context-title");
  var listContextSubtitle = $("chat-list-context-subtitle");
  var listContextActions = $("chat-list-context-actions");

  var infoSheet = $("chat-info-sheet");
  var infoSheetBackdrop = $("chat-sheet-backdrop");
  var infoSheetClose = $("chat-sheet-close");
  var infoTitle = $("chat-info-title");
  var infoStatus = $("chat-info-status");
  var infoAbout = $("chat-info-about");
  var infoIdentityRows = $("chat-info-identity-rows");
  var infoSettingsRows = $("chat-info-settings-rows");
  var infoStats = $("chat-info-stats");
  var infoMembers = $("chat-info-members");
  var infoActionsBlock = $("chat-info-actions-block");
  var infoAvatar = $("chat-info-avatar-fallback") ? $("chat-info-avatar-fallback").parentElement : null;
  var infoAvatarImage = $("chat-info-avatar-image");
  var infoAvatarFallback = $("chat-info-avatar-fallback");
  var infoPeerLink = $("chat-info-peer-link");
  var infoProfileLink = $("chat-info-profile-link");
  var infoSecurityLink = $("chat-info-security-link");
  var infoAccountLink = $("chat-info-account-link");
  var archiveBtn = $("archive-btn");
  var unarchiveBtn = $("unarchive-btn");
  var clearHistoryBtn = $("clear-history-btn");
  var leaveBtn = $("leave-btn");
  var deleteConversationBtn = $("delete-conversation-btn");
  var adminTools = $("chat-admin-tools");
  var muteBtn = $("mute-btn");
  var unmuteBtn = $("unmute-btn");
  var unpinBtn = $("unpin-btn");

  var modalBackdrop = $("chat-modal-backdrop");
  var dmModal = $("dm-modal");
  var dmModalClose = $("dm-modal-close");
  var dmSearch = $("dm-search");
  var dmList = $("dm-list");
  var groupModal = $("group-modal");
  var groupModalClose = $("group-modal-close");
  var groupModalSubtitle = $("group-modal-subtitle");
  var groupStepMembers = $("group-step-members");
  var groupStepDetails = $("group-step-details");
  var groupStepBack = $("group-step-back");
  var groupStepNext = $("group-step-next");
  var groupTitleInput = $("group-title");
  var groupAboutInput = $("group-about");
  var groupSearch = $("group-search");
  var groupSelectionMeta = $("group-selection-meta");
  var groupSelectedMembers = $("group-selected-members");
  var groupMembers = $("group-members");
  var groupCreateBtn = $("group-create-btn");
  var forwardModal = $("forward-modal");
  var forwardModalClose = $("forward-modal-close");
  var forwardSearch = $("forward-search");
  var forwardList = $("forward-list");
  var reactionModal = $("reaction-modal");
  var reactionModalClose = $("reaction-modal-close");
  var reactionSearch = $("reaction-search");
  var reactionNativeWrap = $("reaction-native-wrap");
  var reactionNativeBtn = $("reaction-native-btn");
  var reactionTabs = $("reaction-tabs");
  var reactionGrid = $("reaction-grid");
  var editModal = $("edit-modal");
  var editModalClose = $("edit-modal-close");
  var editCancelBtn = $("edit-cancel");
  var editSaveBtn = $("edit-save");
  var editTextInput = $("edit-text");
  var confirmModal = $("confirm-modal");
  var confirmModalClose = $("confirm-modal-close");
  var confirmModalTitle = $("confirm-modal-title");
  var confirmModalText = $("confirm-modal-text");
  var confirmCancelBtn = $("confirm-cancel");
  var confirmAcceptBtn = $("confirm-accept");

  var toastEl = $("toast");
  var themeColorMetas = Array.from(document.querySelectorAll('meta[name="theme-color"]'));
  var appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');

  if (!chatApp || !bootBox || !loginBox || !chatBox) {
    return;
  }

  var state = {
    me: {
      loggedIn: false,
      studentNumber: "",
      name: "",
      role: "student",
      roleLabel: "دانشجو",
      canModerateChat: false,
      isOwner: false,
      isRepresentative: false,
      profile: {
        avatarUrl: "",
        about: ""
      }
    },
    activeConversationId: "",
    conversations: [],
    conversationsById: new Map(),
    conversationFilter: "",
    conversationListCategory: "all",
    messages: new Map(),
    lastMessageId: 0,
    replyTargetId: null,
    pollingTimer: null,
    pollIntervalMs: 1700,
    requestToken: 0,
    toastTimer: null,
    contextOpen: false,
    contextAnchorMessageId: null,
    listContextOpen: false,
    listContextConversationId: "",
    listSelectionMode: false,
    selectedConversationIds: new Set(),
    infoSheetOpen: false,
    modalOpen: "",
    groupCreateStep: "members",
    pendingDirectStart: false,
    pendingGroupCreate: false,
    pendingForwardMessageId: null,
    pendingReactionMessageId: null,
    reactionCategory: "recent",
    recentReactions: [],
    reactionUsage: new Map(),
    pendingEditMessageId: null,
    confirmDialog: null,
    connectionIssue: false,
    showArchivedConversations: false,
    threadAutoStick: true,
    initialConversationId: normalizeSpace(new URLSearchParams(window.location.search).get("conversationId")),
    groupMemberSelection: new Set(),
    directoryUsers: [],
    directoryLoaded: false,
    pendingAttachments: [],
    voiceRecorder: null
  };
  var nativeEmojiPicker = null;

  function safeAuthApi() {
    return asObject(window.Dent1402Auth);
  }

  async function parseApiResponse(response) {
    var status = response.status;
    var text = "";
    var payload = null;

    try {
      text = await response.text();
    } catch (error) {
      text = "";
    }

    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (error) {
        payload = null;
      }
    }

    if (!asObject(payload)) {
      payload = {
        success: false,
        invalidServerResponse: true
      };

      if (/<!doctype html|<html/i.test(text)) {
        payload.error = "پاسخ غیرمنتظره از سرور دریافت شد. صفحه را تازه‌سازی کنید.";
      } else if (status === 401) {
        payload.error = "نشست شما منقضی شده است.";
      } else if (status >= 500) {
        payload.error = "خطای داخلی سرور رخ داد.";
      } else {
        payload.error = "پاسخ معتبر از سرور دریافت نشد.";
      }
    }

    payload.httpStatus = status;
    if (!payload.success && !payload.error) {
      payload.error = status >= 500 ? "خطای داخلی سرور رخ داد." : "درخواست ناموفق بود.";
    }

    return payload;
  }

  async function apiRequest(method, action, payload) {
    var normalizedMethod = method === "POST" ? "POST" : "GET";
    var options = {
      method: normalizedMethod,
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    };
    var url = "/chat/chat_api.php";

    if (normalizedMethod === "GET") {
      var getParams = new URLSearchParams(Object.assign({ action: action }, payload || {}));
      url += "?" + getParams.toString();
    } else {
      options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
      options.body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
    }

    try {
      var response = await fetch(url, options);
      return parseApiResponse(response);
    } catch (error) {
      return {
        success: false,
        error: "ارتباط با سرور برقرار نشد.",
        networkError: true,
        httpStatus: 0
      };
    }
  }

  function apiGet(action, payload) {
    return apiRequest("GET", action, payload);
  }

  function apiPost(action, payload) {
    return apiRequest("POST", action, payload);
  }

  function consumeUnauthorized(payload, fallbackText) {
    var auth = safeAuthApi();
    var message = (payload && payload.error) || fallbackText || "نشست شما منقضی شده است. دوباره وارد شوید.";

    try {
      if (auth && typeof auth.handleUnauthorizedPayload === "function") {
        if (auth.handleUnauthorizedPayload(payload, message)) {
          handleUnauthorized(message);
          return true;
        }
      }
    } catch (error) {
      // Ignore cross-version auth surface errors and continue with fallback check.
    }

    if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
      if (auth && typeof auth.markUnauthorized === "function") {
        auth.markUnauthorized(message);
      }
      handleUnauthorized(message);
      return true;
    }

    return false;
  }

  function ensureSuccessResponse(response, fallbackText) {
    if (!response || response.success !== true) {
      throw new Error((response && response.error) || fallbackText || "درخواست ناموفق بود.");
    }
  }

  function showToast(text) {
    if (!toastEl || !text) return;
    toastEl.textContent = text;
    toastEl.classList.add("show");
    window.clearTimeout(state.toastTimer);
    state.toastTimer = window.setTimeout(function () {
      toastEl.classList.remove("show");
    }, 2200);
  }

  function setConnectionState(mode, text) {
    if (!connectionBadge) return;
    connectionBadge.dataset.state = mode || "idle";
    connectionBadge.textContent = text || "آفلاین";
  }

  function setBootState(text) {
    if (bootText) {
      bootText.textContent = text || "در حال بازیابی نشست...";
    }
  }

  function showGuardMessage(text, kind) {
    if (!msgBox) return;
    msgBox.textContent = text || "";
    msgBox.className = "login-feedback" + (kind ? " " + kind : "");
  }

  function showStreamState(kind, title, desc) {
    if (!streamStateEl) return;
    if (!kind) {
      streamStateEl.hidden = true;
      streamStateEl.innerHTML = "";
      delete streamStateEl.dataset.kind;
      return;
    }
    streamStateEl.dataset.kind = normalizeSpace(kind) || "info";
    streamStateEl.hidden = false;
    streamStateEl.innerHTML =
      "<strong>" + escapeHtml(title || "") + "</strong>" +
      (desc ? "<span>" + escapeHtml(desc) + "</span>" : "");
  }

  function setComposerStatus(text, kind) {
    if (!composerStatus) return;
    composerStatus.textContent = text || "";
    composerStatus.className = "composer-status" + (kind ? " " + kind : "");
  }

  function hideAllStages() {
    bootBox.hidden = true;
    loginBox.hidden = true;
    chatBox.hidden = true;
  }

  function applyViewportHeight() {
    var viewportHeight = window.innerHeight;
    if (window.visualViewport && window.visualViewport.height) {
      viewportHeight = window.visualViewport.height;
    }
    document.documentElement.style.setProperty("--chat-vh", Math.round(viewportHeight) + "px");
    syncThemeColor();
  }

  function isMobileViewport() {
    return window.matchMedia("(max-width: 980px)").matches;
  }

  function isKeyboardViewportShift() {
    if (!isMobileViewport() || !window.visualViewport) {
      return false;
    }
    var vv = window.visualViewport;
    var delta = Math.max(0, Math.round(window.innerHeight - vv.height));
    return delta >= 140;
  }

  function normalizeConversationListCategory(value) {
    var category = normalizeSpace(value || "").toLowerCase();
    if (category === "unread" || category === "groups" || category === "direct") {
      return category;
    }
    return "all";
  }

  function updateConversationFilterTabs() {
    if (!conversationFilterTabs) return;
    var activeCategory = normalizeConversationListCategory(state.conversationListCategory);
    var buttons = Array.from(conversationFilterTabs.querySelectorAll("[data-list-filter]"));
    buttons.forEach(function (button) {
      var isActive = normalizeConversationListCategory(button.getAttribute("data-list-filter")) === activeCategory;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-selected", isActive ? "true" : "false");
      if (isActive) {
        button.setAttribute("tabindex", "0");
      } else {
        button.setAttribute("tabindex", "-1");
      }
    });
  }

  function setConversationListCategory(value) {
    var next = normalizeConversationListCategory(value);
    if (state.conversationListCategory === next) {
      updateConversationFilterTabs();
      return;
    }
    state.conversationListCategory = next;
    updateConversationFilterTabs();
    renderConversationList();
  }

  function conversationMatchesListCategory(conversation) {
    if (!conversation) return false;
    var category = normalizeConversationListCategory(state.conversationListCategory);
    if (category === "unread") {
      return Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0))) > 0;
    }
    if (category === "groups") {
      return conversation.type !== "direct";
    }
    if (category === "direct") {
      return conversation.type === "direct";
    }
    return true;
  }

  function parseRgbChannels(value) {
    var raw = normalizeSpace(value || "");
    if (!raw) return null;

    var hexMatch = raw.match(/^#([0-9a-f]{3,8})$/i);
    if (hexMatch) {
      var hex = hexMatch[1];
      if (hex.length === 3 || hex.length === 4) {
        return [
          parseInt(hex.charAt(0) + hex.charAt(0), 16),
          parseInt(hex.charAt(1) + hex.charAt(1), 16),
          parseInt(hex.charAt(2) + hex.charAt(2), 16)
        ];
      }
      if (hex.length >= 6) {
        return [
          parseInt(hex.slice(0, 2), 16),
          parseInt(hex.slice(2, 4), 16),
          parseInt(hex.slice(4, 6), 16)
        ];
      }
    }

    var rgbMatch = raw.match(/rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)/i);
    if (!rgbMatch) return null;
    return [
      clamp(Math.round(toNumber(rgbMatch[1], 0)), 0, 255),
      clamp(Math.round(toNumber(rgbMatch[2], 0)), 0, 255),
      clamp(Math.round(toNumber(rgbMatch[3], 0)), 0, 255)
    ];
  }

  function resolveThemeColorValue() {
    if (!document.body) return "";
    var styles = window.getComputedStyle(document.body);
    var candidateColors = [
      styles.getPropertyValue("--chat-statusbar-color"),
      styles.getPropertyValue("--chat-top-chrome-color"),
      styles.getPropertyValue("--chat-header-bg"),
      styles.getPropertyValue("--chat-pane-bg-strong")
    ];

    for (var i = 0; i < candidateColors.length; i += 1) {
      var candidate = normalizeSpace(candidateColors[i]);
      if (!candidate) continue;
      var channels = parseRgbChannels(candidate);
      if (channels) {
        return "rgb(" + channels[0] + ", " + channels[1] + ", " + channels[2] + ")";
      }
      if (candidate.charAt(0) === "#") {
        return candidate;
      }
    }
    return "";
  }

  function syncThemeColor() {
    if ((!themeColorMetas || !themeColorMetas.length) && !appleStatusBarMeta) return;
    var color = resolveThemeColorValue();
    if (!color) return;

    themeColorMetas.forEach(function (meta) {
      if (!meta) return;
      meta.setAttribute("content", color);
    });

    if (appleStatusBarMeta) {
      var channels = parseRgbChannels(color);
      if (channels) {
        var luminance = (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]) / 255;
        appleStatusBarMeta.setAttribute("content", luminance < 0.55 ? "black-translucent" : "default");
      }
    }
  }

  function canOpenPollCenterForUser(user) {
    var source = asObject(user);
    if (!source) return false;
    var role = normalizeSpace(source.role);
    return role === "owner" || role === "representative" || !!source.isOwner || !!source.isRepresentative;
  }

  function canCurrentUserViewStudentNumbers() {
    return !!state.me.loggedIn && canOpenPollCenterForUser(state.me);
  }

  function userRoleMetaText(user) {
    var source = asObject(user) || {};
    var roleLabel = normalizeSpace(source.roleLabel) || "دانشجو";
    var studentNumber = normalizeStudentNumber(source.studentNumber || source.username);
    if (canCurrentUserViewStudentNumbers() && studentNumber) {
      return roleLabel + "  " + studentNumber;
    }
    return roleLabel;
  }

  function displayNameFallback(studentNumber) {
    var normalizedStudentNumber = normalizeStudentNumber(studentNumber);
    if (canCurrentUserViewStudentNumbers() && normalizedStudentNumber) {
      return normalizedStudentNumber;
    }
    return "\u06a9\u0627\u0631\u0628\u0631";
  }

  function updatePollActionVisibility() {
    var canOpen = !!state.me.loggedIn && canOpenPollCenterForUser(state.me);
    if (newPollLink) {
      newPollLink.hidden = !canOpen;
      newPollLink.setAttribute("aria-hidden", canOpen ? "false" : "true");
    }
    if (chatNavPolls) {
      chatNavPolls.hidden = !canOpen;
      chatNavPolls.setAttribute("aria-hidden", canOpen ? "false" : "true");
    }
    if (!canOpen) {
      if (newPollLink) {
        newPollLink.blur();
      }
      if (chatNavPolls) {
        chatNavPolls.blur();
      }
    }
    updateMobileNav();
  }

  function setMobileView(view) {
    if (!chatApp) return;
    var allowed = ["list", "thread", "dm", "group"];
    var next = allowed.indexOf(view) !== -1 ? view : "list";
    if (!isMobileViewport() && (next === "dm" || next === "group")) {
      next = "list";
    }
    chatApp.dataset.mobileView = next;
    updateFabVisibility();
    updateMobileNav();
    syncThemeColor();
  }

  function openListPane() {
    if (state.modalOpen) {
      closeModal();
    }
    setMobileView("list");
  }

  function openThreadPane() {
    if (isMobileViewport()) {
      setMobileView("thread");
    }
  }

  function updateFabVisibility() {
    if (!mobileNewChatFab) return;
    var hasMessengerNav = !!chatMobileNav;
    var shouldShow = !!state.me.loggedIn && isMobileViewport() && chatBox && !chatBox.hidden && !state.modalOpen && !hasMessengerNav;
    mobileNewChatFab.hidden = !shouldShow;
  }

  function setMobileNavItemActive(node, active) {
    if (!node) return;
    node.classList.toggle("is-active", !!active);
  }

  function updateMobileNav() {
    if (!chatMobileNav) {
      if (document.body) {
        document.body.classList.remove("chat-mobile-nav-visible");
      }
      syncThemeColor();
      return;
    }
    var loggedIn = !!state.me.loggedIn && chatBox && !chatBox.hidden;
    var mobile = isMobileViewport();
    var listViewActive = chatApp && chatApp.dataset.mobileView === "list";
    var shouldShow = loggedIn && mobile && !state.modalOpen && !state.contextOpen && !state.listContextOpen && !state.infoSheetOpen && listViewActive;
    chatMobileNav.hidden = !shouldShow;
    if (document.body) {
      document.body.classList.toggle("chat-mobile-nav-visible", shouldShow);
    }
    if (!shouldShow) {
      setMobileNavItemActive(chatNavList, false);
      setMobileNavItemActive(chatNavCompose, false);
      setMobileNavItemActive(chatNavGroup, false);
      setMobileNavItemActive(chatNavPolls, false);
      setMobileNavItemActive(chatNavHome, false);
      setMobileNavItemActive(chatNavSettings, false);
      syncThemeColor();
      return;
    }

    var hasDmModal = state.modalOpen === "dm";
    var hasGroupModal = state.modalOpen === "group";
    setMobileNavItemActive(chatNavList, true);
    setMobileNavItemActive(chatNavCompose, hasDmModal);
    setMobileNavItemActive(chatNavGroup, hasGroupModal);
    setMobileNavItemActive(chatNavPolls, false);
    setMobileNavItemActive(chatNavHome, false);
    setMobileNavItemActive(chatNavSettings, false);
    syncThemeColor();
  }

  function selectedConversations() {
    return Array.from(state.selectedConversationIds)
      .map(function (conversationId) { return state.conversationsById.get(conversationId) || null; })
      .filter(Boolean);
  }

  function selectableConversationIds() {
    var list = filteredConversations();
    var buckets = splitConversationBuckets(list);
    var hasQuery = !!normalizeSpace(state.conversationFilter);
    var visible = buckets.active.slice();
    if (state.showArchivedConversations || hasQuery) {
      visible = visible.concat(buckets.archived);
    }
    return Array.from(new Set(visible.map(function (conversation) {
      return conversation && conversation.id ? conversation.id : "";
    }).filter(Boolean)));
  }

  function updateSelectionUi() {
    if (document.body) {
      document.body.classList.toggle("chat-selection-mode", !!state.listSelectionMode);
    }
    if (conversationManageBar) {
      conversationManageBar.hidden = !state.listSelectionMode;
    }
    if (conversationSelectionCount) {
      var count = state.selectedConversationIds.size;
      conversationSelectionCount.textContent = count.toLocaleString("fa-IR") + " مورد انتخاب";
    }
    if (conversationSelectionToggleAll) {
      var selectableIds = selectableConversationIds();
      var selectableCount = selectableIds.length;
      var selectedVisibleCount = selectableIds.reduce(function (count, conversationId) {
        return count + (state.selectedConversationIds.has(conversationId) ? 1 : 0);
      }, 0);
      var allSelected = selectableCount > 0 && selectedVisibleCount >= selectableCount;
      conversationSelectionToggleAll.disabled = selectableCount <= 0;
      conversationSelectionToggleAll.textContent = allSelected ? "لغو همه" : "انتخاب همه";
    }
    var hasSelection = state.selectedConversationIds.size > 0;
    [
      conversationBatchRead,
      conversationBatchUnread,
      conversationBatchPin,
      conversationBatchUnpin,
      conversationBatchArchive,
      conversationBatchUnarchive,
      conversationBatchMute,
      conversationBatchUnmute,
      conversationBatchDelete
    ].forEach(function (button) {
      if (!button) return;
      button.disabled = !hasSelection;
    });
  }

  function enterConversationSelectionMode(seedConversationId) {
    state.listSelectionMode = true;
    if (seedConversationId) {
      state.selectedConversationIds.add(seedConversationId);
    }
    closeListContextMenu();
    updateSelectionUi();
    renderConversationList();
  }

  function exitConversationSelectionMode() {
    state.listSelectionMode = false;
    state.selectedConversationIds.clear();
    updateSelectionUi();
    renderConversationList();
  }

  function toggleConversationSelection(conversationId, force) {
    var normalizedId = normalizeSpace(conversationId);
    if (!normalizedId) return;
    var shouldSelect = force === true;
    var shouldUnselect = force === false;
    if (!shouldSelect && !shouldUnselect) {
      shouldSelect = !state.selectedConversationIds.has(normalizedId);
    }
    if (shouldSelect) {
      state.selectedConversationIds.add(normalizedId);
    } else {
      state.selectedConversationIds.delete(normalizedId);
    }
    updateSelectionUi();
    renderConversationList();
  }

  function toggleSelectAllVisibleConversations() {
    if (!state.listSelectionMode) {
      state.listSelectionMode = true;
    }
    var ids = selectableConversationIds();
    if (!ids.length) {
      updateSelectionUi();
      renderConversationList();
      return;
    }
    var allSelected = ids.every(function (conversationId) {
      return state.selectedConversationIds.has(conversationId);
    });
    ids.forEach(function (conversationId) {
      if (allSelected) {
        state.selectedConversationIds.delete(conversationId);
      } else {
        state.selectedConversationIds.add(conversationId);
      }
    });
    updateSelectionUi();
    renderConversationList();
  }

  function reactionUsageScore(emoji) {
    return Math.max(0, Math.floor(toNumber(state.reactionUsage.get(emoji), 0)));
  }

  function pushRecentReaction(emoji) {
    if (!isLikelyEmoji(emoji)) return;
    state.recentReactions = [emoji].concat(state.recentReactions.filter(function (item) {
      return item !== emoji;
    })).slice(0, REACTION_RECENTS_LIMIT);
    state.reactionUsage.set(emoji, reactionUsageScore(emoji) + 1);
    saveReactionPreferences();
  }

  function activeReactionGroups(sourceMessage) {
    var groups = REACTION_GROUPS.map(function (group) {
      var emojis = group.id === "recent"
        ? state.recentReactions.concat(reactionEntries(sourceMessage).map(function (entry) { return entry.emoji; }))
        : (Array.isArray(group.emojis) ? group.emojis : []);
      var unique = Array.from(new Set(emojis.filter(isLikelyEmoji)));
      return {
        id: group.id,
        label: group.label,
        emojis: unique
      };
    }).filter(function (group) {
      return group.id !== "recent" || group.emojis.length > 0;
    });
    groups.unshift({ id: "all", label: "همه", emojis: REACTIONS.slice() });
    return groups;
  }

  function quickReactionsForMessage(message) {
    var ordered = [];
    reactionEntries(message).forEach(function (entry) {
      if (!isLikelyEmoji(entry.emoji)) return;
      ordered.push(entry.emoji);
    });
    state.recentReactions.forEach(function (emoji) {
      if (!isLikelyEmoji(emoji)) return;
      ordered.push(emoji);
    });
    QUICK_REACTIONS.forEach(function (emoji) {
      ordered.push(emoji);
    });
    var unique = [];
    ordered.forEach(function (emoji) {
      if (unique.indexOf(emoji) !== -1) return;
      unique.push(emoji);
    });
    unique.sort(function (left, right) {
      return reactionUsageScore(right) - reactionUsageScore(left);
    });
    return unique.slice(0, 12);
  }

  function updateGroupSelectionMeta() {
    if (!groupSelectionMeta) return;
    groupSelectionMeta.textContent = state.groupMemberSelection.size > 0
      ? state.groupMemberSelection.size.toLocaleString("fa-IR") + " عضو انتخاب شد"
      : "هنوز عضوی انتخاب نشده است.";
  }

  function renderGroupSelectedMembers() {
    if (!groupSelectedMembers) return;
    var selectedUsers = usersForDirectory().filter(function (user) {
      return state.groupMemberSelection.has(user.studentNumber);
    });
    if (!selectedUsers.length) {
      groupSelectedMembers.innerHTML = "";
      groupSelectedMembers.hidden = true;
      return;
    }

    groupSelectedMembers.hidden = false;
    groupSelectedMembers.innerHTML = selectedUsers.map(function (user) {
      return [
        '<button class="group-selected-chip" type="button" data-remove-member="' + escapeHtml(user.studentNumber) + '" aria-label="حذف ' + escapeHtml(user.name) + '">',
        "  <span>" + escapeHtml(user.name) + "</span>",
        "  <strong></strong>",
        "</button>"
      ].join("");
    }).join("");
  }

  function setGroupCreateStep(step) {
    var next = step === "details" ? "details" : "members";
    state.groupCreateStep = next;
    if (groupStepMembers) {
      var membersActive = next === "members";
      groupStepMembers.hidden = !membersActive;
      groupStepMembers.classList.toggle("is-active", membersActive);
    }
    if (groupStepDetails) {
      var detailsActive = next === "details";
      groupStepDetails.hidden = !detailsActive;
      groupStepDetails.classList.toggle("is-active", detailsActive);
    }
    if (groupModalSubtitle) {
      groupModalSubtitle.textContent = next === "members" ? "۱ از ۲ • انتخاب اعضا" : "۲ از ۲ • اطلاعات گروه";
    }
    if (groupStepBack) {
      groupStepBack.hidden = next !== "details";
    }
    if (groupStepNext) {
      groupStepNext.hidden = next !== "members";
    }
    if (groupCreateBtn) {
      groupCreateBtn.hidden = next !== "details";
    }
  }

  function resetGroupCreateFlow() {
    setGroupCreateStep("members");
    state.groupMemberSelection.clear();
    if (groupTitleInput) groupTitleInput.value = "";
    if (groupAboutInput) groupAboutInput.value = "";
    if (groupSearch) groupSearch.value = "";
    renderGroupSelectedMembers();
    updateGroupSelectionMeta();
    renderGroupMembersPicker();
  }

  function sortConversations(list) {
    return list.slice().sort(function (a, b) {
      if (!!a.isMandatory !== !!b.isMandatory) return a.isMandatory ? -1 : 1;
      var aArchived = !!(a.viewerState && a.viewerState.archived);
      var bArchived = !!(b.viewerState && b.viewerState.archived);
      if (aArchived !== bArchived) return aArchived ? 1 : -1;
      var aPinned = !!(a.viewerState && a.viewerState.pinned);
      var bPinned = !!(b.viewerState && b.viewerState.pinned);
      if (aPinned !== bPinned) return aPinned ? -1 : 1;
      if (aPinned && bPinned) {
        var aPinnedAt = toNumber(a.viewerState && a.viewerState.pinnedAt, 0);
        var bPinnedAt = toNumber(b.viewerState && b.viewerState.pinnedAt, 0);
        if (aPinnedAt !== bPinnedAt) return bPinnedAt - aPinnedAt;
      }
      var aUnread = toNumber(a.unreadCount, 0);
      var bUnread = toNumber(b.unreadCount, 0);
      if (aUnread > 0 && bUnread === 0) return -1;
      if (bUnread > 0 && aUnread === 0) return 1;
      var aUpdated = toNumber(a.updatedAt, 0);
      var bUpdated = toNumber(b.updatedAt, 0);
      if (aUpdated !== bUpdated) return bUpdated - aUpdated;
      return toText(a.title).localeCompare(toText(b.title), "fa");
    });
  }

  function normalizeUser(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var studentNumber = normalizeStudentNumber(source.studentNumber || source.username);
    if (!studentNumber) return null;
    var profile = asObject(source.profile) || {};
    return {
      studentNumber: studentNumber,
      name: normalizeSpace(source.name) || displayNameFallback(studentNumber),
      role: normalizeSpace(source.role) || "student",
      roleLabel: normalizeSpace(source.roleLabel) || "دانشجو",
      canModerateChat: !!source.canModerateChat,
      isOwner: !!source.isOwner || normalizeSpace(source.role) === "owner",
      isRepresentative: !!source.isRepresentative || normalizeSpace(source.role) === "representative",
      profile: {
        avatarUrl: normalizeAvatarUrl(profile.avatarUrl || source.avatarUrl || ""),
        about: normalizeSpace(profile.about || profile.bio || source.about || "")
      }
    };
  }

  function normalizeAttachment(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = normalizeSpace(source.id);
    if (!id) return null;
    var category = normalizeAttachmentCategory(source.category);
    var mime = normalizeSpace(source.mime).toLowerCase();
    var available = source.available !== false && !source.expired;
    var previewUrl = toText(source.previewUrl || "");
    var url = toText(source.url || "");
    var downloadUrl = toText(source.downloadUrl || "");

    return {
      id: id,
      category: category,
      name: normalizeSpace(source.name) || normalizeSpace(source.safeFileName) || "file",
      safeFileName: normalizeSpace(source.safeFileName),
      mime: mime,
      extension: normalizeSpace(source.extension).toLowerCase(),
      sizeBytes: Math.max(0, Math.floor(toNumber(source.sizeBytes, 0))),
      durationSeconds: source.durationSeconds != null ? Math.max(0, toNumber(source.durationSeconds, 0)) : null,
      available: !!available,
      expired: !available || !!source.expired,
      hasPreview: !!source.hasPreview || !!previewUrl,
      status: normalizeSpace(source.status || (available ? "available" : "expired")) || "available",
      purgedAt: source.purgedAt != null ? Math.floor(toNumber(source.purgedAt, 0)) : null,
      purgeReason: normalizeSpace(source.purgeReason),
      url: url,
      previewUrl: previewUrl,
      downloadUrl: downloadUrl,
      isVoice: !!source.isVoice || category === "voice"
    };
  }

  function normalizeMessage(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = Math.floor(toNumber(source.id, 0));
    if (id <= 0) return null;

    var profile = asObject(source.profile) || {};
    var sender = normalizeStudentNumber(source.studentNumber || source.senderStudentNumber || source.username);
    var text = toText(source.text);
    if (!text) text = "";
    var kind = normalizeSpace(source.kind || "text");
    if (kind !== "poll" && kind !== "attachment" && kind !== "voice") {
      kind = "text";
    }
    var attachments = (Array.isArray(source.attachments) ? source.attachments : [])
      .map(normalizeAttachment)
      .filter(Boolean);
    if (attachments.length && kind === "text") {
      kind = attachments[0].category === "voice" ? "voice" : "attachment";
    }

    return {
      id: id,
      conversationId: normalizeSpace(source.conversationId),
      studentNumber: sender,
      name: normalizeSpace(source.name)
        || (sender && sender === state.me.studentNumber
          ? (normalizeSpace(state.me.name) || "\u0634\u0645\u0627")
          : displayNameFallback(sender)),
      role: normalizeSpace(source.role) || "student",
      roleLabel: normalizeSpace(source.roleLabel) || "دانشجو",
      canModerateChat: !!source.canModerateChat,
      kind: kind,
      text: text,
      ts: Math.floor(toNumber(source.ts, Math.floor(Date.now() / 1000))),
      editedAt: source.editedAt != null ? Math.floor(toNumber(source.editedAt, 0)) : null,
      replyTo: source.replyTo != null ? Math.floor(toNumber(source.replyTo, 0)) : null,
      pinned: !!source.pinned,
      reactions: asObject(source.reactions) || {},
      attachments: attachments,
      avatarUrl: normalizeAvatarUrl(source.avatarUrl || profile.avatarUrl || ""),
      about: normalizeSpace(source.about || profile.about || profile.bio || ""),
      delivery: normalizeSpace(source.delivery) || "sent",
      seenByCount: Math.max(0, Math.floor(toNumber(source.seenByCount, 0)))
    };
  }

  function looksAutoConversationTitle(value) {
    var clean = normalizeSpace(value).toLowerCase();
    if (!clean) return false;
    if (/^(group|conversation|chat)[-_]?\d{3,}$/.test(clean)) return true;
    if (/^grp[-_]\d+$/.test(clean)) return true;
    if (/^conv[-_][0-9a-f]{8,}$/.test(clean)) return true;
    return false;
  }

  function conversationTitleFromSource(source, type, peer, conversationId) {
    var rawTitle = normalizeSpace(source && source.title);
    if (rawTitle) {
      if (!looksAutoConversationTitle(rawTitle)) {
        return rawTitle;
      }
      var hasHumanSignals = !!normalizeSpace(source && source.about)
        || !!normalizeSpace(source && source.subtitle)
        || !!asObject(source && source.lastMessage);
      if (hasHumanSignals) {
        return rawTitle;
      }
    }

    if (type === "direct") {
      var peerName = normalizeSpace(peer && peer.name);
      if (peerName) return peerName;
      var peerStudentNumber = normalizeStudentNumber(peer && peer.studentNumber);
      if (peerStudentNumber && canCurrentUserViewStudentNumbers()) {
        return "گفت‌وگو با " + peerStudentNumber;
      }
      return "گفت‌وگوی خصوصی";
    }

    if (type === "class-group") {
      return "گفت‌وگوی کلاس";
    }

    if (rawTitle && looksAutoConversationTitle(rawTitle)) {
      return "گروه جدید";
    }

    var convId = normalizeSpace(conversationId);
    if (looksAutoConversationTitle(convId)) {
      return "گروه جدید";
    }

    return rawTitle || "گروه";
  }

  function conversationSubtitleFromSource(source, type, peer, memberCount) {
    var rawSubtitle = normalizeSpace(source && source.subtitle);
    if (rawSubtitle) return rawSubtitle;

    if (type === "direct") {
      if (peer && peer.roleLabel) {
        return normalizeSpace(peer.roleLabel);
      }
      return "گفت‌وگوی خصوصی";
    }

    if (type === "class-group") {
      return "گفت‌وگوی مشترک کلاسی";
    }

    var aboutText = normalizeSpace(source && source.about);
    if (aboutText) {
      return snippet(aboutText, 52);
    }

    var total = Math.max(0, Math.floor(toNumber(memberCount, 0)));
    if (total > 0) {
      return total.toLocaleString("fa-IR") + " عضو";
    }
    return "گروه";
  }

  function normalizeConversation(raw) {
    var source = asObject(raw);
    if (!source) return null;
    var id = normalizeSpace(source.id);
    if (!id) return null;
    var type = normalizeSpace(source.type).toLowerCase();
    if (type !== "class-group" && type !== "direct" && type !== "group") {
      type = "";
    }
    if (!type && id.indexOf("dm:") === 0) {
      type = "direct";
    }
    if (type === "group" && id.indexOf("dm:") === 0) {
      type = "direct";
    }
    if (!type) {
      type = "group";
    }

    var permissions = asObject(source.permissions) || {};
    var settings = asObject(source.settings) || {};
    var viewerState = asObject(source.viewerState) || {};
    var peer = normalizeUser(source.peer);
    var memberCount = Math.max(0, Math.floor(toNumber(source.memberCount, 0)));
    var normalized = {
      id: id,
      type: type,
      title: conversationTitleFromSource(source, type, peer, id),
      subtitle: conversationSubtitleFromSource(source, type, peer, memberCount),
      about: toText(source.about || ""),
      avatarUrl: normalizeAvatarUrl(source.avatarUrl),
      createdAt: Math.floor(toNumber(source.createdAt, 0)),
      updatedAt: Math.floor(toNumber(source.updatedAt, 0)),
      isMandatory: !!source.isMandatory,
      memberCount: memberCount,
      unreadCount: Math.max(0, Math.floor(toNumber(source.unreadCount, 0))),
      lastReadMessageId: Math.max(0, Math.floor(toNumber(source.lastReadMessageId, 0))),
      settings: {
        muted: !!settings.muted,
        mutedAt: settings.mutedAt != null ? Math.floor(toNumber(settings.mutedAt, 0)) : null,
        mutedBy: normalizeSpace(settings.mutedBy)
      },
      viewerState: {
        pinned: !!viewerState.pinned,
        pinnedAt: viewerState.pinnedAt != null ? Math.floor(toNumber(viewerState.pinnedAt, 0)) : null,
        archived: !!viewerState.archived,
        archivedAt: viewerState.archivedAt != null ? Math.floor(toNumber(viewerState.archivedAt, 0)) : null,
        deleted: !!viewerState.deleted,
        deletedAt: viewerState.deletedAt != null ? Math.floor(toNumber(viewerState.deletedAt, 0)) : null
      },
      permissions: {
        canSend: permissions.canSend !== false,
        canManageConversation: !!permissions.canManageConversation,
        canPinMessages: !!permissions.canPinMessages,
        canPinConversation: !!permissions.canPinConversation,
        canMuteConversation: !!permissions.canMuteConversation,
        canArchiveConversation: !!permissions.canArchiveConversation,
        canLeaveConversation: !!permissions.canLeaveConversation,
        canClearHistory: !!permissions.canClearHistory,
        canDeleteConversation: !!permissions.canDeleteConversation,
        canMarkRead: permissions.canMarkRead !== false,
        canMarkUnread: permissions.canMarkUnread !== false,
        canCreateGroup: permissions.canCreateGroup !== false
      },
      lastMessage: normalizeMessage(source.lastMessage),
      pinnedMessage: normalizeMessage(source.pinnedMessage),
      peer: peer,
      members: []
    };

    var members = Array.isArray(source.members) ? source.members : [];
    normalized.members = members.map(normalizeUser).filter(Boolean);
    return normalized;
  }

  function upsertConversation(conversation) {
    if (!conversation) return;
    var existing = state.conversationsById.get(conversation.id);
    if (existing) {
      var merged = Object.assign({}, existing, conversation);
      if (conversation.members && conversation.members.length) {
        merged.members = conversation.members;
      } else if (existing.members) {
        merged.members = existing.members;
      }
      state.conversationsById.set(conversation.id, merged);
      return;
    }
    state.conversationsById.set(conversation.id, conversation);
  }

  function replaceConversations(list) {
    state.conversationsById.clear();
    (list || []).forEach(function (item) {
      upsertConversation(item);
    });
    state.conversations = sortConversations(Array.from(state.conversationsById.values()));
  }

  function rebuildConversationsFromMap() {
    state.conversations = sortConversations(Array.from(state.conversationsById.values()));
  }

  function activeConversation() {
    if (!state.activeConversationId) return null;
    return state.conversationsById.get(state.activeConversationId) || null;
  }

  function messageList() {
    var list = Array.from(state.messages.values());
    list.sort(function (a, b) {
      return a.id - b.id;
    });
    return list;
  }

  function clearThreadState() {
    state.messages.clear();
    state.lastMessageId = 0;
    state.replyTargetId = null;
    state.threadAutoStick = true;
    clearReplyTarget();
    if (messagesEl) messagesEl.innerHTML = "";
    showStreamState("empty", "گفت‌وگو خالی است", "برای شروع گفت‌وگو، یک پیام بفرست.");
  }

  function setThreadVisible(visible) {
    if (!threadShell || !threadPlaceholder) return;
    threadShell.hidden = !visible;
    threadPlaceholder.hidden = !!visible;
  }

  function splitConversationBuckets(list) {
    var active = [];
    var archived = [];
    (Array.isArray(list) ? list : []).forEach(function (conversation) {
      if (!conversation) return;
      if (conversation.viewerState && conversation.viewerState.archived) {
        archived.push(conversation);
      } else {
        active.push(conversation);
      }
    });
    return { active: active, archived: archived };
  }

  function updateConversationMeta() {
    if (!conversationMeta) return;
    var buckets = splitConversationBuckets(state.conversations);
    var total = state.conversations.length;
    var activeTotal = buckets.active.length;
    var archivedTotal = buckets.archived.length;
    var unread = buckets.active.reduce(function (count, item) {
      return count + Math.max(0, Math.floor(toNumber(item.unreadCount, 0)));
    }, 0);
    if (total <= 0) {
      conversationMeta.textContent = "هنوز گفت‌وگویی ثبت نشده است";
      return;
    }
    if (unread > 0) {
      conversationMeta.textContent = activeTotal.toLocaleString("fa-IR") + " گفت‌وگوی فعال • " + unread.toLocaleString("fa-IR") + " خوانده‌نشده";
      return;
    }
    if (archivedTotal > 0) {
      conversationMeta.textContent = activeTotal.toLocaleString("fa-IR") + " گفت‌وگوی فعال • " + archivedTotal.toLocaleString("fa-IR") + " بایگانی";
      return;
    }
    conversationMeta.textContent = activeTotal.toLocaleString("fa-IR") + " گفت‌وگوی فعال";
  }

  function conversationBadgeText(conversation) {
    if (!conversation) return "";
    if (conversation.type === "class-group") return "کلاس";
    if (conversation.type === "direct") return "خصوصی";
    return "گروه";
  }

  function conversationTypeIconMarkup(conversation) {
    if (!conversation || conversation.type === "group") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8.5 11.5C10.1569 11.5 11.5 10.1569 11.5 8.5C11.5 6.84315 10.1569 5.5 8.5 5.5C6.84315 5.5 5.5 6.84315 5.5 8.5C5.5 10.1569 6.84315 11.5 8.5 11.5Z" stroke="currentColor" stroke-width="1.7"/><path d="M15.5 10.5C16.8807 10.5 18 9.38071 18 8C18 6.61929 16.8807 5.5 15.5 5.5C14.1193 5.5 13 6.61929 13 8C13 9.38071 14.1193 10.5 15.5 10.5Z" stroke="currentColor" stroke-width="1.7"/><path d="M4.5 18C4.5 15.7909 6.29086 14 8.5 14H9.5C11.7091 14 13.5 15.7909 13.5 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M13.5 17.5C13.5 15.8431 14.8431 14.5 16.5 14.5H17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    }
    if (conversation.type === "class-group") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 9.3L12 4.5L20.5 9.3L12 14.1L3.5 9.3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M7 11.2V16.2C7 17.7 9.24 19 12 19C14.76 19 17 17.7 17 16.2V11.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12.5C14.0711 12.5 15.75 10.8211 15.75 8.75C15.75 6.67893 14.0711 5 12 5C9.92893 5 8.25 6.67893 8.25 8.75C8.25 10.8211 9.92893 12.5 12 12.5Z" stroke="currentColor" stroke-width="1.7"/><path d="M5.5 18.5C6.42 16.22 8.9 14.75 12 14.75C15.1 14.75 17.58 16.22 18.5 18.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
  }

  function conversationPreview(conversation) {
    if (!conversation || !conversation.lastMessage) {
      if (conversation.type === "direct") return "شروع گفت‌وگو";
      if (conversation.type === "class-group") return "گفت‌وگوی عمومی کلاس";
      var aboutText = normalizeSpace(conversation.about);
      if (aboutText) return snippet(aboutText, 58);
      var memberCount = Math.max(0, Math.floor(toNumber(conversation.memberCount, 0)));
      if (memberCount > 0) {
        return memberCount.toLocaleString("fa-IR") + " عضو • هنوز پیامی ثبت نشده";
      }
      return "هنوز پیامی ثبت نشده";
    }
    var last = conversation.lastMessage;
    var own = last.studentNumber === state.me.studentNumber;
    var prefix = own ? "شما: " : (last.name ? last.name + ": " : "");
    var deliveryPrefix = "";
    if (own) {
      deliveryPrefix = last.delivery === "seen" ? "✓✓ " : "✓ ";
    }
    return deliveryPrefix + prefix + snippet(messagePreviewText(last), 58);
  }

  function renderConversationItem(conversation) {
    var node = document.createElement("article");
    node.className = "conversation-item";
    node.dataset.conversationId = conversation.id;
    node.dataset.conversationType = conversation.type || "group";
    if (conversation.id === state.activeConversationId) {
      node.classList.add("is-active");
    }
    if (conversation.unreadCount > 0) {
      node.classList.add("is-unread");
    }
    if ((conversation.viewerState && conversation.viewerState.pinned) || conversation.pinnedMessage) {
      node.classList.add("is-pinned");
    }
    if (conversation.viewerState && conversation.viewerState.archived) {
      node.classList.add("is-archived");
    }
    if (conversation.settings && conversation.settings.muted) {
      node.classList.add("is-muted");
    }
    if (state.selectedConversationIds.has(conversation.id)) {
      node.classList.add("is-selected");
    }

    var badge = conversationBadgeText(conversation);
    var lastTs = conversation.lastMessage ? conversation.lastMessage.ts : conversation.updatedAt;
    var timeLabel = lastTs ? formatTime(lastTs) : "";
    var unread = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0)));
    var flags = [];
    if (conversation.settings && conversation.settings.muted) {
      flags.push('<span class="conversation-flag conversation-flag--muted" title="بی‌صدا">•</span>');
    }
    if ((conversation.viewerState && conversation.viewerState.pinned) || conversation.pinnedMessage) {
      flags.push('<span class="conversation-flag conversation-flag--pinned" title="سنجاق‌شده">•</span>');
    }
    if (conversation.viewerState && conversation.viewerState.archived) {
      flags.push('<span class="conversation-flag conversation-flag--archived" title="بایگانی">•</span>');
    }

    node.innerHTML = [
      '<button type="button" class="conversation-item__main" data-open-conversation="1">',
      '  <span class="conversation-item__avatar" data-has-avatar="0">',
      '    <img alt="" hidden>',
      "    <span>" + escapeHtml(avatarLabel(conversation.title)) + "</span>",
      "  </span>",
      '  <span class="conversation-item__copy">',
      '    <span class="conversation-item__head">',
      '      <span class="conversation-item__kind" aria-hidden="true">' + conversationTypeIconMarkup(conversation) + "</span>",
      '      <strong class="conversation-item__title" data-digit-locale="latin">' + escapeHtml(conversation.title) + "</strong>",
      (badge ? '      <span class="conversation-badge">' + escapeHtml(badge) + "</span>" : ""),
      "    </span>",
      '    <span class="conversation-item__subtitle" data-digit-locale="latin">' + escapeHtml(conversation.subtitle || "") + "</span>",
      '    <span class="conversation-item__preview" data-digit-locale="latin">' + escapeHtml(conversationPreview(conversation)) + "</span>",
      "  </span>",
      '  <span class="conversation-item__meta">',
      '    <span class="conversation-item__time">' + escapeHtml(timeLabel) + "</span>",
      flags.length ? '    <span class="conversation-item__flags">' + flags.join("") + "</span>" : "",
      unread > 0 ? '    <span class="conversation-item__unread">' + unread.toLocaleString("fa-IR") + "</span>" : "",
      "  </span>",
      "</button>",
      '<span class="conversation-item__tools">',
      '  <label class="conversation-item__select"><input type="checkbox" data-conversation-select="1" value="' + escapeHtml(conversation.id) + '"' + (state.selectedConversationIds.has(conversation.id) ? " checked" : "") + '></label>',
      '  <button type="button" class="conversation-item__more" data-conversation-menu="1" aria-label="گزینه‌های گفتگو">&#8942;</button>',
      "</span>"
    ].join("");

    var avatar = node.querySelector(".conversation-item__avatar");
    var image = avatar ? avatar.querySelector("img") : null;
    var fallback = avatar ? avatar.querySelector("span") : null;
    renderAvatar(avatar, image, fallback, conversation.avatarUrl, conversation.title);

    var openButton = node.querySelector("[data-open-conversation]");
    var selectBox = node.querySelector("[data-conversation-select]");
    var menuButton = node.querySelector("[data-conversation-menu]");

    if (openButton) {
      openButton.addEventListener("click", function () {
        if (state.listSelectionMode) {
          toggleConversationSelection(conversation.id);
          return;
        }
        openConversation(conversation.id, { forceFull: true, source: "click" });
      });

      openButton.addEventListener("contextmenu", function (event) {
        event.preventDefault();
        openListContextMenu(conversation, event.clientX, event.clientY);
      });

      var touchTimer = null;
      var startX = 0;
      var startY = 0;
      var cancelled = false;
      var clearTouchTimer = function () {
        if (!touchTimer) return;
        window.clearTimeout(touchTimer);
        touchTimer = null;
      };

      openButton.addEventListener("pointerdown", function (event) {
        if (event.pointerType !== "touch") return;
        startX = event.clientX;
        startY = event.clientY;
        cancelled = false;
        clearTouchTimer();
        touchTimer = window.setTimeout(function () {
          if (cancelled) return;
          openListContextMenu(conversation, event.clientX, event.clientY);
        }, 420);
      });
      openButton.addEventListener("pointermove", function (event) {
        if (event.pointerType !== "touch" || !touchTimer) return;
        var deltaX = Math.abs(event.clientX - startX);
        var deltaY = Math.abs(event.clientY - startY);
        if (deltaX > 9 || deltaY > 9) {
          cancelled = true;
          clearTouchTimer();
        }
      });
      ["pointerup", "pointercancel", "pointerleave"].forEach(function (eventName) {
        openButton.addEventListener(eventName, clearTouchTimer);
      });
    }

    if (selectBox) {
      selectBox.addEventListener("click", function (event) {
        event.stopPropagation();
      });
      selectBox.addEventListener("change", function () {
        if (!state.listSelectionMode && selectBox.checked) {
          state.listSelectionMode = true;
        }
        toggleConversationSelection(conversation.id, !!selectBox.checked);
      });
    }

    if (menuButton) {
      menuButton.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var rect = menuButton.getBoundingClientRect();
        openListContextMenu(conversation, rect.left + (rect.width / 2), rect.bottom + 6);
      });
    }

    return node;
  }
  function filteredConversations() {
    var q = normalizeSpace(state.conversationFilter).toLowerCase();
    return state.conversations.filter(function (conversation) {
      if (!conversationMatchesListCategory(conversation)) {
        return false;
      }
      if (!q) return true;
      var hay = [
        conversation.title,
        conversation.subtitle,
        conversationPreview(conversation),
        canCurrentUserViewStudentNumbers() && conversation.type === "direct" && conversation.peer ? conversation.peer.studentNumber : ""
      ].join(" ").toLowerCase();
      return hay.indexOf(q) !== -1;
    });
  }

  function renderConversationList() {
    if (!conversationList) return;
    conversationList.innerHTML = "";
    var list = filteredConversations();
    var buckets = splitConversationBuckets(list);
    var hasQuery = !!normalizeSpace(state.conversationFilter);
    var hasCategory = normalizeConversationListCategory(state.conversationListCategory) !== "all";
    var activeList = buckets.active;
    var archivedList = buckets.archived;
    var hasAny = activeList.length > 0 || archivedList.length > 0;
    setHidden(conversationEmpty, hasAny);

    if (conversationEmpty && !hasAny) {
      conversationEmpty.textContent = hasQuery
        ? "نتیجه‌ای برای جستجوی گفتگو پیدا نشد."
        : "هنوز گفتگویی پیدا نشد.";
    }

    if (activeList.length) {
      activeList.forEach(function (conversation) {
        conversationList.appendChild(renderConversationItem(conversation));
      });
    }

    if (archivedList.length) {
      var archivedToggle = document.createElement("button");
      archivedToggle.type = "button";
      archivedToggle.className = "conversation-archive-toggle" + (state.showArchivedConversations || hasQuery || hasCategory ? " is-open" : "");
      archivedToggle.innerHTML = [
        '<span>گفتگوهای بایگانی‌شده</span>',
        '<strong>' + archivedList.length.toLocaleString("fa-IR") + "</strong>"
      ].join("");
      archivedToggle.addEventListener("click", function () {
        state.showArchivedConversations = !state.showArchivedConversations;
        renderConversationList();
      });
      conversationList.appendChild(archivedToggle);

      if (state.showArchivedConversations || hasQuery || hasCategory) {
        archivedList.forEach(function (conversation) {
          conversationList.appendChild(renderConversationItem(conversation));
        });
      }
    }

    if (state.modalOpen === "forward") {
      renderForwardList();
    }

    var validSelectedIds = new Set();
    state.selectedConversationIds.forEach(function (conversationId) {
      if (state.conversationsById.has(conversationId)) {
        validSelectedIds.add(conversationId);
      }
    });
    state.selectedConversationIds = validSelectedIds;
    updateSelectionUi();
    updateConversationFilterTabs();
    updateConversationMeta();
  }

  function updateThreadHead() {
    var conversation = activeConversation();
    if (!conversation) {
      if (threadTitle) threadTitle.textContent = "گفت‌وگو";
      if (threadSubtitle) threadSubtitle.textContent = "یک گفت‌وگو را انتخاب کن";
      if (threadAvatar && threadAvatarImage && threadAvatarFallback) {
        renderAvatar(threadAvatar, threadAvatarImage, threadAvatarFallback, "", "?");
      }
      return;
    }

    if (threadTitle) threadTitle.textContent = conversation.title;
    if (threadSubtitle) {
      if (conversation.type === "direct" && conversation.peer) {
        var aboutText = normalizeSpace(conversation.peer.profile && conversation.peer.profile.about);
        threadSubtitle.textContent = aboutText || conversation.subtitle || "گفت‌وگوی خصوصی";
      } else {
        threadSubtitle.textContent = conversation.subtitle || "";
      }
    }
    if (threadAvatar && threadAvatarImage && threadAvatarFallback) {
      renderAvatar(threadAvatar, threadAvatarImage, threadAvatarFallback, conversation.avatarUrl, conversation.title);
    }
  }

  function updatePinnedUi() {
    var conversation = activeConversation();
    if (!conversation || !pinnedWrap || !pinnedText) return;
    var pinned = conversation.pinnedMessage || null;

    if (!pinned) {
      pinnedWrap.hidden = true;
      if (unpinBtn) unpinBtn.hidden = true;
      return;
    }

    pinnedWrap.hidden = false;
    pinnedText.textContent = snippet(messagePreviewText(pinned), 120);
    if (pinnedActionLabel) pinnedActionLabel.textContent = "رفتن به پیام";
    if (unpinBtn) {
      unpinBtn.hidden = !(conversation.permissions && conversation.permissions.canPinMessages);
    }
  }

  function updateMuteUi(settings) {
    var conversation = activeConversation();
    if (!muteBadge) return;

    var muted = !!(settings && settings.muted);
    var canManage = !!(conversation && conversation.permissions && conversation.permissions.canMuteConversation);
    if (!muted) {
      muteBadge.hidden = true;
      muteBadge.textContent = "";
    } else {
      muteBadge.hidden = false;
      muteBadge.textContent = canManage
        ? "ارسال پیام در این گفت‌وگو بسته شده است. با ابزار مدیریت می‌توانی آن را باز کنی."
        : "ارسال پیام در این گفت‌وگو موقتاً بسته است.";
    }

    if (muteBtn) muteBtn.hidden = !(conversation && conversation.permissions && conversation.permissions.canMuteConversation && !muted);
    if (unmuteBtn) unmuteBtn.hidden = !(conversation && conversation.permissions && conversation.permissions.canMuteConversation && muted);
  }

  function updateComposerState() {
    var conversation = activeConversation();
    var canSend = !!(conversation && conversation.permissions && conversation.permissions.canSend);
    var muted = !!(conversation && conversation.settings && conversation.settings.muted);
    var hasUploadsInProgress = composerHasUploadingItems();
    var hasVoiceRecorder = !!state.voiceRecorder;
    var recordingVoice = !!(state.voiceRecorder && state.voiceRecorder.recording);
    var shouldDisable = !conversation || !canSend || muted;
    var canUseAttachmentTools = !!conversation && canSend && !muted;

    if (chatTextEl) {
      chatTextEl.disabled = shouldDisable;
      chatTextEl.placeholder = shouldDisable
        ? (conversation ? "ارسال پیام در این گفت‌وگو ممکن نیست" : "یک گفت‌وگو را انتخاب کن")
        : "پیامت را بنویس...";
    }
    if (sendBtn) {
      sendBtn.disabled = shouldDisable || hasUploadsInProgress || recordingVoice;
    }
    if (attachBtn) {
      attachBtn.disabled = (conversation && !canUseAttachmentTools) || (canUseAttachmentTools && recordingVoice);
    }
    if (voiceBtn) {
      voiceBtn.disabled = (conversation && !canUseAttachmentTools) || (canUseAttachmentTools && (hasUploadsInProgress || hasVoiceRecorder));
    }
    if (attachmentInput) {
      attachmentInput.disabled = (conversation && !canUseAttachmentTools) || (canUseAttachmentTools && recordingVoice);
    }

    if (!conversation) {
      setUploadSheetOpen(false);
      setComposerStatus("یک گفت‌وگو را برای شروع انتخاب کن.", "");
      return;
    }

    if (!canSend) {
      setUploadSheetOpen(false);
      setComposerStatus("در حال حاضر اجازه ارسال پیام در این گفت‌وگو را ندارید.", "error");
      return;
    }

    if (muted) {
      setUploadSheetOpen(false);
      setComposerStatus("ارسال پیام در این گفت‌وگو بسته است.", "error");
      return;
    }

    if (hasUploadsInProgress) {
      setComposerStatus("در حال بارگذاری فایل...", "");
      return;
    }

    if (recordingVoice) {
      setComposerStatus("در حال ضبط پیام صوتی...", "");
      return;
    }

    if (state.connectionIssue) {
      setComposerStatus("اتصال ناپایدار است؛ در حال تلاش برای اتصال مجدد...", "error");
      return;
    }

    setComposerStatus("", "");
  }

  function reactionMap(message) {
    if (!message || !asObject(message.reactions)) return new Map();
    var map = new Map();
    Object.keys(message.reactions).forEach(function (emoji) {
      var users = Array.isArray(message.reactions[emoji]) ? message.reactions[emoji] : [];
      var unique = Array.from(new Set(users.map(function (item) {
        return normalizeSpace(item);
      }).filter(Boolean)));
      if (unique.length) {
        map.set(emoji, unique);
      }
    });
    return map;
  }

  function reactionEntries(message) {
    var map = reactionMap(message);
    var entries = [];
    map.forEach(function (users, emoji) {
      entries.push({
        emoji: emoji,
        users: users.slice(),
        count: users.length,
        own: users.indexOf(state.me.studentNumber) !== -1
      });
    });
    entries.sort(function (left, right) {
      if (right.count !== left.count) return right.count - left.count;
      return toText(left.emoji).localeCompare(toText(right.emoji), "en");
    });
    return entries;
  }

  function studentDisplayName(studentNumber) {
    var normalized = normalizeStudentNumber(studentNumber);
    if (!normalized) return "کاربر";
    if (normalized === normalizeStudentNumber(state.me.studentNumber)) {
      return normalizeSpace(state.me.name) || "شما";
    }

    var conversation = activeConversation();
    if (conversation && Array.isArray(conversation.members)) {
      for (var i = 0; i < conversation.members.length; i += 1) {
        var member = conversation.members[i];
        if (normalizeStudentNumber(member && member.studentNumber) === normalized) {
          return normalizeSpace(member.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
        }
      }
    }

    for (var m = 0; m < state.conversations.length; m += 1) {
      var candidate = state.conversations[m];
      if (!candidate || !Array.isArray(candidate.members)) continue;
      for (var c = 0; c < candidate.members.length; c += 1) {
        var user = candidate.members[c];
        if (normalizeStudentNumber(user && user.studentNumber) === normalized) {
          return normalizeSpace(user.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
        }
      }
    }

    var fromDirectory = state.directoryUsers.find(function (item) {
      return normalizeStudentNumber(item && item.studentNumber) === normalized;
    });
    if (fromDirectory) {
      return normalizeSpace(fromDirectory.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
    }

    var fromMessages = null;
    state.messages.forEach(function (item) {
      if (fromMessages) return;
      if (normalizeStudentNumber(item && item.studentNumber) === normalized) {
        fromMessages = item;
      }
    });
    if (fromMessages) {
      return normalizeSpace(fromMessages.name) || (canCurrentUserViewStudentNumbers() ? normalized : "کاربر");
    }

    return canCurrentUserViewStudentNumbers() ? normalized : "کاربر";
  }

  function reactionDetailsText(entry) {
    if (!entry || !Array.isArray(entry.users)) return "";
    var labels = [];
    entry.users.forEach(function (studentNumber) {
      var label = studentDisplayName(studentNumber);
      if (!label || labels.indexOf(label) !== -1) return;
      labels.push(label);
    });
    if (!labels.length) {
      return entry.count.toLocaleString("fa-IR") + " واکنش";
    }
    var visible = labels.slice(0, 3);
    var extra = labels.length - visible.length;
    return extra > 0
      ? (visible.join("، ") + " +" + extra.toLocaleString("fa-IR"))
      : visible.join("، ");
  }

  function findMessage(messageId) {
    return state.messages.get(Number(messageId)) || null;
  }

  function canManageMessage(message) {
    var conversation = activeConversation();
    if (!conversation || !message) return false;
    if (message.studentNumber && message.studentNumber === state.me.studentNumber) return true;
    return !!(conversation.permissions && conversation.permissions.canManageConversation);
  }

  function canPinMessage() {
    var conversation = activeConversation();
    return !!(conversation && conversation.permissions && conversation.permissions.canPinMessages);
  }

  function renderReplyPreview(replyToId) {
    var target = findMessage(replyToId);
    if (!target) return "";
    return (
      '<button type="button" class="reply-preview" data-reply-id="' + String(replyToId) + '">' +
      '<b data-digit-locale="latin">' + escapeHtml(target.name || "کاربر") + "</b>" +
      '<span data-digit-locale="latin">' + escapeHtml(snippet(messagePreviewText(target), 90)) + "</span>" +
      "</button>"
    );
  }

  function renderReactions(message) {
    var entries = reactionEntries(message);
    if (!entries.length) return "";
    return (
      '<div class="msg-reactions">' +
      entries.map(function (entry) {
        var details = reactionDetailsText(entry);
        var title = entry.emoji + " • " + details;
        return (
          '<button type="button" class="msg-reaction' + (entry.own ? " is-own" : "") + '" data-reaction-emoji="' + escapeHtml(entry.emoji) + '" title="' + escapeHtml(title) + '" aria-label="' + escapeHtml(title) + '">' +
          "<span>" + escapeHtml(entry.emoji) + "</span>" +
          '<span class="msg-reaction__count">' + entry.count.toLocaleString("fa-IR") + "</span>" +
          "</button>"
        );
      }).join("") +
      "</div>"
    );
  }

  function attachmentMetaText(attachment) {
    var parts = [attachmentCategoryLabel(attachment.category)];
    if ((attachment.category === "audio" || attachment.category === "voice") && attachment.durationSeconds > 0) {
      parts.push(formatDuration(attachment.durationSeconds));
    }
    if (attachment.sizeBytes > 0) {
      parts.push(formatFileSize(attachment.sizeBytes));
    }
    return parts.join(" • ");
  }

  function renderAttachmentMedia(attachment) {
    var previewUrl = attachment.previewUrl || attachment.url;
    if ((attachment.category === "image" || attachment.category === "video") && previewUrl && (attachment.available || attachment.hasPreview)) {
      if (attachment.category === "video" && attachment.available && attachment.url) {
        return '<div class="msg-attachment__media"><video controls preload="metadata" src="' + escapeHtml(attachment.url) + '"></video></div>';
      }
      return '<div class="msg-attachment__media"><img src="' + escapeHtml(previewUrl) + '" alt="' + escapeHtml(attachment.name || "attachment") + '" loading="lazy"></div>';
    }

    if ((attachment.category === "audio" || attachment.category === "voice") && attachment.available && attachment.url) {
      return '<audio class="msg-attachment__audio" controls preload="metadata" src="' + escapeHtml(attachment.url) + '"></audio>';
    }

    return "";
  }

  function renderAttachment(attachment) {
    if (!attachment) return "";
    var stateText = "";
    if (!attachment.available) {
      stateText = attachment.category === "voice"
        ? "فایل صوتی اصلی در دسترس نیست."
        : "فایل اصلی دیگر در دسترس نیست.";
    }

    var linkUrl = attachment.downloadUrl || attachment.url;
    var linkAttrs = attachment.available && linkUrl
      ? ('href="' + escapeHtml(linkUrl) + '" target="_blank" rel="noopener"')
      : 'href="#" aria-disabled="true"';

    return [
      '<article class="msg-attachment">',
      renderAttachmentMedia(attachment),
      '  <div class="msg-attachment__head">',
      '    <span class="msg-attachment__name" title="' + escapeHtml(attachment.name) + '">' + escapeHtml(attachment.name) + "</span>",
      '    <span class="msg-attachment__meta">' + escapeHtml(attachmentMetaText(attachment)) + "</span>",
      "  </div>",
      '  <div class="msg-attachment__actions">',
      '    <a class="msg-attachment__link" ' + linkAttrs + ">" + (attachment.available ? "دانلود" : "ناموجود") + "</a>",
      stateText ? ('    <span class="msg-attachment__state is-expired">' + escapeHtml(stateText) + "</span>") : "",
      "  </div>",
      "</article>"
    ].join("");
  }

  function renderAttachments(message) {
    var attachments = Array.isArray(message && message.attachments) ? message.attachments : [];
    if (!attachments.length) return "";
    return '<div class="msg-attachments">' + attachments.map(renderAttachment).join("") + "</div>";
  }

  function messageClass(message) {
    var mine = message.studentNumber === state.me.studentNumber;
    var classes = ["msg-item", mine ? "me" : "other"];
    if (message.pinned) classes.push("is-pinned");
    if (message.delivery === "sending") classes.push("delivery-sending");
    if (message.delivery === "failed") classes.push("delivery-failed");
    return classes.join(" ");
  }

  function renderDeliveryMeta(message, ownMessage) {
    if (!ownMessage) return "";
    if (message.delivery === "sending") {
      return '<span class="msg-delivery is-sending">در حال ارسال</span>';
    }
    if (message.delivery === "seen") {
      var seenCount = Math.max(0, Math.floor(toNumber(message.seenByCount, 0)));
      if (seenCount > 0) {
        return '<span class="msg-delivery is-seen">✓✓ ' + seenCount.toLocaleString("fa-IR") + "</span>";
      }
      return '<span class="msg-delivery is-seen">✓✓</span>';
    }
    return '<span class="msg-delivery">✓</span>';
  }

  function renderMessage(message) {
    var row = document.createElement("article");
    row.className = messageClass(message);
    row.dataset.mid = String(message.id);
    row.dataset.sender = message.studentNumber || "";
    row.dataset.ts = String(Math.max(0, Math.floor(toNumber(message.ts, 0))));
    row.dataset.dayKey = dayKeyFromTimestamp(message.ts);
    row.dataset.dayLabel = formatDate(message.ts);

    var showRole = !!(message.canModerateChat && message.studentNumber !== state.me.studentNumber);
    var ownMessage = message.studentNumber === state.me.studentNumber;

    row.innerHTML = [
      '<div class="msg-row">',
      '  <span class="msg-avatar" data-has-avatar="0">',
      '    <img class="msg-avatar__img" alt="" hidden>',
      '    <span class="msg-avatar__fallback">' + escapeHtml(avatarLabel(message.name)) + "</span>",
      "  </span>",
      '  <div class="msg-bubble">',
      '    <div class="msg-head">',
      '      <span class="msg-name" data-digit-locale="latin">' + escapeHtml(message.name || "کاربر") + "</span>",
      showRole ? '      <span class="msg-badge">' + escapeHtml(message.roleLabel || "دانشجو") + "</span>" : "",
      "    </div>",
      message.replyTo ? renderReplyPreview(message.replyTo) : "",
      '    <div class="msg-text" data-digit-locale="latin">' + escapeHtml(message.text) + "</div>",
      renderAttachments(message),
      renderReactions(message),
      '    <div class="msg-foot">',
      '      <span class="msg-flags">',
      message.pinned ? '        <span class="msg-flag">سنجاق</span>' : "",
      message.editedAt ? '        <span class="msg-flag">ویرایش‌شده</span>' : "",
      "      </span>",
      '      <span class="msg-time">' + escapeHtml(formatTime(message.ts)) + "</span>",
      renderDeliveryMeta(message, ownMessage),
      "    </div>",
      "  </div>",
      "</div>"
    ].join("");

    var avatar = row.querySelector(".msg-avatar");
    var image = row.querySelector(".msg-avatar__img");
    var fallback = row.querySelector(".msg-avatar__fallback");
    renderAvatar(avatar, image, fallback, message.avatarUrl, message.name);

    var bubble = row.querySelector(".msg-bubble");
    Array.from(row.querySelectorAll("[data-reaction-emoji]")).forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var emoji = normalizeSpace(button.getAttribute("data-reaction-emoji"));
        if (!emoji) return;
        toggleReaction(message, emoji);
      });
    });
    Array.from(row.querySelectorAll("[data-reply-id]")).forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var targetId = Math.floor(toNumber(button.getAttribute("data-reply-id"), 0));
        if (targetId > 0) {
          scrollToMessage(targetId);
        }
      });
    });
    attachBubbleMenuEvents(bubble, message);
    return row;
  }

  function updateMessageGroups() {
    if (!messagesEl) return;
    var items = Array.from(messagesEl.querySelectorAll(".msg-item"));
    var groupGapSeconds = 8 * 60;
    items.forEach(function (item, index) {
      item.classList.remove("group-start", "group-middle", "group-end", "group-single", "grouped-with-prev", "day-start");

      var currentSender = item.dataset.sender;
      var prev = index > 0 ? items[index - 1] : null;
      var next = index < items.length - 1 ? items[index + 1] : null;
      var currentTs = toNumber(item.dataset.ts, 0);
      var prevTs = prev ? toNumber(prev.dataset.ts, 0) : 0;
      var nextTs = next ? toNumber(next.dataset.ts, 0) : 0;
      var sameDayWithPrev = !!(prev && prev.dataset.dayKey === item.dataset.dayKey);
      var sameDayWithNext = !!(next && next.dataset.dayKey === item.dataset.dayKey);
      var closeToPrev = sameDayWithPrev && currentTs > 0 && prevTs > 0 && Math.abs(currentTs - prevTs) <= groupGapSeconds;
      var closeToNext = sameDayWithNext && currentTs > 0 && nextTs > 0 && Math.abs(nextTs - currentTs) <= groupGapSeconds;

      if (item.dataset.dayLabel && !sameDayWithPrev) {
        item.classList.add("day-start");
      }

      var samePrev = !!(prev && prev.dataset.sender === currentSender && closeToPrev);
      var sameNext = !!(next && next.dataset.sender === currentSender && closeToNext);

      if (samePrev) item.classList.add("grouped-with-prev");
      if (!samePrev && !sameNext) {
        item.classList.add("group-single");
      } else if (!samePrev && sameNext) {
        item.classList.add("group-start");
      } else if (samePrev && sameNext) {
        item.classList.add("group-middle");
      } else {
        item.classList.add("group-end");
      }
    });
  }

  function syncMessageFocus() {
    if (!messagesEl) return;
    var anchor = state.contextAnchorMessageId;
    Array.from(messagesEl.querySelectorAll(".msg-item")).forEach(function (item) {
      if (anchor && item.dataset.mid === String(anchor)) {
        item.classList.add("is-menu-open");
      } else {
        item.classList.remove("is-menu-open");
      }
    });
  }

  function scrollToBottom(smooth) {
    if (!messagesEl) return;
    state.threadAutoStick = true;
    messagesEl.scrollTo({
      top: messagesEl.scrollHeight,
      behavior: smooth ? "smooth" : "auto"
    });
  }

  function isThreadNearBottom(threshold) {
    if (!messagesEl) return true;
    var offset = toNumber(threshold, 110);
    return (messagesEl.scrollTop + messagesEl.clientHeight + offset) >= messagesEl.scrollHeight;
  }

  function bindMediaAutoStick(node) {
    if (!node || !messagesEl) return;
    var mediaNodes = Array.from(node.querySelectorAll(".msg-attachment__media img, .msg-attachment__media video"));
    mediaNodes.forEach(function (media) {
      if (!media) return;
      var isReady = media.tagName === "IMG"
        ? !!media.complete
        : (toNumber(media.readyState, 0) >= 1);
      if (isReady) return;

      var handleReady = function () {
        media.removeEventListener("load", handleReady);
        media.removeEventListener("loadedmetadata", handleReady);
        media.removeEventListener("error", handleReady);
        if (!isThreadNearBottom(140)) return;
        window.requestAnimationFrame(function () {
          scrollToBottom(false);
        });
      };

      media.addEventListener("load", handleReady, { once: true });
      media.addEventListener("loadedmetadata", handleReady, { once: true });
      media.addEventListener("error", handleReady, { once: true });
    });
  }

  function scrollToMessage(messageId) {
    if (!messagesEl || !messageId) return;
    var node = messagesEl.querySelector('[data-mid="' + Number(messageId) + '"]');
    if (!node) return;
    node.scrollIntoView({ behavior: "smooth", block: "center" });
    state.contextAnchorMessageId = Number(messageId);
    syncMessageFocus();
    window.setTimeout(function () {
      state.contextAnchorMessageId = null;
      syncMessageFocus();
    }, 1400);
  }

  function clearReplyTarget() {
    state.replyTargetId = null;
    if (replyBar) replyBar.hidden = true;
  }

  function setReplyTarget(message) {
    state.replyTargetId = message.id;
    if (!replyBar || !replyToName || !replyToSnippet) return;
    replyBar.hidden = false;
    replyToName.textContent = message.name || "کاربر";
    replyToSnippet.textContent = snippet(messagePreviewText(message), 90);
    if (chatTextEl) chatTextEl.focus();
  }

  function appendMessages(messages, options) {
    var list = Array.isArray(messages) ? messages : [];
    if (!messagesEl) return;

    var shouldStick = !!state.threadAutoStick && isThreadNearBottom(56);
    var markNew = !!(options && options.markNew);
    var forceReplace = !!(options && options.replaceAll);
    var existingNodes = new Map();
    var fragment = document.createDocumentFragment();

    if (forceReplace) {
      state.messages.clear();
      messagesEl.innerHTML = "";
      state.lastMessageId = 0;
    } else {
      Array.from(messagesEl.querySelectorAll(".msg-item[data-mid]")).forEach(function (node) {
        if (!node || !node.dataset || !node.dataset.mid) return;
        existingNodes.set(node.dataset.mid, node);
      });
    }

    list.forEach(function (message) {
      if (!message || message.id <= 0) return;
      state.lastMessageId = Math.max(state.lastMessageId, message.id);
      state.messages.set(message.id, message);

      var nextNode = renderMessage(message);
      bindMediaAutoStick(nextNode);
      var existing = existingNodes.get(String(message.id));
      if (existing) {
        existing.replaceWith(nextNode);
      } else {
        if (markNew) {
          nextNode.classList.add("is-new");
          window.setTimeout(function () {
            nextNode.classList.remove("is-new");
          }, 220);
        }
        fragment.appendChild(nextNode);
      }
    });

    if (fragment.childNodes.length) {
      messagesEl.appendChild(fragment);
    }

    updateMessageGroups();
    syncMessageFocus();
    updatePinnedUi();
    updateInfoSheet();

    if (state.messages.size === 0) {
      showStreamState("empty", "این گفت‌وگو هنوز خالی است", "برای شروع، یک پیام جدید ارسال کن.");
    } else {
      showStreamState("", "", "");
    }

    if (shouldStick || (options && options.forceStick)) {
      state.threadAutoStick = true;
      window.requestAnimationFrame(function () {
        scrollToBottom(!!(options && options.smooth));
      });
    }
  }

  function replaceMessageInDom(message) {
    if (!message || !messagesEl) return;
    state.messages.set(message.id, message);
    var existing = messagesEl.querySelector('[data-mid="' + message.id + '"]');
    if (existing) {
      var nextNode = renderMessage(message);
      bindMediaAutoStick(nextNode);
      existing.replaceWith(nextNode);
    }
    updateMessageGroups();
    syncMessageFocus();
    updatePinnedUi();
    updateInfoSheet();
  }

  function removeMessageFromDom(messageId) {
    if (!messagesEl) return;
    var numericId = Number(messageId);
    state.messages.delete(numericId);
    var existing = messagesEl.querySelector('[data-mid="' + numericId + '"]');
    if (existing) existing.remove();
    updateMessageGroups();
    updatePinnedUi();
    updateInfoSheet();
    if (state.messages.size === 0) {
      showStreamState("empty", "این گفت‌وگو هنوز خالی است", "برای شروع، یک پیام جدید ارسال کن.");
    }
  }

  function contextAction(label, hint, onClick, className) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "chat-context-action" + (className ? " " + className : "");
    button.innerHTML =
      '<span class="chat-context-action__label">' + escapeHtml(label) + "</span>" +
      '<strong class="chat-context-action__hint">' + escapeHtml(hint || "") + "</strong>";
    button.addEventListener("click", onClick);
    return button;
  }

  function positionContextMenu(clientX, clientY) {
    if (!contextMenu) return;
    var menuWidth = contextMenu.offsetWidth;
    var menuHeight = contextMenu.offsetHeight;
    if (isMobileViewport()) {
      var mobileLeft = Math.max(8, Math.min((window.innerWidth - menuWidth) / 2, window.innerWidth - menuWidth - 8));
      var mobileTop = Math.max(8, window.innerHeight - menuHeight - 8);
      contextMenu.style.left = mobileLeft + "px";
      contextMenu.style.top = mobileTop + "px";
      return;
    }
    var left = Math.max(8, Math.min(clientX - menuWidth / 2, window.innerWidth - menuWidth - 8));
    var top = Math.max(8, Math.min(clientY + 12, window.innerHeight - menuHeight - 8));
    contextMenu.style.left = left + "px";
    contextMenu.style.top = top + "px";
  }

  function closeContextMenu() {
    if (!state.contextOpen) return;
    state.contextOpen = false;
    state.contextAnchorMessageId = null;
    syncMessageFocus();
    if (contextBackdrop) {
      contextBackdrop.classList.remove("is-open");
      contextBackdrop.hidden = true;
    }
    if (contextMenu) {
      contextMenu.classList.remove("is-open");
      contextMenu.hidden = true;
      contextMenu.style.left = "";
      contextMenu.style.top = "";
    }
    updateMobileNav();
  }

  function listContextAction(label, hint, onClick, className) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "chat-list-context-action" + (className ? " " + className : "");
    button.innerHTML =
      '<span class="chat-list-context-action__label">' + escapeHtml(label) + "</span>" +
      '<strong class="chat-list-context-action__hint">' + escapeHtml(hint || "") + "</strong>";
    button.addEventListener("click", function () {
      Promise.resolve(onClick && onClick()).catch(function (error) {
        showToast((error && error.message) || "اجرای عملیات انجام نشد.");
      });
    });
    return button;
  }

  function positionListContextMenu(clientX, clientY) {
    if (!listContextMenu) return;
    var menuWidth = listContextMenu.offsetWidth;
    var menuHeight = listContextMenu.offsetHeight;
    if (isMobileViewport()) {
      var mobileLeft = Math.max(8, Math.min((window.innerWidth - menuWidth) / 2, window.innerWidth - menuWidth - 8));
      var mobileTop = Math.max(8, window.innerHeight - menuHeight - 8);
      listContextMenu.style.left = mobileLeft + "px";
      listContextMenu.style.top = mobileTop + "px";
      return;
    }
    var left = Math.max(8, Math.min(clientX - menuWidth / 2, window.innerWidth - menuWidth - 8));
    var top = Math.max(8, Math.min(clientY + 12, window.innerHeight - menuHeight - 8));
    listContextMenu.style.left = left + "px";
    listContextMenu.style.top = top + "px";
  }

  function closeListContextMenu() {
    if (!state.listContextOpen) return;
    state.listContextOpen = false;
    state.listContextConversationId = "";
    if (listContextBackdrop) {
      listContextBackdrop.classList.remove("is-open");
      listContextBackdrop.hidden = true;
    }
    if (listContextMenu) {
      listContextMenu.classList.remove("is-open");
      listContextMenu.hidden = true;
      listContextMenu.style.left = "";
      listContextMenu.style.top = "";
    }
    updateMobileNav();
  }

  function openListContextMenu(conversation, clientX, clientY) {
    if (!conversation || !listContextBackdrop || !listContextMenu || !listContextActions) return;
    closeContextMenu();
    state.listContextOpen = true;
    state.listContextConversationId = conversation.id;
    if (listContextTitle) listContextTitle.textContent = conversation.title || "گفت‌وگو";
    if (listContextSubtitle) {
      var parts = [];
      parts.push(conversationBadgeText(conversation));
      if (conversation.viewerState && conversation.viewerState.archived) {
        parts.push("بایگانی");
      }
      if (conversation.unreadCount > 0) {
        parts.push(conversation.unreadCount.toLocaleString("fa-IR") + " خوانده‌نشده");
      }
      listContextSubtitle.textContent = parts.filter(Boolean).join(" • ");
    }

    var runAndClose = function (fn) {
      return function () {
        closeListContextMenu();
        return fn();
      };
    };

    var isPinned = !!(conversation.viewerState && conversation.viewerState.pinned);
    var isMuted = !!(conversation.settings && conversation.settings.muted);
    var isArchived = !!(conversation.viewerState && conversation.viewerState.archived);
    var hasUnread = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0))) > 0;
    var isSelected = state.selectedConversationIds.has(conversation.id);

    listContextActions.innerHTML = "";
    listContextActions.appendChild(listContextAction("باز کردن گفتگو", "نمایش رشته پیام", runAndClose(function () {
      return openConversation(conversation.id, { forceFull: true, source: "list-context" });
    })));
    listContextActions.appendChild(listContextAction("اطلاعات گفتگو", "مشاهده پروفایل و تنظیمات", runAndClose(async function () {
      await openConversation(conversation.id, { forceFull: true, source: "list-context-info" });
      await openInfoSheet();
    })));
    listContextActions.appendChild(listContextAction(
      isSelected ? "برداشتن از انتخاب" : "انتخاب",
      "افزودن به حالت چندانتخابی",
      runAndClose(function () {
        if (!state.listSelectionMode) {
          enterConversationSelectionMode(conversation.id);
          return;
        }
        toggleConversationSelection(conversation.id, !isSelected);
      })
    ));

    if (conversation.permissions && conversation.permissions.canPinConversation) {
      listContextActions.appendChild(listContextAction(
        isPinned ? "برداشتن سنجاق" : "سنجاق کردن",
        "مدیریت جایگاه در لیست",
        runAndClose(function () {
          return setConversationPinById(conversation.id, !isPinned);
        })
      ));
    }

    if (conversation.permissions && conversation.permissions.canMuteConversation) {
      listContextActions.appendChild(listContextAction(
        isMuted ? "خارج کردن از سکوت" : "بی‌صدا",
        "مدیریت اعلان و ارسال",
        runAndClose(function () {
          return setConversationMuteById(conversation.id, !isMuted);
        })
      ));
    }

    if (conversation.permissions && (conversation.permissions.canMarkRead || conversation.permissions.canMarkUnread)) {
      listContextActions.appendChild(listContextAction(
        hasUnread ? "علامت‌گذاری به‌عنوان خوانده‌شده" : "علامت‌گذاری به‌عنوان خوانده‌نشده",
        "وضعیت خوانده‌نشده",
        runAndClose(function () {
          return setConversationReadStateById(conversation.id, !hasUnread);
        })
      ));
    }

    if (conversation.permissions && conversation.permissions.canArchiveConversation) {
      listContextActions.appendChild(listContextAction(
        isArchived ? "خروج از بایگانی" : "بایگانی",
        "سازمان‌دهی لیست گفتگو",
        runAndClose(function () {
          return setConversationArchiveById(conversation.id, !isArchived);
        })
      ));
    }

    if (conversation.permissions && conversation.permissions.canDeleteConversation) {
      listContextActions.appendChild(listContextAction(
        "حذف گفتگو",
        "حذف از لیست گفتگوها",
        runAndClose(function () {
          return deleteConversationById(conversation.id);
        }),
        "is-danger"
      ));
    }

    listContextBackdrop.hidden = false;
    listContextMenu.hidden = false;
    window.requestAnimationFrame(function () {
      listContextBackdrop.classList.add("is-open");
      listContextMenu.classList.add("is-open");
      positionListContextMenu(clientX, clientY);
    });
    updateMobileNav();
  }

  function openContextMenu(message, clientX, clientY) {
    if (!message || !contextBackdrop || !contextMenu || !reactionBar || !contextActions) return;
    closeListContextMenu();
    state.contextAnchorMessageId = message.id;
    syncMessageFocus();

    reactionBar.innerHTML = "";
    var ownReactions = new Set(reactionEntries(message).filter(function (entry) { return entry.own; }).map(function (entry) { return entry.emoji; }));
    quickReactionsForMessage(message).forEach(function (emoji) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "chat-reaction-btn";
      if (ownReactions.has(emoji)) {
        button.classList.add("is-active");
      }
      button.textContent = emoji;
      button.addEventListener("click", function () {
        toggleReaction(message, emoji);
      });
      reactionBar.appendChild(button);
    });
    var customReactionBtn = document.createElement("button");
    customReactionBtn.type = "button";
    customReactionBtn.className = "chat-reaction-btn chat-reaction-btn-more";
    customReactionBtn.textContent = "+";
    customReactionBtn.title = "واکنش دیگر";
    customReactionBtn.addEventListener("click", function () {
      openReactionPicker(message);
    });
    reactionBar.appendChild(customReactionBtn);

    contextActions.innerHTML = "";
    contextActions.appendChild(contextAction("پاسخ", "پاسخ به پیام", function () {
      setReplyTarget(message);
      closeContextMenu();
    }));

    contextActions.appendChild(contextAction("فوروارد", "ارسال به گفتگوی دیگر", function () {
      openForwardPicker(message);
      closeContextMenu();
    }));

    contextActions.appendChild(contextAction("کپی", "کپی متن", function () {
      copyMessageText(message);
      closeContextMenu();
    }));

    contextActions.appendChild(contextAction("واکنش دیگر", "ثبت هر ایموجی", function () {
      openReactionPicker(message);
      closeContextMenu();
    }));

    if (canManageMessage(message)) {
      contextActions.appendChild(contextAction("ویرایش", "ویرایش پیام", function () {
        openEditPrompt(message);
        closeContextMenu();
      }));
    }

    if (canPinMessage()) {
      contextActions.appendChild(contextAction(message.pinned ? "برداشتن سنجاق" : "سنجاق کردن", "تغییر وضعیت سنجاق", function () {
        togglePin(message, !message.pinned);
        closeContextMenu();
      }));
    }

    if (canManageMessage(message)) {
      contextActions.appendChild(contextAction("حذف", "حذف پیام", function () {
        deleteMessage(message);
        closeContextMenu();
      }, "is-danger"));
    }

    contextBackdrop.hidden = false;
    contextMenu.hidden = false;
    window.requestAnimationFrame(function () {
      contextBackdrop.classList.add("is-open");
      contextMenu.classList.add("is-open");
      positionContextMenu(clientX, clientY);
    });
    state.contextOpen = true;
    updateMobileNav();
  }

  function promptCustomReaction(message) {
    if (!message) return;
    if (reactionModal) {
      openReactionPicker(message);
      return;
    }
    var value = window.prompt("ایموجی واکنش را وارد کن", "");
    if (value === null) return;
    var emoji = normalizeSpace(value);
    if (!isLikelyEmoji(emoji)) {
      showToast("ایموجی معتبر وارد کن.");
      return;
    }
    toggleReaction(message, emoji);
  }

  function attachBubbleMenuEvents(bubble, message) {
    if (!bubble || !message) return;

    var touchTimer = null;
    var startX = 0;
    var startY = 0;
    var cancelled = false;

    function clearTouchTimer() {
      if (touchTimer) {
        window.clearTimeout(touchTimer);
        touchTimer = null;
      }
    }

    bubble.addEventListener("contextmenu", function (event) {
      event.preventDefault();
      openContextMenu(message, event.clientX, event.clientY);
    });

    bubble.addEventListener("pointerdown", function (event) {
      if (event.pointerType !== "touch") return;
      startX = event.clientX;
      startY = event.clientY;
      cancelled = false;
      clearTouchTimer();
      touchTimer = window.setTimeout(function () {
        if (cancelled) return;
        openContextMenu(message, event.clientX, event.clientY);
      }, 460);
    });

    bubble.addEventListener("pointermove", function (event) {
      if (event.pointerType !== "touch" || !touchTimer) return;
      var deltaX = Math.abs(event.clientX - startX);
      var deltaY = Math.abs(event.clientY - startY);
      if (deltaX > 9 || deltaY > 9) {
        cancelled = true;
        clearTouchTimer();
      }
    });

    ["pointerup", "pointercancel", "pointerleave"].forEach(function (eventName) {
      bubble.addEventListener(eventName, clearTouchTimer);
    });
  }

  async function copyMessageText(message) {
    var text = normalizeSpace(message && message.text);
    if (!text) return;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        var helper = document.createElement("textarea");
        helper.value = text;
        helper.setAttribute("readonly", "");
        helper.style.position = "fixed";
        helper.style.opacity = "0";
        helper.style.pointerEvents = "none";
        document.body.appendChild(helper);
        helper.focus();
        helper.select();
        document.execCommand("copy");
        helper.remove();
      }
      showToast("پیام کپی شد.");
    } catch (error) {
      showToast("کپی پیام انجام نشد.");
    }
  }

  function renderInfoRows(container, rows) {
    if (!container) return;
    var list = Array.isArray(rows) ? rows.filter(Boolean) : [];
    if (!list.length) {
      container.innerHTML = '<div class="chat-info-row chat-info-row--empty"><span>اطلاعاتی برای نمایش وجود ندارد.</span></div>';
      return;
    }
    container.innerHTML = list.map(function (row) {
      var toneClass = row.tone ? (" chat-info-row__value--" + row.tone) : "";
      return [
        '<div class="chat-info-row">',
        '  <span class="chat-info-row__label">' + escapeHtml(row.label || "") + "</span>",
        '  <span class="chat-info-row__value' + toneClass + '">' + escapeHtml(row.value || "") + "</span>",
        "</div>"
      ].join("");
    }).join("");
  }

  function updateInfoSheet() {
    if (!infoSheet) return;
    var conversation = activeConversation();
    if (!conversation) {
      if (infoTitle) infoTitle.textContent = "گفت‌وگو";
      if (infoStatus) infoStatus.textContent = "گفت‌وگو انتخاب نشده است";
      if (infoAbout) infoAbout.textContent = "";
      if (infoIdentityRows) infoIdentityRows.innerHTML = "";
      if (infoSettingsRows) infoSettingsRows.innerHTML = "";
      if (infoStats) infoStats.innerHTML = "";
      if (infoMembers) infoMembers.innerHTML = "";
      if (infoActionsBlock) infoActionsBlock.hidden = true;
      if (adminTools) adminTools.hidden = true;
      if (infoPeerLink) infoPeerLink.hidden = true;
      if (infoProfileLink) infoProfileLink.href = "/account/?from=chat#account-profile";
      if (infoSecurityLink) infoSecurityLink.href = "/account/?from=chat#account-security";
      if (infoAccountLink) infoAccountLink.href = "/account/?from=chat";
      return;
    }

    if (infoTitle) infoTitle.textContent = conversation.title;
    if (infoStatus) {
      var statusParts = [];
      statusParts.push(conversation.memberCount.toLocaleString("fa-IR") + " عضو");
      if (conversation.type === "direct") {
        statusParts.push("گفتگوی خصوصی");
      } else if (conversation.type === "class-group") {
        statusParts.push("گروه اجباری");
      } else {
        statusParts.push("گروه");
      }
      var unread = Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0)));
      if (unread > 0) {
        statusParts.push(unread.toLocaleString("fa-IR") + " خوانده‌نشده");
      }
      if (conversation.viewerState && conversation.viewerState.archived) {
        statusParts.push("بایگانی");
      }
      infoStatus.textContent = statusParts.join("  •  ");
    }
    var aboutText = normalizeSpace(conversation.about);
    if (!aboutText && conversation.type === "direct" && conversation.peer && conversation.peer.profile) {
      aboutText = normalizeSpace(conversation.peer.profile.about);
    }
    if (infoAbout) infoAbout.textContent = aboutText || "توضیحی ثبت نشده است.";
    if (infoAvatar && infoAvatarImage && infoAvatarFallback) {
      renderAvatar(infoAvatar, infoAvatarImage, infoAvatarFallback, conversation.avatarUrl, conversation.title);
    }
    if (infoProfileLink) infoProfileLink.href = "/account/?from=chat#account-profile";
    if (infoSecurityLink) infoSecurityLink.href = "/account/?from=chat#account-security";
    if (infoAccountLink) infoAccountLink.href = "/account/?from=chat";
    if (infoPeerLink) {
      if (conversation.type === "direct" && conversation.peer && conversation.peer.studentNumber) {
        infoPeerLink.hidden = false;
        infoPeerLink.href = "/account/?studentNumber=" + encodeURIComponent(conversation.peer.studentNumber) + "&from=chat#account-info";
      } else {
        infoPeerLink.hidden = true;
      }
    }

    var canViewStudentNumbers = canCurrentUserViewStudentNumbers();
    var roleLabel = conversation.permissions && conversation.permissions.canManageConversation
      ? "مدیر گفتگو"
      : "عضو گفتگو";
    var identityRows = [
      {
        label: "نوع گفتگو",
        value: conversation.type === "direct"
          ? "خصوصی"
          : (conversation.type === "class-group" ? "گروه اجباری کلاس" : "گروه")
      },
      {
        label: "نقش شما",
        value: roleLabel
      }
    ];
    if (conversation.type === "direct" && conversation.peer && canViewStudentNumbers) {
      identityRows.push({
        label: "شناسه مخاطب",
        value: "@" + normalizeSpace(conversation.peer.studentNumber || "")
      });
    } else if (canViewStudentNumbers && conversation.id) {
      identityRows.push({
        label: "شناسه گفتگو",
        value: conversation.id
      });
    }
    renderInfoRows(infoIdentityRows, identityRows);

    var settingsRows = [
      {
        label: "وضعیت گفتگو",
        value: conversation.viewerState && conversation.viewerState.archived ? "بایگانی‌شده" : "فعال",
        tone: conversation.viewerState && conversation.viewerState.archived ? "muted" : "good"
      },
      {
        label: "ارسال پیام",
        value: conversation.permissions && conversation.permissions.canSend ? "مجاز" : "غیرفعال",
        tone: conversation.permissions && conversation.permissions.canSend ? "good" : "danger"
      },
      {
        label: "خوانده‌نشده",
        value: Math.max(0, Math.floor(toNumber(conversation.unreadCount, 0))).toLocaleString("fa-IR")
      }
    ];
    renderInfoRows(infoSettingsRows, settingsRows);

    if (infoStats) {
      var list = messageList();
      var pinnedCount = list.filter(function (item) { return !!item.pinned; }).length;
      var lastMessage = list.length ? list[list.length - 1] : null;
      var createdAtLabel = conversation.createdAt ? (formatDate(conversation.createdAt) + " " + formatTime(conversation.createdAt)) : "نامشخص";
      var archiveLabel = conversation.viewerState && conversation.viewerState.archived ? "بایگانی‌شده" : "فعال";
      infoStats.innerHTML = [
        '<div class="chat-info-stat"><strong>پیام‌ها</strong><span>' + list.length.toLocaleString("fa-IR") + " پیام</span></div>",
        '<div class="chat-info-stat"><strong>سنجاق‌ها</strong><span>' + pinnedCount.toLocaleString("fa-IR") + " پیام سنجاق‌شده</span></div>",
        '<div class="chat-info-stat"><strong>وضعیت گفتگو</strong><span>' + archiveLabel + "</span></div>",
        '<div class="chat-info-stat"><strong>تاریخ ایجاد</strong><span>' + escapeHtml(createdAtLabel) + "</span></div>",
        '<div class="chat-info-stat"><strong>آخرین فعالیت</strong><span>' + (lastMessage ? escapeHtml(formatDate(lastMessage.ts) + " " + formatTime(lastMessage.ts)) : "بدون فعالیت") + "</span></div>"
      ].join("");
    }

    if (infoMembers) {
      infoMembers.innerHTML = "";
      var members = Array.isArray(conversation.members) ? conversation.members : [];
      if (!members.length) {
        var empty = document.createElement("div");
        empty.className = "chat-member chat-member--empty";
        empty.innerHTML = "<strong>در حال بارگذاری اعضا</strong><span>برای این گفت‌وگو اطلاعات اعضا در حال دریافت است.</span>";
        infoMembers.appendChild(empty);
      } else {
        members.forEach(function (member) {
          var node = document.createElement("div");
          node.className = "chat-member";
          node.innerHTML = [
            '<span class="chat-member__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(member.name)) + "</span></span>",
            '<span class="chat-member__copy">',
            "  <strong>" + escapeHtml(member.name) + "</strong>",
            "  <span>" + escapeHtml(userRoleMetaText(member)) + "</span>",
            "  <small>" + escapeHtml(member.profile && member.profile.about ? member.profile.about : "بدون توضیح") + "</small>",
            "</span>"
          ].join("");
          var avatar = node.querySelector(".chat-member__avatar");
          var image = node.querySelector("img");
          var fallback = node.querySelector(".chat-member__avatar span");
          renderAvatar(avatar, image, fallback, member.profile && member.profile.avatarUrl, member.name);
          infoMembers.appendChild(node);
        });
      }
    }

    if (adminTools) {
      adminTools.hidden = !(conversation.permissions && conversation.permissions.canManageConversation);
    }
    if (infoActionsBlock) {
      var canArchive = !!(conversation.permissions && conversation.permissions.canArchiveConversation);
      var isArchived = !!(conversation.viewerState && conversation.viewerState.archived);
      if (archiveBtn) archiveBtn.hidden = !canArchive || isArchived;
      if (unarchiveBtn) unarchiveBtn.hidden = !canArchive || !isArchived;
      if (clearHistoryBtn) clearHistoryBtn.hidden = !(conversation.permissions && conversation.permissions.canClearHistory);
      if (leaveBtn) leaveBtn.hidden = !(conversation.permissions && conversation.permissions.canLeaveConversation);
      if (deleteConversationBtn) deleteConversationBtn.hidden = !(conversation.permissions && conversation.permissions.canDeleteConversation);

      var hasPrimaryAction = false;
      [archiveBtn, unarchiveBtn, clearHistoryBtn, leaveBtn, deleteConversationBtn].forEach(function (button) {
        if (button && !button.hidden) hasPrimaryAction = true;
      });
      infoActionsBlock.hidden = !hasPrimaryAction;
    }
    updateMuteUi(conversation.settings);
    updatePinnedUi();
  }

  function closeInfoSheet() {
    if (!infoSheet || !infoSheetBackdrop) return;
    infoSheet.classList.remove("is-open");
    if (chatApp) {
      chatApp.classList.remove("has-info-open");
    }
    infoSheet.hidden = true;
    infoSheetBackdrop.classList.remove("is-open");
    infoSheetBackdrop.hidden = true;
    state.infoSheetOpen = false;
    updateMobileNav();
  }

  async function openInfoSheet() {
    var conversation = activeConversation();
    if (!conversation || !infoSheet || !infoSheetBackdrop) return;
    state.infoSheetOpen = true;
    if (chatApp) {
      chatApp.classList.add("has-info-open");
    }
    infoSheet.hidden = false;
    var mobileSheet = isMobileViewport();
    infoSheetBackdrop.hidden = !mobileSheet;
    window.requestAnimationFrame(function () {
      infoSheet.classList.add("is-open");
      if (mobileSheet) {
        infoSheetBackdrop.classList.add("is-open");
      } else {
        infoSheetBackdrop.classList.remove("is-open");
      }
    });
    if (!Array.isArray(conversation.members) || !conversation.members.length) {
      await syncConversation({ forceFull: false, includeMembers: true, silent: true });
    } else {
      updateInfoSheet();
    }
    updateMobileNav();
  }

  function modalNodeByKey(key) {
    if (key === "dm") return dmModal;
    if (key === "group") return groupModal;
    if (key === "forward") return forwardModal;
    if (key === "reaction") return reactionModal;
    if (key === "edit") return editModal;
    if (key === "confirm") return confirmModal;
    return null;
  }

  function resolveConfirmDialog(result) {
    if (!state.confirmDialog || typeof state.confirmDialog.resolve !== "function") return;
    var resolver = state.confirmDialog.resolve;
    state.confirmDialog = null;
    resolver(!!result);
  }

  async function openConfirmDialog(options) {
    var opts = asObject(options) || {};
    var title = normalizeSpace(opts.title) || "تایید عملیات";
    var message = normalizeSpace(opts.message) || "آیا از انجام این عملیات مطمئن هستی؟";
    var acceptLabel = normalizeSpace(opts.acceptLabel) || "تایید";
    var danger = !!opts.danger;

    if (!confirmModal || !confirmAcceptBtn || !confirmCancelBtn || !confirmModalText || !confirmModalTitle) {
      return window.confirm(message);
    }

    confirmModalTitle.textContent = title;
    confirmModalText.textContent = message;
    confirmAcceptBtn.textContent = acceptLabel;
    confirmAcceptBtn.classList.toggle("chat-login-btn-danger", danger);

    openModal(confirmModal, "confirm");
    return new Promise(function (resolve) {
      state.confirmDialog = { resolve: resolve };
    });
  }

  function closeModal(force) {
    var hardClose = force === true;
    var closingKey = state.modalOpen;
    if (!hardClose && state.modalOpen === "dm" && state.pendingDirectStart) {
      showToast("در حال ایجاد گفت‌وگوی خصوصی است...");
      return;
    }
    if (!hardClose && state.modalOpen === "group" && state.pendingGroupCreate) {
      showToast("در حال ساخت گروه است...");
      return;
    }
    var hadOpenModal = !!state.modalOpen;
    if (modalBackdrop) {
      modalBackdrop.classList.remove("is-open");
      modalBackdrop.hidden = true;
    }
    [dmModal, groupModal, forwardModal, reactionModal, editModal, confirmModal].forEach(function (node) {
      if (!node) return;
      node.classList.remove("is-open");
      node.classList.remove("is-busy");
      node.setAttribute("aria-busy", "false");
      node.hidden = true;
    });
    state.modalOpen = "";
    if (document.body) {
      document.body.classList.remove("chat-modal-open");
    }
    if (hadOpenModal && closingKey === "group" && groupModal) {
      resetGroupCreateFlow();
    }
    if (hadOpenModal && closingKey === "forward") {
      state.pendingForwardMessageId = null;
      if (forwardSearch) forwardSearch.value = "";
      if (forwardList) forwardList.innerHTML = "";
    }
    if (hadOpenModal && closingKey === "reaction") {
      state.pendingReactionMessageId = null;
      state.reactionCategory = "recent";
      if (reactionSearch) reactionSearch.value = "";
      if (reactionTabs) reactionTabs.innerHTML = "";
      if (reactionGrid) reactionGrid.innerHTML = "";
    }
    if (hadOpenModal && closingKey === "edit") {
      state.pendingEditMessageId = null;
      if (editTextInput) editTextInput.value = "";
    }
    if (hadOpenModal && closingKey === "confirm") {
      resolveConfirmDialog(false);
    }
    if (hadOpenModal && isMobileViewport()) {
      setMobileView(state.activeConversationId ? "thread" : "list");
    }
    updateFabVisibility();
    updateMobileNav();
  }

  function setModalBusy(key, busy) {
    var target = modalNodeByKey(key);
    if (!target) return;
    target.classList.toggle("is-busy", !!busy);
    target.setAttribute("aria-busy", busy ? "true" : "false");
  }

  function openModal(node, key) {
    if (!node || !modalBackdrop) return;
    closeContextMenu();
    closeInfoSheet();
    setUploadSheetOpen(false);
    closeModal(true);
    node.hidden = false;
    modalBackdrop.hidden = false;
    window.requestAnimationFrame(function () {
      node.classList.add("is-open");
      modalBackdrop.classList.add("is-open");
    });
    state.modalOpen = key || "";
    node.classList.remove("is-busy");
    node.setAttribute("aria-busy", "false");
    if (document.body) {
      document.body.classList.add("chat-modal-open");
    }
    if (key === "group") {
      setGroupCreateStep("members");
    }
    if (isMobileViewport()) {
      if (key === "group") {
        setMobileView("group");
      } else if (key === "dm" || key === "forward") {
        setMobileView("dm");
      } else {
        setMobileView(state.activeConversationId ? "thread" : "list");
      }
    }
    updateFabVisibility();
    updateMobileNav();
  }

  function syncCurrentUserAvatar() {
    if (!accountBtn || !accountBtnAvatarImage || !accountBtnAvatarFallback) return;
    var profile = asObject(state.me.profile) || {};
    renderAvatar(accountBtn, accountBtnAvatarImage, accountBtnAvatarFallback, profile.avatarUrl, state.me.name || state.me.studentNumber);
  }

  function usersForDirectory() {
    return state.directoryUsers.filter(function (user) {
      return !!user && user.studentNumber !== state.me.studentNumber;
    });
  }

  async function loadDirectory(force) {
    if (!force && state.directoryLoaded) {
      return usersForDirectory();
    }

    var response = await apiGet("directory", {});
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      throw new Error((response && response.error) || "نشست شما منقضی شده است.");
    }
    ensureSuccessResponse(response, "بارگذاری فهرست کاربران انجام نشد.");

    var users = (Array.isArray(response.users) ? response.users : [])
      .map(normalizeUser)
      .filter(Boolean)
      .sort(function (left, right) {
        return toText(left.name).localeCompare(toText(right.name), "fa");
      });

    state.directoryUsers = users;
    state.directoryLoaded = true;
    return usersForDirectory();
  }

  function renderDmList() {
    if (!dmList) return;

    var query = normalizeSpace(dmSearch && dmSearch.value).toLowerCase();
    var users = usersForDirectory().filter(function (user) {
      if (!query) return true;
      var haystack = [
        user.name,
        user.roleLabel,
        user.profile && user.profile.about,
        canCurrentUserViewStudentNumbers() ? user.studentNumber : ""
      ].join(" ").toLowerCase();
      return haystack.indexOf(query) !== -1;
    });

    if (!users.length) {
      dmList.innerHTML = '<div class="chat-picker-empty">کاربری برای شروع گفتگو پیدا نشد.</div>';
      return;
    }

    dmList.innerHTML = "";
    users.forEach(function (user) {
      var item = document.createElement("button");
      item.type = "button";
      item.className = "chat-picker-item";
      item.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(user.name)) + '</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>' + escapeHtml(user.name) + '</strong>',
        '  <span>' + escapeHtml((user.profile && user.profile.about) || userRoleMetaText(user)) + '</span>',
        '</span>',
        '<span class="chat-picker-check">‹</span>'
      ].join("");

      var avatar = item.querySelector(".chat-picker-item__avatar");
      var image = item.querySelector("img");
      var fallback = item.querySelector(".chat-picker-item__avatar span");
      renderAvatar(avatar, image, fallback, user.profile && user.profile.avatarUrl, user.name);

      item.addEventListener("click", function () {
        startDirectConversation(user.studentNumber);
      });

      dmList.appendChild(item);
    });
  }

  function renderGroupMembersPicker() {
    updateGroupSelectionMeta();
    renderGroupSelectedMembers();
    if (!groupMembers) return;

    var query = normalizeSpace(groupSearch && groupSearch.value).toLowerCase();
    var users = usersForDirectory().filter(function (user) {
      if (!query) return true;
      var haystack = [
        user.name,
        user.roleLabel,
        user.profile && user.profile.about,
        canCurrentUserViewStudentNumbers() ? user.studentNumber : ""
      ].join(" ").toLowerCase();
      return haystack.indexOf(query) !== -1;
    }).sort(function (left, right) {
      var leftSelected = state.groupMemberSelection.has(left.studentNumber);
      var rightSelected = state.groupMemberSelection.has(right.studentNumber);
      if (leftSelected !== rightSelected) {
        return leftSelected ? -1 : 1;
      }
      return toText(left.name).localeCompare(toText(right.name), "fa");
    });

    if (!users.length) {
      groupMembers.innerHTML = '<div class="chat-picker-empty">دانشجویی برای افزودن پیدا نشد.</div>';
      return;
    }

    groupMembers.innerHTML = "";
    users.forEach(function (user) {
      var selected = state.groupMemberSelection.has(user.studentNumber);
      var item = document.createElement("button");
      item.type = "button";
      item.className = "chat-picker-item" + (selected ? " is-selected" : "");
      item.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(user.name)) + '</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>' + escapeHtml(user.name) + '</strong>',
        '  <span>' + escapeHtml((user.profile && user.profile.about) || userRoleMetaText(user)) + '</span>',
        '</span>',
        '<input class="chat-picker-check" type="checkbox"' + (selected ? ' checked' : '') + ' tabindex="-1" aria-hidden="true">'
      ].join("");

      var avatar = item.querySelector(".chat-picker-item__avatar");
      var image = item.querySelector("img");
      var fallback = item.querySelector(".chat-picker-item__avatar span");
      renderAvatar(avatar, image, fallback, user.profile && user.profile.avatarUrl, user.name);

      item.addEventListener("click", function () {
        if (state.pendingGroupCreate) return;
        if (state.groupMemberSelection.has(user.studentNumber)) {
          state.groupMemberSelection.delete(user.studentNumber);
        } else {
          state.groupMemberSelection.add(user.studentNumber);
        }
        renderGroupMembersPicker();
      });

      groupMembers.appendChild(item);
    });
  }

  function reactionPickerSourceMessage() {
    return findMessage(state.pendingReactionMessageId);
  }

  function renderReactionPicker() {
    if (!reactionGrid) return;
    var sourceMessage = reactionPickerSourceMessage();
    if (!sourceMessage) {
      reactionGrid.innerHTML = '<div class="chat-picker-empty">پیام مرجع برای واکنش پیدا نشد.</div>';
      if (reactionTabs) reactionTabs.innerHTML = "";
      return;
    }

    var query = normalizeSpace(reactionSearch && reactionSearch.value);
    var normalizedQuery = query.toLowerCase();
    var ownReactions = new Set(
      reactionEntries(sourceMessage)
        .filter(function (entry) { return entry.own; })
        .map(function (entry) { return entry.emoji; })
    );

    var groups = activeReactionGroups(sourceMessage);
    var selectedGroup = state.reactionCategory || "recent";
    if (!groups.some(function (group) { return group.id === selectedGroup; })) {
      selectedGroup = groups[0] ? groups[0].id : "all";
      state.reactionCategory = selectedGroup;
    }

    if (reactionTabs) {
      reactionTabs.innerHTML = "";
      groups.forEach(function (group) {
        var tab = document.createElement("button");
        tab.type = "button";
        tab.className = "reaction-picker-tab" + (group.id === selectedGroup ? " is-active" : "");
        tab.textContent = group.label;
        tab.addEventListener("click", function () {
          state.reactionCategory = group.id;
          renderReactionPicker();
        });
        reactionTabs.appendChild(tab);
      });
    }

    var baseGroup = groups.find(function (group) { return group.id === selectedGroup; }) || { emojis: REACTIONS.slice() };
    var list = baseGroup.emojis.filter(function (emoji) {
      return reactionMatchesQuery(emoji, query);
    });
    list = Array.from(new Set(list)).sort(function (left, right) {
      return reactionUsageScore(right) - reactionUsageScore(left);
    });

    if (!list.length) {
      reactionGrid.innerHTML = '<div class="chat-picker-empty">واکنش مطابق با جستجو پیدا نشد.</div>';
      return;
    }

    reactionGrid.innerHTML = "";
    list.forEach(function (emoji) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "reaction-picker-btn" + (ownReactions.has(emoji) ? " is-active" : "");
      button.textContent = emoji;
      button.addEventListener("click", function () {
        chooseReactionFromPicker(emoji);
      });
      reactionGrid.appendChild(button);
    });
  }

  function supportsNativeEmojiPicker() {
    return typeof window.EmojiPicker === "function";
  }

  async function openNativeEmojiPicker() {
    var message = reactionPickerSourceMessage();
    if (!message) return;
    if (!supportsNativeEmojiPicker()) {
      showToast("ایموجی سیستم در این مرورگر پشتیبانی نمی‌شود.");
      return;
    }

    try {
      if (!nativeEmojiPicker) {
        nativeEmojiPicker = new window.EmojiPicker({ locale: "fa" });
      }
      var picked = await nativeEmojiPicker.pick();
      var emoji = normalizeSpace(picked && (picked.emoji || picked.unicode || picked.character));
      if (!emoji) return;
      await chooseReactionFromPicker(emoji);
    } catch (error) {
      var code = normalizeSpace((error && (error.name || error.code)) || "");
      if (code === "AbortError") return;
      showToast("باز کردن ایموجی سیستم انجام نشد.");
    }
  }

  async function chooseReactionFromPicker(emoji) {
    var message = reactionPickerSourceMessage();
    if (!message) return;
    var ok = await toggleReaction(message, emoji);
    if (ok) {
      closeModal(true);
    } else {
      renderReactionPicker();
    }
  }

  function openReactionPicker(message) {
    if (!message || !reactionModal) {
      promptCustomReaction(message);
      return;
    }
    state.pendingReactionMessageId = message.id;
    state.reactionCategory = state.recentReactions.length ? "recent" : "all";
    if (reactionSearch) reactionSearch.value = "";
    if (reactionTabs) reactionTabs.innerHTML = "";
    if (reactionNativeWrap) {
      reactionNativeWrap.hidden = !supportsNativeEmojiPicker();
    }
    openModal(reactionModal, "reaction");
    renderReactionPicker();
    if (reactionSearch && !isMobileViewport()) {
      reactionSearch.focus({ preventScroll: true });
    }
  }

  function buildForwardText(message) {
    if (!message) return "";
    var senderName = normalizeSpace(message.name) || "کاربر";
    var text = normalizeSpace(message.text);
    if (!text) return "";
    return "↪️ فوروارد از " + senderName + ":\n" + text;
  }

  async function forwardMessageToConversation(targetConversationId) {
    var conversationId = normalizeSpace(targetConversationId);
    var sourceMessage = findMessage(state.pendingForwardMessageId);
    var forwardText = buildForwardText(sourceMessage);
    if (!conversationId || !sourceMessage || !forwardText) return;

    setModalBusy("forward", true);
    try {
      var response = await apiPost("send", {
        conversationId: conversationId,
        text: forwardText
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "فوروارد پیام انجام نشد.");

      var nextConversation = normalizeConversation(response.conversation);
      if (nextConversation) {
        upsertConversation(nextConversation);
        rebuildConversationsFromMap();
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "forward"
      });
      showToast("پیام فوروارد شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "فوروارد پیام انجام نشد.");
    } finally {
      setModalBusy("forward", false);
    }
  }

  function renderForwardList() {
    if (!forwardList) return;
    var sourceMessage = findMessage(state.pendingForwardMessageId);
    if (!sourceMessage) {
      forwardList.innerHTML = '<div class="chat-picker-empty">پیام مبدا پیدا نشد.</div>';
      return;
    }

    var query = normalizeSpace(forwardSearch && forwardSearch.value).toLowerCase();
    var targets = state.conversations.filter(function (conversation) {
      if (!conversation || conversation.id === state.activeConversationId) return false;
      var haystack = [
        conversation.title,
        conversation.subtitle,
        conversationPreview(conversation)
      ].join(" ").toLowerCase();
      return !query || haystack.indexOf(query) !== -1;
    });

    if (!targets.length) {
      forwardList.innerHTML = '<div class="chat-picker-empty">گفتگویی برای فوروارد پیدا نشد.</div>';
      return;
    }

    forwardList.innerHTML = "";
    targets.forEach(function (conversation) {
      var item = document.createElement("button");
      item.type = "button";
      item.className = "chat-picker-item";
      item.innerHTML = [
        '<span class="chat-picker-item__avatar" data-has-avatar="0"><img alt="" hidden><span>' + escapeHtml(avatarLabel(conversation.title)) + '</span></span>',
        '<span class="chat-picker-item__copy">',
        '  <strong>' + escapeHtml(conversation.title) + '</strong>',
        '  <span>' + escapeHtml(conversation.subtitle || conversationPreview(conversation)) + '</span>',
        '</span>',
        '<span class="chat-picker-check">‹</span>'
      ].join("");

      var avatar = item.querySelector(".chat-picker-item__avatar");
      var image = item.querySelector("img");
      var fallback = item.querySelector(".chat-picker-item__avatar span");
      renderAvatar(avatar, image, fallback, conversation.avatarUrl, conversation.title);

      item.addEventListener("click", function () {
        forwardMessageToConversation(conversation.id);
      });

      forwardList.appendChild(item);
    });
  }

  function openForwardPicker(message) {
    if (!message || !forwardModal) return;
    state.pendingForwardMessageId = message.id;
    openModal(forwardModal, "forward");
    if (forwardSearch) forwardSearch.value = "";
    renderForwardList();
    if (forwardSearch && !isMobileViewport()) {
      forwardSearch.focus({ preventScroll: true });
    }
  }

  function autosizeComposer() {
    if (!chatTextEl) return;
    chatTextEl.style.height = "auto";
    var nextHeight = clamp(chatTextEl.scrollHeight, 38, isMobileViewport() ? 156 : 184);
    chatTextEl.style.height = nextHeight + "px";
  }

  function stopPolling() {
    if (state.pollingTimer) {
      window.clearInterval(state.pollingTimer);
    }
    state.pollingTimer = null;
  }

  function startPolling() {
    stopPolling();
    if (!state.me.loggedIn) return;

    state.pollingTimer = window.setInterval(function () {
      syncConversation({
        forceFull: false,
        includeMembers: state.infoSheetOpen,
        silent: true
      }).catch(function () {});
    }, clamp(state.pollIntervalMs, MIN_POLL_MS, MAX_POLL_MS));
  }

  async function syncConversation(options) {
    if (!state.me.loggedIn) return null;

    var opts = asObject(options) || {};
    var forceFull = !!opts.forceFull;
    var includeMembers = !!opts.includeMembers;
    var silent = !!opts.silent;

    var requestedConversationId = normalizeSpace(
      opts.conversationId
      || opts.requestedConversationId
      || state.activeConversationId
      || state.initialConversationId
    );

    var requestPayload = {
      full: forceFull ? "1" : "0",
      sinceId: String(forceFull ? 0 : Math.max(0, state.lastMessageId)),
      includeMembers: includeMembers ? "1" : "0"
    };
    if (requestedConversationId) {
      requestPayload.conversationId = requestedConversationId;
    }

    var token = ++state.requestToken;

    if (!silent) {
      setConnectionState(forceFull ? "sync" : "live", forceFull ? "در حال همگام‌سازی..." : "در حال دریافت...");
    }

    try {
      var response = await apiGet("sync", requestPayload);
      if (token !== state.requestToken) {
        return null;
      }

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }

      ensureSuccessResponse(response, "همگام‌سازی گفتگو انجام نشد.");

      var hadConnectionIssue = !!state.connectionIssue;
      state.connectionIssue = false;

      if (response && asObject(response.transport) && response.transport.intervalMs != null) {
        state.pollIntervalMs = clamp(toNumber(response.transport.intervalMs, 1700), MIN_POLL_MS, MAX_POLL_MS);
      }

      var incomingConversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      if (incomingConversations.length) {
        replaceConversations(incomingConversations);
      } else {
        rebuildConversationsFromMap();
      }

      var currentPayloadConversation = normalizeConversation(response.conversation);
      if (currentPayloadConversation) {
        upsertConversation(currentPayloadConversation);
        rebuildConversationsFromMap();
      }

      var nextConversationId = normalizeSpace(
        response.conversationId
        || (currentPayloadConversation && currentPayloadConversation.id)
        || requestedConversationId
        || state.activeConversationId
      );

      if (nextConversationId && state.conversationsById.has(nextConversationId)) {
        if (nextConversationId !== state.activeConversationId) {
          state.lastMessageId = 0;
        }
        state.activeConversationId = nextConversationId;
      } else if (!state.activeConversationId || !state.conversationsById.has(state.activeConversationId)) {
        state.activeConversationId = state.conversations.length ? state.conversations[0].id : "";
        state.lastMessageId = 0;
      }

      state.initialConversationId = "";

      var hasActiveConversation = !!state.activeConversationId;
      setThreadVisible(hasActiveConversation);
      renderConversationList();
      updateThreadHead();
      updateComposerState();
      updatePinnedUi();

      var active = activeConversation();
      updateMuteUi(active && active.settings ? active.settings : { muted: false });

      if (!hasActiveConversation) {
        clearThreadState();
        updateInfoSheet();
        updateFabVisibility();
        updateMobileNav();
        if (!silent || hadConnectionIssue) {
          setConnectionState("idle", "آفلاین");
        }
        updateComposerState();
        return response;
      }

      var normalizedMessages = (Array.isArray(response.messages) ? response.messages : [])
        .map(normalizeMessage)
        .filter(function (message) {
          if (!message) return false;
          if (!message.conversationId) return true;
          return message.conversationId === state.activeConversationId;
        });

      appendMessages(normalizedMessages, {
        replaceAll: forceFull || Number(requestPayload.sinceId) <= 0,
        forceStick: !!opts.forceStick || forceFull,
        smooth: !forceFull,
        markNew: !forceFull
      });

      updateInfoSheet();
      updateComposerState();

      if (!silent || hadConnectionIssue) {
        setConnectionState("live", "متصل");
      }

      return response;
    } catch (error) {
      state.connectionIssue = true;
      updateComposerState();
      if (!silent) {
        setConnectionState("issue", "اختلال ارتباط");
        if (state.messages.size === 0) {
          showStreamState("error", "ارتباط با سرور قطع شد", "چند لحظه دیگر دوباره تلاش کن.");
        }
      } else {
        setConnectionState("issue", "در انتظار اتصال");
        if (state.messages.size === 0) {
          showStreamState("reconnect", "در حال برقراری اتصال مجدد", "ارتباط ناپایدار است، لطفاً چند لحظه صبر کن.");
        }
      }
      throw error;
    }
  }

  async function openConversation(conversationId, options) {
    var nextId = normalizeSpace(conversationId);
    if (!nextId) return null;

    var opts = asObject(options) || {};
    var mobileView = normalizeSpace(opts.mobileView);
    var changed = state.activeConversationId !== nextId;
    state.activeConversationId = nextId;

    if (changed) {
      state.lastMessageId = 0;
      state.threadAutoStick = true;
      clearReplyTarget();
      closeContextMenu();
      clearComposerAttachments();
      setUploadSheetOpen(false);
      resetVoiceRecorder();
      setComposerStatus("", "");
    }

    setThreadVisible(true);
    renderConversationList();
    updateThreadHead();
    updateComposerState();

    if (isMobileViewport() && !state.modalOpen) {
      setMobileView(mobileView === "list" ? "list" : "thread");
    }

    return syncConversation({
      forceFull: opts.forceFull !== false,
      includeMembers: state.infoSheetOpen,
      conversationId: nextId,
      silent: !!opts.silent,
      source: opts.source || ""
    });
  }

  async function handleUnauthorized(message) {
    stopPolling();
    closeContextMenu();
    closeInfoSheet();
    closeModal(true);
    resetLoggedOutUi(message || "نشست شما منقضی شده است.");

    var auth = safeAuthApi();
    if (auth && typeof auth.bootstrap === "function") {
      try {
        await auth.bootstrap(true);
      } catch (error) {
        // no-op
      }
    }
  }

  function resetLoggedOutUi(errorText) {
    state.me = {
      loggedIn: false,
      studentNumber: "",
      name: "",
      role: "student",
      roleLabel: "دانشجو",
      canModerateChat: false,
      isOwner: false,
      isRepresentative: false,
      profile: {
        avatarUrl: "",
        about: ""
      }
    };

    state.activeConversationId = "";
    state.conversations = [];
    state.conversationsById.clear();
    state.conversationFilter = "";
    state.conversationListCategory = "all";
    state.messages.clear();
    state.lastMessageId = 0;
    state.replyTargetId = null;
    state.directoryUsers = [];
    state.directoryLoaded = false;
    state.pendingDirectStart = false;
    state.pendingGroupCreate = false;
    state.pendingForwardMessageId = null;
    state.pendingReactionMessageId = null;
    state.pendingEditMessageId = null;
    state.confirmDialog = null;
    state.connectionIssue = false;
    state.showArchivedConversations = false;
    state.threadAutoStick = true;
    state.recentReactions = [];
    state.reactionUsage = new Map();
    state.pendingAttachments = [];
    nativeEmojiPicker = null;

    stopPolling();
    resetVoiceRecorder();
    setUploadSheetOpen(false);
    renderComposerUploads();
    clearReplyTarget();
    closeContextMenu();
    closeInfoSheet();
    closeModal(true);
    setThreadVisible(false);
    clearThreadState();
    renderConversationList();
    updateConversationFilterTabs();
    updateThreadHead();
    updateComposerState();
    updatePinnedUi();
    updateMuteUi({ muted: false });
    syncCurrentUserAvatar();

    hideAllStages();
    setHidden(bootBox, true);
    setHidden(loginBox, false);
    setHidden(chatBox, true);

    if (logoutBtn) logoutBtn.hidden = true;
    if (refreshBtn) refreshBtn.hidden = true;

    if (accountBtn) {
      var auth = safeAuthApi();
      if (auth && typeof auth.loginUrl === "function") {
        accountBtn.href = auth.loginUrl("/chat/");
      } else {
        accountBtn.href = "/account/?returnTo=%2Fchat%2F";
      }
    }

    showGuardMessage(
      errorText || "برای استفاده از پیام‌رسان، وارد حساب سایت شوید.",
      errorText ? "error" : ""
    );
    setConnectionState("idle", "آفلاین");
    setMobileView("list");
    updatePollActionVisibility();
    updateFabVisibility();
    updateMobileNav();
    syncThemeColor();
  }

  async function applyAuthenticatedUser(rawUser) {
    var user = normalizeUser(rawUser);
    if (!user) {
      resetLoggedOutUi("اطلاعات هویتی حساب معتبر نیست.");
      return;
    }

    var userChanged = state.me.studentNumber !== user.studentNumber;
    state.me = Object.assign({ loggedIn: true }, user);
    loadReactionPreferences();

    if (userChanged) {
      state.activeConversationId = state.initialConversationId || "";
      state.conversations = [];
      state.conversationsById.clear();
      state.conversationFilter = "";
      state.conversationListCategory = "all";
      state.messages.clear();
      state.lastMessageId = 0;
      state.directoryUsers = [];
      state.directoryLoaded = false;
      state.pendingForwardMessageId = null;
      state.pendingReactionMessageId = null;
      state.pendingEditMessageId = null;
      state.confirmDialog = null;
      state.connectionIssue = false;
      state.showArchivedConversations = false;
      state.pendingAttachments = [];
      setUploadSheetOpen(false);
      renderComposerUploads();
      resetVoiceRecorder();
      clearReplyTarget();
      closeContextMenu();
      closeInfoSheet();
      closeModal(true);
    }

    hideAllStages();
    setHidden(bootBox, true);
    setHidden(loginBox, true);
    setHidden(chatBox, false);
    showGuardMessage("", "");

    if (logoutBtn) logoutBtn.hidden = false;
    if (refreshBtn) refreshBtn.hidden = false;
    if (accountBtn) accountBtn.href = "/account/";

    syncCurrentUserAvatar();
    updatePollActionVisibility();
    updateConversationFilterTabs();

    try {
      await syncConversation({
        forceFull: true,
        includeMembers: state.infoSheetOpen,
        conversationId: state.activeConversationId || state.initialConversationId,
        silent: false
      });
    } catch (error) {
      showToast(error && error.message ? error.message : "بارگذاری گفتگوها انجام نشد.");
    }

    startPolling();
  }

  async function handleAuthChange(detail) {
    var payload = asObject(detail) || {};
    var status = normalizeSpace(payload.status || "");

    if (status === "session-restoring" || status === "logging-out" || status === "logging-in") {
      hideAllStages();
      setHidden(loginBox, true);
      setHidden(chatBox, true);
      setHidden(bootBox, false);
      if (status === "logging-out") {
        setBootState("در حال خروج از حساب...");
      } else if (status === "logging-in") {
        setBootState("در حال ورود به حساب...");
      } else {
        setBootState("در حال بازیابی نشست...");
      }
      return;
    }

    if (!payload.loggedIn || !payload.user) {
      resetLoggedOutUi(payload.error || "");
      return;
    }

    await applyAuthenticatedUser(payload.user);
  }

  function nextComposerAttachmentId() {
    return "upl-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 7);
  }

  function uploadedComposerItems() {
    return state.pendingAttachments.filter(function (item) {
      return item && item.status === "uploaded" && item.attachment && item.attachment.id;
    });
  }

  function composerHasUploadingItems() {
    return state.pendingAttachments.some(function (item) {
      return !!item && item.status === "uploading";
    });
  }

  function removeComposerAttachmentByLocalId(localId) {
    var id = normalizeSpace(localId);
    if (!id) return;
    state.pendingAttachments = state.pendingAttachments.filter(function (item) {
      if (!item || item.localId !== id) return true;
      if (item.status === "uploading" && item.xhr && typeof item.xhr.abort === "function") {
        try {
          item.xhr.abort();
        } catch (_error) {
          // Ignore abort failures.
        }
      }
      return false;
    });
    renderComposerUploads();
    updateComposerState();
  }

  function clearComposerAttachments(attachmentIds) {
    var ids = Array.isArray(attachmentIds)
      ? attachmentIds.map(function (value) { return normalizeSpace(value); }).filter(Boolean)
      : [];
    if (!ids.length) {
      state.pendingAttachments = [];
      renderComposerUploads();
      return;
    }
    var idSet = new Set(ids);
    state.pendingAttachments = state.pendingAttachments.filter(function (item) {
      var attachmentId = normalizeSpace(item && item.attachment && item.attachment.id);
      if (!attachmentId || !idSet.has(attachmentId)) return true;
      return false;
    });
    renderComposerUploads();
  }

  function renderComposerUploads() {
    if (!composerUploads) return;
    if (!state.pendingAttachments.length) {
      composerUploads.hidden = true;
      composerUploads.innerHTML = "";
      return;
    }

    composerUploads.hidden = false;
    composerUploads.innerHTML = state.pendingAttachments.map(function (item) {
      var statusLabel = "در حال ارسال";
      if (item.status === "uploaded") {
        statusLabel = "آماده ارسال";
      } else if (item.status === "error") {
        statusLabel = item.error || "خطا در بارگذاری";
      }
      var progressWidth = clamp(toNumber(item.progress, 0), 0, 100);
      return [
        '<article class="composer-upload-item" data-upl-id="' + escapeHtml(item.localId) + '">',
        '  <div class="composer-upload-item__copy">',
        '    <div class="composer-upload-item__name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + "</div>",
        '    <div class="composer-upload-item__meta">' + escapeHtml(statusLabel) + ' • ' + escapeHtml(formatFileSize(item.sizeBytes || 0)) + "</div>",
        item.status === "uploading"
          ? ('    <div class="composer-upload-item__progress"><span style="width:' + progressWidth.toFixed(1) + '%"></span></div>')
          : "",
        "  </div>",
        '  <button type="button" class="composer-upload-item__remove" data-upl-remove="' + escapeHtml(item.localId) + '"' + (item.status === "uploading" ? ' disabled aria-label="در حال بارگذاری"' : ' aria-label="حذف"' ) + ">×</button>",
        "</article>"
      ].join("");
    }).join("");
  }

  function setUploadSheetOpen(open) {
    if (!composerUploadSheet || !attachBtn) return;
    var shouldOpen = !!open;
    composerUploadSheet.hidden = !shouldOpen;
    attachBtn.classList.toggle("is-open", shouldOpen);
  }

  function parseUploadResponse(xhr) {
    var payload = null;
    var raw = toText(xhr.responseText || "");
    if (raw) {
      try {
        payload = JSON.parse(raw);
      } catch (_error) {
        payload = null;
      }
    }
    if (!payload || typeof payload !== "object") {
      payload = {
        success: false,
        error: xhr.status >= 500 ? "خطای داخلی سرور رخ داد." : "پاسخ نامعتبر از سرور دریافت شد."
      };
    }
    payload.httpStatus = xhr.status;
    return payload;
  }

  function uploadAttachmentRequest(file, conversationId, options, onProgress) {
    var opts = asObject(options) || {};
    return new Promise(function (resolve) {
      var xhr = new XMLHttpRequest();
      var form = new FormData();
      form.append("action", "uploadAttachment");
      form.append("conversationId", conversationId);
      form.append("file", file, file.name || "file");
      if (opts.isVoice) {
        form.append("isVoice", "1");
      }
      if (opts.durationSeconds != null) {
        form.append("durationSeconds", String(Math.max(0, toNumber(opts.durationSeconds, 0))));
      }

      xhr.open("POST", "/chat/chat_api.php", true);
      xhr.withCredentials = true;
      xhr.setRequestHeader("Accept", "application/json");

      xhr.upload.onprogress = function (event) {
        if (!event || !event.lengthComputable || typeof onProgress !== "function") return;
        var percent = event.total > 0 ? (event.loaded / event.total) * 100 : 0;
        onProgress(percent);
      };

      xhr.onload = function () {
        resolve({ response: parseUploadResponse(xhr), xhr: xhr });
      };
      xhr.onerror = function () {
        resolve({
          response: {
            success: false,
            error: "ارتباط با سرور برقرار نشد.",
            networkError: true,
            httpStatus: 0
          },
          xhr: xhr
        });
      };
      xhr.onabort = function () {
        resolve({
          response: {
            success: false,
            error: "بارگذاری لغو شد.",
            aborted: true,
            httpStatus: 0
          },
          xhr: xhr
        });
      };
      xhr.send(form);
    });
  }

  async function queueAttachmentUpload(file, options) {
    var conversation = activeConversation();
    var opts = asObject(options) || {};
    if (!conversation) {
      showToast("ابتدا یک گفت‌وگو را انتخاب کن.");
      return null;
    }
    if (!conversation.permissions || conversation.permissions.canSend === false) {
      showToast("در این گفت‌وگو اجازه ارسال فایل ندارید.");
      return null;
    }
    if (!file) return null;

    if (file.size > MAX_MEDIA_BYTES) {
      showToast("حجم فایل از سقف مجاز بیشتر است.");
      return null;
    }

    var localId = nextComposerAttachmentId();
    var item = {
      localId: localId,
      name: normalizeSpace(file.name) || (opts.isVoice ? "voice-note" : "file"),
      sizeBytes: Math.max(0, Math.floor(toNumber(file.size, 0))),
      status: "uploading",
      progress: 0,
      attachment: null,
      error: "",
      xhr: null
    };
    state.pendingAttachments.push(item);
    renderComposerUploads();
    updateComposerState();

    var uploadResult = await uploadAttachmentRequest(file, conversation.id, opts, function (progress) {
      item.progress = progress;
      renderComposerUploads();
    });
    item.xhr = uploadResult.xhr;

    var response = uploadResult.response;
    if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
      item.status = "error";
      item.error = "نشست شما منقضی شده است.";
      renderComposerUploads();
      updateComposerState();
      return null;
    }

    if (!response || !response.success || !response.attachment) {
      item.status = "error";
      item.error = (response && response.error) || "بارگذاری فایل انجام نشد.";
      renderComposerUploads();
      updateComposerState();
      return null;
    }

    item.status = "uploaded";
    item.progress = 100;
    item.error = "";
    item.attachment = normalizeAttachment(response.attachment);
    renderComposerUploads();
    if (response.notice) {
      setComposerStatus(response.notice, "");
    }
    updateComposerState();
    return item.attachment;
  }

  function pickAttachmentFiles(accept, label) {
    if (!attachmentInput) return;
    attachmentInput.accept = accept || "*/*";
    attachmentInput.dataset.attachLabel = label || "فایل";
    attachmentInput.click();
  }

  function bestVoiceMimeType() {
    if (typeof window.MediaRecorder !== "function" || typeof window.MediaRecorder.isTypeSupported !== "function") {
      return "";
    }
    for (var i = 0; i < VOICE_MIME_CANDIDATES.length; i += 1) {
      if (window.MediaRecorder.isTypeSupported(VOICE_MIME_CANDIDATES[i])) {
        return VOICE_MIME_CANDIDATES[i];
      }
    }
    return "";
  }

  function voiceFileExtension(mimeType) {
    var mime = normalizeSpace(mimeType).toLowerCase();
    if (mime.indexOf("ogg") !== -1) return "ogg";
    if (mime.indexOf("mp4") !== -1 || mime.indexOf("m4a") !== -1) return "m4a";
    return "webm";
  }

  function updateVoiceUi() {
    var recorder = state.voiceRecorder;
    if (!composerVoice || !voiceBtn) return;
    var active = !!recorder;
    composerVoice.hidden = !active;
    voiceBtn.classList.toggle("is-recording", !!(recorder && recorder.recording));
    if (composerVoiceTimer) {
      var elapsed = recorder ? Math.max(0, Math.floor(toNumber(recorder.elapsedSeconds, 0))) : 0;
      composerVoiceTimer.textContent = formatDuration(elapsed);
    }
    if (voiceStopBtn) {
      voiceStopBtn.disabled = !recorder || !recorder.recording;
    }
    if (voiceSendBtn) {
      voiceSendBtn.disabled = !recorder || (!recorder.recording && !recorder.blob);
    }
    if (voiceCancelBtn) {
      voiceCancelBtn.disabled = !recorder;
    }
    updateComposerState();
  }

  function stopVoiceTracks(stream) {
    if (!stream || typeof stream.getTracks !== "function") return;
    stream.getTracks().forEach(function (track) {
      if (track && typeof track.stop === "function") {
        track.stop();
      }
    });
  }

  function resetVoiceRecorder() {
    var recorder = state.voiceRecorder;
    if (recorder && recorder.timerId) {
      window.clearInterval(recorder.timerId);
    }
    if (recorder && recorder.stream) {
      stopVoiceTracks(recorder.stream);
    }
    state.voiceRecorder = null;
    updateVoiceUi();
  }

  async function startVoiceRecording() {
    var conversation = activeConversation();
    if (!conversation) {
      showToast("ابتدا یک گفت‌وگو را انتخاب کن.");
      return;
    }
    if (conversation.permissions && conversation.permissions.canSend === false) {
      showToast("در این گفت‌وگو اجازه ارسال پیام ندارید.");
      return;
    }
    if (conversation.settings && conversation.settings.muted) {
      showToast("ارسال پیام در این گفت‌وگو بسته است.");
      return;
    }
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== "function" || typeof window.MediaRecorder !== "function") {
      showToast("مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.");
      return;
    }
    if (state.voiceRecorder) {
      return;
    }

    try {
      var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      var mimeType = bestVoiceMimeType();
      var mediaRecorder = mimeType ? new window.MediaRecorder(stream, { mimeType: mimeType }) : new window.MediaRecorder(stream);
      var recorderState = {
        mediaRecorder: mediaRecorder,
        stream: stream,
        chunks: [],
        startedAt: Date.now(),
        elapsedSeconds: 0,
        timerId: null,
        recording: true,
        blob: null,
        mimeType: mimeType || mediaRecorder.mimeType || "audio/webm",
        sendAfterStop: false
      };
      state.voiceRecorder = recorderState;
      if (voiceBtn) {
        voiceBtn.classList.add("is-recording");
      }

      mediaRecorder.ondataavailable = function (event) {
        if (!event || !event.data || !event.data.size) return;
        recorderState.chunks.push(event.data);
      };
      mediaRecorder.onstop = function () {
        recorderState.recording = false;
        recorderState.elapsedSeconds = Math.max(1, Math.floor((Date.now() - recorderState.startedAt) / 1000));
        if (recorderState.timerId) {
          window.clearInterval(recorderState.timerId);
          recorderState.timerId = null;
        }
        if (recorderState.chunks.length) {
          recorderState.blob = new Blob(recorderState.chunks, { type: recorderState.mimeType || "audio/webm" });
        }
        stopVoiceTracks(recorderState.stream);
        updateVoiceUi();
        if (recorderState.sendAfterStop && recorderState.blob) {
          sendRecordedVoice(recorderState).catch(function (error) {
            showToast(error && error.message ? error.message : "ارسال پیام صوتی انجام نشد.");
          });
        }
      };
      mediaRecorder.onerror = function () {
        showToast("ضبط صدا با خطا متوقف شد.");
        resetVoiceRecorder();
      };

      mediaRecorder.start(250);
      recorderState.timerId = window.setInterval(function () {
        recorderState.elapsedSeconds = Math.max(0, Math.floor((Date.now() - recorderState.startedAt) / 1000));
        updateVoiceUi();
      }, 300);
      setComposerStatus("در حال ضبط پیام صوتی...", "");
      updateVoiceUi();
    } catch (_error) {
      showToast("دسترسی میکروفون داده نشد یا ضبط صدا شروع نشد.");
      resetVoiceRecorder();
    }
  }

  function stopVoiceRecording(sendAfterStop) {
    var recorder = state.voiceRecorder;
    if (!recorder) return;
    if (!recorder.recording) {
      if (sendAfterStop && recorder.blob) {
        recorder.sendAfterStop = true;
        sendRecordedVoice(recorder).catch(function (error) {
          showToast(error && error.message ? error.message : "ارسال پیام صوتی انجام نشد.");
        });
      }
      return;
    }
    recorder.sendAfterStop = !!sendAfterStop;
    try {
      recorder.mediaRecorder.stop();
    } catch (_error) {
      resetVoiceRecorder();
    }
    updateVoiceUi();
  }

  async function sendRecordedVoice(recorderState) {
    if (!recorderState || !recorderState.blob) {
      throw new Error("فایل صوتی آماده ارسال نیست.");
    }
    var extension = voiceFileExtension(recorderState.mimeType);
    var fileName = "voice-" + Date.now() + "." + extension;
    var file = new File([recorderState.blob], fileName, {
      type: recorderState.mimeType || recorderState.blob.type || "audio/webm"
    });
    var attachment = await queueAttachmentUpload(file, {
      isVoice: true,
      durationSeconds: recorderState.elapsedSeconds || 0
    });
    if (!attachment || !attachment.id) {
      throw new Error("بارگذاری پیام صوتی انجام نشد.");
    }
    await sendCurrentMessage({
      attachmentIds: [attachment.id],
      text: "",
      fromVoice: true
    });
    resetVoiceRecorder();
  }

  async function sendCurrentMessage(options) {
    var opts = asObject(options) || {};
    var conversation = activeConversation();
    if (!conversation || !chatTextEl || !sendBtn || sendBtn.disabled) return;
    var response = null;

    if (composerHasUploadingItems()) {
      showToast("بارگذاری فایل‌ها هنوز کامل نشده است.");
      return;
    }

    var text = opts.text != null ? toText(opts.text).trim() : toText(chatTextEl.value).trim();
    if (text.length > MAX_MESSAGE_SIZE) {
      text = text.slice(0, MAX_MESSAGE_SIZE);
    }

    var attachmentIds = Array.isArray(opts.attachmentIds)
      ? opts.attachmentIds.map(function (value) { return normalizeSpace(value); }).filter(Boolean)
      : uploadedComposerItems().map(function (item) { return normalizeSpace(item.attachment.id); }).filter(Boolean);

    if (!text && !attachmentIds.length) {
      return;
    }

    var payload = {
      conversationId: conversation.id,
      text: text
    };
    if (attachmentIds.length) {
      payload.attachmentIds = JSON.stringify(attachmentIds);
      payload.kind = attachmentIds.length === 1 && opts.fromVoice ? "voice" : "attachment";
    }
    if (state.replyTargetId) {
      payload.replyTo = String(state.replyTargetId);
    }

    sendBtn.disabled = true;
    setComposerStatus("در حال ارسال...", "");

    try {
      response = await apiPost("send", payload);
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ارسال پیام انجام نشد.");
      state.connectionIssue = false;

      var message = normalizeMessage(response.message);
      if (message) {
        appendMessages([message], {
          replaceAll: false,
          forceStick: true,
          smooth: true,
          markNew: true
        });
      }

      var nextConversation = normalizeConversation(response.conversation);
      if (nextConversation) {
        upsertConversation(nextConversation);
        rebuildConversationsFromMap();
        renderConversationList();
        updateThreadHead();
      }

      if (opts.text == null) {
        chatTextEl.value = "";
      }
      autosizeComposer();
      clearReplyTarget();
      clearComposerAttachments(attachmentIds);
      setComposerStatus("", "");
      setConnectionState("live", "متصل");
    } catch (error) {
      if (response && (response.networkError || toNumber(response.httpStatus, 0) <= 0)) {
        state.connectionIssue = true;
        setConnectionState("issue", "در انتظار اتصال");
      }
      setComposerStatus(error && error.message ? error.message : "ارسال پیام انجام نشد.", "error");
      showToast(error && error.message ? error.message : "ارسال پیام انجام نشد.");
    } finally {
      if (!chatTextEl.disabled) {
        sendBtn.disabled = false;
      }
      updateComposerState();
    }
  }

  async function toggleReaction(message, emoji) {
    var conversation = activeConversation();
    var normalizedEmoji = normalizeSpace(emoji);
    if (!conversation || !message || !normalizedEmoji) return false;
    if (!isLikelyEmoji(normalizedEmoji)) {
      showToast("ایموجی واکنش معتبر نیست.");
      return false;
    }

    try {
      var response = await apiPost("react", {
        conversationId: conversation.id,
        id: String(message.id),
        messageId: String(message.id),
        emoji: normalizedEmoji
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ثبت واکنش انجام نشد.");

      var updated = normalizeMessage(response.message);
      if (updated) {
        replaceMessageInDom(updated);
        var stillOwn = reactionEntries(updated).some(function (entry) {
          return entry.emoji === normalizedEmoji && entry.own;
        });
        if (stillOwn) {
          pushRecentReaction(normalizedEmoji);
        }
      }
      setConnectionState("live", "متصل");
      closeContextMenu();
      return true;
    } catch (error) {
      showToast(error && error.message ? error.message : "ثبت واکنش انجام نشد.");
      return false;
    }
  }

  async function submitEditedMessage(message, nextText) {
    var conversation = activeConversation();
    if (!conversation || !message) return false;
    var next = toText(nextText).trim();
    if (!next) {
      showToast("متن پیام نمی‌تواند خالی باشد.");
      return false;
    }

    try {
      var response = await apiPost("edit", {
        conversationId: conversation.id,
        id: String(message.id),
        text: next
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ویرایش پیام انجام نشد.");

      var updated = normalizeMessage(response.message);
      if (updated) {
        replaceMessageInDom(updated);
      }
      showToast("پیام ویرایش شد.");
      return true;
    } catch (error) {
      showToast(error && error.message ? error.message : "ویرایش پیام انجام نشد.");
      return false;
    }
  }

  async function saveEditedMessageFromModal() {
    var message = findMessage(state.pendingEditMessageId);
    if (!message || !editTextInput) return;
    var next = toText(editTextInput.value).trim();
    if (!next) {
      showToast("متن پیام نمی‌تواند خالی باشد.");
      return;
    }
    setModalBusy("edit", true);
    var ok = await submitEditedMessage(message, next);
    setModalBusy("edit", false);
    if (ok) {
      closeModal(true);
    }
  }

  function openEditPrompt(message) {
    if (!message) return;
    if (!editModal || !editTextInput) {
      var edited = window.prompt("ویرایش پیام", toText(message.text));
      if (edited === null) return;
      submitEditedMessage(message, edited);
      return;
    }
    state.pendingEditMessageId = message.id;
    editTextInput.value = toText(message.text);
    openModal(editModal, "edit");
    window.setTimeout(function () {
      if (!editTextInput) return;
      editTextInput.focus({ preventScroll: true });
      editTextInput.setSelectionRange(editTextInput.value.length, editTextInput.value.length);
    }, 40);
  }

  async function deleteMessage(message) {
    var conversation = activeConversation();
    if (!conversation || !message) return;
    var approved = await openConfirmDialog({
      title: "حذف پیام",
      message: "این پیام برای همیشه از گفتگو حذف می‌شود.",
      acceptLabel: "حذف پیام",
      danger: true
    });
    if (!approved) return;

    try {
      var response = await apiPost("delete", {
        conversationId: conversation.id,
        id: String(message.id)
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "حذف پیام انجام نشد.");

      removeMessageFromDom(response.deletedId || message.id);
      showToast("پیام حذف شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "حذف پیام انجام نشد.");
    }
  }

  async function togglePin(message, pin) {
    var conversation = activeConversation();
    if (!conversation || !message) return;

    try {
      var response = await apiPost("pin", {
        conversationId: conversation.id,
        id: String(message.id),
        pinned: pin ? "1" : "0"
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر سنجاق پیام انجام نشد.");

      var updated = normalizeMessage(response.message);
      if (updated) {
        replaceMessageInDom(updated);
      }

      await syncConversation({
        forceFull: false,
        includeMembers: state.infoSheetOpen,
        silent: true,
        conversationId: conversation.id
      });

      showToast(pin ? "پیام سنجاق شد." : "سنجاق پیام برداشته شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "تغییر سنجاق پیام انجام نشد.");
    }
  }

  async function setConversationPinById(conversationId, pinned, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var response = await apiPost("setPinConversation", {
        conversationId: conversation.id,
        pinned: pinned ? "1" : "0"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر وضعیت سنجاق انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(pinned ? "گفتگو سنجاق شد." : "سنجاق گفتگو برداشته شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "تغییر وضعیت سنجاق انجام نشد.");
      }
      return false;
    }
  }

  async function setConversationReadStateById(conversationId, read, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var action = read ? "markRead" : "markUnread";
      var payload = { conversationId: conversation.id };
      if (read && conversation.lastMessage && conversation.lastMessage.id) {
        payload.lastReadMessageId = String(conversation.lastMessage.id);
      }
      var response = await apiPost(action, payload);
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "به‌روزرسانی وضعیت خوانده‌نشده انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(read ? "گفتگو خوانده‌شده علامت خورد." : "گفتگو خوانده‌نشده علامت خورد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "به‌روزرسانی وضعیت خوانده‌نشده انجام نشد.");
      }
      return false;
    }
  }

  async function setConversationMuteById(conversationId, muted, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var response = await apiPost("setMute", {
        conversationId: conversation.id,
        muted: muted ? "1" : "0"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر وضعیت سکوت گفتگو انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(muted ? "گفتگو بی‌صدا شد." : "گفتگو از حالت بی‌صدا خارج شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "تغییر وضعیت سکوت گفتگو انجام نشد.");
      }
      return false;
    }
  }

  async function setConversationArchiveById(conversationId, archived, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    try {
      var response = await apiPost("setArchive", {
        conversationId: conversation.id,
        archived: archived ? "1" : "0"
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "تغییر وضعیت بایگانی انجام نشد.");
      await refreshAfterConversationAction(response, state.activeConversationId || conversation.id, {
        keepCurrentActive: true,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: false
      });
      if (!opts.silentToast) {
        showToast(archived ? "گفتگو بایگانی شد." : "گفتگو از بایگانی خارج شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "تغییر وضعیت بایگانی انجام نشد.");
      }
      return false;
    }
  }

  async function deleteConversationById(conversationId, options) {
    var conversation = state.conversationsById.get(normalizeSpace(conversationId));
    if (!conversation) return false;
    var opts = asObject(options) || {};
    if (!opts.skipConfirm) {
      var isDirectConversation = conversation.type === "direct";
      var approved = await openConfirmDialog({
        title: "حذف گفتگو",
        message: isDirectConversation
          ? "این گفتگوی خصوصی از لیست شما حذف می‌شود."
          : "این گفتگوی گروهی برای همه اعضا حذف می‌شود.",
        acceptLabel: "حذف",
        danger: true
      });
      if (!approved) return false;
    }
    try {
      var response = await apiPost("deleteConversation", {
        conversationId: conversation.id
      });
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "حذف گفتگو انجام نشد.");
      state.selectedConversationIds.delete(conversation.id);
      await refreshAfterConversationAction(response, "", {
        keepCurrentActive: !opts.focusDeleted,
        skipSync: !!opts.skipSync,
        silent: true,
        forceFull: true
      });
      if (!opts.silentToast) {
        showToast("گفتگو حذف شد.");
      }
      return true;
    } catch (error) {
      if (!opts.silentToast) {
        showToast((error && error.message) || "حذف گفتگو انجام نشد.");
      }
      return false;
    }
  }

  async function runConversationBatchAction(actionKey) {
    var action = normalizeSpace(actionKey).toLowerCase();
    var list = selectedConversations();
    if (!list.length) {
      showToast("هیچ گفتگویی انتخاب نشده است.");
      return;
    }

    var requiresDeleteConfirm = action === "delete";
    if (requiresDeleteConfirm) {
      var approved = await openConfirmDialog({
        title: "حذف گفتگوهای انتخاب‌شده",
        message: "گفتگوهای انتخاب‌شده در موارد مجاز حذف می‌شوند.",
        acceptLabel: "حذف موارد انتخاب‌شده",
        danger: true
      });
      if (!approved) return;
    }

    closeListContextMenu();
    var success = 0;
    var skipped = 0;
    for (var i = 0; i < list.length; i += 1) {
      var conversation = list[i];
      if (!conversation) {
        skipped += 1;
        continue;
      }
      var permissions = conversation.permissions || {};
      var ok = false;
      if (action === "read") {
        if (!permissions.canMarkRead) {
          skipped += 1;
          continue;
        }
        ok = await setConversationReadStateById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unread") {
        if (!permissions.canMarkUnread) {
          skipped += 1;
          continue;
        }
        ok = await setConversationReadStateById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "pin") {
        if (!permissions.canPinConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationPinById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unpin") {
        if (!permissions.canPinConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationPinById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "archive") {
        if (!permissions.canArchiveConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationArchiveById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unarchive") {
        if (!permissions.canArchiveConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationArchiveById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "mute") {
        if (!permissions.canMuteConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationMuteById(conversation.id, true, { skipSync: true, silentToast: true });
      } else if (action === "unmute") {
        if (!permissions.canMuteConversation) {
          skipped += 1;
          continue;
        }
        ok = await setConversationMuteById(conversation.id, false, { skipSync: true, silentToast: true });
      } else if (action === "delete") {
        if (!permissions.canDeleteConversation) {
          skipped += 1;
          continue;
        }
        ok = await deleteConversationById(conversation.id, { skipConfirm: true, skipSync: true, silentToast: true });
      }
      if (ok) {
        success += 1;
      } else {
        skipped += 1;
      }
    }

    exitConversationSelectionMode();
    await syncConversation({
      forceFull: true,
      includeMembers: state.infoSheetOpen,
      silent: true,
      conversationId: state.activeConversationId || ""
    });

    showToast(success.toLocaleString("fa-IR") + " انجام شد" + (skipped > 0 ? (" • " + skipped.toLocaleString("fa-IR") + " مورد رد شد") : ""));
  }

  async function setConversationMute(muted) {
    var conversation = activeConversation();
    if (!conversation) return;
    await setConversationMuteById(conversation.id, muted);
  }

  async function refreshAfterConversationAction(response, fallbackConversationId, options) {
    var opts = asObject(options) || {};
    var conversations = (Array.isArray(response && response.conversations) ? response.conversations : [])
      .map(normalizeConversation)
      .filter(Boolean);
    if (conversations.length) {
      replaceConversations(conversations);
    } else {
      rebuildConversationsFromMap();
    }

    var responseConversation = normalizeConversation(response && response.conversation);
    if (responseConversation) {
      upsertConversation(responseConversation);
      rebuildConversationsFromMap();
    }

    var preferredConversationId = normalizeSpace(opts.keepCurrentActive ? state.activeConversationId : "");
    if (!preferredConversationId || !state.conversationsById.has(preferredConversationId)) {
      preferredConversationId = normalizeSpace(
        (response && response.conversationId)
        || (responseConversation && responseConversation.id)
        || fallbackConversationId
        || state.activeConversationId
        || (state.conversations[0] && state.conversations[0].id)
        || ""
      );
    }
    state.activeConversationId = preferredConversationId;

    if (opts.skipSync) {
      var hasActiveConversation = !!state.activeConversationId && state.conversationsById.has(state.activeConversationId);
      setThreadVisible(hasActiveConversation);
      if (!hasActiveConversation) {
        clearThreadState();
      }
      renderConversationList();
      updateThreadHead();
      updateComposerState();
      updatePinnedUi();
      var activeMutedConversation = activeConversation();
      updateMuteUi(activeMutedConversation && activeMutedConversation.settings ? activeMutedConversation.settings : { muted: false });
      updateInfoSheet();
      updateSelectionUi();
      return;
    }

    state.lastMessageId = 0;
    await syncConversation({
      forceFull: opts.forceFull !== false,
      includeMembers: state.infoSheetOpen,
      silent: !!opts.silent,
      conversationId: preferredConversationId
    });
  }

  async function setConversationArchive(archived) {
    var conversation = activeConversation();
    if (!conversation) return;
    await setConversationArchiveById(conversation.id, archived);
  }

  async function clearConversationHistory() {
    var conversation = activeConversation();
    if (!conversation) return;
    var approved = await openConfirmDialog({
      title: "پاک‌کردن تاریخچه",
      message: "تمام پیام‌های این گفتگو پاک می‌شود. این عملیات قابل بازگشت نیست.",
      acceptLabel: "پاک‌کردن تاریخچه",
      danger: true
    });
    if (!approved) return;

    try {
      var response = await apiPost("clearHistory", {
        conversationId: conversation.id
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "پاک‌کردن تاریخچه انجام نشد.");

      await refreshAfterConversationAction(response, conversation.id);
      var clearedCount = Math.max(0, Math.floor(toNumber(response && response.clearedCount, 0)));
      if (clearedCount > 0) {
        showToast("تاریخچه گفتگو پاک شد (" + clearedCount.toLocaleString("fa-IR") + " پیام).");
      } else {
        showToast("تاریخچه از قبل خالی بود.");
      }
    } catch (error) {
      showToast(error && error.message ? error.message : "پاک‌کردن تاریخچه انجام نشد.");
    }
  }

  async function leaveCurrentConversation() {
    var conversation = activeConversation();
    if (!conversation) return;
    var approved = await openConfirmDialog({
      title: "ترک گروه",
      message: "با ترک گروه، پیام‌های جدید این گروه را دریافت نمی‌کنی.",
      acceptLabel: "ترک گروه",
      danger: true
    });
    if (!approved) return;

    try {
      var response = await apiPost("leaveConversation", {
        conversationId: conversation.id
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "خروج از گروه انجام نشد.");

      closeInfoSheet();
      await refreshAfterConversationAction(response, "");
      showToast("از گروه خارج شدی.");
    } catch (error) {
      showToast(error && error.message ? error.message : "خروج از گروه انجام نشد.");
    }
  }

  async function deleteCurrentConversation() {
    var conversation = activeConversation();
    if (!conversation) return;
    var deleted = await deleteConversationById(conversation.id);
    if (deleted) {
      closeInfoSheet();
    }
  }

  async function startDirectConversation(studentNumber) {
    var peerStudentNumber = normalizeStudentNumber(studentNumber);
    if (!peerStudentNumber || state.pendingDirectStart) return;

    state.pendingDirectStart = true;
    setModalBusy("dm", true);

    try {
      var response = await apiPost("startDirect", {
        peerStudentNumber: peerStudentNumber
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "شروع گفت‌وگوی خصوصی انجام نشد.");

      var conversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      if (conversations.length) {
        replaceConversations(conversations);
      }

      var currentConversation = normalizeConversation(response.conversation);
      if (currentConversation) {
        upsertConversation(currentConversation);
        rebuildConversationsFromMap();
      }

      var conversationId = normalizeSpace(
        response.conversationId || (currentConversation && currentConversation.id)
      );
      if (!conversationId) {
        throw new Error("گفت‌وگوی خصوصی ایجاد شد اما شناسه معتبر برنگشت.");
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "dm-create",
        silent: false,
        mobileView: "list"
      });
      showToast("گفت‌وگوی خصوصی آماده است.");
    } catch (error) {
      showToast(error && error.message ? error.message : "شروع گفت‌وگوی خصوصی انجام نشد.");
    } finally {
      state.pendingDirectStart = false;
      setModalBusy("dm", false);
    }
  }

  async function createGroupConversation() {
    if (state.pendingGroupCreate) return;

    var selected = Array.from(state.groupMemberSelection);
    if (!selected.length) {
      showToast("حداقل یک عضو برای ساخت گروه انتخاب کن.");
      setGroupCreateStep("members");
      return;
    }

    var title = normalizeSpace(groupTitleInput && groupTitleInput.value);
    if (!title) {
      showToast("نام گروه را وارد کن.");
      setGroupCreateStep("details");
      if (groupTitleInput) groupTitleInput.focus({ preventScroll: true });
      return;
    }

    var about = normalizeSpace(groupAboutInput && groupAboutInput.value);

    state.pendingGroupCreate = true;
    setModalBusy("group", true);

    try {
      var response = await apiPost("createGroup", {
        title: title,
        about: about,
        membersJson: JSON.stringify(selected)
      });

      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      ensureSuccessResponse(response, "ساخت گروه انجام نشد.");

      var conversations = (Array.isArray(response.conversations) ? response.conversations : [])
        .map(normalizeConversation)
        .filter(Boolean);
      if (conversations.length) {
        replaceConversations(conversations);
      }

      var currentConversation = normalizeConversation(response.conversation);
      if (currentConversation) {
        upsertConversation(currentConversation);
        rebuildConversationsFromMap();
      }

      var conversationId = normalizeSpace(
        response.conversationId || (currentConversation && currentConversation.id)
      );
      if (!conversationId) {
        throw new Error("گروه ساخته شد اما شناسه معتبر برنگشت.");
      }

      closeModal(true);
      await openConversation(conversationId, {
        forceFull: true,
        source: "group-create",
        silent: false,
        mobileView: "list"
      });
      var createdGroupTitle = normalizeSpace(currentConversation && currentConversation.title) || title || "گروه";
      showToast("گروه «" + createdGroupTitle + "» ساخته شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "ساخت گروه انجام نشد.");
    } finally {
      state.pendingGroupCreate = false;
      setModalBusy("group", false);
    }
  }

  function openDmCreationFlow() {
    return loadDirectory(false).then(function () {
      openModal(dmModal, "dm");
      if (dmSearch) dmSearch.value = "";
      renderDmList();
      if (dmSearch && !isMobileViewport()) {
        dmSearch.focus({ preventScroll: true });
      }
    }).catch(function (error) {
      showToast(error && error.message ? error.message : "بارگذاری فهرست کاربران انجام نشد.");
    });
  }

  function openGroupCreationFlow() {
    return loadDirectory(false).then(function () {
      openModal(groupModal, "group");
      resetGroupCreateFlow();
      renderGroupMembersPicker();
      if (groupSearch && !isMobileViewport()) {
        groupSearch.focus({ preventScroll: true });
      }
    }).catch(function (error) {
      showToast(error && error.message ? error.message : "بارگذاری فهرست کاربران انجام نشد.");
    });
  }

  function bindEvents() {
    if (conversationSearch) {
      conversationSearch.addEventListener("input", function () {
        state.conversationFilter = conversationSearch.value || "";
        renderConversationList();
      });
    }
    if (conversationFilterTabs) {
      conversationFilterTabs.addEventListener("click", function (event) {
        var button = event.target && event.target.closest ? event.target.closest("[data-list-filter]") : null;
        if (!button) return;
        setConversationListCategory(button.getAttribute("data-list-filter"));
      });
    }
    if (conversationManageBtn) {
      conversationManageBtn.addEventListener("click", function () {
        if (state.listSelectionMode) {
          exitConversationSelectionMode();
        } else {
          enterConversationSelectionMode("");
        }
      });
    }
    if (conversationSelectionClear) {
      conversationSelectionClear.addEventListener("click", function () {
        exitConversationSelectionMode();
      });
    }
    if (conversationSelectionToggleAll) {
      conversationSelectionToggleAll.addEventListener("click", function () {
        toggleSelectAllVisibleConversations();
      });
    }
    if (conversationBatchRead) {
      conversationBatchRead.addEventListener("click", function () { runConversationBatchAction("read"); });
    }
    if (conversationBatchUnread) {
      conversationBatchUnread.addEventListener("click", function () { runConversationBatchAction("unread"); });
    }
    if (conversationBatchPin) {
      conversationBatchPin.addEventListener("click", function () { runConversationBatchAction("pin"); });
    }
    if (conversationBatchUnpin) {
      conversationBatchUnpin.addEventListener("click", function () { runConversationBatchAction("unpin"); });
    }
    if (conversationBatchArchive) {
      conversationBatchArchive.addEventListener("click", function () { runConversationBatchAction("archive"); });
    }
    if (conversationBatchUnarchive) {
      conversationBatchUnarchive.addEventListener("click", function () { runConversationBatchAction("unarchive"); });
    }
    if (conversationBatchMute) {
      conversationBatchMute.addEventListener("click", function () { runConversationBatchAction("mute"); });
    }
    if (conversationBatchUnmute) {
      conversationBatchUnmute.addEventListener("click", function () { runConversationBatchAction("unmute"); });
    }
    if (conversationBatchDelete) {
      conversationBatchDelete.addEventListener("click", function () { runConversationBatchAction("delete"); });
    }
    if (newDmBtn) newDmBtn.addEventListener("click", openDmCreationFlow);
    if (newGroupBtn) newGroupBtn.addEventListener("click", openGroupCreationFlow);

    if (newPollLink) {
      newPollLink.addEventListener("click", function (event) {
        if (canOpenPollCenterForUser(state.me)) return;
        event.preventDefault();
        showToast("دسترسی به نظرسنجی فقط برای مالک یا نماینده فعال است.");
      });
    }

    if (dmSearch) {
      dmSearch.addEventListener("input", renderDmList);
    }

    if (groupSearch) {
      groupSearch.addEventListener("input", renderGroupMembersPicker);
    }

    if (groupCreateBtn) {
      groupCreateBtn.addEventListener("click", function () {
        createGroupConversation();
      });
    }

    if (groupStepNext) {
      groupStepNext.addEventListener("click", function () {
        if (!state.groupMemberSelection.size) {
          showToast("حداقل یک عضو برای ادامه انتخاب کن.");
          return;
        }
        setGroupCreateStep("details");
        if (groupTitleInput) {
          groupTitleInput.focus({ preventScroll: true });
        }
      });
    }

    if (groupStepBack) {
      groupStepBack.addEventListener("click", function () {
        setGroupCreateStep("members");
      });
    }

    if (groupSelectedMembers) {
      groupSelectedMembers.addEventListener("click", function (event) {
        var button = event.target.closest("[data-remove-member]");
        if (!button) return;
        var studentNumber = normalizeStudentNumber(button.getAttribute("data-remove-member"));
        if (!studentNumber) return;
        state.groupMemberSelection.delete(studentNumber);
        renderGroupMembersPicker();
      });
    }

    if (dmModalClose) dmModalClose.addEventListener("click", closeModal);
    if (groupModalClose) groupModalClose.addEventListener("click", closeModal);
    if (forwardModalClose) forwardModalClose.addEventListener("click", closeModal);
    if (reactionModalClose) reactionModalClose.addEventListener("click", closeModal);
    if (editModalClose) editModalClose.addEventListener("click", closeModal);
    if (editCancelBtn) editCancelBtn.addEventListener("click", closeModal);
    if (confirmModalClose) {
      confirmModalClose.addEventListener("click", function () {
        resolveConfirmDialog(false);
        closeModal(true);
      });
    }
    if (confirmCancelBtn) {
      confirmCancelBtn.addEventListener("click", function () {
        resolveConfirmDialog(false);
        closeModal(true);
      });
    }
    if (confirmAcceptBtn) {
      confirmAcceptBtn.addEventListener("click", function () {
        resolveConfirmDialog(true);
        closeModal(true);
      });
    }
    if (editSaveBtn) editSaveBtn.addEventListener("click", saveEditedMessageFromModal);
    if (modalBackdrop) modalBackdrop.addEventListener("click", closeModal);
    if (forwardSearch) {
      forwardSearch.addEventListener("input", renderForwardList);
    }
    if (reactionSearch) {
      reactionSearch.addEventListener("input", renderReactionPicker);
    }
    if (reactionNativeBtn) {
      reactionNativeBtn.addEventListener("click", function () {
        openNativeEmojiPicker();
      });
    }
    if (editTextInput) {
      editTextInput.addEventListener("keydown", function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
          event.preventDefault();
          saveEditedMessageFromModal();
        }
      });
    }

    if (threadInfoTrigger) {
      threadInfoTrigger.addEventListener("click", function () {
        openInfoSheet().catch(function (error) {
          console.error(error);
          showToast("باز کردن اطلاعات گفتگو انجام نشد.");
        });
      });
    }
    if (infoSheetClose) infoSheetClose.addEventListener("click", closeInfoSheet);
    if (infoSheetBackdrop) infoSheetBackdrop.addEventListener("click", closeInfoSheet);

    if (muteBtn) muteBtn.addEventListener("click", function () { setConversationMute(true); });
    if (unmuteBtn) unmuteBtn.addEventListener("click", function () { setConversationMute(false); });
    if (archiveBtn) archiveBtn.addEventListener("click", function () { setConversationArchive(true); });
    if (unarchiveBtn) unarchiveBtn.addEventListener("click", function () { setConversationArchive(false); });
    if (clearHistoryBtn) clearHistoryBtn.addEventListener("click", clearConversationHistory);
    if (leaveBtn) leaveBtn.addEventListener("click", leaveCurrentConversation);
    if (deleteConversationBtn) deleteConversationBtn.addEventListener("click", deleteCurrentConversation);
    if (unpinBtn) {
      unpinBtn.addEventListener("click", function () {
        var conversation = activeConversation();
        if (!conversation || !conversation.pinnedMessage) return;
        togglePin(conversation.pinnedMessage, false);
      });
    }

    if (pinnedWrap) {
      pinnedWrap.addEventListener("click", function () {
        var conversation = activeConversation();
        if (!conversation || !conversation.pinnedMessage) return;
        scrollToMessage(conversation.pinnedMessage.id);
      });
    }

    if (contextBackdrop) contextBackdrop.addEventListener("click", closeContextMenu);
    if (listContextBackdrop) listContextBackdrop.addEventListener("click", closeListContextMenu);
    if (messagesEl) messagesEl.addEventListener("scroll", function () {
      if (state.contextOpen) closeContextMenu();
      if (state.listContextOpen) closeListContextMenu();
      state.threadAutoStick = isThreadNearBottom(56);
    });

    if (chatTextEl) {
      chatTextEl.addEventListener("input", autosizeComposer);
      chatTextEl.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && !event.shiftKey) {
          event.preventDefault();
          sendCurrentMessage();
        }
      });
    }

    if (sendBtn) sendBtn.addEventListener("click", sendCurrentMessage);
    if (attachBtn) {
      attachBtn.addEventListener("click", function () {
        var conversation = activeConversation();
        if (!conversation) {
          showToast("ابتدا یک گفت‌وگو را انتخاب کن.");
          return;
        }
        if (conversation.permissions && conversation.permissions.canSend === false) {
          showToast("در این گفت‌وگو اجازه ارسال فایل ندارید.");
          return;
        }
        if (conversation.settings && conversation.settings.muted) {
          showToast("ارسال پیام در این گفت‌وگو بسته است.");
          return;
        }
        setUploadSheetOpen(composerUploadSheet ? composerUploadSheet.hidden : false);
      });
    }
    if (composerUploadSheet) {
      composerUploadSheet.addEventListener("click", function (event) {
        var target = event.target.closest("[data-attach-accept]");
        if (!target) return;
        var accept = normalizeSpace(target.getAttribute("data-attach-accept")) || "*/*";
        var label = normalizeSpace(target.getAttribute("data-attach-label")) || "فایل";
        setUploadSheetOpen(false);
        pickAttachmentFiles(accept, label);
      });
    }
    if (attachmentInput) {
      attachmentInput.addEventListener("change", function () {
        var fileList = attachmentInput.files ? Array.from(attachmentInput.files) : [];
        attachmentInput.value = "";
        if (!fileList.length) return;
        fileList.forEach(function (file) {
          queueAttachmentUpload(file, {}).catch(function () {});
        });
      });
    }
    if (composerUploads) {
      composerUploads.addEventListener("click", function (event) {
        var button = event.target.closest("[data-upl-remove]");
        if (!button) return;
        removeComposerAttachmentByLocalId(button.getAttribute("data-upl-remove"));
      });
    }
    if (voiceBtn) {
      voiceBtn.addEventListener("click", function () {
        startVoiceRecording();
      });
    }
    if (voiceStopBtn) {
      voiceStopBtn.addEventListener("click", function () {
        stopVoiceRecording(false);
      });
    }
    if (voiceCancelBtn) {
      voiceCancelBtn.addEventListener("click", function () {
        resetVoiceRecorder();
        setComposerStatus("", "");
      });
    }
    if (voiceSendBtn) {
      voiceSendBtn.addEventListener("click", function () {
        stopVoiceRecording(true);
      });
    }
    if (replyCancel) replyCancel.addEventListener("click", clearReplyTarget);

    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        var auth = safeAuthApi();
        if (!auth || typeof auth.logout !== "function") {
          window.location.href = "/account/";
          return;
        }
        logoutBtn.disabled = true;
        auth.logout().catch(function () {}).finally(function () {
          logoutBtn.disabled = false;
        });
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener("click", function () {
        syncConversation({
          forceFull: true,
          includeMembers: state.infoSheetOpen,
          silent: false
        }).catch(function (error) {
          showToast(error && error.message ? error.message : "بازخوانی گفتگو انجام نشد.");
        });
      });
    }

    if (mobileOpenListBtn) mobileOpenListBtn.addEventListener("click", openListPane);
    if (mobileCloseListBtn) {
      mobileCloseListBtn.addEventListener("click", function () {
        if (state.activeConversationId) {
          openThreadPane();
        } else {
          openListPane();
        }
      });
    }

    if (mobileNewChatFab) {
      mobileNewChatFab.addEventListener("click", function () {
        openDmCreationFlow();
      });
    }

    if (chatNavList) {
      chatNavList.addEventListener("click", function () {
        if (state.modalOpen) {
          closeModal();
        }
        openListPane();
      });
    }
    if (chatNavCompose) {
      chatNavCompose.addEventListener("click", function () {
        openDmCreationFlow();
      });
    }
    if (chatNavGroup) {
      chatNavGroup.addEventListener("click", function () {
        openGroupCreationFlow();
      });
    }
    if (chatNavPolls) {
      chatNavPolls.addEventListener("click", function (event) {
        if (canOpenPollCenterForUser(state.me)) return;
        event.preventDefault();
        showToast("دسترسی به نظرسنجی فقط برای مالک یا نماینده فعال است.");
      });
    }

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Escape") return;
      if (composerUploadSheet && !composerUploadSheet.hidden) {
        setUploadSheetOpen(false);
      }
      if (state.contextOpen) {
        closeContextMenu();
        return;
      }
      if (state.infoSheetOpen) {
        closeInfoSheet();
        return;
      }
      if (state.modalOpen) {
        closeModal();
      }
    });

    document.addEventListener("click", function (event) {
      if (!composerUploadSheet || composerUploadSheet.hidden) return;
      var target = event.target;
      if (!target) return;
      if (composerUploadSheet.contains(target) || (attachBtn && attachBtn.contains(target))) {
        return;
      }
      setUploadSheetOpen(false);
    });

    window.addEventListener("resize", function () {
      applyViewportHeight();
      if (isKeyboardViewportShift()) {
        updateMobileNav();
        return;
      }
      if (!isMobileViewport()) {
        setMobileView("list");
      } else if (state.modalOpen) {
        if (state.modalOpen === "group") {
          setMobileView("group");
        } else if (state.modalOpen === "dm" || state.modalOpen === "forward") {
          setMobileView("dm");
        } else if (state.activeConversationId) {
          setMobileView("thread");
        } else {
          setMobileView("list");
        }
      } else {
        // On mobile, list view should remain the default after layout recalculations.
        var currentView = chatApp && chatApp.dataset ? normalizeSpace(chatApp.dataset.mobileView) : "";
        if (currentView === "thread" && state.activeConversationId) {
          setMobileView("thread");
        } else {
          setMobileView("list");
        }
      }
      closeContextMenu();
    });

    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", applyViewportHeight);
      window.visualViewport.addEventListener("scroll", applyViewportHeight);
    }

    window.addEventListener("focus", function () {
      if (!state.me.loggedIn) return;
      syncConversation({ forceFull: false, includeMembers: state.infoSheetOpen, silent: true }).catch(function () {});
    });

    document.addEventListener("visibilitychange", function () {
      if (document.hidden || !state.me.loggedIn) return;
      syncConversation({ forceFull: false, includeMembers: state.infoSheetOpen, silent: true }).catch(function () {});
    });
  }


  function boot() {
    applyViewportHeight();
    syncThemeColor();
    autosizeComposer();
    renderComposerUploads();
    updateVoiceUi();
    setUploadSheetOpen(false);
    setThreadVisible(false);
    setMobileView("list");
    setConnectionState("idle", "آفلاین");
    showStreamState("loading", "در حال آماده‌سازی...", "در حال بررسی وضعیت نشست.");
    updateConversationFilterTabs();
    updateConversationMeta();
    renderConversationList();
    updateThreadHead();
    updateComposerState();
    updatePinnedUi();
    updateMuteUi({ muted: false });
    syncCurrentUserAvatar();
    updatePollActionVisibility();
    updateFabVisibility();
    bindEvents();
    if (window.matchMedia) {
      var darkMedia = window.matchMedia("(prefers-color-scheme: dark)");
      if (typeof darkMedia.addEventListener === "function") {
        darkMedia.addEventListener("change", syncThemeColor);
      } else if (typeof darkMedia.addListener === "function") {
        darkMedia.addListener(syncThemeColor);
      }
    }
    if (typeof MutationObserver === "function") {
      var themeObserver = new MutationObserver(function () {
        syncThemeColor();
      });
      themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-theme", "class"]
      });
    }

    var auth = safeAuthApi();
    if (!auth || typeof auth.onChange !== "function") {
      resetLoggedOutUi("وضعیت احراز هویت بارگذاری نشد.");
      return;
    }

    auth.onChange(function (detail) {
      Promise.resolve(handleAuthChange(detail)).catch(function (error) {
        console.error(error);
      });
    });
  }

  boot();
})();
