# 🛠️ Dentistry1402 — Iran VPS Website Runtime

> قرارداد عملیاتی وب‌سایت production روی VPS ایران. این پوشه config و tooling زیرساخت را version می‌کند؛ **دادهٔ زنده، secret و backup داخل Git نیستند**.

برای release روزمره، مرجع canonical [`../../DEPLOY.md`](../../DEPLOY.md) و `scripts/run_release_gate.ps1` است. اسکریپت‌های bootstrap/install این پوشه مسیر جایگزین برای deploy عادی نیستند.

## 🧭 معماری runtime

```text
Internet
  │
  ▼
Nginx :80/:443
  │
  ├── static/PWA assets
  └── PHP → /run/php/php8.3-fpm-dentistry1402.sock
                    │
                    ▼
             PHP-FPM pool: dentweb
                    │
      ┌─────────────┴─────────────┐
      ▼                           ▼
shared/storage              shared/server-only
canonical mutable data      env / sessions / secrets / tmp
```

Layout ثابت production:

```text
/srv/dentistry1402/
├── current -> releases/<40-char-git-sha>
├── releases/
│   └── <git-sha>/
│       ├── .release-sha
│       └── public_html/
└── shared/
    ├── storage/              # canonical mutable website data
    ├── server-only/
    │   ├── .env
    │   ├── sessions/
    │   ├── secrets/
    │   ├── tmp/
    │   └── backups/
    └── acme/.well-known/acme-challenge/

/opt/integrated-dent/
├── telegram/current -> ../releases/telegram-...
└── bale/current     -> ../releases/bale-...

/var/backups/dentistry1402/
├── runtime/        # verified full runtime snapshots
├── site-data/      # verified website storage snapshots
├── bale-database/  # verified database-only Bale packages
└── restore-drills/ # sanitized drill reports (not counted as backups)
```

## 📁 فایل‌های این پوشه

| فایل/گروه | مسئولیت |
| --- | --- |
| `nginx-dentistry1402.conf` | HTTPS virtual host، redirects، static/PHP routing و denyهای مسیر حساس |
| `php-fpm-dentistry1402.conf` | pool ایزوله `dentweb` و runtime env paths |
| `logrotate-dentistry1402` | فقط rotation لاگ اختصاصی PHP-FPM |
| `backup-runtime.sh` + unit/timer | snapshot verified از mutable website/bot runtime |
| `backup_retention.py` + unit/timer | سقف پنج backup set کامل برای کل پروژه، با مسیر مجزا و بررسی hash |
| `send-bale-database-backup.py` + unit/timer | ارسال دیتابیس‌های snapshotشده به Bale مالک، بدون فایل‌های حجیم و secret |
| `restore-drill.sh` + unit/timer | restore آزمایشی، isolated و بدون mutation روی live runtime |
| `session-clean.sh` + unit/timer | پاک‌سازی sessionهای PHP مطابق `session.gc_maxlifetime` |
| `housekeeping.sh` + unit/timer | retention امن releaseهای وب، Telegram و Bale |
| `verify-site.sh` | syntax/service/data/live-health verification |
| `certbot-reload-nginx.sh` | validate و reload Nginx پس از renewal |
| `install-site.sh` | bootstrap/migration installer؛ نه deploy روزمره exact-SHA |
| `finalize-storage.sh` | ابزار migration/finalization storage در سناریوی نصب |

## 🌐 Nginx و TLS

virtual host فعلی این hostها را می‌شناسد:

- `dentistry1402tums.ir`
- `www.dentistry1402tums.ir`
- `reader.dentistry1402tums.ir`

HTTP به HTTPS canonical redirect می‌شود. certificate فعال از مسیر Certbot زیر خوانده می‌شود:

```text
/etc/letsencrypt/live/dentistry1402tums.ir/fullchain.pem
/etc/letsencrypt/live/dentistry1402tums.ir/privkey.pem
```

`/.well-known/acme-challenge/` از `shared/acme` سرو می‌شود. reload hook قبل از reload، `nginx -t` را اجرا می‌کند.

