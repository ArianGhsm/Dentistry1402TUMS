# AGENTS.md - Dental Residency

این دستورالعمل فقط برای مسیر `public_html/dental-residency/` است.

## مرز محصول
- بخش Dental Residency یک محصول جدا و ایزوله از Dentistry1402TUMS است.
- این بخش نباید با مسیرهای `/app/`, `/grades/`, `/exams/`, `/notes/`, `/resources/`, `/account/`, `/forms/`, `/buy/`, `/chat/` قاطی شود.
- الگوهای UX می‌تواند از محصول‌هایی مثل Medofast الهام بگیرد، اما نباید سوال، متن اختصاصی، دارایی تصویری یا محتوای دارای حق نشر آن‌ها کپی شود.

## هویت و Login
- منبع حقیقت login این بخش فقط همین فایل‌هاست:
  - `public_html/dental-residency/api/auth.php`
  - session با نام `drx_residency_session`
  - storage جدا در `storage/dental_residency/`
- استفاده از `public_html/api/auth_api.php`، `public_html/api/auth_store.php` و session اصلی سایت برای identity کاربران Dental Residency ممنوع است.
- استفاده از helperهای عمومی پروژه برای storage، UTF-8 و ارسال پیامک فقط در حد زیرساخت مجاز است؛ user/session/role نباید از سایت اصلی خوانده شود.

## داده پایدار و Deploy
- هر state پایدار این بخش باید زیر `storage/dental_residency/` بماند.
- داده runtime یا storage این بخش نباید از لپتاپ به هاست یا GitHub آپلود شود.
- جهت sync داده مثل پروژه اصلی فقط host -> laptop است.
- deploy نباید باعث wipe/reset/fork داده‌های `dental_residency` شود.
- seed/backfill فقط وقتی مجاز است که فایل storage کاملاً وجود نداشته باشد.

## UI و قابلیت‌ها
- بخش اصلی باید ابزار واقعی باشد، نه landing page صرف.
- قابلیت‌های اصلی: بانک تست، آزمون‌ساز، سوابق آزمون، داشبورد، مرور غلط‌ها، سوالات نشان‌شده، یادداشت، هایلایت، برنامه روزانه و جستجو.
- صفحه‌ها نباید خیلی بلند و انباشته شوند؛ امکانات زیاد باید در تب/صفحه جدا قرار بگیرند.
- عملیات پرخطر مثل reset داده محلی باید تایید صریح داشته باشد و success کاذب نمایش ندهد.

## متن فارسی، RTL و کیفیت
- همه متن‌های UI باید UTF-8 سالم بمانند.
- تاریخ، ساعت و اعداد کاربرمحور با `fa-IR` نمایش داده شوند.
- فیلدهای فنی Latin digit باید `data-digit-locale="latin"` یا `data-latin-digits="true"` داشته باشند.
- پیش‌فرض امن متن فارسی:
  - `direction: rtl`
  - `unicode-bidi: isolate`
- input/textarea/select/contenteditable قابل فوکوس در موبایل باید حداقل `16px` باشند.
- بعد از هر تغییر متن UI/CSS اجرا شود:
  - `python scripts/check_text_integrity.py`

## Deploy
Deploy رسمی همچنان فقط از اسکریپت canonical پروژه انجام می‌شود:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

ترتیب الزامی:
- host storage backup/mirror
- local validation
- host deploy
- live health-check
- GitHub sync

قبل از upload کد، storage هاست باید mirror شود و هیچ runtime data از لپتاپ به هاست ارسال نشود. فقط فایل‌های تغییرکرده deploy شوند.
