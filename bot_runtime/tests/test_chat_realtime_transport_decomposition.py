from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
CHAT = ROOT / "public_html/assets/site/scripts/chat.js"
MODULE = ROOT / "public_html/assets/site/scripts/chat-realtime-transport.js"
HTML = ROOT / "public_html/chat/index.html"
NAMES = ['clearStreamRetryTimer', 'clearStreamSyncTimer', 'clearPresenceHeartbeatTimer', 'clearTypingTimers', 'configureTransport', 'buildStreamContextUrl', 'stopRealtimeStream', 'queueRealtimeSync', 'scheduleRealtimeReconnect', 'sendPresenceHeartbeat', 'schedulePresenceHeartbeat', 'clearTypingActivity', 'handleComposerTypingActivity', 'startRealtimeStream', 'refreshTransportBinding', 'stopPolling', 'startPolling', 'clearAutoReadTimer', 'scheduleAutoMarkRead']

def test_realtime_transport_functions_leave_chat_shell():
    shell = CHAT.read_text(encoding="utf-8-sig")
    module = MODULE.read_text(encoding="utf-8")
    for name in NAMES:
        assert f"function {name}(" not in shell and f"async function {name}(" not in shell
        assert f"function {name}(" in module or f"async function {name}(" in module
        assert f"var {name} = realtimeTransport.{name};" in shell

def test_realtime_transport_module_order_and_factory_contract():
    html = HTML.read_text(encoding="utf-8")
    a=html.index("chat-utils.js?v=20260916-p4-realtime1")
    b=html.index("chat-cache-drafts.js?v=20260916-p4-realtime1")
    c=html.index("chat-realtime-transport.js?v=20260916-p4-realtime1")
    d=html.index("chat.js?v=20260916-p4-realtime1")
    assert a < b < c < d
    module = MODULE.read_text(encoding="utf-8")
    assert "window.Dent1402ChatRealtimeTransport" in module and "function create(context)" in module

def test_realtime_transport_runtime_contract():
    js = r'''const fs=require("fs"),vm=require("vm");const win={location:{origin:"https://example.test"},clearTimeout(){},setTimeout(){return 1}};const c={window:win,document:{hidden:false},URL:URL,Promise:Promise};vm.createContext(c);vm.runInContext(fs.readFileSync(process.argv[1],"utf8"),c);const f=c.window.Dent1402ChatRealtimeTransport;if(!f||typeof f.create!=="function")process.exit(2);const noop=()=>{};const state={me:{loggedIn:false},activeConversationId:"abc",infoSheetOpen:true,transportMode:"polling",pollIntervalMs:5000,streamRetryTimer:null,streamSyncTimer:null,presenceHeartbeatTimer:null,typingRefreshTimer:null,typingStopTimer:null,pollingTimer:null,pollInFlight:false};const ctx={CHAT_PRESENCE_HEARTBEAT_MS:25000,CHAT_STREAM_RETRY_MS:3000,CHAT_TYPING_IDLE_MS:5000,CHAT_TYPING_REFRESH_MS:2500,MAX_POLL_MS:15000,MIN_POLL_MS:1000,activeConversation:()=>null,apiPost:()=>Promise.resolve({success:true}),applyPresenceBundle:noop,asObject:v=>(v&&typeof v==="object"?v:null),chatTextEl:{value:""},clamp:(n,min,max)=>Math.max(min,Math.min(max,n)),consumeUnauthorized:()=>false,ensureSuccessResponse:noop,normalizeSpace:v=>String(v||"").trim(),setConnectionState:noop,setConversationReadStateById:()=>Promise.resolve(),state:state,syncConversation:()=>Promise.resolve(),toNumber:(v,f)=>Number.isFinite(Number(v))?Number(v):f};const api=f.create(ctx);api.configureTransport({mode:"sse",streamUrl:"/stream",presenceUrl:"/presence",fallbackIntervalMs:300});if(state.transportMode!=="sse"||state.pollIntervalMs!==1000)process.exit(3);const url=api.buildStreamContextUrl();if(!url.includes("conversationId=abc")||!url.includes("includeMembers=1"))process.exit(4);if(typeof api.startRealtimeStream!=="function"||typeof api.scheduleAutoMarkRead!=="function")process.exit(5);'''
    assert subprocess.run(["node","-e",js,str(MODULE)],cwd=ROOT).returncode == 0
