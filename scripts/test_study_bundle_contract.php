<?php
declare(strict_types=1);
require_once __DIR__ . '/../public_html/api/bot_cart_payments.php';

function dent_error(string $message, int $statusCode = 400, array $extra = []): void {
    throw new RuntimeException((string) ($extra['code'] ?? $message));
}
function assert_valid(array $data, array $items, int $subtotal): void {
    dent_bot_payment_validate_study_bundle($data, $items, $subtotal);
}
function assert_rejected(array $data, array $items, int $subtotal): void {
    try {
        dent_bot_payment_validate_study_bundle($data, $items, $subtotal);
    } catch (RuntimeException $error) {
        if (str_starts_with($error->getMessage(), 'STUDY_BUNDLE_')) {
            return;
        }
        throw $error;
    }
    throw new RuntimeException('Invalid campaign was accepted');
}
$endo = static fn(int $session): array => [
    'kind' => 'ai_booklet',
    'courseCode' => 'endodontics-basics-2',
    'term' => 7,
    'sessionNo' => $session,
    'amountRials' => 390000,
];
$dentnote = [
    'kind' => 'offer',
    'amountRials' => 390000,
    'title' => 'دنت‌نوت پاتولوژی عملی ۱',
    'fulfillment' => ['kind' => 'paid_file'],
];
$pair = [$endo(9), $endo(13)];
$triple = [...$pair, $dentnote];
$rule2 = ['code' => 'ENDO1405_2', 'kind' => 'fixed', 'amount' => 80000, 'minSubtotalRials' => 780000, 'maxUses' => 0];
$rule3 = ['code' => 'ENDO1405_3', 'kind' => 'fixed', 'amount' => 270000, 'minSubtotalRials' => 1170000, 'maxUses' => 0];
assert_valid($rule2, $pair, 780000);
assert_valid($rule3, $triple, 1170000);
assert_rejected($rule3, [...$pair, ['kind'=>'offer','amountRials'=>390000,'title'=>'محصول دیگر','fulfillment'=>['kind'=>'paid_file']]], 1170000);
assert_rejected($rule3, [...$pair, ['kind'=>'offer','amountRials'=>390000,'title'=>'دنت‌نوت پاتولوژی','fulfillment'=>['kind'=>'text']]], 1170000);
assert_rejected($rule2, [$endo(9), $endo(9)], 780000);
assert_rejected($rule2, [$endo(9), $endo(12)], 780000);
assert_rejected($rule2, [$endo(9), $endo(13), $dentnote], 1170000);
assert_rejected([...$rule2, 'amount'=>200000], $pair, 780000);
assert_rejected([...$rule3, 'minSubtotalRials'=>1000000], $triple, 1170000);
assert_rejected($rule3, [$endo(9), $endo(13), [...$dentnote, 'amountRials'=>550000]], 1330000);
echo "Study bundle contract: all positive and negative cases passed\n";
