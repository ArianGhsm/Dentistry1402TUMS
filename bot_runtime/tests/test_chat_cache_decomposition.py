from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[2]
CHAT=ROOT/"public_html/assets/site/scripts/chat.js"
MODULE=ROOT/"public_html/assets/site/scripts/chat-cache-drafts.js"
HTML=ROOT/"public_html/chat/index.html"
NAMES=['chatStorage', 'chatCacheUserKey', 'chatCacheKey', 'chatDraftKey', 'compactCachedConversation', 'compactCachedMessage', 'clearFastChatCacheSaveHandle', 'saveFastChatCacheNow', 'scheduleFastChatCacheSave', 'readFastChatCache', 'hydrateFastChatCache', 'readComposerDraft', 'saveComposerDraftNow', 'scheduleComposerDraftSave', 'clearComposerDraft', 'restoreComposerDraftForConversation', 'clearThreadState']

def test_chat_cache_module_order_and_factory_contract():
    html=HTML.read_text(encoding="utf-8")
    a=html.index("chat-utils.js?v=20260916-p4-realtime1"); b=html.index("chat-cache-drafts.js?v=20260916-p4-realtime1"); c=html.index("chat-realtime-transport.js?v=20260916-p4-realtime1"); d=html.index("chat.js?v=20260916-p4-realtime1")
    assert a < b < c < d
    module=MODULE.read_text(encoding="utf-8")
    assert "window.Dent1402ChatCache" in module and "function create(context)" in module

def test_cache_and_draft_functions_leave_chat_shell():
    chat=CHAT.read_text(encoding="utf-8-sig"); module=MODULE.read_text(encoding="utf-8")
    for name in NAMES:
        assert f"function {name}(" not in chat
        assert f"function {name}(" in module
        assert f"var {name} = chatCache.{name};" in chat

def test_chat_cache_runtime_contract():
    js=r'''const fs=require("fs"),vm=require("vm");const store=new Map();const win={localStorage:{getItem:k=>store.has(k)?store.get(k):null,setItem:(k,v)=>store.set(k,String(v)),removeItem:k=>store.delete(k)},clearTimeout(){},setTimeout(){return 1}};const c={window:win};vm.createContext(c);vm.runInContext(fs.readFileSync(process.argv[1],"utf8"),c);const f=c.window.Dent1402ChatCache;if(!f||typeof f.create!=="function")process.exit(2);const noop=()=>{};const ctx={CHAT_DRAFT_SAVE_DELAY_MS:10,CHAT_DRAFT_TTL_MS:1000,CHAT_FAST_CACHE_CONVERSATION_LIMIT:10,CHAT_FAST_CACHE_MESSAGE_LIMIT:10,CHAT_FAST_CACHE_TTL_MS:1000,CHAT_FAST_CACHE_VERSION:3,MAX_MESSAGE_SIZE:100,activeConversation:()=>null,appendMessages:noop,asObject:v=>(v&&typeof v==="object"?v:null),autosizeComposer:noop,chatTextEl:{value:""},clearReplyTarget:noop,closeMentionSuggestions:noop,invalidateMessageListCache:noop,messageList:()=>[],messagesEl:{},normalizeConversation:v=>v,normalizeConversationListCategory:v=>v||"all",normalizeMessage:v=>v,normalizeSpace:v=>String(v||"").trim(),normalizeStudentNumber:v=>String(v||"").replace(/\D/g,""),pageCohort:"main",pauseAllVoiceNotes:noop,renderConversationList:noop,replaceConversations:noop,resetThreadSearchState:noop,setConnectionState:noop,setThreadVisible:noop,showStreamState:noop,state:{me:{loggedIn:true,studentNumber:"1402123"},activeConversationId:"",conversations:[],messages:new Map(),conversationsById:new Map()},syncComposerDraftState:noop,toNumber:(v,f)=>Number.isFinite(Number(v))?Number(v):f,toText:v=>String(v==null?"":v),updateComposerState:noop,updateConversationFilterTabs:noop,updateFabVisibility:noop,updateInfoSheet:noop,updateMessageSelectionUi:noop,updateMobileNav:noop,updateMuteUi:noop,updatePinnedUi:noop,updateThreadHead:noop,updateThreadSearchUi:noop};const api=f.create(ctx);if(api.chatCacheKey()!=="dent1402-chat-fast:v3:main:1402123"||api.chatDraftKey("abc")!=="dent1402-chat-fast:v3:main:1402123:draft:abc"||api.compactCachedMessage({id:0})!==null)process.exit(3);'''
    assert subprocess.run(["node","-e",js,str(MODULE)],cwd=ROOT).returncode==0
