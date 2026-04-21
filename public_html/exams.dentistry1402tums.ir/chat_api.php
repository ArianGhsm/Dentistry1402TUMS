<?php
declare(strict_types=1);
session_start();

/*
  Dent1402Chat Backend (chat_api.php)
  - Auth from users.csv (supports both headers: username,password,name OR StudentID,Password,Name)
  - Messages stored in data/messages.json
  - State stored in data/state.json (mute)
  - Features: send, edit, delete (admin), reply, pin/unpin (admin), mute/unmute (admin)
*/

header('Content-Type: application/json; charset=UTF-8');

$BASE_DIR   = __DIR__;
$USERS_CSV  = $BASE_DIR . '/users.csv';
$DATA_DIR   = $BASE_DIR . '/data';
$MSG_FILE   = $DATA_DIR . '/messages.json';
$STATE_FILE = $DATA_DIR . '/state.json';

// ✅ Admin Users (Keep As You Wanted)
$ADMIN_USERS = ['40211272003', '40211272021'];

/* ---------------- Helpers ---------------- */

function respond(array $data): void {
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function ensureDataFiles(string $dataDir, string $msgFile, string $stateFile): void {
  if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
  if (!file_exists($msgFile)) { file_put_contents($msgFile, json_encode([], JSON_UNESCAPED_UNICODE), LOCK_EX); }
  if (!file_exists($stateFile)) {
    $init = ['muted' => false, 'mutedBy' => null, 'mutedAt' => null];
    file_put_contents($stateFile, json_encode($init, JSON_UNESCAPED_UNICODE), LOCK_EX);
  }
}

function readJsonFile(string $path, $default) {
  $raw = @file_get_contents($path);
  if ($raw === false || trim($raw) === '') return $default;
  $data = json_decode($raw, true);
  return (json_last_error() === JSON_ERROR_NONE) ? $data : $default;
}

function writeJsonFile(string $path, $data): bool {
  $json = json_encode($data, JSON_UNESCAPED_UNICODE);
  return file_put_contents($path, $json, LOCK_EX) !== false;
}

function isLoggedIn(): bool {
  return isset($_SESSION['username'], $_SESSION['name']);
}

function isAdmin(array $adminUsers): bool {
  return isLoggedIn() && in_array((string)$_SESSION['username'], $adminUsers, true);
}

function sanitizeText(string $s, int $maxLen = 2000): string {
  $s = trim($s);
  // normalize newlines
  $s = preg_replace("/\r\n|\r/u", "\n", $s);
  // remove null bytes
  $s = str_replace("\0", "", $s);
  // limit length (UTF-8 safe-ish)
  if (mb_strlen($s, 'UTF-8') > $maxLen) {
    $s = mb_substr($s, 0, $maxLen, 'UTF-8');
  }
  return $s;
}

function now(): int { return time(); }

/* ---------------- CSV Users ----------------
   Supports headers:
   - username,password,name
   - StudentID,Password,Name
*/
function loadUsers(string $csvPath): array {
  if (!file_exists($csvPath)) return [];
  $fh = fopen($csvPath, 'r');
  if (!$fh) return [];

  $header = fgetcsv($fh);
  if (!$header) { fclose($fh); return []; }

  $map = [];
  foreach ($header as $i => $col) {
    $key = trim((string)$col);
    $map[$key] = $i;
  }

  $candidates = [
    // canonical
    ['u' => 'username',  'p' => 'password',  'n' => 'name'],
    // your dataset header
    ['u' => 'StudentID', 'p' => 'Password',  'n' => 'Name'],
  ];

  $uKey = $pKey = $nKey = null;
  foreach ($candidates as $c) {
    if (isset($map[$c['u']], $map[$c['p']], $map[$c['n']])) {
      $uKey = $c['u']; $pKey = $c['p']; $nKey = $c['n'];
      break;
    }
  }

  if ($uKey === null) { fclose($fh); return []; }

  $users = [];
  while (($row = fgetcsv($fh)) !== false) {
    $u = trim((string)($row[$map[$uKey]] ?? ''));
    if ($u === '') continue;
    $p = (string)($row[$map[$pKey]] ?? '');
    $n = trim((string)($row[$map[$nKey]] ?? ''));
    $users[$u] = ['username' => $u, 'password' => $p, 'name' => ($n !== '' ? $n : $u)];
  }

  fclose($fh);
  return $users;
}

/* ---------------- Message Model ----------------
  {
    id: int,
    username: string,
    name: string,
    text: string,
    ts: int,
    editedAt: int|null,
    replyTo: int|null,
    pinned: bool
  }
*/

function getLastId(array $messages): int {
  $lastId = 0;
  if (!empty($messages)) {
    $last = end($messages);
    $lastId = (int)($last['id'] ?? 0);
  }
  return $lastId;
}

function findMessageById(array $messages, int $id): ?array {
  foreach ($messages as $m) {
    if ((int)($m['id'] ?? 0) === $id) return $m;
  }
  return null;
}

/* ---------------- Init ---------------- */
ensureDataFiles($DATA_DIR, $MSG_FILE, $STATE_FILE);

/* Basic anti-spam (per session) */
if (!isset($_SESSION['rl'])) $_SESSION['rl'] = ['t' => 0, 'c' => 0];

/* ---------------- Router ---------------- */
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$action = trim($action);

if ($action === 'login') {
  $username = trim((string)($_POST['username'] ?? ''));
  $password = trim((string)($_POST['password'] ?? ''));

  if ($username === '' || $password === '') respond(['error' => 'لطفاً Username و Password را وارد کنید.']);

  $users = loadUsers($USERS_CSV);
  if (!isset($users[$username])) respond(['error' => 'Username یا Password اشتباه است.']);

  // Plain password check (as in your current system)
  if ((string)$users[$username]['password'] !== $password) respond(['error' => 'Username یا Password اشتباه است.']);

  $_SESSION['username'] = $users[$username]['username'];
  $_SESSION['name']     = $users[$username]['name'];

  respond([
    'success'  => true,
    'username' => $_SESSION['username'],
    'name'     => $_SESSION['name'],
    'isAdmin'  => in_array($_SESSION['username'], $ADMIN_USERS, true),
    'state'    => readJsonFile($STATE_FILE, ['muted' => false]),
  ]);
}

if ($action === 'logout') {
  session_destroy();
  respond(['success' => true]);
}

if ($action === 'me') {
  if (!isLoggedIn()) respond(['loggedIn' => false]);
  respond([
    'loggedIn' => true,
    'username' => $_SESSION['username'],
    'name'     => $_SESSION['name'],
    'isAdmin'  => isAdmin($ADMIN_USERS),
    'state'    => readJsonFile($STATE_FILE, ['muted' => false]),
  ]);
}

if ($action === 'fetch') {
  if (!isLoggedIn()) respond(['error' => 'Unauthorized']);

  $sinceId = (int)($_GET['sinceId'] ?? $_POST['sinceId'] ?? 0);

  $messages = readJsonFile($MSG_FILE, []);
  if (!is_array($messages)) $messages = [];

  $state = readJsonFile($STATE_FILE, ['muted' => false]);

  $filtered = [];
  foreach ($messages as $m) {
    $id = (int)($m['id'] ?? 0);
    if ($id > $sinceId) $filtered[] = $m;
  }

  respond([
    'success'  => true,
    'messages' => $filtered,
    'state'    => $state,
  ]);
}

if ($action === 'send') {
  if (!isLoggedIn()) respond(['error' => 'Unauthorized']);

  // rate limit: max 6 messages / 10 seconds per session
  $rl = $_SESSION['rl'];
  $t  = now();
  if (($t - (int)$rl['t']) > 10) { $rl = ['t' => $t, 'c' => 0]; }
  $rl['c'] = (int)$rl['c'] + 1;
  $_SESSION['rl'] = $rl;
  if ($rl['c'] > 6) respond(['error' => 'لطفاً کمی آهسته‌تر پیام بفرستید.']);

  $state = readJsonFile($STATE_FILE, ['muted' => false]);
  if (($state['muted'] ?? false) === true && !isAdmin($ADMIN_USERS)) {
    respond(['error' => 'ارسال پیام توسط مدیر متوقف شده است.']);
  }

  $text = sanitizeText((string)($_POST['text'] ?? ''), 2000);
  if ($text === '') respond(['error' => 'پیام خالی است.']);

  $replyTo = (int)($_POST['replyTo'] ?? 0);
  $replyTo = $replyTo > 0 ? $replyTo : null;

  $messages = readJsonFile($MSG_FILE, []);
  if (!is_array($messages)) $messages = [];

  if ($replyTo !== null && findMessageById($messages, $replyTo) === null) {
    $replyTo = null; // ignore invalid reply target
  }

  $new = [
    'id'       => getLastId($messages) + 1,
    'username' => (string)$_SESSION['username'],
    'name'     => (string)$_SESSION['name'],
    'text'     => $text,
    'ts'       => now(),
    'editedAt' => null,
    'replyTo'  => $replyTo,
    'pinned'   => false,
  ];

  $messages[] = $new;

  if (!writeJsonFile($MSG_FILE, $messages)) respond(['error' => 'خطا در ذخیره پیام.']);

  respond(['success' => true, 'message' => $new]);
}

if ($action === 'edit') {
  if (!isLoggedIn()) respond(['error' => 'Unauthorized']);

  $id   = (int)($_POST['id'] ?? 0);
  $text = sanitizeText((string)($_POST['text'] ?? ''), 2000);
  if ($id <= 0) respond(['error' => 'Id نامعتبر است.']);
  if ($text === '') respond(['error' => 'پیام خالی است.']);

  $messages = readJsonFile($MSG_FILE, []);
  if (!is_array($messages)) $messages = [];

  $found = false;
  for ($i = 0; $i < count($messages); $i++) {
    $mId = (int)($messages[$i]['id'] ?? 0);
    if ($mId === $id) {
      $owner = (string)($messages[$i]['username'] ?? '');
      $canEdit = ($owner === (string)$_SESSION['username']) || isAdmin($ADMIN_USERS);
      if (!$canEdit) respond(['error' => 'اجازه ویرایش این پیام را ندارید.']);

      $messages[$i]['text'] = $text;
      $messages[$i]['editedAt'] = now();
      $found = true;
      $editedMsg = $messages[$i];
      break;
    }
  }

  if (!$found) respond(['error' => 'پیام پیدا نشد.']);
  if (!writeJsonFile($MSG_FILE, $messages)) respond(['error' => 'خطا در ویرایش پیام.']);

  respond(['success' => true, 'message' => $editedMsg]);
}

if ($action === 'delete') {
  if (!isLoggedIn()) respond(['error' => 'Unauthorized']);
  if (!isAdmin($ADMIN_USERS)) respond(['error' => 'Access Denied']);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) respond(['error' => 'Id نامعتبر است.']);

  $messages = readJsonFile($MSG_FILE, []);
  if (!is_array($messages)) $messages = [];

  $out = [];
  $deleted = false;

  foreach ($messages as $m) {
    if ((int)($m['id'] ?? 0) === $id) { $deleted = true; continue; }
    $out[] = $m;
  }

  if (!$deleted) respond(['error' => 'پیام پیدا نشد.']);
  if (!writeJsonFile($MSG_FILE, $out)) respond(['error' => 'خطا در حذف پیام.']);

  respond(['success' => true, 'deletedId' => $id]);
}

