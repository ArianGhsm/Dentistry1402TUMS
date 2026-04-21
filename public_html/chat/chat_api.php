<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/auth_store.php';

function chat_messages_path(): string
{
    return dent_storage_path('chat/messages.json');
}

function chat_state_path(): string
{
    return dent_storage_path('chat/state.json');
}

function chat_ensure_storage(): void
{
    dent_ensure_directory(dirname(chat_messages_path()));

    if (!file_exists(chat_messages_path())) {
        dent_write_json_file(chat_messages_path(), []);
    }

    if (!file_exists(chat_state_path())) {
        dent_write_json_file(chat_state_path(), [
            'muted' => false,
            'mutedBy' => null,
            'mutedAt' => null,
        ]);
    }

    if (!isset($_SESSION['chat_rate_limit'])) {
        $_SESSION['chat_rate_limit'] = [
            'startedAt' => 0,
            'count' => 0,
        ];
    }
}

function chat_read_messages(): array
{
    $messages = dent_read_json_file(chat_messages_path(), []);
    return is_array($messages) ? $messages : [];
}

function chat_write_messages(array $messages): void
{
    dent_write_json_file(chat_messages_path(), array_values($messages));
}

function chat_read_state(): array
{
    $state = dent_read_json_file(chat_state_path(), [
        'muted' => false,
        'mutedBy' => null,
        'mutedAt' => null,
    ]);

    if (!is_array($state)) {
        $state = [
            'muted' => false,
            'mutedBy' => null,
            'mutedAt' => null,
        ];
    }

    return [
        'muted' => (bool) ($state['muted'] ?? false),
        'mutedBy' => $state['mutedBy'] ?? null,
        'mutedAt' => $state['mutedAt'] ?? null,
    ];
}

function chat_write_state(array $state): void
{
    dent_write_json_file(chat_state_path(), [
        'muted' => (bool) ($state['muted'] ?? false),
        'mutedBy' => $state['mutedBy'] ?? null,
        'mutedAt' => $state['mutedAt'] ?? null,
    ]);
}

function chat_next_message_id(array $messages): int
{
    $lastId = 0;
    foreach ($messages as $message) {
        $lastId = max($lastId, (int) ($message['id'] ?? 0));
    }

    return $lastId + 1;
}

function chat_find_message_index(array $messages, int $messageId): int
{
    foreach ($messages as $index => $message) {
        if ((int) ($message['id'] ?? 0) === $messageId) {
            return (int) $index;
        }
    }

    return -1;
}

function chat_sanitize_message_text(string $value, int $maxLength = 2000): string
{
    return dent_clean_text($value, $maxLength);
}

function chat_sanitize_emoji(string $value): string
{
    $value = trim($value);
    $allowed = ['??', '??', '??', '??', '??', '??'];

    return in_array($value, $allowed, true) ? $value : '';
}

function chat_public_user_payload(array $user): array
{
    $public = dent_public_user($user);
    return [
        'studentNumber' => $public['studentNumber'],
        'username' => $public['studentNumber'],
        'name' => $public['name'],
        'role' => $public['role'],
        'roleLabel' => $public['roleLabel'],
        'canModerateChat' => $public['canModerateChat'],
    ];
}

function chat_role_payload_for_student(string $studentNumber): array
{
    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        return [
            'role' => 'student',
            'roleLabel' => dent_role_label('student'),
            'canModerateChat' => false,
        ];
    }

    $public = dent_public_user($user);
    return [
        'role' => $public['role'],
        'roleLabel' => $public['roleLabel'],
        'canModerateChat' => $public['canModerateChat'],
    ];
}

function chat_normalize_message(array $message): array
{
    $studentNumber = dent_normalize_student_number((string) ($message['username'] ?? $message['studentNumber'] ?? ''));
    $rolePayload = chat_role_payload_for_student($studentNumber);

    return [
        'id' => (int) ($message['id'] ?? 0),
        'username' => $studentNumber,
        'studentNumber' => $studentNumber,
        'name' => (string) ($message['name'] ?? $studentNumber),
        'text' => (string) ($message['text'] ?? ''),
        'ts' => (int) ($message['ts'] ?? time()),
        'editedAt' => isset($message['editedAt']) ? (int) $message['editedAt'] : null,
        'replyTo' => isset($message['replyTo']) ? (int) $message['replyTo'] : null,
        'pinned' => (bool) ($message['pinned'] ?? false),
        'reactions' => is_array($message['reactions'] ?? null) ? $message['reactions'] : [],
        'role' => $rolePayload['role'],
        'roleLabel' => $rolePayload['roleLabel'],
        'canModerateChat' => $rolePayload['canModerateChat'],
    ];
}

