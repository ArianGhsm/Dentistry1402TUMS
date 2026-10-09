# 🛠️ Dentistry1402 — Iran VPS Website Runtime

> قرارداد عملیاتی وب‌سایت production روی VPS ایران. این پوشه config و tooling زیرساخت را version می‌کند؛ **دادهٔ زنده، secret و backup داخل Git نیستند**. فایل `dentistry1402-recovery-recipient.pub` فقط کلید عمومی رمزگذاری است و دسترسی یا امکان رمزگشایی نمی‌دهد.

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

/var/backups/dentistry1402-runtime/
└── verified runtime snapshots + restore-drill reports
```

## 📁 فایل‌های این پوشه

| فایل/گروه | مسئولیت |
| --- | --- |
| `nginx-dentistry1402.conf` | HTTPS virtual host، redirects، static/PHP routing و denyهای مسیر حساس |
| `php-fpm-dentistry1402.conf` | pool ایزوله `dentweb` و runtime env paths |
| `logrotate-dentistry1402` | فقط rotation لاگ اختصاصی PHP-FPM |
| `backup-runtime.sh` + unit/timer | snapshot verified از mutable website/bot runtime |
| `send-bale-database-backup.py` + unit/timer | ساخت و ارسال بستهٔ بازیابی رمزگذاری‌شدهٔ دیتابیس و تنظیمات لازم به Bale مالک |
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
| 📤 Bale recovery backup | هر روز `03:35` + حداکثر ۵ دقیقه delay تصادفی | بستهٔ رمزگذاری‌شدهٔ داده و تنظیمات لازم را به Bale مالک می‌فرستد |
| 📦 Housekeeping | هر روز `04:10` + حداکثر ۵ دقیقه delay | retention releaseها بدون حذف active/in-use |
| 🧪 Restore drill | یکشنبهٔ اول ماه `04:45` + حداکثر ۱۰ دقیقه delay | restore کامل در محیط isolated و loopback-only |

timerها `Persistent=true` هستند؛ missed run بعد از بازگشت host می‌تواند اجرا شود.

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

Retention فعلی: **۱۴ snapshot روزانهٔ جدید + یک snapshot از هرکدام از ۸ هفتهٔ جدید**.

### ارسال روزانه به Bale

`send-bale-database-backup.py` فقط از تازه‌ترین snapshot معتبر `backup-runtime.sh` استفاده می‌کند؛ checksum بیرونی را بررسی می‌کند، JSON/JSONL و SQLite را دوباره اعتبارسنجی می‌کند و یک بستهٔ جداگانه می‌سازد. بسته شامل JSON/JSONL/SQLite سایت، دیتابیس‌های ربات‌ها، `.env`ها و secrets پایدار، گواهی و کلید TLS، تنظیمات Nginx/PHP-FPM و metadata نسخهٔ فعال است. PDF، تصویر، فایل‌های upload، log، session، فایل موقت و آرشیوهای بکاپ وارد بسته نمی‌شوند.

timer ارسال ساعت `03:35` تهران اجرا می‌شود؛ یعنی بعد از snapshot روزانهٔ `03:20`. snapshot قدیمی‌تر از ۲۶ ساعت ارسال نمی‌شود. بسته‌های بزرگ‌تر از ۱۹٬۰۰۰٬۰۰۰ بایت به بخش‌های کوچک‌تر شکسته می‌شوند. پس از موفقیت، هفت بستهٔ رمز‌شدهٔ آخر در مسیر خصوصی زیر باقی می‌مانند:

```text
/var/backups/dentistry1402-runtime/bale-recovery/
```

محتوای بسته با `age` و کلید عمومی `dentistry1402-recovery-recipient.pub` رمز می‌شود؛ فقط فایل `.age` و checksum آن در outbox باقی می‌ماند. متن Bale فقط زمان snapshot، اندازه، شمار فایل‌ها و SHA-256 فایل رمز‌شده را می‌گوید و نام فایل‌های تنظیمات یا مقدار secretها را نشان نمی‌دهد. هر بخش زیر ۱۹٬۰۰۰٬۰۰۰ بایت است تا از سقف فعلی ۵۰ مگابایتی ارسال سند بله فاصله داشته باشد.

کلید خصوصی متناظر فقط روی لپ‌تاپ مالک در `%USERPROFILE%\.codex\recovery\dentistry1402-bale-recovery` نگه‌داری می‌شود و به سرور، Bale یا GitHub کپی نمی‌شود. این فایل برای رمزگشایی لازم است؛ یک نسخهٔ آفلاین امن از آن نگه‌داری کنید. گم‌شدن کلید خصوصی یعنی بسته‌های رمز‌شده قابل بازیابی نیستند. کلیدهای SSH میزبان عمداً منتقل نمی‌شوند و روی میزبان جایگزین دوباره ساخته می‌شوند.

سرویس از همان `DENT_BALE_BOT_TOKEN` و `DENT_BALE_OWNER_ID` موجود در `/etc/integrated-dent/bale-bot.env` استفاده می‌کند و هویت یا token تازه‌ای نمی‌سازد. مالک باید قبلاً گفت‌وگو را با ربات بله شروع کرده باشد. در صورت خطای snapshot یا ارسال، سرویس تلاش می‌کند در همان گفت‌وگوی مالک پیام خطا بفرستد؛ متن journal هیچ secret یا فهرست فایل را ثبت نمی‌کند.

`age` از بستهٔ سیستم نصب می‌شود، recipient عمومی در مسیر `/usr/local/lib/dentistry1402/dentistry1402-recovery-recipient.pub` قرار می‌گیرد و plaintext موقت فقط در `/run` با مجوز `0700` ساخته می‌شود؛ systemd آن را هنگام پایان سرویس پاک می‌کند. مسیر انتشار canonical در `scripts/deploy_site_vps.ps1` چهار فایل دقیق sender/service/timer/recipient را hash-check می‌کند، نصب می‌کند و timer را فعال می‌کند. `install-site.sh` نیز برای bootstrap میزبان تازه همین فایل‌ها را می‌شناسد. افزودن این فایل‌ها به مخزن به‌تنهایی وضعیت production را تغییر نمی‌دهد؛ ارسال خودکار پس از release exact-SHA فعال می‌شود.

### بازیابی بسته‌ای که از Bale دریافت شده است

کلید خصوصی بالا را روی یک ویندوز قابل‌اعتماد و فقط در اختیار مالک نگه دارید. اگر Bale یک فایل `.tar.gz.age` فرستاد، همان فایل آمادهٔ رمزگشایی است. اگر فایل‌های `partNNN-of-NNN` فرستاد، پس از قرار دادن همهٔ بخش‌ها در یک پوشه، آن‌ها را با ترتیب عددی به هم بچسبانید و SHA-256 فایل کامل را با مقدار داخل caption بله مقایسه کنید:

```powershell
$parts = Get-ChildItem .\dentistry1402-recovery-*.tar.gz.age.part*-of-* | Sort-Object Name
$output = [System.IO.File]::Create('.\dentistry1402-recovery.tar.gz.age')
try {
  foreach ($item in $parts) {
    $input = [System.IO.File]::OpenRead($item.FullName)
    try { $input.CopyTo($output) } finally { $input.Dispose() }
  }
} finally { $output.Dispose() }
Get-FileHash .\dentistry1402-recovery.tar.gz.age -Algorithm SHA256
```

وقتی فایل رمز‌شدهٔ واحد را دارید، با همان کلید خصوصی رمزگشایی کنید و فقط پس از موفقیت رمزگشایی، آرشیو را در یک پوشهٔ خصوصی استخراج کنید:

```powershell
age --decrypt `
  --identity "$env:USERPROFILE\.codex\recovery\dentistry1402-bale-recovery" `
  --output .\dentistry1402-recovery.tar.gz `
  .\dentistry1402-recovery.tar.gz.age
tar -tzf .\dentistry1402-recovery.tar.gz
```

