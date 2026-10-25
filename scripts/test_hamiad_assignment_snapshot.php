<?php
declare(strict_types=1);
$root = sys_get_temp_dir() . '/dent-hamiad-snapshot-test-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $root);
putenv('DENT_SERVER_ONLY_ROOT=' . $root . '/server-only');
require_once dirname(__DIR__) . '/public_html/api/hamiad_dashboard.php';
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
    verify(hamiad_assignment_snapshot_read() === null, 'missing snapshot is not invented');
    $snapshot = [
        'connected'=>true, 'courses'=>[['id'=>21,'title'=>'اندو','assignmentCount'=>1]],
        'assignments'=>[['id'=>88,'courseId'=>21,'title'=>'تکلیف','opensText'=>'۱۴۰۵/۹/۱',
            'deadlineText'=>'۱۴۰۵/۹/۲۵','dueStatus'=>'not_open']], 'resources'=>0,
    ];
    hamiad_assignment_snapshot_save($snapshot);
    $loaded=hamiad_assignment_snapshot_read();
    verify(($loaded['assignments'][0]['deadlineText']??'')==='۱۴۰۵/۹/۲۵','snapshot preserves deadline');
    verify(($loaded['assignments'][0]['opensText']??'')==='۱۴۰۵/۹/۱','snapshot preserves start');
    $fallback=hamiad_term7_dashboard(); // No LMS session; cache must remain visible.
    verify(!empty($fallback['stale']) && empty($fallback['connected']),'disconnected account shows stale state');
    verify(count($fallback['assignments'])===1, 'disconnected account does not delete tasks');
    echo "HAMIAD_SNAPSHOT_CONTRACT_OK\n";
} finally {
    if (is_dir($root)) {
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $node) { $node->isDir()?@rmdir($node->getPathname()):@unlink($node->getPathname()); }
        @rmdir($root);
    }
}
