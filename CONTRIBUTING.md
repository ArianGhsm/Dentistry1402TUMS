# CONTRIBUTING

## اصل کار
- فقط repository دقیق `ArianGhsm/Dentistry1402TUMS` قابل write است.
- branchهای feature از SHA immutable شروع می‌شوند، مستقیم روی main نمی‌نویسند، self-merge و deploy نمی‌کنند.
- تغییرات باید مستقیم در راستای درخواست باشند.
- refactor نامرتبط در همان تغییر ممنوع.
- commit message کوچک، روشن، قابل‌ردیابی.

## الزامات قبل از تحویل
- اگر متن UI یا CSS تغییر کرد:
  - `python scripts/check_text_integrity.py`
- تست/چک مرتبط با فایل‌های touch‌شده اجرا شود.
- `python scripts/test_shared_contracts.py` و `python scripts/check_repository_hygiene.py` برای تغییرات contract/workflow اجرا شوند.
- flowهای متاثر روی desktop و mobile دوباره بررسی شوند.

## مسیر مرجع
- قوانین اجرایی کل پروژه: `AGENTS.md`
- قواعد متن/RTL/Locale: `CONTRIBUTING-UTF8.md`
- قرارداد deploy: `DEPLOY.md`
- workflow و integration ownership: `docs/DEVELOPMENT_WORKFLOW.md`
