# DEPLOY

Deploy رسمی پروژه فقط از مسیر اسکریپت canonical انجام شود.
خطای بسیار تکرار شده: با دپلوی کردن، بعضی صفحات به نسخه های قبلی که ربطی به دپلوی و تاغییرات فعلی هم نداشت باز میگردند! نگذار این خطا رخ دهد.

## دستور اصلی
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

## ترتیب اجباری Deploy
0. دانلود یک‌طرفه‌ی `storage/` از هاست به لپتاپ و mirror در `server-only/storage`
1. local validation
2. deploy به `/public_html`
3. live health-check
4. GitHub sync

## رفتار ایمن پیش‌فرض
- منبع اصلی کد، فایل‌های ویندوز است؛ منبع اصلی دیتا و دیتابیس، `storage/` روی هاست است.
- قبل از هر deploy، دیتای هاست در `.codex-local/remote-storage/snapshots/` بکاپ گرفته می‌شود و نسخه‌ی فعال لوکال در `server-only/storage/` فقط از روی هاست mirror می‌شود.
- این mirror شامل فایل‌های upload شده‌ی runtime هم هست؛ مسیرهای موقتی مثل `tmp`، `sessions`، `backups` و `cache` جزو منبع حقیقت deploy نیستند.
- جهت sync دیتا فقط هاست -> لپتاپ است. دیتای `storage/`، `server-only/storage/`، بکاپ‌ها، sessionها، lockها و فایل‌های `.env` نباید از لپتاپ به هاست یا GitHub آپلود شوند.
- GitHub باید بر اساس فایل‌های کد/ظاهر/اسکریپت روی لپتاپ آپدیت شود، نه دیتای runtime.
- مرحله‌ی GitHub sync باید روی worktree موقتِ مبتنی بر آخرین upstream انجام شود؛ نه با `git add -A` روی workspace اصلی. این کار باعث می‌شود اختلاف branch محلی با `origin/main` یا dirty بودن workspace، deploy را روی push گیر ندهد.
- صرفا فایل هایی که تغییر کرده اند باید دپلوی شوند. نیازی به اپلود هرباره همه فایل ها نیست.
- بعد از health-check موفق، اسکریپت باید با login واقعی مالک یک اعلان داخل سایت فقط برای مالک ثبت کند که نسخه‌ی فعال و زمان دقیق deploy را ذکر می‌کند.
- اعلان completion deploy فقط داخل سایت ثبت می‌شود؛ پیامک یا کانال اعلان موازی برای آن مجاز نیست.
- صفحات منابع/جزوات فقط shell کد هستند. کارت‌های قابل مدیریت منابع باید از storage و API خوانده شوند، از جمله `notes/1402_terms.json`، `notes/1403_terms.json`، `notes/1404_terms.json` و `notes/prosthesis_1402_terms.json`.

## دستورات مهم
Dry run:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```

Full sync (فقط در نیاز صریح):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

pre-deploy pull (فقط با درخواست صریح):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```

## ایمنی داده
- state/runtime باید زیر `server-only/` بماند و commit نشود.
- `storage/`, `server-only/`, `scripts/` وب‌دیپلوی نمی‌شوند.
- حتی در `FullSync`، فایل‌های runtime/data مثل `public_html/.env` و `public_html/storage/` از upload/delete محافظت می‌شوند.
- deploy نباید seed یا HTML ثابت را جایگزین کارت‌های منابعی کند که مالک در سایت اضافه، ویرایش یا حذف کرده است.
- از deploy دستی و ad-hoc پرهیز شود.
