# دستیار برنامه ترم ۷

منبع برنامه، `public_html/api/academic_term7.php` و نسخهٔ فعلی آن
`1405-1406.1` است. عضویت گروه‌ها داخل کد یا PDF حدس زده نمی‌شود و فقط با شماره
دانشجویی canonical در storage خصوصی `academic/term7-1405-1406.json` نگه‌داری
می‌شود. این فایل بخشی از دادهٔ پایدار سرور است و با deploy جایگزین نمی‌شود.

## مدل canonical گروه‌ها

دو assignment مستقل برای هر دانشجو نگه‌داری می‌شود:

- `group10`: گروه صبح، فقط ۱ تا ۱۰.
- `group8`: گروه عصر، فقط ۱۱ تا ۱۸.

وضعیت هر دانشجو در هر گروه یکی از `leader`، `member` یا `unassigned` است و در UI
با «سرگروه»، «عضو» و «بدون گروه» نمایش داده می‌شود. این وضعیت فقط وضعیت
گروه‌بندی ترم ۷ است و نقش/وضعیت حساب کاربری سایت را تغییر نمی‌دهد.

عضویت گروه در همان state اصلی Term 7 می‌ماند. metadata مدیریتی سرگروه‌ها در
storage خصوصی deploy-safe `academic/term7-1405-1406-leaders.json` نگه‌داری
می‌شود؛ این فایل roster یا membership موازی نیست و فقط برای هر گروه، شماره
دانشجویی canonical سرگروه را ثبت می‌کند. در read، هر اشارهٔ سرگروهی که دیگر با
assignment فعلی همان دانشجو سازگار نباشد نادیده گرفته می‌شود. برای هر گروه
حداکثر یک سرگروه وجود دارد و display name یا شناسهٔ Telegram/Bale مرجع نیست.

مالک از «امور کلاس» ربات می‌تواند گروه صبح، گروه عصر و وضعیت سرگروهی هر دانشجو
را تغییر دهد. mutation گروه فقط `academic/term7-1405-1406.json` را تغییر می‌دهد
و mutation سرگروه فقط metadata مدیریتی بالا را. برنامهٔ شخصی، خلاصه‌های
فردا/هفته و reminderهای ترم ۷ همچنان از assignment canonical اصلی می‌خوانند.

فهرست مدیریتی ربات فقط accountهای cohort `dentistry-1402` را نشان می‌دهد که در
state canonical ترم ۷ assignment صریح دارند. صرفِ وجود یک account در cohort،
آن شخص را عضو فهرست ترم ۷ نمی‌کند. این قاعده اجازه می‌دهد account/link مستقل
بدون تخریب هویت از فهرست ترم ۷ کنار گذاشته شود.

`dent_rotation_group_catalog()` و تطبیق نامی قدیمی `dent_user_rotation_assignment()`
مرجع canonical گروه‌بندی ترم ۷ نیستند و نباید برای ClassOps audience، برنامهٔ
شخصی یا mutation گروه‌های این ترم استفاده شوند.

ایموجی و wording دکمه‌های مدیریت صرفاً presentation هستند و می‌توانند بعداً بدون
تغییر identifierها، storage schema یا قراردادهای business عوض شوند.

## ورود گروه‌ها

ورودی CSV باید header زیر را داشته باشد:

```csv
studentNumber,name,group
40211272000,نام دانشجو,6
```

JSON نیز آرایه‌ای با همین سه field است. شماره دانشجویی مرجع اصلی است. تطبیق نام
فقط fallback است و حروف `ي/ی`، `ك/ک`، نیم‌فاصله و فاصله‌های اضافی را normalize
می‌کند. گزارش dry-run شامل matched، unmatched، ambiguous، duplicate، invalid،
`missingGroup10` و `missingGroup8` است. تا وقتی خطای مبهم/نامعتبر یا کمبود در
فهرست target وجود داشته باشد `--commit` چیزی ثبت نمی‌کند.

ابتدا dry-run و سپس commit گروه‌های ۱ تا ۱۰:

```bash
php scripts/import_term7_groups.php --field=group10 --file=/absolute/group10.csv
php scripts/import_term7_groups.php --field=group10 --file=/absolute/group10.csv --commit
```

برای گروه‌های ۱۱ تا ۱۸:

```bash
php scripts/import_term7_groups.php --field=group8 --file=/absolute/group8.csv
php scripts/import_term7_groups.php --field=group8 --file=/absolute/group8.csv --commit
```

برنامه نظری در نبود گروه هم نمایش داده می‌شود؛ برنامه عملی هرگز حدس زده نمی‌شود.

برای «مبانی پارسیل نظری»، recurrence هفتگی چهارشنبه ۷:۳۰ تا ۸:۳۰ همچنان فقط
در `dent_term7_schedule()` تعریف می‌شود. فایل
`public_html/api/classops_partial_theory_syllabus.php` صرفاً metadata سیلابس
درس را بر اساس تاریخ روی همان occurrence موجود در ClassOps enrich می‌کند و
source-of-truth یا timetable موازی نیست. چند جلسهٔ ثبت‌شده در یک تاریخ با شماره
و عنوان مستقل حفظ می‌شوند. جلسه‌های مجازی برچسب «مجازی» دارند و چون PDF برای آنها ساعت مجازی مستقلی
ثبت نکرده، در projection ساعت یا محل فیزیکی آمفی‌تئاتر برای آنها جعل نمی‌شود. تاریخ جلسهٔ ۳ در منبع به‌صورت
`054/07/15` آمده و به‌دلیل ابهام، تا اصلاح صریح منبع به هیچ تاریخ canonical
متصل نمی‌شود.

مالک سامانه می‌تواند هم‌زمان دانشجوی واقعی باشد. اگر حساب owner شماره دانشجویی
canonical، cohort دقیق `dentistry-1402` و assignment صریح ترم ۷ داشته باشد،
نماهای دانشجویی ClassOps برنامهٔ شخصی همان هویت را نیز نشان می‌دهند؛ owner بودن
نباید projection تحصیلی را حذف کند. owner بدون assignment صریح، برنامهٔ شخصی
ساختگی دریافت نمی‌کند و دسترسی مدیریتی همچنان فقط در مسیر مدیریت ربات باقی
می‌ماند.

یادآوری‌های فردا و رزرو غذا فقط برای اتصال‌های احرازشدهٔ cohort دقیق
`dentistry-1402` ساخته می‌شوند. تأیید رزرو غذا با کلید user canonical + تاریخ
سه‌شنبه ذخیره می‌شود و برای تلگرام و بله مشترک است.
