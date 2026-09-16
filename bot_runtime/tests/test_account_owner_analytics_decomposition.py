from pathlib import Path
import subprocess
ROOT = Path(__file__).resolve().parents[2]
ACCOUNT = ROOT / "public_html/assets/site/scripts/account.js"
MODULE = ROOT / "public_html/assets/site/scripts/account-owner-analytics.js"
HTML = ROOT / "public_html/account/index.html"
NAMES = ['renderOwnerSummary', 'summaryCard', 'ownerStatsMetric', 'syncOwnerStatsShortcut', 'ownerStatsEmptyMarkup', 'ownerStatsShortPath', 'renderOwnerStatsOverview', 'renderOwnerStatsChart', 'renderOwnerStatsBars', 'renderOwnerStatsSimpleTable', 'renderOwnerStatsTables', 'renderOwnerAnalytics', 'loadOwnerAnalytics']

def test_owner_analytics_module_order_and_factory_contract():
    html = HTML.read_text(encoding="utf-8")
    a=html.index("account-utils.js?v=20260916-p4-notifications1"); b=html.index("account-owner-analytics.js?v=20260916-p4-notifications1"); c=html.index("account-notifications.js?v=20260916-p4-notifications1"); d=html.index("account.js?v=20260916-p4-notifications1")
    assert a < b < c < d
    module=MODULE.read_text(encoding="utf-8")
    assert "window.Dent1402AccountOwnerAnalytics" in module and "function create(context)" in module

def test_owner_analytics_functions_leave_account_shell():
    account=ACCOUNT.read_text(encoding="utf-8-sig"); module=MODULE.read_text(encoding="utf-8")
    for name in NAMES:
        assert f"function {name}(" not in account and f"async function {name}(" not in account
        assert f"function {name}(" in module or f"async function {name}(" in module
        assert f"var {name} = ownerAnalytics.{name};" in account

def test_owner_analytics_module_runtime_smoke():
    js=r'''const fs=require("fs"),vm=require("vm");const c={window:{}};vm.createContext(c);vm.runInContext(fs.readFileSync(process.argv[1],"utf8"),c);const f=c.window.Dent1402AccountOwnerAnalytics;if(!f||typeof f.create!=="function")process.exit(2);const noop={textContent:"",innerHTML:"",classList:{toggle(){}}};const ctx={};["accountOwnerAppearanceShortcut","accountOwnerStatsShortcut","accountRowOwnerMeta","accountRowOwnerStatsMeta","ownerStatsCohorts","ownerStatsDownloads","ownerStatsDownloadsChart","ownerStatsErrors","ownerStatsErrorsMeta","ownerStatsExams","ownerStatsFamilies","ownerStatsFeedbackMessage","ownerStatsFunnel","ownerStatsLoginsChart","ownerStatsMeta","ownerStatsMethods","ownerStatsOverview","ownerStatsPages","ownerStatsRecentLogins","ownerStatsReferences","ownerStatsRefreshButton","ownerStatsRetention","ownerStatsVisitsChart","ownerSummary"].forEach(k=>ctx[k]=noop);ctx.analyticsGet=async()=>({success:true});ctx.consumeUnauthorized=()=>false;ctx.escapeHtml=v=>String(v);ctx.formatJalaliDateTime=v=>String(v||"");ctx.hasOwnerAccess=()=>true;ctx.ownerCanAccessStats=()=>true;ctx.ownerUsersInActiveCohort=()=>[];ctx.ownerAnalyticsState={loading:false,loaded:false,data:null};ctx.ownerState={users:[]};const api=f.create(ctx);if(typeof api.ownerStatsMetric!=="function"||api.ownerStatsMetric(7)!==(7).toLocaleString("fa-IR")||api.ownerStatsShortPath("abc")!=="abc")process.exit(3);'''
    assert subprocess.run(["node","-e",js,str(MODULE)],cwd=ROOT).returncode==0
