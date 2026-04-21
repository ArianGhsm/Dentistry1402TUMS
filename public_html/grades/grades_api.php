<?php
// خروجی‌ها به صورت JSON
header('Content-Type: application/json; charset=utf-8');

// مسیر فایل CSV نمرات
$csvFile = __DIR__ . '/grades.csv';

// تابع کمکی برای ارسال JSON
function sendJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function sendError($message) {
    sendJson(['error' => $message]);
}

/**
 * تبدیل اعداد فارسی/عربی به انگلیسی + یکسان‌سازی جداکننده اعشار
 */
function normalizeNumberString($str) {
    if ($str === null) return '';
    $str = trim((string)$str);

    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];

    $str = str_replace($fa, $en, $str);
    $str = str_replace($ar, $en, $str);

    // جداکننده‌های رایج فارسی
    $str = str_replace(['٫', '٬', '،'], ['.', '', ''], $str);

    // اگر اعشار با ویرگول بود
    $str = str_replace(',', '.', $str);

    return trim($str);
}

/**
 * پارس نمره:
 * - اگر خالی/غیرعددی بود => null
 * - اگر 0 یا کمتر بود => null (فرض: 0.00 یعنی نمره ثبت نشده)
 */
function parseScore($val) {
    $s = normalizeNumberString($val);
    if ($s === '') return null;
    if (!is_numeric($s)) return null;

    $num = (float)$s;

    // اگر می‌خوای 0 هم نمره محسوب بشه، این خط رو به ($num < 0) تغییر بده
    if ($num <= 0) return null;

    return $num;
}

// اطلاعات درخواست
$action     = $_POST['action']      ?? '';
$studentId  = trim($_POST['studentId']  ?? '');
$password   = trim($_POST['password']   ?? '');

// برای اینکه اگر کسی اعداد را فارسی زد هم مشکلی پیش نیاید
$studentId = normalizeNumberString($studentId);
$password  = normalizeNumberString($password);

// ✅ اکشن ۱: بررسی نمرات
if ($action === 'check') {
    if ($studentId === '' || $password === '') {
        sendError('لطفاً شماره دانشجویی و رمز عبور را وارد کنید.');
    }

    if (!file_exists($csvFile)) {
        sendError('فایل نمرات پیدا نشد.');
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        sendError('امکان خواندن فایل نمرات وجود ندارد.');
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        sendError('هدر فایل نمرات نامعتبر است.');
    }

    // پیدا کردن ستون‌ها بر اساس اسم
    $idIndex   = array_search('StudentID', $header);
    $pwdIndex  = array_search('Password', $header);
    $nameIndex = array_search('Name', $header);

    if ($idIndex === false || $pwdIndex === false) {
        fclose($handle);
        sendError('ستون‌های StudentID یا Password در فایل نمرات پیدا نشدند.');
    }

    // تمام ستون‌های نمره: هرچی غیر از StudentID/Password/Name
    $gradeCols = []; // idx => label
    foreach ($header as $idx => $label) {
        if ($idx === $idIndex || $idx === $pwdIndex || $idx === $nameIndex) continue;
        $gradeCols[$idx] = $label;
    }

    if (count($gradeCols) === 0) {
        fclose($handle);
        sendError('هیچ ستون نمره‌ای در فایل پیدا نشد.');
    }

    // آمار هر ستون نمره
    $statsAcc = []; // idx => ['sum'=>0,'count'=>0,'scores'=>[]]
    foreach ($gradeCols as $gIdx => $gLabel) {
        $statsAcc[$gIdx] = ['sum' => 0.0, 'count' => 0, 'scores' => []];
    }

    $rows = [];

    // خواندن بقیه ردیف‌ها
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;

        // برای هر ستون نمره، میانگین/رتبه جدا حساب کن
        foreach ($gradeCols as $gIdx => $gLabel) {
            $score = isset($row[$gIdx]) ? parseScore($row[$gIdx]) : null;
            if ($score !== null) {
                $statsAcc[$gIdx]['sum'] += $score;
                $statsAcc[$gIdx]['count']++;
                $statsAcc[$gIdx]['scores'][] = $score;
            }
        }
    }
    fclose($handle);

    // پیدا کردن دانشجو
    $foundIndex = -1;
    foreach ($rows as $i => $row) {
        $sid = isset($row[$idIndex])  ? normalizeNumberString($row[$idIndex]) : '';
        $pwd = isset($row[$pwdIndex]) ? (string)$row[$pwdIndex] : '';

        // برای اطمینان، رمز را هم نرمال کن (فقط روی اعداد اثر دارد)
        $pwdNorm = normalizeNumberString($pwd);

        if ($sid === $studentId && ($pwd === $password || $pwdNorm === $password)) {
            $foundIndex = $i;
            break;
        }
    }

    if ($foundIndex === -1) {
        sendError('شماره دانشجویی یا رمز اشتباه است.');
    }

    $row  = $rows[$foundIndex];
    $name = ($nameIndex !== false && isset($row[$nameIndex])) ? $row[$nameIndex] : '';

    // ساختن لیست نمره‌ها (برای نمایش)
    $grades = [];
    foreach ($header as $idx => $label) {
        if ($idx === $idIndex || $idx === $pwdIndex || $idx === $nameIndex) continue;
        $value = isset($row[$idx]) ? $row[$idx] : '';
        $grades[] = ['label' => $label, 'value' => $value];
    }

    // ساختن stats برای هر ستون: میانگین کلاس + رتبه + تعداد افراد دارای نمره
    $stats = [];
    foreach ($gradeCols as $gIdx => $gLabel) {
        $count = $statsAcc[$gIdx]['count'];
        $sum   = $statsAcc[$gIdx]['sum'];
        $scores = $statsAcc[$gIdx]['scores'];

        $classAverage = $count > 0 ? ($sum / $count) : null;
        $totalWithScore = count($scores);

        $sortedScores = $scores;
        rsort($sortedScores, SORT_NUMERIC);

        $myScore = isset($row[$gIdx]) ? parseScore($row[$gIdx]) : null;
        $rank = null;

        if ($myScore !== null && $totalWithScore > 0) {
            $rank = 1; // 1 + تعداد نمره‌های بزرگ‌تر
            foreach ($sortedScores as $s) {
                if ($s > $myScore) $rank++;
                else break;
            }
        }

        $stats[] = [
            'label'         => $gLabel,
            'myScore'       => $myScore,
            'classAverage'  => $classAverage,
            'rank'          => $rank,
            'totalWithScore'=> $totalWithScore
        ];
    }

    // برای سازگاری با خروجی قبلی: اولین ستون نمره را به عنوان پیش‌فرض بگذار
    $defaultScoreIndex = null;
    foreach ($gradeCols as $idx => $_) { $defaultScoreIndex = $idx; break; }

    $default = null;
    foreach ($stats as $st) {
        if ($st['label'] === $gradeCols[$defaultScoreIndex]) { $default = $st; break; }
    }

    sendJson([
        'success'        => true,
        'name'           => $name,
        'grades'         => $grades,

        // خروجی جدید (اصلی)
        'stats'          => $stats,

        // خروجی قدیمی برای اینکه فرانت قبلی نشکند (برای اولین ستون نمره)
        'classAverage'   => $default ? $default['classAverage'] : null,
        'rank'           => $default ? $default['rank'] : null,
        'totalWithScore' => $default ? $default['totalWithScore'] : 0
    ]);
}

