# CONTRIBUTING

## اصل کار
- تغییرات باید مستقیم در راستای درخواست باشند.
- refactor نامرتبط در همان تغییر ممنوع.
- commit message کوچک، روشن، قابل‌ردیابی.

## الزامات قبل از تحویل
- اگر متن UI یا CSS تغییر کرد:
  - `python scripts/check_text_integrity.py`
- تست/چک مرتبط با فایل‌های touch‌شده اجرا شود.
- flowهای متاثر روی desktop و mobile دوباره بررسی شوند.

## مسیر مرجع
- قوانین اجرایی کل پروژه: `AGENTS.md`
- قواعد متن/RTL/Locale: `CONTRIBUTING-UTF8.md`
- قرارداد deploy: `DEPLOY.md`
