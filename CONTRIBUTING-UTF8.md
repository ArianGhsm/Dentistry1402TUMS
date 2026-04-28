# CONTRIBUTING-UTF8

این پروژه Persian-first است. هر تغییر متن/Locale/CSS باید این قواعد را رعایت کند.

## قواعد اجباری
1. همه فایل‌های متنی UTF-8 باشند.
2. جایگزینی متن فارسی با `????` ممنوع.
3. اعداد کاربرمحور به‌صورت فارسی نمایش داده شوند.
4. تاریخ/ساعت کاربرمحور به‌صورت فارسی (`fa-IR`) نمایش داده شوند.
5. فقط برای فیلدهای machine-only از Latin digits استفاده شود:
   - `data-digit-locale="latin"`
   - `data-latin-digits="true"`
6. `unicode-bidi: plaintext` استفاده نشود مگر با توجیه مستند.
7. پیش‌فرض امن متن فارسی:
   - `direction: rtl`
   - `unicode-bidi: isolate`

## چک اجباری پس از ویرایش متن/CSS
```bash
python scripts/check_text_integrity.py
```

## خطاهایی که نباید عبور کنند
- mojibake / invalid UTF-8 / `????`
- الگوهای خرابی متن فارسی
- bidi ناامن بدون allow-mark
