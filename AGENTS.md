# AGENTS.md

## 0) Repository and workflow invariants (highest priority)

- The only writable repository for this product is exactly `ArianGhsm/Dentistry1402TUMS`. Verify `origin` before every write; similarly named repositories are read-only evidence.
- Canonical code is an exact GitHub commit. Production `storage/` is canonical data; verified ignored laptop/server-only snapshots are recovery copies.
- `bot_runtime/` is the canonical source for Telegram/Bale runtime. Live state, credentials, logs and service releases remain outside Git.
- `main` is integration/release only. Feature branches start at an immutable declared SHA and do not rebase/pull mid-task; a feature branch itself never deploys.
- **Owner rule (2026-10-26): the laptop is removed from the work path.** Development and release both happen through GitHub and on the Iran VPS itself. An agent may merge its own PR to `main` once CI is green, then releases that exact `origin/main` SHA from a clean release worktree on the VPS with `pwsh -File scripts/run_release_gate.ps1 -ReleaseSha <sha> -ServerConfig local -Deploy` (dry-run first with `-DryRun`). `-ServerConfig local` runs the same gate steps locally with sudo instead of SSH; the SSH server-config form remains only as a fallback.
- Feature-branch completion and integrated-release completion are distinct. Only an integrated release requires backup, exact-SHA canonical deploy and live verification. A docs/tests/workflow-only change with no production code change does not trigger an empty production deploy.
- The canonical release command requires `-ReleaseSha <exact-origin-main-sha>`. Deploy never creates or pushes a Git commit and never rewrites source files.
- The canonical website production release path is `scripts/run_release_gate.ps1` -> `scripts/deploy_site_vps.ps1`; `scripts/complete_task.ps1` is only an alias into that same gate. The retired cPanel/FTP deployer files have been removed from the working tree; Git history is evidence only and no operational workflow may invoke or recreate them.
- Shared contracts and high-risk hotspots are defined in `docs/SHARED_CONTRACTS.md` and `docs/DEVELOPMENT_WORKFLOW.md`. `AGENTS.md`, `DEPLOY.md` and `docs/DEVELOPMENT_WORKFLOW.md` must remain semantically aligned; contradictory release instructions are a failing contract.
- No agent lock-in is allowed. Any capable agent, including Codex, must be able to continue from the exact SHA plus repository docs/contracts/tests.

اصل اساسی: اگر بعد تغییر فایل های md و اینستراکشن ها نیاز به تغغیر داشتند(حذف کردن یا اضافه کردن موارد) حتما چک کن و انجام بده. مثلا یک قابلیتی حذف، اصول سایت تغییر یا چیزهایی به سایت اضافه شود(و یا موارد دیگر)

دستورالعمل اجرایی اصلی برای کل پروژه Dentistry1402TUMS.

## 1) مرز محصول (کل سایت)
- این پروژه یک سایت آموزشی چندبخشی است، نه یک پیام‌رسان مستقل.
- مسیرهای اصلی سایت باید کاربرد آموزشی/عملیاتی خود را حفظ کنند: `/app/`, `/grades/`, `/exams/`, `/notes/`, `/resources/`, `/account/`, `/forms/`, `/buy/`, `/admin/`.
- صدور جزوه‌های محافظت‌شده فقط در ربات تلگرام انجام می‌شود. سایت هیچ viewer، پنل مدیریت جزوه خصوصی، API تحویل PDF یا state دستگاه مطالعه ندارد؛ `/account/devices/` فقط نشست‌های واقعی ورود به حساب سایت را نمایش و لغو می‌کند. سایت فقط هویت canonical شامل نام کامل، کد ملی و موبایل تأییدشده را پس از احراز کامل اتصال ربات و درخواست HMAC-signed سرویس ارائه می‌کند.
- UX تلگرام‌مانند فقط برای `/chat/` و بخش‌های تنظیمات/پروفایل مرتبط با چت مجاز است.
- هیچ بخش غیرچتی نباید به الگوی پیام‌رسان تبدیل شود.
- ورودی پروتز ۱۴۰۲ باید از همان مسیرهای shared سایت (`/chat/`, `/forms/`, `/grades/`, `/notes/`, `/exams/`) با cohort/query یا config مناسب استفاده کند؛ state چت و نمرات آن جدا می‌ماند اما shell و فایل‌بندی UI نباید برایش fork جداگانه داشته باشد. مالک سایت در همه بخش‌های پروتز دسترسی دارد.

