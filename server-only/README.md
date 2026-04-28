# server-only

تمام داده‌های runtime و secret باید فقط اینجا باشند و commit نشوند.

## ساختار پیشنهادی
```text
server-only/
  storage/
  tmp/
  sessions/
  backups/
  secrets/
```

## قواعد
- داده واقعی و credential در فایل‌های tracked ریشه پروژه قرار نگیرند.
- برای مسیر runtime از env varها استفاده شود:
  - `DENT_SERVER_ONLY_ROOT`
  - `DENT_STORAGE_ROOT`
  - `DENT_SESSION_SAVE_PATH`