function chat_normalize_messages(array $messages): array
{
    $normalized = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $normalized[] = chat_normalize_message($message);
    }

    return $normalized;
}

function chat_require_user(): array
{
    return dent_require_user();
}

function chat_require_moderator(): array
{
    $user = chat_require_user();
    if (!dent_can_moderate_chat($user)) {
        dent_error('??? ??? ??? ???? ???? ?? ??????? ???? ???.', 403);
    }

    return $user;
}

function chat_apply_rate_limit(): void
{
    $windowSeconds = 10;
    $maxMessages = 6;
    $state = $_SESSION['chat_rate_limit'] ?? [
        'startedAt' => 0,
        'count' => 0,
    ];

    $now = time();
    if (($now - (int) ($state['startedAt'] ?? 0)) > $windowSeconds) {
        $state = [
            'startedAt' => $now,
            'count' => 0,
        ];
    }

    $state['count'] = (int) ($state['count'] ?? 0) + 1;
    $_SESSION['chat_rate_limit'] = $state;

    if ((int) $state['count'] > $maxMessages) {
        dent_error('????? ??? ???????? ???? ?????.', 429);
    }
}

chat_ensure_storage();

$action = dent_request_action();

if ($action === 'login') {
    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? $_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($studentNumber === '' || trim($password) === '') {
        dent_error('????? ???????? ? ??? ???? ?? ???? ??.', 422);
    }

    $user = dent_verify_credentials($studentNumber, $password);
    if ($user === null) {
        dent_error('????? ???????? ?? ??? ???? ?????? ???.', 401, ['loggedOut' => true]);
    }

    dent_login_user($user);

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'user' => chat_public_user_payload($user),
        'state' => chat_read_state(),
    ]);
}

if ($action === 'logout') {
    dent_logout_user();
    dent_json_response([
        'success' => true,
        'loggedIn' => false,
    ]);
}

if ($action === 'me') {
    $user = dent_current_user();
    if ($user === null) {
        dent_json_response([
            'success' => true,
            'loggedIn' => false,
        ]);
    }

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'user' => chat_public_user_payload($user),
        'state' => chat_read_state(),
    ]);
}

if ($action === 'fetch') {
    chat_require_user();

    $sinceId = (int) ($_GET['sinceId'] ?? $_POST['sinceId'] ?? 0);
    $messages = chat_read_messages();
    $filtered = [];

    foreach ($messages as $message) {
        if ((int) ($message['id'] ?? 0) > $sinceId) {
            $filtered[] = $message;
        }
    }

    dent_json_response([
        'success' => true,
        'messages' => chat_normalize_messages($filtered),
        'state' => chat_read_state(),
    ]);
}

if ($action === 'send') {
    $user = chat_require_user();
    chat_apply_rate_limit();

    $state = chat_read_state();
    if ($state['muted'] && !dent_can_moderate_chat($user)) {
        dent_error('????? ???? ???? ??????? ?????? ???? ??? ???.', 403);
    }

    $text = chat_sanitize_message_text((string) ($_POST['text'] ?? ''));
    if ($text === '') {
        dent_error('???? ???? ???.', 422);
    }

    $replyTo = (int) ($_POST['replyTo'] ?? 0);
    $replyTo = $replyTo > 0 ? $replyTo : null;

    $messages = chat_read_messages();
    if ($replyTo !== null && chat_find_message_index($messages, $replyTo) === -1) {
        $replyTo = null;
    }

    $message = [
        'id' => chat_next_message_id($messages),
        'username' => (string) $user['studentNumber'],
        'name' => (string) $user['name'],
        'text' => $text,
        'ts' => time(),
        'editedAt' => null,
        'replyTo' => $replyTo,
        'pinned' => false,
        'reactions' => [],
    ];

    $messages[] = $message;
    chat_write_messages($messages);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message($message),
    ]);
}