## 2) معماری کلان (کل پروژه)
- Frontend: چندصفحه‌ای (MPA) با HTML/CSS/JS در `public_html/`.
- Backend: APIهای PHP در `public_html/api/` و `public_html/chat/` و `public_html/grades/`.
- صفحه خانه shared سایت در `public_html/app/` باید کارت‌ها و entryهای cohort-driven خود را از config مشترک cohort در auth store بگیرد (service flags + route map) و نباید برای هر ورودی جدید با HTML hardcode جداگانه branch شود.
- فرم‌ها/نظرسنجی‌های جدید از ماژول یکپارچه `public_html/forms/` و `public_html/api/forms_api.php` استفاده می‌کنند.
- خرید گروهی و ثبت‌نام سایت از `public_html/buy/` و `public_html/api/payments_api.php` استفاده می‌کند و نباید به انتقال وجه مبهم تبدیل شود. محصولات مستقل ربات می‌توانند deep-link opaque و قابل لغو داشته باشند، اما تعریف و مخاطب آن‌ها در storage مشترک تلگرام/بله می‌ماند و فقط سفارش، callback، verify، تراکنش و reconciliation از backend پرداخت فعلی سایت استفاده می‌کند؛ این قابلیت نباید کاتالوگ `/buy/` را mirror یا خراب کند.
- «فروش فایل» محصول مستقل ربات است و source of truth مالی/مخاطب آن همان `payment_offers` و backend پرداخت موجود می‌ماند؛ فایل منبع فقط در تلگرام خصوصی مالک دریافت و به‌صورت metadata امن Telegram ثبت می‌شود و بایت منبع نباید به storage دائمی VPS کپی شود. خرید و تحویل این نوع محصول فقط در Telegram مجاز است؛ PDF باید از همان pipeline شخصی‌سازی/واترمارک/fingerprint جزوه عبور کند؛ تنها استثنا دنت‌نوتی است که هویت فایل/پیام مبدأ آن با ثبت فعال کانال خصوصی دقیقاً تطبیق دارد و پرداختش دوباره بررسی می‌شود. جزوات هوش مصنوعی و دنت‌نوت در ربات تلگرام به شکل PDF اصلی بدون واترمارک و قفل فوروارد تحویل می‌شوند. سایر فایل/رسانه‌ها با `protect_content` تحویل شوند. Bale نباید برای این محصول سفارش بسازد یا لینک Telegram را با username بله حدس بزند.
- مرکز آپلود شخصی مالک از `public_html/files/` و `public_html/api/content_tools_api.php` استفاده می‌کند؛ ساخت/مدیریت فقط مالک است اما لینک‌های عمومی فایل از `/files/f/` و دانلود از API بدون login برای دارنده لینک قابل دسترسی‌اند.
- Pastebin شخصی مالک از `public_html/paste/` و `public_html/api/content_tools_api.php` استفاده می‌کند؛ ساخت/مدیریت فقط مالک است اما لینک‌های عمومی paste از `/paste/p/` و raw view از API بدون login برای دارنده لینک قابل دسترسی‌اند.
- آپلودر HTML موقت از `public_html/html-uploader/` و `public_html/api/html_uploader_api.php` استفاده می‌کند؛ هر دارنده‌ی لینک uploader می‌تواند فایل HTML تک‌فایلی آپلود کند و خروجی عمومی آن از `/html/p/` فقط با لینک مستقیم باز می‌شود. صفحه‌های HTML آپلودشده باید با sandbox/CSP ایزوله سرو شوند و نباید به session یا origin اصلی سایت دسترسی هم‌ارز بگیرند.
- **بازنشستگی (تصمیم مالک ۲۰۲۶-۱۱-۱۲، سایت ربات‌محور):** صفحه‌های `/notes/` (همهٔ زیرمسیرها)، `/resources/` و `/dis-request/` (و `/dis-request/manage/`) به یک صفحهٔ اعلان «این بخش به ربات منتقل شد» با لینک آزمون‌ها و خانه تبدیل شدند؛ جزوه‌ها، منابع و درخواست‌ها از ربات‌های تلگرام و بله ارائه می‌شوند. APIها و داده‌های پشت آن‌ها (`notes_api.php` و storage) دست نخورده باقی می‌مانند و نباید حذف شوند؛ بازگرداندن UI این بخش‌ها فقط با درخواست صریح مالک.
- آرشیو منابع/جزواتی که از داخل سایت قابل افزودن، ویرایش یا حذف است از `public_html/notes/`، `public_html/notes/files/` و `public_html/api/notes_api.php` استفاده می‌کند؛ HTML صفحات فقط shell نمایشی است و کارت‌های منابع نباید به‌عنوان state قابل مدیریت داخل HTML ثابت نگه‌داری شوند. آپلود مستقیم فایل منبع و فایل‌منیجر فولدری هاست دانلود هم باید از همین ماژول و API مشترک استفاده کنند، نه از مسیر موازی.
- بررسی روزانه تکلیف‌های نوید از snapshot و login موجود `navid_service.php`
  استفاده می‌کند. کپچا فقط از action امضاشده owner-only برای Telegram صادر
  می‌شود و پاسخ فقط برای challenge همان روز پذیرفته می‌شود. snapshot موفق اول
  baseline خاموش است و نباید همه تکلیف‌های تاریخی را اعلان کند؛ فقط assignment
  key جدید بعدی از `notifications_enqueue_navid_assignment` وارد اعلان canonical
  سایت می‌شود. ربات‌ها feed یا read-state موازی نمی‌سازند. قرارداد کامل در
  `bot_runtime/docs/NAVID_DAILY_AUTOMATION.md` است.
