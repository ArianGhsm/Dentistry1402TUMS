<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/exams_store.php';
require_once __DIR__ . '/payments_store.php';
require_once __DIR__ . '/exams_radiology2_overrides.php';

final class DentExamsApiException extends RuntimeException
{
    private int $statusCode;
    private array $payload;

    public function __construct(string $message, int $statusCode = 422, array $payload = [])
    {
        parent::__construct($message);
        $this->statusCode = max(400, min(599, $statusCode));
        $this->payload = $payload;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}

function dent_exams_api_apply_runtime_exam_override(string $courseSlug, array $exam): array
{
    if ($courseSlug === 'radiology2' && function_exists('dent_exams_radiology2_overrides')) {
        $slug = trim((string) ($exam['slug'] ?? ''));
        if ($slug !== '') {
            $overrides = dent_exams_radiology2_overrides();
            $override = $overrides[$slug] ?? null;
            if (is_array($override)) {
                foreach ($override as $key => $value) {
                    $exam[$key] = $value;
                }
            }
        }
    }

    $exam['questionCount'] = dent_exams_api_resolve_exam_question_count($exam);
    return $exam;
}

function dent_exams_api_apply_runtime_course_override(array $course): array
{
    $courseSlug = dent_exams_clean_course_slug((string) ($course['slug'] ?? ''));
    if ($courseSlug === '' || !is_array($course['exams'] ?? null)) {
        return $course;
    }

    $nextExams = [];
    foreach ($course['exams'] as $exam) {
        $nextExams[] = is_array($exam)
            ? dent_exams_api_apply_runtime_exam_override($courseSlug, $exam)
            : $exam;
    }
    $course['exams'] = $nextExams;

    return $course;
}

function dent_exams_api_resolve_exam_question_count(array $exam): int
{
    $questions = $exam['questions'] ?? null;
    if (is_array($questions)) {
        return count($questions);
    }

    return max(0, (int) ($exam['questionCount'] ?? 0));
}

function dent_exams_api_require_method(array $methods): void
{
    $method = dent_request_method();
    if (!in_array($method, $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function dent_exams_api_payment_highlights(): array
{
    return [
        'طراحی شده صرفا بر اساس جزوات',
        'با بروزترین مدل های هوش مصنوعی و الگو گیری از سوالات رزیدنتی + آزمون های سال های قبل',
        'همراه با درصدگیری، پاسخ تشریحی و توضیح کامل',
        'هرگونه پیشنهاد برای بهتر شدن آزمون‌ها را به نماینده اطلاع دهید. پیشنهاد شما در صورت امکان، «حتما و فورا» برای آزمون های بعدی، در نظر گرفته می شود.',
    ];
}

function dent_exams_api_money(int $amount): string
{
    return number_format(max(0, $amount)) . ' ریال';
}

function dent_exams_api_absolute_url(string $path): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $isSecure = $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    return ($isSecure ? 'https://' : 'http://') . $host . $path;
}

function dent_exams_api_collection_for_setting(array $paymentsStore, array $setting): ?array
{
    $collectionId = max(0, (int) ($setting['collectionId'] ?? 0));
    if ($collectionId <= 0) {
        return null;
    }

    $index = payments_find_collection_index_by_id($paymentsStore, $collectionId);
    if ($index < 0 || !is_array($paymentsStore['collections'][$index] ?? null)) {
        return null;
    }

    return $paymentsStore['collections'][$index];
}

function dent_exams_api_order_matches_collection(array $order, int $collectionId): bool
{
    if ($collectionId <= 0) {
        return false;
    }

    $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
    return (string) ($extra['_source'] ?? '') === 'collection'
        && (int) ($extra['collection_id'] ?? 0) === $collectionId;
}

function dent_exams_api_collection_orders(array $paymentsStore, int $collectionId): array
{
    $orders = [];
    foreach (($paymentsStore['orders'] ?? []) as $order) {
        if (!is_array($order) || !dent_exams_api_order_matches_collection($order, $collectionId)) {
            continue;
        }
        $orders[] = $order;
    }

    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return $orders;
}

function dent_exams_api_user_paid_order(array $paymentsStore, int $collectionId, ?array $user): ?array
{
    if ($collectionId <= 0 || !is_array($user)) {
        return null;
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ($user['student_number'] ?? '')));
    if ($studentNumber === '') {
        return null;
    }

    foreach (dent_exams_api_collection_orders($paymentsStore, $collectionId) as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        if (
            $studentNumber === dent_normalize_student_number((string) ($order['user_id'] ?? ''))
            || $studentNumber === dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''))
        ) {
            return $order;
        }
    }

