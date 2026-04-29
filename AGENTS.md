# AGENTS.md

اصل اساسی: اگر بعد تغییر فایل های md و اینستراکشن ها نیاز به تغغیر داشتند(حذف کردن یا اضافه کردن موارد) حتما چک کن و انجام بده. مثلا یک قابلیتی حذف، اصول سایت تغییر یا چیزهایی به سایت اضافه شود(و یا موارد دیگر)

دستورالعمل اجرایی اصلی برای کل پروژه Dentistry1402TUMS.

## 1) مرز محصول (کل سایت)
- این پروژه یک سایت آموزشی چندبخشی است، نه یک پیام‌رسان مستقل.
- مسیرهای اصلی سایت باید کاربرد آموزشی خود را حفظ کنند: `/app/`, `/grades/`, `/exams/`, `/notes/`, `/resources/`, `/account/`.
- UX تلگرام‌مانند فقط برای `/chat/` و بخش‌های تنظیمات/پروفایل مرتبط با چت مجاز است.
- هیچ بخش غیرچتی نباید به الگوی پیام‌رسان تبدیل شود.

## 2) معماری کلان (کل پروژه)
- Frontend: چندصفحه‌ای (MPA) با HTML/CSS/JS در `public_html/`.
- Backend: APIهای PHP در `public_html/api/` و `public_html/chat/` و `public_html/grades/`.
- Storage: داده‌های پایدار باید در مسیرهای ذخیره‌سازی مشترک نگه‌داری شوند؛ نه در فایل‌های موقتی جایگزین‌شونده در Deploy.
- PWA: `manifest.webmanifest` و `sw.js` فعال هستند و باید سازگار بمانند.

## 3) هویت و احراز هویت (غیرقابل مذاکره)
- تنها منبع حقیقت هویت/نقش/session:
  - `public_html/api/auth_api.php`
  - `public_html/api/auth_store.php`
  - shared PHP session/bootstrap
- ایجاد auth یا identity موازی برای چت ممنوع است.
- `chat_api.php` نباید به منبع دوم auth تبدیل شود.

## 4) قرارداد داده پایدار و همگام‌سازی (غیرقابل مذاکره)
- پیام‌ها، نمرات، حافظه کاربر و هر state پایدار باید بین local + live + deploy target همگام بمانند.
- Deploy نباید باعث wipe/reset/fork/desync داده شود.
- تنها نسخه داده نباید در فایل‌های deploy-replaced یا temp runtime نگه‌داری شود.
- هر تغییر در storage/sync/backup/restore/migration/deploy باید continuity تاریخچه پیام و داده را حفظ کند.
- گزارش موفقیت کاذب ممنوع است: اگر داده فقط local یا cache است، موفقیت اعلام نشود.

## 7) قرارداد زبان/متن/RTL/Locale
- متن‌های UI باید UTF-8 سالم بمانند.
- تاریخ و ساعت و اعداد کاربر-محور باید فارسی (`fa-IR`) نمایش داده شوند، مگر فیلد machine-only.
- برای فیلدهای فنی Latin-digit:
  - `data-digit-locale="latin"` یا `data-latin-digits="true"`
- الگوی bidi ناامن (به‌خصوص `unicode-bidi: plaintext`) فقط با توجیه صریح.
- پیش‌فرض امن بلوک‌های فارسی:
  - `direction: rtl`
  - `unicode-bidi: isolate`
- بعد از هر ویرایش متن UI/CSS باید اجرا شود:
  - `python scripts/check_text_integrity.py`

## 8) قرارداد کیفیت اجرا (desktop + mobile)
- قبل از اصلاح، باگ باید بازتولید شود (desktop و phone-size).
- فقط با حدس اصلاح نکنید؛ request/response و state transition واقعی بررسی شود.
- بعد از اصلاح، همان flow روی desktop و mobile retest شود.
- کیفیت mobile-first برای create-flow و core actionهای چت اجباری است.
- تست موقت چت/گروه/DM باید بعد از اعتبارسنجی cleanup شود.

## 9) جلوگیری از false-success
- toast موفقیت وقتی end-state خراب است ممنوع.
- conversation/group ایجادشده باید در لیست دیده شود و باز شود.
- UI/store/network state باید sync بمانند.
- پیام‌ها نباید گاتی/مخدوش/نامنظم شوند.

## 10) قرارداد تم و استایل
- از semantic tokenهای `public_html/assets/site/styles/core.css` استفاده شود.
- از hardcode رنگ reusable روشن‌محور خودداری شود.
- patch موضعی dark-mode با `!important` فقط در صورت اجبار.

## 11) Workflow اجباری اجرای کار
1. وضعیت مخزن را بررسی کنید (`git status` + فایل‌های مرتبط).
2. مسئله را روی desktop/mobile بازتولید کنید.
3. رفتار واقعی شبکه و state را بررسی کنید.
4. اصلاح scoped اعمال کنید.
5. retest کامل همان flow روی desktop/mobile.
6. وضعیت را دقیق گزارش کنید: `completed` / `partial` / `blocked`.
7. Deploy پیش‌فرض انجام شود مگر کاربر صراحتاً منع کند.

## 12) Deploy پیش‌فرض
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```
- ترتیب اجباری:
  - local validation -> host deploy -> live health-check -> GitHub sync
- `git pull` قبل از deploy پیش‌فرض ممنوع است مگر درخواست صریح.
- override اختیاری:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```
## 13) درصورت نیاز به تست سایت
یوزرنیم مالک(من): 40211272003
رمز مالک: AAbb11__