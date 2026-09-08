from __future__ import annotations

from .api import TelegramBotApi
from .class_operations import install_class_operations_product
from .config import load_settings
from .runtime import run_service


def main() -> int:
    settings = load_settings()
    api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
    install_class_operations_product()
    return run_service(settings=settings, api=api, platform_name="Telegram")


if __name__ == "__main__":
    raise SystemExit(main())