    return null;
}

function dent_exams_api_collection_stats(array $paymentsStore, int $collectionId): array
{
    $totalOrders = 0;
    $successCount = 0;
    $receivedAmount = 0;
    foreach (dent_exams_api_collection_orders($paymentsStore, $collectionId) as $order) {
        $totalOrders++;
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $successCount++;
        $receivedAmount += max(0, (int) ($order['amount'] ?? 0));
    }

    return [
        'totalOrders' => $totalOrders,
        'successCount' => $successCount,
        'receivedAmount' => $receivedAmount,
    ];
}

function dent_exams_api_paid_order_summary(?array $order): ?array
{
    if (!is_array($order)) {
        return null;
    }

    return [
        'status' => (string) ($order['status'] ?? ''),
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
    ];
}

function dent_exams_api_is_owner(?array $user): bool
{
    return is_array($user) && (string) ($user['role'] ?? '') === 'owner';
}

function dent_exams_api_course_access(?array $user, array $setting, ?array $collection, array $paymentsStore): array
{
    $isOwner = dent_exams_api_is_owner($user);
    $mode = (string) ($setting['paymentMode'] ?? 'free');
    $isPaidCourse = $mode === 'paid';
    $paidOrder = dent_exams_api_user_paid_order($paymentsStore, max(0, (int) ($setting['collectionId'] ?? 0)), $user);
    $isProsthesis = is_array($user) && dent_user_is_prosthesis($user);

    if ($isOwner) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'owner',
            'unlockLabel' => 'دسترسی مالک',
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => $isPaidCourse,
            'paidOrder' => dent_exams_api_paid_order_summary($paidOrder),
        ];
    }

    if (!$isPaidCourse) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'free',
            'unlockLabel' => 'رایگان',
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => false,
            'paidOrder' => dent_exams_api_paid_order_summary($paidOrder),
        ];
    }

    if ($paidOrder !== null) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'paid',
            'unlockLabel' => 'پرداخت تایید شده',
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => dent_exams_api_paid_order_summary($paidOrder),
        ];
    }

    if (!is_array($user)) {
        return [
            'hasAccess' => false,
            'unlockKey' => 'login-required',
            'unlockLabel' => 'نیاز به ورود',
            'requiresLogin' => true,
            'requiresPayment' => true,
            'canPurchase' => true,
            'isPaidCourse' => true,
            'paidOrder' => null,
        ];
    }

    if ($isProsthesis) {
        return [
            'hasAccess' => false,
            'unlockKey' => 'unavailable',
            'unlockLabel' => 'غیرفعال برای این ورودی',
            'requiresLogin' => false,
            'requiresPayment' => true,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => null,
        ];
    }

    if ($collection === null || (string) ($collection['status'] ?? '') !== PAYMENTS_COLLECTION_STATUS_ACTIVE) {
        return [
            'hasAccess' => false,
            'unlockKey' => 'inactive',
            'unlockLabel' => 'پرداخت غیرفعال',
            'requiresLogin' => false,
            'requiresPayment' => true,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => null,
        ];
    }

    return [
        'hasAccess' => false,
        'unlockKey' => 'payment-required',
        'unlockLabel' => 'نیاز به پرداخت',
        'requiresLogin' => false,
        'requiresPayment' => true,
        'canPurchase' => true,
        'isPaidCourse' => true,
        'paidOrder' => null,
    ];
}