- همیاد فقط از حساب تأییدشدهٔ مالک با APIهای `hamiad_*` خوانده می‌شود. پس از همگام‌سازی کامل و معتبر، snapshot خصوصی `hamiad/assignments-snapshot.json` در `DENT_STORAGE_ROOT` ذخیره می‌شود و با deploy جایگزین نمی‌گردد؛ فهرست خالی یا پاسخ نامعتبر/قطع ارتباط نباید آخرین snapshot را پاک کند. وضعیت `CLOSED` به‌تنهایی نه ارسال تکلیف است و نه پایان مهلت. تاریخ شروع، تاریخ تحویل، آخرین مهلت و وضعیت پذیرش مستقل هستند و فقط تاریخ دقیق معتبر مبنای یادآوری deadline قرار می‌گیرد. هنگام نمایش snapshot قدیمی برچسب واضح لازم است و هیچ یادآوری جدیدی نباید از داده stale صادر شود. قرارداد جزئی در `docs/HAMIAD_ASSIGNMENTS.md` است.
- کوییزهای برنامهٔ ترم ۷ فقط از session metadata در `classops_term7_syllabus.php` و دادهٔ همان طرح درس ساخته می‌شوند؛ برچسب کوییز و رخداد مستقل در همان source، timeline و daily notification برای گروه canonical یکسان است و تقویم موازی ممنوع است.
- داشبورد عملیاتی مالک از `public_html/admin/` و `public_html/api/admin_api.php` استفاده می‌کند؛ این مسیر فقط summary/health/status/linkهای مدیریتی را با auth مشترک مالک نمایش می‌دهد و نباید به پنل موازی برای عملیات تخصصی مثل خرید، فرم، منابع، فایل، paste، HTML uploader یا نوید تبدیل شود. عملیات تخصصی باید در route خودش بماند و `/admin/` فقط ورود سریع و وضعیت عملیاتی بدهد.
- تنظیم‌های ظاهر سراسری سایت، مثل روشن/خاموش‌کردن سوایپ نوار پایین، از بخش «مدیریت ظاهر سایت» در `/admin/` مدیریت می‌شوند و state پایدارشان باید در `auth/meta.json` و helperهای `auth_store.php` بماند؛ نه در HTML ثابت، localStorage یا فایل deploy-replaced.
- Storage: داده‌های پایدار باید در مسیرهای ذخیره‌سازی مشترک نگه‌داری شوند؛ نه در فایل‌های موقتی جایگزین‌شونده در Deploy.
- برنامه نسخه‌دار ترم ۷، assignment گروه‌های ۱..۱۰ و ۱۱..۱۸ و وضعیت هفتگی تأیید رزرو غذا فقط در `public_html/api/academic_term7.php` و storage خصوصی `academic/term7-1405-1406.json` نگه‌داری می‌شوند. گروه ترم ۶ یا متن/OCR PDF نباید عضویت ترم ۷ را بسازد. تنها external CTA مجاز این قابلیت URL ثابت سامانه غذای TUMS از همان config است؛ کلیک URL تأیید رزرو نیست.
- هسته canonical مرکز عملیات کلاس در قرارداد `classops-v1` و فایل‌های `public_html/api/classops_*` است. state آن فقط در storage مستقل `classops/store.json` با persistence اتمیک/fail-closed، revision افزایشی، optimistic concurrency و mutationهای idempotent نگه‌داری می‌شود. این state نباید با bot link، صف delivery، notification، payment یا Term 7 یکی شود. تا ساخت صریح مرحله Delivery، هیچ وضعیت ClassOps مجوز ارسال Telegram/Bale/گروه/کانال نیست و readهای ClassOps حق بازنویسی storage ندارند. قرارداد کامل در `docs/CLASSOPS_FOUNDATION.md` است.
- رابط runtime امور کلاس در Telegram/Bale از router صریح `bot_runtime/dent_bot/feature_router.py` وارد `classops_ui.py` می‌شود؛ Home/Admin مشترک در `classops_shell.py` و flowهای create/edit مالک در `classops_owner_workflows.py` هستند. featureها حق monkey-patch کردن `DentBotApp`، `runtime` یا rendererهای import‌شده را ندارند و entrypointهای Telegram/Bale نباید به ترتیب installerها وابسته باشند؛ compatibility callbackهای قدیمی فقط باید در ingress canonical ترجمه شود.
- جستجوی سراسری سایت از `public_html/api/search_api.php` + `public_html/api/search_store.php` استفاده می‌کند و فقط عنوان منابع/جزوات و دروس/جلسات آزمونِ همان cohortِ مجاز کاربر را index می‌کند؛ ورودی آن باکس جستجو در هدر shared است (`shell.js`). این مسیر باید auth و مرز cohort را enforce کند و نباید به منبع داده موازی یا فهرست‌کردن state حساس تبدیل شود. mapping توکن notes باید با `notes_curriculum_store_for_cohort` هم‌خوان بماند.
- زمان افزوده‌شدن هر مجموعه آزمون باید با فیلد ISO-8601 `addedAt` در تعریف همان مجموعه ثبت و از API آزمون‌ها منتشر شود؛ آزمون‌های داخل مجموعه نیز باید `addedAt` صریح یا زمان ارث‌برده از مجموعه داشته باشند. ترتیب «جدیدترین آزمون‌ها» فقط از این timeline محاسبه می‌شود و نباید به ترتیب آرایه/فایل متکی باشد. مجموعه‌های قدیمی فقط از index پایدار `public_html/api/exams_catalog_timeline.php` backfill می‌شوند و `scripts/check_exam_catalog_timeline.php` باید نبود زمان در مجموعه/آزمون جدید را در CI رد کند.
- **همگامی قطعی فهرست آزمون‌ها:** هر مجموعه آزمون قابل‌نمایش در کاتالوگ `/exams/` و بخش «آزمون‌های جدید»، باید از همان دادهٔ catalog و از طریق نگاشت canonical `examCourseSlugs` در `public_html/api/dentistry_curriculum.php` در واحد/ترم مربوط، یا از طریق `dent_dentistry_exam_reference_specialties()` در بخش رفرنس‌ها نیز قابل‌دستیابی باشد. افزودن course جدید بدون تعیین این نگاشت ممنوع است؛ نباید برای Home، آزمون‌های تمرینی و صفحهٔ ترم لیست مستقل یا شناسهٔ تکراری ساخت. تست سراسری `scripts/test_exam_catalog_navigation_sync.php` باید در CI هر مجموعهٔ منتشرشدهٔ بدون مسیر navigation را رد کند و برای موردهای جدید، counts و پیوند دقیق واحد بررسی شود.
- تجربه فعال آزمون در `public_html/assets/site/scripts/exam-quiz.js` و `exam-quiz.css` shared است و باید برای همه cohortها حالت تمرکز، نوار وضعیت/ذخیره، نقشه کامل سؤال‌ها و مرور نهایی پیش از ثبت را حفظ کند؛ این اجزا نباید در صفحه‌های آزمون یا cohortهای مختلف fork شوند. زمان آزمون اختیاری و config-driven با `durationMinutes`/`timeLimitMinutes` است و ارسال خودکار فقط با `autoSubmitOnExpiry` صریح مجاز است؛ در نبود زمان محدود، UI باید زمان سپری‌شده را نمایش دهد و وضعیت ذخیره آفلاین/روی دستگاه را صادقانه اعلام کند.
- درخواست‌های shared کاتالوگ، درس، bootstrap و ذخیرهٔ آزمون باید timeout محدود داشته باشند؛ خطای شبکه/timeout نباید صفحه را بی‌نهایت در حالت loading نگه دارد و bootstrap آزمون باید امکان «تلاش دوباره» و بازگشت امن به فهرست را نمایش دهد.
- ابزارهای مطالعه داخل سؤال (یادداشت، هایلایت، گزینه خط‌خورده و دفترچه اشتباهات)، تاریخچه تلاش‌ها و تحلیل مبحثی کارنامه باید در همان `exams/store.json` و رکورد مشترک آزمون با مرز participant/cohort ذخیره شوند؛ localStorage فقط draft/fallback همگام‌نشده است و نباید منبع حقیقت موازی شود. `reportsByUser` برای سازگاری کارنامه جاری می‌ماند و `attemptsByUser` تاریخچه append-only محدود را نگه می‌دارد؛ ریست کارنامه جاری نباید تاریخچه تلاش یا داده مطالعه را حذف کند. سؤال‌های جدید برای تحلیل بهتر باید تا حد امکان `topic` و `optionRationales` ساخت‌یافته داشته باشند. خلاصه و پاک‌سازی کنترل‌شده ابزارهای مطالعه هر کاربر باید در پنل اختصاصی همان کاربر برای مالک در دسترس باشد، اما پاک‌سازی ابزار مطالعه نباید کارنامه یا تاریخچه تلاش را حذف کند.
- سؤال‌های بالینی/تصویری باید در همان قرارداد shared بانک آزمون با فیلدهای اختیاری `difficulty`، `case`/`caseContext` و `media` تعریف شوند؛ `media` فقط URL داخلی یا HTTPS با `alt` توصیفی دارد و viewer زوم/چرخش نباید مسیر آپلود یا storage موازی بسازد. آزمون‌ساز شخصی فعلاً جلسه تمرینیِ client-side از بانک همان آزمون است و فقط با فیلتر مبحث/سختی/وضعیت، حالت آموزشی را محدود می‌کند؛ نباید پاسخ یا کارنامه سنجشی رسمی را تغییر دهد یا سؤال‌های cohort دیگر را وارد کند. پیشنهاد مرور بعدی نیز فقط از topic breakdown، دفترچه اشتباهات و سابقه همان participant/cohort استخراج می‌شود.
- چون صفحه‌های آزمون به‌صورت catalog-generated و پرتعداد ساخته می‌شوند، assetهای shared `exam-quiz.css`، `exam-quiz.js` و `exam-bootstrap.js` در `.htaccess` باید با `no-cache/must-revalidate` سرو شوند تا انتشار UI به بازنویسی صدها HTML و عبور از guard دیپلوی گسترده نیاز نداشته باشد؛ URL versioned صفحات همچنان حفظ می‌شود.
- برنامه پایان‌ترم هر ورودی باید به‌صورت cohort-driven در curriculum مشترک index شود و حداقل تاریخ شمسی نمایشی، `startsAt` و `expiresAt` استاندارد داشته باشد. تاریخ یک درس باید از همان metadata مشترک زیر نام درس در `/exams/` و `/notes/` نمایش داده شود. اعلان آزمون‌های خانه ابتدا فقط دو مجموعه جدید بر اساس `addedAt` را انتخاب می‌کند و سپس مواردی را که `expiresAt` آن‌ها گذشته حذف می‌کند؛ آزمون قدیمی‌تر نباید جای مورد منقضی‌شده backfill شود. صحت تاریخ‌ها، مرز cohort و انقضای پایان روز باید با `scripts/check_term6_final_exam_schedule.php` در CI و validation دیپلوی کنترل شود.
- اعلان‌های Web Push از `public_html/api/push_api.php` + `public_html/api/push_store.php` استفاده می‌کنند؛ کلید VAPID و اشتراک‌ها در storage مشترک `push/` می‌مانند و ارسال push فقط باید از همان مسیر dispatch اعلان‌های مشترک (`notifications_dispatch_push_if_needed` در `notifications_store.php`) عبور کند، نه queue/auth موازی. فعال/غیرفعال‌سازی فقط با اجازه مرورگر و از پنل حساب کاربر (`push.js`) است و `sw.js` باید handlerهای `push`/`notificationclick` را داشته باشد.
- PWA: `manifest.webmanifest` و `sw.js` فعال هستند و باید سازگار بمانند. `sw.js` برای navigationها network-first همراه navigation preload و سقف انتظار محدود است و علاوه بر STATIC_CACHE یک PAGE_CACHE نسخه‌دار نگه می‌دارد تا صفحات قبلاً بازدیدشده آفلاین یا هنگام stall شبکه سریع باز شوند؛ مقصدهای همان‌مبدأ نوار پایین باید پس از render به‌صورت non-blocking در PAGE_CACHE گرم شوند و navigation دارای cache سالم حداکثر پس از grace کوتاه زیر ۷۵۰ میلی‌ثانیه همان shell را نمایش دهد، درحالی‌که revalidate شبکه در پس‌زمینه کامل می‌شود. assetهای same-origin از نوع CSS/JS برای جلوگیری از توقف رابط در هر جابه‌جایی از STATIC_CACHE با stale-while-revalidate سرو می‌شوند؛ runtimeهای سراسری `pwa.js`، `shell.js`، `core.css` و `theme.css` حتی وقتی HTML قدیمی query منسوخ دارد باید از نسخه canonical همان worker پاسخ داده شوند. نصب asset و فعال‌شدن worker باید در پس‌زمینه و بدون reload اجباری صفحه انجام شود و انتشار canonical باید با تغییر `APP_VERSION` cache نسخه‌دار را بازسازی/پاک کند. هیچ اعلان، بنر یا toast مربوط به «نسخه جدید وب‌اپ» در UI مجاز نیست؛ چرخهٔ بروزرسانی باید کاملاً silent باشد و CSS سراسری نیز بنر احتمالی تزریق‌شده توسط runtime قدیمی را تا takeover worker مخفی کند. پاسخ APIهای حساس، به‌خصوص آزمون‌های پولی و داده‌های حساب، نباید برای آفلاین کش شوند.

