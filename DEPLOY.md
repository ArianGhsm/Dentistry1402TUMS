# DEPLOY

Deploy رسمی پروژه فقط از مسیر اسکریپت canonical انجام شود.

## دستور اصلی
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

## ترتیب اجباری Deploy
1. local validation
2. deploy به `/public_html`
3. live health-check
4. GitHub sync

## رفتار ایمن پیش‌فرض
- منبع اصلی اطلاعات، فایل های ویندوز بوده و گیتهاب باید همیشه بر اساس آن ها آپدیت شود. اینکه در گیتهاب چه مواردی وجود دارد، مههم نیست. 

## دستورات مهم
Dry run:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```

Full sync (فقط در نیاز صریح):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

pre-deploy pull (فقط با درخواست صریح):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```

## ایمنی داده
- state/runtime باید زیر `server-only/` بماند و commit نشود.
- `storage/`, `server-only/`, `scripts/` وب‌دیپلوی نمی‌شوند.
- از deploy دستی و ad-hoc پرهیز شود.
