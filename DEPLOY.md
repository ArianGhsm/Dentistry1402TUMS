# DEPLOY

Deploy رسمی پروژه فقط از مسیر اسکریپت canonical انجام شود.
خطای بسیار تکرار شده: با دپلوی کردن، بعضی صفحات به نسخه های قبلی که ربطی به دپلوی و تاغییرات فعلی هم نداشت باز میگردند! نگذار این خطا رخ دهد.

## دستور نهایی بستن کار
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\complete_task.ps1
```

این wrapper فقط یک alias است و در نهایت همان `scripts/deploy_public_html.ps1` را با release-completion guard داخلی اجرا می‌کند.

## دستور canonical deploy
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

## Definition of Done
- بعد از هر پرامپت/کاری که روی پروژه انجام می‌شود، deploy canonical باید قبل از پاسخ نهایی اجرا شود مگر کاربر صراحتاً همان نوبت منع کند.
- command نهایی بالا دیگر نباید به deploy خام ختم شود؛ اسکریپت canonical حالا freshness guard را هم داخل همان run اجرا می‌کند و اگر `public_html/` بعد از آخرین host deploy drift داشته باشد non-zero fail می‌شود.
- کار فقط وقتی `completed` محسوب می‌شود که deploy، live health-check و اعلان داخل سایت برای مالک موفق شده باشند؛ fail/skip شدن deploy یا اعلان باید صریحاً `blocked` یا `partial` گزارش شود.
- ارسال پاسخ `final` بدون اجرای موفق `scripts/complete_task.ps1` در همان turn یک completion bug است، نه فراموشی قابل‌قبول؛ اگر این command اجرا نشده یا fail شده باشد، کار هنوز بسته نشده است.

## ترتیب اجباری Deploy
0. دانلود یک‌طرفه‌ی `storage/` از هاست به لپتاپ و mirror در `server-only/storage`
1. local validation
2. deploy به `/public_html`
3. live health-check
4. GitHub sync

## رفتار ایمن پیش‌فرض
- منبع اصلی کد، فایل‌های ویندوز است؛ منبع اصلی دیتا و دیتابیس، `storage/` روی هاست است.
- قبل از هر deploy، دیتای هاست در `.codex-local/remote-storage/snapshots/` بکاپ گرفته می‌شود و نسخه‌ی فعال لوکال در `server-only/storage/` فقط از روی هاست mirror می‌شود.
- promotion به `latest` فقط از snapshot کامل verified انجام می‌شود: تمام JSONها باید parse شوند، schemaهای حیاتی حاضر باشند، size/hash manifest دوباره تطبیق داده شود و `bot_links.json`، صف پرداخت و صف اعلان در دو read متوالی پایدار باشند. failure یا snapshot انتقالی اجازه تغییر active/latest را ندارد.
- این mirror شامل فایل‌های upload شده‌ی runtime هم هست؛ مسیرهای موقتی مثل `tmp`، `sessions`، `backups` و `cache` جزو منبع حقیقت deploy نیستند.
- نبودن دایرکتوری‌های اختیاریِ runtime مثل media preview/original/upload rootها نباید mirror را fail کند؛ فقط stateهای واقعی مثل `store.json` و storageهای اصلی blocker هستند.
- جهت sync دیتا فقط هاست -> لپتاپ است. دیتای `storage/`، `server-only/storage/`، بکاپ‌ها، sessionها، lockها و فایل‌های `.env` نباید از لپتاپ به هاست یا GitHub آپلود شوند.
- GitHub باید بر اساس فایل‌های کد/ظاهر/اسکریپت روی لپتاپ آپدیت شود، نه دیتای runtime.
- مرحله‌ی GitHub sync باید روی worktree موقتِ مبتنی بر آخرین upstream انجام شود؛ نه با `git add -A` روی workspace اصلی. این کار باعث می‌شود اختلاف branch محلی با `origin/main` یا dirty بودن workspace، deploy را روی push گیر ندهد.
- صرفا فایل هایی که تغییر کرده اند باید دپلوی شوند. نیازی به اپلود هرباره همه فایل ها نیست.
- delta deploy باید علاوه بر `git diff` با manifest آخرین محتوای deploy‌شده روی همین لپتاپ فیلتر شود؛ یعنی اگر فایلی هنوز در worktree dirty است اما همان محتوا قبلاً deploy شده، دوباره upload نشود.
- اگر `HEAD` فعلی همان آخرین `host deploy` موفق است، اختلاف `upstream..HEAD` نباید دوباره وارد plan شود؛ در این حالت فقط delta بعد از آخرین deploy موفق و تغییرات واقعی worktree مجازند.
- deploy عادی اگر بیش از ۸۰ upload یا بیش از ۲۵ delete داشته باشد باید قبل از upload fail شود. deploy گسترده فقط وقتی مجاز است که dry-run دیده شده و دستور با `-AllowLargeDeploy` یا `-FullSync` صریح اجرا شود.
- PWA version stamp دیگر نباید `HTML/PHP`های کل سایت را فقط برای تغییر `?v=` rewrite کند. cache-busting فایل‌های shared از این به بعد با revalidate header روی `css/js` و stamp محدود به `pwa.js`, `sw.js`, `manifest.webmanifest`, `app-version.json` انجام می‌شود.
- به‌محض موفقیت deploy روی هاست و health-check زنده، state و manifest لوکال باید قبل از notification/GitHub sync ثبت شوند تا failureهای مرحله‌های بعدی باعث تکرار uploadهای قبلاً deploy‌شده نشوند.
- هیچ سقف حجمی/proxy budget نباید deploy یا GitHub sync را متوقف کند؛ اگر مسیر شبکه در دسترس است، deploy باید ادامه پیدا کند.
- اعلان‌های شروع و نتیجهٔ نهایی باید از notifier مرکزی با قرارداد امضاشده داخل سایت فقط برای مالک ثبت شوند. مسیر login واقعی مالک فقط fallback صریح bootstrap است و در deploy عادی نباید یک اعلان سوم و تکراری بسازد.
- اگر در rerun هیچ delta جدیدی زیر `public_html/` روی هاست deploy نشود، owner notification نباید دوباره ارسال شود؛ retryهای repair فقط باید مرحله‌های باقی‌مانده مثل GitHub sync را ادامه دهند.
- همان lifecycle مرکزی باید هر رویداد را مستقلاً به Telegram، Bale و مرکز اعلان owner-only سایت تحویل دهد. رکورد سایت `disablePush` است تا workerهای ربات آن را دوباره ارسال نکنند. نگه‌داری token یا transport موازی داخل این مخزن ممنوع است.

## اعلان عملیاتی Telegram/Bale/Website

- مالک پیاده‌سازی transport، صف، retry و secretها پروژه `IntegratedDent1402Tums` است.
- deploy canonical سایت فقط eventهای lifecycle خودش را با `service=website` و `event-id` پایدار emit می‌کند.
- رویداد باید قبل از ارسال روی notifier به‌صورت durable queue شود؛ اختلال یک کانال نباید باعث تکرار upload موفق سایت شود.
- deploy با `started` آغاز می‌شود و دقیقاً با یکی از `succeeded`، `failed` یا `rolled_back` خاتمه می‌یابد.
- notifier سه‌کاناله روی VPS ایران نصب است؛ هر تغییر topology باید دوباره با health و تحویل واقعی هر سه کانال اثبات شود.
- token، chat ID، cookie و secret اعلان نباید در این repository، آرگومان command، log یا manifest deploy ذخیره شوند.
- صفحات منابع/جزوات فقط shell کد هستند. کارت‌های قابل مدیریت منابع باید از storage و API خوانده شوند، از جمله `notes/1402_terms.json`، `notes/1403_terms.json`، `notes/1404_terms.json` و `notes/prosthesis_1402_terms.json`.
- صدور و تحویل PDF محافظت‌شده در پروژه `IntegratedDent1402Tums` و ربات تلگرام انجام می‌شود. سایت فقط endpoint امضاشدهٔ هویت canonical واترمارک را نگه می‌دارد؛ هیچ فایل PDF، cache واترمارک، viewer یا state دستگاه مطالعه روی هاست سایت deploy نمی‌شود.

## دستورات مهم
Dry run:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```
این dry-run باید version-stamp واقعی را preview کند؛ اگر run واقعی قرار است صدها فایل cache-sensitive را rewrite کند، dry-run هم باید همان delta را نشان دهد.
در حالت عادی بعد از اصلاح pipeline، preview version-stamp نباید از چند فایل shared بیشتر شود مگر این‌که واقعاً asset-url یا HTMLهای سراسری را خودت تغییر داده باشی.

