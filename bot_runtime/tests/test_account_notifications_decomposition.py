from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
ACCOUNT = ROOT / "public_html/assets/site/scripts/account.js"
MODULE = ROOT / "public_html/assets/site/scripts/account-notifications.js"
HTML = ROOT / "public_html/account/index.html"
NAMES = ['notificationsUserKey', 'notificationsGet', 'notificationsPost', 'notificationsResetState', 'notificationsDispatchSummary', 'notificationsNormalizeDigestHourValue', 'notificationsPreferenceSnapshotFromInputs', 'notificationsCurrentPreferenceView', 'notificationsUpdatePreferenceDraft', 'notificationsItemIsImportant', 'notificationsLatestUnreadItemFromItems', 'notificationsLatestUnreadItem', 'notificationsSyncPreview', 'notificationsApplyResponseMeta', 'notificationsCompactText', 'notificationsItemIsManaged', 'notificationsMatchesFilter', 'notificationsFilterConfigs', 'notificationsNormalizeActiveFilter', 'notificationsFilteredItems', 'notificationsBodyHtml', 'notificationsValidateBroadcastPayload', 'notificationsDeployMeta', 'notificationsDeploySummaryRows', 'notificationsDeployPreviewText', 'notificationsDeployBodyHtml', 'notificationsKindLabel', 'notificationsItemDisplayAt', 'notificationsPrimaryState', 'notificationsSmsStatusLabel', 'notificationsAudienceSummaryText', 'notificationsRowMetaText', 'notificationsFooterNoteText', 'notificationsComposeContainer', 'notificationsSetComposeOpen', 'notificationsOverviewPillHtml', 'notificationsOverviewHtml', 'notificationsSmsDetailText', 'notificationsAudienceEntryHtml', 'notificationsAudienceColumnHtml', 'notificationsAudiencePanelHtml', 'notificationsHubMetaText', 'renderNotificationsHub', 'renderNotificationsSurface', 'renderNotificationsUi', 'applyNotificationsPayload', 'loadNotifications', 'updateNotificationsReadState', 'markNotificationsRead', 'markAllNotificationsRead', 'saveNotificationsPreferences', 'snoozeNotification', 'loadNotificationAudience', 'toggleNotificationAudience', 'deleteNotification', 'submitNotificationsBroadcast', 'handleNotificationCtaNavigation']

def test_notification_workflows_leave_account_shell():
    shell = ACCOUNT.read_text(encoding="utf-8-sig")
    module = MODULE.read_text(encoding="utf-8")
    for name in NAMES:
        assert f"function {name}(" not in shell and f"async function {name}(" not in shell
        assert f"function {name}(" in module or f"async function {name}(" in module
        assert f"var {name} = notificationsModule.{name};" in shell
    assert "function handlePushToggleChange(" in shell

def test_notification_module_order_and_live_user_contract():
    html = HTML.read_text(encoding="utf-8")
    a = html.index("account-utils.js?v=20260916-p4-notifications1")
    b = html.index("account-owner-analytics.js?v=20260916-p4-notifications1")
    c = html.index("account-notifications.js?v=20260916-p4-notifications1")
    d = html.index("account.js?v=20260916-p4-notifications1")
    assert a < b < c < d
    shell = ACCOUNT.read_text(encoding="utf-8-sig")
    module = MODULE.read_text(encoding="utf-8")
    assert "getCurrentUser: function () { return currentUser; }" in shell
    assert "return accountUserKey(getCurrentUser());" in module
    assert "return accountUserKey(currentUser);" not in module

def test_notification_module_runtime_live_user_getter():
    js = r'''const fs=require("fs"),vm=require("vm");let current={key:"A"};const c={window:{},CustomEvent:function(){},URLSearchParams:URLSearchParams,fetch:()=>Promise.resolve({json:()=>Promise.resolve({})})};vm.createContext(c);vm.runInContext(fs.readFileSync(process.argv[1],"utf8"),c);const factory=c.window.Dent1402AccountNotifications;if(!factory||typeof factory.create!=="function")process.exit(2);const noop=()=>{};const ctx={getCurrentUser:()=>current,accountUserKey:u=>u&&u.key||"",notificationsState:{},toNumber:(v,f)=>Number.isFinite(Number(v))?Number(v):f,toPersianDigits:v=>String(v),escapeHtml:v=>String(v),formatJalaliDateTime:v=>String(v),consumeUnauthorized:()=>false,networkErrorResponse:()=>({}),setFeedback:noop,setInlineFeedback:noop};const keys=["accountNavidAlertBody","accountNavidAlertCard","accountNavidAlertLink","accountNavidAlertMarkRead","accountNavidAlertTime","accountNavidAlertTitle","accountNotificationAlertKind","accountRowNotificationsMeta","notificationsBodyInput","notificationsBroadcastForm","notificationsBroadcastSubmit","notificationsComposeShell","notificationsCtaHrefInput","notificationsCtaLabelInput","notificationsDigestEnabledToggle","notificationsDigestHourInput","notificationsEmpty","notificationsFeedback","notificationsFilters","notificationsFormRemindersToggle","notificationsList","notificationsManagerCard","notificationsManagerFeedback","notificationsMarkAllButton","notificationsNavidAlertsToggle","notificationsNavidRow","notificationsPaymentRemindersToggle","notificationsPrefsCard","notificationsPrefsHint","notificationsPrefsSaveButton","notificationsRefreshButton","notificationsScheduleInput","notificationsSendSmsInput","notificationsSummary","notificationsTargetSelect","notificationsTitleInput"];for(const k of keys)ctx[k]=null;const api=factory.create(ctx);if(api.notificationsUserKey()!=="A")process.exit(3);current={key:"B"};if(api.notificationsUserKey()!=="B")process.exit(4);if(typeof api.loadNotifications!=="function"||typeof api.renderNotificationsUi!=="function")process.exit(5);'''
    assert subprocess.run(["node", "-e", js, str(MODULE)], cwd=ROOT).returncode == 0