function dent_exams_api_course_summary_payload(
    string $catalogKey,
    array $course,
    array $setting,
    array $access,
    ?array $collection,
    array $paymentsStore,
    bool $includeExams = false,
    ?array $viewer = null
): array {
    $courseSlug = (string) ($course['slug'] ?? '');
    $paymentPath = '/exams/pay/?course=' . rawurlencode($courseSlug);
    $requestedCohort = dent_requested_cohort_key();
    $viewerIsOwner = dent_exams_api_is_owner($viewer);
    if ($requestedCohort !== '') {
        $paymentPath .= '&cohort=' . rawurlencode($requestedCohort);
    }

    $exams = [];
    if ($includeExams) {
        foreach (($course['exams'] ?? []) as $exam) {
            if (!is_array($exam)) {
                continue;
            }

            $examPath = (string) ($exam['path'] ?? '');
            $exams[] = [
                'slug' => (string) ($exam['slug'] ?? ''),
                'label' => (string) ($exam['label'] ?? ''),
                'title' => (string) ($exam['title'] ?? ''),
                'questionCount' => dent_exams_api_resolve_exam_question_count($exam),
                'path' => $examPath,
                'href' => $access['hasAccess'] ? $examPath : $paymentPath,
                'isLocked' => !$access['hasAccess'] && (bool) ($access['isPaidCourse'] ?? false),
            ];
        }
    }

    $collectionStats = dent_exams_api_collection_stats($paymentsStore, max(0, (int) ($setting['collectionId'] ?? 0)));

    return [
        'catalogKey' => $catalogKey,
        'slug' => $courseSlug,
        'title' => (string) ($course['title'] ?? ''),
        'shortTitle' => (string) ($course['shortTitle'] ?? ''),
        'badge' => (string) ($course['badge'] ?? ''),
        'cardDescription' => (string) ($course['cardDescription'] ?? ''),
        'heroTitle' => (string) ($course['heroTitle'] ?? ''),
        'heroDescription' => (string) ($course['heroDescription'] ?? ''),
        'path' => (string) ($course['path'] ?? ''),
        'paymentPath' => $paymentPath,
        'paymentUrl' => dent_exams_api_absolute_url($paymentPath),
        'paymentTitle' => (string) ($course['paymentTitle'] ?? ''),
        'paymentDescription' => (string) ($course['paymentDescription'] ?? ''),
        'paymentHighlights' => dent_exams_api_payment_highlights(),
        'paymentSuccessMessage' => (string) ($course['paymentSuccessMessage'] ?? ''),
        'paymentFailureMessage' => (string) ($course['paymentFailureMessage'] ?? ''),
        'paymentMode' => (string) ($setting['paymentMode'] ?? 'free'),
        'amount' => max(0, (int) ($setting['amount'] ?? 0)),
        'amountLabel' => dent_exams_api_money((int) ($setting['amount'] ?? 0)),
        'collectionId' => max(0, (int) ($setting['collectionId'] ?? 0)),
        'collectionToken' => $collection ? (string) ($collection['token'] ?? '') : '',
        'collectionStatus' => $collection ? (string) ($collection['status'] ?? '') : '',
        'access' => $access,
        'stats' => [
            'examCount' => count(is_array($course['exams'] ?? null) ? $course['exams'] : []),
            'questionCount' => array_sum(array_map(static function ($exam): int {
                if (!is_array($exam)) {
                    return 0;
                }
                return dent_exams_api_resolve_exam_question_count($exam);
            }, is_array($course['exams'] ?? null) ? $course['exams'] : [])),
            'totalOrders' => $collectionStats['totalOrders'],
            'successCount' => $viewerIsOwner ? $collectionStats['successCount'] : null,
            'receivedAmount' => $collectionStats['receivedAmount'],
            'showApprovedAccessCount' => $viewerIsOwner,
        ],
        'ownerSettings' => [
            'canManage' => $viewerIsOwner,
            'updatedAt' => (string) ($setting['updatedAt'] ?? ''),
        ],
        'exams' => $exams,
    ];
}

function dent_exams_api_course_or_fail(string $catalogKey, string $courseSlug): array
{
    $course = dent_exams_course($catalogKey, $courseSlug);
    if ($course === null) {
        throw new DentExamsApiException('درس آزمون پیدا نشد.', 404);
    }

    return $course;
}

function dent_exams_api_exam_or_fail(string $catalogKey, string $courseSlug, string $examSlug): array
{
    $exam = dent_exams_exam($catalogKey, $courseSlug, $examSlug);
    if ($exam === null) {
        throw new DentExamsApiException('آزمون موردنظر پیدا نشد.', 404);
    }

    return $exam;
}

function dent_exams_api_collection_base_payload(array $course, array $setting, ?array $currentCollection = null): array
{
    $existingAmount = max(0, (int) ($currentCollection['amount'] ?? 0));
    $desiredAmount = max(0, (int) ($setting['amount'] ?? 0));
    $mode = (string) ($setting['paymentMode'] ?? 'free');
    $amount = $mode === 'paid'
        ? max(1, $desiredAmount)
        : max(1, $existingAmount > 0 ? $existingAmount : $desiredAmount);
    $now = dent_iso_now();

    return [
        'title' => (string) ($course['paymentTitle'] ?? $course['title'] ?? 'پرداخت آزمون'),
        'description' => (string) ($course['paymentDescription'] ?? ''),
        'image_url' => '',
        'amount' => $amount,
        'status' => $mode === 'paid' ? PAYMENTS_COLLECTION_STATUS_ACTIVE : PAYMENTS_COLLECTION_STATUS_INACTIVE,
        'gateway' => '',
        'allow_guest_payments' => false,
        'collect_payer_name' => false,
        'collect_payer_phone' => false,
        'collect_payer_student_number' => false,
        'success_message' => (string) ($course['paymentSuccessMessage'] ?? ''),
        'failure_message' => (string) ($course['paymentFailureMessage'] ?? ''),
        'updated_at' => $now,
    ];
}

