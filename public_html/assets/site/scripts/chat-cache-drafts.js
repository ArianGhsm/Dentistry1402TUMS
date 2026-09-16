(function () {
  "use strict";

  function create(context) {
    var CHAT_DRAFT_SAVE_DELAY_MS = context.CHAT_DRAFT_SAVE_DELAY_MS;
    var CHAT_DRAFT_TTL_MS = context.CHAT_DRAFT_TTL_MS;
    var CHAT_FAST_CACHE_CONVERSATION_LIMIT = context.CHAT_FAST_CACHE_CONVERSATION_LIMIT;
    var CHAT_FAST_CACHE_MESSAGE_LIMIT = context.CHAT_FAST_CACHE_MESSAGE_LIMIT;
    var CHAT_FAST_CACHE_TTL_MS = context.CHAT_FAST_CACHE_TTL_MS;
    var CHAT_FAST_CACHE_VERSION = context.CHAT_FAST_CACHE_VERSION;
    var MAX_MESSAGE_SIZE = context.MAX_MESSAGE_SIZE;
    var activeConversation = context.activeConversation;
    var appendMessages = context.appendMessages;
    var asObject = context.asObject;
    var autosizeComposer = context.autosizeComposer;
    var chatTextEl = context.chatTextEl;
    var clearReplyTarget = context.clearReplyTarget;
    var closeMentionSuggestions = context.closeMentionSuggestions;
    var invalidateMessageListCache = context.invalidateMessageListCache;
    var messageList = context.messageList;
    var messagesEl = context.messagesEl;
    var normalizeConversation = context.normalizeConversation;
    var normalizeConversationListCategory = context.normalizeConversationListCategory;
    var normalizeMessage = context.normalizeMessage;
    var normalizeSpace = context.normalizeSpace;
    var normalizeStudentNumber = context.normalizeStudentNumber;
    var pageCohort = context.pageCohort;
    var pauseAllVoiceNotes = context.pauseAllVoiceNotes;
    var renderConversationList = context.renderConversationList;
    var replaceConversations = context.replaceConversations;
    var resetThreadSearchState = context.resetThreadSearchState;
    var setConnectionState = context.setConnectionState;
    var setThreadVisible = context.setThreadVisible;
    var showStreamState = context.showStreamState;
    var state = context.state;
    var syncComposerDraftState = context.syncComposerDraftState;
    var toNumber = context.toNumber;
    var toText = context.toText;
    var updateComposerState = context.updateComposerState;
    var updateConversationFilterTabs = context.updateConversationFilterTabs;
    var updateFabVisibility = context.updateFabVisibility;
    var updateInfoSheet = context.updateInfoSheet;
    var updateMessageSelectionUi = context.updateMessageSelectionUi;
    var updateMobileNav = context.updateMobileNav;
    var updateMuteUi = context.updateMuteUi;
    var updatePinnedUi = context.updatePinnedUi;
    var updateThreadHead = context.updateThreadHead;
    var updateThreadSearchUi = context.updateThreadSearchUi;

  function chatStorage() {
    try {
      return window.localStorage || null;
    } catch (_error) {
      return null;
    }
  }

  function chatCacheUserKey() {
    return normalizeStudentNumber(state.me && state.me.studentNumber);
  }

  function chatCacheKey() {
    var userKey = chatCacheUserKey();
    if (!userKey) return "";
    return [
      "dent1402-chat-fast",
      "v" + CHAT_FAST_CACHE_VERSION,
      encodeURIComponent(pageCohort || "main"),
      encodeURIComponent(userKey)
    ].join(":");
  }

  function chatDraftKey(conversationId) {
    var baseKey = chatCacheKey();
    var id = normalizeSpace(conversationId);
    if (!baseKey || !id) return "";
    return baseKey + ":draft:" + encodeURIComponent(id);
  }

  function compactCachedConversation(conversation) {
    if (!conversation) return null;
    var copy = Object.assign({}, conversation);
    if (Array.isArray(copy.members) && copy.members.length > 60) {
      copy.members = [];
    }
    return copy;
  }

  function compactCachedMessage(message) {
    if (!message || message.id <= 0) return null;
    return Object.assign({}, message);
  }

  function clearFastChatCacheSaveHandle() {
    if (state.cacheSaveTimer) {
      window.clearTimeout(state.cacheSaveTimer);
      state.cacheSaveTimer = null;
    }
    if (state.cacheSaveIdleId && typeof window.cancelIdleCallback === "function") {
      window.cancelIdleCallback(state.cacheSaveIdleId);
      state.cacheSaveIdleId = 0;
    }
  }

  function saveFastChatCacheNow() {
    clearFastChatCacheSaveHandle();
    if (!state.me.loggedIn) return;

    var storage = chatStorage();
    var key = chatCacheKey();
    if (!storage || !key) return;

    var activeId = normalizeSpace(state.activeConversationId);
    var cachedMessages = activeId
      ? messageList().filter(function (message) {
        return message && message.conversationId === activeId;
      }).slice(-CHAT_FAST_CACHE_MESSAGE_LIMIT).map(compactCachedMessage).filter(Boolean)
      : [];

    var payload = {
      version: CHAT_FAST_CACHE_VERSION,
      savedAt: Date.now(),
      cohort: pageCohort || "main",
      user: chatCacheUserKey(),
      activeConversationId: activeId,
      conversationListVersion: state.conversationListVersion || "",
      conversationListCategory: normalizeConversationListCategory(state.conversationListCategory),
      showArchivedConversations: state.showArchivedConversations === true,
      conversations: state.conversations
        .slice(0, CHAT_FAST_CACHE_CONVERSATION_LIMIT)
        .map(compactCachedConversation)
        .filter(Boolean),
      thread: {
        conversationId: activeId,
        messages: cachedMessages,
        lastMessageId: Math.max(0, Math.floor(toNumber(state.lastMessageId, 0))),
        oldestMessageId: Math.max(0, Math.floor(toNumber(state.oldestMessageId, 0))),
        hasMoreBefore: state.hasMoreBefore === true
      }
    };

    try {
      storage.setItem(key, JSON.stringify(payload));
    } catch (_error) {
      try {
        payload.thread.messages = payload.thread.messages.slice(-60);
        storage.setItem(key, JSON.stringify(payload));
      } catch (__error) {
        // Cache is best-effort; live sync remains the source of truth.
      }
    }
  }

  function scheduleFastChatCacheSave(delayMs) {
    if (!state.me.loggedIn) return;
    clearFastChatCacheSaveHandle();
    var delay = Math.max(0, Math.floor(toNumber(delayMs, 220)));
    state.cacheSaveTimer = window.setTimeout(function () {
      state.cacheSaveTimer = null;
      if (typeof window.requestIdleCallback === "function") {
        state.cacheSaveIdleId = window.requestIdleCallback(function () {
          state.cacheSaveIdleId = 0;
          saveFastChatCacheNow();
        }, { timeout: 900 });
        return;
      }
      saveFastChatCacheNow();
    }, delay);
  }

  function readFastChatCache() {
    var storage = chatStorage();
    var key = chatCacheKey();
    if (!storage || !key) return null;

    try {
      var payload = JSON.parse(storage.getItem(key) || "null");
      if (!payload || payload.version !== CHAT_FAST_CACHE_VERSION) return null;
      if (payload.cohort !== (pageCohort || "main")) return null;
      if (normalizeStudentNumber(payload.user) !== chatCacheUserKey()) return null;
      var savedAt = Math.max(0, Math.floor(toNumber(payload.savedAt, 0)));
      if (!savedAt || Date.now() - savedAt > CHAT_FAST_CACHE_TTL_MS) {
        storage.removeItem(key);
        return null;
      }
      return payload;
    } catch (_error) {
      return null;
    }
  }

  function hydrateFastChatCache() {
    if (!state.me.loggedIn || state.cacheHydrated) return false;

    var payload = readFastChatCache();
    var conversations = (Array.isArray(payload && payload.conversations) ? payload.conversations : [])
      .map(normalizeConversation)
      .filter(Boolean);
    if (!conversations.length) return false;

    replaceConversations(conversations);
    state.conversationListVersion = normalizeSpace(payload.conversationListVersion);
    state.conversationListCategory = normalizeConversationListCategory(payload.conversationListCategory);
    state.showArchivedConversations = payload.showArchivedConversations === true;

    var preferredConversationId = normalizeSpace(state.initialConversationId)
      || normalizeSpace(payload.activeConversationId);
    if (!preferredConversationId || !state.conversationsById.has(preferredConversationId)) {
      preferredConversationId = state.conversations[0] ? state.conversations[0].id : "";
    }
    state.activeConversationId = preferredConversationId;
    state.initialConversationId = "";

    var thread = asObject(payload.thread) || {};
    var cachedMessages = [];
    if (state.activeConversationId && normalizeSpace(thread.conversationId) === state.activeConversationId) {
      cachedMessages = (Array.isArray(thread.messages) ? thread.messages : [])
        .map(normalizeMessage)
        .filter(function (message) {
          return message && message.conversationId === state.activeConversationId;
        });
    }

    setThreadVisible(!!state.activeConversationId);
    if (state.activeConversationId && cachedMessages.length) {
      appendMessages(cachedMessages, {
        replaceAll: true,
        forceStick: true,
        smooth: false
      });
      state.hasMoreBefore = thread.hasMoreBefore === true;
      state.lastMessageId = Math.max(state.lastMessageId, Math.floor(toNumber(thread.lastMessageId, 0)));
      state.oldestMessageId = Math.max(0, Math.floor(toNumber(thread.oldestMessageId, state.oldestMessageId)));
    } else if (state.activeConversationId) {
      clearThreadState();
      setThreadVisible(true);
    } else {
      clearThreadState();
    }

    renderConversationList();
    updateConversationFilterTabs();
    updateThreadHead();
    updateComposerState();
    updatePinnedUi();
    var active = activeConversation();
    updateMuteUi(active && active.settings ? active.settings : { muted: false });
    updateInfoSheet();
    updateFabVisibility();
    updateMobileNav();
    restoreComposerDraftForConversation(state.activeConversationId, { preserveExisting: false });
    setConnectionState("sync", "در حال بروزرسانی...");
    state.cacheHydrated = true;
    state.cacheHydratedAt = Math.max(0, Math.floor(toNumber(payload.savedAt, 0)));
    return true;
  }

  function readComposerDraft(conversationId) {
    var storage = chatStorage();
    var key = chatDraftKey(conversationId);
    if (!storage || !key) return "";

    try {
      var payload = JSON.parse(storage.getItem(key) || "null");
      if (!payload) return "";
      var updatedAt = Math.max(0, Math.floor(toNumber(payload.updatedAt, 0)));
      if (!updatedAt || Date.now() - updatedAt > CHAT_DRAFT_TTL_MS) {
        storage.removeItem(key);
        return "";
      }
      return toText(payload.text).slice(0, MAX_MESSAGE_SIZE);
    } catch (_error) {
      return "";
    }
  }

  function saveComposerDraftNow(conversationId) {
    if (state.draftSaveTimer) {
      window.clearTimeout(state.draftSaveTimer);
      state.draftSaveTimer = null;
    }
    if (!state.me.loggedIn || !chatTextEl) return;

    var id = normalizeSpace(conversationId || state.activeConversationId);
    var storage = chatStorage();
    var key = chatDraftKey(id);
    if (!storage || !key) return;

    var text = toText(chatTextEl.value).slice(0, MAX_MESSAGE_SIZE);
    try {
      if (!normalizeSpace(text)) {
        storage.removeItem(key);
        return;
      }
      storage.setItem(key, JSON.stringify({
        text: text,
        updatedAt: Date.now()
      }));
    } catch (_error) {
      // Draft persistence is best-effort and should never block composing.
    }
  }

  function scheduleComposerDraftSave(conversationId) {
    if (!state.me.loggedIn) return;
    if (state.draftSaveTimer) {
      window.clearTimeout(state.draftSaveTimer);
    }
    var id = normalizeSpace(conversationId || state.activeConversationId);
    state.draftSaveTimer = window.setTimeout(function () {
      saveComposerDraftNow(id);
    }, CHAT_DRAFT_SAVE_DELAY_MS);
  }

  function clearComposerDraft(conversationId) {
    var storage = chatStorage();
    var key = chatDraftKey(conversationId || state.activeConversationId);
    if (!storage || !key) return;
    try {
      storage.removeItem(key);
    } catch (_error) {}
  }

  function restoreComposerDraftForConversation(conversationId, options) {
    if (!chatTextEl) return;
    var opts = asObject(options) || {};
    if (opts.preserveExisting !== false && normalizeSpace(chatTextEl.value)) {
      return;
    }
    chatTextEl.value = readComposerDraft(conversationId);
    autosizeComposer();
    syncComposerDraftState();
  }

  function clearThreadState() {
    state.messages.clear();
    invalidateMessageListCache();
    state.lastMessageId = 0;
    state.oldestMessageId = 0;
    state.hasMoreBefore = false;
    state.olderMessagesLoading = false;
    state.unreadDividerMessageId = 0;
    state.replyTargetId = null;
    state.messageSelectionMode = false;
    state.selectedMessageIds.clear();
    state.threadAutoStick = true;
    closeMentionSuggestions();
    pauseAllVoiceNotes();
    resetThreadSearchState({ keepPanel: false, keepNavigator: false });
    clearReplyTarget();
    if (messagesEl) messagesEl.innerHTML = "";
    showStreamState("empty", "گفت‌وگو خالی است", "برای شروع گفت‌وگو، یک پیام بفرست.");
    updateThreadSearchUi();
    updateMessageSelectionUi();
  }

    return Object.freeze({
      chatStorage: chatStorage,
      chatCacheUserKey: chatCacheUserKey,
      chatCacheKey: chatCacheKey,
      chatDraftKey: chatDraftKey,
      compactCachedConversation: compactCachedConversation,
      compactCachedMessage: compactCachedMessage,
      clearFastChatCacheSaveHandle: clearFastChatCacheSaveHandle,
      saveFastChatCacheNow: saveFastChatCacheNow,
      scheduleFastChatCacheSave: scheduleFastChatCacheSave,
      readFastChatCache: readFastChatCache,
      hydrateFastChatCache: hydrateFastChatCache,
      readComposerDraft: readComposerDraft,
      saveComposerDraftNow: saveComposerDraftNow,
      scheduleComposerDraftSave: scheduleComposerDraftSave,
      clearComposerDraft: clearComposerDraft,
      restoreComposerDraftForConversation: restoreComposerDraftForConversation,
      clearThreadState: clearThreadState
    });
  }

  window.Dent1402ChatCache = Object.freeze({ create: create });
}());
