# دستیار برنامه ترم ۷

منبع برنامه، `public_html/api/academic_term7.php` و نسخهٔ فعلی آن
`1405-1406.1` است. عضویت گروه‌ها داخل کد یا PDF حدس زده نمی‌شود و فقط با شماره
دانشجویی canonical در storage خصوصی `academic/term7-1405-1406.json` نگه‌داری
می‌شود. این فایل بخشی از دادهٔ پایدار سرور است و با deploy جایگزین نمی‌شود.

## ورود گروه‌ها

ورودی CSV باید header زیر را داشته باشد:

```csv
studentNumber,name,group
40211272000,نام دانشجو,6
```

JSON نیز آرایه‌ای با همین سه field است. شماره دانشجویی مرجع اصلی است. تطبیق نام
فقط fallback است و حروف `ي/ی`، `ك/ک`، نیم‌فاصله و فاصله‌های اضافی را normalize
می‌کند. گزارش dry-run شامل matched، unmatched، ambiguous، duplicate، invalid و
missing است. تا وقتی خطای مبهم/نامعتبر وجود داشته باشد `--commit` چیزی ثبت
نمی‌کند.

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