Full sync (فقط در نیاز صریح):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

pre-deploy pull (فقط با درخواست صریح):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```

retry سبک برای تکمیل GitHub sync بعد از live deploy موفق:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipRemoteStorageSync -SkipValidation -SkipPostDeployVerification -SkipVersionStamp -SkipOwnerDeployNotification -HostDeployNetworkPath direct -HealthCheckNetworkPath direct -GitHubNetworkPath proxy
```

Deploy محدود به فایل‌های مشخص، وقتی workspace تغییرات unrelated زیادی دارد:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PathScope public_html/assets/site/scripts/account.js,public_html/assets/site/styles/account.css
```

audit دستی اختیاری بعد از هر deploy:
```powershell
python .\scripts\check_host_deploy_freshness.py
```

## ایمنی داده
- state/runtime باید زیر `server-only/` بماند و commit نشود.
- `storage/`, `server-only/`, `scripts/` وب‌دیپلوی نمی‌شوند.
- حتی در `FullSync`، فایل‌های runtime/data مثل `public_html/.env` و `public_html/storage/` از upload/delete محافظت می‌شوند.
- deploy نباید seed یا HTML ثابت را جایگزین کارت‌های منابعی کند که مالک در سایت اضافه، ویرایش یا حذف کرده است.
- از deploy دستی و ad-hoc پرهیز شود.
- عملیات FTP canonical باید retry محدود و خودکار برای timeout/connection error داشته باشد تا خطاهای گذرای شبکه کل deploy را بی‌دلیل fail نکنند.