Nginx دسترسی مستقیم به dotfileها، `storage/`، `server-only/`، `backups/` و extensionهای حساس مثل `.env`, `.db`, `.sqlite`, `.log` را deny می‌کند. این denyها جای permission/backend auth را نمی‌گیرند؛ لایهٔ دفاع اضافی هستند.

## 🐘 PHP-FPM

pool اختصاصی `dentistry1402` با user/group `dentweb` و socket مجزا اجرا می‌شود:

```text
/run/php/php8.3-fpm-dentistry1402.sock
```

runtime paths به‌صورت environment صریح هستند:

```text
DENT_STORAGE_ROOT=/srv/dentistry1402/shared/storage
DENT_SERVER_ONLY_ROOT=/srv/dentistry1402/shared/server-only
DENT_SESSION_SAVE_PATH=/srv/dentistry1402/shared/server-only/sessions
DENT_ENV_FILE=/srv/dentistry1402/shared/server-only/.env
```

upload/post limit فعلی برای pool سایت حدود ۵۱۲MB/۵۲۰MB است؛ تغییر این مقادیر باید همراه بررسی application upload flow و Nginx انجام شود.

## 🚀 قرارداد release

release production وب باید همیشه **code-only و exact-SHA** باشد:

```text
origin/main@<sha>
   ↓
clean immutable workspace
   ↓
validated backup / preflight
   ↓
/srv/dentistry1402/releases/<sha>
   ↓
atomic current symlink switch
   ↓
PHP reload + live verification
```

قواعد غیرقابل مذاکره:

- `HEAD == origin/main == ReleaseSha`؛
- worktree release پاک است؛
- feature branch deploy نمی‌شود؛
- `shared/storage` و `shared/server-only` copy/delete/sync/replace نمی‌شوند؛
- اگر همان SHA فعال است، عملیات verification-only است؛
- cPanel/FTP مسیر production نیست.

فرمان canonical از root repository:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 `
  -ReleaseSha <exact-origin-main-sha> -DryRun

powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 `
  -ReleaseSha <exact-origin-main-sha> -Deploy
