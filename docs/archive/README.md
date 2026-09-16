# 🗃️ آرشیو مستندات تاریخی

> `docs/archive/` محل نگه‌داری **provenance و evidence تاریخی** است؛ نه محل دستورالعمل فعلی توسعه، deploy یا عملیات production.

اسناد این پوشه snapshot یک تصمیم، migration، integration wave یا وضعیت release در یک مقطع زمانی هستند. عبارت‌هایی مثل نام branch، مسیر قدیمی deploy، وضعیت «مرحله بعد» یا محدودیت‌های آن زمان عمداً ممکن است در گزارش تاریخی حفظ شده باشند.

## 🧭 مرجع فعلی کجاست؟

| موضوع | سند authoritative فعلی |
| --- | --- |
| 🧱 invariants و معماری repository | [`../../AGENTS.md`](../../AGENTS.md) |
| 🔀 workflow توسعه و branch policy | [`../DEVELOPMENT_WORKFLOW.md`](../DEVELOPMENT_WORKFLOW.md) |
| 🚀 release production | [`../../DEPLOY.md`](../../DEPLOY.md) |
| 🔗 قراردادهای مشترک | [`../SHARED_CONTRACTS.md`](../SHARED_CONTRACTS.md) |
| 🗓️ ClassOps canonical | [`../CLASSOPS_FOUNDATION.md`](../CLASSOPS_FOUNDATION.md) |
| 🛠️ VPS website runtime | [`../../ops/site-vps/README.md`](../../ops/site-vps/README.md) |

**قاعده:** اگر یک سند archive با کد فعلی، contract فعلی یا اسناد بالا اختلاف دارد، archive مرجع اجرایی نیست.

## 📦 محتوای فعلی آرشیو

| مسیر | نقش |
| --- | --- |
| `WORKFLOW_MIGRATION_AUDIT.md` | evidence مهاجرت workflow و مرز بین مدل قدیمی/فعلی |
| `classops/INTEGRATION_STAGE1_REPORT.md` | گزارش historical integration مرحله ۱ ClassOps |
| `classops/INTEGRATION_STAGE2_REPORT.md` | گزارش historical integration مرحله ۲ ClassOps |

گزارش‌های ClassOps برای traceability نگه داشته شده‌اند؛ source-of-truth فعلی ClassOps در `public_html/api/classops_*`، `contracts/` و مستندات non-archive قرار دارد.

## ✅ چه چیزی باید وارد archive شود؟

مناسب برای archive:

- گزارش migration تکمیل‌شده؛
- evidence یک release/integration قدیمی که هنوز ارزش forensic دارد؛
- تصمیم تاریخی که قرارداد جدید جایگزین آن شده اما حذف کاملش context را از بین می‌برد؛
- گزارش branch/wave قدیمی که نباید به‌عنوان instruction روزمره استفاده شود.

نامناسب برای archive:

- دستور deploy فعلی؛
- credential، token، ID خصوصی، log خام یا production snapshot؛
- TODO فعال که هنوز باید در docs اصلی یا issue/PR دنبال شود؛
- duplicate مستندات canonical.

## 🧹 قواعد نگه‌داری

۱. سند archived را برای «به‌روز جلوه‌دادن» بازنویسی نکن؛ provenance آن باید قابل‌ردیابی بماند.
۲. اگر رفتار محصول عوض شد، سند canonical را اصلاح کن و در صورت نیاز گزارش قبلی را archive کن.
۳. commandهای archive را بدون تطبیق با `main` فعلی اجرا نکن.
۴. archive نباید در CI، deploy script یا runtime به‌عنوان config/source-of-truth مصرف شود.
۵. secret یا دادهٔ کاربر حتی به‌عنوان evidence تاریخی وارد Git نمی‌شود.
