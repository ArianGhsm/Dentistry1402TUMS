# CONTRIBUTING

## اصل کار
- فقط repository دقیق `ArianGhsm/Dentistry1402TUMS` قابل write است.
- workflow فعال پروژه sequential است: هر task از latest `origin/main` یک SHA immutable ثبت می‌کند و فقط یک branch کاری می‌سازد؛ worker/branch موازی و integration wave برای task جدید نداریم.
- مستقیم روی `main` کدنویسی نمی‌شود و force-push ممنوع است.
- همان task پس از اجرای تست‌ها، سبزشدن CI و بررسی divergence/security می‌تواند PR خودش را به `main` merge کند؛ production deploy همچنان task جداگانه و exact-SHA است.
- تغییرات باید مستقیم در راستای درخواست باشند.
- refactor نامرتبط در همان تغییر ممنوع.
- commit message کوچک، روشن، قابل‌ردیابی.

## قبل از اولین write
- `AGENTS.md` و instructionهای nested مرتبط را بخوان.
- contractها و implementation موجودی که مرجع همان feature/UX است بررسی شود.
- برای bot UX، `bot_runtime/docs/BOT_UX_SYSTEM.md` و بهترین screenهای موجود خود ربات مرجع presentation هستند؛ صرفاً وجود helper یا navigation کافی نیست.

## الزامات قبل از تحویل
- اگر متن UI یا CSS تغییر کرد:
  - `python scripts/check_text_integrity.py`
- تست/چک مرتبط با فایل‌های touch‌شده اجرا شود.
- `python scripts/test_shared_contracts.py` و `python scripts/check_repository_hygiene.py` برای تغییرات contract/workflow اجرا شوند.
- flowهای متاثر روی desktop و mobile دوباره بررسی شوند.
- برای bot، Telegram/Bale parity و renderer/fallbackهای platform-specific باید با تست مناسب بررسی شوند.
- PR فقط بعد از CI سبز و بررسی latest `main` merge شود؛ SHA نهایی `main` گزارش شود.

## مسیر مرجع
- قوانین اجرایی کل پروژه: `AGENTS.md`
- قواعد متن/RTL/Locale: `CONTRIBUTING-UTF8.md`
- قرارداد deploy: `DEPLOY.md`
- workflow فعال: `docs/DEVELOPMENT_WORKFLOW.md`
- UX ربات: `bot_runtime/docs/BOT_UX_SYSTEM.md`
