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

سرگروه‌ها نیز در همان storage خصوصی Term 7 و در `groupLeaders` نگه‌داری می‌شوند.
برای هر گروه حداکثر یک سرگروه وجود دارد و مقدار آن شماره دانشجویی canonical است؛
نام نمایشی یا شناسهٔ Telegram/Bale مرجع عضویت نیست. تغییر گروه یک سرگروه، اشارهٔ
سرگروهی قبلی را پاک می‌کند تا state ناسازگار باقی نماند.

مالک از «امور کلاس» ربات می‌تواند گروه صبح، گروه عصر و وضعیت سرگروهی هر دانشجو
را تغییر دهد. این mutationها owner-only هستند و همان stateای را تغییر می‌دهند که
برنامهٔ شخصی، خلاصه‌های فردا/هفته و یادآوری‌های ترم ۷ از آن می‌خوانند؛ database یا
roster موازی ساخته نمی‌شود.

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
یادآوری‌های فردا و رزرو غذا فقط برای اتصال‌های احرازشدهٔ cohort دقیق
`dentistry-1402` ساخته می‌شوند. تأیید رزرو غذا با کلید user canonical + تاریخ
سه‌شنبه ذخیره می‌شود و برای تلگرام و بله مشترک است.