if ($action === 'edit') {
    $user = chat_require_user();
    $messageId = (int) ($_POST['id'] ?? 0);
    $text = chat_sanitize_message_text((string) ($_POST['text'] ?? ''));

    if ($messageId <= 0) {
        dent_error('????? ???? ??????? ???.', 422);
    }

    if ($text === '') {
        dent_error('???? ???? ???.', 422);
    }

    $messages = chat_read_messages();
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('???? ???? ???.', 404);
    }

    $ownerStudentNumber = dent_normalize_student_number((string) ($messages[$index]['username'] ?? ''));
    $canEdit = $ownerStudentNumber === (string) $user['studentNumber'] || dent_can_moderate_chat($user);
    if (!$canEdit) {
        dent_error('????? ?????? ??? ???? ?? ?????.', 403);
    }

    $messages[$index]['text'] = $text;
    $messages[$index]['editedAt'] = time();
    chat_write_messages($messages);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message($messages[$index]),
    ]);
}

if ($action === 'delete') {
    $user = chat_require_user();
    $messageId = (int) ($_POST['id'] ?? 0);

    if ($messageId <= 0) {
        dent_error('????? ???? ??????? ???.', 422);
    }

    $messages = chat_read_messages();
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('???? ???? ???.', 404);
    }

    $ownerStudentNumber = dent_normalize_student_number((string) ($messages[$index]['username'] ?? ''));
    $canDelete = $ownerStudentNumber === (string) $user['studentNumber'] || dent_can_moderate_chat($user);
    if (!$canDelete) {
        dent_error('????? ??? ??? ???? ?? ?????.', 403);
    }

    array_splice($messages, $index, 1);
    chat_write_messages($messages);

    dent_json_response([
        'success' => true,
        'deletedId' => $messageId,
    ]);
}

if ($action === 'pin') {
    chat_require_moderator();

    $messageId = (int) ($_POST['id'] ?? 0);
    $shouldPin = (string) ($_POST['pinned'] ?? '1') === '1';

    if ($messageId <= 0) {
        dent_error('????? ???? ??????? ???.', 422);
    }

    $messages = chat_read_messages();
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('???? ???? ???.', 404);
    }

    $messages[$index]['pinned'] = $shouldPin;
    chat_write_messages($messages);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message($messages[$index]),
    ]);
}

if ($action === 'react') {
    chat_require_user();

    $messageId = (int) ($_POST['id'] ?? 0);
    $emoji = chat_sanitize_emoji((string) ($_POST['emoji'] ?? ''));

    if ($messageId <= 0) {
        dent_error('????? ???? ??????? ???.', 422);
    }

    if ($emoji === '') {
        dent_error('????? ??????? ???.', 422);
    }

    $messages = chat_read_messages();
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('???? ???? ???.', 404);
    }

    if (!isset($messages[$index]['reactions']) || !is_array($messages[$index]['reactions'])) {
        $messages[$index]['reactions'] = [];
    }

    if (!isset($messages[$index]['reactions'][$emoji]) || !is_array($messages[$index]['reactions'][$emoji])) {
        $messages[$index]['reactions'][$emoji] = [];
    }

    $studentNumber = (string) (dent_current_user()['studentNumber'] ?? '');
    $currentUsers = array_values(array_filter(
        $messages[$index]['reactions'][$emoji],
        static fn($value): bool => is_string($value) && trim($value) !== ''
    ));

    if (in_array($studentNumber, $currentUsers, true)) {
        $currentUsers = array_values(array_filter(
            $currentUsers,
            static fn(string $value): bool => $value !== $studentNumber
        ));
    } else {
        $currentUsers[] = $studentNumber;
    }

    if ($currentUsers === []) {
        unset($messages[$index]['reactions'][$emoji]);
    } else {
        $messages[$index]['reactions'][$emoji] = $currentUsers;
    }

    chat_write_messages($messages);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message($messages[$index]),
    ]);
}

if ($action === 'setMute') {
    $user = chat_require_moderator();

    $state = [
        'muted' => (string) ($_POST['muted'] ?? '0') === '1',
        'mutedBy' => (string) $user['studentNumber'],
        'mutedAt' => time(),
    ];

    chat_write_state($state);

    dent_json_response([
        'success' => true,
        'state' => $state,
    ]);
}

dent_error('??????? ??????? ???.', 404);
