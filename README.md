# 🦷 Dentistry1402TUMS

> مخزن canonical اکوسیستم دندان‌پزشکی ورودی ۱۴۰۲ دانشگاه علوم پزشکی تهران؛ شامل وب‌سایت، قراردادهای مشترک، سورس runtime ربات‌های Telegram/Bale، تست‌ها و ابزارهای عملیاتی.

این README نقشهٔ ورود به پروژه است. جزئیات الزام‌آور در `AGENTS.md`، `DEPLOY.md` و مستندات تخصصی هر دامنه قرار دارند؛ اگر بین توضیح عمومی و قرارداد تخصصی اختلافی وجود داشت، قرارداد تخصصی و کد تست‌شده مرجع است.

## ✨ در یک نگاه

| لایه | مرجع واقعی |
| --- | --- |
| 🌐 وب‌سایت | `public_html/` — PHP + JavaScript/CSS، PWA و APIهای همان‌مبدأ |
| 🤖 Telegram / Bale | `bot_runtime/` — سورس Python مشترک با adapterهای پلتفرمی |
| 🧩 قراردادها | `contracts/` و `docs/SHARED_CONTRACTS.md` |
| 🧪 تست و نگه‌داری | `scripts/` + workflowهای `.github/workflows/` |
| 🛠️ عملیات VPS | `ops/site-vps/` — Nginx، PHP-FPM، backup، restore drill و housekeeping |
| 💾 دادهٔ وب production | `/srv/dentistry1402/shared/storage` روی VPS ایران |
| 🔐 secret/session وب | `/srv/dentistry1402/shared/server-only` |
| 📦 release وب | `/srv/dentistry1402/releases/<git-sha>` و symlink اتمیک `current` |
| 🤖 release ربات‌ها | `/opt/integrated-dent/releases` با state خارج از Git |

دامنهٔ اصلی production وب‌سایت `dentistry1402tums.ir` است. زمان‌های user-facing دانشگاهی بر مبنای تهران هستند و خروجی فارسی باید RTL-safe، با اعداد فارسی و تاریخ شمسی باشد.

## 🧭 معماری کلان

```text
GitHub: ArianGhsm/Dentistry1402TUMS
│
├── public_html/                 وب‌سایت و APIهای canonical
│   ├── api/                     auth, storage, payments, ClassOps, exams, ...
│   ├── app/ account/ admin/     shell، حساب و مدیریت
│   ├── notes/ exams/ grades/    محتوای آموزشی و کارنامه
│   ├── chat/ forms/ msg/        ارتباطات و فرم‌ها
│   ├── classops/ navid/         عملیات کلاس و نوید
│   └── sw.js                    PWA/service worker
│
├── bot_runtime/                 Telegram + Bale runtime source
│   └── dent_bot/                shared application logic + platform adapters
├── contracts/                   قراردادهای versioned بین لایه‌ها
├── scripts/                     test / migration / release / verification
├── ops/site-vps/                runtime configuration و recovery tooling
└── docs/                        قراردادها، workflow و گزارش‌های فنی

Production — Iran VPS
│
├── /srv/dentistry1402/
│   ├── current -> releases/<sha>
│   ├── releases/                code immutable وب
│   └── shared/
│       ├── storage/             دادهٔ mutable و canonical وب
│       └── server-only/         env، session، secret و runtime خصوصی
│
└── /opt/integrated-dent/
    ├── telegram/current         release فعال Telegram
    └── bale/current             release فعال Bale
```

**اصل اصلی:** کد و داده دو lifecycle جدا دارند. deploy کد وب حق overwrite، sync یا reset کردن `shared/storage` و `shared/server-only` را ندارد.

## 🗂️ نقشهٔ محصول

| حوزه | route / ماژول اصلی | نکتهٔ معماری |
| --- | --- | --- |
| 🏠 Shell و حساب | `/app/`, `/account/`, `/admin/` | auth/session مشترک و permission در backend |
| 📚 منابع آموزشی | `/notes/`, `/resources/` | state منابع در storage مشترک؛ PDF خصوصی از سایت تحویل نمی‌شود |
| 📝 آزمون | `/exams/` | کاتالوگ و attempt state canonical وب |
| 📊 نمرات | `/grades/` | `grades_store.php` منبع authoritative نمرات |
| 💬 چت و فرم | `/chat/`, `/forms/`, `/msg/` | identity موازی مجاز نیست |
| 🗓️ امور کلاس | `/classops/` | `classops-v1` با revision، idempotency و storage مستقل canonical |
| 🧑‍🏫 ترم ۷ | `academic_term7.php` | resolver رسمی زمان‌بندی و گروه‌ها برای وب و ربات |
| 🎓 نوید | `/navid/` + APIهای Navid | snapshot canonical وب و notification fan-out موجود |
| 💳 خرید و پرداخت | `/buy/`, `/payments/`, `/payment/` | order/transaction در `payments/store.json`؛ bot offers دامنه‌ای جداست |
| 🧰 ابزار محتوا | `/files/`, `/paste/`, `/html-uploader/` | state پایدار خارج از code release |
| 🤖 ربات‌ها | `bot_runtime/dent_bot/` | Telegram و Bale روی shared logic |

