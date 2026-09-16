(function () {
  "use strict";

  function create(context) {
    var CHAT_PRESENCE_HEARTBEAT_MS = context.CHAT_PRESENCE_HEARTBEAT_MS;
    var CHAT_STREAM_RETRY_MS = context.CHAT_STREAM_RETRY_MS;
    var CHAT_TYPING_IDLE_MS = context.CHAT_TYPING_IDLE_MS;
    var CHAT_TYPING_REFRESH_MS = context.CHAT_TYPING_REFRESH_MS;
    var MAX_POLL_MS = context.MAX_POLL_MS;
    var MIN_POLL_MS = context.MIN_POLL_MS;
    var activeConversation = context.activeConversation;
    var apiPost = context.apiPost;
    var applyPresenceBundle = context.applyPresenceBundle;
    var asObject = context.asObject;
    var chatTextEl = context.chatTextEl;
    var clamp = context.clamp;
    var consumeUnauthorized = context.consumeUnauthorized;
    var ensureSuccessResponse = context.ensureSuccessResponse;
    var normalizeSpace = context.normalizeSpace;
    var setConnectionState = context.setConnectionState;
    var setConversationReadStateById = context.setConversationReadStateById;
    var state = context.state;
    var syncConversation = context.syncConversation;
    var toNumber = context.toNumber;

  function clearStreamRetryTimer() {
    if (state.streamRetryTimer) {
      window.clearTimeout(state.streamRetryTimer);
      state.streamRetryTimer = null;
    }
  }

  function clearStreamSyncTimer() {
    if (state.streamSyncTimer) {
      window.clearTimeout(state.streamSyncTimer);
      state.streamSyncTimer = null;
    }
  }

  function clearPresenceHeartbeatTimer() {
    if (state.presenceHeartbeatTimer) {
      window.clearTimeout(state.presenceHeartbeatTimer);
      state.presenceHeartbeatTimer = null;
    }
  }

  function clearTypingTimers() {
    if (state.typingRefreshTimer) {
      window.clearTimeout(state.typingRefreshTimer);
      state.typingRefreshTimer = null;
    }
    if (state.typingStopTimer) {
      window.clearTimeout(state.typingStopTimer);
      state.typingStopTimer = null;
    }
  }

  function configureTransport(transport) {
    var source = asObject(transport) || {};
    var mode = normalizeSpace(source.mode).toLowerCase();
    state.transportMode = mode === "sse" ? "sse" : "polling";
    state.transportStreamUrl = normalizeSpace(source.streamUrl);
    state.transportPresenceUrl = normalizeSpace(source.presenceUrl);
    var fallbackMs = source.fallbackIntervalMs != null ? source.fallbackIntervalMs : source.intervalMs;
    if (fallbackMs != null) {
      state.pollIntervalMs = clamp(toNumber(fallbackMs, 5000), MIN_POLL_MS, MAX_POLL_MS);
    }
  }

  function buildStreamContextUrl() {
    if (!state.transportStreamUrl) return "";
    try {
      var url = new URL(state.transportStreamUrl, window.location.origin);
      if (state.activeConversationId) {
        url.searchParams.set("conversationId", state.activeConversationId);
      } else {
        url.searchParams.delete("conversationId");
      }
      url.searchParams.set("includeMembers", state.infoSheetOpen ? "1" : "0");
      return url.toString();
    } catch (_error) {
      return "";
    }
  }

  function stopRealtimeStream(options) {
    var opts = asObject(options) || {};
    if (state.streamSource) {
      try {
        state.streamSource.close();
      } catch (_error) {
        // no-op
      }
    }
    state.streamSource = null;
    state.streamConnected = false;
    if (!opts.keepBinding) {
      state.streamBoundConversationId = "";
      state.streamBoundMembers = false;
    }
    if (!opts.keepRetry) {
      clearStreamRetryTimer();
    }
    clearStreamSyncTimer();
  }

  function queueRealtimeSync() {
    if (!state.me.loggedIn) return;
    if (state.streamSyncTimer) return;
    state.streamSyncTimer = window.setTimeout(function () {
      state.streamSyncTimer = null;
      syncConversation({
        forceFull: false,
        includeMembers: state.infoSheetOpen,
        silent: true
      }).catch(function () {});
    }, 120);
  }

  function scheduleRealtimeReconnect() {
    if (!state.me.loggedIn || state.transportMode !== "sse") return;
    if (state.streamRetryTimer) return;
    state.streamRetryTimer = window.setTimeout(function () {
      state.streamRetryTimer = null;
      startRealtimeStream();
    }, CHAT_STREAM_RETRY_MS);
  }

  function sendPresenceHeartbeat(options) {
    var opts = asObject(options) || {};
    if (!state.me.loggedIn) return Promise.resolve(null);
    var conversationId = normalizeSpace(opts.conversationId != null ? opts.conversationId : state.activeConversationId);
    var payload = {
      conversationId: conversationId,
      typing: opts.typing === true ? "1" : "0",
      activity: normalizeSpace(opts.activity) || (opts.typing === true ? "typing" : "active")
    };
    return apiPost("presencePing", payload, { quiet: true }).then(function (response) {
      if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
        throw new Error((response && response.error) || "نشست شما منقضی شده است.");
      }
      if (response && response.success === false && !response.networkError) {
        ensureSuccessResponse(response, "بروزرسانی وضعیت حضور انجام نشد.");
      }
      return response;
    }).catch(function () {
      return null;
    });
  }

  function schedulePresenceHeartbeat(delayMs) {
    clearPresenceHeartbeatTimer();
    if (!state.me.loggedIn || document.hidden) return;
    var wait = Math.max(0, Math.floor(toNumber(delayMs, CHAT_PRESENCE_HEARTBEAT_MS)));
    state.presenceHeartbeatTimer = window.setTimeout(function () {
      state.presenceHeartbeatTimer = null;
      var activeTyping = state.typingActive && normalizeSpace(state.typingConversationId) === normalizeSpace(state.activeConversationId);
      sendPresenceHeartbeat({
        conversationId: state.activeConversationId,
        typing: activeTyping,
        activity: activeTyping ? "typing" : "active"
      }).finally(function () {
        schedulePresenceHeartbeat(CHAT_PRESENCE_HEARTBEAT_MS);
      });
    }, wait);
  }

  function clearTypingActivity(skipNetwork) {
    var hadTyping = state.typingActive || !!state.typingConversationId;
    state.typingActive = false;
    state.typingConversationId = "";
    clearTypingTimers();
    if (hadTyping && !skipNetwork) {
      sendPresenceHeartbeat({
        conversationId: state.activeConversationId,
        typing: false,
        activity: "active"
      }).finally(function () {
        schedulePresenceHeartbeat(CHAT_PRESENCE_HEARTBEAT_MS);
      });
      return;
    }
    schedulePresenceHeartbeat(CHAT_PRESENCE_HEARTBEAT_MS);
  }

  function handleComposerTypingActivity() {
    var conversation = activeConversation();
    var text = normalizeSpace(chatTextEl && chatTextEl.value);
    if (!conversation || !text || document.hidden || !(conversation.permissions && conversation.permissions.canSend) || (conversation.settings && conversation.settings.muted)) {
      clearTypingActivity(false);
      return;
    }

    var alreadyTyping = state.typingActive && normalizeSpace(state.typingConversationId) === normalizeSpace(conversation.id);
    state.typingActive = true;
    state.typingConversationId = conversation.id;
    if (!alreadyTyping) {
      sendPresenceHeartbeat({
        conversationId: conversation.id,
        typing: true,
        activity: "typing"
      });
    }
    clearTypingTimers();
    state.typingRefreshTimer = window.setTimeout(function refreshTyping() {
      if (!state.typingActive || normalizeSpace(state.typingConversationId) !== normalizeSpace(conversation.id) || document.hidden) {
        return;
      }
      sendPresenceHeartbeat({
        conversationId: conversation.id,
        typing: true,
        activity: "typing"
      });
      state.typingRefreshTimer = window.setTimeout(refreshTyping, CHAT_TYPING_REFRESH_MS);
    }, CHAT_TYPING_REFRESH_MS);
    state.typingStopTimer = window.setTimeout(function () {
      clearTypingActivity(false);
    }, CHAT_TYPING_IDLE_MS);
  }

  function startRealtimeStream() {
    if (!state.me.loggedIn || state.transportMode !== "sse" || typeof window.EventSource !== "function") {
      return false;
    }
    var nextUrl = buildStreamContextUrl();
    if (!nextUrl) return false;
    var sameBinding = !!state.streamSource
      && state.streamBoundConversationId === normalizeSpace(state.activeConversationId)
      && state.streamBoundMembers === !!state.infoSheetOpen;
    if (sameBinding) {
      return true;
    }

    stopRealtimeStream({ keepRetry: false });

    try {
      var source = new window.EventSource(nextUrl);
      state.streamSource = source;
      state.streamBoundConversationId = normalizeSpace(state.activeConversationId);
      state.streamBoundMembers = !!state.infoSheetOpen;
      source.addEventListener("open", function () {
        if (state.streamSource !== source) return;
        state.streamConnected = true;
        clearStreamRetryTimer();
        stopPolling();
        setConnectionState("live", "متصل");
      });
      source.addEventListener("sync", function () {
        if (state.streamSource !== source) return;
        queueRealtimeSync();
      });
      source.addEventListener("presence", function (event) {
        if (state.streamSource !== source) return;
        var payload = null;
        try {
          payload = event && event.data ? JSON.parse(event.data) : null;
        } catch (_error) {
          payload = null;
        }
        if (payload) {
          applyPresenceBundle(payload);
        }
      });
      source.addEventListener("hello", function (event) {
        if (state.streamSource !== source) return;
        try {
          var payload = event && event.data ? JSON.parse(event.data) : null;
          if (payload && payload.mode === "sse") {
            setConnectionState("live", "متصل");
          }
        } catch (_error) {
          setConnectionState("live", "متصل");
        }
      });
      source.onerror = function () {
        if (state.streamSource !== source) return;
        stopRealtimeStream({ keepBinding: false, keepRetry: true });
        state.streamConnected = false;
        if (state.me.loggedIn) {
          setConnectionState("issue", "در انتظار اتصال زنده");
          startPolling();
          scheduleRealtimeReconnect();
        }
      };
      return true;
    } catch (_error) {
      state.streamConnected = false;
      return false;
    }
  }

  function refreshTransportBinding() {
    if (!state.me.loggedIn) return;
    if (state.transportMode === "sse" && typeof window.EventSource === "function") {
      if (!startRealtimeStream()) {
        startPolling();
      }
      return;
    }
    stopRealtimeStream();
    startPolling();
  }

  function stopPolling() {
    if (state.pollingTimer) {
      window.clearTimeout(state.pollingTimer);
    }
    state.pollingTimer = null;
    state.pollInFlight = false;
  }

  function startPolling() {
    stopPolling();
    if (!state.me.loggedIn) return;
    if (state.transportMode === "sse" && state.streamConnected) return;

    var scheduleNextPoll = function () {
      if (!state.me.loggedIn) {
        state.pollingTimer = null;
        return;
      }
      state.pollingTimer = window.setTimeout(function () {
        if (state.pollInFlight) {
          scheduleNextPoll();
          return;
        }
        state.pollInFlight = true;
        syncConversation({
          forceFull: false,
          includeMembers: state.infoSheetOpen,
          silent: true
        }).catch(function () {}).finally(function () {
          state.pollInFlight = false;
          scheduleNextPoll();
        });
      }, clamp(state.pollIntervalMs, MIN_POLL_MS, MAX_POLL_MS));
    };

    scheduleNextPoll();
  }

  function clearAutoReadTimer() {
    if (state.autoReadTimer) {
      window.clearTimeout(state.autoReadTimer);
      state.autoReadTimer = null;
    }
    state.autoReadConversationId = "";
    state.autoReadMessageId = 0;
  }

  function scheduleAutoMarkRead() {
    clearAutoReadTimer();
    var conversation = activeConversation();
    if (!conversation || !conversation.permissions || !conversation.permissions.canMarkRead) {
      return;
    }
    var targetMessageId = Math.max(0, Math.floor(toNumber(conversation.lastMessage && conversation.lastMessage.id, 0)));
    var currentRead = Math.max(0, Math.floor(toNumber(conversation.lastReadMessageId, 0)));
    if (targetMessageId <= 0 || targetMessageId <= currentRead) {
      return;
    }
    state.autoReadConversationId = conversation.id;
    state.autoReadMessageId = targetMessageId;
    state.autoReadTimer = window.setTimeout(function () {
      var conversationId = state.autoReadConversationId;
      var messageId = state.autoReadMessageId;
      clearAutoReadTimer();
      if (!conversationId || messageId <= 0) return;
      var latestConversation = state.conversationsById.get(conversationId);
      if (!latestConversation) return;
      var latestRead = Math.max(0, Math.floor(toNumber(latestConversation.lastReadMessageId, 0)));
      var latestLastMessageId = Math.max(0, Math.floor(toNumber(latestConversation.lastMessage && latestConversation.lastMessage.id, 0)));
      if (latestLastMessageId <= latestRead || latestLastMessageId !== messageId) return;
      setConversationReadStateById(conversationId, true, {
        silentToast: true,
        skipSync: true
      }).catch(function () {
        // no-op
      });
    }, 120);
  }

    return {
      clearStreamRetryTimer: clearStreamRetryTimer,
      clearStreamSyncTimer: clearStreamSyncTimer,
      clearPresenceHeartbeatTimer: clearPresenceHeartbeatTimer,
      clearTypingTimers: clearTypingTimers,
      configureTransport: configureTransport,
      buildStreamContextUrl: buildStreamContextUrl,
      stopRealtimeStream: stopRealtimeStream,
      queueRealtimeSync: queueRealtimeSync,
      scheduleRealtimeReconnect: scheduleRealtimeReconnect,
      sendPresenceHeartbeat: sendPresenceHeartbeat,
      schedulePresenceHeartbeat: schedulePresenceHeartbeat,
      clearTypingActivity: clearTypingActivity,
      handleComposerTypingActivity: handleComposerTypingActivity,
      startRealtimeStream: startRealtimeStream,
      refreshTransportBinding: refreshTransportBinding,
      stopPolling: stopPolling,
      startPolling: startPolling,
      clearAutoReadTimer: clearAutoReadTimer,
      scheduleAutoMarkRead: scheduleAutoMarkRead
    };
  }

  window.Dent1402ChatRealtimeTransport = { create: create };
})();