// ✅ اکشن ۲: تغییر رمز عبور
if ($action === 'changePassword') {
    $oldPassword = trim($_POST['oldPassword'] ?? '');
    $newPassword = trim($_POST['newPassword'] ?? '');

    $studentId   = normalizeNumberString($studentId);
    $oldPassword = normalizeNumberString($oldPassword);

    if ($studentId === '' || $oldPassword === '' || $newPassword === '') {
        sendError('شماره دانشجویی، رمز فعلی و رمز جدید را کامل وارد کنید.');
    }

    if (mb_strlen($newPassword) < 6) {
        sendError('رمز جدید باید حداقل ۶ کاراکتر باشد.');
    }

    if (!file_exists($csvFile)) {
        sendError('فایل نمرات پیدا نشد.');
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        sendError('امکان خواندن فایل نمرات وجود ندارد.');
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        sendError('هدر فایل نمرات نامعتبر است.');
    }

    $idIndex  = array_search('StudentID', $header);
    $pwdIndex = array_search('Password',  $header);

    if ($idIndex === false || $pwdIndex === false) {
        fclose($handle);
        sendError('ستون‌های StudentID یا Password پیدا نشدند.');
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    $foundIndex = -1;
    foreach ($rows as $i => $row) {
        $sid = isset($row[$idIndex])  ? normalizeNumberString($row[$idIndex]) : '';
        $pwd = isset($row[$pwdIndex]) ? (string)$row[$pwdIndex] : '';
        $pwdNorm = normalizeNumberString($pwd);

        if ($sid === $studentId && ($pwd === $oldPassword || $pwdNorm === $oldPassword)) {
            $foundIndex = $i;
            break;
        }
    }

    if ($foundIndex === -1) {
        sendError('شماره دانشجویی یا رمز فعلی معتبر نیست.');
    }

    // آپدیت رمز در آرایه
    $rows[$foundIndex][$pwdIndex] = $newPassword;

    // نوشتن دوباره در فایل CSV
    $handle = fopen($csvFile, 'w');
    if ($handle === false) {
        sendError('امکان نوشتن در فایل نمرات وجود ندارد.');
    }

    fputcsv($handle, $header);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    sendJson(['success' => true]);
}

// اگر هیچ اکشنی مچ نشد
sendError('درخواست نامعتبر است.');