پروژه multi-cohort است. cohortهای دیگر باید از shell/API مشترک و boundaryهای canonical استفاده کنند؛ fork جداگانهٔ auth، storage یا business logic برای هر cohort الگوی مجاز نیست.

## 🧱 Source of truthها

۱. **کد:** یک commit دقیق از `ArianGhsm/Dentistry1402TUMS`.
۲. **دادهٔ وب production:** فقط `/srv/dentistry1402/shared/storage`.
۳. **secret/session وب:** `/srv/dentistry1402/shared/server-only` و environmentهای service؛ هرگز Git.
۴. **هویت کاربر:** auth canonical وب و mappingهای versioned؛ display name یا platform ID به‌تنهایی هویت نیست.
۵. **ClassOps:** خانوادهٔ storage و contractهای canonical خودش؛ Telegram/Bale دیتابیس ClassOps نیستند.
۶. **نمرات:** storage وب؛ ربات‌ها فقط مقدار canonical را می‌خوانند و render می‌کنند.
۷. **اعلان‌ها:** notification subsystem موجود؛ feature جدید نباید feed/read-state موازی بسازد.
۸. **bot runtime source:** فقط `bot_runtime/` در همین repository؛ state زنده و credentialها خارج از Git.

Recovery snapshot یا mirror محلی **backup است، نه ورودی deploy و نه source of truth زنده**.

## 🤖 Runtime ربات‌ها

`bot_runtime/` سورس مشترک Telegram و Bale است و production آن از releaseهای `/opt/integrated-dent` اجرا می‌شود. منطق shared شامل auth/linking، notifications، grades، ClassOps، Term 7، Navid، commerce و جریان جزوات محافظت‌شده است.

- Telegram و Bale باید semantics یکسان داشته و تافاوت در transport/render platform-aware بماند.
- Telegram روی VPS ایران از egress ایزولهٔ loopback استفاده می‌کند؛ Bale و website traffic مستقیم هستند.
- protected booklet delivery فقط در Telegram bot انجام می‌شود؛ سایت viewer یا API تحویل PDF خصوصی ندارد.
- token، session شخصی، SQLite/JSON state زنده، log و backup نباید وارد Git شوند.

جزئیات: [`bot_runtime/PROJECT_LOGIC.md`](bot_runtime/PROJECT_LOGIC.md) و [`bot_runtime/docs/BOT_UX_SYSTEM.md`](bot_runtime/docs/BOT_UX_SYSTEM.md).

## 🌐 Runtime وب‌سایت

وب‌سایت production با Nginx و PHP 8.3 FPM اجرا می‌شود:

```text
DENT_STORAGE_ROOT=/srv/dentistry1402/shared/storage
DENT_SERVER_ONLY_ROOT=/srv/dentistry1402/shared/server-only
DENT_SESSION_SAVE_PATH=/srv/dentistry1402/shared/server-only/sessions
```

کد release فقط `public_html/` است و immutable نگه داشته می‌شود. تنظیمات VPS، backup و recovery در [`ops/site-vps/README.md`](ops/site-vps/README.md) مستند شده‌اند.

## 🔐 قواعد امنیت و داده

- `.env`، token، API key، private key، session، production DB/JSON، log و backup در Git ممنوع‌اند.
- permission باید در backend enforce شود؛ پنهان‌کردن دکمه در frontend مجوز امنیتی نیست.
- auth/session مشترک باید در deploy حفظ شود؛ timeout یا `5xx` نباید به logout کاذب تبدیل شود.
- writeهای پایدار باید atomic/locked و fail-closed باشند؛ data سالم برای recovery پاک نمی‌شود.
- APIهای حساس و پاسخ‌های حساب/آزمون پولی نباید در PWA cache ذخیره شوند.
- raw Telegram/Bale IDs و credentialها نباید داخل domain stateهایی مثل ClassOps persist شوند.

