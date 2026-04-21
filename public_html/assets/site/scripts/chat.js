(function () {
  "use strict";

  if (!window.Dent1402Auth) {
    return;
  }

  var REACTIONS = ["??", "??", "??", "??", "??", "??"];

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function avatarLabel(value) {
    var clean = String(value || "").replace(/\s+/g, " ").trim();
    if (!clean) return "?";
    var parts = clean.split(" ").filter(Boolean);
    return parts.slice(0, 2).map(function (part) { return part.charAt(0); }).join("") || clean.charAt(0);
  }

  function fmtTime(ts) {
    try {
      return new Date(ts * 1000).toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit" });
    } catch (error) {
      return "";
    }
  }

  function snippet(text, maxLen) {
    var clean = String(text || "").replace(/\s+/g, " ").trim();
    if (clean.length <= maxLen) return clean;
    return clean.slice(0, maxLen) + "�";
  }

  function canModerate(entity) {
    return !!(entity && entity.canModerateChat);
  }

  function roleLabel(entity) {
    return entity && entity.roleLabel ? entity.roleLabel : "";
  }

  var bootBox = $("boot-box");
  var bootText = $("boot-text");
  var loginBox = $("login-box");
  var chatBox = $("chat-box");
  var logoutBtn = $("logout-btn");
  var refreshBtn = $("refresh-btn");
  var accountBtn = $("account-btn");
  var msgBox = $("msg");

  var muteBadge = $("mute-badge");
  var pinnedWrap = $("pinned-wrap");
  var pinnedText = $("pinned-text");
  var pinnedActionLabel = $("pinned-action-label");
  var unpinBtn = $("unpin-btn");
  var messagesEl = $("messages");
  var streamStateEl = $("stream-state");
  var chatTextEl = $("chat-text");
  var sendBtn = $("send-btn");
  var replyBar = $("reply-bar");
  var replyToName = $("reply-to-name");
  var replyToSnip = $("reply-to-snippet");
  var replyCancel = $("reply-cancel");
  var chatStatusEl = $("chat-status");
  var connectionBadge = $("connection-badge");
  var toastEl = $("toast");

  var roomInfoTrigger = $("room-info-trigger");
  var infoSheet = $("chat-info-sheet");
  var infoSheetStatus = $("chat-info-status");
  var infoStats = $("chat-info-stats");
  var infoMembers = $("chat-info-members");
  var infoSheetClose = $("chat-sheet-close");
  var infoSheetBackdrop = $("chat-sheet-backdrop");
  var adminTools = $("chat-admin-tools");
  var muteBtn = $("mute-btn");
  var unmuteBtn = $("unmute-btn");

  var contextBackdrop = $("chat-context-backdrop");
  var contextMenu = $("chat-context-menu");
  var reactionBar = $("chat-reaction-bar");
  var contextActions = $("chat-context-actions");

  var me = {
    loggedIn: false,
    username: null,
    name: null,
    isAdmin: false,
    role: "student",
    roleLabel: "??????",
    canModerateChat: false
  };
  var cache = new Map();
  var lastId = 0;
  var pollingTimer = null;
  var toastTimer = null;
  var replyTargetId = null;
  var menuAnchorId = null;
  var contextOpen = false;
  var sheetOpen = false;

  function apiPost(action, bodyObj) {
    var body = new URLSearchParams(Object.assign({ action: action }, bodyObj || {}));
    return fetch("chat_api.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept": "application/json"
      },
      body: body
    }).then(function (response) {
      return response.json().catch(function () {
        return { success: false, error: "???? ???? ??????? ???." };
      });
    });
  }

  function apiGet(action, paramsObj) {
    var query = new URLSearchParams(Object.assign({ action: action }, paramsObj || {}));
    return fetch("chat_api.php?" + query.toString(), {
      method: "GET",
      headers: {
        "Accept": "application/json"
      }
    }).then(function (response) {
      return response.json().catch(function () {
        return { success: false, error: "???? ???? ??????? ???." };
      });
    });
  }

  function showToast(text) {
    if (!toastEl || !text) return;
    toastEl.textContent = text;
    toastEl.classList.add("show");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toastEl.classList.remove("show");
    }, 1800);
  }

  function setConnectionState(mode, text) {
    if (!connectionBadge) return;
    connectionBadge.dataset.state = mode || "idle";
    connectionBadge.textContent = text || "?????";
    updateInfoSheet();
  }

  function setBootState(text) {
    bootText.textContent = text || "?? ??? ??????? ????...";
  }

  function showGuardMessage(text, kind) {
    msgBox.textContent = text || "";
    msgBox.className = "login-feedback" + (kind ? " " + kind : "");
  }

  function showStreamState(kind, title, desc) {
    if (!kind) {
      streamStateEl.hidden = true;
      streamStateEl.innerHTML = "";
      return;
    }

    streamStateEl.hidden = false;
    streamStateEl.innerHTML =
      "<strong>" + escapeHtml(title || "") + "</strong>" +
      (desc ? "<span>" + escapeHtml(desc) + "</span>" : "");
  }

  function autosizeComposer() {
    chatTextEl.style.height = "0px";
    chatTextEl.style.height = Math.min(chatTextEl.scrollHeight, 180) + "px";
  }

  function resetConversationUi() {
    cache.clear();
    lastId = 0;
    replyTargetId = null;
    messagesEl.innerHTML = "";
    clearReplyTarget();
    updatePinnedUi();
    updateChatMeta();
    updateInfoSheet();
    showStreamState("empty", "???? ????? ????.", "????? ???? ?? ??????? ?????.");
  }

  function memberList() {
    var map = new Map();
    cache.forEach(function (message) {
      if (!message || !message.username) return;
      if (!map.has(message.username)) {
        map.set(message.username, {
          username: message.username,
          name: message.name || message.username,
          role: message.role || "student",
          roleLabel: message.roleLabel || "??????",
          canModerateChat: !!message.canModerateChat,
          messages: 0
        });
      }
      map.get(message.username).messages += 1;
    });

    var members = Array.from(map.values());
    members.sort(function (a, b) {
      if (a.canModerateChat !== b.canModerateChat) return a.canModerateChat ? -1 : 1;
      return a.name.localeCompare(b.name, "fa");
    });
    return members;
  }

  function updateChatMeta() {
    if (!chatStatusEl) return;

    if (!me.loggedIn) {
      chatStatusEl.textContent = "???? ???? ??????? ???? ???? ??";
      updateInfoSheet();
      return;
    }

    var count = cache.size;
    var members = memberList().length;
    var pieces = [];

    if (count) {
      pieces.push(count.toLocaleString("fa-IR") + " ????");
    } else {
      pieces.push("???? ????? ????");
    }

    if (members) {
      pieces.push(members.toLocaleString("fa-IR") + " ???");
    }

    if (me.canModerateChat) {
      pieces.push(me.roleLabel);
    }

    chatStatusEl.textContent = pieces.join(" � ");
    updateInfoSheet();
  }

  function setReplyTarget(message) {
    replyTargetId = message.id;
    replyToName.textContent = message.name || message.username || "";
    replyToSnip.textContent = snippet(message.text || "", 64);
    replyBar.hidden = false;
  }

  function clearReplyTarget() {
    replyTargetId = null;
    replyBar.hidden = true;
    replyToName.textContent = "";
    replyToSnip.textContent = "";
  }

  function closeContextMenu() {
    contextOpen = false;
    contextBackdrop.hidden = true;
    contextBackdrop.classList.remove("is-open");
    contextMenu.hidden = true;
    contextMenu.classList.remove("is-open");
    syncMessageFocus();
  }

  function closeInfoSheet() {
    sheetOpen = false;
    infoSheetBackdrop.hidden = true;
    infoSheetBackdrop.classList.remove("is-open");
    infoSheet.hidden = true;
    infoSheet.classList.remove("is-open");
  }

  function syncMessageFocus() {
    Array.from(messagesEl.querySelectorAll(".msg-item")).forEach(function (item) {
      item.classList.toggle("is-menu-open", Number(item.dataset.mid) === Number(menuAnchorId));
    });
  }

  function openInfoSheet() {
    updateInfoSheet();
    sheetOpen = true;
    infoSheet.hidden = false;
    infoSheetBackdrop.hidden = false;
    requestAnimationFrame(function () {
      infoSheet.classList.add("is-open");
      infoSheetBackdrop.classList.add("is-open");
    });
  }

  function reactionMap(message) {
    var raw = message && message.reactions;
    if (!raw || typeof raw !== "object") return {};
    return raw;
  }

  function reactionEntries(message) {
    var map = reactionMap(message);
    return Object.keys(map).map(function (emoji) {
      var users = Array.isArray(map[emoji]) ? map[emoji] : [];
      return {
        emoji: emoji,
        users: users,
        count: users.length,
        own: users.indexOf(String(me.username || "")) !== -1
      };
    }).filter(function (entry) {
      return entry.count > 0;
    }).sort(function (a, b) {
      return b.count - a.count;
    });
  }

  function updatePinnedUi() {
    var pinned = null;

    cache.forEach(function (message) {
      if (message && message.pinned) {
        if (!pinned || Number(message.id) > Number(pinned.id)) {
          pinned = message;
        }
      }
    });

    if (!pinned) {
      pinnedWrap.hidden = true;
      pinnedText.textContent = "";
      unpinBtn.hidden = true;
      updateInfoSheet();
      return;
    }

    pinnedWrap.hidden = false;
    pinnedText.textContent = (pinned.name ? pinned.name + " � " : "") + snippet(pinned.text || "", 88);
    pinnedActionLabel.textContent = "??? ????";
    pinnedWrap.onclick = function () {
      scrollToMessage(pinned.id);
    };

    if (me.isAdmin) {
      unpinBtn.hidden = false;
      unpinBtn.onclick = async function () {
        try {
          var result = await apiPost("pin", { id: String(pinned.id), pinned: "0" });
          if (await ensureAuthorized(result) && result && result.success) {
            replaceMessageInDom(result.message);
            showToast("????? ??????? ??.");
          }
        } catch (error) {
          showToast("??????? ????? ????? ???.");
        }
      };
    } else {
      unpinBtn.hidden = true;
    }

    updateInfoSheet();
  }

  function updateMuteUi(state) {
    var muted = !!(state && state.muted);
    adminTools.hidden = !me.isAdmin;

    if (!muted) {
      muteBadge.hidden = true;
      chatTextEl.disabled = false;
      sendBtn.disabled = false;
      chatTextEl.placeholder = "????? ?? ?????...";
      if (me.isAdmin) {
        muteBtn.hidden = false;
        unmuteBtn.hidden = true;
      }
      updateInfoSheet();
      return;
    }

    muteBadge.hidden = false;
    muteBadge.textContent = "????? ???? ?????? ???? ??? ???.";

    if (!me.isAdmin) {
      chatTextEl.disabled = true;
      sendBtn.disabled = true;
      chatTextEl.placeholder = "????? ???? ?????? ???? ??? ???.";
    } else {
      chatTextEl.disabled = false;
      sendBtn.disabled = false;
      chatTextEl.placeholder = "????? ?? ?????...";
      muteBtn.hidden = true;
      unmuteBtn.hidden = false;
    }

    updateInfoSheet();
  }

  function renderReplyPreview(replyToId) {
    var ref = cache.get(replyToId);
    if (!ref) return null;

    var preview = document.createElement("button");
    preview.type = "button";
    preview.className = "reply-preview";
    preview.innerHTML =
      "<div><b>" + escapeHtml(ref.name || ref.username || "") + "</b></div>" +
      "<div>" + escapeHtml(snippet(ref.text || "", 90)) + "</div>";
    preview.addEventListener("click", function (event) {
      event.stopPropagation();
      scrollToMessage(ref.id);
    });
    return preview;
  }

  function renderReactions(message) {
    var entries = reactionEntries(message);
    if (!entries.length) return null;

    var wrap = document.createElement("div");
    wrap.className = "msg-reactions";

    entries.forEach(function (entry) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "msg-reaction" + (entry.own ? " is-own" : "");
      button.innerHTML =
        '<span class="msg-reaction__emoji">' + entry.emoji + "</span>" +
        '<span class="msg-reaction__count">' + entry.count.toLocaleString("fa-IR") + "</span>";
      button.addEventListener("click", function (event) {
        event.stopPropagation();
        toggleReaction(message, entry.emoji);
      });
      wrap.appendChild(button);
    });

    return wrap;
  }

  function msgClass(message) {
    if (message.role === "owner") return "role-owner";
    if (message.role === "representative") return "role-representative";
    return "";
  }

  function renderMessage(message) {
    var isMine = String(message.username) === String(me.username);

    var article = document.createElement("article");
    article.className = "msg-item " + msgClass(message) + (isMine ? " me" : " other");
    article.dataset.mid = String(message.id);
    article.dataset.user = String(message.username || "");

    var row = document.createElement("div");
    row.className = "msg-row";

    var avatar = document.createElement("div");
    avatar.className = "msg-avatar";
    avatar.textContent = avatarLabel(message.name || message.username || "");

    var bubble = document.createElement("div");
    bubble.className = "msg-bubble";
    bubble.tabIndex = 0;

    var head = document.createElement("div");
    head.className = "msg-head";

    var name = document.createElement("div");
    name.className = "msg-name";
    name.textContent = message.name || "";
    head.appendChild(name);

    if (canModerate(message)) {
      var badge = document.createElement("span");
      badge.className = "msg-badge";
      badge.textContent = roleLabel(message);
      head.appendChild(badge);
    }

    bubble.appendChild(head);

    if (message.replyTo) {
      var replyPreview = renderReplyPreview(message.replyTo);
      if (replyPreview) bubble.appendChild(replyPreview);
    }

    var text = document.createElement("div");
    text.className = "msg-text";
    text.textContent = message.text || "";
    bubble.appendChild(text);

    var reactions = renderReactions(message);
    if (reactions) {
      bubble.appendChild(reactions);
    }

    var foot = document.createElement("div");
    foot.className = "msg-foot";

    var flags = document.createElement("div");
    flags.className = "msg-flags";

    if (message.editedAt) {
      var edited = document.createElement("span");
      edited.className = "msg-flag";
      edited.textContent = "??????";
      flags.appendChild(edited);
    }

    if (message.pinned) {
      var pinned = document.createElement("span");
      pinned.className = "msg-flag";
      pinned.textContent = "?????";
      flags.appendChild(pinned);
    }

    if (flags.childNodes.length) {
      foot.appendChild(flags);
    }

    var time = document.createElement("div");
    time.className = "msg-time";
    time.textContent = fmtTime(message.ts || 0);
    foot.appendChild(time);
    bubble.appendChild(foot);

    attachBubbleMenuEvents(bubble, message);

    if (isMine) {
      row.appendChild(bubble);
      row.appendChild(avatar);
    } else {
      row.appendChild(avatar);
      row.appendChild(bubble);
    }

    article.appendChild(row);
    return article;
  }

  function attachBubbleMenuEvents(bubble, message) {
    var longPressTimer = null;

    bubble.addEventListener("contextmenu", function (event) {
      event.preventDefault();
      openContextMenu(message, bubble, event.clientX, event.clientY);
    });

    bubble.addEventListener("touchstart", function (event) {
      if (!event.touches || !event.touches[0]) return;
      var touch = event.touches[0];
      longPressTimer = window.setTimeout(function () {
        openContextMenu(message, bubble, touch.clientX, touch.clientY);
      }, 360);
    }, { passive: true });

    ["touchend", "touchcancel", "touchmove"].forEach(function (type) {
      bubble.addEventListener(type, function () {
        window.clearTimeout(longPressTimer);
      }, { passive: true });
    });
  }

  function updateMessageGroups() {
    var items = Array.from(messagesEl.querySelectorAll(".msg-item"));

    items.forEach(function (item, index) {
      var prev = items[index - 1];
      var next = items[index + 1];
      var user = item.dataset.user;
      var prevSame = !!(prev && prev.dataset.user === user);
      var nextSame = !!(next && next.dataset.user === user);

      item.classList.toggle("grouped-with-prev", prevSame);
      item.classList.toggle("group-single", !prevSame && !nextSame);
      item.classList.toggle("group-start", !prevSame && nextSame);
      item.classList.toggle("group-middle", prevSame && nextSame);
      item.classList.toggle("group-end", prevSame && !nextSame);
    });
  }

  function scrollToBottom(smooth) {
    messagesEl.scrollTo({
      top: messagesEl.scrollHeight,
      behavior: smooth ? "smooth" : "auto"
    });
  }

  function scrollToMessage(messageId) {
    var node = messagesEl.querySelector('[data-mid="' + messageId + '"]');
    if (!node) return;
    node.scrollIntoView({ behavior: "smooth", block: "center" });
    menuAnchorId = messageId;
    syncMessageFocus();
    window.setTimeout(function () {
      menuAnchorId = null;
      syncMessageFocus();
    }, 1400);
  }

  function appendMessages(list, options) {
    var items = Array.isArray(list) ? list : [];
    var shouldStick = (messagesEl.scrollTop + messagesEl.clientHeight + 84) >= messagesEl.scrollHeight;
    var markNew = !!(options && options.markNew);

    items.forEach(function (message) {
      if (!message || !message.id) return;
      lastId = Math.max(lastId, Number(message.id));
      cache.set(message.id, message);

      var existing = messagesEl.querySelector('[data-mid="' + message.id + '"]');
      if (existing) {
        existing.replaceWith(renderMessage(message));
      } else {
        var node = renderMessage(message);
        if (markNew) {
          node.classList.add("is-new");
          window.setTimeout(function () {
            node.classList.remove("is-new");
          }, 260);
        }
        messagesEl.appendChild(node);
      }
    });

    updateMessageGroups();
    syncMessageFocus();
    updatePinnedUi();
    updateChatMeta();
    updateInfoSheet();

    if (cache.size === 0) {
      showStreamState("empty", "???? ????? ????.", "????? ???? ?? ??????? ?????.");
    } else {
      showStreamState("", "", "");
    }

    if (shouldStick || (options && options.forceStick)) {
      requestAnimationFrame(function () {
        scrollToBottom(!!(options && options.smooth));
      });
    }
  }

  function replaceMessageInDom(updated) {
    var existing = messagesEl.querySelector('[data-mid="' + updated.id + '"]');
    cache.set(updated.id, updated);
    if (existing) {
      existing.replaceWith(renderMessage(updated));
    }
    updateMessageGroups();
    syncMessageFocus();
    updatePinnedUi();
    updateChatMeta();
    updateInfoSheet();
  }

  async function ensureAuthorized(response, fallbackText) {
    if (response && response.loggedOut) {
      await handleUnauthorized(fallbackText || "???? ??????? ????? ??.");
      return false;
    }

    return true;
  }

  async function handleUnauthorized(message) {
    stopPolling();
    showGuardMessage(message || "???? ??????? ????? ??.", "error");
    await window.Dent1402Auth.bootstrap(true);
  }

  async function toggleReaction(message, emoji) {
    try {
      var result = await apiPost("react", { id: String(message.id), emoji: emoji });
      if (!await ensureAuthorized(result)) return;
      if (result && result.success) {
        replaceMessageInDom(result.message);
        closeContextMenu();
      }
    } catch (error) {
      showToast("??? ????? ????? ???.");
    }
  }

  async function copyMessageText(message) {
    var text = String((message && message.text) || "").trim();
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
      showToast("???? ??? ??.");
    } catch (error) {
      showToast("??? ????? ???.");
    }
  }

  function contextAction(label, hint, onClick, className) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "chat-context-action" + (className ? " " + className : "");
    button.innerHTML = "<span>" + escapeHtml(label) + "</span><strong>" + escapeHtml(hint || "") + "</strong>";
    button.addEventListener("click", onClick);
    return button;
  }

  function positionContextMenu(clientX, clientY) {
    var menuWidth = contextMenu.offsetWidth;
    var menuHeight = contextMenu.offsetHeight;
    var left = Math.max(8, Math.min(clientX - menuWidth / 2, window.innerWidth - menuWidth - 8));
    var top = Math.max(8, Math.min(clientY + 12, window.innerHeight - menuHeight - 8));
    contextMenu.style.left = left + "px";
    contextMenu.style.top = top + "px";
  }

  function openContextMenu(message, bubble, clientX, clientY) {
    if (!me.loggedIn) return;

    menuAnchorId = message.id;
    syncMessageFocus();

    reactionBar.innerHTML = "";
    REACTIONS.forEach(function (emoji) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "chat-reaction-btn";
      if (reactionEntries(message).some(function (entry) { return entry.emoji === emoji && entry.own; })) {
        button.classList.add("is-active");
      }
      button.textContent = emoji;
      button.addEventListener("click", function () {
        toggleReaction(message, emoji);
      });
      reactionBar.appendChild(button);
    });

    contextActions.innerHTML = "";
    contextActions.appendChild(contextAction("????", "Reply", function () {
      setReplyTarget(message);
      closeContextMenu();
    }));
    contextActions.appendChild(contextAction("???", "Copy", function () {
      copyMessageText(message);
      closeContextMenu();
    }));

    if (String(message.username) === String(me.username) || me.isAdmin) {
      contextActions.appendChild(contextAction("??????", "Edit", function () {
        openEditPrompt(message);
        closeContextMenu();
      }));
    }

    if (me.isAdmin) {
      contextActions.appendChild(contextAction(message.pinned ? "??????? ?????" : "????? ????", "Pin", function () {
        togglePin(message, !message.pinned);
        closeContextMenu();
      }));
    }

    if (String(message.username) === String(me.username) || me.isAdmin) {
      contextActions.appendChild(contextAction("???", "Delete", function () {
        deleteMessage(message);
        closeContextMenu();
      }, "is-danger"));
    }

    contextBackdrop.hidden = false;
    contextMenu.hidden = false;
    requestAnimationFrame(function () {
      contextBackdrop.classList.add("is-open");
      contextMenu.classList.add("is-open");
      positionContextMenu(clientX, clientY);
    });
    contextOpen = true;
  }

  function updateInfoSheet() {
    if (!infoSheet) return;

    var members = memberList();
    var pinnedCount = 0;

    cache.forEach(function (message) {
      if (message && message.pinned) pinnedCount += 1;
    });

    var connectionText = connectionBadge ? connectionBadge.textContent : "?????";
    infoSheetStatus.textContent = me.loggedIn
      ? members.length.toLocaleString("fa-IR") + " ??? � " + connectionText
      : "???? ???? ??????? ???? ???? ??";

    infoStats.innerHTML = "";
    [
      {
        title: "???????",
        meta: cache.size ? cache.size.toLocaleString("fa-IR") + " ???? ?? ???????" : "???? ????? ??? ????"
      },
      {
        title: "????????",
        meta: pinnedCount ? pinnedCount.toLocaleString("fa-IR") + " ???? ?????????" : "????? ???? ????? ????"
      },
      {
        title: "????? ?????",
        meta: muteBadge.hidden ? "????? ???? ??? ???" : "????? ???? ?????? ???? ???"
      }
    ].forEach(function (item) {
      var stat = document.createElement("div");
      stat.className = "chat-info-stat";
      stat.innerHTML = "<strong>" + item.title + "</strong><span>" + item.meta + "</span>";
      infoStats.appendChild(stat);
    });

    infoMembers.innerHTML = "";
    if (!members.length) {
      var empty = document.createElement("div");
      empty.className = "chat-member";
      empty.innerHTML = "<strong>???? ??? ????? ????????.</strong><span>??? ?? ????? ????? ???? ?????? ???? ???????.</span>";
      infoMembers.appendChild(empty);
    } else {
      members.forEach(function (member) {
        var node = document.createElement("div");
        node.className = "chat-member";
        node.innerHTML =
          "<strong>" + escapeHtml(member.name) + "</strong>" +
          "<span>" + (member.canModerateChat ? escapeHtml(member.roleLabel) + " � " : "") + member.messages.toLocaleString("fa-IR") + " ????</span>";
        infoMembers.appendChild(node);
      });
    }
  }

  async function openEditPrompt(message) {
    var edited = window.prompt("?????? ????", message.text || "");
    if (edited === null) return;
    var next = edited.trim();
    if (!next) return;

    try {
      var result = await apiPost("edit", { id: String(message.id), text: next });
      if (!await ensureAuthorized(result)) return;
      if (result && result.success) {
        replaceMessageInDom(result.message);
        showToast("???? ?????? ??.");
      }
    } catch (error) {
      showToast("?????? ????? ???.");
    }
  }

  async function deleteMessage(message) {
    if (!window.confirm("??? ???? ??? ????")) return;

    try {
      var result = await apiPost("delete", { id: String(message.id) });
      if (!await ensureAuthorized(result)) return;
      if (result && result.success) {
        cache.delete(message.id);
        var existing = messagesEl.querySelector('[data-mid="' + message.id + '"]');
        if (existing) existing.remove();
        updateMessageGroups();
        updatePinnedUi();
        updateChatMeta();
        updateInfoSheet();
        if (cache.size === 0) {
          showStreamState("empty", "???? ????? ????.", "????? ???? ?? ??????? ?????.");
        }
      }
    } catch (error) {
      showToast("??? ????? ???.");
    }
  }

  async function togglePin(message, pin) {
    try {
      var result = await apiPost("pin", { id: String(message.id), pinned: pin ? "1" : "0" });
      if (!await ensureAuthorized(result)) return;
      if (result && result.success) {
        replaceMessageInDom(result.message);
        showToast(pin ? "???? ????? ??." : "????? ??????? ??.");
      }
    } catch (error) {
      showToast("????? ????? ????? ???.");
    }
  }

  async function fullRefresh() {
    if (!me.loggedIn) return;
    lastId = 0;
    cache.clear();
    messagesEl.innerHTML = "";
    showStreamState("loading", "?? ??? ????? ???????...", "??? ???? ??? ??.");
    updateChatMeta();
    await poll(true);
  }

  async function poll(force) {
    if (!me.loggedIn) return;

    try {
      setConnectionState(force ? "sync" : "live", force ? "?? ??? ???????????" : "????");
      var response = await apiGet("fetch", { sinceId: String(force ? 0 : lastId) });
      if (!await ensureAuthorized(response)) return;
      if (!response || !response.success) {
        throw new Error("Fetch failed");
      }

      updateMuteUi(response.state);

      if (force) {
        var all = Array.isArray(response.messages) ? response.messages : [];
        lastId = 0;
        cache.clear();
        messagesEl.innerHTML = "";
        appendMessages(all, { forceStick: true });
      } else {
        appendMessages(response.messages || [], { smooth: true, markNew: true });
      }

      if (cache.size === 0) {
        showStreamState("empty", "???? ????? ????.", "????? ???? ?? ??????? ?????.");
      } else {
        showStreamState("", "", "");
      }

      setConnectionState("live", "????");
    } catch (error) {
      console.error(error);
      setConnectionState("issue", "?? ??? ???? ??????");
      if (cache.size === 0) {
        showStreamState("error", "???? ????? ?????? ???.", "??? ????? ???? ?????? ???? ??????.");
      }
    }
  }

  function startPolling() {
    stopPolling();
    poll(false);
    pollingTimer = window.setInterval(function () {
      poll(false);
    }, 1500);
  }

  function stopPolling() {
    if (pollingTimer) {
      window.clearInterval(pollingTimer);
    }
    pollingTimer = null;
  }

  function hideAllStages() {
    bootBox.hidden = true;
    loginBox.hidden = true;
    chatBox.hidden = true;
  }

  function resetLoggedOutUi(errorText) {
    me = {
      loggedIn: false,
      username: null,
      name: null,
      isAdmin: false,
      role: "student",
      roleLabel: "??????",
      canModerateChat: false
    };

    hideAllStages();
    loginBox.hidden = false;
    logoutBtn.hidden = true;
    refreshBtn.hidden = true;
    accountBtn.hidden = false;
    accountBtn.href = window.Dent1402Auth.loginUrl("/chat/");
    adminTools.hidden = true;
    stopPolling();
    clearReplyTarget();
    resetConversationUi();
    updateChatMeta();
    setConnectionState("idle", "?????");
    showGuardMessage(errorText || "???? ???? ??????? ? ????? ????? ??? ???? ???? ?????? ??.", errorText ? "error" : "");
  }

  async function applyAuthenticatedUser(user) {
    var nextStudentNumber = String(user.studentNumber || "");
    var userChanged = nextStudentNumber !== String(me.username || "");

    me = {
      loggedIn: true,
      username: nextStudentNumber,
      name: user.name || nextStudentNumber,
      isAdmin: !!user.canModerateChat,
      role: user.role || "student",
      roleLabel: user.roleLabel || "??????",
      canModerateChat: !!user.canModerateChat
    };

    hideAllStages();
    chatBox.hidden = false;
    logoutBtn.hidden = false;
    refreshBtn.hidden = false;
    accountBtn.hidden = false;
    accountBtn.href = "/account/";
    adminTools.hidden = !me.isAdmin;

    updateMuteUi({ muted: false });
    updateChatMeta();
    updateInfoSheet();

    if (userChanged) {
      await fullRefresh();
      startPolling();
    } else if (!pollingTimer) {
      startPolling();
    }

    autosizeComposer();
  }

  async function handleAuthChange(detail) {
    if (detail.status === "restoring" || detail.status === "logging-out") {
      hideAllStages();
      bootBox.hidden = false;
      setBootState(detail.status === "logging-out" ? "?? ??? ???? ?? ????..." : "?? ??? ??????? ????...");
      return;
    }

    if (!detail.loggedIn || !detail.user) {
      resetLoggedOutUi(detail.error || "");
      return;
    }

    await applyAuthenticatedUser(detail.user);
  }

  async function sendCurrentMessage() {
    var text = String(chatTextEl.value || "").trim();
    if (!text || sendBtn.disabled) return;

    sendBtn.disabled = true;
    var payload = { text: text };
    if (replyTargetId) {
      payload.replyTo = String(replyTargetId);
    }

    try {
      var response = await apiPost("send", payload);
      if (!await ensureAuthorized(response)) return;
      if (!response || !response.success) {
        throw new Error((response && response.error) || "????? ????? ???.");
      }

      chatTextEl.value = "";
      autosizeComposer();
      clearReplyTarget();
      appendMessages([response.message], { forceStick: true, smooth: true, markNew: true });
      setConnectionState("live", "????");
    } catch (error) {
      var message = error && error.message ? error.message : "????? ????? ???.";
      if (message.indexOf("????") !== -1) {
        muteBadge.hidden = false;
        muteBadge.textContent = message;
      } else {
        showToast(message);
      }
    } finally {
      if (!chatTextEl.disabled) {
        sendBtn.disabled = false;
      }
    }
  }

  logoutBtn.addEventListener("click", function () {
    logoutBtn.disabled = true;
    window.Dent1402Auth.logout().finally(function () {
      logoutBtn.disabled = false;
    });
  });

  refreshBtn.addEventListener("click", function () {
    fullRefresh();
  });

  replyCancel.addEventListener("click", clearReplyTarget);
  sendBtn.addEventListener("click", sendCurrentMessage);

  chatTextEl.addEventListener("input", autosizeComposer);
  chatTextEl.addEventListener("keydown", function (event) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      sendCurrentMessage();
    }
  });

  muteBtn.addEventListener("click", async function () {
    try {
      var response = await apiPost("setMute", { muted: "1" });
      if (!await ensureAuthorized(response)) return;
      if (response && response.success) {
        updateMuteUi(response.state);
        showToast("????? ???? ???? ??.");
      }
    } catch (error) {
      showToast("????? ????? ????? ???.");
    }
  });

  unmuteBtn.addEventListener("click", async function () {
    try {
      var response = await apiPost("setMute", { muted: "0" });
      if (!await ensureAuthorized(response)) return;
      if (response && response.success) {
        updateMuteUi(response.state);
        showToast("????? ???? ??? ??.");
      }
    } catch (error) {
      showToast("????? ????? ????? ???.");
    }
  });

  roomInfoTrigger.addEventListener("click", openInfoSheet);
  infoSheetClose.addEventListener("click", closeInfoSheet);
  infoSheetBackdrop.addEventListener("click", closeInfoSheet);
  contextBackdrop.addEventListener("click", closeContextMenu);

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      closeContextMenu();
      closeInfoSheet();
    }
  });

  messagesEl.addEventListener("scroll", function () {
    if (contextOpen) {
      closeContextMenu();
    }
  });

  window.addEventListener("resize", function () {
    if (contextOpen) {
      closeContextMenu();
    }
  });

  window.addEventListener("focus", function () {
    if (me.loggedIn) poll(false);
  });

  document.addEventListener("visibilitychange", function () {
    if (!document.hidden && me.loggedIn) {
      poll(false);
    }
  });

  autosizeComposer();
  window.Dent1402Auth.onChange(function (detail) {
    Promise.resolve(handleAuthChange(detail)).catch(function (error) {
      console.error(error);
    });
  });
})();