function dent_exams_api_sync_collection(string $catalogKey, string $courseSlug, array $course, array $setting): int
{
    $existingCollectionId = max(0, (int) ($setting['collectionId'] ?? 0));
    $mode = (string) ($setting['paymentMode'] ?? 'free');

    if ($existingCollectionId <= 0 && $mode !== 'paid') {
        return 0;
    }

    return payments_with_store_lock(static function (array &$paymentsStore) use (
        $existingCollectionId,
        $course,
        $setting
    ): int {
        $collectionIndex = $existingCollectionId > 0
            ? payments_find_collection_index_by_id($paymentsStore, $existingCollectionId)
            : -1;
        $current = $collectionIndex >= 0 && is_array($paymentsStore['collections'][$collectionIndex] ?? null)
            ? $paymentsStore['collections'][$collectionIndex]
            : null;
        $payload = dent_exams_api_collection_base_payload($course, $setting, is_array($current) ? $current : null);

        if ($collectionIndex >= 0 && is_array($current)) {
            $payload = array_merge($current, $payload, [
                'id' => (int) ($current['id'] ?? $existingCollectionId),
                'token' => (string) ($current['token'] ?? payments_random_token(12)),
                'created_at' => (string) ($current['created_at'] ?? dent_iso_now()),
            ]);
            $paymentsStore['collections'][$collectionIndex] = $payload;
            return (int) ($payload['id'] ?? $existingCollectionId);
        }

        $newId = payments_next_collection_id($paymentsStore);
        $payload = array_merge($payload, [
            'id' => $newId,
            'token' => payments_random_token(12),
            'created_at' => dent_iso_now(),
        ]);
        $paymentsStore['collections'][] = $payload;
        return $newId;
    });
}

function dent_exams_api_persist_course_setting(string $catalogKey, string $courseSlug, array $setting): void
{
    $normalizedSetting = dent_exams_normalize_course_setting($setting);
    dent_exams_with_store_lock(static function (array &$store) use ($catalogKey, $courseSlug, $normalizedSetting): void {
        $courseKey = dent_exams_course_key($catalogKey, $courseSlug);
        if ($courseKey === '') {
            throw new DentExamsApiException('شناسه داخلی درس معتبر نیست.', 422);
        }

        $store['courseSettings'][$courseKey] = $normalizedSetting;
    });
}

function dent_exams_api_resolve_course_setting(
    array $examsStore,
    array $paymentsStore,
    string $catalogKey,
    string $courseSlug,
    array $course
): array {
    $setting = dent_exams_course_setting($examsStore, $catalogKey, $courseSlug);
    if ((string) ($setting['paymentMode'] ?? 'free') !== 'paid') {
        return $setting;
    }

    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    if ($collection !== null) {
        return $setting;
    }

    $nextSetting = $setting;
    $nextSetting['updatedAt'] = dent_iso_now();
    $collectionId = dent_exams_api_sync_collection($catalogKey, $courseSlug, $course, $nextSetting);
    if ($collectionId <= 0) {
        return $setting;
    }

    $nextSetting['collectionId'] = $collectionId;
    dent_exams_api_persist_course_setting($catalogKey, $courseSlug, $nextSetting);

    return $nextSetting;
}

function dent_exams_api_current_course_summary(string $catalogKey, string $courseSlug): array
{
    $user = dent_current_user();
    $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $paymentsStore = payments_read_store();
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore);
    return dent_exams_api_course_summary_payload($catalogKey, $course, $setting, $access, $collection, $paymentsStore, true, $user);
}

$action = dent_clean_text((string) ($_REQUEST['action'] ?? ''), 60);
$catalogKey = dent_exams_resolve_catalog_key(dent_requested_cohort_key());

if ($action === 'catalog') {
    dent_exams_api_require_method(['GET']);

    $catalog = dent_exams_catalog($catalogKey);
    if ($catalog === null) {
        dent_error('کاتالوگ آزمون‌ها پیدا نشد.', 404);
    }

    $user = dent_current_user();
    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $courses = [];
    foreach (($catalog['courses'] ?? []) as $courseSlug => $course) {
        if (!is_array($course)) {
            continue;
        }
        $course = dent_exams_api_apply_runtime_course_override($course);
        $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, (string) $courseSlug, $course);
        $paymentsStore = payments_read_store();
        $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
        $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore);
        $courses[] = dent_exams_api_course_summary_payload($catalogKey, $course, $setting, $access, $collection, $paymentsStore, false, $user);
    }

    dent_json_response([
        'success' => true,
        'catalog' => [
            'catalogKey' => $catalogKey,
            'requestedCohort' => dent_requested_cohort_key(),
            'title' => (string) ($catalog['title'] ?? 'آزمون‌ها'),
            'description' => (string) ($catalog['description'] ?? ''),
            'courses' => $courses,
        ],
        'viewer' => $user ? dent_public_user($user) : null,
    ]);
}

