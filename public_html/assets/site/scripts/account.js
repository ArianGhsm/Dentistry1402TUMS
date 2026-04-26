(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    if (!window.Dent1402Auth) {
        return;
    }

    var stageBoot = $("account-boot");
    var stageLogin = $("account-login");
    var stagePanel = $("account-panel");
    var bootText = $("account-boot-text");

    var loginForm = $("login-form");
    var loginSubmit = $("login-submit");
    var loginFeedback = $("login-feedback");
    var loginMethodSwitch = $("login-method-switch");
    var loginMethodPasswordBtn = $("login-method-password");
    var loginMethodOtpBtn = $("login-method-otp");
    var loginOtpForm = $("login-otp-form");
    var loginOtpRequestButton = $("login-otp-request");
    var loginOtpSubmitButton = $("login-otp-submit");
    var loginOtpFeedback = $("login-otp-feedback");
    var loginOtpMeta = $("login-otp-meta");
    var loginPhoneInput = $("login-phone-number");
    var loginOtpCodeInput = $("login-otp-code");
    var loginOtpSlots = $("login-otp-slots");

    var profileForm = $("profile-form");
    var profileSubmit = $("profile-submit");
    var profileFeedback = $("profile-feedback");
    var profileAvatarFeedback = $("profile-avatar-feedback");
    var profileAvatarFile = $("profile-avatar-file");
    var profileAvatarClear = $("profile-avatar-clear");
    var accountAvatar = $("account-avatar");
    var accountAvatarImage = $("account-avatar-image");
    var accountAvatarFallback = $("account-avatar-fallback");
    var accountRotation = $("account-rotation");
    var profileAvatarPreview = $("profile-avatar-preview");
    var profileAvatarImage = $("profile-avatar-image");
    var profileAvatarFallback = $("profile-avatar-fallback");

    var securityForm = $("security-form");
    var securitySubmit = $("security-submit");
    var securityFeedback = $("security-feedback");

    var logoutSubmit = $("logout-submit");

    var ownerSearch = $("owner-search");
    var ownerSummary = $("owner-summary");
    var ownerFeedback = $("owner-feedback");
    var representativeList = $("representative-list");
    var ownerUserList = $("owner-user-list");
    var ownerCreateStudentForm = $("owner-create-student-form");
    var ownerStudentFirstName = $("owner-student-first-name");
    var ownerStudentLastName = $("owner-student-last-name");
    var ownerStudentNumber = $("owner-student-number");
    var ownerStudentPassword = $("owner-student-password");
    var ownerCreateStudentSubmit = $("owner-create-student-submit");
    var ownerCreateStudentFeedback = $("owner-create-student-feedback");
    var navidConfigForm = $("navid-config-form");
    var navidOwnerStatus = $("navid-owner-status");
    var navidLoginUrlInput = $("navid-login-url");
    var navidSyncIntervalInput = $("navid-sync-interval");
    var navidCaptchaStrategyInput = $("navid-captcha-strategy");
    var navidUsernameInput = $("navid-username");
    var navidPasswordInput = $("navid-password");
    var navidSyncNowButton = $("navid-sync-now");
    var navidConfigFeedback = $("navid-config-feedback");
    var navidGetCaptchaButton = $("navid-get-captcha");
    var navidCompleteReconnectButton = $("navid-complete-reconnect");
    var navidCaptchaImage = $("navid-captcha-image");
    var navidCaptchaCodeInput = $("navid-captcha-code");
    var navidReconnectFeedback = $("navid-reconnect-feedback");

    var accountHubPanel = $("account-hub-panel");
    var accountSurfaceLayout = $("account-surface-layout");
    var accountPhoneNudge = $("account-phone-nudge");
    var accountPhoneNudgeOpen = $("account-phone-nudge-open");
    var accountPhoneNudgeDismiss = $("account-phone-nudge-dismiss");
    var ownerHubSection = $("account-owner-section");
    var pollManagerHubSection = $("account-poll-manager-section");
    var accountActivePollsRow = $("account-active-polls-row");
    var accountActivePollsMeta = $("account-active-polls-meta");
    var accountInfoRole = $("account-info-role");
    var accountInfoSession = $("account-info-session");
    var accountInfoRotation = $("account-info-rotation");
    var accountRowProfileMeta = $("account-row-profile-meta");
    var accountRowInfoMeta = $("account-row-info-meta");
    var accountRowOwnerMeta = $("account-row-owner-meta");
    var accountRowNavidMeta = $("account-row-navid-meta");
    var accountRowPollsMeta = $("account-row-polls-meta");
    var accountRowPhoneMeta = $("account-row-phone-meta");
    var surfaceOpeners = Array.prototype.slice.call(document.querySelectorAll("[data-open-surface]"));
    var surfaceBackButtons = Array.prototype.slice.call(document.querySelectorAll("[data-surface-back]"));
    var surfacePanels = Array.prototype.slice.call(document.querySelectorAll(".account-surface-panel[data-surface]"));

    var phoneStatusSummary = $("phone-status-summary");
    var phoneStatusBadge = $("phone-status-badge");
    var phoneNumberState = $("phone-number-state");
    var phoneVerifyState = $("phone-verify-state");
    var phoneLoginState = $("phone-login-state");
    var phoneCurrentNumber = $("phone-current-number");
    var phoneCurrentCaption = $("phone-current-caption");
    var phoneNumberEditButton = $("phone-number-edit");
    var phoneNumberRemoveButton = $("phone-number-remove");
    var phoneManageFeedback = $("phone-manage-feedback");
    var phoneEnrollNumber = $("phone-enroll-number");
    var phoneEnrollCode = $("phone-enroll-code");
    var phoneEnrollOtpSlots = $("phone-enroll-otp-slots");
    var phoneEnrollRequestButton = $("phone-enroll-request");
    var phoneEnrollSubmitButton = $("phone-enroll-submit");
    var phoneEnrollMeta = $("phone-enroll-meta");
    var phoneEnrollFeedback = $("phone-enroll-feedback");
    var phoneLoginEnabledInput = $("phone-login-enabled");
    var phoneLoginSaveButton = $("phone-login-save");
    var phoneLoginToggleHint = $("phone-login-toggle-hint");
    var phoneToggleFeedback = $("phone-toggle-feedback");

    var ownerSmsStatus = $("owner-sms-status");
    var ownerSmsForm = $("owner-sms-form");
    var ownerSmsEnabled = $("owner-sms-enabled");
    var ownerSmsPatternCode = $("owner-sms-pattern-code");
    var ownerSmsApiKey = $("owner-sms-api-key");
    var ownerSmsClearApi = $("owner-sms-clear-api");
    var ownerSmsSenderLine = $("owner-sms-sender-line");
    var ownerSmsDomain = $("owner-sms-domain");
    var ownerSmsCodeParam = $("owner-sms-code-param");
    var ownerSmsTestPhone = $("owner-sms-test-phone");
    var ownerSmsSaveButton = $("owner-sms-save");
    var ownerSmsHealthButton = $("owner-sms-health");
    var ownerSmsFeedback = $("owner-sms-feedback");

    var ownerMediaStatus = $("owner-media-status");
    var ownerMediaRefreshButton = $("owner-media-refresh");
    var ownerMediaCleanupButton = $("owner-media-cleanup");
    var ownerMediaFeedback = $("owner-media-feedback");

    var redirectedAfterLogin = false;
    var pendingReturnTo = safeReturnTo(new URLSearchParams(window.location.search).get("returnTo"));
    var activeSurface = "hub";
    var currentUser = null;
    var pollShortcutState = {
        loading: false,
        lastUserKey: "",
        count: 0
    };
    var ownerState = {
        loading: false,
        savingStudentNumber: "",
        creatingStudent: false,
        deletingStudentNumber: "",
        removingPhoneStudentNumber: "",
        loadingGradesStudentNumber: "",
        savingGradeKey: "",
        expandedStudentNumber: "",
        users: [],
        gradePayloadByStudent: {}
    };
    var navidState = {
        loading: false,
        syncing: false,
        loaded: false,
        ownerStatus: null
    };
    var smsState = {
        loading: false,
        status: null
    };
    var mediaState = {
        loading: false,
        status: null
    };
    var loginMode = "password";
    var loginOtpCooldownUntil = 0;
    var phoneEnrollCooldownUntil = 0;
    var loginOtpCooldownTimer = null;
    var phoneEnrollCooldownTimer = null;
    var profileDraftAvatarUrl = "";
    var profileSaving = false;
    var profileAvatarProcessing = false;
    function safeReturnTo(value) {
        if (!value || typeof value !== "string") {
            return "";
        }

        if (!value.startsWith("/") || value.startsWith("//")) {
            return "";
        }

        return value;
    }

    function showStage(name) {
        stageBoot.hidden = name !== "boot";
        stageLogin.hidden = name !== "login";
        stagePanel.hidden = name !== "panel";
        document.body.classList.toggle("account-stage-login-active", name === "login");
        document.body.classList.toggle("account-stage-panel-active", name === "panel");
    }

    function normalizeSurfaceName(raw) {
        var name = String(raw || "").trim().toLowerCase();
        switch (name) {
            case "profile":
            case "info":
            case "security":
            case "phone":
            case "owner":
            case "navid":
                return name;
            default:
                return "hub";
        }
    }

    function hasOwnerAccess() {
        return !!(currentUser && currentUser.isOwner);
    }

    function hasPollManagementAccess(user) {
        var source = user || currentUser || {};
        var role = String(source.role || "").trim();
        return !!source.isOwner || !!source.isRepresentative || role === "owner" || role === "representative";
    }

    function accountUserKey(user) {
        var source = user || currentUser || {};
        return String(source.studentNumber || "").trim();
    }

    function canOpenSurface(surface) {
        if (surface === "owner" || surface === "navid") {
            return hasOwnerAccess();
        }
        return true;
    }

    function surfaceFromHash() {
        var raw = String(window.location.hash || "").replace(/^#/, "");
        if (!raw) {
            return "hub";
        }

        if (raw.indexOf("account-") === 0) {
            raw = raw.slice(8);
        }
        return normalizeSurfaceName(raw);
    }

    function syncSurfaceHash(surface, replace) {
        var suffix = surface === "hub" ? "" : ("#account-" + surface);
        var nextUrl = window.location.pathname + window.location.search + suffix;
        if (replace) {
            window.history.replaceState(null, "", nextUrl);
            return;
        }
        window.history.pushState(null, "", nextUrl);
    }

    function findSurfacePanel(name) {
        var matched = null;
        surfacePanels.some(function (panel) {
            if (panel.dataset.surface === name) {
                matched = panel;
                return true;
            }
            return false;
        });
        return matched;
    }

    function playSurfaceTransition(node, backwards) {
        if (!node) {
            return;
        }

        node.classList.remove("account-surface-enter", "account-surface-enter-back");
        // Force restart so each navigation feels responsive.
        void node.offsetWidth;
        node.classList.add("account-surface-enter");
        if (backwards) {
            node.classList.add("account-surface-enter-back");
        }

        window.setTimeout(function () {
            node.classList.remove("account-surface-enter", "account-surface-enter-back");
        }, 280);
    }

    function openSurface(surface, options) {
        var opts = options || {};
        var target = normalizeSurfaceName(surface);
        if (!canOpenSurface(target)) {
            target = "hub";
        }

        var previous = activeSurface;
        activeSurface = target;
        var showingHub = target === "hub";
        if (accountHubPanel) {
            accountHubPanel.hidden = !showingHub;
        }
        if (accountSurfaceLayout) {
            accountSurfaceLayout.hidden = showingHub;
        }
        surfacePanels.forEach(function (panel) {
            panel.hidden = panel.dataset.surface !== target;
        });
        if (stagePanel) {
            stagePanel.dataset.surface = target;
        }

        if (opts.syncHash !== false) {
            syncSurfaceHash(target, !!opts.replaceHash);
        }

        if (!opts.skipAnimation && stagePanel && !stagePanel.hidden) {
            if (showingHub) {
                playSurfaceTransition(accountHubPanel, previous !== "hub");
            } else {
                playSurfaceTransition(findSurfacePanel(target), previous !== "hub");
            }
        }

        if (!opts.preserveScroll) {
            window.scrollTo(0, 0);
        }
    }

    function setBootText(text) {
        bootText.textContent = text;
    }

    function setFeedback(node, text, kind, loading) {
        node.className = "account-feedback" + (kind ? " " + kind : "");

        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        node.textContent = text || "";
    }

    function setInlineFeedback(node, text, kind, loading) {
        if (!node) {
            return;
        }

        node.className = "account-feedback account-feedback--inline" + (kind ? " " + kind : "");
        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        node.textContent = text || "";
    }

    function normalizeDigits(value) {
        var text = String(value || "").trim();
        if (!text) {
            return "";
        }
        return text
            .replace(/[Û°-Û¹]/g, function (ch) {
                return String("Û°Û±Û²Û³Û´ÛµÛ¶Û·Û¸Û¹".indexOf(ch));
            })
            .replace(/[Ù -Ù©]/g, function (ch) {
                return String("Ù Ù¡Ù¢Ù£Ù¤Ù¥Ù¦Ù§Ù¨Ù©".indexOf(ch));
            });
    }

    function normalizedPhone(value) {
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) return "";
        if (digits.indexOf("98") === 0 && digits.length >= 12) {
            return "0" + digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === "9") {
            return "0" + digits;
        }
        return digits;
    }

    function bindNumericInput(input, maxLength) {
        if (!input) {
            return;
        }
        input.addEventListener("input", function () {
            var digits = normalizeDigits(input.value).replace(/\D+/g, "");
            var next = digits;
            if (Number.isFinite(maxLength) && maxLength > 0) {
                next = next.slice(0, maxLength);
            }
            if (input.value !== next) {
                input.value = next;
            }
        });
    }

    function smsHealthStatusLabel(value) {
        var status = String(value || "").trim().toLowerCase();
        if (status === "ok") return "Ø³Ø§Ù„Ù…";
        if (status === "error") return "Ø®Ø·Ø§Ø¯Ø§Ø±";
        if (status === "unknown") return "Ù†Ø§Ù…Ø´Ø®Øµ";
        return status || "Ù†Ø§Ù…Ø´Ø®Øµ";
    }

    function ensureOwnerSmsHealthPhone() {
        if (!ownerSmsTestPhone) {
            return "";
        }
        var normalized = normalizedPhone(ownerSmsTestPhone.value);
        ownerSmsTestPhone.value = normalized;
        return normalized;
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

    function applyOtpSlots(input, slotsRoot) {
        if (!input || !slotsRoot) {
            return;
        }
        var slots = Array.prototype.slice.call(slotsRoot.querySelectorAll(".otp-slot"));
        if (!slots.length) {
            return;
        }

        var update = function () {
            var digits = normalizeDigits(input.value).replace(/\D+/g, "").slice(0, slots.length);
            slots.forEach(function (slot, index) {
                var ch = digits.charAt(index);
                slot.textContent = ch || "";
                slot.classList.toggle("has-value", !!ch);
                slot.classList.toggle("is-active", index === digits.length && digits.length < slots.length);
            });
            slotsRoot.classList.toggle("is-complete", digits.length === slots.length);
        };

        input.addEventListener("focus", function () {
            slotsRoot.classList.add("is-focused");
            update();
        });
        input.addEventListener("blur", function () {
            slotsRoot.classList.remove("is-focused");
            update();
        });
        input.addEventListener("input", update);
        slotsRoot.addEventListener("click", function () {
            input.focus({ preventScroll: true });
        });
        update();
    }

    function parsedPhone(user) {
        var source = user && typeof user === "object" && user.phone && typeof user.phone === "object"
            ? user.phone
            : {};
        return {
            hasNumber: !!source.hasNumber,
            numberMasked: String(source.numberMasked || ""),
            verified: !!source.verified,
            otpLoginEnabled: !!source.otpLoginEnabled,
            canLoginWithOtp: !!source.canLoginWithOtp,
            nudgeDismissedAt: String(source.nudgeDismissedAt || "")
        };
    }

    function setPhonePill(node, text, state) {
        if (!node) return;
        node.textContent = text;
        node.className = "phone-status-pill" + (state ? (" is-" + state) : "");
    }

    function renderPhoneSecurityState(user) {
        var phone = parsedPhone(user || {});
        var maskedPhone = String(phone.numberMasked || "").trim();
        var maskedPhoneLabel = ltrMaskedPhone(maskedPhone, "Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡");
        var badgeText = "Ù†ÛŒØ§Ø² Ø¨Ù‡ Ø«Ø¨Øª Ø´Ù…Ø§Ø±Ù‡";
        var badgeState = "warn";
        var summary = "Ø´Ù…Ø§Ø±Ù‡â€ŒØ§ÛŒ Ø¨Ø±Ø§ÛŒ Ø§ÛŒÙ† Ø­Ø³Ø§Ø¨ Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª. Ø¨Ø±Ø§ÛŒ ÙØ¹Ø§Ù„â€ŒØ³Ø§Ø²ÛŒ ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒØŒ Ø´Ù…Ø§Ø±Ù‡ Ø±Ø§ Ø«Ø¨Øª Ùˆ ØªØ§ÛŒÛŒØ¯ Ú©Ù†.";

        if (phone.hasNumber && !phone.verified) {
            badgeText = "Ø¯Ø± Ø§Ù†ØªØ¸Ø§Ø± ØªØ§ÛŒÛŒØ¯";
            badgeState = "warn";
            summary = "Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ø«Ø¨Øª Ø´Ø¯Ù‡ ÙˆÙ„ÛŒ Ù‡Ù†ÙˆØ² Ø¨Ø§ Ú©Ø¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ØªØ§ÛŒÛŒØ¯ Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.";
        } else if (phone.hasNumber && phone.verified && !phone.otpLoginEnabled) {
            badgeText = "Ø´Ù…Ø§Ø±Ù‡ ØªØ§ÛŒÛŒØ¯ Ø´Ø¯Ù‡";
            badgeState = "ok";
            summary = "Ø´Ù…Ø§Ø±Ù‡ " + maskedPhoneLabel + " ØªØ§ÛŒÛŒØ¯ Ø´Ø¯Ù‡ Ø§Ø³ØªØ› ÙˆØ±ÙˆØ¯ Ø¨Ø§ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ù‡Ù†ÙˆØ² ØºÛŒØ±ÙØ¹Ø§Ù„ Ø§Ø³Øª.";
        } else if (phone.hasNumber && phone.verified && phone.otpLoginEnabled) {
            badgeText = "ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ÙØ¹Ø§Ù„";
            badgeState = "ok";
            summary = "ÙˆØ±ÙˆØ¯ Ø¨Ø§ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø¨Ø±Ø§ÛŒ " + maskedPhoneLabel + " ÙØ¹Ø§Ù„ Ø§Ø³Øª.";
        }

        if (phoneStatusBadge) {
            phoneStatusBadge.textContent = badgeText;
            phoneStatusBadge.className = "phone-status-badge is-" + badgeState;
        }
        if (phoneStatusSummary) {
            phoneStatusSummary.textContent = summary;
        }
        setPhonePill(phoneNumberState, phone.hasNumber ? ("Ø´Ù…Ø§Ø±Ù‡ " + maskedPhoneLabel) : "Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡", phone.hasNumber ? "ok" : "warn");
        setPhonePill(phoneVerifyState, phone.verified ? "ØªØ§ÛŒÛŒØ¯ Ø´Ø¯Ù‡" : "ØªØ§ÛŒÛŒØ¯ Ù†Ø´Ø¯Ù‡", phone.verified ? "ok" : "warn");
        setPhonePill(phoneLoginState, phone.otpLoginEnabled ? "ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ÙØ¹Ø§Ù„" : "ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ØºÛŒØ±ÙØ¹Ø§Ù„", phone.otpLoginEnabled ? "ok" : "warn");

        if (phoneLoginEnabledInput) {
            phoneLoginEnabledInput.checked = !!phone.otpLoginEnabled;
            phoneLoginEnabledInput.disabled = !phone.hasNumber || !phone.verified;
        }
        if (phoneLoginSaveButton) {
            phoneLoginSaveButton.disabled = !phone.hasNumber || !phone.verified;
        }
        if (phoneLoginToggleHint) {
            phoneLoginToggleHint.textContent = phone.hasNumber && phone.verified
                ? "Ù…ÛŒâ€ŒØªÙˆØ§Ù†ÛŒ ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ Ø±Ø§ Ø¨Ø±Ø§ÛŒ Ù‡Ù…ÛŒÙ† Ø´Ù…Ø§Ø±Ù‡ Ø±ÙˆØ´Ù† ÛŒØ§ Ø®Ø§Ù…ÙˆØ´ Ú©Ù†ÛŒ."
                : "Ø¨Ø±Ø§ÛŒ ÙØ¹Ø§Ù„â€ŒØ³Ø§Ø²ÛŒØŒ Ø§Ø¨ØªØ¯Ø§ Ø´Ù…Ø§Ø±Ù‡ Ø±Ø§ Ø¨Ø§ Ú©Ø¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ØªØ§ÛŒÛŒØ¯ Ú©Ù†.";
        }
        if (phoneCurrentNumber) {
            phoneCurrentNumber.textContent = phone.hasNumber ? ltrMaskedPhone(maskedPhone, "â€”") : "â€”";
        }
        if (phoneCurrentCaption) {
            phoneCurrentCaption.textContent = phone.hasNumber
                ? "Ø¨Ø±Ø§ÛŒ ØªØºÛŒÛŒØ± Ø´Ù…Ø§Ø±Ù‡ØŒ Ø´Ù…Ø§Ø±Ù‡ Ø¬Ø¯ÛŒØ¯ Ø±Ø§ ÙˆØ§Ø±Ø¯ Ú©Ù† Ùˆ Ø¯ÙˆØ¨Ø§Ø±Ù‡ ØªØ§ÛŒÛŒØ¯ Ø¨Ú¯ÛŒØ±."
                : "Ù‡Ù†ÙˆØ² Ø´Ù…Ø§Ø±Ù‡â€ŒØ§ÛŒ Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª. Ø§Ø² Ú©Ø§Ø±Øª Ù¾Ø§ÛŒÛŒÙ† Ø¨Ø±Ø§ÛŒ Ø«Ø¨Øª Ø´Ù…Ø§Ø±Ù‡ Ø§Ø³ØªÙØ§Ø¯Ù‡ Ú©Ù†.";
        }
        if (phoneNumberEditButton) {
            phoneNumberEditButton.textContent = phone.hasNumber ? "ØªØºÛŒÛŒØ± Ø´Ù…Ø§Ø±Ù‡" : "Ø«Ø¨Øª Ø´Ù…Ø§Ø±Ù‡";
        }
        if (phoneNumberRemoveButton) {
            phoneNumberRemoveButton.disabled = !phone.hasNumber;
        }
        if (phoneEnrollNumber && !phoneEnrollNumber.value) {
            phoneEnrollNumber.placeholder = "9xxxxxxxxx ÛŒØ§ 09xxxxxxxxx";
        }
        if (accountPhoneNudge) {
            accountPhoneNudge.hidden = !shouldShowPhoneNudge(user || {});
        }
    }

    function shouldShowPhoneNudge(user) {
        var phone = parsedPhone(user);
        if (phone.hasNumber) {
            return false;
        }
        return !phone.nudgeDismissedAt;
    }

    function setLoginMode(mode) {
        loginMode = mode === "otp" ? "otp" : "password";

        if (loginMethodPasswordBtn) {
            var passwordActive = loginMode === "password";
            loginMethodPasswordBtn.classList.toggle("is-active", passwordActive);
            loginMethodPasswordBtn.setAttribute("aria-selected", passwordActive ? "true" : "false");
        }
        if (loginMethodOtpBtn) {
            var otpActive = loginMode === "otp";
            loginMethodOtpBtn.classList.toggle("is-active", otpActive);
            loginMethodOtpBtn.setAttribute("aria-selected", otpActive ? "true" : "false");
        }
        if (loginForm) {
            loginForm.hidden = loginMode !== "password";
        }
        if (loginOtpForm) {
            loginOtpForm.hidden = loginMode !== "otp";
        }
    }

    function stopLoginOtpCooldownTicker() {
        if (loginOtpCooldownTimer) {
            window.clearInterval(loginOtpCooldownTimer);
            loginOtpCooldownTimer = null;
        }
    }

    function updateLoginOtpCooldownUi() {
        var left = secondsRemaining(loginOtpCooldownUntil);
        var active = left > 0;
        if (loginOtpRequestButton) {
            loginOtpRequestButton.disabled = active;
        }
        if (loginOtpMeta) {
            loginOtpMeta.textContent = active
                ? ("Ø§Ø±Ø³Ø§Ù„ Ù…Ø¬Ø¯Ø¯ ØªØ§ " + formatSeconds(left) + " Ø¯ÛŒÚ¯Ø±")
                : "ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ÙÙ‚Ø· Ø¨Ø±Ø§ÛŒ Ø´Ù…Ø§Ø±Ù‡ ØªØ§ÛŒÛŒØ¯Ø´Ø¯Ù‡ Ùˆ ÙØ¹Ø§Ù„â€ŒØ´Ø¯Ù‡ Ø§Ù…Ú©Ø§Ù† Ø¯Ø§Ø±Ø¯.";
        }
        if (!active) {
            stopLoginOtpCooldownTicker();
        }
    }

    function startLoginOtpCooldown(seconds) {
        loginOtpCooldownUntil = nowSeconds() + Math.max(0, Math.floor(toNumber(seconds, 0)));
        updateLoginOtpCooldownUi();
        if (secondsRemaining(loginOtpCooldownUntil) > 0 && !loginOtpCooldownTimer) {
            loginOtpCooldownTimer = window.setInterval(updateLoginOtpCooldownUi, 1000);
        }
    }

    function stopPhoneEnrollCooldownTicker() {
        if (phoneEnrollCooldownTimer) {
            window.clearInterval(phoneEnrollCooldownTimer);
            phoneEnrollCooldownTimer = null;
        }
    }

    function updatePhoneEnrollCooldownUi() {
        var left = secondsRemaining(phoneEnrollCooldownUntil);
        var active = left > 0;
        if (phoneEnrollRequestButton) {
            phoneEnrollRequestButton.disabled = active;
        }
        if (phoneEnrollMeta) {
            phoneEnrollMeta.textContent = active
                ? ("Ø§Ø±Ø³Ø§Ù„ Ù…Ø¬Ø¯Ø¯ ØªØ§ " + formatSeconds(left) + " Ø¯ÛŒÚ¯Ø±")
                : "Ø¨Ø¹Ø¯ Ø§Ø² Ø§Ø±Ø³Ø§Ù„ØŒ Ø§Ù…Ú©Ø§Ù† Ø§Ø±Ø³Ø§Ù„ Ø¯ÙˆØ¨Ø§Ø±Ù‡ Ø¨Ø§ Ø²Ù…Ø§Ù†â€ŒØ³Ù†Ø¬ ÙØ¹Ø§Ù„ Ù…ÛŒâ€ŒØ´ÙˆØ¯.";
        }
        if (!active) {
            stopPhoneEnrollCooldownTicker();
        }
    }

    function startPhoneEnrollCooldown(seconds) {
        phoneEnrollCooldownUntil = nowSeconds() + Math.max(0, Math.floor(toNumber(seconds, 0)));
        updatePhoneEnrollCooldownUi();
        if (secondsRemaining(phoneEnrollCooldownUntil) > 0 && !phoneEnrollCooldownTimer) {
            phoneEnrollCooldownTimer = window.setInterval(updatePhoneEnrollCooldownUi, 1000);
        }
    }

    function resetOtpUi() {
        loginOtpCooldownUntil = 0;
        phoneEnrollCooldownUntil = 0;
        stopLoginOtpCooldownTicker();
        stopPhoneEnrollCooldownTicker();
        updateLoginOtpCooldownUi();
        updatePhoneEnrollCooldownUi();
    }

    function avatarLabel(value) {
        var clean = String(value || "").replace(/\s+/g, " ").trim();
        if (!clean) {
            return "ØŸ";
        }

        var parts = clean.split(" ").filter(Boolean);
        var initials = parts.slice(0, 2).map(function (part) {
            return part.charAt(0);
        }).join("");

        return initials || clean.charAt(0);
    }

    function normalizeAvatarUrl(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return "";
        }

        if (clean.indexOf("data:image/") === 0) {
            return clean;
        }

        if (clean.charAt(0) === "/") {
            return clean;
        }

        if (/^https?:\/\//i.test(clean)) {
            return clean;
        }

        return "";
    }

    function profileAbout(profile) {
        if (!profile || typeof profile !== "object") {
            return "";
        }

        return profile.about || profile.bio || "";
    }

    function renderAvatar(container, imageNode, fallbackNode, avatarUrl, label) {
        if (!container || !imageNode || !fallbackNode) {
            return;
        }

        var safeUrl = normalizeAvatarUrl(avatarUrl);
        fallbackNode.textContent = avatarLabel(label);

        if (!safeUrl) {
            container.dataset.hasAvatar = "0";
            imageNode.hidden = true;
            imageNode.removeAttribute("src");
            imageNode.alt = "";
            return;
        }

        container.dataset.hasAvatar = "1";
        imageNode.hidden = false;
        imageNode.alt = label ? ("ØªØµÙˆÛŒØ± Ù¾Ø±ÙˆÙØ§ÛŒÙ„ " + label) : "ØªØµÙˆÛŒØ± Ù¾Ø±ÙˆÙØ§ÛŒÙ„";
        imageNode.onerror = function () {
            container.dataset.hasAvatar = "0";
            imageNode.hidden = true;
            imageNode.removeAttribute("src");
        };
        imageNode.src = safeUrl;
    }

    function syncProfileAvatarClearButton() {
        if (!profileAvatarClear) {
            return;
        }

        profileAvatarClear.disabled = !profileDraftAvatarUrl || profileSaving || profileAvatarProcessing;
    }

    function updateIdentityAvatars(name) {
        var label = name || $("profile-name").value || $("account-name").textContent || "";
        renderAvatar(accountAvatar, accountAvatarImage, accountAvatarFallback, profileDraftAvatarUrl, label);
        renderAvatar(profileAvatarPreview, profileAvatarImage, profileAvatarFallback, profileDraftAvatarUrl, label);
        syncProfileAvatarClearButton();
    }

    function setProfileBusy(isBusy) {
        profileSaving = !!isBusy;
        var controlsDisabled = profileSaving || profileAvatarProcessing;
        profileSubmit.disabled = controlsDisabled;
        if (profileAvatarFile) {
            profileAvatarFile.disabled = controlsDisabled;
        }
        syncProfileAvatarClearButton();
    }

    function setProfileAvatarProcessing(isProcessing) {
        profileAvatarProcessing = !!isProcessing;
        setProfileBusy(profileSaving);
    }

    function readFileAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () {
                resolve(String(reader.result || ""));
            };
            reader.onerror = function () {
                reject(new Error("file-read"));
            };
            reader.readAsDataURL(file);
        });
    }

    function imageFileToAvatarDataUrl(file) {
        return readFileAsDataUrl(file).then(function (rawDataUrl) {
            return new Promise(function (resolve, reject) {
                var image = new Image();
                image.onload = function () {
                    var size = 320;
                    var canvas = document.createElement("canvas");
                    canvas.width = size;
                    canvas.height = size;

                    var ctx = canvas.getContext("2d");
                    if (!ctx) {
                        reject(new Error("canvas-context"));
                        return;
                    }

                    var sourceSize = Math.min(image.width, image.height);
                    var sx = Math.max(0, Math.floor((image.width - sourceSize) / 2));
                    var sy = Math.max(0, Math.floor((image.height - sourceSize) / 2));
                    ctx.drawImage(image, sx, sy, sourceSize, sourceSize, 0, 0, size, size);

                    var quality = 0.9;
                    var dataUrl = canvas.toDataURL("image/jpeg", quality);
                    while (dataUrl.length > 390000 && quality > 0.55) {
                        quality -= 0.08;
                        dataUrl = canvas.toDataURL("image/jpeg", quality);
                    }

                    if (dataUrl.length > 390000) {
                        reject(new Error("avatar-too-large"));
                        return;
                    }

                    resolve(dataUrl);
                };
                image.onerror = function () {
                    reject(new Error("avatar-invalid"));
                };
                image.src = rawDataUrl;
            });
        });
    }

    function ownerFeedbackMessage(text, kind) {
        setInlineFeedback(ownerFeedback, text, kind);
    }

    function ownerCreateStudentFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerCreateStudentFeedback, text, kind, loading);
    }

    function request(action, payload) {
        return fetch("/api/auth_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function requestUsers() {
        return fetch("/api/auth_api.php?action=users", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function requestActivePolls() {
        return fetch("/chat/chat_api.php?action=activePolls", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function navidGet(action) {
        return fetch("/api/navid_api.php?action=" + encodeURIComponent(action), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        });
    }

    function navidPost(action, payload) {
        return fetch("/api/navid_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                if (action === "syncNow" || action === "captchaChallenge" || action === "completeReconnect") {
                    navidSyncChallengeVisual((data && data.ownerStatus) || navidState.ownerStatus, data && data.captchaDataUri);
                }
                return data;
            });
        });
    }

    function consumeUnauthorized(response, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;

        if (!auth) {
            return false;
        }

        var message = fallbackText || "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.";
        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(response, message);
            }
        } catch (_error) {
            // Ignore stale auth surface mismatch and continue fallback.
        }

        if (response && (response.loggedOut || response.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((response && response.error) || message);
            }
            return true;
        }

        return false;
    }

    function renderActivePollShortcut(user, count) {
        if (!accountActivePollsRow) {
            return;
        }

        var total = Math.max(0, Math.floor(toNumber(count, 0)));
        var show = total > 0;
        accountActivePollsRow.hidden = !show;

        if (accountActivePollsMeta) {
            if (show) {
                accountActivePollsMeta.textContent = total.toLocaleString("fa-IR") + " Ù†Ø¸Ø±Ø³Ù†Ø¬ÛŒ ÙØ¹Ø§Ù„ Ø¨Ø±Ø§ÛŒ Ø´Ù…Ø§ Ø¯Ø± Ø¯Ø³ØªØ±Ø³ Ø§Ø³Øª.";
            } else {
                accountActivePollsMeta.textContent = "Ø¯Ø± Ø­Ø§Ù„ Ø­Ø§Ø¶Ø± Ù†Ø¸Ø±Ø³Ù†Ø¬ÛŒ ÙØ¹Ø§Ù„ÛŒ Ø¨Ø±Ø§ÛŒ Ø§ÛŒÙ† Ø­Ø³Ø§Ø¨ ÙˆØ¬ÙˆØ¯ Ù†Ø¯Ø§Ø±Ø¯.";
            }
        }
    }

    function resetActivePollShortcut() {
        pollShortcutState.loading = false;
        pollShortcutState.lastUserKey = "";
        pollShortcutState.count = 0;
        renderActivePollShortcut(null, 0);
    }

    function loadActivePollShortcut(user) {
        var userKey = accountUserKey(user);
        if (!userKey || pollShortcutState.loading) {
            return;
        }

        if (pollShortcutState.lastUserKey === userKey) {
            renderActivePollShortcut(user, pollShortcutState.count);
            return;
        }

        pollShortcutState.loading = true;
        requestActivePolls().then(function (response) {
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                resetActivePollShortcut();
                return;
            }

            if (!response || !response.success) {
                if (response && response.captchaDataUri && navidCaptchaImage) {
                    navidCaptchaImage.hidden = false;
                    navidCaptchaImage.src = response.captchaDataUri;
                }
                if (response && response.ownerStatus) {
                    navidState.ownerStatus = response.ownerStatus;
                    navidRenderOwnerStatus(navidState.ownerStatus);
                }
                pollShortcutState.lastUserKey = userKey;
                pollShortcutState.count = 0;
                renderActivePollShortcut(user, 0);
                return;
            }

            var count = toNumber(response.count, Array.isArray(response.polls) ? response.polls.length : 0);
            pollShortcutState.lastUserKey = userKey;
            pollShortcutState.count = Math.max(0, Math.floor(count));
            renderActivePollShortcut(user, pollShortcutState.count);
        }).catch(function () {
            pollShortcutState.lastUserKey = userKey;
            pollShortcutState.count = 0;
            renderActivePollShortcut(user, 0);
        }).finally(function () {
            pollShortcutState.loading = false;
        });
    }

    function renderIdentity(user) {
        var roleLabel = user.roleLabel || "Ø¯Ø§Ù†Ø´Ø¬Ùˆ";
        var sessionLabel = user.isOwner ? "Ø¯Ø³ØªØ±Ø³ÛŒ Ù…Ø§Ù„Ú© ÙØ¹Ø§Ù„" : (user.canModerateChat ? "Ø¯Ø³ØªØ±Ø³ÛŒ Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡ ÙØ¹Ø§Ù„" : "Ù†Ø´Ø³Øª ÙØ¹Ø§Ù„");
        var profile = user.profile && typeof user.profile === "object" ? user.profile : {};
        var aboutText = profileAbout(profile);
        var focusText = profile.focusArea || "";
        var contactText = profile.contactHandle || "";

        $("account-role-eyebrow").textContent = roleLabel;
        $("account-name").textContent = user.name || "Ø¯Ø§Ù†Ø´Ø¬Ùˆ";
        $("account-student-number").textContent = "Ø´Ù…Ø§Ø±Ù‡ Ø¯Ø§Ù†Ø´Ø¬ÙˆÛŒÛŒ: " + (user.studentNumber || "-");
        $("account-role-badge").textContent = roleLabel;
        $("account-session-badge").textContent = sessionLabel;

        $("profile-name").value = user.name || "";
        $("profile-student-number").value = user.studentNumber || "";
        $("profile-about").value = aboutText;
        $("profile-focus-area").value = focusText;
        $("profile-contact-handle").value = contactText;
        profileDraftAvatarUrl = profile.avatarUrl || "";
        updateIdentityAvatars(user.name || "");
        setInlineFeedback(profileAvatarFeedback, "", "");

        if (accountInfoRole) {
            accountInfoRole.textContent = roleLabel;
        }
        if (accountInfoSession) {
            accountInfoSession.textContent = sessionLabel;
        }

        if (accountRowProfileMeta) {
            accountRowProfileMeta.textContent = aboutText || focusText || contactText || "ÙˆÛŒØ±Ø§ÛŒØ´ Ø¢ÙˆØ§ØªØ§Ø±ØŒ Ø¨ÛŒÙˆ Ùˆ Ø±Ø§Ù‡ Ø§Ø±ØªØ¨Ø§Ø·ÛŒ";
        }
        if (accountRowInfoMeta) {
            accountRowInfoMeta.textContent = [user.studentNumber || "-", roleLabel].join(" â€¢ ");
        }

        var phone = parsedPhone(user);
        var phoneLabel = ltrMaskedPhone(phone.numberMasked, "Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡");
        if (accountRowPhoneMeta) {
            if (!phone.hasNumber) {
                accountRowPhoneMeta.textContent = "Ù‡Ù†ÙˆØ² Ø´Ù…Ø§Ø±Ù‡â€ŒØ§ÛŒ Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.";
            } else if (!phone.verified) {
                accountRowPhoneMeta.textContent = "Ø´Ù…Ø§Ø±Ù‡ " + phoneLabel + " Ø«Ø¨Øª Ø´Ø¯Ù‡ ÙˆÙ„ÛŒ Ù‡Ù†ÙˆØ² ØªØ§ÛŒÛŒØ¯ Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.";
            } else if (phone.otpLoginEnabled) {
                accountRowPhoneMeta.textContent = "ÙˆØ±ÙˆØ¯ Ø¨Ø§ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ ÙØ¹Ø§Ù„ Ø§Ø³Øª (" + phoneLabel + ").";
            } else {
                accountRowPhoneMeta.textContent = "Ø´Ù…Ø§Ø±Ù‡ " + phoneLabel + " ØªØ§ÛŒÛŒØ¯ Ø´Ø¯Ù‡ Ø§Ø³Øª ÙˆÙ„ÛŒ ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ ØºÛŒØ±ÙØ¹Ø§Ù„ Ø§Ø³Øª.";
            }
        }
        renderPhoneSecurityState(user);

        if (accountRotation) {
            var rotation = user.rotation && typeof user.rotation === "object" ? user.rotation : null;
            var rotationSummary = rotation && rotation.assigned ? String(rotation.summary || "").trim() : "";
            if (rotationSummary) {
                accountRotation.hidden = false;
                accountRotation.textContent = "Ø±ÙˆØªÛŒØ´Ù†/Ú¯Ø±ÙˆÙ‡: " + rotationSummary;
                if (accountInfoRotation) {
                    accountInfoRotation.textContent = rotationSummary;
                }
            } else {
                accountRotation.hidden = true;
                accountRotation.textContent = "";
                if (accountInfoRotation) {
                    accountInfoRotation.textContent = "â€”";
                }
            }
        } else if (accountInfoRotation) {
            accountInfoRotation.textContent = "â€”";
        }
    }

    function renderOwnerSummary(users) {
        var totalUsers = users.length;
        var representatives = users.filter(function (user) {
            return user.role === "representative";
        }).length;
        var withGrades = users.filter(function (user) {
            return user.hasGrades;
        }).length;
        var withPhone = users.filter(function (user) {
            return !!user.hasPhone;
        }).length;

        ownerSummary.innerHTML = [
            summaryCard("Ú©Ø§Ø±Ø¨Ø±", totalUsers.toLocaleString("fa-IR"), "Ú©Ù„ Ø­Ø³Ø§Ø¨â€ŒÙ‡Ø§ÛŒ ØªØ¹Ø±ÛŒÙâ€ŒØ´Ø¯Ù‡"),
            summaryCard("Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡", representatives.toLocaleString("fa-IR"), "Ø§ÙØ±Ø§Ø¯ Ø¯Ø§Ø±Ø§ÛŒ Ø¯Ø³ØªØ±Ø³ÛŒ Ú¯ÙØªâ€ŒÙˆÚ¯Ùˆ"),
            summaryCard("Ø¯Ø§Ø±Ø§ÛŒ Ø´Ù…Ø§Ø±Ù‡", withPhone.toLocaleString("fa-IR"), "Ú©Ø§Ø±Ø¨Ø±Ù‡Ø§ÛŒÛŒ Ú©Ù‡ Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡ Ø¯Ø§Ø±Ù†Ø¯"),
            summaryCard("Ø¯Ø§Ø±Ø§ÛŒ Ù†Ù…Ø±Ù‡", withGrades.toLocaleString("fa-IR"), "Ú©Ø§Ø±Ø¨Ø±Ù‡Ø§ÛŒÛŒ Ú©Ù‡ Ø¯Ø± ÙØ§ÛŒÙ„ Ù†Ù…Ø±Ø§Øª Ø±Ú©ÙˆØ±Ø¯ Ø¯Ø§Ø±Ù†Ø¯")
        ].join("");

        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = [
                "Ú©Ø§Ø±Ø¨Ø± " + totalUsers.toLocaleString("fa-IR"),
                "Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡ " + representatives.toLocaleString("fa-IR")
            ].join(" â€¢ ");
        }
    }

    function summaryCard(label, value, meta, tone) {
        var toneClass = String(tone || "").trim();
        return [
            '<article class="owner-summary-card' + (toneClass ? (" owner-summary-card--" + toneClass) : "") + '">',
            '  <span>' + label + "</span>",
            '  <strong>' + value + "</strong>",
            '  <small>' + meta + "</small>",
            "</article>"
        ].join("");
    }

    function formatBytes(bytes) {
        var size = Math.max(0, Math.floor(toNumber(bytes, 0)));
        if (!size) return "0 B";
        if (size < 1024) return size + " B";
        if (size < (1024 * 1024)) return (size / 1024).toFixed(1) + " KB";
        if (size < (1024 * 1024 * 1024)) return (size / (1024 * 1024)).toFixed(1) + " MB";
        return (size / (1024 * 1024 * 1024)).toFixed(1) + " GB";
    }

    function ownerSmsFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerSmsFeedback, text, kind, loading);
    }

    function ownerMediaFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerMediaFeedback, text, kind, loading);
    }

    function renderOwnerSmsStatus(status) {
        if (!ownerSmsStatus) return;
        var s = status && typeof status === "object" ? status : {};
        var ready = !!(s.enabled && s.apiKeyConfigured && s.patternConfigured && s.senderLineConfigured);
        var missing = [];
        if (!s.enabled) missing.push("ÙØ¹Ø§Ù„â€ŒØ³Ø§Ø²ÛŒ Ø³Ø±ÙˆÛŒØ³");
        if (!s.apiKeyConfigured) missing.push("API Key");
        if (!s.patternConfigured) missing.push("Pattern Code");
        if (!s.senderLineConfigured) missing.push("Ù„Ø§ÛŒÙ† Ø§Ø±Ø³Ø§Ù„");

        var healthLabel = smsHealthStatusLabel(s.lastHealthStatus || "");
        var healthTone = healthLabel === "Ø³Ø§Ù„Ù…" ? "ok" : (healthLabel === "Ø®Ø·Ø§Ø¯Ø§Ø±" ? "danger" : "warn");
        var readinessMeta = ready
            ? "ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù¾Ø§ÛŒÙ‡ Ø¨Ø±Ø§ÛŒ Ø§Ø±Ø³Ø§Ù„ OTP Ú©Ø§Ù…Ù„ Ø§Ø³Øª."
            : ("Ù…ÙˆØ§Ø±Ø¯ Ù†Ø§Ù‚Øµ: " + missing.join("ØŒ "));

        ownerSmsStatus.innerHTML = [
            summaryCard("Ø¢Ù…Ø§Ø¯Ú¯ÛŒ OTP", ready ? "Ø¢Ù…Ø§Ø¯Ù‡" : "Ù†Ø§Ù‚Øµ", readinessMeta, ready ? "ok" : "warn"),
            summaryCard("Ø³Ø±ÙˆÛŒØ³", s.enabled ? "ÙØ¹Ø§Ù„" : "ØºÛŒØ±ÙØ¹Ø§Ù„", "ÙˆØ¶Ø¹ÛŒØª Ú©Ù„ÛŒ Ø³Ø±ÙˆÛŒØ³ FarazSMS", s.enabled ? "ok" : "warn"),
            summaryCard("API Key", s.apiKeyConfigured ? "ØªÙ†Ø¸ÛŒÙ… Ø´Ø¯Ù‡" : "ØªÙ†Ø¸ÛŒÙ… Ù†Ø´Ø¯Ù‡", "Ú©Ù„ÛŒØ¯ API ÙÙ‚Ø· Ø±ÙˆÛŒ Ø³Ø±ÙˆØ± Ù†Ú¯Ù‡Ø¯Ø§Ø±ÛŒ Ù…ÛŒâ€ŒØ´ÙˆØ¯.", s.apiKeyConfigured ? "ok" : "warn"),
            summaryCard("Pattern Code", s.patternConfigured ? "ØªÙ†Ø¸ÛŒÙ… Ø´Ø¯Ù‡" : "ØªÙ†Ø¸ÛŒÙ… Ù†Ø´Ø¯Ù‡", "Ú©Ø¯ Ù¾ØªØ±Ù† Ù…Ø®ØµÙˆØµ Ø§Ø±Ø³Ø§Ù„ OTP.", s.patternConfigured ? "ok" : "warn"),
            summaryCard("Ø®Ø· Ø§Ø±Ø³Ø§Ù„", s.senderLineConfigured ? (s.senderLine || "ØªÙ†Ø¸ÛŒÙ… Ø´Ø¯Ù‡") : "Ù…Ø³ÛŒØ± Ø®Ø¯Ù…Ø§ØªÛŒ", "Ø¯Ø± Ù†Ø¨ÙˆØ¯ Ø®Ø· Ø§Ø®ØªØµØ§ØµÛŒØŒ Ø§Ø² Ù…Ø³ÛŒØ± Ø®Ø¯Ù…Ø§ØªÛŒ/Ø§Ø´ØªØ±Ø§Ú©ÛŒ Ø§Ø³ØªÙØ§Ø¯Ù‡ Ù…ÛŒâ€ŒØ´ÙˆØ¯."),
            summaryCard("Ø¯Ø§Ù…Ù†Ù‡", s.domainConfigured ? (s.domain || "ØªÙ†Ø¸ÛŒÙ… Ø´Ø¯Ù‡") : "ØªÙ†Ø¸ÛŒÙ… Ù†Ø´Ø¯Ù‡", "Ø¨Ø±Ø§ÛŒ Ù…Ø§Ù†ÛŒØªÙˆØ±ÛŒÙ†Ú¯ Ùˆ Ø§Ø¹ØªØ¨Ø§Ø±Ø³Ù†Ø¬ÛŒ Ø¯Ø±Ø®ÙˆØ§Ø³Øªâ€ŒÙ‡Ø§."),
            summaryCard("Ø¢Ø®Ø±ÛŒÙ† ØªØ³Øª Ø³Ù„Ø§Ù…Øª", healthLabel, s.lastHealthMessage || "Ù‡Ù†ÙˆØ² ØªØ³ØªÛŒ Ø§Ø¬Ø±Ø§ Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.", healthTone),
            summaryCard("Ø²Ù…Ø§Ù† Ø¢Ø®Ø±ÛŒÙ† ØªØ³Øª", s.lastHealthAt || "â€”", "Ø¢Ø®Ø±ÛŒÙ† Ø²Ù…Ø§Ù† health check Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡")
        ].join("");

        if (ownerSmsEnabled) {
            ownerSmsEnabled.checked = !!s.enabled;
        }
        if (ownerSmsPatternCode && !ownerSmsPatternCode.value) {
            ownerSmsPatternCode.value = s.patternConfigured ? (ownerSmsPatternCode.value || "") : "";
        }
        if (ownerSmsSenderLine) {
            ownerSmsSenderLine.value = s.senderLine || "";
        }
        if (ownerSmsDomain) {
            ownerSmsDomain.value = s.domain || "";
        }
        if (ownerSmsCodeParam) {
            ownerSmsCodeParam.value = s.codeParam || "code";
        }
        if (ownerSmsTestPhone) {
            var normalized = normalizedPhone(ownerSmsTestPhone.value);
            ownerSmsTestPhone.value = normalized;
        }
    }

    function renderOwnerMediaStatus(media) {
        if (!ownerMediaStatus) return;
        var m = media && typeof media === "object" ? media : {};
        var usagePercent = toNumber(m.usagePercent, 0);
        ownerMediaStatus.innerHTML = [
            summaryCard("Ù…ØµØ±Ù ÙØ¶Ø§ÛŒ Ù…Ø¯ÛŒØ±ÛŒØªâ€ŒØ´Ø¯Ù‡", usagePercent.toFixed(2) + "%", formatBytes(m.usageBytes || 0) + " Ø§Ø² " + formatBytes(m.targetBytes || 0)),
            summaryCard("Ø¢Ø³ØªØ§Ù†Ù‡ Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ", toNumber(m.thresholdPercent, 60).toFixed(2) + "%", "ÙˆÙ‚ØªÛŒ Ù…ØµØ±Ù Ø§Ø² Ø§ÛŒÙ† Ø­Ø¯ Ø¹Ø¨ÙˆØ± Ú©Ù†Ø¯ Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ Ø®ÙˆØ¯Ú©Ø§Ø± Ø§Ø¬Ø±Ø§ Ù…ÛŒâ€ŒØ´ÙˆØ¯"),
            summaryCard("ÙØ§ÛŒÙ„ Ø§ØµÙ„ÛŒ Ø¨Ø§Ù‚ÛŒâ€ŒÙ…Ø§Ù†Ø¯Ù‡", String(Math.max(0, Math.floor(toNumber(m.originalCount, 0))).toLocaleString("fa-IR")), "ØªØ¹Ø¯Ø§Ø¯ originalÙ‡Ø§ÛŒÛŒ Ú©Ù‡ Ù‡Ù†ÙˆØ² Ø¯Ø± Ø¯Ø³ØªØ±Ø³â€ŒØ§Ù†Ø¯"),
            summaryCard("ÙØ§ÛŒÙ„ Ù¾Ø§Ú©â€ŒØ´Ø¯Ù‡", String(Math.max(0, Math.floor(toNumber(m.purgedCount, 0))).toLocaleString("fa-IR")), "ØªØ¹Ø¯Ø§Ø¯ originalÙ‡Ø§ÛŒ Ù…Ù†Ù‚Ø¶ÛŒ/Ù¾Ø§Ú©â€ŒØ´Ø¯Ù‡"),
            summaryCard("Ø¢Ø®Ø±ÛŒÙ† Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ", m.lastCleanupAt || "â€”", m.lastCleanupStatus || "unknown"),
            summaryCard("Ø³Ù„Ø§Ù…Øª Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ", m.cleanupHealthy ? "Ø³Ø§Ù„Ù…" : "Ù…Ø´Ú©Ù„â€ŒØ¯Ø§Ø±", m.lastCleanupError || "Ø¨Ø¯ÙˆÙ† Ø®Ø·Ø§ÛŒ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡")
        ].join("");
    }

    async function loadOwnerSmsStatus() {
        if (!currentUser || !currentUser.isOwner) return;
        smsState.loading = true;
        ownerSmsFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø¯Ø±ÛŒØ§ÙØª ÙˆØ¶Ø¹ÛŒØª Ù¾ÛŒØ§Ù…Ú©...", "", true);
        var response = await request("smsStatus", {});
        smsState.loading = false;

        if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
            ownerSmsFeedbackMessage("", "");
            return;
        }
        if (!response || !response.success || !response.status) {
            ownerSmsFeedbackMessage((response && response.error) || "Ø®ÙˆØ§Ù†Ø¯Ù† ÙˆØ¶Ø¹ÛŒØª Ù¾ÛŒØ§Ù…Ú© Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
            return;
        }

        smsState.status = response.status;
        renderOwnerSmsStatus(smsState.status);
        ownerSmsFeedbackMessage("", "");
    }

    async function saveOwnerSmsConfig(event) {
        if (event) event.preventDefault();
        if (!currentUser || !currentUser.isOwner || !ownerSmsForm) return;

        ownerSmsFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù¾ÛŒØ§Ù…Ú©...", "", true);
        if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = true;
        if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = true;

        try {
            var response = await request("saveSmsConfig", {
                enabled: ownerSmsEnabled && ownerSmsEnabled.checked ? "1" : "0",
                apiKey: ownerSmsApiKey ? ownerSmsApiKey.value.trim() : "",
                clearApiKey: ownerSmsClearApi && ownerSmsClearApi.checked ? "1" : "0",
                patternCode: ownerSmsPatternCode ? ownerSmsPatternCode.value.trim() : "",
                senderLine: ownerSmsSenderLine ? ownerSmsSenderLine.value.trim() : "",
                domain: ownerSmsDomain ? ownerSmsDomain.value.trim() : "",
                codeParam: ownerSmsCodeParam ? ownerSmsCodeParam.value.trim() : "code"
            });

            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerSmsFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.status) {
                ownerSmsFeedbackMessage((response && response.error) || "Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù¾ÛŒØ§Ù…Ú© Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            smsState.status = response.status;
            renderOwnerSmsStatus(smsState.status);
            if (ownerSmsApiKey) ownerSmsApiKey.value = "";
            if (ownerSmsClearApi) ownerSmsClearApi.checked = false;
            ownerSmsFeedbackMessage(response.message || "ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù¾ÛŒØ§Ù…Ú© Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.", "success");
        } finally {
            if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = false;
            if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = false;
        }
    }

    async function runOwnerSmsHealthCheck() {
        if (!currentUser || !currentUser.isOwner) return;
        ownerSmsFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø¨Ø±Ø±Ø³ÛŒ Ø³Ù„Ø§Ù…Øª Ø³Ø±ÙˆÛŒØ³ Ù¾ÛŒØ§Ù…Ú©...", "", true);
        if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = true;
        if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = true;

        try {
            var testPhone = ensureOwnerSmsHealthPhone();
            if (!testPhone) {
                ownerSmsFeedbackMessage("Ø¨Ø±Ø§ÛŒ ØªØ³Øª Ø§Ø±Ø³Ø§Ù„ ÙˆØ§Ù‚Ø¹ÛŒØŒ Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ù…Ø¹ØªØ¨Ø± Ø±Ø§ ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
                return;
            }
            var response = await request("smsHealthCheck", {
                phoneNumber: testPhone
            });
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerSmsFeedbackMessage("", "");
                return;
            }
            if (response && response.status) {
                smsState.status = response.status;
                renderOwnerSmsStatus(smsState.status);
            }
            if (!response || !response.success) {
                ownerSmsFeedbackMessage((response && response.error) || (response && response.message) || "ØªØ³Øª Ø³Ù„Ø§Ù…Øª Ø³Ø±ÙˆÛŒØ³ Ù¾ÛŒØ§Ù…Ú©ÛŒ Ù†Ø§Ù…ÙˆÙÙ‚ Ø¨ÙˆØ¯.", "error");
                return;
            }
            ownerSmsFeedbackMessage(response.message || "ØªØ³Øª Ø³Ù„Ø§Ù…Øª Ø³Ø±ÙˆÛŒØ³ Ù¾ÛŒØ§Ù…Ú©ÛŒ Ø¨Ø§ Ù…ÙˆÙÙ‚ÛŒØª Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯.", "success");
        } finally {
            if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = false;
            if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = false;
        }
    }

    async function loadOwnerMediaStatus() {
        if (!currentUser || !currentUser.isOwner) return;
        mediaState.loading = true;
        ownerMediaFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø¯Ø±ÛŒØ§ÙØª ÙˆØ¶Ø¹ÛŒØª ÙØ¶Ø§ÛŒ Ø±Ø³Ø§Ù†Ù‡...", "", true);
        var response = await fetch("/chat/chat_api.php?action=mediaStatus", {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯." };
            }).then(function (data) {
                data.httpStatus = res.status;
                return data;
            });
        });
        mediaState.loading = false;

        if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
            ownerMediaFeedbackMessage("", "");
            return;
        }
        if (!response || !response.success || !response.media) {
            ownerMediaFeedbackMessage((response && response.error) || "Ø®ÙˆØ§Ù†Ø¯Ù† ÙˆØ¶Ø¹ÛŒØª ÙØ¶Ø§ÛŒ Ø±Ø³Ø§Ù†Ù‡ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
            return;
        }

        mediaState.status = response.media;
        renderOwnerMediaStatus(mediaState.status);
        ownerMediaFeedbackMessage("", "");
    }

    async function runOwnerMediaCleanup() {
        if (!currentUser || !currentUser.isOwner) return;
        ownerMediaFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø§Ø¬Ø±Ø§ÛŒ Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ ÙÙˆØ±ÛŒ...", "", true);
        if (ownerMediaCleanupButton) ownerMediaCleanupButton.disabled = true;
        if (ownerMediaRefreshButton) ownerMediaRefreshButton.disabled = true;

        try {
            var response = await fetch("/chat/chat_api.php", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "Accept": "application/json"
                },
                body: new URLSearchParams({ action: "mediaCleanupNow" })
            }).then(function (res) {
                return res.json().catch(function () {
                    return { success: false, error: "Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø² Ø³Ø±ÙˆØ± Ø¯Ø±ÛŒØ§ÙØª Ø´Ø¯." };
                }).then(function (data) {
                    data.httpStatus = res.status;
                    return data;
                });
            });

            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerMediaFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.media) {
                ownerMediaFeedbackMessage((response && response.error) || "Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ ÙØ¶Ø§ÛŒ Ø±Ø³Ø§Ù†Ù‡ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            mediaState.status = response.media;
            renderOwnerMediaStatus(mediaState.status);
            ownerMediaFeedbackMessage("Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ ÙÙˆØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯.", "success");
        } finally {
            if (ownerMediaCleanupButton) ownerMediaCleanupButton.disabled = false;
            if (ownerMediaRefreshButton) ownerMediaRefreshButton.disabled = false;
        }
    }

    function userMatchesQuery(user, query) {
        if (!query) {
            return true;
        }

        var normalized = query.trim().toLowerCase();
        if (!normalized) {
            return true;
        }

        return String(user.name || "").toLowerCase().indexOf(normalized) !== -1 ||
            String(user.studentNumber || "").indexOf(normalized) !== -1;
    }

    function renderRepresentatives(users) {
        var items = users.filter(function (user) {
            return user.role === "representative";
        });

        if (!items.length) {
            representativeList.innerHTML = '<div class="owner-empty">ÙØ¹Ù„Ø§Ù‹ Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡â€ŒØ§ÛŒ ØªØ¹Ø±ÛŒÙ Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.</div>';
            return;
        }

        representativeList.innerHTML = "";
        items.forEach(function (user) {
            var article = document.createElement("article");
            article.className = "representative-chip";
            article.innerHTML = [
                "<strong>" + user.name + "</strong>",
                "<span>" + user.studentNumber + "</span>"
            ].join("");
            representativeList.appendChild(article);
        });
    }

    function toggleButtonLabel(user) {
        if (user.role === "owner") {
            return "Ù…Ø§Ù„Ú© Ø§ØµÙ„ÛŒ";
        }

        return user.role === "representative" ? "Ù„ØºÙˆ Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡" : "Ø«Ø¨Øª Ø¨Ù‡â€ŒØ¹Ù†ÙˆØ§Ù† Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡";
    }

    function isOwnerUser(user) {
        return !!user && user.role === "owner";
    }

    function ownerRoleMeta(user) {
        if (!user) {
            return "Ø¯Ø§Ù†Ø´Ø¬Ùˆ";
        }
        return user.roleLabel || (user.role === "representative" ? "Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡" : (user.role === "owner" ? "Ù…Ø§Ù„Ú©" : "Ø¯Ø§Ù†Ø´Ø¬Ùˆ"));
    }

    function ownerUserPhoneMeta(user) {
        var phone = parsedPhone(user || {});
        if (!phone.hasNumber) {
            return "Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡";
        }
        var masked = ltrMaskedPhone(phone.numberMasked, "Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡");
        if (!phone.verified) {
            return masked + " (ØªØ§ÛŒÛŒØ¯ Ù†Ø´Ø¯Ù‡)";
        }
        if (phone.otpLoginEnabled) {
            return masked + " (OTP ÙØ¹Ø§Ù„)";
        }
        return masked + " (OTP ØºÛŒØ±ÙØ¹Ø§Ù„)";
    }

    function ownerGradesPayload(studentNumber) {
        var key = String(studentNumber || "");
        if (!key) {
            return null;
        }
        var payload = ownerState.gradePayloadByStudent[key];
        if (!payload || typeof payload !== "object") {
            return null;
        }
        return payload;
    }

    function ownerGradeSaveKey(studentNumber, columnIndex) {
        return String(studentNumber || "") + ":" + String(Math.max(0, Math.floor(toNumber(columnIndex, 0))));
    }

    function ownerUserBusyState(studentNumber) {
        var key = String(studentNumber || "");
        return {
            representative: ownerState.savingStudentNumber === key,
            gradesLoading: ownerState.loadingGradesStudentNumber === key,
            removingPhone: ownerState.removingPhoneStudentNumber === key,
            deletingUser: ownerState.deletingStudentNumber === key
        };
    }

    function ownerDetailsToggleLabel(isExpanded) {
        return isExpanded ? "Ø¨Ø³ØªÙ† Ù¾Ù†Ù„" : "Ù¾Ù†Ù„ Ú©Ø§Ø±Ø¨Ø±";
    }

    function userMeta(user) {
        var parts = [ownerRoleMeta(user)];
        parts.push(user.hasPhone ? "Ø¯Ø§Ø±Ø§ÛŒ Ø´Ù…Ø§Ø±Ù‡" : "Ø¨Ø¯ÙˆÙ† Ø´Ù…Ø§Ø±Ù‡");
        if (user.hasGrades) {
            parts.push("Ø¯Ø§Ø±Ø§ÛŒ Ù†Ù…Ø±Ø§Øª");
        }
        return parts.join(" â€¢ ");
    }

    function buildOwnerMetaCell(label, value) {
        var item = document.createElement("div");
        item.className = "owner-user-meta-item";

        var title = document.createElement("span");
        title.textContent = label;

        var content = document.createElement("strong");
        content.textContent = value || "â€”";

        item.appendChild(title);
        item.appendChild(content);
        return item;
    }

    function buildOwnerUserDetails(user, busyState) {
        var studentNumber = String(user.studentNumber || "");
        var details = document.createElement("section");
        details.className = "owner-user__details";

        var metaGrid = document.createElement("div");
        metaGrid.className = "owner-user__meta-grid";
        metaGrid.appendChild(buildOwnerMetaCell("Ù†Ø§Ù…", user.name || "â€”"));
        metaGrid.appendChild(buildOwnerMetaCell("Ø´Ù…Ø§Ø±Ù‡ Ø¯Ø§Ù†Ø´Ø¬ÙˆÛŒÛŒ", studentNumber || "â€”"));
        metaGrid.appendChild(buildOwnerMetaCell("Ù†Ù‚Ø´", ownerRoleMeta(user)));
        metaGrid.appendChild(buildOwnerMetaCell("Ù…ÙˆØ¨Ø§ÛŒÙ„", ownerUserPhoneMeta(user)));
        details.appendChild(metaGrid);

        var actions = document.createElement("div");
        actions.className = "owner-user__actions owner-user__actions--detail";

        var gradesPayload = ownerGradesPayload(studentNumber);
        var loadGradesBtn = document.createElement("button");
        loadGradesBtn.type = "button";
        loadGradesBtn.className = "shell-action-btn";
        loadGradesBtn.dataset.ownerAction = "reload-grades";
        loadGradesBtn.dataset.studentNumber = studentNumber;
        loadGradesBtn.disabled = busyState.gradesLoading || busyState.deletingUser;
        loadGradesBtn.textContent = busyState.gradesLoading
            ? "Ø¯Ø± Ø­Ø§Ù„ Ø¯Ø±ÛŒØ§ÙØª Ú©Ø§Ø±Ù†Ø§Ù…Ù‡..."
            : (gradesPayload ? "Ø¨Ø§Ø±Ú¯Ø°Ø§Ø±ÛŒ Ù…Ø¬Ø¯Ø¯ Ú©Ø§Ø±Ù†Ø§Ù…Ù‡" : "Ù†Ù…Ø§ÛŒØ´ Ú©Ø§Ø±Ù†Ø§Ù…Ù‡");
        actions.appendChild(loadGradesBtn);

        var removePhoneBtn = document.createElement("button");
        removePhoneBtn.type = "button";
        removePhoneBtn.className = "shell-action-btn";
        removePhoneBtn.dataset.ownerAction = "remove-phone";
        removePhoneBtn.dataset.studentNumber = studentNumber;
        removePhoneBtn.disabled = busyState.removingPhone || busyState.deletingUser || isOwnerUser(user) || !user.hasPhone;
        removePhoneBtn.textContent = busyState.removingPhone ? "Ø¯Ø± Ø­Ø§Ù„ Ø­Ø°Ù Ø´Ù…Ø§Ø±Ù‡..." : "Ø­Ø°Ù Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡";
        actions.appendChild(removePhoneBtn);

        var deleteUserBtn = document.createElement("button");
        deleteUserBtn.type = "button";
        deleteUserBtn.className = "shell-action-btn shell-action-btn-danger";
        deleteUserBtn.dataset.ownerAction = "delete-student";
        deleteUserBtn.dataset.studentNumber = studentNumber;
        deleteUserBtn.disabled = busyState.deletingUser || isOwnerUser(user);
        deleteUserBtn.textContent = busyState.deletingUser ? "Ø¯Ø± Ø­Ø§Ù„ Ø­Ø°Ù Ø¯Ø§Ù†Ø´Ø¬Ùˆ..." : "Ø­Ø°Ù Ú©Ø§Ù…Ù„ Ø¯Ø§Ù†Ø´Ø¬Ùˆ";
        actions.appendChild(deleteUserBtn);

        details.appendChild(actions);

        var gradesWrap = document.createElement("div");
        gradesWrap.className = "owner-user__grades";

        if (busyState.gradesLoading && !gradesPayload) {
            var loadingHint = document.createElement("div");
            loadingHint.className = "owner-user__hint";
            loadingHint.textContent = "Ø¯Ø± Ø­Ø§Ù„ Ø¯Ø±ÛŒØ§ÙØª Ú©Ø§Ø±Ù†Ø§Ù…Ù‡...";
            gradesWrap.appendChild(loadingHint);
            details.appendChild(gradesWrap);
            return details;
        }

        if (!gradesPayload) {
            var emptyHint = document.createElement("div");
            emptyHint.className = "owner-user__hint";
            emptyHint.textContent = "Ø¨Ø±Ø§ÛŒ Ù…Ø´Ø§Ù‡Ø¯Ù‡ Ùˆ ÙˆÛŒØ±Ø§ÛŒØ´ Ù†Ù…Ø±Ø§ØªØŒ Ø±ÙˆÛŒ Â«Ù†Ù…Ø§ÛŒØ´ Ú©Ø§Ø±Ù†Ø§Ù…Ù‡Â» Ø¨Ø²Ù†.";
            gradesWrap.appendChild(emptyHint);
            details.appendChild(gradesWrap);
            return details;
        }

        var gradeList = document.createElement("div");
        gradeList.className = "owner-grade-list";
        var grades = Array.isArray(gradesPayload.grades) ? gradesPayload.grades : [];

        if (!grades.length) {
            var noGrades = document.createElement("div");
            noGrades.className = "owner-user__hint";
            noGrades.textContent = "Ø³ØªÙˆÙ† Ù†Ù…Ø±Ù‡â€ŒØ§ÛŒ Ø¨Ø±Ø§ÛŒ Ø§ÛŒÙ† Ø¯Ø§Ù†Ø´Ø¬Ùˆ Ù¾ÛŒØ¯Ø§ Ù†Ø´Ø¯.";
            gradesWrap.appendChild(noGrades);
            details.appendChild(gradesWrap);
            return details;
        }

        grades.forEach(function (grade) {
            var index = Math.max(0, Math.floor(toNumber(grade.index, 0)));
            var row = document.createElement("div");
            row.className = "owner-grade-row";

            var label = document.createElement("label");
            label.className = "owner-grade-row__label";
            label.textContent = String(grade.label || ("Ø³ØªÙˆÙ† " + index));
            row.appendChild(label);

            var controls = document.createElement("div");
            controls.className = "owner-grade-row__controls";

            var input = document.createElement("input");
            input.type = "text";
            input.inputMode = "decimal";
            input.className = "owner-grade-input";
            input.placeholder = "Ø¨Ø¯ÙˆÙ† Ù†Ù…Ø±Ù‡";
            input.value = String(grade.value == null ? "" : grade.value);
            input.dataset.gradeInput = "true";
            input.dataset.studentNumber = studentNumber;
            input.dataset.columnIndex = String(index);
            input.disabled = busyState.deletingUser || busyState.gradesLoading;
            controls.appendChild(input);

            var saveBtn = document.createElement("button");
            saveBtn.type = "button";
            saveBtn.className = "shell-action-btn shell-action-btn-primary";
            saveBtn.dataset.ownerAction = "save-grade";
            saveBtn.dataset.studentNumber = studentNumber;
            saveBtn.dataset.columnIndex = String(index);
            var isSaving = ownerState.savingGradeKey === ownerGradeSaveKey(studentNumber, index);
            saveBtn.disabled = isSaving || busyState.deletingUser || busyState.gradesLoading;
            saveBtn.textContent = isSaving ? "Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡..." : "Ø°Ø®ÛŒØ±Ù‡";
            controls.appendChild(saveBtn);

            row.appendChild(controls);
            gradeList.appendChild(row);
        });

        gradesWrap.appendChild(gradeList);
        details.appendChild(gradesWrap);
        return details;
    }

    function renderUsers(users) {
        var query = ownerSearch.value || "";
        var visibleUsers = users.filter(function (user) {
            return userMatchesQuery(user, query);
        });

        if (!visibleUsers.length) {
            ownerUserList.innerHTML = '<div class="owner-empty">Ú©Ø§Ø±Ø¨Ø±ÛŒ Ø¨Ø§ Ø§ÛŒÙ† Ø¬Ø³Øªâ€ŒÙˆØ¬Ùˆ Ù¾ÛŒØ¯Ø§ Ù†Ø´Ø¯.</div>';
            return;
        }

        ownerUserList.innerHTML = "";
        visibleUsers.forEach(function (user) {
            var studentNumber = String(user.studentNumber || "");
            var isExpanded = ownerState.expandedStudentNumber === studentNumber;
            var busyState = ownerUserBusyState(studentNumber);
            var article = document.createElement("article");
            article.className = "owner-user" + (isExpanded ? " is-expanded" : "");

            var head = document.createElement("div");
            head.className = "owner-user__head";

            var copy = document.createElement("div");
            copy.className = "owner-user__copy";
            var strong = document.createElement("strong");
            strong.textContent = user.name || "Ø¯Ø§Ù†Ø´Ø¬Ùˆ";
            var number = document.createElement("span");
            number.textContent = studentNumber || "â€”";
            var meta = document.createElement("small");
            meta.textContent = userMeta(user);
            copy.appendChild(strong);
            copy.appendChild(number);
            copy.appendChild(meta);
            head.appendChild(copy);

            var actions = document.createElement("div");
            actions.className = "owner-user__actions";

            var representativeBtn = document.createElement("button");
            representativeBtn.type = "button";
            representativeBtn.className = "shell-action-btn" + (user.role === "representative" ? " shell-action-btn-primary" : "");
            representativeBtn.dataset.ownerAction = "toggle-representative";
            representativeBtn.dataset.studentNumber = studentNumber;
            representativeBtn.disabled = isOwnerUser(user) || busyState.representative || busyState.deletingUser;
            representativeBtn.textContent = busyState.representative ? "Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡..." : toggleButtonLabel(user);
            actions.appendChild(representativeBtn);

            var panelBtn = document.createElement("button");
            panelBtn.type = "button";
            panelBtn.className = "shell-action-btn";
            panelBtn.dataset.ownerAction = "toggle-details";
            panelBtn.dataset.studentNumber = studentNumber;
            panelBtn.textContent = ownerDetailsToggleLabel(isExpanded);
            actions.appendChild(panelBtn);

            head.appendChild(actions);
            article.appendChild(head);

            if (isExpanded) {
                article.appendChild(buildOwnerUserDetails(user, busyState));
            }

            ownerUserList.appendChild(article);
        });
    }

    function renderOwnerPanel() {
        renderOwnerSummary(ownerState.users);
        renderRepresentatives(ownerState.users);
        renderUsers(ownerState.users);
    }
    function navidStatusResultLabel(result) {
        switch (result) {
            case "ok":
                return "\u067e\u0627\u06cc\u062f\u0627\u0631";
            case "partial":
                return "\u0646\u0627\u0642\u0635";
            case "running":
                return "\u062f\u0631 \u062d\u0627\u0644 \u0627\u062c\u0631\u0627";
            case "config-updated":
                return "\u062a\u0646\u0638\u06cc\u0645\u0627\u062a \u0628\u0647\u200c\u0631\u0648\u0632 \u0634\u062f";
            case "credentials-missing":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u062b\u0628\u062a \u0646\u0634\u062f\u0647";
            case "credentials-invalid":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0627\u0645\u0639\u062a\u0628\u0631";
            case "reconnect-required":
                return "\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f";
            case "login-failed":
                return "\u062e\u0637\u0627\u06cc \u0648\u0631\u0648\u062f";
            case "dashboard-failed":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u0634\u0628\u0648\u0631\u062f";
            case "exception":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u062e\u0644\u06cc";
            case "skipped":
                return "\u0641\u0639\u0644\u0627\u064b \u0646\u06cc\u0627\u0632 \u0646\u06cc\u0633\u062a";
            case "already-running":
                return "\u062f\u0631 \u062d\u0627\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "lock-failed":
                return "\u062e\u0637\u0627\u06cc \u0642\u0641\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0646\u0627\u0645\u0634\u062e\u0635";
        }
    }

    function navidActionRequiredLabel(action) {
        switch (String(action || "")) {
            case "save-credentials":
                return "\u062b\u0628\u062a \u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0648\u06cc\u062f";
            case "update-credentials":
                return "\u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0648\u06cc\u062f";
            case "manual-reconnect":
                return "\u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0628\u0627 \u06a9\u067e\u0686\u0627";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0647\u06cc\u0686";
        }
    }

    function navidFeedbackMessage(text, kind, loading) {
        setInlineFeedback(navidConfigFeedback, text, kind, loading);
    }

    function navidReconnectMessage(text, kind, loading) {
        setInlineFeedback(navidReconnectFeedback, text, kind, loading);
    }

    function navidSyncChallengeVisual(ownerStatus, explicitCaptchaDataUri) {
        if (!navidCaptchaImage) {
            return;
        }

        var state = ownerStatus && typeof ownerStatus === "object" ? (ownerStatus.state || {}) : {};
        var challengeActive = !!state.hasActiveChallenge;
        var captchaDataUri = String(explicitCaptchaDataUri || state.captchaDataUri || "").trim();

        if (challengeActive && captchaDataUri) {
            navidCaptchaImage.hidden = false;
            navidCaptchaImage.src = captchaDataUri;
            return;
        }

        navidCaptchaImage.hidden = true;
        navidCaptchaImage.removeAttribute("src");
    }

    function navidRenderOwnerStatus(ownerStatus) {
        if (!navidOwnerStatus) {
            return;
        }

        if (!ownerStatus || typeof ownerStatus !== "object") {
            navidOwnerStatus.innerHTML = summaryCard("\u0646\u0648\u06cc\u062f", "\u2014", "\u0648\u0636\u0639\u06cc\u062a \u06cc\u06a9\u067e\u0627\u0631\u0686\u0647\u200c\u0633\u0627\u0632\u06cc \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
            navidSyncChallengeVisual(null, "");
            if (accountRowNavidMeta) {
                accountRowNavidMeta.textContent = "\u0648\u0636\u0639\u06cc\u062a \u0627\u062a\u0635\u0627\u0644 \u062e\u0648\u0627\u0646\u062f\u0647 \u0646\u0634\u062f\u0647 \u0627\u0633\u062a";
            }
            return;
        }

        var config = ownerStatus.config || {};
        var state = ownerStatus.state || {};
        var session = ownerStatus.session || {};
        var counts = ownerStatus.snapshotCounts || {};
        var actionRequired = String(state.actionRequired || "");
        var statusDetail = String(state.lastError || "").trim();
        var failedCourses = Math.max(0, Math.floor(toNumber(state.lastFailedCourses != null ? state.lastFailedCourses : counts.failedCourses, 0)));

        if (!statusDetail) {
            if (actionRequired === "save-credentials" || !!state.credentialsMissing) {
                statusDetail = "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0648 \u0631\u0645\u0632 \u0646\u0648\u06cc\u062f \u0630\u062e\u06cc\u0631\u0647 \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.";
            } else if (actionRequired === "update-credentials" || !!state.credentialsInvalid) {
                statusDetail = "\u0627\u0639\u062a\u0628\u0627\u0631 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0633\u062a.";
            } else if (actionRequired === "manual-reconnect" || !!state.requiresReconnect) {
                statusDetail = "\u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u062f\u0627\u0631\u062f.";
            } else if (String(state.lastResult || "") === "partial") {
                statusDetail = failedCourses > 0
                    ? ("\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f\u061b " + failedCourses.toLocaleString("fa-IR") + " \u062f\u0631\u0633 \u062f\u0631\u06cc\u0627\u0641\u062a \u0646\u0634\u062f.")
                    : "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f \u0648 \u062e\u0631\u0648\u062c\u06cc \u062a\u0627\u06cc\u06cc\u062f \u0646\u0634\u062f.";
            } else if (String(state.lastResult || "") === "ok") {
                statusDetail = "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f \u0633\u0627\u0644\u0645 \u0627\u0633\u062a.";
            } else {
                statusDetail = "\u0628\u062f\u0648\u0646 \u062c\u0632\u0626\u06cc\u0627\u062a \u062e\u0637\u0627";
            }
        }

        navidOwnerStatus.innerHTML = [
            summaryCard("\u0648\u0636\u0639\u06cc\u062a", navidStatusResultLabel(state.lastResult || ""), statusDetail),
            summaryCard("\u0622\u062e\u0631\u06cc\u0646 \u0645\u0648\u0641\u0642", state.lastSuccessAt || "\u2014", "\u0622\u062e\u0631\u06cc\u0646 \u0632\u0645\u0627\u0646 \u0645\u0648\u0641\u0642\u06cc\u062a \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc"),
            summaryCard("\u062f\u0631\u0648\u0633 \u0645\u0648\u0641\u0642", String(counts.successfulCourses || 0).toLocaleString("fa-IR"), "\u062a\u0639\u062f\u0627\u062f \u062f\u0631\u0633\u200c\u0647\u0627\u06cc\u06cc \u06a9\u0647 \u062f\u0631 \u0622\u062e\u0631\u06cc\u0646 sync \u0628\u062f\u0648\u0646 \u062e\u0637\u0627 \u06af\u0631\u0641\u062a\u0647 \u0634\u062f\u0646\u062f.", (counts.successfulCourses || 0) > 0 ? "ok" : ""),
            summaryCard("\u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc", String(counts.assignments || 0).toLocaleString("fa-IR"), "\u0645\u062c\u0645\u0648\u0639 \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u062f\u0631 snapshot"),
            summaryCard("\u062f\u0631\u0648\u0633 \u0646\u0627\u0645\u0648\u0641\u0642", String(failedCourses).toLocaleString("fa-IR"), "\u062a\u0627 \u0635\u0641\u0631 \u0646\u0634\u062f\u0646 \u0627\u06cc\u0646 \u0645\u0642\u062f\u0627\u0631\u060c \u062e\u0631\u0648\u062c\u06cc \u0646\u0648\u06cc\u062f \u062a\u0627\u06cc\u06cc\u062f \u0646\u0645\u06cc\u200c\u0634\u0648\u062f.", failedCourses > 0 ? "warn" : "ok"),
            summaryCard("\u0627\u0642\u062f\u0627\u0645 \u0644\u0627\u0632\u0645", navidActionRequiredLabel(actionRequired), "\u0627\u0642\u062f\u0627\u0645\u06cc \u06a9\u0647 \u0628\u0631\u0627\u06cc \u067e\u0627\u06cc\u062f\u0627\u0631\u06cc \u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u06cc\u062f \u0627\u0646\u062c\u0627\u0645 \u0634\u0648\u062f."),
            summaryCard("\u062d\u0633\u0627\u0628 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647", config.hasCredentials ? (config.usernameMasked || "\u062b\u0628\u062a \u0634\u062f\u0647") : "\u062b\u0628\u062a \u0646\u0634\u062f\u0647", "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0631\u0645\u0632\u0646\u06af\u0627\u0631\u06cc\u200c\u0634\u062f\u0647 \u062f\u0631 \u0633\u0631\u0648\u0631"),
            summaryCard("\u0648\u0636\u0639\u06cc\u062a \u0646\u0634\u0633\u062a", session.status || "missing", "\u0622\u062e\u0631\u06cc\u0646 \u0648\u0636\u0639\u06cc\u062a \u062f\u0627\u062f\u0647 \u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f"),
            summaryCard("\u0627\u0646\u0642\u0636\u0627\u06cc \u0686\u0627\u0644\u0634", state.challengeExpiresAt || "\u2014", "\u0627\u06af\u0631 \u06a9\u067e\u0686\u0627\u06cc \u062f\u0633\u062a\u06cc \u0641\u0639\u0627\u0644 \u0628\u0627\u0634\u062f\u060c \u0627\u06cc\u0646 \u0632\u0645\u0627\u0646 \u0628\u0631\u0627\u06cc \u062a\u06a9\u0645\u06cc\u0644 \u0622\u0646 \u0627\u0633\u062a.")
        ].join("");

        if (accountRowNavidMeta) {
            accountRowNavidMeta.textContent = [
                navidStatusResultLabel(state.lastResult || ""),
                navidActionRequiredLabel(actionRequired),
                state.lastSuccessAt || "\u0628\u062f\u0648\u0646 \u0632\u0645\u0627\u0646 \u0645\u0648\u0641\u0642"
            ].join(" \u2022 ");
        }

        if (navidLoginUrlInput) {
            navidLoginUrlInput.value = config.loginUrl || "";
        }
        if (navidSyncIntervalInput) {
            navidSyncIntervalInput.value = String(config.syncIntervalMinutes || 30);
        }
        if (navidCaptchaStrategyInput) {
            navidCaptchaStrategyInput.value = config.captchaStrategy || "python_ocr";
        }
        navidSyncChallengeVisual(ownerStatus, "");
    }

    async function loadNavidOwnerStatus() {
        if (!navidOwnerStatus) {
            return;
        }

        navidState.loading = true;
        navidFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø®ÙˆØ§Ù†Ø¯Ù† ÙˆØ¶Ø¹ÛŒØª Ù†ÙˆÛŒØ¯...", "", true);
        navidRenderOwnerStatus(navidState.ownerStatus);

        var response = await navidGet("ownerStatus");
        navidState.loading = false;

        if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
            navidFeedbackMessage("", "");
            return;
        }

        if (!response || !response.success || !response.ownerStatus) {
            navidFeedbackMessage((response && response.error) || "ÙˆØ¶Ø¹ÛŒØª Ù†ÙˆÛŒØ¯ Ø®ÙˆØ§Ù†Ø¯Ù‡ Ù†Ø´Ø¯.", "error");
            return;
        }

        navidState.ownerStatus = response.ownerStatus;
        navidState.loaded = true;
        navidRenderOwnerStatus(navidState.ownerStatus);
        navidFeedbackMessage("", "");
    }

    async function saveNavidConfig(event) {
        event.preventDefault();
        if (!navidConfigForm) {
            return;
        }

        var payload = {
            enabled: "1",
            loginUrl: (navidLoginUrlInput && navidLoginUrlInput.value.trim()) || "",
            syncIntervalMinutes: (navidSyncIntervalInput && navidSyncIntervalInput.value.trim()) || "30",
            captchaStrategy: (navidCaptchaStrategyInput && navidCaptchaStrategyInput.value) || "python_ocr",
            username: (navidUsernameInput && navidUsernameInput.value.trim()) || "",
            password: (navidPasswordInput && navidPasswordInput.value.trim()) || ""
        };

        navidFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù†ÙˆÛŒØ¯...", "", true);
        if (navidSyncNowButton) {
            navidSyncNowButton.disabled = true;
        }

        try {
            var response = await navidPost("saveConfig", payload);
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                navidFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidFeedbackMessage((response && response.error) || "Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù†ÙˆÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
            navidFeedbackMessage(response.message || "ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ù†ÙˆÛŒØ¯ Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.", "success");
            if (navidPasswordInput) {
                navidPasswordInput.value = "";
            }
        } finally {
            if (navidSyncNowButton) {
                navidSyncNowButton.disabled = false;
            }
        }
    }

    async function syncNavidNow() {
        if (!navidSyncNowButton) {
            return;
        }

        navidSyncNowButton.disabled = true;
        navidFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ù‡Ù…Ú¯Ø§Ù…â€ŒØ³Ø§Ø²ÛŒ ÙÙˆØ±ÛŒ Ù†ÙˆÛŒØ¯...", "", true);
        try {
            var response = await navidPost("syncNow", {});
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                navidFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidFeedbackMessage((response && response.message) || (response && response.error) || "Ù‡Ù…Ú¯Ø§Ù…â€ŒØ³Ø§Ø²ÛŒ Ù†ÙˆÛŒØ¯ Ù…ÙˆÙÙ‚ Ù†Ø´Ø¯.", "error");
            } else {
                navidFeedbackMessage(response.message || "Ù‡Ù…Ú¯Ø§Ù…â€ŒØ³Ø§Ø²ÛŒ Ù†ÙˆÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯.", "success");
            }

            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
            if (!response || !response.success) {
                navidSyncChallengeVisual(navidState.ownerStatus, response && response.captchaDataUri);
                if (response && response.captchaDataUri) {
                    navidReconnectMessage("\u06a9\u067e\u0686\u0627\u06cc \u0646\u0648\u06cc\u062f \u0622\u0645\u0627\u062f\u0647 \u0627\u0633\u062a. \u06a9\u062f \u0631\u0627 \u0648\u0627\u0631\u062f \u06a9\u0646 \u0648 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0631\u0627 \u0628\u0632\u0646.", "error");
                }
            }
        } finally {
            navidSyncNowButton.disabled = false;
        }
    }

    async function loadNavidCaptchaChallenge() {
        if (!navidGetCaptchaButton) {
            return;
        }

        navidGetCaptchaButton.disabled = true;
        navidReconnectMessage("Ø¯Ø± Ø­Ø§Ù„ Ø¯Ø±ÛŒØ§ÙØª Ú©Ù¾Ú†Ø§ÛŒ Ù†ÙˆÛŒØ¯...", "", true);
        try {
            var response = await navidPost("captchaChallenge", {});
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                navidReconnectMessage("", "");
                return;
            }

            if (!response || !response.success || !response.captchaDataUri) {
                navidReconnectMessage((response && response.error) || "Ø¯Ø±ÛŒØ§ÙØª Ú©Ù¾Ú†Ø§ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            navidSyncChallengeVisual(response.ownerStatus || navidState.ownerStatus, response.captchaDataUri);
            navidReconnectMessage("Ú©Ù¾Ú†Ø§ Ø¢Ù…Ø§Ø¯Ù‡ Ø´Ø¯. Ú©Ø¯ Ø±Ø§ ÙˆØ§Ø±Ø¯ Ú©Ù† Ùˆ Ø§ØªØµØ§Ù„ Ù…Ø¬Ø¯Ø¯ Ø±Ø§ Ø¨Ø²Ù†.", "success");
            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
        } finally {
            navidGetCaptchaButton.disabled = false;
        }
    }

    async function completeNavidReconnect() {
        if (!navidCompleteReconnectButton) {
            return;
        }

        var captchaCode = (navidCaptchaCodeInput && navidCaptchaCodeInput.value.trim()) || "";
        if (!captchaCode) {
            navidReconnectMessage("Ú©Ø¯ Ú©Ù¾Ú†Ø§ Ø±Ø§ ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }

        navidCompleteReconnectButton.disabled = true;
        navidReconnectMessage("Ø¯Ø± Ø­Ø§Ù„ Ø§ØªØµØ§Ù„ Ù…Ø¬Ø¯Ø¯ Ù†ÙˆÛŒØ¯...", "", true);
        try {
            var response = await navidPost("completeReconnect", { captchaCode: captchaCode });
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                navidReconnectMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidReconnectMessage((response && response.error) || "Ø§ØªØµØ§Ù„ Ù…Ø¬Ø¯Ø¯ Ù†ÙˆÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            navidReconnectMessage(response.message || "Ø§ØªØµØ§Ù„ Ù…Ø¬Ø¯Ø¯ Ù†ÙˆÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯.", "success");
            if (response && response.captchaDataUri && navidCaptchaImage) {
                navidCaptchaImage.hidden = false;
                navidCaptchaImage.src = response.captchaDataUri;
            }
            if (navidCaptchaCodeInput) {
                navidCaptchaCodeInput.value = "";
            }
            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
        } finally {
            navidCompleteReconnectButton.disabled = false;
        }
    }

    async function loadOwnerUsers() {
        ownerState.loading = true;
        ownerFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ú¯Ø±ÙØªÙ† ÙÙ‡Ø±Ø³Øª Ú©Ø§Ø±Ø¨Ø±Ø§Ù†...", "");
        ownerSummary.innerHTML = summaryCard("Ú©Ø§Ø±Ø¨Ø±", "â€¦", "Ø¯Ø± Ø­Ø§Ù„ Ø¨Ø§Ø±Ú¯Ø°Ø§Ø±ÛŒ Ø¯Ø§Ø¯Ù‡â€ŒÙ‡Ø§ÛŒ Ø­Ø³Ø§Ø¨â€ŒÙ‡Ø§");
        representativeList.innerHTML = '<div class="owner-empty">Ø¯Ø± Ø­Ø§Ù„ Ø®ÙˆØ§Ù†Ø¯Ù† Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡â€ŒÙ‡Ø§...</div>';
        ownerUserList.innerHTML = '<div class="owner-empty">Ø¯Ø± Ø­Ø§Ù„ Ø®ÙˆØ§Ù†Ø¯Ù† ÙÙ‡Ø±Ø³Øª Ú©Ø§Ø±Ø¨Ø±Ø§Ù†...</div>';
        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = "Ø¯Ø± Ø­Ø§Ù„ Ø¨Ø§Ø±Ú¯Ø°Ø§Ø±ÛŒ Ú©Ø§Ø±Ø¨Ø±Ø§Ù†...";
        }

        var response = await requestUsers();
        ownerState.loading = false;

        if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
            ownerFeedbackMessage("", "");
            return;
        }

        if (!response || !response.success) {
            ownerFeedbackMessage((response && response.error) || "ÙÙ‡Ø±Ø³Øª Ú©Ø§Ø±Ø¨Ø±Ø§Ù† Ú¯Ø±ÙØªÙ‡ Ù†Ø´Ø¯.", "error");
            return;
        }

        var nextUsers = Array.isArray(response.users) ? response.users : [];
        var preservedGrades = {};
        nextUsers.forEach(function (user) {
            var key = String(user.studentNumber || "");
            if (!key) return;
            if (ownerState.gradePayloadByStudent[key]) {
                preservedGrades[key] = ownerState.gradePayloadByStudent[key];
            }
        });
        ownerState.users = nextUsers;
        ownerState.gradePayloadByStudent = preservedGrades;
        if (!ownerState.users.some(function (item) { return item.studentNumber === ownerState.expandedStudentNumber; })) {
            ownerState.expandedStudentNumber = "";
        }
        ownerFeedbackMessage("", "");
        renderOwnerPanel();
    }

    async function setRepresentative(studentNumber, representative) {
        ownerState.savingStudentNumber = studentNumber;
        ownerFeedbackMessage(representative ? "Ø¯Ø± Ø­Ø§Ù„ Ø«Ø¨Øª Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡..." : "Ø¯Ø± Ø­Ø§Ù„ Ù„ØºÙˆ Ù†Ù‚Ø´ Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("setRepresentative", {
                studentNumber: studentNumber,
                representative: representative ? "1" : "0"
            });

            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "Ø°Ø®ÛŒØ±Ù‡ ØªØºÛŒÛŒØ±Ø§Øª Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            ownerState.users = ownerState.users.map(function (user) {
                return user.studentNumber === response.user.studentNumber ? Object.assign({}, user, response.user) : user;
            });

            ownerFeedbackMessage(response.message || "ØªØºÛŒÛŒØ±Ø§Øª Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingStudentNumber = "";
            renderOwnerPanel();
        }
    }

    function upsertOwnerUserRecord(nextUser) {
        if (!nextUser || typeof nextUser !== "object") {
            return;
        }
        var targetStudentNumber = String(nextUser.studentNumber || "");
        if (!targetStudentNumber) {
            return;
        }
        ownerState.users = ownerState.users.map(function (user) {
            if (String(user.studentNumber || "") !== targetStudentNumber) {
                return user;
            }
            return Object.assign({}, user, nextUser);
        });
    }

    async function loadOwnerUserGrades(studentNumber, options) {
        var opts = options && typeof options === "object" ? options : {};
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return null;
        }

        ownerState.loadingGradesStudentNumber = targetStudentNumber;
        if (!opts.silent) {
            ownerFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø¯Ø±ÛŒØ§ÙØª Ú©Ø§Ø±Ù†Ø§Ù…Ù‡ Ú©Ø§Ø±Ø¨Ø±...", "");
        }
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerUserGrades", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                if (!opts.silent) {
                    ownerFeedbackMessage("", "");
                }
                return null;
            }
            if (!response || !response.success || !response.grades) {
                ownerFeedbackMessage((response && response.error) || "Ø®ÙˆØ§Ù†Ø¯Ù† Ú©Ø§Ø±Ù†Ø§Ù…Ù‡ Ú©Ø§Ø±Ø¨Ø± Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return null;
            }

            ownerState.gradePayloadByStudent[targetStudentNumber] = response.grades;
            var hasGrades = Array.isArray(response.grades.grades) && response.grades.grades.some(function (grade) {
                return String(grade && grade.value != null ? grade.value : "").trim() !== "";
            });
            ownerState.users = ownerState.users.map(function (user) {
                if (String(user.studentNumber || "") !== targetStudentNumber) {
                    return user;
                }
                return Object.assign({}, user, { hasGrades: hasGrades });
            });
            if (!opts.silent) {
                ownerFeedbackMessage("", "");
            }
            renderOwnerPanel();
            return response.grades;
        } finally {
            ownerState.loadingGradesStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    function ownerGradeInputValue(studentNumber, columnIndex) {
        if (!ownerUserList) {
            return "";
        }
        var selector = 'input[data-grade-input="true"][data-student-number="' + String(studentNumber || "") + '"][data-column-index="' + String(columnIndex) + '"]';
        var input = ownerUserList.querySelector(selector);
        return input ? input.value.trim() : "";
    }

    async function saveOwnerUserGrade(studentNumber, columnIndex, gradeValue) {
        var targetStudentNumber = String(studentNumber || "").trim();
        var targetColumnIndex = Math.floor(toNumber(columnIndex, -1));
        if (!targetStudentNumber || targetColumnIndex < 0) {
            return;
        }

        ownerState.savingGradeKey = ownerGradeSaveKey(targetStudentNumber, targetColumnIndex);
        ownerFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡ Ù†Ù…Ø±Ù‡...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerSetUserGrade", {
                studentNumber: targetStudentNumber,
                columnIndex: String(targetColumnIndex),
                gradeValue: String(gradeValue == null ? "" : gradeValue).trim()
            });
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.grades) {
                ownerFeedbackMessage((response && response.error) || "Ø°Ø®ÛŒØ±Ù‡ Ù†Ù…Ø±Ù‡ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            ownerState.gradePayloadByStudent[targetStudentNumber] = response.grades;
            upsertOwnerUserRecord(response.user || {});
            ownerFeedbackMessage(response.message || "Ù†Ù…Ø±Ù‡ Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingGradeKey = "";
            renderUsers(ownerState.users);
        }
    }

    async function removeOwnerUserPhone(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        var targetUser = ownerState.users.find(function (item) {
            return String(item.studentNumber || "") === targetStudentNumber;
        }) || null;
        if (!targetUser || !targetUser.hasPhone || isOwnerUser(targetUser)) {
            return;
        }

        var confirmText = "Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡ Ú©Ø§Ø±Ø¨Ø± Â«" + (targetUser.name || targetStudentNumber) + "Â» Ø­Ø°Ù Ø´ÙˆØ¯ØŸ";
        if (!window.confirm(confirmText)) {
            return;
        }

        ownerState.removingPhoneStudentNumber = targetStudentNumber;
        ownerFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø­Ø°Ù Ø´Ù…Ø§Ø±Ù‡ Ø«Ø¨Øªâ€ŒØ´Ø¯Ù‡ Ú©Ø§Ø±Ø¨Ø±...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerRemoveUserPhone", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "Ø­Ø°Ù Ø´Ù…Ø§Ø±Ù‡ Ú©Ø§Ø±Ø¨Ø± Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }
            upsertOwnerUserRecord(response.user);
            ownerFeedbackMessage(response.message || "Ø´Ù…Ø§Ø±Ù‡ Ú©Ø§Ø±Ø¨Ø± Ø­Ø°Ù Ø´Ø¯.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.removingPhoneStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    async function deleteOwnerStudentAccount(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        var targetUser = ownerState.users.find(function (item) {
            return String(item.studentNumber || "") === targetStudentNumber;
        }) || null;
        if (!targetUser || isOwnerUser(targetUser)) {
            return;
        }

        var confirmText = "Ø­Ø³Ø§Ø¨ Ø¯Ø§Ù†Ø´Ø¬Ùˆ Â«" + (targetUser.name || targetStudentNumber) + "Â» Ø¨Ù‡â€ŒØ·ÙˆØ± Ú©Ø§Ù…Ù„ Ø­Ø°Ù Ø´ÙˆØ¯ØŸ Ø§ÛŒÙ† Ø¹Ù…Ù„ Ù‚Ø§Ø¨Ù„ Ø¨Ø§Ø²Ú¯Ø´Øª Ù†ÛŒØ³Øª.";
        if (!window.confirm(confirmText)) {
            return;
        }

        ownerState.deletingStudentNumber = targetStudentNumber;
        ownerFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø­Ø°Ù Ø¯Ø§Ù†Ø´Ø¬Ùˆ...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerDeleteStudent", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerFeedbackMessage((response && response.error) || "Ø­Ø°Ù Ø¯Ø§Ù†Ø´Ø¬Ùˆ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            ownerState.users = ownerState.users.filter(function (item) {
                return String(item.studentNumber || "") !== targetStudentNumber;
            });
            delete ownerState.gradePayloadByStudent[targetStudentNumber];
            if (ownerState.expandedStudentNumber === targetStudentNumber) {
                ownerState.expandedStudentNumber = "";
            }
            ownerFeedbackMessage(response.message || "Ø­Ø³Ø§Ø¨ Ø¯Ø§Ù†Ø´Ø¬Ùˆ Ø­Ø°Ù Ø´Ø¯.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.deletingStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    function toggleOwnerUserDetails(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        if (ownerState.expandedStudentNumber === targetStudentNumber) {
            ownerState.expandedStudentNumber = "";
            renderUsers(ownerState.users);
            return;
        }

        ownerState.expandedStudentNumber = targetStudentNumber;
        renderUsers(ownerState.users);
        if (!ownerGradesPayload(targetStudentNumber)) {
            loadOwnerUserGrades(targetStudentNumber, { silent: true });
        }
    }

    function setCreateStudentBusy(isBusy) {
        ownerState.creatingStudent = !!isBusy;
        if (ownerCreateStudentSubmit) {
            ownerCreateStudentSubmit.disabled = ownerState.creatingStudent;
        }
        [ownerStudentFirstName, ownerStudentLastName, ownerStudentNumber, ownerStudentPassword].forEach(function (node) {
            if (node) {
                node.disabled = ownerState.creatingStudent;
            }
        });
    }

    async function createStudentAccount(event) {
        event.preventDefault();
        if (!ownerCreateStudentForm) {
            return;
        }

        var firstName = ownerStudentFirstName ? ownerStudentFirstName.value.trim() : "";
        var lastName = ownerStudentLastName ? ownerStudentLastName.value.trim() : "";
        var studentNumber = ownerStudentNumber ? ownerStudentNumber.value.trim() : "";
        var password = ownerStudentPassword ? ownerStudentPassword.value.trim() : "";

        if (!firstName || !lastName || !studentNumber || !password) {
            ownerCreateStudentFeedbackMessage("Ù‡Ù…Ù‡ ÙÛŒÙ„Ø¯Ù‡Ø§ Ø±Ø§ Ú©Ø§Ù…Ù„ ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }

        if (password.length < 6) {
            ownerCreateStudentFeedbackMessage("Ø±Ù…Ø² Ø¹Ø¨ÙˆØ± Ø¨Ø§ÛŒØ¯ Ø­Ø¯Ø§Ù‚Ù„ Û¶ Ú©Ø§Ø±Ø§Ú©ØªØ± Ø¨Ø§Ø´Ø¯.", "error");
            return;
        }

        setCreateStudentBusy(true);
        ownerCreateStudentFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø§ÛŒØ¬Ø§Ø¯ Ø­Ø³Ø§Ø¨ Ø¯Ø§Ù†Ø´Ø¬Ùˆ...", "", true);
        try {
            var response = await request("createStudent", {
                firstName: firstName,
                lastName: lastName,
                studentNumber: studentNumber,
                password: password
            });

            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                ownerCreateStudentFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success || !response.user) {
                ownerCreateStudentFeedbackMessage((response && response.error) || "Ø§ÛŒØ¬Ø§Ø¯ Ø­Ø³Ø§Ø¨ Ø¯Ø§Ù†Ø´Ø¬Ùˆ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            if (ownerCreateStudentForm) {
                ownerCreateStudentForm.reset();
            }
            ownerCreateStudentFeedbackMessage(response.message || "Ø­Ø³Ø§Ø¨ Ø¯Ø§Ù†Ø´Ø¬Ùˆ Ø§ÛŒØ¬Ø§Ø¯ Ø´Ø¯.", "success");
            await loadOwnerUsers();
        } finally {
            setCreateStudentBusy(false);
        }
    }

    function phoneEnrollFeedbackMessage(text, kind, loading) {
        setFeedback(phoneEnrollFeedback, text, kind, loading);
    }

    function phoneToggleFeedbackMessage(text, kind, loading) {
        setFeedback(phoneToggleFeedback, text, kind, loading);
    }

    function phoneManageFeedbackMessage(text, kind, loading) {
        setFeedback(phoneManageFeedback, text, kind, loading);
    }

    function applyPhoneDetailsFromCurrentUser() {
        var user = currentUser || {};
        renderPhoneSecurityState(user);
    }

    async function requestLoginOtpCode() {
        if (!loginPhoneInput) return;
        var phoneNumber = normalizedPhone(loginPhoneInput.value);
        if (!phoneNumber) {
            setFeedback(loginOtpFeedback, "Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ù…Ø¹ØªØ¨Ø± ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }
        loginPhoneInput.value = phoneNumber;
        if (loginOtpRequestButton) loginOtpRequestButton.disabled = true;
        setFeedback(loginOtpFeedback, "Ø¯Ø± Ø­Ø§Ù„ Ø§Ø±Ø³Ø§Ù„ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯...", "", true);
        try {
            var auth = window.Dent1402Auth;
            var response = await auth.requestLoginOtp(phoneNumber);
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startLoginOtpCooldown(response.cooldownSeconds);
                }
                setFeedback(loginOtpFeedback, (response && response.error) || "Ø§Ø±Ø³Ø§Ù„ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            startLoginOtpCooldown(response.cooldownSeconds || 0);
            var masked = ltrMaskedPhone(response && response.phoneMasked, "");
            setFeedback(loginOtpFeedback, (response.message || "Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø§Ø±Ø³Ø§Ù„ Ø´Ø¯.") + (masked ? (" (" + masked + ")") : ""), "success");
            if (loginOtpCodeInput) {
                loginOtpCodeInput.focus({ preventScroll: true });
            }
        } finally {
            updateLoginOtpCooldownUi();
        }
    }

    async function submitOtpLogin(event) {
        event.preventDefault();
        if (!loginPhoneInput || !loginOtpCodeInput) return;
        var phoneNumber = normalizedPhone(loginPhoneInput.value);
        var otpCode = normalizeDigits(loginOtpCodeInput.value).replace(/\D+/g, "");
        if (!phoneNumber || !otpCode) {
            setFeedback(loginOtpFeedback, "Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ùˆ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø±Ø§ Ú©Ø§Ù…Ù„ ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }

        if (loginOtpSubmitButton) loginOtpSubmitButton.disabled = true;
        setFeedback(loginOtpFeedback, "Ø¯Ø± Ø­Ø§Ù„ ÙˆØ±ÙˆØ¯ Ø¨Ø§ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯...", "", true);
        try {
            var state = await window.Dent1402Auth.loginWithOtp(phoneNumber, otpCode);
            if (!state || !state.loggedIn) {
                setFeedback(loginOtpFeedback, (state && state.error) || "ÙˆØ±ÙˆØ¯ Ø¨Ø§ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }
            setFeedback(loginOtpFeedback, "ÙˆØ±ÙˆØ¯ Ø¨Ø§ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯.", "success");
        } finally {
            if (loginOtpSubmitButton) loginOtpSubmitButton.disabled = false;
        }
    }

    async function requestPhoneEnrollmentOtp() {
        if (!phoneEnrollNumber) return;
        var phoneNumber = normalizedPhone(phoneEnrollNumber.value);
        if (!phoneNumber) {
            phoneEnrollFeedbackMessage("Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ù…Ø¹ØªØ¨Ø± ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }
        phoneEnrollNumber.value = phoneNumber;

        phoneEnrollFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø§Ø±Ø³Ø§Ù„ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯...", "", true);
        if (phoneEnrollRequestButton) phoneEnrollRequestButton.disabled = true;
        try {
            var response = await window.Dent1402Auth.requestPhoneEnrollOtp(phoneNumber);
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                phoneEnrollFeedbackMessage("Ù†Ø´Ø³ØªØª Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ÙˆØ§Ø±Ø¯ Ø´Ùˆ.", "error");
                return;
            }
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startPhoneEnrollCooldown(response.cooldownSeconds);
                }
                phoneEnrollFeedbackMessage((response && response.error) || "Ø§Ø±Ø³Ø§Ù„ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }
            startPhoneEnrollCooldown(response.cooldownSeconds || 0);
            var masked = ltrMaskedPhone(response && response.phoneMasked, "");
            phoneEnrollFeedbackMessage((response.message || "Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø§Ø±Ø³Ø§Ù„ Ø´Ø¯.") + (masked ? (" (" + masked + ")") : ""), "success");
            if (phoneEnrollCode) {
                phoneEnrollCode.focus({ preventScroll: true });
            }
        } finally {
            updatePhoneEnrollCooldownUi();
        }
    }

    async function verifyPhoneEnrollment() {
        if (!phoneEnrollNumber || !phoneEnrollCode) return;
        var phoneNumber = normalizedPhone(phoneEnrollNumber.value);
        var otpCode = normalizeDigits(phoneEnrollCode.value).replace(/\D+/g, "");
        if (!phoneNumber || !otpCode) {
            phoneEnrollFeedbackMessage("Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ùˆ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø±Ø§ Ú©Ø§Ù…Ù„ ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }

        if (phoneEnrollSubmitButton) phoneEnrollSubmitButton.disabled = true;
        phoneEnrollFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ ØªØ§ÛŒÛŒØ¯ Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„...", "", true);
        try {
            var response = await window.Dent1402Auth.verifyPhoneEnrollOtp(phoneNumber, otpCode);
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                phoneEnrollFeedbackMessage("Ù†Ø´Ø³ØªØª Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ÙˆØ§Ø±Ø¯ Ø´Ùˆ.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneEnrollFeedbackMessage((response && response.error) || "ØªØ§ÛŒÛŒØ¯ Ø´Ù…Ø§Ø±Ù‡ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            if (phoneEnrollCode) {
                phoneEnrollCode.value = "";
            }
            phoneEnrollFeedbackMessage(response.message || "Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ ØªØ§ÛŒÛŒØ¯ Ø´Ø¯.", "success");
            phoneToggleFeedbackMessage("", "");
        } finally {
            if (phoneEnrollSubmitButton) phoneEnrollSubmitButton.disabled = false;
        }
    }

    async function savePhoneLoginToggle() {
        if (!phoneLoginEnabledInput) return;
        var enabled = !!phoneLoginEnabledInput.checked;
        if (phoneLoginSaveButton) phoneLoginSaveButton.disabled = true;
        phoneToggleFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡ ÙˆØ¶Ø¹ÛŒØª ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ...", "", true);
        try {
            var response = await window.Dent1402Auth.setPhoneLoginEnabled(enabled);
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                phoneToggleFeedbackMessage("Ù†Ø´Ø³ØªØª Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ÙˆØ§Ø±Ø¯ Ø´Ùˆ.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneToggleFeedbackMessage((response && response.error) || "Ø°Ø®ÛŒØ±Ù‡ ÙˆØ¶Ø¹ÛŒØª ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            phoneToggleFeedbackMessage(response.message || "ÙˆØ¶Ø¹ÛŒØª ÙˆØ±ÙˆØ¯ Ù¾ÛŒØ§Ù…Ú©ÛŒ Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.", "success");
        } finally {
            if (phoneLoginSaveButton) phoneLoginSaveButton.disabled = false;
        }
    }

    function startPhoneNumberEdit() {
        if (!phoneEnrollNumber) {
            return;
        }
        phoneEnrollNumber.focus({ preventScroll: true });
        phoneEnrollNumber.select();
        phoneManageFeedbackMessage("Ø´Ù…Ø§Ø±Ù‡ Ø¬Ø¯ÛŒØ¯ Ø±Ø§ ÙˆØ§Ø±Ø¯ Ú©Ù†ØŒ Ú©Ø¯ ØªØ§ÛŒÛŒØ¯ Ø¨Ú¯ÛŒØ± Ùˆ Ø«Ø¨Øª Ú©Ù†.", "success");
    }

    async function removePhoneNumber() {
        if (!currentUser) {
            return;
        }

        var phone = parsedPhone(currentUser);
        if (!phone.hasNumber) {
            phoneManageFeedbackMessage("Ø´Ù…Ø§Ø±Ù‡â€ŒØ§ÛŒ Ø¨Ø±Ø§ÛŒ Ø­Ø°Ù Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.", "error");
            return;
        }

        var masked = ltrMaskedPhone(phone.numberMasked, "Ø´Ù…Ø§Ø±Ù‡ ÙØ¹Ù„ÛŒ");
        var confirmed = window.confirm("Ø´Ù…Ø§Ø±Ù‡ " + masked + " Ø§Ø² Ø§ÛŒÙ† Ø­Ø³Ø§Ø¨ Ø­Ø°Ù Ø´ÙˆØ¯ØŸ");
        if (!confirmed) {
            return;
        }

        if (phoneNumberRemoveButton) {
            phoneNumberRemoveButton.disabled = true;
        }
        phoneManageFeedbackMessage("Ø¯Ø± Ø­Ø§Ù„ Ø­Ø°Ù Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„...", "", true);
        try {
            var response = await window.Dent1402Auth.removePhoneNumber();
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                phoneManageFeedbackMessage("Ù†Ø´Ø³ØªØª Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ÙˆØ§Ø±Ø¯ Ø´Ùˆ.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneManageFeedbackMessage((response && response.error) || "Ø­Ø°Ù Ø´Ù…Ø§Ø±Ù‡ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            if (phoneEnrollNumber) {
                phoneEnrollNumber.value = "";
            }
            if (phoneEnrollCode) {
                phoneEnrollCode.value = "";
                phoneEnrollCode.dispatchEvent(new Event("input", { bubbles: true }));
            }
            phoneEnrollFeedbackMessage("", "");
            phoneToggleFeedbackMessage("", "");
            phoneManageFeedbackMessage(response.message || "Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ Ø­Ø°Ù Ø´Ø¯.", "success");
        } finally {
            if (phoneNumberRemoveButton) {
                phoneNumberRemoveButton.disabled = false;
            }
        }
    }

    async function dismissPhoneSetupNudge() {
        if (!currentUser) return;
        if (accountPhoneNudgeDismiss) accountPhoneNudgeDismiss.disabled = true;
        try {
            var response = await window.Dent1402Auth.dismissPhoneNudge();
            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                return;
            }
            if (!response || !response.success || !response.user) {
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
        } finally {
            if (accountPhoneNudgeDismiss) accountPhoneNudgeDismiss.disabled = false;
        }
    }

    function maybeRedirectAfterLogin(user) {
        if (!pendingReturnTo || redirectedAfterLogin) {
            return;
        }

        if (!user) {
            return;
        }

        redirectedAfterLogin = true;
        setBootText("ÙˆØ±ÙˆØ¯ Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯ØŒ Ø¯Ø± Ø­Ø§Ù„ Ø¨Ø§Ø²Ú¯Ø´Øª...");
        showStage("boot");

        window.setTimeout(function () {
            window.location.href = pendingReturnTo;
        }, 520);
    }

    function handleAuthState(detail) {
        if (detail.status === "session-restoring") {
            setBootText("Ø¯Ø± Ø­Ø§Ù„ Ø¨Ø§Ø²ÛŒØ§Ø¨ÛŒ Ù†Ø´Ø³Øª...");
            showStage("boot");
            return;
        }

        if (detail.status === "logging-in") {
            setFeedback(loginFeedback, "Ø¯Ø± Ø­Ø§Ù„ ÙˆØ±ÙˆØ¯...", "", true);
            showStage("login");
            return;
        }

        if (detail.status === "logging-out") {
            setBootText("Ø¯Ø± Ø­Ø§Ù„ Ø®Ø±ÙˆØ¬ Ø§Ø² Ø­Ø³Ø§Ø¨...");
            showStage("boot");
            return;
        }

        if (!detail.loggedIn) {
            currentUser = null;
            showStage("login");
            openSurface("hub", { replaceHash: true, preserveScroll: true });
            resetOtpUi();
            profileDraftAvatarUrl = "";
            updateIdentityAvatars("");
            if (accountRotation) {
                accountRotation.hidden = true;
                accountRotation.textContent = "";
            }
            if (accountInfoRole) {
                accountInfoRole.textContent = "â€”";
            }
            if (accountInfoSession) {
                accountInfoSession.textContent = "â€”";
            }
            if (accountInfoRotation) {
                accountInfoRotation.textContent = "â€”";
            }
            if (accountRowProfileMeta) {
                accountRowProfileMeta.textContent = "ÙˆÛŒØ±Ø§ÛŒØ´ Ø¢ÙˆØ§ØªØ§Ø±ØŒ Ø¨ÛŒÙˆ Ùˆ Ø±Ø§Ù‡ Ø§Ø±ØªØ¨Ø§Ø·ÛŒ";
            }
            if (accountRowInfoMeta) {
                accountRowInfoMeta.textContent = "Ù†Ø§Ù…ØŒ Ø´Ù…Ø§Ø±Ù‡ Ø¯Ø§Ù†Ø´Ø¬ÙˆÛŒÛŒ Ùˆ Ù†ÙˆØ¹ Ø¯Ø³ØªØ±Ø³ÛŒ";
            }
            if (accountRowOwnerMeta) {
                accountRowOwnerMeta.textContent = "Ù…Ø¯ÛŒØ±ÛŒØª Ú©Ø§Ø±Ø¨Ø±Ø§Ù†ØŒ Ù†Ù…Ø§ÛŒÙ†Ø¯Ù‡â€ŒÙ‡Ø§ Ùˆ Ø§ÛŒØ¬Ø§Ø¯ Ø¯Ø§Ù†Ø´Ø¬Ùˆ";
            }
            if (accountRowNavidMeta) {
                accountRowNavidMeta.textContent = "ÙˆØ¶Ø¹ÛŒØª Ø§ØªØµØ§Ù„ Ùˆ Ù‡Ù…Ú¯Ø§Ù…â€ŒØ³Ø§Ø²ÛŒ Ù†ÙˆÛŒØ¯";
            }
            if (accountRowPollsMeta) {
                accountRowPollsMeta.textContent = "\u062f\u0633\u062a\u0631\u0633\u06cc \u0645\u062f\u06cc\u0631\u06cc\u062a \u0646\u0638\u0631\u0633\u0646\u062c\u06cc \u0628\u0631\u0627\u06cc \u0645\u0627\u0644\u06a9/\u0646\u0645\u0627\u06cc\u0646\u062f\u0647 \u0641\u0639\u0627\u0644 \u0627\u0633\u062a.";
            }
            if (accountRowPhoneMeta) {
                accountRowPhoneMeta.textContent = "Ø«Ø¨Øª Ø´Ù…Ø§Ø±Ù‡ Ù…ÙˆØ¨Ø§ÛŒÙ„ØŒ ØªØ§ÛŒÛŒØ¯ Ø¨Ø§ OTP Ùˆ ÙØ¹Ø§Ù„â€ŒØ³Ø§Ø²ÛŒ Ù…Ø³ÛŒØ± Ø¯ÙˆÙ… ÙˆØ±ÙˆØ¯";
            }
            if (ownerHubSection) {
                ownerHubSection.hidden = true;
            }
            ownerState.users = [];
            ownerState.expandedStudentNumber = "";
            ownerState.gradePayloadByStudent = {};
            ownerState.savingStudentNumber = "";
            ownerState.deletingStudentNumber = "";
            ownerState.removingPhoneStudentNumber = "";
            ownerState.loadingGradesStudentNumber = "";
            ownerState.savingGradeKey = "";
            if (pollManagerHubSection) {
                pollManagerHubSection.hidden = true;
            }
            resetActivePollShortcut();
            if (accountPhoneNudge) {
                accountPhoneNudge.hidden = true;
            }
            if (phoneStatusSummary) {
                phoneStatusSummary.textContent = "â€”";
            }
            if (phoneLoginEnabledInput) {
                phoneLoginEnabledInput.checked = false;
                phoneLoginEnabledInput.disabled = true;
            }
            phoneEnrollFeedbackMessage("", "");
            phoneToggleFeedbackMessage("", "");
            phoneManageFeedbackMessage("", "");
            setFeedback(loginOtpFeedback, "", "");
            setLoginMode(loginMode);
            setInlineFeedback(profileAvatarFeedback, "", "");
            setProfileBusy(false);
            if (detail.status === "login-error" || detail.status === "unauthorized") {
                setFeedback(loginFeedback, detail.error || "ÙˆØ±ÙˆØ¯ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
            } else {
                setFeedback(loginFeedback, "", "");
            }
            return;
        }

        currentUser = detail.user;
        renderIdentity(detail.user);
        applyPhoneDetailsFromCurrentUser();
        showStage("panel");
        openSurface(surfaceFromHash(), { replaceHash: true, preserveScroll: true });
        setProfileBusy(false);
        setFeedback(profileFeedback, "", "");
        setFeedback(securityFeedback, "", "");

        if (pollManagerHubSection) {
            pollManagerHubSection.hidden = !hasPollManagementAccess(detail.user);
        }
        loadActivePollShortcut(detail.user);

        if (detail.user.isOwner) {
            if (ownerHubSection) {
                ownerHubSection.hidden = false;
            }
            if (!ownerState.users.length && !ownerState.loading) {
                loadOwnerUsers();
            } else {
                renderOwnerPanel();
            }
            loadOwnerSmsStatus();
            loadOwnerMediaStatus();
            if (!navidState.loaded && !navidState.loading) {
                loadNavidOwnerStatus();
            } else {
                navidRenderOwnerStatus(navidState.ownerStatus);
            }
        } else {
            if (ownerHubSection) {
                ownerHubSection.hidden = true;
            }
            ownerState.users = [];
            ownerState.expandedStudentNumber = "";
            ownerState.gradePayloadByStudent = {};
            ownerState.savingStudentNumber = "";
            ownerState.deletingStudentNumber = "";
            ownerState.removingPhoneStudentNumber = "";
            ownerState.loadingGradesStudentNumber = "";
            ownerState.savingGradeKey = "";
            setCreateStudentBusy(false);
            ownerCreateStudentFeedbackMessage("", "");
            navidState.loaded = false;
            navidState.ownerStatus = null;
            navidRenderOwnerStatus(null);
            navidFeedbackMessage("", "");
            navidReconnectMessage("", "");
            smsState.status = null;
            mediaState.status = null;
            renderOwnerSmsStatus(null);
            renderOwnerMediaStatus(null);
            ownerSmsFeedbackMessage("", "");
            ownerMediaFeedbackMessage("", "");
            if (activeSurface === "owner" || activeSurface === "navid") {
                openSurface("hub", { replaceHash: true, preserveScroll: true });
            }
        }

        maybeRedirectAfterLogin(detail.user);
    }

    surfaceOpeners.forEach(function (node) {
        node.addEventListener("click", function (event) {
            var target = normalizeSurfaceName(node.dataset.openSurface);
            if (target === "hub") {
                return;
            }

            event.preventDefault();
            if (!canOpenSurface(target)) {
                return;
            }
            openSurface(target, { replaceHash: false });
        });
    });

    surfaceBackButtons.forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            openSurface("hub", { replaceHash: true });
        });
    });

    window.addEventListener("hashchange", function () {
        if (!stagePanel || stagePanel.hidden) {
            return;
        }
        openSurface(surfaceFromHash(), { syncHash: false, preserveScroll: true });
    });

    bindNumericInput(loginPhoneInput, 14);
    bindNumericInput(phoneEnrollNumber, 14);
    bindNumericInput(ownerSmsTestPhone, 14);
    bindNumericInput(loginOtpCodeInput, 6);
    bindNumericInput(phoneEnrollCode, 6);
    applyOtpSlots(loginOtpCodeInput, loginOtpSlots);
    applyOtpSlots(phoneEnrollCode, phoneEnrollOtpSlots);

    if (loginMethodPasswordBtn) {
        loginMethodPasswordBtn.addEventListener("click", function () {
            setLoginMode("password");
            setFeedback(loginOtpFeedback, "", "");
        });
    }

    if (loginMethodOtpBtn) {
        loginMethodOtpBtn.addEventListener("click", function () {
            setLoginMode("otp");
            setFeedback(loginFeedback, "", "");
            if (loginPhoneInput) {
                loginPhoneInput.focus({ preventScroll: true });
            }
        });
    }

    if (loginOtpRequestButton) {
        loginOtpRequestButton.addEventListener("click", requestLoginOtpCode);
    }

    if (loginOtpForm) {
        loginOtpForm.addEventListener("submit", submitOtpLogin);
    }

    if (phoneEnrollRequestButton) {
        phoneEnrollRequestButton.addEventListener("click", requestPhoneEnrollmentOtp);
    }

    if (phoneEnrollSubmitButton) {
        phoneEnrollSubmitButton.addEventListener("click", verifyPhoneEnrollment);
    }

    if (phoneLoginSaveButton) {
        phoneLoginSaveButton.addEventListener("click", savePhoneLoginToggle);
    }

    if (phoneNumberEditButton) {
        phoneNumberEditButton.addEventListener("click", function (event) {
            event.preventDefault();
            startPhoneNumberEdit();
        });
    }

    if (phoneNumberRemoveButton) {
        phoneNumberRemoveButton.addEventListener("click", function (event) {
            event.preventDefault();
            removePhoneNumber();
        });
    }

    if (accountPhoneNudgeOpen) {
        accountPhoneNudgeOpen.addEventListener("click", function (event) {
            event.preventDefault();
            openSurface("phone", { replaceHash: false });
        });
    }

    if (accountPhoneNudgeDismiss) {
        accountPhoneNudgeDismiss.addEventListener("click", function (event) {
            event.preventDefault();
            dismissPhoneSetupNudge();
        });
    }

    if (ownerSmsForm) {
        ownerSmsForm.addEventListener("submit", saveOwnerSmsConfig);
    }

    if (ownerSmsHealthButton) {
        ownerSmsHealthButton.addEventListener("click", runOwnerSmsHealthCheck);
    }

    if (ownerMediaRefreshButton) {
        ownerMediaRefreshButton.addEventListener("click", loadOwnerMediaStatus);
    }

    if (ownerMediaCleanupButton) {
        ownerMediaCleanupButton.addEventListener("click", runOwnerMediaCleanup);
    }

    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();

        var studentNumber = $("login-student-number").value.trim();
        var password = $("login-password").value.trim();

        if (!studentNumber || !password) {
            setFeedback(loginFeedback, "Ø´Ù…Ø§Ø±Ù‡ Ø¯Ø§Ù†Ø´Ø¬ÙˆÛŒÛŒ Ùˆ Ø±Ù…Ø² Ø¹Ø¨ÙˆØ± Ø±Ø§ Ú©Ø§Ù…Ù„ ÙˆØ§Ø±Ø¯ Ú©Ù†.", "error");
            return;
        }

        loginSubmit.disabled = true;
        window.Dent1402Auth.login(studentNumber, password).finally(function () {
            loginSubmit.disabled = false;
        });
    });

    if (profileAvatarFile) {
        profileAvatarFile.addEventListener("change", function () {
            var file = profileAvatarFile.files && profileAvatarFile.files[0];
            if (!file) {
                return;
            }

            if (file.size > (12 * 1024 * 1024)) {
                setInlineFeedback(profileAvatarFeedback, "Ø­Ø¬Ù… ÙØ§ÛŒÙ„ Ø²ÛŒØ§Ø¯ Ø§Ø³Øª. ÛŒÚ© Ø¹Ú©Ø³ Ú©ÙˆÚ†Ú©â€ŒØªØ± Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†.", "error");
                profileAvatarFile.value = "";
                return;
            }

            setProfileAvatarProcessing(true);
            setInlineFeedback(profileAvatarFeedback, "Ø¯Ø± Ø­Ø§Ù„ Ø¢Ù…Ø§Ø¯Ù‡â€ŒØ³Ø§Ø²ÛŒ Ø¹Ú©Ø³...", "", true);
            imageFileToAvatarDataUrl(file).then(function (avatarDataUrl) {
                profileDraftAvatarUrl = avatarDataUrl;
                updateIdentityAvatars($("profile-name").value || $("account-name").textContent || "");
                setInlineFeedback(profileAvatarFeedback, "Ø¹Ú©Ø³ Ø¢Ù…Ø§Ø¯Ù‡ Ø´Ø¯. Ø¨Ø±Ø§ÛŒ Ø«Ø¨Øª Ù†Ù‡Ø§ÛŒÛŒØŒ Ø°Ø®ÛŒØ±Ù‡ ØªØºÛŒÛŒØ±Ø§Øª Ø±Ø§ Ø¨Ø²Ù†.", "success");
            }).catch(function (error) {
                if (error && error.message === "avatar-too-large") {
                    setInlineFeedback(profileAvatarFeedback, "Ø­Ø¬Ù… Ø¹Ú©Ø³ Ù†Ù‡Ø§ÛŒÛŒ Ø¨ÛŒØ´ØªØ± Ø§Ø² Ø­Ø¯ Ù…Ø¬Ø§Ø² Ø§Ø³Øª. Ø¹Ú©Ø³ Ø³Ø§Ø¯Ù‡â€ŒØªØ±ÛŒ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†.", "error");
                    return;
                }
                setInlineFeedback(profileAvatarFeedback, "Ø®ÙˆØ§Ù†Ø¯Ù† Ø¹Ú©Ø³ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ØªÙ„Ø§Ø´ Ú©Ù†.", "error");
            }).finally(function () {
                setProfileAvatarProcessing(false);
                profileAvatarFile.value = "";
            });
        });
    }

    if (profileAvatarClear) {
        profileAvatarClear.addEventListener("click", function () {
            if (!profileDraftAvatarUrl) {
                return;
            }

            profileDraftAvatarUrl = "";
            updateIdentityAvatars($("profile-name").value || $("account-name").textContent || "");
            setInlineFeedback(profileAvatarFeedback, "Ø¹Ú©Ø³ Ù¾Ø±ÙˆÙØ§ÛŒÙ„ Ø­Ø°Ù Ø´Ø¯. Ø¨Ø±Ø§ÛŒ Ø«Ø¨Øª Ù†Ù‡Ø§ÛŒÛŒØŒ Ø°Ø®ÛŒØ±Ù‡ ØªØºÛŒÛŒØ±Ø§Øª Ø±Ø§ Ø¨Ø²Ù†.", "success");
        });
    }

    profileForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        setProfileBusy(true);
        setFeedback(profileFeedback, "Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡ Ù¾Ø±ÙˆÙØ§ÛŒÙ„...", "", true);

        try {
            var response = await request("updateProfile", {
                about: $("profile-about").value.trim(),
                contactHandle: $("profile-contact-handle").value.trim(),
                focusArea: $("profile-focus-area").value.trim(),
                avatarUrl: profileDraftAvatarUrl
            });

            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                setFeedback(profileFeedback, "Ù†Ø´Ø³ØªØª Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ÙˆØ§Ø±Ø¯ Ø´Ùˆ.", "error");
                return;
            }

            if (!response || !response.success || !response.user) {
                setFeedback(profileFeedback, (response && response.error) || "Ø°Ø®ÛŒØ±Ù‡ Ù¾Ø±ÙˆÙØ§ÛŒÙ„ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            if (typeof window.Dent1402Auth.patchCurrentUser === "function") {
                window.Dent1402Auth.patchCurrentUser(response.user);
            } else {
                window.Dent1402Auth.bootstrap(true);
            }
            setFeedback(profileFeedback, response.message || "Ù¾Ø±ÙˆÙØ§ÛŒÙ„ Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.", "success");
            setInlineFeedback(profileAvatarFeedback, "", "");
        } finally {
            setProfileBusy(false);
        }
    });

    securityForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        var currentPassword = $("security-current-password").value.trim();
        var newPassword = $("security-new-password").value.trim();
        var confirmPassword = $("security-confirm-password").value.trim();

        if (!currentPassword || !newPassword || !confirmPassword) {
            setFeedback(securityFeedback, "Ù‡Ù…Ù‡ ÙÛŒÙ„Ø¯Ù‡Ø§ÛŒ Ø±Ù…Ø² Ø±Ø§ Ú©Ø§Ù…Ù„ Ú©Ù†.", "error");
            return;
        }

        if (newPassword !== confirmPassword) {
            setFeedback(securityFeedback, "Ø±Ù…Ø² Ø¬Ø¯ÛŒØ¯ Ùˆ ØªÚ©Ø±Ø§Ø±Ø´ ÛŒÚ©Ø³Ø§Ù† Ù†ÛŒØ³Øª.", "error");
            return;
        }

        securitySubmit.disabled = true;
        setFeedback(securityFeedback, "Ø¯Ø± Ø­Ø§Ù„ ØªØºÛŒÛŒØ± Ø±Ù…Ø²...", "", true);

        try {
            var response = await request("changePassword", {
                currentPassword: currentPassword,
                newPassword: newPassword
            });

            if (consumeUnauthorized(response, "Ù†Ø´Ø³Øª Ø´Ù…Ø§ Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯Ù‡ Ø§Ø³Øª.")) {
                setFeedback(securityFeedback, "Ù†Ø´Ø³ØªØª Ù…Ù†Ù‚Ø¶ÛŒ Ø´Ø¯. Ø¯ÙˆØ¨Ø§Ø±Ù‡ ÙˆØ§Ø±Ø¯ Ø´Ùˆ.", "error");
                return;
            }

            if (!response || !response.success) {
                setFeedback(securityFeedback, (response && response.error) || "ØªØºÛŒÛŒØ± Ø±Ù…Ø² Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.", "error");
                return;
            }

            $("security-current-password").value = "";
            $("security-new-password").value = "";
            $("security-confirm-password").value = "";
            setFeedback(securityFeedback, response.message || "Ø±Ù…Ø² Ø¹Ø¨ÙˆØ± ØªØºÛŒÛŒØ± Ú©Ø±Ø¯.", "success");
        } finally {
            securitySubmit.disabled = false;
        }
    });

    logoutSubmit.addEventListener("click", function () {
        logoutSubmit.disabled = true;
        window.Dent1402Auth.logout().finally(function () {
            logoutSubmit.disabled = false;
        });
    });

    if (ownerSearch) {
        ownerSearch.addEventListener("input", function () {
            renderUsers(ownerState.users);
        });
    }

    if (ownerUserList) {
        ownerUserList.addEventListener("click", function (event) {
            var button = event.target.closest("button[data-owner-action]");
            if (!button) {
                return;
            }

            var action = String(button.dataset.ownerAction || "");
            var studentNumber = String(button.dataset.studentNumber || "");
            var user = ownerState.users.find(function (item) {
                return item.studentNumber === studentNumber;
            });

            if (!user) {
                return;
            }

            if (action === "toggle-representative") {
                if (user.role === "owner") {
                    return;
                }
                setRepresentative(studentNumber, user.role !== "representative");
                return;
            }

            if (action === "toggle-details") {
                toggleOwnerUserDetails(studentNumber);
                return;
            }

            if (action === "reload-grades") {
                loadOwnerUserGrades(studentNumber);
                return;
            }

            if (action === "save-grade") {
                var columnIndex = Math.floor(toNumber(button.dataset.columnIndex, -1));
                if (columnIndex < 0) {
                    return;
                }
                var gradeValue = ownerGradeInputValue(studentNumber, columnIndex);
                saveOwnerUserGrade(studentNumber, columnIndex, gradeValue);
                return;
            }

            if (action === "remove-phone") {
                removeOwnerUserPhone(studentNumber);
                return;
            }

            if (action === "delete-student") {
                deleteOwnerStudentAccount(studentNumber);
            }
        });

        ownerUserList.addEventListener("keydown", function (event) {
            if (event.key !== "Enter") {
                return;
            }
            var input = event.target && event.target.closest
                ? event.target.closest('input[data-grade-input="true"]')
                : null;
            if (!input) {
                return;
            }

            event.preventDefault();
            var studentNumber = String(input.dataset.studentNumber || "");
            var columnIndex = Math.floor(toNumber(input.dataset.columnIndex, -1));
            if (!studentNumber || columnIndex < 0) {
                return;
            }
            saveOwnerUserGrade(studentNumber, columnIndex, input.value.trim());
        });
    }

    if (ownerCreateStudentForm) {
        ownerCreateStudentForm.addEventListener("submit", createStudentAccount);
    }

    if (navidConfigForm) {
        navidConfigForm.addEventListener("submit", saveNavidConfig);
    }

    if (navidSyncNowButton) {
        navidSyncNowButton.addEventListener("click", syncNavidNow);
    }

    if (navidGetCaptchaButton) {
        navidGetCaptchaButton.addEventListener("click", loadNavidCaptchaChallenge);
    }

    if (navidCompleteReconnectButton) {
        navidCompleteReconnectButton.addEventListener("click", completeNavidReconnect);
    }

    if (navidCaptchaCodeInput) {
        navidCaptchaCodeInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                completeNavidReconnect();
            }
        });
    }

    setLoginMode("password");
    resetOtpUi();
    window.Dent1402Auth.onChange(handleAuthState);
})();
