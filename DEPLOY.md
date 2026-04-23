# Deploy

مسیر مجاز deploy این پروژه:

- لوکال: `public_html/`
- ریموت: `/home/wluoczid/public_html`

قواعد:

- `storage/` جزو web deploy نیست.
- `scripts/` جزو web deploy نیست.
- `.vscode/` نباید روی هاست بماند.
- فقط از اسکریپت `scripts/deploy_public_html.ps1` استفاده کن.

## رفتار پیش‌فرض (مهم)

اسکریپت به صورت پیش‌فرض **فقط فایل‌های تغییرکرده داخل `public_html`** را deploy می‌کند:

- فایل‌های `Modified/Added/Untracked` آپلود می‌شوند.
- فایل‌های `Deleted` از ریموت حذف می‌شوند.
- rename هم به صورت حذف مسیر قدیم + آپلود مسیر جدید اعمال می‌شود.

نمونه اجرا (استاندارد):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

پیش‌نمایش بدون آپلود واقعی:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```

فقط اگر واقعاً لازم بود، full sync کل `public_html`:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

اگر لازم بود `.vscode` ریموت هم حذف شود:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DeleteVscodeOnRemote
```

برای ترکیب فلگ‌ها:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun -DeleteVscodeOnRemote
```