```

## ⏱️ timerهای production

| کار | زمان تهران | رفتار |
| --- | --- | --- |
| 🧹 Session clean | هر ساعت در دقیقه‌های `:14` و `:44` | فقط sessionهای منقضی؛ فایل باز PHP-FPM محافظت می‌شود |
| 💾 Runtime backup | هر روز `03:20` + حداکثر ۵ دقیقه delay تصادفی | snapshot + checksum + JSON/SQLite verification |
| 📤 Bale database backup | هر روز `03:35` + حداکثر ۵ دقیقه delay تصادفی | فقط JSON/SQLiteهای سایت و دیتابیس‌های ربات را به Bale مالک می‌فرستد |
| 🧹 Backup retention | یک ساعت پس از فعال‌سازی، سپس هر ساعت + حداکثر ۵ دقیقه delay تصادفی | سقف پنج بکاپ مجموع پروژه؛ پس از jobهای بکاپ هم اجرا می‌شود؛ deploy فقط migration انجام می‌دهد |
| 📦 Housekeeping | هر روز `04:10` + حداکثر ۵ دقیقه delay | retention releaseها بدون حذف active/in-use |
| 🧪 Restore drill | یکشنبهٔ اول ماه `04:45` + حداکثر ۱۰ دقیقه delay | restore کامل در محیط isolated و loopback-only |

timerهای بکاپ با `Persistent=true` اجرا می‌شوند؛ retention اولین اجرا را یک ساعت پس از فعال‌سازی زمان‌بندی می‌کند تا deploy خودش حذف را شروع نکند.

## 💾 Backup contract

`backup-runtime.sh` این stateها را snapshot می‌کند:

- `/srv/dentistry1402/shared/storage`؛
- بخش durable از `shared/server-only`، بدون session/tmp/nested backup؛
- TLS/runtime metadata موردنیاز recovery؛
- SQLiteهای `/var/lib/integrated-dent` با **SQLite backup API**، نه copy خام WAL؛
- `/etc/integrated-dent` و configهای اصلی Nginx/PHP-FPM؛
- pointerهای release فعال وب، Telegram و Bale.

هر archive قبل از publish:

۱. archive-readable بودن را ثابت می‌کند؛
۲. manifest `SHA256SUMS` را verify می‌کند؛
۳. همه JSONها را parse می‌کند؛
۴. SQLite snapshotها را `PRAGMA quick_check` می‌کند.

Retention فعلی: **حداکثر پنج backup set کامل برای کل پروژه**. این سقف بین runtime، site-data، Bale database و recovery artifactهای تاریخ‌دار مشترک است. پیش از حذف، checksum مجموعه‌های خودکار بررسی می‌شود. فایل‌های ناشناخته یا ناقص دست‌نخورده می‌مانند؛ مسیر پروژه‌های دیگر سرور در محدودهٔ نوشتن این سرویس نیست.

### ارسال روزانه به Bale

`send-bale-database-backup.py` فقط از تازه‌ترین snapshot معتبر `backup-runtime.sh` استفاده می‌کند؛ checksum بیرونی را بررسی می‌کند، JSON/JSONL و SQLite را دوباره اعتبارسنجی می‌کند و یک آرشیو جداگانه می‌سازد. این آرشیو شامل همهٔ JSON/JSONLهای سایت به‌جز مسیر `logs` و همهٔ SQLiteهای ذخیره‌شده در snapshot سایت و ربات‌هاست. PDF، تصویر، log، کلید، token، TLS و تنظیمات سرور به Bale فرستاده نمی‌شوند.

timer ارسال ساعت `03:35` تهران اجرا می‌شود؛ یعنی بعد از snapshot روزانهٔ `03:20`. snapshot قدیمی‌تر از ۲۶ ساعت ارسال نمی‌شود. بسته‌های بزرگ‌تر از ۱۹٬۰۰۰٬۰۰۰ بایت به بخش‌های کوچک‌تر شکسته می‌شوند. بستهٔ محلی در سقف پنج‌تایی کل پروژه حساب می‌شود:

```text
/var/backups/dentistry1402/bale-database/
```

سرویس از همان `DENT_BALE_BOT_TOKEN` و `DENT_BALE_OWNER_ID` موجود در `/etc/integrated-dent/bale-bot.env` استفاده می‌کند و هویت یا token تازه‌ای نمی‌سازد. مالک باید قبلاً گفت‌وگو را با ربات بله شروع کرده باشد. در صورت خطای snapshot یا ارسال، سرویس تلاش می‌کند در همان گفت‌وگوی مالک پیام خطا بفرستد و جزئیات را در journal ثبت می‌کند.

مسیر انتشار canonical در `scripts/deploy_site_vps.ps1` فایل‌های دقیق backup و retention را در یک bundle مجزا hash-check می‌کند، consumerهای جدید را نصب می‌کند و snapshotهای کامل legacy را بدون کپی به `/var/backups/dentistry1402/` منتقل می‌کند. این migration-only مرحله فایل‌های `shared/server-only` را prune نمی‌کند. بعد از موفقیت release gate، اجرای جداگانهٔ سرویس retention سقف پنج‌تایی را اعمال می‌کند؛ timer و اجرای پس از backupها ادامهٔ خودکار را انجام می‌دهند. `install-site.sh` نیز migration را برای bootstrap نصب تازه انجام می‌دهد.

در deploy و bootstrap، timerهای backup، retention، restore drill و ارسال Bale پیش از نصب consumerهای جدید متوقف می‌شوند. اگر سرویس متناظر هنوز در حال اجرا باشد، migration با خطا خارج می‌شود؛ timerهایی که از قبل فعال بودند در مسیر خروج ناموفق دوباره فعال می‌شوند.

## 🧪 Restore drill

`restore-drill.sh` آخرین backup را در tree خصوصی `/var/tmp` بازسازی می‌کند و بدون دست‌زدن به production ثابت می‌کند که recovery قابل اجرا است. پیش از انتخاب آرشیو، همان قفل سرویس retention را می‌گیرد و تا پایان drill نگه می‌دارد؛ بنابراین آرشیوی که drill در حال بررسی آن است هم‌زمان prune نمی‌شود:

- outer checksum و manifest داخلی verify می‌شوند؛
- JSONها parse و SQLiteها quick-check می‌شوند؛
- ownership واقعی `dentweb`, `dentbot`, `dentbale`, `dentcommerce` بازسازی می‌شود؛
- دسترسی DB با service userهای واقعی تست می‌شود؛
- auth store با runtime pathهای restored load می‌شود؛
- یک PHP server موقت فقط روی loopback boot و smoke-test می‌شود؛
- symlink live و PID ربات‌های production باید قبل/بعد یکسان بمانند؛
- network سرویس restore drill فقط localhost مجاز است.

گزارش‌های sanitized در:

```text
/var/backups/dentistry1402/restore-drills/
```

نگه‌داری می‌شوند و ۲۴ گزارش جدید حفظ می‌شود.

## 📦 Housekeeping releaseها

retention پایه:

- ۵ release وب؛
- ۵ release Telegram؛
- ۵ release Bale.

`housekeeping.sh` علاوه بر current symlink، هر releaseای را که process فعال از `cwd` یا `exe` به آن reference دارد protect می‌کند؛ بنابراین pruning فقط روی releaseهای امن و unreferenced انجام می‌شود.

## 🧾 Logging و logrotate

لاگ‌های اصلی وب:

```text
/var/log/nginx/dentistry1402.access.log
/var/log/nginx/dentistry1402.error.log
/var/log/php8.3-fpm-dentistry1402.log
/srv/dentistry1402/shared/storage/logs/errors.jsonl
```

**مالک rotationها:**

- Nginx access/error logها توسط config استاندارد Nginx سیستم rotate می‌شوند.
- `ops/site-vps/logrotate-dentistry1402` فقط `php8.3-fpm-dentistry1402.log` را روزانه rotate می‌کند و ۱۴ نسخه compressed نگه می‌دارد.

این جداسازی عمدی است؛ اضافه‌کردن دوبارهٔ Nginx logها به config پروژه باعث duplicate logrotate entry و شکست global `logrotate.service` می‌شود.

## 🔎 Verification عملیاتی

روی VPS:

```bash
nginx -t
php-fpm8.3 -t
systemctl is-active nginx php8.3-fpm \
  integrated-dent-bot.service integrated-dent-bale-bot.service