## 3) هویت و احراز هویت (غیرقابل مذاکره)
- تنها منبع حقیقت هویت/نقش/session:
  - `public_html/api/auth_api.php`
  - `public_html/api/auth_store.php`
  - shared PHP session/bootstrap
- ایجاد auth یا identity موازی برای چت ممنوع است.
- `chat_api.php` نباید به منبع دوم auth تبدیل شود.
- `forms_api.php` هم باید از همین auth/session مشترک استفاده کند و نباید identity موازی برای کاربران سایت بسازد؛ فقط برای شرکت‌کننده مهمان، guest identity محدود به همان فرم مجاز است.
- حفظ نشست فعال کاربران در زمان تغییر و deploy اصل غیرقابل مذاکره است: تغییر قابلیت، cohort، config، cache، نسخه PWA یا storage نباید session cookie، session file، session save path، secret یا شناسه ورود کاربران فعال را حذف، rotate، overwrite یا نامعتبر کند.
- مدیریت نشست‌های ورود حساب از همان auth مشترک و مسیر `/account/devices/` انجام می‌شود: registry کمکی آن در `auth/active_sessions.json` فقط hash شناسه PHP session، شناسه عمومی نشست و metadata محدود دستگاه را نگه می‌دارد؛ loginهای جدید و نشست‌های legacy باید بدون خروج اجباری در آن ثبت شوند. لغو نشست از `auth_api.php` باید روی درخواست بعدی همان نشست enforce شود، اما خرابی/نبود registry نباید login یا session معتبر را از کار بیندازد.
- تغییرات غیرضروری برای یک قابلیت نباید از مسیر `auth_store` یا نرمال‌سازی persisted cohort/user عبور کنند؛ مخصوصاً read request نباید صرفاً به‌دلیل تغییر default/config باعث بازنویسی فایل کاربران، قفل طولانی auth یا خروج سراسری شود.
- timeout، خطای شبکه، پاسخ malformed و خطاهای موقت `5xx`/`401` از APIهای صفحه نباید در فرانت‌اند به logout قطعی، پاک‌کردن auth cache یا نمایش «نیازمند ورود» تبدیل شوند؛ logout فقط بعد از پاسخ معتبر endpoint canonical احراز هویت و تایید پایان واقعی session مجاز است.
- هر deploy که auth، bootstrap، session، storage، cohort normalization، PWA یا shared shell را لمس می‌کند باید قبل و بعد از انتشار با یک session موجودِ مالک و یک کاربر عادی smoke-test شود؛ حفظ همان cookie/session و دسترسی نقش‌ها شرط تکمیل deploy است، نه صرفاً `200` بودن صفحه عمومی.

