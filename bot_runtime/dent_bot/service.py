from __future__ import annotations

from .api import TelegramBotApi
from .classops_runtime import install_classops_runtime
from .config import load_settings
from .runtime import run_service


def main() -> int:
    settings = load_settings()
    api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
    install_classops_runtime()
    return run_service(settings=settings, api=api, platform_name="Telegram")


if __name__ == "__main__":
    raise SystemExit(main())
