<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/notifications_store.php';
require_once dirname(__DIR__) . '/public_html/api/academic_term7.php';

function classops_v3_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$timezone = new DateTimeZone('Asia/Tehran');
$start = dent_term7_academic_context(new DateTimeImmutable('2026-09-09 12:00:00', $timezone));
$boundary = dent_term7_academic_context(new DateTimeImmutable('2027-02-12 12:00:00', $timezone));
$after = dent_term7_academic_context(new DateTimeImmutable('2027-02-13 12:00:00', $timezone));

classops_v3_assert(($start['term'] ?? null) === 7, 'Term 7 must be active on 1405/06/18');
classops_v3_assert(($boundary['currentJalaliDate'] ?? '') === '1405/11/23', 'Boundary date mismatch');
classops_v3_assert(($boundary['term'] ?? null) === 7, 'Term 7 must include 1405/11/23');
classops_v3_assert(($after['currentJalaliDate'] ?? '') === '1405/11/24', 'Post-boundary date mismatch');
classops_v3_assert(($after['term'] ?? null) === null, 'Term 7 must stop after 1405/11/23');
classops_v3_assert(($after['state'] ?? '') === 'after_window', 'Post-boundary state must be explicit');

echo "ClassOps UX V3 academic term checks passed.\n";