`age` صحت رمزنگاری/یکپارچگی را هنگام رمزگشایی بررسی می‌کند؛ SHA-256 caption هم مونتاژ بخش‌ها را بررسی می‌کند. برای بازسازی سامانه، کد را از commit ثبت‌شده در `operations/metadata/runtime-pointers.txt` در GitHub بگیرید، مقادیر و فایل‌های `operations/` را فقط روی میزبان جایگزین با مجوز محدود بازگردانید، و داده‌های `data/` را طبق restore drill بازیابی کنید. کلید خصوصی نباید در Bale، GitHub یا سرور قرار بگیرد؛ فایل‌های رمزگشایی‌شده و استخراج‌شده حاوی secret هستند و باید در فضای محدود نگه‌داری و پس از بازیابی پاک شوند. این بسته شامل فایل‌های upload، PDF/تصویر، session و log نیست.

## 🧪 Restore drill

`restore-drill.sh` آخرین backup را در tree خصوصی `/var/tmp` بازسازی می‌کند و بدون دست‌زدن به production ثابت می‌کند که recovery قابل اجرا است:

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
/var/backups/dentistry1402-runtime/restore-drills/
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
  dentistry1402-backup.timer dentistry1402-restore-drill.timer \
  dentistry1402-bale-database-backup.timer dentistry1402-housekeeping.timer

/usr/local/lib/dentistry1402/backup-runtime
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
