# 🔐 `server-only/` — Runtime Private Boundary

> این پوشهٔ tracked **محل نگه‌داری دادهٔ production در Git نیست**؛ فقط boundary و fallback محلی runtime را تعریف می‌کند. خود محتوا به‌جز `.gitignore` و همین README باید untracked بماند.

## 🧭 نقش واقعی

`public_html/api/bootstrap.php` مسیرهای خصوصی را با environment resolve می‌کند. اگر environment صریح وجود نداشته باشد، fallback محلی به این پوشه است:

```text
DENT_SERVER_ONLY_ROOT → <project>/server-only
DENT_STORAGE_ROOT     → <project>/server-only/storage
DENT_SESSION_SAVE_PATH → <project>/server-only/sessions
```

اما **production VPS** عمداً storage و server-only را sibling نگه می‌دارد:

```text
/srv/dentistry1402/shared/
├── storage/              # canonical mutable application data
└── server-only/          # private runtime/config
    ├── .env
    ├── sessions/
    ├── tmp/
    ├── secrets/
    └── backups/
```

PHP-FPM production این مسیرها را صریح set می‌کند:

```text
DENT_STORAGE_ROOT=/srv/dentistry1402/shared/storage
DENT_SERVER_ONLY_ROOT=/srv/dentistry1402/shared/server-only
DENT_SESSION_SAVE_PATH=/srv/dentistry1402/shared/server-only/sessions
DENT_ENV_FILE=/srv/dentistry1402/shared/server-only/.env
```

بنابراین `server-only/` داخل checkout **نمونهٔ runtime boundary و محل مناسب development محلی** است، نه نسخهٔ data production.

## 📁 ساختار محلی پیشنهادی

```text
server-only/
├── .env
├── storage/
├── sessions/
├── tmp/
├── secrets/
└── backups/
```

همهٔ این موارد توسط `.gitignore` همین پوشه ignore می‌شوند.

## 🔑 چه چیزهایی خصوصی هستند؟

نمونه‌ها:

- `.env` و API keyها؛
- auth/service secrets و signing keys؛
- PHP session files؛
- temporary runtime files؛
- local development storage؛
- recovery copies و backupهای محلی؛
- token، private key، database، log و هر production snapshot.

فایل `.env.example` در root فقط **نام متغیرها و defaultهای غیرحساس** را مستند می‌کند و نباید secret واقعی داشته باشد.

## 🧱 Source-of-truth rule

| داده | Source of truth |
| --- | --- |
| کد | exact Git commit |
| دادهٔ وب production | `/srv/dentistry1402/shared/storage` |
| secret/session وب production | `/srv/dentistry1402/shared/server-only` + service environment |
| bot runtime state | مسیرهای خصوصی `/var/lib/integrated-dent` / `/etc/integrated-dent` مطابق domain |
| snapshot محلی/backup | recovery evidence؛ نه ورودی deploy |

هیچ deploy عادی نباید data این پوشهٔ محلی را به production synchronize کند.

## 🛡️ قواعد امنیتی

۱. secret واقعی را هرگز commit نکن.
۲. برای production از environment/path صریح استفاده کن؛ به fallback checkout تکیه نکن.
۳. permission فایل‌های خصوصی را محدود نگه دار؛ web root نباید آن‌ها را مستقیم serve کند.
۴. session/storage را هنگام deploy پاک یا rotate نکن مگر migration صریح و تست‌شده چنین نیازی داشته باشد.
۵. backup را جای source-of-truth زنده ننشان؛ restore فقط برای incident واقعی و با verification انجام می‌شود.
۶. اگر secret ناخواسته وارد Git شد، حذف commit به‌تنهایی کافی نیست؛ secret باید rotate/revoke شود.

## 🧪 توسعهٔ محلی

برای محیط local می‌توان مسیرها را صریح set کرد:

```bash
export DENT_SERVER_ONLY_ROOT="$PWD/server-only"
export DENT_STORAGE_ROOT="$PWD/server-only/storage"
export DENT_SESSION_SAVE_PATH="$PWD/server-only/sessions"
```

قبل از اجرای app مطمئن شو directoryهای لازم وجود دارند و permission مناسب دارند. برای پroduction، runbook معتبر [`../ops/site-vps/README.md`](../ops/site-vps/README.md) و [`../DEPLOY.md`](../DEPLOY.md) اسؤ.
