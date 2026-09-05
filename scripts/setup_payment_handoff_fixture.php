<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('DENT_HANDOFF_TEST') !== '1' || getenv('DENT_APP_ENV') !== 'test') {
    exit(2);
}
require __DIR__ . '/setup_bot_api_http_fixture.php';
$owner = dent_get_user_record(dent_owner_student_number());
$challenge = dent_bot_start_link('bale', '654322', ['authVersion' => dent_bot_canonical_auth_version()]);
parse_str((string) parse_url($challenge['linkUrl'], PHP_URL_QUERY), $parameters);
dent_bot_confirm_link($parameters['token'], $owner);
payments_with_store_lock(static function (array &$store): void {
    $store['gatewaySettings'] = ['managed' => true, 'default' => 'zibal'];
    $store['gateways'] = [[
        'id' => 2, 'key' => 'zibal', 'provider' => 'zibal', 'label' => 'Synthetic Zibal',
        'merchant_id' => 'synthetic-merchant', 'is_enabled' => true, 'is_default' => true,
        'request_url' => getenv('DENT_PAYMENT_ZIBAL_REQUEST_URL'),
        'verify_url' => getenv('DENT_PAYMENT_ZIBAL_VERIFY_URL'),
        'start_url' => 'https://gateway.zibal.ir/start/',
    ]];
});
