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
    var ownerState = {
        loading: false,
        savingStudentNumber: "",
        creatingStudent: false,
        users: []
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
            .replace(/[۰-۹]/g, function (ch) {
                return String("۰۱۲۳۴۵۶۷۸۹".indexOf(ch));
            })
            .replace(/[٠-٩]/g, function (ch) {
                return String("٠١٢٣٤٥٦٧٨٩".indexOf(ch));
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
        if (status === "ok") return "سالم";
        if (status === "error") return "خطادار";
        if (status === "unknown") return "نامشخص";
        return status || "نامشخص";
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
        var maskedPhoneLabel = ltrMaskedPhone(maskedPhone, "شماره ثبت‌شده");
        var badgeText = "نیاز به ثبت شماره";
        var badgeState = "warn";
        var summary = "شماره‌ای برای این حساب ثبت نشده است. برای فعال‌سازی ورود پیامکی، شماره را ثبت و تایید کن.";

        if (phone.hasNumber && !phone.verified) {
            badgeText = "در انتظار تایید";
            badgeState = "warn";
            summary = "شماره موبایل ثبت شده ولی هنوز با کد پیامکی تایید نشده است.";
        } else if (phone.hasNumber && phone.verified && !phone.otpLoginEnabled) {
            badgeText = "شماره تایید شده";
            badgeState = "ok";
            summary = "شماره " + maskedPhoneLabel + " تایید شده است؛ ورود با کد تایید هنوز غیرفعال است.";
        } else if (phone.hasNumber && phone.verified && phone.otpLoginEnabled) {
            badgeText = "ورود پیامکی فعال";
            badgeState = "ok";
            summary = "ورود با کد تایید برای " + maskedPhoneLabel + " فعال است.";
        }

        if (phoneStatusBadge) {
            phoneStatusBadge.textContent = badgeText;
            phoneStatusBadge.className = "phone-status-badge is-" + badgeState;
        }
        if (phoneStatusSummary) {
            phoneStatusSummary.textContent = summary;
        }
        setPhonePill(phoneNumberState, phone.hasNumber ? ("شماره " + maskedPhoneLabel) : "شماره ثبت نشده", phone.hasNumber ? "ok" : "warn");
        setPhonePill(phoneVerifyState, phone.verified ? "تایید شده" : "تایید نشده", phone.verified ? "ok" : "warn");
        setPhonePill(phoneLoginState, phone.otpLoginEnabled ? "ورود پیامکی فعال" : "ورود پیامکی غیرفعال", phone.otpLoginEnabled ? "ok" : "warn");

        if (phoneLoginEnabledInput) {
            phoneLoginEnabledInput.checked = !!phone.otpLoginEnabled;
            phoneLoginEnabledInput.disabled = !phone.hasNumber || !phone.verified;
        }
        if (phoneLoginSaveButton) {
            phoneLoginSaveButton.disabled = !phone.hasNumber || !phone.verified;
        }
        if (phoneLoginToggleHint) {
            phoneLoginToggleHint.textContent = phone.hasNumber && phone.verified
                ? "می‌توانی ورود پیامکی را برای همین شماره روشن یا خاموش کنی."
                : "برای فعال‌سازی، ابتدا شماره را با کد پیامکی تایید کن.";
        }
        if (phoneCurrentNumber) {
            phoneCurrentNumber.textContent = phone.hasNumber ? ltrMaskedPhone(maskedPhone, "—") : "—";
        }
        if (phoneCurrentCaption) {
            phoneCurrentCaption.textContent = phone.hasNumber
                ? "برای تغییر شماره، شماره جدید را وارد کن و دوباره تایید بگیر."
                : "هنوز شماره‌ای ثبت نشده است. از کارت پایین برای ثبت شماره استفاده کن.";
        }
        if (phoneNumberEditButton) {
            phoneNumberEditButton.textContent = phone.hasNumber ? "تغییر شماره" : "ثبت شماره";
        }
        if (phoneNumberRemoveButton) {
            phoneNumberRemoveButton.disabled = !phone.hasNumber;
        }
        if (phoneEnrollNumber && !phoneEnrollNumber.value) {
            phoneEnrollNumber.placeholder = "9xxxxxxxxx یا 09xxxxxxxxx";
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
                ? ("ارسال مجدد تا " + formatSeconds(left) + " دیگر")
                : "ورود پیامکی فقط برای شماره تاییدشده و فعال‌شده امکان دارد.";
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
                ? ("ارسال مجدد تا " + formatSeconds(left) + " دیگر")
                : "بعد از ارسال، امکان ارسال دوباره با زمان‌سنج فعال می‌شود.";
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
            return "؟";
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
        imageNode.alt = label ? ("تصویر پروفایل " + label) : "تصویر پروفایل";
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
                    error: "پاسخ نامعتبر از سرور دریافت شد."
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
                    error: "پاسخ نامعتبر از سرور دریافت شد."
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
                    error: "پاسخ نامعتبر از سرور دریافت شد."
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
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
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

        var message = fallbackText || "نشست شما منقضی شده است.";
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

    function renderIdentity(user) {
        var roleLabel = user.roleLabel || "دانشجو";
        var sessionLabel = user.isOwner ? "دسترسی مالک فعال" : (user.canModerateChat ? "دسترسی نماینده فعال" : "نشست فعال");
        var profile = user.profile && typeof user.profile === "object" ? user.profile : {};
        var aboutText = profileAbout(profile);
        var focusText = profile.focusArea || "";
        var contactText = profile.contactHandle || "";

        $("account-role-eyebrow").textContent = roleLabel;
        $("account-name").textContent = user.name || "دانشجو";
        $("account-student-number").textContent = "شماره دانشجویی: " + (user.studentNumber || "-");
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
            accountRowProfileMeta.textContent = aboutText || focusText || contactText || "ویرایش آواتار، بیو و راه ارتباطی";
        }
        if (accountRowInfoMeta) {
            accountRowInfoMeta.textContent = [user.studentNumber || "-", roleLabel].join(" • ");
        }

        var phone = parsedPhone(user);
        var phoneLabel = ltrMaskedPhone(phone.numberMasked, "شماره ثبت‌شده");
        if (accountRowPhoneMeta) {
            if (!phone.hasNumber) {
                accountRowPhoneMeta.textContent = "هنوز شماره‌ای ثبت نشده است.";
            } else if (!phone.verified) {
                accountRowPhoneMeta.textContent = "شماره " + phoneLabel + " ثبت شده ولی هنوز تایید نشده است.";
            } else if (phone.otpLoginEnabled) {
                accountRowPhoneMeta.textContent = "ورود با کد تایید فعال است (" + phoneLabel + ").";
            } else {
                accountRowPhoneMeta.textContent = "شماره " + phoneLabel + " تایید شده است ولی ورود پیامکی غیرفعال است.";
            }
        }
        renderPhoneSecurityState(user);

        if (accountRotation) {
            var rotation = user.rotation && typeof user.rotation === "object" ? user.rotation : null;
            var rotationSummary = rotation && rotation.assigned ? String(rotation.summary || "").trim() : "";
            if (rotationSummary) {
                accountRotation.hidden = false;
                accountRotation.textContent = "روتیشن/گروه: " + rotationSummary;
                if (accountInfoRotation) {
                    accountInfoRotation.textContent = rotationSummary;
                }
            } else {
                accountRotation.hidden = true;
                accountRotation.textContent = "";
                if (accountInfoRotation) {
                    accountInfoRotation.textContent = "—";
                }
            }
        } else if (accountInfoRotation) {
            accountInfoRotation.textContent = "—";
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

        ownerSummary.innerHTML = [
            summaryCard("کاربر", totalUsers.toLocaleString("fa-IR"), "کل حساب‌های تعریف‌شده"),
            summaryCard("نماینده", representatives.toLocaleString("fa-IR"), "افراد دارای دسترسی گفت‌وگو"),
            summaryCard("دارای نمره", withGrades.toLocaleString("fa-IR"), "کاربرهایی که در فایل نمرات رکورد دارند")
        ].join("");

        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = [
                "کاربر " + totalUsers.toLocaleString("fa-IR"),
                "نماینده " + representatives.toLocaleString("fa-IR")
            ].join(" • ");
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
        if (!s.enabled) missing.push("فعال‌سازی سرویس");
        if (!s.apiKeyConfigured) missing.push("API Key");
        if (!s.patternConfigured) missing.push("Pattern Code");
        if (!s.senderLineConfigured) missing.push("لاین ارسال");

        var healthLabel = smsHealthStatusLabel(s.lastHealthStatus || "");
        var healthTone = healthLabel === "سالم" ? "ok" : (healthLabel === "خطادار" ? "danger" : "warn");
        var readinessMeta = ready
            ? "تنظیمات پایه برای ارسال OTP کامل است."
            : ("موارد ناقص: " + missing.join("، "));

        ownerSmsStatus.innerHTML = [
            summaryCard("آمادگی OTP", ready ? "آماده" : "ناقص", readinessMeta, ready ? "ok" : "warn"),
            summaryCard("سرویس", s.enabled ? "فعال" : "غیرفعال", "وضعیت کلی سرویس FarazSMS", s.enabled ? "ok" : "warn"),
            summaryCard("API Key", s.apiKeyConfigured ? "تنظیم شده" : "تنظیم نشده", "کلید API فقط روی سرور نگهداری می‌شود.", s.apiKeyConfigured ? "ok" : "warn"),
            summaryCard("Pattern Code", s.patternConfigured ? "تنظیم شده" : "تنظیم نشده", "کد پترن مخصوص ارسال OTP.", s.patternConfigured ? "ok" : "warn"),
            summaryCard("خط ارسال", s.senderLineConfigured ? (s.senderLine || "تنظیم شده") : "مسیر خدماتی", "در نبود خط اختصاصی، از مسیر خدماتی/اشتراکی استفاده می‌شود."),
            summaryCard("دامنه", s.domainConfigured ? (s.domain || "تنظیم شده") : "تنظیم نشده", "برای مانیتورینگ و اعتبارسنجی درخواست‌ها."),
            summaryCard("آخرین تست سلامت", healthLabel, s.lastHealthMessage || "هنوز تستی اجرا نشده است.", healthTone),
            summaryCard("زمان آخرین تست", s.lastHealthAt || "—", "آخرین زمان health check ثبت‌شده")
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
            summaryCard("مصرف فضای مدیریت‌شده", usagePercent.toFixed(2) + "%", formatBytes(m.usageBytes || 0) + " از " + formatBytes(m.targetBytes || 0)),
            summaryCard("آستانه پاکسازی", toNumber(m.thresholdPercent, 60).toFixed(2) + "%", "وقتی مصرف از این حد عبور کند پاکسازی خودکار اجرا می‌شود"),
            summaryCard("فایل اصلی باقی‌مانده", String(Math.max(0, Math.floor(toNumber(m.originalCount, 0))).toLocaleString("fa-IR")), "تعداد originalهایی که هنوز در دسترس‌اند"),
            summaryCard("فایل پاک‌شده", String(Math.max(0, Math.floor(toNumber(m.purgedCount, 0))).toLocaleString("fa-IR")), "تعداد originalهای منقضی/پاک‌شده"),
            summaryCard("آخرین پاکسازی", m.lastCleanupAt || "—", m.lastCleanupStatus || "unknown"),
            summaryCard("سلامت پاکسازی", m.cleanupHealthy ? "سالم" : "مشکل‌دار", m.lastCleanupError || "بدون خطای ثبت‌شده")
        ].join("");
    }

    async function loadOwnerSmsStatus() {
        if (!currentUser || !currentUser.isOwner) return;
        smsState.loading = true;
        ownerSmsFeedbackMessage("در حال دریافت وضعیت پیامک...", "", true);
        var response = await request("smsStatus", {});
        smsState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerSmsFeedbackMessage("", "");
            return;
        }
        if (!response || !response.success || !response.status) {
            ownerSmsFeedbackMessage((response && response.error) || "خواندن وضعیت پیامک انجام نشد.", "error");
            return;
        }

        smsState.status = response.status;
        renderOwnerSmsStatus(smsState.status);
        ownerSmsFeedbackMessage("", "");
    }

    async function saveOwnerSmsConfig(event) {
        if (event) event.preventDefault();
        if (!currentUser || !currentUser.isOwner || !ownerSmsForm) return;

        ownerSmsFeedbackMessage("در حال ذخیره تنظیمات پیامک...", "", true);
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

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerSmsFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.status) {
                ownerSmsFeedbackMessage((response && response.error) || "ذخیره تنظیمات پیامک انجام نشد.", "error");
                return;
            }

            smsState.status = response.status;
            renderOwnerSmsStatus(smsState.status);
            if (ownerSmsApiKey) ownerSmsApiKey.value = "";
            if (ownerSmsClearApi) ownerSmsClearApi.checked = false;
            ownerSmsFeedbackMessage(response.message || "تنظیمات پیامک ذخیره شد.", "success");
        } finally {
            if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = false;
            if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = false;
        }
    }

    async function runOwnerSmsHealthCheck() {
        if (!currentUser || !currentUser.isOwner) return;
        ownerSmsFeedbackMessage("در حال بررسی سلامت سرویس پیامک...", "", true);
        if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = true;
        if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = true;

        try {
            var testPhone = ensureOwnerSmsHealthPhone();
            if (!testPhone) {
                ownerSmsFeedbackMessage("برای تست ارسال واقعی، شماره موبایل معتبر را وارد کن.", "error");
                return;
            }
            var response = await request("smsHealthCheck", {
                phoneNumber: testPhone
            });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerSmsFeedbackMessage("", "");
                return;
            }
            if (response && response.status) {
                smsState.status = response.status;
                renderOwnerSmsStatus(smsState.status);
            }
            if (!response || !response.success) {
                ownerSmsFeedbackMessage((response && response.error) || (response && response.message) || "تست سلامت سرویس پیامکی ناموفق بود.", "error");
                return;
            }
            ownerSmsFeedbackMessage(response.message || "تست سلامت سرویس پیامکی با موفقیت انجام شد.", "success");
        } finally {
            if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = false;
            if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = false;
        }
    }

    async function loadOwnerMediaStatus() {
        if (!currentUser || !currentUser.isOwner) return;
        mediaState.loading = true;
        ownerMediaFeedbackMessage("در حال دریافت وضعیت فضای رسانه...", "", true);
        var response = await fetch("/chat/chat_api.php?action=mediaStatus", {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = res.status;
                return data;
            });
        });
        mediaState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerMediaFeedbackMessage("", "");
            return;
        }
        if (!response || !response.success || !response.media) {
            ownerMediaFeedbackMessage((response && response.error) || "خواندن وضعیت فضای رسانه انجام نشد.", "error");
            return;
        }

        mediaState.status = response.media;
        renderOwnerMediaStatus(mediaState.status);
        ownerMediaFeedbackMessage("", "");
    }

    async function runOwnerMediaCleanup() {
        if (!currentUser || !currentUser.isOwner) return;
        ownerMediaFeedbackMessage("در حال اجرای پاکسازی فوری...", "", true);
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
                    return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
                }).then(function (data) {
                    data.httpStatus = res.status;
                    return data;
                });
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerMediaFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.media) {
                ownerMediaFeedbackMessage((response && response.error) || "پاکسازی فضای رسانه انجام نشد.", "error");
                return;
            }

            mediaState.status = response.media;
            renderOwnerMediaStatus(mediaState.status);
            ownerMediaFeedbackMessage("پاکسازی فوری انجام شد.", "success");
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
            representativeList.innerHTML = '<div class="owner-empty">فعلاً نماینده‌ای تعریف نشده است.</div>';
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
            return "مالک اصلی";
        }

        return user.role === "representative" ? "لغو نماینده" : "ثبت به‌عنوان نماینده";
    }

    function userMeta(user) {
        var parts = [user.roleLabel || "دانشجو"];
        if (user.hasGrades) {
            parts.push("دارای نمرات");
        }
        return parts.join(" • ");
    }

    function renderUsers(users) {
        var query = ownerSearch.value || "";
        var visibleUsers = users.filter(function (user) {
            return userMatchesQuery(user, query);
        });

        if (!visibleUsers.length) {
            ownerUserList.innerHTML = '<div class="owner-empty">کاربری با این جست‌وجو پیدا نشد.</div>';
            return;
        }

        ownerUserList.innerHTML = "";
        visibleUsers.forEach(function (user) {
            var article = document.createElement("article");
            article.className = "owner-user";

            var actionDisabled = user.role === "owner" || ownerState.savingStudentNumber === user.studentNumber;
            var button = '<button class="shell-action-btn' + (user.role === "representative" ? " shell-action-btn-primary" : "") + '" type="button" data-student-number="' + user.studentNumber + '" ' + (actionDisabled ? "disabled" : "") + ">" + toggleButtonLabel(user) + "</button>";

            article.innerHTML = [
                '<div class="owner-user__copy">',
                "  <strong>" + user.name + "</strong>",
                "  <span>" + user.studentNumber + "</span>",
                "  <small>" + userMeta(user) + "</small>",
                "</div>",
                '<div class="owner-user__actions">' + button + "</div>"
            ].join("");

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
    function navidRenderOwnerStatus(ownerStatus) {
        if (!navidOwnerStatus) {
            return;
        }

        if (!ownerStatus || typeof ownerStatus !== "object") {
            navidOwnerStatus.innerHTML = summaryCard("\u0646\u0648\u06cc\u062f", "\u2014", "\u0648\u0636\u0639\u06cc\u062a \u06cc\u06a9\u067e\u0627\u0631\u0686\u0647\u200c\u0633\u0627\u0632\u06cc \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
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

        if (!statusDetail) {
            if (actionRequired === "save-credentials" || !!state.credentialsMissing) {
                statusDetail = "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0648 \u0631\u0645\u0632 \u0646\u0648\u06cc\u062f \u0630\u062e\u06cc\u0631\u0647 \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.";
            } else if (actionRequired === "update-credentials" || !!state.credentialsInvalid) {
                statusDetail = "\u0627\u0639\u062a\u0628\u0627\u0631 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0633\u062a.";
            } else if (actionRequired === "manual-reconnect" || !!state.requiresReconnect) {
                statusDetail = "\u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u062f\u0627\u0631\u062f.";
            } else if (String(state.lastResult || "") === "ok") {
                statusDetail = "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f \u0633\u0627\u0644\u0645 \u0627\u0633\u062a.";
            } else {
                statusDetail = "\u0628\u062f\u0648\u0646 \u062c\u0632\u0626\u06cc\u0627\u062a \u062e\u0637\u0627";
            }
        }

        navidOwnerStatus.innerHTML = [
            summaryCard("\u0648\u0636\u0639\u06cc\u062a", navidStatusResultLabel(state.lastResult || ""), statusDetail),
            summaryCard("\u0622\u062e\u0631\u06cc\u0646 \u0645\u0648\u0641\u0642", state.lastSuccessAt || "\u2014", "\u0622\u062e\u0631\u06cc\u0646 \u0632\u0645\u0627\u0646 \u0645\u0648\u0641\u0642\u06cc\u062a \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc"),
            summaryCard("\u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc", String(counts.assignments || 0).toLocaleString("fa-IR"), "\u0645\u062c\u0645\u0648\u0639 \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u062f\u0631 snapshot"),
            summaryCard("\u0627\u0642\u062f\u0627\u0645 \u0644\u0627\u0632\u0645", navidActionRequiredLabel(actionRequired), "\u0627\u0642\u062f\u0627\u0645\u06cc \u06a9\u0647 \u0628\u0631\u0627\u06cc \u067e\u0627\u06cc\u062f\u0627\u0631\u06cc \u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u06cc\u062f \u0627\u0646\u062c\u0627\u0645 \u0634\u0648\u062f."),
            summaryCard("\u062d\u0633\u0627\u0628 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647", config.hasCredentials ? (config.usernameMasked || "\u062b\u0628\u062a \u0634\u062f\u0647") : "\u062b\u0628\u062a \u0646\u0634\u062f\u0647", "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0631\u0645\u0632\u0646\u06af\u0627\u0631\u06cc\u200c\u0634\u062f\u0647 \u062f\u0631 \u0633\u0631\u0648\u0631"),
            summaryCard("\u0648\u0636\u0639\u06cc\u062a \u0646\u0634\u0633\u062a", session.status || "missing", "\u0622\u062e\u0631\u06cc\u0646 \u0648\u0636\u0639\u06cc\u062a \u06a9\u0648\u06a9\u06cc \u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f")
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
    }

    async function loadNavidOwnerStatus() {
        if (!navidOwnerStatus) {
            return;
        }

        navidState.loading = true;
        navidFeedbackMessage("در حال خواندن وضعیت نوید...", "", true);
        navidRenderOwnerStatus(navidState.ownerStatus);

        var response = await navidGet("ownerStatus");
        navidState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            navidFeedbackMessage("", "");
            return;
        }

        if (!response || !response.success || !response.ownerStatus) {
            navidFeedbackMessage((response && response.error) || "وضعیت نوید خوانده نشد.", "error");
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

        navidFeedbackMessage("در حال ذخیره تنظیمات نوید...", "", true);
        if (navidSyncNowButton) {
            navidSyncNowButton.disabled = true;
        }

        try {
            var response = await navidPost("saveConfig", payload);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidFeedbackMessage((response && response.error) || "ذخیره تنظیمات نوید انجام نشد.", "error");
                return;
            }

            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
            navidFeedbackMessage(response.message || "تنظیمات نوید ذخیره شد.", "success");
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
        navidFeedbackMessage("در حال همگام‌سازی فوری نوید...", "", true);
        try {
            var response = await navidPost("syncNow", {});
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidFeedbackMessage((response && response.message) || (response && response.error) || "همگام‌سازی نوید موفق نشد.", "error");
            } else {
                navidFeedbackMessage(response.message || "همگام‌سازی نوید انجام شد.", "success");
            }

            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
        } finally {
            navidSyncNowButton.disabled = false;
        }
    }

    async function loadNavidCaptchaChallenge() {
        if (!navidGetCaptchaButton) {
            return;
        }

        navidGetCaptchaButton.disabled = true;
        navidReconnectMessage("در حال دریافت کپچای نوید...", "", true);
        try {
            var response = await navidPost("captchaChallenge", {});
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidReconnectMessage("", "");
                return;
            }

            if (!response || !response.success || !response.captchaDataUri) {
                navidReconnectMessage((response && response.error) || "دریافت کپچا انجام نشد.", "error");
                return;
            }

            if (navidCaptchaImage) {
                navidCaptchaImage.hidden = false;
                navidCaptchaImage.src = response.captchaDataUri;
            }
            navidReconnectMessage("کپچا آماده شد. کد را وارد کن و اتصال مجدد را بزن.", "success");
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
            navidReconnectMessage("کد کپچا را وارد کن.", "error");
            return;
        }

        navidCompleteReconnectButton.disabled = true;
        navidReconnectMessage("در حال اتصال مجدد نوید...", "", true);
        try {
            var response = await navidPost("completeReconnect", { captchaCode: captchaCode });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidReconnectMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidReconnectMessage((response && response.error) || "اتصال مجدد نوید انجام نشد.", "error");
                return;
            }

            navidReconnectMessage(response.message || "اتصال مجدد نوید انجام شد.", "success");
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
        ownerFeedbackMessage("در حال گرفتن فهرست کاربران...", "");
        ownerSummary.innerHTML = summaryCard("کاربر", "…", "در حال بارگذاری داده‌های حساب‌ها");
        representativeList.innerHTML = '<div class="owner-empty">در حال خواندن نماینده‌ها...</div>';
        ownerUserList.innerHTML = '<div class="owner-empty">در حال خواندن فهرست کاربران...</div>';
        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = "در حال بارگذاری کاربران...";
        }

        var response = await requestUsers();
        ownerState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerFeedbackMessage("", "");
            return;
        }

        if (!response || !response.success) {
            ownerFeedbackMessage((response && response.error) || "فهرست کاربران گرفته نشد.", "error");
            return;
        }

        ownerState.users = Array.isArray(response.users) ? response.users : [];
        ownerFeedbackMessage("", "");
        renderOwnerPanel();
    }

    async function setRepresentative(studentNumber, representative) {
        ownerState.savingStudentNumber = studentNumber;
        ownerFeedbackMessage(representative ? "در حال ثبت نماینده..." : "در حال لغو نقش نماینده...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("setRepresentative", {
                studentNumber: studentNumber,
                representative: representative ? "1" : "0"
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "ذخیره تغییرات انجام نشد.", "error");
                return;
            }

            ownerState.users = ownerState.users.map(function (user) {
                return user.studentNumber === response.user.studentNumber ? Object.assign({}, user, response.user) : user;
            });

            ownerFeedbackMessage(response.message || "تغییرات ذخیره شد.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingStudentNumber = "";
            renderUsers(ownerState.users);
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
            ownerCreateStudentFeedbackMessage("همه فیلدها را کامل وارد کن.", "error");
            return;
        }

        if (password.length < 6) {
            ownerCreateStudentFeedbackMessage("رمز عبور باید حداقل ۶ کاراکتر باشد.", "error");
            return;
        }

        setCreateStudentBusy(true);
        ownerCreateStudentFeedbackMessage("در حال ایجاد حساب دانشجو...", "", true);
        try {
            var response = await request("createStudent", {
                firstName: firstName,
                lastName: lastName,
                studentNumber: studentNumber,
                password: password
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerCreateStudentFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success || !response.user) {
                ownerCreateStudentFeedbackMessage((response && response.error) || "ایجاد حساب دانشجو انجام نشد.", "error");
                return;
            }

            if (ownerCreateStudentForm) {
                ownerCreateStudentForm.reset();
            }
            ownerCreateStudentFeedbackMessage(response.message || "حساب دانشجو ایجاد شد.", "success");
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
            setFeedback(loginOtpFeedback, "شماره موبایل معتبر وارد کن.", "error");
            return;
        }
        loginPhoneInput.value = phoneNumber;
        if (loginOtpRequestButton) loginOtpRequestButton.disabled = true;
        setFeedback(loginOtpFeedback, "در حال ارسال کد تایید...", "", true);
        try {
            var auth = window.Dent1402Auth;
            var response = await auth.requestLoginOtp(phoneNumber);
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startLoginOtpCooldown(response.cooldownSeconds);
                }
                setFeedback(loginOtpFeedback, (response && response.error) || "ارسال کد تایید انجام نشد.", "error");
                return;
            }

            startLoginOtpCooldown(response.cooldownSeconds || 0);
            var masked = ltrMaskedPhone(response && response.phoneMasked, "");
            setFeedback(loginOtpFeedback, (response.message || "کد تایید ارسال شد.") + (masked ? (" (" + masked + ")") : ""), "success");
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
            setFeedback(loginOtpFeedback, "شماره موبایل و کد تایید را کامل وارد کن.", "error");
            return;
        }

        if (loginOtpSubmitButton) loginOtpSubmitButton.disabled = true;
        setFeedback(loginOtpFeedback, "در حال ورود با کد تایید...", "", true);
        try {
            var state = await window.Dent1402Auth.loginWithOtp(phoneNumber, otpCode);
            if (!state || !state.loggedIn) {
                setFeedback(loginOtpFeedback, (state && state.error) || "ورود با کد تایید انجام نشد.", "error");
                return;
            }
            setFeedback(loginOtpFeedback, "ورود با کد تایید انجام شد.", "success");
        } finally {
            if (loginOtpSubmitButton) loginOtpSubmitButton.disabled = false;
        }
    }

    async function requestPhoneEnrollmentOtp() {
        if (!phoneEnrollNumber) return;
        var phoneNumber = normalizedPhone(phoneEnrollNumber.value);
        if (!phoneNumber) {
            phoneEnrollFeedbackMessage("شماره موبایل معتبر وارد کن.", "error");
            return;
        }
        phoneEnrollNumber.value = phoneNumber;

        phoneEnrollFeedbackMessage("در حال ارسال کد تایید...", "", true);
        if (phoneEnrollRequestButton) phoneEnrollRequestButton.disabled = true;
        try {
            var response = await window.Dent1402Auth.requestPhoneEnrollOtp(phoneNumber);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneEnrollFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startPhoneEnrollCooldown(response.cooldownSeconds);
                }
                phoneEnrollFeedbackMessage((response && response.error) || "ارسال کد تایید انجام نشد.", "error");
                return;
            }
            startPhoneEnrollCooldown(response.cooldownSeconds || 0);
            var masked = ltrMaskedPhone(response && response.phoneMasked, "");
            phoneEnrollFeedbackMessage((response.message || "کد تایید ارسال شد.") + (masked ? (" (" + masked + ")") : ""), "success");
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
            phoneEnrollFeedbackMessage("شماره موبایل و کد تایید را کامل وارد کن.", "error");
            return;
        }

        if (phoneEnrollSubmitButton) phoneEnrollSubmitButton.disabled = true;
        phoneEnrollFeedbackMessage("در حال تایید شماره موبایل...", "", true);
        try {
            var response = await window.Dent1402Auth.verifyPhoneEnrollOtp(phoneNumber, otpCode);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneEnrollFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneEnrollFeedbackMessage((response && response.error) || "تایید شماره انجام نشد.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            if (phoneEnrollCode) {
                phoneEnrollCode.value = "";
            }
            phoneEnrollFeedbackMessage(response.message || "شماره موبایل تایید شد.", "success");
            phoneToggleFeedbackMessage("", "");
        } finally {
            if (phoneEnrollSubmitButton) phoneEnrollSubmitButton.disabled = false;
        }
    }

    async function savePhoneLoginToggle() {
        if (!phoneLoginEnabledInput) return;
        var enabled = !!phoneLoginEnabledInput.checked;
        if (phoneLoginSaveButton) phoneLoginSaveButton.disabled = true;
        phoneToggleFeedbackMessage("در حال ذخیره وضعیت ورود پیامکی...", "", true);
        try {
            var response = await window.Dent1402Auth.setPhoneLoginEnabled(enabled);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneToggleFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneToggleFeedbackMessage((response && response.error) || "ذخیره وضعیت ورود پیامکی انجام نشد.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            phoneToggleFeedbackMessage(response.message || "وضعیت ورود پیامکی ذخیره شد.", "success");
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
        phoneManageFeedbackMessage("شماره جدید را وارد کن، کد تایید بگیر و ثبت کن.", "success");
    }

    async function removePhoneNumber() {
        if (!currentUser) {
            return;
        }

        var phone = parsedPhone(currentUser);
        if (!phone.hasNumber) {
            phoneManageFeedbackMessage("شماره‌ای برای حذف ثبت نشده است.", "error");
            return;
        }

        var masked = ltrMaskedPhone(phone.numberMasked, "شماره فعلی");
        var confirmed = window.confirm("شماره " + masked + " از این حساب حذف شود؟");
        if (!confirmed) {
            return;
        }

        if (phoneNumberRemoveButton) {
            phoneNumberRemoveButton.disabled = true;
        }
        phoneManageFeedbackMessage("در حال حذف شماره موبایل...", "", true);
        try {
            var response = await window.Dent1402Auth.removePhoneNumber();
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneManageFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneManageFeedbackMessage((response && response.error) || "حذف شماره انجام نشد.", "error");
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
            phoneManageFeedbackMessage(response.message || "شماره موبایل حذف شد.", "success");
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
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
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
        setBootText("ورود انجام شد، در حال بازگشت...");
        showStage("boot");

        window.setTimeout(function () {
            window.location.href = pendingReturnTo;
        }, 520);
    }

    function handleAuthState(detail) {
        if (detail.status === "session-restoring") {
            setBootText("در حال بازیابی نشست...");
            showStage("boot");
            return;
        }

        if (detail.status === "logging-in") {
            setFeedback(loginFeedback, "در حال ورود...", "", true);
            showStage("login");
            return;
        }

        if (detail.status === "logging-out") {
            setBootText("در حال خروج از حساب...");
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
                accountInfoRole.textContent = "—";
            }
            if (accountInfoSession) {
                accountInfoSession.textContent = "—";
            }
            if (accountInfoRotation) {
                accountInfoRotation.textContent = "—";
            }
            if (accountRowProfileMeta) {
                accountRowProfileMeta.textContent = "ویرایش آواتار، بیو و راه ارتباطی";
            }
            if (accountRowInfoMeta) {
                accountRowInfoMeta.textContent = "نام، شماره دانشجویی و نوع دسترسی";
            }
            if (accountRowOwnerMeta) {
                accountRowOwnerMeta.textContent = "مدیریت کاربران، نماینده‌ها و ایجاد دانشجو";
            }
            if (accountRowNavidMeta) {
                accountRowNavidMeta.textContent = "وضعیت اتصال و همگام‌سازی نوید";
            }
            if (accountRowPollsMeta) {
                accountRowPollsMeta.textContent = "\u062f\u0633\u062a\u0631\u0633\u06cc \u0645\u062f\u06cc\u0631\u06cc\u062a \u0646\u0638\u0631\u0633\u0646\u062c\u06cc \u0628\u0631\u0627\u06cc \u0645\u0627\u0644\u06a9/\u0646\u0645\u0627\u06cc\u0646\u062f\u0647 \u0641\u0639\u0627\u0644 \u0627\u0633\u062a.";
            }
            if (accountRowPhoneMeta) {
                accountRowPhoneMeta.textContent = "ثبت شماره موبایل، تایید با OTP و فعال‌سازی مسیر دوم ورود";
            }
            if (ownerHubSection) {
                ownerHubSection.hidden = true;
            }
            if (pollManagerHubSection) {
                pollManagerHubSection.hidden = true;
            }
            if (accountPhoneNudge) {
                accountPhoneNudge.hidden = true;
            }
            if (phoneStatusSummary) {
                phoneStatusSummary.textContent = "—";
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
                setFeedback(loginFeedback, detail.error || "ورود انجام نشد.", "error");
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
            setFeedback(loginFeedback, "شماره دانشجویی و رمز عبور را کامل وارد کن.", "error");
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
                setInlineFeedback(profileAvatarFeedback, "حجم فایل زیاد است. یک عکس کوچک‌تر انتخاب کن.", "error");
                profileAvatarFile.value = "";
                return;
            }

            setProfileAvatarProcessing(true);
            setInlineFeedback(profileAvatarFeedback, "در حال آماده‌سازی عکس...", "", true);
            imageFileToAvatarDataUrl(file).then(function (avatarDataUrl) {
                profileDraftAvatarUrl = avatarDataUrl;
                updateIdentityAvatars($("profile-name").value || $("account-name").textContent || "");
                setInlineFeedback(profileAvatarFeedback, "عکس آماده شد. برای ثبت نهایی، ذخیره تغییرات را بزن.", "success");
            }).catch(function (error) {
                if (error && error.message === "avatar-too-large") {
                    setInlineFeedback(profileAvatarFeedback, "حجم عکس نهایی بیشتر از حد مجاز است. عکس ساده‌تری انتخاب کن.", "error");
                    return;
                }
                setInlineFeedback(profileAvatarFeedback, "خواندن عکس انجام نشد. دوباره تلاش کن.", "error");
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
            setInlineFeedback(profileAvatarFeedback, "عکس پروفایل حذف شد. برای ثبت نهایی، ذخیره تغییرات را بزن.", "success");
        });
    }

    profileForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        setProfileBusy(true);
        setFeedback(profileFeedback, "در حال ذخیره پروفایل...", "", true);

        try {
            var response = await request("updateProfile", {
                about: $("profile-about").value.trim(),
                contactHandle: $("profile-contact-handle").value.trim(),
                focusArea: $("profile-focus-area").value.trim(),
                avatarUrl: profileDraftAvatarUrl
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                setFeedback(profileFeedback, "نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }

            if (!response || !response.success || !response.user) {
                setFeedback(profileFeedback, (response && response.error) || "ذخیره پروفایل انجام نشد.", "error");
                return;
            }

            if (typeof window.Dent1402Auth.patchCurrentUser === "function") {
                window.Dent1402Auth.patchCurrentUser(response.user);
            } else {
                window.Dent1402Auth.bootstrap(true);
            }
            setFeedback(profileFeedback, response.message || "پروفایل ذخیره شد.", "success");
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
            setFeedback(securityFeedback, "همه فیلدهای رمز را کامل کن.", "error");
            return;
        }

        if (newPassword !== confirmPassword) {
            setFeedback(securityFeedback, "رمز جدید و تکرارش یکسان نیست.", "error");
            return;
        }

        securitySubmit.disabled = true;
        setFeedback(securityFeedback, "در حال تغییر رمز...", "", true);

        try {
            var response = await request("changePassword", {
                currentPassword: currentPassword,
                newPassword: newPassword
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                setFeedback(securityFeedback, "نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }

            if (!response || !response.success) {
                setFeedback(securityFeedback, (response && response.error) || "تغییر رمز انجام نشد.", "error");
                return;
            }

            $("security-current-password").value = "";
            $("security-new-password").value = "";
            $("security-confirm-password").value = "";
            setFeedback(securityFeedback, response.message || "رمز عبور تغییر کرد.", "success");
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
            var button = event.target.closest("button[data-student-number]");
            if (!button) {
                return;
            }

            var studentNumber = button.dataset.studentNumber;
            var user = ownerState.users.find(function (item) {
                return item.studentNumber === studentNumber;
            });

            if (!user || user.role === "owner") {
                return;
            }

            setRepresentative(studentNumber, user.role !== "representative");
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