systemctl is-active dentistry1402-session-clean.timer \
  dentistry1402-backup.timer dentistry1402-backup-retention.timer dentistry1402-restore-drill.timer \
  dentistry1402-bale-database-backup.timer dentistry1402-housekeeping.timer

/usr/local/lib/dentistry1402/backup-runtime
/usr/local/lib/dentistry1402/backup-retention
/usr/local/lib/dentistry1402/send-bale-database-backup
/usr/local/lib/dentistry1402/restore-drill
```

و verification جامع checked-in:

```bash
sudo ./ops/site-vps/verify-site.sh
```

اجرای دستی backup/restore drill روی production یک عملیات واقعی است؛ فقط وقتی لازم است اجرا شود، نه صرفاً برای خواندن README.

## ↩️ Rollback

**code rollback:** `current` به release exact-SHA سالم قبلی برگردد و PHP-FPM reload/health-check شود.

**data recovery:** فقط در incident واقعی corruption/loss و از snapshot verified انجام شود. rollback کد به‌تنهایی مجوز بازگرداندن data قدیمی نیست؛ چون ممکن است writeهای سالم جدید کاربران را از بین ببرد.

## 🚫 Anti-patternها

- deploy از worktree dirty یا branch غیر-`main`؛
- کپی storage لپ‌تاپ روی production؛
- restore خودکار data صرفاً به‌خاطر rollback کد؛
- قراردادن `.env` یا secret در release؛
- استفاده از archive تاریخی به‌عنوان runbook فعلی؛
- duplicate کردن Nginx logs در logrotate اختصاصی پروژه؛
- اعلام موفقیت deploy فقط با `200` صفحهٔ عمومی و بدون data/service health.
