# Deploy

تنها مسیر مجاز برای deploy این پروژه:

- لوکال: `public_html/`
- ریموت: `/home/wluoczid/public_html`

قواعد:

- `storage/` جزو web deploy نیست.
- `scripts/` جزو web deploy نیست.
- `.vscode/` نباید روی هاست بماند.
- برای deploy از اسکریپت `scripts/deploy_public_html.ps1` استفاده کن.

نمونه اجرا:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

اگر لازم بود `.vscode` ریموت هم پاک شود:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DeleteVscodeOnRemote
```
