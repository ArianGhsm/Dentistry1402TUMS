<?php
// scripts/migrate_dm_store.php
// Canonicalize 1:1 DM conversation IDs in server-only/storage/chat/store.json
// Usage: php scripts/migrate_dm_store.php --apply

$storeRel = __DIR__ . '/../server-only/storage/chat/store.json';
$storePath = realpath($storeRel) ?: $storeRel;

if (!file_exists($storePath)) {
    fwrite(STDERR, "Error: store file not found: $storePath\n");
    exit(2);
}

$raw = file_get_contents($storePath);
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Error: failed to parse JSON from store file.\n");
    exit(3);
}

$backupPath = $storePath . '.bak.' . date('YmdHis');
if (!copy($storePath, $backupPath)) {
    fwrite(STDERR, "Error: failed to create backup at $backupPath\n");
    exit(4);
}

// Collect renames: oldId => newId
$renames = [];
foreach ($data['conversations'] as $cid => $conv) {
    $type = isset($conv['type']) ? strtolower($conv['type']) : '';
    if ($type === 'direct' || strpos($cid, 'dm:') === 0) {
        $members = isset($conv['memberStudentNumbers']) && is_array($conv['memberStudentNumbers'])
            ? array_values($conv['memberStudentNumbers']) : [];
        if (count($members) === 2) {
            $a = (string)$members[0];
            $b = (string)$members[1];
            if (strcmp($a, $b) > 0) {
                list($a, $b) = [$b, $a];
            }
            $newId = 'dm:' . $a . ':' . $b;
            if ($newId !== $cid) $renames[$cid] = $newId;
        }
    }
}

if (empty($renames)) {
    echo "No DM conversation IDs required renaming. Backup created: $backupPath\n";
    exit(0);
}

// Apply renames/merges
$changes = [];
foreach ($renames as $old => $new) {
    if (!isset($data['conversations'][$old])) continue;
    $oldConv = $data['conversations'][$old];

    // Ensure canonical members
    $members = isset($oldConv['memberStudentNumbers']) ? array_values($oldConv['memberStudentNumbers']) : [];
    if (count($members) === 2) {
        $a = (string)$members[0]; $b = (string)$members[1];
        if (strcmp($a, $b) > 0) list($a, $b) = [$b, $a];
        $canonicalMembers = [$a, $b];
    } else {
        $canonicalMembers = $members;
    }

    // Prepare target conversation
    if (!isset($data['conversations'][$new])) {
        $newConv = $oldConv;
        $newConv['id'] = $new;
        $newConv['memberStudentNumbers'] = $canonicalMembers;
        $newConv['directParticipants'] = $canonicalMembers;
        $data['conversations'][$new] = $newConv;
    } else {
        // merge basic fields conservatively
        $exist = $data['conversations'][$new];
        $exist['memberStudentNumbers'] = array_values(array_unique(array_merge($exist['memberStudentNumbers'] ?? [], $canonicalMembers)));
        $exist['directParticipants'] = array_values(array_unique(array_merge($exist['directParticipants'] ?? [], $canonicalMembers)));
        $exist['admins'] = array_values(array_unique(array_merge($exist['admins'] ?? [], $oldConv['admins'] ?? [])));
        $exist['createdAt'] = min(intval($exist['createdAt'] ?? PHP_INT_MAX), intval($oldConv['createdAt'] ?? PHP_INT_MAX));
        $exist['updatedAt'] = max(intval($exist['updatedAt'] ?? 0), intval($oldConv['updatedAt'] ?? 0));
        $data['conversations'][$new] = $exist;
    }

    // Move/merge messages
    $oldMsgs = isset($data['messages'][$old]) && is_array($data['messages'][$old]) ? $data['messages'][$old] : [];
    $newMsgs = isset($data['messages'][$new]) && is_array($data['messages'][$new]) ? $data['messages'][$new] : [];
    $merged = array_merge($newMsgs, $oldMsgs);
    // dedupe by numeric message id and set conversationId
    $seen = [];
    $unique = [];
    foreach ($merged as $msg) {
        $mid = isset($msg['id']) ? intval($msg['id']) : null;
        if ($mid === null) continue;
        if (isset($seen[$mid])) continue;
        $seen[$mid] = true;
        $msg['conversationId'] = $new;
        $unique[] = $msg;
    }
    usort($unique, function($a,$b){ return intval($a['id']) - intval($b['id']); });
    $data['messages'][$new] = $unique;
    if (isset($data['messages'][$old])) unset($data['messages'][$old]);

    // Merge reads
    if (isset($data['reads'][$old])) {
        if (!isset($data['reads'][$new])) $data['reads'][$new] = [];
        foreach ($data['reads'][$old] as $user => $info) {
            if (!isset($data['reads'][$new][$user])) {
                $data['reads'][$new][$user] = $info;
            } else {
                $existing = $data['reads'][$new][$user];
                $existing['lastReadMessageId'] = max(intval($existing['lastReadMessageId'] ?? 0), intval($info['lastReadMessageId'] ?? 0));
                $existing['lastReadAt'] = max(intval($existing['lastReadAt'] ?? 0), intval($info['lastReadAt'] ?? 0));
                $existing['archived'] = !empty($existing['archived']) || !empty($info['archived']);
                $existing['deleted'] = !empty($existing['deleted']) || !empty($info['deleted']);
                $data['reads'][$new][$user] = $existing;
            }
        }
        unset($data['reads'][$old]);
    }

    // Update attachments referring to old conversation
    if (isset($data['attachments']) && is_array($data['attachments'])) {
        foreach ($data['attachments'] as $aid => &$att) {
            if (isset($att['conversationId']) && $att['conversationId'] === $old) {
                $att['conversationId'] = $new;
            }
        }
        unset($att);
    }

    // Remove old conversation entry
    if (isset($data['conversations'][$old])) unset($data['conversations'][$old]);

    $changes[] = "$old -> $new";
}

// Write back
$tmp = $storePath . '.tmp.' . uniqid();
$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    fwrite(STDERR, "Error: failed to encode JSON. Aborting.\n");
    exit(5);
}
if (file_put_contents($tmp, $encoded) === false) {
    fwrite(STDERR, "Error: failed to write temp file $tmp\n");
    exit(6);
}
if (!rename($tmp, $storePath)) {
    fwrite(STDERR, "Error: failed to replace store file.\n");
    exit(7);
}

echo "Migration applied. Backup: $backupPath\n";
echo "Renamed/merged " . count($changes) . " conversations:\n";
foreach ($changes as $c) echo " - $c\n";
echo "Done.\n";

exit(0);

?>