if ($action === 'pin') {
  if (!isLoggedIn()) respond(['error' => 'Unauthorized']);
  if (!isAdmin($ADMIN_USERS)) respond(['error' => 'Access Denied']);

  $id = (int)($_POST['id'] ?? 0);
  $pin = (int)($_POST['pinned'] ?? 1) === 1;

  if ($id <= 0) respond(['error' => 'Id نامعتبر است.']);

  $messages = readJsonFile($MSG_FILE, []);
  if (!is_array($messages)) $messages = [];

  $found = false;
  for ($i = 0; $i < count($messages); $i++) {
    if ((int)($messages[$i]['id'] ?? 0) === $id) {
      $messages[$i]['pinned'] = $pin;
      $found = true;
      $pinnedMsg = $messages[$i];
      break;
    }
  }

  if (!$found) respond(['error' => 'پیام پیدا نشد.']);
  if (!writeJsonFile($MSG_FILE, $messages)) respond(['error' => 'خطا در پین کردن پیام.']);

  respond(['success' => true, 'message' => $pinnedMsg]);
}

if ($action === 'setMute') {
  if (!isLoggedIn()) respond(['error' => 'Unauthorized']);
  if (!isAdmin($ADMIN_USERS)) respond(['error' => 'Access Denied']);

  $muted = (int)($_POST['muted'] ?? 0) === 1;

  $newState = [
    'muted'   => $muted,
    'mutedBy' => (string)$_SESSION['username'],
    'mutedAt' => now(),
  ];

  if (!writeJsonFile($STATE_FILE, $newState)) respond(['error' => 'خطا در ذخیره وضعیت.']);

  respond(['success' => true, 'state' => $newState]);
}

respond(['error' => 'Unknown Action']);