## 4) قرارداد داده پایدار و همگام‌سازی (غیرقابل مذاکره)
- پیام‌ها، نمرات، حافظه کاربر و هر state پایدار باید بین local + live + deploy target همگام بمانند.
- منبع حقیقت نمرات فقط storage خصوصی سایت و helper مشترک
  `public_html/api/grades_store.php` است. Telegram و Bale نمره را با API امضاشده
  سایت می‌خوانند/می‌نویسند و نباید کپی authoritative یا cache پایدار نمرات روی
  VPS، SQLite ربات یا مرورگر بسازند.
- اتصال هویت ربات به حساب سایت با challenge کوتاه‌عمر سایت یا، فقط برای cohort
  اصلی کلاس، OTP ارسال‌شده به شماره verified و OTP-enabled همان حساب canonical
  مجاز است. رمز سایت فقط در سایت وارد می‌شود؛ OTP کلاس در چت خصوصی کوتاه‌عمر
  و غیرقابل‌ذخیره است. شناسه پلتفرم در storage سایت رمز می‌شود و API سرویس باید
  HMAC، timestamp و nonce ضد replay داشته باشد.
- `bot-onboarding-v1` یک پروفایل intake جدا از احراز حساب سایت است. تنها در
  آخرین مرحله و پس از Contact متعلق به همان حساب، OTP کوتاه‌عمر/یک‌بارمصرف
  همان زیرساخت سایت را برای اثبات شماره می‌گیرد. این OTP حق ساخت session، لینک
  حساب canonical یا دسترسی خصوصی ندارد؛ شماره، پروفایل و platform ID در
  `integrations/bot_links.json` رمز می‌شوند، OTP خام ذخیره یا log نمی‌شود و
  پروفایل بین Telegram/Bale فقط با HMAC شماره‌ای که در هر دو مستقل تأیید شده
  همگام می‌شود. این storage باید پیش از deploy از host روی لپ‌تاپ mirror شود.
- `bot_links.json` فقط source of truth هویت/link/onboarding/audit است. صف‌های
  پرداخت و اعلان به‌ترتیب در `bot_payment_deliveries.json` و
  `bot_notification_deliveries.json` با lock/generation مستقل نگه‌داری می‌شوند؛
  خرابی identity نباید workerهای service-to-service را پاک یا reset کند. هر JSON
  موجود ولی malformed باید fail-closed/503 شود و فقط نبود واقعی فایل با
  initialization صریح مجاز است. write باید temp + full-write + flush/fsync +
  decode/hash + atomic rename و previous generation تأییدشده باشد؛ truncate فایل
  فعال ممنوع است.
- کاتالوگ همین قرارداد ۷۵ مرکز علوم پزشکی دولتی و ۳۱ واحد علوم پزشکی دانشگاه
  آزاد را با فیلد صریح `system` نگه می‌دارد. پذیرش مراکز دولتی یکی از شش ترکیب
  نیمسال/نوع دوره است؛ برای واحد آزاد فقط `نیمسال اول` یا `نیمسال دوم` مجاز
  است و backend باید ناسازگاری دانشگاه/استان/نوع پذیرش را با خطای مشخص رد کند.
- فیلد الزامی `entryYear` بعد از دانشگاه و پیش از نیمسال قرار می‌گیرد و API فقط
  اعداد فارسی `۱۳۹۹` تا `۱۴۰۵` را می‌پذیرد (ورودی لاتین/عربی را پیش از اعتبارسنجی
  canonical می‌کند). پروفایل canonical اعضای کلاس دندانپزشکی تهران ۱۴۰۲ همیشه
  باید `entryYear=۱۴۰۲` داشته باشد و migration لینک‌های موجود نیز همین مقدار را
  همگن کند.
- احراز اعضای کلاس داخل همان `integrations/bot_links.json` و auth canonical
  سایت می‌ماند. claim دستی/نامی غیرفعال است؛ مسیرها فقط OTP حساب canonical یا
  login سایت هستند. حساب مفقود با User موازی ربات جایگزین نمی‌شود و تا تکمیل
  لینک هیچ بخش خصوصی ربات باز نیست. migration نسخه ۲ pendingهای قدیمی را رد
  می‌کند و approval قدیمی را فقط در صورت وجود link قطعی به تاریخچه منتقل می‌کند.
- وجود یک mapping معتبر در `integrations/bot_links.json` که به یک user canonical
  موجود resolve می‌شود، خودِ تعریف «حساب متصل» است. metadataهای نسل ایجاد اتصال
  مانند `authVersion`، `authMethod` و `authCompletedAt` حق ندارند mapping موجود را
  از access، شمارش اتصال، reminder، audience یا سرویس‌های عادی ربات حذف کنند؛
 