if ($action === 'course') {
    dent_exams_api_require_method(['GET']);

    $courseSlug = dent_exams_clean_course_slug((string) ($_GET['course'] ?? ''));
    if ($courseSlug === '') {
        dent_error('شناسه درس آزمون معتبر نیست.', 422);
    }

    try {
        $payload = dent_exams_api_current_course_summary($catalogKey, $courseSlug);
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    dent_json_response([
        'success' => true,
        'course' => $payload,
        'viewer' => dent_current_user() ? dent_public_user(dent_current_user()) : null,
    ]);
}

if ($action === 'exam') {
    dent_exams_api_require_method(['GET']);

    $courseSlug = dent_exams_clean_course_slug((string) ($_GET['course'] ?? ''));
    $examSlug = trim((string) ($_GET['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
        $exam = dent_exams_api_apply_runtime_exam_override($courseSlug, dent_exams_api_exam_or_fail($catalogKey, $courseSlug, $examSlug));
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $user = dent_current_user();
    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $paymentsStore = payments_read_store();
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore);
    $coursePayload = dent_exams_api_course_summary_payload($catalogKey, $course, $setting, $access, $collection, $paymentsStore, false, $user);

    if (!(bool) ($access['hasAccess'] ?? false)) {
        if ((bool) ($access['requiresLogin'] ?? false)) {
            dent_error('برای مشاهده این آزمون باید وارد حساب کاربری شوید.', 401, [
                'loggedOut' => true,
                'course' => $coursePayload,
            ]);
        }

        dent_error('برای مشاهده سوال‌های این درس باید دسترسی آن را فعال کنید.', 403, [
            'requiresPayment' => true,
            'course' => $coursePayload,
        ]);
    }

    dent_json_response([
        'success' => true,
        'course' => $coursePayload,
        'exam' => $exam,
        'viewer' => $user ? dent_public_user($user) : null,
    ]);
}

if ($action === 'ownerSaveCourseAccess') {
    dent_exams_api_require_method(['POST']);
    dent_require_owner();

    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    if ($courseSlug === '') {
        dent_error('شناسه درس آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_course_or_fail($catalogKey, $courseSlug);
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $rawMode = trim(strtolower((string) ($_POST['paymentMode'] ?? '')));
    if ($rawMode === '') {
        $isPaid = dent_parse_bool($_POST['isPaid'] ?? false, false);
        $rawMode = $isPaid ? 'paid' : 'free';
    }
    if (!in_array($rawMode, ['free', 'paid'], true)) {
        dent_error('حالت دسترسی آزمون معتبر نیست.', 422);
    }

    $amount = max(0, (int) dent_normalize_digits((string) ($_POST['amount'] ?? 0)));
    if ($rawMode === 'paid' && $amount <= 0) {
        dent_error('برای درس پولی باید مبلغ معتبر ثبت شود.', 422);
    }

    $currentStore = dent_exams_read_store();
    $currentSetting = dent_exams_course_setting($currentStore, $catalogKey, $courseSlug);
    $nextSetting = [
        'paymentMode' => $rawMode,
        'amount' => $amount,
        'collectionId' => max(0, (int) ($currentSetting['collectionId'] ?? 0)),
        'updatedAt' => dent_iso_now(),
    ];
    $collectionId = dent_exams_api_sync_collection($catalogKey, $courseSlug, $course, $nextSetting);
    if ($collectionId > 0) {
        $nextSetting['collectionId'] = $collectionId;
    }

    dent_exams_with_store_lock(static function (array &$store) use ($catalogKey, $courseSlug, $nextSetting): void {
        $courseKey = dent_exams_course_key($catalogKey, $courseSlug);
        if ($courseKey === '') {
            throw new DentExamsApiException('شناسه داخلی درس معتبر نیست.', 422);
        }

        $store['courseSettings'][$courseKey] = $nextSetting;
    });

    dent_json_response([
        'success' => true,
        'course' => dent_exams_api_current_course_summary($catalogKey, $courseSlug),
        'message' => $rawMode === 'paid'
            ? 'پرداخت این درس فعال شد و مبلغ جدید ذخیره شد.'
            : 'این درس به‌صورت رایگان تنظیم شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