## 🎨 قرارداد UI/UX

قبل از UI جدید، بهترین feature مشابه موجود در خود محصول audit و primitiveهای بالغ آن reuse/generalize می‌شوند.

- متن، label، اعداد و تاریخ user-facing فارسی و RTL-safe هستند.
- hierarchy، spacing و stateهای loading/empty/error/success صریح‌اند.
- renderer و domain logic از هم جدا می‌مانند.
- Daily / Weekly / Monthly / Detail صرفاً یک dataset با عنوان متفاوت نیستند.
- Telegram/Bale تا حد ممکن semantics مشترک دارند.
- raw JSON، raw DB status، raw ID و text dump خروجی کاربر محسوب نمی‌شوند.

## 🧪 توسعه و کیفیت

workflow توسعه GitHub-first و sequential است:

```text
main@exact-sha
   ↓
feature branch
   ↓
implementation + targeted tests
   ↓
full relevant checks
   ↓
Pull Request + GitHub CI
   ↓
merge to main
   ↓
در صورت production code/runtime delta: exact-SHA release gate جداگانه
```

سه workflow repository:

- `CI Static Checks`
- `ClassOps Stage 1 Domain Matrix`
- `Persian Text Integrity`

gate اصلی repository:

```bash
bash scripts/run_static_checks.sh
```

این gate syntax، UTF-8/Persian integrity، repository hygiene، shared contracts، auth/storage resilience، آزمون‌ها، ClassOps، bot integration و payment handoff را بررسی می‌کند.

> تغییر صرفاً docs/tests/workflow که production code/runtime را عوض نکرده است، deploy خالی production نمی‌خواهد.

## 🚀 Release production

مسیر canonical وب فقط این است:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 `
  -ReleaseSha <exact-origin-main-sha> -DryRun

powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 `
  -ReleaseSha <exact-origin-main-sha> -Deploy
```

release gate باید repository درست، worktree پاک و برابری `HEAD == origin/main == ReleaseSha` را ثابت کند. cPanel/FTP مسیر production نیست و deploy از feature branch مجاز نیست.

جزئیات authoritative: [`DEPLOY.md`](DEPLOY.md).

## 📚 مستندات مرجع

| سند | کاربرد |
| --- | --- |
| [`AGENTS.md`](AGENTS.md) | invariants سراسری، معماری، امنیت، UI/UX و Definition of Done |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | قواعد مشارکت و تحویل source task |
| [`DEPLOY.md`](DEPLOY.md) | قرارداد exact-SHA release و production safety |
| [`docs/DEVELOPMENT_WORKFLOW.md`](docs/DEVELOPMENT_WORKFLOW.md) | workflow توسعه، gateها و branch policy |
| [`docs/SHARED_CONTRACTS.md`](docs/SHARED_CONTRACTS.md) | قراردادهای مشترک و source-of-truth boundaries |
| [`docs/CLASSOPS_FOUNDATION.md`](docs/CLASSOPS_FOUNDATION.md) | بنیاد canonical ClassOps |
| [`docs/TERM7_ACADEMIC_ASSISTANT.md`](docs/TERM7_ACADEMIC_ASSISTANT.md) | برنامه و assistant ترم ۷ |
| [`docs/MAINTENANCE_DEBT.md`](docs/MAINTENANCE_DEBT.md) | debtهای معماری شناخته‌شده و مرز refactor |
| [`ops/site-vps/README.md`](ops/site-vps/README.md) | runtime وب روی VPS ایران، backup و recovery |
| [`docs/archive/README.md`](docs/archive/README.md) | اسناد historical/non-normative |
| [`docs/archive/WORKFLOW_MIGRATION_AUDIT.md`](docs/archive/WORKFLOW_MIGRATION_AUDIT.md) | evidence تاریخی مهاجرت workflow؛ فقط برای provenance |

## 🧹 تمیزی repository

- generated/runtime state وارد source tree نمی‌شود.
- branchهای task پس از merge/close حذف می‌شوند.
- diff unrelated قبل از merge حذف می‌شود.
- archive فقط provenance تاریخی است و instruction فعلی از آن برداشت نمی‌شود.
- فایل‌های مرکزی بزرگ تحت module-size budget هستند و feature جدید نباید debt شناخته‌شده را بی‌دلیل بزرگ‌تر کند.

برای شروع هر تغییر، ابتدا `AGENTS.md` و instructionهای nested مسیر موردنظر را بخوان؛ سپس implementation فعلی، تست‌ها و بهترین الگوی مشابه موجود را بررسی کن.