…[sentinelx: truncated 51394 bytes]…
lug>()`). ساخت/ویرایش این داده‌ها فقط با `scripts/build_term6_reference_courses.py` انجام شود؛ ساختن دوباره یک فایل تک و حجیم (مثل `exams_term6_reference_data.php` قدیمی، ~۴۱ مگابایت) که کل course map را یک‌جا require کند ممنوع است، چون دقیقاً همان عاملی بود که باعث 500/memory-limit در exam لایو شد. بارگذاری همیشه باید per-course و lazy از طریق `dent_exams_term6_reference_course_data_lazy()` در `exams_term6_reference_overrides.php` بماند.
- در تمام صفحه‌های `exams`، مخصوصاً صفحه‌های جلسه و پرداخت، هدر بالا و نوار پایین باید از shell سراسری سایت بیاید و با بقیه routeها یکسان بماند؛ داخل HTML یا JS آزمون نباید `site-header` موازی بسازد و session pageها نباید با `data-shell="off"` shell را خاموش کنند مگر با تایید صریح.
- در صفحه جلسه آزمون، محتوا نباید برای جا شدن در viewport به‌صورت اجباری `scale` یا کوچک شود؛ عرض stage باید هم‌عرض صفحه/شِل بماند و در صورت نیاز اسکرول کنترل‌شده از ریز و ناخوانا شدن محتوا بهتر است.
- صفحه جلسه آزمون باید page-surface باشد نه یک outer card بزرگ؛ کارت‌بودن اگر لازم است فقط در سکشن‌ها و بلوک‌های داخلی بیاید، نه به‌صورت یک پنل سراسری دور کل session stage.
- روی موبایل در `exams`، nested scroll تمام‌قد فقط وقتی مجاز است که واقعاً لازم و از نظر دسترسی آخرین آیتم‌ها زیر bottom-nav کامل تست شده باشد؛ در غیر این صورت page scroll عادی ترجیح دارد.

## 10.1) قرارداد واقعیت Production و کانسدریشن سراسری هر تغییر
- هیچ قابلیت، باگ‌فیکس، ریفکتور یا patch ظاهری فقط با دید UI یا فقط با جواب گرفتن از یک endpoint تمام‌شده محسوب نمی‌شود؛ هر تغییر باید در همه لایه‌های مرتبطِ واقعی production بررسی شود.
- این لایه‌ها `relevance-based` هستند، نه بهانه‌ای برای بازنویسی بی‌ربط؛ اگر change واقعاً یک لایه را درگیر نمی‌کند، باید همان‌جا صریح تشخیص داده شود و به refactor یا guard مصنوعیِ بی‌فایده تبدیل نشود.
- قبل از شروع هر تغییر، باید حداقل این‌ها برای خودت روشن باشد: منبع حقیقت داده، actorها و roleها، routeها و APIهای درگیر، stateهای `loading/empty/error/success`، failure mode محتمل، و اثر change روی deploy/cache/versioning.
- اگر یک تغییر فایل shared، shell، auth، storage، API مشترک، utility مشترک، design token یا route مشترک را لمس می‌کند، باید مثل تغییر production با ریسک رگرسیون بالا با آن رفتار شود؛ نه مثل patch موقت و موضعی.
- `Frontend / UX`: هر قابلیت جدید باید روی mobile و desktop، light و dark، RTL، safe-area، bottom-nav، keyboard/focus، `prefers-reduced-motion` و `performance-mode=lite` در صورت relevance بررسی شود و stateهای خالی، loading، error و success واضح و کاربرفهم داشته باشد.
- `APIs & Backend Logic`: هر endpoint یا action جدید باید validation و normalization ورودی، auth و permission backend، shape پاسخ پایدار، error message قابل‌استفاده و رفتار مشخص در timeout/failure داشته باشد؛ reliance صرف به کنترل UI مجاز نیست.
- `Database & Storage`: قبل از افزودن هر state جدید، source of truth آن باید روشن باشد؛ state پایدار نباید داخل HTML ثابت، cache موقت، فایل deploy-replaced یا متغیر runtime بی‌دوام نگه‌داری شود و write path باید تا حد ممکن در برابر overwrite/race/partial-write محافظه‌کار باشد.
- `Auth & Permissions`: هیچ دسترسی‌ای فقط با مخفی‌کردن دکمه یا شرط frontend امن فرض نمی‌شود؛ owner، representative، student، guest و cohort boundary باید در backend enforce شوند و دسترسی بین ورودی‌ها/کاربرها به‌صورت پیش‌فرض deny باشد مگر اجازه‌ی صریح.
- `Hosting & Deployment`: هر تغییری که route، asset، storage contract، upload flow یا dependency عملیاتی را تغییر می‌دهد باید از نظر deploy continuity، backup/mirror، live health-check و ریسک reset شدن state بررسی شود؛ fixی که فقط local سالم است کامل محسوب نمی‌شود.
- `Cloud / External Services`: هر اتصال به سرویس بیرونی یا upstream باید با فرض قطع‌شدن، timeout، تغییر markup/response و محدودیت شبکه طراحی شود؛ fallback، retry محدود، حفظ آخرین state معتبر و پیام خطای غیرکاذب از موفقیت‌نمایی مهم‌تر است.
- `CI/CD & Version Control`: هر تغییری که قرارداد رفتاری، workflow، اصول طراحی یا dependency اجرایی را عوض می‌کند باید در فایل‌های instruction/doc/script مرتبط هم منعکس شود؛ رفتار جدید بدون آپدیت مستندات پروژه کامل نیست.
- `Security & Isolation`: escape/sanitize متن و ورودی کاربر، عدم نشت secret به Git، sandbox بودن HTML/file public، least-privilege بودن لینک‌های عمومی و ندادن جزئیات حساس خطا به کاربر نهایی باید در تمام فیچرها رعایت شود.
- `Rate Limiting / Abuse`: login، sync، upload، broadcast، create/delete، submit و هر action قابل‌سوءاستفاده باید حداقل از نظر throttle، debounce، lock، nonce، duplicate-submit guard یا محدودیت scope بررسی شود؛ نبود rate limiter سراسری بهانه‌ی رها کردن abuse guard نیست.
- `Caching / CDN`: هر cache جدید باید strategy و invalidation مشخص داشته باشد؛ اگر route یا asset به stale cache حساس است، versioning یا busting آن باید جزئی از change باشد و hard-refresh راه‌حل رسمی مشکل محسوب نمی‌شود.
- `Load / Performance / Scaling`: در shared routeها نباید query، rerender، layout thrash، payload سنگین یا loop پرهزینه بی‌دلیل اضافه شود؛ پیاده‌سازی باید با رشد داده و cohortهای بیشتر هم degrade معقول داشته باشد، نه فقط روی dataset کوچک فعلی.
- `Error Tracking & Logs`: failureهای مهم باید لاگ/trace کافی برای عیب‌یابی واقعی بگذارند، اما بدون نشت secret یا داده حساس؛ `catch` خاموش یا پیام generic فقط وقتی مجاز است که fallback واقعی و قابل‌اعتماد وجود داشته باشد.
- `Availability & Recovery`: اگر sync/import/upload/upstream در میانه راه fail شد، سیستم باید تا حد ممکن آخرین state سالم را نگه دارد، destructive reset نکند و مسیر recovery روشن داشته باشد؛ پاک‌کردن data سالم برای شروع دوباره، راه‌حل پیش‌فرض نیست.
- `Validation Design`: هر validation یا guard جدیدی که برای enforce این اصول اضافه می‌شود باید تا حد ممکن deterministic، local-first، کم‌نویز، کم‌هزینه و low-false-positive باشد؛ check شکننده، مبهم، خیلی کند یا وابسته به شرایط ناپایدار production نباید بی‌محابا blocker پیش‌فرض deploy شود.
- `Definition of Done`: قبل از اعلام `completed` باید لایه‌های relevant از این بخش یا واقعاً verify شده باشند، یا صریحاً در گزارش نهایی گفته شود چرا برای آن change قابل‌اعمال نبوده‌اند.

## 10.2) چک‌لیست اجباری قبل از بستن هر قابلیت یا تغییر
- باید روشن باشد source of truth این تغییر کجاست و آیا deploy یا migration می‌تواند آن را reset، fork یا desync کند یا نه.
- باید سناریوی `loading / empty / error / success` و false-success آن flow بررسی شده باشد و toast یا banner موفقیت با end-state واقعی هماهنگ باشد.
- باید مشخص باشد چه roleها و چه cohortهایی مجاز به read/write این قابلیت هستند و همین منطق در backend تست یا حداقل بررسی صریح شده باشد.
- باید روی mobile و desktop، و هرجا relevant است روی light/dark، RTL، safe-area و overlap با bottom-nav retest انجام شود.
- باید اگر route shared یا API shared لمس شده، حداقل regression اصلی بخش‌های متاثر هم retest شود و فقط به «احتمالاً نمی‌شکند» اکتفا نشود.
- باید اگر asset، cache، service worker یا HTML route cache-sensitive تغییر کرده، versioning/invalidation مناسب هم انجام شده باشد.
- باید اگر change به upload، sync، import، external service، storage write یا عملیات زمان‌بر مربوط است، timeout، retry، cleanup و recovery path آن هم بررسی شده باشد.
- باید اگر change شامل متن UI، اصول طراحی یا workflow اجرایی است، فایل‌های `md` و instruction مرتبط هم همگام به‌روزرسانی شوند.
- validation سراسری قبل از deploy باید علاوه بر smoke و text-integrity، یک audit قراردادیِ دستورالعمل‌ها هم داشته باشد تا مواردی مثل zoom-lock، shell-off اشتباه، asset versioning، bypass شدن registry آزمون و خاموش‌شدن guardهای upload/shared nav زودتر fail شوند.
- هر audit یا check جدیدی که به deploy اضافه می‌شود باید اول از نظر نویز، سرعت، deterministic بودن، نیاز نداشتن به مداخله دستی، و عدم حساسیت به upstream/DNS/captcha/تغییرات volatile سنجیده شود؛ اگر این شرط‌ها را ندارد، باید advisory/post-deploy بماند نه blocker پیش‌فرض.
- checkهای blocker deploy باید ترجیحاً یا local باشند یا روی contractهای shared و پایدار تکیه کنند؛ dependency به سرویس بیرونی، داده زنده، یا UI volatile فقط وقتی blocker شود که خود change دقیقاً همان integration را دست زده باشد و failure آن واقعاً نشانه خرابی release باشد.

## 11) Workflow اجباری اجرای کار
1. وضعیت مخزن را بررسی کنید (`git status` + فایل‌های مرتبط).
2. مسئله را روی desktop/mobile بازتولید کنید.
3. رفتار واقعی شبکه، state و لایه‌های درگیرِ change مثل API، storage، auth، cache، deploy و dependencyهای بیرونی را بررسی کنید.
4. اصلاح scoped اعمال کنید؛ بدون بازنویسی بی‌مورد لایه‌هایی که به change ربط ندارند و بدون ساختن guard/checkی که بیش از خود change ریسک و پیچیدگی بیاورد.
5. retest کامل همان flow + سناریوهای وابسته + regression بخش‌های متاثر روی desktop/mobile و هر لایه‌ی relevant از بخش `10.1` و `10.2`.
6. وضعیت را دقیق گزارش کنید: `completed` / `partial` / `blocked`، و اگر بخشی از لایه‌های relevant verify نشده‌اند یا عمداً scope نشده‌اند، صریحاً ذکر کنید.
7. برای integrated release، پس از merge به `main` و قبل از پاسخ نهایی release، فرمان زیر با SHA دقیق `origin/main` اجرا شود:
```powershell
pwsh -File scripts/complete_task.ps1 -ReleaseSha <exact-sha> -ServerConfig local
```
7.1. Feature branch خودش production deploy نمی‌کند؛ ادغام PR سبز در `main` و release همان SHA روی خود VPS (بدون لپ‌تاپ) مجاز است؛ DoD آن در `docs/DEVELOPMENT_WORKFLOW.md` است.
7.2. تغییر صرفاً workflow/docs/tests که production code/runtime را تغییر نداده است با CI و coherence check بسته می‌شود، نه empty deploy.
8. وضعیت integrated release فقط وقتی کامل است که backup، deploy exact-SHA، live health/log/smoke و rollback readiness موفق باشند.

## 12) Deploy پیش‌فرض
- اتمام source task و production deploy دو مرحله جدا هستند. فقط integrated release از SHA دقیق merge‌شده‌ی `origin/main` وارد release gate می‌شود؛ feature branch و تغییر صرفاً docs/tests/workflow production deploy نمی‌کنند.
- dry-run canonical:
```powershell
pwsh -File scripts/run_release_gate.ps1 -ReleaseSha <exact-origin-main-sha> -ServerConfig local -DryRun
```
- deploy canonical:
```powershell
pwsh -File scripts/run_release_gate.ps1 -ReleaseSha <exact-origin-main-sha> -ServerConfig local -Deploy
```
- alias بستن integrated release:
```powershell
pwsh -File scripts/complete_task.ps1 -ReleaseSha <exact-origin-main-sha> -ServerConfig local
```
- `complete_task.ps1` فقط همان `run_release_gate.ps1 -Deploy` را فراخوانی می‌کند و مسیر deploy مستقلی نیست. `run_release_gate.ps1` نیز فقط `scripts/deploy_site_vps.ps1` را برای website production اجرا می‌کند.
- deployerهای قدیمی cPanel/FTP از working tree حذف شده‌اند؛ تاریخچه Git فقط مرجع forensic است و هیچ wrapper یا recovery workflow نباید آن‌ها را اجرا یا دوباره به‌عنوان مسیر عملیاتی برگرداند.
- target production سایت فقط VPS ایران با layout `/srv/dentistry1402/current -> releases/<sha>` است. release workspace باید repository درست، `HEAD == ReleaseSha`، `origin/main == ReleaseSha` و worktree پاک داشته باشد.
- deploy website فقط code-only `public_html` را به release immutable می‌برد. `/srv/dentistry1402/shared/storage` و `/srv/dentistry1402/shared/server-only` باید در تمام مسیر محافظت شوند و از Git/laptop overwrite یا synchronize نشوند.
- قبل از activation، backup/rollback ورودیِ قابل‌تأیید و validation لازم است؛ بعد از activation نیز website/Telegram/Bale/storage health، lifecycle notification و `release-report.json` باید موفق باشند. failure بعد از activation باید rollback خودکار مسیر کد را فعال کند.
- اگر همان exact SHA از قبل active است، release فقط verification-only است و production mutation انجام نمی‌دهد.
- چک‌های deterministic و آفلاین repository از `scripts/run_static_checks.sh` اجرا می‌شوند و CI باید قبل از merge سبز باشد. جزئیات اجرایی release در `DEPLOY.md` و policy توسعه در `docs/DEVELOPMENT_WORKFLOW.md` مرجع هستند و این سه سند نباید درباره مسیر canonical با هم تناقض داشته باشند.

## 13) درصورت نیاز به تست سایت
credential انسانی مالک نباید در فایل‌های tracked، fixtureها، log یا command line ثبت شود.
برای تست مجاز، credential فقط از secret storage محلیِ ignored یا environment موقت خوانده شود.

## 14) تمیزی و پاکسازی
در صورت ایجاد فایل های موقت، تست، چت های موقت، آیتم های موقت و هرچیزی که برای تست انجام شده، حتما بعد اتمام کار پاکسازی انجام بده و حذفشون کن.

## 15) تغییر فایل در یکی از چت های دیگر!
اگر یک فایلی که تو تغییرش ندادی یهو تغییر کرد، احتمالا همزمان 2 یا 3 چت کدکس روشن بوده.
 اگر کمتر از 3 دقیقه در حال تغییر بود و مدام ادیت میشد، صبر کن و وقتی دیگر تغییر نکرد، تغییرات خودت رو توش اعمال کن. 
 اگر بیشتر از 3 دقیقه درحال تغییر بود، اون فایل رو اسکیپ کن و رو بقیه بخش های سایت کارات رو انجام بده، در انتهای کار دوباره اون فایل رو چک کن.

 ## 16) آپشن های مورد نیاز
 حین انجام کار هر آپشنی/کتابخانه‌ای/برنامه‌ای که برای انجام کار نیاز داری، مثل ffmpeg یا هرچیزی، خودت دانلود و رو سیستمم نصب کن تا برای دفعات بعد هم بماند. البته مواردی که کم کاربردی هستند و ممکن است حاوی ویروس باشند را نه!
 طبیعتا چیزهایی مثل پایتون، کتابخانه ها، اپلیکیشن و پیش نیاز های رایج و... مورد تایید هستند و مشکلی ندارند.

## 17) نحوه اتصال به اینترنت
برای تمامی سایت های ایرانی بدون پروکسی(در VS Code پروکسی قرار داده شده)
برای تمامی سایت های عادی و خارجی و غیر ایرانی پروکسی با ادرس 127.0.0.1 و پورت 10808

## 18) دسترسی سرویس‌های خارجی پروژه
- برای سرویس‌های ایرانی مرتبط با همین پروژه، از جمله پنل میهن‌وب‌هاست، هاست دانلود `dl.dentistry1402tums.ir`، host فنی `cpdl1.mihanbank.com` و لینک‌های ایرانی منابع، بدون پروکسی عمل کن.
- برای عملیات مربوط به هاست دانلود، ابتدا فایل محلی `.codex-local/mihan-download-host.json` را بخوان و از همان credentials استفاده کن.
- لینک مستقیم cPanel/ورود این سرویس هم باید در همان فایل محلی secret نگه‌داری شود؛ از session URL موقت فقط به‌عنوان helper محلی استفاده کن و آن را به‌عنوان مقدار پایدار داخل Git کپی نکن.
- اگر لازم شد لینک‌های منابع روی سایت live به‌روزرسانی شوند، این کار باید از API/پنل مجاز همان قابلیت یا workflow تاییدشده انجام شود؛ نه با overwrite کورِ storage محلی.

## 19) قرارداد اعلان وضعیت دیپلوی
- مالک transport، صف پایدار، retry و secretهای Telegram/Bale/Website پروژه‌ی `IntegratedDent1402Tums` و قرارداد `docs/DEPLOY_STATUS_NOTIFIER.md` همان پروژه است.
- این مخزن فقط lifecycle دیپلوی سایت را با `service=website` emit می‌کند: ابتدا `started` و سپس دقیقاً یکی از `succeeded`، `failed` یا `rolled_back`.
- event ID باید در retry ثابت بماند و queue شدن idempotent باشد. failure ارسال پیام نباید upload موفق سایت را دوباره اجرا کند.
- token و chat ID ربات Telegram/Bale و secret امضای سایت نباید در این مخزن، deploy arguments، Git، log یا manifest ذخیره شوند؛ ارسال مستقیم ad-hoc از اسکریپت‌های سایت ممنوع است.
- notifier همان دو lifecycle event را owner-only داخل سایت نیز ثبت می‌کند و آن‌ها را با `disablePush` از بازپخش workerهای Telegram/Bale خارج می‌کند؛ deploy عادی نباید اعلان legacy سومی بسازد.
- notifier سه‌کاناله روی VPS ایران نصب است؛ ادعای موفقیت هر deploy به queue شدن lifecycle و موفقیت کانال سایت در پاسخ همان event وابسته است.
