from __future__ import annotations

import json
import shutil
import subprocess
import unittest
from pathlib import Path


@unittest.skipUnless(shutil.which("php"), "PHP CLI is unavailable")
class ExternalBookletPhpContractTests(unittest.TestCase):
    def test_private_otp_profile_without_public_is_class_member_flag(self) -> None:
        """Reproduce the real private/public onboarding profile mismatch."""
        source = (Path(__file__).resolve().parents[2] / "public_html/api/bot_store.php").read_text()
        start = source.index("function dent_bot_booklet_watermark_identity(")
        end = source.index("\nfunction dent_bot_normalize_identity_name", start)
        php_function = source[start:end]
        stubs = """<?php
function dent_clean_text($value, $limit) { return substr($value, 0, $limit); }
function dent_dis_request_private_identity_for_student($student) { return null; }
function dent_normalize_national_code($value) { return (string) $value; }
function dent_normalize_phone_number($value) { return (string) $value; }
function dent_bot_onboarding_private_profile($platform, $userId) {
    return $GLOBALS['profile'];
}
function dent_bot_onboarding_profile_is_primary_class_member($profile) {
    return ($profile['studentNumber'] ?? '') === '402-class';
}
function dent_error($message, $status, $details = []) {
    throw new RuntimeException((string) ($details['code'] ?? 'error'));
}
"""
        scenarios = """
$external = ['name' => 'Verified External Student', 'studentNumber' => 'ext-20', 'role' => 'external_exam_user'];
$GLOBALS['profile'] = ['studentNumber' => 'ext-20', 'phoneNumber' => '09123456789', 'verifiedAt' => '2026-10-23T10:00:00Z'];
$valid = dent_bot_booklet_watermark_identity($external, ['contractVersion' => 'booklet-watermark-identity-v1'], 'telegram', '20');
$accepted = ($valid['identity']['identityMethod'] === 'verified_mobile') && $valid['identity']['nationalCode'] === '';
$deniedClass = false;
$GLOBALS['profile']['studentNumber'] = '402-class';
try { dent_bot_booklet_watermark_identity($external, ['contractVersion' => 'booklet-watermark-identity-v1'], 'telegram', '20'); }
catch (RuntimeException $e) { $deniedClass = $e->getMessage() === 'BOOKLET_IDENTITY_INCOMPLETE'; }
$deniedUnverified = false;
$GLOBALS['profile'] = ['studentNumber' => 'ext-20', 'phoneNumber' => '09123456789'];
try { dent_bot_booklet_watermark_identity($external, ['contractVersion' => 'booklet-watermark-identity-v1'], 'telegram', '20'); }
catch (RuntimeException $e) { $deniedUnverified = $e->getMessage() === 'BOOKLET_IDENTITY_INCOMPLETE'; }
echo json_encode(['accepted' => $accepted, 'deniedClass' => $deniedClass, 'deniedUnverified' => $deniedUnverified]);
"""
        done = subprocess.run(
            ["php"], input=stubs + php_function + scenarios,
            text=True, capture_output=True, timeout=15, check=False,
        )
        self.assertEqual(done.returncode, 0, done.stderr)
        self.assertEqual(json.loads(done.stdout), {
            "accepted": True, "deniedClass": True, "deniedUnverified": True,
        })
